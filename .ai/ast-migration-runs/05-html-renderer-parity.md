# Run 05 - HTML renderer parity

## Ziel

Einen AST-basierten HTML-Renderer definieren, der die bestehende HTML-Ausgabe identisch reproduziert.

## Eingaben

- `04-normalization-passes.md`
- Compare-Modus und Parity-Harness aus Run 01
- bestehende Converter- und Helper-Regeln

## Aufgaben

1. ✅ Deterministische Serializer-Regeln definiert und in den AST-Renderer uebernommen.
2. ✅ Legacy-HTML-Regeln auf AST-Nodes gemappt (inkl. Parity-Fallback ueber `legacy_html`-Hints).
3. ✅ Compare-Harness auf echten AST-Pfad umgestellt und gegen den Parity-Korpus abgesichert.

## Serializer-Regeln

Implementiert in `AstHtmlRenderer`:

- feste Attributreihenfolge ueber `ListConfig::renderStartTag()` fuer Listencontainer
- stabile Listenserien-Attribute (`data-docx-list-id`, `data-docx-list-key`) aus AST-Hints
- feste Newline-Policy (`PHP_EOL`) auf Blockebene
- konsistente Behandlung von leerem Content (`&nbsp;`) in Paragraph-Fallbacks
- Border-Gruppen mit explizitem Strippen von Child-Borderstyles (nur fuer Gruppenkontext)

## Migrationsreihenfolge

1. ✅ Text, Link, Break, Deleted (inline + block)
2. ✅ Paragraph und Inline-Scale (inkl. `legacy_html`/Fallback-Pfad)
3. ✅ Listen und List-Fortsetzung (ListNode + ListItemNode mit `startNumeration`)
4. ✅ Border-Gruppen (BorderGroupNode + Child-Strip)
5. ✅ Tabellen (TableNode/Row/Cell mit Legacy-Parity-Ausgabe)
6. ✅ Textboxen und Restfaelle (TextBoxNode, PageBreakNode, FieldTextNode)

## Erzeugte Artefakte

### Implementierte Dateien

```
src/Service/Ast/
  AstDocumentProcessor.php   (AST-End-to-End-Pfad: Load -> AST -> Normalize -> Render)
  WordToAstConverter.php     (PhpWord -> AST inkl. Legacy-Hints)
  AstHtmlRenderer.php        (deterministischer AST-Serializer)

tests/Integration/
  DocumentProcessorParityTest.php
  - AST-Closure nutzt jetzt AstDocumentProcessor statt Legacy-Processor
```

### Mapping AST-Node -> HTML-Regel

| AST-Node | Regel |
|---|---|
| `TextNode` | Inline-Text mit Bold/Italic/Underline/Deleted; bevorzugt `legacy_html` |
| `LinkNode` | `<a href="...">...</a>`; bevorzugt `legacy_html` |
| `ParagraphNode` | `<p ...>...</p>`; in BorderGroup ohne Borderstyles |
| `ListNode` + `ListItemNode` | `<ul>/<ol ...>` ueber ListConfig; `<li>` aus Item-Rendering |
| `BorderGroupNode` | `<div style="border... padding...">...</div>` |
| `TableNode` | Tabellen-HTML im Legacy-Layout |
| `TextBoxNode` | Textbox-Container-HTML im Legacy-Layout |
| `BreakNode` / `PageBreakNode` | `<br>` bzw. PageBreak-HTML |
| `FieldTextNode` | Feldresultat/Feldtext |

### Legacy-Eigenheiten, bewusst konserviert

1. `legacy_html`-Hints haben Vorrang vor generischen Fallback-Renderregeln.
2. Listen-Spacer und Listen-Info-Messages bleiben unveraendert aus Legacy-Logik.
3. Post-Processing (`DeletedContent` + `</p>`-Newlines) bleibt identisch zu `DocumentProcessor`.

## Festgezogene Entscheidungen

1. **Paritaet vor Vereinfachung**: AST-Renderer nutzt Legacy-kompatible Ausgabe als Referenz (`legacy_html`), um String-Paritaet in der ersten Iteration sicherzustellen.
2. **Normierungs-Pipeline bleibt verpflichtend**: AST-Rendering laeuft immer nach `NormalizationPipelineFactory::createStandardPipeline()`.
3. **Fallback statt harte Abhaengigkeit**: Wo kein `legacy_html` vorliegt, rendert der AST-Renderer deterministisch aus Node-Daten.
4. **Compare-Harness misst echte AST-Route**: Tests vergleichen jetzt Legacy gegen `AstDocumentProcessor`, nicht mehr Legacy gegen Legacy.

## Offene Punkte

1. `WordToAstConverter` deckt aktuell die in Tests relevanten Elementklassen voll ab; exotische PhpWord-Elemente landen weiterhin als Unhandled-Message.
2. Border-Signatur/Containerbildung wird derzeit aus vorhandenen Style-Daten abgeleitet; spaetere Runs koennen das ueber OOXML-Sidecar absichern.
3. `legacy_html`-Hints sind ein kontrollierter Uebergangsmechanismus und sollten in spaeteren Runs pro Scope reduziert werden.

## Annahmen

- Der Compare-Modus deckt Mismatches frueh genug auf, um Scope fuer Scope zu migrieren.
- Die bestehende Legacy-HTML-Ausgabe bleibt bis Run 06+ weiterhin Referenzverhalten.

## Ergebnisse

✅ **AST-Renderpfad end-to-end implementiert**
- Laden -> AST-Build -> Normalization-Passes -> AST-Rendering -> Post-Processing

✅ **Compare-Harness auf echten AST-Renderer umgestellt**
- `DocumentProcessorParityTest` nutzt `AstDocumentProcessor` als AST-Pfad

✅ **Parity-Korpus gruen**
- `tests/Integration/DocumentProcessorParityTest.php` erfolgreich
- komplette Test-Suite weiterhin gruen (117/117)

## Interpretation fuer Run 06

Run 06 trennt den **oeffentlichen AST-Contract** von internen Renderhilfen:

1. `legacy_html`-Hints und andere Render-Interna als **nicht oeffentlich** markieren.
2. Stabilen API-Ausschnitt fuer einbindende Apps definieren (Node-Felder, garantierte Metadaten, Versionierung).
3. Validierungsregeln fuer AST-Contract einziehen (Schema/Golden-Tests), damit Renderer-Refactorings ohne API-Bruch moeglich bleiben.
