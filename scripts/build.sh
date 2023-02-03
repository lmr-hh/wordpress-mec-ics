#!/usr/bin/env bash

# Builds the plugin *.zip file.
# The command accepts a single argument, the version number that should be built.
# This command is intended to be run from the project root as ./scripts/build.sh <version>.

version="${1#v}"
build_dir=build

if [ -z "$version" ]; then
  echo "Version must be supplied as first argument." >&2
  exit 1
fi

function log() {
    echo -e "\033[0;34m$1\033[0m"
}

log "=== Building For Version $version ==="

log "=== Copying Files ==="
# Copy all plugin files to the build folder
rsync --recursive --exclude-from=".wpignore" . "$build_dir"

log "=== Installing Dependencies ==="
composer install --no-dev --no-interaction --working-dir="$build_dir"

log "=== Creating Optimized Autoloader ==="
composer dump-autoload --classmap-authoritative --working-dir="$build_dir"

