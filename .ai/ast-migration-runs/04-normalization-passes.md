# Run 04 - Normalization passes

## Ziel

Die heute impliziten, dokumentweiten Strukturregeln als explizite AST-Passes modellieren.

## Eingaben

- `03-word-ast-modell.md`
- globale Legacy-Regeln aus `DocumentProcessor`
- lokale Renderregeln aus den Convertern

## Aufgaben

1. ✅ Pass-Reihenfolge definieren.
2. ✅ Jede heutige globale Sonderregel genau einem Pass zuordnen.
3. ✅ Festlegen, welche Passes semantisch neutral und welche renderorientiert sind.

## Implementierte Passes (7/7)

### Pass-Pipeline (Reihenfolge ist kritisch)

```
1. ListNormalizationPass ✅
   - Eingabe: AST mit einzelnen ListItemNodes
   - Ausgabe: ListItemNodes sind in ListNode-Container verschachtelt
   - Invarianten: 
     * ListItemNodes immer in ListNodes
     * Keine direkten ListItemNodes in Sections
     * ListNodes gruppieren Items mit gleicher numId/numFormat

2. ListContinuationPass ✅
   - Eingabe: AST mit ListNodes aus Pass 1
   - Ausgabe: startNumeration ist gesetzt für Listenfortsetzungen
   - Invarianten:
     * startNumeration dokumentiert Numerierungssprünge
     * Listenfortsetzungen nach Unterbrechung sind erfasst

3. ListSpacerPass ✅
   - Eingabe: AST mit ListNodes
   - Ausgabe: Leere Absätze zwischen ListNodes entfernt
   - Invarianten:
     * Keine leeren ParagraphNodes direkt zwischen ListNodes
     * Spacing dokumentiert auf ListItemNodes

4. BorderGroupingPass ✅
   - Eingabe: AST mit Absätzen
   - Ausgabe: Absätze mit gleichen Border-Stilen in BorderGroupNode-Container
   - Invarianten:
     * BorderGroupNodes enthalten nur Absätze mit identischen Borders
     * Border-Styles nicht doppelt (im Container + Child)

5. EmptyParagraphPass ✅
   - Eingabe: AST mit möglicherweise redundanten leeren Absätzen
   - Ausgabe: Leere Absätze sind normalisiert
   - Invarianten:
     * Trailing-Absätze am Ende entfernt
     * Absätze innerhalb von Listen respektiert

6. InlineScalePass ✅
   - Eingabe: AST mit möglicherweise verschachtelten ScaleNodes
   - Ausgabe: ScaleNodes flach, keine Verschachtelung
   - Invarianten:
     * Scale kann nicht Scale enthalten
     * Struktur bleibt für Render-Hinweise erhalten

7. HangingIndentPass ✅
   - Eingabe: AST mit Absätzen
   - Ausgabe: Hanging-Indents in RenderHints dokumentiert
   - Invarianten:
     * Nur Metadaten-Anreicherung, keine strukturelle Änderung
     * RenderHints::hanging_indent gesetzt für indentFirstLine < indentLeft
```

## Erzeugte Artefakte

### Implementierte Dateien

```
src/Ast/Pass/
  AstPass.php (Interface)
  AstNormalizationPipeline.php (Orchestrator)
  AstNormalizationException.php (Exception)
  ListNormalizationPass.php ✅
  ListContinuationPass.php ✅
  ListSpacerPass.php ✅
  BorderGroupingPass.php ✅
  EmptyParagraphPass.php ✅
  InlineScalePass.php ✅
  HangingIndentPass.php ✅
  NormalizationPipelineFactory.php (Standard-Pipeline)

Erweiterte Node-Klassen:
  SectionNode::setParagraphs() (für Pass-Operationen)
  ParagraphNode::setChildren()
  ScaleNode::setChildren()
  ListItemNode::setStartNumeration()

Metadaten-Verbesserungen:
  RenderHints::set() (mutable für Pass-Operationen)
```

### Tests

```
tests/Unit/Ast/Pass/NormalizationPassesTest.php
- test_list_normalization_pass_groups_adjacent_items() ✅
- test_list_normalization_pass_creates_separate_lists_for_different_numids() ✅
- test_empty_paragraph_pass_removes_trailing_empty_paragraphs() ✅
- test_normalization_pipeline_factory_creates_all_passes() ✅
- test_pipeline_normalizes_document_end_to_end() ✅

Alle AST-Tests: 14/14 ✅
Komplette Test-Suite: 117/117 ✅
```

## Festgezogene Entscheidungen

1. **Pass-Reihenfolge ist immutable**: Listen werden VOR Borders normalisiert, um zu verhindern, dass ListSpacerPass Border-Gruppen zerstört.

2. **Jeder Pass hat eine Verantwortung**: Pass-Namen und Beschreibungen sind eindeutig. Keine Cross-Concerns.

3. **Keine Template-Semantik in Passes**: Passes arbeiten nur mit Struktur und Metadaten, nicht mit App-spezifischen Interpretationen (Templates, Bedingungen, etc.)

4. **Passes sind debugbar**: AstNormalizationPipeline dokumentiert jeden Pass mit Name, Beschreibung, Success-Status und Error-Meldungen.

5. **RenderHints sind mutable während Normalisierung**: Erlaubt Metadaten-Anreicherung (z.B. HangingIndentPass) ohne Struktur zu ändern.

6. **Node-Klassen erweitert für Pass-Operationen**:
   - `setParagraphs()` statt Array-Mutation
   - `setChildren()` für Paragraph/Scale
   - `setStartNumeration()` für ListItem
   - Alle Changes sind explizit und dokumentiert

## Mapping: Legacy-Regel → Pass

| Legacy-Regel | Quelle | Pass | Aktion |
|---|---|---|---|
| Listenfortsetzung mit numId-Tracking | DocumentProcessor::$listContinuationMap | ListContinuation | startNumeration setzen |
| Border-Gruppierung | DocumentProcessor::$openBorderSignature | BorderGrouping | BorderGroupNode erstellen |
| Spacer-Absätze zwischen Listen | DocumentProcessor::isSpacerParagraph() | ListSpacer | Entfernen |
| Hanging-Indents | TextRunElementConverter::buildHangingIndentHtml() | HangingIndent | RenderHints markieren |
| Leere Absätze am Ende | DocumentProcessor (implizit) | EmptyParagraph | Entfernen |
| Scale-Verschachtelung | (Keine heute, Vorsorge) | InlineScale | Flattening |
| Listengruppierung | DocumentProcessor (implizit in Converter) | ListNormalization | ListNode erstellen |

## Offene Punkte

1. **BorderGroupingPass Stil-Extraktion**: Heute nutzt BorderGroupingPass `resolvedStyle` aus den Metadaten. Wenn resolvedStyle nicht vorhanden ist, funktioniert der Pass, aber findet keine Borders. Zukünftig: `OoxmlSidecarLoader` muss styles.xml parsen und `resolvedStyle` füllen.

2. **ListSpacerPass Heuristik**: Pass erkennt Spacer nur zwischen zwei ListNodes. Spaetere Laufs könnten die Heuristik verfeinern (z.B. auf Spacing-Attribute prüfen).

3. **Revision-Cleanup verzögert**: `RevisionCleanupPass` (optional) nicht implementiert. Wird in Run 06 bei Bedarf hinzugefügt.

4. **Performance bei großen Dokumenten**: Keine Optimierungen für sehr große ASTs. Spaetere Laufs könnten Streaming oder Visitor-Pattern implementieren.

## Annahmen

- Pass-Reihenfolge ist optimal für die meisten Dokumente
- Legacy-Verhalten ist in diesen 7 Passes vollständig abgebildet
- Keine versteckten Strukturheuristiken in Converter-Klassen, die Passes duplizieren

## Ergebnisse

✅ **Alle 7 Pflicht-Passes implementiert**
- Klasse AstPass (Interface)
- Klasse AstNormalizationPipeline (Orchestrator)
- 7 spezifische Pass-Implementierungen
- Factory für Standard-Pipeline

✅ **Pass-Tests alle grün**
- 5 neue Unit-Tests in NormalizationPassesTest.php
- Alle 14 AST-Tests bestehen
- Keine Regressions (117/117 komplette Tests)

✅ **Node-Klassen erweitert**
- SectionNode, ParagraphNode, ScaleNode, ListItemNode angepasst
- RenderHints mutable für Pass-Operationen

✅ **Legacy-Regeln zugeordnet**
- Jede Normalisierungs-Regel aus DocumentProcessor hat einen Pass
- Mapping dokumentiert in Tabelle oben

✅ **Pipeline debugbar**
- NormalizationPipeline.normalize() gibt Pass-Details zurück
- Factory dokumentiert Reihenfolge inline

## Interpretation fuer Run 05

Run 05 implementiert den **HTML-Renderer für den normalisierten AST**. Er:

1. Konsumiert den normalisierten AST aus Run 04 (mit allen ListNodes, BorderGroupNodes, RenderHints)
2. Nutzt die RenderHints und Metadaten als Leitfaden (nicht als Interpretationen)
3. Erzeugt HTML, das mit dem Legacy-Renderer paritätstreu ist
4. Darf KEINE neue Strukturheuristik einfuehren (z.B. keine BorderGrouping-Heuristik)
5. Nutzt die AST-Struktur als Quelle der Wahrheit

### Anforderungen für Run 05

- HTML-Renderer-Klasse (oder mehrere Render-Module)
- Unit-Tests gegen Parity-Korpus im Compare-Harness
- Keine neuen AST-Transformationen (nur Rendering)
- Rendering-Output ist stabiler und dokumentierter als heute
