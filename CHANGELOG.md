# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-08-24

### Added
- **AST-Pipeline**: Interner `DocumentNode`-basierter AST als Zwischenschicht zwischen DOCX-Parsing und HTML-Rendering (ersetzt direkten Converter-Durchlauf)
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
- **`ParagraphIndentHelper`**: Löst effektive Einrückung mit Prioritätskette auf (direkt > Style-Name > `basedOn`-Kette aus `styles.xml`)
- `ListNormalizationPass` verschachtelt `ListItemNode`s korrekt nach Tiefe, auch bei fehlerhaft strukturierten Eingaben
- `PublicAstSerializer` filtert interne Render-Metadaten (`renderHints`, `legacy_html*`, Converter-Interna) aus dem öffentlichen Contract

### Changed
- `DocumentProcessor::process()` leitet intern über die neue AST-Pipeline; HTML-Ausgabe bleibt abwärtskompatibel
- Öffentlicher AST-Contract: nur `metadata.sourceRef` verbleibt als öffentliches Metadatenfeld

### Architecture
- Siehe [ADR-0001](doc/adr/0001-ast-als-interne-zwischenschicht.md), [ADR-0002](doc/adr/0002-normalization-passes.md), [ADR-0003](doc/adr/0003-oeffentliche-ast-api.md), [ADR-0004](doc/adr/0004-template-syntax-annotation.md)

---

## [1.2.0] - 2026-08-21

### Added
- **`TextBoxElementConverter`**: neuer Converter für Word-Textrahmen (`<w:txbxContent>`) mit Border- und Farbunterstützung
- **Spacer-Paragraphen-Handling**: Leere Absätze zwischen Listenpunkten gleicher `numId` werden nicht als HTML gerendert; ihr Spacing wird als `margin-bottom` auf das vorherige `<li>` übertragen, sodass zusammengehörige Listen nicht in separate `<ol>`/`<ul>` aufgeteilt werden
- Optionale HTML-Fragment-Validierung mit Severity-Normalisierung in `DocumentProcessor`
- Unterstützung für nicht akzeptierte Änderungen (`hasUnacceptedChanges`) in `DocumentProcessor` via `ParserError`

### Fixed
- **Einrückung aus Built-in-Styles** (`styles.xml`): Absatzeinrückung, die nur im Paragraph-Style (z. B. `Listenabsatz`) und nicht direkt am Absatz definiert ist, wird jetzt korrekt aufgelöst und als HTML-Einrückung ausgegeben
- **wkhtmltopdf-Kompatibilität**: `&#32;` (numerisches Leerzeichen) durch `&nbsp;` ersetzt, damit leere Absätze und TextRuns im PDF nicht kollabieren

### Changed
- Border-Styling in `TextBoxElementConverter` und `TextRunElementConverter` vereinheitlicht
- Border-Farbbehandlung in `BorderStyleHelper` konsolidiert
- Font-Size-Handling in `ConversionContext` und abhängigen Convertern überarbeitet
- Absatz-Ausrichtung in `ListElementConverter` und `TextRunElementConverter` konsistent gemacht
- Tabellen-Border-Handling in `TableElementConverter` erweitert (diagonale Borders, Zellenrahmen-Kaskade)
- Fortlaufende geordnete Listen über Absatzgrenzen hinweg (`start`-Attribut-Fortführung)
- Gelöschte Inhalte (Track Changes) werden korrekt aus der HTML-Ausgabe ausgeblendet
- Font-Scale-Attribut wird bei einzelnen Font-Gruppen auf Absatzebene gesetzt

---

## [1.1.0] - 2026-03-12

### Added
- **`TextBoxElementConverter`** (Grundstruktur): Converter für Word-Textrahmen mit Tests

### Changed
- Listenkonvertierungslogik in `doConvert()` / `convertWithSpacing()` extrahiert und wiederverwendbar gemacht
- `ParserError`-Konstanten auf einzeilige, PSR-konforme Deklarationen vereinheitlicht
- Typ-Assertion für `DocList` in `convert()` ergänzt (explizitere Fehlerdiagnose)
- Code-Formatierung und PHPDoc-Kommentare über alle Klassen konsistent gemacht

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
