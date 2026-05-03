<div
    x-data="synapseConnector()"
    class="inline-flex items-center gap-2 rounded-md border px-3 py-1 text-xs"
    x-bind:class="connected ? 'border-emerald-500 text-emerald-600' : 'border-amber-500 text-amber-600'"
>
    <span class="h-2 w-2 rounded-full" x-bind:class="connected ? 'bg-emerald-500' : 'bg-amber-500'"></span>
    <span x-text="connected ? 'Hardware Online' : 'Hardware Offline'"></span>
</div>
