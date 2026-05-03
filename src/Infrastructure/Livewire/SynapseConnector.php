<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class SynapseConnector extends Component
{
    public bool $connected = false;

    public ?string $portLabel = null;

    public function setConnectionState(bool $state, ?string $label = null): void
    {
        $this->connected = $state;
        $this->portLabel = $label;
    }

    public function render(): View
    {
        return view('synapse::livewire.connector');
    }
}
