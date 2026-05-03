<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Application\Services;

use Hamzi\Synapse\Domain\DTO\SerialFrame;

final class HardwareMessageService
{
    public function __construct(
        private readonly DriverRegistry $drivers,
        private readonly MessageRouter $router,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function ingest(string $driverName, string $rawChunk, array $context = []): array
    {
        $driver = $this->drivers->resolve($driverName);
        $frames = $driver->parseInbound($rawChunk, $context);

        foreach ($frames as $frame) {
            $this->router->route($frame);
        }

        return $frames;
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encode(string $driverName, array|string $payload): string
    {
        return $this->drivers->resolve($driverName)->encodeOutbound($payload);
    }
}
