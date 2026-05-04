<?php

declare(strict_types=1);

use Hamzi\PortFlow\Infrastructure\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

$middleware = array_merge(
    (array) config('portflow.ingest_middleware', ['web']),
    ['throttle:portflow'],
);

Route::middleware($middleware)
    ->post((string) config('portflow.ingest_path', '/portflow/ingest'), IngestController::class)
    ->name('portflow.ingest');
