#!/usr/bin/env bash
#
# Remove the whole Azure deployment by deleting its resource group.
# Optionally back up the database and uploads first.
#
# Usage:
#   scripts/azure/teardown.sh            # delete everything (asks first)
#   BACKUP=1 scripts/azure/teardown.sh   # back up to ./backups, then delete
#   DRY_RUN=1 scripts/azure/teardown.sh  # preview

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_tools

BACKUP="${BACKUP:-0}"

if ! az group show -n "$AZ_RESOURCE_GROUP" --output none 2> /dev/null; then
    note "Resource group ${AZ_RESOURCE_GROUP} does not exist. Nothing to delete."
    exit 0
fi

info "Teardown of resource group ${AZ_RESOURCE_GROUP}$([ "$DRY_RUN" = "1" ] && echo ' (DRY RUN)')"
note "This permanently deletes the app, database, storage, registry and budget."
az resource list -g "$AZ_RESOURCE_GROUP" --query '[].{name:name, type:type}' -o table

if [ "$DRY_RUN" != "1" ]; then
    read -r -p "Type the resource group name to confirm: " answer
    [ "$answer" = "$AZ_RESOURCE_GROUP" ] || fail "Aborted."
fi

if [ "$BACKUP" = "1" ]; then
    BACKUP_DIR="$PROJECT_DIR/backups/$(date +%Y%m%d-%H%M%S)"
    info "Backing up to ${BACKUP_DIR}"
    run mkdir -p "$BACKUP_DIR"

    # The database only accepts Azure services: open it briefly for this machine.
    MY_IP="$(curl -fsS https://api.ipify.org)"
    note "Temporarily allowing ${MY_IP} to reach the database"
    run az mysql flexible-server firewall-rule create -g "$AZ_RESOURCE_GROUP" -n "$MYSQL_SERVER" \
        --rule-name teardown-backup --start-ip-address "$MY_IP" --end-ip-address "$MY_IP" --only-show-errors --output none

    note "Exporting the database"
    if [ "$DRY_RUN" = "1" ]; then
        note "[dry-run] docker run mysql:8.4 mysqldump ... > ${BACKUP_DIR}/database.sql"
    else
        docker run --rm -e MYSQL_PWD="$MYSQL_ADMIN_PASSWORD" mysql:8.4 \
            mysqldump --ssl-mode=REQUIRED --single-transaction --no-tablespaces \
            -h "${MYSQL_SERVER}.mysql.database.azure.com" -u "$MYSQL_ADMIN_USER" "$DB_NAME" \
            > "$BACKUP_DIR/database.sql"
    fi

    run az mysql flexible-server firewall-rule delete -g "$AZ_RESOURCE_GROUP" -n "$MYSQL_SERVER" \
        --rule-name teardown-backup --yes --only-show-errors

    note "Downloading uploads"
    STORAGE_KEY="$(az storage account keys list -g "$AZ_RESOURCE_GROUP" -n "$STORAGE_ACCOUNT" --query '[0].value' -o tsv)"
    run mkdir -p "$BACKUP_DIR/uploads"
    run az storage file download-batch --account-name "$STORAGE_ACCOUNT" --account-key "$STORAGE_KEY" \
        --source uploads --destination "$BACKUP_DIR/uploads" --only-show-errors --output none
fi

info "Deleting resource group ${AZ_RESOURCE_GROUP} (takes a few minutes)"
run az group delete -n "$AZ_RESOURCE_GROUP" --yes

# The GitHub deploy app registration (scripts/azure/github-oidc.sh) lives in
# Entra ID, outside the resource group.
APP_ID="$(az ad app list --display-name "${AZ_PREFIX}-github-deploy" --query '[0].appId' -o tsv)"
if [ -n "$APP_ID" ]; then
    info "Deleting the GitHub deploy app registration ${AZ_PREFIX}-github-deploy"
    run az ad app delete --id "$APP_ID"
    note "The GitHub repository secrets/variables (AZURE_*) can be removed in the repository settings."
fi

note "Done. Generated secrets remain in scripts/azure/.state.env; delete it if you do not plan to redeploy."
