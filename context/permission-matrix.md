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

### Project

- `project.view`
- `project.create`
- `project.update`
- `project.delete`
- `project.manage_members`
- `project.close`
- `project.export`

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
