-- Migration 084: Menütext = Seitentitel = Browser-Tab, einheitliches Namensschema (Clarify Okt 2026)
--
-- Entscheide 08.10.2026: Wettkampf vorne, Tätigkeit hinten («Jahresmeisterschaft erfassen»,
-- «Jahresmeisterschaft Rangliste», «Kantonalstich Abrechnung»), «Munitionsverkauf», «Stiche lösen».
-- Menü und Tab kommen aus navigation.Text, die Seitentitel ($page_title) stehen im Code.
-- Mitgezogen: Titel der Übersichts-Hilfetexte und Hilfetexte, die eine Seite mit altem Namen nennen
-- (gezielt je Schlüssel; das Druckprofil «Endschiessen Ranglisten» bleibt unverändert).
-- Idempotent: UPDATE nach Link bzw. altem Text, REPLACE findet beim zweiten Lauf nichts mehr.

-- Hauptgruppen
UPDATE navigation SET Text = 'Resultate erfassen'       WHERE Text = 'Resultat Erfassung' AND ParentID = 0;
UPDATE navigation SET Text = 'Schiessdaten importieren' WHERE Text = 'Schiessdaten Import' AND ParentID = 0;

-- Resultate erfassen
UPDATE navigation SET Text = 'Jahresmeisterschaft erfassen'   WHERE Link = 'jmresultate.php';
UPDATE navigation SET Text = 'Heimmeisterschaft erfassen'     WHERE Link = 'heimresultate.php';
UPDATE navigation SET Text = 'Kantonalstich erfassen'         WHERE Link = 'kantiresultate.php';
UPDATE navigation SET Text = 'Endschiessen erfassen'          WHERE Text = 'Endschiessen Erfassung';
UPDATE navigation SET Text = 'Endschiessen Stiche lösen'      WHERE Link = 'endschloesen.php';
UPDATE navigation SET Text = 'Endschiessen Resultate erfassen' WHERE Link = 'endresultate.php';
UPDATE navigation SET Text = 'Endschiessen Partnerinnen erfassen' WHERE Link = 'endresultate_partner.php';
UPDATE navigation SET Text = 'Vereinscup erfassen'            WHERE Link = 'cup.php';
UPDATE navigation SET Text = 'Sektionsrangierungen erfassen'  WHERE Link = 'sektionsrangierungen.php';
UPDATE navigation SET Text = 'Einzelrangierungen erfassen'    WHERE Link = 'einzelrangierung.php';

-- Ranglisten (Menü war schon im Schema; nur zur Sicherheit)
UPDATE navigation SET Text = 'Jahresmeisterschaft Rangliste' WHERE Link = 'jmrang.php';
UPDATE navigation SET Text = 'Heimmeisterschaft Rangliste'   WHERE Link = 'heimrang.php';
UPDATE navigation SET Text = 'Kantonalstich Rangliste'       WHERE Link = 'kantirang.php';
UPDATE navigation SET Text = 'Endschiessen Rangliste'        WHERE Link = 'endschrang.php';
UPDATE navigation SET Text = 'Vereinscup Rangliste'          WHERE Link = 'cuprang.php';
UPDATE navigation SET Text = 'Wanderpreise'                  WHERE Link = 'wanderpreise.php';

-- Abrechnung
UPDATE navigation SET Text = 'Munitionsverkauf'              WHERE Link = 'munitionskauf.php';
UPDATE navigation SET Text = 'Kantonalstich Abrechnung'      WHERE Link = 'kantiabr.php';

-- Schiessdaten importieren
UPDATE navigation SET Text = 'Heim und Kanti importieren'    WHERE Link = 'heimkanti_import.php';
UPDATE navigation SET Text = 'Endschiessen importieren'      WHERE Link = 'endsch_import.php';
UPDATE navigation SET Text = 'Imetron-Stichnummern'          WHERE Link = 'internestichedef.php';

-- Titel der Übersichts-Hilfetexte = Seitentitel
UPDATE hilfetexte SET titel = 'Jahresmeisterschaft erfassen'      WHERE schluessel = 'jmresultate.uebersicht';
UPDATE hilfetexte SET titel = 'Heimmeisterschaft erfassen'        WHERE schluessel = 'heimresultate.uebersicht';
UPDATE hilfetexte SET titel = 'Kantonalstich erfassen'            WHERE schluessel = 'kantiresultate.uebersicht';
UPDATE hilfetexte SET titel = 'Endschiessen Stiche lösen'         WHERE schluessel = 'endschloesen.uebersicht';
UPDATE hilfetexte SET titel = 'Endschiessen Resultate erfassen'   WHERE schluessel = 'endresultate.uebersicht';
UPDATE hilfetexte SET titel = 'Endschiessen Partnerinnen erfassen' WHERE schluessel = 'endresultate_partner.uebersicht';
UPDATE hilfetexte SET titel = 'Vereinscup erfassen'               WHERE schluessel = 'cup.uebersicht';
UPDATE hilfetexte SET titel = 'Sektionsrangierungen erfassen'     WHERE schluessel = 'sektionsrangierungen.uebersicht';
UPDATE hilfetexte SET titel = 'Einzelrangierungen erfassen'       WHERE schluessel = 'einzelrangierung.uebersicht';
UPDATE hilfetexte SET titel = 'Jahresmeisterschaft Rangliste'     WHERE schluessel = 'jmrang.uebersicht';
UPDATE hilfetexte SET titel = 'Heimmeisterschaft Rangliste'       WHERE schluessel = 'heimrang.uebersicht';
UPDATE hilfetexte SET titel = 'Kantonalstich Rangliste'           WHERE schluessel = 'kantirang.uebersicht';
UPDATE hilfetexte SET titel = 'Endschiessen Rangliste'            WHERE schluessel = 'endschrang.uebersicht';
UPDATE hilfetexte SET titel = 'Vereinscup Rangliste'              WHERE schluessel = 'cuprang.uebersicht';
UPDATE hilfetexte SET titel = 'Wanderpreise'                      WHERE schluessel = 'wanderpreise.uebersicht';
UPDATE hilfetexte SET titel = 'Munitionsverkauf'                  WHERE schluessel = 'munitionskauf.uebersicht';
UPDATE hilfetexte SET titel = 'Kantonalstich Abrechnung'          WHERE schluessel = 'kantiabr.uebersicht';
UPDATE hilfetexte SET titel = 'Heim und Kanti importieren'        WHERE schluessel = 'heimkanti_import.uebersicht';
UPDATE hilfetexte SET titel = 'Endschiessen importieren'          WHERE schluessel = 'endsch_import.uebersicht';

-- Hilfetexte, die eine Seite mit altem Namen nennen
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, 'in der Erfassung Jahresmeisterschaft', 'unter Jahresmeisterschaft erfassen')
 WHERE schluessel IN ('einzelrangierung.uebersicht', 'sektionsrangierungen.uebersicht');
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Endschiessen Resultaterfassung</a>', '>Endschiessen Resultate erfassen</a>')
 WHERE schluessel = 'endresultate_partner.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>CSV-Import Heim/Kanti</a>', '>Heim und Kanti importieren</a>')
 WHERE schluessel IN ('heimresultate.uebersicht', 'kantiresultate.uebersicht');
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>CSV-Import Endschiessen</a>', '>Endschiessen importieren</a>')
 WHERE schluessel = 'endresultate.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Jahresmeisterschaft Ranglisten</a>', '>Jahresmeisterschaft Rangliste</a>')
 WHERE schluessel = 'jmresultate.rangliste';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Heimmeisterschaft Ranglisten<', '>Heimmeisterschaft Rangliste<')
 WHERE schluessel = 'heimresultate.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Kantonalstich Ranglisten<', '>Kantonalstich Rangliste<')
 WHERE schluessel = 'kantiresultate.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, 'unter «Kantonalstich – Ranglisten» (<a href="kantiabr.php">Kantonalstich Ranglisten und Abrechnung</a>)', 'unter <a href="kantiabr.php">Kantonalstich Abrechnung</a>')
 WHERE schluessel = 'kantirang.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, 'in der Abrechnung (<a href="kantiabr.php">Kantonalstich Ranglisten und Abrechnung</a>)', 'in der <a href="kantiabr.php">Kantonalstich Abrechnung</a>')
 WHERE schluessel = 'kantiresultate.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Endschiessen Ranglisten</a>', '>Endschiessen Rangliste</a>')
 WHERE schluessel IN ('endresultate.ansage', 'endresultate_partner.uebersicht', 'endresultate_partner.sieunder');
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Partnerinnen erfassen<', '>Endschiessen Partnerinnen erfassen<')
 WHERE schluessel = 'endresultate.sieunder';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Heimmeisterschaft Resultate</a>', '>Heimmeisterschaft erfassen</a>')
 WHERE schluessel = 'heimrang.uebersicht';
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html, '>Kantonalstich Resultate</a>', '>Kantonalstich erfassen</a>')
 WHERE schluessel = 'kantirang.uebersicht';
