#!/usr/bin/env bash
set -euo pipefail
docker exec --user=www-data master-nextcloud-1 php apps-extra/structureddiary/vendor/bin/phpunit -c apps-extra/structureddiary/tests/phpunit.xml "$@"