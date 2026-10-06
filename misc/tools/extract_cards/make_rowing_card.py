"""Builds img/rowing_card.jpg, the Rowing card.

There is no printed Rowing card, so this one follows the layout of the grid-based starting cards
(measured off them): cost icon top left, the 3x3 maneuver grid, a small caps label under it, an
icon and a line of text, and a deck band at the bottom - tan here, as rowing belongs to no deck.
The parchment comes from the Ship-of-the-Line "forward" card (sprite slot 5) and the maneuver
diagram from the rulebook (img_sources/rowing.png). It is its own image rather than a sprite slot
so playable_cards.jpg can still be regenerated from the PDFs.

Run from the repo root: uv run --with pillow --with numpy misc/tools/extract_cards/make_rowing_card.py
"""

import numpy as np
from PIL import Image, ImageDraw, ImageFilter, ImageFont

SPRITE = "img/playable_cards.jpg"
OUT = "img/rowing_card.jpg"
W, H, COLS = 382, 528, 6  # sprite cell size
TEMPLATE_SLOT = 5

# Measured off the printed cards (and the rulebook diagram): (left, top, right, bottom).
RULEBOOK_GRID = (303, 68, 477, 242)
CARD_GRID = (125, 37, 333, 245)
COST_ICON = (38, 37, 86, 87)
LABEL_XY = (125, 258)
SMALL_ICON = (122, 281, 151, 310)
TEXT_X, TEXT_Y, TEXT_RIGHT, LINE = 164, 279, 362, 19
BAND_TOP = (428, 402)  # the band's top edge at the left and right of the card
BAND_ICON = (42, 412, 124, 486)  # where the deck's ship sits
TEMPLATE_DECK_MARK = (0, 380, 356, 472)  # the template's ship and deck name, kept out of the spray
CLEAN_BAND = (285, 375)  # rows of the template card with nothing but parchment

BROWN = (88, 66, 38)  # cost icons
TAN = (172, 157, 120)  # label and the small icon
TEXT = (98, 90, 78)
BAND = (228, 214, 186)
BAND_TEXT = (128, 104, 66)
FONTS = "/System/Library/Fonts/Supplemental/"
CONDENSED = "/System/Library/Fonts/Avenir Next Condensed.ttc"


def cell(slot):
    return ((slot % COLS) * W, (slot // COLS) * H)


def parchment(card):
    """A whole card of plain parchment, mirror-tiled from the template's empty band."""
    # Only the left half: the deck band's spray reaches up into the right of it.
    half = card.crop((0, CLEAN_BAND[0], W // 2, CLEAN_BAND[1]))
    band = Image.new("RGB", (W, half.height))
    band.paste(half, (0, 0))
    band.paste(half.transpose(Image.FLIP_LEFT_RIGHT), (W // 2, 0))
    flipped = band.transpose(Image.FLIP_TOP_BOTTOM)
    out = Image.new("RGB", (W, H))
    for i, y in enumerate(range(0, H, band.height)):
        out.paste(band if i % 2 == 0 else flipped, (0, y))
    return out


def multiply(card, overlay, xy):
    """Darken `card` by `overlay` (white = no change), keeping the parchment's grain."""
    x, y = xy
    region = np.asarray(card.crop((x, y, x + overlay.width, y + overlay.height))).astype(float)
    ratio = np.asarray(overlay.convert("RGB")).astype(float) / 255
    card.paste(Image.fromarray((region * ratio).astype(np.uint8)), (x, y))


def cards_icon(box, color):
    """Two fanned cards, rowing's cost. Drawn large and scaled down for smooth edges; white is
    the parchment gap between the two, as the printed icons separate overlapping shapes."""
    w, h = box[2] - box[0], box[3] - box[1]
    s = 8
    icon = Image.new("RGB", (w * s, h * s), (255, 255, 255))
    card_w, card_h = 0.56 * w * s, 0.78 * h * s
    gap = 0.06 * w * s
    for angle, dx, outline in ((12, 0.4, 0), (-10, 0.06, gap)):
        layer = Image.new("L", icon.size, 0)
        d = ImageDraw.Draw(layer)
        x0, y0 = dx * w * s, 0.12 * h * s
        d.rounded_rectangle((x0, y0, x0 + card_w, y0 + card_h), radius=card_w * 0.14, fill=255)
        mask = layer.rotate(angle, resample=Image.BICUBIC, center=(x0 + card_w / 2, y0 + card_h))
        if outline:
            ring = Image.new("L", icon.size, 0)
            ImageDraw.Draw(ring).rounded_rectangle(
                (x0 - outline, y0 - outline, x0 + card_w + outline, y0 + card_h + outline),
                radius=card_w * 0.2, fill=255)
            icon.paste((255, 255, 255), mask=ring.rotate(angle, resample=Image.BICUBIC, center=(x0 + card_w / 2, y0 + card_h)))
        icon.paste(color, mask=mask)
    return icon.resize((w, h), Image.LANCZOS)


def spray(template):
    """The template band's ink spray, recoloured from its pink to our tan."""
    t = np.asarray(template).astype(float)
    # How much darker than the parchment or band around it each pixel is. Brightness, not hue:
    # the JPEG keeps colour at half resolution, which smears the dots into blobs.
    gray = template.convert("L")
    luma = np.asarray(gray).astype(float)
    surroundings = np.asarray(gray.filter(ImageFilter.MedianFilter(15))).astype(float)
    strength = np.clip((surroundings - luma - 8) / 70, 0, 1)
    x0, y0, x1, y1 = TEMPLATE_DECK_MARK
    strength[y0:y1, x0:x1] = 0
    strength[: CLEAN_BAND[0]] = 0
    dot = np.array(BAND_TEXT) / 255
    overlay = 1 - strength[..., None] * (1 - dot)
    return Image.fromarray((overlay * 255).astype(np.uint8))


def oars_icon(box, color):
    """Two crossed oars, where the starting cards show their deck's ship."""
    w, h = box[2] - box[0], box[3] - box[1]
    s = 8
    icon = Image.new("RGB", (w * s, h * s), (255, 255, 255))
    for flip in (False, True):
        layer = Image.new("L", (w * s, h * s), 0)
        d = ImageDraw.Draw(layer)
        cx = w * s / 2
        # One oar standing up: shaft, grip, and the blade at the bottom.
        d.rounded_rectangle((cx - 0.035 * w * s, 0.02 * h * s, cx + 0.035 * w * s, 0.7 * h * s), radius=0.03 * w * s, fill=255)
        d.rounded_rectangle((cx - 0.06 * w * s, 0.0, cx + 0.06 * w * s, 0.12 * h * s), radius=0.04 * w * s, fill=255)
        d.ellipse((cx - 0.075 * w * s, 0.52 * h * s, cx + 0.075 * w * s, 1.0 * h * s), fill=255)
        layer = layer.rotate(-38 if flip else 38, resample=Image.BICUBIC)
        icon.paste(color, mask=layer)
    return icon.resize((w, h), Image.LANCZOS)


def wrap(draw, text, font, width):
    lines, line = [], ""
    for word in text.split():
        trial = (line + " " + word).strip()
        if draw.textlength(trial, font=font) > width and line:
            lines.append(line)
            line = word
        else:
            line = trial
    return lines + [line]


def main():
    sprite = Image.open(SPRITE).convert("RGB")
    x0, y0 = cell(TEMPLATE_SLOT)
    template = sprite.crop((x0, y0, x0 + W, y0 + H))
    card = parchment(template)

    # The rulebook diagram, scaled onto the card's grid, its own background turned white so only
    # the lines and ships darken the card.
    pad = 3
    diagram = Image.open("img_sources/rowing.png").convert("RGB").crop(
        (RULEBOOK_GRID[0] - pad, RULEBOOK_GRID[1] - pad, RULEBOOK_GRID[2] + pad, RULEBOOK_GRID[3] + pad)
    )
    scale = (CARD_GRID[2] - CARD_GRID[0]) / (RULEBOOK_GRID[2] - RULEBOOK_GRID[0])
    diagram = diagram.resize((round(diagram.width * scale), round(diagram.height * scale)), Image.LANCZOS)
    d = np.asarray(diagram).astype(float)
    ratio = np.clip(d / np.median(d.reshape(-1, 3), axis=0), 0, 1)
    ratio[ratio.min(axis=2) > 0.9] = 1  # the rulebook page's own stains and grain
    whitened = (ratio * 255).astype(np.uint8)
    multiply(card, Image.fromarray(whitened), (CARD_GRID[0] - round(pad * scale), CARD_GRID[1] - round(pad * scale)))

    multiply(card, cards_icon(COST_ICON, BROWN), COST_ICON[:2])
    multiply(card, cards_icon(SMALL_ICON, TAN), SMALL_ICON[:2])

    draw = ImageDraw.Draw(card)
    label = ImageFont.truetype(CONDENSED, 13, index=0)
    x = LABEL_XY[0]
    for ch in "ROWING":  # letter-spaced, like the printed labels
        draw.text((x, LABEL_XY[1]), ch, font=label, fill=TAN)
        x += draw.textlength(ch, font=label) + 1.6

    body = ImageFont.truetype(FONTS + "Georgia.ttf", 15)
    italic = ImageFont.truetype(FONTS + "Georgia Italic.ttf", 15)
    y = TEXT_Y
    for text, font in (("Discard 2 cards instead of playing one.", body),
                       ("Only way to sail backwards.", italic)):
        for line in wrap(draw, text, font, TEXT_RIGHT - TEXT_X):
            draw.text((TEXT_X, y), line, font=font, fill=TEXT)
            y += LINE

    # The deck band: a tan wash under the slanted top edge.
    band = Image.new("RGB", (W, H), (255, 255, 255))
    tint = tuple(round(255 * c / p) for c, p in zip(BAND, (247, 245, 236)))
    ImageDraw.Draw(band).polygon([(0, BAND_TOP[0]), (W, BAND_TOP[1]), (W, H), (0, H)], fill=tint)
    multiply(card, band, (0, 0))
    multiply(card, spray(template), (0, 0))
    multiply(card, oars_icon(BAND_ICON, BAND_TEXT), BAND_ICON[:2])
    draw.text((138, 452), "Rowing", font=ImageFont.truetype(FONTS + "Georgia.ttf", 19), fill=BAND_TEXT, anchor="lm")

    card.save(OUT, quality=85)


if __name__ == "__main__":
    main()
