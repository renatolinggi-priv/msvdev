-- Migration 123: Hilfetexte PDF-Vorlage, nachdem auch das Monatsblatt im Ranglisten-Stil ist (Nov 2026, 1.68.48).
-- Die Seite zeigt nur noch Logo und «Listen und Ranglisten»; der Bereich «Monatsblatt» ist weg.
-- Zebrastreifen gibt es seit 1.68.47 auch im Jahresprogramm.
--
-- Idempotent: ON DUPLICATE KEY UPDATE bzw. UPDATE auf feste Schlüssel.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('pdf_design.uebersicht',
 'PDF-Vorlage (nur Administratoren)',
 '<p>Die <strong>PDF-Vorlage</strong> legt Logo und Farben der vom System erzeugten PDFs zentral fest. Zugriff haben nur Administratoren; der Vorstand sieht diese Seite nicht. Auf der Seite steht nur, was in den PDFs auch sichtbar wirkt.</p><ul><li><strong>Logo</strong>: erscheint in allen PDFs mit Vereinslogo.</li><li><strong>Listen und Ranglisten</strong>: gilt für alle PDFs im Stil des Absendenbuchs, also Ranglisten, Absendenanmeldung, Wanderpreise, Endschiessen lösen, Munition, Termine, Jahresprogramm, Monatsblatt, Helferabrechnungen, Fragebogen und Zielscheiben.</li></ul><p>Farben lassen sich als Hex-Wert tippen oder mit dem Farbwähler setzen. <strong>Speichern</strong> wirkt sofort auf alle danach erzeugten PDFs; <strong>Auf Standard zurücksetzen</strong> stellt die Vorgabe her (das Logo bleibt). Ungültige Werte fallen auf die Vorgabe zurück, ein PDF scheitert nie an einer Einstellung. Das Standblatt hat ein eigenes, festes Layout und hängt nicht an dieser Seite.</p>',
 'pdf_design'),

('pdf_design.listen',
 'Listen und Ranglisten',
 '<p>Diese Werte gelten für alle PDFs im Stil des Absendenbuchs: Logo links mit Titel, schwarz auf weiss, Linie unter dem Tabellenkopf, Fusszeile mit Stand und Seitenzahl.</p><ul><li><strong>Titel und Abschnitte</strong>: Dokumenttitel und Zwischentitel wie «Kat. A» oder die Monate im Monatsblatt.</li><li><strong>Linien</strong>: Linie unter dem Tabellenkopf und über den Totalzeilen.</li><li><strong>Gedämpft</strong>: Untertitel, Hinweise, Zusatzangaben und die Fusszeile.</li><li><strong>Zebrastreifen</strong>: jede zweite Zeile breiter Tabellen (JM-Rangliste, Jahresprogramm, Endschiessen lösen, Fragebogen). Weiss (#ffffff) schaltet sie aus.</li><li><strong>Hinterlegung gestrichener Resultate</strong>: Streicher in der JM-Rangliste.</li><li><strong>Logo-Höhe</strong>: Höhe des Logos im Kopf, 10 bis 30 mm.</li></ul><p>Die Vorgabe entspricht dem Aussehen des Absendenbuchs. Schrift, Abstände und Spaltenbreiten sind fest, damit Seitenumbrüche stimmen (z.&nbsp;B. das Jahresprogramm auf einer Seite oder die Broschüre des Monatsblatts).</p>',
 'pdf_design'),

('pdf_design.vorschau',
 'Live-Vorschau',
 '<p>Die Vorschau zeigt eine Beispiel-Rangliste im Stil aller PDFs: Kopf mit Logo, Titel und Untertitel, eine Tabelle mit Rängen 1 bis 3 fett, Zebrastreifen, einem gestrichenen Resultat und einer Totalzeile, darunter Hinweis und Fusszeile.</p><p>Jede Änderung an einem Feld wird <strong>sofort</strong> übernommen, auch ungespeichert. Im erzeugten PDF sieht das Ergebnis praktisch gleich aus.</p>',
 'pdf_design')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;

UPDATE hilfetexte SET ist_geloescht = 1
WHERE schluessel = 'pdf_design.monatsblatt';
