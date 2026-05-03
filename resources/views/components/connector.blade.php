<button
    type="button"
    x-data="synapseConnector()"
    x-on:click="connect()"
    x-bind:disabled="connecting"
    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:opacity-60"
>
    <span x-show="!connected">Connect Device</span>
    <span x-show="connected">Connected</span>
</button>
