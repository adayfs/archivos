#!/usr/bin/env python3
import json
import glob
import os
import re
from collections import OrderedDict


# ---------- Utilidades básicas ----------

def slugify(text):
    """
    Convierte un nombre en un identificador simple:
    'Circle of the Moon' -> 'circle-of-the-moon'
    """
    text = text.lower()
    # quitar apóstrofes raros
    text = text.replace("’", "").replace("'", "")
    # cualquier cosa que no sea letra o número -> guion
    text = re.sub(r"[^a-z0-9]+", "-", text)
    # quitar guiones al principio/fin
    text = text.strip("-")
    return text


def make_class_id(name, source, edition):
    """
    Genera un id único para la clase, usando nombre + source + edition.
    Ej: 'Cleric', 'PHB', 'classic' -> 'cleric-phb-classic'
    """
    base = slugify(name)
    src = slugify(source) if source else "unknown"
    edt = slugify(edition) if edition else "any"
    return f"{base}-{src}-{edt}"


def make_subclass_id(class_name, subclass_short, source, edition):
    """
    Genera un id único para la subclase.
    Ej: ('Cleric', 'Life', 'PHB', 'classic') -> 'cleric-life-phb-classic'
    """
    c = slugify(class_name)
    s = slugify(subclass_short)
    src = slugify(source) if source else "unknown"
    edt = slugify(edition) if edition else "any"
    return f"{c}-{s}-{src}-{edt}"


# ---------- Procesado de archivos class-*.json ----------

def main():
    # Todos los JSON de clase del estilo class-cleric.json, class-fighter.json, etc.
    files = sorted(glob.glob("class-*.json"))
    if not files:
        print("No se han encontrado archivos class-*.json en este directorio.")
        return

    # Usamos un OrderedDict para mantener el orden de inserción de las clases
    classes_by_key = OrderedDict()

    for path in files:
        print(f"Procesando {path}...")
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        class_entries = data.get("class", [])
        subclass_entries = data.get("subclass", [])

        # 1) Registrar todas las clases de este archivo
        for c in class_entries:
            name = c.get("name")
            source = c.get("source")
            edition = c.get("edition", "")

            if not name or not source:
                continue

            key = (name, source, edition)

            if key not in classes_by_key:
                class_id = make_class_id(name, source, edition)
                classes_by_key[key] = {
                    "id": class_id,
                    "name": name,
                    "source": source,
                    "edition": edition or None,
                    "subclassTitle": c.get("subclassTitle", ""),
                    "subclasses": []
                }

        # 2) Asociar las subclases a su clase correspondiente
        for sc in subclass_entries:
            class_name = sc.get("className")
            class_source = sc.get("classSource")
            sc_name = sc.get("name")
            sc_short = sc.get("shortName") or sc_name
            sc_source = sc.get("source")
            sc_edition = sc.get("edition", "")

            if not (class_name and class_source and sc_name and sc_source):
                continue

            key = (class_name, class_source, sc_edition)

            # Si no hay coincidencia exacta por edición, probamos sin edición
            if key not in classes_by_key:
                key_alt = (class_name, class_source, "")
            else:
                key_alt = None

            target_key = key if key in classes_by_key else key_alt

            if target_key not in classes_by_key:
                # Si llegamos aquí, la clase correspondiente no se ha registrado.
                # No abortamos; simplemente avisamos y seguimos.
                print(
                    f"  [AVISO] No se ha encontrado clase para subclase "
                    f"'{sc_name}' ({class_name}, {class_source}, edition={sc_edition})"
                )
                continue

            subclass_id = make_subclass_id(
                class_name, sc_short, sc_source, sc_edition
            )

            classes_by_key[target_key]["subclasses"].append({
                "id": subclass_id,
                "name": sc_name,
                "shortName": sc_short,
                "source": sc_source,
                "edition": sc_edition or None,
                "className": class_name,
                "classSource": class_source,
            })

    # Ordenar subclases por nombre corto dentro de cada clase
    for cls in classes_by_key.values():
        cls["subclasses"].sort(key=lambda s: (s["shortName"].lower(), s["name"].lower()))

    # Lista final de clases
    classes_list = list(classes_by_key.values())
    # Ordenar clases por nombre
    classes_list.sort(key=lambda c: c["name"].lower())

    output = {
        "classes": classes_list
    }

    out_path = "dnd-classes.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    print(f"\nHe generado {out_path} con {len(classes_list)} clases.")


if __name__ == "__main__":
    main()
