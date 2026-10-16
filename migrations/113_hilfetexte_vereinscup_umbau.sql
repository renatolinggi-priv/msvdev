-- Migration 113: Hilfetexte zum Vereinscup nach dem Umbau (Okt 2026, 1.68.31).
-- Die Seite «Vereinscup erfassen» folgt jetzt dem Erscheinungsbild der übrigen Admin-Seiten:
-- Paarungen als Zeilen (Haken = kommt weiter), «Speichern» oben rechts, Löschen über den Papierkorb
-- mit Liste der Folgen und Sicherung vorher, neue Paarungen unten in Runde 1 («Hinzufügen»).
-- Neu: cup.nachruecker (Hilfe neben «Weiter aus Runde 1» in Runde 2).
--
-- Idempotent: ON DUPLICATE KEY UPDATE; die Migration ist die Quelle der Wahrheit für diese Texte.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO hilfetexte (schluessel, titel, inhalt_html, kategorie) VALUES

('cup.uebersicht',
 'Vereinscup erfassen',
 '<p>Der Cup läuft in drei Stufen: <strong>Runde 1</strong>, <strong>Runde 2</strong>, <strong>Finale</strong>. Oben stehen das Jahr und der Stand jeder Runde: grauer Punkt = noch leer, gelber Punkt = angefangen, grüner Punkt = alle Paarungen entschieden. Sind alle Finalpunkte eingetragen, steht dort auch der Cupsieger.</p><ul><li><strong>Teilnehmer einteilen</strong>: einen Namen aus der Liste links in einen freien Platz ziehen. Ohne Ziehen: den Namen anklicken (oder mit Tab wählen und Enter drücken), danach den freien Platz ebenso. Escape hebt die Wahl auf. Ein Name wird mit dem x neben ihm (oder Entf) wieder herausgenommen.</li><li><strong>Punkte</strong> direkt neben dem Namen eintragen. Wer weiterkommt, hat einen grünen Haken.</li><li><strong>Speichern</strong> oben rechts (auch Ctrl+S) sichert alle Runden, das Finale und den Standcup auf einmal. Solange etwas nicht gespeichert ist, steht «Nicht gespeichert» daneben; beim Jahreswechsel und beim Verlassen der Seite fragt sie nach. Klappt etwas nicht, nennt die Meldung, was fehlt, und die Eingaben bleiben stehen.</li><li><strong>Paarung löschen</strong>: der Papierkorb in der Paarung. Ist sie gespeichert, zeigt die Rückfrage, was mitgelöscht wird (bei Runde 1 auch die Paarung in Runde 2 und Finalresultate derselben Schützen, im Jahr der Paarung). Vorher wird die Datenbank gesichert. Gibt es ungespeicherte Eingaben, zuerst speichern.</li><li><strong>Rangliste</strong> erzeugt die Cup-Rangliste als PDF, der Drucker-Knopf druckt sie direkt. Beide enthalten nur Gespeichertes und fragen sonst zuerst nach.</li></ul><p>Frühere Jahre bleiben über die Jahresauswahl abrufbar.</p>',
 'cup'),

('cup.runden',
 'Runde 1, Dreier und Gleichstand',
 '<p><strong>Neue Paarungen</strong> legst du unten in Runde 1 an: Anzahl und Grösse (Zweier oder Dreier) wählen, dann <strong>Hinzufügen</strong>. Die leeren Paarungen werden unten angefügt; danach Teilnehmer einteilen. Runde 2 baut sich aus den Gewinnern von selbst auf (bei ungerader Zahl mit einer Dreier-Paarung).</p><ul><li>Bei <strong>Dreiern</strong> legt der Schalter «Weiter kommen 1 | 2» unter der Paarung fest, ob einer oder zwei weiterkommen (Standard 2). Wer danach nicht mehr weiterkommt, verschwindet aus Runde 2.</li><li>Bei <strong>Gleichstand</strong> wird die Zeile gelb, und oben in der Paarung steht «Gleichstand: wer kommt weiter?». Mit <strong>Weiter</strong> neben dem Namen wählst du, wer weiterkommt; sind alle drei eines Dreiers gleich, nacheinander die zwei. Die Wahl wird mit Speichern gesichert und hat Vorrang vor den Punkten. Ändern sich die Punkte oder die Teilnehmer, gilt sie nicht mehr.</li></ul>',
 'cup'),

('cup.nachruecker',
 'Weiter aus Runde 1 und Nachrücker',
 '<p>Hier stehen die Gewinner aus Runde 1, die in Runde 2 noch keinen Platz haben. Sie werden wie in Runde 1 in die freien Plätze eingeteilt.</p><ul><li><strong>Nachrücker</strong> nimmt einen Verlierer aus Runde 1 in Runde 2 auf, z.B. wenn ein Gewinner nicht antreten kann.</li><li>Nachrücker tragen das Symbol mit dem Plus neben dem Namen. In Rangliste und Absendenbuch werden sie als Nachrücker geführt.</li></ul>',
 'cup'),

('cup.finale',
 'Finale und Kategorie B',
 '<p>Das Finale wird aus den Gewinnern von Runde 2 gebildet; die Liste baut sich beim Eintragen der Punkte in Runde 2 von selbst auf. Sind alle Finalpunkte eingetragen, zeigt die Liste die Ränge, und oben steht der Cupsieger.</p><ul><li><strong>Bester Kat. B rückt ins Finale nach</strong>: Ist der Schalter an, kommt der beste Schütze der Kategorie B aus <strong>Runde 2</strong> (nach Resultat und Tiefschuss) zusätzlich ins Finale, auch wenn er seine Paarung verloren hat; er trägt die Marke «Kat. B». Stand kein Kat.-B-Schütze in Runde 2, gilt der beste aus Runde 1.</li><li>Der Schalter wirkt sofort und gilt pro Jahr; schon getippte Finalpunkte bleiben stehen. Beim Ausschalten bleiben gespeicherte Finalresultate des Kat.-B-Schützen in der Datenbank und müssen bei Bedarf von Hand bereinigt werden.</li></ul><p>Der <strong>Standcup Final</strong> darunter erscheint, sobald Finalresultate gespeichert sind: je ein Teilnehmer und Resultat pro Verein. Er wird mit Speichern gesichert, sobald alle drei Namen und Punkte eingetragen sind.</p>',
 'cup')

ON DUPLICATE KEY UPDATE
    titel         = VALUES(titel),
    inhalt_html   = VALUES(inhalt_html),
    kategorie     = VALUES(kategorie),
    ist_geloescht = 0;
