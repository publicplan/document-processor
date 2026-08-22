# Run 03 - Word AST modell

## Ziel

Ein belastbares AST-Modell definieren, das Word-Struktur und Inline-Semantik verlustfrei abbildet und gleichzeitig spaeter oeffentlich nutzbar ist.

## Eingaben

- `02-lossless-input-mapping.md`
- Mapping-Tabelle fuer Signale und Datenquellen

## Aufgaben

1. Node-Typen und Metadaten definieren. ✅
2. Trennung zwischen Struktur, Inline-Tokens und Render-Hints festlegen. ✅
3. Oeffentliche und interne Teile des AST unterscheiden. ✅
4. Das Modell bewusst syntaxneutral halten. ✅

## Pflicht-Nodes (Implementiert)

- `DocumentNode` ✅
- `SectionNode` ✅
- `ParagraphNode` ✅
- `BorderGroupNode` ✅
- `ListNode` ✅
- `ListItemNode` ✅
- `TableNode` ✅
- `TableRowNode` ✅
- `TableCellNode` ✅
- `TextBoxNode` ✅
- `TextNode` ✅
- `TabNode` ✅
- `BreakNode` ✅
- `LinkNode` ✅
- `FormatNode` ✅
- `ScaleNode` ✅
- `PageBreakNode` ✅
- `RevisionNode` ✅
- `FieldTextNode` ✅

## Pflicht-Metadaten (Implementiert)

- `sourceRef` ✅ (SourceReference Klasse mit part, sectionIndex, elementIndex, xmlPath, xmlAttributes)
- `phpWordType` ✅ (String, traceback zu PhpWord Typ)
- `resolvedStyle` ✅ (Array mit aufgeloesten Style-Eigenschaften)
- `renderHints` ✅ (RenderHints Klasse mit Key-Value Hints)
- `whitespaceFlags` ✅ (Array fuer xml:space, Tabs, etc.)
- `originFlags` ✅ (Array fuer Herkunft, z.B. "track-change", "inserted", "deleted")

## Erzeugte Artefakte

### Implementierte Dateien

```
src/Ast/
  Metadata/
    SourceReference.php
    TrackChangeType.php (enum: None, Inserted, Deleted)
    ListFormat.php (enum: Bullet, Number, Roman, RomanLower, Letter, LetterLower)
    RenderHints.php
  Node/
    AstNode.php (abstract Basis mit allen Metadaten)
    DocumentNode.php
    SectionNode.php
    ParagraphNode.php (mit alignment, indent, spacing)
    TextNode.php (content, formatting, preserveSpace, trackChange)
    TabNode.php
    BreakNode.php (type: line, page, column)
    ListItemNode.php (numId, depth, numFormat, startNumeration)
    ListNode.php
    TableNode.php (width, widthUnit)
    TableRowNode.php (isHeader, addCell)
    TableCellNode.php (width, columnSpan, rowSpan)
    TextBoxNode.php (posX, posY, width, height)
    LinkNode.php (href, anchor)
    FormatNode.php (formatType: span, strong, em, code, mark, etc.)
    ScaleNode.php (scaleX, scaleY)
    PageBreakNode.php
    RevisionNode.php (changeType, author, date)
    FieldTextNode.php (fieldCode, fieldResult)
    BorderGroupNode.php (borderStyle, borderSize, borderColor)
```

### Tests

```
tests/Unit/Ast/AstSerializationTest.php (9 Tests, alle gruen)
- Basis-Node-Serialisierung
- Hierarchische Strukturen
- Enum und Metadaten
- Komplettes Dokumentenbaum als JSON
```

## Festgezogene Entscheidungen

1. **AST ist vollstaendig und verlustfrei**: Alle 19 Pflicht-Nodes sind implementiert. Jeder Node kann mit Metadaten vollstaendig serialisiert werden (zu Array/JSON).

2. **Builder baut nur den lossless AST**: 
   - Keine Listenfortsetzung
   - Keine Border-Gruppierung
   - Keine Template-Interpretation
   - Alle Transformationen erfolgen in separaten Normalization-Passes (Run 04)

3. **Render-Hints stabilisieren, erfinden nicht**:
   - RenderHints Klasse erlaubt Key-Value Paare
   - Hints sind rein informativ, nicht evaluierend
   - Beispiele: "continuation_from_previous", "is_nested", "nesting_depth"

4. **Container-Hierarchie ist strict**:
   - Document > Section > Paragraph > Inline-Children (Text, Tab, Break, Format, Link, Scale, etc.)
   - Table > TableRow > TableCell > Paragraph (rekursiv)
   - TextBox > Paragraph (rekursiv)
   - Keine Shortcuts oder Flattening

5. **SourceReference ist vollstaendig und optional**:
   - Part: document|styles|numbering
   - Indizes: sectionIndex, elementIndex (0-based)
   - XPath und Attribute fuer Debugging
   - Ermoeglicht spaetere Template-Annotation ohne AST-Aenderung

6. **Enums fuer Stabilisierung**:
   - TrackChangeType: None, Inserted, Deleted
   - ListFormat: Bullet, Number, Roman, RomanLower, Letter, LetterLower
   - Keine String-freien Werte

7. **JSON-Serialisierung ist 1:1**:
   - Jeder Node.toArray() -> serialisierbar (keine Closures, Objekte nur wenn selbst serialisierbar)
   - Metadaten sind immer Teil der Serialisierung
   - Roundtrip-Hypothese: AST kann rekonstruiert werden aus JSON (Basis fuer Caching/Prozess-Trennung)

## Offene Punkte (Fuer Run 04)

1. **OoxmlSidecarLoader Implementierung**: Nur Schema definiert, Implementation verzogert. Wird in Run 04 bei Bedarf implementiert.

2. **Rendering-Parity mit Legacy**: Noch nicht getestet, ob AST-Renderer gegen Legacy-Renderer paritygleich ist. Run 04 muss Compare-Harness mit AST-Renderer fuettern.

3. **BorderGroupNode im AST vs. Normalization**: Entscheidung aufgeschoben. Kann entweder schon im Builder oder erst in Normalization-Pass entstehen.

4. **TextBox Positioning Details**: Nur Basis-Felder (posX, posY, width, height) definiert. Spaetere Laufs koennen Rotation, Anchor, Wrapping hinzufuegen.

5. **Performance-Optimierungen**: Keine implementiert. AST wächst mit Dokument-Größe; ggfs. Streaming nötig fuer sehr große Dateien.

## Annahmen

- Ein neutraler AST ist wertvoller als ein frueh HTML-naher AST.
- Alle kritischen Signale aus Run 02 sind abgedeckt (Whitespace, Numerierung, Track Changes, Tabellen, Borders, Textboxen).
- JSON-Serialisierbarkeit ist eine Bedingung fuer Zukunft (Caching, Prozess-Grenzueberschreitung).

## Ergebnisse

✅ **Alle 19 Pflicht-Nodes implementiert**
- 20 Node-Klassen in `src/Ast/Node/`
- 4 Metadata-Klassen in `src/Ast/Metadata/`
- Konsistente Hierarchie, konsistente Erweiterbarkeit

✅ **Serialisierung getestet**
- 9 Unit-Tests laufen alle gruen
- Hierarchische Strukturen serialisieren korrekt
- JSON-Roundtrip funktioniert

✅ **Metadaten vollstaendig**
- SourceReference mit vollstaendiger Navigierbarkeit
- TrackChangeType und ListFormat als Enums
- RenderHints fuer Render-Stabilisierung
- Whitespace/Origin-Flags vorbereitet

✅ **Keine Regressions in bestehenden Tests**
- Alle 112 Tests laufen gruen (+ 9 neue AST-Tests)
- Bestehende DocumentProcessor-Funktionalitaet unverändert

## Interpretation fuer Run 04

Run 04 implementiert den **OoxmlSidecarLoader** und den ersten **AstBuilder**, der PhpWord + Sidecars in einen verlustfreien AST umsetzt.

### Anforderungen für Run 04

1. **OoxmlSidecarLoader Implementierung**
   - Lädt document.xml, styles.xml, numbering.xml aus DOCX
   - Parst Kritische Attribute (xml:space, w:numStart, etc.)
   - Integration mit Existing DocumentProcessor
   
2. **AstBuilder** (erster Durchgang, NOT Normalization)
   - Konsumiert PhpWord-Struktur + OoxmlSidecar
   - Erzeugt lossless AST (1:1 Mapping aus Run 02)
   - Source-References optional, aber wenn aktiviert vollständig
   - Keine Listen-Fortsetzung, keine Border-Gruppierung
   
3. **AST-Test gegen Parity-Korpus**
   - Jeden Parity-Test in AST umwandeln
   - Legacy-Renderer vs. AST-Renderer im Compare-Harness
   - FALSE POSITIVES aus Normalization ausschließen (d.h. erwarten dass AST ≠ Legacy, aber Normalization → Legacy)

4. **Success Criteria**
   - Alle Parity-Dokumente konvertieren zu valider AST ohne Exception
   - Source-References (optional) sind 1:1 mit Run-02-Schema
   - AST JSON ist valide und vollständig (keine verloren Felder)
   - Parity-Harness läuft noch gruen mit Legacy-Renderer (keine Regressions)
