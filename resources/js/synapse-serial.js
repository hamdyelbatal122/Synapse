class SynapseBridge {
    constructor(options = {}) {
        this.options = {
            baudRate: options.baudRate ?? 9600,
            driver: options.driver ?? 'raw-json',
            ingestUrl: options.ingestUrl ?? '/synapse/ingest',
            csrfToken: options.csrfToken ?? document.querySelector('meta[name="csrf-token"]')?.content,
        };

        this.port = null;
        this.reader = null;
        this.writer = null;
        this.connected = false;
        this.frames = 0;
    }

    async requestPort(filters = []) {
        if (!('serial' in navigator)) {
            throw new Error('Web Serial API is not supported in this browser.');
        }

        this.port = await navigator.serial.requestPort({ filters });
        return this.port;
    }

    async connect() {
        if (!this.port) {
            await this.requestPort();
        }

        await this.port.open({ baudRate: this.options.baudRate });

        this.writer = this.port.writable.getWriter();
        this.reader = this.port.readable.getReader();
        this.connected = true;

        this.emitStatus();
        this.readLoop();
    }

    async readLoop() {
        const decoder = new TextDecoder();

        try {
            while (this.connected) {
                const { value, done } = await this.reader.read();
                if (done) {
                    break;
                }

                if (!value) {
                    continue;
                }

                const chunk = decoder.decode(value);
                this.frames += 1;

                await this.pushToBackend(chunk);
                this.emitStatus();
            }
        } catch (error) {
            console.error('[Synapse] read loop error', error);
        }
    }

    async write(payload) {
        if (!this.writer) {
            throw new Error('Serial writer is not available.');
        }

        const data = typeof payload === 'string' ? payload : JSON.stringify(payload);
        const bytes = new TextEncoder().encode(data);
        await this.writer.write(bytes);
    }

    async pushToBackend(chunk) {
        const response = await fetch(this.options.ingestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.options.csrfToken ?? '',
            },
            body: JSON.stringify({
                driver: this.options.driver,
                chunk,
                context: {
                    source: 'web-serial',
                    userAgent: navigator.userAgent,
                },
            }),
        });

        if (!response.ok) {
            throw new Error(`Synapse ingest failed with status ${response.status}`);
        }

        window.dispatchEvent(new CustomEvent('synapse-frame-received', { detail: { chunk } }));
    }

    async close() {
        this.connected = false;

        if (this.reader) {
            await this.reader.cancel();
            this.reader.releaseLock();
            this.reader = null;
        }

        if (this.writer) {
            this.writer.releaseLock();
            this.writer = null;
        }

        if (this.port) {
            await this.port.close();
        }

        this.emitStatus();
    }

    emitStatus() {
        window.dispatchEvent(new CustomEvent('synapse-status', {
            detail: {
                connected: this.connected,
                driver: this.options.driver,
                frames: this.frames,
            },
        }));

        if (window.Livewire) {
            window.Livewire.dispatch('synapse-status-updated', {
                connected: this.connected,
                driver: this.options.driver,
                frames: this.frames,
            });
        }
    }
}

window.SynapseBridge = SynapseBridge;

window.synapseConnector = function synapseConnector() {
    const bridge = new SynapseBridge(window.synapseConfig ?? {});

    return {
        connected: false,
        connecting: false,
        frames: 0,
        async connect() {
            this.connecting = true;
            try {
                await bridge.connect();
                this.connected = true;
                this.frames = bridge.frames;
            } finally {
                this.connecting = false;
            }
        },
        async disconnect() {
            await bridge.close();
            this.connected = false;
        },
    };
};
