-- Migration 062: Duplikat-Erkennung in der Foto-Galerie
--
-- SHA-1 der verarbeiteten Full-Version pro Foto. Laedt ein zweites Mitglied dasselbe
-- Bild in dieselbe Galerie, erkennt api/foto_upload.php das ueber den Hash und lehnt
-- den Upload mit Hinweis ab. Bestehende Fotos erhalten den Hash beim naechsten Upload
-- in die Galerie automatisch nachgetragen (fotoHashesNachtragen), darum hier NULL.
--
-- Der Code prueft zur Laufzeit, ob die Spalte existiert; bis zur Ausfuehrung dieser
-- Migration laeuft der Upload ohne Duplikat-Pruefung weiter.

ALTER TABLE `anlass_fotos`
    ADD COLUMN IF NOT EXISTS `datei_hash` CHAR(40) DEFAULT NULL AFTER `dateigroesse`;

ALTER TABLE `anlass_fotos`
    ADD INDEX IF NOT EXISTS `ix_foto_hash` (`galerie_id`, `datei_hash`);
