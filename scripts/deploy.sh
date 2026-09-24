#!/bin/sh
#
# Lo que hay que hacer después de que Plesk copia los archivos al sitio.
#
# Vive acá y no en la caja de «additional deployment actions» del panel por dos
# razones: cambia con el código —una migración nueva, un paso nuevo— y así viaja
# con él; y se puede leer sin entrar a Plesk.
#
# En el panel va una sola línea:
#
#     sh $HOME/biblio.urano.dev/scripts/deploy.sh
#
set -e

# Plesk corre esto en un shell que no carga el perfil del usuario, así que no
# trae phpenv ni nodenv. Se agregan los shims —no las rutas de una versión—:
# un shim es el que decide qué versión usar, y sigue siendo el correcto después
# de actualizar PHP o Node. `composer` además lo necesita en el PATH, porque su
# primera línea es `#!/usr/bin/env php`.
export PATH="$HOME/.phpenv/shims:$HOME/.nodenv/shims:$PATH"

cd "$(dirname "$0")/.."

echo "→ Dependencias de PHP"
composer install --no-dev --optimize-autoloader

echo "→ Dependencias de JavaScript"
npm ci

echo "→ Hoja de estilos y scripts"
npm run build

echo "→ Migraciones"
php artisan migrate --force

# El caché de rutas se limpia, no se reconstruye: con las rutas cacheadas
# desaparece la que sirve las Portadas, porque el framework la registra al
# arrancar y no está escrita en routes/web.php (BIB-32).
echo "→ Cachés"
php artisan route:clear
php artisan view:clear
php artisan config:clear

echo "Listo."
