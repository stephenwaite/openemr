#!/usr/bin/env python3
"""Compare two 837P files claim by claim.

Usage: x12-diff.py <production.x12> <dry-run.x12>

Claims are matched on CLM01 (OpenEMR writes "<pid>-<encounter>"). Values that
differ on every run are masked: interchange/group/transaction control numbers,
creation dates and times (ISA09/10, GS04/05, BHT04/05) and the ST/SE counts.
Everything else, including service dates, is compared exactly.

Prints only claim IDs and segment differences. The output contains claim data,
so keep it with the dry-run files, not in git.
"""

import difflib
import sys

MASK = "<run>"

# (segment id, element positions to mask); element 0 is the segment id.
MASKED = {
    "ISA": (9, 10, 13),
    "GS": (4, 5, 6),
    "ST": (2,),
    "SE": (1, 2),
    "BHT": (3, 4, 5),
    "GE": (1, 2),
    "IEA": (2,),
}


def segments(path):
    with open(path, encoding="latin-1") as f:
        data = f.read().replace("\r", "").replace("\n", "")
    if not data.startswith("ISA") or len(data) < 106:
        sys.exit(f"{path}: not an X12 file (no ISA header)")
    element_sep, segment_term = data[3], data[105]
    out = []
    for raw in data.split(segment_term):
        if not raw:
            continue
        parts = raw.split(element_sep)
        for pos in MASKED.get(parts[0], ()):
            if pos < len(parts):
                parts[pos] = MASK
        out.append("*".join(parts))
    return out


def by_claim(segs):
    """Envelope and billing-provider loops go under '<header>', then one
    entry per claim. A claim runs from its CLM to the next CLM, HL or SE; the
    subscriber/patient HL loops just before a CLM are kept with it."""
    claims = {"<header>": []}
    pending = []  # HL loop segments since the last claim
    current = None
    for seg in segs:
        sid = seg.split("*", 1)[0]
        if sid == "CLM":
            current = seg.split("*")[1]
            claims.setdefault(current, []).extend(pending + [seg])
            pending = []
        elif sid == "HL":
            current = None
            pending = [seg]
        elif sid in ("SE", "GE", "IEA"):
            current = None
            claims["<header>"].extend(pending + [seg])
            pending = []
        elif current is not None:
            claims[current].append(seg)
        elif pending:
            pending.append(seg)
        else:
            claims["<header>"].append(seg)
    # Billing provider HL loops (no CLM after them) belong to the header.
    claims["<header>"].extend(pending)
    return claims


def main():
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    old, new = (by_claim(segments(p)) for p in sys.argv[1:])
    same = differ = 0
    for claim in sorted(set(old) | set(new), key=lambda c: (c != "<header>", c)):
        if claim not in new:
            print(f"== {claim}: only in {sys.argv[1]}")
            differ += 1
            continue
        if claim not in old:
            print(f"== {claim}: only in {sys.argv[2]}")
            differ += 1
            continue
        if old[claim] == new[claim]:
            same += 1
            continue
        differ += 1
        print(f"== {claim}")
        for line in difflib.unified_diff(old[claim], new[claim], "production", "dry-run", n=1, lineterm=""):
            if not line.startswith(("---", "+++")):
                print("  " + line)
    print(f"\n{same} identical, {differ} different (the header counts as one)")
    sys.exit(1 if differ else 0)


if __name__ == "__main__":
    main()
