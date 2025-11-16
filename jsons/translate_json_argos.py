import json
import sys
import argparse
import re
from pathlib import Path

from argostranslate import translate as atranslate


# ---------- INICIALIZACIÓN DEL TRADUCTOR (ARGOS) ----------

_translator_cache = {}


def get_translator(source="en", target="es"):
    """
    Devuelve un traductor Argos de source -> target.
    Requiere que el modelo de idiomas esté instalado.
    """
    global _translator_cache
    key = (source, target)
    if key in _translator_cache:
        return _translator_cache[key]

    installed_languages = atranslate.get_installed_languages()
    from_lang = next((l for l in installed_languages if l.code == source), None)
    to_lang = next((l for l in installed_languages if l.code == target), None)

    if not from_lang or not to_lang:
        print("❌ No se encontró el modelo de idiomas para", source, "->", target)
        print("   Asegúrate de haber instalado el par de idiomas con:")
        print("   python -m argostranslate.gui")
        sys.exit(1)

    translator = from_lang.get_translation(to_lang)
    _translator_cache[key] = translator
    return translator


# ---------- TRADUCCIÓN DE TEXTO (OPCIÓN A) ----------

def translate_text(text: str, source="en", target="es") -> str:
    """
    Traduce una cadena usando Argos Translate, preservando las etiquetas 5etools
    del tipo {@...} o {#...} (Opción A: solo texto visible).
    Si algo falla, devuelve el texto original.
    """
    if not isinstance(text, str) or not text.strip():
        return text

    translator = get_translator(source, target)

    # Proteger tags {@...} para que el traductor no los toque
    tag_pattern = r"\{[@#][^}]+\}"
    tags = re.findall(tag_pattern, text)
    placeholders = {}

    tmp = text
    for i, tag in enumerate(tags):
        key = f"[[TAG{i}]]"
        placeholders[key] = tag
        tmp = tmp.replace(tag, key)

    try:
        translated = translator.translate(tmp)
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
    """
    Crea una versión paralela de entries: entries_es.
    - Traduce solo strings.
    - Deja dicts y estructuras tal cual, pero recursivamente se añaden sus propios *_es.
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
    - NO toca id, className, source, etc.
    - Mantiene estructura intacta.
    """
    if isinstance(obj, dict):
        new_obj = {}
        # Primero copiamos todo recursivamente
        for k, v in obj.items():
            new_obj[k] = translate_json(v, source, target)

        # 1) name -> name_es (si es texto “visible”)
        if "name" in obj and isinstance(obj["name"], str) and "name_es" not in obj:
            new_obj["name_es"] = translate_text(obj["name"], source, target)

        # 2) entries -> entries_es
        if "entries" in obj and isinstance(obj["entries"], list) and "entries_es" not in obj:
            new_obj["entries_es"] = translate_entries_list(obj["entries"], source, target)

        return new_obj

    elif isinstance(obj, list):
        return [translate_json(x, source, target) for x in obj]

    else:
        # int, float, str (no envuelto en name/entries), etc.
        return obj


# ---------- MAIN CLI ----------

def main():
    parser = argparse.ArgumentParser(
        description="Traduce un JSON de 5eTools al español siguiendo la Opción A (Argos offline)."
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

    print("Traduciendo... (esto puede tardar según el tamaño del JSON y del modelo)")
    translated = translate_json(data, args.source_lang, args.target_lang)

    out_path = in_path.with_name(in_path.stem + f"-{args.target_lang}.json")
    with out_path.open("w", encoding="utf-8") as f:
        json.dump(translated, f, ensure_ascii=False, indent=2)

    print("✅ Traducción terminada.")
    print(f"Archivo generado: {out_path}")


if __name__ == "__main__":
    main()
