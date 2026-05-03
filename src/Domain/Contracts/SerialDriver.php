<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Domain\Contracts;

use Hamzi\Synapse\Domain\DTO\SerialFrame;

interface SerialDriver
{
    public function name(): string;

    public function configure(array $options = []): void;

    /**
     * Convert normalized outbound payload into device-level bytes.
     */
    public function encodeOutbound(array|string $payload): string;

    /**
     * Parse raw inbound chunk(s) into normalized frames.
     *
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array;
}
