<div class="rounded-lg border p-3 text-sm">
    <div class="font-semibold">PortFlow Status</div>
    <div class="mt-2">State: {{ $connected ? 'Connected' : 'Disconnected' }}</div>
    <div>Driver: {{ $driver }}</div>
    <div>Baud Rate: {{ $baudRate }}</div>
    <div>Frames: {{ $frames }}</div>
    <div>Frames Processed: {{ $framesProcessed }}</div>
    <div>Retry Count: {{ $retryCount }}</div>

    @if ($lastChunk)
        <div class="mt-2 font-semibold">Last Chunk</div>
        <pre class="mt-1 overflow-x-auto rounded bg-slate-950 p-2 text-xs text-slate-200">{{ $lastChunk }}</pre>
    @endif

    @if ($lastError)
        <div class="mt-2 rounded border border-rose-300 bg-rose-50 p-2 text-rose-700">
            {{ $lastError }}
        </div>
    @endif
</div>
