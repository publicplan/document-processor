# A) Ist-Analyse (kurz)

## Stabil verfügbare Word/DOCX-Stylequellen (fachlich)

1. **Paragraph Styles (`styles.xml`)**: `w:style[@w:type="paragraph"]` mit `styleId`, `basedOn`, `pPr/rPr` und direkten Absatz-Overrides in `document.xml` (`w:pPr`).
2. **List/Numbering (`numbering.xml` + `document.xml`)**: `w:numPr/w:numId`, `w:ilvl`, `w:numStart` im Absatz; Auflösung über `w:num -> w:abstractNum -> w:lvl`.
3. **Table Styles (`styles.xml` + `document.xml`)**: `w:tblStyle` + `w:tblPr`, `w:trPr`, `w:tcPr` inkl. Borders, Cell-Margins, Cell-Spacing, Conditional Regions.

## Was im Repo bereits genutzt wird

- **Paragraph**:
  - `DocumentLoader` registriert aus `styles.xml` Paragraph-Styles aktuell nur `basedOn` + `indentation` (left/hanging/firstLine).
  - `WordToAstConverter` extrahiert für `ParagraphNode`/`ListItemNode` Alignment, Spacing, Indent, LineHeight als resolved Werte.
  - `ParagraphIndentHelper` löst Indent-Vererbung über `styleName`/`basedOn`.
- **Numbering/Listen**:
  - `ListElementConverter` und `WordToAstConverter` nutzen `numId`, `depth`, `numStyle`, `Numbering->getLevels()` (Format/Start + Level-Indents/TabStop).
- **Tabellen**:
  - `DocumentLoader` registriert Table-Styles aus `styles.xml` derzeit primär Borders.
  - `WordToAstConverter` extrahiert Table-Layoutfelder (indent/spacing/cellSpacing/layout/cellMargins), Table-Border-Kontext, Cell-Border/Bg.
- **Renderer/Export**:
  - `AstHtmlRenderer` rendert Paragraph-Layout inline, rendert Table-Borders/Cell-Bg, **ignoriert aber viele vorhandene List-/Table-Layoutfelder**.
  - `PublicAstSerializer` filtert `metadata` auf `sourceRef`; `resolvedStyle` und `renderHints` sind öffentlich nicht sichtbar.

## Wo Information heute verloren geht

- **Keine zentrale Style-Referenz im öffentlichen AST**: kein `styleId/styleName/styleType/source`.
- **Paragraph-Style-Substanz**: über `extractParagraphStyle()` werden praktisch nur Borders in `resolvedStyle` gesichert.
- **Numbering-Details**: kein explizites `abstractNumId`, `lvlText`, `lvlJc`, `suff`, `numRestart`, kein verlässlicher `numStart` aus `document.xml`.
- **Table-Style-Details**: keine Conditional-Regionen (firstRow, bandedRows etc.), kaum `trPr/tcPr`-Semantik außer Border/Bg.
- **Prioritätskette nicht als Datenmodell expliziert** (direkt vs. style-inherited vs. defaults).
- **Source-Trace**: `SourceReference` ist meistens `null` (außer z. B. Template-Annotation-Pass).

---

# B) Empfohlenes AST-Schema (konkrete Felder, inkl. Beispiel-JSON)

**Empfehlung: Strategy B (Hybrid)** — zentrale Referenzen + resolved Inline-Werte je Node.

## Additive Felder pro stylefähigem Node (Paragraph, ListItem, ListNode, Table, TableRow, TableCell)

- `styleRef`:
  - `styleId?: string`
  - `styleName?: string`
  - `styleType: "paragraph" | "numbering" | "table" | "character" | "direct"`
  - `source: "document.xml" | "styles.xml" | "numbering.xml" | "computed"`
  - `basedOnChain?: string[]`
- `styleRefs` (optional, mehrere Quellen):
  - `paragraph?: styleRef`
  - `numbering?: { numId?: int, abstractNumId?: int, level?: int, numStyleId?: string }`
  - `table?: styleRef`
- `styleProvenance`:
  - pro resolved Feld: `{ value, source: "direct"|"style"|"numberingLevel"|"default", sourceRef?: {...} }`
- `resolvedLayout` (öffentliche, renderer-nahe Werte):
  - Paragraph/ListItem: `alignment`, `indent{left,right,firstLine,hanging}`, `spacing{before,after,line}`, `tabs`, `keepNext`, `keepLines`, `widowControl` (soweit verfügbar)
  - ListItem: `level{indentLeft,indentHanging,tabStop,markerOffset}`, `marker{format,text,start,suffix,justification}`
  - Table: `indent`, `spacing`, `cellSpacing`, `layout`, `cellMargins`, `borders`
  - TableCell: `margins`, `verticalAlign`, `textDirection`, `borders`, `shading`, `width`, `colSpan`, `rowSpan`

## Beispiel-JSON (ListItem)

```json
{
  "type": "listItem",
  "numId": 12,
  "depth": 1,
  "styleRefs": {
    "paragraph": {
      "styleId": "ListParagraph",
      "styleName": "Listenabsatz",
      "styleType": "paragraph",
      "source": "styles.xml",
      "basedOnChain": ["ListParagraph", "Normal"]
    },
    "numbering": {
      "numId": 12,
      "abstractNumId": 4,
      "level": 1,
      "numStyleId": "NumStyleLegal"
    }
  },
  "resolvedLayout": {
    "alignment": "left",
    "indent": { "left": 1.27, "right": null, "firstLine": -0.63, "hanging": 0.63 },
    "spacing": { "before": 0.0, "after": 0.42, "line": 1.15 },
    "level": { "indentLeft": 1.27, "indentHanging": 0.63, "tabStop": 1.27, "markerOffset": 0.0 },
    "marker": { "format": "decimal", "text": "%2.", "start": 1, "suffix": "tab", "justification": "left" }
  },
  "styleProvenance": {
    "indent.left": { "value": 1.27, "source": "numberingLevel" },
    "spacing.after": { "value": 0.42, "source": "direct" }
  },
  "children": []
}
```

---

# C) Mapping-Regeln Word -> AST (inkl. Vererbung/Overrides)

## Priorität (verbindlich)

1. **Direktes Node-Override aus `document.xml`** (`w:pPr/w:rPr/w:tblPr/w:tcPr`)  
2. **Style-Definition des referenzierten Styles** (`w:pStyle`, `w:tblStyle`, `w:rStyle`, `w:numPr`)  
3. **`basedOn`-Kette** (rekursiv, zyklusgeschützt)  
4. **DocDefaults / Numbering-Level-Defaults**  
5. **Renderer-Default**

## Konkrete Mapping-Regeln

- **Paragraph**:
  - `w:pPr/w:pStyle@w:val` -> `styleRefs.paragraph.styleId`
  - `styles.xml(styleId)` + `basedOn`-Kette -> `styleRefs.paragraph.basedOnChain`
  - `w:ind`, `w:spacing`, `w:jc` -> `resolvedLayout.*` + `styleProvenance`
- **List/Numbering**:
  - `w:numPr/w:numId`, `w:ilvl`, `w:numStart` -> `numId`, `depth`, `marker.start` (falls gesetzt)
  - `numbering.xml: numId -> abstractNumId -> lvl[ilvl]` -> `marker.format/text/suffix/justification`, `level.*`
  - Absatz-Indent vs. Level-Indent: direct Paragraph-Override gewinnt; sonst Level-Indent.
- **Table**:
  - `w:tblPr/w:tblStyle` -> `styleRefs.table.styleId`
  - `tblPr` + Style + basedOn -> `resolvedLayout` (`indent`, `spacing`, `cellSpacing`, `layout`, `cellMargins`, `borders`)
  - `trPr/tcPr` -> row/cell `resolvedLayout` (z. B. repeatHeader, vAlign, tcMar, tcBorders)

---

# D) Vergleichstabelle der Wege (A/B/C + App-Extraktion nachgelagert)

| Weg | Beschreibung | Parität/Wkhtmltopdf | Stabilität über DOCX-Varianten | Wartbarkeit | Empfehlung |
|---|---|---|---|---|---|
| **A: Inline-only** | Nur resolved Werte im AST/HTML | Sehr gut kurzfristig | Mittel (Root-Cause schlecht nachvollziehbar) | Mittel/schlecht bei Wachstum | Gut für schnellen Output, schwach für Langfrist |
| **B: Hybrid** | Style-Referenz + resolved Werte | Sehr gut (Inline bleibt steuerbar) | Hoch (Trace + Fallback) | Hoch | **Beste Balance** |
| **C: Zentral zuerst** | Registry/Klassen primär, Inline nur Overrides | Variabel, abhängig von CSS-Kaskade | Hoch theoretisch | Anfangs komplex | Für Browser ok, für wkhtmltopdf riskanter |
| **App-Extraktion nachgelagert** | Lib inline-first, App dedupliziert zu globalen Styles | Gut, wenn deterministisch dedupliziert | Mittel bis hoch (abhängig von App-Qualität) | App-seitig höherer Aufwand | Gute Ergänzung zu B, nicht Ersatz |

**Kurzbewertung App-Extraktion:** sinnvoll als zweiter Schritt (Optimierung/Templating), aber die Lib sollte bereits stabile Referenzen + Provenance liefern, damit Extraktion korrekt bleibt.

---

# E) Migrations- und Kompatibilitätsplan

1. **AST additive erweitern (non-breaking)**  
   Neue Felder (`styleRef(s)`, `resolvedLayout`, `styleProvenance`) in Node-Klassen + `toArray()`.
2. **Extractor-Schicht ausbauen**  
   `WordToAstConverter` + Helper für Paragraph/List/Table-Referenzen und Provenance; optional Sidecar-Leser für `numbering.xml`-Details.
3. **Renderer unverändert stabil halten, dann gezielt auf neue Felder umstellen**  
   Erst gleiche HTML-Ausgabe sichern, danach List-/Table-Layout aus `resolvedLayout` vollständig rendern.
4. **Public-AST-Contract minor bump**  
   `astVersion` minor erhöhen, neue Felder dokumentieren; bestehende Felder unverändert lassen.
5. **Tests erweitern**  
   Unit: Mapping/Priorität/Provenance; Integration: DOCX-Fälle mit basedOn/list-level/table-style; Parity: AST→HTML stabil.
6. **App-Rollout**  
   Jarvis/Twig zunächst inline weiter nutzen; danach optional Deduplizierung in globale Styles über `styleRef+resolvedLayout`.

---

# F) Abschließende Entscheidungsempfehlung für dieses Projekt

**Klare Präferenz: B (Hybrid).**

Begründung im Kontext **AST → Twig → wkhtmltopdf**:

- wkhtmltopdf ist bei CSS-Kaskade/Spezifität empfindlicher als moderne Browser; stabile Inline-resolved Werte sichern PDF-Konsistenz.
- Gleichzeitig braucht ihr für Wartbarkeit/Debugbarkeit belastbare Style-Referenzen und Herkunft (sonst “warum ist dieser Wert so?” unklar).
- Hybrid ermöglicht späteres App-seitiges Deduplizieren ohne Informationsverlust.

## Wann auf anderen Weg wechseln

- **Temporär zu A**, wenn nur kurzfristig Parität fixiert werden muss und Consumer keine Style-Intelligenz brauchen.
- **Richtung C**, wenn ihr einen stark standardisierten Template-Stack mit kontrollierter CSS-Pipeline habt und wkhtmltopdf-Abweichungen durch harte Rendering-Tests abgesichert sind.

## Konkrete Implementierungspunkte im Repo

- **Nodes**:  
  `src/Ast/Node/ParagraphNode.php`, `ListItemNode.php`, `ListNode.php`, `TableNode.php`, `TableRowNode.php`, `TableCellNode.php`, ggf. `TextNode.php` (character style ref).
- **Converter/Extraction**:  
  `src/Service/Ast/WordToAstConverter.php`, `src/Service/Converter/ParagraphIndentHelper.php`, ggf. neuer `StyleReferenceResolver`/`NumberingResolver`.
- **Loader/OOXML-Quelle**:  
  `src/Service/DocumentLoader.php` (Styles/Numbering-Registrierung erweitern, inkl. mehr Table/Paragraph-Merkmale).
- **Serializer/Public Contract**:  
  `src/Service/Ast/PublicAstSerializer.php` (neue öffentliche Felder durchreichen; interne Meta weiter filtern).
- **Renderer**:  
  `src/Service/Ast/AstHtmlRenderer.php` (ListItem/Table/TableCell vollständig aus `resolvedLayout`; klare CSS-Reihenfolge für Spezifität).
- **Passes**:  
  `ListNormalizationPass`, `ListContinuationPass`, `BorderGroupingPass`, `TextCoalescingPass` auf Feldweitergabe/Erhalt der neuen Style-Metadaten prüfen.
- **Tests**:  
  `tests/Service/Ast/AstDocumentProcessorApiTest.php`, `tests/Service/DocumentLoaderTest.php`, `tests/Integration/DocumentProcessorParityTest.php`, `tests/Unit/Ast/AstSerializationTest.php`, plus neue Mapping-Prioritäts-Tests.

