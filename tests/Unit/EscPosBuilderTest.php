<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Tests\Unit;

use Hamzi\Synapse\Infrastructure\Printing\EscPosBuilder;
use PHPUnit\Framework\TestCase;

final class EscPosBuilderTest extends TestCase
{
    public function test_bytes_start_with_init_sequence(): void
    {
        $builder = new EscPosBuilder;

        $this->assertStringStartsWith("\x1B\x40", $builder->bytes());
    }

    public function test_text_appends_newline(): void
    {
        $bytes = (new EscPosBuilder)->text('Hello')->bytes();

        $this->assertStringContainsString("Hello\n", $bytes);
    }

    public function test_bold_on_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->bold()->bytes();

        $this->assertStringContainsString("\x1B\x45\x01", $bytes);
    }

    public function test_bold_off_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->bold(false)->bytes();

        $this->assertStringContainsString("\x1B\x45\x00", $bytes);
    }

    public function test_align_center_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->align('center')->bytes();

        $this->assertStringContainsString("\x1B\x61\x01", $bytes);
    }

    public function test_align_right_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->align('right')->bytes();

        $this->assertStringContainsString("\x1B\x61\x02", $bytes);
    }

    public function test_align_left_is_default(): void
    {
        $bytes = (new EscPosBuilder)->align('left')->bytes();

        $this->assertStringContainsString("\x1B\x61\x00", $bytes);
    }

    public function test_underline_on_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->underline()->bytes();

        $this->assertStringContainsString("\x1B\x2D\x01", $bytes);
    }

    public function test_full_cut_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->cut()->bytes();

        $this->assertStringContainsString("\x1D\x56\x00", $bytes);
    }

    public function test_partial_cut_emits_correct_bytes(): void
    {
        $bytes = (new EscPosBuilder)->cut(partial: true)->bytes();

        $this->assertStringContainsString("\x1D\x56\x01", $bytes);
    }

    public function test_divider_prints_dashes(): void
    {
        $bytes = (new EscPosBuilder)->divider(10)->bytes();

        $this->assertStringContainsString(str_repeat('-', 10)."\n", $bytes);
    }

    public function test_feed_appends_blank_lines(): void
    {
        $bytes = (new EscPosBuilder)->feed(3)->bytes();

        $this->assertStringContainsString(str_repeat("\n", 3), $bytes);
    }

    public function test_builder_is_fluent(): void
    {
        $builder = new EscPosBuilder;

        $result = $builder->bold()->text('Hello')->bold(false)->cut();

        $this->assertSame($builder, $result);
    }
}
