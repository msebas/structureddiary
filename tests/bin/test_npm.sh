#!/usr/bin/env bash

set -euo pipefail

export BROWSERSLIST="defaults"
export BROWSERSLIST_DISABLE_CACHE=1

source /home/Sebastian/bin/conda

conda activate fin
npm run "$@"