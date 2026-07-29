# Sprint 2 — Activity Timeline: Design

## 1. Mục tiêu

Gom mọi hoạt động của một công việc vào một dòng thời gian duy nhất trên trang chi tiết (FR-TASK-11 trong `docs/srs/SRS.md`). Đây là hạng mục cuối của Sprint 2 theo `context/sprint-plan.md`; xong đợt này là khép lại Sprint 2 — Task Core.

Hiện trang chi tiết có ba khối rời rạc: "Trao đổi", "Tệp đính kèm" và "Lịch sử trạng thái". Người quản lý muốn biết "chuyện gì đã xảy ra với công việc này" phải đọc ba chỗ và tự ghép theo thời gian. Nghiêm trọng hơn: **đổi người phụ trách và cập nhật tiến độ hiện không được ghi lại ở đâu** — hai thông tin quản lý cần nhất lại mất dấu hoàn toàn.

## 2. Phạm vi

Trong phạm vi:

- Bảng `task_activities` (append-only) và model tương ứng.
- Ghi hoạt động từ các Action sẵn có: tạo công việc, đổi trạng thái, đổi người phụ trách, cập nhật tiến độ, bình luận, thêm/xoá tệp đính kèm.
- Prop `activities` phân trang trên trang chi tiết.
- Component timeline thay cho mục "Lịch sử trạng thái".

Ngoài phạm vi (mục 10 nêu lý do):

- Ghi nhận sửa tiêu đề, mô tả, thời hạn, độ ưu tiên, công việc cha.
- Bỏ hoặc migrate bảng `task_status_histories`.
- Lọc timeline theo loại sự kiện hoặc theo người thực hiện.
- Realtime cập nhật timeline khi người khác thao tác.
- Timeline xuyên nhiều công việc (bảng tin hoạt động toàn hệ thống).

## 3. Quyết định thiết kế

| Quyết định | Lựa chọn | Lý do |
|---|---|---|
| Nguồn dữ liệu | Bảng `task_activities` là nguồn duy nhất của timeline | Một query, phân trang chuẩn SQL, mỗi loại sự kiện mới chỉ cần thêm chỗ ghi chứ không phải sửa chỗ đọc |
| `task_status_histories` | Giữ nguyên, không migrate | Bảng phục vụ nghiệp vụ (kiểm tra transition, lưu `from`/`to`/`reason` theo `context/task-flow.md`); bỏ đi phải migrate dữ liệu và sửa code đang chạy ổn, rủi ro không tương xứng |
| Trùng lặp sự kiện đổi trạng thái | Chấp nhận ghi ở hai bảng | Hai bảng phục vụ hai mục đích: một cho nghiệp vụ, một cho hiển thị. Ghi trong cùng transaction nên không lệch nhau |
| Dữ liệu hiển thị | `payload` JSON lưu sẵn tên và giá trị tại thời điểm xảy ra | Dòng lịch sử vẫn đọc được sau khi bản ghi gốc bị xoá mềm hoặc bị đổi tên |
| Câu chữ tiếng Việt | Dựng ở frontend từ `type` + `payload` | Backend trả dữ liệu thô, không trả câu đã ghép — dễ đổi câu chữ, dễ test, không nhét chuỗi hiển thị vào DB |
| Bố cục | Thay mục "Lịch sử trạng thái"; giữ "Trao đổi" và "Tệp đính kèm" | Hai mục kia còn chức năng riêng (ô nhập bình luận có phân trang, khu upload và tải tệp) |
| Bình luận trong timeline | Chỉ hiện trích đoạn 120 ký tự | Nội dung đầy đủ đã có ở mục "Trao đổi"; timeline giữ vai trò dòng sự kiện, không nhân đôi nội dung |

## 4. Database

Migration mới: `create_task_activities_table`.

```
id           bigint unsigned, PK
task_id      bigint unsigned, FK -> tasks.id, restrict on delete
actor_id     bigint unsigned, FK -> users.id, restrict on delete
type         varchar(40)
payload      json, nullable
created_at   timestamp, useCurrent
```

Index: `(task_id, id)` — timeline sắp xếp theo `id` giảm dần thay vì `created_at` để thứ tự ổn định khi nhiều sự kiện rơi vào cùng một giây (ví dụ upload 5 tệp trong một request).

Bảng là append-only theo `prompts/03_DATABASE.md` §10: không `updated_at`, không soft delete, không sửa bản ghi đã tạo. Cùng quy ước với `task_status_histories`.

## 5. Enum

`App\Enums\TaskActivityType` — backed enum, `string`:

| Case | Giá trị | Payload |
|---|---|---|
| `Created` | `created` | không có |
| `StatusChanged` | `status_changed` | `from`, `to` (giá trị `TaskStatus`) |
| `Assigned` | `assigned` | `from_assignee_id`, `from_assignee_name`, `to_assignee_id`, `to_assignee_name` (id và tên cùng nullable — bỏ phân công thì `to_*` là `null`) |
| `ProgressUpdated` | `progress_updated` | `from`, `to` (số nguyên 0–100) |
| `Commented` | `commented` | `comment_id`, `excerpt` (tối đa 120 ký tự, cắt theo ký tự có dấu) |
| `AttachmentAdded` | `attachment_added` | `attachment_ids` (mảng), `original_names` (mảng), `file_count` |
| `AttachmentRemoved` | `attachment_removed` | `attachment_id`, `original_name` |

`attachment_added` ghi **một** hoạt động cho mỗi lần tải lên, không phải mỗi tệp một dòng — người dùng chọn 5 tệp và bấm một nút thì đó là một hành động, timeline không nên bị 5 dòng giống hệt nhau. Cách này nhất quán với việc audit log hiện cũng ghi một bản ghi cho mỗi request upload.

## 6. Ghi hoạt động

`App\Actions\Task\RecordTaskActivityAction`:

```php
execute(User $actor, Task $task, TaskActivityType $type, array $payload = []): TaskActivity
```

Action chỉ tạo một bản ghi, không tự mở transaction — nó luôn được gọi từ bên trong transaction của Action nghiệp vụ, để hoạt động và thay đổi dữ liệu cùng thành công hoặc cùng thất bại.

Điểm gọi:

| Action nghiệp vụ | Loại hoạt động | Điều kiện |
|---|---|---|
| `CreateTaskAction` | `created` | luôn |
| `TransitionTaskStatusAction` | `status_changed` | luôn, cùng transaction với việc ghi `task_status_histories` |
| `UpdateTaskAction` | `assigned` | **chỉ khi** `assignee_id` thực sự thay đổi so với giá trị cũ |
| `UpdateTaskProgressAction` | `progress_updated` | **chỉ khi** giá trị tiến độ thực sự thay đổi |
| `CreateTaskCommentAction` | `commented` | luôn |
| `StoreTaskAttachmentAction` | `attachment_added` | một hoạt động cho mỗi request upload |
| `DeleteTaskAttachmentAction` | `attachment_removed` | luôn |

Ràng buộc "chỉ khi thực sự thay đổi" là để timeline không đầy những dòng vô nghĩa kiểu "đổi người phụ trách từ Minh sang Minh" khi người dùng bấm lưu mà không sửa gì.

`CreateTaskCommentAction` và `StoreTaskAttachmentAction` hiện chưa mở transaction (comment) hoặc đã có sẵn transaction (attachment). `CreateTaskCommentAction` cần bọc thêm transaction để bình luận và hoạt động không lệch nhau.

`UpdateTaskAction` hiện nhận `array $data` và không biết ai đang thao tác. Cần bổ sung tham số `User $actor` — chữ ký đổi từ `execute(Task $task, array $data)` thành `execute(User $actor, Task $task, array $data)`. Chỉ có `TaskController::update()` gọi Action này nên phạm vi ảnh hưởng nhỏ.

## 7. Backend — dữ liệu cho trang chi tiết

`TaskController::show()` bổ sung prop `activities`: phân trang 30 dòng mỗi trang, `pageName: 'activities_page'`, sắp xếp `id` giảm dần, eager load `actor:id,name,avatar_path` (tránh N+1 theo NFR-02), `withQueryString()` — cùng khuôn với `comments` đang có.

Mỗi phần tử trả về: `id`, `type`, `payload`, `created_at`, `actor` (`{id, name, avatar_url}` hoặc `null` nếu tài khoản bị xoá cứng).

Đồng thời gỡ `statusHistories.actor:id,name,avatar_path` khỏi `$task->load(...)` trong `show()`: mục "Lịch sử trạng thái" bị thay thế nên dữ liệu này không còn nơi tiêu thụ, giữ lại là code chết và một truy vấn thừa mỗi lần mở trang. Bảng `task_status_histories` vẫn được ghi và vẫn phục vụ nghiệp vụ transition — chỉ ngừng đẩy sang frontend.

Không cần thay đổi Policy: quyền xem timeline chính là quyền xem công việc, đã kiểm ở `TaskController::show()`.

## 8. Frontend

Component mới `resources/js/Components/TaskActivityTimeline.vue`, thay vào vị trí mục "Lịch sử trạng thái" trong `Pages/Tasks/Show.vue`.

Mỗi dòng gồm: chấm tròn có icon và màu theo loại sự kiện, đường kẻ dọc nối các mốc, avatar người thực hiện, câu mô tả tiếng Việt, và thời điểm.

Câu chữ dựng từ `type` + `payload`:

| Loại | Câu hiển thị |
|---|---|
| `created` | "**Minh** đã tạo công việc" |
| `status_changed` | "**Minh** chuyển trạng thái từ *Đang thực hiện* sang *Chờ kiểm tra*" |
| `assigned` | "**Minh** giao việc cho **Lan**" / "**Minh** chuyển phụ trách từ **Lan** sang **Hùng**" / "**Minh** bỏ phân công **Lan**" |
| `progress_updated` | "**Minh** cập nhật tiến độ từ 40% lên 65%" |
| `commented` | "**Minh** đã trao đổi: *trích đoạn…*" |
| `attachment_added` | "**Minh** đính kèm 3 tệp: báo cáo.pdf, số liệu.xlsx, ảnh.png" |
| `attachment_removed` | "**Minh** xoá tệp *báo cáo.pdf*" |

Nhãn trạng thái dùng lại `taskStatusLabels` trong `Constants/task` để không lệch chữ với phần còn lại của trang. Tên người lấy từ `payload` chứ không truy vấn lại, nên tài khoản bị xoá vẫn hiện đúng tên tại thời điểm xảy ra; riêng `actor` bị xoá cứng thì hiện "Tài khoản đã xóa" như các mục khác đang làm.

Có đủ empty state ("Chưa có hoạt động nào"), phân trang giống mục "Trao đổi", và responsive: trên mobile thời điểm xuống dòng dưới câu mô tả thay vì nằm cùng hàng.

Loại sự kiện lạ (dữ liệu cũ hoặc phiên bản sau) không được làm vỡ giao diện: component bỏ qua dòng không nhận diện được thay vì render rỗng.

## 9. Testing

`tests/Feature/Task/TaskActivityTest.php`:

Ghi hoạt động:

- Tạo công việc ghi một hoạt động `created` với đúng actor.
- Chuyển trạng thái ghi `status_changed` với `payload.from` và `payload.to` đúng, đồng thời vẫn ghi `task_status_histories` như cũ.
- Đổi người phụ trách ghi `assigned` với đủ bốn khoá payload, giữ đúng tên tại thời điểm đổi.
- Cập nhật form mà **không** đổi người phụ trách thì không sinh hoạt động `assigned`.
- Cập nhật tiến độ ghi `progress_updated`; cập nhật đúng giá trị cũ thì không ghi.
- Bình luận ghi `commented` với `comment_id` đúng và `excerpt` cắt còn 120 ký tự.
- Upload 3 tệp trong một request ghi **đúng một** hoạt động `attachment_added` với `file_count = 3`.
- Xoá tệp ghi `attachment_removed`, và tên tệp trong payload vẫn đọc được sau khi bản ghi bị xoá mềm.
- Upload thất bại (transaction rollback) không để lại hoạt động mồ côi.

Hiển thị:

- Trang chi tiết trả prop `activities` phân trang 30 dòng, thứ tự mới nhất trước.
- Người không có `task.view` không mở được trang (403), tức không xem được timeline.
- Truy vấn không sinh N+1 khi timeline có nhiều actor khác nhau.

## 10. Ngoài phạm vi và lý do

| Hạng mục | Lý do hoãn |
|---|---|
| Ghi nhận sửa tiêu đề/mô tả/thời hạn/độ ưu tiên | Làm timeline nhiễu; khi cần truy vết chi tiết thì dùng audit log đúng vai trò hơn |
| Bỏ `task_status_histories` | Phải migrate dữ liệu và sửa code đang chạy ổn; lợi ích chỉ là gọn mô hình |
| Lọc timeline theo loại/người | Chưa có nhu cầu xác nhận; số sự kiện mỗi công việc còn nhỏ |
| Realtime | Phụ thuộc Reverb, thuộc Sprint 4 |
| Bảng tin hoạt động toàn hệ thống | Thuộc Dashboard/Report, Sprint 6 |
