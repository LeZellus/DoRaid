"""
Génère un visuel "carte de jeu" pour annoncer les guildatons sur X/Twitter.
Style plus proche de l'univers Dofus (or, bronze, cadre orné) que les
bannières OG habituelles du site (fond indigo/gris).
Usage : python3 scripts/generate_tweet_guildatons.py
"""

import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

W, H = 1200, 675
FONT_DIR = "C:/Windows/Fonts"
ROOT = os.path.join(os.path.dirname(__file__), "..")
OUT_DIR = os.path.join(ROOT, "public", "og")
COIN_PATH = os.path.join(ROOT, "public", "uploads", "divers", "guildatons.png")

BRONZE_DARK = (20, 12, 6)
BRONZE_DARKER = (10, 6, 3)
GOLD = (250, 204, 21)        # yellow-400, cohérent avec le losange "Zeminal"
GOLD_DIM = (180, 130, 40)
AMBER = (251, 191, 36)       # amber-400, palette déjà utilisée pour les guildatons
AMBER_DEEP = (146, 64, 14)   # amber-800, pour le stroke du titre
CREAM = (255, 244, 214)
INDIGO_400 = (129, 140, 248)  # accent de marque conservé pour le wordmark
GRAY_400 = (156, 163, 175)


def font(name, size):
    return ImageFont.truetype(os.path.join(FONT_DIR, name), size)


def diamond(draw, x, y, s, fill):
    draw.polygon([(x, y - s), (x + s, y), (x, y + s), (x - s, y)], fill=fill)


def base_canvas():
    img = Image.new("RGB", (W, H), BRONZE_DARK)

    # dégradé vertical bronze -> plus sombre
    grad = Image.new("L", (1, H))
    for y in range(H):
        grad.putpixel((0, y), int(255 * (y / H)))
    grad = grad.resize((W, H))
    dark_layer = Image.new("RGB", (W, H), BRONZE_DARKER)
    img = Image.composite(dark_layer, img, grad)

    # halo doré derrière la pièce
    glow = Image.new("RGB", (W, H), (0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gd.ellipse([80, 140, 620, 620], fill=(150, 100, 20))
    glow = glow.filter(ImageFilter.GaussianBlur(120))
    img = Image.blend(img, glow, 0.55)

    return img


def draw_frame(draw):
    margin = 28
    draw.rounded_rectangle(
        [margin, margin, W - margin, H - margin],
        radius=18, outline=GOLD_DIM, width=3,
    )
    inner = margin + 10
    draw.rounded_rectangle(
        [inner, inner, W - inner, H - inner],
        radius=14, outline=(GOLD_DIM[0] // 2, GOLD_DIM[1] // 2, GOLD_DIM[2] // 2), width=1,
    )
    s = 9
    for cx, cy in [(margin, margin), (W - margin, margin), (margin, H - margin), (W - margin, H - margin)]:
        diamond(draw, cx, cy, s, GOLD)


def draw_wordmark(draw, x, y):
    s = 15
    diamond(draw, x, y, s, GOLD)
    draw.text((x + s + 16, y - 20), "Zeminal", font=font("segoeuib.ttf", 34), fill=CREAM)
    draw.text((x + s + 16 + 158, y - 12), "⚔", font=font("seguisym.ttf", 24), fill=INDIGO_400)


def draw_stroked_text(draw, xy, text, f, fill, stroke_fill, stroke_width):
    draw.text(xy, text, font=f, fill=fill, stroke_width=stroke_width, stroke_fill=stroke_fill)


def main():
    os.makedirs(OUT_DIR, exist_ok=True)
    img = base_canvas()
    draw = ImageDraw.Draw(img)
    draw_frame(draw)

    margin = 90

    # bandeau "nouveauté"
    draw.text((margin, 78), "NOUVEAU SUR ZEMINAL", font=font("segoeuib.ttf", 24), fill=GOLD)

    # pièce de guildatons, agrandie et légèrement inclinée
    coin = Image.open(COIN_PATH).convert("RGBA")
    coin = coin.resize((260, 260), Image.LANCZOS)
    coin = coin.rotate(-8, expand=True, resample=Image.BICUBIC)
    coin_x, coin_y = margin - 10, 250
    img.paste(coin, (coin_x, coin_y), coin)

    # titre + sous-titre, à droite de la pièce
    text_x = margin + 300
    title_font = font("impact.ttf", 92)
    draw_stroked_text(draw, (text_x, 210), "GUILDATONS", title_font, AMBER, AMBER_DEEP, 6)

    sub_font = font("segoeui.ttf", 30)
    lines = [
        "Suivez les guildatons de chaque personnage,",
        "recevez un rappel s'ils datent de plus de 7 jours.",
    ]
    for i, line in enumerate(lines):
        draw.text((text_x, 330 + i * 42), line, font=sub_font, fill=CREAM)

    # pied de page
    draw_wordmark(draw, margin + 15, H - 90)
    draw.text((margin, H - 60), "zeminal.tech", font=font("segoeui.ttf", 22), fill=GRAY_400)

    out_path = os.path.join(OUT_DIR, "tweet-guildatons.png")
    img.save(out_path)
    print("Visuel généré :", out_path)


if __name__ == "__main__":
    main()
