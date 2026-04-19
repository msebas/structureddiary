#!/bin/bash

set -euo pipefail

tests/bin/test_cypress_e2e.sh 2&>1 > tests/bin/test_cypress_e2e.sh.log
tests/bin/test_cypress_component.sh 2&>1 > tests/bin/test_cypress_component.sh.log