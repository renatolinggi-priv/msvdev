-- Migration 056: Trigger trg_gaeste_mitgliedernr entfernen (blockierte das Anlegen von Gästen)
--
-- Migration 015 legte einen AFTER-INSERT-Trigger an, der endstich_gaeste selbst per UPDATE
-- beschreibt. MariaDB verbietet das («Can't update table 'endstich_gaeste' in stored
-- function/trigger because it is already used by statement which invoked this stored
-- function/trigger», Fehler 1442) – jeder neue Gast/Jungschütze scheiterte damit beim Speichern.
-- Die synthetische Mitgliedernummer (999000 + id, für den CSV-Export der Schiessanlage) setzt
-- neu die Anwendung direkt nach dem INSERT (inc/endschloesen/endschloesen_api.php, save_selection).
-- Idempotent.

DROP TRIGGER IF EXISTS trg_gaeste_mitgliedernr;

-- Bestehende Lücken auffüllen (falls Gäste ohne Nummer angelegt wurden)
UPDATE endstich_gaeste SET mitgliedernr = 999000 + id WHERE mitgliedernr IS NULL;
