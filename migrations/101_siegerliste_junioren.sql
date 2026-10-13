-- Migration 101: Junioren-Siegerliste als normale Siegerliste (1.68.12, Okt 2026)
--
-- Bisher stand «Junioren Endschiessen Sieger seit 1997» als feste Tabelle in der Word-Vorlage des Absendenbuchs
-- und wurde von Hand in der Vorlage nachgeführt. Neu ist sie eine Auszeichnung in siegerdef (Platzhalter SJ in der
-- Vorlage Resultatbuch_Template20261013.docx) und wird auf «Sieger der letzten Jahre» gepflegt bzw. mit
-- «Aus Ranglisten übernehmen» gefüllt. Jahre ohne Jungschützen: «Keine Konkurrenz» ohne Punkte.
-- Übernommen sind alle Einträge der bisherigen Tabelle (1997–2015, 2024, 2025); 2016–2023 gab es laut Verein
-- keine Junioren-Sieger, darum «Keine Konkurrenz».
--
-- Dazu die Hilfetexte: Jungschützen haben jetzt eine eigene Siegerliste; im Absendenbuch stehen je Liste die
-- letzten 30 Sieger.
--
-- Idempotent: siegerdef und jedes Jahr nur, wenn noch nicht vorhanden; Hilfetexte per REPLACE bzw. nur einmal ergänzt.
-- Hinweis: keine Zeile innerhalb der Strings darf mit zwei Bindestrichen beginnen (Kommentar-Filter des Runners).

INSERT INTO siegerdef (Bezeichnung, PlatzhalterWord)
SELECT 'Junioren', 'SJ' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM siegerdef WHERE Bezeichnung = 'Junioren');

INSERT INTO sieger (Name, Wert, siegerdef, year)
SELECT v.n, v.w, d.ID, v.y
FROM (
          SELECT 1997 AS y, 'Schober Marco' AS n, '195' AS w
    UNION ALL SELECT 1998, 'von Euw Alexander', '205'
    UNION ALL SELECT 1999, 'Schober Marco', '197'
    UNION ALL SELECT 2000, 'Schober Marco', '189'
    UNION ALL SELECT 2001, 'Schober Marco', '198'
    UNION ALL SELECT 2002, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2003, 'Linggi Renato', '206'
    UNION ALL SELECT 2004, 'Lienert Roman', '210'
    UNION ALL SELECT 2005, 'Lienert Roman', '252.7'
    UNION ALL SELECT 2006, 'Lienert Roman', '205'
    UNION ALL SELECT 2007, 'Lienert Roman', '210'
    UNION ALL SELECT 2008, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2009, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2010, 'Kauf Roland', '191'
    UNION ALL SELECT 2011, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2012, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2013, 'Wittek Robin', '180'
    UNION ALL SELECT 2014, 'Fuchs Stefan', '195'
    UNION ALL SELECT 2015, 'Tobler Marcel', '168'
    UNION ALL SELECT 2016, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2017, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2018, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2019, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2020, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2021, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2022, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2023, 'Keine Konkurrenz', ''
    UNION ALL SELECT 2024, 'Reichlin Raja', '111'
    UNION ALL SELECT 2025, 'Eberle Jeaninne', '171'
) v
JOIN siegerdef d ON d.Bezeichnung = 'Junioren'
WHERE NOT EXISTS (SELECT 1 FROM sieger s WHERE s.siegerdef = d.ID AND s.year = v.y);

UPDATE hilfetexte SET inhalt_html = REPLACE(inhalt_html,
  'Gäste und Jungschützen stehen in keiner Siegerliste.',
  'Gäste stehen in keiner Siegerliste. Die Jungschützen haben die eigene Liste «Junioren»: Sieger ist, wer in der Jungschützen-Wertung des Endschiessens vorne liegt; hat kein Jungschütze geschossen, steht «Keine Konkurrenz».')
WHERE schluessel = 'sieger.uebernehmen';

UPDATE hilfetexte SET inhalt_html = CONCAT(inhalt_html,
  '<p>Im Absendenbuch stehen je Liste die letzten 30 Sieger bis zum gewählten Jahr; die Überschrift «… seit» nennt das erste gezeigte Jahr. Ältere Sieger bleiben hier erhalten.</p>')
WHERE schluessel = 'sieger.uebersicht' AND inhalt_html NOT LIKE '%letzten 30 Sieger%';
