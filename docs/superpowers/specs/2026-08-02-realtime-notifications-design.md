# Thiết kế thông báo realtime và tiêu đề ứng dụng

## Mục tiêu

Bổ sung trung tâm thông báo tức thời cho DORMIDA WORK bằng Laravel Notifications,
database và Reverb; bảo đảm người dùng không mất thông báo khi WebSocket gián
đoạn. Đồng thời sửa toast flash và tiêu đề tab trình duyệt.

## Kiến trúc

- Task activity phát domain event đồng bộ; notification queue dùng `afterCommit`
  để chỉ chạy sau khi transaction nghiệp vụ thành công. Laravel lưu thông báo
  vào bảng `notifications` chuẩn và broadcast cùng
  payload qua private channel `App.Models.User.{id}`.
- Laravel Echo + `pusher-js` kết nối Reverb. Component chuông nạp 10 thông báo
  gần nhất từ endpoint JSON, sau đó ghép thông báo broadcast mới vào danh sách.
- Badge hiển thị số chưa đọc. Người dùng có thể đánh dấu một hoặc toàn bộ thông
  báo đã đọc; mọi endpoint chỉ thao tác thông báo thuộc chính user đăng nhập.
- Database là nguồn dữ liệu chuẩn. Khi reconnect hoặc mở lại dropdown, frontend
  gọi endpoint để đồng bộ lại dữ liệu bị lỡ.
- Queue worker phát broadcast. Cấu hình dev/test hiện tại tiếp tục hoạt động với
  queue sync; production dùng Redis/Supervisor đã có trong runbook.

## Sự kiện và người nhận

| Sự kiện                              | Người nhận                   |
| ------------------------------------ | ---------------------------- |
| Giao hoặc đổi người phụ trách        | Người phụ trách mới          |
| Đổi trạng thái hoặc tiến độ/số lượng | Người tạo và người phụ trách |
| Thêm bình luận hoặc tệp              | Người tạo và người phụ trách |
| Sắp đến hạn trong 24 giờ             | Người tạo và người phụ trách |
| Đã quá hạn                           | Người tạo và người phụ trách |

Actor không nhận thông báo do chính mình tạo. Danh sách người nhận được khử
trùng. Mỗi payload gồm `event`, `title`, `message`, `url`, `task_id`, `actor` và
`created_at` để frontend không cần truy vấn bổ sung.

## Chống gửi trùng thông báo hạn

Scheduler chạy mỗi 5 phút. Thông báo `due_soon` và `overdue` dùng
`deduplication_key` ổn định theo task, mốc và người nhận. Trước khi gửi, hệ thống
kiểm tra key trong database notifications; một người chỉ nhận một thông báo cho
mỗi mốc của một task. Scheduler dùng `withoutOverlapping`.

Không gửi cho task ở trạng thái hoàn thành hoặc đã hủy/xóa. Task không có hạn
hoặc không có người liên quan cũng bị bỏ qua.

## Giao diện

- Chuông nằm bên trái khối hồ sơ ở header desktop và mobile, có badge tối đa
  hiển thị `99+`.
- Dropdown rộng khoảng 380 px, hiển thị trạng thái chưa đọc rõ ràng, thời gian
  tương đối, liên kết tới công việc và nút “Đánh dấu tất cả đã đọc”.
- Thông báo broadcast mới xuất hiện ngay trên đầu danh sách và đồng thời hiện
  toast ngắn; focus, Escape và click ngoài đóng dropdown.
- Toast flash theo dõi toàn bộ object flash thay vì chỉ primitive message, nên
  các thao tác liên tiếp có cùng nội dung vẫn hiển thị.
- Title luôn theo mẫu `<Tên trang> - DORMIDA WORK`; cấu hình mẫu không còn dùng
  tên mặc định `Laravel`.

## Endpoint

- `GET /notifications` — 10 bản gần nhất và tổng chưa đọc.
- `PATCH /notifications/{notification}/read` — đánh dấu một bản đã đọc.
- `PATCH /notifications/read-all` — đánh dấu toàn bộ đã đọc.

Các route yêu cầu `auth`; notification được resolve qua user hiện tại thay vì
route model binding toàn cục để ngăn đọc chéo tài khoản.

## Xử lý lỗi

- Endpoint trả validation/authorization chuẩn Laravel; frontend giữ dropdown và
  hiển thị toast lỗi nếu đồng bộ hoặc đánh dấu đã đọc thất bại.
- WebSocket reconnect theo cơ chế Pusher/Echo. Khi kết nối lại, component gọi
  endpoint để bù thông báo bị lỡ.
- Lỗi broadcast không làm mất lịch sử vì bản database vẫn là nguồn chuẩn.

## Kiểm thử

- Feature tests cho payload, đúng người nhận, loại actor, deduplication hạn,
  endpoint danh sách và quyền đánh dấu đã đọc.
- Test shared props/route auth để bảo vệ tích hợp Inertia.
- Chạy Pest toàn bộ, Pint, ESLint, Prettier, TypeScript và Vite production build.
- Kiểm tra thủ công bằng hai tài khoản: thao tác ở tài khoản A và quan sát badge,
  dropdown/toast cập nhật tức thời ở tài khoản B.

## Ngoài phạm vi

- Email, SMS, push notification hệ điều hành và trang lịch sử toàn màn hình.
- Tùy chỉnh loại thông báo theo từng người dùng.
