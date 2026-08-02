import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { resolveRealtimeOptions } from './Support/realtimeConfig';

const isBrowser = typeof window !== 'undefined';

if (isBrowser) {
    window.Pusher = Pusher;
}

export const realtimeEcho = (() => {
    if (!isBrowser) {
        return null;
    }

    const options = resolveRealtimeOptions(import.meta.env, window.location);

    if (!options.key) {
        return null;
    }

    return new Echo({
        broadcaster: 'reverb',
        ...options,
        key: options.key,
    });
})();
