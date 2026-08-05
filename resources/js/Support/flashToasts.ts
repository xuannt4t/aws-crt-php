import { router } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

type ToastAdd = (options: { severity: 'success' | 'error'; summary: string; detail: string; life: number }) => void;

/**
 * `AppToast` nằm trong layout, mà các trang dùng layout theo kiểu không bền
 * (đặt trong template chứ không phải `defineOptions({ layout })`), nên component
 * này mount lại sau mỗi lần điều hướng.
 *
 * Trước đây mỗi instance vừa đọc flash lúc `onMounted`, vừa tự đăng ký listener
 * `router.on('success')`. Sau một lần điều hướng, listener của instance cũ vẫn
 * còn sống khi sự kiện `success` bắn ra, rồi instance mới lại đọc đúng flash đó
 * lúc mount — cùng một thông báo hiện hai lần.
 *
 * Ở đây listener chỉ được đăng ký MỘT lần cho cả vòng đời ứng dụng, và flash
 * của lần tải trang đầu tiên cũng chỉ đọc đúng một lần. Không dùng cách so
 * chuỗi để khử trùng, vì hai thông báo giống hệt nhau liên tiếp là hợp lệ và
 * phải hiện đủ cả hai.
 */
let installed = false;

export const resetFlashToastsForTesting = (): void => {
    installed = false;
};

export const isFlashToastsInstalled = (): boolean => installed;

export const installFlashToasts = (add: ToastAdd, initialFlash: PageProps['flash']): void => {
    if (installed) {
        return;
    }

    installed = true;

    const showFlash = (flash: PageProps['flash'] | undefined): void => {
        if (flash?.success) {
            add({
                severity: 'success',
                summary: 'Thành công',
                detail: flash.success,
                life: 4000,
            });
        }

        if (flash?.error) {
            add({
                severity: 'error',
                summary: 'Không thể thực hiện',
                detail: flash.error,
                life: 6000,
            });
        }
    };

    // Lần tải trang đầu tiên không sinh ra sự kiện `success`, nên phải đọc tay.
    showFlash(initialFlash);

    // Mọi điều hướng sau đó — kể cả thao tác `preserveState` không remount
    // component — đều đi qua đây.
    router.on('success', (event) => {
        showFlash((event.detail.page.props as unknown as PageProps).flash);
    });
};
