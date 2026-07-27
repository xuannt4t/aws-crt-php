# 03 — DATABASE

## 1. Công nghệ

- MySQL 8.
- Charset `utf8mb4`.
- Collation thống nhất toàn hệ thống.
- Mọi migration phải có rollback hợp lệ khi khả thi.

## 2. Quy tắc đặt tên

- Tên bảng: số nhiều, snake_case.
- Khóa ngoại: `{model}_id`.
- Bảng pivot: tên hai thực thể theo thứ tự chữ cái khi không có tên nghiệp vụ tốt hơn.
- Boolean bắt đầu bằng `is_`, `has_`, `can_`.
- Thời điểm kết thúc bằng `_at`.
- Ngày kết thúc bằng `_date`.
- Trạng thái dùng `status`.

## 3. Khóa chính

Mặc định dùng bigint unsigned auto increment.

Chỉ dùng UUID/ULID khi:

- ID lộ ra ngoài và cần khó đoán.
- Có đồng bộ phân tán.
- Có yêu cầu merge dữ liệu từ nhiều nguồn.

Không dùng UUID theo thói quen nếu không có lợi ích rõ ràng.

## 4. Cột hệ thống

Không bắt buộc mọi bảng đều có đủ `created_by`, `updated_by`, `deleted_by`.

Chỉ thêm khi có giá trị audit thực tế.

Thông thường:

```text
id
created_at
updated_at
deleted_at
```

Bảng nghiệp vụ nhạy cảm có thể thêm:

```text
created_by
updated_by
deleted_by
```

## 5. Foreign key

- Khai báo foreign key khi không gây cản trở vận hành.
- Chọn `cascade`, `restrict`, `set null` theo nghiệp vụ.
- Không mặc định cascade delete.
- Cột nullable khi bản ghi con vẫn có ý nghĩa nếu bản ghi cha bị xóa mềm.

## 6. Index

Bắt buộc đánh giá index cho:

- Foreign key.
- `status`.
- `assigned_to`.
- `department_id`.
- `project_id`.
- `due_at`.
- `created_at`.
- Các cột thường filter/sort.
- Composite index theo đúng thứ tự truy vấn thực tế.

Không tạo index dư thừa. Xác nhận bằng `EXPLAIN` với query quan trọng.

## 7. Enum và status

Ưu tiên:

- PHP backed enum.
- Cột `varchar` ngắn hoặc tiny integer có mapping rõ ràng.

Không dùng MySQL ENUM nếu cần thay đổi linh hoạt hoặc triển khai nhiều môi trường khó đồng bộ.

## 8. Tiền và phần trăm

- Tiền: integer theo đơn vị nhỏ nhất hoặc decimal tùy nghiệp vụ.
- Không dùng float.
- Phần trăm: decimal với precision phù hợp.
- Ghi rõ currency nếu có nhiều loại tiền.

## 9. Thời gian

- Lưu timestamp theo UTC.
- Chuyển timezone ở lớp hiển thị.
- Dùng `datetime` hoặc `timestamp` nhất quán.
- Deadline phải xác định có bao gồm thời điểm cuối hay không.
- Không so sánh ngày bằng string không chuẩn hóa.

## 10. Soft delete

Dùng soft delete cho:

- User.
- Organization unit.
- Project.
- Task nếu cần khôi phục.
- Danh mục nghiệp vụ.

Không dùng soft delete cho:

- Bảng log append-only.
- Pivot có thể tạo lại.
- Dữ liệu tạm.
- Bảng history bất biến.

## 11. Audit và history

Với thay đổi trạng thái quan trọng, tạo bảng history riêng thay vì chỉ dựa vào `updated_at`.

Ví dụ:

```text
task_status_histories
approval_histories
assignment_histories
```

History nên lưu:

- Trạng thái cũ.
- Trạng thái mới.
- Người thực hiện.
- Thời điểm.
- Lý do.
- Metadata cần thiết.

## 12. Migration

Migration phải:

- Có tên rõ ràng.
- Không phụ thuộc dữ liệu cục bộ.
- Không thực hiện tác vụ cực nặng trong một request deploy.
- Có chiến lược backfill riêng với dữ liệu lớn.
- Không sửa migration đã chạy production; tạo migration mới.

## 13. Seeder và Factory

- Seeder tạo role, permission và dữ liệu nền.
- Factory phục vụ test.
- Không đưa dữ liệu thật hoặc secret vào seeder.
- Seeder phải có khả năng chạy lại an toàn khi hợp lý.

## 14. Truy vấn

- Không dùng `SELECT *` trong query nặng.
- Không query trong loop.
- Dùng chunk/cursor cho dữ liệu lớn.
- Cẩn thận khi dùng `whereHas` lồng sâu.
- Phân trang phía server.
- Giới hạn export đồng bộ; export lớn phải chạy queue.
