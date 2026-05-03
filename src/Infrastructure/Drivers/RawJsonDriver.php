<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Drivers;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Domain\DTO\SerialFrame;
use Hamzi\Synapse\Domain\Services\IoTFrameBuffer;
use JsonException;

final class RawJsonDriver implements SerialDriver
{
    private IoTFrameBuffer $buffer;

    public function __construct()
    {
        $this->buffer = new IoTFrameBuffer;
    }

    public function name(): string
    {
        return 'raw-json';
    }

    public function configure(array $options = []): void
    {
        $delimiter = (string) ($options['delimiter'] ?? "\n");
        $maxBytes = (int) ($options['max_bytes'] ?? 16384);

        $this->buffer = new IoTFrameBuffer($delimiter, $maxBytes);
    }

    public function encodeOutbound(array|string $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        return json_encode($payload, JSON_THROW_ON_ERROR)."\n";
    }

    public function parseInbound(string $chunk, array $context = []): array
    {
        $frames = [];

        foreach ($this->buffer->push($chunk) as $packet) {
            try {
                $decoded = json_decode($packet, true, 512, JSON_THROW_ON_ERROR);
                if (! is_array($decoded)) {
                    $decoded = ['raw' => $packet];
                }
            } catch (JsonException) {
                $decoded = ['raw' => $packet];
            }

            $frames[] = SerialFrame::now($this->name(), $decoded, $context);
        }

        return $frames;
    }
}
