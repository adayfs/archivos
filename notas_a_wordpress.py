import argparse
import os
import re
import sys
import unicodedata
from pathlib import Path
from typing import Any, Dict, Iterable, List, Optional, Tuple

import requests


POST_TYPE_DIARY = "diario"
TYPE_MAP = {"N": "npc", "L": "lugar", "F": "faccion"}
TYPE_MAP_DETALLE = {"NPC": "npc", "LUGAR": "lugar", "FACCION": "faccion"}


class ConfigError(Exception):
    pass


class WPClientError(Exception):
    pass


class WPClient:
    def __init__(self, base_url: str, user: str, app_password: str, timeout: int = 20) -> None:
        base = base_url.rstrip("/")
        if not base.startswith("http://") and not base.startswith("https://"):
            base = f"https://{base}"
        self.base_api = f"{base}/wp-json/wp/v2"
        self.auth = (user, app_password)
        self.timeout = timeout

    def _request(self, method: str, endpoint: str, **kwargs: Any) -> Any:
        url = f"{self.base_api}{endpoint}"
        try:
            response = requests.request(method, url, auth=self.auth, timeout=self.timeout, **kwargs)
        except requests.RequestException as exc:
            raise WPClientError(f"Request to {url} failed: {exc}") from exc
        if not response.ok:
            detail = response.text
            raise WPClientError(f"WP API error {response.status_code} for {url}: {detail}")
        if response.status_code == 204:
            return None
        return response.json()

    def create_post(
        self,
        post_type: str,
        title: str,
        content: str,
        slug: Optional[str] = None,
        status: str = "publish",
    ) -> Dict[str, Any]:
        data: Dict[str, Any] = {"title": title, "content": content, "status": status}
        if slug:
            data["slug"] = slug
        return self._request("post", f"/{post_type}", json=data)

    def find_post_by_slug(self, post_type: str, slug: str) -> Optional[Dict[str, Any]]:
        items = self._request(
            "get", f"/{post_type}", params={"slug": slug, "per_page": 1, "context": "edit"}
        )
        if isinstance(items, list) and items:
            return items[0]
        return None

    def update_post(self, post_type: str, post_id: int, data: Dict[str, Any]) -> Dict[str, Any]:
        return self._request("post", f"/{post_type}/{post_id}", json=data)


def slugify(text: str) -> str:
    normalized = unicodedata.normalize("NFKD", text)
    ascii_text = normalized.encode("ascii", "ignore").decode("ascii")
    ascii_text = ascii_text.lower()
    ascii_text = re.sub(r"[^a-z0-9]+", "-", ascii_text)
    ascii_text = ascii_text.strip("-")
    ascii_text = re.sub(r"-{2,}", "-", ascii_text)
    return ascii_text or "item"


def cargar_config_desde_entorno() -> Tuple[str, str, str]:
    base_url = os.getenv("WP_BASE_URL", "https://adayfs.com")
    user = os.getenv("WP_USER", "admin")
    app_password = os.getenv("WP_APP_PASSWORD")
    if not app_password:
        raise ConfigError("Debes definir la variable de entorno WP_APP_PASSWORD.")
    return base_url, user, app_password


def _extract_post_content(post: Dict[str, Any]) -> str:
    content_block = post.get("content") or {}
    raw = content_block.get("raw")
    if raw is not None:
        return str(raw)
    rendered = content_block.get("rendered")
    return str(rendered) if rendered is not None else ""


def parse_diario(path: Path) -> Tuple[str, str, List[Tuple[str, str]]]:
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines()
    start_idx: Optional[int] = None
    end_idx: Optional[int] = None
    title: Optional[str] = None

    for idx, line in enumerate(lines):
        stripped = line.lstrip()
        if stripped.startswith("#@"):
            start_idx = idx
            title = stripped[2:].strip()
            break

    if start_idx is not None:
        for idx in range(start_idx + 1, len(lines)):
            if lines[idx].lstrip().startswith("#@"):
                end_idx = idx
                break
        content_lines = lines[start_idx + 1 : end_idx or len(lines)]
        content = "\n".join(content_lines).strip("\n")
        diary_title = title or path.stem
    else:
        diary_title = path.stem.replace("_", " ").strip() or path.stem
        content = text.strip("\n")

    markers = re.findall(r"#([NLF])\[(.+?)\]", content)
    elementos: List[Tuple[str, str]] = []
    seen = set()
    for tipo, nombre in markers:
        nombre = nombre.strip()
        key = (tipo.upper(), nombre)
        if key in seen:
            continue
        seen.add(key)
        elementos.append((tipo.upper(), nombre))

    return diary_title, content, elementos


def parse_detalles(path: Path) -> List[Dict[str, str]]:
    header_re = re.compile(r"^\s*##\s*([A-Za-z]+):\s*(.+)$")
    lines = path.read_text(encoding="utf-8").splitlines()
    bloques: List[Dict[str, str]] = []
    current_tipo: Optional[str] = None
    current_nombre: Optional[str] = None
    buffer: List[str] = []

    def flush() -> None:
        if current_tipo and current_nombre:
            descripcion = "\n".join(buffer).strip("\n")
            bloques.append(
                {"tipo": current_tipo, "nombre": current_nombre, "descripcion": descripcion}
            )

    for line in lines:
        match = header_re.match(line)
        if match:
            flush()
            current_tipo = match.group(1).strip()
            current_nombre = match.group(2).strip()
            buffer = []
        else:
            if current_tipo:
                buffer.append(line)
    flush()
    return bloques


def modo_crear_diario(path: Path, wp_client: WPClient) -> None:
    titulo, contenido, elementos = parse_diario(path)
    slug_diario = slugify(titulo)

    existente = wp_client.find_post_by_slug(POST_TYPE_DIARY, slug_diario)
    if existente is None:
        creado = wp_client.create_post(POST_TYPE_DIARY, titulo, contenido, slug=slug_diario)
        diario_id = creado.get("id")
        print(f"Creado diario '{titulo}' (id={diario_id}, slug={slug_diario})")
    else:
        diario_id = existente.get("id")
        actual = _extract_post_content(existente)
        nuevo_contenido = actual + "\n\n<hr/>\n\n" + contenido if actual else contenido
        wp_client.update_post(POST_TYPE_DIARY, int(diario_id), {"content": nuevo_contenido})
        print(f"Actualizado diario '{titulo}' (id={diario_id}, slug={slug_diario})")

    for tipo, nombre in elementos:
        post_type = TYPE_MAP[tipo]
        slug = slugify(nombre)
        ficha = wp_client.find_post_by_slug(post_type, slug)
        if ficha is None:
            texto = f"Entrada creada automaticamente desde el diario '{titulo}'."
            creado = wp_client.create_post(post_type, nombre, texto, slug=slug)
            print(f"Creada ficha {post_type} '{nombre}' (id={creado.get('id')}, slug={slug})")
        else:
            print(f"Ficha {post_type} '{nombre}' ya existe (id={ficha.get('id')}, slug={slug})")


def modo_elementos_wiki(path: Path, wp_client: WPClient) -> None:
    bloques = parse_detalles(path)
    if not bloques:
        print("No se encontraron bloques de detalle en el fichero.")
        return

    for bloque in bloques:
        tipo_norm = bloque["tipo"].strip().upper()
        post_type = TYPE_MAP_DETALLE.get(tipo_norm)
        if not post_type:
            print(f"Tipo no reconocido '{bloque['tipo']}', se omite.")
            continue
        nombre = bloque["nombre"].strip()
        descripcion = bloque["descripcion"].strip()
        slug = slugify(nombre)

        existente = wp_client.find_post_by_slug(post_type, slug)
        if existente is None:
            creado = wp_client.create_post(post_type, nombre, descripcion, slug=slug)
            print(f"Creada entrada {post_type} '{nombre}' (id={creado.get('id')}, slug={slug})")
        else:
            actual = _extract_post_content(existente)
            nuevo = actual + "\n\n<hr/>\n\n" + descripcion if actual else descripcion
            wp_client.update_post(post_type, int(existente.get("id")), {"content": nuevo})
            print(f"Actualizada entrada {post_type} '{nombre}' (id={existente.get('id')}, slug={slug})")


def main(argv: Optional[Iterable[str]] = None) -> None:
    parser = argparse.ArgumentParser(
        description="Sincroniza notas de diario y fichas con WordPress mediante REST API."
    )
    parser.add_argument(
        "modo", choices=["CrearDiario", "ElementosWiki"], help="Modo de ejecucion."
    )
    parser.add_argument("archivo", help="Ruta al archivo de notas o detalles.")
    args = parser.parse_args(list(argv) if argv is not None else None)

    try:
        base_url, user, app_password = cargar_config_desde_entorno()
    except ConfigError as exc:
        print(f"Error de configuracion: {exc}", file=sys.stderr)
        sys.exit(1)

    wp_client = WPClient(base_url=base_url, user=user, app_password=app_password)
    fichero = Path(args.archivo)
    if not fichero.exists():
        print(f"El archivo {fichero} no existe.", file=sys.stderr)
        sys.exit(1)

    try:
        if args.modo == "CrearDiario":
            modo_crear_diario(fichero, wp_client)
        else:
            modo_elementos_wiki(fichero, wp_client)
    except WPClientError as exc:
        print(f"Error al comunicar con WordPress: {exc}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
