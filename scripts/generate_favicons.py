"""
Génère le favicon et les icônes du site Zeminal à partir du glyphe épée (⚔),
dans le même indigo que la navbar.
Usage : python3 scripts/generate_favicons.py
"""

import os
from PIL import Image, ImageDraw, ImageFont

FONT_PATH = "C:/Windows/Fonts/seguisym.ttf"
OUT_DIR = os.path.join(os.path.dirname(__file__), "..", "public")

INDIGO_400 = (129, 140, 248, 255)
GRAY_950 = (3, 7, 18, 255)


def render(size, bg=None, fg=INDIGO_400, pad_ratio=0.78):
    img = Image.new("RGBA", (size, size), bg if bg else (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    f = ImageFont.truetype(FONT_PATH, int(size * pad_ratio))
    bbox = draw.textbbox((0, 0), "⚔", font=f)
    w, h = bbox[2] - bbox[0], bbox[3] - bbox[1]
    x = (size - w) / 2 - bbox[0]
    y = (size - h) / 2 - bbox[1]
    draw.text((x, y), "⚔", font=f, fill=fg)
    return img


if __name__ == "__main__":
    sizes_ico = [16, 32, 48]
    imgs = [render(s) for s in sizes_ico]
    imgs[-1].save(os.path.join(OUT_DIR, "favicon.ico"), sizes=[(s, s) for s in sizes_ico])

    render(32).save(os.path.join(OUT_DIR, "favicon-32x32.png"))
    render(16).save(os.path.join(OUT_DIR, "favicon-16x16.png"))

    # fond opaque requis par iOS (pas de transparence sur apple-touch-icon)
    render(180, bg=GRAY_950, pad_ratio=0.55).save(os.path.join(OUT_DIR, "apple-touch-icon.png"))

    print("Favicons générés dans", OUT_DIR)
