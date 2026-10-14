import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher, { AuthorizerCallback } from 'pusher-js';

window.Pusher = Pusher;

// Where to connect. The cloud build bakes these in (VITE_REVERB_*); a build
// without them - the self-hosted image, which can't know its customer's
// hostname - connects to the page's own origin, where the reverse proxy sends
// /app/* to Reverb. The app key is public by design (the secret never leaves
// the server), so the server hands it over in a <meta> tag at runtime.
const sameOrigin = !import.meta.env.VITE_REVERB_HOST;
const secure =
    (import.meta.env.VITE_REVERB_SCHEME ||
        (sameOrigin ? window.location.protocol.replace(':', '') : 'https')) ===
    'https';
const port = Number(
    import.meta.env.VITE_REVERB_PORT ||
        (sameOrigin ? window.location.port : '') ||
        (secure ? 443 : 80),
);

// Private channels authorize over /broadcasting/auth. Routed through axios
// (already configured with the app's session/XSRF cookies for every other
// request) instead of Pusher's own authorizer, which doesn't know about
// Laravel's CSRF cookie.
window.Echo = new Echo({
    broadcaster: 'reverb',
    key:
        document.querySelector<HTMLMetaElement>('meta[name="reverb-key"]')
            ?.content || import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: secure,
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

// Tag this browser's own requests with its socket id so `->toOthers()`
// broadcasts skip it - otherwise the mover's board reloads itself mid-
// redirect. Echo would normally add this itself, but only if a global
// `axios` exists when it's constructed, and bootstrap.ts's hoisted
// `import './echo'` runs before `window.axios` is assigned.
axios.interceptors.request.use((config) => {
    const socketId = window.Echo.socketId();

    if (socketId) {
        config.headers['X-Socket-Id'] = socketId;
    }

    return config;
});

export default window.Echo;
