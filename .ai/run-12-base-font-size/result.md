# Run 12 – Ergebnis: Dokument-Basis-Schriftgröße

## Implementiert

1. **Öffentlicher AST erweitert**
   - `document.baseFontSizePt` (immer vorhanden, `float`, pt)
   - `document.baseFontSizeSource` (Quelle: `docDefaults|normalStyle|styleChain|bodyRuns|fallback`)
   - `document.baseFontSizeRaw` (optionaler Debug-Payload)

2. **Deterministische Ableitung in `DocumentLoader`**
   - Neue zentrale Ermittlung: `extractDocumentBaseFontSizeMetadata(string $filePath): array`
   - Prioritätskette (erste valide Quelle gewinnt):
     1) `styles.xml/docDefaults` (`w:sz`, sekundär `w:szCs`)
     2) `Normal`/primärer Body-Style inkl. `basedOn`-Kette
     3) häufigste Body-Run-Schriftgröße aus `document.xml` (Tabellen/TOC ausgeschlossen)
     4) harter Fallback `12pt`
   - Korrekte Umrechnung von half-points (`w:sz`) nach pt.

3. **Verdrahtung in die AST-Pipeline**
   - `AstDocumentProcessor` übernimmt Basisgröße + Quelle + Raw-Metadaten aus `DocumentLoader` und setzt sie auf `DocumentNode`.
   - `ConversionContext->defaultFontSize` bleibt konsistent auf derselben Basisgröße.

4. **Versionierung**
   - `PublicAstSerializer::AST_VERSION` auf `1.5.0` erhöht (additive Contract-Erweiterung).

## Tests ergänzt/angepasst

- `tests/Service/DocumentLoaderTest.php`
  - `docDefaults=12pt` ⇒ `12`
  - ohne `docDefaults`, Normal-Style `11pt` ⇒ `11`
  - widersprüchliche Größen (Body `12`, Tabelle `9`) ohne Styles ⇒ `12` (Body-priorisiert)
  - komplett fehlende Größen ⇒ Fallback `12`
  - half-point-Umrechnung (`22 => 11pt`)
- `tests/Service/Ast/AstDocumentProcessorApiTest.php`
  - öffentlicher AST enthält explizit `baseFontSizePt` und `baseFontSizeSource`
- `tests/Integration/DocumentProcessorParityTest.php`
  - Mock-Callbacks auf variadische Signatur angepasst (kompatibel zur erweiterten Loader-Methode)

## Doku aktualisiert

- `README.md`
  - Neuer Abschnitt zur Basis-Schriftgröße im öffentlichen AST:
    - ausgewertete Quellen
    - Prioritätsreihenfolge
    - Bedeutung von `baseFontSizePt`
    - Bezug relativer Größen auf `document.baseFontSizePt`
- `CHANGELOG.md`
  - Eintrag zur neuen AST-Erweiterung und Ableitungslogik
