#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

vite_started=0
if ! curl -fsS http://localhost:5174/@vite/client >/dev/null 2>&1; then
	"${script_dir}/test_npm.sh" serve -- --host 0.0.0.0 --port 5174 --strictPort > "${script_dir}/test_vite_dev_server.log" 2>&1 &
	vite_pid=$!
	vite_started=1
	trap 'if [ "${vite_started}" = "1" ]; then kill "${vite_pid}" 2>/dev/null || true; fi' EXIT

	for _ in {1..60}; do
		if curl -fsS http://localhost:5174/@vite/client >/dev/null 2>&1; then
			break
		fi
		sleep 1
	done
fi

"${script_dir}/test_npm.sh" cypress:e2e "$@"
