<button
    type="button"
    x-data="portflowConnector()"
    x-init="init()"
    x-on:click="connected ? disconnect() : connect()"
    x-bind:disabled="connecting || (!supported && !connected)"
    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
>
    <span x-show="!connecting && !connected">Scan &amp; Connect</span>
    <span x-show="connecting">Connecting...</span>
    <span x-show="!connecting && connected">Disconnect</span>
</button>
