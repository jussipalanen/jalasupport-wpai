#!/usr/bin/env bash
#
# Deploy the current code to the existing Azure Container App:
# build the production image, push it to the registry and roll out a new
# revision. Run scripts/azure/setup.sh once first.
#
# Usage:
#   DRY_RUN=1 scripts/azure/deploy.sh
#   scripts/azure/deploy.sh

. "$(dirname "$0")/lib.sh"

load_config
load_state
require_tools

az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --output none 2> /dev/null \
    || fail "Container app ${APP_NAME} not found. Run scripts/azure/setup.sh first."

TAG="$(image_tag)"
IMAGE="${ACR_NAME}.azurecr.io/${IMAGE_REPO}:${TAG}"

info "Deploying ${IMAGE}$([ "$DRY_RUN" = "1" ] && echo ' (DRY RUN)')"
confirm "Build, push and roll out this image?"

build_and_push "$TAG"

info "Rolling out a new revision"
run az containerapp update -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --image "$IMAGE" --only-show-errors --output none

note "Deployed: https://$(az containerapp show -g "$AZ_RESOURCE_GROUP" -n "$APP_NAME" --query properties.configuration.ingress.fqdn -o tsv)"
