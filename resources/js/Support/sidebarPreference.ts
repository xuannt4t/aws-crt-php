/**
 * `AuthenticatedLayout` mount lại sau mỗi lần điều hướng (layout đặt trong
 * template chứ không phải layout bền), nên trạng thái thu nhỏ không thể chỉ nằm
 * trong `ref` của component — bấm thu nhỏ xong mở trang khác là sidebar bung ra
 * lại. Vì vậy lựa chọn được ghi xuống `localStorage` và đọc lại ngay lúc setup.
 *
 * Đọc đồng bộ trong `setup` chứ không phải `onMounted`: lần render đầu tiên đã
 * có đúng bề rộng, người dùng không thấy sidebar nháy từ rộng sang hẹp.
 *
 * Mọi truy cập đều bọc try/catch vì `localStorage` có thể ném lỗi khi trình
 * duyệt chặn cookie của bên thứ ba hoặc khi chạy ở chế độ riêng tư đã đầy bộ
 * nhớ. Không đọc/ghi được thì coi như sidebar mở — mất một tuỳ chọn giao diện
 * không đáng để làm hỏng cả trang.
 */
const STORAGE_KEY = 'dormida.sidebar.collapsed';

export const readSidebarCollapsed = (): boolean => {
    try {
        return window.localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
};

export const writeSidebarCollapsed = (collapsed: boolean): void => {
    try {
        window.localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    } catch {
        // Không lưu được thì lần tải trang sau quay về mặc định, chấp nhận được.
    }
};
