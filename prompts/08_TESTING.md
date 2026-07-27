# 08 — TESTING

## 1. Mục tiêu

Test bảo vệ business rule và quyền truy cập, không chỉ tăng coverage.

## 2. Cấp độ

### Unit test

Dùng cho:

- Enum.
- Value object.
- Rule.
- Calculation.
- Pure service.

### Feature test

Dùng cho:

- Route.
- Authorization.
- Validation.
- Database mutation.
- Inertia response.
- JSON API.

### Integration test

Dùng cho:

- Queue.
- Realtime.
- Storage.
- Import/export.
- Tích hợp ngoài với fake adapter.

## 3. Test bắt buộc cho module

- User có quyền thực hiện thành công.
- User không có quyền bị từ chối.
- Validation quan trọng.
- Business rule chính.
- Không thay đổi dữ liệu khi transaction lỗi.
- Filter và pagination.
- Soft delete/restore nếu có.
- Side effect quan trọng được dispatch.
- Trạng thái không hợp lệ bị chặn.

## 4. Database

- Dùng factory.
- Không phụ thuộc thứ tự test.
- Không dùng dữ liệu production.
- Test tên rõ hành vi.
- Tránh seeder toàn hệ thống cho mọi test nếu không cần.

## 5. Frontend

Ưu tiên test:

- Composable có logic.
- Component nghiệp vụ quan trọng.
- Form validation mapping.
- Permission-driven action.
- Utility chuyển đổi dữ liệu.

Không cần snapshot mọi component trình bày đơn giản.

## 6. Quy tắc

- Một test chỉ nên kiểm tra một hành vi chính.
- Arrange, Act, Assert rõ ràng.
- Không mock Eloquent quá mức.
- Fake queue, notification, event khi phù hợp.
- Test lỗi phải xác nhận dữ liệu không bị thay đổi.
