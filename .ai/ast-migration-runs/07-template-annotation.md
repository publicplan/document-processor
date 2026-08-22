# Run 07 - Template annotation

## Ziel

Eine optionale, syntaxerkennende Schicht fuer Platzhalter, Bedingungen und aehnliche Tag-Formate definieren, ohne deren fachliche Bedeutung in die Lib zu ziehen.

## Eingaben

- `06-oeffentliche-ast-api.md`
- Public-AST-Contract
- Anforderungen aus einbindenden Apps an Placeholder- und Bedingungsparser

## Aufgaben

1. Annotation statt Evaluation festschreiben.
2. Definieren, auf welcher Token-Sicht geparst wird.
3. Profile oder Parser-Plugins fuer unterschiedliche App-Dialekte beschreiben.

## Pflichtprinzipien

- Parser arbeitet auf lossless Inline-Sequenzen
- Run-Grenzen, Tabs, Breaks und Spaces bleiben nachvollziehbar
- Erkennung ist optional
- Ergebnis ist Annotation oder separater `TemplateAst`
- keine fachliche Evaluation

## Mögliche Rueckgaben

- markierte Ranges
- `TemplateAnnotation`
- optional separater `TemplateAst`

## Erzeugte Artefakte

- Trennlinie `Syntax erkennen` vs. `Semantik interpretieren`
- Profilkonzept fuer unterschiedliche App-Syntaxen
- Liste der Parser-Invarianten, damit HTML-Paritaet nicht beeinflusst wird

## Festgezogene Entscheidungen

- Die Lib erkennt nur Syntaxmuster.
- Die App bewertet Bedeutung, ersetzt Inhalte und fuehrt Bedingungen aus.

## Offene Punkte

- Wie weit die Lib bei fehlerhaften oder unvollstaendigen Tag-Sequenzen diagnostisch helfen soll
- Ob Annotationen direkt am AST oder separat gespeichert werden sollen

## Annahmen

- Unterschiedliche Apps brauchen unterschiedliche Dialekte, daher darf die Lib keine harte Business-Semantik kodieren.

## Interpretation fuer Run 08

Run 08 plant die praktische Einfuehrung in konsumierenden Anwendungen. Dabei wird der AST als Primaerformat und HTML als Renderer-Ausgabe behandelt.
