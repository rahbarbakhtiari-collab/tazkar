#!/usr/bin/env python3
from __future__ import annotations
import json
import os
import subprocess
import sys
from pathlib import Path

REQUIRED_SECTIONS = [
    "## Objective",
    "## Acceptance criteria",
    "## Tests",
    "## Out of scope",
    "## Risk",
    "## Human decision",
    "## Agent evidence",
]
PROTECTED_PREFIXES = (
    "docs/project/01-PROJECT-CHARTER.md",
    "docs/project/02-PRODUCT-CONCEPT.md",
    "docs/project/03-MVP-SCOPE.md",
    "docs/project/05-DATA-CONTRACT.md",
    "docs/project/09-SECURITY-AND-PRIVACY.md",
    "docs/project/12-MONETIZATION.md",
    "docs/management/decision-log.md",
)

def fail(message: str) -> None:
    print(f"PR policy failure: {message}", file=sys.stderr)
    raise SystemExit(1)

def changed_files(base_sha: str, head_sha: str) -> list[str]:
    output = subprocess.check_output(
        ["git", "diff", "--name-only", f"{base_sha}...{head_sha}"], text=True
    )
    return [line.strip() for line in output.splitlines() if line.strip()]

def main() -> None:
    event_path = Path(os.environ["GITHUB_EVENT_PATH"])
    event = json.loads(event_path.read_text(encoding="utf-8"))
    pr = event["pull_request"]
    body = pr.get("body") or ""
    title = (pr.get("title") or "").strip()
    head = pr["head"]["ref"]
    labels = {item["name"] for item in pr.get("labels", [])}

    if not title or title.lower().startswith(("wip", "draft")):
        fail("title must be specific and must not begin with WIP/Draft")

    allowed_branch = head.startswith("agent/") or head.startswith("dependabot/") or head.startswith("docs/")
    if not allowed_branch:
        fail("head branch must start with agent/, docs/, or dependabot/")

    missing = [section for section in REQUIRED_SECTIONS if section not in body]
    if missing:
        fail("missing PR template sections: " + ", ".join(missing))

    files = changed_files(pr["base"]["sha"], pr["head"]["sha"])
    protected = [path for path in files if path.startswith(PROTECTED_PREFIXES)]
    if protected and "human-decision-required" not in labels:
        fail("protected decision files changed without human-decision-required label: " + ", ".join(protected))

    if "automerge" in labels:
        if "[human-decision: no]" not in body:
            fail("automerge requires [human-decision: no]")
        if {"blocked", "human-decision-required"} & labels:
            fail("automerge cannot coexist with blocked or human-decision-required")
        if pr.get("draft"):
            fail("automerge PR must be ready for review")
        if pr["head"]["repo"]["full_name"] != pr["base"]["repo"]["full_name"]:
            fail("automerge is allowed only for same-repository branches")

    print(f"PR policy passed for {len(files)} changed files")

if __name__ == "__main__":
    main()
