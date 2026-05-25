#!/bin/bash

set -euo pipefail

status=0

i=0

while [ -f "tests/bin/logs/test_cypress_e2e_$i.log" ] || [ -f "tests/bin/logs/test_cypress_component_$i.log" ]; do
	i=$((i + 1))
done

tests/bin/test_cypress_e2e.sh > "tests/bin/logs/test_cypress_e2e_$i.log" 2>&1 || status=$?
tests/bin/test_cypress_component.sh > "tests/bin/logs/test_cypress_component_$i.log" 2>&1 || status=$?

exit "${status}"
