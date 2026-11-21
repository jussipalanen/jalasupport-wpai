#!/usr/bin/env bash
#
# Shared helpers for the Azure deployment scripts. Sourced, not executed.
#
# DRY_RUN=1 prints the Azure and Docker commands that change something instead
# of running them. Read-only lookups still run.

set -euo pipefail

AZURE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$AZURE_DIR/../.." && pwd)"
CONFIG_FILE="$AZURE_DIR/config.env"
STATE_FILE="$AZURE_DIR/.state.env"
DRY_RUN="${DRY_RUN:-0}"

info() { printf '\n\033[1;34m==>\033[0m %s\n' "$*"; }
note() { printf '    %s\n' "$*"; }
warn() { printf '\033[1;33mWARNING:\033[0m %s\n' "$*" >&2; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# Run a command that changes something (skipped and printed in dry-run mode).
run() {
    if [ "$DRY_RUN" = "1" ]; then
        printf '    [dry-run] %s\n' "$*"
        return 0
    fi
    "$@"
}

# Random lowercase letters and digits.
random_alnum() {
    LC_ALL=C tr -dc 'a-z0-9' < /dev/urandom | head -c "${1:-16}"
}

# Random secret safe for YAML, env files and URLs.
random_secret() {
    LC_ALL=C tr -dc 'A-Za-z0-9_-' < /dev/urandom | head -c "${1:-64}"
}

load_config() {
    [ -f "$CONFIG_FILE" ] || fail "Missing $CONFIG_FILE. Create it: cp scripts/azure/config.example.env scripts/azure/config.env"
    # shellcheck source=/dev/null
    . "$CONFIG_FILE"

    [[ "$AZ_PREFIX" =~ ^[a-z0-9]{3,14}$ ]] || fail "AZ_PREFIX must be 3-14 lowercase letters or digits (storage account names allow 24 characters)."
}

# Generated values (name suffix, database password, WordPress salts) are kept
# in a local git-ignored file so the scripts can be re-run safely.
load_state() {
    if [ ! -f "$STATE_FILE" ]; then
        umask 077
        {
            echo "SUFFIX='$(random_alnum 6)'"
            echo "MYSQL_ADMIN_PASSWORD='Js1-$(random_secret 28)'"
            for key in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
                echo "$key='$(random_secret 64)'"
            done
        } > "$STATE_FILE"
        note "Generated secrets in scripts/azure/.state.env (keep it private, it is git-ignored)."
    fi
    # shellcheck source=/dev/null
    . "$STATE_FILE"

    MYSQL_SERVER="${AZ_PREFIX}-db-${SUFFIX}"
    STORAGE_ACCOUNT="${AZ_PREFIX}st${SUFFIX}"
    ACR_NAME="${AZ_PREFIX}acr${SUFFIX}"
    IDENTITY_NAME="${AZ_PREFIX}-pull"
    ENV_NAME="${AZ_PREFIX}-env"
    APP_NAME="${AZ_PREFIX}-app"
    DB_NAME="jalasupport"
    IMAGE_REPO="jalasupport"
}

require_tools() {
    command -v az > /dev/null || fail "Azure CLI (az) is not installed."
    command -v docker > /dev/null || fail "Docker is not installed."
    docker info > /dev/null 2>&1 || fail "Docker is not running."
    az account show > /dev/null 2>&1 || fail "Not logged in to Azure. Run: az login"

    if [ -n "${AZ_SUBSCRIPTION:-}" ]; then
        az account set --subscription "$AZ_SUBSCRIPTION"
    fi

    az extension add --name containerapp --upgrade --yes --only-show-errors > /dev/null
}

confirm() {
    [ "$DRY_RUN" = "1" ] && return 0
    local answer
    read -r -p "$1 [y/N] " answer
    [[ "$answer" =~ ^[Yy]$ ]] || fail "Aborted."
}

# Image tag: short commit hash, marked when the working tree has changes.
image_tag() {
    local tag
    tag="$(git -C "$PROJECT_DIR" rev-parse --short HEAD)"
    if [ -n "$(git -C "$PROJECT_DIR" status --porcelain)" ]; then
        tag="${tag}-dirty-$(date +%Y%m%d%H%M%S)"
    fi
    echo "$tag"
}

# Build the production image and push it to Azure Container Registry.
build_and_push() {
    local tag="$1"
    local image="${ACR_NAME}.azurecr.io/${IMAGE_REPO}:${tag}"

    info "Building the production image ${image}"
    run az acr login --name "$ACR_NAME"
    run docker build --target prod -f "$PROJECT_DIR/docker/php/Dockerfile" -t "$image" "$PROJECT_DIR"
    run docker push "$image"
}
