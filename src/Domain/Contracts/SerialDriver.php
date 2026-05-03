<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Domain\Contracts;

use Hamzi\Synapse\Domain\DTO\SerialFrame;

interface SerialDriver
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void;

    /**
     * Convert normalized outbound payload into device-level bytes.
     *
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string;

    /**
     * Parse raw inbound chunk(s) into normalized frames.
     *
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array;
}
