#!/usr/bin/env bash
# Sauvegarde chiffrée de la base PostgreSQL. Usage : BACKUP_PASSPHRASE=... infra/scripts/backup.sh [dossier]
# Variables : PGDATABASE (défaut chezvous), PGHOST/PGUSER/PGPASSWORD comme pg_dump. Rien n'est envoyé à l'extérieur.
set -euo pipefail
: "${BACKUP_PASSPHRASE:?BACKUP_PASSPHRASE est obligatoire (la sauvegarde contient des données personnelles)}"
db="${PGDATABASE:-chezvous}"
dir="${1:-./backups}"
keep="${BACKUP_KEEP:-14}"
mkdir -p "$dir"; chmod 700 "$dir"
file="$dir/${db}-$(date -u +%Y%m%dT%H%M%SZ).dump.enc"
pg_dump --format=custom --no-owner "$db" \
  | openssl enc -aes-256-cbc -pbkdf2 -iter 200000 -salt -pass env:BACKUP_PASSPHRASE -out "$file"
chmod 600 "$file"
# Rotation : on garde les $keep plus récentes.
ls -1t "$dir"/"${db}"-*.dump.enc | tail -n +"$((keep + 1))" | xargs -I{} rm -f {}
echo "Sauvegarde écrite : $file"
