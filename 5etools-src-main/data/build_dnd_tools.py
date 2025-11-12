#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import json
import re
from pathlib import Path

# Traducciones EN -> ES para los nombres de herramienta
# (Si falta alguna, se deja el nombre EN y luego lo puedes editar a mano en el JSON)
TOOL_NAME_TRANSLATIONS = {
    "Alchemist's Supplies": "Suministros de alquimista",
    "Brewer's Supplies": "Suministros de cervecero",
    "Calligrapher's Supplies": "Suministros de calígrafo",
    "Carpenter's Tools": "Herramientas de carpintero",
    "Cartographer's Tools": "Herramientas de cartógrafo",
    "Cobbler's Tools": "Herramientas de zapatero",
    "Cook's Utensils": "Utensilios de cocinero",
    "Glassblower's Tools": "Herramientas de soplador de vidrio",
    "Jeweler's Tools": "Herramientas de joyero",
    "Leatherworker's Tools": "Herramientas de curtidor",
    "Mason's Tools": "Herramientas de albañil",
    "Navigator's Tools": "Herramientas de navegante",
    "Painter's Supplies": "Suministros de pintor",
    "Poisoner's Kit": "Kit de venenos",
    "Potter's Tools": "Herramientas de alfarero",
    "Smith's Tools": "Herramientas de herrero",
    "Tinker's Tools": "Herramientas de hojalatero",
    "Weaver's Tools": "Herramientas de tejedor",
    "Woodcarver's Tools": "Herramientas de tallista",
    "Disguise Kit": "Kit de disfraces",
    "Forgery Kit": "Kit de falsificación",
    "Herbalism Kit": "Kit de herboristería",
    "Thieves' Tools": "Herramientas de ladrón",
    "Dice Set": "Juego de dados",
    "Dragonchess Set": "Juego de ajedrez de dragones",
    "Playing Card Set": "Baraja de cartas",
    "Playing Cards": "Baraja de cartas",
    "Three-Dragon Ante Set": "Juego de Tres Dragones",
}


def slugify(text: str) -> str:
    """Convierte un texto en slug simple (minúsculas, guiones)."""
    text = text.lower()
    text = text.replace("’", "").replace("'", "")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    text = text.strip("-")
    return text


def translate_name(name_en: str) -> str:
    """Devuelve la traducción si existe, si no, el propio nombre EN."""
    return TOOL_NAME_TRANSLATIONS.get(name_en, name_en)


def classify_category(item_type: str) -> str:
    """Clasifica un poco el tipo de herramienta para futuras lógicas."""
    if item_type.startswith("AT"):
        return "artisan_tools"   # herramientas de artesano
    if item_type.startswith("T"):
        return "tool_kits"       # kits (disguise, herbalism, thieves…)
    if item_type.startswith("GS"):
        return "game_sets"       # juegos de azar
    return "other"


def main():
    base_dir = Path(__file__).resolve().parent
    src_path = base_dir / "items.json"
    out_path = base_dir / "dnd-tools.json"

    if not src_path.is_file():
        raise SystemExit(f"No se encuentra items.json en {base_dir}")

    with src_path.open("r", encoding="utf-8") as f:
        src = json.load(f)

    items = src.get("item", [])

    # Tipos que consideramos "herramientas"
    tool_types = {"T", "T|XPHB", "AT", "AT|XPHB", "GS", "GS|XPHB"}

    # Usamos un dict por nombre EN para deduplicar (PHB/XPHB, etc.)
    by_name = {}

    def score_source(src_name: str) -> int:
        """Para decidir qué versión conservar al deduplicar."""
        if src_name == "PHB":
            return 3
        if src_name == "XPHB":
            return 2
        return 1

    for it in items:
        item_type = it.get("type", "")
        if item_type not in tool_types:
            continue

        name_en = (it.get("name") or "").strip()
        if not name_en:
            continue

        source = (it.get("source") or "").strip()
        entries = it.get("entries")
        add_entries = it.get("additionalEntries")

        # Deduplicar por nombre EN, prefiriendo PHB/XPHB
        key = name_en
        existing = by_name.get(key)
        if existing:
            old_src = existing.get("source", "")
            if score_source(source) <= score_source(old_src):
                # La versión que ya tenemos es "mejor", ignoramos esta
                continue

        tool_id = slugify(f"{name_en}-{source}") if source else slugify(name_en)

        obj = {
            "id": tool_id,
            "name": {
                "en": name_en,
                "es": translate_name(name_en),
            },
            "source": source,
            "type": item_type,
            "category": classify_category(item_type),
        }

        if entries:
            obj["entries_en"] = entries
        if add_entries:
            obj["additionalEntries_en"] = add_entries

        by_name[key] = obj

    tools = sorted(by_name.values(), key=lambda x: x["name"]["es"].lower())

    out_data = {"tools": tools}

    with out_path.open("w", encoding="utf-8") as f:
        json.dump(out_data, f, ensure_ascii=False, indent=2)

    print(f"Generado {out_path.name} con {len(tools)} herramientas.")


if __name__ == "__main__":
    main()
