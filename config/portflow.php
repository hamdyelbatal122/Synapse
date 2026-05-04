<?php

declare(strict_types=1);

use Hamzi\PortFlow\Domain\Events\ProductScanned;
use Hamzi\PortFlow\Infrastructure\Drivers\EscPosDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\RawJsonDriver;
use Hamzi\PortFlow\Infrastructure\Drivers\Rs232Driver;

return [
    'default_driver' => env('PORTFLOW_DEFAULT_DRIVER', 'raw-json'),

    'ingest_path' => env('PORTFLOW_INGEST_PATH', '/portflow/ingest'),

    /*
     | Middleware applied to the ingest route.
     | NOTE: Do NOT include TrimStrings or ConvertEmptyStringsToNull here —
     | they would corrupt binary delimiters (\n, \r\n) in serial chunks.
     | The 'web' group is used by default to keep CSRF protection. If you
     | prefer a fully stateless endpoint, switch to ['api'].
     */
    'ingest_middleware' => ['web'],

    /*
     | Maximum allowed byte-length for an inbound chunk.
     | Requests exceeding this limit receive a 422 Unprocessable Entity.
     */
    'max_chunk_bytes' => env('PORTFLOW_MAX_CHUNK_BYTES', 16384),

    /*
     | Per-minute rate limit for the ingest endpoint (per IP).
     | Set to 0 to disable throttling.
     */
    'ingest_rate_limit' => env('PORTFLOW_RATE_LIMIT', 60),

    /*
     | When true, SerialFrames are routed via the queue instead of synchronously.
     | Requires a working queue worker (php artisan queue:work).
     */
    'queue_routing' => env('PORTFLOW_QUEUE_ROUTING', false),

    'drivers' => [
        'raw-json' => RawJsonDriver::class,
        'escpos' => EscPosDriver::class,
        'rs232' => Rs232Driver::class,
    ],

    'driver_options' => [
        'raw-json' => [
            'delimiter' => "\n",
            'max_bytes' => 16384,
        ],
        'rs232' => [
            'delimiter' => "\n",
        ],
        'escpos' => [],
    ],

    'mappings' => [
        [
            'driver' => 'raw-json',
            'payload_field' => 'type',
            'equals' => 'barcode.scan',
            'event' => ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
        [
            'driver' => 'escpos',
            'event' => ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
    ],
];
