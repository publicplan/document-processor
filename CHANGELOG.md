# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Öffentlicher AST-Contract erweitert um dokumentweiten Basis-Schriftgrößenwert:
  - `document.baseFontSizePt` (immer gesetzt)
  - `document.baseFontSizeSource` (`docDefaults`, `normalStyle`, `styleChain`, `bodyRuns`, `fallback`)
  - `document.baseFontSizeRaw` (optionaler Debug-Payload)
- Deterministische DOCX-Ableitung der Basis-Schriftgröße mit Prioritätskette:
  1) `styles.xml/docDefaults`
  2) Normal-/Body-Style inkl. `basedOn`-Kette
  3) häufigste Body-Run-Größe (ohne Tabellen/TOC)
  4) Fallback `12pt`
- Öffentlicher AST-Contract erweitert um Word-nahe Layout-Metadaten für Listen:
  - `ListItemNode`: `alignment`, `indent` (`left/right/firstLine/hanging`), `spacing` (`before/after/line`), `level` (`indentLeft/indentHanging/tabStop/markerOffset`)
  - `ListNode`: aggregierte `spacing` (`before/after`) und `indent.left`
- Öffentlicher AST-Contract erweitert um Tabellen-Layout-Metadaten auf `TableNode`:
  - `alignment`, `indent.left`, `spacing.before/after`, `cellSpacing`, `layout`, `cellMargins`
- `WordToAstConverter` liest die neuen Werte direkt aus DOCX/PhpWord-Styles (inkl. List-Level-Definitionen und Table-Styles), ohne Heuristik.

### Changed
- AST-Version im öffentlichen Serializer auf `1.5.0` erhöht.

## [2.0.1] - 2026-08-24

### Added
- **AST-Pipeline**: Interner `DocumentNode`-basierter AST als Zwischenschicht zwischen DOCX-Parsing und HTML-Rendering
- **NormalizationPipeline** mit sieben expliziten Passes: `SpacerParagraphPass`, `ListStructurePass`, `ListNormalizationPass`, `TextCoalescingPass`, `TemplateAnnotationPass` u. a.
- **Öffentliche AST-API** über `AstDocumentProcessor`:
  - `processToHtml()` – kompatibler HTML-Pfad (bisheriges Verhalten)
  - `processToAst()` – gibt `ProcessedAstDocument` mit versioniertem AST zurück
  - `processToAstAndHtml()` – kombiniert beides
  - `processToDocumentNode()` – direkter Zugriff auf den internen `DocumentNode`
- **AST-Versionierung**: `astVersion`-Feld im öffentlichen AST-Contract nach SemVer; Start bei `1.0.0`, nach Template-Annotation auf `1.1.0`
- **Template-Syntax-Annotation** als optionaler Pass (`TemplateAnnotationPass`):
  - Aktivierung über `ProcessingOptions->templateSyntaxProfile`
  - `GenericTemplateSyntaxProfile` erkennt `{{ }}`, `{% %}` und `#{ }` sowie Steuerungs-Tags (`wenn`, `sonst wenn`, `sonst`, `ende`)
  - Plugin-Schnittstelle `TemplateSyntaxProfile` für app-spezifische Dialekte
  - Fehlerhafte Fragmente werden als `malformed` annotiert, nicht repariert
- `ListNormalizationPass` verschachtelt `ListItemNode`s korrekt nach Tiefe, auch bei fehlerhaft strukturierten Eingaben
- `PublicAstSerializer` filtert interne Render-Metadaten (`renderHints`, `legacy_html*`, Converter-Interna) aus dem öffentlichen Contract

### Changed
- `DocumentProcessor::process()` leitet intern über die neue AST-Pipeline; HTML-Ausgabe bleibt abwärtskompatibel
- Öffentlicher AST-Contract: nur `metadata.sourceRef` verbleibt als öffentliches Metadatenfeld

### Architecture
- Siehe [ADR-0001](doc/adr/0001-ast-als-interne-zwischenschicht.md), [ADR-0002](doc/adr/0002-normalization-passes.md), [ADR-0003](doc/adr/0003-oeffentliche-ast-api.md), [ADR-0004](doc/adr/0004-template-syntax-annotation.md)

---

## [1.9.0] - 2026-08-21

### Added
- **Spacer-Paragraphen-Handling**: Leere Absätze zwischen Listenpunkten gleicher `numId` werden nicht als HTML gerendert; ihr Spacing wird als `margin-bottom` auf das vorherige `<li>` übertragen, sodass zusammengehörige Listen nicht in separate `<ol>`/`<ul>` aufgeteilt werden

---

## [1.8.0] - 2026-08-21

### Fixed
- **Einrückung aus Built-in-Styles** (`styles.xml`): Absatzeinrückung, die nur im Paragraph-Style (z. B. `Listenabsatz`) und nicht direkt am Absatz definiert ist, wird jetzt korrekt aufgelöst (`ParagraphIndentHelper` mit Prioritätskette direkt > Style-Name > `basedOn`-Kette)
- **wkhtmltopdf-Kompatibilität**: `&#32;` (numerisches Leerzeichen) durch `&nbsp;` ersetzt, damit leere Absätze und TextRuns im PDF nicht kollabieren

---

## [1.7.0] - 2026-08-21

### Added
- Optionale HTML-Fragment-Validierung mit Severity-Normalisierung in `DocumentProcessor`

### Changed
- Font-Scale-Attribut wird bei einzelnen Font-Gruppen auf Absatzebene gesetzt statt auf Run-Ebene

---

## [1.6.0] - 2026-08-20

### Added
- Gelöschte Inhalte (Track Changes) werden korrekt aus der HTML-Ausgabe ausgeblendet

---

## [1.5.0] - 2026-08-20

### Changed
- Fortlaufende geordnete Listen über Absatzgrenzen hinweg (`start`-Attribut-Fortführung)
- Tabellen-Border-Handling in `TableElementConverter` weiter ausgebaut (Zellenrahmen-Kaskade, diagonale Borders)

---

## [1.4.0] - 2026-08-20

### Changed
- Tabellen-Border-Handling in `TableElementConverter` grundlegend überarbeitet, Tests ergänzt

---

## [1.3.0] - 2026-08-20

### Changed
- Absatz-Ausrichtung in `ListElementConverter` und `TextRunElementConverter` konsistent gemacht

---

## [1.2.0] - 2026-08-19

### Changed
- Font-Size-Handling in `ConversionContext` und abhängigen Convertern überarbeitet

---

## [1.1.6] - 2026-08-19

### Changed
- Border-Farbbehandlung in `BorderStyleHelper` konsolidiert

---

## [1.1.5] - 2026-08-19

### Changed
- Border-Styling in `TextBoxElementConverter` und `TextRunElementConverter` vereinheitlicht

---

## [1.1.4] - 2026-08-10

### Added
- Unterstützung für nicht akzeptierte Änderungen (`hasUnacceptedChanges`) in `DocumentProcessor` via `ParserError`

### Changed
- `ParserError`-Konstanten aktualisiert

---

## [1.1.3] - 2026-03-12

### Added
- **`TextBoxElementConverter`**: Converter für Word-Textrahmen (`<w:txbxContent>`) mit Tests

---

## [1.1.2] - 2026-03-06

### Changed
- Listenkonvertierungslogik in `doConvert()` / `convertWithSpacing()` extrahiert und wiederverwendbar gemacht
- `ParserError`-Konstanten auf einzeilige, PSR-konforme Deklarationen vereinheitlicht
- Typ-Assertion für `DocList` in `convert()` ergänzt (explizitere Fehlerdiagnose)
- Code-Formatierung und PHPDoc-Kommentare über alle Klassen konsistent gemacht

---

## [1.1.1] - 2026-03-05

### Changed
- IDE-Dateien (`.idea/`) aus Repository entfernt, `.gitignore` um OS- und Editor-Dateien erweitert

---

## [1.0.0] - 2026-03-05

### Added
- Initial release
- DOCX to HTML conversion
- Strategy Pattern architecture with 10 element converters
- Support for: text, lists, tables, links, formatting (bold, italic, underline)
- Comprehensive test suite (33 tests, 71 assertions)
- MIT License
- Full PHP 8.4+ compatibility

### Features
- `DocumentProcessor::process()` - Main facade method
- `DocumentLoader` - Loading and validation
- `ProcessedDocument` DTO for results
- Element converters for all major Word elements
- Bottom spacing support for list items
- Track Changes detection

[1.0.0]: https://github.com/publicplan/document-processor/releases/tag/v1.0.0
