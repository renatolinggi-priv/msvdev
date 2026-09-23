-- Migration 067: Hilfetexte – erste Inhalte (Pilot: Hilfetexte, JM-Definition, Endschiessen lösen,
-- Einsatzplanung, Cup, Kantonalstich). Schlüssel = data-help-Attribut im Markup der jeweiligen Seite.
--
-- Idempotent: ON DUPLICATE KEY UPDATE, d.h. die Migration ist die Quelle der Wahrheit für diese Texte;
-- Korrekturen im Editor (inc/hilfetexte.php) werden beim nächsten Lauf einer neuen Migration wieder
-- eingespielt und sollten deshalb hier nachgeführt werden.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES

-- ---------------------------------------------------------------- Hilfetexte selbst
('hilfetexte.uebersicht',
 'Hilfetexte verwalten',
 '<p>Jedes Fragezeichen im Admin-Bereich zeigt einen Text aus dieser Liste. <strong>Zeigen mit der Maus</strong> auf ein Fragezeichen öffnet eine Kurzansicht, <strong>Klick</strong> die volle Ansicht.</p><ul><li><strong>Schlüssel</strong> verbindet Text und Knopf: im Code steht <code>data-help="seite.thema"</code>, hier der gleiche Schlüssel. Konvention: Seitenname, Punkt, Thema.</li><li><strong>Kategorie</strong> ist nur die Gruppierung in dieser Liste, in der Regel der Seitenname.</li><li><strong>Code-Scan</strong> durchsucht den Programmcode nach Fragezeichen und zeigt, welche noch keinen Text haben (anklicken legt den Eintrag an) und welche Texte nirgends mehr verwendet werden.</li></ul><p>Bearbeiten können nur Administratoren. Erlaubt sind Absätze, Listen, Fett/Kursiv, Zwischentitel, Code und Links; alles andere wird beim Speichern entfernt.</p>',
 'hilfetexte'),

-- ---------------------------------------------------------------- JM-Definition
('jmdefinition.uebersicht',
 'Jahresmeisterschaft definieren',
 '<p>Hier wird das <strong>Jahresprogramm</strong> eines Jahres gepflegt: alle Anlässe mit Adresse, Schiesstagen, Maximalpunkten und den Optionen, die bestimmen, wie ein Anlass in der Jahresmeisterschaft zählt. Die Reihenfolge lässt sich per Griff links ziehen oder mit «Nach Datum sortieren» setzen.</p><ul><li><strong>Neuer Anlass</strong> legt einen Eintrag an, <strong>Vom Vorjahr übernehmen</strong> kopiert das ganze Programm eines früheren Jahres als Ausgangslage.</li><li><strong>Infotext zur JM</strong> erscheint im Jahresprogramm-PDF; der Platzhalter <code>{anzahl_streicher}</code> wird durch die eingestellte Zahl ersetzt.</li><li><strong>Anzahl Streicher</strong> legt fest, wie viele der schlechtesten Streicher-Anlässe pro Schütze nicht zählen.</li><li><strong>Jahresprogramm</strong> als PDF (fertig oder als Entwurf), Fragebogen als Word und alle Schiesstage als Kalenderdatei (ICS).</li></ul><p>Änderungen an Bezeichnung, Punkten und Optionen wirken sofort auf die Ranglisten des Jahres.</p>',
 'jmdefinition'),

('jmdefinition.stiche',
 'Spalten der Anlass-Tabelle',
 '<p>Ein Klick auf eine Zeile öffnet den Anlass rechts zum Bearbeiten.</p><ul><li><strong>Nr.</strong> ist die Reihenfolge im Jahresprogramm (ziehen am Griff-Symbol).</li><li><strong>Bezeichnung</strong> ist zugleich der Name des Stichs in Ranglisten, Resultatbuch und Portal. Die Bezeichnungen <em>Einzelwettschiessen</em>, <em>Obligatorisch</em> und <em>Feldschiessen</em> haben eine Sonderregel: sie werden nie auf 100 Punkte hochgerechnet.</li><li><strong>Schiesstage</strong> als Datumsliste; daraus entstehen Kalenderdatei, Termin-Erinnerungen und die Zuordnung von Fotos zum Anlass.</li><li><strong>Max</strong> ist das höchstmögliche Resultat des Stichs, siehe Hilfe beim Feld «Max. Punkte».</li><li><strong>Optionen</strong> zeigen Streicher, Erweitert, Info und Gruppenwettkampf als Kürzel.</li></ul>',
 'jmdefinition'),

('jmdefinition.punkte',
 'Maximalpunkte und Zuschlag',
 '<p><strong>Max. Punkte</strong> steuert, wie ein Resultat in der Jahresmeisterschaft gewertet wird:</p><ul><li>Liegt das Maximum <strong>unter 100</strong>, wird jedes Resultat auf 100 hochgerechnet (z.B. 87 von 90 Punkten ergibt 96.67). In den PDF-Ranglisten stehen solche Werte mit zwei Nachkommastellen.</li><li>Ist das Maximum <strong>100 oder höher</strong> (z.B. 120), zählt das Resultat unverändert.</li><li>Teilnahme-Stiche mit Maximum 20 werden bewusst mit 5 multipliziert.</li><li>Nie hochgerechnet werden <em>Einzelwettschiessen</em>, <em>Obligatorisch</em> und <em>Feldschiessen</em>.</li></ul><p>Der <strong>Zuschlag</strong> (Beteiligungszuschlag) wird zum erzielten Resultat addiert und belohnt das Mitmachen an auswärtigen Schiessen (z.B. Schlossturm 2, Rossberg 1). Er wird beim Übernehmen vom Vorjahr mitkopiert.</p><p>Die Hochrechnung steckt gleich in der Bildschirm-Rangliste, den PDF-Ranglisten, dem Resultatbuch, dem Portal und den Wanderpreis-Regeln. Eine Änderung hier wirkt überall.</p>',
 'jmdefinition'),

('jmdefinition.optionen',
 'Optionen eines Anlasses',
 '<ul><li><strong>Streicher</strong>: der Anlass gehört zum Streicher-Pool. Von allen <em>durchgeführten</em> Streicher-Anlässen zählen pro Schütze die besten, die schlechtesten «Anzahl Streicher» fallen weg. Ein nicht geschossener Streicher-Anlass zählt als 0 und wird so in der Regel gestrichen. Noch nicht durchgeführte Anlässe zählen weder als Resultat noch als Streicher.</li><li><strong>Erweitert JM</strong>: der Anlass steht im Jahresprogramm, fliesst aber <em>nicht</em> in die JM-Rangliste ein (erweitertes Programm).</li><li><strong>Info</strong>: reiner Informationseintrag ohne Resultate, z.B. Generalversammlung. Erscheint einzeilig im Jahresprogramm und nicht in Ranglisten.</li><li><strong>Gruppenwettkampf</strong>: zum Anlass gehören Gruppen, die unter «Gruppenschiessen» zusammengestellt werden.</li></ul>',
 'jmdefinition'),

-- ---------------------------------------------------------------- Endschiessen lösen
('endschloesen.uebersicht',
 'Endschiessen: Stiche lösen',
 '<p>Hier wird erfasst, wer am Endschiessen welche Stiche löst und wie bezahlt wird. Links das Formular, rechts die Übersicht aller Lösungen des gewählten Jahres mit Standblatt, Bearbeiten und Löschen.</p><ul><li><strong>Ablauf</strong>: Teilnehmertyp wählen, Person bestimmen, Waffe prüfen, Stiche anklicken, Zahlungsart setzen, speichern. Der Preis wird vom Server nach den hinterlegten Regeln berechnet, die Anzeige im Formular ist eine Vorschau.</li><li><strong>Waffe</strong> gilt für alle Stiche einer Person im Jahr. Bei Mitgliedern wird sie aus den Stammdaten vorbelegt, Gäste und Jungschützen starten mit Stgw 90. Die Stammdaten werden dabei nicht verändert.</li><li><strong>Standblatt</strong> kann pro Lösung als PDF erzeugt oder direkt gedruckt werden.</li></ul><p>Stich-Definitionen und Spezialpreise pflegt der Vorstand über den Knopf <strong>«Definition»</strong> (Zahnrad) in der Übersicht rechts.</p>',
 'endschloesen'),

('endschloesen.teilnehmer',
 'Teilnehmertyp: Mitglied, Gast, Jungschütze',
 '<p>Der Umschalter bestimmt Preisregel und Speicherort:</p><ul><li><strong>Mitglied</strong>: aus der Mitgliederliste wählen. Jeder Stich kostet seinen Einzelpreis, der Probestich ist gratis. Beim Zabig-Stich kann ein Partner gewählt werden, dann gilt der Partnerpreis.</li><li><strong>Gast</strong>: Name eingeben (bestehende Gäste werden vorgeschlagen). Für Gäste sind nur die Gast-Stiche zugelassen. Ein Stich kostet den Einzelpreis, zwei den Kombi-Preis 2, drei oder mehr den Kombi-Preis 3; «Sie und Er» kommt dazu.</li><li><strong>Jungschütze</strong>: Name und Geburtsdatum. Jungschützen lösen ein festes Paket zum Jungschützen-Paketpreis.</li></ul><p>Das Jahr oben rechts steuert, für welches Endschiessen erfasst wird.</p>',
 'endschloesen'),

('endschloesen.stiche',
 'Stiche wählen',
 '<p>Jede Kachel ist ein Stich des Endschiessens mit Schusszahl und Preis. Angeklickte Stiche sind gelöst; «Alle» wählt alle für den Teilnehmertyp zugelassenen Stiche.</p><ul><li>Welche Stiche für Gäste und Jungschützen überhaupt wählbar sind, ist in den Regeln festgelegt; nicht zugelassene Kacheln sind ausgeblendet oder gesperrt.</li><li>Der Totalpreis unten ist eine Vorschau, verbindlich rechnet der Server beim Speichern.</li><li><strong>Zusätzliche Munition</strong> (aufklappbar) wird pro Paket zum Preis pro Schuss dazugerechnet.</li></ul><p>Stiche, Preise und Reihenfolge der Kacheln stammen aus der Endschiessen-Definition (Knopf «Definition»).</p>',
 'endschloesen'),

('endschloesen.definition',
 'Stich-Definitionen',
 '<p>Die Liste der Stiche des Endschiessens. Sie gilt jahresübergreifend und steuert die Kacheln im Formular, das Standblatt und die Abrechnung.</p><ul><li><strong>Code</strong> ist der technische Schlüssel (z.B. <code>END</code>, <code>ZABIG</code>, <code>PROBE</code>). Die Preisregeln für Gäste und Jungschützen hängen an diesen Codes; bestehende Codes deshalb nicht umbenennen.</li><li><strong>Name</strong>, <strong>Schuss</strong> und <strong>Preis</strong> erscheinen auf Kachel und Standblatt.</li><li><strong>Sortierung</strong> ordnet die Kacheln, <strong>Aktiv</strong> blendet einen Stich aus, ohne alte Lösungen zu verlieren.</li></ul><p>Ein neuer Stich ist sofort im Formular sichtbar.</p>',
 'endschloesen'),

('endschloesen.spezialpreise',
 'Spezialpreise',
 '<p>Pauschalen, die statt der Einzelpreise gelten:</p><ul><li><strong>Gast Kombi 2</strong> und <strong>Gast Kombi 3</strong>: Preis für zwei bzw. drei und mehr Stiche eines Gasts.</li><li><strong>Sie und Er</strong>: Zusatz für den gleichnamigen Gast-Stich.</li><li><strong>Partner Zabig</strong>: Preis des Zabig-Stichs, wenn ein Mitglied einen Partner angibt.</li><li><strong>Jungschützen-Paket</strong>: Preis des festen Pakets; 0 bedeutet gratis.</li></ul><p>Die Werte werden beim Speichern einer Lösung serverseitig angewendet. Bestehende Lösungen behalten ihren gespeicherten Preis.</p>',
 'endschloesen'),

-- ---------------------------------------------------------------- Einsatzplanung
('einsatzplanung.liste',
 'Einsatzpläne',
 '<p>Ein Einsatzplan ist die Helferliste eines Anlasses (Obligatorisch, Feldschiessen, Wiler Chilbi, Schlossturmschiessen) mit Terminen bzw. Schichten, Funktionen und Positionen. Die Liste zeigt alle Pläne des gewählten Jahres.</p><ul><li><strong>Status</strong>: <em>Entwurf</em> ist nur hier sichtbar. <em>Freigegeben</em> überträgt die Einsätze der MSV-Mitglieder ins Portal («Meine Einsätze», Tausch, Erinnerungen). <em>Final</em> erklärt den Plan als definitiv (alle Namen komplett) und aktualisiert die Einsätze im Portal ein letztes Mal.</li><li><strong>Neuer Plan</strong> startet leer, aus einem früheren Plan gleichen Typs (Kopie mit Personen als Endstand nach allfälligen Tauschen) oder aus einem vorhandenen Dokument.</li><li><strong>Auswertung</strong> zeigt die Anwesenheit des Jahres je Verein und Mitglied über alle freigegebenen Pläne.</li></ul>',
 'einsatzplanung'),

('einsatzplanung.editor',
 'Einsatzplan bearbeiten',
 '<p>Das Raster zeigt Funktionen (Zeilen) mal Termine bzw. Schichten (Spalten). Jede Position ist ein Chip: Person aus der Liste rechts hineinziehen oder Chip anklicken und im Panel bearbeiten. Farben stehen für den Verein (MSV Wilen rot, SV Freienbach blau, SV Wollerau grün); leere Positionen der anderen Vereine erscheinen im Word als Platzhalter mit dem Vereinsnamen.</p><ul><li><strong>Termine / Funktionen / Positionen je Termin</strong>: Struktur des Plans. Bei der Chilbi gilt statt fester Positionen ein Soll je Schicht.</li><li><strong>Verfügbarkeiten</strong> sammelt, wer wann in welcher Rolle kann (aus Umfrage, Excel-Personalanfrage oder manuell). <strong>Einteilen</strong> macht daraus Vorschläge (blau gestrichelt), die einzeln oder gesamthaft übernommen oder verworfen werden.</li><li><strong>Rückmeldung</strong> liest das von einem Verein ausgefüllte Word wieder ein: die Namen landen in den Positionen, an denen der Platzhalter stand.</li><li><strong>Export / Drucken</strong>: Word (Ansicht «Funktionen» oder «Personen»), PDF, Excel; das PDF kann direkt als Dokument fürs Portal abgelegt werden.</li><li><strong>OK-Mitglieder</strong> (nur Schlossturm): ein Häkchen pro Person gilt für alle ihre Positionen; im Word steht dann «OK» statt «X», in der Abrechnung zählen sie separat.</li><li><strong>Anwesenheit</strong> wird pro Schicht erfasst (auch mobil im Portal) und in der Auswertung sowie der Helferabrechnung berücksichtigt.</li></ul><p>Änderungen an Chips werden sofort gespeichert; bei freigegebenen Plänen wandern sie direkt ins Portal.</p>',
 'einsatzplanung'),

('einsatzplanung.abrechnung',
 'Helferabrechnung (Schlossturm)',
 '<p>Rechnet die Helferstunden des Plans pro Verein aus den besetzten Positionen: Stunden je Position = <strong>Pauschale</strong> der Schicht oder, wenn keine gesetzt ist, die Schichtdauer.</p><ul><li><strong>Abrechnung</strong>: Summe je Verein und je Person. Wer als «nicht da» erfasst ist, zählt nicht; offene Anwesenheit zählt.</li><li><strong>Ansätze</strong>: Pauschale je Schicht in Stunden (leer = Schichtdauer).</li><li><strong>Manuelle Zeilen</strong>: Vor- und Nacharbeiten, Nachträge und OK-Funktionen, die nicht im Raster stehen; «Aus Vorjahr übernehmen» kopiert Vor-/Nacharbeiten des letzten Plans.</li><li><strong>Detail</strong>: jede Zuteilung einzeln mit Korrekturmöglichkeit der Stunden.</li><li><strong>OK-Einsätze mitzählen</strong>: Positionen von OK-Mitgliedern werden standardmässig separat geführt und nur mit diesem Schalter in die Vereinssummen gerechnet.</li></ul><p><strong>Excel</strong> füllt die Vorlage des Vereins mit Formeln (Excel rechnet beim Öffnen), <strong>PDF</strong> gibt die Zusammenfassung quer aus. Warnungen erscheinen, wenn eine Person in mehreren Vereinen geführt wird oder der Verein im Original unklar war.</p>',
 'einsatzplanung'),

-- ---------------------------------------------------------------- Cup
('cup.uebersicht',
 'Vereinscup erfassen',
 '<p>Der Cup läuft in drei Stufen: <strong>Runde 1</strong>, <strong>Runde 2</strong>, <strong>Finale</strong>. Oben zeigt der Fortschrittsbalken, wie viele Paarungen je Runde erfasst sind.</p><ul><li><strong>Generieren</strong> zieht aus den Teilnehmern die gewünschte Anzahl Paarungen in der gewählten Grösse (Zweier- oder Dreiergruppen). Teilnehmer lassen sich danach per Ziehen umsortieren.</li><li>Resultate direkt in die Karten eintragen; <strong>Speichern</strong> sichert alle Runden auf einmal. Bei Gleichstand wird die Paarung markiert und der Gewinner per Klick auf den Namen bestimmt.</li><li><strong>PDF</strong> erzeugt die Cup-Rangliste, <strong>Löschen</strong> entfernt die Paarungen des gewählten Jahres.</li></ul><p>Runde 2 wird aus den Gewinnern von Runde 1 gebildet, das Finale aus den Gewinnern von Runde 2. Der Standcup-Final unten ist ein eigener Block für die drei Vereine.</p>',
 'cup'),

('cup.runden',
 'Runde 1 und Dreiergruppen',
 '<p>Bei <strong>Dreiergruppen</strong> legt der Schalter im Kartenkopf fest, ob <strong>1</strong> oder <strong>2</strong> Teilnehmer weiterkommen (Standard 2). Wird umgeschaltet, passt sich der Pool für Runde 2 automatisch an: ausscheidende Teilnehmer werden aus Runde 2 entfernt, nachrückende hinzugefügt.</p><ul><li>Bei Punktgleichheit wird die Paarung gelb markiert und der Gewinner per Klick auf den Namen bestimmt; ein so gesetzter Gewinner hat Vorrang vor dem Resultat.</li><li>Ein «Nachrücker» in Runde 2 ist, wer in Runde 2 steht, ohne Runde 1 gewonnen zu haben; er wird in Rangliste und Absendebuch entsprechend geführt.</li></ul>',
 'cup'),

('cup.finale',
 'Finale und Kategorie B',
 '<p>Das Finale wird aus den Gewinnern von Runde 2 gebildet.</p><ul><li><strong>Kat. B automatisch ins Finale</strong>: ist der Schalter an, rückt der beste Schütze der Kategorie B aus <strong>Runde 2</strong> (nach Resultat und Tiefschuss) zusätzlich ins Finale, auch wenn er seine Runde-2-Paarung verloren hat. Stand kein Kat.-B-Schütze in Runde 2, gilt der beste aus Runde 1.</li><li>Der Schalter wird pro Jahr gespeichert. Beim Ausschalten bleiben bereits erfasste Finalresultate stehen und müssen bei Bedarf von Hand bereinigt werden.</li></ul><p>Der <strong>Standcup Final</strong> darunter ist unabhängig vom Turnierbaum: je ein Teilnehmer und Resultat pro Verein.</p>',
 'cup'),

-- ---------------------------------------------------------------- Kantonalstich
('kantiabr.uebersicht',
 'Kantonalstich: Ranglisten',
 '<p>Zeigt die Ranglisten des Kantonalstichs für das gewählte Jahr, getrennt nach <strong>Kategorie A</strong> und <strong>Kategorie B</strong>: Hauptdoppel, bis vier Nachdoppel und Total.</p><ul><li>Das Jahr oben wählen; «Neu laden» holt die Daten frisch aus der Datenbank, «Zur Erfassung» wechselt auf die Resultaterfassung.</li><li><strong>PDF generieren</strong> erstellt die Rangliste als Datei, der Drucker-Knopf schickt sie direkt an den hinterlegten Drucker.</li></ul>',
 'kantiabr'),

('kantiabr.sksg',
 'SKSG-Abrechnung (Excel)',
 '<p>Füllt das offizielle <strong>Abrechnungsformular der SKSG</strong> (Excel-Datei mit Makro) mit den Daten des gewählten Jahres. Die Datei geht per Mail an den Kantonalschützenverband.</p><ul><li><strong>Titelblatt</strong>: Distanz, Verein, Verantwortlicher mit Adresse und E-Mail. Der Verantwortliche wird als Mitglied gewählt und bleibt gespeichert; Adresse und E-Mail kommen aus den Stammdaten und lassen sich vor dem Export anpassen.</li><li><strong>Kontrollblatt</strong>: Name, Vorname, Jahrgang, Sportgerät und die Passen aller Schützen des Jahres. Kategorie, Kranzresultate und Totalbetrag rechnet das Formular beim Öffnen selbst.</li><li><strong>Sportgerät</strong> muss einem Wert der SKSG-Liste entsprechen (Standardgewehr, Freigewehr, Karabiner, Stgw57/03, Stgw57/02, Stgw90). Passt eine Waffenbezeichnung nicht, erscheint eine Warnung; dann die Waffe in den Stammdaten prüfen.</li><li>Das Formular fasst höchstens <strong>40 Schützen</strong>.</li></ul><p>Makro, Blattschutz und Logo der Vorlage bleiben erhalten.</p>',
 'kantiabr')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
