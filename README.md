# Synapse

[![CI](https://github.com/hamdyelbatal122/Synapse/actions/workflows/ci.yml/badge.svg)](https://github.com/hamdyelbatal122/Synapse/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hamzi/synapse.svg?style=flat-square)](https://packagist.org/packages/hamzi/synapse)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12%2F13-red?style=flat-square)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)

> **Neural bridge for Laravel × Hardware.** Connect thermal printers, IoT sensors, RS-232 scales, barcode scanners, and any serial device to your Laravel application — all through a clean, driver-based architecture and the browser's Web Serial API.

---

## Contents

- [What is Synapse?](#what-is-synapse)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Drivers](#available-drivers)
  - [RAW / JSON (ESP32, Arduino, IoT)](#raw--json-driver)
  - [ESC/POS (Thermal Printers)](#escpos-driver)
  - [RS-232 (Scales, Legacy Devices)](#rs-232-driver)
- [Creating a Custom Driver](#creating-a-custom-driver)
- [Web Serial Integration](#web-serial-integration)
- [Hardware → Events & Eloquent](#hardware--events--eloquent)
- [Thermal Printing Engine](#thermal-printing-engine)
- [IoT Frame Buffering](#iot-frame-buffering)
- [Blade & Livewire Components](#blade--livewire-components)
- [Configuration Reference](#configuration-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## What is Synapse?

Most Laravel packages live entirely in software. Synapse does not.

It bridges **physical hardware** (printers, scales, sensors, microcontrollers) with Laravel's event system and database, translating raw serial bytes into typed, routable `SerialFrame` DTOs that your application can consume like any other event.

```
Browser (Web Serial API)
       │
       │  POST /synapse/ingest  { driver: "raw-json", chunk: "..." }
       ▼
Laravel (IngestController → SynapseManager → DriverRegistry)
       │
       ├── parse inbound bytes → SerialFrame[]
       │
       ├── route to Laravel Events  (ProductScanned, WeightReceived, ...)
       │
       └── persist to Eloquent models
```

---

## Architecture

Synapse follows Clean Architecture — domain logic is completely isolated from infrastructure:

```
src/
├── Domain/
│   ├── Contracts/
│   │   └── SerialDriver.php          ← Interface all drivers implement
│   ├── DTO/
│   │   └── SerialFrame.php           ← Immutable parsed-frame value object
│   ├── Events/
│   │   └── ProductScanned.php        ← Example domain event
│   └── Services/
│       └── IoTFrameBuffer.php        ← Byte-stream accumulation buffer
│
├── Application/
│   └── Services/
│       ├── DriverRegistry.php        ← Resolves driver instances
│       ├── HardwareMessageService.php← Orchestrates ingest / encode
│       └── MessageRouter.php         ← Routes frames → Events / Eloquent
│
├── Infrastructure/
│   ├── Drivers/
│   │   ├── RawJsonDriver.php         ← ESP32, Arduino, MQTT payloads
│   │   ├── EscPosDriver.php          ← Thermal printers + barcode scanners
│   │   └── Rs232Driver.php           ← RS-232 scales and legacy devices
│   ├── Http/Controllers/
│   │   └── IngestController.php      ← POST endpoint consumed by JS bridge
│   ├── Livewire/
│   │   ├── SynapseConnector.php      ← Tracks port connection state
│   │   └── SynapseStatus.php        ← Real-time status display
│   └── Printing/
│       ├── EscPosBuilder.php         ← Fluent ESC/POS byte builder
│       └── BladeEscPosRenderer.php   ← Renders Blade → ESC/POS bytes
│
├── Exceptions/
│   └── SynapseException.php
├── Facades/
│   └── Synapse.php
└── SynapseServiceProvider.php
```

---

## Requirements

| Dependency | Version | Notes |
|---|---|---|
| PHP | `^8.2` | PHP 8.3+ recommended for Laravel 13 |
| Laravel | `^11.0 \| ^12.0 \| ^13.0` | All actively supported versions |
| Livewire | `^3.0` | |

---

## Installation

```bash
composer require hamzi/synapse
```

Publish the config file:

```bash
php artisan vendor:publish --tag=synapse-config
```

Publish the JavaScript bridge (optional):

```bash
php artisan vendor:publish --tag=synapse-assets
```

This copies `synapse-serial.js` to `public/vendor/synapse/` for use in Blade layouts.

---

## Quick Start

**1. Add the JS bridge to your layout:**

```html
<script src="{{ asset('vendor/synapse/synapse-serial.js') }}"></script>
```

**2. Drop in the Livewire connector component:**

```blade
<livewire:synapse-connector />
```

**3. Listen for hardware events in your application:**

```php
use Hamzi\Synapse\Domain\Events\ProductScanned;

class HandleBarcodeScan
{
    public function handle(ProductScanned $event): void
    {
        $product = Product::where('barcode', $event->barcode)->firstOrFail();
        // ...
    }
}
```

Register the listener in `AppServiceProvider::boot()`:

```php
Event::listen(ProductScanned::class, HandleBarcodeScan::class);
```

---

## Available Drivers

### RAW / JSON Driver

**Use case:** ESP32, Arduino, MQTT-to-serial bridges, custom IoT sensors.

The driver accumulates bytes into an `IoTFrameBuffer` and emits complete JSON frames when a newline delimiter is detected. Invalid JSON is forwarded as `{ raw: "..." }` so data is never silently dropped.

```php
// config/synapse.php
'default_driver' => 'raw-json',

'driver_options' => [
    'raw-json' => [
        'delimiter' => "\n",
        'max_bytes'  => 16384,   // rolling buffer ceiling
    ],
],
```

**Inbound frame from an ESP32:**

```json
{ "type": "barcode.scan", "barcode": "4006381333931" }
```

**Mapping it to a Laravel event:**

```php
'mappings' => [
    [
        'driver'              => 'raw-json',
        'payload_field'       => 'type',
        'equals'              => 'barcode.scan',
        'event'               => ProductScanned::class,
        'event_payload_field' => 'barcode',
    ],
],
```

---

### ESC/POS Driver

**Use case:** Thermal receipt printers and USB barcode scanners (which behave like keyboards).

The ESC/POS driver handles **both directions**:
- **Inbound** — scanner sends a barcode string that becomes a `SerialFrame`.
- **Outbound** — `Synapse::encode('escpos', ['text' => $line])` returns bytes to send to the printer.

**Printing a Blade template:**

```php
$bytes = Synapse::print('receipts.order', ['order' => $order]);
// $bytes can be sent directly to the printer via Web Serial
```

**Building ESC/POS bytes manually:**

```php
use Hamzi\Synapse\Infrastructure\Printing\EscPosBuilder;

$bytes = (new EscPosBuilder)
    ->align('center')
    ->bold()
    ->text('ACME STORE')
    ->bold(false)
    ->divider()
    ->align('left')
    ->text('Item 1 ................. $9.99')
    ->text('Item 2 ................. $4.50')
    ->divider()
    ->bold()
    ->text('TOTAL ................. $14.49')
    ->bold(false)
    ->feed(3)
    ->cut()
    ->bytes();
```

**Available `EscPosBuilder` methods:**

| Method | ESC/POS Command | Description |
|--------|----------------|-------------|
| `text(string $value)` | — | Append a line of text |
| `bold(bool $on = true)` | `ESC E n` | Toggle bold |
| `underline(bool $on = true)` | `ESC - n` | Toggle underline |
| `align(string)` | `ESC a n` | `'left'`, `'center'`, `'right'` |
| `divider(int $width = 48)` | — | Print a dash line separator |
| `feed(int $lines = 1)` | `LF` | Feed blank lines |
| `cut(bool $partial = false)` | `GS V` | Cut paper (full or partial) |
| `bytes()` | — | Return accumulated byte string |

---

### RS-232 Driver

**Use case:** Industrial scales, label printers, and legacy serial devices using semicolon-delimited records.

**Example record:**

```
12.500;kg;SCALE-A1
```

Parsed into:

```php
$frame->payload['weight']   // "12.500"
$frame->payload['segments'] // ["12.500", "kg", "SCALE-A1"]
$frame->payload['raw']      // "12.500;kg;SCALE-A1"
```

**Encoding outbound commands:**

```php
Synapse::encode('rs232', ['TARE', '0', 'RESET']);
// → "TARE,0,RESET\n"
```

---

## Creating a Custom Driver

Implement `Hamzi\Synapse\Domain\Contracts\SerialDriver`:

```php
use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Domain\DTO\SerialFrame;

final class MyModbusDriver implements SerialDriver
{
    public function name(): string
    {
        return 'modbus';
    }

    public function configure(array $options = []): void
    {
        // Store $options for use in parse/encode
    }

    public function encodeOutbound(array|string $payload): string
    {
        // Serialize $payload to device-specific bytes
        return '...';
    }

    /** @return array<int, SerialFrame> */
    public function parseInbound(string $chunk, array $context = []): array
    {
        return [
            SerialFrame::now($this->name(), ['data' => $chunk], $context),
        ];
    }
}
```

Register it in your `AppServiceProvider`:

```php
use Hamzi\Synapse\Facades\Synapse;

public function boot(): void
{
    Synapse::registerDriver('modbus', MyModbusDriver::class);
}
```

Or in config:

```php
// config/synapse.php
'drivers' => [
    'modbus' => MyModbusDriver::class,
],
```

---

## Web Serial Integration

`synapse-serial.js` provides a thin wrapper around the [Web Serial API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Serial_API) that POSTs incoming chunks to Laravel automatically.

```html
<button id="connect">Connect Device</button>

<script src="{{ asset('vendor/synapse/synapse-serial.js') }}"></script>
<script>
  const bridge = new SynapseBridge({
    ingestUrl: '{{ route("synapse.ingest") }}',
    driver:    'raw-json',
    baudRate:  115200,
    csrfToken: document.head.querySelector('meta[name="csrf-token"]').content,
  });

  document.getElementById('connect').addEventListener('click', () => bridge.connect());
</script>
```

**Listening for events (dispatched on `window`):**

```js
// Fired after each inbound chunk is processed
window.addEventListener('synapse-status', (e) => {
  console.log('Connected:', e.detail.connected);
  console.log('Driver:', e.detail.driver);
  console.log('Frames received:', e.detail.frames);
});

// Fired for every raw chunk POSTed to the backend
window.addEventListener('synapse-frame-received', (e) => {
  console.log('Raw chunk:', e.detail.chunk);
});
```

**Alpine.js integration (built-in):**

```html
<div x-data="synapseConnector()">
  <button @click="connect" :disabled="connecting" x-text="connected ? 'Disconnect' : 'Connect'"></button>
  <span x-show="connected" x-text="'Frames: ' + frames"></span>
</div>

<script>
  window.synapseConfig = {
    ingestUrl: '{{ route("synapse.ingest") }}',
    driver: 'raw-json',
    baudRate: 115200,
  };
</script>
```

> **Browser support:** Chrome 89+, Edge 89+. Not supported in Firefox or Safari.

---

## Hardware → Events & Eloquent

The `mappings` config key lets you automatically route frames to Laravel events or Eloquent models without writing any controller code.

```php
// config/synapse.php
'mappings' => [
    // Fire ProductScanned when raw-json frame has type = "barcode.scan"
    [
        'driver'              => 'raw-json',
        'payload_field'       => 'type',
        'equals'              => 'barcode.scan',
        'event'               => ProductScanned::class,
        'event_payload_field' => 'barcode',
    ],

    // Save weight readings directly to an Eloquent model
    [
        'driver'        => 'rs232',
        'model'         => WeightReading::class,
        'model_field'   => 'value',
        'payload_field' => 'weight',
    ],
],
```

---

## Thermal Printing Engine

Synapse ships a Blade-to-ESC/POS renderer so you can design receipts in familiar Blade syntax:

**`resources/views/receipts/order.blade.php`**

```blade
Order #{{ $order->id }}
Date: {{ $order->created_at->format('d/m/Y H:i') }}
------------------------------------------------
@foreach ($order->items as $item)
{{ str_pad($item->name, 38) }}{{ number_format($item->price, 2) }}
@endforeach
------------------------------------------------
TOTAL: {{ number_format($order->total, 2) }}
```

**In a controller or job:**

```php
$bytes = Synapse::print('receipts.order', ['order' => $order]);
// Send $bytes to the printer via Web Serial or a direct socket
```

---

## IoT Frame Buffering

`IoTFrameBuffer` is a standalone utility that accumulates streaming bytes and emits complete frames when a delimiter is found. Use it independently for any stream protocol:

```php
use Hamzi\Synapse\Domain\Services\IoTFrameBuffer;

$buffer = new IoTFrameBuffer(delimiter: "\n", maxBytes: 8192);

// Simulate receiving chunks from a streaming source
$frames = $buffer->push("partial-");  // → []
$frames = $buffer->push("data\n");    // → ["partial-data"]

// Flush any incomplete frame before closing the connection
$remainder = $buffer->flushRemainder();
```

---

## Blade & Livewire Components

### Blade

```blade
{{-- Connection toggle button + port label --}}
<x-synapse-connector />

{{-- Real-time driver status badge --}}
<x-synapse-status />
```

### Livewire

```blade
<livewire:synapse-connector />
<livewire:synapse-status />
```

The `SynapseStatus` component listens for the `synapse-status-updated` browser event:

```js
Livewire.dispatch('synapse-status-updated', {
  connected: true,
  driver: 'raw-json',
  frames: 42,
});
```

---

## Configuration Reference

```php
// config/synapse.php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    | The driver used when no driver is specified in an ingest request.
    */
    'default_driver' => env('SYNAPSE_DEFAULT_DRIVER', 'raw-json'),

    /*
    |--------------------------------------------------------------------------
    | Ingest Endpoint
    |--------------------------------------------------------------------------
    | HTTP path where synapse-serial.js POSTs incoming serial chunks.
    */
    'ingest_path' => env('SYNAPSE_INGEST_PATH', '/synapse/ingest'),

    /*
    |--------------------------------------------------------------------------
    | Ingest Middleware
    |--------------------------------------------------------------------------
    | Middleware applied to the ingest route. Add 'auth' or custom guards
    | as needed.
    */
    'ingest_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    | Map driver names to their implementation classes.
    */
    'drivers' => [
        'raw-json' => \Hamzi\Synapse\Infrastructure\Drivers\RawJsonDriver::class,
        'escpos'   => \Hamzi\Synapse\Infrastructure\Drivers\EscPosDriver::class,
        'rs232'    => \Hamzi\Synapse\Infrastructure\Drivers\Rs232Driver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Options
    |--------------------------------------------------------------------------
    | Per-driver configuration passed to SerialDriver::configure().
    */
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

    /*
    |--------------------------------------------------------------------------
    | Mappings
    |--------------------------------------------------------------------------
    | Automatically route frames to Events or Eloquent models.
    */
    'mappings' => [
        [
            'driver'              => 'raw-json',
            'payload_field'       => 'type',
            'equals'              => 'barcode.scan',
            'event'               => \Hamzi\Synapse\Domain\Events\ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
    ],
];
```

---

## Testing

```bash
composer test
```

Run with coverage:

```bash
composer test -- --coverage
```

Lint & style:

```bash
composer format              # fix in place
composer format -- --test    # check only (used in CI)
```

Static analysis:

```bash
composer analyse
```

---

## Contributing

Contributions are welcome. Please:

1. Fork the repository and create a feature branch.
2. Write tests for all new behaviour.
3. Run `composer format` and `composer analyse` before submitting.
4. Open a Pull Request with a clear description of the change.

---


## License

The MIT License. See [LICENSE](LICENSE) for details.
