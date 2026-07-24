#!/usr/bin/env python3
from __future__ import annotations
import csv
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REQUIRED = [
    "AGENTS.md",
    ".github/PULL_REQUEST_TEMPLATE.md",
    ".github/ISSUE_TEMPLATE/agent-task.yml",
    ".github/workflows/ci.yml",
    ".github/workflows/pr-policy.yml",
    ".github/workflows/nightly.yml",
    ".github/workflows/release-candidate.yml",
    "docs/project/03-MVP-SCOPE.md",
    "docs/project/05-DATA-CONTRACT.md",
    "docs/project/07-PLATFORM-AND-ARCHITECTURE.md",
    "docs/project/09-SECURITY-AND-PRIVACY.md",
    "docs/project/10-TEST-AND-ACCEPTANCE.md",
    "docs/project/11-RELEASE-AND-ROLLBACK.md",
    "docs/project/13-IMPLEMENTATION-PLAN.md",
    "docs/project/16-AUTONOMOUS-DEVELOPMENT-POLICY.md",
    "docs/project/17-AUTOMATED-QUALITY-GATES.md",
    "docs/project/18-RELEASE-CANDIDATE-HUMAN-ACCEPTANCE.md",
    "docs/management/decision-log.md",
]

missing = [path for path in REQUIRED if not (ROOT / path).is_file()]
if missing:
    raise SystemExit("Missing required repository paths: " + ", ".join(missing))

for name in ["valid-content-samples.json", "invalid-content-samples.json"]:
    data = json.loads((ROOT / "docs/testing" / name).read_text(encoding="utf-8-sig"))
    if not isinstance(data, list) or not data:
        raise SystemExit(f"{name} must contain a non-empty JSON array")
    for item in data:
        row = item.get("row", item)
        if row.get("schema_version") != "1.1":
            raise SystemExit(f"{name} contains non-1.1 schema fixture")
        if row.get("prompt_version") != "content-v1.5":
            raise SystemExit(f"{name} contains stale prompt version")

headers = []
for name in ["import-valid-sample.csv", "import-invalid-sample.csv"]:
    with (ROOT / "docs/testing" / name).open(encoding="utf-8-sig", newline="") as handle:
        rows = list(csv.DictReader(handle))
        headers.append(list(rows[0].keys()) if rows else [])
        for row in rows:
            if row.get("schema_version") != "1.1":
                raise SystemExit(f"{name} contains non-1.1 schema fixture")
            if row.get("prompt_version") != "content-v1.5":
                raise SystemExit(f"{name} contains stale prompt version")
if not headers[0] or headers[0] != headers[1] or len(headers[0]) != 35:
    raise SystemExit("CSV fixture headers must match the 35-column contract")

policy = (ROOT / "docs/project/16-AUTONOMOUS-DEVELOPMENT-POLICY.md").read_text(encoding="utf-8")
if "Release Candidate" not in policy or "تست انسانی" not in policy:
    raise SystemExit("Autonomous policy must define RC-only human acceptance")
print("Repository governance and fixture validation passed")
