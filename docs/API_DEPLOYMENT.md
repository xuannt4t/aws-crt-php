# Triển khai Mobile API

API v1 dùng chung Laravel hiện tại, không cần thêm Nginx site hay Supervisor process. Sau khi triển khai, tài liệu tích hợp nằm tại:

- `https://dormida.task.pro.vn/docs/api`
- `https://dormida.task.pro.vn/docs/api.json`
- Base URL: `https://dormida.task.pro.vn/api/v1`

## Cấu hình production

Giữ các giá trị sau trong `/var/www/dormida/shared/.env`:

```dotenv
APP_URL=https://dormida.task.pro.vn
API_ACCESS_TOKEN_TTL_MINUTES=300
API_REFRESH_TOKEN_TTL_DAYS=30
API_VERSION=1.0.0
```

Không đưa access token, refresh token, mật khẩu hoặc nội dung `.env` lên Git.

## Triển khai release mới

Đặt script vào quyền thực thi một lần:

```bash
cd /var/www/dormida/current
chmod +x deploy/deploy-release.sh
```

Sau mỗi lần merge code vào `main`, chạy:

```bash
cd /var/www/dormida/current
sudo -u deploy bash deploy/deploy-release.sh
```

Nếu tài khoản `deploy` không được phép gọi `sudo supervisorctl` và `sudo systemctl`, chạy riêng các lệnh cuối bằng tài khoản `vtc` có quyền sudo:

```bash
sudo supervisorctl restart dormida-reverb
sudo systemctl reload php8.3-fpm nginx
sudo supervisorctl status
```

Queue được yêu cầu khởi động lại mềm bằng `php artisan queue:restart`; worker hoàn thành job hiện tại rồi Supervisor tự tạo process mới. Không cần chạy lại seeder khi chỉ cập nhật code/API. Chỉ chạy seeder nếu release notes của thay đổi đó yêu cầu rõ ràng.

## Kiểm tra sau triển khai

```bash
cd /var/www/dormida/current
php artisan migrate:status
php artisan route:list --path=api/v1
php artisan scramble:analyze
curl -I https://dormida.task.pro.vn/docs/api
curl -sS https://dormida.task.pro.vn/docs/api.json | head
sudo supervisorctl status
```

Ứng dụng mobile đăng nhập bằng `POST /api/v1/auth/login`. Access token có hạn 5 giờ, refresh token có hạn 30 ngày và được xoay vòng qua `POST /api/v1/auth/refresh`.

## Rollback code

Liệt kê release và xác định chính xác release tốt gần nhất:

```bash
readlink -f /var/www/dormida/current
ls -1dt /var/www/dormida/releases/*
```

Sau đó đổi symlink, thay `<RELEASE_TOT>` bằng đường dẫn đã kiểm tra ở trên:

```bash
sudo -u deploy ln -sfn <RELEASE_TOT> /var/www/dormida/current
cd /var/www/dormida/current
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
sudo supervisorctl restart dormida-reverb
sudo systemctl reload php8.3-fpm nginx
```

Không tự động rollback migration: phải xem migration của release lỗi trước vì migration ngược có thể làm mất dữ liệu.
