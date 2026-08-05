import { beforeEach, describe, expect, it, vi } from 'vitest';

const routerOn = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: (...args: unknown[]) => routerOn(...args),
    },
}));

import { installFlashToasts, isFlashToastsInstalled, resetFlashToastsForTesting } from './flashToasts';

describe('installFlashToasts', () => {
    beforeEach(() => {
        resetFlashToastsForTesting();
        routerOn.mockReset();
    });

    it('shows the flash of the initial page load once', () => {
        const add = vi.fn();

        installFlashToasts(add, { success: 'Tạo dự án thành công.', error: null });

        expect(add).toHaveBeenCalledTimes(1);
        expect(add).toHaveBeenCalledWith(
            expect.objectContaining({ severity: 'success', detail: 'Tạo dự án thành công.' }),
        );
    });

    it('registers exactly one success listener no matter how many times AppToast remounts', () => {
        installFlashToasts(vi.fn(), { success: null, error: null });
        installFlashToasts(vi.fn(), { success: null, error: null });
        installFlashToasts(vi.fn(), { success: null, error: null });

        expect(routerOn).toHaveBeenCalledTimes(1);
        expect(isFlashToastsInstalled()).toBe(true);
    });

    it('does not replay the initial flash when AppToast remounts after a navigation', () => {
        const first = vi.fn();
        installFlashToasts(first, { success: 'Tạo dự án thành công.', error: null });

        // Điều hướng xong, AppToast mount lại và truyền vào đúng flash cũ.
        const second = vi.fn();
        installFlashToasts(second, { success: 'Tạo dự án thành công.', error: null });

        expect(first).toHaveBeenCalledTimes(1);
        expect(second).not.toHaveBeenCalled();
    });

    it('shows the flash carried by a later inertia success event', () => {
        const add = vi.fn();
        installFlashToasts(add, { success: null, error: null });

        const handler = routerOn.mock.calls[0][1] as (event: unknown) => void;
        handler({ detail: { page: { props: { flash: { success: 'Đã đóng dự án.', error: null } } } } });

        expect(add).toHaveBeenCalledTimes(1);
        expect(add).toHaveBeenCalledWith(expect.objectContaining({ detail: 'Đã đóng dự án.' }));
    });

    it('shows both a success and an error carried by the same response', () => {
        const add = vi.fn();

        installFlashToasts(add, { success: 'Đã lưu.', error: 'Một phần thất bại.' });

        expect(add).toHaveBeenCalledTimes(2);
        expect(add).toHaveBeenNthCalledWith(1, expect.objectContaining({ severity: 'success' }));
        expect(add).toHaveBeenNthCalledWith(2, expect.objectContaining({ severity: 'error' }));
    });
});
