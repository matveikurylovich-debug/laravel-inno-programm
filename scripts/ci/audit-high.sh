#!/usr/bin/env bash
# Fail only on high and critical Composer advisories. Medium and low stay visible
# and are cleared by the dependency alignment, not by this gate.
set -euo pipefail

tmp="$(mktemp)"
composer audit --locked --format=json >"$tmp" || true

python3 - "$tmp" <<'PY'
import json
import sys

data = json.load(open(sys.argv[1]))
bad = []
for package, items in (data.get("advisories") or {}).items():
    for item in items:
        severity = (item.get("severity") or "none").lower()
        title = item.get("title") or item.get("advisoryId") or "advisory"
        print(f"{severity:8} {package}: {title}")
        if severity in {"high", "critical"}:
            bad.append(f"{package}: {title}")

if bad:
    print("high or critical advisories:", file=sys.stderr)
    print("\n".join(bad), file=sys.stderr)
    raise SystemExit(1)
PY
