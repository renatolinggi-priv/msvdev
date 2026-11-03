-- Migration 121: Hilfetexte PDF-Vorlage nach dem Umbau auf «Listen und Ranglisten» (Nov 2026, 1.68.45).
-- Fast alle PDFs nutzen seit 1.68.28/1.68.44 den Ranglisten-Stil (pdf_rangliste_css). Die Vorlage hat dafür eine
-- eigene Gruppe «Listen» (Titel, Linien, Gedämpft, Zebra, Streichfarbe, Logo-Höhe); die bisherigen Gruppen
-- wirken nur noch auf ältere Ausdrucke wie das Monatsblatt.
--
-- Idempotent: ON DUPLICATE KEY UPDATE; diese Datei ist die Quelle der Wahrheit für die Texte.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('pdf_design.uebersicht',
 'PDF-Vorlage (nur Administratoren)',
 '<p>Die <strong>PDF-Vorlage</strong> legt Logo und Aussehen der vom System erzeugten PDFs zentral fest. Zugriff haben nur Administratoren; der Vorstand sieht diese Seite nicht.</p><ul><li><strong>Logo</strong>: erscheint in allen PDFs mit Vereinslogo.</li><li><strong>Listen und Ranglisten</strong>: gilt für fast alle PDFs (Ranglisten, Absendenanmeldung, Wanderpreise, Endschiessen lösen, Munition, Termine, Helferabrechnungen, Fragebogen, Zielscheiben).</li><li><strong>Monatsblatt und ältere Ausdrucke</strong>: die früheren Farben und Layout-Werte; sie wirken nur noch auf Ausdrucke, die nicht im Ranglisten-Stil sind.</li></ul><p>Farben lassen sich als Hex-Wert tippen oder mit dem Farbwähler setzen. <strong>Speichern</strong> wirkt sofort auf alle danach erzeugten PDFs; <strong>Auf Standard zurücksetzen</strong> stellt überall die Vorgabe her (das Logo bleibt). Ungültige Werte fallen auf die Vorgabe zurück, ein PDF scheitert nie an einer Einstellung.</p>',
 'pdf_design'),

('pdf_design.listen',
 'Listen und Ranglisten',
 '<p>Diese Werte gelten für alle PDFs im Stil des Absendenbuchs: Logo links mit Titel, schwarz auf weiss, Linie unter dem Tabellenkopf, Fusszeile mit Stand und Seitenzahl.</p><ul><li><strong>Titel und Abschnitte</strong>: Dokumenttitel und Zwischentitel wie «Kat. A».</li><li><strong>Linien</strong>: Linie unter dem Tabellenkopf und über den Totalzeilen.</li><li><strong>Gedämpft</strong>: Untertitel, Hinweise, Zusatzangaben und die Fusszeile.</li><li><strong>Zebrastreifen</strong>: jede zweite Zeile breiter Tabellen (JM-Rangliste, Endschiessen lösen, Fragebogen). Weiss (#ffffff) schaltet sie aus.</li><li><strong>Hinterlegung gestrichener Resultate</strong>: Streicher in der JM-Rangliste.</li><li><strong>Logo-Höhe</strong>: Höhe des Logos im Kopf, 10 bis 30 mm.</li></ul><p>Die Vorgabe entspricht dem bisherigen Aussehen. Schrift, Abstände und Spaltenbreiten sind fest, damit Seitenumbrüche (z.&nbsp;B. 32 Namen auf der ersten Seite der Absendenanmeldung) stimmen.</p>',
 'pdf_design'),

('pdf_design.aeltere',
 'Monatsblatt und ältere Ausdrucke',
 '<p>Die Gruppen <strong>Allgemein</strong>, <strong>Tabelle</strong>, <strong>Medaillen</strong>, <strong>Status</strong> und <strong>Layout</strong> stammen aus der Zeit vor dem Ranglisten-Stil. Sie wirken nur noch auf Ausdrucke, die diesen Stil nicht verwenden, zurzeit vor allem das <strong>Monatsblatt</strong>.</p><p>Auf Ranglisten und Listen haben sie keine Wirkung; dafür ist die Gruppe <strong>Listen und Ranglisten</strong> da. Die Vorschau «Monatsblatt und ältere Ausdrucke» zeigt, wie diese Werte aussehen.</p>',
 'pdf_design'),

('pdf_design.logo',
 'Zentrales Logo ersetzen',
 '<p><strong>Logo ersetzen</strong> nimmt eine PNG- oder JPEG-Datei bis 6 MB an. Im nächsten Schritt wird der <strong>Bildausschnitt</strong> frei gewählt; transparente Flächen werden weiss, das Ergebnis wird als JPEG mit höchstens 1200 px Breite gespeichert.</p><ul><li>Das neue Logo wird als zentrales Logo abgelegt <strong>und</strong> in alle Modul-Kopien verteilt. Es erscheint damit in allen PDFs mit Vereinslogo und auf der Startseite des Admin-Bereichs.</li><li>Das bisherige Logo bleibt als Sicherung erhalten.</li><li>Die <strong>Grösse</strong> im PDF wird unter Listen und Ranglisten → Logo-Höhe eingestellt (ältere Ausdrucke: Layout → Logo-Breite).</li></ul><p>Die Kantonalstich-Abrechnung verwendet bewusst das SKSG-Logo und ist davon nicht betroffen.</p>',
 'pdf_design'),

('pdf_design.vorlagen',
 'Vorlagen (Presets)',
 '<p>Die Vorlagen setzen mit einem Klick die Werte der <strong>älteren Ausdrucke</strong> (Monatsblatt); die Gruppe «Listen und Ranglisten» bleibt dabei unverändert:</p><ul><li><strong>Hell &amp; minimal</strong>: die Standardwerte (heller Tabellenkopf, dezente Rahmen).</li><li><strong>Dezent markentreu</strong>: Vereinsblau als Kopfband mit weisser Kopfschrift, angepasste Rahmen-, Zebra- und Total-Farben; die übrigen Werte wie Standard.</li></ul><p>Ein Klick füllt nur die Felder und die Vorschau, gespeichert wird erst mit <strong>Speichern</strong>.</p>',
 'pdf_design'),

('pdf_design.vorschau',
 'Live-Vorschau',
 '<p>Rechts stehen zwei Vorschauen mit Beispieldaten:</p><ul><li><strong>Listen und Ranglisten</strong>: Kopf mit Logo, Titel und Untertitel, eine Tabelle mit Rängen 1 bis 3 fett, Zebrastreifen, einem gestrichenen Resultat und einer Totalzeile, darunter Hinweis und Fusszeile.</li><li><strong>Monatsblatt und ältere Ausdrucke</strong>: die früheren Farben für Tabellenkopf, Medaillen und Status.</li></ul><p>Jede Änderung an einem Feld oder einer Vorlage wird <strong>sofort</strong> übernommen, auch ungespeichert. Im erzeugten PDF sieht das Ergebnis praktisch gleich aus.</p>',
 'pdf_design')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
