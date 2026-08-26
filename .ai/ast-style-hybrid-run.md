# Run: Hybrid-Strategie für Word-Styles im AST

Datum: 2026-08-26

## Ziel

Additive Einführung einer Hybrid-Strategie:

- zentrale Style-Referenzen und Herkunft (`styleRef` / `styleRefs`, `styleProvenance`)
- weiterhin renderer-nahe resolved Inline-Werte (`resolvedLayout`) für robuste HTML/PDF-Parität

## Umgesetzte Änderungen

### 1) AST-Schema (additiv, rückwärtskompatibel)

- `AstNode` um optionale Style-Kontextfelder erweitert:
  - `styleRef`
  - `styleRefs`
  - `styleProvenance`
  - `resolvedLayout`
- Relevante Nodes serialisieren diese Felder jetzt öffentlich:
  - `ParagraphNode`
  - `ListItemNode`
  - `ListNode`
  - `TableNode`
  - `TableRowNode`
  - `TableCellNode`
  - `TextNode` (für Character-Style-Referenz)

### 2) Extraction/Resolver (WordToAstConverter)

- Paragraph-Mapping erweitert:
  - `styleRefs.paragraph` inkl. `styleId`, `styleName`, `styleType`, `source`, `basedOnChain`
  - `resolvedLayout` für Paragraph (`alignment`, `indent`, `spacing`)
  - `styleProvenance` pro Feld (`direct` / `style` / `basedOn` / `default` / `rendererDefault`)
- List-Mapping erweitert:
  - `styleRefs.numbering` inkl. `numId`, `abstractNumId`, `level`, `numStyleId`
  - Numbering-Level-Metadaten in `resolvedLayout.marker`:
    - `format`, `text`, `start`, `suffix`, `justification`, `restart`
  - Level-Layout in `resolvedLayout.level`
  - Provenance für Marker-/Level-Felder (`numberingLevel`)
- Table-Mapping erweitert:
  - `styleRefs.table` inkl. `basedOnChain`
  - `resolvedLayout` für Tabelle (`indent`, `spacing`, `cellSpacing`, `layout`, `cellMargins`, `borders`)
  - `resolvedLayout` für TableRow (`repeatHeader`, `cantSplit`, `height`)
  - `resolvedLayout` für TableCell (`margins`, `verticalAlign`, `textDirection`, `borders`, `shading`)
- Character-Style-Referenz auf `TextNode` (wenn Style-Name vorhanden).

### 3) DocumentLoader

- Registrierung aus `styles.xml` erweitert:
  - Paragraph: zusätzliche Alignment/Spacing/Indent-Felder
  - Table: zusätzliche Layout-/Margin-/Spacing-Felder
- Neue Snapshot-Extraktion für AST-Auflösung:
  - `extractAstStyleSnapshot(string $filePath): ?array`
  - liest `styles.xml` + `numbering.xml`
  - liefert:
    - Paragraph-/Table-Styles inkl. `basedOn`
    - DocDefaults (Paragraph)
    - Numbering-Mapping (`numId -> abstractNumId`) und Level-Daten

### 4) Renderer

- `AstHtmlRenderer` nutzt zusätzlich `resolvedLayout` als Fallback/Ergänzung:
  - Listen: `margin-top`, `margin-bottom`, `margin-left` auf `<ul>/<ol>`, `<li>`
  - Tabellen: `margin`, `border-spacing`, `table-layout`
  - Tabellenzellen: `vertical-align`, `padding` (aus resolved margins)
- Border-/Textbox-Parität beibehalten:
  - `TextBoxNode` rendert Legacy-HTML via `renderHints.legacy_html`
  - Border-Group-Absatzstilformatierung bleibt parity-kompatibel

### 5) Public AST Contract

- `PublicAstSerializer::AST_VERSION` von `1.2.0` auf `1.3.0` (Minor-Bump).
- Interne Metadaten-Filterung (`metadata`) unverändert: weiterhin nur `sourceRef`.

## Auflösungspriorität (implementiert)

Für Paragraph-Felder:

1. direktes Override (Node/`document.xml`-nahe Werte)
2. referenzierter Style
3. `basedOn`-Kette
4. Defaults
5. Renderer-Default

Für Numbering-Level-Felder:

- primär Numbering-Level (`numbering.xml`/Style-Level), inkl. Snapshot-Fallback

## Tests

Erweitert/angepasst:

- `tests/Unit/Ast/AstSerializationTest.php`
  - serialisiert neue Style-Felder auf `ListItemNode`
- `tests/Service/Ast/AstDocumentProcessorApiTest.php`
  - list/table AST enthält neue Style-Felder
  - Paragraph-StyleRef + `basedOnChain`
- `tests/Service/DocumentLoaderTest.php`
  - Snapshot-Extraktion (`extractAstStyleSnapshot`) auf Styles/Numbering

Gesamte Test-Suite grün.

## Kompatibilität / Migration

- Keine Breaking Changes an bestehenden Feldern.
- Bestehende Consumer können unverändert weiterlaufen.
- Neue Felder sind additiv und optional nutzbar.
- Inline-/resolved-orientiertes Rendering bleibt erhalten (wkhtmltopdf-sicher).
