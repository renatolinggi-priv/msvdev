-- Migration 026: PDF-Design - zentrale, bearbeitbare Theme-Einstellungen
-- Speichert die Farb-Palette und ein paar Layout-Werte fuer alle generierten
-- PDFs als Key/Value. Fehlt die Tabelle oder ein Wert, greifen automatisch die
-- in inc/pdf/pdf_theme.php hinterlegten Defaults (PDF bricht nie an einer
-- Einstellung). Bearbeitbar unter inc/pdf_design.php.

CREATE TABLE IF NOT EXISTS pdf_theme_settings (
    skey   VARCHAR(64)  NOT NULL,
    svalue VARCHAR(64)  NOT NULL,
    PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
