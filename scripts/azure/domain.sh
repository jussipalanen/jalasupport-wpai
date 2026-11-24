#!/usr/bin/env bash
#
# Custom domain for the Azure container app, with a free managed certificate
# (issued and renewed by Azure).
#
# Usage:
#   scripts/azure/domain.sh                    Show the custom domain and WordPress address
#   scripts/azure/domain.sh add <hostname>     Add the domain and move WordPress to it
#   scripts/azure/domain.sh update <hostname>  Move to another domain and remove the old one
#   scripts/azure/domain.sh clean              Remove the custom domain, back to the Azure address
#
# The DNS records (CNAME and TXT) are added by hand at your DNS provider, such
# as GoDaddy; "add" prints them and waits for your confirmation. Moving
# WordPress replaces the old address in the database with WP-CLI
# search-replace, after showing a dry run.
#
# To keep the domain when setup.sh runs again, set APP_DOMAIN in
# scripts/azure/config.env. DRY_RUN=1 previews the changes. See
# docs/DEPLOYMENT.md#custom-domain.

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_azure

app_query() {
    az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query "$1" -o tsv 2> /dev/null || true
}

FQDN="$(app_query properties.configuration.ingress.fqdn)"
[ -n "$FQDN" ] || fail "Container app ${APP_NAME} not found. Run ./dev azure setup first."
AZURE_URL="https://${FQDN}"

# Custom domains on the app, one per line.
app_domains() {
    az containerapp hostname list -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query '[].name' -o tsv 2> /dev/null || true
}

binding_type() {
    az containerapp hostname list -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
        --query "[?name=='$1'].bindingType | [0]" -o tsv 2> /dev/null || true
}

wp_home() {
    app_query "properties.template.containers[0].env[?name=='WP_HOME'].value | [0]"
}

require_hostname() {
    [ -n "${1:-}" ] || fail "Usage: ./dev azure domain ${ACTION} <hostname>, e.g. support.example.com"
    valid_hostname "$1" || fail "Not a valid custom domain: $1 (lowercase name such as support.example.com)."
}

show_dns_records() {
    local host="$1" verification_id zone label
    verification_id="$(app_query properties.customDomainVerificationId)"
    [ -n "$verification_id" ] || fail "Could not read the domain verification ID of ${APP_NAME}."

    # Usual case: the DNS zone is the last two labels (example.com).
    zone="$(printf '%s' "$host" | awk -F. '{ print $(NF - 1) "." $NF }')"
    label="${host%."$zone"}"

    info "DNS records for ${host}"
    note "Add these at your DNS provider (GoDaddy: Domain -> DNS -> Add record):"
    note ""
    note "  Type   Name                          Value"
    printf '        %-6s %-29s %s\n' "CNAME" "${label}" "${FQDN}"
    printf '        %-6s %-29s %s\n' "TXT" "asuid.${label}" "${verification_id}"
    note ""
    note "Names are relative to ${zone}; GoDaddy adds the domain itself."
    note "Check them (each answer should show the value):"
    note "  curl -s 'https://dns.google/resolve?name=${host}&type=CNAME'"
    note "  curl -s 'https://dns.google/resolve?name=asuid.${host}&type=TXT'"
}

# Wait until the managed certificate is bound and the domain answers over HTTPS.
wait_for_certificate() {
    local host="$1"
    [ "$DRY_RUN" = "1" ] && return 0

    note "Waiting for the certificate (usually 5-20 minutes) ..."
    for _ in $(seq 1 60); do
        if [ "$(binding_type "$host")" = "SniEnabled" ] \
            && curl -fsS --max-time 60 -o /dev/null "https://${host}/healthz.txt" 2> /dev/null; then
            note "https://${host} is up with its certificate."
            return 0
        fi
        sleep 30
    done
    fail "The certificate for ${host} is not ready after 30 minutes. Check the DNS records, then run: ./dev azure domain add ${host}"
}

# Point WordPress to <url> and replace the old address in the database.
set_site_url() {
    local url="$1" old
    old="$(wp_home)"

    if [ "$old" = "$url" ]; then
        note "WordPress already uses ${url}."
        return 0
    fi

    info "Moving WordPress to ${url}"
    run az containerapp update -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
        --set-env-vars "WP_HOME=${url}" "WP_SITEURL=${url}/wp" \
        --only-show-errors --output none

    [ -n "$old" ] || return 0

    info "Replacing ${old} with ${url} in the database (dry run first)"
    "$AZURE_DIR/exec.sh" wp search-replace "$old" "$url" --skip-columns=guid --report-changed-only --dry-run
    confirm "Apply these replacements?"
    run "$AZURE_DIR/exec.sh" wp search-replace "$old" "$url" --skip-columns=guid --report-changed-only
    note "Sessions are tied to the address: sign in again at ${url}/wp/wp-login.php."
}

# Remove a custom domain and its managed certificate from Azure.
remove_domain() {
    local host="$1" certificate
    info "Removing ${host}"
    run az containerapp hostname delete -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
        --hostname "$host" --yes --only-show-errors --output none

    for certificate in $(az containerapp env certificate list -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" \
        --managed-certificates-only --query "[?properties.subjectName=='${host}'].name" -o tsv 2> /dev/null || true); do
        run az containerapp env certificate delete -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" \
            --certificate "$certificate" --yes --only-show-errors --output none
    done
    note "Delete the CNAME and TXT records of ${host} at your DNS provider."
}

add_domain() {
    local host="$1"
    show_dns_records "$host"
    confirm "Are both DNS records in place?"

    info "Adding ${host} with a free managed certificate"
    if ! app_domains | grep -qxF "$host"; then
        run az containerapp hostname add -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
            --hostname "$host" --only-show-errors --output none
    fi
    if [ "$(binding_type "$host")" != "SniEnabled" ]; then
        run az containerapp hostname bind -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
            --hostname "$host" --environment "$ENV_NAME" --validation-method CNAME \
            --only-show-errors --output none
    fi
    wait_for_certificate "$host"

    set_site_url "https://${host}"
}

remind_config() {
    local wanted="$1"
    [ "${APP_DOMAIN:-}" = "$wanted" ] && return 0
    if [ -n "$wanted" ]; then
        warn "Set APP_DOMAIN=\"${wanted}\" in scripts/azure/config.env, or ./dev azure setup moves WordPress back to the Azure address."
    else
        warn "Remove APP_DOMAIN from scripts/azure/config.env, or ./dev azure setup expects the removed domain."
    fi
}

ACTION="${1:-show}"
[ "$#" -gt 0 ] && shift
HOST="${1:-}"
HOST="${HOST,,}"

case "$ACTION" in
    show)
        info "Custom domain of ${APP_NAME}"
        domains="$(app_domains)"
        if [ -n "$domains" ]; then
            for host in $domains; do
                note "${host} ($(binding_type "$host"))"
            done
        else
            note "None. Add one with: ./dev azure domain add <hostname>"
        fi
        note "Azure address: ${AZURE_URL}"
        note "WordPress:     $(wp_home)"
        note "APP_DOMAIN:    ${APP_DOMAIN:-(not set)}"
        ;;
    add)
        require_hostname "$HOST"
        add_domain "$HOST"
        remind_config "$HOST"
        ;;
    update)
        require_hostname "$HOST"
        old_domains="$(app_domains | grep -vxF "$HOST" || true)"
        add_domain "$HOST"
        for old in $old_domains; do
            remove_domain "$old"
        done
        remind_config "$HOST"
        ;;
    clean)
        domains="$(app_domains)"
        if [ -z "$domains" ] && [ "$(wp_home)" = "$AZURE_URL" ]; then
            note "No custom domain: WordPress uses ${AZURE_URL}."
        else
            confirm "Remove the custom domain ($(echo "$domains" | xargs)) and move WordPress back to ${AZURE_URL}?"
            # Move WordPress first, so the site works on the Azure address
            # before the domain disappears.
            set_site_url "$AZURE_URL"
            for host in $domains; do
                remove_domain "$host"
            done
        fi
        remind_config ""
        ;;
    *)
        fail "Usage: ./dev azure domain [add <hostname> | update <hostname> | clean]"
        ;;
esac
