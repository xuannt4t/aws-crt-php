# Business Rules

## 1. Tổ chức

- Tổ chức có thể có nhiều cấp.
- Mỗi người dùng có đơn vị chính.
- Một người có thể tham gia nhiều dự án ngoài đơn vị chính.
- Quyền xem dữ liệu có thể phụ thuộc role, permission, đơn vị và quan hệ với bản ghi.

## 2. Công việc

Một công việc có tối thiểu:

- Tiêu đề.
- Người tạo.
- Đơn vị hoặc phạm vi sở hữu.
- Trạng thái.
- Mức ưu tiên.
- Thời hạn khi nghiệp vụ yêu cầu.

Công việc có thể có:

- Người phụ trách chính.
- Nhiều người phối hợp.
- Người theo dõi.
- Công việc cha.
- Dự án.
- Mục tiêu.
- Tệp.
- Bình luận.
- Checklist.
- Lịch sử trạng thái.

## 3. Tiến độ

- Tiến độ từ 0 đến 100.
- Công việc hoàn thành phải đạt điều kiện hoàn thành đã xác định.
- Tiến độ công việc cha có thể tính từ công việc con hoặc nhập thủ công tùy cấu hình.
- Không tự động đổi trạng thái nếu chưa có rule rõ ràng.

## 4. Deadline

- Công việc quá hạn khi chưa hoàn thành và thời hạn nhỏ hơn thời điểm hiện tại.
- Gia hạn phải lưu lịch sử.
- Thay đổi deadline quan trọng phải có lý do khi chính sách yêu cầu.

## 5. Phê duyệt

- Người tạo không mặc định được tự phê duyệt.
- Luồng phê duyệt có thể một hoặc nhiều bước.
- Mỗi quyết định phải lưu người thực hiện, thời gian và ghi chú.
- Từ chối hoặc yêu cầu chỉnh sửa phải có lý do.
- Không sửa nội dung đã duyệt nếu chưa mở lại hoặc tạo phiên bản mới.

## 6. Dự án

- Dự án có owner.
- Thành viên dự án có vai trò trong dự án.
- Quyền dự án không thay thế hoàn toàn permission hệ thống.
- Hoàn thành dự án phải kiểm tra công việc còn mở hoặc có ngoại lệ được ghi nhận.

## 7. Báo cáo

- Số liệu phải theo phạm vi người dùng được phép xem.
- Báo cáo cần ghi rõ thời gian chốt dữ liệu.
- Export dữ liệu nhạy cảm phải được phân quyền và audit.
