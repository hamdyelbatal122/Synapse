class PortFlowBridge {
    constructor(options = {}) {
        this.options = {
            baudRate:       options.baudRate       ?? 9600,
            driver:         options.driver         ?? 'raw-json',
            ingestUrl:      options.ingestUrl      ?? '/portflow/ingest',
            csrfToken:      options.csrfToken      ?? document.querySelector('meta[name="csrf-token"]')?.content,
            autoReconnect:  options.autoReconnect  ?? true,
            maxRetries:     options.maxRetries     ?? 5,
            retryDelay:     options.retryDelay     ?? 2000,
        };

        this.port            = null;
        this.reader          = null;
        this.writer          = null;
        this.connected       = false;
        this.frames          = 0;
        this._retryCount     = 0;
        this._intentionalClose = false;
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

        this.writer              = this.port.writable.getWriter();
        this.reader              = this.port.readable.getReader();
        this.connected           = true;
        this._intentionalClose   = false;
        this._retryCount         = 0;

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
            console.error('[PortFlow] read loop error', error);
        }

        // Connection dropped — attempt auto-reconnect unless closed intentionally.
        if (!this._intentionalClose && this.options.autoReconnect) {
            this._scheduleReconnect();
        } else {
            this.connected = false;
            this.emitStatus();
        }
    }

    _scheduleReconnect() {
        if (this._retryCount >= this.options.maxRetries) {
            console.error(`[PortFlow] Max reconnect attempts (${this.options.maxRetries}) reached. Giving up.`);
            this.connected = false;
            this.emitStatus();
            window.dispatchEvent(new CustomEvent('portflow-reconnect-failed', {
                detail: { retries: this._retryCount },
            }));
            return;
        }

        this._retryCount += 1;
        // Exponential back-off: 2 s, 4 s, 8 s, …
        const delay = this.options.retryDelay * Math.pow(2, this._retryCount - 1);

        console.warn(`[PortFlow] Reconnecting in ${delay}ms (attempt ${this._retryCount}/${this.options.maxRetries})…`);
        this.connected = false;
        this.emitStatus();

        window.dispatchEvent(new CustomEvent('portflow-reconnecting', {
            detail: { attempt: this._retryCount, delay },
        }));

        setTimeout(async () => {
            try {
                // Release stale locks before reopening.
                if (this.reader) { try { await this.reader.cancel(); this.reader.releaseLock(); } catch (_) {} this.reader = null; }
                if (this.writer) { try { this.writer.releaseLock(); } catch (_) {} this.writer = null; }
                if (this.port)   { try { await this.port.close(); } catch (_) {} }

                await this.connect();
            } catch (err) {
                console.error('[PortFlow] Reconnect attempt failed', err);
                this._scheduleReconnect();
            }
        }, delay);
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
                    source:    'web-serial',
                    userAgent: navigator.userAgent,
                },
            }),
        });

        if (!response.ok) {
            throw new Error(`PortFlow ingest failed with status ${response.status}`);
        }

        window.dispatchEvent(new CustomEvent('portflow-frame-received', { detail: { chunk } }));
    }

    async close() {
        this._intentionalClose = true;
        this._retryCount       = 0;
        this.connected         = false;

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
        window.dispatchEvent(new CustomEvent('portflow-status', {
            detail: {
                connected:   this.connected,
                driver:      this.options.driver,
                frames:      this.frames,
                retryCount:  this._retryCount,
            },
        }));

        if (window.Livewire) {
            window.Livewire.dispatch('portflow-status-updated', {
                connected:  this.connected,
                driver:     this.options.driver,
                frames:     this.frames,
                retryCount: this._retryCount,
            });
        }
    }
}

window.PortFlowBridge = PortFlowBridge;

window.portflowConnector = function portflowConnector() {
    const bridge = new PortFlowBridge(window.portflowConfig ?? {});

    return {
        connected:   false,
        connecting:  false,
        frames:      0,
        retryCount:  0,

        init() {
            window.addEventListener('portflow-status', (e) => {
                this.connected  = e.detail.connected;
                this.frames     = e.detail.frames;
                this.retryCount = e.detail.retryCount ?? 0;
            });
        },

        async connect() {
            this.connecting = true;
            try {
                await bridge.connect();
                this.connected = true;
                this.frames    = bridge.frames;
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

    constructor(options = {}) {
        this.options = {
            baudRate: options.baudRate ?? 9600,
            driver: options.driver ?? 'raw-json',
            ingestUrl: options.ingestUrl ?? '/portflow/ingest',
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
            console.error('[PortFlow] read loop error', error);
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
            throw new Error(`PortFlow ingest failed with status ${response.status}`);
        }

        window.dispatchEvent(new CustomEvent('portflow-frame-received', { detail: { chunk } }));
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
        window.dispatchEvent(new CustomEvent('portflow-status', {
            detail: {
                connected: this.connected,
                driver: this.options.driver,
                frames: this.frames,
            },
        }));

        if (window.Livewire) {
            window.Livewire.dispatch('portflow-status-updated', {
                connected: this.connected,
                driver: this.options.driver,
                frames: this.frames,
            });
        }
    }
}

window.PortFlowBridge = PortFlowBridge;

window.portflowConnector = function portflowConnector() {
    const bridge = new PortFlowBridge(window.portflowConfig ?? {});

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
