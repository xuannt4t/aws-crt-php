import { router } from '@inertiajs/vue3';

/**
 * Cho phép bấm vào bất kỳ đâu trên một hàng bảng để mở trang chi tiết.
 *
 * Điều kiện bắt buộc: ô tên trong hàng vẫn phải là một thẻ `<Link>` thật. Hàng
 * `<tr>` không nhận được focus bàn phím, nên nếu bỏ link đi thì người dùng bàn
 * phím và trình đọc màn hình sẽ không còn đường nào vào chi tiết. Xử lý ở đây
 * chỉ là phần bổ sung cho chuột.
 *
 * Ba trường hợp phải nhường lại, nếu không sẽ phá những thao tác đang chạy tốt:
 *
 * 1. Bấm trúng một phần tử tương tác lồng bên trong (link việc dự án, nút xoá, ô
 *    select đổi vai trò...). Để phần tử đó tự xử lý.
 * 2. Người dùng đang bôi đen chữ trong hàng — nhả chuột sau khi bôi đen cũng
 *    sinh ra sự kiện `click`, mà điều hướng lúc đó thì mất luôn phần vừa chọn.
 * 3. Bấm giữ Ctrl/Cmd/Shift hoặc bấm chuột giữa. Người dùng đang muốn mở tab
 *    mới, nên phải mở tab mới thật chứ không điều hướng tại chỗ.
 */
const INTERACTIVE_SELECTOR = 'a, button, input, select, textarea, label, summary, [role="button"], [data-row-ignore]';

// PrimeVue chỉ khai báo `originalEvent` là `Event`, nên nhận kiểu rộng rồi tự
// thu hẹp ở đây, thay vì ép kiểu ở nơi gọi và tin rằng nó luôn là MouseEvent.
const wantsNewTab = (event: Event): boolean =>
    event instanceof MouseEvent &&
    (event.metaKey || event.ctrlKey || event.shiftKey || event.button === 1);

export const navigateRow = (event: Event, href: string): void => {
    const target = event.target as HTMLElement | null;

    if (target?.closest(INTERACTIVE_SELECTOR)) {
        return;
    }

    if ((window.getSelection()?.toString() ?? '') !== '') {
        return;
    }

    if (wantsNewTab(event)) {
        window.open(href, '_blank', 'noopener');

        return;
    }

    router.visit(href);
};
