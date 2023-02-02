#!/usr/bin/env sh

./vendor/bin/wp i18n make-pot ./includes ./languages/mec-ics.pot --domain=mec-ics
./vendor/bin/wp i18n update-po ./languages/mec-ics.pot
rm ./languages/mec-ics.pot
