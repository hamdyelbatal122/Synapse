# Hamzi/Synapse

[![CI](https://github.com/hamzi/synapse/actions/workflows/ci.yml/badge.svg)](https://github.com/hamzi/synapse/actions/workflows/ci.yml)

`Hamzi/Synapse` is a professional Laravel package that acts as a **Neural Bridge** between Laravel and physical hardware using:

- Web Serial API (browser side)
- Livewire + Alpine.js (real-time UI layer)
- Driver-based protocol architecture (ESC/POS, RS232, RAW/JSON)
- Hardware-to-Event/Eloquent mapping pipeline

Built for IoT, POS peripherals, cashier accessories, barcode readers, scales, and high-throughput sensor streams.

## Core Capabilities

- Clean PSR-4 package architecture with Service Layers
- Driver Registry for protocol-specific parsing/encoding
- Browser serial stream ingestion endpoint (`/synapse/ingest`)
- Ready Blade components:
  - `<x-synapse-connector />`
  - `<x-synapse-status />`
- Livewire components for reactive status and port awareness
- ESC/POS printing engine from Blade view to binary commands
- IoT frame buffering to reduce Laravel request overhead

## Directory Structure

```text
src/
├── Application/
│   └── Services/
│       ├── DriverRegistry.php
│       ├── HardwareMessageService.php
│       └── MessageRouter.php
├── Domain/
│   ├── Contracts/
│   │   └── SerialDriver.php
│   ├── DTO/
│   │   └── SerialFrame.php
│   ├── Events/
│   │   └── ProductScanned.php
│   └── Services/
│       └── IoTFrameBuffer.php
├── Infrastructure/
│   ├── Drivers/
│   │   ├── EscPosDriver.php
│   │   ├── RawJsonDriver.php
│   │   └── Rs232Driver.php
│   ├── Http/Controllers/
│   │   └── IngestController.php
│   ├── Livewire/
│   │   ├── SynapseConnector.php
│   │   └── SynapseStatus.php
│   └── Printing/
│       ├── BladeEscPosRenderer.php
│       └── EscPosBuilder.php
├── Facades/
│   └── Synapse.php
├── SynapseManager.php
└── SynapseServiceProvider.php
```

## Installation

```bash
composer require hamzi/synapse
```

Publish config and assets:

```bash
php artisan vendor:publish --tag=synapse-config
php artisan vendor:publish --tag=synapse-assets
```

Include the package JS in your layout:

```blade
<script src="{{ asset('vendor/synapse/synapse-serial.js') }}"></script>
```

## Service Provider Registration

Auto-discovered by Laravel. Manual registration (if needed):

```php
// config/app.php
'providers' => [
   Hamzi\Synapse\SynapseServiceProvider::class,
],
```

## Driver Contract (Custom Hardware)

```php
<?php

declare(strict_types=1);

namespace App\Synapse\Drivers;

use Hamzi\Synapse\Domain\Contracts\SerialDriver;
use Hamzi\Synapse\Domain\DTO\SerialFrame;

final class MyHardwareDriver implements SerialDriver
{
   public function name(): string
   {
      return 'my-hardware';
   }

   public function configure(array $options = []): void
   {
   }

   public function encodeOutbound(array|string $payload): string
   {
      return is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);
   }

   public function parseInbound(string $chunk, array $context = []): array
   {
      return [SerialFrame::now($this->name(), ['raw' => $chunk], $context)];
   }
}
```

## Web Serial + Backend Snippet

```js
const bridge = new window.SynapseBridge({
   driver: 'raw-json',
   baudRate: 115200,
   ingestUrl: '/synapse/ingest',
});

await bridge.connect();
await bridge.write({ command: 'ping' });
```

The bridge continuously reads data from the serial port and POSTs chunks to Laravel ingestion with contextual metadata.

## Hardware to Event/Eloquent Mapping

Configure in `config/synapse.php`:

```php
'mappings' => [
   [
      'driver' => 'raw-json',
      'payload_field' => 'type',
      'equals' => 'barcode.scan',
      'event' => Hamzi\Synapse\Domain\Events\ProductScanned::class,
      'event_payload_field' => 'barcode',
   ],
],
```

## Printing Engine (Blade -> ESC/POS)

```php
$bytes = app(Hamzi\Synapse\Infrastructure\Printing\BladeEscPosRenderer::class)
   ->render('receipts.ticket', ['order' => $order]);

app('synapse')->encode('escpos', $bytes);
```

## UI Components

```blade
<x-synapse-connector />
<x-synapse-status />

<livewire:synapse-connector />
<livewire:synapse-status />
```

## Automated Tags and Releases

This repository uses GitHub Actions with Release Please:

- `.github/workflows/release-please.yml` creates Release PRs from Conventional Commits
- `.github/workflows/release-on-tag.yml` publishes GitHub Releases on `v*.*.*` tags

Use Conventional Commit messages (`feat:`, `fix:`, `chore:`) to trigger proper semantic versioning.

## Quality

```bash
composer install
composer format -- --test
composer test
```

## License

MIT
