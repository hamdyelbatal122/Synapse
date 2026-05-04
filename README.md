# PortFlow

[![CI](https://github.com/hamdyelbatal122/PortFlow/actions/workflows/ci.yml/badge.svg)](https://github.com/hamdyelbatal122/PortFlow/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/hamzi/portflow.svg?style=flat-square)](https://packagist.org/packages/hamzi/portflow)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue?style=flat-square)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2F12%2F13-red?style=flat-square)](https://laravel.com)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg?style=flat-square)](LICENSE)

> **Neural bridge for Laravel × Hardware.** Connect thermal printers, IoT sensors, RS-232 scales, barcode scanners, and any serial device to your Laravel application — all through a clean, driver-based architecture and the browser's Web Serial API.

---

## Contents

- [What is PortFlow?](#what-is-portflow)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Drivers](#available-drivers)
  - [RAW / JSON (ESP32, Arduino, IoT)](#raw--json-driver)
  - [Barcode Line Driver (USB/TTL Scanners)](#barcode-line-driver)
  - [RFID ASCII Driver (STX/ETX Readers)](#rfid-ascii-driver)
  - [Fingerprint Packet Driver (Binary UART)](#fingerprint-packet-driver)
  - [ESC/POS (Thermal Printers)](#escpos-driver)
  - [RS-232 (Scales, Legacy Devices)](#rs-232-driver)
- [Creating a Custom Driver](#creating-a-custom-driver)
- [Web Serial Integration](#web-serial-integration)
  - [Backend Direct Serial Mode](#backend-direct-serial-mode)
  - [Auto-Reconnect](#auto-reconnect)
  - [Browser Compatibility](#browser-compatibility)
- [Hardware → Events & Eloquent](#hardware--events--eloquent)
  - [Queue-Based Routing](#queue-based-routing)
- [Thermal Printing Engine](#thermal-printing-engine)
- [IoT Frame Buffering](#iot-frame-buffering)
  - [Buffer Persistence Across Requests](#buffer-persistence-across-requests)
- [Blade & Livewire Components](#blade--livewire-components)
- [Security](#security)
- [Configuration Reference](#configuration-reference)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)

---

## What is PortFlow?

Most Laravel packages live entirely in software. PortFlow does not.

It bridges **physical hardware** (printers, scales, sensors, microcontrollers) with Laravel's event system and database, translating raw serial bytes into typed, routable `SerialFrame` DTOs that your application can consume like any other event.

```
Browser (Web Serial API)
       │
       │  POST /portflow/ingest  { driver: "raw-json", chunk: "..." }
       ▼
Laravel (IngestController → PortFlowManager → DriverRegistry)
       │
       ├── parse inbound bytes → SerialFrame[]
       │
       ├── route synchronously or via Queue → Laravel Events
       │
       └── persist to Eloquent models
```

---

## Architecture

PortFlow follows Clean Architecture — domain logic is completely isolated from infrastructure:

```
src/
├── Domain/
│   ├── Contracts/
│   │   ├── SerialDriver.php          ← Interface all drivers implement
│   │   └── SerialEvent.php           ← Marker interface for domain events
│   ├── DTO/
│   │   └── SerialFrame.php           ← Immutable parsed-frame value object
│   ├── Events/
│   │   └── ProductScanned.php        ← Example domain event
│   └── Services/
│       └── IoTFrameBuffer.php        ← Byte-stream accumulation buffer
│
├── Application/
│   ├── Jobs/
│   │   └── RouteSerialFrameJob.php   ← Queueable frame routing job
│   └── Services/
│       ├── DriverRegistry.php        ← Resolves driver instances
│       ├── HardwareMessageService.php← Orchestrates ingest / encode
│       └── MessageRouter.php         ← Routes frames → Events / Eloquent
│
├── Infrastructure/
│   ├── Drivers/
│   │   ├── RawJsonDriver.php         ← ESP32, Arduino, MQTT payloads
│   │   ├── BarcodeLineDriver.php     ← Barcode scanners (ASCII line mode)
│   │   ├── RfidAsciiDriver.php       ← STX/ETX RFID ASCII readers
│   │   ├── FingerprintPacketDriver.php ← Binary UART fingerprint modules
│   │   ├── EscPosDriver.php          ← Thermal printers + barcode scanners
│   │   └── Rs232Driver.php           ← RS-232 scales and legacy devices
│   ├── Http/Controllers/
│   │   └── IngestController.php      ← POST endpoint consumed by JS bridge
│   ├── Livewire/
│   │   ├── PortFlowConnector.php     ← Tracks port connection state
│   │   └── PortFlowStatus.php        ← Real-time status display
│   └── Printing/
│       ├── EscPosBuilder.php         ← Fluent ESC/POS byte builder
│       └── BladeEscPosRenderer.php   ← Renders Blade → ESC/POS bytes
│
├── Console/Commands/
│   ├── MakeDriverCommand.php         ← php artisan portflow:make-driver
│   └── ListenSerialCommand.php       ← php artisan portflow:listen
│
├── Exceptions/
│   └── PortFlowException.php
├── Facades/
│   └── PortFlow.php
└── PortFlowServiceProvider.php
```

---

## Requirements

| Dependency | Version | Notes |
|---|---|---|
| PHP | `^8.2` | PHP 8.3+ recommended for Laravel 13 |
| Laravel | `^11.0 \| ^12.0 \| ^13.0` | All actively supported versions |
| Livewire | `^3.0` | |

> **Browser support for Web Serial API:** Chrome 89+, Edge 89+. Not supported in Firefox or Safari.

---

## Installation

```bash
composer require hamzi/portflow
```

Publish the config file:

```bash
php artisan vendor:publish --tag=portflow-config
```

Publish the JavaScript bridge (optional):

```bash
php artisan vendor:publish --tag=portflow-assets
```

Publish driver stubs (optional — for customising the `make:driver` template):

```bash
php artisan vendor:publish --tag=portflow-stubs
```

---

## Quick Start

**1. Add the JS bridge to your layout:**

```html
<script src="{{ asset('vendor/portflow/portflow-serial.js') }}"></script>
```

**2. Drop in the Livewire connector component:**

```blade
<livewire:portflow-connector :baud-rate="115200" :auto-connect-on-load="true" />
```

The connector now bootstraps sensible defaults from `config/portflow.php` automatically, including the ingest URL, default driver, and baud rate. Override them from Blade when needed instead of rendering a select box for the end user.

If `:baud-rate` is explicitly passed in Blade, PortFlow gives it priority over remembered localStorage values.

Useful Blade props:

```blade
<livewire:portflow-connector
  :baud-rate="115200"
  driver="raw-json"
  :auto-connect-on-load="true"
  :context="['device' => 'esp32-line-a']"
  :filters="[['usbVendorId' => 6790, 'usbProductId' => 29987]]"
  browser-chunk-event="esp32-browser-frame"
  livewire-chunk-event="esp32-livewire-frame"
/>
```

When `auto-connect-on-load` is enabled, PortFlow asks the browser for already-authorized serial devices with `navigator.serial.getPorts()` and reconnects automatically after reload when permission still exists.

**3. Listen for hardware events in your application:**

```php
use Hamzi\PortFlow\Domain\Events\ProductScanned;

class HandleBarcodeScan
{
    public function handle(ProductScanned $event): void
    {
        $product = Product::where('barcode', $event->barcode)->firstOrFail();
        // process $product ...
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

The driver accumulates bytes into an `IoTFrameBuffer` and emits complete JSON frames when a newline delimiter is detected. Invalid JSON is forwarded as `{ "raw": "..." }` so data is never silently dropped.

```php
// config/portflow.php
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
- **Outbound** — `PortFlow::encode('escpos', ['text' => $line])` returns bytes to send to the printer.

**Printing a Blade template:**

```php
$bytes = PortFlow::print('receipts.order', ['order' => $order]);
// $bytes can be sent directly to the printer via Web Serial
```

**Building ESC/POS bytes manually:**

```php
use Hamzi\PortFlow\Infrastructure\Printing\EscPosBuilder;

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
PortFlow::encode('rs232', ['TARE', '0', 'RESET']);
// → "TARE,0,RESET\n"
```

---

## Creating a Custom Driver

### Using the Artisan Generator

```bash
php artisan portflow:make-driver MyScale
# creates app/SerialDrivers/MyScaleDriver.php

php artisan portflow:make-driver Modbus --namespace="App\\Hardware\\Drivers"
# creates app/Hardware/Drivers/ModbusDriver.php
```

Then register it in your config:

```php
// config/portflow.php
'drivers' => [
    'my-scale' => \App\SerialDrivers\MyScaleDriver::class,
],
```

### Manually Implementing the Interface

Implement `Hamzi\PortFlow\Domain\Contracts\SerialDriver` and, for type safety, also implement `SerialEvent` on any event classes you create:

```php
use Hamzi\PortFlow\Domain\Contracts\SerialDriver;
use Hamzi\PortFlow\Domain\DTO\SerialFrame;

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
        return is_string($payload) ? $payload : json_encode($payload);
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

Register in `AppServiceProvider` or config:

```php
PortFlow::registerDriver('modbus', MyModbusDriver::class);
```

### Defining a Typed Domain Event

```php
use Hamzi\PortFlow\Domain\Contracts\SerialEvent;

final class WeightReceived implements SerialEvent
{
    public function __construct(
        public readonly string $value,
        public readonly array  $context = [],
    ) {}
}
```

> Events that do **not** implement `SerialEvent` will still be dispatched, but a `Log::warning` will be emitted to encourage type safety.

---

## Web Serial Integration

`portflow-serial.js` provides a thin wrapper around the [Web Serial API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Serial_API) that POSTs incoming chunks to Laravel automatically.

```html
<button id="connect">Connect Device</button>

<script src="{{ asset('vendor/portflow/portflow-serial.js') }}"></script>
<script>
  const bridge = new PortFlowBridge({
    ingestUrl: '{{ route("portflow.ingest") }}',
    driver:    'raw-json',
    baudRate:  115200,
    autoConnectOnLoad: true,
    filters: [{ usbVendorId: 6790, usbProductId: 29987 }],
    browserChunkEvent: 'esp32-browser-frame',
    livewireChunkEvent: 'esp32-livewire-frame',
    csrfToken: document.head.querySelector('meta[name="csrf-token"]').content,
  });

  document.getElementById('connect').addEventListener('click', () => bridge.connect());
</script>
```

**Constructor options:**

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `baudRate` | `number` | `9600` | Serial baud rate |
| `autoConnectOnLoad` | `boolean` | `true` | Reconnect automatically after reload when the browser already trusts the port |
| `driver` | `string` | `'raw-json'` | PortFlow driver name |
| `ingestUrl` | `string` | `'/portflow/ingest'` | Backend endpoint |
| `csrfToken` | `string` | auto-detected | CSRF token for POST |
| `rememberBaudRate` | `boolean` | `true` | Persist the last selected baud rate in `localStorage` |
| `filters` | `array` | `[]` | Web Serial port filters used by `requestPort()` |
| `browserChunkEvent` | `string` | `'portflow-frame-received'` | Browser event name emitted for received chunks |
| `livewireChunkEvent` | `string` | `'portflow-frame-received'` | Livewire event name emitted for received chunks |
| `livewireStatusEvent` | `string` | `'portflow-status-updated'` | Livewire event name emitted for connection status |
| `livewireErrorEvent` | `string` | `'portflow-error'` | Livewire event name emitted for bridge errors |
| `autoReconnect` | `boolean` | `true` | Auto-reconnect on disconnect |
| `maxRetries` | `number` | `5` | Max reconnect attempts |
| `retryDelay` | `number` | `2000` | Base delay in ms (exponential back-off) |

**Window events:**

```js
// Fired on every status change (connect, disconnect, reconnect attempt)
window.addEventListener('portflow-status', (e) => {
  console.log('Connected:', e.detail.connected);
  console.log('Driver:', e.detail.driver);
  console.log('Frames received:', e.detail.frames);
  console.log('Retry count:', e.detail.retryCount);
});

// Fired for every raw chunk POSTed to the backend
window.addEventListener('portflow-frame-received', (e) => {
  console.log('Raw chunk:', e.detail.chunk);
});

// Fired on each reconnect attempt
window.addEventListener('portflow-reconnecting', (e) => {
  console.log(`Reconnect attempt ${e.detail.attempt} in ${e.detail.delay}ms`);
});

// Fired when max retries are exhausted
window.addEventListener('portflow-reconnect-failed', (e) => {
  console.error(`Failed after ${e.detail.retries} retries`);
});

// Fired immediately when the browser does not support Web Serial API
window.addEventListener('portflow-unsupported', (e) => {
  console.warn(e.detail.reason);
  console.info('Supported browsers:', e.detail.suggestedBrowsers);
});
```

### Auto-Reconnect

When a serial connection drops unexpectedly, the bridge automatically attempts to reconnect using **exponential back-off** (2 s, 4 s, 8 s, …) up to `maxRetries` attempts. This is enabled by default and requires no configuration.

### Backend Direct Serial Mode

Some devices are easier or safer to integrate from the backend (Linux service, kiosk daemon, headless station) rather than browser Web Serial. PortFlow includes an Artisan listener:

```bash
php artisan portflow:listen /dev/ttyUSB0 --driver=barcode-line --baud=115200
```

Windows example:

```bash
php artisan portflow:listen COM3 --driver=barcode-line --baud=115200
```

Advanced UART parameters:

```bash
php artisan portflow:listen /dev/ttyUSB1 \
  --driver=rfid-ascii \
  --baud=9600 \
  --parity=none \
  --data-bits=8 \
  --stop-bits=1 \
  --flow-control=none \
  --context='{"station":"gate-a"}'
```

Show incoming serial payloads in the console while still ingesting frames:

```bash
php artisan portflow:listen /dev/ttyUSB0 \
  --driver=raw-json \
  --baud=921600 \
  --show-data=1 \
  --show-data-format=json
```

Data preview options:

- `--show-data=1` enables payload preview logs.
- `--show-data-format=auto|raw|plain|json|hex|base64` controls rendering format.
- `--show-data-max=512` limits displayed bytes per chunk to keep logs readable.

Security and hardening notes:

- Device path validation supports `/dev/*` on Linux/macOS and `COMx` / `\\.\COMx` on Windows.
- Use `portflow.backend.allowed_devices` to allowlist exact/glob device paths.
- Listener configures serial parameters with platform-native tooling (`stty` on POSIX, `mode` on Windows).
- Ingest now validates decoded base64 chunk size against `max_chunk_bytes`.

`config/portflow.php` backend section:

```php
'backend' => [
    'allowed_devices' => [
        '/dev/ttyUSB0',
        '/dev/ttyACM*',
        'COM*',
        '\\\\.\\COM*',
    ],
    'default_chunk_bytes' => 256,
    'default_read_sleep_us' => 20000,
],
```

To disable:

```js
const bridge = new PortFlowBridge({ autoReconnect: false });
```

**Alpine.js integration (built-in):**

```html
<div x-data="portflowConnector()" x-init="init()">
  <button @click="connect()" :disabled="connecting">
    <span x-text="connected ? 'Connected' : (connecting ? 'Connecting…' : 'Connect Device')"></span>
  </button>
  <span x-show="connected" x-text="'Frames: ' + frames"></span>
  <span x-show="retryCount > 0" x-text="'Reconnecting… attempt ' + retryCount"></span>
</div>

<script>
  window.portflowConfig = {
    ingestUrl: '{{ route("portflow.ingest") }}',
    driver:   'raw-json',
    baudRate:  115200,
  };
</script>
```

### Browser Compatibility

The Web Serial API is only available in **Chromium-based browsers** (Chrome 89+ / Edge 89+). Firefox and Safari do not support it.

**Check support in JavaScript:**

```js
if (!PortFlowBridge.isSupported()) {
  // Show a fallback UI, redirect, or degrade gracefully
  console.warn('Web Serial not available in this browser.');
}
```

**Check support in Blade (no Alpine required):**

```blade
{{-- Wraps any content; shows a warning banner in unsupported browsers --}}
<x-portflow::portflow-browser-check>
    <livewire:portflow-connector />
</x-portflow::portflow-browser-check>
```

The component renders a yellow warning banner with a "Download Chrome" link in Firefox/Safari, and leaves the content untouched in supported browsers.

Customise the message and hide the download link:

```blade
<x-portflow::portflow-browser-check
    message="Serial device features require Google Chrome or Microsoft Edge."
    :show-download-link="false"
>
    <livewire:portflow-connector />
</x-portflow::portflow-browser-check>
```

| Browser | Web Serial | Status |
|---------|-----------|--------|
| Chrome 89+ | ✅ | Supported |
| Edge 89+ | ✅ | Supported |
| Firefox | ❌ | Not supported (flag-only, no stable release) |
| Safari / iOS | ❌ | Not supported |
| Opera (Chromium) | ✅ | Supported |

---

## Hardware → Events & Eloquent

The `mappings` config key lets you automatically route frames to Laravel events or Eloquent models without writing controller code.

```php
// config/portflow.php
'mappings' => [
    // Fire ProductScanned when a raw-json frame has type = "barcode.scan"
    [
        'driver'              => 'raw-json',
        'payload_field'       => 'type',
        'equals'              => 'barcode.scan',
        'event'               => ProductScanned::class,
        'event_payload_field' => 'barcode',
    ],

    // Match every ESC/POS frame (no payload filter)
    [
        'driver' => 'escpos',
        'event'  => ProductScanned::class,
        'event_payload_field' => 'barcode',
    ],

    // Persist weight readings directly to an Eloquent model
    [
        'driver'    => 'rs232',
        'model'     => \App\Models\WeightReading::class,
        'field_map' => [
            'value' => 'weight',   // model column => payload key
            'unit'  => 'segments.1',
        ],
    ],
],
```

If a mapping's event or model throws an exception, it is caught and written to `Log::error` so one bad handler never breaks other mappings or the HTTP response.

### Queue-Based Routing

For high-throughput or slow listeners, route frames asynchronously via the queue:

```php
// config/portflow.php  (or .env)
'queue_routing' => env('PORTFLOW_QUEUE_ROUTING', false),
```

Or in `.env`:

```
PORTFLOW_QUEUE_ROUTING=true
```

When enabled, each `SerialFrame` is dispatched as a `RouteSerialFrameJob` (3 retries by default) on the configured queue connection. The HTTP response is returned immediately.

```bash
php artisan queue:work
```

---

## Thermal Printing Engine

PortFlow ships a Blade-to-ESC/POS renderer so you can design receipts in familiar Blade syntax:

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
$bytes = PortFlow::print('receipts.order', ['order' => $order]);
// Send $bytes to the printer via Web Serial write() or a TCP socket
```

---

## IoT Frame Buffering

`IoTFrameBuffer` is a standalone utility that accumulates streaming bytes and emits complete frames when a delimiter is found. Use it independently for any stream protocol:

```php
use Hamzi\PortFlow\Domain\Services\IoTFrameBuffer;

$buffer = new IoTFrameBuffer(delimiter: "\n", maxBytes: 8192);

$frames = $buffer->push("partial-");  // → []
$frames = $buffer->push("data\n");    // → ["partial-data"]

// Flush any incomplete frame before closing the connection
$remainder = $buffer->flushRemainder();
```

### Buffer Persistence Across Requests

IoT devices may split a JSON packet across multiple HTTP requests. Pass a `session_id` in the request context and the `RawJsonDriver` will automatically persist the buffer state in the Laravel cache between requests (5-minute TTL):

**JavaScript (add `session_id` to the context):**

```js
const bridge = new PortFlowBridge({
  driver: 'raw-json',
  ingestUrl: '/portflow/ingest',
});
// PortFlowBridge sets context.source automatically.
// To enable persistence, pass session_id from the server:
```

**Or POST directly from firmware:**

```json
{
  "driver":  "raw-json",
  "chunk":   "{\"sensor\":\"temp\",\"val",
  "context": { "session_id": "device-esp32-A4:CF:12" }
}
```

A subsequent request with the same `session_id` will complete and emit the frame:

```json
{
  "driver":  "raw-json",
  "chunk":   "ue\":22.5}\n",
  "context": { "session_id": "device-esp32-A4:CF:12" }
}
```

The backend will emit one complete `SerialFrame` with `{ "sensor": "temp", "value": 22.5 }`.

> Requires a cache driver other than `array` in production (e.g., `redis`, `database`).

---

## Blade & Livewire Components

### Blade

```blade
{{-- Connection toggle button + port label --}}
<x-portflow::connector />

{{-- Real-time driver status badge --}}
<x-portflow::status />

{{-- Browser compatibility warning (no Alpine required) --}}
<x-portflow::portflow-browser-check>
    <livewire:portflow-connector />
</x-portflow::portflow-browser-check>
```

`portflow-browser-check` wraps any slot content and injects a styled warning banner when the browser does not support the Web Serial API. See the [Browser Compatibility](#browser-compatibility) section for full options.

### Livewire

```blade
<livewire:portflow-connector />
<livewire:portflow-status />
```

The `PortFlowStatus` component listens for the `portflow-status-updated` browser event dispatched by the JS bridge automatically.

---

## Security

| Protection | Detail |
|------------|--------|
| **Rate limiting** | `60` requests / minute per IP by default. Configurable via `portflow.ingest_rate_limit` or `PORTFLOW_RATE_LIMIT` env. Returns `429` when exceeded. |
| **Chunk size limit** | Inbound `chunk` field capped at `16 384` bytes (configurable). Returns `422` on violation. |
| **CSRF** | Ingest endpoint inherits the `web` middleware group (CSRF enforced). Switch to `['api']` if using token auth. |
| **Event type safety** | Events used in mappings should implement `SerialEvent`. A `Log::warning` is emitted for non-conforming classes. |

---

## Configuration Reference

```php
// config/portflow.php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Driver
    |--------------------------------------------------------------------------
    */
    'default_driver' => env('PORTFLOW_DEFAULT_DRIVER', 'raw-json'),

    /*
    |--------------------------------------------------------------------------
    | Ingest Endpoint
    |--------------------------------------------------------------------------
    */
    'ingest_path' => env('PORTFLOW_INGEST_PATH', '/portflow/ingest'),

    /*
    |--------------------------------------------------------------------------
    | Ingest Middleware
    |--------------------------------------------------------------------------
    | Do NOT add TrimStrings or ConvertEmptyStringsToNull — they corrupt
    | binary delimiters (\n, \r\n) in serial chunk data.
    */
    'ingest_middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'max_chunk_bytes'   => env('PORTFLOW_MAX_CHUNK_BYTES', 16384),
    'ingest_rate_limit' => env('PORTFLOW_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Queue Routing
    |--------------------------------------------------------------------------
    | Set to true to dispatch SerialFrames via the queue instead of
    | processing them synchronously inside the HTTP request cycle.
    */
    'queue_routing' => env('PORTFLOW_QUEUE_ROUTING', false),

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'raw-json' => \Hamzi\PortFlow\Infrastructure\Drivers\RawJsonDriver::class,
        'escpos'   => \Hamzi\PortFlow\Infrastructure\Drivers\EscPosDriver::class,
        'rs232'    => \Hamzi\PortFlow\Infrastructure\Drivers\Rs232Driver::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Driver Options
    |--------------------------------------------------------------------------
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
    | Supported keys per mapping:
    |   driver              — match only frames from this driver (optional)
    |   payload_field       — payload key to match on (optional)
    |   equals              — expected value of payload_field (optional)
    |   event               — fully-qualified event class to dispatch
    |   event_payload_field — payload key passed as first constructor arg
    |   model               — Eloquent model class to create
    |   field_map           — [ 'column' => 'payload_key' ]
    */
    'mappings' => [
        [
            'driver'              => 'raw-json',
            'payload_field'       => 'type',
            'equals'              => 'barcode.scan',
            'event'               => \Hamzi\PortFlow\Domain\Events\ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
        [
            'driver'              => 'escpos',
            'event'               => \Hamzi\PortFlow\Domain\Events\ProductScanned::class,
            'event_payload_field' => 'barcode',
        ],
    ],
];
```

### Barcode Line Driver

**Use case:** common 1D/2D barcode scanners in serial mode (USB CDC, TTL UART, RS-232 adapters).

Most scanners send ASCII text followed by a line terminator (`\r`, `\n`, or both). `barcode-line` normalizes this into:

```php
[
  'barcode' => '4006381333931',
  'raw' => '4006381333931\r',
  'length' => 13,
]
```

Recommended config:

```php
'default_driver' => 'barcode-line',

'driver_options' => [
    'barcode-line' => [
        'delimiter' => "\n",
        'strip_prefix' => [],
        'strip_suffix' => ["\r", "\n", "\t"],
    ],
],
```

### RFID ASCII Driver

**Use case:** 125kHz serial readers (for example ID-12/ID-20 family) that send framed ASCII.

A common frame format is:

- `STX (0x02)`
- ASCII tag bytes
- `CR` + `LF`
- `ETX (0x03)`

`rfid-ascii` parses this safely and emits payload like:

```php
[
  'tag' => '7A005B0FF8D6',
  'raw' => '7A005B0FF8D6\r\n',
  'raw_hex' => '023741303035423046463844360D0A03',
  'format' => 'stx-etx-ascii',
]
```

Recommended config:

```php
'driver_options' => [
    'rfid-ascii' => [
        'stx' => "\x02",
        'etx' => "\x03",
        'uppercase' => true,
    ],
],
```

### Fingerprint Packet Driver

**Use case:** optical/capacitive UART fingerprint modules that speak binary packet protocol (common start code `0xEF01`).

Unlike barcode/RFID text streams, fingerprint sensors are binary. PortFlow now supports binary chunks over web ingest using `chunk_encoding: base64` automatically in the JS bridge.

`fingerprint-packet` validates checksums and emits parsed packet metadata:

```php
[
  'packet_type' => 7,
  'packet_type_name' => 'ack',
  'address_hex' => 'FFFFFFFF',
  'data_hex' => '00',
  'checksum' => 11,
  'checksum_calculated' => 11,
  'checksum_valid' => true,
  'raw_hex' => 'EF01FFFFFFFF07000300000B',
]
```

Recommended config:

```php
'driver_options' => [
    'fingerprint-packet' => [
        'start_code_hex' => 'EF01',
    ],
],
```

### Device Patterns Supported Out-of-the-box

| Device Type | Common Serial Pattern | Recommended Driver |
|---|---|---|
| Barcode scanners | ASCII + CR/LF terminator | `barcode-line` |
| RFID serial readers | `STX + TAG + CRLF + ETX` | `rfid-ascii` |
| Fingerprint sensors | Binary framed packets (`EF01 ... checksum`) | `fingerprint-packet` |

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
composer format -- --test    # check only (CI mode)
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

See [CONTRIBUTING.md](CONTRIBUTING.md) for full guidelines.

---

## License

The MIT License. See [LICENSE](LICENSE) for details.
