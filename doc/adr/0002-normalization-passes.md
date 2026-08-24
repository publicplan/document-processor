# ADR-0002: Normalization Passes als explizite Transformationsschritte

**Status:** Accepted  
**Datum:** 2026-08-22  
**Implementiert in:** v2.0.0

## Kontext

Die Legacy-Pipeline enthielt zahlreiche implizite, dokumentweite Sonderregeln direkt im `DocumentProcessor` (z. B. Spacer-Paragraphen-Erkennung, Listen-Gruppierung, Border-Block-Handling). Diese Regeln waren schwer isoliert zu testen und versteckten strukturelle Absicht in prozeduralem Code.

## Entscheidung

Alle dokumentweiten Transformationen werden als geordnete, explizite **Normalization Passes** auf dem AST modelliert. Jeder Pass hat eine klar definierte Eingabe und Ausgabe und verändert den Baum in genau einer Dimension.

Implementierte Passes in Ausführungsreihenfolge:

1. `SpacerParagraphPass` – erkennt leere Absätze zwischen Listenpunkten gleicher `numId` und überträgt ihr Spacing als `margin-bottom` auf den vorherigen `ListItemNode`
2. `ListStructurePass` – gruppiert aufeinanderfolgende `ListItemNode`s zu `ListNode`-Containern
3. `ListNormalizationPass` – verschachtelt `ListItemNode`s korrekt nach Tiefe, auch bei fehlerhaft strukturierten Eingaben
4. `TextCoalescingPass` – fasst aufeinanderfolgende `TextNode`s mit gleicher Formatierung zusammen (reduziert Komplexität für App-Konsum)
5. `TemplateAnnotationPass` – optionaler Pass, nur aktiv wenn `ProcessingOptions->templateSyntaxProfile` gesetzt (→ ADR-0004)

## Konsequenzen

- Jeder Pass ist isoliert unit-testbar
- Reihenfolgeabhängigkeiten sind explizit und dokumentiert
- Neue Transformationen können als zusätzliche Passes ergänzt werden, ohne bestehende Passes zu berühren
- Der `DocumentProcessor` delegiert alle Strukturtransformationen an die `NormalizationPipeline`; eigene Sonderlogik entfällt dort
