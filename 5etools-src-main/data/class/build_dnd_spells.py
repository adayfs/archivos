#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""Genera un JSON compacto con los conjuros de PHB, TCE, XGE y XPHB."""

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DATA_DIR = ROOT / 'data'
SPELL_DIR = DATA_DIR / 'spells'
OUT_PATH = DATA_DIR / 'dnd-spells.json'

TARGET_SOURCES = ['PHB', 'TCE', 'XGE', 'XPHB']
FILE_MAP = {
    'PHB': 'spells-phb.json',
    'TCE': 'spells-tce.json',
    'XGE': 'spells-xge.json',
    'XPHB': 'spells-xphb.json',
}

EDITION_MAP = {
    'PHB': 'classic',
    'TCE': 'classic',
    'XGE': 'classic',
    'XPHB': 'one',
}


def slugify(text: str) -> str:
    text = (text or '').lower()
    text = text.replace("'", '').replace('’', '')
    text = re.sub(r"[^a-z0-9]+", '-', text)
    return text.strip('-')


def make_class_id(name: str, source: str) -> str:
    edition = EDITION_MAP.get(source.upper(), 'classic')
    return f"{slugify(name)}-{slugify(source)}-{edition}"


def load_json(path: Path):
    with path.open('r', encoding='utf-8') as fh:
        return json.load(fh)


def main():
    sources_map = load_json(SPELL_DIR / 'sources.json')
    spells_out = []

    for source_code, filename in FILE_MAP.items():
        data = load_json(SPELL_DIR / filename)
        source_spells = sources_map.get(source_code, {})

        for spell in data.get('spell', []):
            name = spell.get('name')
            if not name:
                continue

            class_info = source_spells.get(name, {})
            classes = []
            seen = set()
            for key in ('class', 'classVariant'):
                for cls in class_info.get(key, []) or []:
                    cls_name = cls.get('name')
                    cls_source = (cls.get('source') or source_code).upper()
                    if not cls_name or cls_source not in TARGET_SOURCES:
                        continue
                    class_id = make_class_id(cls_name, cls_source)
                    dedupe_key = (class_id, cls_name, cls_source)
                    if dedupe_key in seen:
                        continue
                    seen.add(dedupe_key)
                    classes.append({
                        'id': class_id,
                        'name': cls_name,
                        'source': cls_source,
                    })

            spells_out.append({
                'id': f"{slugify(name)}-{source_code.lower()}",
                'name': name,
                'source': source_code,
                'level': spell.get('level', 0),
                'school': spell.get('school'),
                'time': spell.get('time', []),
                'range': spell.get('range'),
                'components': spell.get('components'),
                'duration': spell.get('duration', []),
                'entries': spell.get('entries', []),
                'classes': classes,
            })

    with OUT_PATH.open('w', encoding='utf-8') as fh:
        json.dump({'spells': spells_out}, fh, ensure_ascii=False, indent=2)

    print(f"Generado {OUT_PATH} con {len(spells_out)} conjuros")


if __name__ == '__main__':
    main()
