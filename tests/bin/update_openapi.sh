#!/usr/bin/env bash
set -euo pipefail
docker exec master-nextcloud-1 php \
	apps-extra/structureddiary/vendor/bin/generate-spec \
	apps-extra/structureddiary \
	apps-extra/structureddiary/openapi.json \
	--openapi-version 3.1.0 \
	--allow-missing-docs \
	"$@"
