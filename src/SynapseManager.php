<?php

declare(strict_types=1);

namespace Hamzi\Synapse;

use Hamzi\Synapse\Application\Services\DriverRegistry;
use Hamzi\Synapse\Application\Services\HardwareMessageService;
use Hamzi\Synapse\Domain\DTO\SerialFrame;
use Hamzi\Synapse\Infrastructure\Printing\BladeEscPosRenderer;

final class SynapseManager
{
    public function __construct(
        private readonly DriverRegistry $registry,
        private readonly HardwareMessageService $messages,
    ) {}

    /** Register or override a driver class for the given name. */
    public function registerDriver(string $name, string $driverClass): void
    {
        $this->registry->register($name, $driverClass);
    }

    /**
     * Parse an inbound chunk through the named driver and route resulting frames.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function ingest(string $driver, string $chunk, array $context = []): array
    {
        return $this->messages->ingest($driver, $chunk, $context);
    }

    /**
     * Encode an outbound payload through the named driver.
     *
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encode(string $driver, array|string $payload): string
    {
        return $this->messages->encode($driver, $payload);
    }

    /**
     * Render a Blade view to ESC/POS bytes for thermal printing.
     *
     * @param  array<string, mixed>  $data
     */
    public function print(string $view, array $data = []): string
    {
        /** @var BladeEscPosRenderer $renderer */
        $renderer = app(BladeEscPosRenderer::class);

        return $renderer->render($view, $data);
    }

    /**
     * Return a health-check array with registered driver names.
     *
     * @return array{default_driver: string, registered_drivers: list<string>}
     */
    public function health(): array
    {
        return [
            'default_driver' => (string) config('synapse.default_driver'),
            'registered_drivers' => array_keys($this->registry->all()),
        ];
    }
}
