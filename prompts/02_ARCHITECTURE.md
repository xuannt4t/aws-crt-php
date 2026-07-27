# 02 — ARCHITECTURE

## 1. Kiểu kiến trúc

Sử dụng modular monolith trên Laravel 11.

Mục tiêu:

- Triển khai nhanh.
- Giữ domain rõ ràng.
- Không chia microservice quá sớm.
- Cho phép tách service trong tương lai nếu có nhu cầu thực tế.

## 2. Cấu trúc backend đề xuất

```text
app/
├── Actions/
├── DTO/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Observers/
├── Policies/
├── Repositories/
├── Rules/
├── Services/
├── Support/
├── Traits/
└── ValueObjects/
```

Có thể nhóm theo domain khi số lượng file tăng:

```text
app/Domains/Tasks/
├── Actions/
├── DTO/
├── Enums/
├── Models/
├── Policies/
├── Repositories/
└── Services/
```

Không chuyển sang domain structure khi dự án còn nhỏ nếu việc đó chỉ làm tăng độ phức tạp.

## 3. Controller

Controller chỉ chịu trách nhiệm:

- Nhận request.
- Gọi authorization.
- Gọi service/action.
- Trả response.

Không đặt:

- Query phức tạp.
- Business rule.
- Xử lý file nặng.
- Gửi notification trực tiếp nếu có nhiều side effect.

## 4. Service và Action

Dùng Service khi:

- Use case gồm nhiều bước.
- Có transaction.
- Phối hợp nhiều repository/model.
- Có side effect.

Dùng Action khi:

- Một hành động cụ thể.
- Input và output rõ ràng.
- Có thể gọi từ controller, command hoặc job.

## 5. Repository

Chỉ dùng Repository khi:

- Query phức tạp.
- Query dùng ở nhiều nơi.
- Cần tách query khỏi nghiệp vụ.
- Cần abstraction cho nguồn dữ liệu.

Không tạo repository chỉ để bọc `Model::find()` hoặc `Model::create()`.

## 6. Event, Listener và Job

Event mô tả điều đã xảy ra:

- `TaskAssigned`
- `TaskSubmittedForApproval`
- `ApprovalRejected`
- `ProjectCompleted`

Listener xử lý side effect:

- Gửi notification.
- Ghi activity.
- Đồng bộ dashboard.
- Phát realtime event.

Job dùng cho:

- Export.
- Import.
- Gửi email hàng loạt.
- Tạo báo cáo.
- Xử lý tệp.
- Đồng bộ dữ liệu.
- Notification số lượng lớn.

Event quan trọng phải được dispatch sau commit khi side effect phụ thuộc dữ liệu đã ghi thành công.

## 7. Transaction

Bắt buộc dùng transaction khi:

- Tạo bản ghi chính và nhiều bản ghi liên quan.
- Thay đổi trạng thái kèm lịch sử.
- Phê duyệt gây cập nhật nhiều bảng.
- Xóa hoặc khôi phục nhiều thực thể.
- Cập nhật tiến độ cha từ nhiều con.

Không đặt thao tác mạng chậm trong transaction.

## 8. Cache

Cache chỉ dùng khi:

- Dữ liệu đọc nhiều, thay đổi ít.
- Có chiến lược invalidation rõ ràng.
- Có lợi ích đo được.

Các ứng viên:

- Cấu hình hệ thống.
- Permission map.
- Organization tree.
- Dashboard aggregate.
- Danh mục dùng chung.

Không cache query theo user khi chưa có key và invalidation chính xác.

## 9. Realtime

Laravel Reverb dùng cho:

- Notification mới.
- Task được phân công.
- Bình luận mới.
- Trạng thái phê duyệt thay đổi.
- Tiến độ dự án thay đổi.

Channel phải được authorize. Không broadcast dữ liệu nhạy cảm hoặc payload quá lớn.

## 10. Tích hợp ngoài

Mọi tích hợp ngoài phải đi qua adapter/service riêng.

Yêu cầu:

- Timeout.
- Retry có giới hạn.
- Idempotency khi cần.
- Log trace ID.
- Không log secret.
- Có mapping lỗi rõ ràng.

## 11. Xử lý lỗi

- Domain exception cho lỗi nghiệp vụ.
- Validation exception cho input.
- Authorization exception cho quyền.
- Không dùng exception cho flow bình thường.
- Response lỗi phải nhất quán.
- Production không trả stack trace.

## 12. Audit

Ghi audit cho:

- Thay đổi role/permission.
- Thay đổi trạng thái phê duyệt.
- Xóa dữ liệu.
- Export dữ liệu nhạy cảm.
- Đăng nhập thất bại bất thường.
- Thay đổi cấu hình.
- Chuyển người phụ trách hoặc deadline quan trọng.
