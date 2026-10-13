-- Migration 099: Hilfetexte «Sieger der letzten Jahre» (1.68.9, Okt 2026): neuer Knopf «Aus Ranglisten übernehmen»
-- (sieger.uebernehmen), Übersicht nachgeführt (Sieger werden nicht mehr nur von Hand erfasst).
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('sieger.uebernehmen',
 'Aus Ranglisten übernehmen',
 '<p>Ermittelt für das oben gewählte Jahr den Sieger jeder Auszeichnung aus den Ranglisten und trägt ihn fest ein. Zuerst erscheint eine Vorschau; gespeichert wird erst mit <strong>Übernehmen</strong>.</p><ul><li><strong>Endstich, Schwini, Kunst, Glück, Zabigstich</strong> und <strong>Endschiessen Kat. A/B</strong>: das bestplatzierte Mitglied der Endschiessen-Rangliste, also wer dort «WP» erhält. Gäste und Jungschützen stehen in keiner Siegerliste.</li><li><strong>Jahresmeisterschaft, Heimmeisterschaft, Kantonalstich Kat. A/B</strong>: der Sieger nach der Wanderpreis-Regel mit demselben Namen (Seite «Wanderpreis-Regeln»).</li><li><strong>Vorangehakt</strong> sind nur Auszeichnungen, für die im Jahr noch kein Sieger erfasst ist. Weicht ein erfasster Eintrag ab (andere Person oder anderer Wert), steht der bisherige Eintrag darunter; wer ihn ersetzen will, hakt ihn bewusst an.</li><li>Ohne Resultate oder mit mehreren Einträgen für dieselbe Auszeichnung ist nichts wählbar; solche Einträge von Hand bereinigen.</li></ul><p>Die Kategorie A/B richtet sich wie in den Ranglisten nach der <strong>heutigen Waffe</strong> des Mitglieds. Hat jemand seither die Kategorie gewechselt, kann die Vorschau für frühere Jahre vom damaligen Sieger abweichen; dann den Handeintrag stehen lassen.</p>',
 'sieger'),

('sieger.uebersicht',
 'Sieger der letzten Jahre',
 '<p>Hier stehen die <strong>Sieger pro Auszeichnung und Jahr</strong>. Sie fliessen in die Listen «… Sieger seit» des <strong>Absendenbuchs</strong> ein.</p><ul><li>Das Jahr oben ist mit dem Vorjahr vorbelegt. Die Karten sind in zwei Gruppen geordnet: <strong>Jahresmeisterschaft</strong> (Jahresmeisterschaft, Kantonalstich, Heimmeisterschaft) und <strong>Endschiessen</strong> (alle übrigen Auszeichnungen).</li><li><strong>Aus Ranglisten übernehmen</strong> ermittelt die Sieger des gewählten Jahres aus den Ranglisten und trägt sie nach einer Vorschau fest ein.</li><li><strong>Hinzufügen</strong> öffnet das Panel für einen Eintrag von Hand: Mitglied, Auszeichnung, Resultat/Punkte (auch mit Nachkommastellen, z.B. 263.90) und Jahr. Der Name wird beim Speichern aus dem Mitglied übernommen.</li><li>Klick auf eine Zeile oder den Stift öffnet den Eintrag zum Bearbeiten, der Papierkorb löscht ihn.</li><li>Wird in ein anderes Jahr gespeichert, wechselt die Ansicht dorthin, damit der Eintrag sichtbar bleibt.</li></ul>',
 'sieger')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
