#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Genera un JSON resumido de backgrounds desde 5etools."""

import json
import re
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent  # /data


def slugify(text: str) -> str:
    text = (text or "").lower()
    text = text.replace("'", "").replace("’", "")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def main():
    src_path = BASE_DIR / "backgrounds.json"
    out_path = BASE_DIR / "dnd-backgrounds.json"

    if not src_path.exists():
        raise SystemExit(f"No se encontró {src_path}")

    with src_path.open("r", encoding="utf-8") as fh:
        data = json.load(fh)

    backgrounds = []
    for raw in data.get("background", []):
        name = raw.get("name")
        source = raw.get("source")
        if not name:
            continue

        bg_id = slugify(f"{name}-{source}")

        entry = {
            "id": bg_id,
            "name": name,
            "source": source,
            "hasFluff": raw.get("hasFluff"),
            "entries": raw.get("entries") or raw.get("entries_en") or [],
            "skillProficiencies": raw.get("skillProficiencies"),
            "toolProficiencies": raw.get("toolProficiencies"),
            "languageProficiencies": raw.get("languageProficiencies"),
            "equipment": raw.get("equipment"),
            "feature": raw.get("feature"),
        }

        backgrounds.append(entry)

    backgrounds.sort(key=lambda b: b["name"].lower())

    with out_path.open("w", encoding="utf-8") as fh:
        json.dump({"backgrounds": backgrounds}, fh, ensure_ascii=False, indent=2)

    print(f"Generado {out_path} con {len(backgrounds)} backgrounds")


if __name__ == "__main__":
    main()
