#!/usr/bin/env bash
set -euo pipefail

PORT="${PORT:-80}"

# Render injects PORT; Apache must listen on that port.
if grep -q '^Listen ' /etc/apache2/ports.conf; then
  sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
else
  echo "Listen ${PORT}" >> /etc/apache2/ports.conf
fi

for f in /etc/apache2/sites-available/000-default.conf /etc/apache2/sites-enabled/000-default.conf; do
  if [[ -f "$f" ]]; then
    sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" "$f" || true
  fi
done

mkdir -p var/cache var/log \
  public/uploads/certificates public/uploads/faces public/uploads/face \
  public/uploads/profile public/uploads/tickets

# Clear then warm prod cache when runtime env is available (non-fatal during first boot).
php bin/console cache:clear --env=prod --no-debug --no-warmup 2>/dev/null || true
php bin/console cache:warmup --env=prod --no-debug 2>/dev/null || true

chown -R www-data:www-data var public/uploads || true
chmod -R ug+rwX var public/uploads || true

exec apache2-foreground
