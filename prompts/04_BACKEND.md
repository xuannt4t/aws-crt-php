# 04 — BACKEND

## 1. FormRequest

Mọi thao tác create/update quan trọng phải dùng FormRequest.

FormRequest chịu trách nhiệm:

- Validation.
- Chuẩn hóa input đơn giản.
- Authorization mức request khi phù hợp.

Không đặt business rule phức tạp trong FormRequest.

## 2. Controller

Controller ngắn, dễ đọc và không chứa query phức tạp.

Ví dụ luồng:

```php
public function store(StoreTaskRequest $request, CreateTaskAction $action): RedirectResponse
{
    $task = $action->execute($request->user(), TaskData::from($request->validated()));

    return redirect()
        ->route('tasks.show', $task)
        ->with('success', 'Tạo công việc thành công.');
}
```

## 3. DTO

Dùng DTO khi:

- Input có nhiều trường.
- Input truyền qua nhiều lớp.
- Cần type safety.
- Cần mapping rõ ràng.

DTO không truy vấn database.

## 4. Service/Action

Service hoặc Action:

- Nhận input đã chuẩn hóa.
- Kiểm tra business rule.
- Quản lý transaction.
- Gọi model/repository.
- Dispatch event.
- Trả entity hoặc result object.

## 5. Policy

Mỗi tài nguyên chính cần policy:

- viewAny
- view
- create
- update
- delete
- restore
- forceDelete khi cần
- Các action nghiệp vụ như assign, approve, export

Không chỉ ẩn nút ở frontend. Backend luôn phải authorize.

## 6. API Resource

Dùng Resource cho JSON API hoặc dữ liệu cấu trúc lớn.

- Không trả toàn bộ model.
- Không để lộ cột nội bộ.
- Dùng `whenLoaded`.
- Tránh query phát sinh trong Resource.

## 7. Notification

Notification có thể qua:

- Database.
- Broadcast.
- Mail.

Nội dung phải ngắn, có actor, action và URL điều hướng.

Không gửi mail trong request nếu có thể chạy queue.

## 8. Upload

Upload phải kiểm tra:

- MIME.
- Extension.
- Dung lượng.
- Quyền upload.
- Tên file an toàn.
- Visibility.
- Virus scan nếu môi trường yêu cầu.

Không dùng tên gốc làm storage key duy nhất.

## 9. Import/Export

- Import lớn chạy queue.
- Có validate từng dòng.
- Có báo cáo lỗi.
- Có giới hạn file.
- Có tiến độ nếu xử lý lâu.
- Export lớn lưu tạm trên object storage và tạo link có hạn.

## 10. Scheduler

Scheduler dùng cho:

- Nhắc deadline.
- Tổng hợp báo cáo.
- Dọn file tạm.
- Đồng bộ trạng thái.
- Retry tác vụ định kỳ.

Command phải idempotent khi có thể.

## 11. Logging

Log phải có context:

- user_id
- request_id hoặc trace_id
- action
- entity_type
- entity_id

Không log:

- Password.
- Access token.
- Refresh token.
- Secret.
- Nội dung tệp nhạy cảm.

## 12. Security

- Chống mass assignment.
- Kiểm tra authorization trước khi mutate.
- Dùng CSRF của Laravel/Inertia.
- Rate limit login và endpoint nhạy cảm.
- Validate redirect URL.
- Escape nội dung người dùng.
- Không tin MIME do client gửi.
- Dùng signed URL cho file riêng tư.
