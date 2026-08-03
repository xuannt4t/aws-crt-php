# Ba màn công việc và bố cục bảng điều khiển — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bốn mục công việc trên menu (tổng quan, định kỳ, dự án, phòng ban) dùng chung một trang bố cục ba khúc — thống kê, bộ lọc gọn, danh sách chi tiết — và quyền xem việc trong dự án đặt được cho từng thành viên.

**Architecture:** Một `TaskController::index` phục vụ ba route với một hằng số bối cảnh do route truyền vào. Phạm vi dữ liệu vẫn hoàn toàn do `Task::scopeVisibleTo` quyết định; bối cảnh chỉ thu hẹp thêm. Quyền xem việc trong dự án là một cột trên `project_members` được đọc bởi đúng một điều kiện trong `scopeVisibleTo`.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Pest, Vue 3 + Inertia + TypeScript, Tailwind, Vitest.

**Spec:** `docs/superpowers/specs/2026-08-03-work-screens-and-dashboard-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa business rule.
- Mọi class mới `final`, type hint và return type đầy đủ, `./vendor/bin/pint` sạch.
- KHÔNG thêm permission mới. Phạm vi cấp bậc đã có sẵn qua `task.view_own|view_department|view_all`.
- Điều kiện phạm vi vẫn viết MỘT lần trong `Task::scopeVisibleTo`; Policy gọi lại scope đó. Không nơi nào khác được dựng lại điều kiện.
- Bối cảnh (`overview`/`project`/`department`) do route quyết định, KHÔNG nhận từ query string.
- Thống kê tính bằng một truy vấn tổng hợp trên cùng điều kiện của danh sách — không phải sáu truy vấn đếm, không đếm trên trang hiện tại.
- Không biểu đồ, không phần trăm tiến độ ở khúc thống kê. Người dùng nêu rõ điều này.
- Mọi chuỗi hiển thị bằng tiếng Việt.
- Test framework: Pest cho PHP, Vitest cho TS. `php` không có trên PATH: dùng `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe`.
- `README.md` đang có thay đổi chưa commit của người dùng — không đụng vào, không đưa vào commit.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.

---

### Task 1: Quyền xem việc trong dự án

**Files:**
- Create: `app/Enums/ProjectTaskVisibility.php`
- Create: `database/migrations/2026_08_03_150000_add_task_visibility_to_project_members_table.php`
- Modify: `app/Models/ProjectMember.php`, `app/Models/Project.php`, `app/Models/Task.php`
- Modify: `database/factories/ProjectMemberFactory.php`
- Test: `tests/Feature/Project/ProjectMemberTaskVisibilityTest.php`; cập nhật `tests/Feature/Task/TaskVisibilityTest.php`

**Interfaces:**
- `App\Enums\ProjectTaskVisibility`: `Own = 'own'`, `All = 'all'`, kèm `label(): string` (Chỉ việc của mình / Toàn bộ việc dự án).
- Migration: `task_visibility` varchar(16) NOT NULL default `own`, đặt sau `role`. Dữ liệu đang có: bản ghi vai trò `manager` đặt `all`, phần còn lại `own`. `down()` gỡ cột.
- `ProjectMember`: thêm `task_visibility` vào `$fillable`, cast sang `ProjectTaskVisibility`; thêm `effectiveTaskVisibility(): ProjectTaskVisibility` trả `All` khi `role === ProjectMemberRole::Manager`, ngược lại trả giá trị cột. Đây là nơi DUY NHẤT diễn giải quy tắc "manager luôn thấy toàn bộ".
- `Task::scopeVisibleTo`: điều kiện thứ ba của `own` đổi từ "dự án tôi là thành viên" thành "dự án tôi là thành viên với hiệu lực `all`" — tức `task_visibility = 'all'` HOẶC `role = 'manager'`. Ba điều kiện còn lại giữ nguyên.

**Đây là thay đổi hành vi có chủ đích, không phải sửa lỗi.** Các test đang khoá hành vi cũ ("thành viên dự án thấy mọi việc của dự án") phải được viết lại cho đúng hành vi mới — không được xoá hay nới lỏng khẳng định. Liệt kê từng test đã sửa và lý do trong report.

- [ ] **Step 1: Viết test thất bại** — mặc định thành viên mới là `own`; thành viên `own` KHÔNG thấy việc của đồng nghiệp trong dự án; thành viên `own` VẪN thấy việc được giao cho mình và việc mình tạo trong dự án đó; thành viên `all` thấy toàn bộ; thành viên vai trò `manager` thấy toàn bộ kể cả khi cột là `own`; migration đặt đúng giá trị cho dữ liệu đang có.
- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 2: Đặt quyền xem cho thành viên dự án

**Files:**
- Modify: `app/Http/Requests/StoreProjectMemberRequest.php`, `UpdateProjectMemberRequest.php`
- Modify: `app/Actions/Project/AddProjectMemberAction.php`, `UpdateProjectMemberRoleAction.php`
- Modify: `app/Http/Controllers/ProjectController.php` (prop cho danh sách thành viên)
- Modify: `resources/js/Components/ProjectMemberList.vue`, `resources/js/types/index.d.ts`
- Test: bổ sung `tests/Feature/Project/ProjectMemberControllerTest.php`

**Interfaces:**
- `StoreProjectMemberRequest`: thêm `task_visibility` `nullable`, `Rule::enum(ProjectTaskVisibility::class)`; vắng mặt thì mặc định `own`.
- `UpdateProjectMemberRequest`: thêm `task_visibility` `required`, `Rule::enum(...)`. Giữ nguyên `role` như hiện tại.
- `AddProjectMemberAction`/`UpdateProjectMemberRoleAction`: lưu cột; nâng lên `manager` thì đặt `all`; hạ khỏi `manager` thì giữ nguyên giá trị đang có. Ghi audit như hiện tại, thêm `task_visibility` vào metadata.
- `ProjectMemberList.vue`: mỗi dòng thêm ô chọn quyền xem cạnh ô chọn vai trò, chỉ hiện khi `actions.manageMembers`. Dòng vai trò `manager` hiển thị "Toàn bộ việc dự án" ở dạng khoá, kèm chú thích ngắn.
- Form thêm thành viên: thêm ô chọn quyền xem, mặc định "Chỉ việc của mình".

- [ ] **Step 1: Viết test thất bại** — thêm thành viên không truyền `task_visibility` ra `own`; truyền `all` lưu đúng; đổi quyền xem của thành viên đang có; nâng lên `manager` chuyển thành `all`; hạ khỏi `manager` giữ nguyên; người không có `manageMembers` bị 403; giá trị enum không hợp lệ bị từ chối.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` xanh; `npm run build` và `npx eslint resources/js --max-warnings=0` sạch; `pint`; commit.**

---

### Task 3: Bối cảnh và thống kê

**Files:**
- Create: `app/Enums/TaskContext.php`
- Create: `app/Support/TaskSummary.php`
- Modify: `app/Http/Controllers/TaskController.php`, `app/Http/Requests/IndexTaskRequest.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Task/TaskContextTest.php`, `tests/Feature/Task/TaskSummaryTest.php`

**Interfaces:**
- `App\Enums\TaskContext`: `Overview = 'overview'`, `Project = 'project'`, `Department = 'department'`, kèm `label(): string` (Tổng quan việc / Việc dự án / Việc phòng ban).
- Routes đăng ký **trước** `Route::resource('tasks', ...)`:
  - `GET tasks` → `tasks.index` (bối cảnh `overview`)
  - `GET tasks/projects` → `tasks.projects` (bối cảnh `project`)
  - `GET tasks/departments` → `tasks.departments` (bối cảnh `department`)
  Bối cảnh truyền vào controller như một giá trị cố định của route, KHÔNG đọc từ request.
- Thu hẹp theo bối cảnh: `project` → `whereNotNull('project_id')`; `department` → `whereNotNull('organization_unit_id')`; `overview` → không thêm gì.
- `App\Support\TaskSummary` — `final`, `for(Builder $query): array` trả đúng các khoá: `total`, `not_started`, `in_progress`, `waiting_approval`, `completed`, `overdue`, `earliest_start`, `latest_due`. Tính bằng MỘT truy vấn tổng hợp (`selectRaw` với các biểu thức đếm có điều kiện) trên **bản sao** của query danh sách trước khi phân trang. Ánh xạ trạng thái đúng bảng ở spec mục 6.1.
- `TaskController::index` trả thêm prop `summary`, `context` (giá trị enum) và `availableFilters` (danh sách khoá bộ lọc được hiển thị).
- `/dashboard` chuyển hướng 302 sang `tasks.index`, giữ tên route `dashboard` để các liên kết và luồng đăng nhập hiện có không gãy.

- [ ] **Step 1: Viết test thất bại** — mỗi bối cảnh trả đúng tập dữ liệu; **bối cảnh không đổi được bằng `?context=`**; bối cảnh chỉ thu hẹp chứ không mở rộng phạm vi (người dùng `own` mở `tasks.departments` vẫn không thấy việc ngoài phạm vi); sáu ô đếm đúng cho từng trạng thái; ô trễ hạn cắt ngang các ô kia; `cancelled` không rơi vào ô nào và tổng năm ô nhỏ hơn ô tổng; thống kê tính trên toàn tập đã lọc chứ không riêng trang hiện tại (tạo hơn 20 việc rồi kiểm tra); `earliest_start`/`latest_due` đúng; `/dashboard` chuyển hướng.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 4: Giao diện ba khúc và thanh menu

**Files:**
- Modify: `resources/js/Pages/Tasks/Index.vue` (dựng lại theo bố cục ba khúc)
- Create: `resources/js/Components/TaskSummaryCards.vue`, `resources/js/Components/TaskFilterBar.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/types/index.d.ts`
- Modify: `resources/js/Pages/Projects/Show.vue` (danh sách việc bỏ bộ lọc dự án nếu đang dùng bộ lọc)

**Interfaces:**
- `TaskSummaryCards.vue` — nhận `summary`, vẽ sáu ô số và hai mốc thời gian. Không biểu đồ, không phần trăm. Ô trễ hạn nhấn mạnh bằng màu cảnh báo.
- `TaskFilterBar.vue` — nhận `filters`, `availableFilters` và các danh sách chọn; chỉ vẽ những bộ lọc có trong `availableFilters`. Thu gọn được, khi thu gọn hiện số bộ lọc đang bật. Nút "Áp dụng" và "Xoá lọc". Giữ trong một hàng ngang gọn, không quá một phần tư chiều cao màn hình khi mở.
- `Tasks/Index.vue` — ba khúc theo đúng thứ tự: `TaskSummaryCards`, `TaskFilterBar`, bảng chi tiết. Cột: tên việc, tình trạng, mức ưu tiên, người phụ trách, phòng ban, dự án, hạn, người tạo. Tiêu đề trang đổi theo `context`. Đánh dấu dòng trễ hạn.
- Thanh menu, đúng thứ tự người dùng nêu: Tổng quan việc (`tasks.index`), Việc định kỳ (`task-recurrences.index`), Việc dự án (`tasks.projects`), Việc phòng ban (`tasks.departments`), rồi giữ nguyên Dự án, Cơ cấu tổ chức, Người dùng, Nhật ký hệ thống, Phân quyền. Bỏ mục "Tổng quan" cũ và mục "Công việc" cũ.
- Đánh dấu mục menu đang chọn phải phân biệt được ba route công việc — `activePattern` hiện tại dùng `tasks.*` sẽ làm cả ba mục cùng sáng. Sửa cho đúng.

- [ ] **Step 1: Đọc `resources/js/Pages/Tasks/Index.vue` hiện tại và `Pages/Projects/Index.vue` để giữ pattern.**
- [ ] **Step 2: Viết component và trang.**
- [ ] **Step 3: `npm run build` sạch; `npx vitest run` xanh; `npx eslint resources/js --max-warnings=0` sạch.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; commit.**
