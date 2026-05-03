<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Printing;

final class EscPosBuilder
{
    private string $buffer = "\x1B\x40";

    public function text(string $value): self
    {
        $this->buffer .= $value."\n";

        return $this;
    }

    public function feed(int $lines = 1): self
    {
        $this->buffer .= str_repeat("\n", max(1, $lines));

        return $this;
    }

    public function cut(bool $partial = false): self
    {
        $this->buffer .= $partial ? "\x1D\x56\x01" : "\x1D\x56\x00";

        return $this;
    }

    public function bytes(): string
    {
        return $this->buffer;
    }
}
