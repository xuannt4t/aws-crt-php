# Permission Matrix

## Role tham khảo

- `system_admin`
- `director`
- `department_manager`
- `project_manager`
- `employee`
- `auditor`

Role chỉ là tập permission. Business logic không nên hard-code role nếu permission giải quyết được.

## Permission chuẩn

### Organization

- `organization.view`
- `organization.create`
- `organization.update`
- `organization.delete`
- `organization.manage_members`

### User

- `user.view`
- `user.create`
- `user.update`
- `user.disable`
- `user.assign_role`

### Task

- `task.view`
- `task.create`
- `task.update`
- `task.delete`
- `task.assign`
- `task.comment`
- `task.submit`
- `task.approve`
- `task.reject`
- `task.export`
- `task.view_own`
- `task.view_department`
- `task.view_all`

### Project

- `project.view`
- `project.create`
- `project.update`
- `project.delete`
- `project.manage_members`
- `project.close`
- `project.export`
- `project.view_own`
- `project.view_department`
- `project.view_all`

### Report

- `report.view_own`
- `report.view_department`
- `report.view_all`
- `report.export`

### System

- `system.manage_settings`
- `system.view_audit_logs`

## Nguyên tắc phạm vi

Permission cho phép hành động, Policy xác định người dùng có được hành động trên bản ghi cụ thể hay không.

Ví dụ:

- Có `task.update` chưa chắc được sửa mọi task.
- Manager có thể sửa task thuộc phòng ban quản lý.
- Assignee có thể cập nhật tiến độ nhưng không nhất thiết được đổi owner.
- Auditor chỉ xem, không sửa.

## Permission phạm vi dữ liệu (`task.view_*` / `project.view_*`)

`task.view` / `project.view` vẫn là **cổng vào module**: có thì mở được màn hình, không có thì 403.

`task.view_own` / `task.view_department` / `task.view_all` và cặp tương ứng cho `project.*` quyết định
**những dòng nào** người dùng thấy được trong danh sách và mở trực tiếp được bằng URL. `App\Enums\DataScope`
biểu diễn ba mức này (`own` / `department` / `all`), và `App\Support\DataScopeResolver` tính mức hiệu lực
của một user cho công việc (`forTasks`) và dự án (`forProjects`): **mức rộng nhất mà người dùng đang giữ**;
không giữ mức nào thì mặc định `own`.

- `own` — bản ghi liên quan trực tiếp tới người dùng (được giao, do mình tạo, thuộc dự án mình tham gia...).
- `department` — như `own`, cộng thêm bản ghi thuộc đơn vị của người dùng hoặc bất kỳ đơn vị con nào
  (`OrganizationUnit::descendantIdsOf()`).
- `all` — không giới hạn.

`system_admin` không cần xử lý đặc biệt — vai trò này giữ `view_all` như mọi permission khác.

### Đường cơ sở theo vai trò (spec §6.4)

| Vai trò | Công việc | Dự án |
| --- | --- | --- |
| `system_admin` | `task.view_all` | `project.view_all` |
| `director` | `task.view_all` | `project.view_all` |
| `department_manager` | `task.view_department` | `project.view_department` |
| `project_manager` | `task.view_own` | `project.view_own` |
| `employee` | `task.view_own` | `project.view_own` |
| `auditor` | `task.view_all` | `project.view_all` |

`RolePermissionSeeder` là đường cơ sở chỉ áp dụng cho vai trò **chưa có permission nào** (cài đặt mới).
Vai trò đã có cấu hình (kể cả chỉnh qua màn hình ma trận phân quyền) thì seeder bỏ qua, không ghi đè.
