# 09 — DEVOPS

## 1. Môi trường

Tối thiểu:

- local
- staging
- production

Không dùng chung database, Redis prefix hoặc storage bucket giữa các môi trường.

## 2. Biến môi trường

- Không commit `.env`.
- Có `.env.example`.
- Secret lưu trong secret manager hoặc CI/CD variables.
- Kiểm tra cấu hình bắt buộc khi deploy.

## 3. Deploy

Quy trình đề xuất:

1. Maintenance mode khi cần.
2. Pull hoặc release artifact.
3. Composer install production.
4. NPM build.
5. Migration.
6. Cache config/route/view.
7. Restart queue.
8. Restart Reverb.
9. Health check.
10. Tắt maintenance mode.

## 4. Queue

- Chạy bằng Supervisor hoặc systemd.
- Có queue riêng cho notification, export, import khi tải lớn.
- Thiết lập timeout, tries, backoff.
- Theo dõi failed jobs.
- Job phải idempotent khi có thể.

## 5. Scheduler

Cron:

```text
* * * * * php /path/artisan schedule:run
```

Scheduler task phải có:

- `withoutOverlapping` khi cần.
- `onOneServer` nếu chạy nhiều instance.
- Log lỗi.
- Không chạy tác vụ nặng trực tiếp nếu có thể dispatch job.

## 6. Reverb

- Chạy process riêng.
- Reverse proxy hỗ trợ WebSocket.
- Có health monitoring.
- Không mở channel private khi chưa authorize.

## 7. Backup

Backup:

- Database.
- Object storage metadata cần thiết.
- File cấu hình không chứa secret hoặc backup secret an toàn.
- Kiểm tra restore định kỳ.

## 8. Monitoring

Theo dõi:

- HTTP 5xx.
- Response time.
- Slow query.
- Queue delay.
- Failed jobs.
- Redis memory.
- Disk.
- Reverb connection.
- Login failure bất thường.

## 9. Rollback

Mỗi release phải biết:

- Có rollback code được không.
- Migration có backward compatible không.
- Có feature flag không.
- Dữ liệu có cần backfill hoặc reverse script không.

Không giả định `migrate:rollback` luôn an toàn ở production.
