#!/bin/sh
set -eu

server_target=/var/www/html/custom/Espo/Modules/StudioManagement
client_target=/var/www/html/client/custom/modules/studio-management

mkdir -p "$server_target" "$client_target"

# The application code lives in the image while EspoCRM's writable custom
# directories stay in persistent volumes. Refresh only this module on startup.
find "$server_target" -mindepth 1 -delete
find "$client_target" -mindepth 1 -delete
cp -a /opt/studio-management/server/. "$server_target/"
cp -a /opt/studio-management/client/. "$client_target/"
chown -R www-data:www-data "$server_target" "$client_target"

# The official entrypoint must install/migrate first. Its apache2* dispatch
# recognizes this bootstrap command, which applies Studio settings afterwards
# and only then starts the original web-server command.
case "${1:-}" in
    apache2*|php-fpm)
        exec docker-entrypoint.sh apache2-studio-bootstrap "$@"
        ;;
    *)
        exec docker-entrypoint.sh "$@"
        ;;
esac
