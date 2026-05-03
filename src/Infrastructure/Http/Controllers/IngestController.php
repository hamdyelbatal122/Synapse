<?php

declare(strict_types=1);

namespace Hamzi\Synapse\Infrastructure\Http\Controllers;

use Hamzi\Synapse\SynapseManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IngestController
{
    public function __invoke(Request $request, SynapseManager $synapse): JsonResponse
    {
        $validated = $request->validate([
            'driver' => ['nullable', 'string'],
            'chunk' => ['required', 'string'],
            'context' => ['nullable', 'array'],
        ]);

        $driver = (string) ($validated['driver'] ?? config('synapse.default_driver'));

        $frames = $synapse->ingest(
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
