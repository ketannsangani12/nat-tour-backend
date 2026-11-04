/**
 * SocketManager
 *
 * Central hub for all real-time WebSocket communication.
 * Sits between Laravel Echo (window.listener) and feature modules.
 *
 * Usage (per page):
 *
 *   // 1. Register the feature module(s) needed on this page
 *   SocketManager.register(new CalendarSocketModule());
 *
 *   // 2. Subscribe to the tenant channel once
 *   SocketManager.subscribe('tenant.ENCRYPTED_ID');
 *
 *   // 3. Teardown when leaving (optional, e.g. SPA navigation)
 *   SocketManager.unsubscribe('tenant.ENCRYPTED_ID');
 *
 * Adding a new feature:
 *   - Create a module class implementing { feature, events, handle(entity, operation, payload) }
 *   - Register it before calling subscribe()
 *   - No changes needed here
 */
class SocketManager {
    constructor() {
        /** @type {Object.<string, Object>} feature name → module instance */
        this._modules = {};

        /** @type {Object.<string, Object>} channelName → Echo channel */
        this._channels = {};
    }

    /**
     * Register a feature module.
     * Must be called before subscribe() for the module to receive events.
     *
     * A module may handle one or more features:
     *   - Single feature:   { feature: 'calendar', events: [...], handle() }
     *   - Multiple features: { features: ['calendar', 'booking'], events: [...], handle() }
     *
     * @param {Object} module
     */
    register(module) {
        const features = Array.isArray(module.features)
            ? module.features
            : (module.feature ? [module.feature] : null);

        if (!features || !Array.isArray(module.events)) {
            console.error('[SocketManager] Module must define feature/features and events', module);
            return;
        }

        features.forEach((f) => {
            this._modules[f] = module;
        });
    }

    /**
     * Subscribe to a channel and wire up all registered module event listeners.
     * Safe to call multiple times — reuses an existing channel subscription.
     *
     * @param {string} channelName  e.g. 'tenant.ENCRYPTED_ID'
     */
    subscribe(channelName) {
        if (this._channels[channelName]) {
            return; // already subscribed
        }

        if (!window.listener) {
            console.error('[SocketManager] window.listener (Echo) is not initialised. Load pusher.js first.');
            return;
        }

        const channel = window.listener.channel(channelName);
        this._channels[channelName] = channel;

        Object.values(this._modules).forEach((module) => {
            module.events.forEach((eventName) => {
                channel.listen('.' + eventName, (data) => {
                    this._dispatch(data);
                });
            });
        });
    }

    /**
     * Unsubscribe from a channel and remove its listeners.
     *
     * @param {string} channelName
     */
    unsubscribe(channelName) {
        if (!this._channels[channelName]) return;

        window.listener.leaveChannel(channelName);
        delete this._channels[channelName];
    }

    /**
     * Route an incoming event payload to the correct feature module.
     * Called internally by each channel listener.
     *
     * @param {Object} data - { feature, entity, operation, payload, timestamp }
     */
    _dispatch(data) {
        const { feature, entity, operation, payload } = data;

        if (!feature || !entity || !operation) {
            console.warn('[SocketManager] Received malformed event (missing feature/entity/operation)', data);
            return;
        }

        const module = this._modules[feature];
        if (!module) {
            // Not registered on this page — silently ignore
            return;
        }

        try {
            module.handle(entity, operation, payload);
        } catch (err) {
            console.error(`[SocketManager] Error in module "${feature}" handling ${entity}.${operation}`, err);
        }
    }
}

export default SocketManager;
