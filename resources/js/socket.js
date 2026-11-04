/**
 * Socket bootstrap entry point.
 *
 * Compiled by Mix → public/plugins/socket.js
 * Include this script on any page that needs real-time features,
 * AFTER pusher.js (which sets up window.listener).
 *
 * Exposes to window:
 *   window.SocketManager        — singleton hub, shared across all features
 *   window.CalendarSocketModule — module class (instantiate per page use)
 *
 * Per-page usage in a Blade template:
 *
 *   <script>
 *     const socketModule = new CalendarSocketModule();
 *     SocketManager.register(socketModule);
 *     SocketManager.subscribe('tenant.{{ $encryptedTenantId }}');
 *   </script>
 */
import SocketManager from './socket/SocketManager';
import CalendarSocketModule from './socket/modules/CalendarSocketModule';
import BoardSocketModule from './socket/modules/BoardSocketModule';

window.SocketManager = new SocketManager();
window.CalendarSocketModule = CalendarSocketModule;
window.BoardSocketModule = BoardSocketModule;
