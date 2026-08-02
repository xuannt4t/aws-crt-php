# Production Deployment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Đưa DORMIDA WORK lên một VPS Ubuntu chạy production, deploy tự động từ `main` qua GitHub Actions, rollback bằng cách đổi symlink.

**Architecture:** Cài trực tiếp lên VPS (nginx + PHP-FPM 8.3 + MySQL 8 + Redis 7), không container. Mỗi lần deploy tạo một thư mục release mới trong `/var/www/dormida/releases/`, symlink `current` trỏ vào release đang phục vụ; `.env` và `storage/` nằm trong `shared/` dùng chung. Supervisor giữ queue worker và Reverb, cron giữ scheduler.

**Tech Stack:** Laravel 11, PHP 8.3, MySQL 8, Redis 7 (predis), Vue 3 + Inertia + Vite, Cloudflare R2 (S3-compatible), Laravel Reverb, nginx, Supervisor, GitHub Actions.

Spec: `docs/superpowers/specs/2026-08-02-production-deployment-design.md`

## Global Constraints

- PHP `^8.2` theo `composer.json`; server dùng **PHP 8.3**. CI đã pin `php-version: '8.3'` và `node-version: '22'` — mọi workflow mới dùng đúng hai giá trị này.
- Code style: `./vendor/bin/pint --test` phải xanh. JS/TS: `npm run lint` và `npm run format:check` phải xanh.
- Test runner là **Pest 3** (`./vendor/bin/pest`), không phải PHPUnit trần.
- Tất cả chuỗi hiển thị cho người dùng viết bằng tiếng Việt (`APP_LOCALE=vi`).
- Mặc định của mọi cấu hình mới phải giữ nguyên hành vi hiện tại ở dev/test: disk đính kèm mặc định `local`, broadcast mặc định `log`.
- Không commit giá trị bí mật thật vào repo — chỉ commit tệp `*.example`.
- Migration phải tương thích ngược: cột thêm mới để `nullable` hoặc có `default`; không xoá/đổi tên cột trong cùng release với code ngừng dùng nó.

---

### Task 1: Cho phép cấu hình disk lưu tệp đính kèm

Hiện `StoreTaskAttachmentAction` hardcode `private const DISK = 'local'`. Task này biến nó thành giá trị đọc từ config, mặc định vẫn là `local`.

**Files:**
- Modify: `config/dormida.php`
- Modify: `app/Actions/Task/StoreTaskAttachmentAction.php`
- Test: `tests/Feature/Task/TaskAttachmentControllerTest.php`

**Interfaces:**
- Consumes: không có (task đầu tiên).
- Produces: khoá config `dormida.attachments.disk` (string, mặc định `'local'`, đọc env `DORMIDA_ATTACHMENT_DISK`). Task 2 đặt giá trị này thành `r2` ở production.

- [ ] **Step 1: Viết test thất bại**

Thêm vào cuối `tests/Feature/Task/TaskAttachmentControllerTest.php`:

```php
test('uploads land on the disk named in config', function () {
    Storage::fake('archive');
    config()->set('dormida.attachments.disk', 'archive');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    $this->actingAs($uploader)
        ->post(route('tasks.attachments.store', $task), [
            'files' => [UploadedFile::fake()->create('ke-hoach.pdf', 40, 'application/pdf')],
        ])
        ->assertRedirect(route('tasks.show', $task));

    $attachment = TaskAttachment::query()->sole();

    expect($attachment->disk)->toBe('archive');
    Storage::disk('archive')->assertExists($attachment->path);
});

test('files stored on an older disk stay downloadable after the default changes', function () {
    Storage::fake('local');
    Storage::fake('archive');

    $uploader = uploaderUser();
    $task = Task::factory()->create();

    // Tệp cũ đã nằm trên disk `local` từ trước khi chuyển sang disk mới.
    Storage::disk('local')->put('task-attachments/legacy.pdf', 'noi dung cu');
    $attachment = TaskAttachment::factory()->create([
        'task_id' => $task->id,
        'uploader_id' => $uploader->id,
        'disk' => 'local',
        'path' => 'task-attachments/legacy.pdf',
        'original_name' => 'legacy.pdf',
    ]);

    config()->set('dormida.attachments.disk', 'archive');

    $this->actingAs($uploader)
        ->get(route('tasks.attachments.download', [$task, $attachment]))
        ->assertOk()
        ->assertDownload('legacy.pdf');
});
```

- [ ] **Step 2: Chạy test để xác nhận thất bại**

Run: `./vendor/bin/pest tests/Feature/Task/TaskAttachmentControllerTest.php --filter="disk named in config"`
Expected: FAIL — tệp vẫn ghi vào `local`, `$attachment->disk` là `'local'` chứ không phải `'archive'`.

Nếu test thứ hai đã PASS ngay: đúng như mong đợi, vì `download()` đọc `$attachment->disk`. Giữ nó lại làm test hồi quy bảo vệ tính chất này.

Nếu factory `TaskAttachment::factory()` chưa nhận các khoá trên, đọc `database/factories/TaskAttachmentFactory.php` và truyền đúng tên cột đang có; không sửa factory.

- [ ] **Step 3: Thêm khoá config**

Trong `config/dormida.php`, thêm vào mảng trả về:

```php
    'attachments' => [
        'disk' => env('DORMIDA_ATTACHMENT_DISK', 'local'),
    ],
```

- [ ] **Step 4: Đọc config trong action**

Trong `app/Actions/Task/StoreTaskAttachmentAction.php`, xoá dòng `private const DISK = 'local';` và thêm phương thức:

```php
    private function disk(): string
    {
        return (string) config('dormida.attachments.disk', 'local');
    }
```

Thay `self::DISK` ở ba chỗ:

```php
$path = $file->store("task-attachments/{$task->id}", $disk);
```

trong đó `$disk = $this->disk();` được gán một lần ở đầu `execute()`, trước vòng lặp — để mọi tệp trong cùng một request chắc chắn nằm trên cùng một disk. Dùng chính `$disk` đó cho `'disk' => $disk` trong `$rows[]`.

Trong `discard()`, đổi chữ ký thành `private function discard(string $disk, array $paths): void` và gọi `Storage::disk($disk)->delete($path)`. Ở khối `catch`, gọi `$this->discard($disk, $storedPaths);` — biến `$disk` phải được gán **trước** khối `try` để vẫn nhìn thấy được trong `catch`.

- [ ] **Step 5: Chạy toàn bộ test đính kèm**

Run: `./vendor/bin/pest tests/Feature/Task`
Expected: PASS toàn bộ, gồm cả các test cũ khẳng định `disk` là `'local'` (mặc định không đổi).

- [ ] **Step 6: Kiểm tra style**

Run: `./vendor/bin/pint --test`
Expected: PASS. Nếu fail, chạy `./vendor/bin/pint` rồi chạy lại.

- [ ] **Step 7: Commit**

```bash
git add config/dormida.php app/Actions/Task/StoreTaskAttachmentAction.php tests/Feature/Task/TaskAttachmentControllerTest.php
git commit -m "feat(attachment): cho phép cấu hình disk lưu tệp đính kèm"
```

---

### Task 2: Thêm disk R2 (Cloudflare) cho tệp đính kèm

**Files:**
- Modify: `composer.json`, `composer.lock`
- Modify: `config/filesystems.php`
- Modify: `.env.example`

**Interfaces:**
- Consumes: `dormida.attachments.disk` từ Task 1.
- Produces: disk tên `r2` trong `config/filesystems.php`. Task 4 tham chiếu các biến env `R2_*` trong `.env.production.example`.

- [ ] **Step 1: Cài gói S3**

Run: `composer require league/flysystem-aws-s3-v3:"^3.0" --no-interaction`
Expected: cài thành công, `composer.lock` thay đổi.

- [ ] **Step 2: Thêm disk `r2`**

Trong `config/filesystems.php`, thêm vào mảng `disks` ngay sau disk `s3`:

```php
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => false,
            'throw' => true,
            'report' => false,
        ],
```

`'throw' => true` là cố ý và khác disk `local`: lỗi mạng khi ghi lên R2 phải ném ngoại lệ để `StoreTaskAttachmentAction` bắt được và dọn tệp mồ côi, thay vì âm thầm trả `false`.

- [ ] **Step 3: Thêm biến vào `.env.example`**

Thêm dưới khối `AWS_*` đang có:

```
DORMIDA_ATTACHMENT_DISK=local

R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
```

- [ ] **Step 4: Xác nhận disk đăng ký được**

Run: `php artisan tinker --execute="dump(array_keys(config('filesystems.disks')));"`
Expected: output có `"r2"`.

- [ ] **Step 5: Chạy test đảm bảo không hồi quy**

Run: `./vendor/bin/pest`
Expected: PASS toàn bộ (mặc định vẫn là `local`, không có test nào chạm R2).

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock config/filesystems.php .env.example
git commit -m "feat(storage): thêm disk R2 cho tệp đính kèm"
```

---

### Task 3: Thêm Laravel Reverb và cấu hình broadcasting

Repo chưa có `config/broadcasting.php` và chưa event nào broadcast. Task này chỉ dựng sẵn hạ tầng; frontend Echo sẽ làm cùng event đầu tiên ở sprint sau.

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `config/broadcasting.php`, `config/reverb.php` (do `artisan install:broadcasting` sinh ra)
- Modify: `.env.example`
- Modify: `bootstrap/app.php` hoặc `routes/channels.php` tuỳ những gì lệnh cài sinh ra

**Interfaces:**
- Consumes: không.
- Produces: cấu hình `broadcasting.connections.reverb`, biến env `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`. Task 4 dùng chúng trong `.env.production.example` và cấu hình supervisor/nginx.

- [ ] **Step 1: Cài Reverb**

Run: `php artisan install:broadcasting --reverb --no-interaction`
Expected: cài `laravel/reverb`, tạo `config/broadcasting.php`, `config/reverb.php`, `routes/channels.php`, và thêm biến `REVERB_*` vào `.env`.

Nếu lệnh hỏi cài npm packages (`laravel-echo`, `pusher-js`), **từ chối** — frontend chưa dùng đến, cài bây giờ chỉ làm phình bundle. Nếu lệnh đã tự cài, gỡ bằng `npm remove laravel-echo pusher-js` và hoàn nguyên `resources/js/echo.js` nếu nó được tạo.

- [ ] **Step 2: Giữ mặc định là `log`**

Trong `config/broadcasting.php`, đảm bảo dòng default là:

```php
    'default' => env('BROADCAST_CONNECTION', 'log'),
```

Nếu lệnh cài đã đổi thành `'reverb'`, sửa lại về `'log'` — dev và test không được yêu cầu một server Reverb đang chạy.

- [ ] **Step 3: Đồng bộ `.env.example`**

Đảm bảo `.env.example` có `BROADCAST_CONNECTION=log` (đã có sẵn) và bổ sung khối:

```
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
```

- [ ] **Step 4: Chạy test**

Run: `./vendor/bin/pest`
Expected: PASS toàn bộ. Nếu có test fail vì thiếu biến `REVERB_*`, nghĩa là default chưa phải `log` — quay lại Step 2.

- [ ] **Step 5: Kiểm tra style và commit**

```bash
./vendor/bin/pint --test
git add -A
git commit -m "feat(realtime): thêm Laravel Reverb và cấu hình broadcasting"
```

---

### Task 4: Tệp cấu hình server và script backup

Các tệp `*.example` để copy lên server khi setup lần đầu. Không tệp nào chứa bí mật thật.

**Files:**
- Create: `deploy/nginx.conf.example`
- Create: `deploy/supervisor.conf.example`
- Create: `deploy/backup-db.sh`
- Create: `.env.production.example`

**Interfaces:**
- Consumes: biến `R2_*` (Task 2), `REVERB_*` (Task 3), `DORMIDA_ATTACHMENT_DISK` (Task 1).
- Produces: đường dẫn `/var/www/dormida/current`, tên chương trình supervisor `dormida-queue` và `dormida-reverb` — Task 5 restart đúng hai tên này, Task 6 tài liệu hoá chúng.

- [ ] **Step 1: Viết `deploy/nginx.conf.example`**

```nginx
server {
    listen 80;
    server_name dormida.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name dormida.example.com;

    root /var/www/dormida/current/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/dormida.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/dormida.example.com/privkey.pem;

    client_max_body_size 32M;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/build/ {
        expires 1y;
        access_log off;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # WebSocket cho Reverb
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_read_timeout 3600s;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        # $realpath_root thay vì $document_root: root là symlink, opcache cần
        # đường dẫn thật để không phục vụ bytecode của release cũ sau khi đổi symlink.
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

- [ ] **Step 2: Viết `deploy/supervisor.conf.example`**

```ini
[program:dormida-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dormida/current/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/dormida/current
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/dormida-queue.log
stopwaitsecs=3600

[program:dormida-reverb]
command=php /var/www/dormida/current/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/dormida/current
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/dormida-reverb.log
stopwaitsecs=10
```

`--max-time=3600` cho worker tự thoát mỗi giờ để supervisor khởi động lại — tránh worker giữ code cũ trong bộ nhớ sau deploy.

- [ ] **Step 3: Viết `deploy/backup-db.sh`**

```bash
#!/usr/bin/env bash
# Backup DB hằng đêm rồi đẩy lên bucket R2 dành riêng cho backup.
# Chạy qua cron: 0 2 * * * /var/www/dormida/current/deploy/backup-db.sh >> /var/log/dormida-backup.log 2>&1
set -euo pipefail

ENV_FILE=/var/www/dormida/shared/.env
BACKUP_DIR=/var/backups/dormida
RETENTION_DAYS=14

# shellcheck disable=SC2046
export $(grep -E '^(DB_|BACKUP_R2_)' "$ENV_FILE" | xargs -d '\n')

mkdir -p "$BACKUP_DIR"
STAMP=$(date +%Y%m%d-%H%M%S)
DUMP="$BACKUP_DIR/dormida-$STAMP.sql.gz"

mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" \
    --user="$DB_USERNAME" --password="$DB_PASSWORD" \
    --single-transaction --quick --routines --events \
    "$DB_DATABASE" | gzip > "$DUMP"

# Chỉ xoá bản cục bộ sau khi upload thành công; set -e đảm bảo lỗi upload dừng script.
aws s3 cp "$DUMP" "s3://$BACKUP_R2_BUCKET/db/" \
    --endpoint-url "$BACKUP_R2_ENDPOINT"

find "$BACKUP_DIR" -name 'dormida-*.sql.gz' -mtime +$RETENTION_DAYS -delete

echo "[$(date -Is)] backup ok: $(basename "$DUMP")"
```

- [ ] **Step 4: Viết `.env.production.example`**

```
APP_NAME="DORMIDA WORK"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_TIMEZONE=Asia/Ho_Chi_Minh
APP_URL=https://dormida.example.com

APP_LOCALE=vi
APP_FALLBACK_LOCALE=vi

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dormida_work
DB_USERNAME=dormida
DB_PASSWORD=

SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true

CACHE_STORE=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

FILESYSTEM_DISK=local
DORMIDA_ATTACHMENT_DISK=r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=dormida-attachments
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com

BACKUP_R2_BUCKET=dormida-backups
BACKUP_R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="no-reply@dormida.example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

- [ ] **Step 5: Kiểm tra cú pháp script**

Run: `bash -n deploy/backup-db.sh`
Expected: không output, exit 0.

- [ ] **Step 6: Commit**

```bash
git add deploy .env.production.example
git commit -m "chore(deploy): thêm mẫu cấu hình nginx, supervisor và script backup"
```

---

### Task 5: Workflow deploy tự động

**Files:**
- Create: `.github/workflows/deploy.yml`

**Interfaces:**
- Consumes: tên chương trình supervisor `dormida-queue`, `dormida-reverb` và layout thư mục từ Task 4.
- Produces: không có gì cho task sau; Task 6 tài liệu hoá secrets mà workflow này cần.

- [ ] **Step 1: Viết workflow**

```yaml
name: Deploy

on:
  workflow_run:
    workflows: ['CI']
    types: [completed]
    branches: [main]

concurrency:
  group: deploy-production
  cancel-in-progress: false

jobs:
  deploy:
    # Chỉ deploy khi CI đã xanh — workflow_run vẫn kích hoạt cả khi CI đỏ.
    if: github.event.workflow_run.conclusion == 'success'
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4
        with:
          ref: ${{ github.event.workflow_run.head_sha }}

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_mysql, redis

      - name: Install PHP dependencies
        run: composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'npm'

      - name: Install JS dependencies
        run: npm ci

      - name: Build frontend
        run: npm run build

      - name: Configure SSH
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.SSH_PRIVATE_KEY }}" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          ssh-keyscan -p ${{ secrets.SSH_PORT }} -H ${{ secrets.SSH_HOST }} >> ~/.ssh/known_hosts

      - name: Compute release name
        id: release
        run: echo "name=$(date +%Y%m%d-%H%M%S)" >> "$GITHUB_OUTPUT"

      - name: Upload release
        env:
          RELEASE: ${{ steps.release.outputs.name }}
        run: |
          RELEASE_PATH="/var/www/dormida/releases/$RELEASE"
          ssh -p ${{ secrets.SSH_PORT }} ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }} "mkdir -p $RELEASE_PATH"
          rsync -az --delete \
            -e "ssh -p ${{ secrets.SSH_PORT }}" \
            --exclude '.git' \
            --exclude '.github' \
            --exclude 'node_modules' \
            --exclude 'tests' \
            --exclude 'storage' \
            --exclude '.env' \
            ./ ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:$RELEASE_PATH/

      - name: Activate release
        env:
          RELEASE: ${{ steps.release.outputs.name }}
        run: |
          ssh -p ${{ secrets.SSH_PORT }} ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }} bash -euo pipefail <<EOF
          BASE=/var/www/dormida
          RELEASE_PATH=\$BASE/releases/$RELEASE

          ln -sfn \$BASE/shared/.env \$RELEASE_PATH/.env
          rm -rf \$RELEASE_PATH/storage
          ln -sfn \$BASE/shared/storage \$RELEASE_PATH/storage

          cd \$RELEASE_PATH
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache

          # ln -T + mv -T: đổi symlink nguyên tử, không có khoảnh khắc nào current biến mất.
          ln -sfnT \$RELEASE_PATH \$BASE/current.tmp
          mv -Tf \$BASE/current.tmp \$BASE/current

          sudo systemctl reload php8.3-fpm
          php \$BASE/current/artisan queue:restart
          sudo supervisorctl restart dormida-reverb

          # Giữ 5 release gần nhất.
          cd \$BASE/releases
          ls -1dt */ | tail -n +6 | xargs -r rm -rf
          EOF

      - name: Verify health
        run: |
          for i in 1 2 3 4 5; do
            code=\$(curl -s -o /dev/null -w '%{http_code}' https://${{ secrets.APP_HOST }}/up) || true
            if [ "\$code" = "200" ]; then echo "healthy"; exit 0; fi
            sleep 5
          done
          echo "health check failed (last status: \$code)"
          exit 1
```

- [ ] **Step 2: Kiểm tra cú pháp YAML**

Run: `node -e "const fs=require('fs');const s=fs.readFileSync('.github/workflows/deploy.yml','utf8');if(!s.includes('workflow_run'))process.exit(1);console.log('ok')"`
Expected: `ok`.

Nếu có `yamllint` hoặc `actionlint` sẵn trong máy thì chạy thêm; nếu không có thì bỏ qua, không cài mới.

- [ ] **Step 3: Commit**

```bash
git add .github/workflows/deploy.yml
git commit -m "ci(deploy): thêm workflow deploy tự động lên VPS"
```

---

### Task 6: Runbook vận hành

**Files:**
- Create: `docs/deployment.md`
- Modify: `README.md` (thêm một dòng trỏ tới runbook)

**Interfaces:**
- Consumes: mọi thứ từ Task 1–5.
- Produces: không.

- [ ] **Step 1: Viết `docs/deployment.md`**

Runbook phải bao gồm, mỗi phần có lệnh cụ thể chạy được:

1. **Yêu cầu server** — Ubuntu 24.04, 2 vCPU / 4GB / 80GB; các gói cần cài: `nginx php8.3-fpm php8.3-{mysql,redis,mbstring,xml,curl,zip,bcmath,gd} mysql-server redis-server supervisor certbot python3-certbot-nginx unzip awscli`.
2. **Setup lần đầu** — theo thứ tự: tạo user `deploy`; tạo cây thư mục `/var/www/dormida/{releases,shared/storage}`; copy `.env.production.example` thành `shared/.env` và điền giá trị thật; `php artisan key:generate` cho production; tạo DB và user MySQL; đặt `requirepass` cho Redis; copy `deploy/nginx.conf.example` và `deploy/supervisor.conf.example` vào chỗ của chúng; chạy `certbot`; cấp `sudo` không mật khẩu cho user `deploy` **chỉ** với `systemctl reload php8.3-fpm` và `supervisorctl restart dormida-*` (ghi rõ nội dung tệp sudoers).
3. **Cron** — hai dòng crontab: `schedule:run` mỗi phút dưới `www-data`, và `deploy/backup-db.sh` lúc 2 giờ sáng.
4. **Secrets GitHub** — bảng `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_PRIVATE_KEY`, `APP_HOST` kèm mô tả cách lấy giá trị.
5. **Deploy thường ngày** — merge vào `main` là xong; cách xem log workflow.
6. **Rollback** — lệnh cụ thể:
   ```bash
   cd /var/www/dormida
   PREV=$(ls -1dt releases/*/ | sed -n 2p)
   ln -sfnT "$PWD/$PREV" current.tmp && mv -Tf current.tmp current
   sudo systemctl reload php8.3-fpm && php current/artisan queue:restart
   ```
   Kèm cảnh báo: rollback **không** hoàn tác migration; nếu release lỗi đã chạy migration phá vỡ tương thích thì phải khôi phục DB.
7. **Quy tắc migration tương thích ngược** — chép nguyên mục 3 của spec.
8. **Khôi phục backup** — tải tệp `.sql.gz` từ R2, `gunzip`, restore vào schema tạm `dormida_work_restore`, kiểm chứng bằng đếm số bản ghi vài bảng chính, rồi mới đổi tên.
9. **Xử lý sự cố** — nơi xem log: `shared/storage/logs/laravel.log`, `/var/log/nginx/error.log`, `/var/log/supervisor/dormida-*.log`; lệnh kiểm tra nhanh: `supervisorctl status`, `systemctl status php8.3-fpm`, `redis-cli -a <pass> ping`.

- [ ] **Step 2: Thêm dòng trỏ trong README**

Trong `README.md`, thêm mục ngay trước phần cuối:

```markdown
## Triển khai

Quy trình dựng server, deploy và rollback: [docs/deployment.md](docs/deployment.md).
```

- [ ] **Step 3: Đọc lại runbook như người chưa từng đụng server này**

Kiểm tra: mỗi lệnh đều copy-paste chạy được, không có `<điền vào đây>` nào ngoài giá trị bí mật thật, thứ tự các bước setup không có bước nào phụ thuộc vào bước sau nó.

- [ ] **Step 4: Commit**

```bash
git add docs/deployment.md README.md
git commit -m "docs(deploy): runbook dựng server, deploy và rollback"
```

---

## Việc thủ công ngoài phạm vi code (sau khi 6 task xong)

Những việc này cần quyền truy cập server và tài khoản Cloudflare, không tự động hoá được từ repo:

1. Mua VPS, dựng theo mục 2 của runbook.
2. Tạo hai bucket R2 và một API token phạm vi hẹp.
3. Nạp 5 secrets vào GitHub repository settings.
4. Chạy deploy đầu tiên, xác minh `/up` trả 200 và HTTPS hợp lệ.
5. Kiểm chứng backup: chạy tay `deploy/backup-db.sh`, xác nhận tệp xuất hiện trên R2, restore thử vào schema tạm.
