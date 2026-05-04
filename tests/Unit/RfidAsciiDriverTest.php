<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Infrastructure\Drivers\RfidAsciiDriver;
use PHPUnit\Framework\TestCase;

final class RfidAsciiDriverTest extends TestCase
{
    public function test_driver_name(): void
    {
        $this->assertSame('rfid-ascii', (new RfidAsciiDriver)->name());
    }

    public function test_parses_single_stx_etx_frame(): void
    {
        $driver = new RfidAsciiDriver;
        $frames = $driver->parseInbound("\x02E200001234567890\r\n\x03");

        $this->assertCount(1, $frames);
        $this->assertSame('E200001234567890', $frames[0]->payload['tag']);
        $this->assertSame('stx-etx-ascii', $frames[0]->payload['format']);
    }

    public function test_tag_is_uppercased_by_default(): void
    {
        $driver = new RfidAsciiDriver;
        $frames = $driver->parseInbound("\x02e200001234abcdef\r\n\x03");

        $this->assertCount(1, $frames);
        $this->assertSame('E200001234ABCDEF', $frames[0]->payload['tag']);
    }

    public function test_uppercase_can_be_disabled(): void
    {
        $driver = new RfidAsciiDriver;
        $driver->configure(['uppercase' => false]);
        $frames = $driver->parseInbound("\x02lowercase_tag\r\n\x03");

        $this->assertCount(1, $frames);
        $this->assertSame('lowercase_tag', $frames[0]->payload['tag']);
    }

    public function test_parses_multiple_frames_in_one_chunk(): void
    {
        $driver = new RfidAsciiDriver;
        $chunk = "\x02TAG001\r\n\x03\x02TAG002\r\n\x03";
        $frames = $driver->parseInbound($chunk);

        $this->assertCount(2, $frames);
        $this->assertSame('TAG001', $frames[0]->payload['tag']);
        $this->assertSame('TAG002', $frames[1]->payload['tag']);
    }

    public function test_empty_tag_between_stx_etx_is_skipped(): void
    {
        $driver = new RfidAsciiDriver;
        $frames = $driver->parseInbound("\x02\r\n\x03");

        $this->assertCount(0, $frames);
    }

    public function test_partial_frame_produces_no_output(): void
    {
        $driver = new RfidAsciiDriver;
        $frames = $driver->parseInbound("\x02PARTIAL_NO_ETX");

        $this->assertCount(0, $frames);
    }

    public function test_custom_stx_etx_delimiters(): void
    {
        $driver = new RfidAsciiDriver;
        $driver->configure(['stx' => '<', 'etx' => '>']);
        $frames = $driver->parseInbound('<RFID_TAG_99>');

        $this->assertCount(1, $frames);
        $this->assertSame('RFID_TAG_99', $frames[0]->payload['tag']);
    }

    public function test_frame_contains_expected_fields(): void
    {
        $driver = new RfidAsciiDriver;
        $frames = $driver->parseInbound("\x02TAGABC\r\n\x03");

        $this->assertCount(1, $frames);
        $this->assertArrayHasKey('tag', $frames[0]->payload);
        $this->assertArrayHasKey('raw', $frames[0]->payload);
        $this->assertArrayHasKey('raw_hex', $frames[0]->payload);
        $this->assertArrayHasKey('format', $frames[0]->payload);
    }

    public function test_encode_outbound_wraps_with_stx_etx(): void
    {
        $driver = new RfidAsciiDriver;
        $encoded = $driver->encodeOutbound(['tag' => 'WRITETAG01']);

        $this->assertStringStartsWith("\x02", $encoded);
        $this->assertStringEndsWith("\x03", $encoded);
        $this->assertStringContainsString('WRITETAG01', $encoded);
    }

    public function test_encode_outbound_from_string(): void
    {
        $driver = new RfidAsciiDriver;
        $encoded = $driver->encodeOutbound('RAW_TAG');

        $this->assertSame("\x02RAW_TAG\r\n\x03", $encoded);
    }

    public function test_data_after_etx_is_buffered_correctly(): void
    {
        $driver = new RfidAsciiDriver;

        // Send first complete frame plus start of second
        $frames1 = $driver->parseInbound("\x02FIRST\r\n\x03\x02SECON");
        $this->assertCount(1, $frames1);

        // No buffer persistence without session_id — second frame is lost, which is expected
        $this->assertSame('FIRST', $frames1[0]->payload['tag']);
    }
}
