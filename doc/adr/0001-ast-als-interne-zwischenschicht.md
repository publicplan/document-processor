# ADR-0001: AST als interne Zwischenschicht

**Status:** Accepted  
**Datum:** 2026-08-22  
**Implementiert in:** v2.0.0

## Kontext

Die ursprüngliche Pipeline konvertierte DOCX-Elemente direkt zu HTML: `DocumentProcessor` rief für jedes PhpWord-Element einen `ElementConverter` auf und verkettete die HTML-Strings. Das funktionierte, hatte aber strukturelle Nachteile:

- Globale Sonderregeln (Spacer-Paragraphen, Listen-Gruppierung, Border-Blöcke) lagen verteilt im `DocumentProcessor` und in den Convertern
- Konsumierende Apps mussten HTML parsen, um strukturelle Information zurückzugewinnen
- Testbarkeit einzelner Transformationsschritte war schwierig, weil kein intermediäres Datenformat existierte

## Entscheidung

Zwischen DOCX-Parsing und HTML-Rendering wird ein interner **Word AST** (`DocumentNode`-Baum) als Zwischenschicht eingeführt.

Der AST ist:
- **syntaxneutral** – keine frühe Interpretation von Template-Syntax oder App-Semantik
- **verlustfrei** – alle für HTML oder App-Konsum relevanten Word-Signale bleiben erhalten
- **explizit strukturiert** – Nodes für Dokument, Abschnitte, Paragraphen, Listen, Tabellen, Inline-Tokens

Die bestehende HTML-Ausgabe bleibt vollständig abwärtskompatibel; der AST-Renderer reproduziert das bisherige HTML.

## Konsequenzen

**Positiv:**
- Globale Transformationen können als explizite, testbare Normalization-Passes modelliert werden (→ ADR-0002)
- Apps können den AST direkt konsumieren, ohne HTML zu parsen (→ ADR-0003)
- Template-Annotation lässt sich als optionaler Pass nachschalten (→ ADR-0004)

**Negativ/Risiken:**
- Zwei Rendering-Pfade müssen parallel korrekt gehalten werden (Legacy-HTML und AST-HTML) bis vollständige Parität erreicht ist
- Bekannte kleine HTML-Paritätsabweichungen bei `borders` und `textbox` bleiben vorerst dokumentiert und akzeptiert
