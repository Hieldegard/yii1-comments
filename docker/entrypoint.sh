#!/bin/sh
set -eu
: "${ADMIN_PASSWORD:?Set ADMIN_PASSWORD in .env}"
: "${WS_SECRET:?Set WS_SECRET in .env}"
if [ "${#ADMIN_PASSWORD}" -lt 12 ] || [ "${#WS_SECRET}" -lt 32 ]; then
  echo 'Use an admin password of at least 12 characters and WS_SECRET of at least 32.' >&2
  exit 1
fi
ADMIN_PASSWORD_HASH="$(php -r 'echo password_hash(getenv("ADMIN_PASSWORD"), PASSWORD_DEFAULT);')"
export ADMIN_PASSWORD_HASH
unset ADMIN_PASSWORD
chown www-data:www-data /data
exec docker-php-entrypoint "$@"
