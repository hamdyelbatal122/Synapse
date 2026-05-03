<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Tests\Unit;

use Hamzi\Synapse\Domain\Services\IoTFrameBuffer;
use Hamzi\Synapse\Tests\TestCase;

final class IoTFrameBufferTest extends TestCase
{
    public function test_it_emits_frames_when_delimiter_is_found(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $frames = $buffer->push("a\nb\n");

        $this->assertSame(['a', 'b'], $frames);
    }

    public function test_it_keeps_partial_payload_until_completed(): void
    {
        $buffer = new IoTFrameBuffer("\n");

        $first = $buffer->push('abc');
        $second = $buffer->push("123\n");

        $this->assertSame([], $first);
        $this->assertSame(['abc123'], $second);
    }
}
