# Run 00 - Arbeitsvertrag fuer die Migrationsserie

## Ziel

Einen verbindlichen Rahmen fuer alle Folge-Runs schaffen, damit Scope, Handoffs und Qualitaetskriterien ueber die gesamte Serie konsistent bleiben.

## Eingaben

- Architekturstand des Repos
- bestehende Legacy-Pipeline
- vorhandene Testsuite
- Anforderungen an HTML-Paritaet und spaetere AST-Nutzung

## Aufgaben

1. Die Migrationsprinzipien festschreiben.
2. Die Trennung zwischen Processor, AST, Renderer und App-Semantik fixieren.
3. Das Handoff-Format zwischen den Runs definieren.

## Verbindliche Entscheidungen

1. **Legacy bleibt Referenzverhalten**, bis der AST-Renderer im Compare-Modus dauerhaft paritaetstreu ist.
2. **Word AST bleibt syntaxneutral**. Keine fruehe Interpretation von Platzhaltern, Bedingungen oder proprietaeren Tags.
3. **Template-Parsing ist optional und annotierend**, nicht evaluierend.
4. **Apps interpretieren Semantik**, die Lib erkennt hoechstens Syntax und Struktur.
5. **OOXML-Sidecar ist erlaubt und erwuenscht**, wenn PhpWord Details nicht verlustfrei liefert.

## Handoff-Format fuer alle Folge-Runs

Jeder Folge-Run dokumentiert mindestens:

- `Erzeugte Artefakte`
- `Festgezogene Entscheidungen`
- `Offene Punkte`
- `Annahmen`
- `Interpretation fuer Run N+1`

## Definition of done

- Die Serie hat ein klares Zielbild.
- Jeder Folge-Run weiss, welche Informationen explizit uebergeben werden muessen.

## Standard-Prompt fuer alle Folge-Runs

Verwende diesen Template-Text fuer jeden neuen Run (ersetze nur `{N}` durch die Nummer):

```
Lies zuerst die Basis-Dateien in dieser Reihenfolge:
1. .ai/ast-migration-runs/00-arbeitsvertrag.md (Arbeitsvertrag)
2. .ai/ast-migration-runs/{N_MINUS_1}-*.md (vorhergehender Run fuer Kontext)

Achte besonders auf die Sektion "Interpretation fuer Run {N}" im vorhergehenden Run.

Arbeite dann nur mit dem referenzierten Run-Dokument:
- .ai/ast-migration-runs/{N}-*.md (aktueller Run)

Setze den Run end-to-end um, aber ziehe keine späteren Runs vor.
Aktualisiere am Ende das Run-Dokument mit Ergebnissen, Entscheidungen und Interpretation fuer Run {N_PLUS_1}.
```

## Interpretation fuer Run 01

Run 01 darf noch keine produktive AST-Umschaltung vorbereiten. Er schafft zuerst die Sicherheitsgurte: Compare-Modus, Diffing, Artefakte, Teststrategie.
