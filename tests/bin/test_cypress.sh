#!/bin/bash

set -euo pipefail

status=0

tests/bin/test_cypress_e2e.sh > tests/bin/test_cypress_e2e.sh.log 2>&1 || status=$?
tests/bin/test_cypress_component.sh > tests/bin/test_cypress_component.sh.log 2>&1 || status=$?

exit "${status}"
