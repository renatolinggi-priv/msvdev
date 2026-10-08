-- Migration 072: Hilfetext «Endschiessen Resultaterfassung» nach dem Umbau (Okt 2026):
-- Stand-Spalte, Filter Alle/Offen/Vollständig, Suche, Tastatur, «Speichern & nächstes offenes».
-- Ersetzt den Text aus 068/070/071 für diesen Schlüssel.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endresultate.uebersicht',
 'Endschiessen: Resultate erfassen',
 '<p>Hier werden die Resultate der <strong>Mitglieder</strong> am Endschiessen erfasst. Die Tabelle zeigt für das gewählte Jahr alle Mitglieder, die Stiche gelöst oder bereits Resultate haben.</p><ul><li><strong>Zellen</strong>: eine Zahl ist das erfasste Resultat, «gelöst» (gelb) heisst bezahlt, aber noch kein Resultat, «–» heisst nicht gelöst. Bei Schwini steht «gelöst P1» oder «gelöst P2», wenn nur eine Passe gelöst ist.</li><li><strong>Stand</strong>: «offen» (Zeile gelb), solange ein gelöster Stich noch kein Resultat hat, sonst «vollständig». Oben steht, wie viele Mitglieder vollständig erfasst sind.</li><li><strong>Filter und Suche</strong>: «Offen» zeigt nur, was noch zu tun ist; die Suche filtert nach Namen.</li><li><strong>Zeile anklicken</strong> (oder mit den Pfeiltasten wählen und Enter) öffnet rechts das Erfassungs-Panel. Aktiv sind nur die gelösten Stiche; nicht gelöste sind zusammengeklappt.</li><li><strong>Werte</strong>: Endstich und Schwini pro Schuss 0–10, Kunst, Glück und Zabig als Hunderterwertung 0–100. Die Totale rechnen beim Tippen mit.</li><li><strong>Speichern &amp; nächstes offenes</strong> (auch Ctrl+Enter oder Enter im letzten Feld) speichert und öffnet das nächste Mitglied mit offenen Stichen. <strong>Speichern</strong> (Ctrl+S) speichert und schliesst das Panel. Schlägt das Speichern fehl, bleiben die Eingaben stehen.</li><li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist.</li></ul><p>Der Papierkorb im Panel löscht die Resultate eines einzelnen Mitglieds. Ganze Jahre werden nicht gelöscht; frühere Jahre bleiben über die Jahresauswahl abrufbar. Resultate lassen sich auch aus der Imetron-CSV importieren (<a href="endsch_import.php">CSV-Import Endschiessen</a>); die Schüsse der Partnerinnen werden unter <a href="endresultate_partner.php">Partnerinnen erfassen</a> erfasst.</p>',
 'endresultate')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
