#!/usr/bin/env python3
"""
Derive the site icon set from the client-supplied brand-icon masters.

Masters (dem-vault 85_assets/kotiva/masters/brand-icon/, client drop 2026-07-17):
  kotiva-icon-keyline-2026-07-17.png   283x283 RGBA — k glyph + 15px white keyline, transparent ground
  kotiva-icon-glyph-2026-07-17.svg     154.11x251.7 — same glyph as bare vector, no keyline

Outputs (assets/):
  favicon.svg           the vector glyph, squared to the master's 283.46 canvas geometry
  favicon-48.png        48x48, straight downscale of the keyline master (transparent)
  apple-touch-icon.png  180x180, opaque cream plate — iOS composites transparency onto BLACK,
                        which would render a black glyph invisible on the home screen.

Usage: python scripts/generate-icons.py <path-to-masters-dir>
"""
import sys
import pathlib
from PIL import Image

# Brand cream, --bg of the default "ritual" theme (css/kotiva.css).
PLATE = (247, 242, 235, 255)
# Glyph placement inside the square canvas, derived from the master's alpha bbox:
# glyph 154.11x251.7 centred in 283.46 -> x 64.675, y 15.88 (keyline extends 15px beyond).
# The keyline is not decoration — it is what makes the mark legible on a dark tab strip.
# The client's vector master ships the bare glyph, so the PNG master's 15px white keyline
# is reproduced here as a 30px stroke painted under the fill (paint-order). Without it the
# black glyph is invisible in dark-themed Chrome/Firefox/Edge, which prefer the SVG icon.
SVG_TEMPLATE = """<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 283.46 283.46">
  <title>kotiva</title>
  <g transform="translate(64.675 15.88)">
    <path fill="#231f20" stroke="#fff" stroke-width="30" stroke-linejoin="round"
          paint-order="stroke fill" d="{path}"/>
  </g>
</svg>
"""


def main(masters: pathlib.Path) -> None:
    out = pathlib.Path(__file__).resolve().parent.parent / "assets"

    png_master = Image.open(masters / "kotiva-icon-keyline-2026-07-17.png").convert("RGBA")
    if png_master.size != (283, 283):
        raise SystemExit(f"unexpected master size {png_master.size}, expected (283, 283)")

    # favicon-48.png — faithful downscale, keyline and transparency preserved.
    png_master.resize((48, 48), Image.LANCZOS).save(out / "favicon-48.png", optimize=True)

    # apple-touch-icon.png — opaque plate + ~78% glyph box, per Apple's margin guidance.
    glyph = png_master.resize((140, 140), Image.LANCZOS)
    touch = Image.new("RGBA", (180, 180), PLATE)
    touch.alpha_composite(glyph, (20, 20))
    touch.convert("RGB").save(out / "apple-touch-icon.png", optimize=True)

    # favicon.svg — lift the path straight out of the client's vector master.
    svg_src = (masters / "kotiva-icon-glyph-2026-07-17.svg").read_text(encoding="utf-8")
    start = svg_src.index(' d="') + 4
    path_d = svg_src[start:svg_src.index('"', start)]
    # newline="" keeps LF on Windows too — otherwise the worktree drifts from the
    # committed (LF-normalised) blob and every md5 verification reads as a mismatch.
    with open(out / "favicon.svg", "w", encoding="utf-8", newline="") as fh:
        fh.write(SVG_TEMPLATE.format(path=path_d))

    for name in ("favicon.svg", "favicon-48.png", "apple-touch-icon.png"):
        print(f"wrote assets/{name} ({(out / name).stat().st_size} bytes)")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        raise SystemExit(__doc__)
    main(pathlib.Path(sys.argv[1]))
