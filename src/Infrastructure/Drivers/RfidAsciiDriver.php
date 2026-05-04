<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Drivers;

use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Illuminate\Support\Facades\Cache;

final class RfidAsciiDriver implements SerialDriver
{
    private string $stx = "\x02";

    private string $etx = "\x03";

    private bool $uppercase = true;

    public function name(): string
    {
        return 'rfid-ascii';
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function configure(array $options = []): void
    {
        $this->stx = isset($options['stx']) && is_string($options['stx']) && $options['stx'] !== ''
            ? $options['stx']
            : "\x02";
        $this->etx = isset($options['etx']) && is_string($options['etx']) && $options['etx'] !== ''
            ? $options['etx']
            : "\x03";
        $this->uppercase = (bool) ($options['uppercase'] ?? true);
    }

    /**
     * @param  array<int|string, mixed>|string  $payload
     */
    public function encodeOutbound(array|string $payload): string
    {
        if (is_array($payload)) {
            $payload = (string) ($payload['tag'] ?? $payload['raw'] ?? '');
        }

        return $this->stx.$payload."\r\n".$this->etx;
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
            $start = strpos($buffer, $this->stx);
            if ($start === false) {
                $buffer = '';
                break;
            }

            $end = strpos($buffer, $this->etx, $start + strlen($this->stx));
            if ($end === false) {
                $buffer = substr($buffer, $start);
                break;
            }

            $rawFrame = substr($buffer, $start, ($end - $start) + strlen($this->etx));
            $rawPayload = substr($buffer, $start + strlen($this->stx), $end - ($start + strlen($this->stx)));
            $buffer = substr($buffer, $end + strlen($this->etx));

            $tag = trim(str_replace(["\r", "\n"], '', $rawPayload));
            if ($this->uppercase) {
                $tag = strtoupper($tag);
            }

            if ($tag === '') {
                continue;
            }

            $frames[] = SerialFrame::now($this->name(), [
                'tag' => $tag,
                'raw' => $rawPayload,
                'raw_hex' => strtoupper(bin2hex($rawFrame)),
                'format' => 'stx-etx-ascii',
            ], $context);
        }

        $this->storeBuffer($cacheKey, $buffer);

        return $frames;
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

        $cacheKey = 'portflow.rfid.buf.'.hash('sha256', $sessionId);

        return [(string) Cache::get($cacheKey, ''), $cacheKey];
    }

    private function storeBuffer(?string $cacheKey, string $buffer): void
    {
        if ($cacheKey !== null) {
            Cache::put($cacheKey, $buffer, 300);
        }
    }
}
