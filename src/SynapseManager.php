<?php

declare(strict_types=1);

namespace Hamzi\Synapse;

use Hamzi\Synapse\Application\Services\DriverRegistry;
use Hamzi\Synapse\Application\Services\HardwareMessageService;

final class SynapseManager
{
    public function __construct(
        private readonly DriverRegistry $registry,
        private readonly HardwareMessageService $messages,
    ) {}

    public function registerDriver(string $name, string $driverClass): void
    {
        $this->registry->register($name, $driverClass);
    }

    public function ingest(string $driver, string $chunk, array $context = []): array
    {
        return $this->messages->ingest($driver, $chunk, $context);
    }

    public function encode(string $driver, array|string $payload): string
    {
        return $this->messages->encode($driver, $payload);
    }

    public function health(): array
    {
        return [
            'default_driver' => config('synapse.default_driver'),
            'registered_drivers' => array_keys($this->registry->all()),
        ];
    }
}
