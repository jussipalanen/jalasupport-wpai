#!/usr/bin/env bash
#
# One-time setup for the GitHub Actions "Deploy" workflow:
#   - an Entra ID app registration that trusts only this repository's main
#     branch (OIDC federated credential, no passwords or keys),
#   - AcrPush on the container registry and Contributor on the resource group,
#   - the IDs and names as GitHub repository secrets and variables.
#
# Run scripts/azure/setup.sh first.
#
# Usage:
#   DRY_RUN=1 scripts/azure/github-oidc.sh
#   scripts/azure/github-oidc.sh

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_tools

command -v gh > /dev/null || fail "GitHub CLI (gh) is not installed."
gh auth status > /dev/null 2>&1 || fail "Not logged in to GitHub. Run: gh auth login"

REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"
SUBSCRIPTION_ID="$(az account show --query id -o tsv)"
TENANT_ID="$(az account show --query tenantId -o tsv)"
APP_REGISTRATION="${AZ_PREFIX}-github-deploy"

az group show -n "$AZ_RESOURCE_GROUP" --output none 2> /dev/null \
    || fail "Resource group ${AZ_RESOURCE_GROUP} not found. Run scripts/azure/setup.sh first."

info "GitHub deploy access for ${REPO} (main branch only)$([ "$DRY_RUN" = "1" ] && echo ' (DRY RUN)')"
note "App registration: ${APP_REGISTRATION}"
note "Permissions:      AcrPush on ${ACR_NAME}, Contributor on ${AZ_RESOURCE_GROUP}"
confirm "Create or update this access?"

# -----------------------------------------------------------------------------
info "App registration and service principal"
APP_ID="$(az ad app list --display-name "$APP_REGISTRATION" --query '[0].appId' -o tsv)"
if [ -z "$APP_ID" ]; then
    if [ "$DRY_RUN" = "1" ]; then
        run az ad app create --display-name "$APP_REGISTRATION"
        APP_ID="<app-id>"
    else
        APP_ID="$(az ad app create --display-name "$APP_REGISTRATION" --query appId -o tsv)"
    fi
fi

if [ "$DRY_RUN" = "1" ] || ! az ad sp show --id "$APP_ID" --output none 2> /dev/null; then
    run az ad sp create --id "$APP_ID" --only-show-errors --output none
fi

# -----------------------------------------------------------------------------
info "Federated credential: repo:${REPO}:ref:refs/heads/main"
credential=$(cat <<JSON
{
    "name": "github-main",
    "issuer": "https://token.actions.githubusercontent.com",
    "subject": "repo:${REPO}:ref:refs/heads/main",
    "audiences": ["api://AzureADTokenExchange"],
    "description": "GitHub Actions deploy workflow, main branch only"
}
JSON
)
if [ "$DRY_RUN" = "1" ] || [ -z "$(az ad app federated-credential list --id "$APP_ID" --query "[?name=='github-main'].name" -o tsv)" ]; then
    run az ad app federated-credential create --id "$APP_ID" --parameters "$credential" --only-show-errors --output none
fi

# -----------------------------------------------------------------------------
info "Role assignments"
if [ "$DRY_RUN" = "1" ]; then
    SP_ID="<service-principal-id>"
    ACR_ID="<acr-resource-id>"
    RG_ID="<resource-group-id>"
else
    SP_ID="$(az ad sp show --id "$APP_ID" --query id -o tsv)"
    ACR_ID="$(az acr show -g "$AZ_RESOURCE_GROUP" -n "$ACR_NAME" --query id -o tsv)"
    RG_ID="$(az group show -n "$AZ_RESOURCE_GROUP" --query id -o tsv)"
fi

assign_role() {
    local role="$1" scope="$2"
    if [ "$DRY_RUN" = "1" ] || [ -z "$(az role assignment list --assignee "$SP_ID" --scope "$scope" --role "$role" --query '[0].id' -o tsv)" ]; then
        run az role assignment create --assignee-object-id "$SP_ID" --assignee-principal-type ServicePrincipal \
            --role "$role" --scope "$scope" --only-show-errors --output none
    fi
}
assign_role AcrPush "$ACR_ID"
assign_role Contributor "$RG_ID"

# -----------------------------------------------------------------------------
info "GitHub repository secrets and variables (${REPO})"
run gh secret set AZURE_CLIENT_ID --repo "$REPO" --body "$APP_ID"
run gh secret set AZURE_TENANT_ID --repo "$REPO" --body "$TENANT_ID"
run gh secret set AZURE_SUBSCRIPTION_ID --repo "$REPO" --body "$SUBSCRIPTION_ID"
run gh variable set AZURE_RESOURCE_GROUP --repo "$REPO" --body "$AZ_RESOURCE_GROUP"
run gh variable set AZURE_ACR_NAME --repo "$REPO" --body "$ACR_NAME"
run gh variable set AZURE_CONTAINERAPP_NAME --repo "$REPO" --body "$APP_NAME"

info "Done$([ "$DRY_RUN" = "1" ] && echo ' (dry run)')"
note "Deploy from GitHub: Actions -> Deploy -> Run workflow (branch: main)."
note "Or: gh workflow run deploy.yml --ref main"
