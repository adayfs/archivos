#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import re
from pathlib import Path

# Traducciones EN -> ES para los nombres de idioma.
# Si falta alguno, se deja el nombre EN y luego lo puedes editar
# a mano en el JSON resultante.
LANG_NAME_TRANSLATIONS = {
    "Common": "Común",
    "Common Sign Language": "Lengua de signos común",
    "Common Trade Pidgin": "Pidgin de comercio común",
    "Dwarvish": "Enano",
    "Elvish": "Élfico",
    "Giant": "Gigante",
    "Gnomish": "Gnómico",
    "Goblin": "Goblin",
    "Halfling": "Mediano",
    "Orc": "Orco",
    "Abyssal": "Abisal",
    "Celestial": "Celestial",
    "Deep Speech": "Habla profunda",
    "Draconic": "Dragónico",
    "Infernal": "Infernal",
    "Primordial": "Primordial",
    "Sylvan": "Silvano",
    "Undercommon": "Subcomún",
    "Druidic": "Druídico",
    "Thieves' Cant": "Jerga de ladrones",
    "Aarakocra": "Aarakocra",
    "Aquan": "Acuano",
    "Auran": "Aurano",
    "Terran": "Terrano",
    "Ignan": "Ígnano",
}


def slugify(text: str) -> str:
    """Convierte un texto en un slug simple (minúsculas, guiones)."""
    text = text.lower()
    text = text.replace("’", "").replace("'", "")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def translate_name(name_en: str) -> str:
    """Devuelve la traducción al español si existe; si no, el nombre EN."""
    return LANG_NAME_TRANSLATIONS.get(name_en, name_en)


def score_source(lang: dict) -> int:
    """
    Da una "puntuación" a una entrada para decidir cuál conservar
    cuando hay duplicados por nombre (PHB/XPHB/etc.).
    """
    s = 0
    src = lang.get("source", "")

    if src == "PHB":
        s += 5
    elif src == "XPHB":
        s += 4
    else:
        s += 1

    if lang.get("srd") or lang.get("srd52"):
        s += 3
    if lang.get("basicRules") or lang.get("basicRules2024"):
        s += 2

    return s


def main():
    base_dir = Path(__file__).resolve().parent
    src_path = base_dir / "languages.json"
    out_path = base_dir / "dnd-languages.json"

    if not src_path.is_file():
        raise SystemExit(f"No se encuentra languages.json en {base_dir}")

    with src_path.open("r", encoding="utf-8") as f:
        src_data = json.load(f)

    languages = src_data.get("language", [])

    # Deduplicamos por nombre EN, eligiendo la mejor versión según score_source
    by_name = {}

    for entry in languages:
        name_en = (entry.get("name") or "").strip()
        if not name_en:
            continue

        key = name_en
        existing = by_name.get(key)
        if existing is None or score_source(entry) > score_source(existing):
            by_name[key] = entry

    out_list = []

    for name_en, lang in by_name.items():
        source = (lang.get("source") or "").strip()
        lang_id = slugify(f"{name_en}-{source}") if source else slugify(name_en)
        name_es = translate_name(name_en)

        obj = {
            "id": lang_id,
            "name": {
                "en": name_en,
                "es": name_es,
            },
            "source": source,
        }

        # Campos útiles para la hoja
        for key in ["type", "script", "typicalSpeakers", "origin"]:
            if key in lang:
                obj[key] = lang[key]

        # Algunos idiomas tienen texto descriptivo extra
        if "entries" in lang:
            obj["entries_en"] = lang["entries"]

        out_list.append(obj)

    # Ordenamos por nombre en español para que el selector quede ordenado
    out_list.sort(key=lambda x: x["name"]["es"].lower())

    out_data = {"languages": out_list}

    with out_path.open("w", encoding="utf-8") as f:
        json.dump(out_data, f, ensure_ascii=False, indent=2)

    print(f"Generado {out_path.name} con {len(out_list)} idiomas.")


if __name__ == "__main__":
    main()
