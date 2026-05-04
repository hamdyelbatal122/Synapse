<script>
    window.portflowConfig = Object.assign(
        {},
        @js($portflowConfig),
        window.portflowConfig ?? {}
    );
</script>

<div x-data="portflowConnector()" x-init="init()" class="space-y-3">
    <div class="flex flex-wrap items-center gap-2">
        <button
            type="button"
            x-on:click="connected ? disconnect() : connect()"
            x-bind:disabled="connecting || (!supported && !connected)"
            class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span x-show="!connecting && !connected">Scan & Connect</span>
            <span x-show="connecting">Connecting...</span>
            <span x-show="!connecting && connected">Disconnect</span>
        </button>

        <span class="text-xs text-slate-500" x-show="connected">
            Browser bridge connected. Frames: <span x-text="frames"></span>
        </span>
    </div>

    <p class="text-xs text-slate-500" x-show="supported">
        ESP32 raw-json mode expects newline-delimited JSON, for example:
        <code>{"type":"barcode.scan","barcode":"4006381333931"}</code>
    </p>

    <p class="text-xs text-slate-500">
        Configured baud rate from Blade/backend: <span x-text="baudRate"></span>. Example:
        <code>&lt;livewire:portflow-connector :baud-rate=&quot;115200&quot; /&gt;</code>
    </p>

    <p class="text-xs text-slate-500" x-show="autoConnectOnLoad">
        Previously granted devices will auto-reconnect after page reload when browser permission still exists.
    </p>

    <p class="text-xs text-amber-600" x-show="!supported">
        Web Serial works in Chromium-based browsers only.
    </p>

    <p class="text-xs text-rose-600" x-show="lastError" x-text="lastError"></p>
</div>
