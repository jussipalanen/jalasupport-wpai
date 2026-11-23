#!/usr/bin/env bash
#
# WordPress Playground demo: the app running entirely in the browser
# (PHP compiled to WebAssembly, SQLite), with sample tickets, demo AI answers
# and a demo agent account that cannot open wp-admin.
#
# Usage:
#   scripts/playground.sh build    Build the bundle in build/playground/
#   scripts/playground.sh serve    Build, then run it locally with the
#                                  Playground CLI on http://127.0.0.1:9400
#
# The bundle (build/playground/jalasupport-playground.zip) holds:
#   blueprint.json           Setup steps (demo/playground/blueprint.json)
#   jalasupport-plugin.zip   The plugin with its own Composer autoloader
#   jalasupport-theme.zip    The theme with Acorn and production assets
#   tickets.json             Fictional sample tickets (demo/playground/)
#   jalasupport-playground.php  Must-use plugin: lets Acorn render pages
#                            under Playground's "cli" PHP (demo only)
#
# Nothing secret goes into the bundle: no .env values, no keys, no real data.

set -euo pipefail

cd "$(dirname "$0")/.."

PLAYGROUND_CLI="@wp-playground/cli@3.1.63"
PHP_VERSION="8.3"
PORT="${PORT:-9400}"

PLUGIN_SRC="web/app/plugins/jalasupport"
THEME_SRC="web/app/themes/jalasupport"
OUT="build/playground"
STAGE="$OUT/stage"
BUNDLE="$OUT/bundle"

info() { printf '\n==> %s\n' "$*"; }
fail() {
    printf 'ERROR: %s\n' "$*" >&2
    exit 1
}

require() {
    command -v "$1" > /dev/null 2>&1 || fail "$1 is required on the host ($2)."
}

# zip <archive> <directory>: archive with <directory>'s name as the top folder.
make_zip() {
    python3 - "$1" "$2" <<'PY'
import os, sys, zipfile
archive, source = sys.argv[1], sys.argv[2]
parent = os.path.dirname(os.path.abspath(source))
with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as zf:
    for root, dirs, files in os.walk(source):
        dirs.sort()
        for name in sorted(files):
            path = os.path.join(root, name)
            zf.write(path, os.path.relpath(os.path.abspath(path), parent))
PY
}

acorn_version() {
    # shellcheck disable=SC2016 # PHP code: $ must reach PHP unexpanded.
    php -r '
        $lock = json_decode(file_get_contents("composer.lock"), true);
        foreach ($lock["packages"] as $package) {
            if ($package["name"] === "roots/acorn") {
                echo ltrim($package["version"], "v");
            }
        }
    '
}

build_plugin() {
    info "Packaging the plugin"
    local dir="$STAGE/jalasupport"
    mkdir -p "$dir"
    cp -R "$PLUGIN_SRC/jalasupport.php" "$PLUGIN_SRC/app" "$PLUGIN_SRC/resources" "$dir/"
    cat > "$dir/composer.json" <<'JSON'
{
    "name": "jalasupport/plugin-playground",
    "autoload": {
        "psr-4": {
            "JalaSupport\\": "app/"
        }
    }
}
JSON
    composer dump-autoload --working-dir="$dir" --classmap-authoritative --no-interaction --quiet
    rm "$dir/composer.json"
    make_zip "$BUNDLE/jalasupport-plugin.zip" "$dir"
}

build_theme() {
    local version dir="$STAGE/theme/jalasupport"
    version="$(acorn_version)"
    [ -n "$version" ] || fail "roots/acorn not found in composer.lock."

    info "Packaging the theme (Acorn ${version})"
    mkdir -p "$dir"
    cp -R "$THEME_SRC/app" "$THEME_SRC/resources" "$THEME_SRC/functions.php" "$THEME_SRC/index.php" \
        "$THEME_SRC/style.css" "$THEME_SRC/theme.json" "$THEME_SRC/screenshot.png" "$dir/"

    cat > "$dir/composer.json" <<JSON
{
    "name": "jalasupport/theme-playground",
    "require": {
        "roots/acorn": "${version}"
    },
    "autoload": {
        "psr-4": {
            "App\\\\": "app/"
        }
    },
    "config": {
        "platform": {
            "php": "${PHP_VERSION}"
        },
        "optimize-autoloader": true
    }
}
JSON
    composer update --working-dir="$dir" --no-dev --no-scripts --no-interaction --quiet
    # Acorn reads the theme's composer.json (App\ namespace): keep it.
    rm "$dir/composer.lock"

    info "Building theme assets for /wp-content/themes/jalasupport"
    [ -d "$THEME_SRC/node_modules" ] || fail "Theme npm dependencies are missing: ./dev assets"
    (
        cd "$THEME_SRC"
        npx vite build --base=/wp-content/themes/jalasupport/public/build/ \
            --outDir="$OLDPWD/$dir/public/build" --emptyOutDir --logLevel=warn
    )
    [ -f "$dir/public/build/manifest.json" ] || fail "The theme build did not write public/build/manifest.json."

    make_zip "$BUNDLE/jalasupport-theme.zip" "$dir"
}

build() {
    require php "PHP 8.3+"
    require composer "Composer 2"
    require npx "Node.js 20.18+"
    require python3 "used to write the zip files"

    rm -rf "$OUT"
    mkdir -p "$STAGE" "$BUNDLE"

    build_plugin
    build_theme
    cp demo/playground/blueprint.json demo/playground/tickets.json demo/playground/jalasupport-playground.php "$BUNDLE/"

    info "Writing the bundle archive"
    (
        cd "$BUNDLE"
        python3 -m zipfile -c ../jalasupport-playground.zip blueprint.json tickets.json \
            jalasupport-playground.php jalasupport-plugin.zip jalasupport-theme.zip
    )
    rm -rf "$STAGE"

    printf '\nBundle ready:\n'
    du -h "$BUNDLE"/* "$OUT/jalasupport-playground.zip" | sed 's/^/  /'
}

serve() {
    build
    info "Starting WordPress Playground on http://127.0.0.1:${PORT} (Ctrl+C stops it)"
    printf 'Logged in as the demo agent (demo / demo). wp-admin is closed to it.\n'
    printf 'Everything resets when the server stops.\n'
    exec npx --yes "$PLAYGROUND_CLI" server \
        --blueprint="$OUT/jalasupport-playground.zip" \
        --php="$PHP_VERSION" \
        --port="$PORT"
}

case "${1:-}" in
    build) build ;;
    serve) serve ;;
    *) fail "Usage: $(basename "$0") build | serve" ;;
esac
