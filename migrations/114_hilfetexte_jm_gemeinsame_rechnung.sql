-- Migration 114: JM-Rangliste mit gemeinsamer Rechnung (Okt 2026, 1.68.33).
-- Admin-Rangliste, PDF «Rangliste nach Rang» und Portal rechnen seit 15.10.2026 mit jmWertung()
-- (inc/jm_berechnung.inc.php). Neu: ein Anlass zählt für alle, sobald jemand ein Resultat hat (abgesagte
-- oder noch nicht erfasste Anlässe geben niemandem eine 0); vor dem Endstich zeigt der Zwischenstand Total
-- und Rang, gestrichen werden dann nur verpasste Anlässe (Entscheid 15.10.2026); die Rangliste gilt erst,
-- wenn der Endstich-Tag erreicht UND Endstich-Resultate erfasst sind.
-- Ersetzt die Sätze aus 103/110 im Hilfetext jmresultate.rangliste. Dazu die Aufschlüsselung (Gruppe heisst neu
-- «Streichresultate», listet verpasste Anlässe und Rohwerte), Kurznamen im Spaltenkopf und der Knopf
-- «Alle Resultate nach Name» (vorher «Rangliste nach Name»; Label = Dokumentname).
--
-- Idempotent: alle Texte per REPLACE (ohne Treffer keine Änderung).
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li>«–» heisst: Anlass durchgeführt, Resultat noch nicht erfasst. Durchgestrichene Werte sind Streicher und zählen nicht.</li>',
  '<li>«–» heisst: kein Resultat. Hat in diesem Anlass schon jemand ein Resultat, gilt er als verpasst und zählt 0 (der Hinweis beim Darüberfahren sagt, ob die 0 gestrichen ist). Durchgestrichene Werte sind Streicher und zählen nicht.</li>')
WHERE schluessel = 'jmresultate.rangliste';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Zwischenstand</strong>: Bis zum Endstich heisst die Liste «Zwischenstand Kat. A/B». Sie ist nach Name sortiert, es gibt noch keine Streicher, und Total und Rang stehen auf «offen». Der Kopf nennt das Datum des Endstichs; danach wird sie zur Rangliste.</li>',
  '<li><strong>Streicher</strong>: Ein Anlass mit Streichresultat zählt für alle, sobald jemand ein Resultat hat; wer ihn verpasst hat, bekommt 0. Ein abgesagter oder noch nicht erfasster Anlass zählt für niemanden.</li><li><strong>Zwischenstand</strong>: Bis zum Endstich heisst die Liste «Zwischenstand Kat. A/B». Total und Rang sind vorläufig und ohne Podest; gestrichen werden nur verpasste Anlässe, höchstens so viele wie Streicher. Zur Rangliste wird sie, wenn der Endstich-Tag erreicht ist und Endstich-Resultate erfasst sind; dann zählen die schlechtesten Resultate nicht. Dieselbe Rechnung gilt im PDF «Rangliste nach Rang» und im Portal.</li>')
WHERE schluessel = 'jmresultate.rangliste';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li>Als Spalten stehen die bis zu sechs zuletzt durchgeführten Anlässe (neueste rechts); Obligatorisch und Feldschiessen erscheinen nur in der Aufschlüsselung. Ein Klick auf den Anlass im Spaltenkopf öffnet seine Erfassung.</li><li>Ein Klick auf eine Zeile klappt die Aufschlüsselung auf (Pflicht- und Streichresultate mit Zwischensummen).</li>',
  '<li>Als Spalten stehen die bis zu sechs zuletzt durchgeführten Anlässe (neueste rechts), im Kopf mit Kurznamen; der volle Name erscheint beim Darüberfahren. Obligatorisch und Feldschiessen erscheinen nur in der Aufschlüsselung. Ein Klick auf den Anlass im Spaltenkopf öffnet seine Erfassung.</li><li>Ein Klick auf eine Zeile klappt die Aufschlüsselung auf: <strong>Pflicht</strong> und <strong>Streichresultate</strong> mit Zwischensummen. Unter den Streichresultaten stehen alle Anlässe, die zählen, auch die verpassten mit 0. Hochgerechnete Werte nennen den Rohwert, z.B. «96.25 aus 77 / 80».</li>')
WHERE schluessel = 'jmresultate.rangliste';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Rangliste nach Rang</strong> und <strong>Rangliste nach Name</strong> erstellen die Rangliste als PDF; der Drucker daneben druckt sie direkt.</li>',
  '<li><strong>Rangliste nach Rang</strong> erstellt die Rangliste als PDF (vor dem Endstich den Zwischenstand), <strong>Alle Resultate nach Name</strong> eine alphabetische Liste aller Resultate ohne Streicher. Der Drucker daneben druckt direkt.</li>')
WHERE schluessel = 'jmresultate.uebersicht';
