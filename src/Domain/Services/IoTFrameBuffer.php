<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Domain\Services;

final class IoTFrameBuffer
{
    private string $buffer = '';

    public function __construct(
        private readonly string $delimiter = "\n",
        private readonly int $maxBytes = 16384,
    ) {}

    /**
     * @return array<int, string>
     */
    public function push(string $chunk): array
    {
        $this->buffer .= $chunk;

        if (strlen($this->buffer) > $this->maxBytes) {
            $this->buffer = substr($this->buffer, -$this->maxBytes);
        }

        $frames = [];

        while (($index = strpos($this->buffer, $this->delimiter)) !== false) {
            $frame = trim(substr($this->buffer, 0, $index));
            $this->buffer = substr($this->buffer, $index + strlen($this->delimiter));

            if ($frame !== '') {
                $frames[] = $frame;
            }
        }

        return $frames;
    }

    public function flushRemainder(): ?string
    {
        $remainder = trim($this->buffer);
        $this->buffer = '';

        return $remainder === '' ? null : $remainder;
    }

    /** Return the raw pending bytes (used for cache persistence). */
    public function getState(): string
    {
        return $this->buffer;
    }

    /** Restore pending bytes from a previously persisted state. */
    public function setState(string $state): void
    {
        $this->buffer = $state;
    }
}
