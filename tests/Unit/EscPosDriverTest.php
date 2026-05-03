<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Tests\Unit;

use Hamzi\Synapse\Infrastructure\Drivers\EscPosDriver;
use PHPUnit\Framework\TestCase;

final class EscPosDriverTest extends TestCase
{
    public function test_name_is_escpos(): void
    {
        $this->assertSame('escpos', (new EscPosDriver)->name());
    }

    public function test_parses_barcode_input(): void
    {
        $frames = (new EscPosDriver)->parseInbound("12345\n");

        $this->assertCount(1, $frames);
        $this->assertSame('12345', $frames[0]->payload['barcode']);
    }

    public function test_empty_input_returns_no_frames(): void
    {
        $frames = (new EscPosDriver)->parseInbound('   ');

        $this->assertSame([], $frames);
    }

    public function test_encodes_array_with_text_key(): void
    {
        $result = (new EscPosDriver)->encodeOutbound(['text' => 'Hello, World!']);

        $this->assertSame("Hello, World!\n", $result);
    }

    public function test_encodes_string_as_is(): void
    {
        $result = (new EscPosDriver)->encodeOutbound('RAW');

        $this->assertSame('RAW', $result);
    }
}
