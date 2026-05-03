<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Domain\DTO;

use DateTimeImmutable;

final class SerialFrame
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $driver,
        public readonly array $payload,
        public readonly DateTimeImmutable $receivedAt,
        public readonly array $context = [],
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public static function now(string $driver, array $payload, array $context = []): self
    {
        return new self(
            driver: $driver,
            payload: $payload,
            receivedAt: new DateTimeImmutable,
            context: $context,
        );
    }
}
