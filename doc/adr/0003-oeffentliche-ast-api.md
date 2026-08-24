# ADR-0003: Öffentliche AST-API mit stabilem Contract

**Status:** Accepted  
**Datum:** 2026-08-22  
**Implementiert in:** v2.0.0

## Kontext

Konsumierende Apps benötigten bisher HTML und mussten daraus strukturelle Information extrahieren. Das führte zu fragiler Kopplung an konkrete HTML-Ausgaben. Der interne AST (→ ADR-0001) bietet eine reichhaltigere und stabilere Grundlage – aber nur, wenn interne Render-Details nicht unbeabsichtigt Teil des öffentlichen Contracts werden.

## Entscheidung

Der AST wird als **stabiles Integrationsformat** über `AstDocumentProcessor` öffentlich zugänglich gemacht:

| Methode | Rückgabe |
|---|---|
| `processToHtml()` | `ProcessedDocument` (bisheriges HTML-DTO) |
| `processToAst()` | `ProcessedAstDocument` (AST-only) |
| `processToAstAndHtml()` | `ProcessedAstAndHtmlDocument` (kombiniert) |
| `processToDocumentNode()` | `DocumentNode` (interner Baum, für Lib-interne Nutzung) |

Der öffentliche AST-Contract wird durch `PublicAstSerializer` gefiltert:

**Öffentlich:**
- Node-Struktur (`type`, `sections`, `paragraphs`, `children`, …)
- Inline-/Semantikfelder (`content`, `trackChange`, `numFormat`, `spacing`, …)
- `metadata.sourceRef` (technische Rückverfolgbarkeit)

**Intern / nicht Teil des Contracts:**
- `metadata.renderHints` inkl. `legacy_html*`
- `metadata.phpWordType`, `metadata.resolvedStyle`
- `metadata.whitespaceFlags`, `metadata.originFlags`

Der AST-Contract wird über `astVersion` nach SemVer versioniert (unabhängig von der Paketversion). Start: `1.0.0`. Additive Erweiterungen (z. B. Template-Annotation) erhöhen den Minor: `1.1.0`.

## Konsequenzen

- Apps können Struktur, Formatierung und Semantik aus dem AST lesen, ohne HTML zu parsen
- Breaking Changes am AST-Contract erfordern eine Major-Erhöhung von `astVersion`
- Additive Felder (neue Node-Typen, neue öffentliche Metadaten) können als Minor ergänzt werden
- Interne Render-Details bleiben frei veränderbar, ohne den öffentlichen Contract zu verletzen
