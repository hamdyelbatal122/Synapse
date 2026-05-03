<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

final class SynapseStatus extends Component
{
    public bool $connected = false;

    public string $driver = 'raw-json';

    public int $frames = 0;

    /**
     * @param  array<string, mixed>  $payload
     */
    #[On('synapse-status-updated')]
    public function updateStatus(array $payload): void
    {
        $this->connected = (bool) ($payload['connected'] ?? false);
        $this->driver = (string) ($payload['driver'] ?? 'raw-json');
        $this->frames = (int) ($payload['frames'] ?? 0);
    }

    public function render(): View
    {
        return view('synapse::livewire.status');
    }
}
