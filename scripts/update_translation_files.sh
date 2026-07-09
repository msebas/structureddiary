#!/usr/bin/env bash
set -euo pipefail
docker exec master-nextcloud-1 php \
	occ l10n:createjs structureddiary
	"$@"
