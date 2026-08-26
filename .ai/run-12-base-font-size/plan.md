# Run 12 – Dokument-Basis-Schriftgröße (DOCX → AST)

## Ziel
- Einen deterministischen, expliziten Basiswert `document.baseFontSizePt` im öffentlichen AST bereitstellen.
- Herkunft und Ableitung nachvollziehbar machen (`baseFontSizeSource`, optional `baseFontSizeRaw`).
- Relative Skalierungen auf einen kanonischen Dokument-Basiswert ausrichten.

## Umsetzungsschritte
1. **Datenmodell erweitern**
   - `DocumentNode` um `baseFontSizePt`, `baseFontSizeSource`, `baseFontSizeRaw` ergänzen.
   - Serialisierung additiv, ohne bestehende Felder zu verändern.

2. **DOCX-Ableitungslogik implementieren**
   - In `DocumentLoader` robuste Ermittlung mit Priorität:
     1) `docDefaults` (`w:sz`, sekundär `w:szCs`)
     2) Normal-/Body-Paragraph-Style inkl. `basedOn`-Auflösung
     3) häufigste Body-Run-Größe (Tabellen/TOC ausgeschlossen)
     4) Fallback `12pt`
   - Half-point → pt korrekt umrechnen.

3. **AST-Verdrahtung**
   - Ermittelte Basisgröße inkl. Source/Raw in `AstDocumentProcessor` auf `DocumentNode` setzen.
   - `ConversionContext` weiter mit Basisgröße für bestehende Scale-Berechnung versorgen.

4. **Tests ergänzen**
   - Abdeckung der fünf geforderten Fälle (docDefaults, Normal-Style, Body-vs-Table, kompletter Fallback, Half-point-Umrechnung).
   - AST-Test: `document.baseFontSizePt` ist explizit vorhanden.

5. **Dokumentation**
   - Technische Doku (README/Changelog) um Quellenkette, Bedeutung von `baseFontSizePt` und Bezug relativer Größen ergänzen.
