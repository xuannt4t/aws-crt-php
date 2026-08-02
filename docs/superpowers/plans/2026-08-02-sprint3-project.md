# Sprint 3 — Project Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Module Dự án đầy đủ: CRUD, thành viên và vai trò trong dự án, gắn công việc vào dự án, tiến độ tự tính, đóng dự án có ngoại lệ và dữ liệu demo.

**Architecture:** Ba thay đổi schema (`projects`, `project_members`, `tasks.project_id`) cộng với `ProjectController` + `ProjectMemberController` theo đúng pattern `TaskController`/`TaskCommentController` đang có: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Không thêm permission mới — `project.*` đã có sẵn trong `App\Enums\PermissionName` và đã được gán role trong `RolePermissionSeeder`.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Pest, Vue 3 + Inertia + TypeScript, Tailwind, PrimeVue.

**Spec:** `docs/superpowers/specs/2026-08-02-sprint3-project-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa business rule; mọi ghi dữ liệu đi qua Action trong `app/Actions/Project/` và bọc `DB::transaction`.
- Mọi class PHP mới khai báo `final`, có type hint và return type đầy đủ, chạy được `./vendor/bin/pint`.
- **Không thêm permission mới.** Chỉ dùng `project.view`, `project.create`, `project.update`, `project.delete`, `project.manage_members`, `project.close` đã có trong `App\Enums\PermissionName`.
- Enum `ProjectStatus`: `planning`, `active`, `on_hold`, `completed`, `cancelled`. Enum `ProjectMemberRole`: `manager`, `member`, `viewer`. Dùng đúng các giá trị này, không đổi tên.
- "Công việc còn mở" = `status` không thuộc `{completed, cancelled}`. Dùng đúng định nghĩa này ở mọi nơi.
- Tiến độ dự án = trung bình `progress` của các công việc **không bị huỷ**, làm tròn số nguyên; không có công việc nào (hoặc chỉ có công việc đã huỷ) → `0`.
- Không xoá cứng dự án. `destroy` luôn soft delete và ghi audit.
- Mọi chuỗi hiển thị cho người dùng bằng tiếng Việt.
- Ghi audit qua `App\Services\AuditLogger::record()` như `DeleteTaskAction` đang làm.
- Test framework: Pest. Chạy một file: `php artisan test tests/Feature/Project/ProjectControllerTest.php`. Chạy một test: thêm `--filter="tên test"`.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.

---

### Task 1: Schema, enum, model và factory

**Files:**
- Create: `database/migrations/2026_08_02_150000_create_projects_table.php`
- Create: `database/migrations/2026_08_02_150100_create_project_members_table.php`
- Create: `database/migrations/2026_08_02_150200_add_project_id_to_tasks_table.php`
- Create: `app/Enums/ProjectStatus.php`, `app/Enums/ProjectMemberRole.php`
- Create: `app/Models/Project.php`, `app/Models/ProjectMember.php`
- Create: `database/factories/ProjectFactory.php`, `database/factories/ProjectMemberFactory.php`
- Modify: `app/Models/Task.php` (thêm `project_id` vào `$fillable`, thêm quan hệ `project()`)
- Modify: `app/Enums/AuditAction.php` (thêm 6 case ở mục Audit của spec)
- Test: `tests/Feature/Project/ProjectModelTest.php`

**Interfaces:**
- Produces `App\Models\Project` — `$fillable = ['organization_unit_id','owner_id','code','name','description','status','start_date','end_date','closed_at','close_reason']`; casts `status => ProjectStatus::class`, `start_date`/`end_date` => `'date'`, `closed_at` => `'datetime'`; `SoftDeletes`.
- Quan hệ: `organizationUnit(): BelongsTo`, `owner(): BelongsTo` (`User`, `withTrashed()`), `members(): HasMany` (`ProjectMember`), `users(): BelongsToMany` (`User` qua `project_members`, `withPivot('role','joined_at')`), `tasks(): HasMany` (`Task`).
- Methods: `openTasks(): HasMany` (lọc status ngoài `completed`/`cancelled`), `calculateProgress(): int`, `isMember(User $user): bool`, `isManager(User $user): bool`, `isClosed(): bool` (status `completed` hoặc `cancelled`).
- Produces `App\Models\ProjectMember` — `$fillable = ['project_id','user_id','role','joined_at']`, casts `role => ProjectMemberRole::class`, `joined_at => 'datetime'`, quan hệ `project()`, `user()` (`withTrashed()`).
- Enum có method `label(): string` trả nhãn tiếng Việt theo spec mục 3.

- [ ] **Step 1: Viết test thất bại** — `tests/Feature/Project/ProjectModelTest.php` phủ: quan hệ `tasks`/`members`/`owner`/`organizationUnit`; `calculateProgress()` trả `0` khi không có công việc; trả trung bình làm tròn khi có công việc; **bỏ qua công việc `cancelled`** khi tính; `openTasks()` chỉ trả công việc ngoài `completed`/`cancelled`; `isManager()`/`isMember()` đúng theo `project_members.role`; owner đã xoá mềm vẫn truy cập được qua `owner`.
- [ ] **Step 2: Chạy test và xác nhận thất bại đúng lý do.**
- [ ] **Step 3: Viết migration, enum, model, factory cho tới khi test xanh.** `ProjectFactory` mặc định `status = ProjectStatus::Active`, `code` unique dạng `PRJ-{n}`. Đặt cột `project_id` của `tasks` ngay sau `organization_unit_id`, `nullOnDelete`, kèm index `(project_id, status)`.
- [ ] **Step 4: `php artisan test tests/Feature/Project/ProjectModelTest.php` và `php artisan test tests/Feature/Task` xanh; `./vendor/bin/pint`; commit.**

---

### Task 2: ProjectPolicy

**Files:**
- Create: `app/Policies/ProjectPolicy.php`
- Test: `tests/Feature/Project/ProjectPolicyTest.php`
- Modify (nếu cần): nơi đăng ký policy — kiểm tra cách `TaskPolicy` được nhận diện (auto-discovery) và làm giống hệt.

**Interfaces:** đúng bảng ability trong spec mục 5:

| Ability | Điều kiện |
| --- | --- |
| `viewAny` | `project.view` |
| `view` | `project.view` hoặc là thành viên dự án (bất kỳ vai trò) |
| `create` | `project.create` |
| `update` | `project.update` hoặc là thành viên vai trò `manager` |
| `delete` | `project.delete` |
| `manageMembers` | `project.manage_members` hoặc là thành viên vai trò `manager` |
| `close` | `project.close` hoặc là thành viên vai trò `manager` |

- [ ] **Step 1: Viết test thất bại** phủ từng ô của bảng theo cả hai chiều (có permission nhưng không phải thành viên; là `manager` nhưng không có permission; là `member`/`viewer` không có permission → chỉ `view`).
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Viết policy cho tới khi xanh.**
- [ ] **Step 4: Test xanh; `pint`; commit.**

---

### Task 3: Project CRUD (Action, Request, Controller, Route)

**Files:**
- Create: `app/Actions/Project/CreateProjectAction.php`, `UpdateProjectAction.php`, `DeleteProjectAction.php`
- Create: `app/Http/Requests/IndexProjectRequest.php`, `StoreProjectRequest.php`, `UpdateProjectRequest.php`
- Create: `app/Http/Controllers/ProjectController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Project/ProjectControllerTest.php`, `tests/Feature/Project/ProjectBusinessRulesTest.php`

**Interfaces:**
- `CreateProjectAction::execute(User $actor, array $data): Project` — tạo dự án với `status` mặc định `ProjectStatus::Planning` nếu không truyền, **và thêm owner vào `project_members` với role `manager`, `joined_at = now()`**.
- `UpdateProjectAction::execute(User $actor, Project $project, array $data): Project` — nếu `owner_id` đổi, bảo đảm owner mới có bản ghi thành viên vai trò `manager` (tạo mới hoặc nâng vai trò); owner cũ giữ nguyên bản ghi thành viên.
- `DeleteProjectAction::execute(User $actor, Project $project): void` — soft delete + `AuditLogger::record()` với `AuditAction::ProjectDeleted`, `beforeValues` gồm `organization_unit_id, owner_id, code, name, status`, metadata `['task_count' => ...]`.
- `StoreProjectRequest` rules: `organization_unit_id` required exists; `owner_id` required exists users; `code` required, `max:32`, `regex:/^[A-Z0-9_\-]+$/`, unique `projects`; `name` required `max:160`; `description` nullable string `max:5000`; `status` nullable `Rule::enum(ProjectStatus::class)`; `start_date` nullable date; `end_date` nullable date `after_or_equal:start_date`. `authorize()` dùng policy `create`.
- `UpdateProjectRequest`: như trên, `code` unique bỏ qua chính nó, `authorize()` dùng policy `update`.
- `IndexProjectRequest` validated filters: `search` (string, max 160), `status` (enum), `organization_unit_id` (int exists), `owner_id` (int exists), `only_mine` (boolean).
- `ProjectController::index` trả Inertia `Projects/Index` với `projects` (paginate 20, `withQueryString`, mỗi item kèm `progress`, `open_task_count`, `task_count`, `member_count`), `filters`, `statuses`, `organizationUnits`, `users`. Tính tiến độ và số lượng bằng aggregate query (`withCount`, `withAvg`) — **không N+1**.
- `ProjectController::show` trả `Projects/Show` với `project` (kèm `progress`, `owner`, `organizationUnit`), `members` (kèm user + role), `tasks` (paginate 20, `pageName: 'tasks_page'`), `actions` (`update`, `delete`, `manageMembers`, `close` từ policy).
- Routes trong nhóm `['auth','verified','active']`: `Route::resource('projects', ProjectController::class)`.

- [ ] **Step 1: Viết test thất bại** — index yêu cầu `project.view` và áp dụng từng bộ lọc; `only_mine` chỉ trả dự án user là thành viên; store tạo dự án và tự thêm owner làm `manager`; store bị chặn khi thiếu `project.create`; validation `code` unique, `code` sai định dạng, `end_date` trước `start_date`; update đổi owner thì owner mới thành `manager`; destroy soft delete (bản ghi còn trong DB với `deleted_at`) và ghi `AuditLog` `project.deleted`; show trả đúng `progress` và `actions`.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.** Trang Inertia chưa tồn tại ở task này — dùng `Inertia::render` bình thường, test Inertia không cần file `.vue`.
- [ ] **Step 4: Test xanh; `pint`; commit.**

---

### Task 4: Thành viên dự án

**Files:**
- Create: `app/Actions/Project/AddProjectMemberAction.php`, `UpdateProjectMemberRoleAction.php`, `RemoveProjectMemberAction.php`
- Create: `app/Http/Requests/StoreProjectMemberRequest.php`, `UpdateProjectMemberRequest.php`
- Create: `app/Http/Controllers/ProjectMemberController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Project/ProjectMemberControllerTest.php`

**Interfaces:**
- Routes (đặt **trước** `Route::resource('projects', ...)`):
  - `POST projects/{project}/members` → `projects.members.store`
  - `PATCH projects/{project}/members/{member}` → `projects.members.update`, `->scopeBindings()`
  - `DELETE projects/{project}/members/{member}` → `projects.members.destroy`, `->scopeBindings()`
- Cả ba request `authorize()` dùng policy `manageMembers` trên `Project`.
- `StoreProjectMemberRequest`: `user_id` required, exists `users` với `is_active = true`, `Rule::unique('project_members')->where('project_id', ...)`; `role` required `Rule::enum(ProjectMemberRole::class)`.
- `UpdateProjectMemberRequest`: `role` required enum.
- Ràng buộc nghiệp vụ (test bắt buộc phủ):
  - Không xoá được thành viên là owner của dự án → trả lỗi validation trên `user_id` hoặc `abort(422)` với thông báo tiếng Việt; chọn một cách và giữ nhất quán.
  - Không hạ vai trò owner xuống dưới `manager`.
  - Thêm trùng người → lỗi validation.
- Audit: `project.member_added`, `project.member_role_updated`, `project.member_removed` với `subject` là `Project`, metadata gồm `member_user_id` và `role` (cũ/mới khi đổi vai trò).
- Sau mỗi thao tác `Redirect::back()->with('success', ...)` (tiếng Việt).

- [ ] **Step 1: Viết test thất bại** phủ toàn bộ ràng buộc trên, cộng với: `manager` của dự án không có permission `project.manage_members` vẫn thao tác được; người ngoài dự án không có permission bị chặn 403.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: Test xanh; `pint`; commit.**

---

### Task 5: Đóng dự án

**Files:**
- Create: `app/Actions/Project/CloseProjectAction.php`
- Create: `app/Http/Requests/CloseProjectRequest.php`
- Modify: `app/Http/Controllers/ProjectController.php` (thêm `close`), `routes/web.php`
- Test: `tests/Feature/Project/ProjectClosingTest.php`

**Interfaces:**
- Route `PATCH projects/{project}/close` → `projects.close`, đặt trước `Route::resource`.
- `CloseProjectRequest::authorize()` dùng policy `close`. Rules: `close_reason` `nullable|string|min:10|max:1000`, và trong `withValidator`/`after` thêm lỗi `close_reason` "Dự án còn công việc chưa hoàn thành, vui lòng nhập lý do đóng ngoại lệ." khi `$project->openTasks()->exists()` và `close_reason` trống.
- `CloseProjectAction::execute(User $actor, Project $project, ?string $reason): Project`:
  - `abort_if($project->isClosed(), 422)` — thông báo tiếng Việt "Dự án đã được đóng."
  - Đặt `status = ProjectStatus::Completed`, `closed_at = now()`.
  - Không còn công việc mở → `close_reason = null`, audit `AuditAction::ProjectClosed`.
  - Còn công việc mở → `close_reason = $reason`, audit `AuditAction::ProjectClosedWithException`, metadata `['open_task_count' => N, 'open_task_ids' => tối đa 20 id]`.
- Sau khi đóng: `Redirect::back()->with('success', 'Đã đóng dự án.')`.

- [ ] **Step 1: Viết test thất bại** phủ: đóng sạch (không công việc mở) → status `completed`, `closed_at` khác null, `close_reason` null, audit `project.closed`; còn công việc mở mà không có lý do → 422/redirect kèm lỗi `close_reason`, dự án **không** đổi trạng thái; còn công việc mở kèm lý do hợp lệ → đóng được, audit `project.closed_with_exception` với `open_task_count` đúng; đóng dự án đã `completed` → 422; user thiếu quyền `close` và không phải `manager` → 403; `manager` không có permission `project.close` → đóng được.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: Test xanh; `pint`; commit.**

---

### Task 6: Gắn công việc vào dự án (backend)

**Files:**
- Modify: `app/Http/Requests/StoreTaskRequest.php`, `UpdateTaskRequest.php`, `IndexTaskRequest.php`
- Modify: `app/Http/Controllers/TaskController.php`
- Test: `tests/Feature/Task/TaskControllerTest.php` (bổ sung ca mới, không sửa ca cũ trừ khi payload bắt buộc đổi)

**Interfaces:**
- `StoreTaskRequest`/`UpdateTaskRequest`: thêm `project_id` `nullable|integer|exists:projects,id`, cộng rule tuỳ chỉnh từ chối dự án đã đóng (`status` thuộc `completed`/`cancelled`) với thông báo "Không thể gắn công việc vào dự án đã đóng."
- `IndexTaskRequest`: thêm bộ lọc `project_id` `nullable|integer|exists:projects,id`.
- `TaskController::index`: thêm `->when($filters['project_id'] ?? null, ...)`, eager load `project:id,name,code`, và truyền prop `projects` (danh sách dự án chưa đóng, `id`, `name`, `code`) cho bộ lọc.
- `TaskController::create`/`edit`: truyền prop `projects` (dự án chưa đóng mà người dùng xem được).
- `TaskController::show`: eager load `project:id,name,code` để trang chi tiết hiển thị dự án.
- `TaskController::edit`: thêm `project_id` vào danh sách `$task->only([...])`.

- [ ] **Step 1: Viết test thất bại** — tạo công việc kèm `project_id` lưu đúng; tạo kèm dự án đã đóng bị từ chối; lọc `?project_id=` chỉ trả công việc của dự án đó; trang create/edit trả prop `projects` không chứa dự án đã đóng.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test tests/Feature/Task` toàn bộ xanh; `pint`; commit.**

---

### Task 7: Giao diện

**Files:**
- Create: `resources/js/pages/Projects/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`
- Create: `resources/js/components/ProjectForm.vue`, `ProjectMemberList.vue`, `ProjectProgressBar.vue`, `AppProjectStatusBadge.vue`
- Modify: `resources/js/layouts/AuthenticatedLayout.vue` (thêm mục "Dự án", `routeName: 'projects.index'`, đặt sau "Công việc")
- Modify: `resources/js/components/TaskForm.vue` (chọn dự án), `resources/js/pages/Tasks/Index.vue` (bộ lọc + cột dự án), `resources/js/pages/Tasks/Show.vue` (hiển thị dự án)
- Modify: `resources/js/types/index.d.ts` (kiểu `Project`, `ProjectMember`)

**Interfaces:** bám sát pattern hiện có — `AppPageHeader`, `AppEmptyState`, `AppStatusBadge`, `AppConfirmDialog`, `AppActionButton`, `useForm` của Inertia, `route()` helper. Đọc `resources/js/pages/Tasks/Index.vue` và `Show.vue` trước khi viết để giữ đồng nhất bố cục, class Tailwind và cách phân trang.

Yêu cầu màn hình:
- Index: ô tìm kiếm, select trạng thái, select đơn vị, select owner, checkbox "Chỉ dự án của tôi"; bảng gồm mã, tên, đơn vị, owner, trạng thái (badge), tiến độ (thanh %), số công việc, số thành viên; nút Tạo dự án chỉ hiện khi có quyền.
- Show: khối tóm tắt + thanh tiến độ; danh sách thành viên với form thêm (chọn người + vai trò), đổi vai trò inline, nút xoá có xác nhận — chỉ hiện khi `actions.manageMembers`; danh sách công việc thuộc dự án có phân trang, link sang chi tiết công việc; nút "Đóng dự án" mở hộp thoại, **hiện ô nhập lý do bắt buộc khi `open_task_count > 0`** kèm cảnh báo số công việc còn mở.
- Create/Edit: `ProjectForm.vue` với mã, tên, mô tả, đơn vị, owner, trạng thái, ngày bắt đầu, ngày kết thúc; hiển thị lỗi qua `InputError`.

- [ ] **Step 1: Đọc các trang Task hiện có để nắm pattern.**
- [ ] **Step 2: Viết component và trang.**
- [ ] **Step 3: `npm run build` (hoặc `npx vue-tsc --noEmit` nếu dự án có) chạy sạch, `npx eslint resources/js --max-warnings=0` sạch.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; commit.**

---

### Task 8: Dữ liệu demo và tài liệu

**Files:**
- Modify: `database/seeders/DemoDataSeeder.php`
- Modify: `tests/Feature/DemoDataSeederTest.php`
- Modify: `context/sprint-plan.md` (đánh dấu Sprint 3 hoàn tất, đặt Sprint 4 là việc kế tiếp)
- Modify: `README.md` nếu có mục liệt kê module đã hoàn thành

**Interfaces:**
- Thêm 4 dự án demo, mỗi dự án gắn đúng đơn vị đã có trong seeder: `PRODUCT` (Phòng Sản phẩm), `ENGINEERING` (Phòng Kỹ thuật), `SALES` (Phòng Kinh doanh), `OPERATIONS` (Phòng Vận hành).
- Mỗi dự án: owner là người dùng thuộc đơn vị đó, 3–5 thành viên phủ đủ ba vai trò `manager`/`member`/`viewer`, và một số công việc hiện có được gán `project_id`.
- Ít nhất một dự án ở trạng thái `completed` đã đóng sạch, một dự án `on_hold`, còn lại `active`.
- Seeder phải **idempotent** — chạy hai lần không tạo bản ghi trùng (dùng `updateOrCreate` theo `code` như `upsertOrganizationUnit` đang làm).

- [ ] **Step 1: Viết test thất bại** trong `tests/Feature/DemoDataSeederTest.php`: seeder tạo đúng 4 dự án theo `code`; mỗi dự án có owner là thành viên `manager`; có công việc gắn dự án; chạy seeder hai lần không tăng số bản ghi.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; cập nhật tài liệu; commit.**
