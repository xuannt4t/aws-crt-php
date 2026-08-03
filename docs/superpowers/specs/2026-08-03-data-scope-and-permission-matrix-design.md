# Phạm vi dữ liệu theo vai trò và màn hình ma trận phân quyền — Thiết kế

**Trạng thái:** đã chốt các lựa chọn với người dùng (2026-08-03).

## 1. Vấn đề

Hiện tại `task.view` và `project.view` là quyền xem **toàn hệ thống**: nhân viên thấy đúng những gì giám đốc thấy. `context/permission-matrix.md` đã nêu nguyên tắc đúng — "Permission cho phép hành động, Policy xác định người dùng có được hành động trên bản ghi cụ thể hay không" — nhưng phần lọc theo bản ghi chưa được cài.

Ngoài ra ma trận vai trò × quyền chỉ tồn tại trong `RolePermissionSeeder`; muốn đổi phải sửa code và triển khai lại.

## 2. Lựa chọn đã chốt

| Câu hỏi | Quyết định |
| --- | --- |
| Cơ chế phạm vi | Ba mức chuẩn `của tôi` / `đơn vị` / `toàn bộ`, khai báo bằng permission, chỉnh được trên màn hình vai trò. |
| "Của tôi" với công việc | Việc được giao cho tôi, việc tôi tạo, việc thuộc dự án tôi tham gia, và việc con của những việc đó. |
| Phạm vi đơn vị | Đơn vị của người dùng và **tất cả đơn vị con** theo cây tổ chức. |
| Màn hình phân quyền | Xem và chỉnh ma trận vai trò × quyền, lưu vào CSDL, ghi audit. |

Không làm trong phạm vi này: tạo/xoá vai trò tuỳ biến, phân quyền theo từng người dùng riêng lẻ, phạm vi dữ liệu cho báo cáo (`report.*` đã có sẵn ba mức riêng và không đụng tới ở đây).

## 3. Permission phạm vi

Thêm vào `App\Enums\PermissionName`:

- `task.view_own`, `task.view_department`, `task.view_all`
- `project.view_own`, `project.view_department`, `project.view_all`

Song song với `report.view_own` / `report.view_department` / `report.view_all` đã có.

### 3.1 Quan hệ với `task.view` / `project.view`

Hai lớp tách bạch:

- `task.view` / `project.view` — **cổng vào module**: có thì mở được màn hình, không có thì 403.
- `task.view_*` / `project.view_*` — **phạm vi bản ghi**: quyết định thấy những dòng nào.

### 3.2 Quy tắc giải phạm vi

`App\Support\DataScope` là enum `own` / `department` / `all`.

Phạm vi hiệu lực = **mức rộng nhất mà người dùng đang giữ**. Không giữ mức nào thì mặc định là `own`.

Chọn `own` làm sàn thay vì "không thấy gì" là có chủ ý: mọi thay đổi cấu hình sai chỉ có thể làm hẹp tầm nhìn của người dùng, không bao giờ mở rộng ngoài ý muốn, và người dùng không bao giờ gặp màn hình trắng không giải thích được.

`system_admin` không cần xử lý đặc biệt — vai trò này giữ `view_all` như mọi permission khác.

## 4. Định nghĩa phạm vi

### 4.1 Công việc

`own` — công việc thoả **bất kỳ** điều kiện nào:

1. `assignee_id` là tôi.
2. `creator_id` là tôi.
3. `project_id` thuộc các dự án tôi là thành viên.
4. `parent_id` trỏ tới một công việc thoả 1–3.

Điều kiện 4 chỉ đi **một cấp**: việc con của việc tôi thấy. Ứng dụng hiện dùng mô hình việc cha — việc con một cấp; không truy ngược đệ quy để tránh truy vấn đệ quy trên mọi lần liệt kê.

`department` — mọi điều kiện của `own`, **cộng thêm** `organization_unit_id` thuộc đơn vị của tôi hoặc bất kỳ đơn vị con nào của nó.

`all` — không giới hạn.

Mức rộng luôn bao trùm mức hẹp: người có `view_department` vẫn thấy việc mình được giao ở đơn vị khác.

### 4.2 Dự án

`own` — dự án tôi là thành viên (owner luôn là thành viên vai trò `manager`, nên chủ dự án tự nhiên nằm trong phạm vi này).

`department` — như trên, cộng thêm `organization_unit_id` thuộc đơn vị tôi hoặc đơn vị con.

`all` — không giới hạn.

Đây là mở rộng của `Project::scopeVisibleTo()` đã có từ Sprint 3, không phải cơ chế mới song song.

### 4.3 Mẫu việc định kỳ

Dùng lại phạm vi công việc: `own` = mẫu tôi tạo hoặc mẫu giao cho tôi; `department` = cộng thêm đơn vị tôi và đơn vị con; `all` = không giới hạn.

### 4.4 Cây đơn vị

`OrganizationUnit::descendantIdsOf(int $unitId): array` trả về id đơn vị đó cùng toàn bộ đơn vị con ở mọi cấp. Cài bằng một truy vấn lấy toàn bộ cặp `(id, parent_id)` rồi duyệt trong PHP — số đơn vị trong một doanh nghiệp là nhỏ, và cách này chạy giống nhau trên MySQL lẫn SQLite của test.

Người dùng không có `organization_unit_id` thì phạm vi `department` thu về đúng `own`.

## 5. Nơi áp dụng

Điều kiện phạm vi được viết **một lần** dưới dạng query scope trên model, và Policy dùng lại chính scope đó:

- `Task::scopeVisibleTo(Builder, User)` và `TaskPolicy::view()`
- `Project::scopeVisibleTo(Builder, User)` (mở rộng) và `ProjectPolicy::view()`
- `TaskRecurrence::scopeVisibleTo(Builder, User)` và `TaskRecurrencePolicy::view()`

Đây là điểm quan trọng nhất về mặt an toàn: nếu danh sách lọc theo một điều kiện còn Policy kiểm theo điều kiện khác, người dùng sẽ không thấy bản ghi trong danh sách nhưng vẫn mở được bằng URL trực tiếp. Một định nghĩa, hai nơi gọi.

Áp dụng vào: `TaskController::index/show`, `ProjectController::index/show`, `TaskRecurrenceController::index/show`, và các danh sách con (công việc trong trang dự án, công việc sinh từ mẫu).

Các ô chọn trên form (chọn dự án cho công việc, chọn dự án cho mẫu lặp) đã lọc theo `ProjectIsVisible` từ Sprint 3 — rule đó phải chuyển sang dùng chung `scopeVisibleTo` để không có hai định nghĩa "dự án tôi thấy được".

## 6. Ma trận phân quyền

### 6.1 Màn hình

`/permission-matrix` — bảng tích chọn: hàng là permission (nhóm theo module, kèm nhãn tiếng Việt), cột là sáu vai trò. Lưu một lần cho toàn bảng.

Vào được và sửa được đều cần `system.manage_settings`.

### 6.2 Quy tắc

- Vai trò `system_admin` luôn giữ **toàn bộ** permission. Cột này hiển thị đã tích và bị khoá; phía server từ chối mọi thay đổi lên vai trò này. Không có quy tắc này thì một lần bỏ tích nhầm sẽ khoá vĩnh viễn mọi người khỏi hệ thống.
- Người đang đăng nhập không được tự gỡ `system.manage_settings` khỏi vai trò của chính mình — cùng lý do trên, ở quy mô nhỏ hơn.
- Lưu bằng `syncPermissions` cho từng vai trò, trong một transaction, rồi xoá cache của `PermissionRegistrar`.

### 6.3 Audit

`AuditAction::RolePermissionsUpdated` (`role.permissions_updated`), một bản ghi cho mỗi vai trò thực sự đổi, `subject` là `Role`, `beforeValues`/`afterValues` là danh sách permission trước và sau. Vai trò không đổi thì không ghi.

### 6.4 Seeder và dữ liệu đang có

`RolePermissionSeeder` là **đường cơ sở cho cài đặt mới**. Sau khi có màn hình chỉnh tay, seeder không được ghi đè cấu hình đang chạy: nó chỉ gán permission cho vai trò **chưa có permission nào**. Vai trò đã có cấu hình thì bỏ qua.

Với cơ sở dữ liệu đang chạy, một migration dữ liệu gán permission phạm vi mới theo đường cơ sở dưới đây cho từng vai trò hiện có. Migration chỉ **thêm** permission phạm vi, không đụng tới permission khác.

Đường cơ sở:

| Vai trò | Công việc | Dự án |
| --- | --- | --- |
| `system_admin` | `view_all` | `view_all` |
| `director` | `view_all` | `view_all` |
| `department_manager` | `view_department` | `view_department` |
| `project_manager` | `view_own` | `view_own` |
| `employee` | `view_own` | `view_own` |
| `auditor` | `view_all` | `view_all` |

`auditor` giữ `view_all` vì vai trò này tồn tại để soát xét toàn hệ thống, và đã có `report.view_all` cùng `system.view_audit_logs`.

## 7. Kiểm thử

- `tests/Feature/Permission/DataScopeTest.php` — bảng ca: mỗi mức phạm vi × mỗi điều kiện của `own`; mức rộng bao trùm mức hẹp; người dùng không giữ mức nào rơi về `own`; người dùng không có đơn vị thì `department` thu về `own`.
- `tests/Feature/Permission/OrganizationUnitDescendantsTest.php` — cây nhiều cấp, đơn vị lá, đơn vị không tồn tại.
- `tests/Feature/Task/TaskVisibilityTest.php`, `tests/Feature/Project/ProjectVisibilityTest.php`, `tests/Feature/TaskRecurrence/TaskRecurrenceVisibilityTest.php` — danh sách chỉ trả bản ghi trong phạm vi, **và** mở trực tiếp bản ghi ngoài phạm vi bằng URL trả 403/404. Ca thứ hai là ca quan trọng nhất.
- `tests/Feature/Permission/PermissionMatrixControllerTest.php` — xem, sửa, audit, chặn sửa `system_admin`, chặn tự gỡ `system.manage_settings`, phân quyền vào màn hình.
- `tests/Feature/RolePermissionSeederTest.php` — bổ sung: seeder không ghi đè vai trò đã có cấu hình; đường cơ sở gán đúng permission phạm vi.
