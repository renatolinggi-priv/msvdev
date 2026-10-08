-- Migration 092: Hilfetexte zu den vereinheitlichten Ausgabe-Knöpfen (Okt 2026).
-- Knöpfe heissen nach dem Dokument («Rangliste», «Termine»), das Format steht nur bei Word, Excel, CSV und
-- Kalender in Klammern; PDF ist der Normalfall. Dokument und Drucker bilden eine Gruppe. Neu in der Kopf-Karte:
-- Sektionsabrechnung (JM-Durchschnitt), Bezüge (Munitionsverkauf), Helferstunden (Jungschützen). Die
-- Wanderpreis-Berichte fragen kein Jahr mehr ab, sondern nehmen das oben gewählte.
--
-- Gezielte Ersetzungen statt ganzer Texte, damit Anpassungen anderer Migrationen (z.B. 084, 086) erhalten bleiben.
-- Findet REPLACE den Satz nicht (im Editor geändert), bleibt der Text unverändert.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '«Zur Erfassung» wechselt auf die Resultaterfassung.</li><li><strong>Rangliste PDF</strong> erstellt die Rangliste als Datei,',
  '«Resultate bearbeiten» wechselt auf die Resultaterfassung.</li><li><strong>Rangliste</strong> erstellt die Rangliste als PDF und lädt sie herunter,')
WHERE schluessel = 'kantiabr.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Dokumente erstellen</strong> erzeugt die PDF-Ranglisten.',
  'die Knöpfe daneben erzeugen die PDF-Ranglisten.')
WHERE schluessel = 'jmrang.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Rangliste (nach Rang)</strong>',
  '<strong>Rangliste nach Rang</strong>')
WHERE schluessel = 'jmrang.dokumente';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<strong>Rangliste (nach Name)</strong>',
  '<strong>Rangliste nach Name</strong>')
WHERE schluessel = 'jmrang.dokumente';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Tabelle und Zusammenfassung erscheinen sofort, danach <strong>PDF exportieren</strong> oder direkt drucken',
  'Tabelle und Zusammenfassung erscheinen sofort, danach oben <strong>Sektionsabrechnung</strong> (PDF) oder direkt drucken; beide sind gesperrt, bis ein Anlass mit Resultaten gewählt ist')
WHERE schluessel = 'jmdurchschnitt.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Alle (DOCX)</strong> lädt nacheinander',
  '<li><strong>Alle Standblätter (Word)</strong> lädt nacheinander')
WHERE schluessel = 'jmstandblatt.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Alle (PDF)</strong> erstellt ein Sammel-PDF;',
  '<li><strong>Alle Standblätter</strong> erstellt ein Sammel-PDF;')
WHERE schluessel = 'jmstandblatt.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Alle drucken</strong> schickt dasselbe Sammel-PDF',
  '<li>Der <strong>Drucker-Knopf</strong> daneben schickt dasselbe Sammel-PDF')
WHERE schluessel = 'jmstandblatt.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong> erzeugt die Cup-Rangliste des gewählten Jahres.</li>',
  '<li><strong>Rangliste</strong> erzeugt die Cup-Rangliste des gewählten Jahres als PDF, mit denselben Daten wie die Seite «Vereinscup Rangliste»; ungespeicherte Eingaben fehlen darin. Der Drucker-Knopf daneben druckt sie direkt.</li>')
WHERE schluessel = 'cup.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong> erstellt die Liste «Einzelrangierungen» des Jahres',
  '<li><strong>Einzelrangierungen</strong> erstellt die Liste des Jahres als PDF')
WHERE schluessel = 'einzelrangierung.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong> erstellt die Liste «Sektionsrangierungen» des Jahres',
  '<li><strong>Sektionsrangierungen</strong> erstellt die Liste des Jahres als PDF')
WHERE schluessel = 'sektionsrangierungen.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'gefundene Stiche prüfen. <strong>PDF Generieren</strong> erstellt die Datei',
  'gefundene Stiche prüfen. <strong>Zielscheiben</strong> erstellt das PDF')
WHERE schluessel = 'endsch_targetprint.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF Generieren</strong> ist erst aktiv,',
  '<li><strong>Zielscheiben</strong> (PDF) und der Drucker-Knopf sind erst aktiv,')
WHERE schluessel = 'endsch_targetprint.stiche';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Gesamt</strong>: Gesamtrangliste Kat. A und B mit allen Stichen. <strong>Zwischen</strong>: dieselbe Liste',
  '<li><strong>Zwischenrangliste</strong>: die Gesamtrangliste')
WHERE schluessel = 'endschrang.dokumente';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Anmeldung</strong>: Liste der Absenden-Anmeldungen',
  '<li><strong>Anmeldungen</strong>: Liste der Absenden-Anmeldungen')
WHERE schluessel = 'endschrang.dokumente';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'dann <strong>Excel</strong>; die Datei wird direkt heruntergeladen.',
  'dann <strong>Abrechnung (Excel)</strong>; die Datei wird direkt heruntergeladen.')
WHERE schluessel = 'schuetzenabr.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>CSV</strong> ist der vollständige Export (auch als Vorlage für den Import), <strong>Adressliste</strong> eine Excel-Datei',
  '<li><strong>Mitglieder (CSV)</strong> ist der vollständige Export (auch als Vorlage für den Import), <strong>Adressliste (Excel)</strong> eine Excel-Datei')
WHERE schluessel = 'mitgliederverwaltung.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong> erstellt die Liste des Jahres mit den Summen pro Verein; der Download-Link erscheint unter der Tabelle.</li>',
  '<li><strong>Helferstunden</strong> (oben) erstellt die Liste des Jahres mit den Summen pro Verein als PDF und lädt sie herunter; es zählen die gespeicherten Stunden.</li>')
WHERE schluessel = 'jungschuetzen_helfer.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong>: Terminliste des gewählten Jahres.',
  '<li><strong>Termine</strong>: Terminliste des gewählten Jahres als PDF.')
WHERE schluessel = 'wichtigetermine.exporte';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>ICS</strong>: alle wichtigen Termine',
  '<li><strong>Termine (Kalender)</strong>: alle wichtigen Termine')
WHERE schluessel = 'wichtigetermine.exporte';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'von dort lässt sich die Historie als PDF ausgeben.',
  'von dort lädt <strong>Historie</strong> sie als PDF herunter.')
WHERE schluessel = 'wanderpreise.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Jeder Bericht fragt ein Jahr ab, vorbelegt mit dem gewählten: CSV (Tabellenexport), PDF Alle (Jahresbericht über alle Preise), JM Preise (Bericht der drei besten Schützen), Mitglieder (kompakte Mitglieder-Information).',
  'Jeder Bericht gilt für das oben gewählte Jahr und wird direkt heruntergeladen: Liste (CSV, Tabellenexport), Jahresbericht (über alle Preise), JM-Preise (Bericht der drei besten Schützen), Mitglieder-Info (kompakte Mitglieder-Information).')
WHERE schluessel = 'wanderpreise.aktionen';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<p><strong>Fragebogen PDF</strong> erstellt die Auswertung',
  '<p><strong>Fragebogen</strong> erstellt die Auswertung')
WHERE schluessel = 'mitgliederfragebogen.pdf';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<p><strong>Monatsblatt PDF</strong> lädt die Datei direkt herunter,',
  '<p><strong>Monatsblatt</strong> lädt das PDF direkt herunter,')
WHERE schluessel = 'monatsblatt.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Direkt exportieren</strong> erzeugt die Schiesstagemeldung',
  '<li><strong>Schiesstage-Meldung (Excel)</strong> erzeugt die Schiesstagemeldung')
WHERE schluessel = 'standbelegung.import';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>JSK PDF</strong>: alle Einträge des Jahres,',
  '<li><strong>JSK-Termine</strong>: PDF mit allen Einträgen des oben gewählten Jahres (bei «Alle Jahre» erst ein Jahr wählen),')
WHERE schluessel = 'standbelegung.eintraege';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Schiesstage</strong>: Schiesstagemeldung als Excel',
  '<li><strong>Schiesstage-Meldung (Excel)</strong>: Schiesstagemeldung als Excel')
WHERE schluessel = 'standbelegung.eintraege';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>PDF</strong> erstellt die Liste mit demselben Zeitraum-Filter als Datei.</li>',
  '<li><strong>Bezüge</strong> oben in der Kopf-Karte erstellt die Liste mit demselben Zeitraum-Filter als PDF.</li>')
WHERE schluessel = 'munitionskauf.bezuege';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Summen unten, Löschen pro Zeile, PDF und Direktdruck.</li>',
  'Summen unten, Löschen pro Zeile. Die Liste des gewählten Zeitraums gibt es oben als <strong>Bezüge</strong> (PDF) und per Direktdruck.</li>')
WHERE schluessel = 'munitionskauf.uebersicht';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Export / Drucken</strong>: Word (Ansicht «Funktionen» oder «Personen»), PDF, Excel; das PDF kann direkt als Dokument fürs Portal abgelegt werden.</li>',
  '<li><strong>Dokumente</strong> (Menü) und Drucker-Knopf: Einsatzplan als Word (Ansicht «Funktionen» oder «Personen»), PDF oder Excel; das PDF kann direkt als Dokument fürs Portal abgelegt werden.</li>')
WHERE schluessel = 'einsatzplanung.editor';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<p><strong>Excel</strong> füllt die Vorlage des Vereins mit Formeln (Excel rechnet beim Öffnen), <strong>PDF</strong> gibt die Zusammenfassung quer aus.',
  '<p><strong>Abrechnung (Excel)</strong> füllt die Vorlage des Vereins mit Formeln (Excel rechnet beim Öffnen), <strong>Abrechnung</strong> gibt die Zusammenfassung als PDF quer aus.')
WHERE schluessel = 'einsatzplanung.abrechnung';
