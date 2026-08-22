# Run 06 - Oeffentliche AST API

## Ziel

Den AST als stabiles Integrationsformat fuer einbindende Apps definieren, ohne interne Renderdetails versehentlich zu veroeffentlichen.

## Eingaben

- `05-html-renderer-parity.md`
- AST-Modell aus Run 03
- Erfahrungen aus Compare-Modus und Renderer-Migration

## Aufgaben

1. ✅ Oeffentlichen Contract von internen Feldern getrennt.
2. ✅ Rueckgabeformate und API-Einstiegspunkte umgesetzt.
3. ✅ Versionierung und Kompatibilitaetsregeln festgelegt.

## API-Zielbild

- `processToHtml(...)`
- `processToAst(...)`
- `processToAstAndHtml(...)`

## Oeffentliche Vertragsflaeche

- Struktur-Nodes
- Inline-Tokens
- Source-Referenzen
- stabile Stil-/Semantikfelder, die Apps fuer Folgeverarbeitung brauchen

## Nicht oeffentlich oder nur als intern markiert

- Renderer-spezifische Zwischenhilfen
- Compare-Only-Metadaten
- debugging-orientierte Pass-Interna

## Erzeugte Artefakte

### Implementierte Dateien

```
src/Service/Ast/
  AstDocumentProcessor.php      (neue API-Einstiegspunkte)
  PublicAstSerializer.php       (oeffentlicher AST-Contract + Feldfilter)

src/Model/
  ProcessedAstDocument.php      (AST-only DTO)
  ProcessedAstAndHtmlDocument.php (AST+HTML DTO)

tests/Service/Ast/
  AstDocumentProcessorApiTest.php
```

### Public-AST-Contract

- `astVersion` ist verpflichtend und aktuell auf `1.0.0` gesetzt.
- Der oeffentliche AST ist ein sanitisiertes Node-Array aus dem normalisierten `DocumentNode`.
- `metadata` wird auf `sourceRef` reduziert; interne Felder werden entfernt.

### Abgrenzung intern vs. extern

| Feldgruppe | Status | Details |
|---|---|---|
| Node-Struktur (`type`, `sections`, `paragraphs`, `children`, ...) | oeffentlich | stabile Struktur fuer App-Konsum |
| Inline-/Semantikfelder (`content`, `trackChange`, `numFormat`, `spacing`, ...) | oeffentlich | fuer Weiterverarbeitung gedacht |
| `metadata.sourceRef` | oeffentlich | technische Rueckverfolgbarkeit |
| `metadata.renderHints` inkl. `legacy_html*` | intern | Renderer-Parity-Hilfen, nicht Teil des Contracts |
| `metadata.phpWordType`, `metadata.resolvedStyle` | intern | Implementierungsdetail des Converters |
| `metadata.whitespaceFlags`, `metadata.originFlags` | intern | Pass-/Debug-Hilfen, nicht stabil zugesagt |

## Festgezogene Entscheidungen

- Apps sollen den AST konsumieren koennen, ohne HTML analysieren zu muessen.
- Stabilitaet des AST-Contracts ist ein eigenes Ziel, nicht nur Nebenprodukt des Renderers.
- API wird explizit als drei Einstiegspunkte angeboten:
  - `processToHtml(...)` (kompatibler HTML-DTO-Pfad)
  - `processToAst(...)` (AST-only)
  - `processToAstAndHtml(...)` (kombiniert)
- AST-Versionierung erfolgt ueber `astVersion` nach SemVer-Regeln, unabhaengig von der Paketversion.

## Offene Punkte

- Keine fuer Run 06.

## Annahmen

- Ein klarer Public Contract senkt spaetere Koppelung an interne Renderdetails.

## Ergebnisse

✅ **Oeffentliche AST-API umgesetzt**
- `AstDocumentProcessor` bietet jetzt `processToHtml`, `processToAst`, `processToAstAndHtml`.

✅ **Public Contract von Render-Interna getrennt**
- `PublicAstSerializer` entfernt interne Metadaten (`renderHints`, `legacy_html*`, Converter-/Pass-Interna).
- Nur `metadata.sourceRef` bleibt als oeffentliches Metadatenfeld erhalten.

✅ **Versionierter AST-Output eingefuehrt**
- `astVersion = 1.0.0` wird in AST-DTOs immer mitgegeben.

✅ **Tests und Doku aktualisiert**
- Neue Service-Tests fuer API-Einstiegspunkte und Contract-Filter.
- README erweitert um AST-API-Nutzung und Kompatibilitaetsregeln.

## Interpretation fuer Run 07

Run 07 fuegt optionales Template-/Placeholder-Parsing hinzu und bleibt strikt auf der oeffentlichen AST-Sicht:

1. Parser-Annotationen duerfen nur auf Public-Contract-Feldern (inkl. `sourceRef`) aufsetzen.
2. Neue Token/Marker fuer Placeholder muessen als additive, AST-versionierte Erweiterung eingefuehrt werden (kein Bruch von `1.0.0`-Konsumenten).
3. Keine App-Semantik im Core: nur Syntax-Erkennung/Annotation, keine Auswertung oder Business-Regeln.
