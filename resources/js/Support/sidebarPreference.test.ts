import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { readSidebarCollapsed, writeSidebarCollapsed } from './sidebarPreference';

// Bộ test chạy trên môi trường `node`, không có DOM, nên `window` phải được dựng
// thủ công. Cũng nhờ vậy mà tự nhiên kiểm luôn được nhánh "trình duyệt chặn
// localStorage" — chỉ cần cho hàm giả ném lỗi.
const useStorage = (impl: Partial<Storage>): void => {
    vi.stubGlobal('window', { localStorage: impl as Storage });
};

const useWorkingStorage = (): void => {
    const store = new Map<string, string>();

    useStorage({
        getItem: (key: string) => store.get(key) ?? null,
        setItem: (key: string, value: string) => {
            store.set(key, value);
        },
    });
};

describe('sidebarPreference', () => {
    beforeEach(() => {
        useWorkingStorage();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('defaults to an expanded sidebar when nothing has been saved', () => {
        expect(readSidebarCollapsed()).toBe(false);
    });

    it('remembers the collapsed choice across a page load', () => {
        writeSidebarCollapsed(true);

        expect(readSidebarCollapsed()).toBe(true);
    });

    it('remembers the expanded choice instead of falling back to the default', () => {
        writeSidebarCollapsed(true);
        writeSidebarCollapsed(false);

        expect(readSidebarCollapsed()).toBe(false);
    });

    it('falls back to expanded when localStorage cannot be read', () => {
        useStorage({
            getItem: () => {
                throw new Error('Access to storage is denied');
            },
        });

        expect(readSidebarCollapsed()).toBe(false);
    });

    it('does not blow up the page when localStorage cannot be written', () => {
        useStorage({
            setItem: () => {
                throw new Error('QuotaExceededError');
            },
        });

        expect(() => writeSidebarCollapsed(true)).not.toThrow();
    });

    it('survives a server-side render where there is no window at all', () => {
        vi.unstubAllGlobals();

        expect(readSidebarCollapsed()).toBe(false);
        expect(() => writeSidebarCollapsed(true)).not.toThrow();
    });
});
