#!/usr/bin/env sh

# Extracts localized strings and applies them to existing *.po files.
# This command is intended to be run from the project root as ./scripts/extract-strings.sh.

./vendor/bin/wp i18n make-pot ./includes ./languages/mec-ics.pot --domain=mec-ics
./vendor/bin/wp i18n update-po ./languages/mec-ics.pot
rm ./languages/mec-ics.pot
