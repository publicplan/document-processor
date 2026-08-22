# Run 01 - Baseline und parity harness

## Ziel

Eine belastbare Vergleichsinfrastruktur schaffen, damit jede spaetere AST-Aenderung sofort gegen das Legacy-Ergebnis pruefbar ist.

## Eingaben

- `00-arbeitsvertrag.md`
- bestehende PHPUnit-Suite
- aktueller `DocumentProcessor`
- bestehende HTML-Validierung

## Aufgaben

1. Compare-Modus fuer Legacy vs. AST definieren.
2. String-Diff und DOM-Diff als getrennte Ebenen beschreiben.
3. Artefakt-Ausgabe fuer lokale Entwicklung und CI festlegen.
4. Parity-Fixture-Korpus bestimmen.

## Erzeugte Artefakte

- `src/Enum/RenderMode.php` mit `legacy|ast|compare` als explizitem Vergleichsmodus
- `src/Service/HtmlParityComparator.php` und `src/Model/HtmlParityResult.php`
- Test-Harness unter `tests/Support/Parity/`:
  - `DocumentProcessorParityHarness`
  - `ParityHarnessResult`
  - `ParityArtifactWriter`
- Pflicht-Artefakte im Compare-Lauf:
  - `legacy.html`
  - `ast.html`
  - `parity-diff.json`
- Parity-Korpus in `tests/Integration/DocumentProcessorParityTest.php` fuer:
  - einfache Dokumente
  - Listen
  - Borders
  - Tabellen
  - Deleted Content
  - Breaks
  - Textboxen
- Comparator-Tests in `tests/Service/HtmlParityComparatorTest.php` fuer identische HTML-Fragmente, reine Whitespace-Abweichungen und echte DOM-Abweichungen

## Festgezogene Entscheidungen

- String-Paritaet ist primaere Schranke.
- DOM-Diff dient der Diagnose, nicht als Lockerung der String-Paritaet.
- Parity-Pruefung wird vom ersten AST-Run an mitgefuehrt.
- Compare wird in Run 01 bewusst als Test-Harness eingefuehrt, nicht als Runtime-API des `DocumentProcessor`.
- Artefakte sind standardmaessig aus; bei gesetztem `DOCUMENT_PROCESSOR_WRITE_PARITY_ARTIFACTS` schreibt der Harness nach `DOCUMENT_PROCESSOR_PARITY_ARTIFACTS_DIR` oder sonst nach `build/parity/`.
- `parity-diff.json` enthaelt String-Paritaet (inklusive erster Abweichung, Laengen und Hashes) und separat die DOM-Diagnose.

## Offene Punkte

- Ob spaetere AST-Runs den bestehenden Compare-Harness direkt weiterverwenden oder ihn in eine oeffentliche interne API im `src/` ueberfuehren.
- Ob fuer groessere reale DOCX-Korpora zusaetzlich dateibasierte Golden-Fixtures benoetigt werden oder die programmatisch erzeugten Dokumente als Baseline zunaechst reichen.

## Annahmen

- Die bestehende Testsuite beschreibt einen relevanten Teil des Legacy-Verhaltens, aber nicht alle OOXML-Randfaelle.
- Fuer Run 01 reicht es, AST noch nicht produktiv zu rendern; der Compare-Harness darf den kuenftigen AST-Renderer per Closure einspeisen.
- Textbox-Paritaet wird vorerst ueber in-memory-Dokumente abgesichert, weil die aktuelle Testumgebung keine direkte Section-API zum Erzeugen von Textboxen bietet.

## Ergebnisse

- Die bestehende PHPUnit-Suite lief vor der Aenderung gruen; nach der Erweiterung laeuft sie weiterhin gruen.
- Der neue Compare-Harness deckt den Pflicht-Korpus end-to-end ab und kann bereits heute Legacy-vs.-Kandidat HTML inklusive Artefakt-Ausgabe vergleichen.
- `.gitignore` ignoriert jetzt `build/`, damit lokale Parity-Artefakte nicht versehentlich im Worktree landen.

## Interpretation fuer Run 02

Run 02 kann den Harness als feste Sicherheitsleine behandeln und sollte jetzt nicht am Diffing oder Artefaktformat arbeiten, sondern an der Verlustfreiheit der Eingabedaten. Konkret: Run 02 untersucht, welche Informationen aus PhpWord und gegebenenfalls OOXML-Sidecars im kuenftigen AST landen muessen, damit der spaetere AST-Renderer im Compare-Modus keine False Positives aus ausgeduennter Struktur oder fehlenden Stil-/Containerdetails erzeugt.
