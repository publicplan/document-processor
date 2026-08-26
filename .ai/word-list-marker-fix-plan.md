## Plan: Verlustfreie Word-Listenmarker im AST

1. ✅ Bestehenden Pfad `numbering.xml -> styleSnapshot -> WordToAstConverter -> resolvedLayout.marker` analysieren und Felder-Lücken identifizieren.
2. ✅ `DocumentLoader::extractNumberingSnapshot()` erweitern:
   - Rohfelder aus `w:lvl` erfassen (`numFmt`, `lvlText`, `suff`, `start`, `lvlJc`)
   - optionalen Marker-Font-Hinweis aus `w:rPr/w:rFonts` erfassen.
3. ✅ `WordToAstConverter::extractNumberingMetadata()` und Marker-Mapping erweitern:
   - neue strukturierte Marker-Felder unter `resolvedLayout.marker.*` ergänzen, alte Felder beibehalten.
   - Bullet-Zeichen aus `lvlText` unverändert durchreichen.
4. ✅ Kurz-Doku ergänzen (PHPDoc + README AST-Metadatenabschnitt).
5. ✅ Tests ergänzen/aktualisieren:
   - Snapshot-Test für neue numbering-Felder
   - AST-Testfälle für Marker "-", "•", decimal+Suffix, lowerLetter/upperRoman
   - gesamte Test-Suite ausführen.
