#!/bin/sh
#
# Run WP-CLI as www-data from the project root. Used by `./dev azure wp`.
#
# The arguments arrive as ONE base64 token (NUL-separated list), so they reach
# WP-CLI unchanged through `az containerapp exec --command`: no shell quoting,
# no word splitting, no injection. Without a token, plain `wp` runs.
set -eu

args_file="$(mktemp)"
trap 'rm -f "$args_file"' EXIT
printf '%s' "${1:-}" | base64 -d > "$args_file"

cd /var/www/html

if [ "$(id -u)" -eq 0 ]; then
    # Drop to www-data with setpriv: unlike su, it stops parsing options at
    # "--", so WP-CLI flags such as --role=... reach wp unchanged.
    xargs -0 -a "$args_file" setpriv --reuid=www-data --regid=www-data --init-groups -- \
        env HOME=/var/www wp
else
    xargs -0 -a "$args_file" wp
fi
