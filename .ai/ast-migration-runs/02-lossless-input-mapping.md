# Run 02 - Lossless input mapping

## Ziel

Festlegen, welche Signale aus PhpWord und direkt aus OOXML benoetigt werden, um spaeter einen verlustfreien AST zu bauen.

## Eingaben

- `01-baseline-und-parity-harness.md`
- `DocumentLoader`
- `DocumentProcessor`
- relevante Converter
- vorhandene Tests fuer Breaks, Listen, Borders, Tabellen

## Aufgaben

1. PhpWord-Sicht und OOXML-Sicht gegeneinander abgleichen.
2. Kritische Verluststellen identifizieren.
3. Sidecar-Konzept fuer `document.xml`, `styles.xml`, `numbering.xml` beschreiben.
4. Source-Mapping fuer AST-Nodes definieren.

## Kritische Signale

- `xml:space="preserve"`
- Mehrfachspaces und NBSP
- `<w:tab/>`
- `<w:br/>`
- leere `<w:p>`
- Revisionen und geloeschte Inhalte
- Listen-Metadaten, Nummerierungsstart, Tiefe
- Absatz- und Border-Informationen

## Erzeugte Artefakte

- ✅ Mapping-Tabelle `Word-Signal -> Datenquelle -> AST-Feld` (30+ Signale kategorisiert)
- ✅ Source-Ref-Schema:
  - `part` (document|styles|numbering)
  - `sectionIndex` (0-based)
  - `elementIndex` (0-based)
  - `xmlPath` (optional, XPath zu Element)
  - `xmlAttributes` (kritische Attribute als JSON)
- ✅ OOXML-Sidecar-Entscheidungen dokumentiert
  - **document.xml**: xml:space, Tabs, Track Changes (OBLIGATORISCH)
  - **styles.xml**: Default Font, Indentation (OBLIGATORISCH)
  - **numbering.xml**: List Formats, Restart-Nummern (OBLIGATORISCH)
- ✅ Verlustfreiheits-Strategie definiert (Kritikalität: Rot/Gelb/Grün)

## Festgezogene Entscheidungen

1. **OOXML-Sidecars sind OBLIGATORISCH**.
   - PhpWord verliert kritische Informationen: `xml:space="preserve"`, Track Changes Metadaten, Nummerierungs-Restart
   - Ohne Sidecars ist der AST nicht verlustfrei

2. **Whitespace ist NICHT NORMALISIERBAR**.
   - `xml:space="preserve"` muss explizit markiert werden
   - Mehrfachspaces und NBSPs müssen buchstäblich gespeichert werden
   - HTML-Paritätsvergleiche sind String-Paritätsvergleiche

3. **Numerierung erfordert KOMPLEX-Lookup**.
   - ListItemRun: NumId + Depth (PhpWord)
   - Format & LevelText: numbering.xml (SIDECAR)
   - Restart-Nummer: document.xml Attribut `w:numStart` (SIDECAR)

4. **Track Changes Annotation (nicht Evaluierung)**.
   - AST speichert `deleted`, `inserted` als boolean Flags
   - Optional: Autor, Datum (Phase 2)
   - Apps entscheiden über Rendering

5. **Three-Part Sidecar Strategy**:
   - **document.xml**: XML-Attribute (xml:space), Inline-Elemente (Tab, Break), Revisions
   - **styles.xml**: Style-Definitionen, Default Font-Size
   - **numbering.xml**: List Format Definitions & Abstract Nummerierungen

6. **Source-References vorbereitet, aber nicht erzwungen**.
   - Schema definiert, Implementierung in Run 03
   - Optional für Debugging & Template-Annotation

## Offene Punkte

1. **OoxmlSidecarLoader Testability**
   - Wie wird Sidecar-Laden getestet? Mit echten DOCX-Dateien oder Mock-XML?
   - Fehlerbehandlung bei fehlenden Parts (z.B. numbering.xml bei Nicht-Listen)?

2. **Performance und Caching**
   - ZipArchive wird potentiell 3x geöffnet (document, styles, numbering)
   - Sollte es gepuffert/gecacht werden? Oder durchlaufender Parse?

3. **Real-World-Vorlagen für Phase 2**
   - Parity-Korpus hat nur Programmatic PhpWord-Dokumente
   - Sollen echte Word-Dokumente als Golden Fixtures hinzugefügt werden?

4. **Header/Footer Timing**
   - Gehören Header/Footer in Phase 1 oder Phase 2 des AST?
   - Sind separate Sections oder Teile der Sections?

5. **TextBox Positioning**
   - Wird nur für HTML-Paritäet benötigt oder auch AST-intern?
   - Verzögern bis Phase 2 oder früher priorisieren?

## Annahmen

- PhpWord 0.18.x wird weiterhin als primäre Abstraktionsschicht genutzt
- OOXML-Zugriff erfolgt lokal über ZipArchive (keine Remote-Ressourcen)
- Sidecar-Laden ist fehlertoleranz: fehlende Parts loggen, aber nicht abbrechen
- AST wird als PHP-Datenstruktur (nicht XML/JSON) modelliert; Serialisierung kommt später
- Nicht jede OOXML-Eigenschaft muss Phase 1 sein, aber alle kritischen Signal-Quellen müssen identifiziert
- Legacy-Renderer im Compare-Modus wird Baseline; AST-Renderer wird gegen ihn validiert

## Ergebnisse

✅ **Phase 1: Input-Vollständigkeit** abgeschlossen
- 30+ Word-Signale kategorisiert nach Quelle (PhpWord vs. OOXML)
- Kritikalität bewertet: 6 Rot (kritisch), 11 Gelb (hoch), 8+ Grün (medium)
- Sidecar-Strategie dokumentiert: 3 obligatorische Parts mit Extraktionspfaden
- Source-Reference-Schema definiert (Ready für Run 03 Implementation)

✅ **Verlustfreiheit validiert gegen Parity-Korpus**
- Breaks, Listen, Borders, Tabellen: PhpWord + Sidecars zusammen hinreichend
- Whitespace/Tabs: Require SIDECAR document.xml
- Track Changes: Require SIDECAR document.xml + PhpWord
- Numerierung: Require SIDECAR numbering.xml + PhpWord

✅ **AST-Datenstruktur skizziert**
- Textrun: content, preserveSpace, bold, italic, underline, fontSize, deleted, sourceRef(optional)
- ListItem: numId, depth, numFormat, startNumeration, sourceRef(optional)
- Tab, Break, Table, TextBox: Detailed Felder definiert
- Container-Hierarchie vorbereitet (Section > Paragraph > TextSpan)

## Interpretation für Run 03

Run 03 entwirft auf Basis dieser Mapping-Tabelle ein **lossless, syntaxneutrales AST-Datenmodell**.

### Anforderungen für Run 03

1. **AST-Datentypen**
   - TextRun, ListItem, Tab, Break, Table, TextBox als dedizierte Klassen
   - Felder müssen 1:1 mit Mapping-Tabelle korrespondieren
   - Keine spekulativen Felder – nur dokumentierte Datenquellen

2. **Container-Struktur**
   - Section → Paragraph → TextSpan (oder Inline-Elemente)
   - Klare Schachtelungsregeln

3. **Enums & Value Objects**
   - `TrackChangeType`: none, inserted, deleted
   - `ListFormat`: bullet, number, roman, etc. (from numbering.xml)
   - `PreserveSpace`: boolean oder explicit enum
   - `SourceReference`: vollständig implementiert

4. **Serialisierbarkeit**
   - AST muss zu JSON/Array/Binary serialisierbar sein
   - Für Caching und Übergabe zwischen Prozessen

5. **Legacy-Renderer-Kompatibilität**
   - AST wird als **Input** für neuen Renderer (nicht nur Datenspeicher)
   - Renderer konsumiert AST, nicht PhpWord
   - Compare-Harness wird mit AST-Renderer gefüttert (als Closure)

### Scope für Run 03

**Umgesetzt**:
- Datentypen für kritische Signale (Rot/Gelb in Mapping-Tabelle)
- Container-Hierarchie
- Source-Reference-Framework
- Serialisierungs-Interface

**Nicht in Run 03** (verzögert bis Phase 2):
- OoxmlSidecarLoader Implementierung (nur Schema/Interface)
- TextBox Positioning Details
- Header/Footer Integration
- Performance-Optimierungen

### Success Criteria für Run 03

- Mapping-Tabelle → AST-Datentypen: 1:1 Entsprechung nachweisbar
- Parity-Korpus-Testfälle: Für jeden Testfall eine gültige AST-Instanz konstruiert
- Source-References: Optional aber vollständig vorbereitet (keine Änderungen in Run 04 nötig)
- AST ist JSON-serialisierbar
