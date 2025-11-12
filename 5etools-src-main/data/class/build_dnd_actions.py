#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Genera un JSON resumido con las acciones estándar."""

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
    src_path = BASE_DIR / "actions.json"
    out_path = BASE_DIR / "dnd-actions.json"

    if not src_path.exists():
        raise SystemExit(f"No se encontró {src_path}")

    with src_path.open("r", encoding="utf-8") as fh:
        data = json.load(fh)

    actions = []
    for item in data.get("action", []):
        name = item.get("name")
        source = item.get("source")
        if not name:
            continue

        action_id = slugify(f"{name}-{source}")

        actions.append({
            "id": action_id,
            "name": name,
            "source": source,
            "group": item.get("group"),
            "page": item.get("page"),
            "time": item.get("time"),
            "entries": item.get("entries") or item.get("entries_en") or [],
        })

    with out_path.open("w", encoding="utf-8") as fh:
        json.dump({"actions": actions}, fh, ensure_ascii=False, indent=2)

    print(f"Generado {out_path} con {len(actions)} acciones")


if __name__ == "__main__":
    main()
