-- Migration 117: Hilfetext «Absenden vorbereiten» nach dem Polish (Okt 2026, 1.68.39).
-- 1) «Zur Erfassung» bei Stichen und Sie und Er schaltet ohne Neuladen auf die Ansicht «Erfassen» und zeigt nur
--    die offenen Teilnehmer.
-- 2) Sie und Er: Hat niemand Sie und Er gelöst, steht «nichts zu tun» statt «erledigt».
--
-- Idempotent: REPLACE (ohne Treffer keine Änderung).
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'nach derselben Regel wie in der Erfassung; der Link führt direkt dorthin.</li>',
  'nach derselben Regel wie in der Erfassung. «Zur Erfassung» schaltet auf die Ansicht «Erfassen» und zeigt nur die offenen Teilnehmer.</li>')
WHERE schluessel = 'endschrang.absenden';

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  '<li><strong>Sie und Er</strong>: wer Sie und Er gelöst hat, aber noch mit niemandem verknüpft ist. Sie und Er hat eine eigene Rangliste und steht nicht im Absendenbuch.</li>',
  '<li><strong>Sie und Er</strong>: wer Sie und Er gelöst hat, aber noch mit niemandem verknüpft ist; «Zur Erfassung» führt wie bei den Stichen zu den offenen Teilnehmern. Hat niemand Sie und Er gelöst, steht «nichts zu tun». Sie und Er hat eine eigene Rangliste und steht nicht im Absendenbuch.</li>')
WHERE schluessel = 'endschrang.absenden';
