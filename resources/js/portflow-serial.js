class PortFlowBridge {
    /**
     * Returns true when the current browser supports the Web Serial API.
     * Call this before rendering connect UI to show a fallback for Firefox / Safari.
     */
    static isSupported() {
        return typeof navigator !== 'undefined' && 'serial' in navigator;
    }

    static defaultSessionId() {
        const fallback = `portflow-${Date.now().toString(36)}`;

        try {
            const storageKey = 'portflow.session-id';
            const existing = window.sessionStorage?.getItem(storageKey);

            if (existing) {
                return existing;
            }

            const generated = typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
                ? `portflow-${crypto.randomUUID()}`
                : fallback;

            window.sessionStorage?.setItem(storageKey, generated);

            return generated;
        } catch (_) {
            return fallback;
        }
    }

    constructor(options = {}) {
        this.options = {
            baudRate:       options.baudRate       ?? 9600,
            baudRateExplicit: options.baudRateExplicit ?? false,
            rememberBaudRate: options.rememberBaudRate ?? true,
            autoConnectOnLoad: options.autoConnectOnLoad ?? true,
            driver:         options.driver         ?? 'raw-json',
            ingestUrl:      options.ingestUrl      ?? '/portflow/ingest',
            csrfToken:      options.csrfToken      ?? document.querySelector('meta[name="csrf-token"]')?.content,
            sessionId:      options.sessionId      ?? PortFlowBridge.defaultSessionId(),
            context:        options.context        ?? {},
            filters:        Array.isArray(options.filters) ? options.filters : [],
            browserChunkEvent: options.browserChunkEvent ?? 'portflow-frame-received',
            livewireChunkEvent: options.livewireChunkEvent ?? 'portflow-frame-received',
            livewireStatusEvent: options.livewireStatusEvent ?? 'portflow-status-updated',
            livewireErrorEvent: options.livewireErrorEvent ?? 'portflow-error',
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
        this._connectPromise = null;
        this._physicallyDisconnected = false;
        this._onSerialDisconnect = null;
    }

    static baudRateStorageKey() {
        return 'portflow.baud-rate';
    }

    static preferredPortStorageKey() {
        return 'portflow.preferred-port';
    }

    static sanitizeBaudRate(value, fallback = 9600) {
        const parsed = Number.parseInt(String(value), 10);

        return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
    }

    static loadRememberedBaudRate(fallback = 9600) {
        try {
            const stored = window.localStorage?.getItem(PortFlowBridge.baudRateStorageKey());

            return stored ? PortFlowBridge.sanitizeBaudRate(stored, fallback) : fallback;
        } catch (_) {
            return fallback;
        }
    }

    _portIsOpen() {
        return !!(this.port && this.port.readable && this.port.writable);
    }

    setBaudRate(baudRate) {
        const nextBaudRate = PortFlowBridge.sanitizeBaudRate(baudRate, this.options.baudRate);

        this.options.baudRate = nextBaudRate;

        if (this.options.rememberBaudRate) {
            try {
                window.localStorage?.setItem(PortFlowBridge.baudRateStorageKey(), String(nextBaudRate));
            } catch (_) {}
        }

        this.emitStatus();

        return nextBaudRate;
    }

    getPortFingerprint(port = this.port) {
        if (!port || typeof port.getInfo !== 'function') {
            return null;
        }

        const info = port.getInfo();

        return JSON.stringify({
            usbVendorId: info.usbVendorId ?? null,
            usbProductId: info.usbProductId ?? null,
        });
    }

    rememberPreferredPort() {
        try {
            const fingerprint = this.getPortFingerprint();

            if (fingerprint) {
                window.localStorage?.setItem(PortFlowBridge.preferredPortStorageKey(), fingerprint);
            }
        } catch (_) {}
    }

    findPreferredPort(ports) {
        if (!Array.isArray(ports) || ports.length === 0) {
            return null;
        }

        let preferredFingerprint = null;

        try {
            preferredFingerprint = window.localStorage?.getItem(PortFlowBridge.preferredPortStorageKey());
        } catch (_) {}

        if (preferredFingerprint) {
            const matchedPort = ports.find((port) => this.getPortFingerprint(port) === preferredFingerprint);

            if (matchedPort) {
                return matchedPort;
            }
        }

        return ports[0] ?? null;
    }

    async restorePort() {
        if (!PortFlowBridge.isSupported() || !navigator.serial?.getPorts) {
            return null;
        }

        const ports = await navigator.serial.getPorts();
        const preferredPort = this.findPreferredPort(ports);

        if (!preferredPort) {
            return null;
        }

        this.port = preferredPort;

        return preferredPort;
    }

    _acquireStreams() {
        if (!this.port || !this.port.readable || !this.port.writable) {
            throw new Error('Serial port is not ready for reading and writing.');
        }

        if (!this.writer) {
            this.writer = this.port.writable.getWriter();
        }

        if (!this.reader) {
            this.reader = this.port.readable.getReader();
        }
    }

    buildContext() {
        const portInfo = this.port && typeof this.port.getInfo === 'function'
            ? this.port.getInfo()
            : {};

        return {
            ...this.options.context,
            session_id: this.options.sessionId,
            source: 'web-serial',
            userAgent: navigator.userAgent,
            baudRate: this.options.baudRate,
            usbVendorId: portInfo.usbVendorId ?? null,
            usbProductId: portInfo.usbProductId ?? null,
        };
    }

    emitError(error, source = 'bridge') {
        const message = error instanceof Error ? error.message : String(error);

        window.dispatchEvent(new CustomEvent('portflow-error', {
            detail: { message, source },
        }));

        if (window.Livewire) {
            window.Livewire.dispatch('portflow-error', {
                message,
                source,
            });

            if (this.options.livewireErrorEvent !== 'portflow-error') {
                window.Livewire.dispatch(this.options.livewireErrorEvent, {
                    message,
                    source,
                });
            }
        }
    }

    async requestPort(filters = this.options.filters) {
        if (!PortFlowBridge.isSupported()) {
            window.dispatchEvent(new CustomEvent('portflow-unsupported', {
                detail: {
                    reason: 'Web Serial API is not supported in this browser.',
                    suggestedBrowsers: ['Chrome 89+', 'Edge 89+'],
                    docsUrl: 'https://developer.mozilla.org/en-US/docs/Web/API/Web_Serial_API#browser_compatibility',
                },
            }));
            throw new Error('Web Serial API is not supported in this browser. Please use Chrome 89+ or Edge 89+.');
        }

        this.port = await navigator.serial.requestPort({ filters });
        return this.port;
    }

    async connect() {
        if (this.connected) {
            return this.port;
        }

        if (this._connectPromise) {
            return this._connectPromise;
        }

        this._connectPromise = (async () => {
            try {
                if (!this.port) {
                    await this.requestPort();
                }

                if (!this._portIsOpen()) {
                    await this.port.open({ baudRate: this.options.baudRate });
                }

                this._acquireStreams();
                this.connected           = true;
                this._intentionalClose   = false;
                this.rememberPreferredPort();

                // Register a one-time disconnect listener so we can stop retrying
                // immediately when the USB device is physically removed.
                this._registerDisconnectListener();

                this.emitStatus();
                this.readLoop();

                return this.port;
            } catch (error) {
                this.emitError(error, 'connect');
                throw error;
            }
        })();

        try {
            return await this._connectPromise;
        } finally {
            this._connectPromise = null;
        }
    }

    async readLoop() {
        const decoder = new TextDecoder();
        let readLoopError = null;
        let readSucceeded = false;

        try {
            while (this.connected) {
                const { value, done } = await this.reader.read();
                if (done) {
                    break;
                }

                if (!value) {
                    continue;
                }

                // Only reset retry counter after at least one successful read,
                // preventing the "port opens but immediately fails" loop.
                if (!readSucceeded) {
                    readSucceeded = true;
                    this._retryCount = 0;
                }

                const hasControlBytes = value.some((byte) => byte === 0x00 || (byte < 0x09) || (byte > 0x0D && byte < 0x20) || byte > 0x7E);
                const chunk = hasControlBytes
                    ? this.uint8ToBase64(value)
                    : decoder.decode(value);
                const chunkEncoding = hasControlBytes ? 'base64' : 'plain';
                this.frames += 1;

                await this.pushToBackend(chunk, chunkEncoding);
                this.emitStatus();
            }
        } catch (error) {
            readLoopError = error;
            console.error('[PortFlow] read loop error', error);
            this.emitError(error, 'read-loop');
        }

        // "The device has been lost" means USB physical disconnect or device crash.
        // Retrying immediately is pointless — we need to wait and give up sooner.
        const isDeviceLost = readLoopError &&
            (readLoopError.message?.toLowerCase().includes('device has been lost') ||
             readLoopError.message?.toLowerCase().includes('device lost') ||
             readLoopError.name === 'NetworkError');

        // Connection dropped — attempt auto-reconnect unless closed intentionally.
        if (!this._intentionalClose && this.options.autoReconnect && !this._physicallyDisconnected) {
            this._scheduleReconnect(isDeviceLost);
        } else {
            this.connected = false;
            this.emitStatus();
        }
    }

    _scheduleReconnect(isDeviceLost = false) {
        if (this._retryCount >= this.options.maxRetries) {
            console.error(`[PortFlow] Max reconnect attempts (${this.options.maxRetries}) reached. Giving up.`);
            this.connected = false;

            // Forget the stored port so auto-restore on next page load doesn't
            // immediately loop again for a device that is still disconnected.
            if (isDeviceLost) {
                try { window.localStorage?.removeItem(PortFlowBridge.preferredPortStorageKey()); } catch (_) {}
                console.warn('[PortFlow] Device lost after max retries — cleared remembered port.');
            }

            this.emitStatus();
            window.dispatchEvent(new CustomEvent('portflow-reconnect-failed', {
                detail: { retries: this._retryCount, deviceLost: isDeviceLost },
            }));
            return;
        }

        this._retryCount += 1;
        // Exponential back-off: 2 s, 4 s, 8 s, …
        // For "device lost" (ESP32 reboot etc.) start with a longer base delay.
        const baseDelay = isDeviceLost ? Math.max(this.options.retryDelay, 3000) : this.options.retryDelay;
        const delay = baseDelay * Math.pow(2, this._retryCount - 1);

        console.warn(`[PortFlow] Reconnecting in ${delay}ms (attempt ${this._retryCount}/${this.options.maxRetries})…`);
        this.connected = false;
        this.emitStatus();

        window.dispatchEvent(new CustomEvent('portflow-reconnecting', {
            detail: { attempt: this._retryCount, delay, deviceLost: isDeviceLost },
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
                this._scheduleReconnect(isDeviceLost);
            }
        }, delay);
    }

    _registerDisconnectListener() {
        if (!this.port || !navigator.serial) {
            return;
        }

        // Remove any previous listener to avoid duplicates.
        if (this._onSerialDisconnect) {
            navigator.serial.removeEventListener('disconnect', this._onSerialDisconnect);
        }

        this._physicallyDisconnected = false;

        this._onSerialDisconnect = (event) => {
            if (event.target !== this.port) {
                return;
            }

            console.warn('[PortFlow] Serial port physically disconnected.');
            this._physicallyDisconnected = true;
            this._intentionalClose = true; // Stop the readLoop from scheduling reconnects.
            this.connected = false;
            this.emitStatus();

            window.dispatchEvent(new CustomEvent('portflow-device-disconnected', {
                detail: { reason: 'USB device physically removed' },
            }));

            navigator.serial.removeEventListener('disconnect', this._onSerialDisconnect);
            this._onSerialDisconnect = null;
        };

        navigator.serial.addEventListener('disconnect', this._onSerialDisconnect);
    }

    async write(payload) {
        if (!this.writer) {
            throw new Error('Serial writer is not available.');
        }

        const data = typeof payload === 'string' ? payload : JSON.stringify(payload);
        const bytes = new TextEncoder().encode(data);
        await this.writer.write(bytes);
    }

    uint8ToBase64(bytes) {
        let binary = '';

        for (let index = 0; index < bytes.length; index += 1) {
            binary += String.fromCharCode(bytes[index]);
        }

        return btoa(binary);
    }

    async pushToBackend(chunk, chunkEncoding = 'plain') {
        const response = await fetch(this.options.ingestUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': this.options.csrfToken ?? '',
            },
            body: JSON.stringify({
                driver: this.options.driver,
                chunk,
                chunk_encoding: chunkEncoding,
                context: this.buildContext(),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload.message ?? `PortFlow ingest failed with status ${response.status}`);
        }

        window.dispatchEvent(new CustomEvent('portflow-frame-received', {
            detail: {
                chunk,
                framesProcessed: payload.frames_processed ?? 0,
                response: payload,
            },
        }));

        if (this.options.browserChunkEvent !== 'portflow-frame-received') {
            window.dispatchEvent(new CustomEvent(this.options.browserChunkEvent, {
                detail: {
                    chunk,
                    framesProcessed: payload.frames_processed ?? 0,
                    response: payload,
                },
            }));
        }

        if (window.Livewire) {
            window.Livewire.dispatch('portflow-frame-received', {
                chunk,
                framesProcessed: payload.frames_processed ?? 0,
                response: payload,
            });

            if (this.options.livewireChunkEvent !== 'portflow-frame-received') {
                window.Livewire.dispatch(this.options.livewireChunkEvent, {
                    chunk,
                    framesProcessed: payload.frames_processed ?? 0,
                    response: payload,
                });
            }
        }
    }

    async close() {
        this._intentionalClose = true;
        this._retryCount       = 0;
        this._physicallyDisconnected = false;
        this.connected         = false;

        if (this._onSerialDisconnect) {
            try { navigator.serial?.removeEventListener('disconnect', this._onSerialDisconnect); } catch (_) {}
            this._onSerialDisconnect = null;
        }

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

        this.port = null;
        this._connectPromise = null;

        this.emitStatus();
    }

    emitStatus() {
        window.dispatchEvent(new CustomEvent('portflow-status', {
            detail: {
                connected:   this.connected,
                driver:      this.options.driver,
                baudRate:    this.options.baudRate,
                frames:      this.frames,
                retryCount:  this._retryCount,
            },
        }));

        if (window.Livewire) {
            window.Livewire.dispatch('portflow-status-updated', {
                connected:  this.connected,
                driver:     this.options.driver,
                baudRate:   this.options.baudRate,
                frames:     this.frames,
                retryCount: this._retryCount,
            });

            if (this.options.livewireStatusEvent !== 'portflow-status-updated') {
                window.Livewire.dispatch(this.options.livewireStatusEvent, {
                    connected:  this.connected,
                    driver:     this.options.driver,
                    baudRate:   this.options.baudRate,
                    frames:     this.frames,
                    retryCount: this._retryCount,
                });
            }
        }
    }
}

window.PortFlowBridge = PortFlowBridge;

window.getPortFlowBridge = function getPortFlowBridge() {
    if (!window.__portflowBridge) {
        window.__portflowBridge = new PortFlowBridge(window.portflowConfig ?? {});
    }

    return window.__portflowBridge;
};

window.portflowConnector = function portflowConnector() {
    const bridge = window.getPortFlowBridge();

    // When baud rate is explicitly set from Blade/server it MUST take priority.
    // Only fall back to localStorage when no explicit server value was provided.
    const serverBaudRate = bridge.options.baudRate;
    const isExplicit = !!bridge.options.baudRateExplicit;
    const rememberedBaudRate = (!isExplicit && bridge.options.rememberBaudRate)
        ? PortFlowBridge.loadRememberedBaudRate(serverBaudRate)
        : serverBaudRate;

    bridge.setBaudRate(rememberedBaudRate);

    return {
        connected:   false,
        connecting:  false,
        autoConnectOnLoad: !!bridge.options.autoConnectOnLoad,
        baudRate:    bridge.options.baudRate,
        frames:      0,
        retryCount:  0,
        framesProcessed: 0,
        lastChunk:   '',
        lastError:   '',
        supported:   PortFlowBridge.isSupported(),

        init() {
            this.connected = bridge.connected;
            this.baudRate = bridge.options.baudRate;
            this.frames = bridge.frames;
            this.retryCount = bridge._retryCount ?? 0;

            if (bridge.options.autoConnectOnLoad) {
                this.restoreConnection();
            }

            window.addEventListener('portflow-unsupported', () => {
                this.supported = false;
            });

            window.addEventListener('portflow-status', (e) => {
                this.connected  = e.detail.connected;
                this.baudRate   = e.detail.baudRate ?? this.baudRate;
                this.frames     = e.detail.frames;
                this.retryCount = e.detail.retryCount ?? 0;
            });

            window.addEventListener(bridge.options.browserChunkEvent, (e) => {
                this.lastChunk = e.detail.chunk ?? '';
                this.framesProcessed = e.detail.framesProcessed ?? 0;
                this.lastError = '';
            });

            window.addEventListener('portflow-error', (e) => {
                this.lastError = e.detail.message ?? 'Unknown PortFlow error';
            });
        },

        async restoreConnection() {
            if (this.connected || this.connecting || !this.supported) {
                return;
            }

            this.connecting = true;
            try {
                const restoredPort = await bridge.restorePort();

                if (!restoredPort) {
                    return;
                }

                await bridge.connect();
                this.connected = true;
                this.frames = bridge.frames;
                this.lastError = '';
            } catch (error) {
                this.lastError = error instanceof Error ? error.message : String(error);
            } finally {
                this.connecting = false;
            }
        },

        async connect() {
            if (this.connected || this.connecting) {
                return;
            }

            this.connecting = true;
            try {
                bridge.setBaudRate(this.baudRate);
                await bridge.connect();
                this.connected = true;
                this.frames    = bridge.frames;
                this.lastError = '';
            } catch (error) {
                this.lastError = error instanceof Error ? error.message : String(error);
            } finally {
                this.connecting = false;
            }
        },

        async disconnect() {
            try {
                await bridge.close();
                this.connected = false;
            } catch (error) {
                this.lastError = error instanceof Error ? error.message : String(error);
            }
        },
    };
};
