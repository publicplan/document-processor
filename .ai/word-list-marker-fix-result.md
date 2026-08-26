## Ergebnis: Fix für Word-Listenmarker im AST

- Verlustfreie Marker-Semantik aus `numbering.xml` ist jetzt im AST verfügbar.
- `resolvedLayout.marker` enthält jetzt strukturiert:
  - `rawNumFmt`
  - `lvlText`
  - `lvlSuffix`
  - `lvlJc`
  - `start`
  - `justification`
  - `font` (optional)
- Bestehende Felder bleiben kompatibel (`format`, `text`, `suffix`, `markerFont`, `restart`, `numFormat`).
- Bullet-Zeichen aus `lvlText` (z. B. `-`, `•`) werden exakt übernommen.
- Zusätzliche Robustheit: Fallback-Matching für Numbering-Snapshots bei `numId`/`abstractNumId`-Abweichungen.

### Geänderte Dateien

- `src/Service/DocumentLoader.php`
- `src/Service/Ast/WordToAstConverter.php`
- `src/Service/Ast/PublicAstSerializer.php` (`AST_VERSION` auf `1.4.0`)
- `tests/Integration/AstListMarkerMetadataTest.php` (neu)
- `tests/Service/DocumentLoaderTest.php`
- `README.md`

### Teststatus

- Zielgerichtete Tests für Marker-Metadaten: grün
- Gesamte Test-Suite: grün
