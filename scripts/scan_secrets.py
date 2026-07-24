#!/usr/bin/env python3
from __future__ import annotations
import re
import subprocess
import sys
from pathlib import Path

PATTERNS = {
    "OpenAI-like key": re.compile(r"\bsk-(?:proj-)?[A-Za-z0-9_-]{20,}\b"),
    "GitHub token": re.compile(r"\bgh[pousr]_[A-Za-z0-9]{20,}\b"),
    "Private key": re.compile(r"-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----"),
}
ALLOW = {"scripts/scan_secrets.py"}

def main() -> None:
    try:
        files = subprocess.check_output(
            ["git", "ls-files"], text=True, stderr=subprocess.DEVNULL
        ).splitlines()
    except (subprocess.CalledProcessError, FileNotFoundError):
        excluded = {".git", "vendor", "dist", "node_modules"}
        files = [
            str(path)
            for path in Path(".").rglob("*")
            if path.is_file() and not any(part in excluded for part in path.parts)
        ]
    findings: list[str] = []
    for filename in files:
        if filename in ALLOW:
            continue
        try:
            text = open(filename, "r", encoding="utf-8").read()
        except (UnicodeDecodeError, OSError):
            continue
        for name, pattern in PATTERNS.items():
            if pattern.search(text):
                findings.append(f"{filename}: {name}")
    if findings:
        print("Potential secrets detected:\n" + "\n".join(findings), file=sys.stderr)
        raise SystemExit(1)
    print("No configured secret patterns found")

if __name__ == "__main__":
    main()
