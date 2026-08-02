# Sprint 3 — Project: Thiết kế

**Trạng thái:** đã được người dùng duyệt phần kiến trúc và dữ liệu (2026-08-02).

## 1. Mục tiêu

Sprint 3 bổ sung module Dự án đầy đủ, tích hợp trực tiếp vào hệ thống hiện có:

- Project CRUD.
- Thành viên dự án và vai trò trong dự án.
- Gắn công việc vào dự án (`tasks.project_id`).
- Tiến độ dự án tự tính từ công việc.
- Đóng dự án có kiểm tra công việc còn mở và ghi ngoại lệ.
- Dữ liệu demo cho Sản phẩm, Kỹ thuật, Kinh doanh và Vận hành.

Không làm trong sprint này: milestone, portfolio, budget, export dự án, báo cáo dự án.

## 2. Mô hình dữ liệu

### 2.1 `projects`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `organization_unit_id` | FK `organization_units` | bắt buộc, `restrictOnDelete` |
| `owner_id` | FK `users` | bắt buộc, `restrictOnDelete` |
| `code` | varchar(32) | unique, chữ in hoa/số/gạch dưới |
| `name` | varchar(160) | |
| `description` | text nullable | |
| `status` | varchar(32) | `ProjectStatus` |
| `start_date` | date nullable | |
| `end_date` | date nullable | `>= start_date` |
| `closed_at` | timestamp nullable | thời điểm đóng |
| `close_reason` | text nullable | chỉ có khi đóng ngoại lệ |
| `timestamps`, `softDeletes` | | |

Index: `unique(code)`, `index(organization_unit_id, status)`, `index(owner_id)`.

### 2.2 `project_members`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `project_id` | FK `projects` | `cascadeOnDelete` |
| `user_id` | FK `users` | `cascadeOnDelete` |
| `role` | varchar(16) | `ProjectMemberRole` |
| `joined_at` | timestamp | mặc định thời điểm thêm |
| `timestamps` | | |

Index: `unique(project_id, user_id)`, `index(user_id)`. Không dùng soft delete (pivot tạo lại được).

### 2.3 `tasks.project_id`

Cột nullable, FK `projects`, `nullOnDelete`, đặt sau `organization_unit_id`. Index `(project_id, status)`.

Một công việc có thể không thuộc dự án nào hoặc thuộc đúng một dự án.

## 3. Enum

`App\Enums\ProjectStatus` (backed string):

- `planning` — Lên kế hoạch
- `active` — Đang thực hiện
- `on_hold` — Tạm dừng
- `completed` — Hoàn thành
- `cancelled` — Đã huỷ

`App\Enums\ProjectMemberRole` (backed string):

- `manager` — Quản lý dự án
- `member` — Thành viên
- `viewer` — Người theo dõi

## 4. Quy tắc nghiệp vụ

### 4.1 Owner và thành viên

- Mỗi dự án thuộc đúng một đơn vị và có đúng một owner.
- Khi tạo dự án, owner được thêm tự động vào `project_members` với vai trò `manager`.
- Không được xoá owner khỏi danh sách thành viên; không được hạ vai trò của owner xuống dưới `manager`.
- Một người chỉ có một bản ghi thành viên trong một dự án.
- Đổi owner (khi cập nhật dự án) phải bảo đảm owner mới là thành viên vai trò `manager`.

### 4.2 Tiến độ

- Tiến độ dự án = trung bình `progress` của các công việc thuộc dự án, **không tính công việc đã huỷ** (`cancelled`), làm tròn về số nguyên.
- Dự án không có công việc (hoặc chỉ có công việc đã huỷ) hiển thị `0`.

### 4.3 Công việc còn mở

Công việc còn mở = `status` không thuộc `{completed, cancelled}`. Đây là cùng định nghĩa dùng ở `Task::scopeOverdue`.

### 4.4 Đóng dự án

- Chỉ đóng được dự án đang ở trạng thái khác `completed` và `cancelled`.
- Không còn công việc mở → đóng bình thường: `status = completed`, `closed_at = now()`, `close_reason = null`, audit `project.closed`.
- Còn công việc mở → **bắt buộc** nhập `close_reason` (10–1000 ký tự). Đóng thành công thì lưu `close_reason` và ghi audit `project.closed_with_exception`, metadata gồm `open_task_count` và danh sách tối đa 20 `open_task_ids`.
- Thiếu lý do khi còn công việc mở → lỗi validation trên trường `close_reason`.

### 4.5 Xoá

- Không xoá cứng dự án trong mọi trường hợp; `ProjectController::destroy` luôn soft delete và ghi audit `project.deleted`.
- Công việc thuộc dự án bị xoá mềm vẫn giữ `project_id`.

## 5. Phân quyền

Dùng permission đã có trong `App\Enums\PermissionName` (không thêm mới): `project.view`, `project.create`, `project.update`, `project.delete`, `project.manage_members`, `project.close`.

Nguyên tắc: **permission hệ thống quyết định phạm vi toàn hệ thống; vai trò trong dự án mở rộng quyền cho riêng dự án đó** (đúng `context/business-rules.md` mục 6: "Quyền dự án không thay thế hoàn toàn permission hệ thống").

`App\Policies\ProjectPolicy`:

| Ability | Điều kiện |
| --- | --- |
| `viewAny` | `project.view` |
| `view` | `project.view` **hoặc** là thành viên dự án |
| `create` | `project.create` |
| `update` | `project.update` **hoặc** là thành viên vai trò `manager` |
| `delete` | `project.delete` |
| `manageMembers` | `project.manage_members` **hoặc** là thành viên vai trò `manager` |
| `close` | `project.close` **hoặc** là thành viên vai trò `manager` |

`ProjectMemberPolicy` không cần thiết — mọi thao tác thành viên kiểm qua `manageMembers` trên `Project`.

Gắn công việc vào dự án: người dùng chỉ chọn được dự án mà họ `view` được và dự án chưa đóng (`completed`/`cancelled`).

## 6. Audit

Bổ sung `App\Enums\AuditAction`:

- `project.deleted`
- `project.closed`
- `project.closed_with_exception`
- `project.member_added`
- `project.member_role_updated`
- `project.member_removed`

## 7. Giao diện

- `resources/js/pages/Projects/Index.vue` — danh sách, bộ lọc (từ khoá, trạng thái, đơn vị, owner, chỉ dự án của tôi), thanh tiến độ, phân trang 20.
- `resources/js/pages/Projects/Create.vue`, `Edit.vue` — dùng chung `components/ProjectForm.vue`.
- `resources/js/pages/Projects/Show.vue` — thông tin dự án, tiến độ, danh sách thành viên (thêm/đổi vai trò/xoá), danh sách công việc thuộc dự án, nút Đóng dự án với hộp thoại nhập lý do khi còn công việc mở.
- `components/AppProjectStatusBadge.vue`, `components/ProjectMemberList.vue`, `components/ProjectProgressBar.vue`.
- Thêm mục "Dự án" vào `resources/js/layouts/AuthenticatedLayout.vue`.
- Trang công việc: form chọn dự án, bộ lọc theo dự án, hiển thị dự án ở danh sách và trang chi tiết.

Toàn bộ chuỗi hiển thị bằng tiếng Việt.

## 8. Kiểm thử

Pest, đặt tại `tests/Feature/Project/`:

- `ProjectModelTest.php` — quan hệ, tính tiến độ, scope công việc mở.
- `ProjectPolicyTest.php` — bảng phân quyền mục 5.
- `ProjectControllerTest.php` — CRUD, bộ lọc, phân trang, soft delete + audit.
- `ProjectMemberControllerTest.php` — thêm/đổi vai trò/xoá, ràng buộc owner, trùng thành viên.
- `ProjectClosingTest.php` — hai nhánh đóng dự án và audit.
- `ProjectBusinessRulesTest.php` — owner tự thành `manager`, `end_date >= start_date`, `code` unique.
- Bổ sung ca cho `tests/Feature/Task/TaskControllerTest.php` (chọn/lọc theo dự án) và `tests/Feature/DemoDataSeederTest.php`.
