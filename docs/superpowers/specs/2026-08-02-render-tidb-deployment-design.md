# Thiết kế triển khai DORMIDA WORK lên Render Free và TiDB Cloud

## Mục tiêu

Đưa ứng dụng Laravel 11 hiện tại lên một Render Web Service gói Free, giữ kết nối
MySQL thông qua TiDB Cloud Starter, và duy trì thông báo realtime hoàn toàn qua
Laravel Reverb. Việc triển khai không được tạo tài nguyên trả phí hoặc đặt spending
limit lớn hơn 0 USD.

## Phạm vi và ràng buộc

- Web service chạy trên Render Free tại Singapore từ nhánh `main`.
- Database `dormida_work` đã được nhập vào TiDB Cloud Starter tại Singapore và có
  spending limit 0 USD/tháng.
- Laravel tiếp tục dùng `DB_CONNECTION=mysql`; kết nối TiDB bắt buộc xác minh TLS.
- Một container phải phục vụ HTTP, WebSocket, queue và scheduler vì Render Free
  không cung cấp worker miễn phí riêng.
- Upload local chỉ phục vụ demo và có thể mất sau khi Render ngủ, restart hoặc
  redeploy. Cấu hình object storage nằm ngoài phạm vi lần triển khai đầu tiên.
- Không đưa mật khẩu, `APP_KEY`, `REVERB_APP_SECRET` hoặc thông tin TiDB vào Git.
- Không tạo Render Postgres, Render Key Value, persistent disk hoặc service trả phí.

## Các phương án đã cân nhắc

### 1. Một Render Web Service bằng Docker — lựa chọn

Nginx là cổng công khai duy nhất, PHP-FPM xử lý Laravel, còn Supervisor giữ queue,
scheduler và Reverb sống trong cùng container. Queue, session và cache dùng database
để không cần Redis riêng. Đây là phương án duy nhất giữ đầy đủ tính năng trong phạm
vi Free, đổi lại container có ít tài nguyên và sẽ ngủ khi không có traffic.

### 2. Tách web, worker, scheduler và Reverb

Kiến trúc này dễ vận hành và mở rộng hơn nhưng cần nhiều service, trong đó worker và
cron không có lựa chọn Free phù hợp. Không chọn vì trái yêu cầu chi phí 0 USD.

### 3. Thay Reverb bằng dịch vụ WebSocket bên ngoài

Giảm số process trong container nhưng trái yêu cầu realtime hoàn toàn qua Reverb.
Không chọn.

## Kiến trúc container

Docker build nhiều giai đoạn:

1. Node build Vue/Vite assets.
2. Composer cài dependency production với autoloader tối ưu.
3. Image PHP 8.3-FPM chứa Nginx, Supervisor và các PHP extension cần thiết.

Supervisor chạy đúng năm process:

- `php-fpm`;
- `nginx` trên `0.0.0.0:$PORT`;
- `php artisan queue:work database --sleep=3 --tries=3 --max-time=3600`;
- `php artisan schedule:work`;
- `php artisan reverb:start --host=127.0.0.1 --port=8080`.

Nginx phục vụ `public/`, chuyển PHP sang PHP-FPM, và proxy cả `/app` lẫn `/apps`
sang Reverb nội bộ. Render kết thúc TLS ở edge, nên kết nối public dùng HTTPS/WSS
trên cùng hostname `onrender.com`.

## Khởi động và deploy

Entrypoint tạo các thư mục Laravel cần ghi, đặt quyền, chạy `storage:link`,
`migrate --force`, sau đó cache config/routes/views trước khi exec Supervisor. Nếu
migration hoặc cache thất bại, container dừng để Render đánh dấu deploy lỗi thay vì
phục vụ phiên bản nửa hoàn chỉnh.

`render.yaml` mô tả một Docker Web Service Free, region Singapore, health check
`/up`, và các biến không nhạy cảm. Các secret được đặt thủ công trên Render:

- `APP_KEY`;
- `DB_PASSWORD`;
- `REVERB_APP_SECRET`.

Thông tin TiDB còn lại dùng host gateway Singapore, port 4000, database
`dormida_work`, username có prefix của instance, và CA hệ thống tại
`/etc/ssl/certs/ca-certificates.crt`.

## Reverb frontend và backend

Backend phát broadcast tới Reverb nội bộ qua `127.0.0.1:8080` bằng HTTP. Frontend
dùng hostname hiện tại, port 443 và WSS. `VITE_REVERB_APP_KEY` là public identifier,
được build vào bundle và phải khớp `REVERB_APP_KEY`; secret không bao giờ vào bundle.

Echo sẽ dùng `window.location.hostname` làm fallback để deployment không phụ thuộc
hostname tại thời điểm Docker build. Giá trị scheme production mặc định là HTTPS.

## Dữ liệu và trạng thái

Queue, session và cache dùng các bảng Laravel đã có trong TiDB. Database là trạng
thái bền vững duy nhất trong lần deploy đầu. `storage/` trong container là ephemeral;
mọi ảnh hoặc attachment local phải được xem là dữ liệu demo.

Database hiện đã được đối chiếu sau import: 22 bảng, 7 người dùng và 8 công việc ở
cả MySQL local và TiDB.

## Xử lý lỗi và an toàn chi phí

- Health check chỉ thành công khi Nginx và Laravel phản hồi `/up`.
- Supervisor tự khởi động lại process dài hạn nếu process thoát bất thường.
- Queue job có số lần thử hữu hạn để tránh vòng lặp tiêu tốn tài nguyên.
- TiDB spending limit giữ nguyên 0 USD; khi hết quota hệ thống bị throttle thay vì
  phát sinh phí.
- Trước khi tạo Render service phải kiểm tra Instance Type là Free và không có disk,
  worker, cron hoặc database trả phí.
- Nếu UI hiển thị phí, thẻ hoặc giá lớn hơn 0 USD thì dừng trước thao tác xác nhận.

## Kiểm thử và tiêu chí hoàn thành

- Test tĩnh xác nhận Dockerfile, entrypoint, Nginx, Supervisor và `render.yaml` chứa
  đủ process, proxy, health check và cấu hình Free.
- Docker image build thành công tại local hoặc môi trường build tương thích.
- Toàn bộ Pest, Vitest, ESLint, Prettier và Vite production build đều qua.
- Nhánh tính năng được merge vào `main` và push trước khi tạo Render service.
- Render deploy thành công; `/up`, đăng nhập và trang công việc phản hồi qua HTTPS.
- WebSocket kết nối WSS và thông báo realtime xuất hiện khi queue xử lý job.
- Database trên TiDB vẫn giữ spending limit 0 USD và số bản ghi cốt lõi không đổi.
