#!/usr/bin/env bash
# Backup do banco e dos documentos enviados (storage/app).
# Agende via cron do host, por exemplo às 02:00:
#   0 2 * * * /opt/bceducar/scripts/backup-vps.sh >> /var/log/bceducar-backup.log 2>&1
#
# Variáveis opcionais:
#   BACKUP_DIR       destino local (padrão: /var/backups/bceducar)
#   BACKUP_KEEP_DAYS dias de retenção local (padrão: 14)
#   RCLONE_REMOTE    destino externo do rclone (ex.: s3:bucket/bceducar). Recomendado:
#                    um backup que fica só na VPS não protege contra perda da VPS.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

BACKUP_DIR="${BACKUP_DIR:-/var/backups/bceducar}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
TARGET="$BACKUP_DIR/$STAMP"

mkdir -p "$TARGET"
chmod 700 "$BACKUP_DIR"

DB_USER="$(grep -E '^DB_USERNAME=' .env | cut -d= -f2- | tr -d "\"'")"
DB_NAME="$(grep -E '^DB_DATABASE=' .env | cut -d= -f2- | tr -d "\"'")"

docker compose -f docker-compose.prod.yml exec -T postgres \
    pg_dump -U "${DB_USER:-ieducar}" -d "${DB_NAME:-ieducar}" -Fc > "$TARGET/database.dump"

# Documentos dos responsáveis (disco registration-documents) e demais arquivos da aplicação.
tar -C "$ROOT/storage" -czf "$TARGET/storage-app.tar.gz" app

# Os dados são pessoais (LGPD): o diretório fica restrito ao root.
chmod -R go-rwx "$TARGET"

if [[ -n "${RCLONE_REMOTE:-}" ]]; then
    rclone copy "$TARGET" "$RCLONE_REMOTE/$STAMP"
fi

find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -mtime +"$KEEP_DAYS" -exec rm -rf {} +

echo "Backup concluído em $TARGET"
