-- Migration 122: Hilfetexte PDF-Vorlage nach dem Aufräumen (Nov 2026, 1.68.46).
-- Die Seite zeigt nur noch, was in den PDFs sichtbar wirkt: Logo, «Listen und Ranglisten» und für das Monatsblatt
-- Textfarbe und Überschriften. Die früheren Gruppen (Tabelle, Medaillen, Status, Layout) und die Vorlagen sind
-- weg; ihre Hilfetexte werden als gelöscht markiert (Schlüssel stehen nicht mehr im Markup).
--
-- Idempotent: ON DUPLICATE KEY UPDATE bzw. UPDATE auf feste Schlüssel.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('pdf_design.uebersicht',
 'PDF-Vorlage (nur Administratoren)',
 '<p>Die <strong>PDF-Vorlage</strong> legt Logo und Farben der vom System erzeugten PDFs zentral fest. Zugriff haben nur Administratoren; der Vorstand sieht diese Seite nicht. Auf der Seite steht nur, was in den PDFs auch sichtbar wirkt.</p><ul><li><strong>Logo</strong>: erscheint in allen PDFs mit Vereinslogo.</li><li><strong>Listen und Ranglisten</strong>: gilt für fast alle PDFs (Ranglisten, Absendenanmeldung, Wanderpreise, Endschiessen lösen, Munition, Termine, Helferabrechnungen, Fragebogen, Zielscheiben).</li><li><strong>Monatsblatt</strong>: Text- und Überschriftenfarbe; sonst hat das Monatsblatt ein eigenes, festes Layout.</li></ul><p>Farben lassen sich als Hex-Wert tippen oder mit dem Farbwähler setzen. <strong>Speichern</strong> wirkt sofort auf alle danach erzeugten PDFs; <strong>Auf Standard zurücksetzen</strong> stellt überall die Vorgabe her (das Logo bleibt). Ungültige Werte fallen auf die Vorgabe zurück, ein PDF scheitert nie an einer Einstellung. Standblatt und Jahresprogramm haben feste Farben und hängen nicht an dieser Seite.</p>',
 'pdf_design'),

('pdf_design.monatsblatt',
 'Monatsblatt',
 '<p>Das <strong>Monatsblatt</strong> (Schiesszeiten eines Monats) hat ein eigenes Layout mit festen Tabellenfarben. Von der PDF-Vorlage übernimmt es nur zwei Farben:</p><ul><li><strong>Textfarbe</strong>: der normale Text.</li><li><strong>Überschriften</strong>: der Titel auf der ersten Seite und die Tagestitel.</li></ul><p>Die Vorschau «Monatsblatt» zeigt beides mit Beispieldaten.</p>',
 'pdf_design'),

('pdf_design.logo',
 'Zentrales Logo ersetzen',
 '<p><strong>Logo ersetzen</strong> nimmt eine PNG- oder JPEG-Datei bis 6 MB an. Im nächsten Schritt wird der <strong>Bildausschnitt</strong> frei gewählt; transparente Flächen werden weiss, das Ergebnis wird als JPEG mit höchstens 1200 px Breite gespeichert.</p><ul><li>Das neue Logo wird als zentrales Logo abgelegt <strong>und</strong> in alle Modul-Kopien verteilt. Es erscheint damit in allen PDFs mit Vereinslogo und auf der Startseite des Admin-Bereichs.</li><li>Das bisherige Logo bleibt als Sicherung erhalten.</li><li>Die <strong>Höhe</strong> im PDF wird unter Listen und Ranglisten → Logo-Höhe eingestellt.</li></ul><p>Die Kantonalstich-Abrechnung verwendet bewusst das SKSG-Logo und ist davon nicht betroffen.</p>',
 'pdf_design'),

('pdf_design.vorschau',
 'Live-Vorschau',
 '<p>Rechts stehen zwei Vorschauen mit Beispieldaten:</p><ul><li><strong>Listen und Ranglisten</strong>: Kopf mit Logo, Titel und Untertitel, eine Tabelle mit Rängen 1 bis 3 fett, Zebrastreifen, einem gestrichenen Resultat und einer Totalzeile, darunter Hinweis und Fusszeile.</li><li><strong>Monatsblatt</strong>: Titel, ein Tagestitel und eine kurze Tabelle in den Monatsblatt-Farben.</li></ul><p>Jede Änderung an einem Feld wird <strong>sofort</strong> übernommen, auch ungespeichert. Im erzeugten PDF sieht das Ergebnis praktisch gleich aus.</p>',
 'pdf_design')
ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;

UPDATE hilfetexte SET ist_geloescht = 1
WHERE schluessel IN ('pdf_design.vorlagen', 'pdf_design.aeltere');
