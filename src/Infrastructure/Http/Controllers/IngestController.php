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
            'driver' => ['nullable', 'string'],
            'chunk' => ['required', 'string'],
            'chunk_encoding' => ['nullable', 'string', 'in:plain,base64'],
            'context' => ['nullable', 'array:session_id,source,device,baud_rate,device_name'],
            'context.session_id' => ['sometimes', 'string', 'max:128'],
            'context.source' => ['sometimes', 'string', 'max:64'],
            'context.device' => ['sometimes', 'string', 'max:128'],
            'context.baud_rate' => ['sometimes', 'integer'],
            'context.device_name' => ['sometimes', 'string', 'max:128'],
        ]);

        $driver = (string) ($validated['driver'] ?? config('portflow.default_driver'));
        $chunk = (string) $validated['chunk'];
        $encoding = (string) ($validated['chunk_encoding'] ?? 'plain');
        $maxChunkBytes = (int) config('portflow.max_chunk_bytes', 16384);

        if ($encoding === 'base64') {
            $decoded = base64_decode($chunk, true);

            if ($decoded === false) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Invalid base64 chunk payload.',
                ], 422);
            }

            $chunk = $decoded;

            if (strlen($chunk) > $maxChunkBytes) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Decoded chunk exceeds configured max_chunk_bytes limit.',
                ], 422);
            }
        } elseif (strlen($chunk) > $maxChunkBytes) {
            return response()->json([
                'ok' => false,
                'message' => 'Chunk exceeds configured max_chunk_bytes limit.',
            ], 422);
        }

        $frames = $portflow->ingest(
            driver: $driver,
            chunk: $chunk,
            context: (array) ($validated['context'] ?? []),
        );

        return response()->json([
            'ok' => true,
            'driver' => $driver,
            'frames_processed' => count($frames),
        ]);
    }
}
