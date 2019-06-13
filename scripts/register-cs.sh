#!/usr/bin/env bash

if [ "${COMPOSER_DEV_MODE}" != 1 ]; then
   echo "No Dev, not checking coding standards."
   exit
fi

echo "Registering code standards..."
./vendor/bin/phpcs --config-set installed_paths "$PWD/vendor/drupal/coder/coder_sniffer"

PHPCS_OUTPUT=$(./vendor/bin/phpcs -i)
echo "$PHPCS_OUTPUT"

if ! [[ ${PHPCS_OUTPUT} == *"DrupalPractice"* && ${PHPCS_OUTPUT} == *"Drupal"* ]]; then
  echo "Unable to register code standards. Please fix the problems."
  exit 1
fi

echo "Done!"
