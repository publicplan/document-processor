# ADR-0004: Template-Syntax-Annotation als optionale, syntaxerkennende Schicht

**Status:** Accepted  
**Datum:** 2026-08-24  
**Implementiert in:** v2.0.0 (AST-Version 1.1.0)

## Kontext

Konsumierende Apps verwenden DOCX-Dokumente mit eingebetteten Template-Platzhaltern und Steuerungs-Tags (z. B. `{{ Platzhalter }}`, `{% wenn Bedingung %}`). Diese Syntax ist app-spezifisch und hat keine Bedeutung für die Lib selbst.

Zwei Extrempositionen wären:
1. Die Lib ignoriert Template-Syntax vollständig → Apps müssen selbst über HTML oder AST-Text parsen
2. Die Lib evaluiert Template-Syntax → fachliche Kopplung, die nicht in eine generische DOCX-Lib gehört

## Entscheidung

Die Lib erkennt Template-Syntax **optional und annotierend**, wertet sie aber nicht aus:

- **Annotation, keine Evaluation**: `TemplateAnnotationPass` markiert Fragmente im AST; welche Bedeutung sie haben, entscheidet die App
- **Opt-in**: Der Pass wird nur aktiviert, wenn `ProcessingOptions->templateSyntaxProfile` gesetzt ist; das Default-Verhalten ändert sich nicht
- **Profil-/Plugin-Konzept**: `TemplateSyntaxProfile` ist eine austauschbare Schnittstelle; jede App kann einen eigenen Dialekt-Parser einspeisen
- **Lossless Parsing auf Inline-Sequenzen**: Treffer werden über `matchId`, `sequenceRange` und `nodeRange` auf die beteiligten AST-Knoten zurückgespiegelt, auch wenn ein Platzhalter über mehrere `TextNode`s verteilt ist
- **Fehlertoleranz**: Unvollständige oder syntaktisch fehlerhafte Fragmente werden als `malformed` annotiert, nicht repariert oder ignoriert
- **Annotationsträger**: `metadata.sourceRef.xmlAttributes.templateAnnotations`

Das mitgelieferte `GenericTemplateSyntaxProfile` erkennt `{{ }}`, `{% %}` und `#{ }` sowie Steuerungs-Tags `wenn`, `sonst wenn`, `sonst`, `ende`. Andere Apps können ein eigenes Profil implementieren.

## Konsequenzen

- Die Lib bleibt fachlich neutral: keine Bedingungsauswertung, keine Platzhalterersetzung, keine Balancing-Prüfung
- App-spezifische Dialekte erfordern lediglich eine `TemplateSyntaxProfile`-Implementierung
- Der öffentliche AST-Contract erhält ein neues additives Feld (`templateAnnotations`) → Minor-Bump `astVersion` auf `1.1.0`
- Ein möglicher separater `TemplateAst` (struktureller Template-Baum) bleibt als spätere Erweiterung offen
