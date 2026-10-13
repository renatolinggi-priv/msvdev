-- Migration 098: Hilfetext «Stand des Absendenbuchs» unter «Fürs Absenden» auf «Endschiessen Rangliste»
-- (1.68.8, Okt 2026): zuletzt erstelltes Buch je Art mit Zeit und Person, Öffnen und Drucken genau dieser Datei,
-- dazu ein Befund mit allen Punkten vor dem Druck.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschrang.buchstand',
 'Stand des Absendenbuchs',
 '<p>Zeigt das zuletzt erstellte Absendenbuch des gewählten Jahres, getrennt nach <strong>Broschüre</strong> und <strong>Word</strong>: wann und von wem es erstellt wurde und bei der Broschüre, wie viele Seiten auf wie vielen Blatt.</p><ul><li><strong>Öffnen</strong> zeigt genau diese Datei, <strong>Drucken</strong> schickt genau diese Broschüre an den Drucker, ohne sie neu zu erstellen. Der Drucker neben dem Knopf «Absendenbuch (Broschüre)» erstellt dagegen zuerst ein neues Buch.</li><li><strong>Vor dem Druck prüfen</strong> sammelt alles, was im Heft fehlt oder leer bleibt: die Hinweise beim Erstellen (z.B. Siegerlisten ohne das Jahr) und die offenen Stiche und Wanderpreise aus der Prüfliste, je mit Link zur Stelle, wo man es behebt.</li><li>Steht bei einem Punkt «Inzwischen erledigt, Buch neu erstellen», ist er behoben, aber die Datei hat noch den alten Stand.</li><li>Während ein Buch erstellt wird, steht hier, was läuft, mit der Zeit. Die Broschüre braucht bis zu einer Minute.</li></ul><p>Den Stand sehen alle im Vorstand, auch nach dem Neuladen der Seite. Ältere Dateien räumt der Server auf; dann steht «Datei nicht mehr vorhanden», und das Buch muss neu erstellt werden.</p>',
 'endschrang')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
