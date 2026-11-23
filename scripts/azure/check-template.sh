#!/usr/bin/env bash
#
# Offline check of infra/containerapp.yaml: render it with dummy values (with
# and without the optional AI block) and validate the result. No Azure calls.
# Used by the Lint workflow; run locally with: scripts/azure/check-template.sh

# The placeholder values below are read indirectly by render_template.
# shellcheck disable=SC2034
# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

TEMPLATE="$PROJECT_DIR/infra/containerapp.yaml"
OUTPUT="$(mktemp)"
trap 'rm -f "$OUTPUT"' EXIT

# Dummy values for every placeholder.
AZ_LOCATION="northeurope"
IDENTITY_ID="/subscriptions/x/resourceGroups/rg/providers/Microsoft.ManagedIdentity/userAssignedIdentities/pull"
ENV_ID="/subscriptions/x/resourceGroups/rg/providers/Microsoft.App/managedEnvironments/env"
ACR_SERVER="example.azurecr.io"
IMAGE="example.azurecr.io/jalasupport:test"
APP_CPU="0.5"
APP_MEMORY="1Gi"
APP_URL="https://app.example.azurecontainerapps.io"
DB_HOST="db.mysql.database.azure.com"
DB_NAME="jalasupport"
MYSQL_ADMIN_USER="admin"
MYSQL_ADMIN_PASSWORD="dummy-password-0123456789"
for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
    printf -v "$key" 'dummy-%s-0123456789' "$key"
done
AI_PROVIDER="gemini"
# Newer Google keys contain dots; cover that format.
GEMINI_API_KEY="dummy.gemini-key-0123456789"
GEMINI_MODEL="gemini-flash-lite-latest"
AI_DAILY_LIMIT="200"

# Validate YAML with yq or Python (whichever is available).
validate() {
    if command -v yq > /dev/null; then
        yq -e '.properties.template.containers[0].image and .properties.template.scale.maxReplicas == 1' "$1" > /dev/null
    elif command -v python3 > /dev/null && python3 -c 'import yaml' 2> /dev/null; then
        python3 -c 'import sys, yaml
d = yaml.safe_load(open(sys.argv[1]))
assert d["properties"]["template"]["containers"][0]["image"]
assert d["properties"]["template"]["scale"]["maxReplicas"] == 1' "$1"
    else
        warn "Neither yq nor python3 with PyYAML found: skipping YAML parsing."
    fi
}

for variant in none ai; do
    INCLUDE_OPTIONAL=""
    [ "$variant" = "ai" ] && INCLUDE_OPTIONAL="ai"

    render_template "$TEMPLATE" \
        AZ_LOCATION IDENTITY_ID ENV_ID ACR_SERVER IMAGE APP_CPU APP_MEMORY APP_URL \
        DB_HOST DB_NAME MYSQL_ADMIN_USER MYSQL_ADMIN_PASSWORD \
        AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY \
        AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT \
        AI_PROVIDER GEMINI_API_KEY GEMINI_MODEL AI_DAILY_LIMIT \
        > "$OUTPUT"

    validate "$OUTPUT" || fail "Rendered template is invalid (optional blocks: ${variant})."

    if [ "$variant" = "none" ] && grep -q "gemini" "$OUTPUT"; then
        fail "The AI block must be omitted when no key is configured."
    fi
    if [ "$variant" = "ai" ] && ! grep -q "secretRef: gemini-api-key" "$OUTPUT"; then
        fail "The AI block is missing although it was requested."
    fi
    # The dry-run preview must never show secret values (all dummies contain "dummy").
    if mask_secrets "$OUTPUT" | grep -q "dummy"; then
        fail "mask_secrets left a secret value visible (optional blocks: ${variant})."
    fi
    note "Template OK (optional blocks: ${variant})."
done
