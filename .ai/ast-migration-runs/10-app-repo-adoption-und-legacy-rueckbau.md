# Run 10 - App-Repo-Adoption und Legacy-Rueckbau

## Ziel

Im konsumierenden App-Repo den AST des `document-processor` produktiv einfuehren, Template-Annotationen fachlich nutzbar machen und app-interne Legacy-Pfade kontrolliert abbauen.

## Eingaben

- `08-template-annotation.md` aus dem `document-processor`-Repo
- `09-lib-repo-abschluss-und-enablement.md` aus dem `document-processor`-Repo
- oeffentliche Doku zu AST und Template-Profilen
- Architektur und bestehende Dokumentverarbeitung des App-Repos

## Aufgaben

1. Reale AST-Consumer im App-Repo identifizieren.
2. Ein app-passendes `TemplateSyntaxProfile` waehlen oder implementieren.
3. Fachliche Interpretation von `templateAnnotations` im App-Layer umsetzen.
4. Den bisherigen HTML-/Legacy-Pfad gegen AST-basierte Verarbeitung migrieren.
5. Rueckbaukriterien fuer app-interne Legacy-Pfade festziehen.

## Scope

Dieser Run gehoert explizit **nicht** in das `document-processor`-Repo, sondern in das konsumierende Anwendungssystem.

In Scope:

- Consumer-Migration im Zielsystem
- Profilkonkretisierung fuer echte Templates
- Business-Interpretation von Placeholders und Control-Strukturen
- Umschaltstrategie und Legacy-Rueckbau in der App

Nicht in Scope:

- neue Core-Semantik im `document-processor`
- fachliche Auswertung innerhalb der Lib
- stillschweigende Rueckverlagerung von App-Logik in den Core

## Erzeugte Artefakte

- App-Migrationsmatrix `Consumer -> heutiger Pfad -> Ziel-AST-Pfad`
- konkretes Profil oder Profil-Adapter fuer die App-Syntax
- Integrationspunkte fuer `templateAnnotations`
- Kriterien und Rollout-Plan fuer app-internen Legacy-Rueckbau

## Festgezogene Entscheidungen

1. **Die App interpretiert Semantik**
   - Die Lib liefert nur Syntax/Struktur.
   - Platzhalterwerte, Bedingungen und Funktionen werden ausschliesslich im App-Repo verstanden.

2. **Legacy-Rueckbau braucht reale Consumer**
   - Es wird nichts abgebaut, nur weil die Lib technisch bereit ist.
   - Erst erfolgreich migrierte Verbraucher rechtfertigen den Rueckbau.

3. **Dialekte gehoeren in die App oder einen app-nahen Adapter**
   - Das Referenzprofil der Lib ist nur ein Ausgangspunkt.
   - Echte Produktdialekte sollen dort gepflegt werden, wo ihre Semantik zuhause ist.

## Offene Punkte

- Welche Consumer zuerst umgestellt werden koennen
- Ob mehrere Template-Dialekte parallel unterstuetzt werden muessen
- Wie lange HTML-basierte Hilfspfadlogik parallel bestehen bleiben muss

## Annahmen

- Die App profitiert besonders dort von AST-Adoption, wo heute HTML nachgeparst oder heuristisch ausgewertet wird.
- Die Fachsemantik ist produkt- und teamnah genug, dass sie nicht in die Lib gehoert.

## Prompt fuer die Ausfuehrung im App-Repo

Verwende im App-Repo folgenden Prompt als Startpunkt:

```text
Lies zuerst die Uebergabedokumente aus dem document-processor-Repo:
1. /Users/nast/Repos/document-processor/.ai/ast-migration-runs/08-template-annotation.md
2. /Users/nast/Repos/document-processor/.ai/ast-migration-runs/09-lib-repo-abschluss-und-enablement.md

Arbeite dann im aktuellen App-Repo an der AST-Adoption, ohne Aenderungen am document-processor vorzunehmen.

Ziele:
- identifiziere reale Consumer fuer den AST
- entscheide, ob das mitgelieferte GenericTemplateSyntaxProfile reicht oder ein app-spezifisches Profil/Adapter benoetigt wird
- setze die fachliche Interpretation von templateAnnotations im App-Layer um
- migriere geeignete Verarbeitungsstrecken von HTML-/Legacy-Logik auf AST-basierte Verarbeitung
- dokumentiere Rueckbaukriterien fuer app-interne Legacy-Pfade

Achte darauf:
- Semantik bleibt im App-Repo, nicht in der Lib
- keine fachliche Auswertung in den document-processor zurueckschieben
- HTML kann Renderer-Ausgabe bleiben, muss aber nicht das Primaerformat fuer Weiterverarbeitung sein

Dokumentiere am Ende:
- welche Consumer migriert wurden
- welches Profil bzw. welcher Adapter verwendet wird
- wie templateAnnotations interpretiert werden
- welche Legacy-Pfade noch verbleiben
- welche Voraussetzungen fuer weiteren Rueckbau gelten
```
