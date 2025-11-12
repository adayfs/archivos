#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import re
from pathlib import Path

# Traducciones EN -> ES para nombres de armas
WEAPON_NAME_TRANSLATIONS = {
    "Battleaxe": "Hacha de batalla",
    "Club": "Garrote",
    "Dagger": "Daga",
    "Greatclub": "Gran garrote",
    "Handaxe": "Hacha de mano",
    "Javelin": "Jabalina",
    "Light Hammer": "Martillo ligero",
    "Mace": "Maza",
    "Quarterstaff": "Bastón",
    "Sickle": "Hoz",
    "Spear": "Lanza",
    "Crossbow, light": "Ballesta ligera",
    "Dart": "Dardo",
    "Shortbow": "Arco corto",
    "Sling": "Honda",
    "Greatsword": "Espadón",
    "Longsword": "Espada larga",
    "Shortsword": "Espada corta",
    "Rapier": "Estoque",
    "Scimitar": "Cimitarra",
    "Glaive": "Guja",
    "Greataxe": "Gran hacha",
    "Halberd": "Alabarda",
    "Lance": "Lanza de caballería",
    "Maul": "Mazo pesado",
    "Morningstar": "Mangual",
    "Pike": "Pica",
    "Warhammer": "Martillo de guerra",
    "Whip": "Látigo",
    # añade aquí las que quieras afinar
}

# Traducciones EN -> ES para nombres de armaduras/escudos
ARMOR_NAME_TRANSLATIONS = {
    "Padded": "Acolchada",
    "Leather": "Cuero",
    "Studded Leather": "Cuero tachonado",
    "Hide": "Cuero endurecido",
    "Chain Shirt": "Camisote de mallas",
    "Scale Mail": "Cota de escamas",
    "Breastplate": "Coraza",
    "Half Plate": "Semiplacas",
    "Ring Mail": "Armadura de anillas",
    "Chain Mail": "Cota de mallas",
    "Splint": "Armadura segmentada",
    "Plate Armor": "Armadura completa",
    "Shield": "Escudo",
    # y las que quieras añadir
}


def slugify(text: str) -> str:
    text = text.lower()
    text = text.replace("’", "").replace("'", "")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def translate_weapon(name_en: str) -> str:
    return WEAPON_NAME_TRANSLATIONS.get(name_en, name_en)


def translate_armor(name_en: str) -> str:
    return ARMOR_NAME_TRANSLATIONS.get(name_en, name_en)


def score_item(item: dict) -> int:
    """Puntuación para decidir qué versión conservar (PHB/XPHB/SRD…)."""
    s = 0
    src = item.get("source", "")

    if src == "PHB":
        s += 5
    elif src == "XPHB":
        s += 4
    else:
        s += 1

    if item.get("srd") or item.get("srd52"):
        s += 3
    if item.get("basicRules") or item.get("basicRules2024"):
        s += 2

    return s


def main():
    base_dir = Path(__file__).resolve().parent
    src_path = base_dir / "items-base.json"
    out_weapons = base_dir / "dnd-weapons.json"
    out_armors = base_dir / "dnd-armors.json"

    if not src_path.is_file():
        raise SystemExit(f"No se encuentra items-base.json en {base_dir}")

    with src_path.open("r", encoding="utf-8") as f:
        src = json.load(f)

    baseitems = src.get("baseitem", [])

    weapons_by_name = {}
    armors_by_name = {}

    for it in baseitems:
        name_en = (it.get("name") or "").strip()
        if not name_en:
            continue

        # --- ARMAS ---
        if it.get("weapon"):
            key = name_en
            existing = weapons_by_name.get(key)
            if existing and score_item(existing["_raw"]) >= score_item(it):
                # Ya tenemos una versión mejor de esta arma
                pass
            else:
                source = (it.get("source") or "").strip()
                wid = slugify(f"{name_en}-{source}") if source else slugify(name_en)

                obj = {
                    "id": wid,
                    "name": {
                        "en": name_en,
                        "es": translate_weapon(name_en),
                    },
                    "source": source,
                    "type": it.get("type"),            # M/R etc
                    "category": it.get("weaponCategory"),
                    "range": it.get("range"),
                    "dmg1": it.get("dmg1"),
                    "dmg2": it.get("dmg2"),
                    "dmgType": it.get("dmgType"),
                    "ammoType": it.get("ammoType"),
                    "properties": it.get("property", []),
                    "weight": it.get("weight"),
                    "value": it.get("value"),
                }
                if "entries" in it:
                    obj["entries_en"] = it["entries"]

                obj["_raw"] = it
                weapons_by_name[key] = obj

        # --- ARMADURAS ---
        if it.get("armor"):
            key = name_en
            existing = armors_by_name.get(key)
            if existing and score_item(existing["_raw"]) >= score_item(it):
                # Ya tenemos una versión mejor de esta armadura
                pass
            else:
                source = (it.get("source") or "").strip()
                aid = slugify(f"{name_en}-{source}") if source else slugify(name_en)

                obj = {
                    "id": aid,
                    "name": {
                        "en": name_en,
                        "es": translate_armor(name_en),
                    },
                    "source": source,
                    "type": it.get("type"),   # LA/MA/HA/SH etc
                    "ac": it.get("ac"),
                    "strength": it.get("strength"),
                    "stealthDisadvantage": bool(it.get("stealth")),
                    "weight": it.get("weight"),
                    "value": it.get("value"),
                }
                if "entries" in it:
                    obj["entries_en"] = it["entries"]

                obj["_raw"] = it
                armors_by_name[key] = obj

    # Limpiamos _raw y ordenamos por nombre ES
    weapons = []
    for w in weapons_by_name.values():
        w.pop("_raw", None)
        weapons.append(w)

    armors = []
    for a in armors_by_name.values():
        a.pop("_raw", None)
        armors.append(a)

    weapons.sort(key=lambda x: x["name"]["es"].lower())
    armors.sort(key=lambda x: x["name"]["es"].lower())

    with out_weapons.open("w", encoding="utf-8") as f:
        json.dump({"weapons": weapons}, f, ensure_ascii=False, indent=2)

    with out_armors.open("w", encoding="utf-8") as f:
        json.dump({"armors": armors}, f, ensure_ascii=False, indent=2)

    print(
        f"Generados {out_weapons.name} ({len(weapons)} armas) "
        f"y {out_armors.name} ({len(armors)} armaduras)."
    )


if __name__ == "__main__":
    main()
