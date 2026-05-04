<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers;

use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class FingerprintPacketDriver implements SerialDriver
{
    private string $startCode = "\xEF\x01";

    public function name(): string
    {
        return 'fingerprint-packet';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        if (isset($options['start_code_hex']) && is_string($options['start_code_hex'])) {
            $hex = preg_replace('/[^0-9a-f]/i', '', $options['start_code_hex']);
            if ($hex !== null && strlen($hex) >= 4 && strlen($hex) % 2 === 0) {
                $decoded = hex2bin($hex);
                if ($decoded !== false) {
                    $this->startCode = $decoded;
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_string($payload)) {
            return $payload;
        }

        $packetType = isset($payload['packet_type']) ? (int) $payload['packet_type'] : 0x01;
        $addressHex = strtoupper((string) ($payload['address_hex'] ?? 'FFFFFFFF'));
        $addressHex = str_pad(substr(preg_replace('/[^0-9A-F]/', '', $addressHex) ?? 'FFFFFFFF', 0, 8), 8, 'F');

        $data = '';
        if (isset($payload['data_hex']) && is_string($payload['data_hex'])) {
            $clean = preg_replace('/[^0-9a-f]/i', '', $payload['data_hex']);
            if ($clean !== null && strlen($clean) % 2 === 0) {
                $decoded = hex2bin($clean);
                if ($decoded !== false) {
                    $data = $decoded;
                }
            }
        } elseif (isset($payload['data']) && is_string($payload['data'])) {
            $data = $payload['data'];
        }

        $address = hex2bin($addressHex) ?: "\xFF\xFF\xFF\xFF";
        $length = strlen($data) + 2;
        $header = $this->startCode.$address.chr($packetType & 0xFF).pack('n', $length);

        $checksum = $packetType + (($length >> 8) & 0xFF) + ($length & 0xFF);
        for ($index = 0; $index < strlen($data); $index++) {
            $checksum += ord($data[$index]);
        }

        return $header.$data.pack('n', $checksum & 0xFFFF);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<int, SerialFrame>
     */
    public function parseInbound(string $chunk, array $context = []): array
    {
        [$buffer, $cacheKey] = $this->loadBuffer($context);
        $buffer .= $chunk;

        $frames = [];

        while (true) {
            $start = strpos($buffer, $this->startCode);
            if ($start === false) {
                $buffer = '';
                break;
            }

            if ($start > 0) {
                $buffer = substr($buffer, $start);
            }

            if (strlen($buffer) < 9) {
                break;
            }

            $lengthUnpacked = unpack('n', substr($buffer, 7, 2));
            if ($lengthUnpacked === false) {
                break;
            }
            $length = $lengthUnpacked[1];
            $packetSize = 9 + $length;

            if (strlen($buffer) < $packetSize) {
                break;
            }

            $packet = substr($buffer, 0, $packetSize);
            $buffer = substr($buffer, $packetSize);

            $packetType = ord($packet[6]);
            $addressHex = strtoupper(bin2hex(substr($packet, 2, 4)));
            $payloadLength = (int) max(0, $length - 2);
            $payloadData = substr($packet, 9, $payloadLength);
            $checksumOffset = 9 + $payloadLength;
            $checksumUnpacked = unpack('n', substr($packet, $checksumOffset, 2));
            $receivedChecksum = $checksumUnpacked !== false ? (int) $checksumUnpacked[1] : 0;

            $calculatedChecksum = $packetType + (($length >> 8) & 0xFF) + ($length & 0xFF);
            for ($index = 0; $index < strlen($payloadData); $index++) {
                $calculatedChecksum += ord($payloadData[$index]);
            }
            $calculatedChecksum &= 0xFFFF;

            $payload = [
                'packet_type' => $packetType,
                'packet_type_name' => $this->packetTypeName($packetType),
                'address_hex' => $addressHex,
                'data_hex' => strtoupper(bin2hex($payloadData)),
                'checksum' => $receivedChecksum,
                'checksum_calculated' => $calculatedChecksum,
                'checksum_valid' => $receivedChecksum === $calculatedChecksum,
                'raw_hex' => strtoupper(bin2hex($packet)),
            ];

            if ($packetType === 0x07 && $payloadLength > 0) {
                $payload['ack_code'] = ord($payloadData[0]);
            }

            if (! $payload['checksum_valid']) {
                Log::warning('[PortFlow] FingerprintPacketDriver: checksum mismatch — frame may be corrupted.', [
                    'expected' => $payload['checksum_calculated'],
                    'received' => $payload['checksum'],
                    'raw_hex' => $payload['raw_hex'],
                ]);
            }

            $frames[] = SerialFrame::now($this->name(), $payload, $context);
        }

        $this->storeBuffer($cacheKey, $buffer);

        return $frames;
    }

    private function packetTypeName(int $packetType): string
    {
        return match ($packetType) {
            0x01 => 'command',
            0x02 => 'data',
            0x07 => 'ack',
            0x08 => 'end-data',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: ?string}
     */
    private function loadBuffer(array $context): array
    {
        $sessionId = isset($context['session_id']) ? (string) $context['session_id'] : null;
        if ($sessionId === null || $sessionId === '') {
            return ['', null];
        }

        $cacheKey = 'portflow.fingerprint.buf.'.hash('sha256', $sessionId);
        $state = (string) Cache::get($cacheKey, '');

        // Binary packet data is stored as base64 to survive cache serialisation
        // round-trips (e.g. file/database cache drivers that handle only UTF-8 strings).
        $buffer = $state !== '' ? (base64_decode($state, true) ?: '') : '';

        return [$buffer, $cacheKey];
    }

    private function storeBuffer(?string $cacheKey, string $buffer): void
    {
        if ($cacheKey !== null) {
            // base64_encode ensures binary-safe storage across all cache drivers.
            Cache::put($cacheKey, base64_encode($buffer), 300);
        }
    }
}
