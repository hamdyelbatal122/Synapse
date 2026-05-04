<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Feature;

use Hamzi\PortFlow\Tests\TestCase;

final class IngestEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // TrimStrings and ConvertEmptyStringsToNull would corrupt binary
        // delimiters (\n, \r\n) in serial chunk data.
        $this->withoutMiddleware([
            \Illuminate\Foundation\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);
    }

    public function test_valid_json_ingest_returns_ok(): void
    {
        $response = $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk'  => '{"type":"barcode.scan","barcode":"123456"}'."\n",
        ]);

        $response->assertOk()
            ->assertJsonStructure(['ok', 'driver', 'frames_processed'])
            ->assertJson(['ok' => true, 'driver' => 'raw-json', 'frames_processed' => 1]);
    }

    public function test_missing_chunk_returns_422(): void
    {
        $this->postJson(route('portflow.ingest'), ['driver' => 'raw-json'])
            ->assertUnprocessable();
    }

    public function test_oversized_chunk_returns_422(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk'  => str_repeat('x', 16385),
        ])->assertUnprocessable();
    }

    public function test_unknown_driver_returns_server_error(): void
    {
        $this->withExceptionHandling();

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'unknown-driver',
            'chunk'  => 'test',
        ])->assertServerError();
    }

    public function test_default_driver_used_when_driver_not_specified(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'chunk' => '{"event":"test"}'."\n",
        ])->assertOk()->assertJson(['ok' => true]);
    }

    public function test_context_is_accepted_and_buffer_persisted(): void
    {
        // First chunk: partial JSON
        $this->postJson(route('portflow.ingest'), [
            'driver'  => 'raw-json',
            'chunk'   => '{"sensor":"temp"',
            'context' => ['session_id' => 'integration-test-session'],
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 0]);

        // Second chunk: completes the JSON — should produce 1 frame thanks to buffer persistence
        $this->postJson(route('portflow.ingest'), [
            'driver'  => 'raw-json',
            'chunk'   => ',"value":22.5}'."\n",
            'context' => ['session_id' => 'integration-test-session'],
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);
    }

    public function test_escpos_driver_parses_barcode(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'driver' => 'escpos',
            'chunk'  => "4901234567890\n",
        ])->assertOk()->assertJson(['ok' => true, 'driver' => 'escpos', 'frames_processed' => 1]);
    }

    public function test_rs232_driver_parses_weight(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'driver' => 'rs232',
            'chunk'  => "1.250 kg\n",
        ])->assertOk()->assertJson(['ok' => true, 'driver' => 'rs232', 'frames_processed' => 1]);
    }
}
