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

# Un worker que ya corría sigue con el código con el que arrancó, aunque se
# haya publicado otro. Así corrió trabajos de un despliegue contra la base del
# siguiente, y terminaron en Trabajos fallidos por columnas que todavía no
# existían (`is_canonical`, `cover_failed_at`, sep 2026). Esto le pide terminar
# el trabajo que lleva y salir; quien lo arranca lo levanta con el código nuevo.
# Va después de las migraciones, para que arranque contra la base que espera.
echo "→ Cola"
php artisan queue:restart

# Qué se publicó. El servidor no tiene `.git`, así que la versión sale del
# encabezado más reciente de CHANGELOG.md, que viaja con el despliegue (BIB-39).
echo "→ Versión publicada"
php artisan app:version

echo "Listo."
