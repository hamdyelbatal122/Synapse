<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Tests\Unit;

use Hamzi\Synapse\Infrastructure\Drivers\RawJsonDriver;
use PHPUnit\Framework\TestCase;

final class RawJsonDriverTest extends TestCase
{
    public function test_it_parses_json_packets(): void
    {
        $driver = new RawJsonDriver;

        $frames = $driver->parseInbound("{\"type\":\"barcode.scan\",\"barcode\":\"123\"}\n");

        $this->assertCount(1, $frames);
        $this->assertSame('123', $frames[0]->payload['barcode']);
    }

    public function test_it_falls_back_to_raw_payload_when_invalid_json(): void
    {
        $driver = new RawJsonDriver;

        $frames = $driver->parseInbound("not-json\n");

        $this->assertCount(1, $frames);
        $this->assertSame('not-json', $frames[0]->payload['raw']);
    }
}
