#!/usr/bin/env bash
set -euo pipefail

NEXTCLOUD_SYSTEM_TEST_BASE_URL="${NEXTCLOUD_SYSTEM_TEST_BASE_URL:-http://nextcloud_integration_tests}"
ANALYSIS_SERVICE_SECRET="${ANALYSIS_SERVICE_SECRET:-`head -c 32 /dev/urandom | md5sum | cut -d' ' -f1`}"
export ANALYSIS_SERVICE_SECRET
ANALYSIS_SERVICE_URL="${ANALYSIS_SERVICE_URL:-http://structureddiary-analysis-integration:8000}"
NEXTCLOUD_SYSTEM_TEST_TRUSTED_DOMAIN="${NEXTCLOUD_SYSTEM_TEST_TRUSTED_DOMAIN:-${NEXTCLOUD_SYSTEM_TEST_BASE_URL#*://}}"
NEXTCLOUD_SYSTEM_TEST_TRUSTED_DOMAIN="${NEXTCLOUD_SYSTEM_TEST_TRUSTED_DOMAIN%%/*}"

compose_file="tests/Integration/AnalysisService/docker-compose.yml"
compose_project="structureddiary-analysis-integration-test"
ANALYSIS_SERVICE_WORK_DIR="${ANALYSIS_SERVICE_WORK_DIR:-$(mktemp -d "${TMPDIR:-/tmp}/structureddiary-analysis-integration.XXXXXX")}"
if [[ -d "$ANALYSIS_SERVICE_WORK_DIR" ]] && [[ -n "$(find "$ANALYSIS_SERVICE_WORK_DIR" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
	printf 'Analysis-service execution directory must be empty: %s\n' "$ANALYSIS_SERVICE_WORK_DIR" >&2
	exit 1
fi
export ANALYSIS_SERVICE_WORK_DIR
mkdir -p "$ANALYSIS_SERVICE_WORK_DIR"
chmod 0777 "$ANALYSIS_SERVICE_WORK_DIR"

get_app_config() {
	docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:app:get structureddiary "$1" --no-warnings 2>/dev/null || true
}

restore_app_config() {
	local key="$1"
	local value="$2"
	local sensitive="$3"
	if [[ -z "$value" ]]; then
		docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:app:delete structureddiary "$key" --no-warnings || true
		return
	fi

	local sensitivity_option="--no-sensitive"
	if [[ "$sensitive" == 'true' ]]; then
		sensitivity_option="--sensitive"
	fi
	docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:app:set structureddiary "$key" --value="$value" --lazy "$sensitivity_option" --no-warnings || true
}

original_overwrite_cli_url="$(docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:get overwrite.cli.url --no-warnings 2>/dev/null || true)"
original_trusted_domain="$(docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:get trusted_domains 99 --no-warnings 2>/dev/null || true)"
original_service_url="$(get_app_config analysis.service_url)"
original_service_secret="$(get_app_config analysis.service_secret)"
original_nextcloud_api_token="$(get_app_config analysis.nextcloud_api_token)"
original_output_base_folder="$(get_app_config analysis.output_base_folder)"

cleanup() {
	docker compose -p "$compose_project" -f "$compose_file" down --remove-orphans
	if [[ -n "$original_overwrite_cli_url" ]]; then
		docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:set overwrite.cli.url --value="$original_overwrite_cli_url"
	else
		docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:delete overwrite.cli.url || true
	fi
	if [[ -n "$original_trusted_domain" ]]; then
		docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:set trusted_domains 99 --value="$original_trusted_domain"
	else
		docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:delete trusted_domains 99 || true
	fi
	restore_app_config analysis.service_url "$original_service_url" false
	restore_app_config analysis.service_secret "$original_service_secret" true
	restore_app_config analysis.nextcloud_api_token "$original_nextcloud_api_token" true
	restore_app_config analysis.output_base_folder "$original_output_base_folder" false
	printf 'Analysis-service execution directory retained at: %s\n' "$ANALYSIS_SERVICE_WORK_DIR"
}
trap cleanup EXIT

docker exec --user=www-data master-nextcloud_integration_tests-1 php occ upgrade --no-interaction
docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:set overwrite.cli.url --value="$NEXTCLOUD_SYSTEM_TEST_BASE_URL"
docker exec --user=www-data master-nextcloud_integration_tests-1 php occ config:system:set trusted_domains 99 --value="$NEXTCLOUD_SYSTEM_TEST_TRUSTED_DOMAIN"

docker compose -p "$compose_project" -f "$compose_file" up --detach --wait

docker exec --user=www-data \
  -e TEST_DONT_LOAD_APPS=1 \
  -e INTEGRATION_TEST_DB=1 \
  -e ANALYSIS_SERVICE_SYSTEM_TEST=1 \
  -e NEXTCLOUD_SYSTEM_TEST_BASE_URL="$NEXTCLOUD_SYSTEM_TEST_BASE_URL" \
  -e ANALYSIS_SERVICE_URL="$ANALYSIS_SERVICE_URL" \
  -e ANALYSIS_SERVICE_SECRET="$ANALYSIS_SERVICE_SECRET" \
  -e ANALYSIS_SERVICE_TIMEOUT="${ANALYSIS_SERVICE_TIMEOUT:-120}" \
  master-nextcloud_integration_tests-1 \
  php apps-extra/structureddiary/vendor/bin/phpunit \
  -c apps-extra/structureddiary/tests/phpunit.integration.xml \
  --group analysis-service
