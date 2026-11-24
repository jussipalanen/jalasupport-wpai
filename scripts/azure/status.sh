#!/usr/bin/env bash
#
# Show the state of the Azure deployment. Read-only: changes nothing.
#
# Usage:
#   scripts/azure/status.sh            # resources, app URL, revision, replicas
#   scripts/azure/status.sh --health   # also request /healthz.txt (wakes the app)

# shellcheck source=scripts/azure/lib.sh
. "$(dirname "$0")/lib.sh"

load_config
load_state
require_azure

if ! az group show -n "$AZ_RESOURCE_GROUP" --output none 2> /dev/null; then
    note "Not deployed: resource group ${AZ_RESOURCE_GROUP} does not exist."
    note "Create it with: ./dev azure setup"
    exit 0
fi

info "Container app ${APP_NAME}"
if az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --output none 2> /dev/null; then
    az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" \
        --query "{url: join('', ['https://', properties.configuration.ingress.fqdn]), state: properties.provisioningState, running: properties.runningStatus, revision: properties.latestRevisionName, image: properties.template.containers[0].image, domains: properties.configuration.ingress.customDomains[].name, minReplicas: properties.template.scale.minReplicas, maxReplicas: properties.template.scale.maxReplicas}" \
        -o yamlc
    note "Running replicas: $(az containerapp replica list -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query 'length(@)' -o tsv 2> /dev/null || echo 0) (0 = scaled to zero, wakes on the next request)"
else
    note "Not found."
fi

info "Database ${MYSQL_SERVER}"
az mysql flexible-server show -g "$AZ_RESOURCE_GROUP" -n "$MYSQL_SERVER" \
    --query "{state: state, sku: sku.name, version: version, storageGB: storage.storageSizeGb, autoGrow: storage.autoGrow, highAvailability: highAvailability.mode}" \
    -o yamlc 2> /dev/null || note "Not found."

info "All resources in ${AZ_RESOURCE_GROUP}"
az resource list -g "$AZ_RESOURCE_GROUP" --query '[].{name: name, type: type}' -o table

if [ "${1:-}" = "--health" ]; then
    fqdn="$(az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query properties.configuration.ingress.fqdn -o tsv 2> /dev/null)"
    if [ -n "$fqdn" ]; then
        info "Health check https://${fqdn}/healthz.txt (may take a few seconds on a cold start)"
        if curl -fsS --max-time 60 -o /dev/null -w '    HTTP %{http_code} in %{time_total}s\n' "https://${fqdn}/healthz.txt"; then
            note "Healthy."
        else
            warn "Health check failed."
        fi
    fi
fi
