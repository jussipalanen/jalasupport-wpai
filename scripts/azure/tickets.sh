#!/usr/bin/env bash
#
# Copy tickets between this machine and the Azure app (JSON export files of
# `wp jalasupport tickets export`).
#
# Usage:
#   scripts/azure/tickets.sh export [--file=<path>] [--ids=<ids>]
#       Download the Azure tickets (default: backups/azure-tickets-<time>.json)
#   scripts/azure/tickets.sh import <file.json> --author=<login> [--dry-run]
#       Import an export file into Azure; --dry-run validates and lists only
#   DRY_RUN=1 scripts/azure/tickets.sh ...   # show the steps, change nothing
#
# How it works:
#   - The file travels through the "uploads" Azure Files share, in the
#     jalasupport-import folder under a random name. The app refuses web
#     access to that folder (docker/apache/000-default.conf); this is checked
#     before any file is written, and the file is deleted afterwards, also
#     when a step fails.
#   - The storage account key comes from your az login session and is passed
#     through the environment, never on the command line.
#   - WP-CLI runs in the container through exec.sh. Imports validate every
#     ticket, give it a new ID and the chosen author, and skip tickets that
#     were imported before.
#
# Export files contain customer names and email addresses: keep them in
# backups/ (git-ignored) and delete them when no longer needed.

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

usage() {
    fail "Usage: $(basename "$0") export [--file=<path>] [--ids=<ids>] | import <file.json> --author=<login> [--dry-run]"
}

load_config
load_state

MODE="${1:-}"
[ -n "$MODE" ] && shift

FILE=""
IDS=""
AUTHOR=""
WP_DRY_RUN=()

case "$MODE" in
    export)
        FILE="$PROJECT_DIR/backups/azure-tickets-$(date +%Y%m%d-%H%M%S).json"
        for arg in "$@"; do
            case "$arg" in
                --file=*) FILE="${arg#--file=}" ;;
                --ids=*) IDS="${arg#--ids=}" ;;
                *) fail "Unknown option: ${arg}" ;;
            esac
        done
        [[ -z "$IDS" || "$IDS" =~ ^[0-9]+(,[0-9]+)*$ ]] || fail "--ids must be comma-separated ticket IDs, e.g. --ids=12,15."
        [ ! -e "$FILE" ] || fail "${FILE} already exists: choose another --file."
        mkdir -p "$(dirname "$FILE")"
        ;;
    import)
        FILE="${1:-}"
        [ -n "$FILE" ] || usage
        shift
        for arg in "$@"; do
            case "$arg" in
                --author=*) AUTHOR="${arg#--author=}" ;;
                --dry-run) WP_DRY_RUN=(--dry-run) ;;
                *) fail "Unknown option: ${arg}" ;;
            esac
        done
        [ -f "$FILE" ] && [ -r "$FILE" ] || fail "File not found or not readable: ${FILE}"
        [ -n "$AUTHOR" ] || fail "Choose the author of the imported tickets: --author=<login> (a user on the Azure site)."
        [[ "$AUTHOR" =~ ^[A-Za-z0-9@._-]{1,100}$ ]] || fail "--author may contain letters, digits and @ . _ - only."
        grep -qE '"format": *"jalasupport-tickets"' "$FILE" \
            || fail "${FILE} is not a ticket export. Create one with: ./dev tickets export"
        [ "$(wc -c < "$FILE")" -le $((20 * 1024 * 1024)) ] || fail "File is larger than 20 MB."
        ;;
    *)
        usage
        ;;
esac

require_azure

SHARE="uploads"
REMOTE_DIR="jalasupport-import"
REMOTE_FILE="${REMOTE_DIR}/$(random_alnum 32).json"
CONTAINER_FILE="/var/www/html/web/app/uploads/${REMOTE_FILE}"

FQDN="$(az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query properties.configuration.ingress.fqdn -o tsv 2> /dev/null || true)"
[ -n "$FQDN" ] || fail "Container app ${APP_NAME} not found. Run ./dev azure setup first."

dry_label=""
[ "$DRY_RUN" = "1" ] && dry_label=" (DRY RUN)"
if [ "$MODE" = "export" ]; then
    info "Export tickets from https://${FQDN}${dry_label}"
    note "Tickets: ${IDS:-all published}"
    note "To:      ${FILE} (contains customer data)"
else
    info "Import $(grep -cE '"source_id": *[0-9]+' "$FILE" || true) tickets into https://${FQDN}$([ "${#WP_DRY_RUN[@]}" -gt 0 ] && echo ' (WP-CLI dry run: nothing is saved)')${dry_label}"
    note "File:    ${FILE} (contains customer data)"
    note "Author:  ${AUTHOR}"
fi
note "Via:     Azure Files share '${SHARE}' (not web-accessible), deleted afterwards"
confirm "Continue?"

if [ "$DRY_RUN" = "1" ]; then
    AZURE_STORAGE_KEY="<storage-key>"
else
    AZURE_STORAGE_KEY="$(az storage account keys list -g "$AZ_RESOURCE_GROUP" -n "$STORAGE_ACCOUNT" --query '[0].value' -o tsv)"
fi
# az storage reads the account and key from its environment: the key never
# appears in the process list, and only the storage commands receive it.
az_storage() {
    AZURE_STORAGE_ACCOUNT="$STORAGE_ACCOUNT" AZURE_STORAGE_KEY="$AZURE_STORAGE_KEY" az storage "$@"
}

info "Checking that the transfer folder is not served over HTTP"
run az_storage directory create --share-name "$SHARE" --name "$REMOTE_DIR" --only-show-errors --output none
if [ "$DRY_RUN" != "1" ]; then
    # The app may be scaled to zero: the first request can take a while.
    probe=""
    for _ in $(seq 1 12); do
        probe="$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "https://${FQDN}/app/uploads/${REMOTE_DIR}/$(random_alnum 32).json" || true)"
        [[ "$probe" =~ ^(403|404)$ ]] && break
        sleep 5
    done
    [ "$probe" = "403" ] || fail "The app does not block web access to uploads/${REMOTE_DIR} (HTTP ${probe:-none}). Deploy the current code first: ./dev azure deploy"
    note "Blocked (HTTP 403)."
fi

cleanup() {
    info "Deleting the transfer file from the share"
    if [ "$DRY_RUN" = "1" ] || [ "$(az_storage file exists --share-name "$SHARE" --path "$REMOTE_FILE" --query exists -o tsv 2> /dev/null)" = "true" ]; then
        run az_storage file delete --share-name "$SHARE" --path "$REMOTE_FILE" --only-show-errors --output none \
            || warn "Could not delete ${REMOTE_FILE} from the '${SHARE}' share: delete it in the Azure portal."
    fi
}
trap cleanup EXIT

if [ "$MODE" = "export" ]; then
    info "Exporting with WP-CLI in the container"
    wp_args=(jalasupport tickets export "--file=${CONTAINER_FILE}")
    [ -n "$IDS" ] && wp_args+=("--ids=${IDS}")
    # Not exec: the cleanup trap must run afterwards.
    run "$AZURE_DIR/exec.sh" wp "${wp_args[@]}"

    info "Downloading the export"
    if [ "$DRY_RUN" != "1" ]; then
        [ "$(az_storage file exists --share-name "$SHARE" --path "$REMOTE_FILE" --query exists -o tsv)" = "true" ] \
            || fail "The export was not created (see the WP-CLI output above)."
    fi
    (
        umask 077
        run az_storage file download --share-name "$SHARE" --path "$REMOTE_FILE" --dest "$FILE" --only-show-errors --output none
    )
    if [ "$DRY_RUN" != "1" ]; then
        grep -qE '"format": *"jalasupport-tickets"' "$FILE" || fail "The downloaded file is not a ticket export: ${FILE}"
        note "Saved $(grep -cE '"source_id": *[0-9]+' "$FILE" || true) tickets to ${FILE}"
        note "Import locally: ./dev tickets import ${FILE#"$PROJECT_DIR"/} --author=admin --dry-run"
    fi
else
    info "Uploading the export"
    run az_storage file upload --share-name "$SHARE" --source "$FILE" --path "$REMOTE_FILE" --only-show-errors --output none

    info "Importing with WP-CLI in the container"
    # Not exec: the cleanup trap must run afterwards.
    run "$AZURE_DIR/exec.sh" wp jalasupport tickets import "$CONTAINER_FILE" "--author=${AUTHOR}" "${WP_DRY_RUN[@]}"
fi
