<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Drivers;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Domain\DTO\SerialFrame;

final class EscPosDriver implements SerialDriver
{
    public function name(): string
    {
        return 'escpos';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        // ESC/POS formatting options are handled in printing service.
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $text = (string) ($payload['text'] ?? '');

            return $text."\n";
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        $barcode = trim($chunk);

        if ($barcode === '') {
            return [];
        }

        return [
            SerialFrame::now($this->name(), [
                'barcode' => $barcode,
                'raw' => $chunk,
            ], $context),
        ];
    }
}
