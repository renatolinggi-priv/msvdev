-- Migration 095: Hilfetexte zu Migration 094 – Gäste und Jungschützen unter «Endschiessen Resultate erfassen»,
-- Sie und Er als verknüpftes Paar, Prüfliste «Absenden vorbereiten» mit Sie und Er statt Partnerinnen.
-- Die Seite «Endschiessen Partnerinnen erfassen» gibt es nicht mehr; ihre Hilfetexte werden als gelöscht markiert.
--
-- Idempotent: ON DUPLICATE KEY UPDATE, die Migration ist die Quelle der Wahrheit für diese Texte.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES

('endresultate.uebersicht',
 'Endschiessen Resultate erfassen',
 '<p>Hier werden die Resultate am Endschiessen erfasst: von <strong>Mitgliedern</strong>, <strong>Gästen</strong> (Partnerinnen und weitere Gäste) und <strong>Jungschützen</strong>. Die Tabelle zeigt für das gewählte Jahr alle, die unter <a href="endschloesen.php">Endschiessen Stiche lösen</a> Stiche gelöst oder bereits Resultate haben: zuerst die Mitglieder, dann die Gäste (Marke «Gast»), dann die Jungschützen (Marke «JS»).</p><ul><li><strong>Zellen</strong>: eine Zahl ist das erfasste Resultat, «gelöst» (gelb) heisst bezahlt, aber noch kein Resultat, «–» heisst nicht gelöst. Bei Schwini steht «gelöst P1» oder «gelöst P2», wenn nur eine Passe gelöst ist. Bei Sie und Er steht «ohne Partner», solange niemand verknüpft ist.</li><li><strong>Stand</strong>: «offen» (Zeile gelb), solange ein gelöster Stich noch kein Resultat hat oder Sie und Er noch nicht verknüpft ist, sonst «vollständig». Oben steht, wie viele vollständig erfasst sind.</li><li><strong>Filter und Suche</strong>: «Offen» zeigt nur, was noch zu tun ist; die Suche filtert nach Namen (auch «Gast» oder «JS»).</li><li><strong>Zeile anklicken</strong> (oder mit den Pfeiltasten wählen und Enter) öffnet rechts das Erfassungs-Panel. Aktiv sind nur die gelösten Stiche; nicht gelöste sind zusammengeklappt. Gäste schiessen alle Stiche, die sie gelöst haben, Jungschützen ihr Paket.</li><li><strong>Werte</strong>: Endstich und Schwini pro Schuss 0–10, Kunst, Glück und Zabig als Hunderterwertung 0–100. Die Totale rechnen beim Tippen mit.</li><li><strong>Speichern &amp; nächstes offenes</strong> (auch Ctrl+Enter oder Enter im letzten Feld) speichert und öffnet die nächste Person mit offenen Stichen. <strong>Speichern</strong> (Ctrl+S) speichert und schliesst das Panel. Schlägt das Speichern fehl, bleiben die Eingaben stehen.</li><li><strong>Prüfung</strong>: Werte ausserhalb des Bereichs oder keine Zahl werden rot markiert. Beim Speichern erscheint über den Knöpfen eine Liste, jeder Eintrag springt direkt ins Feld; gespeichert wird erst, wenn alle Werte stimmen.</li><li><strong>Nicht gespeichert</strong> steht im Panelkopf, solange Eingaben offen sind. Schliessen (Escape, Kreuz oder Klick daneben) und Blättern fragen dann: Speichern, Verwerfen oder Zurück.</li><li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist. Leerst du alle Felder eines gelösten Stichs (bei Schwini einer Passe) und speicherst, wird er auch in der Datenbank geleert; die Meldung nennt dann «geleert: …».</li></ul><p><strong>Ranglisten</strong>: Gäste stehen in allen Einzelstich-Ranglisten (Endstich, Schwini, Kunst, Glück, Zabig, Differenzler) mit dem Kranz nach der Limite ihrer Waffe, in der Gesamtrangliste nur in Kat. B und nur, wenn sie alle fünf Stiche geschossen haben. Jungschützen stehen in Endstich, Schwini und Zabig sowie in ihrer eigenen Liste im Absendenbuch. Den Wanderpreis («WP») erhält immer das bestplatzierte Mitglied.</p><p>Der Papierkorb im Panel löscht die Resultate einer Person im gewählten Jahr. Vorher zeigt er, was betroffen ist, bei Mitgliedern auch das Endstich-Resultat der Jahresmeisterschaft; bei Sie und Er behält die Partnerin bzw. der Partner die eigenen Schüsse, nur die Verknüpfung fällt weg. Die Datenbank wird vor dem Löschen gesichert. Resultate lassen sich auch aus der Imetron-CSV importieren (<a href="endsch_import.php">Endschiessen importieren</a>), für Gäste und Jungschützen über ihre Nummer 999… aus dem CSV-Export.</p>',
 'endresultate'),

('endresultate.schwini',
 'Schwini',
 '<p>Zwei Passen à sechs Schüsse (0–10). Jede Passe hat ihr eigenes Total.</p><ul><li>Die Passen werden unter «Endschiessen Stiche lösen» <strong>einzeln gelöst</strong>; eine nicht gelöste Passe bleibt hier gesperrt.</li><li>In der Gesamtrangliste zählt die <strong>bessere Passe</strong>, die Bildschirm-Rangliste zeigt sie als «beste (schlechtere)».</li><li>Die Schwini-Rangliste sortiert nach der höheren Passe, dann nach der tieferen, dann nach Alter. Mitglieder, Gäste und Jungschützen stehen in derselben Liste.</li><li>Die Partner-Rangliste zählt bei den Gästen die bessere Passe zum Endstich dazu.</li></ul>',
 'endresultate'),

('endresultate.sieunder',
 'Sie und Er',
 '<p>Der Stich wird zu zweit geschossen, jede Person schiesst <strong>fünf Schüsse</strong> und erfasst sie im eigenen Panel.</p><ul><li><strong>Partnerin / Partner</strong>: zur Auswahl steht, wer im Jahr Sie und Er gelöst hat, Mitglied oder Gast. So lassen sich auch zwei Mitglieder als Paar führen. Die Verknüpfung gilt für beide: die gewählte Person zeigt danach auf diese Person zurück, eine frühere Verknüpfung wird gelöst. Steht hinter einem Namen «mit …», ist die Person schon mit jemand anderem verknüpft.</li><li>Unter der Auswahl stehen die Schüsse der Partnerin bzw. des Partners. Gewertet wird die <strong>Summe der eindeutigen Werte</strong> über alle zehn Schüsse des Paars: jeder Wert zählt nur einmal, dreimal 10 ergibt eine 10. Doppelte werden rot durchgestrichen, die Pille zeigt das Total des Paars.</li><li>Solange niemand verknüpft ist, steht in der Tabelle «ohne Partner» und der Stich gilt als offen; die Prüfliste «Absenden vorbereiten» zählt solche Personen.</li><li>Die Rangliste «Sie &amp; Er» nennt zuerst das Mitglied und sortiert nach dem Total des Paars.</li></ul>',
 'endresultate'),

('endschrang.absenden',
 'Absenden vorbereiten',
 '<p>Die Karte zeigt am Abend vor dem Absenden auf einen Blick, ob alles bereit ist. Die drei Dokumente fürs Absenden stehen zuoberst in der Karte «Dokumente».</p><ul><li><strong>Stiche</strong>: wie viele Teilnehmer (Mitglieder, Gäste, Jungschützen) noch gelöste Stiche ohne Resultat haben, nach derselben Regel wie in der Erfassung; der Link führt direkt dorthin.</li><li><strong>Sie und Er</strong>: wer Sie und Er gelöst hat, aber noch mit niemandem verknüpft ist.</li><li><strong>Wanderpreise</strong>: wie viele der Wanderpreise im Umlauf (angeschafft und nicht schon in einem früheren Jahr definitiv gewonnen) für das gewählte Jahr einen Gewinner haben.</li></ul><p>Rechts oben steht der Stand mit der Uhrzeit der Prüfung: «bereit fürs Absenden» oder wie viele Punkte noch offen sind; der Pfeil daneben prüft neu. Sind noch Punkte offen, steht das auch bei den Dokumenten fürs Absenden; sie lassen sich trotzdem erstellen und zeigen dann den heutigen Stand.</p>',
 'endschrang'),

('endschrang.dokumente',
 'Dokumente',
 '<p>Alle Dokumente der Seite stehen in dieser Karte, jedes in einer eigenen Zeile. Der Knopf mit dem Namen lädt das Dokument herunter (PDF, das Absendenbuch als Word); der Drucker daneben druckt direkt und erscheint nur, wenn die Drucksteuerung verbunden ist.</p><ul><li><strong>Fürs Absenden</strong>: das <strong>Absendenbuch</strong> aus der Word-Vorlage; die <strong>Broschüre</strong> legt dessen A5-Seiten paarweise auf A4 quer, so dass gefaltet ein Heft entsteht; die <strong>Gesamtrangliste</strong> beider Kategorien (Gäste in Kat. B, wenn sie alle fünf Stiche geschossen haben).</li><li><strong>Zwischenrangliste</strong>: die Gesamtrangliste ohne Zabig, für den Stand vor dem letzten Stich.</li><li><strong>Anmeldungen</strong>: Liste der Absenden-Anmeldungen (Mitglieder, Jungschützen, Gäste) sowie die Abrechnung von Schwini-Passen, Differenzler, Sie und Er und Partner-Paketen aus den gelösten Stichen und den Spezialpreisen.</li><li><strong>Einzelwettbewerbe</strong>: Endstich und Schwini (Mitglieder, Gäste und Jungschützen), Kunst, Glück und Differenzler (Mitglieder und Gäste), Zabig (alle). Kranz nach der Limite der Waffe, den Wanderpreis erhält das bestplatzierte Mitglied.</li><li><strong>Partner</strong>: Endstich plus beste Schwini-Passe der Gäste. <strong>Sie &amp; Er</strong>: Summe der eindeutigen Werte aus den Schüssen beider Personen eines Paars.</li></ul>',
 'endschrang')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;

-- Löschsperre in «Stiche lösen»: Gäste mit Resultaten
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'das Standblatt herunterladen oder direkt drucken und die Lösung löschen;',
  'das Standblatt herunterladen oder direkt drucken und die Lösung löschen (bei Gästen und Jungschützen erst, wenn unter «Endschiessen Resultate erfassen» keine Resultate mehr stehen);')
WHERE schluessel = 'endschloesen.uebersicht';

-- Bildschirm-Rangliste: Gäste in Kat. B
UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li>Ohne Endstich-Resultat erscheint ein Mitglied in keiner der beiden Tabellen.</li>',
  '<li>Ohne Endstich-Resultat erscheint ein Mitglied in keiner der beiden Tabellen.</li><li>Gäste (ohne Jungschützen) stehen in Kat. B, sobald sie alle fünf Stiche geschossen haben.</li>')
WHERE schluessel = 'endschrang.kategorien'
  AND inhalt_html NOT LIKE '%Gäste (ohne Jungschützen) stehen in Kat. B%';

-- Die Seite «Endschiessen Partnerinnen erfassen» gibt es nicht mehr
UPDATE hilfetexte SET ist_geloescht = 1 WHERE schluessel LIKE 'endresultate\_partner.%';
