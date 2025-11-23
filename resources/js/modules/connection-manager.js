/**
 * WebSocket Connection Manager with reconnection logic
 */
export class ConnectionManager {
    constructor(indicator, statusText, statusDot) {
        this.indicator = indicator;
        this.statusText = statusText;
        this.statusDot = statusDot;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectDelay = 1000;
        this.isConnected = false;
        this.listeners = [];

        this.init();
    }

    init() {
        if (!window.Echo) {
            console.warn('Echo not initialized');
            this.setDisconnected();
            return;
        }

        // Listen to Pusher connection events
        const pusher = window.Echo.connector.pusher;

        pusher.connection.bind('connected', () => {
            this.handleConnected();
        });

        pusher.connection.bind('disconnected', () => {
            this.handleDisconnected();
        });

        pusher.connection.bind('error', (err) => {
            this.handleError(err);
        });

        pusher.connection.bind('unavailable', () => {
            this.handleUnavailable();
        });

        pusher.connection.bind('failed', () => {
            this.handleFailed();
        });

        // Check initial state
        if (pusher.connection.state === 'connected') {
            this.handleConnected();
        } else {
            this.setConnecting();
        }
    }

    handleConnected() {
        console.log('WebSocket connected');
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.setConnected();
        this.notifyListeners('connected');
    }

    handleDisconnected() {
        console.log('WebSocket disconnected');
        this.isConnected = false;
        this.setDisconnected();
        this.notifyListeners('disconnected');
        this.attemptReconnect();
    }

    handleError(err) {
        console.error('WebSocket error:', err);
        this.setError();
        this.notifyListeners('error', err);
    }

    handleUnavailable() {
        console.warn('WebSocket unavailable');
        this.setDisconnected();
        this.attemptReconnect();
    }

    handleFailed() {
        console.error('WebSocket connection failed');
        this.setFailed();
        this.notifyListeners('failed');
    }

    attemptReconnect() {
        if (this.reconnectAttempts >= this.maxReconnectAttempts) {
            console.error('Max reconnection attempts reached');
            this.setFailed();
            return;
        }

        this.reconnectAttempts++;
        const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1);

        console.log(`Reconnecting in ${delay}ms (attempt ${this.reconnectAttempts}/${this.maxReconnectAttempts})`);
        this.setReconnecting(this.reconnectAttempts);

        setTimeout(() => {
            if (!this.isConnected) {
                window.Echo.connector.pusher.connect();
            }
        }, delay);
    }

    setConnected() {
        this.indicator.className = 'bg-green-50 border border-green-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'animate-pulse h-5 w-5 text-green-500';
        this.statusText.innerHTML = '<span class="text-sm font-medium text-green-800">Connected - Real-time updates active</span>';
        this.indicator.classList.remove('hidden');
    }

    setConnecting() {
        this.indicator.className = 'bg-blue-50 border border-blue-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'animate-spin h-5 w-5 text-blue-500';
        this.statusText.innerHTML = '<span class="text-sm font-medium text-blue-800">Connecting...</span>';
        this.indicator.classList.remove('hidden');
    }

    setReconnecting(attempt) {
        this.indicator.className = 'bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'animate-pulse h-5 w-5 text-yellow-500';
        this.statusText.innerHTML = `<span class="text-sm font-medium text-yellow-800">Reconnecting (attempt ${attempt}/${this.maxReconnectAttempts})...</span>`;
        this.indicator.classList.remove('hidden');
    }

    setDisconnected() {
        this.indicator.className = 'bg-gray-50 border border-gray-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'h-5 w-5 text-gray-400';
        this.statusText.innerHTML = '<span class="text-sm font-medium text-gray-700">Disconnected - Updates paused</span>';
        this.indicator.classList.remove('hidden');
    }

    setError() {
        this.indicator.className = 'bg-red-50 border border-red-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'h-5 w-5 text-red-500';
        this.statusText.innerHTML = '<span class="text-sm font-medium text-red-800">Connection error - Retrying...</span>';
        this.indicator.classList.remove('hidden');
    }

    setFailed() {
        this.indicator.className = 'bg-red-50 border border-red-200 rounded-lg px-4 py-2 mb-4';
        this.statusDot.className = 'h-5 w-5 text-red-500';
        this.statusText.innerHTML = `
            <div>
                <span class="text-sm font-medium text-red-800">Connection failed</span>
                <button onclick="window.location.reload()" class="ml-3 text-xs px-2 py-1 bg-red-100 hover:bg-red-200 rounded">
                    Reload Page
                </button>
            </div>
        `;
        this.indicator.classList.remove('hidden');
    }

    onStateChange(callback) {
        this.listeners.push(callback);
    }

    notifyListeners(state, data = null) {
        this.listeners.forEach(callback => callback(state, data));
    }
}
