import { describe, expect, it } from 'vitest';
import { resolveRealtimeOptions } from './realtimeConfig';

describe('resolveRealtimeOptions', () => {
    it('uses same-host WSS when production variables omit host and scheme', () => {
        expect(
            resolveRealtimeOptions(
                { VITE_REVERB_APP_KEY: 'public-key' },
                {
                    hostname: 'dormida-work.onrender.com',
                    protocol: 'https:',
                },
            ),
        ).toEqual({
            key: 'public-key',
            wsHost: 'dormida-work.onrender.com',
            wsPort: 80,
            wssPort: 443,
            forceTLS: true,
            enabledTransports: ['ws', 'wss'],
        });
    });
});
