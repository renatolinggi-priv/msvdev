-- Migration 115: Doppelte Vereinscup-Paarungen entfernen (Okt 2026, 1.68.34).
-- 2024 stand Runde 1 zweimal in cupPairs: die Paarungen 87 bis 92 sind exakte Kopien von 52 bis 57
-- (gleiche Teilnehmer, Resultate und Runde), vermutlich ein doppeltes Speichern vor 1.68.31.
-- Cup-Rangliste und Absendenbuch 2024 zeigten Runde 1 darum doppelt; die Jahresmeisterschaft las
-- nur die erste Paarung und ist nicht betroffen.
--
-- Gelöscht wird nur eine Paarung, die in Jahr, Runde, allen Teilnehmern, Resultaten, Tiefschüssen,
-- «weiter» und manuellem Gewinner einer Paarung mit kleinerer Nummer gleicht; die ältere bleibt.
-- Idempotent: ohne Doppelungen löscht die Anweisung nichts. Der Runner sichert vorher die Datenbank.
-- Hinweis: keine Zeile innerhalb der Anweisung darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

DELETE neu FROM cupPairs AS neu
JOIN cupPairs AS alt
  ON alt.ID < neu.ID
 AND alt.`Year` = neu.`Year`
 AND alt.`Round` = neu.`Round`
 AND alt.Participant1 = neu.Participant1
 AND alt.Participant2 = neu.Participant2
 AND alt.Participant3 <=> neu.Participant3
 AND alt.Result1 <=> neu.Result1
 AND alt.Result2 <=> neu.Result2
 AND alt.Result3 <=> neu.Result3
 AND alt.LowShot1 <=> neu.LowShot1
 AND alt.LowShot2 <=> neu.LowShot2
 AND alt.LowShot3 <=> neu.LowShot3
 AND alt.Advancers <=> neu.Advancers
 AND alt.ManualWinner <=> neu.ManualWinner;
