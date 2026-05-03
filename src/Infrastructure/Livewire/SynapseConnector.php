<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Livewire;

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

    public function render()
    {
        return view('synapse::livewire.connector');
    }
}
