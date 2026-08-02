# Thiết kế triển khai production — DORMIDA WORK

Ngày: 2026-08-02

## 1. Mục tiêu

Đưa DORMIDA WORK lên một VPS Ubuntu để dùng thật trong doanh nghiệp (dưới 100 người dùng), với deploy tự động từ nhánh `main` qua GitHub Actions và khả năng rollback tức thì.

Phạm vi gồm hai nửa:

- **Hạ tầng server** — cài đặt và cấu hình thủ công một lần, ghi lại thành runbook.
- **Thay đổi trong repo** — workflow deploy, cấu hình R2, cấu hình broadcasting/Reverb, tài liệu vận hành.

Ngoài phạm vi: HA, load balancer, nhiều node, container hoá, monitoring dạng metrics (Prometheus/Grafana).

## 2. Kiến trúc

Một VPS Ubuntu 24.04 LTS, khuyến nghị 2 vCPU / 4GB RAM / 80GB SSD.

```
Internet
   │
   ├── :443 nginx (TLS, Let's Encrypt)
   │     ├── /              → PHP-FPM 8.3 (unix socket) → public/index.php
   │     ├── /build/*       → tĩnh từ public/build
   │     └── /app/*         → proxy WebSocket → Reverb 127.0.0.1:8080
   │
   ├── MySQL 8      (bind 127.0.0.1)
   ├── Redis 7      (bind 127.0.0.1, requirepass) — session + cache + queue
   ├── Supervisor
   │     ├── dormida-queue  (queue:work redis, 2 process)
   │     └── dormida-reverb (reverb:start)
   ├── Cron         → schedule:run mỗi phút
   └── Cloudflare R2 ← tệp đính kèm + bản backup DB
```

### Layout thư mục

```
/var/www/dormida/
├── releases/<timestamp>/    ← mỗi lần deploy một thư mục, giữ 5 bản gần nhất
├── current -> releases/<timestamp>   ← nginx root
└── shared/
    ├── .env                 ← không nằm trong git
    └── storage/             ← log, framework cache, tệp local cũ
```

Mỗi release symlink `storage` và `.env` sang `shared/`. `nginx` trỏ root vào `current/public`.

Rollback: đổi symlink `current` về release trước, `php-fpm reload`, `queue:restart`. Không cần build lại.

## 3. Pipeline CI/CD

Workflow mới `.github/workflows/deploy.yml`, kích hoạt khi push vào `main` và chỉ chạy sau khi workflow `CI` hiện có thành công.

Các bước:

1. Checkout.
2. `composer install --no-dev --optimize-autoloader --no-interaction`.
3. `npm ci` và `npm run build`.
4. `rsync` qua SSH vào `/var/www/dormida/releases/<timestamp>`, loại trừ `node_modules`, `tests`, `.git`, `.github`, `storage`, `.env`.
5. Trên server, theo thứ tự:
   - symlink `shared/.env` → `<release>/.env`, `shared/storage` → `<release>/storage`
   - `php artisan migrate --force`
   - `php artisan config:cache route:cache view:cache`
   - đổi symlink `current` sang release mới (atomic: `ln -sfn` + `mv -T`)
   - `sudo systemctl reload php8.3-fpm`
   - `php artisan queue:restart`, `sudo supervisorctl restart dormida-reverb`
6. Xoá release cũ, giữ 5 bản gần nhất.

Secrets trong GitHub: `SSH_HOST`, `SSH_USER`, `SSH_PRIVATE_KEY` (khoá riêng chỉ dùng cho deploy), `SSH_PORT`.

### Quy tắc migration

Migration chạy **trước** khi đổi symlink, nên trong khoảnh khắc đó release cũ vẫn phục vụ request trên schema mới. Vì vậy mọi migration phải tương thích ngược với release đang chạy:

- Thêm cột phải để `nullable` hoặc có `default`.
- Xoá cột chỉ thực hiện ở release sau khi code đã ngừng dùng cột đó.
- Không đổi tên cột trực tiếp — thêm cột mới, chuyển dữ liệu, xoá cột cũ ở release sau.

Quy tắc này ghi vào runbook để áp dụng cho mọi sprint tiếp theo.

## 4. Lưu trữ tệp trên Cloudflare R2

Bảng `task_attachments` đã có cột `disk` lưu theo từng bản ghi, và `TaskAttachmentController::download()` đọc `Storage::disk($attachment->disk)`. Nhờ đó việc chuyển sang R2 chỉ ảnh hưởng tệp tải lên **sau** khi chuyển; tệp cũ trên disk `local` vẫn tải về được, không cần migrate dữ liệu.

Thay đổi:

- Thêm gói `league/flysystem-aws-s3-v3`.
- Thêm disk `r2` vào `config/filesystems.php` (driver `s3`, endpoint R2, `use_path_style_endpoint` = false).
- `StoreTaskAttachmentAction` đang hardcode `private const DISK = 'local'`. Thay bằng đọc `config('dormida.attachments.disk')`, mặc định `local` để môi trường dev và test không đổi hành vi.
- Thêm khoá `attachments.disk` vào `config/dormida.php`, đọc từ biến môi trường `DORMIDA_ATTACHMENT_DISK`.
- Production đặt `DORMIDA_ATTACHMENT_DISK=r2`.

Bucket R2: một bucket cho tệp đính kèm (private, truy cập qua ứng dụng), một bucket riêng cho backup.

## 5. Realtime với Reverb

Repo hiện chưa có `laravel/reverb`, chưa có `config/broadcasting.php`, và chưa event nào broadcast. Dựng Reverb lúc này là chuẩn bị hạ tầng trước: service sẽ chạy nhưng không có traffic cho tới khi sprint sau viết event đầu tiên.

Thay đổi:

- Thêm gói `laravel/reverb`, publish `config/broadcasting.php` và `config/reverb.php`.
- `BROADCAST_CONNECTION=reverb` ở production; giữ `log` ở dev và test.
- Reverb nghe `127.0.0.1:8080`, nginx proxy đường dẫn `/app` kèm header `Upgrade`/`Connection`.
- Supervisor giữ tiến trình `reverb:start`.
- Frontend chưa cấu hình Echo trong sprint này — sẽ làm cùng event đầu tiên.

## 6. Bảo mật

- SSH: tắt đăng nhập bằng mật khẩu, tắt đăng nhập root, `ufw` chỉ mở 22/80/443, `fail2ban` bảo vệ sshd.
- Tài khoản deploy riêng, có quyền `sudo` không mật khẩu **chỉ** cho `systemctl reload php8.3-fpm` và `supervisorctl restart dormida-*`.
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` sinh riêng cho production.
- MySQL: user riêng cho ứng dụng, chỉ có quyền trên schema `dormida_work`, không dùng root.
- Redis: `requirepass`, bind loopback.
- Quyền tệp: source thuộc user deploy; `shared/storage` và `bootstrap/cache` ghi được bởi `www-data`.
- Khoá R2: token phạm vi hẹp, chỉ đọc/ghi trên bucket của ứng dụng.

## 7. Backup và giám sát

- Cron mỗi đêm: `mysqldump` schema `dormida_work`, gzip, upload lên bucket backup R2, giữ 14 ngày, xoá bản cục bộ sau khi upload thành công.
- Script backup ghi log riêng và thoát khác 0 khi thất bại, để lỗi nhìn thấy được.
- Tệp đính kèm đã nằm trên R2 nên không backup riêng.
- Log ứng dụng: kênh `daily`, giữ 14 ngày. `logrotate` cho log nginx.
- Health check: endpoint `/up` có sẵn của Laravel 11, gắn vào một dịch vụ ping ngoài (UptimeRobot hoặc tương đương).
- Khôi phục: quy trình restore ghi trong runbook, kèm bước kiểm chứng sau restore.

## 8. Thay đổi trong repo

| Tệp | Việc |
|---|---|
| `composer.json` | thêm `laravel/reverb`, `league/flysystem-aws-s3-v3` |
| `config/broadcasting.php` | tạo mới (publish từ Reverb) |
| `config/reverb.php` | tạo mới |
| `config/filesystems.php` | thêm disk `r2` |
| `config/dormida.php` | thêm `attachments.disk` |
| `app/Actions/Task/StoreTaskAttachmentAction.php` | bỏ hằng `DISK`, đọc từ config |
| `.env.example` | thêm biến R2, Reverb, `DORMIDA_ATTACHMENT_DISK` |
| `.env.production.example` | tạo mới — bản mẫu cho `shared/.env` |
| `.github/workflows/deploy.yml` | tạo mới |
| `deploy/nginx.conf.example` | tạo mới — mẫu cấu hình nginx |
| `deploy/supervisor.conf.example` | tạo mới — mẫu queue + reverb |
| `deploy/backup-db.sh` | tạo mới |
| `docs/deployment.md` | runbook: setup lần đầu, deploy, rollback, restore |

## 9. Kiểm thử

- Test hiện có phải tiếp tục xanh sau khi đổi `StoreTaskAttachmentAction` sang disk cấu hình được.
- Thêm test khẳng định action ghi vào disk lấy từ `config('dormida.attachments.disk')` — đặt config thành một disk giả rồi kiểm tra `Storage::fake()` nhận tệp và cột `disk` của bản ghi khớp giá trị đó.
- Thêm test khẳng định tệp có `disk` cũ (`local`) vẫn tải về được sau khi đổi disk mặc định — bảo vệ đúng tính chất đã dựa vào ở mục 4.
- Không viết test cho workflow deploy; xác minh bằng một lần deploy thật lên server và kiểm tra `/up` trả 200.

## 10. Tiêu chí hoàn thành

1. Push vào `main` khiến ứng dụng mới chạy trên server mà không cần thao tác tay.
2. Rollback về release trước thực hiện được bằng một lệnh, dưới 30 giây.
3. Tệp đính kèm mới lưu trên R2; tệp cũ vẫn tải về được.
4. Queue worker và scheduler chạy dưới supervisor và tự khởi động lại sau reboot.
5. Backup DB đêm đầu tiên xuất hiện trên bucket backup, và restore thử thành công vào một schema tạm.
6. `APP_DEBUG=false`, HTTPS hoạt động, `/up` trả 200.
