#!/usr/bin/env bash
# Backup DB hằng đêm rồi đẩy lên bucket R2 dành riêng cho backup.
# Chạy qua cron: 0 2 * * * /var/www/dormida/current/deploy/backup-db.sh >> /var/log/dormida-backup.log 2>&1
set -euo pipefail

ENV_FILE=/var/www/dormida/shared/.env
BACKUP_DIR=/var/backups/dormida
RETENTION_DAYS=14

# Nạp biến bằng `source` thay vì `xargs`: `source` bóc dấu nháy đúng cách,
# nên mật khẩu chứa khoảng trắng hay ký tự đặc biệt vẫn nạp nguyên vẹn.
set -a
# shellcheck source=/dev/null
source <(grep -E '^(DB_|R2_|BACKUP_R2_)[A-Z_]+=' "$ENV_FILE")
set +a

# aws CLI chỉ đọc AWS_*; dùng chung token R2 phạm vi hẹp cho hai bucket.
export AWS_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID"
export AWS_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY"
export AWS_DEFAULT_REGION=auto

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
