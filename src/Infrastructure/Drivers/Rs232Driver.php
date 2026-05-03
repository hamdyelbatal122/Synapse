<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Drivers;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Domain\DTO\SerialFrame;

final class Rs232Driver implements SerialDriver
{
    private string $delimiter = "\n";

    public function name(): string
    {
        return 'rs232';
    }

    public function configure(array $options = []): void
    {
        $this->delimiter = (string) ($options['delimiter'] ?? "\n");
    }

    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $payload = implode(',', array_map(static fn (mixed $value): string => (string) $value, $payload));
        }

        return $payload.$this->delimiter;
    }

    public function parseInbound(string $chunk, array $context = []): array
    {
        $records = array_filter(array_map('trim', explode($this->delimiter, $chunk)));

        $frames = [];
        foreach ($records as $record) {
            $segments = array_map('trim', explode(';', $record));
            $payload = [
                'raw' => $record,
                'segments' => $segments,
                'weight' => $segments[0] ?? null,
            ];

            $frames[] = SerialFrame::now($this->name(), $payload, $context);
        }

        return $frames;
    }
}
