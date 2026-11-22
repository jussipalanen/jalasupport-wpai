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
    # su passes the extra arguments to the shell as $0 ("wp") and "$@".
    # shellcheck disable=SC2016  # $@ must expand in the www-data shell.
    xargs -0 -a "$args_file" su -s /bin/sh www-data -c 'cd /var/www/html && exec wp "$@"' wp
else
    xargs -0 -a "$args_file" wp
fi
