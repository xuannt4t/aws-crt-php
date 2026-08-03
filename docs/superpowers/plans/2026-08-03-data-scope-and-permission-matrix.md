# Phạm vi dữ liệu theo vai trò và ma trận phân quyền — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nhân viên chỉ thấy công việc và dự án của mình; trưởng đơn vị thấy đơn vị mình và các đơn vị con; giám đốc thấy toàn bộ. Kèm màn hình chỉnh ma trận vai trò × quyền.

**Architecture:** Ba mức phạm vi (`own`/`department`/`all`) khai báo bằng permission. Điều kiện lọc viết **một lần** dưới dạng `scopeVisibleTo` trên model, dùng chung cho cả danh sách lẫn Policy — đây là điểm cốt lõi: lệch giữa hai nơi sẽ tạo ra bản ghi không thấy trong danh sách nhưng mở được bằng URL.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Spatie Laravel Permission, Pest, Vue 3 + Inertia + TypeScript, Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-03-data-scope-and-permission-matrix-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa business rule.
- Mọi class mới `final`, type hint và return type đầy đủ, `./vendor/bin/pint` sạch.
- Permission mới **chỉ** gồm sáu cái ở spec mục 3: `task.view_own`, `task.view_department`, `task.view_all`, `project.view_own`, `project.view_department`, `project.view_all`. Không thêm gì khác.
- `task.view`/`project.view` vẫn là cổng vào module; permission phạm vi quyết định thấy dòng nào. Hai lớp tách bạch, không gộp.
- Phạm vi hiệu lực = mức rộng nhất người dùng giữ; không giữ mức nào thì mặc định `own`.
- Mức rộng luôn bao trùm mức hẹp.
- Mọi điều kiện phạm vi nằm trong `scopeVisibleTo` của model tương ứng. Policy và Controller đều gọi lại scope đó, không viết lại điều kiện.
- Ngày tháng, chuỗi hiển thị: tiếng Việt.
- Test framework: Pest. Chạy: `php artisan test tests/Feature/Permission`.
- `php` không có trên PATH: dùng `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- `README.md` đang có thay đổi chưa commit của người dùng — không đụng vào, không đưa vào commit.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.

---

### Task 1: Permission phạm vi, `DataScope` và cây đơn vị

**Files:**
- Modify: `app/Enums/PermissionName.php` (thêm 6 case ở spec mục 3)
- Create: `app/Enums/DataScope.php`
- Create: `app/Support/DataScopeResolver.php`
- Modify: `app/Models/OrganizationUnit.php` (thêm `descendantIdsOf`)
- Modify: `database/seeders/RolePermissionSeeder.php`
- Create: `database/migrations/2026_08_03_120000_grant_scope_permissions_to_existing_roles.php`
- Modify: `context/permission-matrix.md` (bổ sung 6 permission mới và bảng đường cơ sở)
- Test: `tests/Feature/Permission/DataScopeTest.php`, `tests/Feature/Permission/OrganizationUnitDescendantsTest.php`; bổ sung `tests/Feature/RolePermissionSeederTest.php`

**Interfaces:**
- `App\Enums\DataScope`: `Own = 'own'`, `Department = 'department'`, `All = 'all'`, kèm `label(): string` tiếng Việt (Của tôi / Đơn vị / Toàn bộ).
- `App\Support\DataScopeResolver` — `final`, không truy vấn ngoài quyền của user:
  - `forTasks(User $user): DataScope`
  - `forProjects(User $user): DataScope`
  - Trả mức rộng nhất người dùng giữ; không giữ mức nào → `DataScope::Own`.
- `OrganizationUnit::descendantIdsOf(int $unitId): array` — `list<int>` gồm chính `$unitId` và mọi đơn vị con ở mọi cấp. Lấy toàn bộ cặp `(id, parent_id)` bằng MỘT truy vấn rồi duyệt trong PHP. Đơn vị không tồn tại → mảng rỗng. Phải chịu được dữ liệu vòng lặp cha-con mà không treo.
- `RolePermissionSeeder`: giữ nguyên việc tạo mọi permission còn thiếu, nhưng **chỉ gán permission cho vai trò chưa có permission nào**. Vai trò đã có cấu hình thì bỏ qua để không ghi đè chỉnh tay từ màn hình ma trận (spec mục 6.4). Đường cơ sở bổ sung đúng bảng ở spec mục 6.4.
- Migration dữ liệu: với mỗi vai trò đang tồn tại, `givePermissionTo` đúng hai permission phạm vi theo bảng đường cơ sở. **Chỉ thêm**, không gỡ gì. `down()` gỡ đúng sáu permission phạm vi khỏi mọi vai trò.

- [ ] **Step 1: Viết test thất bại** — `DataScopeResolver` trả đúng mức cho từng tổ hợp permission (giữ nhiều mức thì lấy rộng nhất; không giữ mức nào → `own`); `descendantIdsOf` trên cây ba cấp, trên đơn vị lá, trên id không tồn tại, và trên dữ liệu có vòng lặp cha-con; seeder không ghi đè vai trò đã có permission; đường cơ sở gán đúng permission phạm vi cho sáu vai trò.
- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 2: Phạm vi công việc

**Files:**
- Modify: `app/Models/Task.php` (thêm `scopeVisibleTo`)
- Modify: `app/Policies/TaskPolicy.php` (`view` dùng lại scope)
- Modify: `app/Http/Controllers/TaskController.php` (`index` áp scope; `show` đã qua Policy)
- Test: `tests/Feature/Task/TaskVisibilityTest.php`

**Interfaces:**
- `Task::scopeVisibleTo(Builder $query, User $user): Builder` — theo đúng spec mục 4.1:
  - `all` → không thêm điều kiện.
  - `own` → nhóm `where` chứa: `assignee_id = user`, HOẶC `creator_id = user`, HOẶC `project_id` thuộc dự án user là thành viên, HOẶC `parent_id` trỏ tới một công việc thoả ba điều kiện trên (đúng **một** cấp).
  - `department` → nhóm `own` HOẶC `organization_unit_id` thuộc `OrganizationUnit::descendantIdsOf($user->organization_unit_id)`. User không có đơn vị thì `department` bằng đúng `own`.
- `TaskPolicy::view(User, Task): bool` = `$user->can('task.view')` và công việc nằm trong `Task::visibleTo($user)`. Kiểm bằng cách hỏi lại scope (`Task::query()->whereKey($task)->visibleTo($user)->exists()`), không viết lại điều kiện.
- `TaskController::index` thêm `->visibleTo($request->user())` vào query.
- Cẩn thận: mọi điều kiện `own` phải nằm trong MỘT closure `where(function ($q) { ... })`, nếu không các `orWhere` sẽ phá vỡ những bộ lọc khác đang có trên query (trạng thái, độ ưu tiên, tìm kiếm).

- [ ] **Step 1: Viết test thất bại** — mỗi điều kiện của `own` cho ra đúng một công việc thấy được; công việc của người khác ở đơn vị khác không thấy; `department` thấy cả đơn vị con nhưng không thấy đơn vị ngang cấp; `all` thấy hết; user không giữ mức nào rơi về `own`; user không có đơn vị mà giữ `view_department` thì thu về `own`; **và ca quan trọng nhất — `GET /tasks/{id}` với công việc ngoài phạm vi trả 403**; bộ lọc trạng thái/tìm kiếm vẫn hoạt động đúng khi kết hợp với scope.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 3: Phạm vi dự án và mẫu việc định kỳ

**Files:**
- Modify: `app/Models/Project.php` (mở rộng `scopeVisibleTo` đã có sang ba mức)
- Modify: `app/Policies/ProjectPolicy.php`
- Modify: `app/Models/TaskRecurrence.php` (thêm `scopeVisibleTo`)
- Modify: `app/Policies/TaskRecurrencePolicy.php`
- Modify: `app/Http/Controllers/ProjectController.php`, `app/Http/Controllers/TaskRecurrenceController.php`
- Modify: `app/Rules/ProjectIsVisible.php` (dùng chung `Project::scopeVisibleTo`)
- Test: `tests/Feature/Project/ProjectVisibilityTest.php`, `tests/Feature/TaskRecurrence/TaskRecurrenceVisibilityTest.php`

**Interfaces:**
- `Project::scopeVisibleTo` mở rộng theo spec mục 4.2: `all` không giới hạn; `own` = dự án user là thành viên; `department` = `own` HOẶC `organization_unit_id` thuộc cây đơn vị của user. Giữ nguyên tên scope để không phải sửa nơi gọi.
- `TaskRecurrence::scopeVisibleTo` theo spec mục 4.3, dùng lại `DataScopeResolver::forTasks`.
- `ProjectPolicy::view` và `TaskRecurrencePolicy::view` hỏi lại scope tương ứng, không viết lại điều kiện.
- Danh sách con cũng phải lọc: công việc trong trang chi tiết dự án (`ProjectController::show`) và công việc sinh từ mẫu (`TaskRecurrenceController::show`) đều đi qua `Task::visibleTo`.
- `ProjectIsVisible` phải gọi `Project::query()->visibleTo($user)` thay vì tự dựng điều kiện — sau task này chỉ còn đúng một định nghĩa "dự án tôi thấy được".

- [ ] **Step 1: Viết test thất bại** — ba mức cho dự án và cho mẫu lặp; mở trực tiếp bản ghi ngoài phạm vi trả 403/404; danh sách công việc trong trang dự án bị lọc theo phạm vi công việc của người xem (thành viên dự án `viewer` chỉ có `task.view_own` không thấy việc của người khác); `ProjectIsVisible` từ chối dự án ngoài phạm vi ở cả `StoreTaskRequest` lẫn form mẫu lặp.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 4: Ma trận phân quyền — backend

**Files:**
- Create: `app/Policies/PermissionMatrixPolicy.php` hoặc dùng Gate trực tiếp trong FormRequest (chọn cách khớp với codebase và ghi rõ trong report)
- Create: `app/Actions/Permission/UpdateRolePermissionsAction.php`
- Create: `app/Http/Requests/UpdatePermissionMatrixRequest.php`
- Create: `app/Http/Controllers/PermissionMatrixController.php`
- Modify: `app/Enums/AuditAction.php` (thêm `RolePermissionsUpdated = 'role.permissions_updated'`)
- Modify: `routes/web.php`
- Test: `tests/Feature/Permission/PermissionMatrixControllerTest.php`

**Interfaces:**
- Routes trong nhóm `['auth','verified','active']`:
  - `GET permission-matrix` → `permission-matrix.index`
  - `PUT permission-matrix` → `permission-matrix.update`
- Cả hai yêu cầu `system.manage_settings`.
- `index` trả Inertia `PermissionMatrix/Index` với: `roles` (id, name, label tiếng Việt), `permissionGroups` (nhóm theo tiền tố module, mỗi permission có `name` và nhãn tiếng Việt), `matrix` (map `role name → list<permission name>`), `lockedRoles` (`['system_admin']`).
- `UpdatePermissionMatrixRequest` rules: `matrix` required array; `matrix.*` array; `matrix.*.*` string `Rule::in` danh sách permission hợp lệ; khoá của `matrix` phải là tên vai trò tồn tại.
- Ràng buộc nghiệp vụ (validation, thông báo tiếng Việt):
  - Từ chối mọi thay đổi lên vai trò `system_admin` — vai trò này luôn giữ toàn bộ permission.
  - Từ chối payload gỡ `system.manage_settings` khỏi vai trò mà chính người đang đăng nhập đang giữ.
- `UpdateRolePermissionsAction::execute(User $actor, array $matrix): int` — trả số vai trò thực sự đổi. Trong một `DB::transaction`: với mỗi vai trò, so sánh tập permission cũ và mới; **chỉ** khi khác thì `syncPermissions` và ghi audit `RolePermissionsUpdated` (`subject` = `Role`, `beforeValues`/`afterValues` là danh sách permission đã sắp xếp). Cuối cùng gọi `app(PermissionRegistrar::class)->forgetCachedPermissions()`.

- [ ] **Step 1: Viết test thất bại** — index cần `system.manage_settings`, trả đủ prop; update gán đúng permission và ghi audit; vai trò không đổi thì **không** sinh bản ghi audit; sửa `system_admin` bị từ chối; tự gỡ `system.manage_settings` của mình bị từ chối; permission không hợp lệ bị từ chối; sau khi lưu thì quyền có hiệu lực ngay (cache đã xoá).
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 5: Ma trận phân quyền — giao diện

**Files:**
- Create: `resources/js/Pages/PermissionMatrix/Index.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue` (mục "Phân quyền", chỉ hiện khi có `system.manage_settings`)
- Modify: `resources/js/types/index.d.ts`

**Interfaces:** đọc `resources/js/Pages/Projects/Index.vue` và `Pages/Users/Index.vue` trước để giữ đúng bố cục, class Tailwind, cách hiển thị lỗi và nút lưu.

Yêu cầu màn hình:
- Bảng: hàng là permission nhóm theo module với tiêu đề nhóm; cột là sáu vai trò; ô là checkbox.
- Cột `system_admin` tích sẵn toàn bộ và bị vô hiệu hoá, kèm chú thích ngắn giải thích vì sao.
- Nút "Lưu thay đổi" chỉ bật khi có thay đổi so với trạng thái ban đầu; hiển thị số ô đã đổi.
- Cảnh báo rõ ràng khi người dùng bỏ tích `system.manage_settings` ở vai trò của chính mình (chặn ở cả client lẫn server).
- Nhóm permission phạm vi (`*.view_own|view_department|view_all`) hiển thị liền nhau với nhãn giải thích ngắn, vì đây là phần người dùng sẽ chỉnh nhiều nhất.
- Toàn bộ chuỗi tiếng Việt.

- [ ] **Step 1: Đọc các trang hiện có để nắm pattern.**
- [ ] **Step 2: Viết trang.**
- [ ] **Step 3: `npm run build` sạch; `npx eslint resources/js --max-warnings=0` sạch.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; commit.**
