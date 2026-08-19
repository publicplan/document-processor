# Zielarchitektur: Trennung von DocumentProcessor und Converter-Schicht

## Prompt für einen späteren Agenten

Analysiere die bestehende Architektur des Dokumentprozessors und entwirf eine Zielarchitektur, die die Verantwortung zwischen `DocumentProcessor`, den `ElementConverter`-Klassen und gemeinsamen Helpern klar trennt.

### Ausgangslage
- `DocumentProcessor` orchestriert aktuell nicht nur den Dokumentfluss, sondern enthält auch Logik für Border-Gruppen, Listen und HTML-Wrapper.
- `TextRunElementConverter` und `TextBoxElementConverter` rendern Elemente, enthalten aber teilweise ähnliche Stil- und Border-Entscheidungen wie der Processor.
- Es gibt bereits wiederverwendbare Helper wie `BorderStyleHelper`.

### Ziel
Erarbeite eine Architektur, in der:
1. `DocumentProcessor` nur noch Ablauf, Kontext, Gruppierung und Zusammensetzung steuert.
2. Converter nur noch das Rendering eines einzelnen Elements übernehmen.
3. Stil-Normalisierung und wiederverwendbare Regeln in dedizierte Helper ausgelagert sind.
4. Gruppierungslogik wie Border-Blöcke nicht mehr direkt im Processor vermischt ist, sondern über klare Abstraktionen läuft.

### Was das Konzept enthalten soll
- Zielbild der Verantwortlichkeiten pro Schicht
- Vorschlag für neue oder umzusetzende Abstraktionen
- Beschreibung des Datenflusses von Word-Elementen zu HTML
- Abgrenzung, welche Logik im Processor bleiben darf und welche nicht
- Migrationspfad in sinnvollen Schritten
- Risiken, Nebenwirkungen und mögliche Inkonsistenzen

### Wichtig
- **Keine Implementierung**
- **Kein Code**
- **Keine konkreten Änderungen an Dateien**
- Nur ein belastbares Architekturkonzept mit klarer Trennung und Begründung

### Erwartetes Ergebnis
Ein kompaktes, aber belastbares Zielarchitektur-Dokument, das als Grundlage für eine spätere Umsetzung durch einen Agenten dient.
