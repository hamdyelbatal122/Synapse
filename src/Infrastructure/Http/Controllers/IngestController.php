<?php

declare(strict_types=1);

namespace Hamzi\PortFlow\Infrastructure\Http\Controllers;

use Hamzi\PortFlow\PortFlowManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IngestController
{
    public function __invoke(Request $request, PortFlowManager $portflow): JsonResponse
    {
        $validated = $request->validate([
            'driver'  => ['nullable', 'string'],
            'chunk'   => ['required', 'string', 'max:' . (int) config('portflow.max_chunk_bytes', 16384)],
            'context' => ['nullable', 'array'],
        ]);

        $driver = (string) ($validated['driver'] ?? config('portflow.default_driver'));

        $frames = $portflow->ingest(
            driver: $driver,
            chunk: $validated['chunk'],
            context: (array) ($validated['context'] ?? []),
        );

        return response()->json([
            'ok' => true,
            'driver' => $driver,
            'frames_processed' => count($frames),
        ]);
    }
}
