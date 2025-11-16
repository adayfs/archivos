import json
import os
import re
import sys
import argparse
from pathlib import Path

import requests

# ---------- CONFIG TRADUCCIÓN ----------

LT_URL = os.getenv("LT_URL", "https://libretranslate.com/translate")
LT_API_KEY = os.getenv("LT_API_KEY")


def translate_text(text: str, source="en", target="es") -> str:
    """
    Traduce una cadena usando LibreTranslate, preservando las etiquetas 5etools
    del tipo {@...}, {#...}, etc. (Opción A: solo texto visible).
    """
    if not isinstance(text, str) or not text.strip():
        return text

    # Proteger tags {@...} para que el traductor no los toque
    tag_pattern = r"\{[@#][^}]+\}"
    tags = re.findall(tag_pattern, text)
    placeholders = {}

    tmp = text
    for i, tag in enumerate(tags):
        key = f"[[TAG{i}]]"
        placeholders[key] = tag
        tmp = tmp.replace(tag, key)

    payload = {
        "q": tmp,
        "source": source,
        "target": target,
        "format": "text",
    }
    if LT_API_KEY:
        payload["api_key"] = LT_API_KEY

    resp = requests.post(LT_URL, data=payload, timeout=20)
    resp.raise_for_status()
    translated = resp.json()["translatedText"]

    # Restaurar los tags
    for key, tag in placeholders.items():
        translated = translated.replace(key, tag)

    return translated


# ---------- LÓGICA DE TRADUCCIÓN JSON (OPCIÓN A) ----------

def translate_entries_list(entries, source="en", target="es"):
    """
    Crea una versión paralela de entries: entries_es.
    - Traduce solo strings.
    - Deja dicts y estructuras tal cual (pero recursivamente podrán tener sus propias entries_es).
    """
    out = []
    for item in entries:
        if isinstance(item, str):
            out.append(translate_text(item, source, target))
        elif isinstance(item, dict):
            out.append(translate_json(item, source, target))
        elif isinstance(item, list):
            out.append(translate_entries_list(item, source, target))
        else:
            out.append(item)
    return out


def translate_json(obj, source="en", target="es"):
    """
    Recorre el JSON y aplica la Opción A:
    - Si encuentra "name": añade "name_es" (si no existe).
    - Si encuentra "entries": añade "entries_es" (si no existe).
    - No toca id, className, source, etc.
    - Mantiene estructura intacta.
    """
    if isinstance(obj, dict):
        new_obj = {}
        # Primero copiamos todo tal cual
        for k, v in obj.items():
            new_obj[k] = translate_json(v, source, target)

        # Reglas opción A
        # 1) name -> name_es
        if "name" in obj and isinstance(obj["name"], str) and "name_es" not in obj:
            new_obj["name_es"] = translate_text(obj["name"], source, target)

        # 2) entries -> entries_es
        if "entries" in obj and isinstance(obj["entries"], list) and "entries_es" not in obj:
            new_obj["entries_es"] = translate_entries_list(obj["entries"], source, target)

        return new_obj

    elif isinstance(obj, list):
        return [translate_json(x, source, target) for x in obj]

    else:
        # int, float, str (no name/entries), etc.
        return obj


# ---------- MAIN CLI ----------

def main():
    parser = argparse.ArgumentParser(
        description="Traduce un JSON de 5eTools al español siguiendo la Opción A."
    )
    parser.add_argument(
        "filename",
        help="Nombre del archivo JSON a traducir (por ejemplo dnd-spells.json)"
    )
    parser.add_argument(
        "--source-lang", default="en",
        help="Idioma origen (por defecto: en)"
    )
    parser.add_argument(
        "--target-lang", default="es",
        help="Idioma destino (por defecto: es)"
    )
    args = parser.parse_args()

    in_path = Path(args.filename)
    if not in_path.exists():
        print(f"ERROR: No se encuentra el archivo {in_path}")
        sys.exit(1)

    print(f"Cargando {in_path}...")
    with in_path.open("r", encoding="utf-8") as f:
        data = json.load(f)

    print("Traduciendo... (esto puede tardar según el tamaño y el servidor LibreTranslate)")
    translated = translate_json(data, args.source_lang, args.target_lang)

    out_path = in_path.with_name(in_path.stem + f"-{args.target_lang}.json")
    with out_path.open("w", encoding="utf-8") as f:
        json.dump(translated, f, ensure_ascii=False, indent=2)

    print(f"✅ Traducción terminada.")
    print(f"Archivo generado: {out_path}")


if __name__ == "__main__":
    main()
