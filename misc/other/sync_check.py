#!/usr/bin/env python3
"""
Check that the lsyncd mirror of this checkout is up to date.

lsyncd (misc/<project>.conf) pushes ~/git/bga-<project>/ to the studio mount at
~/Develop/bga/remote/<project>/. CHANGELOG.md stands in for the whole tree: it
changes on every release, so a stale copy means the mirror stopped running.

Usage: python3 sync_check.py
"""

import sys
from pathlib import Path

REMOTE_ROOT = Path.home() / "Develop" / "bga" / "remote"


def fail(message):
    print(f"sync:check: {message}", file=sys.stderr)
    sys.exit(1)


def main():
    repo = Path(__file__).resolve().parents[2]
    project = repo.name.removeprefix("bga-")

    # The template game ships with every studio account, so its absence means the
    # mount itself is down rather than this project being out of sync.
    if not (REMOTE_ROOT / "template").is_dir():
        fail(f"{REMOTE_ROOT} is not mounted - remote sync is not working")

    local = repo / "CHANGELOG.md"
    remote = REMOTE_ROOT / project / "CHANGELOG.md"
    if not remote.is_file():
        fail(f"{remote} is missing - {project} has never synced")
    if remote.read_bytes() != local.read_bytes():
        fail(f"{remote} is stale - remote is out of sync, check lsyncd")

    print(f"sync:check: {project} is in sync")


if __name__ == "__main__":
    main()
