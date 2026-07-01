-- Migration 042: Beschreibungen fuer die Wanderpreise-Standardregeln nachtragen
--
-- Die 11 Standardregeln existieren bereits (regel_beschreibung war leer). Diese
-- Migration setzt pro regel_code eine sprechende Beschreibung. Reine UPDATEs:
-- fehlt eine Regel, trifft das UPDATE 0 Zeilen (harmlos). sql_query/regel_typ
-- bleiben unangetastet.
--
-- {jahr} ist ein Laufzeit-Platzhalter der auto_zuordnung.php und steht hier nur
-- als Teil des beschreibenden Textes.

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger im Glückstich: rangiert nach dem höchsten der drei Glücksschüsse; bei Gleichstand zählen zweit- und dritthöchster Schuss, danach das Alter (älteres Mitglied vorne). Wertet nur Schützen mit gültigem Resultat im Jahr {jahr}.' WHERE `regel_code` = 'glueckstich';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger im Kunststich: rangiert nach der Summe der fünf Kunstschüsse; bei Gleichstand entscheidet der höchste Einzelschuss (Tiefschuss/TS). Jahr {jahr}.' WHERE `regel_code` = 'kunststich';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger der Heimmeisterschaft in Kategorie A: rangiert nach der Summe aller acht Passen der Heimresultate; nur Mitglieder mit Waffenkategorie „Kat. A" und mindestens einer geschossenen Passe im Jahr {jahr}.' WHERE `regel_code` = 'heimmeisterschaftA';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger der Heimmeisterschaft in Kategorie B: rangiert nach der Summe aller acht Passen der Heimresultate; nur Mitglieder mit Waffenkategorie „Kat. B" und mindestens einer geschossenen Passe im Jahr {jahr}.' WHERE `regel_code` = 'heimmeisterschaftB';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger im Kantonalstich Kategorie A: rangiert nach der Summe der fünf Passen der Kantonalstich-Resultate; nur Mitglieder mit Waffenkategorie „Kat. A" und mindestens einer geschossenen Passe im Jahr {jahr}.' WHERE `regel_code` = 'kantonalstichA';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger im Kantonalstich Kategorie B: rangiert nach der Summe der fünf Passen der Kantonalstich-Resultate; nur Mitglieder mit Waffenkategorie „Kat. B" und mindestens einer geschossenen Passe im Jahr {jahr}.' WHERE `regel_code` = 'kantonalstichB';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger des Endschiessens Kategorie A: Gesamttotal aus Endstich (Summe aller 10 Schüsse), Glückstich (höchster Schuss ÷10), Zabig (pro Passe max. 10 Punkte, aufgerundet ÷10), Kunst (Summe ÷10) und Schwini (bessere der zwei Passen). Nur Waffenkategorie „Kat. A", ohne verstorbene Mitglieder; nur Schützen mit Gesamttotal > 0 im Jahr {jahr}. Rangierung nach Gesamttotal, dann Endstich, dann Alter.' WHERE `regel_code` = 'endschiessenA';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger des Endschiessens Kategorie B: Gesamttotal aus Endstich (Summe aller 10 Schüsse), Glückstich (höchster Schuss ÷10), Zabig (pro Passe max. 10 Punkte, aufgerundet ÷10), Kunst (Summe ÷10) und Schwini (bessere der zwei Passen). Nur Waffenkategorie „Kat. B", ohne verstorbene Mitglieder; nur Schützen mit Gesamttotal > 0 im Jahr {jahr}. Rangierung nach Gesamttotal, dann Endstich, dann Alter.' WHERE `regel_code` = 'endschiessenB';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger der Jahresmeisterschaft Kategorie A: kombiniert alle JM-Wettbewerbe des Jahres {jahr} inkl. Endstich und bestem Kantonalstich. Punkte werden je Wettbewerb auf 100 skaliert (ausser Einzelwettschiessen/Obligatorisch/Feldschiessen = Rohpunkte), bei der Sektionsmeisterschaft zählt das beste Resultat. Streicher-Wettbewerbe: die drei schlechtesten Resultate werden gestrichen, Nichtteilnahmen als 0 gewertet. Nur aktive, nicht verstorbene Mitglieder der Kat. A mit Total > 0; Ausgabe mit Rang, Fix- und Streicherpunkten.' WHERE `regel_code` = 'jahresmeisterschaftA';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger der Jahresmeisterschaft Kategorie B: kombiniert alle JM-Wettbewerbe des Jahres {jahr} inkl. Endstich und bestem Kantonalstich. Punkte werden je Wettbewerb auf 100 skaliert (ausser Einzelwettschiessen/Obligatorisch/Feldschiessen = Rohpunkte), bei der Sektionsmeisterschaft zählt das beste Resultat. Streicher-Wettbewerbe: die drei schlechtesten Resultate werden gestrichen, Nichtteilnahmen als 0 gewertet. Nur aktive, nicht verstorbene Mitglieder der Kat. B mit Total > 0; Ausgabe mit Rang, Fix- und Streicherpunkten.' WHERE `regel_code` = 'jahresmeisterschaftB';

UPDATE `wanderpreise_regeln` SET `regel_beschreibung` = 'Sieger des MSV Wilen Vereinscups: übernimmt die Endresultate des Cup-Turniers aus cupFinalResults für das Jahr {jahr}, rangiert nach Ergebnis und – bei Gleichstand – nach dem Stechschuss (LowShot).' WHERE `regel_code` = 'vereinscup';
