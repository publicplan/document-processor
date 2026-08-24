# Run 09 - Lib-Repo-Abschluss und Enablement

## Ziel

Die Migration im `document-processor` sauber abschliessen, die oeffentliche Nutzbarkeit des AST final absichern und die Uebergabe an konsumierende App-Repos vorbereiten.

## Eingaben

- `08-template-annotation.md`
- stabiler Public-AST-Contract (konsolidiert durch Run 07)
- optionale Template-Annotation als syntaktische Erweiterung
- Erkenntnis: eigentliche App-Adoption und Legacy-Rueckbau gehoeren ins konsumierende App-Repo

## Aufgaben

1. Repo-interne Restarbeiten von App-spezifischen Migrationsschritten trennen.
2. Oeffentliche API-, Doku- und Enablement-Luecken fuer AST + Template-Profile schliessen.
3. Einen expliziten Handoff-Run fuer das App-Repo definieren.

## Scope von Run 09

Run 09 bleibt bewusst im Verantwortungsbereich dieses Repos:

1. oeffentliche Dokumentation und Beispielnutzung
2. API-Glattung, falls noetig, fuer die AST-Nutzung
3. klare Handoff-Artefakte fuer konsumierende Apps

Nicht Teil von Run 09:

1. app-spezifische Interpretation von Platzhaltern oder Bedingungen
2. Auswahl echter Consumer im Zielsystem
3. Abschaltung app-interner Legacy-Pfade
4. produktive Umschaltung im konsumierenden Repo

## Erzeugte Artefakte

- `README.md` mit AST-/Template-Enablement, Opt-in-Beispielen und Konsumenten-Grenzen
- `doc/template-syntax-profiles.md` als oeffentliche Profil-/Integrationsdoku
- `tests/Service/Ast/AstDocumentProcessorApiTest.php` mit API-Vertrag fuer Default-Opt-out und Opt-in-Annotation
- `.ai/ast-migration-runs/10-app-repo-adoption-und-legacy-rueckbau.md` als definierter Folge-Run fuer das App-Repo

## Festgezogene Entscheidungen

1. **Run 09 endet im Lib-Repo**
   - Alles, was konkrete App-Semantik, Consumer-Auswahl oder Legacy-Abbau in einem Produkt betrifft, wird aus diesem Run herausgezogen.

2. **Die Lib bleibt syntax- und enablement-orientiert**
   - Dieses Repo liefert AST, Renderer, opt-in Annotation und Doku.
   - Es entscheidet nicht, wie eine App erkannte Fragmente fachlich interpretiert.

3. **App-Adoption wird als eigener Folge-Run gefuehrt**
   - Die Trennlinie zwischen Bibliothek und konsumierender Anwendung wird explizit im Run-Set abgebildet.

4. **Die bestehende AST-API reicht fuer den Lib-Abschluss aus**
   - Es wird kein zusaetzlicher Entry-Point fuer Template-Erkennung eingefuehrt.
   - `ProcessingOptions::templateSyntaxProfile` bleibt der einzige Opt-in-Schalter.

## Offene Punkte

1. Ob fuer konsumierende Apps langfristig weitere mitgelieferte Referenzprofile nuetzlich sind oder das Interface plus Beispielprofil ausreichen

## Annahmen

1. Der groesste verbleibende Mehrwert in diesem Repo liegt jetzt eher in Klarheit und Integrationsfaehigkeit als in weiterer Core-Semantik.
2. Konsumierende Apps koennen auf Basis von Run 08/09 selbststaendig echte Migrationsentscheidungen treffen.

## Ergebnisse

✅ **Oeffentliche AST-/Template-Doku vervollstaendigt**
- README erklaert jetzt die stabile AST-Nutzung, das opt-in fuer `TemplateSyntaxProfile` und die Grenze zwischen Syntax in der Lib und Semantik in der App.
- `doc/template-syntax-profiles.md` beschreibt Aktivierung, Profilvertrag, Rueckgabeformat und die Verantwortung konsumierender Anwendungen.

✅ **Der API-Vertrag fuer Konsumenten wurde geschaerft**
- Ein expliziter API-Test haelt fest, dass Template-Erkennung standardmaessig deaktiviert bleibt.
- Der bestehende Opt-in-Test fuer annotierte AST-Ausgabe bleibt als Gegenstueck erhalten.

✅ **Der Handoff ins App-Repo ist explizit dokumentiert**
- Run 10 liegt als separater Folge-Run mit Scope, Prompt und Rueckbau-Leitplanken vor.
- Damit ist klar festgehalten, dass Consumer-Auswahl, Fachsemantik und Legacy-Abbau nicht mehr in dieses Repo gezogen werden.

✅ **Der Repo-Stand bleibt innerhalb der bekannten Paritaetsgrenzen stabil**
- Die Suite deckt jetzt den Default-Opt-out fuer Template-Erkennung zusaetzlich ab.
- Unveraendert offen bleiben nur die bereits aus Run 07 bekannten Compare-/Paritaetsabweichungen bei `borders` und `textbox`.

## Definition of done

1. Die verbleibenden Arbeiten fuer dieses Repo sind klar von App-Aufgaben getrennt.
2. Oeffentliche Doku und Handoff fuer AST + Template-Profile sind ausreichend fuer konsumierende Teams.
3. Der naechste Schritt ist als separater App-Run mit ausfuehrbarem Prompt dokumentiert.

## Interpretation fuer Run 10

Run 10 findet im konsumierenden App-Repo statt und nutzt die Ergebnisse aus Run 08 und 09 als Input:

1. **Die App waehlt oder implementiert ihr reales `TemplateSyntaxProfile`**
   - Das Referenzprofil der Lib kann Ausgangspunkt sein, muss aber nicht ausreichend sein.

2. **Die App macht den AST zu einem echten Arbeitsformat**
   - `processToAst()` oder `processToAstAndHtml()` werden in reale Verarbeitungsstrecken integriert.
   - `templateAnnotations` werden fachlich interpretiert, aber nicht in der Lib.

3. **Legacy-Rueckbau passiert erst nach erfolgreicher Integration**
   - Rueckbaukriterien werden im Zielsystem an echte Consumer gekoppelt.
   - HTML bleibt Renderer-Ausgabe, ist aber nicht mehr zwingend das Primaerformat fuer Weiterverarbeitung.
