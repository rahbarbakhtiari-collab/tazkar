#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

find "$ROOT" \
  \( -path "$ROOT/vendor" -o -path "$ROOT/dist" \) -prune -o \
  -name '*.php' -print0 \
  | xargs -0 -n1 php -l
