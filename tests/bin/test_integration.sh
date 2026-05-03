#!/usr/bin/env bash
set -euo pipefail
docker exec --user=www-data -e TEST_DONT_LOAD_APPS=1 master-nextcloud_integration_tests-1 php apps-extra/structureddiary/vendor/bin/phpunit -c apps-extra/structureddiary/tests/phpunit.integration.xml "$@"
