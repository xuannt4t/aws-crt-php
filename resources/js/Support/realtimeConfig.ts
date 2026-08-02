type RealtimeEnvironment = Partial<
    Record<'VITE_REVERB_APP_KEY' | 'VITE_REVERB_HOST' | 'VITE_REVERB_PORT' | 'VITE_REVERB_SCHEME', string>
>;

type BrowserLocation = Pick<Location, 'hostname' | 'protocol'>;

export const resolveRealtimeOptions = (environment: RealtimeEnvironment, location: BrowserLocation) => {
    const scheme = environment.VITE_REVERB_SCHEME ?? (location.protocol === 'https:' ? 'https' : 'http');

    return {
        key: environment.VITE_REVERB_APP_KEY,
        wsHost: environment.VITE_REVERB_HOST ?? location.hostname,
        wsPort: Number(environment.VITE_REVERB_PORT ?? 80),
        wssPort: Number(environment.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'] as ['ws', 'wss'],
    };
};
