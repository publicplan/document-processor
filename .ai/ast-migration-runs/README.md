# AST migration runs

## Zweck

Dieses Verzeichnis enthaelt einen stufenweisen Umsetzungsplan fuer die Einfuehrung eines internen und spaeter oeffentlichen AST fuer die Word-zu-HTML-Konvertierung.

Die Runs bauen strikt aufeinander auf. Jeder Run erzeugt dokumentierte Ergebnisse, die der naechste Run als Eingabe interpretiert. Dadurch bleibt der Kontext pro Run bewusst klein und belastbar.

## Warum die Kontexttrennung hier sinnvoll und notwendig ist

Die Aufgabe hat gleichzeitig mehrere Spannungsfelder:

- HTML-Paritaet zur Legacy-Pipeline
- verlustfreie Word-Semantik fuer Whitespace, Breaks, Tabs und leere Absaetze
- spaetere AST-Nutzung durch einbindende Apps
- optionale Template-/Placeholder-Annotation ohne fachliche Evaluation

Ein einzelner grosser Implementierungsdurchlauf wuerde diese Ziele vermischen. Die Run-Struktur trennt deshalb:

1. **notwendigen Kontext**: nur das, was fuer den aktuellen Schritt fachlich und technisch gebraucht wird
2. **sinnvollen Kontext**: Entscheidungen, Artefakte und offene Fragen, die fuer Folge-Runs bewusst uebergeben werden muessen

So wird verhindert, dass spaetere Entscheidungen implizit oder nur im Codezustand stecken.

## Run-Protokoll

Jeder Run arbeitet nach demselben Muster:

1. Vorherige Run-Dokumente lesen
2. Artefakte und offene Punkte des unmittelbaren Vorgaengers interpretieren
3. Nur den eigenen Scope bearbeiten
4. Ergebnisse fuer den Folge-Run dokumentieren

## Reihenfolge

| Run | Thema | Primäres Ergebnis |
|---|---|---|
| 00 | Arbeitsvertrag | verbindliche Regeln fuer Scope, Handoff und Paritaet |
| 01 | Baseline und Parity Harness | abgesicherter Legacy-vs.-AST-Vergleich |
| 02 | Lossless Input Mapping | Klarheit, welche Word/OOXML-Signale erhalten werden muessen |
| 03 | Word AST Modell | lossless, syntaxneutrales AST-Design |
| 04 | Normalization Passes | explizite Strukturregeln statt impliziter String-Logik |
| 05 | HTML Renderer Parity | AST rendert identisches HTML |
| 06 | Oeffentliche AST API | Apps koennen AST stabil konsumieren |
| 07 | Template Annotation | optionale Syntax-Erkennung ohne Evaluation |
| 08 | App Adoption und Legacy-Rueckbau | kontrollierte Nutzung des AST ausserhalb der Lib |

## Verbindliche Leitplanken

- Kein Big-Bang-Rewrite
- HTML-Paritaet hat Vorrang vor Eleganz
- Word AST bleibt lossless und syntaxneutral
- Template-Semantik bleibt App-Verantwortung
- Jeder Run hinterlaesst explizite Handoff-Artefakte
