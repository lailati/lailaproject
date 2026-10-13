#!/usr/bin/env python
"""Inline docs/erd.mmd into docs/erd.html so the viewer works offline via file://.

Run from the docs/ folder:   python build_erd_html.py
Keep erd.mmd as the single source of truth; this script never edits it.
"""
import pathlib
import sys

here = pathlib.Path(__file__).resolve().parent
mmd = here / "erd.mmd"
html = here / "erd.html"
PLACEHOLDER = "__MERMAID_SOURCE__"

src = mmd.read_text(encoding="utf-8")
doc = html.read_text(encoding="utf-8")

if "`" in src:
    sys.exit("ERROR: erd.mmd contains a backtick, which would break the JS template literal.")
if "${" in src:
    sys.exit("ERROR: erd.mmd contains '${', which JavaScript would interpolate.")

if PLACEHOLDER in doc:
    out = doc.replace(PLACEHOLDER, src)
elif "const SRC = String.raw`" in doc:
    # already built: swap the body between the first backtick and the closing backtick line
    start = doc.index("const SRC = String.raw`") + len("const SRC = String.raw`")
    end = doc.index("\n`;", start)
    out = doc[:start] + src + doc[end:]
else:
    sys.exit("ERROR: no injection point found in erd.html")

html.write_text(out, encoding="utf-8")
print(f"OK  inlined {len(src)} chars from erd.mmd into {html.name}")
