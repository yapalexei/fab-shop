import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher, { AuthorizerCallback } from 'pusher-js';

window.Pusher = Pusher;

// Private channels authorize over /broadcasting/auth. Routed through axios
// (already configured with the app's session/XSRF cookies for every other
// request) instead of Pusher's own authorizer, which doesn't know about
// Laravel's CSRF cookie.
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    authorizer: (channel: { name: string }) => ({
        authorize: (socketId: string, callback: AuthorizerCallback) => {
            axios
                .post('/broadcasting/auth', {
                    socket_id: socketId,
                    channel_name: channel.name,
                })
                .then((response) => callback(null, response.data))
                .catch((error) => callback(error, null));
        },
    }),
});

export default window.Echo;
