#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Genera un JSON compacto con los rasgos/rasgos de clase y subclase."""

import json
import glob
import re
from pathlib import Path
from collections import defaultdict

BASE_DIR = Path(__file__).resolve().parent


def slugify(text: str) -> str:
    text = (text or "").lower()
    text = text.replace("'", "").replace("’", "")
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")


def make_class_id(name, source, edition):
    return f"{slugify(name)}-{slugify(source or 'unknown')}-{slugify(edition or 'any')}"


def make_subclass_id(class_name, subclass_short, source, edition):
    return f"{slugify(class_name)}-{slugify(subclass_short)}-{slugify(source or 'unknown')}-{slugify(edition or 'any')}"


def normalise(value):
    if value is None:
        return ""
    return str(value).strip()


def lower(value):
    return normalise(value).lower()


class FeatureIndex:
    def __init__(self, entries, key_name):
        self.by_name = defaultdict(list)
        for feat in entries:
            name = lower(feat.get("name"))
            if not name:
                continue
            self.by_name[name].append(feat)
        self.key_name = key_name

    def resolve(self, name, **filters):
        name_key = lower(name)
        candidates = self.by_name.get(name_key, [])
        if not candidates:
            return None

        def matches(feat):
            for f_key, f_val in filters.items():
                if f_val in (None, ""):
                    continue
                feat_val = lower(feat.get(f_key))
                if feat_val != lower(f_val):
                    return False
            return True

        for feat in candidates:
            if matches(feat):
                return feat

        # Intento relajado (solo nombre)
        return candidates[0]


def parse_class_feature_ref(ref, fallback_class_name, fallback_class_source):
    parts = ref.split("|")
    parts += [""] * (5 - len(parts))
    name, class_name, class_source, level, feature_source = parts[:5]
    return {
        "name": name or "",
        "className": class_name or fallback_class_name,
        "classSource": class_source or fallback_class_source,
        "level": normalise(level),
        "source": feature_source,
    }


def parse_subclass_feature_ref(ref, cls_name, cls_source, sub_short, sub_source):
    parts = ref.split("|")
    parts += [""] * (7 - len(parts))
    name, class_name, class_source, short_name, subclass_source, level, feature_source = parts[:7]
    return {
        "name": name,
        "className": class_name or cls_name,
        "classSource": class_source or cls_source,
        "subclassShortName": short_name or sub_short,
        "subclassSource": subclass_source or sub_source,
        "level": normalise(level),
        "source": feature_source,
    }


def serialise_feature(feat):
    out = {
        "name": feat.get("name"),
        "level": feat.get("level"),
        "source": feat.get("source"),
    }
    if "entries" in feat:
        out["entries"] = feat["entries"]
    if "shortEntries" in feat:
        out["shortEntries"] = feat["shortEntries"]
    if feat.get("type"):
        out["type"] = feat.get("type")
    return out


def main():
    files = sorted(glob.glob(str(BASE_DIR / "class-*.json")))
    if not files:
        raise SystemExit("No se encontraron class-*.json")

    class_feature_entries = []
    subclass_feature_entries = []

    classes = {}
    subclasses = {}

    for path in files:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        class_feature_entries.extend(data.get("classFeature", []))
        subclass_feature_entries.extend(data.get("subclassFeature", []))

        for cls in data.get("class", []):
            name = cls.get("name")
            source = cls.get("source")
            edition = cls.get("edition")
            if not (name and source):
                continue
            class_id = make_class_id(name, source, edition)
            classes.setdefault(class_id, {"name": name, "source": source, "edition": edition, "features": []})

        for sub in data.get("subclass", []):
            class_name = sub.get("className")
            class_source = sub.get("classSource")
            short = sub.get("shortName") or sub.get("name")
            sub_source = sub.get("source")
            edition = sub.get("edition")
            if not (class_name and class_source and short and sub_source):
                continue
            subclass_id = make_subclass_id(class_name, short, sub_source, edition)
            subclasses.setdefault(subclass_id, {
                "name": sub.get("name"),
                "shortName": short,
                "className": class_name,
                "classSource": class_source,
                "source": sub_source,
                "edition": edition,
                "features": [],
            })

    class_index = FeatureIndex(class_feature_entries, "classFeature")
    subclass_index = FeatureIndex(subclass_feature_entries, "subclassFeature")

    for path in files:
        with open(path, "r", encoding="utf-8") as f:
            data = json.load(f)

        for cls in data.get("class", []):
            name = cls.get("name")
            source = cls.get("source")
            edition = cls.get("edition")
            if not (name and source):
                continue
            class_id = make_class_id(name, source, edition)
            target = classes[class_id]["features"]

            for ref in cls.get("classFeatures", []):
                ref_string = ref if isinstance(ref, str) else ref.get("classFeature")
                if not ref_string:
                    continue
                parsed = parse_class_feature_ref(ref_string, name, source)
                feat = class_index.resolve(
                    parsed["name"],
                    className=parsed["className"],
                    classSource=parsed["classSource"],
                    level=parsed["level"],
                    source=parsed["source"],
                )
                if feat:
                    target.append(serialise_feature(feat))
                else:
                    target.append({"name": parsed["name"], "level": parsed["level"], "source": parsed["source"], "entries": []})

        for sub in data.get("subclass", []):
            class_name = sub.get("className")
            class_source = sub.get("classSource")
            short = sub.get("shortName") or sub.get("name")
            sub_source = sub.get("source")
            edition = sub.get("edition")
            if not (class_name and class_source and short and sub_source):
                continue
            subclass_id = make_subclass_id(class_name, short, sub_source, edition)
            target = subclasses[subclass_id]["features"]

            for ref in sub.get("subclassFeatures", []):
                parsed = parse_subclass_feature_ref(ref, class_name, class_source, short, sub_source)
                feat = subclass_index.resolve(
                    parsed["name"],
                    className=parsed["className"],
                    classSource=parsed["classSource"],
                    subclassShortName=parsed["subclassShortName"],
                    subclassSource=parsed["subclassSource"],
                    level=parsed["level"],
                    source=parsed["source"],
                )
                if feat:
                    target.append(serialise_feature(feat))
                else:
                    target.append({"name": parsed["name"], "level": parsed["level"], "source": parsed["source"], "entries": []})

    out_path = BASE_DIR / "dnd-class-features.json"
    payload = {
        "classFeatures": {cid: entry["features"] for cid, entry in classes.items()},
        "subclassFeatures": {sid: entry["features"] for sid, entry in subclasses.items()},
    }

    with open(out_path, "w", encoding="utf-8") as f:
        json.dump(payload, f, ensure_ascii=False, indent=2)

    print(f"Generado {out_path} ({len(payload['classFeatures'])} clases, {len(payload['subclassFeatures'])} subclases)")


if __name__ == "__main__":
    main()
