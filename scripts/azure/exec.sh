#!/usr/bin/env bash
#
# Run commands inside the Azure container app (az containerapp exec).
#
# Usage:
#   scripts/azure/exec.sh shell            # interactive shell as www-data
#   scripts/azure/exec.sh shell --root     # interactive root shell (use sparingly)
#   scripts/azure/exec.sh wp <args...>     # WP-CLI as www-data
#
# Security:
#   - Uses your existing `az login` session. Azure issues a short-lived exec
#     token for this container app only; nothing is stored or printed here.
#   - Requires Azure RBAC rights on the container app (e.g. Contributor).
#   - Goes through Azure's management proxy; the app exposes no extra port.
#   - WP-CLI arguments travel as one base64 token and become exact arguments
#     in the container (no remote shell parsing). Arguments are visible to
#     Azure's exec endpoint: don't pass passwords; use WP-CLI's --prompt.

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_azure

mode="${1:-}"
[ -n "$mode" ] && shift
case "$mode" in
    shell | wp) ;;
    *) fail "Usage: $(basename "$0") shell [--root] | wp <args...>" ;;
esac

FQDN="$(az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query properties.configuration.ingress.fqdn -o tsv 2> /dev/null || true)"
[ -n "$FQDN" ] || fail "Container app ${APP_NAME} not found. Run ./dev azure setup first."

# The app scales to zero: wake it with a request to the public health
# endpoint (no credentials), then wait for a running replica.
note "Waking https://${FQDN} ..."
for _ in $(seq 1 18); do
    curl -fsS --max-time 10 -o /dev/null "https://${FQDN}/healthz.txt" 2> /dev/null && break
    sleep 5
done
for _ in $(seq 1 12); do
    [ "$(az containerapp replica list -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query 'length(@)' -o tsv 2> /dev/null || echo 0)" -gt 0 ] && break
    sleep 5
done

if [ "$mode" = "shell" ]; then
    command="jalasupport-shell"
    if [ "${1:-}" = "--root" ]; then
        warn "Opening a ROOT shell in the production container."
        command="/bin/bash"
    fi
    note "Idle replicas may be stopped after a few minutes (scale to zero), which ends the session."
else
    command="jalasupport-wp"
    if [ "$#" -gt 0 ]; then
        command="jalasupport-wp $(printf '%s\0' "$@" | base64 | tr -d '\n')"
    fi
fi

exec az containerapp exec -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --command "$command"
