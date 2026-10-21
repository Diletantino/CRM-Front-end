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

# Existing installations keep their config in a persistent volume. Apply the
# Studio theme as structured data before EspoCRM rebuilds its client metadata.
if [ "$(bin/command config:get isInstalled 2>/dev/null || true)" = "true" ]; then
    bin/command config:set applicationName onlineCRM
    bin/command config:set theme StudioDark
    bin/command config:set themeParams '{"navbar":"side"}' --type=json
    bin/command config:set userThemesDisabled true --type=bool
    bin/command config:set timeFormat 'HH:mm'
    bin/command config:set tabList '["SmShift","SmModels","SmOperators","Team","SmAnalytics","SmRegistrationLink","User","Role","SmCredits"]' --type=json
    bin/command config:set quickCreateList '["SmShift","SmRegistrationLink","User","Team"]' --type=json
    bin/command rebuild >/dev/null
    bin/command studio-sync-staff
    bin/command studio-migrate-registration-links
    bin/command update-app-timestamp >/dev/null
fi

exec docker-entrypoint.sh "$@"
