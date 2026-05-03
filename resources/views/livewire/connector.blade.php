<div>
    <button
        type="button"
        x-data="synapseConnector()"
        x-on:click="connect()"
        class="inline-flex items-center rounded-lg border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-medium text-white"
    >
        <span x-show="!connected">Scan & Connect</span>
        <span x-show="connected">{{ $portLabel ?? 'Connected' }}</span>
    </button>
</div>
