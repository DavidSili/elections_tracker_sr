#!/bin/sh
set -eu
mkdir -p /var/www/html/storage
printf 'Require all denied\n' > /var/www/html/storage/.htaccess
chown -R www-data:www-data /var/www/html/storage
if [ -z "${ADMIN_PASSWORD_HASH:-}" ]; then
  echo 'ADMIN_PASSWORD_HASH is required. Run the password setup from README.' >&2
  exit 1
fi
if [ ! -f /var/www/html/storage/demo/rik/latest.json ]; then
  su -s /bin/sh www-data -c 'php /var/www/html/bin/console.php demo 3'
fi
(
  while :; do
    su -s /bin/sh www-data -c 'php /var/www/html/bin/console.php refresh' || true
    sleep 60 & wait $!
  done
) &
collector_pid=$!
apache2-foreground &
apache_pid=$!
shutdown() {
  kill -TERM "$collector_pid" "$apache_pid" 2>/dev/null || true
  wait "$apache_pid" 2>/dev/null || true
}
trap shutdown TERM INT
while kill -0 "$apache_pid" 2>/dev/null && kill -0 "$collector_pid" 2>/dev/null; do
  sleep 2 & wait $! || true
done
shutdown
exit 1
