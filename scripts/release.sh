#!/usr/bin/env bash
#
# Release helper for JalaSupport AI. Versions and tags are plain x.y.z
# (Semantic Versioning, no "v" prefix); CHANGELOG.md is the source of the
# release notes.
#
# Usage:
#   scripts/release.sh prepare <x.y.z>   Bump versions and date the changelog
#                                        on a new release/<x.y.z> branch
#   scripts/release.sh tag <x.y.z>       Tag main and push the tag (asks first);
#                                        the Release workflow publishes it
#   scripts/release.sh check [x.y.z]     Verify version headers and changelog
#                                        (default: newest changelog version)
#   scripts/release.sh notes <x.y.z>     Print the changelog section of a version
#
# DRY_RUN=1 shows what prepare/tag would do without changing anything.

set -euo pipefail

cd "$(dirname "$0")/.."

CHANGELOG="CHANGELOG.md"
PLUGIN_FILE="web/app/plugins/jalasupport/jalasupport.php"
THEME_FILE="web/app/themes/jalasupport/style.css"
# Static badge: shields.io cannot read releases of a private repository.
README_FILE="README.md"
REPO_URL="https://github.com/jussipalanen/jalasupport-wpai"
DRY_RUN="${DRY_RUN:-0}"

info() { printf '\n==> %s\n' "$*"; }
note() { printf '    %s\n' "$*"; }
fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require_version() {
    [[ "${1:-}" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] \
        || fail "Version must be x.y.z (for example 0.2.0), got '${1:-}'."
}

# Newest released version in the changelog (first "## [x.y.z]" heading).
latest_version() {
    grep -m1 -oE '^## \[[0-9]+\.[0-9]+\.[0-9]+\]' "$CHANGELOG" | tr -d '#[] '
}

plugin_header_version() { sed -nE 's/^ \* Version:[[:space:]]+([^[:space:]]+).*/\1/p' "$PLUGIN_FILE"; }
plugin_constant_version() { sed -nE "s/^define\('JALASUPPORT_VERSION', '([^']+)'\);/\1/p" "$PLUGIN_FILE"; }
theme_version() { sed -nE 's/^Version:[[:space:]]+([^[:space:]]+).*/\1/p' "$THEME_FILE"; }
badge_version() { sed -nE 's#.*img\.shields\.io/badge/release-([0-9.]+)-.*#\1#p' "$README_FILE" | head -n1; }

# Body of one version's changelog section, without surrounding blank lines.
section() {
    awk -v heading="## [$1]" '
        index($0, heading) == 1 { found = 1; next }
        found && /^## \[/ { exit }
        found && /^\[[^]]+\]: / { exit }
        found { lines[++n] = $0 }
        END {
            first = 1; while (first <= n && lines[first] ~ /^[[:space:]]*$/) first++
            last = n; while (last >= first && lines[last] ~ /^[[:space:]]*$/) last--
            for (i = first; i <= last; i++) print lines[i]
        }
    ' "$CHANGELOG"
}

cmd_check() {
    local version="${1:-$(latest_version)}" errors=0 actual
    require_version "$version"

    grep -qE "^## \[${version//./\\.}\] - [0-9]{4}-[0-9]{2}-[0-9]{2}$" "$CHANGELOG" \
        || {
            note "CHANGELOG.md: no dated '## [${version}] - YYYY-MM-DD' section"
            errors=1
        }
    [ -n "$(section "$version")" ] || {
        note "CHANGELOG.md: section ${version} is empty"
        errors=1
    }
    grep -qF "[${version}]: " "$CHANGELOG" || {
        note "CHANGELOG.md: link reference [${version}] is missing"
        errors=1
    }

    for pair in "plugin header:$(plugin_header_version)" "plugin JALASUPPORT_VERSION:$(plugin_constant_version)" "theme style.css:$(theme_version)" "README release badge:$(badge_version)"; do
        actual="${pair##*:}"
        [ "$actual" = "$version" ] || {
            note "${pair%:*} is '${actual}', expected ${version}"
            errors=1
        }
    done

    [ "$errors" = 0 ] || fail "Release metadata does not match version ${version}."
    note "Release metadata OK for ${version} (changelog, plugin, theme, README badge)."
}

cmd_notes() {
    require_version "${1:-}"
    local body
    body="$(section "$1")"
    [ -n "$body" ] || fail "No changelog section for ${1}."
    printf '%s\n' "$body"
}

cmd_prepare() {
    local version="${1:-}" previous today branch
    require_version "$version"
    previous="$(latest_version)"
    today="$(date -u +%Y-%m-%d)"
    branch="release/${version}"

    [ -z "$(git status --porcelain)" ] || fail "The working tree has uncommitted changes."
    [ "$(git rev-parse --abbrev-ref HEAD)" = "main" ] || fail "Run prepare on main (git checkout main && git pull)."
    [ -z "$(git tag -l "$version")" ] || fail "Tag ${version} already exists."
    if [ -n "$previous" ]; then
        [ "$(printf '%s\n%s\n' "$previous" "$version" | sort -V | tail -n1)" = "$version" ] && [ "$previous" != "$version" ] \
            || fail "New version ${version} must be greater than ${previous}."
    fi
    [ -n "$(section Unreleased)" ] || fail "The [Unreleased] section of CHANGELOG.md is empty: add the changes first."

    info "Prepare release ${version} (previous: ${previous:-none})$([ "$DRY_RUN" = 1 ] && echo ' (DRY RUN)')"
    note "Branch:    ${branch}"
    note "Changelog: [Unreleased] -> [${version}] - ${today}"
    note "Versions:  ${PLUGIN_FILE} (header, constant), ${THEME_FILE}, ${README_FILE} badge"
    [ "$DRY_RUN" = 1 ] && return 0

    git checkout -q -b "$branch"

    awk -v version="$version" -v today="$today" -v previous="$previous" -v repo="$REPO_URL" '
        $0 == "## [Unreleased]" { print; print ""; print "## [" version "] - " today; next }
        /^\[Unreleased\]: / {
            print "[Unreleased]: " repo "/compare/" version "...HEAD"
            if (previous != "") print "[" version "]: " repo "/compare/" previous "..." version
            else print "[" version "]: " repo "/releases/tag/" version
            next
        }
        { print }
    ' "$CHANGELOG" > "${CHANGELOG}.tmp" && mv "${CHANGELOG}.tmp" "$CHANGELOG"

    sed -i -E "s/^( \* Version:[[:space:]]+).*/\1${version}/" "$PLUGIN_FILE"
    sed -i -E "s/^define\('JALASUPPORT_VERSION', '[^']+'\);/define('JALASUPPORT_VERSION', '${version}');/" "$PLUGIN_FILE"
    sed -i -E "s/^(Version:[[:space:]]+).*/\1${version}/" "$THEME_FILE"
    sed -i -E "s#(img\.shields\.io/badge/release-)[0-9.]+-#\1${version}-#" "$README_FILE"

    cmd_check "$version"
    git add "$CHANGELOG" "$PLUGIN_FILE" "$THEME_FILE" "$README_FILE"
    git commit -q -m "chore(release): ${version}"

    info "Release commit created on ${branch}"
    note "Next: git push -u origin ${branch}, open a PR and merge it,"
    note "then: git checkout main && git pull && ./dev release tag ${version}"
}

cmd_tag() {
    local version="${1:-}" answer
    require_version "$version"

    [ -z "$(git status --porcelain)" ] || fail "The working tree has uncommitted changes."
    [ "$(git rev-parse --abbrev-ref HEAD)" = "main" ] || fail "Tags are created from main (git checkout main && git pull)."
    git fetch -q origin main --tags
    [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ] || fail "Local main differs from origin/main: run git pull."
    [ -z "$(git tag -l "$version")" ] || fail "Tag ${version} already exists."
    cmd_check "$version"

    info "Tag ${version} at $(git rev-parse --short HEAD) and push it$([ "$DRY_RUN" = 1 ] && echo ' (DRY RUN)')"
    note "The Release workflow then publishes the GitHub release with these notes:"
    cmd_notes "$version" | sed 's/^/        /'
    [ "$DRY_RUN" = 1 ] && return 0

    read -r -p "Create and push tag ${version}? [y/N] " answer
    [[ "$answer" =~ ^[Yy]$ ]] || fail "Cancelled."

    git tag -a "$version" -m "JalaSupport AI ${version}"
    git push origin "refs/tags/${version}"
    note "Pushed. Follow the release: gh run watch \$(gh run list -w Release -L1 --json databaseId -q '.[0].databaseId')"
}

case "${1:-help}" in
    prepare | tag | check | notes)
        command="$1"
        shift
        "cmd_${command}" "$@"
        ;;
    help | -h | --help)
        sed -n '3,16p' "$0" | sed 's/^# \{0,1\}//'
        ;;
    *)
        fail "Unknown command '${1}'. Use: prepare, tag, check or notes."
        ;;
esac
