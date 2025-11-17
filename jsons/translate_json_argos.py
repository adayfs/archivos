import json
import sys
import argparse
import re
from pathlib import Path

import argostranslate.translate as atranslate

# ---------- TRADUCCIÓN DE TEXTO (OPCIÓN A) ----------

def translate_text(text: str, source="en", target="es") -> str:
    """
    Traduce una cadena usando Argos Translate, preservando las etiquetas 5etools
    del tipo {@...} o {#...} (Opción A: solo texto visible).
    Si algo falla, devuelve el texto original.
    """
    if not isinstance(text, str) or not text.strip():
        return text

    # Proteger tags {@...} o {#...} para que el traductor no los toque
    tag_pattern = r"\{[@#][^}]+\}"
    tags = re.findall(tag_pattern, text)
    placeholders = {}

    tmp = text
    for i, tag in enumerate(tags):
        key = f"[[TAG{i}]]"
        placeholders[key] = tag
        tmp = tmp.replace(tag, key)

    try:
        # Usamos directamente la función de ejemplo del README
        translated = atranslate.translate(tmp, source, target)
    except Exception as e:
        print("⚠️ Error traduciendo texto, se deja el original.")
        print("   Texto:", text[:80], "...")
        print("   Excepción:", e)
        translated = text

    # Restaurar los tags
    for key, tag in placeholders.items():
        translated = translated.replace(key, tag)

    return translated


# ---------- LÓGICA DE TRADUCCIÓN JSON (OPCIÓN A) ----------

def translate_entries_list(entries, source="en", target="es"):
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
    Opción A:
    - name -> name_es
    - entries -> entries_es
    - NO tocar id, className, source, etc.
    """
    if isinstance(obj, dict):
        new_obj = {}
        # Copiamos todo recursivamente
        for k, v in obj.items():
            new_obj[k] = translate_json(v, source, target)

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
        # ints, floats, strings sueltas, etc.
        return obj


# ---------- MAIN ----------

def main():
    parser = argparse.ArgumentParser(
        description="Traduce un JSON de 5eTools al español siguiendo la Opción A (Argos)."
    )
    parser.add_argument("filename", help="Archivo JSON a traducir, p.ej. dnd-spells.json")
    parser.add_argument("--source-lang", default="en")
    parser.add_argument("--target-lang", default="es")
    args = parser.parse_args()

    in_path = Path(args.filename)
    if not in_path.exists():
        print(f"ERROR: No se encuentra el archivo {in_path}")
        sys.exit(1)

    print(f"Cargando {in_path}...")
    with in_path.open("r", encoding="utf-8") as f:
        data = json.load(f)

    print("Traduciendo... (puede tardar según el tamaño del JSON y del modelo)")
    translated = translate_json(data, args.source_lang, args.target_lang)

    out_path = in_path.with_name(in_path.stem + f"-{args.target_lang}.json")
    with out_path.open("w", encoding="utf-8") as f:
        json.dump(translated, f, ensure_ascii=False, indent=2)

    print("✅ Traducción terminada.")
    print(f"Archivo generado: {out_path}")

if __name__ == "__main__":
    main()
