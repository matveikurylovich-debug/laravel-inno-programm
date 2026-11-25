#!/usr/bin/env bash
set -euo pipefail

if [[ ! -x vendor/bin/rector ]]; then
  echo "rector is not installed in $(basename "$PWD"); dry-run has nothing to apply yet"
  exit 0
fi

vendor/bin/rector process --dry-run --no-progress
