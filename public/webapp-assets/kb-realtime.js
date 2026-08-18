/**
 * kb-realtime — a minimal Pusher-protocol client for Laravel Reverb.
 *
 * Why hand-written instead of laravel-echo + pusher-js: the web app ships as
 * static, self-hosted assets with no bundler (Alpine is a plain <script> too, and
 * the CSP forbids third-party origins). Those two packages together are ~120 KB
 * and would need a Vite entry point the web app does not have. All this page
 * needs is one private channel per open chat, so the slice of the protocol below
 * is the whole requirement:
 *
 *   connect  → wss://{host}:{port}/app/{key}?protocol=7
 *   server   → pusher:connection_established { socket_id }
 *   auth     → POST /broadcasting/auth { socket_id, channel_name } → { auth }
 *   client   → pusher:subscribe { channel, auth }
 *   server   → message.sent on private-chat.thread.{id}
 *
 * Reverb speaks the Pusher protocol, so this also works against Pusher itself.
 *
 * The socket is OPTIONAL by design. `REVERB_APP_KEY` is unset until the Reverb
 * daemon is deployed (BE-IF-18), and `state` reports `disabled` then — callers
 * are expected to poll while `isLive()` is false, so chat works either way.
 */
(function () {
    'use strict';

    /** Pusher close codes 4000–4099 are fatal: reconnecting cannot fix them. */
    function isFatalCloseCode(code) {
        return code >= 4000 && code <= 4099;
    }

    window.kbRealtime = {
        /** 'disabled' | 'connecting' | 'connected' | 'reconnecting' | 'failed' */
        state: 'disabled',
        socket: null,
        socketId: null,
        config: null,
        /** channel name → { bindings: {event: [fn]}, subscribed: bool } */
        channels: {},
        attempt: 0,
        pingTimer: null,
        reconnectTimer: null,
        onStateChange: null,

        /** True only when a socket is open AND the handshake produced a socket id. */
        isLive() {
            return this.state === 'connected' && this.socketId !== null;
        },

        setState(state) {
            if (this.state === state) {
                return;
            }
            this.state = state;
            if (typeof this.onStateChange === 'function') {
                this.onStateChange(state);
            }
        },

        /**
         * Open the socket. Safe to call repeatedly (a live socket is kept).
         * Returns false when real-time is not configured, so the caller polls.
         */
        connect(config) {
            if (config) {
                this.config = config;
            }
            if (!this.config || !this.config.key || !this.config.host) {
                this.setState('disabled');
                return false;
            }
            if (this.socket && (this.socket.readyState === 0 || this.socket.readyState === 1)) {
                return true;
            }

            const tls = String(this.config.scheme || 'https') === 'https';
            const port = this.config.port || (tls ? 443 : 80);
            const url = (tls ? 'wss://' : 'ws://') + this.config.host + ':' + port
                + '/app/' + encodeURIComponent(this.config.key)
                + '?protocol=7&client=kb-web&version=1.0&flash=false';

            this.setState(this.attempt === 0 ? 'connecting' : 'reconnecting');

            try {
                this.socket = new WebSocket(url);
            } catch (e) {
                this.scheduleReconnect();
                return true;
            }

            this.socket.onopen = () => { /* wait for pusher:connection_established */ };
            this.socket.onmessage = (event) => this.handle(event);
            this.socket.onerror = () => { /* onclose always follows */ };
            this.socket.onclose = (event) => {
                this.socketId = null;
                this.stopPing();
                Object.keys(this.channels).forEach((name) => { this.channels[name].subscribed = false; });
                if (isFatalCloseCode(event.code)) {
                    // Bad app key, unauthorized origin, over quota — retrying is futile.
                    this.setState('failed');
                    return;
                }
                this.scheduleReconnect();
            };

            return true;
        },

        /** Full teardown: no reconnect, no timers, bindings dropped. */
        disconnect() {
            this.stopPing();
            if (this.reconnectTimer) {
                clearTimeout(this.reconnectTimer);
                this.reconnectTimer = null;
            }
            this.channels = {};
            this.socketId = null;
            if (this.socket) {
                this.socket.onclose = null;
                try { this.socket.close(); } catch (e) { /* already gone */ }
                this.socket = null;
            }
            this.setState(this.config && this.config.key ? 'connecting' : 'disabled');
            this.attempt = 0;
        },

        scheduleReconnect() {
            if (this.reconnectTimer) {
                return;
            }
            // 1s, 2s, 4s, 8s, 16s, then every 30s. Long threads stay usable via
            // polling in the meantime, so a slow backoff costs nothing.
            const delay = Math.min(1000 * Math.pow(2, this.attempt), 30000);
            this.attempt += 1;
            this.setState('reconnecting');
            this.reconnectTimer = setTimeout(() => {
                this.reconnectTimer = null;
                this.connect();
            }, delay);
        },

        startPing(seconds) {
            this.stopPing();
            // Half the server's activity timeout, the interval pusher-js uses.
            const every = Math.max(10, Math.floor((seconds || 120) / 2)) * 1000;
            this.pingTimer = setInterval(() => this.send('pusher:ping', {}), every);
        },

        stopPing() {
            if (this.pingTimer) {
                clearInterval(this.pingTimer);
                this.pingTimer = null;
            }
        },

        send(event, data) {
            if (!this.socket || this.socket.readyState !== 1) {
                return;
            }
            try {
                this.socket.send(JSON.stringify({ event: event, data: data }));
            } catch (e) { /* the close handler reconnects */ }
        },

        handle(raw) {
            let frame;
            try { frame = JSON.parse(raw.data); } catch (e) { return; }

            // Pusher wraps app payloads as a JSON *string* in `data`.
            let payload = frame.data;
            if (typeof payload === 'string') {
                try { payload = JSON.parse(payload); } catch (e) { /* keep the string */ }
            }

            if (frame.event === 'pusher:connection_established') {
                this.socketId = payload && payload.socket_id ? payload.socket_id : null;
                this.attempt = 0;
                this.setState('connected');
                this.startPing(payload && payload.activity_timeout);
                // Re-subscribe everything the page asked for before/while offline.
                Object.keys(this.channels).forEach((name) => this.subscribeNow(name));
                return;
            }

            if (frame.event === 'pusher:ping') {
                this.send('pusher:pong', {});
                return;
            }

            if (frame.event === 'pusher:subscription_succeeded') {
                if (this.channels[frame.channel]) {
                    this.channels[frame.channel].subscribed = true;
                }
                return;
            }

            if (frame.event === 'pusher:subscription_error' || frame.event === 'pusher:error') {
                // Authorization failed (403 from /broadcasting/auth) or a protocol
                // error. Leave it unsubscribed — polling still delivers messages.
                return;
            }

            if (frame.event === 'pusher:pong' || String(frame.event).indexOf('pusher_internal:') === 0) {
                if (frame.event === 'pusher_internal:subscription_succeeded' && this.channels[frame.channel]) {
                    this.channels[frame.channel].subscribed = true;
                }
                return;
            }

            const channel = this.channels[frame.channel];
            if (!channel) {
                return;
            }
            (channel.bindings[frame.event] || []).forEach((fn) => {
                try { fn(payload); } catch (e) { /* one bad handler must not kill the socket */ }
            });
        },

        /**
         * Listen for `event` on a private channel. `name` is the Laravel channel
         * name WITHOUT the `private-` prefix (e.g. `chat.thread.{id}`).
         */
        listen(name, event, callback) {
            const channel = 'private-' + name;
            if (!this.channels[channel]) {
                this.channels[channel] = { bindings: {}, subscribed: false };
            }
            const bindings = this.channels[channel].bindings;
            bindings[event] = (bindings[event] || []).concat(callback);

            if (this.isLive() && !this.channels[channel].subscribed) {
                this.subscribeNow(channel);
            }
            return channel;
        },

        /** Stop listening and tell the server to unsubscribe. */
        leave(name) {
            const channel = name.indexOf('private-') === 0 ? name : 'private-' + name;
            if (!this.channels[channel]) {
                return;
            }
            delete this.channels[channel];
            this.send('pusher:unsubscribe', { channel: channel });
        },

        async subscribeNow(channel) {
            if (!this.isLive() || !this.channels[channel]) {
                return;
            }
            const auth = await this.authorize(channel);
            if (!auth || !this.channels[channel]) {
                return;
            }
            this.send('pusher:subscribe', { channel: channel, auth: auth });
        },

        /**
         * Sign a private channel through Laravel's broadcasting auth endpoint.
         * Note the path: /broadcasting/auth sits at the app root, NOT under
         * /api/v1, so it cannot go through window.kb.api(). It is Sanctum-guarded,
         * hence the bearer token — and a stale token is refreshed once, the same
         * one-shot retry the REST client uses.
         */
        async authorize(channel, retried) {
            const token = window.kb ? window.kb.token : null;
            if (!token) {
                return null;
            }
            let res;
            try {
                res = await fetch('/broadcasting/auth', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                    },
                    body: JSON.stringify({ socket_id: this.socketId, channel_name: channel }),
                });
            } catch (e) {
                return null;
            }
            if (res.status === 401 && !retried && window.kb && window.kb.refreshToken) {
                if (await window.kb.refresh()) {
                    return this.authorize(channel, true);
                }
                return null;
            }
            if (!res.ok) {
                return null;
            }
            try {
                const json = await res.json();
                return json.auth || null;
            } catch (e) {
                return null;
            }
        },
    };
})();
