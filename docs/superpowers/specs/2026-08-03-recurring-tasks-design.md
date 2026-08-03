# Công việc lặp lại định kỳ — Thiết kế

**Trạng thái:** đã chốt các lựa chọn kiến trúc với người dùng (2026-08-03).

## 1. Vấn đề

Các công việc thường xuyên của công ty — họp giao ban tuần, vệ sinh văn phòng, báo cáo tháng, tổng kết quý — hiện phải tạo tay mỗi kỳ. Cần khai báo một lần rồi hệ thống tự sinh công việc đúng ngày.

## 2. Lựa chọn đã chốt

| Câu hỏi | Quyết định |
| --- | --- |
| Mô hình | Mẫu lặp riêng (`task_recurrences`) sinh ra công việc thật, không nhồi quy tắc lặp vào `tasks`. |
| Thời điểm sinh | Đúng lịch, bất kể kỳ trước đã xong hay chưa. |
| Kiểu lặp bản đầu | Hằng ngày (mỗi N ngày), hằng tuần theo thứ, hằng tháng theo ngày trong tháng, hằng quý. |
| Kết thúc chuỗi | Không giới hạn; chỉ dừng khi tắt thủ công. |
| Sửa mẫu | Chỉ áp dụng từ kỳ sau; công việc đã sinh giữ nguyên. |
| Vị trí menu | Mục riêng "Việc định kỳ", đặt sau "Công việc". |

Không làm trong phạm vi này: lặp theo "thứ Hai đầu tiên của tháng", lặp theo năm, ngày lễ/ngày nghỉ, kết thúc sau N lần hoặc đến ngày cụ thể, nhắc trước hạn riêng cho việc định kỳ.

## 3. Mô hình dữ liệu

### 3.1 `task_recurrences`

| Cột | Kiểu | Ghi chú |
| --- | --- | --- |
| `id` | bigint unsigned PK | |
| `organization_unit_id` | FK `organization_units` | bắt buộc, `restrictOnDelete` |
| `project_id` | FK `projects` nullable | `nullOnDelete` |
| `creator_id` | FK `users` | `restrictOnDelete` |
| `assignee_id` | FK `users` nullable | `nullOnDelete` |
| `title` | varchar(200) | tiêu đề mẫu |
| `description` | text nullable | |
| `priority` | varchar(16) | `TaskPriority` |
| `planned_quantity` | int unsigned nullable | |
| `quantity_unit` | varchar(32) nullable | |
| `frequency` | varchar(16) | `RecurrenceFrequency` |
| `interval` | tinyint unsigned | ≥ 1, mặc định 1 — "mỗi N kỳ" |
| `weekdays` | json nullable | chỉ dùng khi `frequency = weekly`; mảng 1–7 (ISO, 1 = Thứ Hai) |
| `day_of_month` | tinyint unsigned nullable | 1–31; bắt buộc khi `monthly`/`quarterly` |
| `start_date` | date | kỳ đầu tiên không sớm hơn ngày này |
| `due_time` | time nullable | giờ hết hạn trong ngày; null = cuối ngày |
| `is_active` | boolean | mặc định `true` |
| `last_generated_for` | date nullable | kỳ gần nhất đã sinh |
| `timestamps`, `softDeletes` | | |

Index: `index(is_active, last_generated_for)` phục vụ lệnh sinh; `index(organization_unit_id)`; `index(assignee_id)`; `index(project_id)`.

### 3.2 `tasks`

Thêm hai cột:

- `task_recurrence_id` — FK `task_recurrences` nullable, `nullOnDelete`.
- `recurrence_date` — date nullable, ghi kỳ mà công việc này đại diện.

Ràng buộc `unique(task_recurrence_id, recurrence_date)` là chốt chặn chống sinh trùng. Chạy lệnh sinh hai lần trong cùng một ngày không tạo thêm bản ghi.

### 3.3 Enum

`App\Enums\RecurrenceFrequency` (backed string) kèm `label(): string`:

- `daily` — Hằng ngày
- `weekly` — Hằng tuần
- `monthly` — Hằng tháng
- `quarterly` — Hằng quý

## 4. Quy tắc sinh công việc

### 4.1 Tính các kỳ

`App\Support\RecurrenceSchedule` là nơi duy nhất giữ logic ngày tháng. Nó nhận một `TaskRecurrence` và một mốc `from`/`until`, trả về danh sách ngày đến hạn.

- `daily`: từ `start_date`, mỗi `interval` ngày.
- `weekly`: trong mỗi chu kỳ `interval` tuần tính từ tuần chứa `start_date`, sinh một công việc cho mỗi thứ trong `weekdays`.
- `monthly`: mỗi `interval` tháng tính từ tháng của `start_date`, vào ngày `day_of_month`.
- `quarterly`: mỗi `interval × 3` tháng tính từ tháng của `start_date`, vào ngày `day_of_month`.

Tháng thiếu ngày: `day_of_month = 31` ở tháng chỉ có 30 ngày (hoặc tháng 2) lùi về ngày cuối tháng. Không bỏ kỳ.

Múi giờ: mọi phép tính ngày dùng timezone ứng dụng, không dùng UTC thô, vì "ngày đến hạn" là khái niệm theo lịch địa phương.

### 4.2 Lệnh sinh

`php artisan tasks:generate-recurring` chạy trong scheduler mỗi ngày lúc 00:05.

Với mỗi mẫu `is_active = true`:

1. Mốc bắt đầu = `last_generated_for` + 1 ngày, hoặc `start_date` nếu chưa từng sinh.
2. Lấy các kỳ đến hạn từ mốc đó tới hết hôm nay.
3. Giới hạn **tối đa 30 kỳ mỗi mẫu mỗi lần chạy** — nếu hệ thống ngừng lâu thì sinh bù dần qua các ngày, không nổ dữ liệu trong một lần.
4. Tạo công việc cho từng kỳ trong một transaction; cập nhật `last_generated_for` bằng kỳ cuối đã sinh.

Công việc sinh ra:

- `status` = `todo` nếu mẫu có `assignee_id`, ngược lại `draft`.
- `due_at` = ngày kỳ + `due_time` (null → 23:59:59 của ngày đó).
- `progress` = 0; `actual_quantity` = 0 nếu mẫu có `planned_quantity`.
- Sao chép `organization_unit_id`, `project_id`, `assignee_id`, `title`, `description`, `priority`, `planned_quantity`, `quantity_unit` từ mẫu tại thời điểm sinh.
- Ghi `TaskActivity` loại `created` với `creator_id` = `creator_id` của mẫu.

Nếu `project_id` của mẫu trỏ tới một dự án đã đóng, bỏ qua `project_id` khi tạo công việc và ghi cảnh báo vào log — không làm hỏng cả lần chạy.

Lệnh chạy được lặp lại an toàn: ràng buộc unique ở 3.2 cộng với việc bắt lỗi trùng và bỏ qua kỳ đó.

### 4.3 Sửa và tắt mẫu

- Sửa mẫu chỉ ảnh hưởng các kỳ sinh sau. Công việc đã sinh không bị chạm tới.
- Đổi `start_date` về quá khứ **không** sinh bù các kỳ trước `last_generated_for`.
- Tắt (`is_active = false`) dừng sinh ngay; bật lại tiếp tục từ `last_generated_for`, tức là các kỳ trong lúc tắt bị bỏ qua chứ không sinh bù.
- Xoá mẫu là xoá mềm; công việc đã sinh giữ nguyên và `task_recurrence_id` vẫn trỏ tới bản ghi đã xoá mềm.

## 5. Phân quyền

Không thêm permission mới. Dùng lại nhóm `task.*` đã có:

| Ability trên `TaskRecurrence` | Điều kiện |
| --- | --- |
| `viewAny` / `view` | `task.view` |
| `create` | `task.create` |
| `update` | `task.update` và (là người tạo mẫu **hoặc** có `task.assign`) |
| `delete` | `task.delete` |
| `toggle` (bật/tắt) | cùng điều kiện `update` |

Chọn người phụ trách cho mẫu tuân theo đúng quy tắc của công việc thường: chỉ người có `task.assign` mới gán được cho người khác.

Chọn dự án cho mẫu dùng lại quy tắc đã có của Sprint 3: chỉ dự án chưa đóng và người dùng xem được.

## 6. Giao diện

- `resources/js/Pages/TaskRecurrences/Index.vue` — danh sách mẫu: tiêu đề, mô tả chu kỳ bằng tiếng Việt ("Thứ Hai hằng tuần", "Ngày 5 hằng tháng", "Mỗi 3 ngày"), đơn vị, dự án, người phụ trách, kỳ kế tiếp, công tắc bật/tắt, bộ lọc theo từ khoá / đơn vị / người phụ trách / trạng thái bật-tắt, phân trang 20.
- `Create.vue`, `Edit.vue` dùng chung `resources/js/Components/TaskRecurrenceForm.vue`. Form đổi trường theo `frequency`: chọn thứ khi `weekly`, chọn ngày trong tháng khi `monthly`/`quarterly`, ô "mỗi N kỳ" cho mọi kiểu. Hiển thị câu tóm tắt chu kỳ và ngày sinh kế tiếp ngay dưới form.
- `Show.vue` — thông tin mẫu và danh sách công việc đã sinh từ mẫu, phân trang, link sang chi tiết công việc.
- Mục menu "Việc định kỳ" đặt ngay sau "Công việc".
- Trang chi tiết và danh sách công việc hiển thị nhãn cho biết công việc sinh từ mẫu nào, có link về mẫu.

Mô tả chu kỳ bằng tiếng Việt sinh ở backend (một nơi duy nhất) và trả xuống dưới dạng chuỗi, để danh sách và form không tự diễn giải khác nhau.

## 7. Kiểm thử

Pest, đặt tại `tests/Feature/TaskRecurrence/`:

- `RecurrenceScheduleTest.php` — bảng ca cho cả bốn kiểu lặp, `interval > 1`, nhiều thứ trong tuần, ngày 31 ở tháng thiếu, kỳ đầu không sớm hơn `start_date`.
- `GenerateRecurringTasksCommandTest.php` — sinh đúng kỳ; chạy hai lần không sinh trùng; sinh bù tối đa 30 kỳ; mẫu tắt không sinh; mẫu có/không có người phụ trách cho ra `todo`/`draft`; `due_at` ghép đúng `due_time`; mẫu trỏ dự án đã đóng vẫn sinh được công việc không gắn dự án.
- `TaskRecurrenceControllerTest.php` — CRUD, bộ lọc, bật/tắt, xoá mềm, phân quyền.
- `TaskRecurrencePolicyTest.php` — bảng ability mục 5.
- `TaskRecurrenceBusinessRulesTest.php` — validation theo `frequency` (thiếu `weekdays` khi `weekly`, thiếu `day_of_month` khi `monthly`/`quarterly`, `interval` ≥ 1), sửa mẫu không đụng công việc đã sinh.
