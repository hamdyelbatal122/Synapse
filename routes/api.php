<?php

declare(strict_types=1);

use Hamzi\Synapse\Infrastructure\Http\Controllers\IngestController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('synapse.ingest_middleware', ['web']))
    ->post((string) config('synapse.ingest_path', '/synapse/ingest'), IngestController::class)
    ->name('synapse.ingest');
