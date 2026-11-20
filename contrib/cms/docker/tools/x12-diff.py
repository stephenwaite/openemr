#!/usr/bin/env python3
"""Compare two 837P files claim by claim.

Usage: x12-diff.py [--summary] <production.x12> <dry-run.x12>

Claims are matched on CLM01 (OpenEMR writes "<pid>-<encounter>"). Values that
differ on every run are masked: interchange/group/transaction control numbers,
creation dates and times (ISA09/10, GS04/05, BHT04/05) and the ST/SE counts.
Everything else, including service dates, is compared exactly.

Prints only claim IDs and segment differences. The output contains claim data,
so keep it with the dry-run files, not in git.

--summary prints no values and no claim IDs: only how often each kind of
difference occurs, as segment type (plus its qualifier, e.g. "DTP 472",
"NM1 82") and the numbers of the elements that differ, and segments found
on one side only. Safe to share.
"""

import collections
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


# Segments whose first element is a qualifier (not patient data), shown in
# the summary to tell e.g. DTP*472 from DTP*431. HI's first element is
# "<qualifier>:<code>"; only the qualifier is used.
QUALIFIED = {"DTP", "NM1", "REF", "AMT", "QTY", "CAS", "SBR", "PRV", "PWK", "HI", "K3", "NTE"}


def kind(seg):
    parts = seg.split("*")
    if parts[0] in QUALIFIED and len(parts) > 1:
        return parts[0] + " " + parts[1].split(":")[0]
    return parts[0]


def summarize(old_claim, new_claim, counts):
    matcher = difflib.SequenceMatcher(a=old_claim, b=new_claim, autojunk=False)
    for tag, i1, i2, j1, j2 in matcher.get_opcodes():
        if tag == "equal":
            continue
        olds, news = old_claim[i1:i2], new_claim[j1:j2]
        for o, n in zip(olds, news):
            a, b = o.split("*"), n.split("*")
            if a[0] != b[0]:
                counts[f"{kind(o)} in production where the dry run has {kind(n)}"] += 1
                continue
            width = max(len(a), len(b))
            differ = [str(i) for i in range(1, width) if (a + [""] * width)[i] != (b + [""] * width)[i]]
            counts[f"{kind(o)}: element(s) {','.join(differ)} differ"] += 1
        for o in olds[len(news):]:
            counts[f"{kind(o)}: only in production"] += 1
        for n in news[len(olds):]:
            counts[f"{kind(n)}: only in the dry run"] += 1


def main():
    args = sys.argv[1:]
    summary = "--summary" in args
    args = [a for a in args if a != "--summary"]
    if len(args) != 2:
        sys.exit(__doc__)
    old, new = (by_claim(segments(p)) for p in args)
    if summary:
        counts = collections.Counter()
        claims = set(old) | set(new)
        only = sum(1 for c in claims if (c in old) != (c in new))
        differing = 0
        for claim in claims:
            if claim in old and claim in new and old[claim] != new[claim]:
                differing += 1
                summarize(old[claim], new[claim], counts)
        print(f"claims compared: {len(claims) - 1 - only}, differing: {differing - (1 if old.get('<header>') != new.get('<header>') else 0)}, "
              f"only in one file: {only}, header differs: {'yes' if old.get('<header>') != new.get('<header>') else 'no'}")
        for text, count in sorted(counts.items()):
            print(f"{count:4}  {text}")
        sys.exit(1 if differing or only else 0)
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
