#!/bin/sh
# Railway enruta al puerto de la variable PORT. Apache, de fábrica, escucha en el 80.
# Corre en cada arranque, también cuando el contenedor se reinicia: el disco
# se conserva, así que Listen ya no dice 80 y un MPM de más puede seguir habilitado.
set -eu

PORT="${PORT:-80}"

case "$PORT" in
    ''|*[!0-9]*)
        echo "PORT no es un número: $PORT" >&2
        exit 1
        ;;
esac

# mod_php no es thread-safe. event o worker junto con prefork abortan el
# arranque con AH00534. a2enmod no los deshabilita si ya están los dos:
# hay que bajarlos antes. En cada arranque, no solo en el build.
a2dismod -f mpm_event mpm_worker
rm -f \
    /etc/apache2/mods-enabled/mpm_event.load \
    /etc/apache2/mods-enabled/mpm_event.conf \
    /etc/apache2/mods-enabled/mpm_worker.load \
    /etc/apache2/mods-enabled/mpm_worker.conf
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ] || [ ! -e /etc/apache2/mods-enabled/mpm_prefork.conf ]; then
    a2enmod mpm_prefork
fi

if [ -e /etc/apache2/mods-enabled/mpm_event.load ] || [ -e /etc/apache2/mods-enabled/mpm_worker.load ]; then
    echo "Sigue habilitado más de un MPM" >&2
    exit 1
fi
if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    echo "mpm_prefork no quedó habilitado" >&2
    exit 1
fi

CONF_PUERTOS=/etc/apache2/ports.conf
CONF_SITIO=/etc/apache2/sites-available/000-default.conf

# El primer arranque reemplaza "Listen 80". Un reinicio ya tiene "Listen $PORT"
# (o el puerto de un arranque anterior) y hay que dejar el PORT de ahora.
if ! grep -Eq '^Listen [0-9]+$' "$CONF_PUERTOS"; then
    echo "No encontré una directiva Listen en ports.conf" >&2
    exit 1
fi
sed -i -E "s/^Listen [0-9]+$/Listen ${PORT}/" "$CONF_PUERTOS"

if ! grep -Eq '<VirtualHost \*:[0-9]+>' "$CONF_SITIO"; then
    echo "No encontré VirtualHost en 000-default.conf" >&2
    exit 1
fi
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" "$CONF_SITIO"

if ! grep -q "^Listen ${PORT}$" "$CONF_PUERTOS"; then
    echo "Apache no quedó escuchando en ${PORT}" >&2
    exit 1
fi
if ! grep -q "<VirtualHost \*:${PORT}>" "$CONF_SITIO"; then
    echo "El VirtualHost no quedó en el puerto ${PORT}" >&2
    exit 1
fi

echo "Apache escucha en el puerto ${PORT}"
exec apache2-foreground
