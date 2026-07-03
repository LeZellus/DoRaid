"""
Génère les bannières Open Graph (1200x630) du site Zeminal.
Usage : python3 scripts/generate_og_banners.py
"""

import os
from PIL import Image, ImageDraw, ImageFont, ImageFilter

W, H = 1200, 630
FONT_DIR = "C:/Windows/Fonts"
OUT_DIR = os.path.join(os.path.dirname(__file__), "..", "public", "og")

BG = (35, 37, 32)         # gray-950 (thème Dofus)
INDIGO_600 = (5, 150, 105)   # accent = émeraude désormais
INDIGO_400 = (52, 211, 153)
INDIGO_300 = (110, 231, 183)
YELLOW_400 = (247, 208, 1)
WHITE = (240, 240, 230)
GRAY_400 = (188, 190, 176)
GRAY_500 = (163, 166, 152)


def font(name, size):
    return ImageFont.truetype(os.path.join(FONT_DIR, name), size)


def fit_font(text, max_width, base_size, font_name="segoeuib.ttf", min_size=36):
    size = base_size
    while size > min_size:
        f = font(font_name, size)
        bbox = f.getbbox(text)
        if bbox[2] - bbox[0] <= max_width:
            return f
        size -= 4
    return font(font_name, min_size)


def base_canvas():
    img = Image.new("RGB", (W, H), BG)

    glow = Image.new("RGB", (W, H), (0, 0, 0))
    gd = ImageDraw.Draw(glow)
    gd.ellipse([-320, -320, 680, 480], fill=INDIGO_600)
    glow = glow.filter(ImageFilter.GaussianBlur(170))
    img = Image.blend(img, glow, 0.4)

    # bordure subtile en bas
    draw = ImageDraw.Draw(img)
    draw.line([(0, H - 1), (W, H - 1)], fill=(31, 41, 55), width=2)
    return img


def draw_wordmark(draw, x, y):
    # accent en losange + "Zeminal"
    s = 18
    draw.polygon([(x, y - s), (x + s, y), (x, y + s), (x - s, y)], fill=YELLOW_400)
    draw.text((x + s + 18, y - 24), "Zeminal", font=font("segoeuib.ttf", 40), fill=WHITE)


def make_default_banner():
    img = base_canvas()
    draw = ImageDraw.Draw(img)

    margin = 90
    draw_wordmark(draw, margin + 18, margin + 20)

    draw.text((margin, 220), "Organisez vos raids Dofus", font=font("segoeuib.ttf", 64), fill=WHITE)
    draw.text((margin, 300), "en guilde", font=font("segoeuib.ttf", 64), fill=INDIGO_400)

    draw.text((margin, 400), "Planification de raids · Gestion de personnages · Coordination de guilde",
               font=font("segoeui.ttf", 28), fill=GRAY_400)

    draw.text((margin, 540), "Gratuit · Tous serveurs", font=font("segoeui.ttf", 24), fill=GRAY_500)

    img.save(os.path.join(OUT_DIR, "default.png"))


def make_guides_index_banner():
    img = base_canvas()
    draw = ImageDraw.Draw(img)

    margin = 90
    draw_wordmark(draw, margin + 18, margin + 20)

    draw.text((margin, 220), "Tous les guides de raid", font=font("segoeuib.ttf", 60), fill=WHITE)
    draw.text((margin, 296), "Dofus", font=font("segoeuib.ttf", 60), fill=INDIGO_400)

    draw.text((margin, 400), "Mécaniques, étages, boss et stratégies pour chaque raid de guilde",
               font=font("segoeui.ttf", 28), fill=GRAY_400)

    draw.text((margin, 540), "zeminal.tech/guides", font=font("segoeui.ttf", 24), fill=GRAY_500)

    img.save(os.path.join(OUT_DIR, "guides-index.png"))


def make_raid_banner(filename, name, tag, label="RAID DOFUS", label_color=YELLOW_400):
    img = base_canvas()
    draw = ImageDraw.Draw(img)

    margin = 90
    draw_wordmark(draw, margin + 18, margin + 20)

    draw.text((margin, 230), label, font=font("segoeuib.ttf", 26), fill=label_color)

    title_font = fit_font(name, W - 2 * margin, 76)
    draw.text((margin, 280), name, font=title_font, fill=WHITE)

    draw.text((margin, 410), tag, font=font("segoeui.ttf", 30), fill=GRAY_400)
    draw.text((margin, 540), "zeminal.tech", font=font("segoeui.ttf", 24), fill=GRAY_500)

    img.save(os.path.join(OUT_DIR, filename))


def make_guide_banner(filename, name):
    make_raid_banner(filename, name, "Mécaniques, étages, boss et stratégies sur Zeminal",
                      label="GUIDE DE RAID", label_color=INDIGO_300)


if __name__ == "__main__":
    os.makedirs(OUT_DIR, exist_ok=True)

    make_default_banner()
    make_raid_banner("raid-gouffre-du-gigalodon.png", "Gouffre du Gigalodon",
                      "Trouvez un groupe et rejoignez ce raid sur Zeminal")
    make_raid_banner("raid-sanctuaire-des-jardins-eternels.png", "Sanctuaire des Jardins éternels",
                      "Trouvez un groupe et rejoignez ce raid sur Zeminal")

    make_guide_banner("guide-gouffre-du-gigalodon.png", "Gouffre du Gigalodon")
    make_guide_banner("guide-sanctuaire-des-jardins-eternels.png", "Sanctuaire des Jardins éternels")

    make_guides_index_banner()

    print("Bannières générées dans", OUT_DIR)
