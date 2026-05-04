<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Infrastructure\Drivers\BarcodeLineDriver;
use PHPUnit\Framework\TestCase;

final class BarcodeLineDriverTest extends TestCase
{
    public function test_driver_name(): void
    {
        $this->assertSame('barcode-line', (new BarcodeLineDriver)->name());
    }

    public function test_parses_single_barcode(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("1234567890\n");

        $this->assertCount(1, $frames);
        $this->assertSame('1234567890', $frames[0]->payload['barcode']);
        $this->assertSame(10, $frames[0]->payload['length']);
    }

    public function test_parses_multiple_barcodes_in_single_chunk(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("ABC123\nDEF456\nGHI789\n");

        $this->assertCount(3, $frames);
        $this->assertSame('ABC123', $frames[0]->payload['barcode']);
        $this->assertSame('DEF456', $frames[1]->payload['barcode']);
        $this->assertSame('GHI789', $frames[2]->payload['barcode']);
    }

    public function test_strips_carriage_return_suffix_by_default(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("BARCODE123\r\n");

        $this->assertCount(1, $frames);
        $this->assertSame('BARCODE123', $frames[0]->payload['barcode']);
    }

    public function test_empty_lines_are_skipped(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("\n\n\n");

        $this->assertCount(0, $frames);
    }

    public function test_whitespace_only_lines_are_skipped(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("   \n");

        $this->assertCount(0, $frames);
    }

    public function test_custom_delimiter(): void
    {
        $driver = new BarcodeLineDriver;
        $driver->configure(['delimiter' => '|']);
        $frames = $driver->parseInbound('SCAN1|SCAN2|SCAN3|');

        $this->assertCount(3, $frames);
        $this->assertSame('SCAN1', $frames[0]->payload['barcode']);
    }

    public function test_custom_strip_prefix(): void
    {
        $driver = new BarcodeLineDriver;
        $driver->configure(['strip_prefix' => ['STX']]);
        $frames = $driver->parseInbound("STXABC999\n");

        $this->assertCount(1, $frames);
        $this->assertSame('ABC999', $frames[0]->payload['barcode']);
    }

    public function test_encode_outbound_appends_delimiter(): void
    {
        $driver = new BarcodeLineDriver;
        $encoded = $driver->encodeOutbound(['barcode' => 'QR123456']);

        $this->assertSame("QR123456\n", $encoded);
    }

    public function test_encode_outbound_from_string(): void
    {
        $driver = new BarcodeLineDriver;
        $encoded = $driver->encodeOutbound('RAW_BARCODE');

        $this->assertSame("RAW_BARCODE\n", $encoded);
    }

    public function test_partial_chunk_produces_no_frames_without_delimiter(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound('INCOMPLETE_BARCODE');

        $this->assertCount(0, $frames);
    }

    public function test_frame_contains_raw_and_barcode_fields(): void
    {
        $driver = new BarcodeLineDriver;
        $frames = $driver->parseInbound("TEST\r\n");

        $this->assertCount(1, $frames);
        $this->assertArrayHasKey('barcode', $frames[0]->payload);
        $this->assertArrayHasKey('raw', $frames[0]->payload);
        $this->assertArrayHasKey('length', $frames[0]->payload);
    }
}
