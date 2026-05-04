<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Unit;

use Hamzi\PortFlow\Infrastructure\Drivers\FingerprintPacketDriver;
use Hamzi\PortFlow\Tests\TestCase;

final class FingerprintPacketDriverTest extends TestCase
{
    /**
     * Build a valid fingerprint packet with the standard EF01 start code.
     *
     * Packet structure:
     *   [start_code(2)] [address(4)] [packet_type(1)] [length(2)] [data(n)] [checksum(2)]
     * length = len(data) + 2
     * checksum = packet_type + (length >> 8) + (length & 0xFF) + sum(data_bytes)
     *
     * @param  string  $data  raw bytes
     */
    private function buildPacket(int $packetType = 0x01, string $data = ''): string
    {
        $startCode = "\xEF\x01";
        $address = "\xFF\xFF\xFF\xFF";
        $length = strlen($data) + 2;
        $header = $startCode.$address.chr($packetType & 0xFF).pack('n', $length);

        $checksum = $packetType + (($length >> 8) & 0xFF) + ($length & 0xFF);
        for ($i = 0; $i < strlen($data); $i++) {
            $checksum += ord($data[$i]);
        }

        return $header.$data.pack('n', $checksum & 0xFFFF);
    }

    public function test_driver_name(): void
    {
        $this->assertSame('fingerprint-packet', (new FingerprintPacketDriver)->name());
    }

    public function test_parses_single_command_packet(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x01, "\x01\x00\x03");
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertSame(0x01, $frames[0]->payload['packet_type']);
        $this->assertSame('command', $frames[0]->payload['packet_type_name']);
        $this->assertTrue($frames[0]->payload['checksum_valid']);
    }

    public function test_parses_ack_packet_with_ack_code(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x07, "\x00"); // ack_code = 0x00 (success)
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertSame(0x07, $frames[0]->payload['packet_type']);
        $this->assertSame('ack', $frames[0]->payload['packet_type_name']);
        $this->assertSame(0x00, $frames[0]->payload['ack_code']);
    }

    public function test_parses_multiple_packets_in_single_chunk(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet1 = $this->buildPacket(0x01, "\x01\x03\x05");
        $packet2 = $this->buildPacket(0x07, "\x00");
        $frames = $driver->parseInbound($packet1.$packet2);

        $this->assertCount(2, $frames);
        $this->assertSame('command', $frames[0]->payload['packet_type_name']);
        $this->assertSame('ack', $frames[1]->payload['packet_type_name']);
    }

    public function test_checksum_valid_flag_is_true_for_correct_packet(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x01, "\xAB\xCD");
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertTrue($frames[0]->payload['checksum_valid']);
        $this->assertSame($frames[0]->payload['checksum'], $frames[0]->payload['checksum_calculated']);
    }

    public function test_corrupted_packet_has_checksum_invalid(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x01, "\x01\x02");
        // Corrupt last two bytes (checksum field)
        $packet = substr($packet, 0, -2)."\xFF\xFF";
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertFalse($frames[0]->payload['checksum_valid']);
    }

    public function test_incomplete_packet_produces_no_frames(): void
    {
        $driver = new FingerprintPacketDriver;
        // Only start code + address, no further data
        $frames = $driver->parseInbound("\xEF\x01\xFF\xFF\xFF\xFF");

        $this->assertCount(0, $frames);
    }

    public function test_garbage_before_start_code_is_discarded(): void
    {
        $driver = new FingerprintPacketDriver;
        $garbage = 'GARBAGE_BYTES_HERE';
        $packet = $this->buildPacket(0x07, "\x00");
        $frames = $driver->parseInbound($garbage.$packet);

        $this->assertCount(1, $frames);
        $this->assertSame('ack', $frames[0]->payload['packet_type_name']);
    }

    public function test_data_packet_type_name(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x02, "\xDE\xAD");
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertSame('data', $frames[0]->payload['packet_type_name']);
    }

    public function test_end_data_packet_type_name(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x08, "\xBE\xEF");
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertSame('end-data', $frames[0]->payload['packet_type_name']);
    }

    public function test_unknown_packet_type_name(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x99);
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $this->assertSame('unknown', $frames[0]->payload['packet_type_name']);
    }

    public function test_encode_outbound_builds_valid_packet(): void
    {
        $driver = new FingerprintPacketDriver;
        $encoded = $driver->encodeOutbound([
            'packet_type' => 0x01,
            'data_hex' => '010003',
        ]);

        // Must start with EF01
        $this->assertStringStartsWith("\xEF\x01", $encoded);
        // Must be at least 9 bytes (header) + data + checksum
        $this->assertGreaterThanOrEqual(9 + 3 + 2, strlen($encoded));
    }

    public function test_encode_outbound_passthrough_for_string_payload(): void
    {
        $driver = new FingerprintPacketDriver;
        $raw = "\xEF\x01\xFF\xFF\xFF\xFF\x01\x00\x03\x00\x04";
        $encoded = $driver->encodeOutbound($raw);

        $this->assertSame($raw, $encoded);
    }

    public function test_frame_contains_required_fields(): void
    {
        $driver = new FingerprintPacketDriver;
        $packet = $this->buildPacket(0x01);
        $frames = $driver->parseInbound($packet);

        $this->assertCount(1, $frames);
        $payload = $frames[0]->payload;

        $this->assertArrayHasKey('packet_type', $payload);
        $this->assertArrayHasKey('packet_type_name', $payload);
        $this->assertArrayHasKey('address_hex', $payload);
        $this->assertArrayHasKey('data_hex', $payload);
        $this->assertArrayHasKey('checksum', $payload);
        $this->assertArrayHasKey('checksum_calculated', $payload);
        $this->assertArrayHasKey('checksum_valid', $payload);
        $this->assertArrayHasKey('raw_hex', $payload);
    }

    public function test_custom_start_code_hex(): void
    {
        $driver = new FingerprintPacketDriver;
        $driver->configure(['start_code_hex' => 'AA55']);

        // Build a packet with custom start code AA55
        $startCode = "\xAA\x55";
        $address = "\xFF\xFF\xFF\xFF";
        $packetType = 0x01;
        $data = '';
        $length = 2; // empty data + 2
        $header = $startCode.$address.chr($packetType).pack('n', $length);
        $checksum = $packetType + (($length >> 8) & 0xFF) + ($length & 0xFF);
        $packet = $header.pack('n', $checksum & 0xFFFF);

        $frames = $driver->parseInbound($packet);
        $this->assertCount(1, $frames);
    }
}
