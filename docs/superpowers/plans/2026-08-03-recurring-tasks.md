# Công việc lặp lại định kỳ — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Khai báo một lần các công việc thường xuyên (họp giao ban tuần, vệ sinh văn phòng, báo cáo tháng, tổng kết quý) rồi hệ thống tự sinh công việc đúng ngày.

**Architecture:** Bảng `task_recurrences` giữ mẫu lặp; lệnh `tasks:generate-recurring` chạy trong scheduler sinh công việc thật từ mẫu. Toàn bộ logic ngày tháng nằm trong một class hỗ trợ duy nhất `App\Support\RecurrenceSchedule`, không rải rác trong Action hay Controller. Backend theo pattern đã có: `Route → Controller → FormRequest → Action → Model → Inertia Response`.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Pest, Vue 3 + Inertia + TypeScript, Tailwind, PrimeVue.

**Spec:** `docs/superpowers/specs/2026-08-03-recurring-tasks-design.md`

## Global Constraints

- Luồng bắt buộc: `Route → Controller → FormRequest → Action → Model → Inertia Response`. Controller không chứa business rule; mọi ghi dữ liệu đi qua Action trong `app/Actions/TaskRecurrence/` và bọc `DB::transaction`.
- Mọi class PHP mới khai báo `final`, có type hint và return type đầy đủ, `./vendor/bin/pint` sạch.
- **KHÔNG thêm permission mới.** Chỉ dùng `task.view`, `task.create`, `task.update`, `task.delete`, `task.assign` đã có trong `App\Enums\PermissionName`.
- Enum `RecurrenceFrequency`: `daily`, `weekly`, `monthly`, `quarterly`. Dùng đúng các giá trị này.
- Toàn bộ logic tính ngày nằm trong `App\Support\RecurrenceSchedule` — không một nơi nào khác được tự tính kỳ. Mô tả chu kỳ bằng tiếng Việt cũng sinh ở backend, một nơi duy nhất.
- Ngày tháng tính theo `config('app.timezone')` một cách nhất quán; test tự đặt timezone chứ không phụ thuộc môi trường.
- `weekdays` dùng chuẩn ISO: 1 = Thứ Hai … 7 = Chủ Nhật.
- Sửa mẫu chỉ áp dụng từ kỳ sau; công việc đã sinh không bao giờ bị sửa hay xoá bởi mẫu.
- Mọi chuỗi hiển thị cho người dùng bằng tiếng Việt.
- Test framework: Pest. Chạy một file: `php artisan test tests/Feature/TaskRecurrence/RecurrenceScheduleTest.php`.
- Mỗi task kết thúc bằng một commit riêng. Không dùng `--no-verify`.
- `README.md` và `lang/vi/pagination.php` đang có thay đổi chưa commit của người dùng — không đụng vào, không đưa vào commit.

---

### Task 1: Schema, enum, model và factory

**Files:**
- Create: `database/migrations/2026_08_03_090000_create_task_recurrences_table.php`
- Create: `database/migrations/2026_08_03_090100_add_recurrence_columns_to_tasks_table.php`
- Create: `app/Enums/RecurrenceFrequency.php`
- Create: `app/Models/TaskRecurrence.php`
- Create: `database/factories/TaskRecurrenceFactory.php`
- Modify: `app/Models/Task.php` (thêm `task_recurrence_id`, `recurrence_date` vào `$fillable`, cast `recurrence_date` => `'date'`, quan hệ `recurrence(): BelongsTo` dùng `withTrashed()`)
- Test: `tests/Feature/TaskRecurrence/TaskRecurrenceModelTest.php`

**Interfaces:**
- `task_recurrences` đúng bảng cột ở spec mục 3.1, kèm index `(is_active, last_generated_for)`, `(organization_unit_id)`, `(assignee_id)`, `(project_id)`.
- `tasks` thêm `task_recurrence_id` (FK nullable, `nullOnDelete`) và `recurrence_date` (date nullable), cùng `unique(task_recurrence_id, recurrence_date)`.
- `App\Enums\RecurrenceFrequency` với `label(): string` trả nhãn tiếng Việt theo spec mục 3.3.
- `App\Models\TaskRecurrence` — `SoftDeletes`; `$fillable` gồm toàn bộ cột nghiệp vụ trừ `id`/timestamps; casts `frequency => RecurrenceFrequency::class`, `weekdays => 'array'`, `interval`/`day_of_month`/`planned_quantity` => `'integer'`, `start_date`/`last_generated_for` => `'date'`, `is_active` => `'boolean'`, `priority => TaskPriority::class`.
- Quan hệ: `organizationUnit()`, `project()`, `creator()` (`withTrashed()`), `assignee()` (`withTrashed()`), `tasks(): HasMany` (`Task`, sắp xếp `latest('recurrence_date')`).

- [ ] **Step 1: Viết test thất bại** — quan hệ hai chiều `TaskRecurrence::tasks()` / `Task::recurrence()`; cast `weekdays` ra mảng; `recurrence_date` là `date`; ràng buộc unique `(task_recurrence_id, recurrence_date)` thật sự chặn bản ghi trùng; xoá mềm mẫu thì công việc đã sinh vẫn còn và vẫn đọc được `recurrence`.
- [ ] **Step 2: Chạy test, xác nhận thất bại đúng lý do.**
- [ ] **Step 3: Viết migration, enum, model, factory cho tới khi xanh.**
- [ ] **Step 4: `php artisan test tests/Feature/TaskRecurrence` và `php artisan test tests/Feature/Task` xanh; `./vendor/bin/pint`; commit.**

---

### Task 2: `RecurrenceSchedule` — logic ngày tháng

**Files:**
- Create: `app/Support/RecurrenceSchedule.php`
- Test: `tests/Feature/TaskRecurrence/RecurrenceScheduleTest.php`

**Interfaces:**
- `final class RecurrenceSchedule`
- `occurrencesBetween(TaskRecurrence $recurrence, CarbonInterface $from, CarbonInterface $until): array` — trả `list<CarbonImmutable>` các ngày đến hạn trong khoảng đóng `[from, until]`, tăng dần, không trùng, không sớm hơn `start_date`.
- `nextOccurrenceAfter(TaskRecurrence $recurrence, CarbonInterface $after): ?CarbonImmutable` — kỳ kế tiếp, dùng cho cột "kỳ kế tiếp" ở giao diện.
- `describe(TaskRecurrence $recurrence): string` — mô tả tiếng Việt, ví dụ `"Hằng ngày"`, `"Mỗi 3 ngày"`, `"Thứ Hai, Thứ Sáu hằng tuần"`, `"Mỗi 2 tuần vào Thứ Hai"`, `"Ngày 5 hằng tháng"`, `"Ngày 15 hằng quý"`.

Quy tắc tính (spec mục 4.1):
- `daily`: từ `start_date`, mỗi `interval` ngày.
- `weekly`: các chu kỳ `interval` tuần tính từ tuần chứa `start_date` (tuần bắt đầu Thứ Hai); trong mỗi chu kỳ hợp lệ sinh một ngày cho mỗi thứ trong `weekdays`.
- `monthly`: mỗi `interval` tháng tính từ tháng của `start_date`, vào ngày `day_of_month`.
- `quarterly`: mỗi `interval * 3` tháng tính từ tháng của `start_date`, vào ngày `day_of_month`.
- Tháng thiếu ngày: `day_of_month` lớn hơn số ngày của tháng thì lùi về ngày cuối tháng, **không bỏ kỳ**.

- [ ] **Step 1: Viết test thất bại** — bảng ca cho cả bốn kiểu; `interval > 1` cho từng kiểu; `weekly` nhiều thứ trong một tuần; `weekly` với `interval = 2` bỏ đúng tuần xen kẽ; `day_of_month = 31` ở tháng 4 (30 ngày) và tháng 2 năm thường/năm nhuận; kỳ đầu không sớm hơn `start_date` kể cả khi `from` sớm hơn; khoảng `[from, until]` là khoảng đóng ở cả hai đầu; `describe()` cho từng dạng cấu hình.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Viết `RecurrenceSchedule` cho tới khi xanh.** Không truy vấn CSDL trong class này — nó chỉ nhận model và ngày.
- [ ] **Step 4: Test xanh; `pint`; commit.**

---

### Task 3: Lệnh sinh công việc

**Files:**
- Create: `app/Actions/TaskRecurrence/GenerateTasksFromRecurrenceAction.php`
- Create: `app/Console/Commands/GenerateRecurringTasks.php`
- Modify: `routes/console.php` (thêm `Schedule::command('tasks:generate-recurring')->dailyAt('00:05')->withoutOverlapping()`)
- Test: `tests/Feature/TaskRecurrence/GenerateRecurringTasksCommandTest.php`

**Interfaces:**
- Command signature `tasks:generate-recurring`, description tiếng Việt.
- `GenerateTasksFromRecurrenceAction::execute(TaskRecurrence $recurrence, CarbonInterface $until): int` — trả số công việc đã tạo.
- Hành vi (spec mục 4.2):
  - Mốc bắt đầu = `last_generated_for` + 1 ngày, hoặc `start_date` nếu `last_generated_for` là null.
  - Lấy kỳ đến hạn tới hết `until`; **giới hạn 30 kỳ mỗi mẫu mỗi lần chạy** (hằng số công khai trên Action để test tham chiếu, không phải số ma thuật rải rác).
  - Mỗi kỳ tạo một `Task`: `status` = `todo` nếu mẫu có `assignee_id`, ngược lại `draft`; `due_at` = ngày kỳ + `due_time`, hoặc `23:59:59` của ngày đó khi `due_time` null; `progress` = 0; `actual_quantity` = 0 khi mẫu có `planned_quantity`; sao chép `organization_unit_id`, `project_id`, `assignee_id`, `title`, `description`, `priority`, `planned_quantity`, `quantity_unit`; `creator_id` = `creator_id` của mẫu; ghi `TaskActivity` loại `TaskActivityType::Created` qua `App\Actions\Task\RecordTaskActivityAction`.
  - Nếu `project_id` của mẫu trỏ dự án đã đóng (dùng `Project::CLOSED_STATUSES`/`scopeOpen()` đã có) hoặc dự án đã xoá mềm: vẫn tạo công việc nhưng `project_id = null`, ghi `Log::warning` kèm id mẫu và id dự án.
  - Cập nhật `last_generated_for` = kỳ cuối đã sinh, trong cùng transaction.
  - Bắt lỗi trùng khoá unique `(task_recurrence_id, recurrence_date)` và bỏ qua kỳ đó thay vì làm hỏng cả lần chạy.
- Command bỏ qua mẫu `is_active = false` và mẫu đã xoá mềm; duyệt bằng `chunkById(100, ...)` như `SendTaskDeadlineNotifications` đang làm.

- [ ] **Step 1: Viết test thất bại** — sinh đúng các kỳ đến hạn cho từng `frequency`; chạy lệnh hai lần trong cùng ngày không tạo thêm bản ghi; sinh bù đúng 30 kỳ khi mốc lùi rất xa và `last_generated_for` dừng ở kỳ thứ 30; mẫu tắt không sinh gì; mẫu có người phụ trách ra `todo`, không có ra `draft`; `due_at` ghép đúng `due_time` và mặc định cuối ngày; mẫu trỏ dự án đã đóng vẫn sinh công việc với `project_id` null; mỗi công việc sinh ra có một `TaskActivity` loại `created`.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.** Dùng `travel()`/`Carbon::setTestNow()` trong test thay vì phụ thuộc ngày thật.
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 4: CRUD mẫu lặp (Policy, Request, Controller, Route)

**Files:**
- Create: `app/Policies/TaskRecurrencePolicy.php`
- Create: `app/Actions/TaskRecurrence/CreateTaskRecurrenceAction.php`, `UpdateTaskRecurrenceAction.php`, `ToggleTaskRecurrenceAction.php`, `DeleteTaskRecurrenceAction.php`
- Create: `app/Http/Requests/IndexTaskRecurrenceRequest.php`, `StoreTaskRecurrenceRequest.php`, `UpdateTaskRecurrenceRequest.php`
- Create: `app/Http/Controllers/TaskRecurrenceController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/TaskRecurrence/TaskRecurrencePolicyTest.php`, `TaskRecurrenceControllerTest.php`, `TaskRecurrenceBusinessRulesTest.php`

**Interfaces:**
- Policy đúng bảng ở spec mục 5: `viewAny`/`view` = `task.view`; `create` = `task.create`; `update` = `task.update` và (là `creator_id` **hoặc** có `task.assign`); `delete` = `task.delete`; `toggle` = cùng điều kiện `update`.
- Routes trong nhóm `['auth','verified','active']`, đặt route `toggle` **trước** `Route::resource`:
  - `PATCH task-recurrences/{taskRecurrence}/toggle` → `task-recurrences.toggle`
  - `Route::resource('task-recurrences', TaskRecurrenceController::class)->parameters(['task-recurrences' => 'taskRecurrence'])`
- Validation phụ thuộc `frequency` (bắt buộc, thông báo tiếng Việt):
  - `interval` required integer `min:1` `max:52`.
  - `weekdays` required khi `frequency = weekly`, array `min:1`, mỗi phần tử integer 1–7, không trùng; phải vắng mặt (hoặc bị bỏ qua) với các kiểu khác.
  - `day_of_month` required integer 1–31 khi `frequency` là `monthly` hoặc `quarterly`; phải vắng mặt với các kiểu khác.
  - `title` required max 200; `description` nullable max 5000; `priority` required enum `TaskPriority`; `start_date` required date; `due_time` nullable `date_format:H:i`; `planned_quantity` nullable integer `min:1`; `quantity_unit` nullable max 32 và required khi có `planned_quantity`.
  - `organization_unit_id` required exists; `project_id` nullable và phải dùng lại đúng hai rule đã có của Sprint 3: `App\Rules\ProjectIsOpen` và `App\Rules\ProjectIsVisible`.
  - `assignee_id` nullable exists users `is_active = true`, `whereNull('deleted_at')`; chỉ người có `task.assign` mới gán được cho người khác — không có quyền thì chỉ được gán chính mình hoặc để trống.
- `IndexTaskRecurrenceRequest` filters: `search` (max 160), `organization_unit_id`, `assignee_id`, `frequency` (enum), `is_active` (boolean).
- `index` trả Inertia `TaskRecurrences/Index` với `recurrences` (paginate 20, `withQueryString`, mỗi item kèm `description` từ `RecurrenceSchedule::describe()` và `next_occurrence`), `filters`, `frequencies`, `organizationUnits`, `users`, `projects`, và cờ `can.create`.
- `show` trả `TaskRecurrences/Show` với `recurrence` (kèm `description`, `next_occurrence`), `tasks` (công việc đã sinh, paginate 20, `pageName: 'tasks_page'`) và `actions` (`update`, `delete`, `toggle`).
- `ToggleTaskRecurrenceAction` lật `is_active` và trả model; **không** đụng `last_generated_for` — bật lại không sinh bù (spec mục 4.3).
- `DeleteTaskRecurrenceAction` xoá mềm; công việc đã sinh giữ nguyên.

- [ ] **Step 1: Viết test thất bại** — bảng policy cả hai chiều; CRUD và từng bộ lọc; toggle bật/tắt; xoá mềm không đụng công việc đã sinh; toàn bộ validation phụ thuộc `frequency` ở trên; người không có `task.assign` không gán được cho người khác; không chọn được dự án đã đóng hoặc không xem được; **sửa mẫu không làm đổi công việc đã sinh**.
- [ ] **Step 2: Chạy test, xác nhận thất bại.**
- [ ] **Step 3: Cài đặt cho tới khi xanh.** Trang Vue chưa tồn tại ở task này — không sao với test Inertia.
- [ ] **Step 4: `php artisan test` toàn bộ xanh; `pint`; commit.**

---

### Task 5: Giao diện

**Files:**
- Create: `resources/js/Pages/TaskRecurrences/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue`
- Create: `resources/js/Components/TaskRecurrenceForm.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue` (mục "Việc định kỳ", `routeName: 'task-recurrences.index'`, ngay sau "Công việc")
- Modify: `resources/js/Pages/Tasks/Index.vue`, `resources/js/Pages/Tasks/Show.vue` (nhãn "Việc định kỳ" kèm link về mẫu khi công việc có `task_recurrence_id`)
- Modify: `app/Http/Controllers/TaskController.php` (eager load `recurrence:id,title` cho `index` và `show` — thay đổi tối thiểu, chỉ để hiển thị nhãn)
- Modify: `resources/js/types/index.d.ts` (kiểu `TaskRecurrence`)

**Interfaces:** bám sát pattern đã có — đọc `resources/js/Pages/Projects/Index.vue`, `Show.vue`, `Components/ProjectForm.vue` và `Pages/Tasks/Index.vue` trước khi viết, giữ nguyên bố cục, class Tailwind, cách debounce bộ lọc, phân trang, dialog xác nhận và cách hiển thị lỗi qua `InputError`.

Yêu cầu màn hình:
- Index: bộ lọc từ khoá / đơn vị / người phụ trách / chu kỳ / trạng thái bật-tắt; bảng gồm tiêu đề, mô tả chu kỳ (chuỗi backend trả về, **không tự dựng ở frontend**), đơn vị, dự án, người phụ trách, kỳ kế tiếp, công tắc bật/tắt; nút Tạo mẫu chỉ hiện khi có quyền.
- Form: các trường đổi theo `frequency` — chọn nhiều thứ khi `weekly`, chọn ngày trong tháng khi `monthly`/`quarterly`, ô "mỗi N kỳ" cho mọi kiểu. Chọn thứ hiển thị nhãn tiếng Việt Thứ Hai…Chủ Nhật.
- Show: thông tin mẫu, mô tả chu kỳ, kỳ kế tiếp, nút Tạm dừng/Kích hoạt và Xoá theo `actions`; danh sách công việc đã sinh có phân trang, link sang chi tiết công việc.

- [ ] **Step 1: Đọc các trang Projects và Tasks hiện có để nắm pattern.**
- [ ] **Step 2: Viết component và trang.**
- [ ] **Step 3: `npm run build` sạch; `npx eslint resources/js --max-warnings=0` sạch.**
- [ ] **Step 4: `php artisan test` toàn bộ xanh; commit.**
