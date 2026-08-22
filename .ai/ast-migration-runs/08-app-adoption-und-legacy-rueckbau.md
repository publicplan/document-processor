# Run 08 - App adoption und legacy rueckbau

## Ziel

Die Nutzung des AST in einbindenden Apps schrittweise einfuehren und gleichzeitig den Legacy-HTML-Only-Pfad kontrolliert abbauen.

## Eingaben

- `07-template-annotation.md`
- stabiler Public-AST-Contract
- paritaetstreuer HTML-Renderer

## Aufgaben

1. Erste AST-Consumer in Apps identifizieren.
2. Migrationsreihenfolge fuer HTML-zentrierte Folgeverarbeitung definieren.
3. Rueckbaukriterien fuer Legacy-Only-Pfade festlegen.

## Empfohlene Einfuehrungsreihenfolge

1. Read-only AST fuer Analyse und Debugging
2. AST fuer Placeholder-/Bedingungs-Erkennung
3. AST fuer app-spezifische Interpretation
4. HTML nur noch als Ausgabeweg

## Erzeugte Artefakte

- App-Migrationsmatrix `Consumer -> heutiger HTML-Pfad -> Ziel-AST-Pfad`
- Kriterien fuer Legacy-Fallbacks
- Ausstiegskriterien fuer den Compare-Modus

## Festgezogene Entscheidungen

- Der AST wird Primaerformat fuer Weiterverarbeitung.
- HTML bleibt wichtig, aber nicht mehr einziges Integrationsformat.

## Offene Punkte

- Wie lange Compare und Legacy-Fallback in Produktion oder Vorabumgebungen aktiv bleiben sollen
- Welche Apps zuerst migriert werden koennen, ohne hohen Migrationsdruck zu erzeugen

## Annahmen

- Der groesste Mehrwert entsteht zuerst dort, wo heute HTML wieder geparst oder heuristisch analysiert wird.

## Definition of done

- Es gibt eine nachvollziehbare App-Migrationsreihenfolge.
- Die Rolle des AST ausserhalb der Lib ist klar beschrieben.
- Der Legacy-Rueckbau ist an messbare Kriterien gebunden.
