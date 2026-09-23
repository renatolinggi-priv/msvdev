-- Migration 068: Hilfetexte – Rollout auf alle übrigen Admin-Seiten (44 Seiten, 139 Schlüssel).
-- Ergänzt Migration 067 (Pilot). Schlüssel = data-help-Attribut im Markup der jeweiligen Seite,
-- Konvention seite.thema, Kategorie = Seitenname. Gruppen: A Jahresmeisterschaft, B Endschiessen,
-- C Heim/Kanti/Sektion/Cup-Rangliste, D Verwaltung, E Ausdrucke/Wanderpreise, F System.
--
-- Idempotent: ON DUPLICATE KEY UPDATE; diese Datei ist die Quelle der Wahrheit für die Texte.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES
-- ---------------------------------------------------------------- Gruppe A
('jmresultate.uebersicht',
 'Jahresmeisterschaft erfassen',
 '<p>Hier werden die Resultate der Jahresmeisterschaft erfasst. Jede Karte steht für einen Anlass des gewählten Jahres und zeigt, wie viele der aktiven Mitglieder bereits ein Resultat haben (grüner Rahmen = vollständig). Ein Klick auf die Karte öffnet rechts das Erfassungs-Panel.</p><ul><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> haben keine Karte: ihre Werte kommen automatisch aus dem Endschiessen bzw. dem Kantonalstich. Info-Einträge und Anlässe des erweiterten Programms erscheinen ebenfalls nicht.</li><li><strong>PDF importieren</strong> liest eine fremde Einzelrangliste (z.B. Vereinsstich) oder die FSA-Teilnehmerliste für Obligatorisch und Feldschiessen ein. Vereinsmitglieder werden automatisch erkannt, die Vorschau lässt sich korrigieren; bereits erfasste Zeilen sind gelb markiert und abgewählt. Die Ränge 1 bis 10 werden zusätzlich als Einzelrangierung gespeichert, eine erkannte Vereinszeile als Sektionsrangierung.</li><li><strong>Veröffentlichen</strong> erscheint, sobald gespeicherte Änderungen noch nicht für das Portal freigegeben sind (Zähler = offene Einträge), und macht sie dort sichtbar.</li><li><strong>Alle Resultate löschen</strong> entfernt nach Rückfrage sämtliche erfassten JM-Resultate des Jahres; Endschiessen und Kantonalstich bleiben unberührt.</li><li><strong>Rangliste</strong> wechselt zur Ranglisten-Seite; unten auf dieser Seite steht dieselbe Rangliste Kat. A und Kat. B als Kontrolle.</li></ul><p>Mitglieder können ihre Resultate des laufenden Jahres im Portal selbst melden; solche Einträge sind im Panel gelb als «gemeldet» markiert, bis der Vorstand sie speichert.</p>',
 'jmresultate'),

('jmresultate.erfassung',
 'Erfassungs-Panel eines Anlasses',
 '<p>Im Kopf stehen Anlass, Maximalpunkte und, falls gesetzt, das Kennzeichen «Streicher». Darunter alle aktiven Mitglieder, alphabetisch in zwei Gruppen: <strong>Mit JM-Resultat</strong> (hat in diesem Jahr irgendwo ein gewertetes Resultat; Obligatorisch, Feldschiessen, Sektionsmeisterschaft und Cup zählen dafür nicht) und <strong>Noch ohne JM-Resultat</strong>. Grün hinterlegte Zeilen haben in <em>diesem</em> Anlass ein Resultat.</p><ul><li>Punkte als ganze Zahl eingeben; <strong>Enter</strong> springt zum nächsten Feld, das Suchfeld filtert die Liste. Ein geleertes Feld löscht das Resultat beim Speichern.</li><li><strong>Sektionsmeisterschaft</strong> (grüner Kopf) hat zwei Felder R1 und R2; in der Rangliste zählt nur der höhere Wert.</li><li><strong>Endstich</strong> und <strong>Bester Kantonalstich</strong> sind reine Anzeige: Summe der zehn Schüsse bzw. beste Passe. Hier gibt es keinen Speichern-Knopf.</li><li>Gelb umrandete Felder mit «gemeldet» stammen von der Selbsteingabe im Portal. Mit dem Speichern des Anlasses gelten die gespeicherten Einträge als vom Vorstand bestätigt.</li></ul><p>Fortschritt und Zähler unten zeigen erfasst/total. Escape, Abbrechen oder ein Klick neben das Panel schliessen es; bei ungespeicherten Änderungen wird nachgefragt.</p>',
 'jmresultate'),

('jmresultate.rangliste',
 'Kontroll-Rangliste Kat. A und Kat. B',
 '<p>Dieselbe Darstellung wie auf der Seite <a href="jmrang.php">Jahresmeisterschaft Ranglisten</a>, direkt unter der Erfassung als Kontrolle.</p><ul><li>Die Kategorie richtet sich nach der Waffe des Mitglieds. Als Spalten stehen die bis zu sechs zuletzt durchgeführten Anlässe (neueste rechts); Obligatorisch und Feldschiessen erscheinen nur in der Aufschlüsselung.</li><li>«–» heisst: Anlass durchgeführt, Resultat noch nicht erfasst. Rot durchgestrichene Werte sind Streicher.</li><li><strong>Total</strong> und <strong>Rang</strong> werden erst nach dem Endstich berechnet, vorher steht «offen».</li></ul><p>Details zu Streichern, Hochrechnung und Aufschlüsselung stehen in der Hilfe der Ranglisten-Seite.</p>',
 'jmresultate'),

('jmrang.uebersicht',
 'Jahresmeisterschaft Ranglisten',
 '<p>Zeigt die Bildschirm-Rangliste der Jahresmeisterschaft für das gewählte Jahr, getrennt nach <strong>Kat. A</strong> und <strong>Kat. B</strong> (Kategorie der Waffe des Mitglieds). Auswählbar sind das laufende Jahr und die drei Vorjahre.</p><ul><li>Gezählt werden alle Anlässe des Jahresprogramms ohne Info-Einträge und ohne erweitertes Programm; die Anzahl Streicher stammt aus der <a href="jmdefinition.php">JM-Definition</a>.</li><li>Resultate von Anlässen mit weniger als 100 Maximalpunkten werden auf 100 hochgerechnet (Ausnahmen: Einzelwettschiessen, Obligatorisch, Feldschiessen). Endstich und Bester Kantonalstich kommen automatisch aus dem Endschiessen bzw. Kantonalstich.</li><li><strong>Total</strong> und <strong>Rang</strong> stehen erst fest, wenn der Endstich durchgeführt ist; bis dahin zeigt die Tabelle «offen». Punktgleiche erhalten denselben Rang.</li><li><strong>Resultate bearbeiten</strong> wechselt zur Erfassung, <strong>Dokumente erstellen</strong> erzeugt die PDF-Ranglisten.</li></ul>',
 'jmrang'),

('jmrang.dokumente',
 'PDF-Ranglisten und Direktdruck',
 '<ul><li><strong>Rangliste (nach Rang)</strong>: die offizielle Rangliste, je eine Seite pro Kategorie, sortiert nach Total. Streicher sind rot durchgestrichen, die Legende nennt die Anzahl Streicher; hochgerechnete Werte und Totale haben zwei Nachkommastellen, rohe Resultate keine.</li><li><strong>Rangliste (nach Name)</strong>: alphabetische Liste, in der <em>alle</em> Resultate summiert werden, ohne Streicher-Logik. Dient als Übersicht und zur Kontrolle.</li><li>Der <strong>Drucker-Knopf</strong> neben jedem PDF schickt es direkt an den Drucker aus dem Druckprofil «JM Rangliste» (Seite Drucksteuerung, dort auch Hoch- oder Querformat). Ohne verbundenes QZ Tray bleibt er inaktiv, der Grund steht im Tooltip.</li><li>Nach dem Erzeugen erscheint unter den Knöpfen der Link zur Datei.</li></ul><p>Hinweis: Das PDF nimmt als Streicher-Basis alle Anlässe mit mindestens einem erfassten Resultat, die Bildschirm-Rangliste nur nach Datum durchgeführte. Während der Saison können die Totale deshalb abweichen; nach dem Endstich stimmen beide überein.</p>',
 'jmrang'),

('jmrang.rangliste',
 'Aufbau der Rangliste',
 '<p>Als Spalten stehen die bis zu <strong>sechs zuletzt durchgeführten</strong> Anlässe, chronologisch mit dem neuesten rechts. Obligatorisch und Feldschiessen (20-Punkte-Skala) erscheinen nicht als Spalte, zählen aber im Total. «–» bedeutet: Anlass hat stattgefunden, Resultat noch nicht erfasst.</p><ul><li>Der Pfeil am Zeilenende öffnet die <strong>Aufschlüsselung</strong>: <em>Pflicht</em> (zählt immer) und <em>Auswärtige Schiessen</em> (Streicher-Pool) mit Zwischentotalen, gestrichenen Werten und der Anzahl gewertet / gestrichen / verpasst / offen.</li><li><strong>Streicher</strong>: von allen bereits durchgeführten Streicher-Anlässen zählen pro Schütze die besten, die schlechtesten «Anzahl Streicher» fallen weg. Ein verpasster Anlass zählt als 0 und wird so in der Regel gestrichen; noch offene Anlässe zählen weder als Resultat noch als Streicher.</li><li>Von der Sektionsmeisterschaft zählt nur der bessere der beiden Durchgänge.</li><li>Unten steht die Gruppe <strong>Ohne gewertetes JM-Resultat</strong>: Mitglieder, die in diesem Jahr noch kein Resultat ausser Obligatorisch, Feldschiessen, Sektionsmeisterschaft oder Cup haben, alphabetisch und ohne Rang.</li></ul><p>Rang und Total erscheinen erst nach dem Endstich; die Suche über den Karten filtert auf Mobilgeräten die Liste.</p>',
 'jmrang'),

('jmdurchschnitt.uebersicht',
 'Sektionsabrechnungen',
 '<p>Berechnet für einen auswärtigen Anlass das <strong>Vereinsresultat</strong> für die Sektions- bzw. Vereinsabrechnung: Durchschnitt der besten Einzelresultate plus Beteiligungszuschlag für die übrigen Teilnehmer.</p><ul><li><strong>Ablauf</strong>: Jahr wählen, Anzahl zählender Resultate prüfen, Schiessanlass auswählen. Tabelle und Zusammenfassung erscheinen sofort, danach <strong>PDF exportieren</strong> oder direkt drucken (Druckprofil «JM Durchschnitte», Hoch- oder Querformat aus der Drucksteuerung).</li><li>Zur Auswahl stehen nur Anlässe des Jahres, für die bereits Resultate erfasst sind; Info-Einträge, erweitertes Programm und versteckte Anlässe fehlen. In Klammern stehen Maximalpunkte und Beteiligungszuschlag aus der <a href="jmdefinition.php">JM-Definition</a>.</li><li>Gerechnet wird mit den erfassten Punkten aktiver Mitglieder, ohne Hochrechnung auf 100.</li></ul>',
 'jmdurchschnitt'),

('jmdurchschnitt.zaehlende',
 'Anzahl zählende Resultate',
 '<p>Legt fest, wie viele der besten Resultate in den Durchschnitt einfliessen (Standard 6, erlaubt 1 bis 99). Der Wert gilt <strong>pro Jahr</strong> und muss mit «Speichern» bestätigt werden.</p><ul><li>Ist für das Jahr kein Wert gespeichert, gilt der Wert des letzten Vorjahres (Hinweis neben dem Feld), sonst 6.</li><li><strong>Hälfte-Regel</strong>: bei vielen Teilnehmern zählt die Hälfte der Teilnehmer (abgerundet), sobald diese grösser ist als die eingestellte Anzahl. Bei 14 Teilnehmern und Einstellung 6 zählen also 7.</li><li>Sind weniger Teilnehmer vorhanden als eingestellt, zählen alle.</li></ul><p>Die Einstellung wirkt auf Anzeige und PDF aller Anlässe des Jahres.</p>',
 'jmdurchschnitt'),

('jmdurchschnitt.berechnung',
 'Berechnung des Vereinsresultats',
 '<p>Nach der Wahl des Anlasses werden alle Resultate grösser als 0 der aktiven Mitglieder absteigend sortiert. Die zählenden Resultate («Pflichtteilnehmer») sind in der Spalte <strong>Verwendet</strong> mit Häkchen und grün markiert, die übrigen sind Nicht-Pflichtteilnehmer.</p><ul><li><strong>Durchschnitt</strong> = Summe der zählenden Resultate ÷ Anzahl zählende.</li><li><strong>Endergebnis</strong> = (Summe zählende + Beteiligungszuschlag in % × Summe nicht zählende ÷ 100) ÷ Anzahl zählende, auf drei Nachkommastellen gerundet.</li><li>Der <strong>Beteiligungszuschlag</strong> ist der Zuschlag des Anlasses aus der JM-Definition; ohne Zuschlag entspricht das Endergebnis dem Durchschnitt.</li></ul><p>Das PDF «Vereinsabrechnung» enthält Pflicht- und Nicht-Pflichtteilnehmer, die Formel mit den eingesetzten Zahlen und das Endergebnis.</p>',
 'jmdurchschnitt'),

('jmstandblatt.uebersicht',
 'JM Standblatt',
 '<p>Erzeugt für die aktiven Mitglieder das Standblatt der Jahresmeisterschaft aus der Word-Vorlage (A4 quer) mit Jahr, Name und der Lizenznummer als Barcode.</p><ul><li><strong>Jahr</strong> wählen (nächstes Jahr bis drei Jahre zurück); das Suchfeld filtert die Liste.</li><li><strong>Alle (DOCX)</strong> lädt nacheinander je eine Word-Datei pro Mitglied herunter.</li><li><strong>Alle (PDF)</strong> erstellt ein Sammel-PDF; jede Datei wird dafür über den PDF-Dienst umgewandelt, was einige Zeit dauert. Mitglieder, für die kein Standblatt erzeugt werden konnte, werden gemeldet.</li><li><strong>Alle drucken</strong> schickt dasselbe Sammel-PDF als einen Druckauftrag an den Drucker aus dem Druckprofil «JM Standblatt»; der Badge «Direktdruck» zeigt, ob QZ Tray verbunden und ein Profil hinterlegt ist.</li></ul>',
 'jmstandblatt'),

('jmstandblatt.ausgabe',
 'Standblatt pro Mitglied',
 '<p>Pro Zeile lässt sich das Standblatt als <strong>Word</strong> herunterladen oder per <strong>Direktdruck</strong> drucken. Der Druck-Knopf ist nur aktiv, wenn QZ Tray läuft und in der Drucksteuerung ein Profil «JM Standblatt» besteht; der Grund steht im Tooltip.</p><ul><li><strong>Lizenz</strong> ist die Mitgliedernummer. Der Barcode folgt dem SSV-Format: sechsstellige Nummern erhalten den Präfix 10, dazu zwei Prüfziffern. Passt die Nummer nicht in dieses Schema, bleibt das Barcode-Feld leer.</li><li>Name und Vorname kommen aus den Stammdaten; Änderungen dort wirken beim nächsten Erzeugen.</li></ul>',
 'jmstandblatt'),

('jmdefinition_gruppen.uebersicht',
 'Gruppenschiessen',
 '<p>Hier werden die Gruppen für Anlässe mit der Option <strong>Gruppenwettkampf</strong> zusammengestellt. Welche Anlässe zur Auswahl stehen, wird in der <a href="jmdefinition.php">JM-Definition</a> festgelegt (Knopf «Anlässe verwalten»).</p><ul><li>Zuerst <strong>Jahr</strong> und <strong>Anlass</strong> wählen; links erscheinen die bestehenden Gruppen, rechts das Formular für eine neue oder die gewählte Gruppe.</li><li>Ein Mitglied kann pro Anlass und Jahr nur in <strong>einer</strong> Gruppe stehen; die Liste der verfügbaren Mitglieder zeigt darum nur noch nicht zugeteilte aktive Mitglieder.</li><li>Die Gruppeneinteilung wird im Monatsblatt-Export ausgegeben.</li></ul>',
 'jmdefinition_gruppen'),

('jmdefinition_gruppen.gruppen',
 'Bestehende Gruppen',
 '<p>Liste der Gruppen des gewählten Anlasses und Jahres mit Gruppenname und Mitgliedern; der Zähler nennt die Anzahl Gruppen.</p><ul><li><strong>Bearbeiten</strong> (Stift) lädt die Gruppe ins Formular rechts; dort erscheint der gelbe Hinweis «Bearbeiten», und Speichern ersetzt die Gruppe vollständig durch die neue Zusammensetzung.</li><li><strong>Löschen</strong> (Papierkorb) entfernt die Gruppe nach Rückfrage; ihre Mitglieder sind danach wieder verfügbar.</li></ul>',
 'jmdefinition_gruppen'),

('jmdefinition_gruppen.gruppe',
 'Gruppe erstellen oder bearbeiten',
 '<p><strong>Gruppenname</strong> ist Pflicht, ebenso mindestens ein Mitglied.</p><ul><li>Mitglieder per Klick oder Ziehen von <strong>Verfügbar</strong> nach <strong>Gruppe</strong> verschieben (auf dem Handy per Tippen in den beiden Listen); das Suchfeld filtert die verfügbaren Mitglieder.</li><li>Verfügbar sind nur aktive Mitglieder, die für diesen Anlass und dieses Jahr noch keiner Gruppe zugeteilt sind. Ist ein Mitglied inzwischen anderswo eingeteilt, meldet das Speichern den Konflikt mit dem Namen der anderen Gruppe.</li><li><strong>Zurücksetzen</strong> leert das Formular bzw. verlässt den Bearbeitungsmodus ohne zu speichern.</li></ul>',
 'jmdefinition_gruppen'),

('einzelrangierung.uebersicht',
 'Einzelrangierungen',
 '<p>Erfasst, welche Mitglieder an auswärtigen Anlässen einen <strong>Rang</strong> mit Resultat und Preisgeld erreicht haben. Die Liste zeigt alle Einträge des gewählten Jahres, sortiert nach Reihenfolge der Anlässe im Jahresprogramm und Rang; die Ränge 1 bis 3 sind farbig hervorgehoben.</p><ul><li><strong>Hinzufügen</strong> öffnet das Formular für einen neuen Eintrag.</li><li>Ein Klick auf eine Zeile öffnet rechts das Bearbeiten von Rang, Resultat und Preis; Anlass und Mitglied bleiben fest. Der Papierkorb löscht den Eintrag nach Rückfrage.</li><li><strong>PDF</strong> erstellt die Liste «Einzelrangierungen» des Jahres mit einer Rangverteilung (wie oft welcher Rang erreicht wurde); der Drucker-Knopf druckt sie über das gleichnamige Druckprofil.</li></ul><p>Beim PDF-Import in der Erfassung Jahresmeisterschaft werden die Ränge 1 bis 10 automatisch hier eingetragen.</p>',
 'einzelrangierung'),

('einzelrangierung.erfassen',
 'Neue Einzelrangierung',
 '<ul><li><strong>Anlass</strong>: zur Auswahl stehen die Anlässe des Jahres mit der Option «Streicher» (auswärtige Schiessen), ohne Info-Einträge.</li><li><strong>Mitglied</strong>: alle aktiven Mitglieder. Pro Mitglied und Anlass ist nur ein Eintrag möglich.</li><li><strong>Rang</strong>: 1 bis 999, Pflichtfeld.</li><li><strong>Resultat</strong>: optionaler Text, z.B. «95.5»; er erscheint in Liste und PDF.</li><li><strong>Preis (CHF)</strong>: Pflichtfeld, 0 oder mehr, in Schritten von 5 Rappen.</li></ul>',
 'einzelrangierung'),

('sektionsrangierungen.uebersicht',
 'Sektionsrangierungen',
 '<p>Erfasst Rang und Preisgeld des <strong>Vereins</strong> (Sektion) an auswärtigen Anlässen. Pro Anlass und Jahr gibt es genau einen Eintrag; die Liste ist nach der Reihenfolge der Anlässe im Jahresprogramm sortiert, die Ränge 1 bis 3 sind farbig hervorgehoben, die Total-Zeile summiert die Preise.</p><ul><li><strong>Hinzufügen</strong> öffnet das Formular; der Knopf ist inaktiv, wenn für alle Anlässe des Jahres bereits eine Rangierung besteht.</li><li>Ein Klick auf eine Zeile öffnet rechts das Bearbeiten von Rang und Preis; der Papierkorb löscht den Eintrag nach Rückfrage.</li><li><strong>PDF</strong> erstellt die Liste «Sektionsrangierungen» des Jahres mit Rangverteilung; der Drucker-Knopf druckt sie über das gleichnamige Druckprofil.</li></ul><p>Beim PDF-Import in der Erfassung Jahresmeisterschaft wird eine erkannte Vereinszeile der Vereinsrangliste automatisch hier eingetragen.</p>',
 'sektionsrangierungen'),

('sektionsrangierungen.erfassen',
 'Neue Sektionsrangierung',
 '<ul><li><strong>Anlass</strong>: zur Auswahl stehen die Anlässe des Jahres ohne Info-Einträge, erweitertes Programm und versteckte Anlässe, die noch keine Rangierung haben. Nach dem Speichern verschwindet der Anlass aus der Liste.</li><li><strong>Rang</strong>: 1 bis 999, Pflichtfeld.</li><li><strong>Preis (CHF)</strong>: Pflichtfeld, 0 oder mehr, in Schritten von 5 Rappen.</li></ul><p>Ein zweiter Eintrag für denselben Anlass wird abgelehnt; Rang und Preis eines bestehenden Eintrags lassen sich über die Liste ändern.</p>',
 'sektionsrangierungen'),

-- ---------------------------------------------------------------- Gruppe B
('endresultate.uebersicht',
 'Endschiessen: Resultate erfassen',
 '<p>Hier werden die Resultate der <strong>Mitglieder</strong> am Endschiessen erfasst. Die Tabelle zeigt für das gewählte Jahr alle Mitglieder, die Stiche gelöst oder bereits Resultate haben, mit dem Total je Stich.</p><ul><li><strong>Zeile anklicken</strong> öffnet rechts das Erfassungs-Panel. Aktiv sind nur die Stiche, die das Mitglied unter «Endschiessen lösen» gelöst hat; nicht gelöste Stiche sind zusammengeklappt und mit «Nicht gelöst» markiert. Das Feld Absenden ist immer offen.</li><li>In der Tabelle steht <strong>«gelöst»</strong>, wenn ein Stich gelöst, aber noch kein Resultat erfasst ist; «–» heisst nicht gelöst.</li><li><strong>Werte</strong>: Endstich und Schwini pro Schuss 0–10, Kunst, Glück und Zabig als Hunderterwertung 0–100. Die Totale rechnen beim Tippen mit.</li><li><strong>Speichern &amp; Nächster</strong> springt zum nächsten Mitglied ohne Resultate; der Fortschrittsbalken zählt Mitglieder mit Resultaten.</li><li>Gespeichert wird ein Stich nur, wenn mindestens ein Wert grösser 0 ist; leere Stiche erzeugen keinen Eintrag.</li></ul><p><strong>Alle Resultate löschen</strong> entfernt sämtliche Endschiessen-Resultate des Jahres (Endstich, Schwini, Kunst, Glück, Zabig) und den zugehörigen Endstich-Eintrag der Jahresmeisterschaft; der Papierkorb im Panel tut dasselbe für ein einzelnes Mitglied. Alternativ lassen sich Resultate aus der Imetron-CSV importieren (<a href="endsch_import.php">CSV-Import Endschiessen</a>); die Schüsse der Partnerinnen werden unter <a href="endresultate_partner.php">Partnerinnen erfassen</a> erfasst.</p>',
 'endresultate'),

('endresultate.endstich',
 'Endstich',
 '<p>Zehn Schüsse mit Wertung 0–10, das Total wird automatisch gebildet.</p><ul><li><strong>Tiefschuss</strong> ist die beste Hunderterwertung des Stichs (0–100) und dient als erster Stichentscheid.</li><li>Die Endstich-Rangliste sortiert nach Total, dann Tiefschuss, dann Anzahl Zehner, dann Alter (ältere vor jüngeren). Ein <strong>Königskranz (KK)</strong> steht bei Rang 1 sowie bei jedem, dessen Total die Kranzlimite seiner Waffe erreicht.</li><li>Das Endstich-Total zählt auch in der Gesamtrangliste und als Endstich-Resultat der Jahresmeisterschaft. Wer kein Endstich-Resultat hat, erscheint in den Kategorien-Ranglisten nicht.</li></ul>',
 'endresultate'),

('endresultate.schwini',
 'Schwini',
 '<p>Zwei Passen à sechs Schüsse (0–10). Jede Passe hat ihr eigenes Total.</p><ul><li>Die Passen werden unter «Endschiessen lösen» <strong>einzeln gelöst</strong>; eine nicht gelöste Passe bleibt hier gesperrt.</li><li>In der Gesamtrangliste zählt die <strong>bessere Passe</strong>, die Bildschirm-Rangliste zeigt sie als «beste (schlechtere)».</li><li>Die Schwini-Rangliste sortiert nach der höheren Passe, dann nach der tieferen, dann nach Alter. Partnerinnen und Jungschützen stehen in derselben Liste.</li></ul><p>Die Schwini-Schüsse der Partnerinnen werden unter <a href="endresultate_partner.php">Partnerinnen erfassen</a> erfasst.</p>',
 'endresultate'),

('endresultate.sieunder',
 'Sie und Er (Anteil Mitglied)',
 '<p>Der Stich wird zu zweit geschossen: die Partnerin schiesst die Schüsse <strong>1–5</strong> (Erfassung unter <a href="endresultate_partner.php">Partnerinnen erfassen</a>), das Mitglied die Schüsse <strong>6–10</strong>, die hier eingetragen werden.</p><ul><li>Gewertet wird die <strong>Summe der eindeutigen Werte</strong> über alle zehn Schüsse: jeder Wert zählt nur einmal, dreimal 10 ergibt eine 10. Doppelte werden rot durchgestrichen, die Pille zeigt die eindeutige Summe der sichtbaren Schüsse.</li><li>Gespeichert wird nur, wenn mindestens ein Wert grösser 0 ist. Existiert noch kein Partner-Eintrag, wird einer mit dem Platzhalternamen «Partner» angelegt; den Namen unter <a href="endresultate_partner.php">Partnerinnen erfassen</a> ergänzen.</li><li>Die Rangliste «Sie &amp; Er» sortiert nach dieser Spezialsumme.</li></ul>',
 'endresultate'),

('endresultate.ansage',
 'Ansage (Differenzler) und Absenden',
 '<p><strong>Ansage</strong> ist das vor dem Zabig-Stich angesagte Total in Hunderterwertung (0–999). Das Feld ist offen, wenn der Differenzler gelöst wurde.</p><ul><li>Die Differenz ist Ansage minus tatsächliche Zabig-Summe (Rohwerte). Die Differenzler-Rangliste sortiert nach der kleinsten Abweichung, bei Gleichstand nach Alter.</li><li>In der Bildschirm-Rangliste erscheint die Differenz als eigene Spalte; ins Gesamttotal fliesst sie nicht ein.</li></ul><p><strong>Absenden</strong>: Anzahl der Personen, die das Mitglied fürs Absenden anmeldet (als Zahl eintragen). Das Feld ist unabhängig von den gelösten Stichen immer offen und wird zusammen mit dem Endstich gespeichert. Die Liste «Anmeldung» unter <a href="endschrang.php">Endschiessen Ranglisten</a> fasst alle Anmeldungen zusammen.</p>',
 'endresultate'),

('endresultate_partner.uebersicht',
 'Endschiessen: Partnerinnen erfassen',
 '<p>Hier werden die Resultate der <strong>Partnerinnen</strong> erfasst, die ein Mitglied ans Endschiessen mitbringt. Pro Mitglied und Jahr gibt es einen Eintrag mit Endstich, Sie-und-Er-Anteil und Partner Schwini.</p><ul><li><strong>Partnerin hinzufügen</strong> öffnet ein leeres Panel: Mitglied wählen und Namen eingeben (beides Pflicht), Schüsse eintragen, speichern. Eine Zeile anklicken öffnet den bestehenden Eintrag.</li><li>Werte 0–10, Zehntel sind erlaubt; Werte ausserhalb werden als 0 gespeichert.</li><li>In der Spalte «Sie und Er» stehen die Punkte der Partnerin rot und die des Mitglieds blau; die Schüsse des Mitglieds werden unter <a href="endresultate.php">Endschiessen Resultate erfassen</a> erfasst.</li><li><strong>Speichern &amp; Nächste</strong> geht zur nächsten Partnerin ohne Resultate.</li></ul><p><strong>Löschen</strong> entfernt den ganzen Eintrag, also auch die Sie-und-Er-Schüsse 6–10 des Mitglieds; «Alle Resultate löschen» tut das für alle Partnerinnen des Jahres. Ein Eintrag mit dem Namen «Partner» wurde vom Sie-und-Er-Erfassen oder vom CSV-Import als Platzhalter angelegt und wartet auf den richtigen Namen. Ranglisten: «Partner» und «Sie &amp; Er» unter <a href="endschrang.php">Endschiessen Ranglisten</a>.</p>',
 'endresultate_partner'),

('endresultate_partner.endstich',
 'Endstich der Partnerin',
 '<p>Zehn Schüsse mit Wertung 0–10 (Zehntel erlaubt), das Total wird automatisch gebildet.</p><ul><li>Die Partnerin erscheint in der <strong>Endstich-Rangliste</strong> zusammen mit Mitgliedern und Jungschützen, ohne Kranzwertung; bei Gleichstand wird sie hinten eingereiht.</li><li>In der <strong>Partner-Rangliste</strong> ergibt Endstich plus beste Partner-Schwini-Passe das Total; sortiert wird nach Total, dann Endstich, dann der anderen Schwini-Passe.</li></ul>',
 'endresultate_partner'),

('endresultate_partner.sieunder',
 'Sie und Er (Anteil Partnerin)',
 '<p>Die Partnerin schiesst die Schüsse <strong>1–5</strong>, das Mitglied die Schüsse <strong>6–10</strong> (Erfassung unter <a href="endresultate.php">Endschiessen Resultate erfassen</a>).</p><ul><li>Gewertet wird die Summe der <strong>eindeutigen Werte</strong> über alle zehn Schüsse: jeder Wert zählt nur einmal, dreimal 10 ergibt eine 10. Doppelte innerhalb der sichtbaren Schüsse sind rot durchgestrichen.</li><li>Die Pille zeigt die eindeutige Summe der hier sichtbaren fünf Schüsse; das Total über alle zehn steht in der Rangliste «Sie &amp; Er» unter <a href="endschrang.php">Endschiessen Ranglisten</a>.</li></ul>',
 'endresultate_partner'),

('endresultate_partner.schwini',
 'Partner Schwini',
 '<p>Zwei Passen à sechs Schüsse (0–10, Zehntel erlaubt) mit je einem Passen-Total.</p><ul><li>In der <strong>Schwini-Rangliste</strong> stehen Partnerinnen zusammen mit Mitgliedern und Jungschützen; sortiert wird nach der höheren Passe, dann nach der tieferen. Partnerinnen werden bei Gleichstand hinten eingereiht.</li><li>In der <strong>Partner-Rangliste</strong> zählt die bessere Passe zum Endstich dazu.</li><li>Die Abrechnung der Schwini-Passen (Liste «Anmeldung») richtet sich nach den gelösten Stichen, nicht nach den hier erfassten Schüssen.</li></ul>',
 'endresultate_partner'),

('endsch_import.uebersicht',
 'Endschiessen: CSV-Import',
 '<p>Liest die Resultat-Datei der Imetron-Anlage (Semikolon-getrennt) eines Schützen ein und schreibt die Schüsse direkt in die Endschiessen-Resultate.</p><ul><li><strong>Ablauf</strong>: Datei ablegen, Mitglied und Jahr prüfen, gefundene Programme kontrollieren, Import bestätigen. Steht im Dateinamen eine sechsstellige Lizenznummer, wird das Mitglied vorgeschlagen.</li><li><strong>Erkennung</strong> über die Programmnummern aus <a href="internestichedef.php">Imetron-Stichnummern</a> (Endstich, Schwini, Kunst, Glück, Zabig, Sie und Er). Unbekannte Programmnummern werden übersprungen, nur Schüsse mit Wertung grösser 0 zählen.</li><li><strong>Wertungen</strong>: Endstich übernimmt die Zehnerwertung pro Schuss, der Tiefschuss ist die höchste Hunderterwertung. Kunst, Glück und Zabig werden als Hunderterwertung gespeichert. Schwini: ein Programm liefert sechs Schüsse; der erste Import füllt Passe 1, ein weiterer Passe 2. Sie und Er landet in den Schüssen 6–10 des Mitglieds, ein fehlender Partner-Eintrag wird mit dem Namen «Partner» angelegt.</li><li>Vor dem Import fragt das Fenster die <strong>Zabig-Ansage</strong> und die <strong>Anzahl Absenden-Anmeldungen</strong> (0, 1 oder 2) ab.</li></ul><p>Bestehende Resultate des Mitglieds werden im Bestätigungsfenster alt gegen neu angezeigt und beim Import <strong>überschrieben</strong>. Ob ein Stich gelöst wurde, prüft der Import nicht.</p>',
 'endsch_import'),

('endsch_import.einstellungen',
 'Import-Einstellungen',
 '<ul><li><strong>Mitglied</strong>: Empfänger der Resultate; ohne Auswahl startet der Import nicht. Aus einer sechsstelligen Lizenznummer im Dateinamen wird das Mitglied vorbelegt, die Wahl lässt sich ändern.</li><li><strong>Jahr</strong>: Endschiessen-Jahr, in das geschrieben wird (Standard: aktuelles Jahr).</li><li>Hat das Mitglied für dieses Jahr schon Resultate, erscheint eine Warnung mit den betroffenen Stichen; der Import ersetzt sie.</li></ul>',
 'endsch_import'),

('endsch_targetprint.uebersicht',
 'Zielscheiben aus Imetron-CSV drucken',
 '<p>Erzeugt aus einer Imetron-Resultatdatei ein PDF mit den <strong>Trefferbildern</strong> pro Stich, zum Beispiel als Andenken für Partnerinnen und Gäste. Es wird nichts in der Datenbank gespeichert.</p><ul><li><strong>Ablauf</strong>: CSV ablegen, optional den Namen des Schützen eingeben (erscheint im PDF-Titel) und das Jahr wählen, gefundene Stiche prüfen, PDF generieren. Danach herunterladen oder direkt drucken (Druckprofil «Endschiessen Zielscheiben» in der Drucksteuerung).</li><li>Welche Programmnummern erkannt werden, steht im Hinweis oben und stammt aus <a href="internestichedef.php">Imetron-Stichnummern</a>. Nicht definierte Nummern werden trotzdem ausgegeben, mit dem Namen aus der Datei.</li><li>Pro Stich eine Seite mit Trefferbild aus den Koordinaten, Statistik (Schuss, Wertung, Hunderter) und Total. Der Schwini-Stich wird auf der Keiler-Scheibe dargestellt.</li><li>Hunderterwertungen werden für die Anzeige auf die Zehnerskala umgerechnet (91–100 = 10, 81–90 = 9 und so weiter); Kunst und Glück bleiben unverändert.</li></ul>',
 'endsch_targetprint'),

('endsch_targetprint.stiche',
 'Gefundene Stiche',
 '<p>Jede Karte ist ein Programm aus der Datei mit Stichname (bei Schwini mit Passe), Schusszahl, Total, bester Hunderterwertung und einer Vorschau der Schüsse.</p><ul><li>Gezählt werden Schüsse mit Koordinaten oder einer Wertung grösser 0; Probeschüsse ohne Wertung fallen weg.</li><li>Die Farben der Wertungen: 10 rot, 9 blau, 7 und 8 orange.</li><li><strong>PDF Generieren</strong> ist erst aktiv, wenn mindestens ein Stich Schüsse enthält. Alle angezeigten Stiche kommen ins PDF; «Zurück» verwirft die Datei.</li></ul>',
 'endsch_targetprint'),

('endschrang.uebersicht',
 'Endschiessen: Ranglisten',
 '<p>Zeigt die Gesamtrangliste des Endschiessens für das gewählte Jahr, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>, und erzeugt alle Endschiessen-Dokumente als PDF.</p><ul><li>Das Jahr oben wählen; die Tabellen laden neu. <strong>Resultate bearbeiten</strong> wechselt zur Erfassung (<a href="endresultate.php">Endschiessen Resultate erfassen</a>) im gleichen Jahr.</li><li>Jeder Dokument-Knopf erzeugt ein PDF und zeigt den Link darunter; der Drucker-Knopf daneben schickt dasselbe Dokument direkt an den Drucker aus dem Druckprofil «Endschiessen Ranglisten» (Hoch- oder Querformat gemäss Profil). Die Broschüre hat ein eigenes Profil.</li><li>Am Bildschirm erscheinen nur Mitglieder mit einem Endstich-Resultat des Jahres. Sortiert wird nach Total, dann Endstich, dann Alter (ältere vor jüngeren); die ersten drei sind hervorgehoben.</li></ul>',
 'endschrang'),

('endschrang.dokumente',
 'Dokumente erstellen',
 '<ul><li><strong>Gesamt</strong>: Gesamtrangliste Kat. A und B mit allen Stichen. <strong>Zwischen</strong>: dieselbe Liste ohne Zabig, für den Stand vor dem letzten Stich.</li><li><strong>Anmeldung</strong>: Liste der Absenden-Anmeldungen (Mitglieder, Jungschützen, Gäste) sowie die Abrechnung von Schwini-Passen, Differenzler, Sie und Er und Partner-Paketen aus den gelösten Stichen und den Spezialpreisen.</li><li><strong>Absendenbuch</strong>: das Buch aus der Word-Vorlage; <strong>Broschüre</strong> legt dessen A5-Seiten paarweise auf A4 quer, so dass gefaltet ein Heft entsteht.</li><li><strong>Einzelwettbewerbe</strong>: Endstich (Mitglieder, Jungschützen und Partnerinnen, mit Königskranz), Schwini, Kunst, Glück, Zabig, Differenzler.</li><li><strong>Partner</strong>: Endstich plus beste Schwini-Passe der Partnerinnen. <strong>Sie &amp; Er</strong>: Summe der eindeutigen Werte aus den Schüssen von Partnerin und Mitglied.</li></ul>',
 'endschrang'),

('endschrang.wertung',
 'Spalten und Gesamttotal',
 '<ul><li><strong>Endstich</strong>: Summe der zehn Schüsse.</li><li><strong>Schwini</strong>: «beste Passe (schlechtere Passe)»; ins Total zählt die beste.</li><li><strong>Kunst</strong>: Summe der fünf Hunderterwertungen geteilt durch 10.</li><li><strong>Glück</strong>: bester der drei Schüsse geteilt durch 10.</li><li><strong>Zabig</strong>: die sechs Hunderterwertungen auf die Zehnerskala umgerechnet und summiert (91–100 = 10, 81–90 = 9, … 1–10 = 1).</li><li><strong>Differenzler</strong>: Ansage minus Zabig-Summe in Rohwerten; nur Anzeige, zählt nicht ins Total.</li><li><strong>Total</strong> = Endstich + beste Schwini-Passe + Kunst + Glück + Zabig. Die Zwischenrangliste lässt Zabig weg.</li></ul><p>Sortiert wird nach Total, dann Endstich, dann Alter. Die ersten drei jeder Kategorie erhalten in der Schützenabrechnung den Endschiessen-Preis.</p>',
 'endschrang'),

('endschrang.kategorien',
 'Kategorie A und B',
 '<p>Die Zuteilung zu Kat. A oder Kat. B richtet sich nach der <strong>Kategorie der Waffe</strong> in den Mitglieder-Stammdaten, nicht nach der beim Lösen gewählten Waffe. Beide Tabellen sind gleich aufgebaut und werden getrennt rangiert.</p><ul><li>Ein Waffenwechsel in den Stammdaten verschiebt das Mitglied in allen Jahren in die andere Kategorie.</li><li>Ohne Endstich-Resultat erscheint ein Mitglied in keiner der beiden Tabellen.</li></ul>',
 'endschrang'),

('internestichedef.uebersicht',
 'Imetron-Stichnummern',
 '<p>Ordnet jedem internen Stich die <strong>Programmnummern</strong> der Imetron-Schiessanlage zu (bis zu drei je Stich). Über diese Nummern erkennen die CSV-Importe (Endschiessen, Heimmeisterschaft, Kantonalstich) und der Zielscheiben-Ausdruck, zu welchem Stich ein Programm in der Datei gehört.</p><ul><li>Nummern eintragen oder ändern, dann <strong>Speichern</strong> (auch mit Ctrl+S). Der Knopf wird aktiv, sobald etwas geändert wurde.</li><li>Änderungen gelten sofort für den nächsten Import; bereits importierte Resultate bleiben unverändert.</li><li>Fehlt eine Nummer, wird das Programm beim Import übersprungen und die Datei meldet «keine relevanten Stiche».</li></ul>',
 'internestichedef'),

('internestichedef.stichnummern',
 'Stichnummern eintragen',
 '<ul><li>Pro Feld genau eine Programmnummer, so wie sie im Kopf der Imetron-Datei steht (zum Beispiel 522). Die Reihenfolge der drei Felder spielt keine Rolle, nicht gebrauchte Felder leer lassen.</li><li>Mehrere Nummern für einen Stich sind nötig, wenn die Anlage denselben Stich unter verschiedenen Programmen führt (etwa je Passe oder Distanz).</li><li>Eine Nummer darf nur bei einem Stich stehen; steht sie doppelt, ist die Zuordnung beim Import nicht eindeutig.</li><li>Schwini wird pro Programm als eine Passe importiert; Kunst und Glück werden ohne Umrechnung der Hunderterwertung übernommen.</li></ul>',
 'internestichedef'),

('schuetzenabr.uebersicht',
 'Schützenabrechnung',
 '<p>Erstellt für das gewählte Jahr eine Excel-Datei mit <strong>einem Tabellenblatt pro aktivem Mitglied</strong>: Mitgliederbeitrag als Belastung, Preise und Königskränze als Gutschrift, Zwischentotal, Total und eine Zeile «Betrag erhalten» zum Unterschreiben.</p><ul><li>Das Jahr oben wählen (aktuelles Jahr bis drei Jahre zurück), dann <strong>Excel</strong>; die Datei wird direkt heruntergeladen.</li><li>Ist das Total positiv, schuldet das Mitglied den Betrag; ist es negativ, zahlt der Verein aus.</li><li>Alle Beträge werden aus den erfassten Resultaten des Jahres berechnet; fehlende oder falsche Resultate zuerst in den Erfassungsseiten korrigieren und die Datei neu erzeugen.</li></ul>',
 'schuetzenabr'),

('schuetzenabr.inhalt',
 'Was in der Abrechnung steht',
 '<ul><li><strong>Mitgliederbeitrag</strong>: CHF 10, für Ehrenmitglieder CHF 0 (Belastung).</li><li><strong>Kantonalstich</strong>: nur zur Information, der Verein bezahlt ihn: CHF 13 für den Hauptdoppel und CHF 3 je Nachdoppel gemäss den erfassten Passen.</li><li><strong>Endstich</strong>: CHF 10, wenn das Endstich-Total die Kranzlimite der Waffe erreicht.</li><li><strong>Kunststich</strong>: CHF 10, wenn das Kunst-Total die Kunst-Kranzlimite der Waffe erreicht.</li><li><strong>Endschiessen</strong> und <strong>Endschiessen Gesamt</strong>: je CHF 10 für die Ränge 1–3 der eigenen Kategorie in der Gesamtrangliste.</li><li><strong>Heimmeisterschaft</strong>: CHF 30 / 20 / 10 für die Ränge 1–3 der eigenen Kategorie.</li><li><strong>MSV Wilen Cup</strong>: CHF 30 / 20 / 10 für die Ränge 1–3 des Cup-Finals.</li></ul><p>Gutschriften werden vom Mitgliederbeitrag abgezogen; die Kategorie ist die Waffen-Kategorie aus den Stammdaten.</p>',
 'schuetzenabr'),

-- ---------------------------------------------------------------- Gruppe C
('heimresultate.uebersicht',
 'Heimmeisterschaft: Resultate erfassen',
 '<p>Hier werden die Resultate der <strong>Heimmeisterschaft</strong> eines Jahres erfasst: pro Mitglied bis zu <strong>acht Passen</strong> mit je höchstens 100 Punkten. Das Total rechnet die Seite laufend mit.</p><ul><li>Die Liste zeigt alle aktiven Mitglieder alphabetisch, aufgeteilt in <em>Mit Resultaten</em> und <em>Noch keine Resultate</em>. Das Jahr oben bestimmt, welche Saison bearbeitet wird.</li><li>Erfasst wird direkt im Raster oder über die <strong>Schnellerfassung</strong> (Schütze um Schütze, nur am Desktop). Auf dem Handy erscheint pro Mitglied eine aufklappbare Karte mit Suchfeld.</li><li>Gespeichert wird erst mit <strong>Speichern</strong> bzw. im Panel der Schnellerfassung; ein Jahreswechsel ohne Speichern verwirft die Eingaben.</li><li>Die Einteilung in Kategorie A und B erfolgt nicht hier, sondern über die Waffe in den Mitglieder-Stammdaten; sie wirkt in der Rangliste (<a href="heimrang.php">Heimmeisterschaft Ranglisten</a>).</li></ul><p>Resultate aus der Auswertungsdatei der Schiessanlage lassen sich alternativ per CSV einlesen (<a href="heimkanti_import.php">CSV-Import Heim/Kanti</a>).</p>',
 'heimresultate'),

('heimresultate.aktionen',
 'Aktionen der Resultaterfassung',
 '<ul><li><strong>Schnellerfassung</strong> öffnet das Panel, in dem ein Schütze nach dem anderen erfasst wird (nur am Desktop sichtbar).</li><li><strong>Speichern</strong> schreibt alle Zeilen des Rasters. Leere Felder, auf die später noch ein Wert folgt, werden dabei automatisch mit 0 gefüllt; Mitglieder ohne einen einzigen Wert erhalten keinen Datensatz.</li><li><strong>Rangliste</strong> wechselt zur Heimmeisterschaft-Rangliste.</li><li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Heimresultate JAHR aktualisiert» im Änderungsprotokoll an, das den Mitgliedern angezeigt wird. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li><li><strong>Alle Resultate löschen</strong> entfernt nach Bestätigung sämtliche Heimresultate des gewählten Jahres. Das lässt sich nicht rückgängig machen.</li></ul>',
 'heimresultate'),

('heimresultate.tabelle',
 'Raster: Eingabe, Status und Total',
 '<ul><li>Pro Passe sind nur Zahlen erlaubt, höchstens drei Stellen; Werte über 100 werden auf 100 begrenzt. Ein Klick auf den Namen öffnet den Schützen in der Schnellerfassung.</li><li>Der Punkt vor dem Namen zeigt den Stand: leer = keine Passe, halb = teilweise erfasst, voll = alle acht Passen vorhanden.</li><li><strong>Total</strong> ist die Summe aller Passen und wird beim Tippen nachgeführt; die Rangliste rechnet dieselbe Summe.</li><li>Beim Speichern werden bei bestehenden Datensätzen nur ausgefüllte Felder übernommen. Ein geleertes Feld löscht den gespeicherten Wert deshalb nicht; wer ein Resultat entfernen will, trägt 0 ein.</li><li>Leere Felder vor einer später ausgefüllten Passe werden beim Speichern zu 0 (nicht geschossen).</li></ul>',
 'heimresultate'),

('heimresultate.schnellerfassung',
 'Schnellerfassung: Schütze um Schütze',
 '<p>Das Panel zeigt jeweils ein Mitglied mit grossen Eingabefeldern für alle acht Passen. Es ist nur eine andere Ansicht auf das Raster: jede Eingabe wird sofort in die Tabelle übernommen, das Total oben rechts läuft mit.</p><ul><li><strong>Auswahl oben</strong>: Schützen suchen oder wechseln; vollständig erfasste sind mit einem Häkchen markiert. Die Pfeile blättern vor und zurück, der Balken zeigt den Fortschritt.</li><li><strong>Enter</strong> springt zum nächsten Feld, <strong>Escape</strong> schliesst das Panel.</li><li><strong>Speichern</strong> sichert nur diesen Schützen; <strong>Speichern &amp; Weiter</strong> springt anschliessend zum nächsten Mitglied, dem noch Passen fehlen.</li><li>Werte über 100 werden auf 100 begrenzt; leere Felder vor einer späteren Passe werden als 0 gespeichert.</li></ul><p>Nicht gespeicherte Eingaben im Panel stehen weiterhin im Raster und werden mit dem grossen «Speichern» mitgesichert.</p>',
 'heimresultate'),

('kantiresultate.uebersicht',
 'Kantonalstich: Resultate erfassen',
 '<p>Hier werden die Resultate des <strong>Kantonalstichs</strong> eines Jahres erfasst: pro Mitglied bis zu <strong>fünf Passen</strong> (zweistellige Werte). Passe 1 gilt als Hauptdoppel, die Passen 2 bis 5 als Nachdoppel; so werden sie in der Kantonalstich-Rangliste und der SKSG-Abrechnung geführt.</p><ul><li>Die Liste zeigt alle aktiven Mitglieder alphabetisch, aufgeteilt in <em>Mit Resultaten</em> und <em>Noch keine Resultate</em>. Das Jahr oben bestimmt die Saison.</li><li>Erfasst wird direkt im Raster oder über die <strong>Schnellerfassung</strong> (Schütze um Schütze, nur am Desktop). Auf dem Handy erscheint pro Mitglied eine aufklappbare Karte mit Suchfeld.</li><li>Gespeichert wird erst mit <strong>Speichern</strong> bzw. im Panel; ein Jahreswechsel ohne Speichern verwirft die Eingaben.</li><li>Kategorie A und B ergeben sich aus der Waffe in den Mitglieder-Stammdaten und wirken in der Rangliste (<a href="kantirang.php">Kantonalstich Ranglisten</a>) sowie in der Abrechnung (<a href="kantiabr.php">Kantonalstich Ranglisten und Abrechnung</a>).</li></ul><p>Resultate aus der Auswertungsdatei der Schiessanlage lassen sich alternativ per CSV einlesen (<a href="heimkanti_import.php">CSV-Import Heim/Kanti</a>).</p>',
 'kantiresultate'),

('kantiresultate.aktionen',
 'Aktionen der Resultaterfassung',
 '<ul><li><strong>Schnellerfassung</strong> öffnet das Panel, in dem ein Schütze nach dem anderen erfasst wird (nur am Desktop sichtbar).</li><li><strong>Speichern</strong> schreibt alle Zeilen des Rasters. Leere Felder, auf die später noch ein Wert folgt, werden dabei automatisch mit 0 gefüllt; Mitglieder ohne einen einzigen Wert erhalten keinen Datensatz.</li><li><strong>Rangliste</strong> wechselt zur Kantonalstich-Rangliste.</li><li><strong>Veröffentlichen</strong> legt nach Rückfrage einen Eintrag «Kantiresultate JAHR aktualisiert» im Änderungsprotokoll an, das den Mitgliedern angezeigt wird. Die Resultate selbst sind unabhängig davon sofort in der Rangliste sichtbar.</li><li><strong>Alle Resultate löschen</strong> entfernt nach Bestätigung sämtliche Kantonalstich-Resultate des gewählten Jahres. Das lässt sich nicht rückgängig machen.</li></ul>',
 'kantiresultate'),

('kantiresultate.tabelle',
 'Raster: Eingabe, Status und Total',
 '<ul><li>Pro Passe sind nur Zahlen mit höchstens zwei Stellen erlaubt. Ein Klick auf den Namen öffnet den Schützen in der Schnellerfassung.</li><li>Die <strong>beste Passe</strong> eines Schützen wird farblich hervorgehoben (auf dem Handy mit einem Pokal markiert).</li><li>Der Punkt vor dem Namen zeigt den Stand: leer = keine Passe, halb = teilweise erfasst, voll = alle fünf Passen vorhanden.</li><li><strong>Total</strong> ist die Summe aller Passen und wird beim Tippen nachgeführt; die Rangliste rechnet dieselbe Summe.</li><li>Beim Speichern werden bei bestehenden Datensätzen nur ausgefüllte Felder übernommen. Ein geleertes Feld löscht den gespeicherten Wert deshalb nicht; wer ein Resultat entfernen will, trägt 0 ein.</li><li>Leere Felder vor einer später ausgefüllten Passe werden beim Speichern zu 0 (nicht geschossen).</li></ul>',
 'kantiresultate'),

('kantiresultate.schnellerfassung',
 'Schnellerfassung: Schütze um Schütze',
 '<p>Das Panel zeigt jeweils ein Mitglied mit grossen Eingabefeldern für die fünf Passen. Es ist nur eine andere Ansicht auf das Raster: jede Eingabe wird sofort in die Tabelle übernommen, das Total oben rechts läuft mit.</p><ul><li><strong>Auswahl oben</strong>: Schützen suchen oder wechseln; vollständig erfasste sind mit einem Häkchen markiert. Die Pfeile blättern vor und zurück, der Balken zeigt den Fortschritt.</li><li><strong>Enter</strong> springt zum nächsten Feld, <strong>Escape</strong> schliesst das Panel.</li><li><strong>Speichern</strong> sichert nur diesen Schützen; <strong>Speichern &amp; Weiter</strong> springt anschliessend zum nächsten Mitglied, dem noch Passen fehlen.</li><li>Leere Felder vor einer späteren Passe werden als 0 gespeichert.</li></ul><p>Nicht gespeicherte Eingaben im Panel stehen weiterhin im Raster und werden mit dem grossen «Speichern» mitgesichert.</p>',
 'kantiresultate'),

('heimrang.uebersicht',
 'Heimmeisterschaft: Ranglisten',
 '<p>Zeigt die Rangliste der <strong>Heimmeisterschaft</strong> des gewählten Jahres, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>, mit allen acht Passen und dem Total. Die Daten stammen direkt aus der Resultaterfassung; die Seite selbst ist reine Anzeige.</p><ul><li>Die Jahresauswahl umfasst das aktuelle und die drei vorangehenden Jahre; beim Wechsel werden beide Tabellen neu geladen.</li><li><strong>Resultate bearbeiten</strong> wechselt zur Erfassung (<a href="heimresultate.php">Heimmeisterschaft Resultate</a>).</li><li>Auf dem Handy werden die Tabellen als Karten mit Suchfeld dargestellt; die ersten drei Ränge sind hervorgehoben.</li></ul>',
 'heimrang'),

('heimrang.dokumente',
 'Rangliste als PDF und Direktdruck',
 '<ul><li><strong>Rangliste</strong> erzeugt ein PDF mit beiden Kategorien des gewählten Jahres und lädt es herunter. Standard ist Querformat; die Ausrichtung folgt dem Druckprofil «Heimmeisterschaft Rangliste» aus der Drucksteuerung.</li><li>Der <strong>Drucker-Knopf</strong> schickt dasselbe PDF direkt an den im Druckprofil hinterlegten Drucker. Er ist nur aktiv, wenn ein Drucker zugeordnet und der Druckdienst erreichbar ist; der Grund steht im Tooltip.</li></ul><p>Jede Erstellung schreibt eine neue, zeitgestempelte Datei; ältere Stände werden automatisch aufgeräumt.</p>',
 'heimrang'),

('heimrang.kategorien',
 'Kategorien und Reihenfolge',
 '<ul><li>Die Kategorie eines Schützen ergibt sich aus der <strong>Waffe</strong> in seinen Stammdaten (Kat. A oder Kat. B). Mitglieder ohne hinterlegte Waffe erscheinen in keiner der beiden Listen.</li><li>Aufgeführt wird nur, wer im gewählten Jahr ein Total grösser als 0 hat.</li><li>Sortiert wird nach <strong>Total</strong> absteigend (Summe aller acht Passen). Die Rangnummern werden fortlaufend vergeben, auch bei gleichem Total.</li><li>Die einzelnen Passen erscheinen so, wie sie erfasst wurden; nicht geschossene Passen stehen als 0 oder leer.</li></ul><p>Eine Änderung der Waffe in den Stammdaten verschiebt den Schützen samt Resultaten in die andere Kategorie, auch für frühere Jahre.</p>',
 'heimrang'),

('kantirang.uebersicht',
 'Kantonalstich: Ranglisten',
 '<p>Zeigt die Rangliste des <strong>Kantonalstichs</strong> des gewählten Jahres, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>, mit den fünf Passen (Hauptdoppel und bis zu vier Nachdoppel) und dem Total. Die Daten stammen direkt aus der Resultaterfassung; die Seite selbst ist reine Anzeige.</p><ul><li>Die Jahresauswahl umfasst das aktuelle und die drei vorangehenden Jahre; beim Wechsel werden beide Tabellen neu geladen.</li><li><strong>Resultate bearbeiten</strong> wechselt zur Erfassung (<a href="kantiresultate.php">Kantonalstich Resultate</a>).</li><li>Die Abrechnung für den Kantonalschützenverband (Excel-Formular der SKSG) und die Ranglisten je Doppel finden sich unter «Kantonalstich – Ranglisten» (<a href="kantiabr.php">Kantonalstich Ranglisten und Abrechnung</a>).</li><li>Auf dem Handy werden die Tabellen als Karten mit Suchfeld dargestellt; die ersten drei Ränge sind hervorgehoben.</li></ul>',
 'kantirang'),

('kantirang.dokumente',
 'Rangliste als PDF und Direktdruck',
 '<ul><li><strong>Rangliste</strong> erzeugt ein PDF mit beiden Kategorien des gewählten Jahres und lädt es herunter. Standard ist Hochformat; die Ausrichtung folgt dem Druckprofil «Kantonalstich Rangliste» aus der Drucksteuerung.</li><li>Der <strong>Drucker-Knopf</strong> schickt dasselbe PDF direkt an den im Druckprofil hinterlegten Drucker. Er ist nur aktiv, wenn ein Drucker zugeordnet und der Druckdienst erreichbar ist; der Grund steht im Tooltip.</li></ul><p>Jede Erstellung schreibt eine neue, zeitgestempelte Datei; ältere Stände werden automatisch aufgeräumt.</p>',
 'kantirang'),

('kantirang.kategorien',
 'Kategorien und Reihenfolge',
 '<ul><li>Die Kategorie eines Schützen ergibt sich aus der <strong>Waffe</strong> in seinen Stammdaten (Kat. A oder Kat. B). Mitglieder ohne hinterlegte Waffe erscheinen in keiner der beiden Listen.</li><li>Aufgeführt wird nur, wer im gewählten Jahr mindestens eine Passe grösser als 0 hat.</li><li>Sortiert wird nach <strong>Total</strong> absteigend (Summe aller fünf Passen), bei gleichem Total alphabetisch. Punktgleiche Schützen erhalten <strong>denselben Rang</strong>; der nächste Rang wird entsprechend übersprungen.</li><li>Nicht geschossene Passen werden mit «-» angezeigt.</li></ul><p>Eine Änderung der Waffe in den Stammdaten verschiebt den Schützen samt Resultaten in die andere Kategorie, auch für frühere Jahre.</p>',
 'kantirang'),

('sektionrang.uebersicht',
 'Sektionsmeisterschaft: Rangliste',
 '<p>Zeigt die beiden Runden der <strong>Sektionsmeisterschaft</strong> des gewählten Jahres nebeneinander, je mit dem Schnitt nach der Regel der Sektionsabrechnungen. Die Seite ist reine Anzeige.</p><ul><li><strong>Datenquelle</strong> sind die Resultate der Jahresmeisterschaft zum Anlass, dessen Bezeichnung «Sektionsmeisterschaft» enthält; erfasst werden sie in der JM-Resultaterfassung (<a href="jmresultate.php">Jahresmeisterschaft erfassen</a>) mit der Angabe Runde 1 bzw. Runde 2. Dorthin führt <strong>Resultate bearbeiten</strong>.</li><li><strong>Rangliste</strong> erzeugt ein PDF mit demselben Inhalt (Standard Hochformat, Ausrichtung gemäss Druckprofil «Sektionsmeisterschaft Rangliste»); der Drucker-Knopf schickt es direkt an den hinterlegten Drucker.</li><li>Die Jahresauswahl umfasst das aktuelle und die drei vorangehenden Jahre.</li></ul><p>Bewusst gibt es keine Rangnummern und keine Gesamtwertung über beide Runden.</p>',
 'sektionrang'),

('sektionrang.runden',
 'Runden und Schnitt',
 '<p>Jede Runde listet die Schützen mit ihrem Resultat, sortiert nach <strong>Punkten absteigend</strong>, bei Gleichstand alphabetisch; die Zahl im Titel ist die Anzahl Schützen der Runde. Die kürzere Liste wird mit Leerzeilen aufgefüllt, damit die Schnitt-Blöcke auf gleicher Höhe liegen.</p><p>Der <strong>Schnitt</strong> unter jeder Runde folgt der Regel der Sektionsabrechnungen:</p><ul><li><strong>Teilnehmer</strong>: aktive Mitglieder mit mehr als 0 Punkten. Inaktive Mitglieder stehen in der Liste, zählen aber nicht mit.</li><li><strong>Pflichtteilnehmer</strong>: Anzahl der zählenden Resultate. Massgebend ist die pro Jahr eingestellte Anzahl zählender Resultate (Sektionsabrechnungen), mindestens aber die Hälfte der Teilnehmer (abgerundet).</li><li><strong>Durchschnitt</strong>: Summe der besten Pflichtteilnehmer-Resultate geteilt durch deren Anzahl, auf zwei Stellen gerundet.</li><li><strong>Zuschlag</strong>: der Beteiligungszuschlag des Anlasses in Prozent, wie in der JM-Definition hinterlegt.</li><li><strong>Endergebnis</strong>: Summe der zählenden Resultate plus Zuschlag-Prozent der übrigen Resultate, geteilt durch die Pflichtteilnehmer, auf drei Stellen gerundet.</li></ul><p>Fehlt in einer Runde jedes Resultat, bleibt der Schnitt-Block leer.</p>',
 'sektionrang'),

('cuprang.uebersicht',
 'Vereinscup: Übersicht und Rangliste',
 '<p>Reine Anzeige des <strong>Vereinscups</strong> für das gewählte Jahr: alle Paarungen je Runde, die finale Rangliste und der Standcup-Final. Erfasst wird im Cup-Editor, dorthin führt <strong>Resultate bearbeiten</strong> (<a href="cup.php">Cup-Editor</a>).</p><ul><li>Ein Jahreswechsel lädt die Seite neu; zur Auswahl stehen das aktuelle und die drei vorangehenden Jahre.</li><li><strong>Rangliste</strong> erzeugt das Cup-PDF und lädt es herunter (Standard Hochformat, Ausrichtung gemäss Druckprofil «Vereinscup Rangliste»); der Drucker-Knopf schickt dasselbe PDF direkt an den hinterlegten Drucker.</li><li>Gewinner, Ausgeschiedene und Nachrücker werden nach denselben Regeln bestimmt wie im Cup-Editor; Änderungen dort sind hier sofort sichtbar.</li></ul>',
 'cuprang'),

('cuprang.paarungen',
 'Paarungen lesen',
 '<p>Pro Runde eine Karte je Paarung, pro Teilnehmer eine Zeile mit Resultat. Grün mit Abzeichen <strong>Gewinner</strong> bedeutet: dieser Schütze kommt weiter; durchgestrichen mit <strong>Out</strong>: ausgeschieden. Ein Strich «–» steht für ein noch fehlendes Resultat (oder 0).</p><ul><li><strong>Zweierpaarung</strong>: das höhere Resultat gewinnt. Bei Punktgleichheit bleibt die Paarung ohne Markierung, bis im Cup-Editor ein Gewinner per Klick bestimmt wurde.</li><li><strong>Dreiergruppe</strong>: Reihenfolge nach Resultat, bei Gleichstand nach Tiefschuss. Kommen zwei weiter, heisst das Abzeichen <strong>Weiter</strong>; kommt nur einer weiter (Schalter im Editor), heisst es <strong>Gewinner</strong>.</li><li>Ein im Editor von Hand gesetzter Gewinner (bzw. Verlierer bei Dreier-Gleichstand) hat Vorrang vor dem Resultat.</li><li>Solange in einer Dreiergruppe kein Resultat erfasst ist, wird niemand markiert.</li></ul>',
 'cuprang'),

('cuprang.finale',
 'Finale Rangliste',
 '<p>Zeigt die im Cup-Editor erfassten <strong>Finalresultate</strong> als Rangliste.</p><ul><li>Sortiert nach <strong>Punkten</strong> absteigend, bei Gleichstand nach <strong>Tiefschuss</strong>. Sind beide gleich, teilen sich die Schützen den Rang; der nächste Rang wird übersprungen.</li><li>Die ersten drei Ränge sind farblich hervorgehoben (Gold, Silber, Bronze).</li><li>Die Liste gibt den gespeicherten Stand eins zu eins wieder, einschliesslich eines allfälligen Kategorie-B-Finalisten. Wer im Finale steht, wird ausschliesslich im Cup-Editor festgelegt.</li></ul>',
 'cuprang'),

('cuprang.standcup',
 'Standcup Final',
 '<p>Der <strong>Standcup-Final</strong> ist ein eigener Block unabhängig vom Turnierbaum: je ein Teilnehmer mit Resultat pro Verein, erfasst im Cup-Editor.</p><ul><li>Sortiert nach <strong>Punkten</strong> absteigend; punktgleiche Teilnehmer erhalten denselben Rang.</li><li>Hinter dem Namen steht der Verein, die ersten drei Ränge sind farblich hervorgehoben.</li></ul>',
 'cuprang'),

('heimkanti_import.uebersicht',
 'CSV-Import Heim- und Kantonalstich',
 '<p>Liest die <strong>Auswertungsdatei (CSV) der Schiessanlage</strong> eines Schützen ein und übernimmt die Passentotale in die Resultaterfassung von <strong>Heimmeisterschaft</strong> und <strong>Kantonalstich</strong>. Der Ablauf hat drei Schritte: Datei ablegen, Programme und Mitglied wählen, Import bestätigen.</p><ul><li>Erkannt werden die Programm-Kopfzeilen der Datei (Programmnummer, Titel, Datum und Zeit, Total). Berücksichtigt werden nur Programme mit einem Total grösser als 0, deren Programmnummer zur Heimmeisterschaft oder zum Kantonalstich gehört; alle anderen Programme werden ignoriert.</li><li>Die gefundenen Programme erscheinen chronologisch, getrennt nach Heim und Kanti, und lassen sich einzeln an- oder abwählen.</li><li>Enthält der Dateiname eine sechsstellige <strong>Lizenznummer</strong>, wird das passende Mitglied automatisch vorgewählt.</li><li>Vor dem Schreiben zeigt ein Dialog, ob für Mitglied und Jahr bereits Resultate vorhanden sind, und stellt alte und neue Werte gegenüber; erst «Ja, überschreiben» bzw. «Import starten» führt den Import aus.</li></ul><p>Der Import lässt sich nicht rückgängig machen; Korrekturen erfolgen in der Resultaterfassung (<a href="heimresultate.php">Heimmeisterschaft Resultate</a>, <a href="kantiresultate.php">Kantonalstich Resultate</a>).</p>',
 'heimkanti_import'),

('heimkanti_import.einstellungen',
 'Mitglied, Jahr und Programmauswahl',
 '<ul><li><strong>Mitglied</strong>: der Schütze, dem die Resultate zugeschrieben werden. Die Vorwahl anhand der Lizenznummer im Dateinamen ist ein Vorschlag und sollte geprüft werden.</li><li><strong>Jahr</strong>: die Saison, in die importiert wird (aktuelles und die fünf vorangehenden Jahre). Es muss zum Datum der Programme passen.</li><li><strong>Programme</strong>: die angewählten Programme werden in der angezeigten Reihenfolge zu <strong>Passe 1, 2, 3 …</strong>. Heimmeisterschaft fasst höchstens 8 Passen, Kantonalstich höchstens 5; überzählige Programme sind gelb markiert und sollten abgewählt werden. Beim Kantonalstich wird das erste gewählte Programm damit zum Hauptdoppel.</li><li>Importiert werden nur die gewählten Passen; bereits gespeicherte Passen, die nicht im Import sind, bleiben unverändert. Fehlt für Mitglied und Jahr ein Datensatz, wird er angelegt.</li></ul><p>Nach erfolgreichem Import lädt die Seite neu für die nächste Datei.</p>',
 'heimkanti_import'),

-- ---------------------------------------------------------------- Gruppe D
('mitgliederverwaltung.uebersicht',
 'Mitglieder verwalten',
 '<p>Die Stammliste aller Vereinsmitglieder mit Adresse, Kontakt, Sportgerät und Status. Ein Klick auf eine Zeile öffnet das Mitglied rechts zum Bearbeiten; <strong>gespeichert wird automatisch beim Schliessen</strong> des Panels (Enter oder Escape), mit Ctrl+S sofort. Die Zeile leuchtet kurz grün, wenn das Speichern geklappt hat.</p><ul><li><strong>Hinzufügen</strong>: Lizenznummer, Name, Vorname, Geburtsdatum und Waffe sind Pflicht. Die Lizenznummer muss frei sein.</li><li><strong>Import</strong>: CSV mit Semikolon im Format des CSV-Exports (Kopfzeile nötig). Bestehende Lizenznummern werden aktualisiert, neue angelegt; fehlerhafte Zeilen werden übersprungen und nach dem Import aufgelistet.</li><li><strong>CSV</strong> ist der vollständige Export (auch als Vorlage für den Import), <strong>Adressliste</strong> eine Excel-Datei ohne verstorbene Mitglieder.</li></ul><p>Die Mitgliederdaten werden überall weiterverwendet: in Ranglisten und Resultaterfassung, in der Einsatzplanung, für die Zuordnung von Portal-Logins (<a href="benutzerverwaltung.php">Benutzerverwaltung</a>) und in Exporten. <strong>Löschen ist endgültig</strong>; bei Austritten ist es meist besser, nur den Status «Aktiv» auszuschalten.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.liste',
 'Mitgliederliste',
 '<p>Das Suchfeld filtert live nach Lizenznummer, Name, Vorname, E-Mail und Ort; der Zähler zeigt «sichtbar von gesamt».</p><ul><li>Die Spalte <strong>Status</strong> zeigt vier Punkte: Aktiv (Haken), Ehrenmitglied (Auszeichnung, orange), Verstorben (grau) und Jungschützenleiter (türkis). Ein ausgefüllter Punkt bedeutet «gesetzt».</li><li>Verstorbene Mitglieder werden abgeblendet dargestellt, bleiben aber in der Liste.</li><li>Die Spalte <strong>Waffe</strong> zeigt das Sportgerät aus der Waffenliste.</li></ul><p>Auf dem Smartphone erscheinen statt der Tabelle Karten mit eigener Suche und einem Bearbeiten-Knopf, der dasselbe Panel öffnet.</p>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.stammdaten',
 'Stammdaten eines Mitglieds',
 '<ul><li><strong>Lizenznr.</strong> ist die eindeutige Kennung des Mitglieds (Lizenznummer des Verbands) und kann nach dem Anlegen nicht mehr geändert werden. Sie ist der Schlüssel für Resultate, Import und die Portal-Registrierung.</li><li><strong>Waffe</strong> ist das Sportgerät aus der Waffenliste; es steuert die Einteilung in Ranglisten und Abrechnungen.</li><li><strong>Email</strong> wird bei der Portal-Registrierung abgeglichen: Stimmt die eingegebene Adresse mit dieser überein, ist das Konto sofort aktiv, sonst wartet es auf Freischaltung in der <a href="benutzerverwaltung.php">Benutzerverwaltung</a>.</li><li><strong>Telefon/Mobile</strong> werden beim Verlassen des Feldes ins Format «+41 79 123 45 67» gebracht.</li><li><strong>Kommunikation</strong> (Briefpost, Whatsapp, Beides) und <strong>Vereinsaufnahme</strong> sind Informationsfelder und erscheinen in der Adressliste. <strong>Notizen</strong> sind nur hier sichtbar.</li></ul>',
 'mitgliederverwaltung'),

('mitgliederverwaltung.status',
 'Status-Schalter',
 '<ul><li><strong>Aktiv</strong>: Nur aktive Mitglieder werden in vielen Auswahllisten und Ranglisten berücksichtigt, zum Beispiel in der Jahresmeisterschaft oder bei der Zuordnung von Portal-Logins. Ausgetretene Mitglieder werden hier ausgeschaltet statt gelöscht.</li><li><strong>Ehrenmitglied</strong>: Kennzeichnung, die unter anderem in der Adressliste und in der Schützenabrechnung ausgewiesen wird.</li><li><strong>Verstorben</strong>: Die Zeile wird abgeblendet; das Mitglied verschwindet aus Adressliste, Fragebogen und den Auswahllisten der Resultaterfassung, bleibt aber mit seinen Resultaten erhalten.</li><li><strong>Jungschützenleiter</strong>: Das Mitglied gehört zur Jungschützenleitung. Es sieht das Betreuer-Board auch ohne eigene Anmeldung als Betreuer, empfängt den Leitungs-Chat der Jungschützen und die Eskalationen bei unbetreuten Anfragen. Dafür braucht das Mitglied ein freigegebenes Portal-Login; ohne Login laufen diese Meldungen ins Leere (Hinweis in der <a href="jsk_verwaltung.php">JSK-Verwaltung</a>).</li></ul>',
 'mitgliederverwaltung'),

('benutzerverwaltung.uebersicht',
 'Benutzer und Logins verwalten',
 '<p>Hier stehen alle Login-Konten für den Admin-Bereich und das Mitgliederportal. Diese Seite ist <strong>nur für Administratoren</strong> zugänglich. Neue Registrierungen (gelb hinterlegt) stehen zuoberst und warten auf Freischalten oder Ablehnen.</p><ul><li><strong>Registrierung von Mitgliedern</strong>: Das Mitglied registriert sich mit seiner Lizenznummer. Stimmt die E-Mail mit der Adresse in der <a href="mitgliederverwaltung.php">Mitgliederverwaltung</a> überein, ist das Konto sofort aktiv und dem Mitglied zugeordnet; sonst bleibt es «Ausstehend».</li><li><strong>Jungschützen</strong> registrieren sich über eine eigene Seite und werden immer manuell freigegeben, hier oder direkt in der <a href="jsk_verwaltung.php">JSK-Verwaltung</a>.</li><li><strong>Neuer Benutzer</strong> legt ein Konto direkt an, mit Rolle Admin und Status Aktiv. Die Rolle danach bei Bedarf in der Tabelle anpassen.</li><li><strong>Bearbeiten</strong> ändert Benutzername, Name, E-Mail und optional das Passwort (leer lassen = behalten). Benutzername und E-Mail müssen eindeutig sein.</li></ul><p>Schutzregeln: Die eigene Rolle lässt sich nicht ändern, das eigene Konto und der erste Administrator lassen sich nicht löschen, Admin-Konten lassen sich nicht deaktivieren.</p>',
 'benutzerverwaltung'),

('benutzerverwaltung.rollen',
 'Rollen',
 '<ul><li><strong>Admin</strong>: voller Zugriff, zusätzlich Benutzerverwaltung, Hilfetexte, PDF-Vorlagen, Dokumente mit Sichtbarkeit «Nur Admin» und die globalen Schalter der Jungschützen-Betreuung.</li><li><strong>Vorstand</strong>: Admin-Bereich (Resultate, Definitionen, Dokumente, Foto-Galerien, JSK-Verwaltung) und Mitgliederportal, aber keine Benutzerverwaltung und keine globalen Schalter.</li><li><strong>Mitglied</strong>: nur das Mitgliederportal. Persönliche Inhalte (eigene Einsätze, eigene Resultate, Fragebogen) erscheinen erst, wenn das Konto einem Mitglied zugeordnet ist.</li><li><strong>Jungschütze</strong>: eigener Portalbereich mit JSK-Übersicht, Terminen, Resultaten, Dokumenten, Betreuung und Chat. Das Konto ist mit den Jungschützen-Stammdaten verknüpft, nicht mit einem Mitglied.</li></ul><p>Ein Rollenwechsel wird sofort gespeichert und gilt spätestens ab der nächsten Anmeldung des Benutzers. Die eigene Rolle ist gesperrt.</p>',
 'benutzerverwaltung'),

('benutzerverwaltung.status',
 'Konto-Status',
 '<ul><li><strong>Ausstehend</strong>: registriert, aber noch ohne Zugang. <em>Freischalten</em> setzt das Konto auf Aktiv (Zeitpunkt und Bearbeiter werden festgehalten), <em>Ablehnen</em> auf Abgelehnt.</li><li><strong>Aktiv</strong>: Der Benutzer kann sich anmelden.</li><li><strong>Deaktiviert</strong>: Zugang gesperrt, alle Daten bleiben erhalten; jederzeit wieder aktivierbar. Admin-Konten können nicht deaktiviert werden.</li><li><strong>Abgelehnt</strong>: kein Zugang. Abgelehnte Jungschützen-Konten lassen sich in der JSK-Verwaltung nachträglich doch freischalten.</li></ul><p><strong>Löschen</strong> entfernt das Login-Konto endgültig; die Mitglieder- oder Jungschützen-Stammdaten bleiben davon unberührt.</p>',
 'benutzerverwaltung'),

('benutzerverwaltung.zuordnung',
 'Zuordnung zu einem Mitglied',
 '<p>Die Zuordnung verbindet ein Login mit einem Eintrag der <a href="mitgliederverwaltung.php">Mitgliederverwaltung</a>. Erst damit zeigt das Portal persönliche Inhalte: eigene Einsätze und Einsatz-Tausch, eigene Resultate von Jahresmeisterschaft, Heim- und Kantonalstich, Wanderpreise, Fragebogen sowie die Leiter-Funktion im Jungschützen-Chat.</p><ul><li>Bei der Selbstregistrierung mit Lizenznummer entsteht die Zuordnung automatisch. Manuell wird sie mit <em>Zuordnen</em> gesetzt.</li><li>Zur Auswahl stehen nur aktive Mitglieder, die noch kein Konto haben; jedes Mitglied kann höchstens einem Konto zugeordnet sein.</li><li>Bei Jungschützen zeigt die Spalte den verknüpften Jungschützen mit dem Zusatz «JSK»; diese Verknüpfung entsteht bei der JSK-Registrierung.</li></ul>',
 'benutzerverwaltung'),

('dokumente_verwaltung.uebersicht',
 'Dokumente verwalten',
 '<p>Zentrale Ablage aller Vereinsdokumente, die im Mitgliederportal erscheinen: <strong>Einsatzpläne</strong>, <strong>Protokolle</strong> und <strong>JSK-Dokumente</strong>. Hochladen, Bearbeiten und Löschen geschieht nur hier (Admin und Vorstand); die Portal-Seiten sind reine Ansichten.</p><ul><li>Der <strong>Jahr</strong>-Filter oben gilt für alle Tabs. Ein Dokument gehört zum Jahr seines Datums.</li><li>Pro Dokument: Auge öffnet die Datei, Stift bearbeitet Titel, Datum, Sichtbarkeit, Beschreibung oder ersetzt die Datei, Papierkorb löscht endgültig. Bearbeiten darf, wer das Dokument hochgeladen hat, sowie Administratoren.</li><li>Erlaubt sind PDF, Word, Excel und Bilder (je nach Tab), maximal 10 MB. Der Dateityp wird am Inhalt geprüft, nicht am Namen.</li><li>Die Tabs <strong>Einsätze</strong> und <strong>Tausche</strong> zeigen die aus Einsatzplänen importierten Einsätze und das Protokoll der von den Mitgliedern abgewickelten Tausche.</li></ul>',
 'dokumente_verwaltung'),

('dokumente_verwaltung.einsatzplan',
 'Einsatzpläne hochladen',
 '<p>Einsatzpläne erscheinen den Mitgliedern im Portal unter «Einsatzpläne».</p><ul><li><strong>Sichtbarkeit</strong>: <em>Nur Admin</em> (nur von Administratoren wählbar), <em>Nur Vorstand</em> oder <em>Alle Mitglieder</em>. Word- und Excel-Dateien werden automatisch auf «Nur Admin» gesetzt, weil sie Arbeitsdateien sind; für die Mitglieder gehört das PDF hinein.</li><li>Das <strong>Tabellen-Symbol</strong> bei einem Dokument importiert die Einsätze aus der Datei (Word, PDF oder Excel): Vorschau mit Namensabgleich gegen die Mitgliederliste (grün = eindeutig, gelb = ähnlich, rot = nicht gefunden), danach <em>Importieren</em>. Ein erneuter Import desselben Dokuments ersetzt dessen frühere Einträge.</li><li>Importierte Einsätze stehen im Tab <strong>Einsätze</strong> und bei den zugeordneten Mitgliedern unter «Meine Einsätze».</li></ul><p>Obligatorisch, Feldschiessen und Wiler Chilbi werden in der <a href="einsatzplanung.php">Einsatzplanung</a> geführt; dieser Import ist für fremde Einsatzpläne gedacht.</p>',
 'dokumente_verwaltung'),

('dokumente_verwaltung.protokoll',
 'Protokolle',
 '<p>Protokolle von Generalversammlung und Vorstandssitzungen. Das <strong>Datum</strong> ist das Sitzungsdatum und bestimmt das Jahr im Filter.</p><ul><li><strong>Nur Vorstand</strong>: sichtbar für Vorstand und Administratoren.</li><li><strong>Alle Mitglieder</strong>: jedes freigegebene Mitglied sieht das Protokoll im Portal unter «Protokolle».</li><li><strong>Nur Admin</strong>: nur für Administratoren (nur von diesen wählbar).</li></ul><p>Jungschützen haben keinen Zugang zu Protokollen. Erlaubt sind PDF, Word und Bilder bis 10 MB.</p>',
 'dokumente_verwaltung'),

('dokumente_verwaltung.jsk',
 'JSK-Dokumente',
 '<p>Unterlagen für die Jungschützen, zum Beispiel Standblätter oder Kursinformationen. Sie erscheinen im Jungschützen-Portal unter «Dokumente» und für Mitglieder und Vorstand über das Dokumente-Menü.</p><ul><li>Die Sichtbarkeit ist fest <strong>«Alle Mitglieder»</strong> und lässt sich nicht ändern. Nur so können Jungschützen, die keine Vereinsmitglieder sind, die Datei abrufen.</li><li>Erlaubt sind PDF, Word und Bilder bis 10 MB.</li></ul><p>Die <a href="jsk_verwaltung.php">JSK-Verwaltung</a> verlinkt direkt auf diesen Tab.</p>',
 'dokumente_verwaltung'),

('dokumente_verwaltung.einsaetze',
 'Importierte Einsätze',
 '<p>Alle Einsätze des gewählten Jahres, gruppiert nach Anlass und Datum. Kommende Anlässe sind aufgeklappt, vergangene zugeklappt; <em>Alle ausklappen</em> öffnet alles.</p><ul><li><strong>Mitglied (DB)</strong>: grün = das Mitglied ist zugeordnet und sieht den Einsatz im Portal unter «Meine Einsätze», inklusive Erinnerung vor dem Termin. Rot = nicht zugeordnet, das Mitglied erhält nichts. Über den Stift lässt sich das Mitglied nachträglich wählen.</li><li>Der <strong>Stift</strong> ändert Funktion, Name, Zuordnung, Datum und Zeit. Der Papierkorb löscht einen Eintrag, <em>Alle</em> beim Anlass löscht sämtliche Einträge des zugehörigen Imports.</li><li>Auch Einsätze aus der <a href="einsatzplanung.php">Einsatzplanung</a> werden hier abgebildet; wird bei einem solchen Eintrag das Mitglied gewechselt, ändert sich auch der Plan.</li></ul>',
 'dokumente_verwaltung'),

('dokumente_verwaltung.tausche',
 'Einsatz-Tausche und Übernahmen',
 '<p>Protokoll der Tausche, die die Mitglieder im Portal selbst abwickeln: Beim <strong>Tausch</strong> wechseln zwei Mitglieder ihre Einsätze, bei der <strong>Übernahme</strong> übernimmt ein Mitglied den Einsatz eines anderen. Sobald die Gegenseite bestätigt, wird die Zuordnung automatisch umgeschrieben; der Vorstand wird nur informiert.</p><ul><li>Status: <em>Offen</em> (wartet auf die Gegenseite), <em>Bestätigt</em> (umgesetzt), <em>Abgelehnt</em>, <em>Zurückgezogen</em>.</li><li>Die Liste ist nur zur Ansicht und zeigt die letzten 100 Vorgänge des Jahres.</li><li>Korrekturen erfolgen im Tab <strong>Einsätze</strong> über den Stift beim betroffenen Einsatz.</li></ul>',
 'dokumente_verwaltung'),

('anlass_galerie_verwaltung.uebersicht',
 'Foto-Galerien',
 '<p>Zu jedem Anlass des Jahresprogramms kann eine Foto-Galerie freigeschaltet werden. Die Mitglieder laden ihre Fotos im Portal unter «Fotos» hoch; dort läuft auch die Slideshow, die die Bilder nach Schiesstagen abspielt. Jungschützen haben keinen Zugang zu den Galerien.</p><ul><li>Der <strong>Jahr</strong>-Filter bezieht sich auf das Jahresprogramm; angeboten werden die dort erfassten, nicht versteckten Anlässe.</li><li>Ablauf: oben Galerie <strong>freischalten</strong>, danach auf der Karte <strong>Details</strong> für Einstellungen, Moderation, Reihenfolge, Vorschaubild und ZIP-Download. Das Auge öffnet die Galerie im Portal.</li><li>Fotos werden beim Hochladen verkleinert gespeichert (max. 15 MB pro Datei); doppelte Bilder werden abgewiesen.</li><li>Warten Fotos auf Freigabe, erhält der Vorstand eine Mitteilung mit Direktlink hierher, höchstens einmal pro Stunde und Galerie.</li></ul><p>Das Löschen einer Galerie entfernt alle ihre Fotos endgültig.</p>',
 'anlass_galerie_verwaltung'),

('anlass_galerie_verwaltung.freischalten',
 'Galerie freischalten',
 '<p>Die Auswahl zeigt die Anlässe des Jahres, die noch keine Galerie haben. <em>Freischalten</em> legt die Galerie an; die Schalter lassen sich danach unter <strong>Details</strong> anpassen.</p><ul><li>Beim Freischalten erhalten alle Mitglieder, die das Thema «Fotos» in ihren Benachrichtigungen aktiviert haben, die Mitteilung «Neue Foto-Galerie» mit der Aufforderung, Fotos hochzuladen.</li><li>Die <strong>Schiesstage</strong> des Anlasses aus der <a href="jmdefinition.php">JM-Definition</a> steuern die Zuordnung der Fotos zu Tagen. Fehlen sie, werden die Fotos nach Aufnahmedatum gruppiert (Hinweis auf der Karte). Schiesstage dort nachtragen (mit Monatsname, z.B. «27. Juni 2026») und in den Details «Tage neu zuordnen» klicken.</li></ul>',
 'anlass_galerie_verwaltung'),

('anlass_galerie_verwaltung.galerien',
 'Eingerichtete Galerien',
 '<p>Jede Karte zeigt den Zustand einer Galerie:</p><ul><li><strong>Sichtbar / Verborgen</strong>: ob die Galerie im Portal erscheint.</li><li><strong>Moderation an / aus</strong>: ob hochgeladene Fotos zuerst bewilligt werden müssen. Uploads von Vorstand und Admin sind immer sofort freigegeben.</li><li><strong>Upload offen / zu</strong>: ob Mitglieder noch Fotos hochladen dürfen. Vorstand und Admin können immer hochladen.</li><li><strong>Foto(s)</strong> und <strong>wartend</strong>: Anzahl aller Fotos und der noch nicht bewilligten.</li></ul><p>Auge = Galerie im Portal ansehen, <strong>Details</strong> = Einstellungen und Moderation, Papierkorb = Galerie samt allen Fotos löschen.</p>',
 'anlass_galerie_verwaltung'),

('anlass_galerie_verwaltung.moderation',
 'Galerie-Details und Moderation',
 '<p>Oben die <strong>Einstellungen</strong>: Sichtbar, Upload offen, Fotos bewilligen, Beschreibung und ein Programm-PDF (max. 10 MB), das im Portal zur Galerie angezeigt wird. <em>Einstellungen speichern</em> wirkt sofort.</p><ul><li>Die <strong>Fotos</strong> sind nach Tagen gruppiert. Jede Kachel zeigt Status (Wartet, Freigegeben, Abgelehnt), Bildunterschrift (Klick zum Bearbeiten; erscheint in der Slideshow), Uploader und Aufnahmedatum. «(Dateidatum)» heisst: das Bild hatte keine Aufnahmezeit, es zählt das Datum der Datei.</li><li><strong>Freigeben, Ablehnen, Löschen</strong> pro Foto, <em>Alle freigeben</em> für alle wartenden. Uploader werden über Freigabe oder Ablehnung informiert, gebündelt und nur bei einer echten Änderung; der Moderator selbst erhält keine Meldung.</li><li>Galerie und Slideshow zeigen <strong>nur freigegebene</strong> Fotos. Abgelehnte sieht nur der Uploader, als «abgelehnt» markiert.</li><li><strong>Ziehen</strong> ändert die Reihenfolge in Galerie und Slideshow. In einen anderen Tag ziehen setzt den Tag fest («manuell»); <em>Tage neu zuordnen</em> rechnet alle anderen Fotos anhand der Schiesstage neu.</li><li><strong>Stern</strong> macht ein Foto zum Vorschaubild der Übersicht (sonst gilt das erste freigegebene Foto). <strong>ZIP</strong> lädt alle Fotos in voller Grösse mit Ordnern pro Tag, wartende und abgelehnte getrennt, fürs Archiv.</li></ul><p><em>Alle Fotos löschen</em> leert die Galerie endgültig; die Galerie selbst bleibt.</p>',
 'anlass_galerie_verwaltung'),

('jsk_verwaltung.uebersicht',
 'JSK-Verwaltung',
 '<p>Alles rund um die Jungschützen an einem Ort: Stammdaten und Login-Konten (Tab <strong>Jungschützen</strong>), Teilnehmerlisten pro Kurstermin (Tab <strong>Teilnehmerlisten</strong>), Betreuungs-Anfragen (Tab <strong>Anfragen</strong>), die beiden globalen Schalter, der Info-Text für die JSK-Übersicht und der Link zu den JSK-Dokumenten.</p><ul><li>Jungschützen sind <strong>keine Vereinsmitglieder</strong>: eigener Datenbestand, eigene Rolle «Jungschütze» und ein eigener Portalbereich mit Übersicht, Terminen (mit Teilnahme-Schalter), Resultaten, Dokumenten, Betreuung und Chat.</li><li>Der <strong>Info-Text</strong> (Titel und Text) erscheint zuoberst auf der JSK-Übersicht; leer lassen blendet den Block aus.</li><li>Kurstermine werden unter <a href="wichtigetermine.php">Wichtige Termine</a> mit dem Schalter «Für Jungschützen» erfasst; erst dann erscheinen sie in den Teilnehmerlisten und im JSK-Portal.</li><li>Standardmässig gelten alle aktiven Jungschützen als teilnehmend; nur wer sich im Portal abmeldet, steht unter «Nicht dabei».</li></ul><p>Vorstand und Admin können alles bearbeiten; die globalen Schalter sind Administratoren vorbehalten.</p>',
 'jsk_verwaltung'),

('jsk_verwaltung.betreuung',
 'Jungschützen-Betreuung (Master-Schalter)',
 '<p>Schaltet die Betreuungsfunktion als Ganzes ein oder aus; ändern kann das nur ein Administrator. Ist sie <strong>aus</strong>, sind Schiess-Anmeldung, Betreuer-Board, die dazugehörigen Benachrichtigungen, die Navigation und die Registrierung neuer JSK-Konten überall gesperrt.</p><ul><li>Ablauf bei <strong>ein</strong>: Ein Jungschütze meldet ein Schiess-Datum an. Alle <strong>aktivierten Betreuer</strong> (Mitglieder, die in ihren Benachrichtigungen «Jungschützen-Betreuung» eingeschaltet haben) erhalten eine Meldung. Wer zuerst «Ich kümmere mich» klickt, übernimmt; die anderen sehen «Betreut von …». Zwischen Jungschütze und Betreuer entsteht ein eigener Chat.</li><li>Am Vortag werden Jungschütze und Betreuer erinnert. Ist zwei Tage vorher noch niemand eingeteilt, wird die Jungschützenleitung informiert. Vergangene Anfragen wechseln automatisch auf «Erledigt».</li><li>Der <strong>Stand</strong> unter den Schaltern zeigt, wer als Leitung im Portal erreichbar ist (Flag «Jungschützenleiter» in der <a href="mitgliederverwaltung.php">Mitgliederverwaltung</a> plus freigegebenes Login) und wie viele Betreuer aktiviert sind. Bei 0 aktivierten Betreuern erhält niemand die Anfragen.</li></ul>',
 'jsk_verwaltung'),

('jsk_verwaltung.einsicht',
 'Leitung liest Betreuer-Chats mit',
 '<p>Jugendschutz-Schalter, nur für Administratoren; Standard ist <strong>aus</strong>.</p><ul><li><strong>Ein</strong>: Jungschützenleiter können die Chats zwischen einem Jungschützen und seinem Betreuer lesen, aber nicht darin schreiben. Beide Chat-Seiten sehen den Hinweis «Die Jungschützenleitung kann mitlesen».</li><li><strong>Aus</strong>: Diese Chats bleiben privat zwischen Jungschütze und Betreuer.</li></ul><p>Unabhängig davon sehen die Leiter immer den Leitungs-Chat, in dem sich Jungschützen direkt an die Jungschützenleitung wenden.</p>',
 'jsk_verwaltung'),

('jsk_verwaltung.jungschuetzen',
 'Jungschützen-Stammdaten',
 '<p>Ein Klick auf eine Zeile öffnet den Jungschützen rechts: Stammdaten, Adresse, Kontakt, Kurs (Kurs-Nr. 1 bis 4 und Kursjahr), Aktiv und das Login-Konto. Gespeichert wird mit dem Knopf <em>Speichern</em>; Vorname und Name sind Pflicht, die E-Mail muss eindeutig sein.</p><ul><li>Die <strong>E-Mail</strong> ist wichtig: Über sie (zusammen mit dem Namen) registriert sich der Jungschütze für sein Login.</li><li><strong>Aktiv</strong>: Nur aktive Jungschützen zählen in den Teilnehmerlisten als teilnehmend.</li><li><strong>Excel-Import</strong>: Mitgliederverzeichnis des Verbands oder eigene Liste (xlsx, xls, csv). Vorgeschlagen werden nur plausible Jahrgänge (8 bis 22 Jahre) oder Zeilen ohne Geburtsdatum; einzelne Zeilen lassen sich abwählen. Kurs-Nr. und Kursjahr aus dem Import-Fenster gelten für alle importierten Personen. Bestehende Jungschützen (erkannt an E-Mail, sonst an Name und Geburtsdatum) werden aktualisiert und auf Aktiv gesetzt.</li><li>Die Spalte <strong>Konto</strong> zeigt den Stand des Logins: Kein Konto, Freigabe offen, Konto aktiv, Abgelehnt oder Deaktiviert.</li></ul><p><strong>Löschen</strong> entfernt den Jungschützen samt seinen Betreuungs-Anfragen. Ein vorhandenes Login-Konto bleibt bestehen, verliert aber die Verknüpfung und sollte in der <a href="benutzerverwaltung.php">Benutzerverwaltung</a> deaktiviert oder gelöscht werden.</p>',
 'jsk_verwaltung'),

('jsk_verwaltung.anfragen',
 'Betreuungs-Anfragen',
 '<p>Alle Schiess-Anfragen der Jungschützen aus den letzten 120 Tagen und alle kommenden. Der Filter trennt <em>Kommende</em> und <em>Vergangene</em>; die Zahl am Tab sind die offenen kommenden Anfragen.</p><ul><li><strong>Status</strong>: <em>Offen</em> (noch kein Betreuer), <em>Vergeben</em> (Betreuer eingeteilt), <em>Abgesagt</em> (storniert), <em>Erledigt</em> (Datum vorbei; «ohne Betreuer» heisst, dass sich niemand gefunden hat).</li><li><strong>Zuteilen / Umteilen</strong>: Betreuer aus allen aktiven Mitgliedern mit Login wählen, aktivierte Betreuer stehen mit Stern zuoberst. Jungschütze, neuer und allfälliger bisheriger Betreuer werden benachrichtigt.</li><li><strong>Freigeben</strong> setzt eine vergebene Anfrage wieder auf Offen und informiert Jungschütze, bisherigen Betreuer und den Betreuerkreis. <strong>Stornieren</strong> setzt sie auf Abgesagt und informiert die Beteiligten.</li><li>Abgesagte und erledigte Anfragen lassen sich nicht mehr ändern.</li></ul><p><strong>Betreuungen</strong> unten zählt pro Mitglied die vergebenen und erledigten Betreuungen des laufenden Jahres und weist Anfragen aus, die ohne Betreuer blieben.</p>',
 'jsk_verwaltung'),

('jsk_verwaltung.konto',
 'Login-Konto des Jungschützen',
 '<p>Jungschützen registrieren sich selbst über die JSK-Registrierung. Dabei müssen E-Mail und Name mit den hier hinterlegten Stammdaten übereinstimmen. Das Konto steht danach immer auf <strong>«Freigabe offen»</strong>, eine automatische Freischaltung gibt es nicht; die Jungschützenleitung erhält eine Mitteilung.</p><ul><li><strong>Freischalten</strong> (auch durch den Vorstand) aktiviert das Konto mit der Rolle «Jungschütze» und schickt dem Jungschützen eine Bestätigung per E-Mail.</li><li><strong>Ablehnen</strong> verweigert den Zugang; ein abgelehntes Konto lässt sich später mit «Doch freischalten» aktivieren.</li><li><strong>Deaktivieren / Aktivieren</strong> sperrt den Zugang vorübergehend oder gibt ihn wieder frei.</li></ul><p>Dasselbe ist für Administratoren auch in der <a href="benutzerverwaltung.php">Benutzerverwaltung</a> möglich, wo wartende Konten gelb hervorgehoben sind.</p>',
 'jsk_verwaltung'),

('jungschuetzen_helfer.uebersicht',
 'Helferstunden Jungschützenkurs',
 '<p>Hier werden pro Kursanlass die geleisteten <strong>Helferstunden</strong> der beiden Vereine Wilen und Wollerau erfasst, als Grundlage für die Abrechnung des Jungschützenkurses. Die Seite arbeitet immer mit dem <strong>laufenden Jahr</strong>.</p><ul><li>Die Zeilen sind die Termine des Jahres aus <a href="wichtigetermine.php">Wichtige Termine</a>, deren Bezeichnung das Wort «Jungschützenkurs» enthält. Ein Kursabend ohne dieses Wort erscheint hier nicht; die Bezeichnung dort anpassen.</li><li><strong>Zusätzlicher Helfereinsatz</strong> erfasst einen Einsatz ohne Termin (kursiv dargestellt), zum Beispiel Vorbereitungsarbeiten. Solche Einträge erscheinen unabhängig vom Jahr.</li><li><strong>Speichern</strong> übernimmt alle Eingaben der Tabelle auf einmal. Der Papierkorb löscht die gespeicherten Stunden eines Termins bzw. den freien Eintrag; der Termin selbst bleibt.</li><li><strong>PDF</strong> erstellt die Liste des Jahres mit den Summen pro Verein; der Download-Link erscheint unter der Tabelle.</li></ul>',
 'jungschuetzen_helfer'),

('jungschuetzen_helfer.erfassung',
 'Helferstunden pro Anlass',
 '<ul><li><strong>Datum</strong> und <strong>Bezeichnung</strong> kommen vom Termin und werden hier nicht geändert.</li><li><strong>Wilen</strong> und <strong>Wollerau</strong>: Anzahl Helferstunden des jeweiligen Vereins, in halben Stunden (0.5) möglich. Leer bedeutet keine Stunden.</li><li>Beim <em>Speichern</em> werden neue Zeilen angelegt und bestehende aktualisiert.</li><li>Kursive Zeilen sind freie Einträge ohne Termin.</li><li>Der Papierkorb ist nur bei bereits gespeicherten Zeilen wirksam.</li></ul><p>Auf dem Smartphone werden die Zeilen als Karten mit Eingabefeldern gezeigt; <em>Speichern</em> gilt auch dort für alle Karten zusammen.</p>',
 'jungschuetzen_helfer'),

('wichtigetermine.uebersicht',
 'Wichtige Termine',
 '<p>Die Vereinstermine eines Jahres ausserhalb des Schiessprogramms, zum Beispiel Generalversammlung, Delegiertenversammlungen oder Kursabende. Die Schiesstage der Jahresmeisterschaft gehören nicht hierher, sondern in die <a href="jmdefinition.php">JM-Definition</a>.</p><ul><li>Die Termine erscheinen im Mitgliederportal unter «Termine» und auf dem Dashboard unter «Nächste Termine», JSK-Termine zusätzlich im Jungschützen-Portal mit Teilnahme-Schalter. Sie fliessen in PDF und Kalenderdatei, in die Teilnehmerlisten der <a href="jsk_verwaltung.php">JSK-Verwaltung</a> und (bei Bezeichnung «Jungschützenkurs») in die <a href="jungschuetzen_helfer.php">Helferstunden</a>.</li><li>Beim <strong>Erfassen</strong> geht sofort die Mitteilung «Neuer Termin» an alle, die das Thema «Termine» aktiviert haben: bei Vereinsterminen an die Mitglieder, bei JSK-Terminen an die Jungschützen. Zusätzlich erinnert das System täglich um 09:00 gemäss der persönlichen Vorlaufzeit jedes Benutzers.</li><li><strong>Vom Vorjahr</strong> kopiert ausgewählte Termine ins gewählte Jahr: Das Datum wandert auf denselben Wochentag, Jahreszahlen und Zähler im Namen («25.» wird «26.») werden angepasst, bereits vorhandene Termine sind abgewählt.</li><li><strong>Veröffentlichen</strong> setzt einen Eintrag «Wichtige Termine aktualisiert» ins öffentliche Änderungsprotokoll.</li><li><strong>Alle Termine löschen</strong> entfernt sämtliche Termine des gewählten Jahres endgültig.</li></ul><p>Tastenkürzel: Ctrl+N öffnet «Neuer Termin».</p>',
 'wichtigetermine'),

('wichtigetermine.liste',
 'Terminliste',
 '<p>Die Termine des gewählten Jahres, nach Monat gruppiert. Ein Klick auf eine Zeile öffnet den Termin rechts (Bezeichnung, Datum, Zeit, Für Jungschützen); gespeichert wird mit <em>Speichern</em> oder Enter.</p><ul><li>Die <strong>JSK</strong>-Pille in der Zeile schaltet «Für Jungschützen» direkt um: blau = eingeschaltet.</li><li><strong>Zeit</strong> ist Freitext, empfohlen «18.00 - 20.00». Die Kalenderdatei liest daraus Beginn und Ende; steht nur eine Zeit, gilt sie als Beginn, ohne Zeitangabe wird der Termin ganztägig.</li><li>Der Papierkorb in der Zeile oder <em>Löschen</em> im Panel entfernt einen Termin endgültig.</li></ul><p>Die Zahl im Titel ist die Anzahl Termine des Jahres.</p>',
 'wichtigetermine'),

('wichtigetermine.exporte',
 'PDF und Kalenderdatei',
 '<ul><li><strong>PDF</strong>: Terminliste des gewählten Jahres. Sie enthält zusätzlich die Standbelegungs-Termine mit Kalender-Markierung, im PDF mit «(Standbelegung)» gekennzeichnet, damit die Übersicht vollständig ist.</li><li><strong>ICS</strong>: alle wichtigen Termine des Jahres als Kalenderdatei für Outlook, Google oder Apple Kalender. Beginn und Ende stammen aus dem Feld «Zeit»; die Standbelegung ist hier nicht enthalten.</li></ul><p>Beide Dateien werden bei jedem Klick neu erzeugt, der Download startet direkt.</p>',
 'wichtigetermine'),

('wichtigetermine.jsk',
 'Für Jungschützen',
 '<p>Kennzeichnet den Termin als <strong>Jungschützen-Termin</strong>.</p><ul><li>Er erscheint im Jungschützen-Portal unter «Termine» mit einem Teilnahme-Schalter (Standard: teilnehmend) und speist die Teilnehmerlisten in der <a href="jsk_verwaltung.php">JSK-Verwaltung</a>.</li><li>Jungschützen können ihn in einer Betreuungs-Anfrage als Termin auswählen.</li><li>Die Sofort-Mitteilung beim Erfassen geht an die Jungschützen statt an die Mitglieder. Die tägliche Erinnerung erhalten Mitglieder für alle Termine, Jungschützen nur für JSK-Termine.</li><li>Im Mitgliederportal bleibt der Termin sichtbar, mit dem Kennzeichen «Jungschützen».</li></ul><p>Der Schalter lässt sich jederzeit hier oder über die JSK-Pille in der Liste ändern.</p>',
 'wichtigetermine'),

-- ---------------------------------------------------------------- Gruppe E
('wanderpreise.uebersicht',
 'Wanderpreise verwalten',
 '<p>Hier werden die <strong>Wanderpreise</strong> des Vereins geführt: Bezeichnung, Hersteller, aktueller Gewinner, Anzahl bisheriger Gewinner und die Zahl der Gewinne, ab der ein Preis definitiv in den Besitz übergeht («Min. Gewinne»). Ein Klick auf eine Zeile öffnet die <strong>Historie</strong> des Preises mit allen Gewinnern nach Jahr; von dort lässt sich die Historie als PDF ausgeben.</p><ul><li>Pro Preis und Jahr gibt es <strong>höchstens einen Gewinner</strong>. Ein zweiter Eintrag im gleichen Jahr wird abgelehnt.</li><li>Gewinner werden entweder von Hand zugeordnet («Zuordnen») oder automatisch über eine Regel ermittelt («Auto-Zuordnung»). Die Regeln werden unter <a href="wanderpreise_regeln.php">Wanderpreis-Regeln</a> gepflegt.</li><li>Erreicht ein Mitglied die eingestellte Anzahl Gewinne, wird der Gewinn als <strong>definitiver Besitz</strong> markiert (siehe Hilfe beim Feld «Min. Gewinne»).</li><li>Ein Preis kann nur gelöscht werden, solange keine Gewinner zu ihm erfasst sind.</li></ul><p>Alle Listen, Berichte und Gravur-Aufträge sind im Bereich «Aktionen» zusammengefasst.</p>',
 'wanderpreise'),

('wanderpreise.aktionen',
 'Aktionen: Verwaltung, Berichte, Gravur',
 '<p><strong>Verwaltung</strong></p><ul><li><strong>Hinzufügen</strong> legt einen neuen Wanderpreis an (Bezeichnung, Beschreibung, Beschaffungsjahr, Min. Gewinne, Hersteller, optional Auto-Zuordnung).</li><li><strong>Zuordnen</strong> trägt den Gewinner eines Jahres von Hand ein, mit Rang/Resultat und Bemerkung. Wählbar sind aktive Mitglieder; das Jahr darf für diesen Preis noch keinen Gewinner haben. Unten im Fenster stehen die bisherigen Gewinner des gewählten Preises.</li><li><strong>Auto-Zuordnung</strong> ermittelt für ein Jahr die Gewinner aller Preise, bei denen die Auto-Zuordnung mit einer Regel aktiviert ist. Preise, die in diesem Jahr schon einen Gewinner haben, werden übersprungen; das Ergebnis wird als Liste je Preis angezeigt (zugeordnet, übersprungen, keine Daten, Fehler).</li><li><strong>Historie</strong> trägt vergangene Gewinner nach, z.B. aus der Zeit vor der Erfassung im System.</li></ul><p><strong>Listen &amp; Berichte</strong> fragen jeweils ein Jahr ab: <strong>CSV</strong> (Tabellenexport), <strong>PDF Alle</strong> (Jahresbericht über alle Preise), <strong>JM Preise</strong> (Bericht der drei besten Schützen), <strong>Mitglieder</strong> (kompakte Mitglieder-Information).</p><p><strong>Gravur-Aufträge</strong> erzeugen den Jahresbericht gefiltert nach Hersteller: <strong>Schnitzerei</strong> und <strong>Akura</strong> (eigenes Format als Gravur-Auftrag). Der Hersteller wird pro Preis im Feld «Hersteller» festgelegt.</p>',
 'wanderpreise'),

('wanderpreise.definitiv',
 'Min. Gewinne: definitiver Besitz',
 '<p><strong>Min. Gewinne</strong> ist die Anzahl Gewinne desselben Mitglieds, ab der der Wanderpreis <strong>definitiv</strong> in dessen Besitz übergeht. Vorgabe ist 3.</p><ul><li>Bei jeder Zuordnung (von Hand oder automatisch) zählt das System die bisherigen Gewinne dieses Mitglieds bei diesem Preis und rechnet den neuen Gewinn dazu.</li><li>Erreicht oder übersteigt die Zahl das Minimum, wird der Gewinn als <strong>definitiv</strong> gekennzeichnet und die laufende Anzahl beim Eintrag gespeichert.</li><li>Bei der Auto-Zuordnung wird in diesem Fall zusätzlich das Mitglied als Gewinner beim Preis selbst hinterlegt.</li></ul><p>Eine Änderung des Minimums wirkt auf künftige Zuordnungen; bereits gespeicherte Einträge werden nicht neu bewertet.</p>',
 'wanderpreise'),

('wanderpreise.autozuordnung',
 'Auto-Zuordnung eines Wanderpreises',
 '<p>Ist die <strong>Auto-Zuordnung</strong> aktiviert, wird der Gewinner dieses Preises beim Lauf «Auto-Zuordnung» über die gewählte <strong>Regel</strong> ermittelt.</p><ul><li><strong>Regel</strong>: eine aktive Regel aus <a href="wanderpreise_regeln.php">Wanderpreis-Regeln</a>. Ohne Regel oder mit inaktiver Regel wird der Preis beim Lauf mit einem Hinweis übersprungen.</li><li><strong>Jahr</strong>: leer oder 0 bedeutet «in jedem Jahr». Steht ein Jahr, wird der Preis nur beim Lauf für genau dieses Jahr berücksichtigt.</li><li>Endet der Regel-Code auf <strong>A</strong> oder <strong>B</strong>, wird der Regel die entsprechende Kategorie (Kat. A / Kat. B) übergeben.</li><li>Die <strong>erste Zeile</strong> des Regelergebnisses ist der Gewinner; Resultat, Rang und Bemerkung werden übernommen, wenn die Regel sie liefert. Liefert die Regel für das Jahr keine Daten, erfolgt keine Zuordnung.</li></ul><p>Preise, für die im gewählten Jahr bereits ein Gewinner erfasst ist, werden nie überschrieben.</p>',
 'wanderpreise'),

('wanderpreise_regeln.uebersicht',
 'Wanderpreis-Regeln',
 '<p>Eine <strong>Regel</strong> bestimmt per Datenbankabfrage, wer einen Wanderpreis in einem Jahr gewinnt. Regeln werden bei den <a href="wanderpreise.php">Wanderpreisen</a> als Auto-Zuordnung hinterlegt und beim Lauf «Auto-Zuordnung» ausgeführt.</p><ul><li>Die Liste zeigt <strong>Code</strong>, <strong>Name</strong>, Beschreibung und Status. Nur <strong>aktive</strong> Regeln sind bei den Wanderpreisen wählbar und werden ausgeführt; inaktive bleiben erhalten, wirken aber nicht.</li><li>Der <strong>Code</strong> ist eindeutig (Buchstaben, Zahlen, _ und -). Endet er auf A oder B, wird daraus die Kategorie abgeleitet.</li><li><strong>Testen</strong> führt die Regel für ein Jahr aus und zeigt die ersten zehn Zeilen mit Mitgliedernamen, ohne etwas zu speichern.</li><li>Jede Regel liefert die Mitglieder-Nummer des Gewinners, optional Resultat, Rang und Bemerkung. Die <strong>erste Zeile</strong> zählt, die Sortierung entscheidet also über den Sieger.</li></ul><p>Regeln sind auf lesende Abfragen beschränkt; schreibende Befehle werden beim Speichern, Testen und Ausführen abgelehnt. Regeln lassen sich <strong>geführt</strong>, als <strong>Baukasten</strong> oder im <strong>Experten-Modus</strong> mit eigenem SQL erstellen (siehe Hilfe beim Regel-Typ).</p>',
 'wanderpreise_regeln'),

('wanderpreise_regeln.typ',
 'Regel-Typ: Geführt, Baukasten, Experte',
 '<ul><li><strong>Geführt</strong>: Wettbewerb wählen (Glückstich, Kunststich, Heimmeisterschaft, Kantonalstich, Endstich, Zabigstich, Vereinscup), dazu Kategorie (Alle, Kat. A, Kat. B; der Vereinscup kennt keine Kategorie) und ob das <strong>höchste</strong> oder das <strong>niedrigste</strong> Resultat gewinnt. Die Abfrage wird automatisch erzeugt und nur zur Ansicht angezeigt.</li><li><strong>Baukasten</strong>: wie Geführt, zusätzlich eigene Bedingungen und eine eigene Sortierung (siehe Hilfe bei «Bedingungen»).</li><li><strong>Experte</strong>: eigenes SQL für Sonderfälle wie den Jahresmeister (siehe Hilfe bei «SQL-Query»). Mit «Vorlage aus bestehender Regel» wird das SQL einer anderen Regel als Startpunkt übernommen; die Regel wechselt dabei in den Experten-Modus.</li></ul><p>Bei Geführt und Baukasten werden immer automatisch das Jahr gefiltert und Leertreffer (kein erfasstes Resultat) ausgeschlossen. Bei Gleichstand entscheidet ein fester Stichentscheid je Wettbewerb (z.B. Tiefschuss beim Endstich, sonst Geburtsdatum bzw. Name). Ein Wechsel des Typs blendet die passenden Felder ein und baut die Abfrage neu.</p>',
 'wanderpreise_regeln'),

('wanderpreise_regeln.baukasten',
 'Baukasten: Bedingungen und Sortierung',
 '<p><strong>Bedingungen</strong> schränken die Teilnehmer zusätzlich ein. Jede Bedingung besteht aus Spalte, Operator und Wert:</p><ul><li><strong>Spalte</strong>: «Resultat (berechnet)» ist das Gesamtresultat des Wettbewerbs; daneben stehen die Rohspalten des Wettbewerbs (einzelne Schüsse, Passen, Tiefschuss usw.).</li><li><strong>Operator</strong>: =, !=, &lt;, &lt;=, &gt;, &gt;=, «ist leer», «ist nicht leer».</li><li><strong>Wert</strong>: eine Zahl oder der Platzhalter <code>{jahr}</code>. Text ist nicht erlaubt.</li><li>Mehrere Bedingungen sind mit <strong>UND</strong> verknüpft. Jahr und Ausschluss von Leertreffern kommen immer automatisch dazu.</li></ul><p><strong>Sortierung</strong>: Spalte und Richtung; die <strong>erste Zeile</strong> nach dieser Sortierung ist der Sieger. Ohne Angabe gilt Resultat absteigend mit dem Stichentscheid des Wettbewerbs. Die erzeugte Abfrage wird unten laufend zur Ansicht aktualisiert.</p>',
 'wanderpreise_regeln'),

('wanderpreise_regeln.sql',
 'SQL-Query im Experten-Modus',
 '<p>Die Abfrage muss eine Spalte <code>gewinner_id</code> (Mitglieder-Nummer) liefern; optional <code>resultat</code>, <code>rang</code> und <code>bemerkung</code>, die bei der Zuordnung übernommen werden. Die <strong>erste Zeile</strong> ist der Gewinner, also mit ORDER BY sortieren.</p><ul><li><strong>Platzhalter</strong>: <code>{jahr}</code> (Jahr des Laufs), <code>{kategorie}</code> (Kat. A / Kat. B oder leer, aus der Endung des Regel-Codes) und <code>{wanderpreis_id}</code>.</li><li><strong>Erlaubt</strong> sind Zuweisungen der Form <code>SET @variable = …;</code> gefolgt von genau einem SELECT oder WITH als letztem Statement.</li><li><strong>Abgelehnt</strong> werden schreibende oder verwaltende Befehle, mehrere SELECTs und ausführbare Kommentare. Die Prüfung läuft beim Speichern, beim Testen und nochmals bei jeder Ausführung.</li><li>Die Tabellen- und Spaltenreferenz unten im Panel zeigt die verfügbaren Resultat-Tabellen.</li></ul><p>Regeln, die die Jahresmeisterschaft nachrechnen (Jahresmeister Kat. A/B), enthalten die JM-Berechnung samt Hochrechnung auf 100 Punkte selbst. Ändert sich die JM-Berechnung, müssen diese Regeln nachgeführt werden.</p>',
 'wanderpreise_regeln'),

('sieger.uebersicht',
 'Sieger der letzten Jahre',
 '<p>Hier werden die <strong>Sieger pro Auszeichnung und Jahr</strong> von Hand erfasst. Sie werden nicht automatisch aus den Ranglisten übernommen.</p><ul><li>Das Jahr oben ist mit dem Vorjahr vorbelegt. Die Karten sind in zwei Gruppen geordnet: <strong>Jahresmeisterschaft</strong> (Jahresmeisterschaft, Kantonalstich, Heimmeisterschaft) und <strong>Endschiessen</strong> (alle übrigen Auszeichnungen). Innerhalb einer Karte stehen die Einträge nach Resultat absteigend.</li><li><strong>Hinzufügen</strong> öffnet das Panel: Mitglied, Auszeichnung, Resultat/Punkte (grösser als 0) und Jahr. Der Name wird beim Speichern aus dem Mitglied übernommen.</li><li>Klick auf eine Zeile oder den Stift öffnet den Eintrag zum Bearbeiten, der Papierkorb löscht ihn.</li><li>Wird in ein anderes Jahr gespeichert, wechselt die Ansicht dorthin, damit der Eintrag sichtbar bleibt.</li></ul><p>Die Sieger fliessen in die Sieger-Tabellen des <strong>Absendenbuchs</strong> ein, dort über alle Jahre nach Jahr sortiert.</p>',
 'sieger'),

('sieger.auszeichnung',
 'Auszeichnung wählen',
 '<p>Die <strong>Auszeichnung</strong> ist die Kategorie, in der das Mitglied gesiegt hat, z.B. Jahresmeisterschaft Kat. A/B, Kantonalstich Kat. A/B, Heimmeisterschaft Kat. A/B, Endschiessen Kat. A/B, Endstich, Kunst, Glück, Zabigstich oder Schwini. Jede Auszeichnung bildet auf der Seite eine eigene Karte.</p><ul><li><strong>Resultat / Punkte</strong> ist der erzielte Wert; er bestimmt die Reihenfolge innerhalb der Karte.</li><li><strong>Jahr</strong> ist das Schiessjahr, für das der Sieg gilt.</li></ul><p>Pro Auszeichnung können mehrere Einträge im gleichen Jahr stehen (z.B. mehrere Ränge); das System prüft keine Eindeutigkeit.</p>',
 'sieger'),

('mitgliederfragebogen.uebersicht',
 'Auswertung Fragebogen',
 '<p>Hier werden die Antworten des <strong>Mitglieder-Fragebogens</strong> eines Jahres erfasst und ausgewertet, eine Zeile pro Mitglied.</p><ul><li><strong>Waffe</strong>: die gemeldete Waffe oder «Nehme nicht teil». Beim Speichern wird die gewählte Waffe auch in die <strong>Mitglieder-Stammdaten</strong> übernommen (nicht bei «Nehme nicht teil»).</li><li><strong>ZSMM</strong> (Vereinsmannschaft) und <strong>GM</strong> (Gruppenmeisterschaft): Ja, Nein oder Auffüllen. Die Farben zeigen den Zustand (grün Ja, rot Nein, gelb Auffüllen).</li><li>Rechts folgt <strong>eine Spalte pro Anlass</strong>, der im Jahresprogramm die Option «Erweitert» trägt (Ja/Nein). Lange Bezeichnungen sind gekürzt, der volle Text erscheint beim Zeigen mit der Maus.</li><li>Mitglieder mit «Nehme nicht teil» sind ausgeblendet; der Knopf <strong>Nicht-Teilnehmer anzeigen</strong> blendet sie ein und zeigt ihre Anzahl.</li><li>Ohne gespeicherte Antwort gilt: Waffe «Nehme nicht teil», Mannschaft und Gruppen «Nein».</li></ul><p><strong>Speichern</strong> schreibt alle Zeilen des gewählten Jahres. Unter «Aktionen» lassen sich alle Antworten des Jahres löschen. Der leere Fragebogen zum Verteilen wird auf der Seite <a href="jmdefinition.php">Jahresmeisterschaft Definition</a> erzeugt.</p>',
 'mitgliederfragebogen'),

('mitgliederfragebogen.pdf',
 'Fragebogen als PDF und Direktdruck',
 '<p><strong>Fragebogen PDF</strong> erstellt die Auswertung des gewählten Jahres als PDF im Querformat: alle Mitglieder mit ihren Antworten als Text und eine <strong>Total-Zeile</strong> am Ende.</p><ul><li>Mannschaft: Anzahl der Antworten «Ja».</li><li>Gruppen: Anzahl der Teilnahmen, getrennt nach Kat. A und Kat. B gemäss Waffenkategorie des Mitglieds.</li><li>Je erweiterte Frage: Anzahl der Antworten «Ja».</li></ul><p>Nach dem Erstellen erscheint der Download-Link unter der Werkzeugleiste. Das Drucker-Symbol schickt das gleiche PDF direkt an den Drucker, der in der <a href="drucksteuerung.php">Drucksteuerung</a> für das Profil «Fragebogen» hinterlegt ist. Das PDF zeigt den gespeicherten Stand; ungespeicherte Änderungen vorher speichern.</p>',
 'mitgliederfragebogen'),

('monatsblatt.uebersicht',
 'Monatsblatt: Schiesszeiten als PDF',
 '<p>Das <strong>Monatsblatt</strong> ist ein PDF «Schiesszeiten» für ein Jahr und einen Monatsbereich (<strong>Von</strong> bis <strong>Bis</strong>). Liegt der Von-Monat nach dem Bis-Monat, zieht die Seite den anderen Monat mit bzw. lehnt den Export ab.</p><ul><li>Titelseite mit Logo, Zeitraum, Erstelldatum und den Initialen des angemeldeten Benutzers.</li><li>Pro <strong>Schiesstag</strong> ein Block mit Zeit, Bezeichnung, Typ und Gruppen. Die Tage stammen aus dem Feld «Schiesstage» der Anlässe im Jahresprogramm, die Zeiten aus den erfassten Schiesszeiten; ohne Zeit steht «Keine Zeit».</li><li><strong>Typ</strong>: «Gruppenschiessen» bei Anlässen mit Option Erweitert, sonst «JM A + B»; reine Info-Anlässe ohne Typ. Bei Gruppenschiessen stehen Gruppenname und Mitglieder.</li><li>Am Ende folgen die <strong>Wichtigen Termine</strong> im Zeitraum und, falls ausgefüllt, die Bemerkungen.</li></ul><p><strong>Monatsblatt PDF</strong> lädt die Datei direkt herunter, es wird nichts auf dem Server gespeichert. Das Drucker-Symbol druckt über das Profil «Monatsblatt» der <a href="drucksteuerung.php">Drucksteuerung</a>. Grundlage sind die Daten unter <a href="jmdefinition.php">Jahresmeisterschaft Definition</a> und <a href="wichtigetermine.php">Wichtige Termine</a>.</p>',
 'monatsblatt'),

('monatsblatt.bemerkungen',
 'Bemerkungen im Monatsblatt',
 '<p>Der Text erscheint als eigener Abschnitt <strong>«Bemerkungen»</strong> am Ende des Monatsblatts. Zeilenumbrüche bleiben erhalten.</p><ul><li>Bleibt das Feld leer, entfällt der Abschnitt.</li><li>Der Text wird <strong>nicht gespeichert</strong>; er gilt nur für den gerade erzeugten Export und muss beim nächsten Mal neu eingegeben werden.</li></ul>',
 'monatsblatt'),

('standbelegung.uebersicht',
 'Standbelegung',
 '<p>Hier wird der <strong>Standbelegungsplan</strong> der Schiessanlage verwaltet: die Termine pro Jahr mit Kategorie (300m, 50m, 25m, 10m, Sonstiges), Zeit und Kalender-Markierung.</p><ul><li><strong>Import</strong>: Belegungsplan aus Excel einlesen und die PDF-Fassung für die Vereinswebsite hochladen.</li><li><strong>Übersicht &amp; Export</strong>: Einträge filtern, bearbeiten, hinzufügen und löschen; Schiesstagemeldung als Excel und JSK-Termine als PDF ausgeben.</li><li><strong>Art-Erkennung</strong>: Begriffe pflegen, mit denen die Art (Feldschiessen, Obligatorisch, Jungschützenkurs …) für die Schiesstagemeldung erkannt wird.</li></ul><p>Einträge mit <strong>Kalender-Markierung</strong> erscheinen zusätzlich im PDF der <a href="wichtigetermine.php">Wichtigen Termine</a>, dort als «Standbelegung» gekennzeichnet. <strong>Veröffentlichen</strong> schreibt einen Eintrag «Standbelegung &lt;Jahr&gt; aktualisiert» in das Änderungsprotokoll für die Website; die Daten selbst sind bereits beim Speichern gesichert. Die hochgeladene PDF-Datei wird der Vereinswebsite über eine Schnittstelle bereitgestellt.</p>',
 'standbelegung'),

('standbelegung.import',
 'Import aus Excel und PDF-Upload',
 '<p>Zuerst das <strong>Jahr</strong> wählen, dem die importierten Termine zugeordnet werden.</p><ul><li><strong>Excel-Datei</strong>: der Belegungsplan mit zwei Spaltenblöcken nebeneinander (je Wochentag, Tag, Bezeichnung, Zeit) und Monatsüberschriften in der Bezeichnungsspalte. Zeilen ohne gültigen Wochentag (MO–SO) oder ohne Tageszahl werden übersprungen.</li><li>In der <strong>Vorschau</strong> lassen sich Einträge nach Kategorie filtern, an- und abwählen und mit Kalender-Markierung versehen (Standard: 300m im Kalender). Nur angewählte Einträge werden importiert.</li><li><strong>Importieren</strong>: Einträge mit gleichem Datum, gleicher Bezeichnung und gleicher Startzeit werden aktualisiert (Wochentag, Endzeit, Kategorie, Kalender), alle anderen neu angelegt. Die Meldung nennt neu, aktualisiert, unverändert und Fehler.</li><li><strong>Direkt exportieren</strong> erzeugt die Schiesstagemeldung aus der Vorschau, ohne zu speichern.</li><li><strong>PDF-Datei</strong>: die fertige Plan-PDF für die Website. Pro Jahr wird eine Datei gehalten; erneutes Hochladen ersetzt sie.</li></ul>',
 'standbelegung'),

('standbelegung.eintraege',
 'Einträge, Kalender und Exporte',
 '<p>Die Tabelle zeigt die Einträge des gewählten Jahres («Alle Jahre» möglich), eingeschränkt über Suche und <strong>Kategorie-Chips</strong> (standardmässig nur 300m aktiv).</p><ul><li>Das <strong>Kalender-Symbol</strong> in der Zeile schaltet die Kalender-Markierung sofort um; markierte Einträge erscheinen im PDF der Wichtigen Termine.</li><li>Stift = bearbeiten, Papierkorb = löschen; mit den Häkchen lassen sich mehrere Einträge auswählen und gemeinsam löschen.</li><li><strong>Hinzufügen</strong> legt einen Eintrag von Hand an: Datum, Kategorie, Bezeichnung, Von/Bis, Kalender.</li></ul><p><strong>Exporte</strong>:</p><ul><li><strong>JSK PDF</strong>: alle Einträge des Jahres, deren Bezeichnung als Art «Jungschützenkurs» erkannt wird, gruppiert nach Einschreiben, Kurstagen und Wettschiessen.</li><li><strong>Schiesstage</strong>: Schiesstagemeldung als Excel aus den gewählten Einträgen. In der Vorschau kann die <strong>Art</strong> je Eintrag korrigiert werden. Die Disziplin folgt der Kategorie (300m → G300, 50m → KK50, 25m → P25, 10m → LG10); «Anlass auf Schiessanlage» wird mit Ja vorbelegt.</li></ul>',
 'standbelegung'),

('standbelegung.art',
 'Art-Erkennung für die Schiesstagemeldung',
 '<p>Die <strong>Art</strong> eines Eintrags (SF Schützenfest, FS Feldschiessen, OP Obligatorisches Programm, WK Wettkampf/Match, JSK Jungschützenkurs, TR Training, VS Versammlung, AND Anderes) wird aus der Bezeichnung abgeleitet und in der Export-Vorschau vorgeschlagen.</p><ul><li>Zuerst gelten die hier gepflegten <strong>Keywords</strong>: enthält die Bezeichnung einen Begriff (Gross-/Kleinschreibung egal), gilt dessen Art.</li><li>Danach die <strong>Standard-Erkennung</strong> in fester Reihenfolge: Feldschiessen → FS; Bundesprogramm/Obligatorisch → OP; Jungschütz/JS-Kurs/JSK → JSK; Training → TR; Versammlung/Absenden/GV → VS. Kurze Begriffe wie «GV» zählen nur als ganzes Wort.</li><li>Trifft nichts zu, wird <strong>AND</strong> gesetzt.</li></ul><p>Keywords wirken in der Export-Vorschau und beim JSK-PDF gleich. Hinzufügen und Entfernen (×) werden sofort gespeichert; die Standard-Begriffe pro Art sind rechts neben dem Zähler zur Information angezeigt.</p>',
 'standbelegung'),

('pdf_design.uebersicht',
 'PDF-Vorlage (nur Administratoren)',
 '<p>Die <strong>PDF-Vorlage</strong> legt Farben und Grundlayout für die vom System erzeugten PDFs zentral fest. Zugriff haben nur Administratoren; der Vorstand sieht diese Seite nicht.</p><ul><li><strong>Allgemein</strong>: Text, gedämpfter Text/Fusszeile, Akzent für Titel und Totale.</li><li><strong>Tabelle</strong>: Kopf-Hintergrund, Kopf-Text, Kopf-/Fusslinie, Zellrahmen, Zebra für gerade Zeilen, Total-Hintergrund.</li><li><strong>Medaillen</strong>: Hintergrund und Text für Gold, Silber, Bronze (Ränge 1–3).</li><li><strong>Status</strong>: Gewinner im Cup, gestrichene Resultate bzw. Verlierer.</li><li><strong>Layout</strong>: Logo-Breite (40–300 px), Basis-Schriftgrösse (6–14 px), Rahmenstärke (0–4 px).</li></ul><p>Farben lassen sich als Hex-Wert tippen oder mit dem Farbwähler setzen. <strong>Speichern</strong> wirkt sofort auf alle danach erzeugten PDFs; <strong>Auf Standard zurücksetzen</strong> löscht alle Einstellungen und stellt die Vorgabe «Hell &amp; minimal» her. Ungültige Werte fallen auf die Vorgabe zurück, ein PDF scheitert nie an einer Einstellung. Einige ältere Ausdrucke verwenden feste Farben und folgen der Vorlage nicht oder nur teilweise.</p>',
 'pdf_design'),

('pdf_design.logo',
 'Zentrales Logo ersetzen',
 '<p><strong>Logo ersetzen</strong> nimmt eine PNG- oder JPEG-Datei bis 6 MB an. Im nächsten Schritt wird der <strong>Bildausschnitt</strong> frei gewählt; transparente Flächen werden weiss, das Ergebnis wird als JPEG mit höchstens 1200 px Breite gespeichert.</p><ul><li>Das neue Logo wird als zentrales Logo abgelegt <strong>und</strong> in alle Modul-Kopien verteilt. Es erscheint damit in allen PDFs mit Vereinslogo und auf der Startseite des Admin-Bereichs.</li><li>Das bisherige Logo bleibt als Sicherung erhalten.</li><li>Die <strong>Anzeigegrösse</strong> im PDF wird nicht hier, sondern unter Layout → Logo-Breite eingestellt.</li></ul><p>Die Kantonalstich-Abrechnung verwendet bewusst das SKSG-Logo und ist davon nicht betroffen.</p>',
 'pdf_design'),

('pdf_design.vorlagen',
 'Vorlagen (Presets)',
 '<p>Die Vorlagen setzen alle Werte mit einem Klick:</p><ul><li><strong>Hell &amp; minimal</strong>: die Standardwerte (heller Tabellenkopf, dezente Rahmen).</li><li><strong>Dezent markentreu</strong>: Vereinsblau als Kopfband mit weisser Kopfschrift, angepasste Rahmen-, Zebra- und Total-Farben; die übrigen Werte wie Standard.</li></ul><p>Ein Klick füllt nur die Felder und die Vorschau, gespeichert wird erst mit <strong>Speichern</strong>. Danach lassen sich einzelne Werte frei anpassen.</p>',
 'pdf_design'),

('pdf_design.vorschau',
 'Live-Vorschau',
 '<p>Die Vorschau zeigt eine <strong>Beispiel-Rangliste</strong> mit Logo, Titel, Tabelle (Ränge 1–3 in Medaillenfarben, ein gestrichenes Resultat, Total-Spalte), einer Cup-Zeile mit Gewinner und Verlierer, Rang-Badges und Fusszeile.</p><ul><li>Jede Änderung an einem Feld oder einer Vorlage wird <strong>sofort</strong> übernommen, auch ungespeichert.</li><li>Es sind Beispieldaten, keine echten Resultate.</li><li>Im erzeugten PDF sieht das Ergebnis praktisch gleich aus, da nur Volltöne und einfache Rahmen verwendet werden.</li></ul>',
 'pdf_design'),

-- ---------------------------------------------------------------- Gruppe F
('csv_schnittstelle.uebersicht',
 'CSV-Schnittstelle zur Schiessanlage',
 '<p>Die elektronische Schiessanlage liest ihre Schützenliste aus einer Textdatei. Diese Seite schreibt die Datei aus den Daten des Endschiessens: aufgenommen wird, wer im <strong>laufenden Kalenderjahr</strong> mindestens einen Stich gelöst hat (Mitglieder, Gäste und Jungschützen aus «Endschiessen lösen»).</p><ul><li><strong>Einstellungen</strong>: Ablageort der Datei und Schalter, ob die Schnittstelle in Betrieb ist.</li><li><strong>Export-Status</strong>: Vorschau, wie viele Personen je Typ exportiert würden, und wann die Datei zuletzt geschrieben wurde. <strong>Jetzt exportieren</strong> schreibt die Datei.</li></ul><p>Mitglieder behalten ihre Mitgliedernummer, Gäste und Jungschützen erhalten eine eigene fortlaufende Nummer. Der Jahrgang wird zweistellig übergeben. Die Datei ist Semikolon-getrennt und in der Zeichencodierung, die die Anlage erwartet.</p>',
 'csv_schnittstelle'),

('csv_schnittstelle.einstellungen',
 'Pfad und Aktiv-Schalter',
 '<ul><li><strong>Export aktiv</strong> kennzeichnet, ob die Schnittstelle genutzt wird. Der Export selbst wird auf dieser Seite mit «Jetzt exportieren» ausgelöst.</li><li><strong>Pfad</strong> ist der vollständige Dateipfad auf dem Server, an den die Datei geschrieben wird, inklusive Dateiname. Das Verzeichnis muss bereits existieren, es wird nicht angelegt; sonst meldet der Export einen Fehler.</li></ul><p>Die Einstellungen gelten für alle Benutzer und werden erst mit <strong>Speichern</strong> übernommen. Das Jahr lässt sich nicht wählen, es gilt immer das aktuelle Kalenderjahr.</p>',
 'csv_schnittstelle'),

('csv_schnittstelle.export',
 'Export-Status und Ausführung',
 '<p>Die vier Kästchen zeigen live aus der Datenbank, wie viele <strong>Mitglieder</strong>, <strong>Gäste</strong> und <strong>Jungschützen</strong> im aktuellen Jahr Stiche gelöst haben. Gäste mit erfasstem Geburtsdatum zählen als Jungschützen.</p><ul><li><strong>Jetzt exportieren</strong> erzeugt die Datei neu. Hat sich gegenüber der bestehenden Datei nichts geändert, bleibt sie unangetastet und die Meldung sagt das.</li><li>Darunter steht, wann die Datei zuletzt geschrieben wurde, oder dass noch keine vorhanden ist.</li></ul><p>Nach jeder neuen Stichlösung kann der Export wiederholt werden; alte Einträge bleiben in der Datei, solange die Person im Jahr Stiche hat.</p>',
 'csv_schnittstelle'),

('check_resultscsv.uebersicht',
 'Imetron-CSV prüfen',
 '<p>Hier kann eine Resultatdatei der Schiessanlage angeschaut werden, <strong>bevor</strong> sie beim Endschiessen importiert wird. Die Datei wird nur im Browser gelesen, es wird nichts hochgeladen oder gespeichert.</p><ul><li>Datei in den Rahmen ziehen oder anklicken und auswählen (nur CSV).</li><li>Die Seite erkennt jedes Programm an seiner Kopfzeile (Nummer, Titel, Datum und Zeit, Total) und sammelt die zugehörigen Schüsse mit normaler und Hunderter-Wertung. Programme mit Total 0 werden übersprungen.</li><li>Oben stehen Dateiname, Anzahl Programme und die vorkommenden Programmnummern; die Tabelle listet die Programme chronologisch. Der Pfeil rechts öffnet die Schussdetails eines Programms, unten steht das Gesamttotal.</li></ul><p>Der Leser arbeitet nach denselben Regeln wie der Endschiessen-Import. Fehlt hier ein Programm oder stimmt ein Total nicht, wird es auch beim Import so ankommen.</p>',
 'check_resultscsv'),

('check_resultscsv.debug',
 'Rohdaten der Datei',
 '<p>«Rohdaten» zeigt die ersten 50 Zeilen der Datei unverändert an. Das hilft, wenn ein Programm nicht erkannt wird: dort ist sichtbar, ob die Kopfzeile das erwartete Muster (Nummer; Titel; Datum-Zeit; … Total) hat und ob die Spalten mit Semikolon getrennt sind.</p>',
 'check_resultscsv'),

('drucksteuerung.uebersicht',
 'Drucksteuerung (Direktdruck)',
 '<p>Ranglisten, Standblätter und Listen lassen sich auf vielen Seiten mit dem Drucker-Symbol direkt drucken, ohne das PDF zu öffnen. Dafür läuft auf dem Arbeitsplatz das Programm <strong>QZ Tray</strong>, und hier wird festgelegt, welches Dokument auf welchem Drucker wie ausgegeben wird.</p><ul><li><strong>Druckprofile</strong>: pro Dokumenttyp Drucker, Format, Kopien, Duplex und Farbe. Ohne Profil ist das Drucker-Symbol auf der jeweiligen Seite gesperrt und nennt den Grund.</li><li><strong>Drucker</strong>: die am Arbeitsplatz bekannten Drucker mit Anzeigename, Typ und Aktiv-Schalter. Beim Hinzufügen wird die Liste live von QZ Tray geladen.</li><li><strong>Druckprotokoll</strong>: die zuletzt gesendeten Aufträge mit Status. «Gesendet» heisst an den Drucker übergeben; ein Fehler zeigt beim Zeigen mit der Maus die Ursache.</li></ul><p>Drucker und Profile gelten <strong>pro Benutzer und pro Arbeitsplatz</strong> (Browser). An einem anderen Rechner müssen sie erneut eingerichtet werden.</p>',
 'drucksteuerung'),

('drucksteuerung.qz',
 'Verbindung zu QZ Tray',
 '<p>Der Punkt zeigt, ob der Browser mit QZ Tray auf diesem Rechner verbunden ist. Grün: Direktdruck möglich. Rot: QZ Tray ist nicht gestartet oder nicht installiert; dann <strong>Verbinden</strong> versuchen.</p><ul><li>Die Verbindung wird beim Öffnen der Seite automatisch aufgebaut.</li><li>Das Kennzeichen <strong>Arbeitsplatz</strong> ist die Kennung dieses Browsers. Drucker und Profile sind daran gebunden.</li><li>Damit QZ Tray ohne Rückfrage druckt, muss das Zertifikat der Anwendung auf dem Rechner eingerichtet sein; sonst erscheint bei jedem Druck ein Bestätigungsfenster oder ein Signierfehler.</li></ul>',
 'drucksteuerung'),

('drucksteuerung.profile',
 'Druckprofile',
 '<p>Jede Zeile ist ein Dokumenttyp, gruppiert nach Jahresmeisterschaft, Endschiessen, Ranglisten und Einsätze. Die Gruppen lassen sich einklappen; der Zustand wird im Browser gemerkt.</p><ul><li><strong>Drucker</strong>: einer der unter «Drucker» erfassten, aktiven Drucker.</li><li><strong>Format</strong>: bei den meisten Dokumenten fest vorgegeben. Bei Ranglisten kann zwischen Hoch- und Querformat gewählt werden; die Wahl wird beim Erzeugen des PDFs berücksichtigt.</li><li><strong>Kopien</strong> (1 bis 9), <strong>Duplex</strong> (aus, lange oder kurze Seite) und <strong>Farbe</strong> (Schwarzweiss, Graustufen, Farbe). Das Absendenbuch wird als Broschüre über die kurze Seite gewendet.</li><li><strong>Testdruck</strong> (Drucker-Symbol) schickt eine Testseite mit Rahmen, Massband und Farbfeldern mit den Werten der Zeile, auch ungespeichert. Damit lässt sich prüfen, ob Format und Skalierung stimmen.</li><li><strong>Übertragen</strong> (Pfeil nach unten) kopiert Drucker, Duplex, Farbe und Kopien dieser Zeile auf alle Zeilen der Gruppe; mit gedrückter Umschalttaste auf alle Gruppen. Das Format bleibt.</li></ul><p>Änderungen werden erst mit <strong>Speichern</strong> für alle Zeilen gemeinsam übernommen.</p>',
 'drucksteuerung'),

('backup_restore.uebersicht',
 'Backup und Restore der Datenbank',
 '<p>Hier wird die Datenbank gesichert und bei Bedarf aus einer Sicherung wiederhergestellt. Eine Sicherung ist ein gepackter Datenbank-Auszug; er enthält alle Tabellen mit Struktur und Inhalt, aber <strong>nicht</strong> die hochgeladenen Dateien (Fotos, Dokumente).</p><ul><li><strong>Backup jetzt erstellen</strong> legt eine neue Sicherung auf dem Server ab; sie erscheint danach in der Liste.</li><li><strong>Restore aus Datei</strong> spielt eine Sicherung vom eigenen Rechner ein, die Liste erlaubt dasselbe für eine Sicherung auf dem Server.</li><li>Zusätzlich sichert der Server jede Nacht automatisch. Es bleiben die neuesten Stände, ältere werden aufgeräumt; wer einen Stand dauerhaft aufheben will, lädt ihn herunter.</li></ul><p>Die Sicherungen liegen in einem Ordner, der über den Browser nicht erreichbar ist. Vor grösseren Änderungen (Migrationen, Aufräumaktionen) lohnt sich eine manuelle Sicherung.</p>',
 'backup_restore'),

('backup_restore.backup',
 'Sicherung erstellen',
 '<p><strong>Backup jetzt erstellen</strong> zieht einen vollständigen Auszug der Datenbank, packt ihn und legt ihn mit Datum und Uhrzeit im Namen ab. Der Vorgang dauert bei der aktuellen Datenbankgrösse nur Sekunden.</p><ul><li>Schlägt der Auszug fehl oder ist er leer, wird nichts gespeichert und eine Fehlermeldung mit Details angezeigt.</li><li><strong>Aktualisieren</strong> lädt die Liste neu, etwa nach der nächtlichen automatischen Sicherung.</li></ul>',
 'backup_restore'),

('backup_restore.restore',
 'Wiederherstellen',
 '<p>Ein Restore ersetzt die <strong>gesamte</strong> Datenbank durch den Stand der Sicherung. Alles, was seit dieser Sicherung erfasst wurde, geht verloren; der Vorgang lässt sich nicht rückgängig machen.</p><ul><li>Quelle ist entweder eine Datei vom eigenen Rechner (gepackt oder ungepackt) oder eine Sicherung aus der Liste (Pfeil-Symbol).</li><li>Vor dem Start erscheint eine Sicherheitsabfrage. Der Vorgang kann einige Minuten dauern; das Fenster in dieser Zeit nicht schliessen.</li><li>Empfehlung: unmittelbar vorher eine frische Sicherung erstellen, damit der aktuelle Stand zurückgeholt werden kann.</li></ul><p>Bei einem Fehler zeigt das Fenster die Meldung und unter «Technische Details» die Ausgabe des Servers.</p>',
 'backup_restore'),

('backup_restore.liste',
 'Verfügbare Sicherungen',
 '<p>Alle Sicherungen auf dem Server, neueste zuerst, mit Grösse und Erstellzeit. Manuelle und nächtliche Sicherungen stehen in derselben Liste.</p><ul><li><strong>Herunterladen</strong> speichert die gepackte Datei lokal, etwa zur Aufbewahrung oder für einen späteren Restore aus Datei.</li><li><strong>Wiederherstellen</strong> spielt genau diesen Stand ein (siehe Hilfe bei «Restore»).</li><li><strong>Löschen</strong> entfernt die Datei nach Bestätigung endgültig vom Server.</li></ul>',
 'backup_restore'),

('changelog.uebersicht',
 'Changelog',
 '<p>Die Liste aller Programmänderungen, neueste Version zuoberst. Jede Version hat eine Nummer und ein Datum; die Einträge tragen einen Typ (<strong>Feature</strong>, <strong>Fix</strong>, <strong>Verbesserung</strong>, <strong>Info</strong>).</p><ul><li>Einträge mit der Marke <strong>Intern</strong> betreffen den Admin-Bereich und sind für normale Mitglieder im Portal nicht sichtbar. Vorstand und Administratoren sehen sie hier und im Portal.</li><li>Änderungen, die nur den Admin-Bereich betreffen, erscheinen ausschliesslich auf dieser Seite, nie im Portal.</li><li>Änderungen für alle Mitglieder erscheinen zusätzlich im Portal unter «Neuigkeiten» und werden dort beim nächsten Besuch einmalig als «Was ist neu?» eingeblendet.</li></ul><p>Die Einträge werden mit jeder Programmänderung von der Entwicklung nachgeführt; auf dieser Seite wird nichts bearbeitet.</p>',
 'changelog'),

('munitionskauf.uebersicht',
 'Munitionskauf erfassen',
 '<p>Hier werden Munitionsbezüge am Stand erfasst und ausgewertet. Links das Formular, rechts die Bezüge des gewählten Zeitraums; auf dem Handy liegen die Bezüge in einem eigenen Reiter mit Suchfeld.</p><ul><li><strong>Erfassung</strong>: Jahr, Kaufdatum (höchstens heute), optional ein Anlass als Freitext, dann <strong>entweder</strong> ein Mitglied aus der Liste <strong>oder</strong> ein Gastname, nie beides. Munition wählen, Total prüfen, speichern.</li><li><strong>Vergangene Bezüge</strong>: Liste mit Filter Heute / Woche / Monat / Jahr, Summen unten, Löschen pro Zeile, PDF und Direktdruck.</li><li><strong>Statistiken</strong>: Umsatz, Top-Käufer und Munitionsverbrauch des gewählten Jahres.</li></ul><p>Der Preis beträgt einheitlich <strong>50 Rappen pro Schuss</strong> und wird beim Speichern festgehalten. Ein Bezug lässt sich nachträglich nicht ändern, nur löschen und neu erfassen.</p>',
 'munitionskauf'),

('munitionskauf.munition',
 'Munition wählen',
 '<p>Zwei Wege, die sich kombinieren lassen:</p><ul><li><strong>Standard-Pakete</strong>: 60 Schuss GP11 (CHF 30) und 50 Schuss GP90 (CHF 25) per Häkchen.</li><li><strong>Individuelle Anzahl</strong>: beliebige Schusszahl je Sorte (bis 500), der Preis wird daneben angezeigt.</li></ul><p>Die Zeile unten summiert GP11, GP90 und den Betrag laufend. Gespeichert werden die Anzahl je Sorte und der Betrag; der Pfeil-Knopf setzt das Formular zurück.</p>',
 'munitionskauf'),

('munitionskauf.bezuege',
 'Vergangene Bezüge',
 '<p>Zeigt die Bezüge des oben gewählten <strong>Jahres</strong>, eingeschränkt auf den aktiven Zeitraum: <strong>Heute</strong>, <strong>Woche</strong>, <strong>Monat</strong> oder <strong>Jahr</strong>. Die Fusszeile summiert Schüsse und Betrag der angezeigten Zeilen.</p><ul><li><strong>Löschen</strong> (Papierkorb) entfernt einen Bezug nach Bestätigung endgültig; Statistiken und Summen passen sich sofort an.</li><li><strong>PDF</strong> erstellt die Liste mit demselben Zeitraum-Filter als Datei.</li><li>Das <strong>Drucker-Symbol</strong> schickt dieselbe Liste direkt an den Drucker, der in der Drucksteuerung für «Munitionskauf Liste» hinterlegt ist.</li></ul>',
 'munitionskauf'),

('munitionskauf.statistiken',
 'Statistiken',
 '<p>Alle Zahlen beziehen sich auf das im Formular gewählte Jahr.</p><ul><li><strong>Umsatz</strong>: Beträge für heute, diese Woche, diesen Monat und das ganze Jahr.</li><li><strong>Top Käufer</strong>: die fünf Personen mit dem höchsten Jahresbetrag, Gäste nach Name.</li><li><strong>Munitionsverbrauch</strong>: Schusszahl und Betrag je Sorte für das Jahr.</li></ul><p>Die Werte werden aus den erfassten Bezügen gerechnet; gelöschte Bezüge zählen nicht mehr.</p>',
 'munitionskauf'),

('home.uebersicht',
 'Startseite',
 '<p>Die Startseite fasst zusammen, was gerade anliegt, und führt zu den Bereichen, die in der laufenden Saisonphase gebraucht werden.</p><ul><li><strong>Das wartet auf dich</strong> (eingeklappt): offene Punkte quer durch die Anwendung, etwa noch nicht erfasste Anlässe, abgelaufene Umfragen, Einsatzpläne kurz vor dem Termin mit offenen Positionen oder noch nicht freigegeben, offene Einsatztausche, wartende Portal-Anmeldungen und Fotos zur Freigabe; für Administratoren zusätzlich ausstehende Datenbank-Aktualisierungen. Ist nichts offen, steht eine grüne Zeile.</li><li><strong>Nächste Termine</strong> aus Jahresprogramm und wichtigen Terminen, <strong>Vereinsjubiläen</strong> des Jahres (Vielfache von 5 Jahren) und <strong>Geburtstage</strong> der nächsten 90 Tage, runde hervorgehoben.</li><li>Darunter die Kacheln zu den Bereichen in zwei Zonen, siehe Hilfe bei «Jetzt aktuell».</li></ul>',
 'home'),

('home.zonen',
 'Kacheln nach Saisonphase',
 '<p>Die Kacheln werden nicht ausgeblendet, sondern einsortiert: <strong>Jetzt aktuell</strong> zeigt, was in der laufenden Phase gebraucht wird, <strong>Weitere Bereiche</strong> (aufklappbar) den Rest mit einem Status wie «ab 15.11.2026» oder «abgeschlossen».</p><ul><li>Dauerhaft oben: Munitionsverkauf, Heimmeisterschaft, Kantonalstich, Mitgliederverwaltung und Portal.</li><li>Saisonal: <strong>Jahresmeisterschaft</strong> ab einer Woche vor dem ersten Schiesstag und solange noch Anlässe zu erfassen sind. <strong>Endschiessen Stichausgabe</strong> ab 30 Tagen vor dem Endstich bis eine Woche danach, <strong>Endschiessen</strong> und <strong>Rangliste</strong> bis eine Woche nach dem Absenden. <strong>CUP</strong> nur am Cup-Tag, bis Finalresultate vorliegen. <strong>Einsatzplanung</strong> ab sechs Wochen vor dem nächsten Arbeitseinsatz. <strong>Anlässe</strong> und <strong>Wichtige Termine</strong> zur Saisonvorbereitung nach dem Absenden für das Folgejahr.</li></ul><p>Die Stichtage stammen aus dem Jahresprogramm (Schiesstage der Anlässe mit den Bezeichnungen Endstich, Absenden, Vereinscup, Feldschiessen), aus den Einsatzplänen und den wichtigen Terminen. Fehlt ein Datum, bleibt die Kachel sichtbar mit dem Hinweis «kein Datum hinterlegt», damit keine Funktion unerreichbar wird.</p>',
 'home'),

('aktualisierung.uebersicht',
 'Datenbank aktualisieren',
 '<p>Neue Funktionen brauchen oft Änderungen an der Datenbank (neue Tabellen, Spalten, Hilfetexte). Diese Änderungen werden als nummerierte Migrationsdateien mitgeliefert und hier ausgeführt. Nur Administratoren haben Zugriff.</p><ul><li><strong>Ausstehend</strong>: Dateien, die auf dem Server liegen, aber noch nicht ausgeführt wurden. <strong>Ausstehende ausführen</strong> wendet sie in Reihenfolge an.</li><li><strong>Bereits angewendet</strong>: alles, was schon eingespielt ist; jede ausgeführte Datei wird in der Datenbank vermerkt und nie ein zweites Mal ausgeführt.</li></ul><p>Migrationen verändern die Datenbankstruktur und sind nicht automatisch umkehrbar. Vorher eine Sicherung unter <a href="backup_restore.php">Backup &amp; Restore</a> erstellen. Die Startseite meldet Administratoren unter «Das wartet auf dich», wenn Migrationen ausstehen; die Changelog-Einträge nennen, welche Nummern eine Funktion braucht.</p>',
 'aktualisierung'),

('aktualisierung.baseline',
 'Erstmalige Einrichtung (Baseline)',
 '<p>Dieser Block erscheint nur, wenn noch keine einzige Migration vermerkt ist, die Datenbank aber bereits Daten enthält. Das ist der Fall bei einer bestehenden Installation, deren Änderungen früher von Hand eingespielt wurden.</p><ul><li><strong>Baseline</strong> markiert alle aktuell vorhandenen Dateien als angewendet, <strong>ohne</strong> sie auszuführen. Ein Ausführen würde vorhandene Tabellen und Spalten erneut anlegen und mit Fehlern abbrechen.</li><li>Danach verschwindet der Block, und nur neu hinzukommende Dateien gelten als ausstehend.</li></ul><p>Nur setzen, wenn sicher ist, dass die bestehenden Migrationen tatsächlich schon in der Datenbank sind. Bei einer leeren, neuen Datenbank stattdessen die Migrationen normal ausführen.</p>',
 'aktualisierung'),

('aktualisierung.ausstehend',
 'Ausstehende Migrationen ausführen',
 '<p><strong>Ausstehende ausführen</strong> wendet alle gelisteten Dateien nacheinander in Nummernreihenfolge an. Nach einer Bestätigung läuft der Vorgang, das Ergebnis erscheint oben auf der Seite.</p><ul><li>Grün: die erfolgreich angewendeten Dateien; sie wandern nach «Bereits angewendet».</li><li>Rot: der Fehler, bei dem der Lauf <strong>abgebrochen</strong> wurde. Die fehlerhafte Datei und alle folgenden bleiben ausstehend; bereits erfolgreiche Dateien davor sind vermerkt.</li></ul><p>Nach einem Fehler zuerst die Meldung prüfen (meist eine Spalte, die schon existiert, oder ein Tippfehler in der Datei), die Datei korrigieren lassen und den Lauf wiederholen. Der Knopf fehlt, solange die Baseline noch nicht gesetzt ist.</p>',
 'aktualisierung'),

('nav_admin.uebersicht',
 'Navigation verwalten',
 '<p>Hier wird das Hauptmenü des Admin-Bereichs gepflegt, das oben als Leiste oder links als Seitenleiste erscheint. Einträge der Hauptebene sind die Menütitel, ihre Unterpunkte die aufklappbaren Einträge; bis zu drei Ebenen sind möglich.</p><ul><li><strong>Neuer Eintrag</strong> legt Titel, Link, Icon und übergeordneten Punkt an. Ein Klick auf eine Zeile öffnet sie rechts zum Bearbeiten; die Änderungen werden beim Schliessen des Panels gespeichert.</li><li>Reihenfolge und Zuordnung ändern sich per Ziehen am Griff oder mit den Pfeilen «Ebene höher / tiefer». Solche Verschiebungen werden gesammelt (gelbe Marke «Ungespeicherte Änderungen») und erst mit <strong>Alles speichern</strong> übernommen; <strong>Aktualisieren</strong> lädt nach Rückfrage den gespeicherten Stand neu und verwirft sie.</li><li><strong>Duplizieren</strong> kopiert einen Eintrag, <strong>Löschen</strong> geht nur bei Einträgen ohne Unterpunkte.</li></ul><p>Änderungen wirken sofort im Menü aller Benutzer. Der Seitentitel jeder Seite soll dem Menütext entsprechen.</p>',
 'nav_admin'),

('nav_admin.struktur',
 'Navigationsstruktur',
 '<p>Die Tabelle zeigt den Menübaum mit Einrückung. Einträge der Hauptebene mit Unterpunkten lassen sich mit dem Pfeil einklappen; der Zustand wird im Browser gemerkt, beim ersten Aufruf sind alle eingeklappt. «Alle ein-/ausklappen» in der Werkzeugleiste gilt für alle.</p><ul><li>Ziehen am Griff verschiebt einen Eintrag <strong>mit</strong> seinen Unterpunkten; über einem eingeklappten Hauptpunkt öffnet sich dieser beim Darüberziehen.</li><li><strong>Trennlinien</strong> sind besondere Einträge ohne Titel und Link; sie erscheinen im Menü als Strich zwischen Unterpunkten (auf der Hauptebene der Leiste nicht).</li><li>Hat ein Hauptpunkt mit Unterpunkten selbst einen Link, erscheint dieser im Menü als erster Unterpunkt.</li></ul>',
 'nav_admin'),

('nav_admin.link',
 'Link / Datei',
 '<p>Ziel des Menüeintrags. Drei Schreibweisen:</p><ul><li>Nur Dateiname, z.B. <code>home.php</code>: eine Seite des Admin-Bereichs.</li><li>Pfad mit führendem Schrägstrich, z.B. <code>/portal/dashboard.php</code>: eine Seite an anderer Stelle der Anwendung.</li><li>Vollständige Adresse mit <code>http</code>: externe Seite, wird in einem neuen Tab geöffnet.</li></ul><p>Titel und Link sind Pflicht, ausser bei Trennlinien. Der Link wird nicht geprüft; eine falsch geschriebene Datei führt im Menü auf eine Fehlerseite.</p>',
 'nav_admin'),

('nav_admin.ebene',
 'Ebene verschieben',
 '<p><strong>Höher</strong> macht den Eintrag zum Nachbarn seines bisherigen Elternpunkts, <strong>Tiefer</strong> ordnet ihn dem darüberstehenden Eintrag als Unterpunkt zu. Unterpunkte wandern mit. Möglich sind Ebene 0 (Hauptmenü) bis Ebene 3.</p><p>Wie das Ziehen ist dies eine Strukturänderung: sie wird erst mit <strong>Alles speichern</strong> in der Werkzeugleiste übernommen, die Felder im Panel dagegen beim Schliessen.</p>',
 'nav_admin')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
