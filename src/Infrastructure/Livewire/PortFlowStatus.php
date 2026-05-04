<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class PortFlowStatus extends Component
{
    public bool $connected = false;

    public string $driver = 'raw-json';

    public int $baudRate = 9600;

    public int $frames = 0;

    public int $retryCount = 0;

    public int $framesProcessed = 0;

    public ?string $lastChunk = null;

    public ?string $lastError = null;

    #[On('portflow-status-updated')]
    public function updateStatus(
        bool $connected = false,
        string $driver = 'raw-json',
        int $baudRate = 9600,
        int $frames = 0,
        int $retryCount = 0,
    ): void {
        $this->connected = $connected;
        $this->driver = $driver;
        $this->baudRate = $baudRate;
        $this->frames = $frames;
        $this->retryCount = $retryCount;
    }

    #[On('portflow-frame-received')]
    public function ingestFrame(string $chunk = '', int $framesProcessed = 0): void
    {
        $this->lastChunk = $chunk;
        $this->framesProcessed = $framesProcessed;
        $this->lastError = null;
    }

    #[On('portflow-error')]
    public function reportError(string $message = ''): void
    {
        $this->lastError = $message !== '' ? $message : null;
    }

    public function render(): View
    {
        return view('portflow::livewire.status');
    }
}
