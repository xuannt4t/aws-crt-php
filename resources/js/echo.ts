import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

const key = import.meta.env.VITE_REVERB_APP_KEY;
const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'http';
const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    window.Pusher = Pusher;
}

export const realtimeEcho =
    isBrowser && key
        ? new Echo({
              broadcaster: 'reverb',
              key,
              wsHost: import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
              wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
              wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
              forceTLS: scheme === 'https',
              enabledTransports: ['ws', 'wss'],
          })
        : null;
