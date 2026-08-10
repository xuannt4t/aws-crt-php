/**
 * Nhãn tiếng Việt của các vai trò, và thứ tự cấp bậc của chúng.
 *
 * Bảng nhãn này trước đây được chép lại y hệt ở 4 file giao diện. Đặt chung một
 * chỗ để đổi tên vai trò chỉ phải sửa một lần.
 */
export const roleLabels: Record<string, string> = {
    system_admin: 'Quản trị hệ thống',
    director: 'Giám đốc',
    department_manager: 'Quản lý phòng ban',
    project_manager: 'Quản lý việc dự án',
    employee: 'Nhân viên',
    auditor: 'Kiểm toán viên',
};

export const roleLabel = (role: string | null | undefined): string =>
    role ? (roleLabels[role] ?? role) : 'Chưa gán vai trò';

// Cấp bậc từ cao xuống thấp, dùng để xếp thứ tự người trong cùng một đơn vị:
// trưởng phòng nổi lên trước nhân viên, thay vì trộn lẫn theo bảng chữ cái.
const ROLE_RANK: Record<string, number> = {
    system_admin: 0,
    director: 1,
    department_manager: 2,
    project_manager: 3,
    employee: 4,
    auditor: 5,
};

// Vai trò lạ (thêm sau này) xếp sau tất cả vai trò đã biết, nhưng vẫn đứng trước
// người chưa gán vai trò.
export const roleRank = (role: string | null | undefined): number => {
    if (!role) {
        return Number.MAX_SAFE_INTEGER;
    }

    return ROLE_RANK[role] ?? Number.MAX_SAFE_INTEGER - 1;
};
