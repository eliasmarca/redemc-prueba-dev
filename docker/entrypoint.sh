#!/bin/sh
# Railway enruta al puerto de la variable PORT. Apache, de fábrica, escucha en el 80.
set -eu

PORT="${PORT:-80}"

case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT no es un número: $PORT" >&2
        exit 1
        ;;
esac

CONF_PUERTOS=/etc/apache2/ports.conf
CONF_SITIO=/etc/apache2/sites-available/000-default.conf

if ! grep -q '^Listen 80$' "$CONF_PUERTOS"; then
    echo "No encontré 'Listen 80' en ports.conf" >&2
    exit 1
fi

sed -i "s/^Listen 80$/Listen ${PORT}/" "$CONF_PUERTOS"
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" "$CONF_SITIO"

if ! grep -q "^Listen ${PORT}$" "$CONF_PUERTOS"; then
    echo "Apache no quedó escuchando en ${PORT}" >&2
    exit 1
fi

echo "Apache escucha en el puerto ${PORT}"
exec apache2-foreground
