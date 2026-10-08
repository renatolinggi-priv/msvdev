-- Migration 075: Hilfetexte «Endschiessen – Stiche lösen» nach dem Umbau (Okt 2026):
-- Jahr, Abrechnung und Definition stehen in der Kopf-Card, Übersicht mit Suche, gewählte Stiche
-- in der Akzentfarbe; Gast-Preisregeln auf den Stand der Preislogik (preislogik.inc.php) gebracht.
-- Ersetzt die Texte aus 067 für diese Schlüssel.
--
-- Idempotent: ON DUPLICATE KEY UPDATE.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
('endschloesen.uebersicht',
 'Endschiessen: Stiche lösen',
 '<p>Hier wird erfasst, wer am Endschiessen welche Stiche löst und wie bezahlt wird. Oben stehen das Jahr, die Zahl der Teilnehmer mit der Summe des Jahres und die Knöpfe <strong>Abrechnung</strong> (PDF), Direktdruck und <strong>Definition</strong>. Darunter das Formular und die Übersicht aller Lösungen des gewählten Jahres (auf breiten Bildschirmen nebeneinander).</p><ul><li><strong>Ablauf</strong>: Teilnehmertyp wählen, Person bestimmen, Waffe prüfen, Stiche anklicken, Zahlungsart setzen, speichern. Der Preis wird vom Server nach den hinterlegten Regeln berechnet, die Anzeige im Formular ist eine Vorschau.</li><li><strong>Waffe</strong> gilt für alle Stiche einer Person im Jahr. Bei Mitgliedern wird sie aus den Stammdaten vorbelegt, Gäste und Jungschützen starten mit Stgw 90. Die Stammdaten werden dabei nicht verändert.</li><li><strong>Übersicht</strong>: Die Suche filtert nach Namen. Über das Menü «…» einer Zeile lässt sich die Lösung bearbeiten, das Standblatt herunterladen oder direkt drucken und die Lösung löschen; die bearbeitete Zeile ist blau umrandet.</li><li>Das Jahr gilt auch auf den anderen Seiten (für einige Stunden). Ist nicht das laufende Jahr gewählt, steht daneben «Archiv JAHR» mit einem Knopf zurück.</li></ul><p>Stich-Definitionen und Spezialpreise pflegen Admin und Vorstand über den Knopf <strong>«Definition»</strong> oben.</p>',
 'endschloesen'),

('endschloesen.teilnehmer',
 'Teilnehmertyp: Mitglied, Gast, Jungschütze',
 '<p>Der Umschalter bestimmt Preisregel und Speicherort:</p><ul><li><strong>Mitglied</strong>: aus der Mitgliederliste wählen; wer im Jahr schon gelöst hat, erscheint dort nicht mehr (bearbeiten über die Übersicht). Jeder Stich kostet seinen Einzelpreis, der Probestich ist gratis. Beim Zabig kann «Partner» angekreuzt werden, dann gilt der Partnerpreis.</li><li><strong>Gast</strong>: Name eingeben; ein bereits erfasster Gast wird erkannt und geladen. Endstich und Schwini (Passe 1 und 2) kosten zusammen: ein Stich den Einzelpreis, zwei den Kombi-Preis 2, drei den Kombi-Preis 3. «Sie und Er» kommt zum Gastpreis dazu, weitere Stiche (gestrichelte Kacheln) lassen sich zum Einzelpreis dazulösen; der Probestich ist für Gäste gesperrt. Ist eine Pauschale für «alle Stiche» gesetzt, gilt sie, sobald jeder Stich gewählt ist.</li><li><strong>Jungschütze/-in</strong>: Name und Geburtsdatum. Jungschützen lösen ein festes Paket (Endstich, Schwini Passe 1, Zabig, Probe) zum Jungschützen-Paketpreis.</li></ul><p>Das Jahr oben neben dem Seitentitel steuert, für welches Endschiessen erfasst wird.</p>',
 'endschloesen')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
