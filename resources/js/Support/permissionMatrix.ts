// Quyền phạm vi dữ liệu mới của tính năng này chỉ thuộc hai module "task" và "project",
// dạng "<module>.view_own" / "<module>.view_department" / "<module>.view_all" — module và
// hậu tố cách nhau bằng dấu chấm, không phải gạch dưới. `report.view_*` đã có sẵn ba mức
// riêng từ trước và không thuộc phạm vi của màn hình này nên cố tình không khớp ở đây.
const SCOPE_PERMISSION_PATTERN = /^(task|project)\.(view_own|view_department|view_all)$/;

/**
 * Nhận diện quyền phạm vi dữ liệu (vd. "task.view_own", "project.view_all") để gắn nhãn
 * "Phạm vi" và tô nền riêng trên màn hình ma trận phân quyền.
 */
export const isScopePermission = (permissionName: string): boolean => SCOPE_PERMISSION_PATTERN.test(permissionName);
