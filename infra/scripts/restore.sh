#!/usr/bin/env bash
# Restauration dans une base CIBLE vide (jamais par-dessus la base source). Usage :
#   BACKUP_PASSPHRASE=... infra/scripts/restore.sh fichier.dump.enc base_cible
set -euo pipefail
: "${BACKUP_PASSPHRASE:?BACKUP_PASSPHRASE est obligatoire}"
file="${1:?fichier de sauvegarde}"; target="${2:?base cible}"
if [ "$(psql -d "$target" -Atc "select count(*) from information_schema.tables where table_schema='public'")" != "0" ]; then
  echo "La base $target n'est pas vide : restauration refusée." >&2; exit 1
fi
psql -d "$target" -qc "CREATE EXTENSION IF NOT EXISTS postgis; CREATE EXTENSION IF NOT EXISTS unaccent;"
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -pass env:BACKUP_PASSPHRASE -in "$file" \
  | pg_restore --no-owner --exit-on-error -d "$target"
echo "Restauration terminée dans $target"
