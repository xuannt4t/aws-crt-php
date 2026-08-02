# Runbook triển khai production

Tài liệu này dành cho một VPS Ubuntu mới và kiến trúc release dùng symlink tại
`/var/www/dormida/current`. Thay `dormida.example.com` và các giá trị bí mật bằng
giá trị thật trước khi chạy production.

## 1. Yêu cầu server

Cấu hình tối thiểu khuyến nghị: Ubuntu 24.04, 2 vCPU, 4 GB RAM và 80 GB SSD.

```bash
sudo apt update
sudo apt install -y software-properties-common
sudo add-apt-repository -y universe
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-mysql php8.3-redis \
  php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath \
  php8.3-gd mysql-server redis-server supervisor certbot \
  python3-certbot-nginx unzip awscli
sudo systemctl enable --now nginx php8.3-fpm mysql redis-server supervisor
```

## 2. Setup lần đầu

### 2.1. Tạo user deploy và cây thư mục

```bash
sudo adduser --disabled-password --gecos '' deploy
sudo usermod -aG www-data deploy
sudo install -d -o deploy -g deploy -m 0700 /home/deploy/.ssh
sudo install -o deploy -g deploy -m 0600 /dev/null /home/deploy/.ssh/authorized_keys
sudoedit /home/deploy/.ssh/authorized_keys

sudo install -d -o deploy -g www-data -m 2775 \
  /var/www/dormida/releases \
  /var/www/dormida/shared \
  /var/www/dormida/shared/storage \
  /var/www/dormida/shared/storage/app \
  /var/www/dormida/shared/storage/framework/cache \
  /var/www/dormida/shared/storage/framework/sessions \
  /var/www/dormida/shared/storage/framework/views \
  /var/www/dormida/shared/storage/logs
sudo install -d -o root -g root -m 0700 /var/backups/dormida
```

Dán public key của khóa deploy vào `authorized_keys`. Trên máy quản trị, chép
các tệp mẫu của repo lên VPS:

```bash
scp .env.production.example deploy/nginx.conf.example \
  deploy/supervisor.conf.example deploy@dormida.example.com:/tmp/
```

### 2.2. Tạo `.env` production

Trên máy quản trị, sinh key bằng đúng phiên bản code sẽ deploy:

```bash
php artisan key:generate --show
```

Trên VPS, cài tệp env rồi điền `APP_KEY` vừa sinh, mật khẩu DB/Redis, thông tin
Reverb, R2 và SMTP. Token R2 phải có quyền trên đúng hai bucket attachments và
backup, không dùng token toàn tài khoản.

Bốn biến `VITE_REVERB_*` được đóng vào frontend ngay lúc GitHub Actions chạy
`npm run build`, nên phải có trong `.env.production.example` của release. Host
frontend là domain public, port `443`, scheme `https`; không dùng host nội bộ
`127.0.0.1` như tiến trình Reverb trên VPS.

```bash
sudo install -o deploy -g www-data -m 0640 \
  /tmp/.env.production.example /var/www/dormida/shared/.env
sudo -u deploy editor /var/www/dormida/shared/.env
```

### 2.3. Tạo database và cấu hình Redis

Thay hai giá trị mật khẩu bên dưới bằng secret mạnh, đồng thời ghi cùng giá trị
vào `shared/.env`.

```bash
sudo mysql <<'SQL'
CREATE DATABASE dormida_work CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dormida'@'127.0.0.1' IDENTIFIED BY '<mysql-password>';
GRANT ALL PRIVILEGES ON dormida_work.* TO 'dormida'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

sudoedit /etc/redis/redis.conf
# Đặt: requirepass <redis-password>
sudo systemctl restart redis-server
redis-cli -a '<redis-password>' ping
```

Kết quả kiểm tra Redis phải là `PONG`.

### 2.4. TLS và nginx

Lấy chứng chỉ trước khi bật cấu hình nginx có tham chiếu tới chứng chỉ đó:

```bash
export APP_DOMAIN=dormida.example.com
sudo systemctl stop nginx
sudo certbot certonly --standalone -d "$APP_DOMAIN"
sudo sed "s/dormida\.example\.com/$APP_DOMAIN/g" \
  /tmp/nginx.conf.example | sudo tee /etc/nginx/sites-available/dormida >/dev/null
sudo ln -sfn /etc/nginx/sites-available/dormida /etc/nginx/sites-enabled/dormida
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl start nginx
```

### 2.5. Supervisor và quyền sudo giới hạn

```bash
sudo install -o root -g root -m 0644 \
  /tmp/supervisor.conf.example /etc/supervisor/conf.d/dormida.conf
sudo supervisorctl reread
sudo supervisorctl update
```

Trước lần deploy đầu, các process có thể ở trạng thái `BACKOFF` vì symlink
`current` chưa tồn tại. Workflow đầu tiên sẽ khởi động lại chúng sau khi kích
hoạt release.

Xác nhận đường dẫn lệnh bằng `command -v systemctl supervisorctl`, rồi tạo
`/etc/sudoers.d/dormida-deploy` bằng `sudo visudo -f` với đúng nội dung:

```sudoers
deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl restart dormida-queue, /usr/bin/supervisorctl restart dormida-reverb
```

```bash
sudo chmod 0440 /etc/sudoers.d/dormida-deploy
sudo visudo -cf /etc/sudoers.d/dormida-deploy
```

## 3. Cron

Tạo `/etc/cron.d/dormida`:

```cron
* * * * * www-data cd /var/www/dormida/current && php artisan schedule:run >> /dev/null 2>&1
0 2 * * * root /var/www/dormida/current/deploy/backup-db.sh >> /var/log/dormida-backup.log 2>&1
```

```bash
sudo chmod 0644 /etc/cron.d/dormida
sudo touch /var/log/dormida-backup.log
sudo chmod 0600 /var/log/dormida-backup.log
```

## 4. GitHub Actions secrets

Tạo key riêng chỉ dùng cho deploy, không đặt passphrase vì workflow chạy không
tương tác:

```bash
ssh-keygen -t ed25519 -C dormida-github-actions -f ./dormida_deploy_key -N ''
```

Thêm nội dung `dormida_deploy_key.pub` vào
`/home/deploy/.ssh/authorized_keys`, rồi khai báo tại **Repository settings →
Secrets and variables → Actions**:

| Secret            | Giá trị                                    |
| ----------------- | ------------------------------------------ |
| `SSH_HOST`        | IP hoặc hostname SSH của VPS               |
| `SSH_PORT`        | Cổng SSH, thường là `22`                   |
| `SSH_USER`        | `deploy`                                   |
| `SSH_PRIVATE_KEY` | Toàn bộ nội dung file `dormida_deploy_key` |
| `APP_HOST`        | Domain public, không gồm `https://`        |

Xóa bản private key khỏi máy quản trị sau khi lưu nó trong secret manager an
toàn. Không commit bất kỳ key nào vào repo.

## 5. Deploy thường ngày

Merge vào `main`. Workflow `CI` chạy trước; chỉ khi CI xanh thì workflow
`Deploy` mới build và kích hoạt release. Xem tiến độ và log tại tab **Actions**
của repository. Sau lần đầu, kiểm tra:

```bash
curl -fsS https://dormida.example.com/up
ssh deploy@dormida.example.com 'sudo supervisorctl status'
```

## 6. Rollback

```bash
cd /var/www/dormida
PREV=$(ls -1dt releases/*/ | sed -n 2p)
test -n "$PREV"
ln -sfnT "$PWD/$PREV" current.tmp && mv -Tf current.tmp current
sudo systemctl reload php8.3-fpm
php current/artisan queue:restart
sudo supervisorctl restart dormida-reverb
```

Rollback symlink **không hoàn tác migration**. Nếu release lỗi đã chạy migration
phá vỡ tương thích, phải phục hồi DB từ backup; vì vậy mọi migration bắt buộc
tuân thủ quy tắc ở mục tiếp theo.

## 7. Quy tắc migration tương thích ngược

Migration chạy trước khi đổi symlink, nên trong khoảnh khắc đó release cũ vẫn
phục vụ request trên schema mới:

- Thêm cột phải để `nullable` hoặc có `default`.
- Xóa cột chỉ thực hiện ở release sau khi code đã ngừng dùng cột đó.
- Không đổi tên cột trực tiếp — thêm cột mới, chuyển dữ liệu, xóa cột cũ ở
  release sau.

## 8. Khôi phục backup

Không ghi đè production trước khi kiểm chứng. Tải một bản backup từ R2 và restore
vào schema tạm:

```bash
export BACKUP_FILE=dormida-YYYYMMDD-HHMMSS.sql.gz
aws s3 cp "s3://dormida-backups/db/$BACKUP_FILE" "/tmp/$BACKUP_FILE" \
  --endpoint-url 'https://<account-id>.r2.cloudflarestorage.com'
gunzip -t "/tmp/$BACKUP_FILE"

mysql -u root -p -e \
  'DROP DATABASE IF EXISTS dormida_work_restore; CREATE DATABASE dormida_work_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
gunzip -c "/tmp/$BACKUP_FILE" | mysql -u root -p dormida_work_restore
mysql -u root -p dormida_work_restore -e \
  'SELECT COUNT(*) AS users FROM users; SELECT COUNT(*) AS tasks FROM tasks; SELECT COUNT(*) AS attachments FROM task_attachments;'
```

MySQL không hỗ trợ đổi tên database nguyên tử. Sau khi đối chiếu số bản ghi và
kiểm thử ứng dụng trên schema tạm, bật maintenance mode, tạo thêm một backup DB
hiện tại, rồi đổi `DB_DATABASE=dormida_work_restore` trong `shared/.env`:

```bash
cd /var/www/dormida/current
php artisan down --retry=60
sudo /var/www/dormida/current/deploy/backup-db.sh
editor /var/www/dormida/shared/.env
php artisan config:clear
php artisan config:cache
php artisan up
php artisan queue:restart
sudo supervisorctl restart dormida-reverb
```

Chỉ xóa schema cũ sau khi đã vận hành ổn định và có ít nhất một backup đã kiểm
chứng restore được.

## 9. Xử lý sự cố

```bash
sudo supervisorctl status
sudo supervisorctl status dormida-reverb
sudo systemctl status php8.3-fpm --no-pager
redis-cli -a '<redis-password>' ping
tail -n 200 /var/www/dormida/shared/storage/logs/laravel.log
sudo tail -n 200 /var/log/nginx/error.log
sudo tail -n 200 /var/log/supervisor/dormida-queue.log
sudo tail -n 200 /var/log/supervisor/dormida-reverb.log
```

Trong DevTools → Network → WS, kết nối Reverb phải có status `101 Switching
Protocols`. Nếu không có, kiểm tra `VITE_REVERB_HOST`, chứng chỉ HTTPS, location
nginx `/app` và console trình duyệt. Queue worker phải ở trạng thái `RUNNING` vì
notification database và broadcast được đưa qua queue.

Nếu health check lỗi ngay sau deploy, xem log Laravel và nginx trước, sau đó
kiểm tra `readlink -f /var/www/dormida/current` có trỏ đúng release mới không.

## 10. Render Free + TiDB Cloud Starter (demo)

`render.yaml` tạo đúng một Docker Web Service gói Free tại Singapore. Container
dùng Nginx làm cổng public và Supervisor để chạy PHP-FPM, database queue,
scheduler và Reverb. Không tạo Render Postgres, Key Value, worker, cron hay disk.

Trước khi deploy, tạo `APP_KEY` bằng:

```powershell
php artisan key:generate --show
```

Trong TiDB Cloud, mở instance `dormida-work` → **Connect** → **Reset Password**.
Chỉ dán password vào secret `DB_PASSWORD` của Render; không lưu trong repo hay
log. Giữ **Monthly Spending Limit = 0 USD** để khi hết quota instance bị throttle
thay vì phát sinh chi phí.

Các giá trị kết nối không nhạy cảm đã được khai báo trong Blueprint:

```dotenv
DB_CONNECTION=mysql
DB_HOST=gateway01.ap-southeast-1.prod.aws.tidbcloud.com
DB_PORT=4000
DB_DATABASE=dormida_work
DB_USERNAME=T3FYYZVYsjJP5r2.root
MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

Render Free có filesystem tạm. Ảnh và attachment lưu local có thể mất khi
service restart, sleep hoặc redeploy; chỉ dùng upload local cho demo. Service cũng
có thể sleep khi không có traffic và cần thời gian khởi động lại.

Sau deploy, kiểm tra:

1. `https://dormida-work.onrender.com/up` trả HTTP 200.
2. Trang đăng nhập có title kết thúc bằng `DORMIDA WORK`.
3. Đăng nhập được bằng user đã import và trang công việc có dữ liệu.
4. DevTools → Network → WS hiển thị kết nối WSS status `101`.
5. Tạo hoặc cập nhật công việc sinh thông báo chuông/toast tức thời.

Nếu deploy lỗi, đọc log theo thứ tự: Docker build, `artisan migrate --force`,
Supervisor, Nginx/PHP-FPM, queue, scheduler và Reverb. Migration hoặc `artisan
optimize` lỗi sẽ làm entrypoint dừng ngay để Render không phục vụ bản deploy nửa
hoàn chỉnh.
