# Run 11 - Paragraph Formatting Styles (Spacing & Line Height im HTML)

**Status:** Planung & Vorbereitung  
**Datum:** 2026-08-25  
**Kontext:** Feature-Request zur Vollständigkeit der AST→HTML-Stylierung

---

## Ziel

Absatzformate aus DOCX vollständig in den HTML-Output als Inline-Styles übernehmen:
- **Abstände:** `spacingBefore` (margin-top), `spacingAfter` (margin-bottom)
- **Einzüge:** `indentLeft`, `indentRight`, `indentFirstLine`
- **Zeilenhöhe:** `lineHeight` (derzeit noch `null`)
- **Umfang:** Absätze, Listenelemente, Tabellenzellen

---

## Eingaben

- `src/Ast/Node/ParagraphNode.php` (bereits mit Spacing/Indent Properties)
- `src/Service/Ast/WordToAstConverter.php` (Zeilen 182-184: teilweise Extraction)
- `src/Service/Ast/AstHtmlRenderer.php` (Zeile 120: nur `spacingAfter` umgesetzt)
- PhpOffice\PhpWord Paragraph-Style API
- Bestehende Tests

---

## Status quo

### ✅ Bereits in AST
| Property | Quelle | Wert | Einheit |
|----------|--------|------|---------|
| `spacingBefore` | DOCX via `getSpaceBefore()` | Extrahiert | CM |
| `spacingAfter` | DOCX via `getSpaceAfter()` | Extrahiert | CM |
| `indentLeft` | DOCX via `getIndentLeft()` | Extrahiert | CM |
| `indentRight` | DOCX via `getIndentRight()` | Extrahiert | CM |
| `indentFirstLine` | DOCX via `getIndentFirstLine()` | Extrahiert | CM |
| `lineHeight` | – | `null` (TODO) | – |

### ⚠️ Im HTML-Rendering
| Property | Rendering | Scope | Status |
|----------|-----------|-------|--------|
| `spacingBefore` | `margin-top` | Absätze nur | ❌ Nicht implementiert |
| `spacingAfter` | `margin-bottom` | Absätze nur | ✅ Teilweise (Zeile 120) |
| `indentLeft` | `margin-left` oder `padding-left` | – | ❌ Nicht implementiert |
| `indentRight` | `margin-right` oder `padding-right` | – | ❌ Nicht implementiert |
| `indentFirstLine` | `text-indent` | – | ❌ Nicht implementiert |
| `lineHeight` | `line-height` | – | ❌ Nicht implementiert |
| Listenelemente-Spacing | – | `<li>` | ❌ Nicht implementiert |
| Tablenzellen-Spacing | – | `<td>` | ❌ Nicht implementiert |

---

## Aufgaben (3 Phasen)

### Phase 1: Zeilenhöhe aus DOCX extrahieren

1. **PhpOffice\PhpWord API prüfen**
   - Ist `getLineHeight()` oder `getLineSpacing()` vorhanden?
   - Wer set diese Werte im DOCX?
   - Einheiten klären (TWIPS, Percent, Multiplier?)

2. **`WordToAstConverter.php` erweitern** (Zeile 184)
   - Statt `lineHeight: null` → real value extrahieren
   - TWIPS→CM Konvertierung nutzen (falls nötig) oder direkt mit Einheit speichern
   - Diese bei ListItemNode auch prüfen (Zeile 115-120)

3. **Tests hinzufügen**
   - Unit-Test für Zeilenhöhen-Extraction
   - Fixture mit bekanntem DOCX (verschiedene Zeilenhöhen)
   - AST-Validierung

### Phase 2: HTML-Renderer für Paragraph-Styling erweitern

1. **`AstHtmlRenderer::renderParagraph()` erweitern** (Zeile 108–124)
   ```
   Neue Styles:
   - margin-top: spacingBefore cm
   - margin-bottom: spacingAfter cm (bereits teilweise da!)
   - line-height: lineHeight (z.B. "1.5" oder "150%")
   - margin-left: indentLeft cm
   - margin-right: indentRight cm
   - text-indent: indentFirstLine cm
   ```
   
2. **`AstHtmlRenderer::renderListItem()` erweitern** (Zeile 160–176)
   - ListItemNode hat auch Spacing/Indent (von ParagraphNode geerbt)
   - Styles auf `<li>`-Tag anwenden
   
3. **TableCellNode Rendering prüfen**
   - TableCellNode hat auch Paragraph-Properties
   - Cell-Level-Styles anwenden (derzeit nur Border?)

4. **Tests aktualisieren**
   - Bestehende Parity-Tests (sollten nicht brechen)
   - Neue Integration-Tests für Spacing-Output
   - Snapshot-Tests für HTML-Output mit Styles

### Phase 3: Style-Helper & Dokumentation

1. **Style-Builder-Klasse** (optional, für Code-Lesbarkeit)
   ```php
   // Helper für Style-Konvertierung
   class ParagraphStyleBuilder
   ```

2. **README aktualisieren**
   - Feature-Dokumentation
   - HTML-Output-Beispiele
   - CSS-Einheiten erklären

3. **Tests ausführen & validieren**
   - Alle existierenden Tests müssen noch grün sein
   - Neue Tests für Spacing-Features
   - Parity-Harness gegen Fixture-Dokumente

---

## Erzeugte Artefakte (erwartet)

### Code-Änderungen
- `src/Service/Ast/WordToAstConverter.php`
  - Zeile 184: `lineHeight: null` → extrahiert
  - Ggf. ListItemNode-Handling prüfen
  
- `src/Service/Ast/AstHtmlRenderer.php`
  - `renderParagraph()`: Style-Attribute erweitern
  - `renderListItem()`: Style-Attribute hinzufügen
  - ggf. Helper-Methode `buildParagraphStyles()` extrahieren

- `src/Service/Converter/ParagraphIndentHelper.php` (optional)
  - ggf. auch Spacing-Helfer hinzufügen

### Tests
- Tests in `tests/Integration/DocumentProcessorParityTest.php`
- Tests in `tests/Unit/Service/Ast/AstHtmlRendererTest.php` (neu oder erweitert)
- Fixture DOCX mit Spacing-Variationen (falls nicht vorhanden)

### Dokumentation
- README.md (Feature-Beschreibung)
- ADR (Architecture Decision Record) optional

---

## Abhängigkeiten & Annahmen

- PhpOffice\PhpWord hat Methode für Zeilenhöhe (zu validieren)
- CM als Einheit für Abstände bleibt konsistent
- Parity-Tests schlagen nicht fehl durch neue Styles
- Browser-Support für CSS-Properties vorausgesetzt

---

## Verfügbare Test-Fixtures

```
tests/Support/Fixtures/
  - einfache-abstände.docx (zu erstellen?)
  - listen-mit-spacing.docx (zu erstellen?)
  - tabellen-mit-indent.docx (zu erstellen?)
```

---

## Metriken & Erfolgskriterien

✅ **Definition of Done:**
1. Zeilenhöhe wird aus DOCX extrahiert (nicht mehr `null`)
2. `renderParagraph()` gibt alle Spacing-Styles aus
3. `renderListItem()` gibt Spacing-Styles aus
4. Alle bestehenden Tests bleiben grün
5. Parity-Harness schlägt nicht fehl
6. Min. 3 neue Integration-Tests für neue Styles
7. README dokumentiert Feature

**Erwartete Codezeilen:** +100–150 LoC (inkl. Tests)

---

## Nächste Schritte

1. **Schnelle Validierung:** PhpOffice\PhpWord API für Zeilenhöhe prüfen
2. **PoC:** Zeilenhöhe-Extraction in WordToAstConverter (Phase 1)
3. **HTML-Renderer-Update:** (Phase 2)
4. **Tests & Dokumentation:** (Phase 3)

---

## Notizen

- `nullableTwipsToCm()` Helper sollte auch für Zeilenhöhe verfügbar sein
- CSS `line-height` hat keine Einheit (pure Zahl wie `1.5`) oder Prozent
- `text-indent` für first-line Indent könnte alternativ mit `padding-left` + Offset gelöst werden
- Berücksichtigen: unterschiedliche Browser-Rendering für `text-indent` bei RTL/negative Werte
