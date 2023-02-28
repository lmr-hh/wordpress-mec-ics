#!/usr/bin/env bash

# Builds the plugin *.zip file.
# The command accepts a single argument, the version number that should be built.
# This command is intended to be run from the project root as ./scripts/build.sh <version>.

set -e

function log() {
    echo -e "\033[0;34m$1\033[0m"
}

build_dir="./build"
version=""
zip=""
filename=""
slug="mec-ics"

# Parse arguments
while [[ $# -gt 0 ]]; do
  case $1 in
    -v|--version)
      version="$2"
      shift 2
      ;;
    -z|--zip)
      zip=true
      shift
      ;;
    --dir)
      build_dir="$2"
      shift 2
      ;;
    -f|--filename)
      zip=true
      filename="$2"
      shift 2
      ;;
    -s|--slug)
      slug="$2"
      shift 2
      ;;
    *|-*|--*)
      echo "Unknown option $1" >&2
      exit 1
      ;;
  esac
done

if [[ -z "$version" ]]; then
  tag=$(git describe --tags --abbrev=0)
  current=$(git rev-parse HEAD)
  commits=$(git rev-list "$tag..$current" --count)
  version="$tag-dev"
  if [ "$commits" -gt "0" ]; then
    version="$version.$commits"
  fi
  echo "No version number provided. Guessing version from git: $version" >&2
fi


version="${version#v}"
target="$build_dir/$slug"

log "==> Building For Version $version"

log "==> Copying Files"
mkdir -p "$target"
rsync --recursive --exclude-from=".wpignore" --delete . "$target"

log "==> Installing Dependencies"
composer install --no-dev --no-interaction --working-dir="$target"

log "==> Creating Optimized Autoloader"
composer dump-autoload --classmap-authoritative --working-dir="$target"

log "==> Generating Translations"
./vendor/bin/wp i18n make-mo "$target/languages"

log "==> Generating Metadata"
sed "s/{{PLUGIN_VERSION}}/Version:           $version/g" "./mec-ics.php" > "$target/$slug.php"

if [[ "$zip" ]]; then
  log "==> Creating Zip File"
  if [[ -z "$filename" ]]; then
    filename="$slug-$version"
  fi
  [[ $filename == *.zip ]] || filename+=.zip
  pushd "$build_dir" >/dev/null
  zip -FSr9 "$filename" "./$slug"
  popd >/dev/null
fi
