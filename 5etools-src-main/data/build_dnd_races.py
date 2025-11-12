#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import re
from pathlib import Path

# ---------------------------------------------------------
# 1) Diccionario de traducciones EN -> ES para nombres de raza
#    (puedes ampliarlo todo lo que quieras)
# ---------------------------------------------------------

RACE_NAME_TRANSLATIONS = {
    "Aarakocra": "Aarakocra",
    "Aasimar": "Aasimar",
    "Bugbear": "Bugbear",
    "Centaur": "Centauro",
    "Changeling": "Cambiapieles",
    "Dragonborn": "Dracónido",
    "Dragonborn (Chromatic)": "Dracónido (Cromático)",
    "Dragonborn (Metallic)": "Dracónido (Metálico)",
    "Dragonborn (Gem)": "Dracónido (Gema)",
    "Dwarf": "Enano",
    "Hill Dwarf": "Enano de las Colinas",
    "Mountain Dwarf": "Enano de las Montañas",
    "Duergar": "Duergar",
    "Elf": "Elfo",
    "High Elf": "Alto Elfo",
    "Wood Elf": "Elfo de los Bosques",
    "Drow": "Elfo Oscuro",
    "Eladrin": "Eladrín",
    "Gnome": "Gnomo",
    "Forest Gnome": "Gnomo de los Bosques",
    "Rock Gnome": "Gnomo de las Rocas",
    "Goliath": "Goliat",
    "Goblin": "Goblin",
    "Half-Elf": "Semielfo",
    "Half-Orc": "Semiorco",
    "Halfling": "Mediano",
    "Lightfoot Halfling": "Mediano Piesligeros",
    "Stout Halfling": "Mediano Firme",
    "Harengon": "Harengón",
    "Hobgoblin": "Hobgoblin",
    "Human": "Humano",
    "Kalashtar": "Kalashtar",
    "Kenku": "Kenku",
    "Kobold": "Kobold",
    "Leonin": "Leonin",
    "Lizardfolk": "Hombre Lagarto",
    "Loxodon": "Loxodon",
    "Minotaur": "Minotauro",
    "Orc": "Orco",
    "Tabaxi": "Tabaxi",
    "Tiefling": "Tiflin",
    "Fairy": "Hada",
    "Satyr": "Sátiro",
    "Warforged": "Forjado Bélico",
    "Tortle": "Tortle",
    "Yuan-ti Pureblood": "Yuan-ti Purasangre",
    # ...añade aquí todas las que quieras ir afinando
}


def slugify(text: str) -> str:
    """
    Convierte un texto en un slug simple:
    - minúsculas
    - espacios y signos -> guiones
    - solo a-z0-9 y guiones
    """
    text = text.lower()
    text = re.sub(r"['’]", "", text)          # quita apóstrofes
    text = re.sub(r"[^a-z0-9]+", "-", text)   # no alfanumérico -> -
    text = text.strip("-")
    return text


def translate_race_name(name_en: str) -> str:
    """
    Devuelve la traducción al español si está en el diccionario.
    Si no, devuelve el propio nombre en inglés para que lo puedas
    corregir a mano en el JSON resultante.
    """
    return RACE_NAME_TRANSLATIONS.get(name_en, name_en)


def main():
    base_dir = Path(__file__).resolve().parent
    src_path = base_dir / "races.json"        # JSON original de 5etools
    out_path = base_dir / "dnd-races.json"    # Nuestro JSON agregado

    if not src_path.is_file():
        raise SystemExit(f"No se encuentra races.json en {base_dir}")

    with src_path.open("r", encoding="utf-8") as f:
        src_data = json.load(f)

    races_src = src_data.get("race", [])
    out_races = []

    for rc in races_src:
        name_en = (rc.get("name") or "").strip()
        if not name_en:
            continue

        source = (rc.get("source") or "").strip()
        edition = (rc.get("edition") or "").strip()

        # ID: similar a las clases -> nombre + fuente (+ edición si existe)
        parts_for_id = [name_en, source]
        if edition:
            parts_for_id.append(edition)
        race_id = slugify("-".join(parts_for_id))

        name_es = translate_race_name(name_en)

        race_obj = {
            "id": race_id,
            "name": {
                "en": name_en,
                "es": name_es,
            },
            "source": source,
        }

        if edition:
            race_obj["edition"] = edition

        # Campos básicos que pueden ser útiles en la hoja
        for key in [
            "size",
            "speed",
            "ability",
            "darkvision",
            "traitTags",
            "creatureTypes",
            "creatureTypeTags",
            "lineage",
            "heightAndWeight",
            "skillProficiencies",
            "feats",
            "languageProficiencies",
        ]:
            if key in rc:
                race_obj[key] = rc[key]

        # Guardamos las entradas originales como "entries_en"
        # (para una futura ficha ampliada, descripción, etc.)
        if "entries" in rc:
            race_obj["entries_en"] = rc["entries"]

        # Si quieres conservar el objeto bruto entero por si acaso:
        # race_obj["raw"] = rc

        out_races.append(race_obj)

    out_data = {"races": out_races}

    with out_path.open("w", encoding="utf-8") as f:
        json.dump(out_data, f, ensure_ascii=False, indent=2)

    print(f"Generado {out_path.name} con {len(out_races)} razas.")


if __name__ == "__main__":
    main()
