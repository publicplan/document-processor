# Run 11 - Paragraph Formatting Styles

## Status: 📋 Dokumentation & Planung abgeschlossen

**Erstellt:** 2026-08-25  
**Ort:** `.ai/ast-migration-runs/11-paragraph-formatting-styles.md`

---

## Quick Summary

Feature zur vollständigen Übernahme von Absatzformatierungen (Spacing, Indent, Line Height) aus DOCX ins HTML.

### Scope
- **Was:** Margin, Padding, Line-Height Styles im HTML-Output
- **Wo:** Absätze, Listen, Tablenzellen
- **Wie:** Aus AST (bereits teilweise vorhanden) → HTML Inline-Styles

### 3 Phasen
1. **Zeilenhöhe extrahieren** (WorldToAstConverter)
2. **HTML-Renderer erweitern** (AstHtmlRenderer)
3. **Tests & Dokumentation**

---

## Was ist dokumentiert

✅ **Ziel & Kontext**  
✅ **Status quo** (was funktioniert, was fehlt)  
✅ **Detaillierte Aufgaben** pro Phase  
✅ **Artefakte** (Code-Dateien, Tests)  
✅ **Erfolgskriterien** (Definition of Done)  
✅ **Technische Details** (Einheiten, APIs)  

---

## Nächste Schritte

1. **Phase 1 starten:** PhpOffice\PhpWord API für Zeilenhöhe validieren
2. **PoC:** WordToAstConverter anpassen (ca. 15 Minuten)
3. **Phase 2:** AstHtmlRenderer erweitern (ca. 45 Minuten)
4. **Tests & Verifikation:** (ca. 30 Minuten)

---

## Referenz

- **Run-Dokument:** `/Users/nast/Repos/document-processor/.ai/ast-migration-runs/11-paragraph-formatting-styles.md`
- **Plan (Session):** `/Users/nast/.copilot/session-state/a387d93d-99a5-4071-a680-e13a01dcd08b/plan.md`
