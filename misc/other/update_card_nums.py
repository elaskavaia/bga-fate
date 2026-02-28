#!/usr/bin/env python3
"""
Update num column in card_material.csv using correct numbers from CardInfo.csv.

Matches cards by hero name (mapped to hno) and card name.
Hero cards (Level I/II) share the same name, so they are matched by position
(1st occurrence = Level I = odd num, 2nd = Level II = even num).

Usage: python3 update_card_nums.py
"""

import sys
import os

CARDINFO = os.path.expanduser("~/Develop/bga/bga-assets/fate/Cards/CardInfo.csv")
CARD_MATERIAL = os.path.join(os.path.dirname(__file__), "..", "card_material.csv")

# Hero name -> hero number mapping
HERO_MAP = {
    "Bjorn": "1",
    "Alva": "2",
    "Embla": "3",
    "Boldur": "4",
    "Finkel": "5",
    "Sindra": "6",
}

# ctype mapping (CardInfo uses Title case, card_material uses lowercase)
CTYPE_MAP = {
    "Hero": "hero",
    "Ability": "ability",
    "Equipment": "equip",
    "Event": "event",
}


def normalize(s):
    """Normalize curly quotes to straight quotes for matching."""
    return s.replace("\u2018", "'").replace("\u2019", "'").replace("\u201c", '"').replace("\u201d", '"')


def read_cardinfo(path):
    """Read CardInfo.csv and build lookup: (hno, name) -> list of nums."""
    lookup = {}  # (hno, name) -> [num1, num2, ...]
    with open(path, "r", encoding="utf-8") as f:
        header = f.readline()  # skip header
        for line in f:
            line = line.rstrip("\n")
            if not line:
                continue
            fields = line.split("|")
            num = fields[0]
            htype = fields[1]
            name = fields[2]
            hno = HERO_MAP.get(htype)
            if hno is None:
                print(f"WARNING: unknown hero '{htype}' in CardInfo.csv", file=sys.stderr)
                continue
            key = (hno, normalize(name))
            lookup.setdefault(key, []).append(num)
    return lookup


def update_card_material(mat_path, lookup):
    """Update num column in card_material.csv using lookup from CardInfo."""
    # Track which occurrence of each (hno, name) we've seen (for hero card pairs)
    seen_count = {}  # (hno, name) -> count of occurrences so far
    updated = 0
    unmatched = []

    lines = []
    with open(mat_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.rstrip("\n")
            # Pass through comments, directives, empty lines, and header
            if not line or line.startswith("#") or "|" not in line:
                lines.append(line)
                continue

            fields = line.split("|")
            # Header line detection (first field is "num")
            if fields[0] == "num":
                lines.append(line)
                continue

            old_num = fields[0]
            hno = fields[1]
            name = fields[2]

            key = (hno, name)
            seen_count.setdefault(key, 0)
            idx = seen_count[key]
            seen_count[key] += 1

            nums = lookup.get(key)
            if nums is None:
                unmatched.append(f"  {hno}|{name} (old num={old_num})")
                lines.append(line)
                continue

            if idx >= len(nums):
                unmatched.append(f"  {hno}|{name} occurrence {idx+1} (only {len(nums)} in CardInfo)")
                lines.append(line)
                continue

            new_num = nums[idx]
            if old_num != new_num:
                fields[0] = new_num
                updated += 1
                print(f"  {hno}|{name}: {old_num} -> {new_num}")

            lines.append("|".join(fields))

    with open(mat_path, "w", encoding="utf-8") as f:
        for line in lines:
            f.write(line + "\n")

    print(f"\nUpdated {updated} num values.")
    if unmatched:
        print(f"\nUnmatched cards ({len(unmatched)}):")
        for u in unmatched:
            print(u)


def main():
    cardinfo_path = sys.argv[1] if len(sys.argv) > 1 else CARDINFO
    mat_path = sys.argv[2] if len(sys.argv) > 2 else CARD_MATERIAL

    if not os.path.exists(cardinfo_path):
        print(f"ERROR: CardInfo.csv not found at {cardinfo_path}", file=sys.stderr)
        sys.exit(1)
    if not os.path.exists(mat_path):
        print(f"ERROR: card_material.csv not found at {mat_path}", file=sys.stderr)
        sys.exit(1)

    print(f"Reading CardInfo from: {cardinfo_path}")
    lookup = read_cardinfo(cardinfo_path)
    print(f"Loaded {sum(len(v) for v in lookup.values())} cards from CardInfo.csv")

    print(f"\nUpdating: {mat_path}")
    update_card_material(mat_path, lookup)


if __name__ == "__main__":
    main()
