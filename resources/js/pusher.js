import Echo from 'laravel-echo'; // Import Laravel Echo

// Attach Pusher to the global `window` object
window.Pusher = require('pusher-js');

// Enable Pusher logging in the console for debugging (remove in production)
// window.Pusher.logToConsole = true;

// Initialize Laravel Echo
window.listener = new Echo({
    broadcaster: 'pusher', // Specify the broadcaster (Pusher in this case)
    key: process.env.MIX_PUSHER_APP_KEY, // Your Pusher App Key from `.env`
    cluster: process.env.MIX_PUSHER_APP_CLUSTER, // Your Pusher Cluster from `.env`
    forceTLS: true // Enforce secure WebSocket connection
});
