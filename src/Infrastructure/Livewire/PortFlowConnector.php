<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

final class PortFlowConnector extends Component
{
    public bool $connected = false;

    public ?string $portLabel = null;

    public int $baudRate;

    public string $driver;

    public bool $autoConnectOnLoad;

    /**
     * @var array<string, mixed>
     */
    public array $context = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $filters = [];

    public string $browserChunkEvent;

    public string $livewireChunkEvent;

    public string $livewireStatusEvent;

    public string $livewireErrorEvent;

    public bool $rememberBaudRate;

    public bool $baudRateExplicit = false;

    /**
     * @param  array<string, mixed>  $context
     * @param  array<int, array<string, mixed>>  $filters
     */
    public function mount(
        int|string|null $baudRate = null,
        ?string $driver = null,
        ?bool $autoConnectOnLoad = null,
        array $context = [],
        array $filters = [],
        ?string $browserChunkEvent = null,
        ?string $livewireChunkEvent = null,
        ?string $livewireStatusEvent = null,
        ?string $livewireErrorEvent = null,
        ?bool $rememberBaudRate = null,
    ): void {
        $this->baudRateExplicit = $baudRate !== null;
        $this->baudRate = is_numeric($baudRate)
            ? (int) $baudRate
            : (int) config('portflow.serial.baud_rate', 9600);
        $this->driver = $driver ?? (string) config('portflow.default_driver', 'raw-json');
        $this->autoConnectOnLoad = $autoConnectOnLoad ?? (bool) config('portflow.serial.auto_connect_on_load', true);
        $this->context = $context;
        $this->filters = $filters !== [] ? $filters : (array) config('portflow.serial.port_filters', []);
        $this->browserChunkEvent = $browserChunkEvent ?? (string) config('portflow.serial.browser_chunk_event', 'portflow-frame-received');
        $this->livewireChunkEvent = $livewireChunkEvent ?? (string) config('portflow.serial.livewire_chunk_event', 'portflow-frame-received');
        $this->livewireStatusEvent = $livewireStatusEvent ?? (string) config('portflow.serial.livewire_status_event', 'portflow-status-updated');
        $this->livewireErrorEvent = $livewireErrorEvent ?? (string) config('portflow.serial.livewire_error_event', 'portflow-error');
        $this->rememberBaudRate = $rememberBaudRate ?? (bool) config('portflow.serial.remember_baud_rate', true);
    }

    public function setConnectionState(bool $state, ?string $label = null): void
    {
        $this->connected = $state;
        $this->portLabel = $label;
    }

    public function render(): View
    {
        return view('portflow::livewire.connector', [
            'portflowConfig' => [
                'ingestUrl' => Route::has('portflow.ingest') ? route('portflow.ingest') : '/portflow/ingest',
                'driver' => $this->driver,
                'baudRate' => $this->baudRate,
                'baudRateExplicit' => $this->baudRateExplicit,
                'rememberBaudRate' => $this->rememberBaudRate,
                'autoConnectOnLoad' => $this->autoConnectOnLoad,
                'context' => $this->context,
                'filters' => $this->filters,
                'browserChunkEvent' => $this->browserChunkEvent,
                'livewireChunkEvent' => $this->livewireChunkEvent,
                'livewireStatusEvent' => $this->livewireStatusEvent,
                'livewireErrorEvent' => $this->livewireErrorEvent,
            ],
        ]);
    }
}
