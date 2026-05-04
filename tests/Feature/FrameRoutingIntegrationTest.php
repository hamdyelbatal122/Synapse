<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Tests\Feature;

use Hamzi\PortFlow\Application\Jobs\RouteSerialFrameJob;
use Hamzi\PortFlow\Application\Services\MessageRouter;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;
use Hamzi\PortFlow\Domain\Events\ProductScanned;
use Hamzi\PortFlow\Facades\PortFlow;
use Hamzi\PortFlow\Tests\TestCase;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

final class FrameRoutingIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);
    }

    // -----------------------------------------------------------------------
    // Ingest → Event dispatch
    // -----------------------------------------------------------------------

    public function test_raw_json_barcode_scan_dispatches_product_scanned_event(): void
    {
        Event::fake([ProductScanned::class]);

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '{"type":"barcode.scan","barcode":"9780201379624"}'."\n",
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);

        Event::assertDispatched(ProductScanned::class, function (ProductScanned $event): bool {
            return $event->barcode === '9780201379624';
        });
    }

    public function test_barcode_line_driver_dispatches_product_scanned_event(): void
    {
        Event::fake([ProductScanned::class]);

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'barcode-line',
            'chunk' => "0012345678905\r\n",
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);

        Event::assertDispatched(ProductScanned::class, function (ProductScanned $event): bool {
            return $event->barcode === '0012345678905';
        });
    }

    public function test_rfid_ascii_driver_dispatches_product_scanned_event(): void
    {
        Event::fake([ProductScanned::class]);

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'rfid-ascii',
            'chunk' => "\x02E200001234567890\r\n\x03",
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);

        Event::assertDispatched(ProductScanned::class, function (ProductScanned $event): bool {
            return $event->barcode === 'E200001234567890';
        });
    }

    public function test_escpos_driver_dispatches_product_scanned_event(): void
    {
        Event::fake([ProductScanned::class]);

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'escpos',
            'chunk' => "4901234567890\n",
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);

        Event::assertDispatched(ProductScanned::class);
    }

    // -----------------------------------------------------------------------
    // MessageRouter: SerialEvent interface enforcement
    // -----------------------------------------------------------------------

    public function test_non_serial_event_class_is_not_dispatched(): void
    {
        Event::fake([ProductScanned::class]);

        /** @var MessageRouter $router */
        $router = app(MessageRouter::class);

        $frame = SerialFrame::now('raw-json', [
            'type' => 'barcode.scan',
            'barcode' => 'FAKE123',
        ]);

        // Manually route with a mapping pointing to a non-SerialEvent class
        // We test via reflection — the internal log.error path is exercised
        // and no actual event dispatch happens.
        config(['portflow.mappings' => [
            [
                'driver' => 'raw-json',
                'payload_field' => 'type',
                'equals' => 'barcode.scan',
                'event' => \stdClass::class,  // does NOT implement SerialEvent
                'event_payload_field' => 'barcode',
            ],
        ]]);

        $router->routeSync($frame);

        Event::assertNotDispatched(ProductScanned::class);
    }

    // -----------------------------------------------------------------------
    // Buffer persistence (streaming frames split across requests)
    // -----------------------------------------------------------------------

    public function test_buffer_persistence_across_two_requests(): void
    {
        // First half of JSON — no frame yet
        $res1 = $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '{"type":"barcode.scan","barcode":"STREAM',
            'context' => ['session_id' => 'test-stream-session-001'],
        ]);

        $res1->assertOk()->assertJson(['ok' => true, 'frames_processed' => 0]);

        // Second half completes the JSON
        $res2 = $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '_BARCODE_1234"}'."\n",
            'context' => ['session_id' => 'test-stream-session-001'],
        ]);

        $res2->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);
    }

    // -----------------------------------------------------------------------
    // Context validation: only whitelisted keys accepted
    // -----------------------------------------------------------------------

    public function test_context_with_arbitrary_keys_is_rejected(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '{"data":1}'."\n",
            'context' => [
                'admin' => true,
                'user_id' => 999,
                'privileged_flag' => 'yes',
            ],
        ])->assertUnprocessable();
    }

    public function test_context_with_whitelisted_keys_is_accepted(): void
    {
        $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '{"data":1}'."\n",
            'context' => [
                'session_id' => 'valid-session-id',
                'source' => 'browser',
                'device' => '/dev/ttyUSB0',
                'device_name' => 'ESP32 Scanner',
            ],
        ])->assertOk();
    }

    // -----------------------------------------------------------------------
    // Portflow facade helpers
    // -----------------------------------------------------------------------

    public function test_portflow_health_returns_driver_list(): void
    {
        $health = PortFlow::health();

        $this->assertArrayHasKey('registered_drivers', $health);
        $this->assertContains('raw-json', $health['registered_drivers']);
        $this->assertContains('barcode-line', $health['registered_drivers']);
        $this->assertContains('rfid-ascii', $health['registered_drivers']);
        $this->assertContains('fingerprint-packet', $health['registered_drivers']);
    }

    public function test_portflow_encode_returns_string(): void
    {
        $encoded = PortFlow::encode('barcode-line', ['barcode' => 'ENC_TEST_001']);

        $this->assertStringContainsString('ENC_TEST_001', $encoded);
    }

    // -----------------------------------------------------------------------
    // Queue routing flag
    // -----------------------------------------------------------------------

    public function test_queue_routing_dispatches_job_instead_of_sync_event(): void
    {
        config(['portflow.queue_routing' => true]);

        Queue::fake();
        Event::fake([ProductScanned::class]);

        $this->postJson(route('portflow.ingest'), [
            'driver' => 'raw-json',
            'chunk' => '{"type":"barcode.scan","barcode":"QUEUED_SCAN"}'."\n",
        ])->assertOk()->assertJson(['ok' => true, 'frames_processed' => 1]);

        Queue::assertPushed(RouteSerialFrameJob::class);
        Event::assertNotDispatched(ProductScanned::class);
    }
}
