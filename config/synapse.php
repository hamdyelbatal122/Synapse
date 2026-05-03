<?php

declare(strict_types=1);

use Hamzi\Synapse\Domain\Events\ProductScanned;
use Hamzi\Synapse\Infrastructure\Drivers\EscPosDriver;
use Hamzi\Synapse\Infrastructure\Drivers\RawJsonDriver;
use Hamzi\Synapse\Infrastructure\Drivers\Rs232Driver;

return [
    'default_driver' => env('SYNAPSE_DEFAULT_DRIVER', 'raw-json'),

    'ingest_path' => env('SYNAPSE_INGEST_PATH', '/synapse/ingest'),

    'ingest_middleware' => ['web'],

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
