# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Vorstand** (Ehrenamtliche, Rolle `vorstand`): erfasst Resultate, führt Mitglieder, plant Einsätze, erzeugt Ranglisten und Ausdrucke. Arbeitet im Admin-Bereich (`admin.msvwilen.ch`) **meist am Laptop zuhause**, am Abend nach einem Anlass oder vor dem Absenden; das Handy ist die Ausnahme (bestätigt 08.10.2026).
- **Admin** (eine Person, zugleich Entwickler): wie Vorstand, zusätzlich Einstellungen, Benutzer, Navigation, Hilfetexte, Datenbank-Aktualisierung, Sicherungen.
- **Mitglieder** (Rolle `mitglied`): nutzen das Mitgliederportal (`mitglieder.msvwilen.ch`, PWA, vorwiegend Handy): Resultate und Ranglisten, Termine, Einsätze und Tausch, Dokumente, Umfragen, Foto-Galerie, Mitteilungen/Push, Selbstmeldung von JM-Resultaten.
- **Jungschützen und JSK-Leitung** (Rolle `jungschuetze`, Flag `ist_jsk_leiter`): eigener Portal-Bereich mit Betreuung, Chat, Resultaten, Dokumenten.
- **Öffentlich, ohne Login**: Jahresprogramm als PDF, öffentlicher Fragebogen.

Der Admin-Bereich ist Gegenstand der laufenden Überarbeitung; das Portal folgt später.

## Product Purpose

Vereinsverwaltung des **Militärschützenvereins Wilen (MSV Wilen)**, gebaut um das Schützenjahr herum: Jahresmeisterschaft (JM) über alle Anlässe, Endschiessen mit Stichen und Absenden, Heimmeisterschaft, Kantonalstich, Sektionsmeisterschaft, Vereinscup, Wanderpreise und Sieger, Ranglisten und Ausdrucke (PDF, Word, Excel inkl. offizieller SKSG-Formulare), Mitglieder- und Benutzerverwaltung, Einsatzplanung (Helferdienste), Munitionskauf, Termine, Umfragen.

Erfolg heisst: Der Vorstand erledigt die Arbeit einer Saison ohne Schulung, schnell und **fehlerfrei** (korrekte Ranglisten, nichts geht verloren), und die Mitglieder sind informiert, ohne nachfragen zu müssen.

## Positioning

Kein generisches Vereins-Tool: Die Regeln eines Schweizer Schützenvereins sind im System abgebildet und werden gerechnet statt im Kopf gehalten, etwa Streicher, Hochrechnung bei Anlässen unter 100 Punkten, Stiche und Passen, Kat. A/B nach Waffe, Lösen und Absenden, die SKSG-Abrechnung sowie eine Startseite, die nach Saisonphase zeigt, was gerade ansteht.

## Operating Context

- **Saisonrhythmus**: Definition des Jahresprogramms → Anlässe mit Resultaterfassung → Endschiessen (Lösen, Erfassen) → Ranglisten, Absendenbuch, Preise. Die Startseite ordnet sich nach diesen Phasen.
- **Jahre**: Alle Daten sind nach Jahr getrennt; jedes frühere Jahr muss abrufbar bleiben. Ganze Jahre werden nicht gelöscht (Entscheid 08.10.2026), das gewählte Jahr gilt seitenübergreifend.
- **Datenquellen**: manuelle Erfassung, Imetron-CSV (Endschiessen), Heim/Kanti-CSV, PDF-Ranglisten fremder Anlässe, Selbstmeldung durch Mitglieder.
- **Ausgaben**: PDF-Ranglisten und -Listen (Dompdf, eigenes PDF-Theme), Word/Excel-Vorlagen, SKSG-Formular (xlsm), Direktdruck über QZ Tray.
- **Betrieb**: eine Produktionsumgebung ohne Staging; jede gespeicherte Datei ist sofort live (SFTP-Watcher). Tägliche DB-Sicherung, Sicherung vor riskanten Aktionen.
- **Sprache**: Deutsch (de-CH), Schweizer Rechtschreibung (ss statt ß), Du-Form, typografische Anführungszeichen «…».

## Capabilities and Constraints

- **Stack (Bestand)**: PHP 8.3 auf Hostpoint (FreeBSD), MariaDB, Bootstrap 5, jQuery, SweetAlert2, Bootstrap Icons; kein Build-System. PWA mit Web Push.
- **Rollen und Rechte**: admin, vorstand, mitglied, jungschuetze; Admin-Endpunkte müssen die Rolle prüfen.
- **Fachbegriffe**: JM, Anlass, Endschiessen, Endstich, Stich, Passe, Streicher, Kat. A/B, Lösen, Absenden, Kantonalstich (Kanti), Heimmeisterschaft, Sektionsmeisterschaft, Vereinscup, Wanderpreis, Obligatorisch, Feldschiessen, JSK, Vorstand.
- **Offene Entscheidung – Mehrere Vereine (SaaS)**: `MULTITENANT_KONZEPT.md` plant den Betrieb für mehrere Schützenvereine mit eigenem Namen, Logo und Farben pro Verein. Ob das kommt, ist **offen**. Bis zum Entscheid darf die Gestaltung nichts verbauen: Vereinsname, Logo und Vereinsfarbe als austauschbare Schicht.

## Brand Commitments

- **Vereinslogo verbindlich**: «Militärschützenverein Wilen, 1894 / 1992» (`images/MSVWilen_Logo.jpg`, App-Icons in `icons/`). Darüber hinaus ist das Erscheinungsbild der App frei (bestätigt 08.10.2026).
- **Name**: «MSV Wilen» (App- und PWA-Name).
- **Stimme**: sachlich, freundlich, Du-Form, kurze Sätze in der Sprache des Vereins (Fachbegriffe oben), keine Entwicklersprache in der Oberfläche.

## Evidence on Hand

- Echte Vereinsdaten in der Produktionsdatenbank (Mitglieder, Resultate mehrerer Jahre, Definitionen, Einsätze).
- Vereinslogo und App-Icons; Vorlagen für PDF, Word und Excel; offizielles SKSG-Abrechnungsformular.
- Rund 160 Hilfetexte (Tabelle `hilfetexte`, Migrationen 066–071), Changelog (`changelog.json`).
- Keine Testimonials, Kennzahlen oder Fremdkunden – nichts davon erfinden.

## Product Principles

1. **Nichts geht verloren.** Jedes Jahr bleibt abrufbar; riskante Aktionen sichern vorher; wer nicht im laufenden Jahr arbeitet, sieht das deutlich.
2. **Die Saison führt.** Zuerst steht, was jetzt ansteht; selten Gebrauchtes tritt zurück.
3. **Ehrenamt-tauglich.** Wer einmal im Monat am Abend etwas erfasst, muss es ohne Einführung richtig machen können; Hilfe steht dort, wo die Frage entsteht.
4. **Regeln im System, nicht im Kopf.** Streicher, Hochrechnung, Stiche und Preise rechnet die App; die Oberfläche erklärt das Ergebnis, statt es dem Menschen zu überlassen.
5. **Ein Verein heute, vielleicht mehrere morgen.** Vereinsidentität (Name, Logo, Farbe) bleibt eine austauschbare Schicht, solange der SaaS-Entscheid offen ist.
