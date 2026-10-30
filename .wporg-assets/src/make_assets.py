"""Draws the wordpress.org banner and icon for CS2FS Converter.

Run: python3 make_assets.py  (writes into ../)
Everything is drawn at 4x and downscaled for clean edges.
"""
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont, ImageFilter

HERE = Path(__file__).parent
OUT = HERE.parent
S = 4  # supersampling

BG_TOP = (17, 24, 39)
BG_BOT = (30, 41, 70)
CARD = (39, 52, 84)
CARD_EDGE = (62, 78, 120)
TEXT = (241, 245, 249)
MUTED = (148, 163, 184)
ORANGE = (251, 146, 60)   # "from" accent
TEAL = (45, 212, 191)     # "to" accent
CODE = [(129, 140, 248), (244, 114, 182), (125, 211, 252), (148, 163, 184)]


def font(name, size, weight):
    f = ImageFont.truetype(str(HERE / "fonts" / name), size)
    f.set_variation_by_axes([weight] if name.startswith("JetBrains") else [14 if size < 60 * S else 32, weight])
    return f


def gradient(w, h, top, bot):
    img = Image.new("RGB", (w, h), top)
    d = ImageDraw.Draw(img)
    for y in range(h):
        t = y / max(h - 1, 1)
        d.line([(0, y), (w, y)], fill=tuple(round(a + (b - a) * t) for a, b in zip(top, bot)))
    return img


def card(d, x, y, w, h, accent, rows, r):
    """A snippet card: accent strip on top, coloured code bars below."""
    d.rounded_rectangle([x, y, x + w, y + h], r, fill=CARD, outline=CARD_EDGE, width=max(1, S))
    d.rounded_rectangle([x, y, x + w, y + r * 1.6], r, fill=accent)
    d.rectangle([x, y + r * 0.8, x + w, y + r * 1.6], fill=accent)
    bar = h * 0.075
    gap = (h - r * 1.6 - bar * len(rows)) / (len(rows) + 1)
    cy = y + r * 1.6 + gap
    for indent, length, color in rows:
        bx = x + w * 0.1 + indent * w * 0.08
        d.rounded_rectangle([bx, cy, bx + w * length, cy + bar], bar / 2, fill=color)
        cy += bar + gap


def arrow(d, x1, x2, y, thick, color):
    head = thick * 2.6
    d.rounded_rectangle([x1, y - thick / 2, x2 - head * 0.6, y + thick / 2], thick / 2, fill=color)
    d.polygon([(x2 - head, y - head * 0.8), (x2, y), (x2 - head, y + head * 0.8)], fill=color)


ROWS_A = [(0, 0.45, CODE[0]), (1, 0.55, CODE[1]), (1, 0.38, CODE[2]), (0, 0.25, CODE[3])]
ROWS_B = [(0, 0.5, CODE[2]), (1, 0.4, CODE[0]), (1, 0.52, CODE[1]), (0, 0.22, CODE[3])]


def banner(w, h):
    W, H = w * S, h * S
    img = gradient(W, H, BG_TOP, BG_BOT)
    d = ImageDraw.Draw(img)
    u = H / 250  # layout unit relative to the 772x250 size

    # Text block, left
    x = 44 * u
    d.text((x, 62 * u), "CS2FS Converter", font=font("Inter.ttf", int(44 * u), 750), fill=TEXT)
    sub = font("Inter.ttf", int(19 * u), 500)
    d.text((x, 124 * u), "Code Snippets", font=sub, fill=ORANGE)
    cw = d.textlength("Code Snippets", font=sub)
    d.text((x + cw + 10 * u, 124 * u), "to", font=sub, fill=MUTED)
    tw = d.textlength("to", font=sub)
    d.text((x + cw + tw + 20 * u, 124 * u), "Fluent Snippets", font=sub, fill=TEAL)
    d.text((x, 160 * u), "Import your snippets in one click.", font=font("Inter.ttf", int(16 * u), 400), fill=MUTED)

    # Cards, right: a stack on each side with an arrow between
    cw_, ch = 104 * u, 120 * u
    r = 9 * u
    ax = 470 * u
    for i, off in enumerate((2, 1, 0)):
        o = off * 9 * u
        card(d, ax + o, 52 * u + o, cw_, ch, ORANGE, ROWS_A if i == 2 else ROWS_B, r)
    bx = ax + cw_ + 76 * u
    for i, off in enumerate((2, 1, 0)):
        o = off * 9 * u
        card(d, bx + o, 52 * u + o, cw_, ch, TEAL, ROWS_B if i == 2 else ROWS_A, r)
    arrow(d, ax + cw_ + 28 * u, bx - 6 * u, 52 * u + 18 * u + ch / 2, 7 * u, TEXT)

    return img.resize((w, h), Image.LANCZOS)


def icon(size):
    N = size * S
    img = gradient(N, N, BG_TOP, BG_BOT)
    d = ImageDraw.Draw(img)
    u = N / 256
    cw_, ch, r = 92 * u, 112 * u, 10 * u
    card(d, 30 * u, 52 * u, cw_, ch, ORANGE, ROWS_A, r)
    card(d, 134 * u, 92 * u, cw_, ch, TEAL, ROWS_B, r)
    # arrow arcing over from the first card to the second
    d.arc([76 * u, 18 * u, 196 * u, 128 * u], 200, 340, fill=TEXT, width=int(9 * u))
    hx, hy = 190 * u, 66 * u
    d.polygon([(hx - 20 * u, hy - 6 * u), (hx + 16 * u, hy - 4 * u), (hx, hy + 26 * u)], fill=TEXT)
    return img.resize((size, size), Image.LANCZOS)


if __name__ == "__main__":
    banner(772, 250).save(OUT / "banner-772x250.png", optimize=True)
    banner(1544, 500).save(OUT / "banner-1544x500.png", optimize=True)
    icon(128).save(OUT / "icon-128x128.png", optimize=True)
    icon(256).save(OUT / "icon-256x256.png", optimize=True)
    print("ok")
