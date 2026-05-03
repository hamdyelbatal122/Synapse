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

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        $delimiter = (string) ($options['delimiter'] ?? "\n");
        $this->delimiter = $delimiter !== '' ? $delimiter : "\n";
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $payload = implode(',', array_map(static fn (mixed $value): string => (string) $value, $payload));
        }

        return $payload.$this->delimiter;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        $delimiter = $this->delimiter !== '' ? $this->delimiter : "\n";
        $records = array_filter(array_map('trim', explode($delimiter, $chunk)));

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
