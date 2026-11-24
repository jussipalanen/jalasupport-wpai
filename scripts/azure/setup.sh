#!/usr/bin/env bash
#
# Create the JalaSupport AI free-tier deployment on Azure:
#   Azure Container Apps (scale to zero) + MySQL Flexible Server B1ms
#   + Azure Container Registry + Azure Files (uploads, Wordfence data).
#
# Usage:
#   DRY_RUN=1 scripts/azure/setup.sh   # preview the commands, change nothing
#   scripts/azure/setup.sh             # create / update the resources
#
# Safe to re-run: existing resources are kept and the app is updated.
# See docs/DEPLOYMENT.md.

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_tools

SUBSCRIPTION_ID="$(az account show --query id -o tsv)"
SUBSCRIPTION_NAME="$(az account show --query name -o tsv)"

info "JalaSupport AI: Azure free-tier setup$([ "$DRY_RUN" = "1" ] && echo ' (DRY RUN, nothing is changed)')"
note "Subscription:   $SUBSCRIPTION_NAME ($SUBSCRIPTION_ID)"
note "Region:         $AZ_LOCATION"
note "Resource group: $AZ_RESOURCE_GROUP"
note "Resources:      $MYSQL_SERVER, $STORAGE_ACCOUNT, $ACR_NAME, $ENV_NAME, $APP_NAME"
confirm "Create or update these resources?"

# -----------------------------------------------------------------------------
info "Registering resource providers"
for namespace in Microsoft.App Microsoft.DBforMySQL Microsoft.Storage Microsoft.ContainerRegistry Microsoft.ManagedIdentity Microsoft.OperationalInsights; do
    run az provider register --namespace "$namespace" --wait --only-show-errors
done

# -----------------------------------------------------------------------------
info "Resource group"
run az group create --name "$AZ_RESOURCE_GROUP" --location "$AZ_LOCATION" --only-show-errors --output none

# -----------------------------------------------------------------------------
if [ -n "${BUDGET_EMAIL:-}" ]; then
    info "Budget alert: ${BUDGET_AMOUNT} per month for the resource group (email at 50/80/100%)"
    budget_body=$(cat <<JSON
{
    "properties": {
        "category": "Cost",
        "amount": ${BUDGET_AMOUNT},
        "timeGrain": "Monthly",
        "timePeriod": { "startDate": "$(date -u +%Y-%m-01)T00:00:00Z" },
        "notifications": {
            "actual-50": { "enabled": true, "operator": "GreaterThan", "threshold": 50, "thresholdType": "Actual", "contactEmails": ["${BUDGET_EMAIL}"] },
            "actual-80": { "enabled": true, "operator": "GreaterThan", "threshold": 80, "thresholdType": "Actual", "contactEmails": ["${BUDGET_EMAIL}"] },
            "actual-100": { "enabled": true, "operator": "GreaterThan", "threshold": 100, "thresholdType": "Actual", "contactEmails": ["${BUDGET_EMAIL}"] },
            "forecast-100": { "enabled": true, "operator": "GreaterThan", "threshold": 100, "thresholdType": "Forecasted", "contactEmails": ["${BUDGET_EMAIL}"] }
        }
    }
}
JSON
)
    run az rest --method put \
        --url "https://management.azure.com/subscriptions/${SUBSCRIPTION_ID}/resourceGroups/${AZ_RESOURCE_GROUP}/providers/Microsoft.Consumption/budgets/${AZ_PREFIX}-budget?api-version=2023-05-01" \
        --body "$budget_body" --output none
else
    warn "BUDGET_EMAIL is empty: no budget alert is created. Strongly recommended."
fi

# -----------------------------------------------------------------------------
info "MySQL Flexible Server ${MYSQL_SERVER} (Burstable B1ms, free-tier settings)"
if az mysql flexible-server show -g "$AZ_RESOURCE_GROUP" -n "$MYSQL_SERVER" --output none 2> /dev/null; then
    note "Exists, keeping it."
else
    note "Creating (this takes 5-15 minutes)..."
    # Free-tier settings: B1ms, 32 GB, no storage auto-grow, no high
    # availability, no geo-redundant backup. Public access is limited to Azure
    # services (no paid private networking); TLS is required by default.
    run az mysql flexible-server create \
        --resource-group "$AZ_RESOURCE_GROUP" \
        --name "$MYSQL_SERVER" \
        --location "$AZ_LOCATION" \
        --admin-user "$MYSQL_ADMIN_USER" \
        --admin-password "$MYSQL_ADMIN_PASSWORD" \
        --tier Burstable \
        --sku-name Standard_B1ms \
        --storage-size 32 \
        --storage-auto-grow Disabled \
        --high-availability Disabled \
        --backup-retention 7 \
        --geo-redundant-backup Disabled \
        --version "$MYSQL_VERSION" \
        --public-access 0.0.0.0 \
        --yes --only-show-errors --output none
fi
run az mysql flexible-server db create -g "$AZ_RESOURCE_GROUP" -s "$MYSQL_SERVER" -d "$DB_NAME" --only-show-errors --output none

# -----------------------------------------------------------------------------
info "Storage account ${STORAGE_ACCOUNT} (file shares for uploads and Wordfence data)"
if ! az storage account show -g "$AZ_RESOURCE_GROUP" -n "$STORAGE_ACCOUNT" --output none 2> /dev/null; then
    run az storage account create \
        --resource-group "$AZ_RESOURCE_GROUP" \
        --name "$STORAGE_ACCOUNT" \
        --location "$AZ_LOCATION" \
        --sku Standard_LRS \
        --kind StorageV2 \
        --min-tls-version TLS1_2 \
        --https-only true \
        --allow-blob-public-access false \
        --only-show-errors --output none
fi
run az storage share-rm create -g "$AZ_RESOURCE_GROUP" --storage-account "$STORAGE_ACCOUNT" -n uploads --quota 5 --only-show-errors --output none
run az storage share-rm create -g "$AZ_RESOURCE_GROUP" --storage-account "$STORAGE_ACCOUNT" -n wflogs --quota 1 --only-show-errors --output none

# -----------------------------------------------------------------------------
info "Container registry ${ACR_NAME} (Standard, admin user disabled)"
if ! az acr show -g "$AZ_RESOURCE_GROUP" -n "$ACR_NAME" --output none 2> /dev/null; then
    run az acr create -g "$AZ_RESOURCE_GROUP" -n "$ACR_NAME" -l "$AZ_LOCATION" --sku Standard --admin-enabled false --only-show-errors --output none
fi

# -----------------------------------------------------------------------------
info "Managed identity ${IDENTITY_NAME} (lets the app pull images without passwords)"
if ! az identity show -g "$AZ_RESOURCE_GROUP" -n "$IDENTITY_NAME" --output none 2> /dev/null; then
    run az identity create -g "$AZ_RESOURCE_GROUP" -n "$IDENTITY_NAME" -l "$AZ_LOCATION" --only-show-errors --output none
fi

# IDENTITY_ID is also read by render_template (by name).
# shellcheck disable=SC2034
if [ "$DRY_RUN" = "1" ]; then
    IDENTITY_ID="<identity-resource-id>"
    IDENTITY_PRINCIPAL="<identity-principal-id>"
    ACR_ID="<acr-resource-id>"
else
    IDENTITY_ID="$(az identity show -g "$AZ_RESOURCE_GROUP" -n "$IDENTITY_NAME" --query id -o tsv)"
    IDENTITY_PRINCIPAL="$(az identity show -g "$AZ_RESOURCE_GROUP" -n "$IDENTITY_NAME" --query principalId -o tsv)"
    ACR_ID="$(az acr show -g "$AZ_RESOURCE_GROUP" -n "$ACR_NAME" --query id -o tsv)"
fi

if [ "$DRY_RUN" = "1" ] || [ -z "$(az role assignment list --assignee "$IDENTITY_PRINCIPAL" --scope "$ACR_ID" --role AcrPull --query '[0].id' -o tsv 2> /dev/null)" ]; then
    run az role assignment create --assignee-object-id "$IDENTITY_PRINCIPAL" --assignee-principal-type ServicePrincipal --role AcrPull --scope "$ACR_ID" --only-show-errors --output none
    note "Waiting 60 seconds for the role assignment to take effect..."
    [ "$DRY_RUN" = "1" ] || sleep 60
fi

# -----------------------------------------------------------------------------
TAG="$(image_tag)"
build_and_push "$TAG"
IMAGE="${ACR_NAME}.azurecr.io/${IMAGE_REPO}:${TAG}"

# -----------------------------------------------------------------------------
info "Container Apps environment ${ENV_NAME} (consumption only, logs off to avoid ingestion costs)"
if ! az containerapp env show -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" --output none 2> /dev/null; then
    run az containerapp env create -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" -l "$AZ_LOCATION" --logs-destination none --only-show-errors --output none
fi

# ENV_ID is read by render_template (by name).
# shellcheck disable=SC2034
if [ "$DRY_RUN" = "1" ]; then
    STORAGE_KEY="<storage-key>"
    ENV_ID="<environment-resource-id>"
    ENV_DOMAIN="<environment-domain>"
else
    STORAGE_KEY="$(az storage account keys list -g "$AZ_RESOURCE_GROUP" -n "$STORAGE_ACCOUNT" --query '[0].value' -o tsv)"
    ENV_ID="$(az containerapp env show -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" --query id -o tsv)"
    ENV_DOMAIN="$(az containerapp env show -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" --query properties.defaultDomain -o tsv)"
fi

for share in uploads wflogs; do
    run az containerapp env storage set -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" \
        --storage-name "$share" \
        --azure-file-account-name "$STORAGE_ACCOUNT" \
        --azure-file-account-key "$STORAGE_KEY" \
        --azure-file-share-name "$share" \
        --access-mode ReadWrite \
        --only-show-errors --output none
done

# -----------------------------------------------------------------------------
APP_FQDN="${APP_NAME}.${ENV_DOMAIN}"
APP_URL="https://${APP_FQDN}"
INCLUDE_OPTIONAL=""

APP_MIN_REPLICAS="${APP_MIN_REPLICAS:-0}"
[[ "$APP_MIN_REPLICAS" =~ ^[01]$ ]] || fail "APP_MIN_REPLICAS must be 0 (scale to zero) or 1 (always on)."

# Custom domain (./dev azure domain add): keep its binding and address when
# the app definition is applied again. Without its certificate (not added
# yet), the app keeps the Azure address.
DOMAIN_CERT_ID=""
if [ -n "${APP_DOMAIN:-}" ]; then
    valid_hostname "$APP_DOMAIN" || fail "APP_DOMAIN is not a valid domain name: ${APP_DOMAIN}"
    DOMAIN_CERT_ID="$(az containerapp env certificate list -g "$AZ_RESOURCE_GROUP" -n "$ENV_NAME" \
        --managed-certificates-only --query "[?properties.subjectName=='${APP_DOMAIN}'].id | [0]" -o tsv 2> /dev/null || true)"
    if [ -n "$DOMAIN_CERT_ID" ]; then
        APP_URL="https://${APP_DOMAIN}"
        INCLUDE_OPTIONAL="domain"
    else
        warn "APP_DOMAIN ${APP_DOMAIN} has no certificate yet. After setup, add it with: ./dev azure domain add ${APP_DOMAIN}"
    fi
fi

info "Container app ${APP_NAME} (min replicas ${APP_MIN_REPLICAS}, max 1, HTTPS)"

# The app definition contains secrets: write it to a private temp file and
# remove it afterwards.
APP_YAML="$(mktemp)"
chmod 600 "$APP_YAML"
trap 'rm -f "$APP_YAML"' EXIT

# Read by render_template (by name).
# shellcheck disable=SC2034
ACR_SERVER="${ACR_NAME}.azurecr.io"
# shellcheck disable=SC2034
DB_HOST="${MYSQL_SERVER}.mysql.database.azure.com"

if [ -n "${GEMINI_API_KEY:-}" ]; then
    # Classic keys (AIza...) and newer keys containing dots; no quotes or
    # whitespace, so the value is safe inside the YAML definition.
    [[ "$GEMINI_API_KEY" =~ ^[A-Za-z0-9._-]{20,}$ ]] || fail "GEMINI_API_KEY has an unexpected format (allowed: letters, digits, '.', '_' and '-')."
    AI_PROVIDER="${AI_PROVIDER:-gemini}"
    GEMINI_MODEL="${GEMINI_MODEL:-gemini-flash-lite-latest}"
    AI_DAILY_LIMIT="${AI_DAILY_LIMIT:-200}"
    INCLUDE_OPTIONAL="${INCLUDE_OPTIONAL} ai"
else
    note "GEMINI_API_KEY is empty: deploying without AI features."
fi

# The app definition lives in infra/containerapp.yaml; render it with the
# settings and secrets into the private temporary file.
render_template "$PROJECT_DIR/infra/containerapp.yaml" \
    AZ_LOCATION IDENTITY_ID ENV_ID ACR_SERVER IMAGE APP_CPU APP_MEMORY APP_URL APP_MIN_REPLICAS \
    APP_DOMAIN DOMAIN_CERT_ID DB_HOST DB_NAME MYSQL_ADMIN_USER MYSQL_ADMIN_PASSWORD \
    AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY \
    AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT \
    AI_PROVIDER GEMINI_API_KEY GEMINI_MODEL AI_DAILY_LIMIT \
    > "$APP_YAML"

if [ "$DRY_RUN" = "1" ]; then
    note "[dry-run] Container app definition (secrets masked):"
    mask_secrets "$APP_YAML" | sed 's/^/        /'
elif az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --output none 2> /dev/null; then
    az containerapp update -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --yaml "$APP_YAML" --only-show-errors --output none
else
    az containerapp create -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --yaml "$APP_YAML" --only-show-errors --output none
fi

# -----------------------------------------------------------------------------
info "Done$([ "$DRY_RUN" = "1" ] && echo ' (dry run)')"
note "Application: ${APP_URL}"
note "Image:       ${IMAGE}"
note ""
note "Next steps (first deployment only), see docs/DEPLOYMENT.md:"
note "  1. Open ${APP_URL}/wp/wp-admin/install.php and install WordPress."
note "  2. WP admin -> Plugins: activate Advanced Custom Fields, Wordfence and JalaSupport."
note "  3. Wordfence: register the free license."
note ""
note "Deploy code changes later with: scripts/azure/deploy.sh"
note "Remove everything with:         scripts/azure/teardown.sh"
