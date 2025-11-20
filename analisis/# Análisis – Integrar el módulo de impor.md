# Análisis – Integrar el módulo de importación en la home de cada campaña

## 1. Objetivo

1. Que en la **home de cada campaña** (`single-campaign`, sección “Portal”) aparezca el módulo de importación:
   - Importador de sesión (shortcode `[drak_import_session]`) solo para DM/Admin.
   - Importador por elemento (shortcode `[drak_import_element]`) para DM y, en el futuro, jugadores (con restricciones propias del plugin).

2. Que el importador se abra **ya en contexto de la campaña actual**, sin que el usuario tenga que elegir la campaña a mano.

---

## 2. Mejora de los shortcodes del plugin `drak-importer`

### 2.1. Atributo opcional `campaign`

Extender ambos shortcodes existentes:

- `[drak_import_session]`
- `[drak_import_element]`

para que acepten un atributo opcional:

```text
[drak_import_session campaign="123"]
[drak_import_element campaign="123"]
