#!/bin/sh
# Development image only.
#
# The local MySQL container uses a self-signed certificate, which the MariaDB
# client tools reject by default. WP-CLI runs them with --no-defaults, so
# option files are ignored. This wrapper keeps TLS but skips certificate
# verification. Option-file flags must remain the first argument.
real="/usr/bin/$(basename "$0")"

case "${1:-}" in
    --no-defaults | --defaults-file=* | --defaults-extra-file=* | --defaults-group-suffix=* | --print-defaults)
        first="$1"
        shift
        exec "$real" "$first" --ssl-verify-server-cert=0 "$@"
        ;;
esac

exec "$real" --ssl-verify-server-cert=0 "$@"
