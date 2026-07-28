# Sprint 2 — Task Attachment: Design

## 1. Mục tiêu

Cho phép đính kèm tệp vào công việc (FR-TASK-10 trong `docs/srs/SRS.md`), là hạng mục kế tiếp của Sprint 2 theo `context/sprint-plan.md`. Sau đợt này chỉ còn activity timeline hợp nhất (FR-TASK-11) là khép lại Sprint 2.

Phạm vi đợt này: tệp gắn trực tiếp vào công việc, tải lên và xoá ở trang chi tiết công việc, tải xuống có kiểm soát quyền.

## 2. Phạm vi

Trong phạm vi:

- Bảng `task_attachments` và model tương ứng.
- Tải lên nhiều tệp một lần ở trang `tasks.show`.
- Tải xuống qua route có kiểm tra quyền, không lộ đường dẫn lưu trữ.
- Xoá mềm bản ghi đính kèm, ghi audit log.
- Giao diện danh sách tệp trong trang chi tiết công việc.

Ngoài phạm vi (mục 11 nêu lý do):

- Đính kèm tệp vào bình luận.
- Bảng attachment dùng chung (polymorphic) cho Project/Approval.
- Preview ảnh/PDF inline.
- Dọn file rác trên disk sau khi xoá mềm.
- Chuyển sang S3/R2.
- Quét virus.

## 3. Quyết định thiết kế

| Quyết định | Lựa chọn | Lý do |
|---|---|---|
| Quan hệ dữ liệu | Bảng `task_attachments` gắn trực tiếp vào `tasks` | Đúng FR-TASK-10; tránh abstraction polymorphic khi chưa có nhu cầu thực tế (`README.md` §4) |
| Lưu trữ | Disk `local` (ngoài `public/`) | Tệp công việc là dữ liệu nội bộ; disk `public` cho phép ai có link cũng tải được, vi phạm NFR-11 |
| Tải xuống | Route riêng, Policy kiểm tra rồi `Storage::download()` | Không lộ đường dẫn, kiểm quyền ở mọi lượt tải |
| Phân quyền | Tái sử dụng `task.view`, `task.comment`, `task.update` | Không phát sinh permission mới ngoài `context/permission-matrix.md` |
| Luồng UI | Chỉ ở trang chi tiết công việc | Task đã tồn tại khi upload nên không có tệp mồ côi từ form tạo bị huỷ |
| Xoá | Xoá mềm bản ghi, giữ file trên disk | Khôi phục được khi xoá nhầm; dọn file rác tách thành việc riêng |
| Tổ chức code | `TaskAttachmentController` riêng + Action | Đồng nhất với `TaskCommentController`; `TaskController` đã có 12 method |

## 4. Database

Migration mới: `create_task_attachments_table`.

```
id                bigint unsigned, PK
task_id           bigint unsigned, FK -> tasks.id, restrict on delete
uploader_id       bigint unsigned, FK -> users.id, restrict on delete
disk              varchar(30)
path              varchar(2048)
original_name     varchar(255)
mime_type         varchar(150)
size_bytes        bigint unsigned
created_at
updated_at
deleted_at
```

Index: `(task_id, created_at)` phục vụ truy vấn danh sách tệp theo công việc.

Ghi chú:

- `disk` lưu tên disk tại thời điểm upload. Khi hệ thống chuyển sang S3/R2 ở sprint sau, tệp cũ vẫn đọc được từ disk cũ mà không cần backfill.
- `path` do `Illuminate\Http\UploadedFile::store()` sinh (tên ngẫu nhiên), không dùng tên gốc để tránh path traversal và trùng tên.
- `original_name` là tên hiển thị và tên khi tải xuống.
- `restrict on delete` ở `task_id` nhất quán với `task_comments`: không cho xoá cứng công việc còn tệp.

## 5. Backend

### 5.1 Model

`App\Models\TaskAttachment` — dùng `SoftDeletes`, quan hệ `task()` và `uploader()`. Accessor `size_for_humans` để frontend không phải tự quy đổi.

`App\Models\Task` bổ sung quan hệ `attachments(): HasMany` sắp xếp mặc định theo `created_at` giảm dần.

### 5.2 FormRequest

`StoreTaskAttachmentRequest`:

- `authorize()`: `$this->user()->can('attach', $this->route('task'))`.
- Quy tắc:
  - `files`: `required|array|min:1|max:5`
  - `files.*`: `required|file|max:10240` (KB, tương đương 10MB) và `mimetypes:` theo whitelist.

Whitelist MIME:

```text
application/pdf
application/msword
application/vnd.openxmlformats-officedocument.wordprocessingml.document
application/vnd.ms-excel
application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
application/vnd.ms-powerpoint
application/vnd.openxmlformats-officedocument.presentationml.presentation
text/csv
text/plain
application/zip
image/jpeg
image/png
image/webp
```

Dùng `mimetypes` (đọc MIME thật của tệp) chứ không dùng `mimes` (suy từ phần mở rộng), theo NFR-11. Thông báo lỗi tiếng Việt, hiển thị cạnh vùng upload.

### 5.3 Action

`StoreTaskAttachmentAction::execute(User $actor, Task $task, array $files): void`

1. Lưu từng tệp vào disk `local`, thư mục `task-attachments/{task_id}`.
2. Mở transaction, tạo bản ghi cho từng tệp.
3. Nếu bất kỳ bước nào ném exception: rollback transaction rồi xoá các tệp vừa ghi lên disk, ném lại exception. Đây là pattern đã dùng ở `UpdateProfileAction`.

Thao tác ghi disk nằm ngoài transaction (`prompts/02_ARCHITECTURE.md` §7: không đặt thao tác chậm trong transaction), phần bù trừ do khối `catch` đảm nhiệm.

`DeleteTaskAttachmentAction::execute(User $actor, TaskAttachment $attachment): void`

1. Ghi audit `task.attachment_deleted` qua `AuditLogger`, subject là attachment, metadata gồm `task_id`, `original_name`, `size_bytes`.
2. Xoá mềm bản ghi. File trên disk giữ nguyên.

Cả hai bước trong cùng transaction để không có audit mồ côi khi xoá lỗi.

### 5.4 Enum

`App\Enums\AuditAction` thêm:

```php
case TaskAttachmentUploaded = 'task.attachment_uploaded';
case TaskAttachmentDeleted = 'task.attachment_deleted';
```

Upload cũng ghi audit (một bản ghi cho mỗi lần upload, metadata liệt kê số tệp và tổng dung lượng) — tệp đính kèm là dữ liệu nghiệp vụ có thể chứa thông tin nhạy cảm, `README.md` §4 yêu cầu audit cho thao tác nhạy cảm.

### 5.5 Policy

Bổ sung vào `TaskPolicy`:

| Method | Điều kiện |
|---|---|
| `attach(User, Task)` | `task.view` **và** `task.comment` — cùng điều kiện với `comment()` |
| `downloadAttachment(User, Task)` | `task.view` |

Thêm `TaskAttachmentPolicy`:

| Method | Điều kiện |
|---|---|
| `delete(User, TaskAttachment)` | `$attachment->uploader_id === $user->id` **hoặc** `$user->can('task.update')` |

Tách policy riêng vì đối tượng được kiểm tra là attachment, không phải task.

### 5.6 Controller và Route

`TaskAttachmentController` với ba method:

| Method | Route | Hành vi |
|---|---|---|
| `store` | `POST tasks/{task}/attachments` → `tasks.attachments.store` | Authorize qua FormRequest, gọi Action, redirect về `tasks.show` kèm flash `success` |
| `download` | `GET tasks/{task}/attachments/{attachment}/download` → `tasks.attachments.download` | `authorize('downloadAttachment', $task)`, trả `Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name)` |
| `destroy` | `DELETE tasks/{task}/attachments/{attachment}` → `tasks.attachments.destroy` | `authorize('delete', $attachment)`, gọi Action, redirect kèm flash |

Route dùng scoped binding (`->scopeBindings()`) để `{attachment}` bắt buộc thuộc `{task}` trong URL — chặn việc đọc tệp của công việc khác qua một công việc mình có quyền xem. Đặt trong nhóm middleware `auth, verified, active`, khai báo **trước** `Route::resource('tasks', ...)` giống các route action hiện có.

`download` trả về file stream nên không đi qua Inertia; frontend dùng thẻ `<a href>` thường.

### 5.7 Dữ liệu truyền sang trang chi tiết

`TaskController::show()` bổ sung:

- Eager load `attachments.uploader:id,name,avatar_path` (tránh N+1 theo NFR-02).
- Prop `attachments`: danh sách tệp, mỗi phần tử kèm `can_delete` tính sẵn từ `TaskAttachmentPolicy` để frontend không tự suy diễn quyền.
- Prop `actions.attach`: kết quả `can('attach', $task)`.

Không phân trang danh sách tệp: số tệp trên một công việc dự kiến nhỏ (giới hạn 5 tệp mỗi lần upload, thực tế vài chục là tối đa). Nếu về sau vượt ngưỡng sẽ bổ sung phân trang như đã làm với bình luận.

## 6. Frontend

Component mới `resources/js/Components/TaskAttachmentList.vue`, nhúng trong `Pages/Tasks/Show.vue` thành một mục riêng.

Nội dung:

- Vùng chọn tệp (input nhiều tệp, hỗ trợ kéo thả), hiển thị danh sách tệp đang chọn trước khi gửi, cho phép bỏ bớt.
- Nút "Tải lên" ở trạng thái loading trong lúc gửi; hiển thị lỗi validation theo từng tệp ngay cạnh vùng upload.
- Bảng tệp đã đính kèm: tên gốc, dung lượng đọc được, người tải lên, thời điểm, nút "Tải xuống" và "Xoá".
- Nút "Xoá" chỉ hiện khi `can_delete`; vùng upload chỉ hiện khi `actions.attach`. Backend vẫn chặn độc lập.
- Xoá có hộp thoại xác nhận (PrimeVue `ConfirmDialog`, đúng `prompts/06_UI_UX.md` §8).
- Empty state: "Chưa có tệp đính kèm" kèm gợi ý hành động khi người dùng có quyền tải lên.
- Responsive: trên mobile bảng chuyển sang danh sách dạng thẻ, đúng `prompts/06_UI_UX.md` §2.

Upload gửi bằng `router.post` với `forceFormData: true` của Inertia, dùng `onProgress` cho thanh tiến trình.

## 7. Cấu hình

`config/filesystems.php` giữ nguyên; disk `local` đã có sẵn. `.env.example` không đổi.

PHP `upload_max_filesize` và `post_max_size` phải ≥ 50MB để cho phép 5 tệp × 10MB trong một request. Ghi chú điều này vào phần triển khai của kế hoạch; nếu môi trường chưa đạt, request bị chặn ở tầng PHP trước khi tới Laravel và người dùng nhận lỗi khó hiểu, nên `StoreTaskAttachmentRequest` cần xử lý trường hợp `$request->all()` rỗng do vượt `post_max_size` và trả về thông báo tiếng Việt rõ ràng.

## 8. Testing

`tests/Feature/Task/TaskAttachmentControllerTest.php`, dùng `Storage::fake('local')`:

Luồng chính:

- Người có `task.view` + `task.comment` tải lên được, bản ghi và file cùng tồn tại.
- Tải lên nhiều tệp trong một request tạo đúng số bản ghi.
- Người có `task.view` tải xuống được, phản hồi trả đúng tên gốc.
- Người tải lên xoá được tệp của mình.
- Người có `task.update` xoá được tệp của người khác.

Luồng lỗi và bảo mật:

- Thiếu `task.comment` → 403 khi upload.
- Thiếu `task.view` → 403 khi tải xuống.
- Người không phải người tải lên và không có `task.update` → 403 khi xoá.
- MIME ngoài whitelist (ví dụ `application/x-msdownload`) → lỗi validation, không tạo bản ghi, không ghi file.
- Tệp vượt 10MB → lỗi validation.
- Quá 5 tệp một lần → lỗi validation.
- Attachment thuộc công việc khác → 404 nhờ scoped binding.

Hành vi phụ:

- Xoá ghi đúng bản ghi audit `task.attachment_deleted` và file vẫn còn trên disk.
- Upload ghi bản ghi audit `task.attachment_uploaded`.
- Trang `tasks.show` trả prop `attachments` với `can_delete` đúng theo từng người dùng.

`tests/Feature/Task/TaskPolicyTest.php` bổ sung case cho `attach` và `downloadAttachment`.

## 9. Rủi ro

| Rủi ro | Xử lý |
|---|---|
| File ghi lên disk nhưng transaction DB lỗi → file mồ côi | Khối `catch` trong Action xoá các file vừa ghi |
| Người dùng đoán URL để tải tệp của công việc khác | Scoped binding + Policy `downloadAttachment` trên task chứa tệp |
| Tệp thực thi được tải lên rồi mở từ server | Whitelist MIME thật, lưu ngoài `public/`, chỉ trả qua `Storage::download()` (luôn kèm `Content-Disposition: attachment`) |
| Disk đầy do file rác sau xoá mềm | Chấp nhận ở đợt này; ghi vào việc tồn đọng để làm command dọn dẹp |
| Request vượt `post_max_size` của PHP | Xử lý ở FormRequest và ghi chú cấu hình môi trường (mục 7) |

## 10. Deliverables

Backend:

- Migration `create_task_attachments_table`.
- Model `TaskAttachment`; quan hệ `attachments()` trên `Task`.
- `StoreTaskAttachmentRequest`.
- `StoreTaskAttachmentAction`, `DeleteTaskAttachmentAction`.
- `TaskAttachmentPolicy`; bổ sung `attach`, `downloadAttachment` vào `TaskPolicy`.
- `TaskAttachmentController` và 3 route.
- 2 case mới trong `AuditAction`.
- Bổ sung dữ liệu đính kèm vào `TaskController::show()`.

Frontend:

- `Components/TaskAttachmentList.vue`.
- Cập nhật `Pages/Tasks/Show.vue`.

Tài liệu:

- Cập nhật `docs/srs/SRS.md`: FR-TASK-10 chuyển sang *Đã triển khai*, bổ sung `task_attachments` vào mục 10.4, render lại PDF bằng `node docs/srs/build-pdf.mjs`.
- Cập nhật `context/sprint-plan.md` phần trạng thái hiện tại.

## 11. Ngoài phạm vi và lý do

| Hạng mục | Lý do hoãn |
|---|---|
| Đính kèm trong bình luận | Tăng độ phức tạp luồng tạo (upload trước hay sau khi gửi bình luận) mà chưa có nhu cầu xác nhận |
| Bảng attachment polymorphic | Project và Approval chưa tồn tại; tạo abstraction sớm trái `README.md` §4 |
| Preview ảnh/PDF inline | Không cần thiết để hoàn thành FR-TASK-10 |
| Command dọn file rác | Việc vận hành độc lập, gom vào Sprint 7 (Hardening) |
| Chuyển sang S3/R2 | Cần hạ tầng thật; cột `disk` đã chuẩn bị sẵn cho việc chuyển đổi |
| Quét virus | Phụ thuộc dịch vụ ngoài, thuộc Sprint 7 |
