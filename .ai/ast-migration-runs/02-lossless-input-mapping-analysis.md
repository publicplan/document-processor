# Run 02 - Lossless Input Mapping - Detaillierte Analyse

## Analyseverfahren

### PhpWord-Analyse (Eingabeebene)

Die aktuelle Pipeline nutzt PhpWord 0.18.x als Abstraktionsschicht über OOXML. Der DocumentProcessor 
liest die Struktur über die PhpWord-API und konvertiert direkt zu HTML.

**Extraktionspunkte (Quellen):**
1. `Section.getElements()` - TextRun, ListItemRun, TextBreak, Table, TextBox
2. `TextRun` - Absatz mit Textlauf-Elementen
3. `TextRun.getElements()` - Text, Link, TextBreak, Break, Tab
4. `Style` - Font, Paragraph, Border, etc.
5. `DocumentLoader.extractDocumentDefaultFontSize()` - OOXML/styles.xml direkt
6. `DocumentLoader.hasUnacceptedChanges()` - OOXML Track Changes (direkt via ZIP)

### OOXML-Sidecar-Prinzipien

Ein OOXML-Sidecar wird **benutzt**, wenn:
- PhpWord die Struktur nicht zugänglich macht
- XML-Attribute verloren gehen (z.B. `xml:space="preserve"`)
- Revisions-/Track-Changes-Details nötig sind
- Numerierung oder Container-Relationen komplex sind

Ein OOXML-Sidecar ist **optional**, wenn:
- PhpWord die Information vollständig bereithält
- Die Information nicht kritisch für HTML-Parität ist

---

## Kritische Signal-Mapping-Tabelle

| Word-Signal | PhpWord verfügbar? | OOXML-Zugriff | Datenquelle für AST | AST-Feld | Kritikalität |
|---|---|---|---|---|---|
| **Whitespace-Semantik** |
| `xml:space="preserve"` | ❌ Nein | ✅ `document.xml`: `<w:t xml:space="preserve">` | **SIDECAR** document.xml | `Text.preserveSpace` | 🔴 KRITISCH |
| Mehrfachspaces | ⚠️ Partiell | ✅ document.xml | **SIDECAR** document.xml + Text-Länge | `Text.content` (nicht normalisiert) | 🔴 KRITISCH |
| Non-Breaking Spaces (NBSP) | ✅ Ja | ✅ document.xml: `&#x00A0;` | TextElementConverter zeigt `\xC2\xA0` | `Text.content` | 🔴 KRITISCH |
| **Inline-Grenzen** |
| `<w:tab/>` (Tabulatoren) | ✅ Ja | ✅ document.xml | PhpWord → TextRun.getElements() | `Tab.position` (optional) | 🟡 HOCH |
| `<w:br/>` (Zeilenumbruch) | ✅ Ja | ✅ document.xml | PhpWord → TextRun.getElements() | `LineBreak.type` | 🟡 HOCH |
| Soft Hyphens (`\xAD`) | ⚠️ Unklar | ✅ document.xml | **SIDECAR** document.xml | `Text.hasSoftHyphen` | 🟡 HOCH |
| **Absatz-Struktur** |
| Leere `<w:p>` | ✅ Ja | ✅ document.xml | PhpWord → TextBreak | `Paragraph.empty` (markiert) | 🟡 HOCH |
| Absatz-Abstände (space-before/after) | ✅ Ja | ✅ styles.xml | PhpWord → Paragraph.getSpaceAfter() | `Paragraph.spaceAfter` | 🟡 HOCH |
| Paragraph-Indentation (left/hanging/firstLine) | ✅ Ja | ✅ styles.xml | PhpWord → Paragraph.getIndentation() | `Paragraph.indent.*` | 🟡 HOCH |
| **Listen-Struktur** |
| List NumId | ✅ Ja | ✅ document.xml | PhpWord → ListItemRun.getNumId() | `ListItem.numId` | 🔴 KRITISCH |
| List Level | ✅ Ja | ✅ document.xml | PhpWord → ListItemRun.getDepth() | `ListItem.depth` | 🔴 KRITISCH |
| Nummerierungs-Metadaten | ⚠️ Partiell | ✅ numbering.xml | **SIDECAR** numbering.xml | `ListItem.numFormat, levelStart` | 🔴 KRITISCH |
| Restart-Nummer | ⚠️ Unklar | ✅ numbering.xml | **SIDECAR** numbering.xml | `ListItem.startNumeration` | 🟡 HOCH |
| **Formatierungen** |
| Bold, Italic, Underline | ✅ Ja | ✅ document.xml | PhpWord → Font.getStyle() | `TextRun.bold, italic, underline` | 🟡 HOCH |
| Font-Größe | ✅ Ja | ✅ styles.xml | PhpWord → Font.getSize() | `TextRun.fontSize` | 🟡 HOCH |
| Font-Farbe | ✅ Ja | ✅ document.xml | PhpWord → Font.getColor() | `TextRun.color` (Hex) | 🟢 MITTEL |
| Strikethrough (Gelöscht) | ✅ Ja | ✅ document.xml | PhpWord → Font.isStrikethrough() | `TextRun.deleted` | 🔴 KRITISCH |
| **Border-Styling** |
| Border Size/Style/Color | ✅ Ja | ✅ styles.xml | PhpWord → Paragraph.getBorder*() | `Paragraph.border.*` | 🟡 HOCH |
| **Revision & Track Changes** |
| Deleted Content (`<w:del>`) | ✅ Ja* | ✅ document.xml | PhpWord (indirekt) + SIDECAR | `TextRun.trackChangeType` | 🔴 KRITISCH |
| Inserted Content (`<w:ins>`) | ✅ Ja* | ✅ document.xml | PhpWord (indirekt) + SIDECAR | `TextRun.trackChangeType` | 🔴 KRITISCH |
| Track Changes Author/Date | ❌ Nein | ✅ document.xml | **SIDECAR** document.xml | `TextRun.trackChangeMetadata` | 🟡 HOCH |
| **Tables** |
| Table Rows/Cells | ✅ Ja | ✅ document.xml | PhpWord → Table.getRows() | `Table.rows[]` | 🔴 KRITISCH |
| Cell Borders | ✅ Ja | ✅ document.xml | PhpWord → Cell.getStyle() | `TableCell.border.*` | 🟡 HOCH |
| Cell Background | ✅ Ja | ✅ document.xml | PhpWord → Cell.getStyle().getShd() | `TableCell.bgColor` | 🟢 MITTEL |
| **TextBoxes & Shapes** |
| TextBox Content | ✅ Ja | ✅ document.xml | PhpWord → TextBox.getElements() | `TextBox.content` | 🟡 HOCH |
| TextBox Positioning | ⚠️ Partiell | ✅ document.xml | **SIDECAR** document.xml | `TextBox.position` (Optional für Phase 1) | 🟢 MITTEL |
| **Headers/Footers** |
| Header Content | ⚠️ Unklar | ✅ header*.xml | **SIDECAR** header*.xml | `Section.header` | 🟢 MITTEL |
| Footer Content | ⚠️ Unklar | ✅ footer*.xml | **SIDECAR** footer*.xml | `Section.footer` | 🟢 MITTEL |

---

## Kritikalitäts-Klassifizierung

### 🔴 KRITISCH für HTML-Parität (Phase 1 obligatorisch)
1. **xml:space="preserve"** - Bestimmt exakte Whitespace-Semantik → HTML-Diff bei Fehlen
2. **Strikethrough (Deletion)** - Semantische Unterscheidung zwischen Löscht und sichtbar
3. **List NumId & Depth** - Struktur und Nummerierung ändern sich radikal
4. **Numbering Metadata** - Format und Startnummern fehlen ohne numbering.xml
5. **Track Changes (Ins/Del)** - Entscheidend für Dokumente mit Revisionen
6. **Table Structure** - Zellen/Zeilen sind Kern-Struktur

### 🟡 HOCH (Phase 1 stark empfohlen)
1. **Leere Absätze & Umbrüche** - Visuelle Struktur
2. **Absatz-Abstände & Indentation** - CSS-Rendering
3. **Border-Styling** - Visuelles Erscheinungsbild
4. **Tabulatoren & Zeilenumbrüche** - Inline-Semantik
5. **Font Größe, Bold, Italic, Underline** - Formatierung
6. **Soft Hyphens, Restart-Nummern** - Spezialcases

### 🟢 MITTEL (Phase 2/3)
1. **TextBox Positioning** - Für HTML-Paritätwichtiger als für AST-Struktur
2. **Headers/Footers** - Optionale Sections
3. **Font-Farben, Cell-Background** - Dekorativ

---

## OOXML-Sidecar-Strategie

### Sidecar 1: `document.xml` (KOMPLETTANSICHT)

**Zweck**: Zugriff auf XML-Attribute und Track Changes, die PhpWord nicht offenlegt.

**Kritische Elemente**:
```xml
<!-- Whitespace-Preservation -->
<w:t xml:space="preserve">  text  </w:t>

<!-- Tabulatoren -->
<w:tab/>

<!-- Zeilenumbrüche -->
<w:br/>

<!-- Revisionen (Track Changes) -->
<w:ins w:id="..." w:author="..." w:date="...">
  <w:r>...</w:r>
</w:ins>
<w:del w:id="..." w:author="..." w:date="...">
  <w:r>...</w:r>
</w:del>

<!-- Textbox-Definitionen (für Phase 2) -->
<w:txbx>
  <w:p>...</w:p>
</w:txbx>
```

**Extraktionsstrategie**:
- Nach Laden durch PhpWord separat via ZipArchive öffnen
- DOMXPath-Query für kritische Pfade
- Mapping: `sectionIndex -> elementIndex -> xmlPath` zur Korrelation mit PhpWord-Struktur

### Sidecar 2: `styles.xml` (STIL-REFERENZEN)

**Zweck**: Vollständige Stil-Definitionen für Absätze, Schriftarten, Nummerierung.

**Kritische Elemente**:
```xml
<!-- Paragraph Styles mit Indentation -->
<w:style w:type="paragraph" w:styleId="...">
  <w:pPr>
    <w:ind w:left="..." w:hanging="..." w:firstLine="..."/>
    <w:spacing w:before="..." w:after="..." w:line="..."/>
  </w:pPr>
</w:style>

<!-- Document Defaults -->
<w:docDefaults>
  <w:rPrDefault>
    <w:rPr>
      <w:sz w:val="..."/> <!-- Font-Größe in Half-Points -->
    </w:rPr>
  </w:rPrDefault>
</w:docDefaults>
```

**Extraktionsstrategie**:
- Bereits teilweise durch DocumentLoader umgesetzt
- Erweitern um vollständige Indent-/Spacing-Information

### Sidecar 3: `numbering.xml` (LISTEN-SEMANTIK)

**Zweck**: Komplette Nummerierungs-Definitionen, Restart-Punkte, Level-Formate.

**Kritische Elemente**:
```xml
<!-- Abstract Nummerierung -->
<w:abstractNum w:abstractNumId="0">
  <w:multiLvlType w:val="multiLevel"/>
  <w:lvl w:ilvl="0">
    <w:numFmt w:val="bullet"/>
    <w:lvlText w:val="•"/>
    <w:lvlJc w:val="left"/>
  </w:lvl>
</w:abstractNum>

<!-- Konkrete Instanz -->
<w:num w:numId="1">
  <w:abstractNumId w:val="0"/>
</w:num>

<!-- Restart-Nummer in document.xml -->
<w:p>
  <w:pPr>
    <w:numPr>
      <w:ilvl w:val="0"/>
      <w:numId w:val="1"/>
      <w:numStart w:val="5"/> <!-- Custom Start -->
    </w:numPr>
  </w:pPr>
</w:p>
```

**Extraktionsstrategie**:
- Laden als separate XML-Datei aus Archiv
- Mapping: `numId -> lvl -> numFormat & levelText`

---

## Source-Reference Schema für AST-Nodes

Jeder AST-Node speichert eine optional Source-Reference zum Nachverfolgung:

```php
class SourceReference {
    public string $part;              // 'document' | 'styles' | 'numbering'
    public int $sectionIndex;         // 0-based section number
    public int $elementIndex;         // 0-based element index within section
    public ?string $xmlPath;          // XPath zum Element in OOXML (z.B. "//w:p[1]/w:r[2]/w:t")
    public ?array $xmlAttributes;     // Kritische Attribute als JSON-kompatible Array
}
```

**Beispiele**:
1. Text mit `xml:space="preserve"`:
   ```php
   new SourceReference(
       part: 'document',
       sectionIndex: 0,
       elementIndex: 2,
       xmlPath: "//w:p[3]/w:r[1]/w:t[@xml:space='preserve']",
       xmlAttributes: ['xml:space' => 'preserve']
   )
   ```

2. ListItem mit Restart:
   ```php
   new SourceReference(
       part: 'document',
       sectionIndex: 0,
       elementIndex: 5,
       xmlPath: "//w:p[6]/w:pPr/w:numPr",
       xmlAttributes: ['w:numId' => '1', 'w:numStart' => '5']
   )
   ```

---

## Verlustfreiheits-Strategie (Phase 1)

### Was MUSS im AST sein:
1. **Jeder TextRun** mit:
   - `content` (Original-String, nicht normalisiert)
   - `preserveSpace` (wenn `xml:space="preserve"`)
   - `bold`, `italic`, `underline`, `fontSize`, `deleted`
   - `sourceRef` (wenn kritisch)

2. **Jede ListItem** mit:
   - `numId`, `depth`
   - `numFormat` (from numbering.xml)
   - `startNumeration` (wenn gesetzt, from document.xml)

3. **Jeder Tab/Break** mit:
   - `type` ('tab', 'linebreak', 'pagebreak')
   - Nicht einfach in Text normalisieren!

4. **Jede Table** mit:
   - `rows[]` & `cells[]` vollständig
   - `cellBorder.*` und Background

5. **Track Changes**:
   - `deleted` vs. `inserted` als explizite Marker
   - Optional: Autor/Datum (für Phase 2)

### Was KANN weg:
- Dekorative Farben (Phase 2)
- TextBox-Positioning (Phase 2)
- Header/Footer (Phase 2)

---

## Extraktions-Workflow

### Schritt 1: PhpWord laden
```php
$loader = new DocumentLoader();
$doc = $loader->load($filePath);
```

### Schritt 2: OOXML-Sidecars laden
```php
$sidecarLoader = new OoxmlSidecarLoader();
$sidecarData = [
    'document' => $sidecarLoader->loadDocument($filePath),
    'styles' => $sidecarLoader->loadStyles($filePath),
    'numbering' => $sidecarLoader->loadNumbering($filePath),
];
```

### Schritt 3: Fusion in AST
```php
$astBuilder = new AstBuilder($doc, $sidecarData);
$ast = $astBuilder->build();
```

---

## Implementierungs-Checkpoints (Run 02)

- [ ] Dokumentiere OOXML-Extraktionsstrategie für document.xml (xml:space, revisions)
- [ ] Dokumentiere OOXML-Extraktionsstrategie für styles.xml (default font size, indentation)
- [ ] Dokumentiere OOXML-Extraktionsstrategie für numbering.xml (formats, restart)
- [ ] Sketche OoxmlSidecarLoader-Klasse (ohne Implementierung)
- [ ] Definiere SourceReference-Datentyp (ohne Implementierung)
- [ ] Validiere Verlustfreiheit gegen Parity-Korpus

---

## Ergebnisse

### Mapping-Tabelle ✅
Die kritische Signal-Mapping-Tabelle wurde erstellt und dokumentiert:
- 30+ Word-Signale wurden kategorisiert
- Datenquellen (PhpWord vs. OOXML) wurden identifiziert
- AST-Felder wurden zugeordnet
- Kritikalität wurde bewertet (Rot/Gelb/Grün)

### Sidecar-Konzept ✅
Drei obligatorische OOXML-Sidecars wurden definiert:
1. **document.xml** - Whitespace, Tabs, Track Changes
2. **styles.xml** - Default Font, Indentation
3. **numbering.xml** - List Formats, Restart-Nummern

### Source-Reference-Schema ✅
Ein Tracking-Schema wurde definiert:
- `part`, `sectionIndex`, `elementIndex`, `xmlPath`, `xmlAttributes`
- Ermöglicht Nachverfolgung bis zu ursprünglichem XML

---

## Festgezogene Entscheidungen

1. **OOXML-Sidecars sind OBLIGATORISCH**, nicht optional.
   - PhpWord verliert kritische Informationen (xml:space, revisions, numStart)
   - Ohne Sidecars ist der AST nicht verlustfrei

2. **Source-References sind OPTIONAL für Phase 1**, aber Framework muss vorbereitet sein.
   - Wichtig für Debugging und spätere Template-Annotation
   - Werden im Run 03 Datentyp integriert

3. **Numerierung erfordert numbering.xml-Lookup**.
   - ListItemRun hat nur NumId und Depth
   - Das Format (Bullet, Number, etc.) kommt nur aus numbering.xml
   - Restart-Nummern kommen aus Attributen in document.xml

4. **Whitespace ist NICHT NORMALISIERBAR**.
   - `xml:space="preserve"` muss explizit markiert werden
   - Mehrfachspaces und NBSPs müssen buchstäblich gespeichert werden
   - HTML-Paritätsvergleiche sind String-Paritätsvergleiche

5. **Track Changes bleiben annotierend, nicht-evaluierend**.
   - Der AST speichert `deleted=true/false` und optional Metadaten
   - Apps entscheiden, ob Deletions gerendert oder ausgeblendet werden

---

## Offene Punkte

1. **OoxmlSidecarLoader Testability**
   - Wie wird Sidecar-Laden getestet? Mit echten DOCX-Dateien oder Mock-XML?
   - Fehlerbehandlung bei fehlenden Parts (numbering.xml optional bei Nicht-Listen)?

2. **Performance**
   - ZipArchive wird 3x geöffnet (document, styles, numbering)
   - Sollte es gecacht werden? Oder ist ein durchlaufender Parse effizienter?

3. **Relevante Real-World-Dokumente**
   - Parity-Korpus hat nur Programmatic PhpWord-Dokumente
   - Sollen für Phase 2 Real-World-Vorlagen hinzugefügt werden?

---

## Annahmen

- PhpWord 0.18.x wird weiterhin als Abstraktionsschicht genutzt
- OOXML-Zugriff bleibt lokal (keine Remote-Ressourcen)
- Sidecar-Laden fehlertoleranz: fehlende Parts loggen, aber nicht stoppen
- AST wird als PHP-Datenstruktur (nicht XML) modelliert

---

## Interpretation für Run 03

Run 03 entwirft auf Basis dieser Mapping-Tabelle und Sidecar-Strategie ein **verlustloses AST-Datenmodell**.

Constraints für Run 03:
1. Jedes kritische Signal muss einen dedizierten AST-Feld haben (keine Ambiguität)
2. Keine spekulativen Felder – nur dokumentierte Datenquellen
3. Source-References müssen vorbereitet sein, aber nicht erzwungen
4. Der AST muss serialisierbar/deserialiserbar (JSON) sein
5. Legacy-Renderer muss den AST end-to-end konsumieren können

Umfang für Run 03:
- AST-Datentypen für TextRun, ListItem, Tab, Break, Table, TextBox
- Container-Struktur (Section, Paragraph, TextSpan)
- Enum für kritische Zustände (PreserveSpace, TrackChangeType, ListFormat)
- Kein Sidecar-Loader-Code, nur Datentypen
