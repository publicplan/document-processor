# Run 08 - Template annotation

## Ziel

Eine optionale, syntaxerkennende Schicht fuer Platzhalter, Bedingungen und aehnliche Tag-Formate definieren, ohne deren fachliche Bedeutung in die Lib zu ziehen.

## Eingaben

- `07-struktur-vereinfachung.md` (Voraussetzung: TextCoalescing, legacy_html-Entfernung)
- Konsolidierter Public-AST-Contract
- Anforderungen aus einbindenden Apps an Placeholder- und Bedingungsparser

## Aufgaben

1. Annotation statt Evaluation festschreiben.
2. Definieren, auf welcher Token-Sicht geparst wird.
3. Profile oder Parser-Plugins fuer unterschiedliche App-Dialekte beschreiben.
4. Den minimalen Erkennungsumfang fuer Steuerungs-Tags und Platzhalter festziehen.

## Pflichtprinzipien

- Parser arbeitet auf lossless Inline-Sequenzen
- Run-Grenzen, Tabs, Breaks und Spaces bleiben nachvollziehbar
- Erkennung ist optional
- Ergebnis ist Annotation oder separater `TemplateAst`
- keine fachliche Evaluation
- keine Validierung von Schliessung, Balancing oder Interpretierbarkeit
- keine stillschweigende Reparatur syntaktisch fehlerhafter Template-Sequenzen

## Konkretisierter Erkennungsumfang

- Steuerungs-Tags wie `wenn`, `sonst wenn`, `sonst`, `ende` werden als Template-/Control-Elemente erkannt.
- Platzhalter werden ebenfalls als eigene Template-Elemente erkannt.
- Funktionen und aehnliche Template-Ausdruecke koennen nach demselben Prinzip als Steuerungselemente erkannt werden.
- Ziel ist zunaechst nur die Identifizierung, welche Teile der Inline-Sequenz normaler Text sind und welche Steuerungsaufgaben haben.
- Die konkrete Oberflaechensyntax darf je Profil bzw. Parser-Plugin unterschiedlich sein.

## Umgang mit syntaktisch fehlerhaften Sequenzen

- Der Parser arbeitet tolerant und lossless.
- Sequenzen, die erkennbar wie Template-/Control-Syntax beginnen, aber syntaktisch fehlerhaft oder unvollstaendig sind, duerfen als fehlerhafte Syntaxfragmente annotiert werden.
- Solche Fragmente bleiben von normalem Text unterscheidbar, ohne dass ihre Semantik als gueltig behandelt wird.
- Sequenzen, die nicht belastbar als Template-Syntax erkennbar sind, bleiben normaler Text.

## Nicht-Ziele

- Keine Pruefung, ob Bedingungen oder Platzhalter korrekt geschlossen sind.
- Keine Pruefung, ob Tags sinnvoll verschachtelt oder vollstaendig sind.
- Keine Pruefung, ob Ausdruecke fachlich interpretierbar sind.
- Keine Auswertung von Bedingungen und keine Ersetzung von Platzhaltern.
- Keine automatische Reparatur oder Vervollstaendigung fehlerhafter Template-Syntax.

## Mögliche Rueckgaben

- markierte Ranges
- `TemplateAnnotation`
- optional separater `TemplateAst`
- Template-/Control-Knoten fuer erkannte Steuerungs- und Platzhalterelemente
- Annotationen fuer fehlerhafte, aber als Template-Syntax erkennbare Fragmente

## Erzeugte Artefakte

- `src/Ast/Pass/TemplateAnnotationPass.php`
- `src/Service/Ast/Template/TemplateSyntaxProfile.php`
- `src/Service/Ast/Template/GenericTemplateSyntaxProfile.php`
- `src/Service/Ast/Template/DetectedTemplateFragment.php`
- Erweiterte `src/Model/ProcessingOptions.php` fuer optionale Template-Profile
- Erweiterter `src/Service/Ast/AstDocumentProcessor.php` fuer konditionale Annotation nach der Normalisierung
- Aktualisierter `src/Service/Ast/PublicAstSerializer.php` (`AST_VERSION` auf `1.1.0`)
- Neue Tests:
  - `tests/Unit/Ast/Pass/TemplateAnnotationPassTest.php`
  - `tests/Service/Ast/AstDocumentProcessorApiTest.php`

## Festgezogene Entscheidungen

1. **Annotation bleibt optional und liegt ausserhalb der Standard-Pipeline**
   - Die Default-Normalisierung endet weiterhin bei `TextCoalescingPass`.
   - Template-Erkennung wird nur aktiviert, wenn `ProcessingOptions->templateSyntaxProfile` gesetzt ist.

2. **Die Lib erkennt Syntax, nicht Semantik**
   - `TemplateAnnotationPass` markiert nur Fragmente.
   - Die App bewertet Bedeutung, ersetzt Inhalte und fuehrt Bedingungen aus.

3. **Lossless Parsing arbeitet auf abgeflachten Inline-Sequenzen**
   - Text, Tabs und Breaks werden in eine Sequenz projiziert.
   - Treffer werden anschliessend auf die beteiligten AST-Knoten zurueckgespiegelt.
   - Jede Annotation enthaelt `sequenceRange` und `nodeRange`, damit Split-Runs nachvollziehbar bleiben.

4. **`sourceRef` ist der oeffentliche Annotationstraeger**
   - Annotationen landen in `metadata.sourceRef.xmlAttributes.templateAnnotations`.
   - Fuer annotierte Knoten werden synthetische `sourceRef`-Informationen (`part`, `sectionIndex`, `elementIndex`, `astPath`) erzeugt, ohne HTML oder AST-Struktur zu veraendern.

5. **Das mitgelieferte Referenzprofil bleibt generisch**
   - `GenericTemplateSyntaxProfile` erkennt `{{ ... }}`, `{% ... %}` und `#{ ... }`.
   - Steuerungs-Tags `wenn`, `sonst wenn`, `sonst`, `ende` werden als Rollen auf Control-Fragmenten markiert.
   - Andere Apps koennen ueber `TemplateSyntaxProfile` eigene Dialekte als Plugin einspeisen.

6. **Fehlerhafte Fragmente werden sichtbar, aber nicht repariert**
   - Unvollstaendige Sequenzen werden als `status = malformed` annotiert.
   - Es gibt keine Balancing-, Schliessungs- oder Interpretationspruefung.

## Offene Punkte

1. **Source-Referenzen sind derzeit synthetisch**
   - `astPath` zeigt auf den AST-Knotenpfad.
   - Ein spaeterer OOXML-Sidecar koennte `xmlPath` / exaktere Word-Referenzen liefern.

2. **Es gibt noch keinen separaten `TemplateAst`**
   - Run 08 liefert nur Annotationen auf vorhandenen AST-Knoten.
   - Ein struktureller Template-Baum bleibt eine moegliche spaetere Erweiterung.

3. **Diagnostik fuer kaputte Sequenzen ist bewusst minimal**
   - Es gibt nur `malformed`-Fragmente.
   - Eigene Fehlercodes oder Qualitaetsstufen koennen spaeter folgen, falls Apps das brauchen.

## Annahmen

1. Unterschiedliche Apps brauchen unterschiedliche Dialekte; die Interface-basierte Profilinjektion reicht deshalb als Plugin-Schnittstelle fuer Run 08 aus.
2. Per-Knoten-Annotationen auf `sourceRef` sind fuer die erste App-Adoption ausreichend, auch wenn ein spaeterer `TemplateAst` reichhaltiger waere.
3. Die additive Erweiterung des Public AST kann als Minor-Schritt (`1.1.0`) versioniert werden.

## Ergebnisse

✅ **Optionale Template-Annotation umgesetzt**
- `AstDocumentProcessor` wendet `TemplateAnnotationPass` nur bei gesetztem Profil an.
- Default-Verhalten fuer HTML- und AST-Verarbeitung bleibt unveraendert.

✅ **Profil-/Plugin-Konzept implementiert**
- `TemplateSyntaxProfile` definiert die Schnittstelle fuer App-spezifische Dialekte.
- `GenericTemplateSyntaxProfile` dient als mitgelieferte Referenz fuer Platzhalter- und Control-Erkennung.

✅ **Lossless Range-Mapping auf AST-Knoten umgesetzt**
- Erkennung funktioniert ueber zusammengefuehrte Inline-Sequenzen.
- Treffer werden als `templateAnnotations` auf die betroffenen `sourceRef`-Metadaten gespiegelt.
- Split ueber mehrere `TextNode`s bleibt via `matchId`, `sequenceRange` und `nodeRange` nachvollziehbar.

✅ **Toleranter Umgang mit fehlerhaften Sequenzen umgesetzt**
- Unvollstaendige `{% ...`, `{{ ...` oder `#{ ...` Fragmente bleiben als `malformed` sichtbar.
- Keine stillschweigende Reparatur oder fachliche Interpretation.

✅ **Tests erweitert**
- Neue Unit-Tests decken Multi-Node-Placeholder und fehlerhafte Control-Fragmente ab.
- API-Test deckt opt-in Annotation ueber `ProcessingOptions` ab.
- Vollsuite bleibt bei denselben zwei bekannten HTML-Paritaetsabweichungen aus Run 07 (`borders`, `textbox`).

## Interpretation fuer Run 09

Run 09 schliesst die Migration im Lib-Repo ab und bereitet die spaetere App-Adoption sauber vor:

1. **Repo-interne Restarbeiten werden von der App-Adoption getrennt**
   - Run 09 behandelt nur Arbeiten, die sinnvoll im `document-processor` selbst liegen.
   - App-spezifische Profilbildung, Interpretation und Legacy-Rueckbau werden nicht mehr implizit in diesen Run gezogen.

2. **Enablement wird explizit abgeschlossen**
   - Oeffentliche Doku, API-Glattung und Integrationsbeispiele werden finalisiert.
   - Die Grenze `Syntax im Core` vs. `Semantik in der App` wird fuer Nutzer der Lib klar dokumentiert.

3. **Die Uebergabe an das App-Repo wird als eigener Folge-Run vorbereitet**
   - Run 09 liefert die benoetigten Handoffs fuer konsumierende Anwendungen.
   - Erst ein separater App-Run nutzt diese Grundlage fuer echte Adoption und Legacy-Rueckbau im Zielsystem.

4. **Paritaetsabweichungen aus Run 07 bleiben weiterhin ausserhalb des Scopes**
   - Die bekannten Unterschiede bei `borders` und `textbox` sind dokumentiert.
   - Weder Run 09 noch der spaetere App-Run sollen diese Themen vorziehen.
