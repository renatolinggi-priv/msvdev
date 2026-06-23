-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql11j08.db.hostpoint.internal
-- Erstellungszeit: 27. Mrz 2026 um 13:05
-- Server-Version: 10.11.14-MariaDB-log
-- PHP-Version: 8.3.29

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `bdebbd4_msvjm`
--

DELIMITER $$
--
-- Prozeduren
--
CREATE DEFINER=`hpid_25720`@`admin%.adm.hostpoint.internal` PROCEDURE `sp_delete_cup_pair` (IN `p_pair_id` INT, OUT `p_success` BOOLEAN, OUT `p_message` VARCHAR(255))   BEGIN
    DECLARE v_round INT;
    DECLARE v_year INT;
    DECLARE v_participant1 INT;
    DECLARE v_participant2 INT;
    DECLARE v_participant3 INT;
    DECLARE v_deleted_count INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Fehler beim Löschen der Paarung';
    END;
    
    START TRANSACTION;
    
    -- Paarungsdetails abrufen
    SELECT Round, Year, Participant1, Participant2, Participant3
    INTO v_round, v_year, v_participant1, v_participant2, v_participant3
    FROM cupPairs
    WHERE ID = p_pair_id;
    
    -- Hauptpaarung löschen
    DELETE FROM cupPairs WHERE ID = p_pair_id;
    SET v_deleted_count = v_deleted_count + ROW_COUNT();
    
    -- Wenn Runde 1, lösche abhängige Einträge
    IF v_round = 1 THEN
        -- Lösche Runde 2 Einträge
        DELETE FROM cupPairs 
        WHERE Round = 2 
        AND Year = v_year
        AND (Participant1 IN (v_participant1, v_participant2, v_participant3)
             OR Participant2 IN (v_participant1, v_participant2, v_participant3)
             OR Participant3 IN (v_participant1, v_participant2, v_participant3));
        SET v_deleted_count = v_deleted_count + ROW_COUNT();
        
        -- Lösche Finaleinträge
        DELETE FROM cupFinalResults
        WHERE Year = v_year
        AND ParticipantID IN (v_participant1, v_participant2, v_participant3);
        SET v_deleted_count = v_deleted_count + ROW_COUNT();
    END IF;
    
    -- Audit Log
    INSERT INTO cupAuditLog (Action, PairID, Details)
    VALUES ('PAIR_DELETED', p_pair_id, JSON_OBJECT(
        'round', v_round,
        'year', v_year,
        'participants', JSON_ARRAY(v_participant1, v_participant2, v_participant3),
        'deleted_count', v_deleted_count
    ));
    
    COMMIT;
    
    SET p_success = TRUE;
    SET p_message = CONCAT(v_deleted_count, ' Einträge gelöscht');
END$$

--
-- Funktionen
--
CREATE DEFINER=`hpid_25720`@`admin%.adm.hostpoint.internal` FUNCTION `fn_get_3way_winners` (`p_pair_id` INT) RETURNS LONGTEXT CHARSET utf8mb4 COLLATE utf8mb4_bin DETERMINISTIC READS SQL DATA BEGIN
    DECLARE v_winners JSON;
    
    SELECT JSON_ARRAY(
        (SELECT participant_id FROM (
            SELECT 
                Participant1 as participant_id, Result1 as result, LowShot1 as lowshot
            FROM cupPairs WHERE ID = p_pair_id
            UNION ALL
            SELECT 
                Participant2, Result2, LowShot2
            FROM cupPairs WHERE ID = p_pair_id
            UNION ALL
            SELECT 
                Participant3, Result3, LowShot3
            FROM cupPairs WHERE ID = p_pair_id AND Participant3 IS NOT NULL
        ) as results
        ORDER BY result DESC, lowshot DESC
        LIMIT 2)
    ) INTO v_winners;
    
    RETURN v_winners;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `changelog`
--

CREATE TABLE `changelog` (
  `id` int(11) NOT NULL,
  `kategorie` varchar(30) NOT NULL COMMENT 'resultate, termine, definition, standbelegung',
  `aktion` varchar(20) NOT NULL COMMENT 'erstellt, aktualisiert, geloescht',
  `beschreibung` varchar(500) NOT NULL COMMENT 'Menschenlesbare Beschreibung',
  `tabelle` varchar(50) DEFAULT NULL COMMENT 'Betroffene DB-Tabelle',
  `jahr` int(11) DEFAULT NULL COMMENT 'Betroffenes Jahr',
  `details` text DEFAULT NULL COMMENT 'Optionales JSON fuer Zusatzinfos',
  `sichtbar` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=oeffentlich, 0=nur intern (JM-Resultate)',
  `wp_slug` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL COMMENT 'Session user_id',
  `erstellt_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `changelog`
--

INSERT INTO `changelog` (`id`, `kategorie`, `aktion`, `beschreibung`, `tabelle`, `jahr`, `details`, `sichtbar`, `wp_slug`, `user_id`, `erstellt_am`) VALUES
(3, 'definition', 'aktualisiert', 'Jahresprogramm 2026 aktualisiert', 'JMDefinition', 2026, NULL, 1, NULL, 1, '2026-03-02 10:30:41'),
(4, 'definition', 'aktualisiert', 'Jahresprogramm 2026 aktualisiert', 'JMDefinition', 2026, NULL, 1, 'home', 1, '2026-03-02 12:12:26'),
(5, 'termine', 'aktualisiert', 'Termin aktualisiert: DV SKSG in Rothenturm', 'wichtige_termine', NULL, NULL, 1, 'wichtige-termine-3', 1, '2026-03-02 12:12:44'),
(6, 'definition', 'aktualisiert', 'Jahresprogramm 2026 aktualisiert', 'JMDefinition', 2026, NULL, 1, 'home', 1, '2026-03-04 12:48:52');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `cupAuditLog`
--

CREATE TABLE `cupAuditLog` (
  `ID` int(11) NOT NULL,
  `Action` varchar(50) NOT NULL COMMENT 'Aktion (z.B. MANUAL_WINNER_SET, PAIR_DELETED)',
  `PairID` int(11) DEFAULT NULL COMMENT 'Betroffene Paarungs-ID',
  `UserID` int(11) DEFAULT NULL COMMENT 'ID des Benutzers der die Aktion durchgeführt hat',
  `Details` text DEFAULT NULL COMMENT 'JSON-Details zur Aktion',
  `Timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Protokoll aller manuellen Änderungen im Cup-System';

--
-- Daten für Tabelle `cupAuditLog`
--

INSERT INTO `cupAuditLog` (`ID`, `Action`, `PairID`, `UserID`, `Details`, `Timestamp`) VALUES
(1, 'MANUAL_WINNER_SET', 64, NULL, '{\"winner_id\":112131,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-04 15:50:18'),
(2, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112103,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-04 15:51:10'),
(3, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112103,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-04 15:57:47'),
(4, 'MANUAL_WINNER_SET', 61, NULL, '{\"winner_id\":112141,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112141,112101]}}', '2025-07-07 09:03:11'),
(5, 'MANUAL_WINNER_REMOVED', 61, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112141,112101]}}', '2025-07-07 09:06:54'),
(6, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:07:05'),
(7, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:07:09'),
(8, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:07:12'),
(9, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:07:16'),
(10, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112144,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:07:19'),
(11, 'MANUAL_WINNER_SET', 61, NULL, '{\"winner_id\":112141,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112141,112101]}}', '2025-07-07 09:07:42'),
(12, 'MANUAL_WINNER_SET', 64, NULL, '{\"winner_id\":112131,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 09:07:51'),
(13, 'MANUAL_WINNER_REMOVED', 61, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112141,112101]}}', '2025-07-07 09:08:04'),
(14, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:08:06'),
(15, 'MANUAL_WINNER_REMOVED', 64, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 09:08:07'),
(16, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112144,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:14:26'),
(17, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112144,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:14:35'),
(18, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:14:40'),
(19, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112103,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:14:56'),
(20, 'MANUAL_WINNER_SET', 62, NULL, '{\"winner_id\":112126,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112126,385067]}}', '2025-07-07 09:15:25'),
(21, 'MANUAL_WINNER_REMOVED', 62, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112126,385067]}}', '2025-07-07 09:15:29'),
(22, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:18:46'),
(23, 'MANUAL_WINNER_SET', 63, NULL, '{\"winner_id\":112103,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 09:18:52'),
(24, 'MANUAL_WINNER_REMOVED', 63, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112144,112103]}}', '2025-07-07 10:33:33'),
(25, 'MANUAL_WINNER_SET', 64, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 10:39:16'),
(26, 'MANUAL_WINNER_REMOVED', 64, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 10:39:52'),
(27, 'MANUAL_WINNER_SET', 72, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 10:50:00'),
(28, 'MANUAL_WINNER_REMOVED', 72, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 11:31:10'),
(29, 'MANUAL_WINNER_SET', 72, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 11:33:08'),
(30, 'MANUAL_WINNER_SET', 82, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 14:39:28'),
(31, 'MANUAL_WINNER_REMOVED', 82, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 14:39:47'),
(32, 'MANUAL_WINNER_SET', 82, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 14:44:15'),
(33, 'MANUAL_WINNER_REMOVED', 82, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 14:48:04'),
(34, 'MANUAL_WINNER_SET', 82, NULL, '{\"winner_id\":112114,\"reason\":\"Nachr\\u00fccker\",\"pair\":{\"round\":1,\"participants\":[112131,112114]}}', '2025-07-07 14:48:45'),
(35, 'MANUAL_WINNER_SET', 95, NULL, '{\"winner_id\":112108,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112108,112109]}}', '2026-02-19 11:04:08'),
(36, 'MANUAL_WINNER_SET', 96, NULL, '{\"winner_id\":112134,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112126,112134]}}', '2026-02-19 11:04:09'),
(37, 'MANUAL_WINNER_SET', 100, NULL, '{\"winner_id\":112108,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112108,112126]}}', '2026-02-19 11:21:59'),
(38, 'MANUAL_WINNER_SET', 102, NULL, '{\"winner_id\":112098,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112098,112103]}}', '2026-02-19 11:22:00'),
(39, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:00'),
(40, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:15'),
(41, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:18'),
(42, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Nachr&uuml;cker\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:20'),
(43, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:23'),
(44, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112144,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:24'),
(45, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Nachr&uuml;cker\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:54'),
(46, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:55'),
(47, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:56'),
(48, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Nachr&uuml;cker\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:58'),
(49, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:22:59'),
(50, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112141,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:23:18'),
(51, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112140,\"reason\":\"Nachr&uuml;cker\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:23:31'),
(52, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:23:32'),
(53, 'MANUAL_WINNER_SET', 104, NULL, '{\"winner_id\":112144,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:23:33'),
(54, 'MANUAL_WINNER_REMOVED', 104, NULL, '{\"winner_id\":null,\"reason\":\"\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 11:24:21'),
(55, 'MANUAL_WINNER_SET', 100, NULL, '{\"winner_id\":112108,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112108,112126]}}', '2026-02-19 12:06:59'),
(56, 'MANUAL_WINNER_SET', 102, NULL, '{\"winner_id\":112098,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112098,112103]}}', '2026-02-19 12:06:59'),
(57, 'MANUAL_WINNER_SET', 100, NULL, '{\"winner_id\":112108,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112108,112126]}}', '2026-02-19 12:07:28'),
(58, 'MANUAL_WINNER_SET', 102, NULL, '{\"winner_id\":112098,\"reason\":\"Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112098,112103]}}', '2026-02-19 12:07:28'),
(59, 'MANUAL_WINNER_SET', 106, NULL, '{\"winner_id\":-112144,\"reason\":\"Dreier-Gleichstand - manuell\",\"pair\":{\"round\":1,\"participants\":[112140,112141,112144]}}', '2026-02-19 12:07:28');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `cupFinalResults`
--

CREATE TABLE `cupFinalResults` (
  `ID` int(11) NOT NULL,
  `ParticipantID` int(11) NOT NULL,
  `Result` int(11) DEFAULT NULL,
  `Year` int(4) NOT NULL,
  `LowShot` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `cupFinalResults`
--

INSERT INTO `cupFinalResults` (`ID`, `ParticipantID`, `Result`, `Year`, `LowShot`) VALUES
(92, 112108, 80, 2024, 80),
(93, 112139, 88, 2024, 88),
(94, 889594, 99, 2024, 99),
(96, 112098, 93, 2024, 93),
(103, 112101, 86, 2025, 86),
(105, 385067, 94, 2025, 94),
(107, 112144, 92, 2025, 92);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `cupPairs`
--

CREATE TABLE `cupPairs` (
  `ID` int(11) NOT NULL,
  `Participant1` int(11) NOT NULL,
  `Participant2` int(11) NOT NULL,
  `Participant3` int(11) DEFAULT NULL,
  `Result1` int(11) DEFAULT NULL,
  `Result2` int(11) DEFAULT NULL,
  `Result3` int(11) DEFAULT NULL,
  `LowShot1` int(11) DEFAULT NULL,
  `LowShot2` int(11) DEFAULT NULL,
  `LowShot3` int(11) DEFAULT NULL,
  `ManualWinner` int(11) DEFAULT NULL COMMENT 'ID des manuell gesetzten Gewinners',
  `ManualWinnerReason` varchar(255) DEFAULT NULL COMMENT 'Grund für manuelle Auswahl',
  `Round` int(11) NOT NULL,
  `Year` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `cupPairs`
--

INSERT INTO `cupPairs` (`ID`, `Participant1`, `Participant2`, `Participant3`, `Result1`, `Result2`, `Result3`, `LowShot1`, `LowShot2`, `LowShot3`, `ManualWinner`, `ManualWinnerReason`, `Round`, `Year`) VALUES
(52, 112139, 831789, NULL, 88, 65, NULL, 88, 65, NULL, NULL, NULL, 1, 2024),
(53, 112108, 385067, NULL, 85, 78, NULL, 85, 78, NULL, NULL, NULL, 1, 2024),
(54, 112134, 112111, NULL, 80, 85, NULL, 80, 85, NULL, NULL, NULL, 1, 2024),
(55, 112114, 112131, NULL, 92, 95, NULL, 92, 95, NULL, NULL, NULL, 1, 2024),
(56, 112103, 889594, NULL, 91, 96, NULL, 91, 96, NULL, NULL, NULL, 1, 2024),
(57, 112098, 112140, 112144, 97, 98, 95, 97, 98, 95, NULL, NULL, 1, 2024),
(58, 112139, 112111, 112108, 93, 87, 88, 93, 87, 88, NULL, NULL, 2, 2024),
(59, 112140, 889594, NULL, 95, 96, NULL, 95, 96, NULL, NULL, NULL, 2, 2024),
(60, 112098, 112131, NULL, 94, 94, NULL, 95, 94, NULL, NULL, NULL, 2, 2024),
(79, 112126, 385067, NULL, 84, 93, NULL, 84, 93, NULL, NULL, NULL, 1, 2025),
(80, 112144, 112103, NULL, 99, 92, NULL, 99, 92, NULL, NULL, NULL, 1, 2025),
(81, 112141, 112101, NULL, 90, 91, NULL, 90, 91, NULL, NULL, NULL, 1, 2025),
(82, 112131, 112114, NULL, 97, 93, NULL, 97, 93, NULL, 112114, 'Nachrücker', 1, 2025),
(85, 112114, 112101, NULL, 91, 91, NULL, 91, 92, NULL, NULL, NULL, 2, 2025),
(87, 112139, 831789, NULL, 88, 65, NULL, 88, 65, NULL, NULL, NULL, 1, 2024),
(88, 112108, 385067, NULL, 85, 78, NULL, 85, 78, NULL, NULL, NULL, 1, 2024),
(89, 112134, 112111, NULL, 80, 85, NULL, 80, 85, NULL, NULL, NULL, 1, 2024),
(90, 112114, 112131, NULL, 92, 95, NULL, 92, 95, NULL, NULL, NULL, 1, 2024),
(91, 112103, 889594, NULL, 91, 96, NULL, 91, 96, NULL, NULL, NULL, 1, 2024),
(92, 112098, 112140, 112144, 97, 98, 95, 97, 98, 95, NULL, NULL, 1, 2024),
(94, 112131, 112144, NULL, 90, 96, NULL, 90, 96, NULL, NULL, NULL, 2, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `cupStandFinal`
--

CREATE TABLE `cupStandFinal` (
  `ID` int(11) NOT NULL,
  `ParticipantName` varchar(255) NOT NULL,
  `club` varchar(255) NOT NULL,
  `Result` int(11) NOT NULL,
  `Year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `cupStandFinal`
--

INSERT INTO `cupStandFinal` (`ID`, `ParticipantName`, `club`, `Result`, `Year`) VALUES
(1, 'König Marcus', 'MSV Wilen', 96, 2024),
(2, 'Ott Arthur', 'SV Wollerau', 99, 2024),
(3, 'Ebnöther Peter', 'SV Freienbach', 89, 2024),
(31, 'Unterkofler Mark', 'MSV Wilen', 87, 2025),
(32, 'Dubach Urs', 'SV Wollerau', 91, 2025),
(33, 'Jehli Christian', 'SV Freienbach', 99, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `einsatz_zuweisungen`
--

CREATE TABLE `einsatz_zuweisungen` (
  `id` int(11) NOT NULL,
  `typ` varchar(50) NOT NULL,
  `bezeichnung` varchar(100) NOT NULL,
  `event_datum` date NOT NULL,
  `event_zeit` varchar(30) DEFAULT NULL,
  `funktion` varchar(100) NOT NULL,
  `mitglied_name` varchar(100) NOT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `jahr` int(11) NOT NULL,
  `dokument_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `einsatz_zuweisungen`
--

INSERT INTO `einsatz_zuweisungen` (`id`, `typ`, `bezeichnung`, `event_datum`, `event_zeit`, `funktion`, `mitglied_name`, `mitglied_id`, `jahr`, `dokument_id`, `created_at`) VALUES
(228, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Büro:Anmeldung', 'von Euw Alexander', 112140, 2026, 11, '2026-02-23 12:03:22'),
(229, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Anmeldung', 'Steinegger Josef', NULL, 2026, 11, '2026-02-23 12:03:22'),
(230, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 11, '2026-02-23 12:03:22'),
(231, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Standchef / Chef Sintro 300', 'Thomi Ivo', 548406, 2026, 11, '2026-02-23 12:03:22'),
(232, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Schützenmeister', 'König Marcus', 889594, 2026, 11, '2026-02-23 12:03:22'),
(233, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Schützenmeister', 'Schober Hanspeter', 112098, 2026, 11, '2026-02-23 12:03:22'),
(234, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Warner', 'von Euw Judith', 112111, 2026, 11, '2026-02-23 12:03:22'),
(235, 'obligatorisch', '1.Obligatorisch', '2026-05-27', '18:00 – 20:00', 'Parkplatzdienst', 'Müller Hans', 112134, 2026, 11, '2026-02-23 12:03:22'),
(236, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Büro:Anmeldung', 'Kälin Norbert', 112126, 2026, 11, '2026-02-23 12:03:22'),
(237, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Anmeldung', 'Schober Marco', 112103, 2026, 11, '2026-02-23 12:03:22'),
(238, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 11, '2026-02-23 12:03:22'),
(239, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Standchef / Chef Sintro 300', 'Linggi Renato', 112101, 2026, 11, '2026-02-23 12:03:22'),
(240, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Schützenmeister', 'Wittek Robin', 389561, 2026, 11, '2026-02-23 12:03:22'),
(241, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Schützenmeister', 'Hiestand Stefan', 112109, 2026, 11, '2026-02-23 12:03:22'),
(242, 'obligatorisch', '2.Obligatorisch', '2026-06-24', '18:00 – 20:00', 'Türkontrolle Ausgang (300m)', 'von Euw Christian', 112141, 2026, 11, '2026-02-23 12:03:22'),
(243, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Büro:Anmeldung', 'Linggi Andreas', 112097, 2026, 11, '2026-02-23 12:03:22'),
(244, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Anmeldung', 'Fuchs Michael', 112114, 2026, 11, '2026-02-23 12:03:22'),
(245, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 11, '2026-02-23 12:03:22'),
(246, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Standchef / Chef Sintro 300', 'Linggi Renato', 112101, 2026, 11, '2026-02-23 12:03:22'),
(247, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Schützenmeister', 'Lienert Roman', 112131, 2026, 11, '2026-02-23 12:03:22'),
(248, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Schützenmeister', 'Cavelti Roger', 112108, 2026, 11, '2026-02-23 12:03:22'),
(249, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Warner', 'Kälin Joshua', 831789, 2026, 11, '2026-02-23 12:03:22'),
(250, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Warner', 'Weinen Ingo', 29093, 2026, 11, '2026-02-23 12:03:22'),
(251, 'obligatorisch', '3.Obligatorisch', '2026-08-28', '18:00 – 20:00', 'Türkontrolle Eingang Schützenhaus', 'von Euw Stefan', 112144, 2026, 11, '2026-02-23 12:03:22'),
(252, 'einsatz', '29. Mai', '2026-05-29', '18:00 – 20:00', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 13, '2026-02-23 12:03:27'),
(253, 'einsatz', '29. Mai', '2026-05-29', '18:00 – 20:00', 'Standchef / Schützenmeister', 'Thomi Ivo', 548406, 2026, 13, '2026-02-23 12:03:27'),
(254, 'einsatz', '29. Mai', '2026-05-29', '18:00 – 20:00', 'Türkontrolle Eingang', 'Wittek Robin', 389561, 2026, 13, '2026-02-23 12:03:27'),
(255, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 13, '2026-02-23 12:03:27'),
(256, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Büro: Anmeldung', 'Unterkofler Mark', 385067, 2026, 13, '2026-02-23 12:03:27'),
(257, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Büro: Einteilung + Munition', 'Lienert Josef', 112102, 2026, 13, '2026-02-23 12:03:27'),
(258, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Schützenmeister', 'Urs Dubach', NULL, 2026, 13, '2026-02-23 12:03:27'),
(259, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Standchef / Schützenmeister', 'Cavelti Roger', 112108, 2026, 13, '2026-02-23 12:03:27'),
(260, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Speaker/ Warner', 'Bachmann Karl', 112105, 2026, 13, '2026-02-23 12:03:27'),
(261, 'einsatz', '30. Mai', '2026-05-30', '13:30 – 15:30', 'Türkontrolle Eingang', 'von Euw Christian', 112141, 2026, 13, '2026-02-23 12:03:27'),
(262, 'einsatz', '31. Mai', '2026-05-31', '09:30 – 11:30', 'EDV – Erfassung / Abrechnung', 'Cavelti Karin', NULL, 2026, 13, '2026-02-23 12:03:27'),
(263, 'einsatz', '31. Mai', '2026-05-31', '09:30 – 11:30', 'Warner / Standblatt-Kurier', 'Wittek Robin', 389561, 2026, 13, '2026-02-23 12:03:27'),
(324, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'Cavelti Roger', 112108, 2026, 17, '2026-02-23 12:04:59'),
(325, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'Kälin Joshua', 831789, 2026, 17, '2026-02-23 12:04:59'),
(326, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'Lienert Roman', 112131, 2026, 17, '2026-02-23 12:04:59'),
(327, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'Linggi Renato', 112101, 2026, 17, '2026-02-23 12:04:59'),
(328, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'von Euw Christian', 112141, 2026, 17, '2026-02-23 12:04:59'),
(329, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(330, 'einsatz', 'Wiler Chilbi 2026', '2026-11-18', '18:00-20:00', 'Zelt Aufstellen', 'Wittek Robin', 389561, 2026, 17, '2026-02-23 12:04:59'),
(331, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Cavelti Roger', 112108, 2026, 17, '2026-02-23 12:04:59'),
(332, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Fuchs Michael', 112114, 2026, 17, '2026-02-23 12:04:59'),
(333, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Hiestand Stefan', 112109, 2026, 17, '2026-02-23 12:04:59'),
(334, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Kälin Joshua', 831789, 2026, 17, '2026-02-23 12:04:59'),
(335, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Kälin Norbert', 112126, 2026, 17, '2026-02-23 12:04:59'),
(336, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Lienert Roman', 112131, 2026, 17, '2026-02-23 12:04:59'),
(337, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Linggi Renato', 112101, 2026, 17, '2026-02-23 12:04:59'),
(338, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Schober Marco', 112103, 2026, 17, '2026-02-23 12:04:59'),
(339, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Thomi Ivo', 548406, 2026, 17, '2026-02-23 12:04:59'),
(340, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Unterkofler Mark', 385067, 2026, 17, '2026-02-23 12:04:59'),
(341, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'von Euw Alexander', 112140, 2026, 17, '2026-02-23 12:04:59'),
(342, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'von Euw Christian', 112141, 2026, 17, '2026-02-23 12:04:59'),
(343, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(344, 'einsatz', 'Wiler Chilbi 2026', '2026-11-19', '17:00 - 22:00', 'Aufstellen', 'Wittek Robin', 389561, 2026, 17, '2026-02-23 12:04:59'),
(345, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '16:00-19:00', 'Aufstellen', 'Cavelti Roger', 112108, 2026, 17, '2026-02-23 12:04:59'),
(346, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Chef', 'Cavelti Roger', 112108, 2026, 17, '2026-02-23 12:04:59'),
(347, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Einsatz Stand', 'Fuchs Michael', 112114, 2026, 17, '2026-02-23 12:04:59'),
(348, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Einsatz Stand', 'Lienert Roman', 112131, 2026, 17, '2026-02-23 12:04:59'),
(349, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Einsatz Bar', 'von Euw Alexander', 112140, 2026, 17, '2026-02-23 12:04:59'),
(350, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Einsatz Bar', 'von Euw Monika', NULL, 2026, 17, '2026-02-23 12:04:59'),
(351, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '19:45 - 04:00', 'Einsatz Stand', 'von Euw Jasmin', NULL, 2026, 17, '2026-02-23 12:04:59'),
(352, 'einsatz', 'Wiler Chilbi 2026', '2026-11-20', '16:00-19:00', 'Aufstellen', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(353, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Stand', 'Kälin Norbert', 112126, 2026, 17, '2026-02-23 12:04:59'),
(354, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '12:00-20:00', 'Einsatz Stand', 'Linggi Andreas', 112097, 2026, 17, '2026-02-23 12:04:59'),
(355, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '12:00-20:00', 'Chef / Musik', 'Linggi Renato', 112101, 2026, 17, '2026-02-23 12:04:59'),
(356, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Bar', 'Schober Marco', 112103, 2026, 17, '2026-02-23 12:04:59'),
(357, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '12:00-20:00', 'Einsatz Bar', 'Schober Hanspeter', 112098, 2026, 17, '2026-02-23 12:04:59'),
(358, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Chef', 'Unterkofler Mark', 385067, 2026, 17, '2026-02-23 12:04:59'),
(359, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '12:00-20:00', 'Einsatz Stand', 'von Euw Christian', 112141, 2026, 17, '2026-02-23 12:04:59'),
(360, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Bar', 'von Euw Judith', 112111, 2026, 17, '2026-02-23 12:04:59'),
(361, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Bar', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(362, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Stand', 'Wittek Robin', 389561, 2026, 17, '2026-02-23 12:04:59'),
(363, 'einsatz', 'Wiler Chilbi 2026', '2026-11-21', '19:45 - 04:00', 'Einsatz Stand', 'Weinen Ingo', 29093, 2026, 17, '2026-02-23 12:04:59'),
(364, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Cavelti Roger', 112108, 2026, 17, '2026-02-23 12:04:59'),
(365, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Fuchs Michael', 112114, 2026, 17, '2026-02-23 12:04:59'),
(366, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Hiestand Stefan', 112109, 2026, 17, '2026-02-23 12:04:59'),
(367, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '12:00-18:00', 'Einsatz Bar', 'Kälin Joshua', 831789, 2026, 17, '2026-02-23 12:04:59'),
(368, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Kälin Joshua', 831789, 2026, 17, '2026-02-23 12:04:59'),
(369, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Lienert Roman', 112131, 2026, 17, '2026-02-23 12:04:59'),
(370, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Linggi Andreas', 112097, 2026, 17, '2026-02-23 12:04:59'),
(371, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Linggi Renato', 112101, 2026, 17, '2026-02-23 12:04:59'),
(372, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Schober Marco', 112103, 2026, 17, '2026-02-23 12:04:59'),
(373, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '12:00-18:00', 'Einsatz Bar', 'Sigrist Paul', 112137, 2026, 17, '2026-02-23 12:04:59'),
(374, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '12:00-18:00', 'Einsatz Stand', 'Rickenbach Markus', 110379, 2026, 17, '2026-02-23 12:04:59'),
(375, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Rickenbach Markus', 110379, 2026, 17, '2026-02-23 12:04:59'),
(376, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '12:00-18:00', 'Einsatz Stand', 'Thomi Ivo', 548406, 2026, 17, '2026-02-23 12:04:59'),
(377, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Thomi Ivo', 548406, 2026, 17, '2026-02-23 12:04:59'),
(378, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Unterkofler Mark', 385067, 2026, 17, '2026-02-23 12:04:59'),
(379, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'von Euw Alexander', 112140, 2026, 17, '2026-02-23 12:04:59'),
(380, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'von Euw Christian', 112141, 2026, 17, '2026-02-23 12:04:59'),
(381, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '12:00-18:00', 'Chef / Musik', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(382, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'von Euw Stefan', 112144, 2026, 17, '2026-02-23 12:04:59'),
(383, 'einsatz', 'Wiler Chilbi 2026', '2026-11-22', '18:00 - 22:00', 'Aufräumen', 'Wittek Robin', 389561, 2026, 17, '2026-02-23 12:04:59');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `einzelrangierungen`
--

CREATE TABLE `einzelrangierungen` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL COMMENT 'Jahr der Rangierung',
  `jmdefinition_id` int(11) NOT NULL COMMENT 'Referenz zum JM-Anlass',
  `mitglied_id` int(11) NOT NULL COMMENT 'Referenz zum Mitglied',
  `rang` int(11) NOT NULL COMMENT 'Platzierung (1, 2, 3, etc.)',
  `preis` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preis in CHF',
  `resultat` varchar(20) DEFAULT NULL COMMENT 'Erreichtes Resultat (z.B. Punkte, Ringe)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Einzelrangierungen von Mitgliedern bei JM-Anlässen mit Resultat';

--
-- Daten für Tabelle `einzelrangierungen`
--

INSERT INTO `einzelrangierungen` (`id`, `year`, `jmdefinition_id`, `mitglied_id`, `rang`, `preis`, `resultat`, `created_at`, `updated_at`) VALUES
(2, 2025, 103, 112131, 2, 50.00, '78', '2025-08-11 14:23:27', '2025-08-11 14:23:35'),
(3, 2025, 87, 112144, 1, 50.00, '135.7', '2025-08-11 14:23:53', '2025-08-11 14:23:53');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endresultate_partner`
--

CREATE TABLE `endresultate_partner` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `Jahr` int(11) NOT NULL,
  `PartnerName` varchar(100) NOT NULL,
  `EndstichSchuss1` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss2` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss3` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss4` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss5` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss6` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss7` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss8` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss9` decimal(4,1) DEFAULT 0.0,
  `EndstichSchuss10` decimal(4,1) DEFAULT 0.0,
  `SieErSchuss1` decimal(2,0) DEFAULT 0,
  `SieErSchuss2` decimal(2,0) DEFAULT 0,
  `SieErSchuss3` decimal(2,0) DEFAULT 0,
  `SieErSchuss4` decimal(2,0) DEFAULT 0,
  `SieErSchuss5` decimal(2,0) DEFAULT 0,
  `SieErSchuss6` decimal(2,0) DEFAULT NULL,
  `SieErSchuss7` decimal(2,0) DEFAULT NULL,
  `SieErSchuss8` decimal(2,0) DEFAULT NULL,
  `SieErSchuss9` decimal(2,0) DEFAULT NULL,
  `SieErSchuss10` decimal(2,0) DEFAULT NULL,
  `PartnerSchwiniSchuss1` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss2` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss3` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss4` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss5` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss6` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss7` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss8` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss9` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss10` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss11` decimal(4,1) DEFAULT 0.0,
  `PartnerSchwiniSchuss12` decimal(4,1) DEFAULT 0.0,
  `ErstelltAm` timestamp NOT NULL DEFAULT current_timestamp(),
  `AktualiertAm` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `endresultate_partner`
--

INSERT INTO `endresultate_partner` (`ID`, `MitgliedID`, `Jahr`, `PartnerName`, `EndstichSchuss1`, `EndstichSchuss2`, `EndstichSchuss3`, `EndstichSchuss4`, `EndstichSchuss5`, `EndstichSchuss6`, `EndstichSchuss7`, `EndstichSchuss8`, `EndstichSchuss9`, `EndstichSchuss10`, `SieErSchuss1`, `SieErSchuss2`, `SieErSchuss3`, `SieErSchuss4`, `SieErSchuss5`, `SieErSchuss6`, `SieErSchuss7`, `SieErSchuss8`, `SieErSchuss9`, `SieErSchuss10`, `PartnerSchwiniSchuss1`, `PartnerSchwiniSchuss2`, `PartnerSchwiniSchuss3`, `PartnerSchwiniSchuss4`, `PartnerSchwiniSchuss5`, `PartnerSchwiniSchuss6`, `PartnerSchwiniSchuss7`, `PartnerSchwiniSchuss8`, `PartnerSchwiniSchuss9`, `PartnerSchwiniSchuss10`, `PartnerSchwiniSchuss11`, `PartnerSchwiniSchuss12`, `ErstelltAm`, `AktualiertAm`) VALUES
(8, 112140, 2025, 'von Euw Monika', 10.0, 9.0, 9.0, 7.0, 8.0, 9.0, 7.0, 8.0, 8.0, 6.0, 9, 5, 7, 9, 7, 10, 8, 6, 5, 4, 9.0, 0.0, 9.0, 7.0, 0.0, 9.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-02 06:19:51', '2025-10-08 15:08:24'),
(22, 112108, 2025, 'Cavelti Karin', 5.0, 6.0, 4.0, 6.0, 5.0, 7.0, 5.0, 5.0, 6.0, 8.0, 3, 7, 7, 9, 5, 8, 8, 6, 8, 4, 0.0, 0.0, 10.0, 0.0, 8.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-12 15:58:15', '2025-10-12 16:25:19'),
(23, 112126, 2025, 'Hauser Franziska', 7.0, 7.0, 10.0, 8.0, 9.0, 10.0, 9.0, 8.0, 9.0, 6.0, 3, 5, 4, 6, 8, 8, 8, 10, 7, 7, 8.0, 0.0, 9.0, 0.0, 7.0, 6.0, 8.0, 6.0, 0.0, 0.0, 8.0, 0.0, '2025-10-12 16:03:14', '2025-10-12 16:26:13'),
(24, 112131, 2025, 'Lienert Miriam', 3.0, 3.0, 2.0, 4.0, 6.0, 7.0, 8.0, 7.0, 7.0, 8.0, 0, 2, 6, 8, 8, 10, 7, 10, 8, 9, 8.0, 8.0, 0.0, 8.0, 0.0, 10.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-12 16:05:24', '2025-10-12 16:27:55'),
(25, 112101, 2025, 'Linggi-Kaspar Ines', 8.0, 9.0, 7.0, 9.0, 9.0, 9.0, 8.0, 7.0, 10.0, 10.0, 7, 8, 6, 6, 7, 10, 8, 10, 9, 8, 0.0, 0.0, 0.0, 0.0, 8.0, 8.0, 5.0, 8.0, 0.0, 8.0, 10.0, 10.0, '2025-10-12 16:06:45', '2025-10-12 16:28:42'),
(26, 112102, 2025, 'Schober Rita', 8.0, 10.0, 9.0, 9.0, 8.0, 6.0, 5.0, 8.0, 7.0, 6.0, 8, 8, 9, 9, 0, 9, 10, 9, 7, 7, 0.0, 0.0, 10.0, 8.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-12 16:08:18', '2026-03-07 10:34:37'),
(28, 112144, 2025, 'von Euw Judith', 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0, 0, 4, 9, 3, 9, 10, 9, 5, 7, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-12 16:19:54', '2025-10-13 06:38:48'),
(29, 29093, 2025, 'Kistler Yvonne', 8.0, 6.0, 9.0, 4.0, 3.0, 6.0, 2.0, 6.0, 1.0, 4.0, 5, 8, 5, 7, 6, 9, 8, 10, 5, 3, 10.0, 0.0, 7.0, 7.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0, '2025-10-12 16:20:46', '2025-10-12 16:27:13');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich`
--

CREATE TABLE `endstich` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `Schuss1` int(2) DEFAULT NULL,
  `Schuss2` int(2) DEFAULT NULL,
  `Schuss3` int(2) DEFAULT NULL,
  `Schuss4` int(2) DEFAULT NULL,
  `Schuss5` int(2) DEFAULT NULL,
  `Schuss6` int(2) DEFAULT NULL,
  `Schuss7` int(2) DEFAULT NULL,
  `Schuss8` int(2) DEFAULT NULL,
  `Schuss9` int(2) DEFAULT NULL,
  `Schuss10` int(2) DEFAULT NULL,
  `Tiefschuss` int(3) NOT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate()),
  `AbsendenAnmeldung` int(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `endstich`
--

INSERT INTO `endstich` (`ID`, `MitgliedID`, `Schuss1`, `Schuss2`, `Schuss3`, `Schuss4`, `Schuss5`, `Schuss6`, `Schuss7`, `Schuss8`, `Schuss9`, `Schuss10`, `Tiefschuss`, `Jahr`, `AbsendenAnmeldung`) VALUES
(10, 112103, 8, 9, 7, 9, 9, 9, 8, 8, 8, 10, 97, 2024, 1),
(11, 112114, 10, 10, 10, 10, 10, 10, 9, 10, 10, 9, 99, 2024, 2),
(13, 112109, 9, 6, 7, 8, 8, 9, 10, 8, 8, 10, 99, 2024, 2),
(14, 385067, 9, 8, 10, 9, 8, 9, 9, 9, 8, 7, 94, 2024, 1),
(15, 112108, 9, 10, 9, 9, 8, 9, 9, 9, 9, 10, 96, 2024, 2),
(16, 831789, 10, 8, 7, 10, 9, 8, 8, 9, 9, 9, 100, 2024, 1),
(17, 112102, 10, 9, 9, 8, 10, 10, 8, 8, 8, 10, 97, 2024, 2),
(18, 112141, 10, 8, 8, 9, 10, 8, 10, 9, 9, 9, 95, 2024, 2),
(19, 112140, 9, 10, 9, 10, 9, 10, 10, 9, 9, 10, 99, 2024, 2),
(20, 112137, 9, 8, 9, 9, 9, 10, 10, 10, 8, 10, 98, 2024, 2),
(21, 889594, 10, 9, 10, 10, 9, 9, 9, 10, 10, 10, 100, 2024, 1),
(22, 112111, 9, 9, 8, 9, 10, 10, 8, 4, 8, 8, 98, 2024, 1),
(23, 112131, 10, 10, 10, 10, 10, 10, 10, 10, 10, 9, 97, 2024, 2),
(24, 548406, 4, 7, 6, 9, 8, 9, 9, 10, 9, 9, 94, 2024, 1),
(25, 112144, 10, 9, 9, 10, 10, 10, 10, 10, 9, 9, 98, 2024, 1),
(26, 112139, 9, 9, 8, 9, 8, 10, 8, 10, 9, 9, 96, 2024, 1),
(27, 112126, 10, 8, 10, 9, 10, 10, 8, 10, 10, 8, 94, 2024, 2),
(28, 112097, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2024, 1),
(29, 112104, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2024, 2),
(30, 112101, 10, 9, 9, 9, 10, 8, 10, 9, 9, 9, 95, 2024, 2),
(65, 112140, 7, 10, 9, 8, 10, 9, 10, 9, 9, 10, 98, 2025, 2),
(66, 385067, 9, 10, 10, 10, 8, 9, 9, 10, 9, 9, 99, 2025, 1),
(67, 112114, 9, 10, 10, 8, 9, 8, 10, 10, 9, 10, 97, 2025, 2),
(68, 112109, 8, 9, 9, 9, 9, 9, 8, 10, 10, 9, 95, 2025, 2),
(74, 112108, 9, 8, 8, 10, 9, 9, 9, 8, 8, 9, 91, 2025, 2),
(75, 831789, 7, 7, 6, 7, 9, 10, 6, 9, 8, 9, 91, 2025, 1),
(76, 112126, 7, 9, 10, 8, 10, 9, 10, 8, 9, 7, 97, 2025, 2),
(77, 112131, 9, 9, 10, 10, 9, 9, 10, 10, 10, 10, 97, 2025, 2),
(78, 112101, 9, 10, 10, 9, 10, 9, 9, 9, 8, 10, 97, 2025, 2),
(79, 112102, 10, 9, 9, 9, 10, 9, 8, 8, 10, 10, 96, 2025, 2),
(80, 112103, 10, 10, 8, 8, 10, 8, 9, 7, 8, 9, 95, 2025, 0),
(81, 112137, 10, 8, 9, 9, 9, 8, 8, 10, 8, 8, 100, 2025, 2),
(82, 112139, 8, 9, 10, 10, 10, 9, 9, 10, 10, 10, 95, 2025, 2),
(83, 548406, 9, 9, 8, 9, 10, 7, 7, 9, 10, 8, 93, 2025, 1),
(84, 112141, 10, 9, 9, 9, 10, 10, 9, 10, 10, 8, 99, 2025, 2),
(85, 112111, 9, 8, 9, 8, 8, 9, 10, 9, 8, 10, 93, 2025, 1),
(86, 112144, 10, 10, 10, 10, 10, 9, 9, 9, 10, 9, 100, 2025, 1),
(87, 29093, 8, 10, 9, 8, 9, 9, 10, 9, 7, 10, 94, 2025, 2);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_definition`
--

CREATE TABLE `endstich_definition` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `shots` int(11) NOT NULL DEFAULT 0,
  `price_cents` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `endstich_definition`
--

INSERT INTO `endstich_definition` (`id`, `code`, `name`, `shots`, `price_cents`, `active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'END', 'Endstich', 10, 2000, 1, 10, '2025-09-11 06:04:08', '2025-10-01 15:54:35'),
(2, 'SCHWINI_P1', 'Schwini P. 1', 8, 2000, 1, 20, '2025-09-11 06:04:08', '2025-09-26 08:38:12'),
(3, 'SCHWINI_P2', 'Schwini P. 2', 8, 1500, 1, 30, '2025-09-11 06:04:08', '2025-09-26 08:38:12'),
(4, 'KUNST', 'Kunst', 5, 1300, 1, 40, '2025-09-11 06:04:08', '2025-09-11 11:46:06'),
(5, 'GLUECK', 'Glück', 3, 800, 1, 50, '2025-09-11 06:04:08', '2025-09-11 11:46:10'),
(6, 'ZABIG', 'Zabig', 6, 1900, 1, 60, '2025-09-11 06:04:08', '2025-09-26 08:38:14'),
(7, 'DIFF', 'Differenzler', 0, 500, 1, 70, '2025-09-11 06:04:08', '2025-09-11 11:45:37'),
(8, 'SIEUNDER', 'Sie und Er', 5, 1000, 1, 80, '2025-09-11 06:04:08', '2025-09-11 12:14:57'),
(13, 'PROBE', 'Probeschüsse', 3, 0, 1, 15, '2025-10-06 07:24:06', '2025-10-06 07:24:06');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_gaeste`
--

CREATE TABLE `endstich_gaeste` (
  `id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL COMMENT 'Name des Gastes',
  `geburtsdatum` date DEFAULT NULL,
  `waffen_id` int(11) DEFAULT NULL,
  `vorname` varchar(100) DEFAULT NULL,
  `nachname` varchar(100) DEFAULT NULL,
  `jahr` int(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Gäste/Partner für Endschiessen';

--
-- Daten für Tabelle `endstich_gaeste`
--

INSERT INTO `endstich_gaeste` (`id`, `name`, `geburtsdatum`, `waffen_id`, `vorname`, `nachname`, `jahr`, `created_at`, `created_by`) VALUES
(20, 'von Euw Monika', NULL, 2, NULL, NULL, 2025, '2025-10-01 15:48:40', 'renato'),
(36, 'Cavelti Karin', NULL, 2, NULL, NULL, 2025, '2025-10-11 07:21:24', 'Renato'),
(37, 'Schober Rita', NULL, 2, NULL, NULL, 2025, '2025-10-11 07:54:57', 'Renato'),
(38, 'Vasapolli Leandro', '2006-04-09', 2, NULL, NULL, 2025, '2025-10-11 08:37:55', 'Renato'),
(39, 'Eberle Jeaninne', '2005-10-06', 2, NULL, NULL, 2025, '2025-10-11 08:39:08', 'Renato'),
(40, 'Linggi-Kaspar Ines', NULL, 2, NULL, NULL, 2025, '2025-10-11 08:41:24', 'Renato'),
(41, 'Hauser Franziska', NULL, 2, NULL, NULL, 2025, '2025-10-11 09:00:16', 'Renato'),
(42, 'Lienert Miriam', NULL, 2, NULL, NULL, 2025, '2025-10-11 09:07:06', 'Renato'),
(43, 'Kistler Yvonne', NULL, 2, NULL, NULL, 2025, '2025-10-11 11:48:18', 'Renato');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_jung`
--

CREATE TABLE `endstich_jung` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `Schuss1` int(11) DEFAULT NULL,
  `Schuss2` int(11) DEFAULT NULL,
  `Schuss3` int(11) DEFAULT NULL,
  `Schuss4` int(11) DEFAULT NULL,
  `Schuss5` int(11) DEFAULT NULL,
  `Schuss6` int(11) DEFAULT NULL,
  `Schuss7` int(11) DEFAULT NULL,
  `Schuss8` int(11) DEFAULT NULL,
  `Schuss9` int(11) DEFAULT NULL,
  `Schuss10` int(11) DEFAULT NULL,
  `Tiefschuss` int(11) DEFAULT NULL,
  `AbsendenAnmeldung` varchar(255) DEFAULT NULL,
  `Jahr` int(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `endstich_jung`
--

INSERT INTO `endstich_jung` (`ID`, `JungschuetzeID`, `Schuss1`, `Schuss2`, `Schuss3`, `Schuss4`, `Schuss5`, `Schuss6`, `Schuss7`, `Schuss8`, `Schuss9`, `Schuss10`, `Tiefschuss`, `AbsendenAnmeldung`, `Jahr`) VALUES
(4, 38, 10, 7, 10, 8, 8, 9, 9, 8, 7, 6, 95, '2', 2025),
(5, 39, 7, 9, 3, 7, 9, 7, 8, 8, 9, 8, 83, '0', 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_selection`
--

CREATE TABLE `endstich_selection` (
  `id` bigint(20) NOT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `gast_id` int(11) DEFAULT NULL,
  `jahr` int(11) NOT NULL,
  `stich_id` int(11) NOT NULL,
  `zahlungsmethode` varchar(20) DEFAULT 'bar',
  `gast_spezialpreis` int(11) DEFAULT NULL,
  `sie_und_er` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `endstich_selection`
--

INSERT INTO `endstich_selection` (`id`, `mitglied_id`, `gast_id`, `jahr`, `stich_id`, `zahlungsmethode`, `gast_spezialpreis`, `sie_und_er`, `created_at`, `created_by`) VALUES
(317, 112114, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(318, 112114, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(319, 112114, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(320, 112114, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(321, 112114, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(322, 112114, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(323, 112114, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-01 15:23:44', 'renato'),
(324, 112140, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(325, 112140, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(326, 112140, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(327, 112140, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(328, 112140, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(329, 112140, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(330, 112140, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(331, 112140, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-01 15:36:18', 'renato'),
(332, NULL, 20, 2025, 1, 'karte', 4500, 0, '2025-10-01 15:48:40', 'renato'),
(333, NULL, 20, 2025, 2, 'karte', 4500, 0, '2025-10-01 15:48:40', 'renato'),
(334, NULL, 20, 2025, 8, 'karte', 4500, 0, '2025-10-01 15:48:40', 'renato'),
(335, 385067, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(336, 385067, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(337, 385067, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(338, 385067, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(339, 385067, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(340, 385067, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(341, 385067, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-01 16:14:47', 'renato'),
(358, 112109, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(359, 112109, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(360, 112109, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(361, 112109, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(362, 112109, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(363, 112109, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(364, 112109, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-08 16:04:36', 'Renato'),
(465, 112108, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(466, 112108, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(467, 112108, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(468, 112108, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(469, 112108, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(470, 112108, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(471, 112108, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(472, 112108, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 07:20:47', 'Renato'),
(473, NULL, 36, 2025, 1, 'karte', 4500, 0, '2025-10-11 07:21:24', 'Renato'),
(474, NULL, 36, 2025, 2, 'karte', NULL, 0, '2025-10-11 07:21:24', 'Renato'),
(475, NULL, 36, 2025, 8, 'karte', NULL, 0, '2025-10-11 07:21:24', 'Renato'),
(476, 112101, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(477, 112101, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(478, 112101, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(479, 112101, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(480, 112101, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(481, 112101, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(482, 112101, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(483, 112101, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 07:27:48', 'Renato'),
(484, 112137, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(485, 112137, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(486, 112137, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(487, 112137, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(488, 112137, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(489, 112137, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(490, 112137, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 07:38:34', 'Renato'),
(491, 112139, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 07:45:24', 'Renato'),
(492, 112139, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 07:45:24', 'Renato'),
(493, 112139, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 07:45:24', 'Renato'),
(494, 112139, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 07:45:24', 'Renato'),
(495, 112139, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 07:45:24', 'Renato'),
(496, 112098, NULL, 2025, 1, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(497, 112098, NULL, 2025, 2, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(498, 112098, NULL, 2025, 3, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(499, 112098, NULL, 2025, 4, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(500, 112098, NULL, 2025, 5, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(501, 112098, NULL, 2025, 6, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(502, 112098, NULL, 2025, 7, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(503, 112098, NULL, 2025, 8, 'bar', NULL, 0, '2025-10-11 07:53:56', 'Renato'),
(504, NULL, 37, 2025, 8, 'karte', 4500, 0, '2025-10-11 07:54:57', 'Renato'),
(505, 112111, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(506, 112111, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(507, 112111, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(508, 112111, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(509, 112111, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(510, 112111, NULL, 2025, 6, 'karte', NULL, 1, '2025-10-11 08:24:07', 'Renato'),
(511, 112111, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(512, 112111, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 08:24:07', 'Renato'),
(513, 112144, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(514, 112144, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(515, 112144, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(516, 112144, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(517, 112144, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(518, 112144, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(519, 112144, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(520, 112144, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 08:25:49', 'Renato'),
(521, NULL, 38, 2025, 13, 'bar', 2000, 0, '2025-10-11 08:37:55', 'Renato'),
(522, NULL, 38, 2025, 1, 'bar', NULL, 0, '2025-10-11 08:37:55', 'Renato'),
(523, NULL, 38, 2025, 2, 'bar', NULL, 0, '2025-10-11 08:37:55', 'Renato'),
(524, NULL, 38, 2025, 6, 'bar', NULL, 0, '2025-10-11 08:37:55', 'Renato'),
(525, NULL, 39, 2025, 13, 'bar', 2000, 0, '2025-10-11 08:39:08', 'Renato'),
(526, NULL, 39, 2025, 1, 'bar', NULL, 0, '2025-10-11 08:39:08', 'Renato'),
(527, NULL, 39, 2025, 2, 'bar', NULL, 0, '2025-10-11 08:39:08', 'Renato'),
(528, NULL, 39, 2025, 6, 'bar', NULL, 0, '2025-10-11 08:39:08', 'Renato'),
(529, NULL, 40, 2025, 1, 'karte', 5900, 0, '2025-10-11 08:41:24', 'Renato'),
(530, NULL, 40, 2025, 2, 'karte', NULL, 0, '2025-10-11 08:41:24', 'Renato'),
(531, NULL, 40, 2025, 3, 'karte', NULL, 0, '2025-10-11 08:41:24', 'Renato'),
(532, NULL, 40, 2025, 8, 'karte', NULL, 0, '2025-10-11 08:41:24', 'Renato'),
(533, 112126, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(534, 112126, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(535, 112126, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(536, 112126, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(537, 112126, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(538, 112126, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(539, 112126, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 08:55:15', 'Renato'),
(540, NULL, 41, 2025, 1, 'karte', 5900, 0, '2025-10-11 09:00:16', 'Renato'),
(541, NULL, 41, 2025, 2, 'karte', NULL, 0, '2025-10-11 09:00:16', 'Renato'),
(542, NULL, 41, 2025, 8, 'karte', NULL, 0, '2025-10-11 09:00:16', 'Renato'),
(543, NULL, 42, 2025, 1, 'karte', 4500, 0, '2025-10-11 09:07:06', 'Renato'),
(544, NULL, 42, 2025, 2, 'karte', NULL, 0, '2025-10-11 09:07:06', 'Renato'),
(545, NULL, 42, 2025, 8, 'karte', NULL, 0, '2025-10-11 09:07:06', 'Renato'),
(546, 112131, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(547, 112131, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(548, 112131, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(549, 112131, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(550, 112131, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(551, 112131, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(552, 112131, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(553, 112131, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 09:07:50', 'Renato'),
(554, 548406, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(555, 548406, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(556, 548406, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(557, 548406, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(558, 548406, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(559, 548406, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(560, 548406, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 11:37:31', 'Renato'),
(561, 831789, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(562, 831789, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(563, 831789, NULL, 2025, 3, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(564, 831789, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(565, 831789, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(566, 831789, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(567, 831789, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 11:37:39', 'Renato'),
(568, 112103, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(569, 112103, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(570, 112103, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(571, 112103, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(572, 112103, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(573, 112103, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 11:40:12', 'Renato'),
(574, NULL, 43, 2025, 1, 'karte', 4500, 0, '2025-10-11 11:48:18', 'Renato'),
(575, NULL, 43, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:48:18', 'Renato'),
(576, NULL, 43, 2025, 8, 'karte', NULL, 0, '2025-10-11 11:48:18', 'Renato'),
(577, 29093, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 11:49:43', 'Renato'),
(578, 29093, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:49:43', 'Renato'),
(579, 29093, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 11:49:43', 'Renato'),
(580, 29093, NULL, 2025, 8, 'karte', NULL, 0, '2025-10-11 11:49:43', 'Renato'),
(581, NULL, 37, 2025, 1, 'karte', 4500, 0, '2025-10-11 11:53:38', 'Renato'),
(582, NULL, 37, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:53:38', 'Renato'),
(583, 112141, NULL, 2025, 1, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(584, 112141, NULL, 2025, 2, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(585, 112141, NULL, 2025, 4, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(586, 112141, NULL, 2025, 5, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(587, 112141, NULL, 2025, 6, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(588, 112141, NULL, 2025, 7, 'karte', NULL, 0, '2025-10-11 11:56:48', 'Renato'),
(589, NULL, 41, 2025, 3, 'karte', 5900, 0, '2025-10-11 13:48:27', 'Renato');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_spezialpreise`
--

CREATE TABLE `endstich_spezialpreise` (
  `id` int(11) NOT NULL,
  `typ` varchar(50) NOT NULL,
  `price_cents` int(11) NOT NULL,
  `beschreibung` varchar(200) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 100,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `endstich_spezialpreise`
--

INSERT INTO `endstich_spezialpreise` (`id`, `typ`, `price_cents`, `beschreibung`, `sort_order`, `active`) VALUES
(1, 'munition_pro_schuss', 50, 'Preis pro Schuss Munition', 10, 1),
(2, 'gast_kombi_2', 3500, 'Gäste: 2 Stiche aus End/Schwini', 20, 1),
(3, 'gast_kombi_3', 4900, 'Gäste: 3 Stiche aus End/Schwini', 30, 1),
(4, 'gast_sie_und_er', 1000, 'Gäste: Sie und Er Stich', 40, 1),
(5, 'partner_zabig', 1000, 'Partner-Preis für Zabigstich', 50, 1),
(6, 'munition_gp11_60', 3000, 'Standard-Paket: 60 Schuss GP11', 60, 1),
(7, 'munition_gp90_50', 2500, 'Standard-Paket: 50 Schuss GP90', 70, 1),
(8, 'js_paket_preis', 0, 'JS-Paket: Endstich 10 + Probe 3 + Schwini 8 + Zabig 6 = 27 Schuss', 100, 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `endstich_zusatz_schuss`
--

CREATE TABLE `endstich_zusatz_schuss` (
  `id` bigint(20) NOT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `gast_id` int(11) DEFAULT NULL,
  `jahr` int(11) NOT NULL,
  `typ` enum('GP11_60','GP90_50','GP11_CUSTOM','GP90_CUSTOM') NOT NULL,
  `anzahl` int(11) NOT NULL DEFAULT 0,
  `preis_cents` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `endstich_zusatz_schuss`
--

INSERT INTO `endstich_zusatz_schuss` (`id`, `mitglied_id`, `gast_id`, `jahr`, `typ`, `anzahl`, `preis_cents`, `created_at`, `created_by`) VALUES
(37, NULL, 20, 2025, 'GP90_CUSTOM', 10, 500, '2025-10-07 06:33:07', NULL),
(42, 112109, NULL, 2025, 'GP90_50', 50, 2500, '2025-10-09 09:02:41', NULL),
(45, 112108, NULL, 2025, 'GP90_CUSTOM', 100, 5000, '2025-10-11 07:20:54', NULL),
(46, NULL, 40, 2025, 'GP90_50', 50, 2500, '2025-10-11 08:41:24', 'Renato'),
(47, 112126, NULL, 2025, 'GP90_CUSTOM', 50, 2500, '2025-10-11 08:55:15', 'Renato'),
(48, NULL, 43, 2025, 'GP90_CUSTOM', 10, 500, '2025-10-11 11:48:18', 'Renato'),
(49, 29093, NULL, 2025, 'GP11_CUSTOM', 10, 500, '2025-10-11 11:49:43', 'Renato'),
(50, 112131, NULL, 2025, 'GP11_CUSTOM', 55, 2750, '2025-10-13 06:28:35', NULL),
(51, NULL, 42, 2025, 'GP90_CUSTOM', 7, 350, '2025-10-13 06:28:46', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `glueck`
--

CREATE TABLE `glueck` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `GSchuss1` int(11) DEFAULT NULL,
  `GSchuss2` int(11) DEFAULT NULL,
  `GSchuss3` int(11) DEFAULT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `glueck`
--

INSERT INTO `glueck` (`ID`, `MitgliedID`, `GSchuss1`, `GSchuss2`, `GSchuss3`, `Jahr`) VALUES
(10, 112103, 95, 84, 84, 2024),
(11, 112114, 81, 93, 89, 2024),
(12, 112101, 97, 83, 86, 2024),
(13, 112109, 88, 16, 82, 2024),
(14, 385067, 94, 81, 79, 2024),
(15, 112108, 91, 91, 81, 2024),
(16, 831789, 46, 50, 74, 2024),
(17, 112102, 81, 97, 79, 2024),
(18, 112141, 87, 96, 96, 2024),
(19, 112140, 85, 94, 96, 2024),
(20, 112137, 92, 91, 61, 2024),
(21, 889594, 96, 93, 93, 2024),
(22, 112111, 48, 94, 52, 2024),
(23, 112131, 89, 94, 87, 2024),
(24, 548406, 81, 73, 69, 2024),
(25, 112144, 91, 99, 90, 2024),
(26, 112139, 0, 0, 0, 2024),
(27, 112126, 90, 67, 86, 2024),
(28, 112097, 0, 0, 0, 2024),
(29, 112104, 0, 0, 0, 2024),
(66, 112140, 90, 86, 82, 2025),
(67, 385067, 79, 82, 89, 2025),
(68, 112114, 90, 87, 94, 2025),
(69, 112109, 89, 94, 87, 2025),
(75, 112108, 94, 83, 95, 2025),
(76, 831789, 90, 92, 68, 2025),
(77, 112126, 75, 90, 59, 2025),
(78, 112131, 96, 93, 89, 2025),
(79, 112101, 82, 81, 73, 2025),
(80, 112102, 96, 84, 92, 2025),
(81, 112103, 93, 77, 91, 2025),
(82, 112137, 82, 76, 94, 2025),
(83, 112139, 0, 0, 0, 2025),
(84, 548406, 74, 74, 72, 2025),
(85, 112141, 87, 84, 77, 2025),
(86, 112111, 94, 71, 87, 2025),
(87, 112144, 84, 90, 95, 2025),
(88, 29093, 0, 0, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `glueck_jung`
--

CREATE TABLE `glueck_jung` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `GSchuss1` int(11) DEFAULT NULL,
  `GSchuss2` int(11) DEFAULT NULL,
  `GSchuss3` int(11) DEFAULT NULL,
  `Jahr` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `heimresultate`
--

CREATE TABLE `heimresultate` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `Passe1` int(11) DEFAULT NULL,
  `Passe2` int(11) DEFAULT NULL,
  `Passe3` int(11) DEFAULT NULL,
  `Passe4` int(11) DEFAULT NULL,
  `Passe5` int(11) DEFAULT NULL,
  `Passe6` int(11) DEFAULT NULL,
  `Passe7` int(11) DEFAULT NULL,
  `Passe8` int(11) DEFAULT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `heimresultate`
--

INSERT INTO `heimresultate` (`ID`, `MitgliedID`, `Passe1`, `Passe2`, `Passe3`, `Passe4`, `Passe5`, `Passe6`, `Passe7`, `Passe8`, `Jahr`) VALUES
(52, 112108, 92, 88, 90, 92, 85, 93, 90, 90, 2024),
(53, 112114, 95, 91, 94, 96, 94, 93, 96, 96, 2024),
(54, 112109, 86, 85, 75, 85, 87, 83, 72, 84, 2024),
(55, 385067, 87, 87, 94, 91, 90, 94, 95, 94, 2024),
(56, 112144, 95, 93, 94, 96, 90, 96, 97, 96, 2024),
(57, 831789, 78, 0, 0, 0, 0, 0, 0, 0, 2024),
(58, 112126, 89, 92, 90, 89, 85, 84, 84, 87, 2024),
(59, 889594, 95, 97, 97, 97, 98, 94, 97, 94, 2024),
(60, 112131, 95, 96, 98, 95, 96, 93, 94, 95, 2024),
(61, 112102, 90, 96, 95, 94, 96, 88, 97, 91, 2024),
(62, 112140, 95, 93, 97, 93, 95, 93, 94, 94, 2024),
(63, 112111, 85, 93, 95, 86, 0, 0, 0, 0, 2024),
(64, 112108, 92, 89, 90, 85, 79, 88, 95, 88, 2025),
(65, 112114, 95, 93, 94, 95, 91, 94, 92, 91, 2025),
(66, 112126, 89, 86, 88, 91, 90, 89, 85, 88, 2025),
(67, 112131, 98, 94, 95, 97, 97, 95, 95, 97, 2025),
(68, 112101, 91, 97, 91, 91, 92, 93, 94, 90, 2025),
(69, 112102, 88, 94, 0, 0, 0, 0, 0, 0, 2025),
(70, 385067, 95, 85, 85, 93, 90, 90, 93, 89, 2025),
(71, 112140, 93, 94, 96, 98, 96, 92, 96, 93, 2025),
(72, 112144, 98, 92, 97, 95, 96, 94, 95, 95, 2025),
(73, 112109, 0, 0, 0, 0, 82, 92, 0, 0, 2025),
(74, 889594, 0, 0, 0, 0, 0, 0, 0, 0, 2025),
(75, 112097, 0, 0, 0, 0, 0, 0, 0, 0, 2025),
(76, 112141, 0, 0, 0, 0, 0, 0, 0, 0, 2025),
(77, 112111, 90, 94, 90, 92, 91, 95, 90, 88, 2025),
(78, 112137, 0, 0, 88, 94, 0, 0, 0, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `interne_stichdefinition`
--

CREATE TABLE `interne_stichdefinition` (
  `id` int(11) NOT NULL,
  `stich` varchar(50) NOT NULL,
  `nummer1` varchar(30) DEFAULT NULL,
  `nummer2` varchar(30) DEFAULT NULL,
  `nummer3` varchar(30) DEFAULT NULL,
  `restable` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `interne_stichdefinition`
--

INSERT INTO `interne_stichdefinition` (`id`, `stich`, `nummer1`, `nummer2`, `nummer3`, `restable`, `created_at`, `updated_at`) VALUES
(1, 'Heimmeisterschaft', '133', '134', '521', 'heimresultate', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(2, 'Kantonalstich', '520', '', '', 'kantiresultate', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(3, 'Endstich', '522', '', '', 'endstich', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(4, 'Schwini', '526', '', '', 'schwini', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(5, 'Kunst', '523', '', '', 'kunst', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(6, 'Glück', '524', '', '', 'glueck', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(7, 'Zabig', '525', '', '', 'zabig', '2025-08-14 13:10:04', '2025-08-18 06:04:55'),
(36, 'Sie und Er', '527', NULL, NULL, 'endresultate_partner', '2025-08-20 18:30:26', '2025-08-20 18:50:32');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMDefinition`
--

CREATE TABLE `JMDefinition` (
  `ID` int(11) NOT NULL,
  `Reihenfolge` int(2) NOT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `Maxpunkte` int(3) NOT NULL,
  `Streicher` tinyint(1) NOT NULL,
  `hidden` int(1) NOT NULL,
  `year` year(4) NOT NULL DEFAULT current_timestamp(),
  `Erweitert` tinyint(1) NOT NULL DEFAULT 0,
  `Schiesstage` text DEFAULT NULL,
  `Info` tinyint(1) NOT NULL DEFAULT 0,
  `Gruppe` tinyint(1) NOT NULL,
  `Adresse` varchar(255) DEFAULT NULL,
  `Zuschlag` int(2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `JMDefinition`
--

INSERT INTO `JMDefinition` (`ID`, `Reihenfolge`, `Bezeichnung`, `Maxpunkte`, `Streicher`, `hidden`, `year`, `Erweitert`, `Schiesstage`, `Info`, `Gruppe`, `Adresse`, `Zuschlag`) VALUES
(1, 1, 'Obligatorisch', 20, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(2, 4, 'Feldschiessen', 20, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(7, 7, 'Bester Kantonalstich', 100, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(8, 12, '1. Sektionsmeisterschaft', 100, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(9, 10, 'Jubiläumswettkampf SKSG', 120, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(10, 14, '34. Schlossturmschiessen', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(13, 15, '13. Zürcher Oberländer Maischiessen', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(14, 16, '10. Buechwaldschüssa Uznach', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(15, 17, '58. Gasterländer Frühlingsschiessen ', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(16, 18, 'Einzelwettschiessen', 200, 0, 0, '2024', 0, '', 0, 0, NULL, 0),
(17, 19, 'Standcup Roggenacker', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(18, 20, 'RSV Verbandschiessen Schübelbach ', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(19, 21, 'Etzelbundschiessen Wollerau', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(20, 22, 'Bünder Kantonalschützenfest', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(21, 23, '12. Rossbergschiessen ', 50, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(22, 24, 'Muota Schiessen SG Muotathal', 80, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(23, 25, '13. Burg-Schwanau-Schiessen Lauerz ', 80, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(24, 26, 'Muota-Schiessen SV Ibach-Schönenbuch ', 80, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(25, 27, '39. Roggenstockschiessen ', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(26, 28, '26. Hirschfluhschiessen ', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(27, 29, 'Endstich', 100, 1, 0, '2024', 0, '', 0, 0, NULL, 0),
(36, 30, 'SSM 2024', 100, 0, 1, '2024', 0, '', 0, 0, NULL, 0),
(69, 33, 'Obligatorisch', 20, 0, 0, '2025', 0, '', 0, 0, '', 0),
(71, 34, 'Bester Kantonalstich', 100, 0, 0, '2025', 0, '', 0, 0, '', 0),
(72, 13, 'Feldschiessen', 20, 0, 0, '2025', 0, 'Freitag 23. Mai 2025 18:00 - 20:00\r\nSamstag 24. Mai 2025 09:30 - 11:30\r\nSonntag 25. Mai 2025 09:30 - 11:30', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(73, 1, '43. Hanslin Gedenk Schiessen', 100, 0, 0, '2025', 1, 'Freitag 14. März 2025 14:00 – 17:00 Uhr\r\nSamstag 15. März 2025 08:00 – 12:00 Uhr, 13:00 – 15:30 Uhr\r\nSonntag 16. März 2025 08:00 – 11:30 Uhr\r\nSamstag 22. März 2025 08:00 – 12:00 Uhr', 0, 1, '', 0),
(74, 2, '35. Schlossturmschiessen', 100, 1, 0, '2025', 0, 'Samstag 12. April 2025 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr\r\nSonntag 13. April 2025 09:30 – 11:30 Uhr\r\nSamstag 26. April 2025 08:00 – 12:00 Uhr', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(75, 3, '175 Jahre FSS Feusisberg', 100, 1, 0, '2025', 0, 'Freitag 04. April 2025 14:00 - 18:00\r\nSamstag 05. April 2025 08:00 - 12:00, 13:00-18:00\r\nMontag 07. April 2025 18.00 - 19.30\r\nFreitag 11. April 2025 14:00 - 18:00\r\nSamstag 12. April 2025 08:00 - 12:00, 13:00-18:00\r\nSonntag 13. April 2025 09:00 - 11:30', 0, 0, 'Feusisgartenstrasse 21, 8835 Feusisberg', 2),
(76, 16, 'RSV Schiessen March-Höfe', 100, 1, 0, '2025', 0, 'Freitag 13. Juni 2025 16:00 - 20:00\r\nSamstag 14. Juni 2025 08:00 - 12:00, 13:30 - 17:00', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 2),
(77, 17, '13. Rossbergschiessen', 50, 1, 0, '2025', 0, 'Freitag 08. August 2025 16:00 - 20:00\r\nSamstag 09. August 2025 08:00 - 12:00, 13:00 - 17:00\r\nSonntag 10. August 2025 08:00 - 12:00', 0, 0, 'Rossbergstrasse 51, 8834 Schindellegi', 1),
(78, 8, 'Hist. Rothenthurm', 50, 0, 0, '2025', 1, 'Samstag 03. Mai 2025 08:00 - 12:00, 13:30 - 17:00\r\nSonntag 04. Mai 2025 08:00 - 12:00', 0, 0, '', 0),
(79, 22, '27. Hirschflueschiessen', 100, 1, 0, '2025', 0, 'Freitag 15. August 2025 16.00 - 20.00\r\nSamstag 16. August 2025 08.00 - 12.00\r\nSamstag 23. August 2025 08.00-12.00, 13.30-13.30\r\nSonntag 24. August 2025 08.00-12.00', 0, 0, 'Waagtalstrasse 49, 8842 Unteriberg', 2),
(80, 21, '40. Roggenstockschiessen', 100, 1, 0, '2025', 0, 'Freitag 15. August 2025 17.00 - 19.30\r\nSamstag 16. August 2025 08.00 - 12.00, 13.30 - 18.00\r\nSamstag 23. August 2025 08.00 - 12.00, 13.30 - 18.00\r\nSonntag 24. August 2025 08.00 - 12.00', 0, 0, 'Laucherenstrasse 70, 8843 Oberiberg', 1),
(82, 18, '150 Jahre FSG Burg Schwyz', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0, 'Schlagstrasse 129, 6423 Seewen', 0),
(83, 19, 'Muota-Schiessen Ibach', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0, 'Landsgemeindestrasse 80, 6438 Ibach', 0),
(84, 20, 'Muota-Schiessen Muotathal', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0, 'Lustnau, 6436 Muotathal', 0),
(85, 5, 'Einzelwettschiessen', 200, 0, 0, '2025', 0, 'Samstag 26. April 2025 13:30 - 17:30\r\nSonntag 27. April 2025 09:30 - 11:30', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(86, 14, 'MSV Wilen Vereinscup', 100, 1, 0, '2025', 0, 'Samstag 24. Mai 13:00 - 17:00', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(87, 10, 'Sihlseeschiessen Trachslau', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 16.00 - 20.00 Uhr\r\nSamstag 10. Mai 2025 08.00-12.00, 13.30-17.00 Uhr\r\nSamstag 17. Mai 2025 08.00-12.00, 13.30-17.00 Uhr\r\nSonntag 18. Mai 2025 09.00-11.30 Uhr', 0, 0, 'Glarnern, 8840 Trachslau', 2),
(88, 4, 'Fahrtschiessen Mollis', 50, 0, 0, '2025', 1, 'Samstag 12. April 2025 14:00 - 16:30\r\nSonntag 13. April 2025 08:00 - 11:30', 0, 0, '', 0),
(90, 6, '6. Kreiselschiessen Betzholz', 100, 1, 0, '2025', 0, 'Samstag 26. April 2025 08:00 - 12:00, 13:30 - 17:00\r\nFreitag 09. Mai 2025 17:00 - 20:00\r\nSamstag 10. Mai 2025 08:00 - 12:00, 13:30 - 16:30', 0, 0, 'Hinwil', 2),
(91, 7, '14. Zürcher Oberländer Maischiessen', 100, 1, 0, '2025', 0, 'Samstag 26. April 2025 08:00 - 11:30, 13:30 - 16:30\r\nFreitag 09. Mai 2025 16:00 - 19:30\r\nSamstag 10. Mai 2025 08:00 - 11:30, 13:30 - 16:30', 0, 0, 'Erlosenstrasse, 8620 Wetzikon', 2),
(92, 28, 'Endstich', 100, 1, 0, '2025', 0, 'Samstag 11. Oktober 09:00 - 11:30, 13:00 - 16:00', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(93, 35, 'Sektionsmeisterschaft', 100, 0, 0, '2025', 0, '', 0, 0, '', 0),
(94, 31, 'Absenden', 0, 0, 0, '2025', 0, 'Samstag 15. November 18:00 - 23:30', 1, 0, '', 0),
(98, 30, 'Chlaustournee', 80, 0, 0, '2025', 1, 'Samstag 29. November 08:00 - 12:00, 13:00 - 16:00\r\nSamstag 06. Dezember 08:00 - 12:00, 13:00 - 16:00\r\nSonntag 07. Dezember 08:00 - 12:00', 0, 1, '', 0),
(99, 25, '40. Generalwerdmüllerschiessen', 1, 0, 0, '2025', 1, 'Freitag 26. September 17:00 – 19:00 Uhr\r\nSamstag 27. September  08:00 – 12:00,  13:30 – 16:00 Uhr\r\nSamstag 04. Oktober 08:00 – 12:00,  13:30 – 17:00 Uhr', 0, 1, 'Kleinweidweg 20, 8820 Wädenswil', 0),
(100, 27, '53. Bockenkriegschiessen', 0, 0, 0, '2025', 1, 'Freitag 26. September 15:00 – 19:00\r\nSamstag 27. September  08:00 – 12:00,  14:00 – 16:00\r\nSamstag 04. Oktober 08:00 – 12:00,  14:00 – 16:00', 0, 1, 'Mühletalweg 6, 8810 Horgen', 0),
(101, 26, 'Herbstschiessen Stäfa', 0, 0, 0, '2025', 1, 'Freitag 19. September 14:00 – 19:00\r\nSamstag 20. September  08:30 – 12:00,  13:30 – 16:30\r\nSamstag 27. September  08:30 – 12:00,  13:30 – 16:30', 0, 1, 'Bergstrasse 200, 8712 Stäfa', 0),
(103, 11, '125 Jahre SV Tell', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 17:00 - 20:00\r\nSamstag 10. Mai 2025 09:00 - 12:00, 13:30 - 17:00\r\nSamstag 17. Mai 2025 09:00 - 12:00, 13:30 - 17:00\r\nSonntag 18. Mai 2025 09:00 - 12:00', 0, 0, 'Rietstrasse, 8840 Einsiedeln', 2),
(104, 9, '130 Jahre SG Bennau', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 15:00 -19:30\r\nSamstag 10. Mai 2025 08:00 – 12:00, 13:30 – 17.30\r\nSamstag 17. Mai 2025 08:00 – 12:00, 13:30 – 17:30\r\nSonntag 18. Mai 2025 08:30 – 11:30', 0, 0, 'Moosstrasse 12, 8836 Bennau', 2),
(105, 12, 'Gemeinsames Mittagessen (Seefeld)', 0, 0, 0, '2025', 0, '10. Mai 12.00-13.00', 1, 0, '', 0),
(106, 23, 'Gemeinsame Mittagessen (Sager)', 0, 0, 0, '2025', 0, '23. August 12.00-13.00', 1, 0, 'Tschalunstrasse 35, 8843 Oberiberg', 0),
(108, 29, 'Gemeinsamen Mittagessen (Schützenstube)', 0, 0, 0, '2025', 0, '11. Oktober 12.00-13.00', 1, 0, '', 0),
(109, 15, 'Kantonaler GM-Final Gewehr und Pistole', 0, 0, 0, '2025', 0, 'Samstag 31. Mai 2025 07.30 - 17.00\r\nim Cholmattli, Rothenthurm', 1, 0, '8, 6418 Rothenthurm', 0),
(119, 24, 'Familienausflug', 0, 0, 0, '2025', 0, 'Samstag 13. September', 1, 0, '', 0),
(126, 1, 'Generalversammlung', 0, 0, 0, '2026', 0, 'Freitag 06. März 2026 19:00 - 23:00', 1, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(127, 14, 'Eidgenössisches Schützenfest Chur', 100, 1, 0, '2026', 0, 'Freitag 26. Juni 2026 07:30 - 12:00', 0, 0, 'Pulvermühlestrasse 90, 7000 Chur', 0),
(128, 2, '36. Schlossturmschiessen', 100, 1, 0, '2026', 0, 'Samstag 18. April 2026 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr\r\nSonntag 19. April 2026 09:30 – 11:30 Uhr\r\nSamstag 25. April 2026 08:00 – 12:00 Uhr', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(129, 25, 'MSV Wilen Absenden', 0, 0, 0, '2026', 0, 'Samstag 14. November 2026 18:00 - 23:59', 1, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(131, 3, '125 Jahre Brunnen-Ingenbohl', 100, 1, 0, '2026', 0, 'Samstag 18. April 2026 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr\r\nSonntag 19. April 2026 09:30 – 11:30 Uhr\r\nFreitag 24. April 2026 16:00 - 20:00 Uhr\r\nSamstag 25. April 2026 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr', 0, 1, '47.04548, 8.63186', 0),
(132, 12, 'RSV Schiessen March-Höfe (Tuggen)', 100, 1, 0, '2026', 0, 'Freitag 05. Juni 2026 17:00 - 20:00\r\nSamstag 06. Juni 2026 08:00 - 11:30, 13:30 - 17:00\r\nFreitag 12. Juni 2026 17:00 - 20:00\r\nSamstag 13. Mai 2026 13:30 - 17:00', 0, 0, '47.21096475048054, 8.964247835582343', 0),
(133, 13, '63. Etzelbundschiessen (Oberiberg)', 100, 1, 0, '2026', 0, 'Dienstag 09. Juni 2026 18:00 - 20:00\r\nFreitag 12. Juni 17:00 - 20:00\r\nSamstag 13. Juni 08:00 - 12:00, 13:30 - 17:00', 0, 0, 'Laucherenstrasse 70, 8843 Oberiberg', 0),
(134, 15, '14. Rossbergschiessen', 50, 1, 0, '2026', 0, 'Freitag 07. August 2026 16:00 - 20:00\r\nSamstag 08. August 2026 08:00 - 12:00, 13:00 - 17:00\r\nSonntag 09. August 2026 08:00 - 12:00', 0, 0, 'Rossbergstrasse 51, 8834 Schindellegi', 0),
(135, 16, 'Muota-Schiessen SV Ibach-Schönenbuch', 80, 1, 0, '2026', 0, 'Freitag 7. August 2026 17.00 - 20.00\r\nSamstag 8. August 2026 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 21. August 2026 17.00 - 20.00 \r\nSamstag 22. August 2026 08.00 - 12.00, 13.30 - 16.00', 0, 0, 'Landsgemeindestrasse 80, 6438 Ibach', 0),
(136, 17, 'Muota-Schiessen SV Sattel', 80, 1, 0, '2026', 0, 'Freitag 7. August 2026 17.00 - 20.00\r\nSamstag 8. August 2026 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 21. August 2026 17.00 - 20.00 \r\nSamstag 22. August 2026 08.00 - 12.00, 13.30 - 16.00', 0, 0, 'Müllernstrasse, 6418 Rothenthurm', 0),
(137, 19, '41. Roggenstockschiessen', 100, 1, 0, '2026', 0, 'Freitag 14. August 2026 17.00 - 19.30\r\nSamstag 15. August 2026 08.00 - 12.00, 13.30 - 18.00\r\nSamstag 22. August 2026 08.00 - 12.00, 13.30 - 18.00\r\nSonntag 23. August 2026 08.00 - 12.00', 0, 0, 'Laucherenstrasse 70, 8843 Oberiberg', 0),
(138, 20, '28. Hirschflueschiessen', 100, 1, 0, '2026', 0, 'Freitag 14. August 2026 17.00 - 19.30\r\nSamstag 15. August 2026 08.00 - 12.00, 13.30 - 18.00\r\nSamstag 22. August 2026 08.00 - 12.00, 13.30 - 18.00\r\nSonntag 23. August 2026 08.00 - 12.00', 0, 0, 'Waagtalstrasse 49, 8842 Unteriberg', 0),
(140, 24, 'Winterschiessen Höfe (SV Freienbach)', 100, 0, 0, '2026', 1, 'Samstag 07. November 2026 09:30 - 11:30, 13:30 - 15:30', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(141, 27, 'Obligatorisch', 20, 0, 0, '2026', 0, '', 0, 0, '', 0),
(142, 10, 'Feldschiessen', 20, 0, 0, '2026', 0, 'Freitag 29. Mai 2026 18:00 - 20:00\r\nSamstag 30. Mai 2026 13:30 - 15:30\r\nSonntag 31. Mai 2026 09:30 - 11:30', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(143, 29, 'Sektionsmeisterschaft', 100, 0, 0, '2026', 0, '', 0, 0, '', 0),
(144, 30, 'Bester Kantonalstich', 100, 0, 0, '2026', 0, '', 0, 0, '', 0),
(145, 4, '60. Gasterländer Frühlingsschiessen', 100, 1, 0, '2026', 0, 'Freitag 24. April 2026 16:00 - 19:00 Uhr\r\nSamstag 25. April 2026 08:00 - 12:00, 13:00 - 18:00 Uhr\r\nFreitag 02. Mai 2026 16:00 - 19:00 Uhr\r\nSamstag 03. Mai 2026 08:00 - 12:00, 13:00 - 18:00 Uhr', 0, 0, 'Rietstrasse, 8723 Maseltrangen', 0),
(146, 5, '11. Buechwaldschüssä', 100, 1, 0, '2026', 0, 'Freitag 24. April 2026 15:00 - 19:00\r\nSamstag 25. April 2026 08:00 - 12:00, 13:30 - 18:00\r\nSamstag 02. Mai 2026 08:00 - 12:00, 13:30 - 16:00', 0, 0, '47.2300, 8.9831', 0),
(147, 6, '15. Zürcher Oberländer Maischiessen 2026', 100, 1, 0, '2026', 0, 'Samstag 25. April 2026 08:00 - 11:30, 13:30 - 16:30\r\nFreitag 08. Mai 2026 13:30 - 19:30\r\nSamstag 09. Mai 2026 08:00 - 11:30, 13:30 - 16:30', 0, 0, '47.3166/8.8206', 0),
(148, 26, 'Chlaustournee', 0, 0, 0, '2026', 1, 'Samstag 28. November 2026 08:00 - 12:00, 13:00 - 16:00\r\nSamstag 05. Dezember 2026 08:00 - 12:00, 13:00 - 16:00\r\nSonntag 06. Dezember 2026 08:00 - 12:00', 0, 1, '', 0),
(149, 9, 'Kant. GM-Final Gewehr und Pistole', 0, 0, 0, '2026', 0, 'Samstag, 23. Mai 2026 07:30 - 17:00', 1, 0, '47.124160151141545, 8.693927', 0),
(150, 7, 'Einzelwettschiessen', 200, 0, 0, '2026', 0, 'Samstag 25. April 2026 13:30 - 17:30\r\nSonntag 26. April 2026 09:30 - 11:30', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(151, 11, 'MSV Wilen Vereinscup', 100, 1, 0, '2026', 0, 'Samstag 30. Mai 2026 08:30 - 12:00', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(152, 22, 'Endstich', 100, 1, 0, '2026', 0, 'Samstag 10. Oktober 09:00 - 11:30, 13:00 - 16:00', 0, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(156, 8, 'Gemeinsames Mittagessen Roggenacker', 0, 0, 0, '2026', 0, 'Samstag 25. April 2026 12:00 - 13:00', 1, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(157, 21, 'Gemeinsame Mittagessen (Sager)', 0, 0, 0, '2026', 0, 'Samstag 22. August 2026 12:00 - 13:00', 1, 0, 'Tschalunstrasse 35, 8843 Oberiberg', 0),
(158, 31, 'Generalversammlung 2027', 0, 0, 0, '2026', 0, 'Freitag 05. März 2027 19:00 - 23:00', 1, 0, 'Eichenstrasse 18, 8808 Pfäffikon', 0),
(161, 18, 'Muota-Schiessen SG Muotathal', 80, 1, 0, '2026', 0, 'Freitag 7. August 2026 17.00 - 20.00\r\nSamstag 8. August 2026 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 21. August 2026 17.00 - 20.00 \r\nSamstag 22. August 2026 08.00 - 12.00, 13.30 - 16.00', 0, 0, '46.97696260888294, 8.736090773303438', 0),
(162, 23, 'Gemeinsames Mittagessen Roggenacker', 0, 0, 0, '2026', 0, 'Samstag 10. Oktober 2026 11:30 - 13:00', 1, 0, '', 0),
(163, 28, 'Jubiläumsstich 25 Jahre RSV March-Höfe', 120, 0, 0, '2026', 0, '', 0, 0, '', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMDefinition2024`
--

CREATE TABLE `JMDefinition2024` (
  `ID` int(11) NOT NULL,
  `Reihenfolge` int(2) NOT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `Maxpunkte` int(3) NOT NULL,
  `Streicher` tinyint(1) NOT NULL,
  `hidden` int(1) NOT NULL,
  `year` year(4) NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `JMDefinition2024`
--

INSERT INTO `JMDefinition2024` (`ID`, `Reihenfolge`, `Bezeichnung`, `Maxpunkte`, `Streicher`, `hidden`, `year`) VALUES
(1, 1, 'Obligatorisch', 20, 0, 0, '2024'),
(2, 2, 'Feldschiessen', 20, 0, 0, '2024'),
(7, 3, 'Bester Kantonalstich', 100, 0, 0, '2024'),
(8, 5, '1. Sektionsmeisterschaft', 100, 0, 0, '2024'),
(9, 4, 'Jubiläumswettkampf SKSG', 120, 0, 0, '2024'),
(10, 7, '34. Schlossturmschiessen', 100, 1, 0, '2024'),
(12, 6, '2. Sektionsmeisterschaft', 100, 0, 0, '2024'),
(13, 8, '13. Zürcher Oberländer Maischiessen', 100, 1, 0, '2024'),
(14, 9, '10. Buechwaldschüssa Uznach', 100, 1, 0, '2024'),
(15, 10, '58. Gasterländer Frühlingsschiessen ', 100, 1, 0, '2024'),
(16, 11, 'Einzelwettschiessen', 200, 0, 0, '2024'),
(17, 12, 'Standcup Roggenacker', 100, 1, 0, '2024'),
(18, 13, 'RSV Verbandschiessen Schübelbach ', 100, 1, 0, '2024'),
(19, 14, 'Etzelbundschiessen Wollerau', 100, 1, 0, '2024'),
(20, 15, 'Bünder Kantonalschützenfest', 100, 1, 0, '2024'),
(21, 16, '12. Rossbergschiessen ', 50, 1, 0, '2024'),
(22, 17, 'Muota Schiessen SG Muotathal', 80, 1, 0, '2024'),
(23, 18, '13. Burg-Schwanau-Schiessen Lauerz ', 80, 1, 0, '2024'),
(24, 19, 'Muota-Schiessen SV Ibach-Schönenbuch ', 80, 1, 0, '2024'),
(25, 20, '39. Roggenstockschiessen ', 100, 1, 0, '2024'),
(26, 21, '26. Hirschfluhschiessen ', 100, 1, 0, '2024'),
(27, 22, 'Endstich', 100, 1, 0, '2024'),
(36, 60, 'SSM 2024', 100, 0, 1, '2024');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMDefinition_bak20250315`
--

CREATE TABLE `JMDefinition_bak20250315` (
  `ID` int(11) NOT NULL,
  `Reihenfolge` int(2) NOT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `Maxpunkte` int(3) NOT NULL,
  `Streicher` tinyint(1) NOT NULL,
  `hidden` int(1) NOT NULL,
  `year` year(4) NOT NULL DEFAULT current_timestamp(),
  `Erweitert` tinyint(1) NOT NULL DEFAULT 0,
  `Schiesstage` text DEFAULT NULL,
  `Info` tinyint(1) NOT NULL DEFAULT 0,
  `Gruppe` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `JMDefinition_bak20250315`
--

INSERT INTO `JMDefinition_bak20250315` (`ID`, `Reihenfolge`, `Bezeichnung`, `Maxpunkte`, `Streicher`, `hidden`, `year`, `Erweitert`, `Schiesstage`, `Info`, `Gruppe`) VALUES
(1, 1, 'Obligatorisch', 20, 0, 0, '2024', 0, '', 0, 0),
(2, 4, 'Feldschiessen', 20, 0, 0, '2024', 0, '', 0, 0),
(7, 7, 'Bester Kantonalstich', 100, 0, 0, '2024', 0, '', 0, 0),
(8, 12, '1. Sektionsmeisterschaft', 100, 0, 0, '2024', 0, '', 0, 0),
(9, 10, 'Jubiläumswettkampf SKSG', 120, 0, 0, '2024', 0, '', 0, 0),
(10, 14, '34. Schlossturmschiessen', 100, 1, 0, '2024', 0, '', 0, 0),
(13, 15, '13. Zürcher Oberländer Maischiessen', 100, 1, 0, '2024', 0, '', 0, 0),
(14, 16, '10. Buechwaldschüssa Uznach', 100, 1, 0, '2024', 0, '', 0, 0),
(15, 17, '58. Gasterländer Frühlingsschiessen ', 100, 1, 0, '2024', 0, '', 0, 0),
(16, 18, 'Einzelwettschiessen', 200, 0, 0, '2024', 0, '', 0, 0),
(17, 19, 'Standcup Roggenacker', 100, 1, 0, '2024', 0, '', 0, 0),
(18, 20, 'RSV Verbandschiessen Schübelbach ', 100, 1, 0, '2024', 0, '', 0, 0),
(19, 21, 'Etzelbundschiessen Wollerau', 100, 1, 0, '2024', 0, '', 0, 0),
(20, 22, 'Bünder Kantonalschützenfest', 100, 1, 0, '2024', 0, '', 0, 0),
(21, 23, '12. Rossbergschiessen ', 50, 1, 0, '2024', 0, '', 0, 0),
(22, 24, 'Muota Schiessen SG Muotathal', 80, 1, 0, '2024', 0, '', 0, 0),
(23, 25, '13. Burg-Schwanau-Schiessen Lauerz ', 80, 1, 0, '2024', 0, '', 0, 0),
(24, 26, 'Muota-Schiessen SV Ibach-Schönenbuch ', 80, 1, 0, '2024', 0, '', 0, 0),
(25, 27, '39. Roggenstockschiessen ', 100, 1, 0, '2024', 0, '', 0, 0),
(26, 28, '26. Hirschfluhschiessen ', 100, 1, 0, '2024', 0, '', 0, 0),
(27, 29, 'Endstich', 100, 1, 0, '2024', 0, '', 0, 0),
(36, 30, 'SSM 2024', 100, 0, 1, '2024', 0, '', 0, 0),
(69, 33, 'Obligatorisch', 20, 0, 0, '2025', 0, '', 0, 0),
(71, 34, 'Bester Kantonalstich', 100, 0, 0, '2025', 0, '', 0, 0),
(72, 13, 'Feldschiessen', 20, 0, 0, '2025', 0, 'Freitag 23. Mai 2025 18:00 - 20:00\r\nSamstag 24. Mai 2025 09:30 - 11:30\r\nSonntag 25. Mai 2025 09:30 - 11:30', 0, 0),
(73, 1, '43. Hanslin Gedenk Schiessen', 100, 0, 0, '2025', 1, 'Freitag 14. März 2025 14:00 – 17:00 Uhr\r\nSamstag 15. März 2025 08:00 – 12:00 Uhr, 13:00 – 15:30 Uhr\r\nSonntag 16. März 2025 08:00 – 11:30 Uhr\r\nSamstag 22. März 2025 08:00 – 12:00 Uhr', 0, 1),
(74, 3, '35. Schlossturmschiessen', 100, 1, 0, '2025', 0, 'Samstag 12. April 2025 08:00 – 12:00 Uhr, 13:30 – 17:00 Uhr\r\nSonntag 13. April 2025 09:30 – 11:30 Uhr\r\nSamstag 26. April 2025 08:00 – 12:00 Uhr', 0, 0),
(75, 2, '175 Jahre FSS Feusisberg', 100, 1, 0, '2025', 0, 'Freitag 04. April 2025 14:00 - 18:00\r\nSamstag 05. April 2025 08:00 - 12:00, 13:00-18:00\r\nMontag 07. April 2025 18.00 - 19.30\r\nFreitag 11. April 2025 14:00 - 18:00\r\nSamstag 12. April 2025 08:00 - 12:00, 13:00-18:00\r\nSonntag 13. April 2025 09:00 - 11:30', 0, 0),
(76, 16, 'RSV Schiessen March-Höfe', 100, 1, 0, '2025', 0, 'Freitag 13. Juni 2025 16:00 - 20:00\r\nSamstag 14. Juni 2025 08:00 - 12:00, 13:30 - 17:00', 0, 0),
(77, 17, '13. Rossbergschiessen', 50, 1, 0, '2025', 0, 'Freitag 08. August 2025 16:00 - 20:00\r\nSamstag 09. August 2025 08:00 - 12:00, 13:00 - 17:00\r\nSonntag 10. August 2025 08:00 - 12:00', 0, 0),
(78, 8, 'Hist. Rothenthurm', 50, 0, 0, '2025', 1, 'Samstag 03. Mai 2025 08:00 - 12:00, 13:30 - 17:00\r\nSonntag 04. Mai 2025 08:00 - 12:00', 0, 0),
(79, 22, '27. Hirschflueschiessen', 100, 1, 0, '2025', 0, 'Freitag 15. August 2025 16.00 - 20.00\r\nSamstag 16. August 2025 08.00 - 12.00\r\nSamstag 23. August 2025 08.00-12.00, 13.30-17.00\r\nSonntag 24. August 2025 08.00-12.00', 0, 0),
(80, 21, '40. Roggenstockschiessen', 100, 1, 0, '2025', 0, 'Freitag 15. August 2025 17.00 - 19.30\r\nSamstag 16. August 2025 08.00 - 12.00, 13.30 - 18.00\r\nSamstag 23. August 2025 08.00 - 12.00, 13.30 - 18.00\r\nSonntag 24. August 2025 08.00 - 12.00', 0, 0),
(82, 18, '150 Jahre FSG Burg Schwyz', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0),
(83, 19, 'Muota-Schiessen Ibach', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0),
(84, 20, 'Muota-Schiessen Muotathal', 80, 1, 0, '2025', 0, 'Freitag 8. August 17.00 - 20.00\r\nSamstag 9. August 08.00 - 12.00, 13.30 - 16.00\r\nFreitag 22. August 17.00 - 20.00 \r\nSamstag 23. August  08.00 - 12.00, 13.30 - 16.00', 0, 0),
(85, 5, 'Einzelwettschiessen', 200, 0, 0, '2025', 0, 'Samstag 26. April 2025 13:30 - 17:30\r\nSonntag 27. April 2025 09:30 - 11:30', 0, 0),
(86, 14, 'MSV Wilen Vereinscup', 100, 1, 0, '2025', 0, 'Samstag 24. Mai 13:00 - 17:00', 0, 0),
(87, 10, 'Sihlseeschiessen', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 16.00 - 20.00 Uhr\r\nSamstag 10. Mai 2025 08.00-12.00, 13.30-17.00 Uhr\r\nSamstag 17. Mai 2025 08.00-12.00, 13.30-17.00 Uhr\r\nSonntag 18. Mai 2025 09.00-11.30 Uhr', 0, 0),
(88, 4, 'Fahrtschiessen Mollis', 50, 0, 0, '2025', 1, 'Samstag 12. April 2025 14:00 - 16:30\r\nSonntag 13. April 2025 08:00 - 11:30', 0, 0),
(90, 6, '6. Kreiselschiessen Betzholz', 100, 1, 0, '2025', 0, 'Samstag 26. April 2025 08:00 - 12:00, 13:30 - 17:00\r\nFreitag 09. Mai 2025 17:00 - 20:00\r\nSamstag 10. Mai 2025 08:00 - 12:00, 13:30 - 16:30', 0, 0),
(91, 7, '14. Zürcher Oberländer Maischiessen', 100, 1, 0, '2025', 0, 'Samstag 26. April 2025 08:00 - 11:30, 13:30 - 16:30\r\nFreitag 09. Mai 2025 16:00 - 19:30\r\nSamstag 10. Mai 2025 08:00 - 11:30, 13:30 - 16:30', 0, 0),
(92, 28, 'Endstich', 100, 1, 0, '2025', 0, 'Samstag 11. Oktober 09:00 - 11:30, 13:00 - 16:00', 0, 0),
(93, 35, 'Sektionsmeisterschaft', 100, 0, 0, '2025', 0, '', 0, 0),
(94, 31, 'Absenden', 0, 0, 0, '2025', 0, 'Samstag 15. November 18:00 - 23:30', 1, 0),
(97, 32, 'Generalversammlung 2026', 0, 0, 0, '2025', 0, 'Freitag 06. März 2026 19:00 - 23:00', 1, 0),
(98, 30, 'Chlaustournee', 80, 0, 0, '2025', 1, 'Samstag 29. November 08:00 - 12:00, 13:00 - 16:00\r\nSamstag 06. Dezember 08:00 - 12:00, 13:00 - 16:00\r\nSonntag 07. Dezember 08:00 - 12:00', 0, 1),
(99, 25, '40. Generalwerdmüllerschiessen', 1, 0, 0, '2025', 1, 'Freitag 26. September 17:00 – 19:00 Uhr\r\nSamstag 27. September  08:00 – 12:00,  13:30 – 16:00 Uhr\r\nSamstag 04. Oktober 08:00 – 12:00,  13:30 – 17:00 Uhr', 0, 1),
(100, 26, '53. Bockenkriegschiessen', 0, 0, 0, '2025', 1, 'Freitag 26. September 15:00 – 19:00\r\nSamstag 27. September  08:00 – 12:00,  14:00 – 16:00\r\nSamstag 04. Oktober 08:00 – 12:00,  14:00 – 16:00', 0, 1),
(101, 27, 'Herbstschiessen Stäfa', 0, 0, 0, '2025', 1, 'Freitag 19. September 14:00 – 19:00\r\nSamstag 20. September  08:30 – 12:00,  13:30 – 16:30\r\nSamstag 27. September  08:30 – 12:00,  13:30 – 16:30', 0, 1),
(103, 11, '125 Jahre SV Tell', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 17:00 - 20:00\r\nSamstag 10. Mai 2025 09:00 - 12:00, 13:30 - 17:00\r\nSamstag 17. Mai 2025 09:00 - 12:00, 13:30 - 17:00\r\nSonntag 18. Mai 2025 09:00 - 12:00', 0, 0),
(104, 9, '130 Jahre SG Bennau', 100, 1, 0, '2025', 0, 'Freitag 09. Mai 2025 15:00 -19:30\r\nSamstag 10. Mai 2025 08:00 – 12:00, 13:30 – 17.30\r\nSamstag 17. Mai 2025 08:00 – 12:00, 13:30 – 17:30\r\nSonntag 18. Mai 2025 08:30 – 11:30', 0, 0),
(105, 12, 'Gemeinsames Mittagessen (Seefeld)', 0, 0, 0, '2025', 0, '10. Mai 12.00-13.00', 1, 0),
(106, 23, 'Gemeinsame Mittagessen (Sager)', 0, 0, 0, '2025', 0, '23. August 12.00-13.00', 1, 0),
(108, 29, 'Gemeinsamen Mittagessen (Schützenstube)', 0, 0, 0, '2025', 0, '11. Oktober 12.00-13.00', 1, 0),
(109, 15, 'Kantonaler GM-Final Gewehr und Pistole', 0, 0, 0, '2025', 0, 'Samstag 31. Mai 2025 07.30 - 17.00\r\nim Cholmattli, Rothenthurm', 1, 0),
(119, 24, 'Familienausflug', 0, 0, 0, '2025', 0, 'Samstag 13. September', 1, 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMDefinition_Gruppen`
--

CREATE TABLE `JMDefinition_Gruppen` (
  `ID` int(11) NOT NULL,
  `mitgliederID` int(11) NOT NULL,
  `JMDefinitionID` int(11) NOT NULL,
  `Gruppenname` varchar(255) NOT NULL,
  `Jahr` year(4) NOT NULL,
  `GruppenUID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `JMDefinition_Gruppen`
--

INSERT INTO `JMDefinition_Gruppen` (`ID`, `mitgliederID`, `JMDefinitionID`, `Gruppenname`, `Jahr`, `GruppenUID`) VALUES
(66, 112102, 98, 'Leutschner', '2025', 1),
(67, 385067, 98, 'Leutschner', '2025', 1),
(68, 112141, 98, 'Leutschner', '2025', 1),
(69, 112144, 98, 'Leutschner', '2025', 1),
(70, 112137, 98, 'Leutschner', '2025', 1),
(71, 112131, 131, 'Leutschner', '2026', 2),
(72, 112140, 131, 'Leutschner', '2026', 2),
(73, 112144, 131, 'Leutschner', '2026', 2),
(74, 112114, 131, 'Leutschner', '2026', 2),
(75, 112101, 131, 'Leutschner', '2026', 2),
(76, 112108, 131, 'Republikaner', '2026', 3),
(77, 112126, 131, 'Republikaner', '2026', 3),
(78, 112111, 131, 'Republikaner', '2026', 3),
(79, 112137, 131, 'Republikaner', '2026', 3),
(80, 385067, 131, 'Republikaner', '2026', 3);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMInformation`
--

CREATE TABLE `JMInformation` (
  `id` int(11) NOT NULL,
  `text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `JMInformation`
--

INSERT INTO `JMInformation` (`id`, `text`, `created_at`) VALUES
(1, 'asdf', '2025-01-29 07:48:15'),
(2, 'asdf', '2025-01-29 07:58:46'),
(3, 'asdfa', '2025-01-29 07:58:51'),
(4, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 08:03:57'),
(5, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 11:38:43'),
(6, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 11:40:27'),
(7, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 11:54:23'),
(8, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 11:57:34'),
(9, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 12:20:03'),
(10, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 12:28:18'),
(11, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 13:03:31'),
(12, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:15:18'),
(13, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:15:55'),
(14, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:16:07'),
(15, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:20:00'),
(16, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:29:53'),
(17, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:29:59'),
(18, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-29 14:36:46'),
(19, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:01:40'),
(20, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:02:01'),
(21, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:08:17'),
(22, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:08:19'),
(23, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:10:49'),
(24, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:10:54'),
(25, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:11:00'),
(26, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:11:09'),
(27, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:50:05'),
(28, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 08:52:28'),
(29, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 09:00:55'),
(30, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 09:19:13'),
(31, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-01-30 15:27:42'),
(32, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-04 15:35:49'),
(33, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:00'),
(34, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:02'),
(35, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:44'),
(36, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:46'),
(37, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:47'),
(38, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:50'),
(39, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:50'),
(40, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:03:50'),
(41, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:04:13'),
(42, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:04:17'),
(43, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:04:23'),
(44, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 07:04:51'),
(45, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-05 13:58:28'),
(46, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:01:24'),
(47, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:03:09'),
(48, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:05:18'),
(49, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:13:20'),
(50, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:31:06'),
(51, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:32:27'),
(52, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:33:38'),
(53, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:35:12'),
(54, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:40:46'),
(55, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-06 21:41:30'),
(56, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-07 08:12:10'),
(57, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-07 08:13:06'),
(58, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-10 07:59:22'),
(59, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:07:08'),
(60, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:47:23'),
(61, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:47:29'),
(62, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:48:12'),
(63, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:55:09'),
(64, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:55:10'),
(65, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:55:16'),
(66, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:55:20'),
(67, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 08:55:44'),
(68, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 10:22:31'),
(69, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 10:31:00'),
(70, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 10:31:04'),
(71, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-14 10:55:55'),
(72, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-17 20:34:35'),
(73, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-18 09:24:51'),
(74, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:28:03'),
(75, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:34:52'),
(76, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:35:07'),
(77, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:38:04'),
(78, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:38:11'),
(79, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:38:48'),
(80, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:39:25'),
(81, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:39:27'),
(82, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:42:42'),
(83, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:42:45'),
(84, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:45:39'),
(85, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:50:10'),
(86, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:50:11'),
(87, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:54:23'),
(88, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:54:26'),
(89, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:54:56'),
(90, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 14:59:13'),
(91, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 15:00:16'),
(92, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-19 15:00:17'),
(93, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 06:55:31'),
(94, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 09:58:40'),
(95, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:03:11'),
(96, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:13:11'),
(97, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:13:45'),
(98, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:53:09'),
(99, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:55:11'),
(100, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:59:27'),
(101, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:59:29'),
(102, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:59:31'),
(103, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-21 10:59:35'),
(104, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-24 13:32:21'),
(105, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-24 13:41:59'),
(106, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-24 14:49:17'),
(107, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-24 14:49:37'),
(108, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 06:47:01'),
(109, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 06:59:02'),
(110, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 06:59:29'),
(111, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:05:53'),
(112, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:11:15'),
(113, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:13:43'),
(114, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:15:38'),
(115, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:18:01'),
(116, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-02-25 07:18:31'),
(117, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-07 16:29:25'),
(118, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 08:56:17'),
(119, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 08:56:58'),
(120, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 09:20:26'),
(121, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 09:24:16'),
(122, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 09:26:28'),
(123, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 09:27:49'),
(124, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 09:29:11'),
(125, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 10:30:12'),
(126, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-03-15 10:48:11'),
(127, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-16 08:28:32'),
(128, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-16 08:28:38');
INSERT INTO `JMInformation` (`id`, `text`, `created_at`) VALUES
(129, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-16 08:33:08'),
(130, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-16 08:33:13'),
(131, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-22 11:13:33'),
(132, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-22 11:35:32'),
(133, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-22 11:41:31'),
(134, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-22 11:41:34'),
(135, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-23 05:47:28'),
(136, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-23 06:03:43'),
(137, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-23 06:07:25'),
(138, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-07-23 07:15:31'),
(139, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 11:59:52'),
(140, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 11:59:56'),
(141, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 12:00:11'),
(142, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 12:00:40'),
(143, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:36:06'),
(144, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:37:04'),
(145, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:38:31'),
(146, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:41:10'),
(147, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:41:14'),
(148, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:41:23'),
(149, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:41:26'),
(150, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:41:44'),
(151, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:43:56'),
(152, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:44:00'),
(153, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-11 14:48:52'),
(154, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-12 09:19:05'),
(155, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-12 12:42:21'),
(156, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-12 13:22:29'),
(157, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-08-12 13:35:50'),
(158, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-12-19 12:09:35'),
(159, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-12-19 12:21:24'),
(160, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2025-12-19 12:21:58'),
(161, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 10:38:13'),
(162, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 10:38:19'),
(163, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 10:48:31'),
(164, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 10:48:38'),
(165, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:00:59'),
(166, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:11:39'),
(167, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:13:27'),
(168, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:15:44'),
(169, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:21:06'),
(170, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:25:36'),
(171, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:26:28'),
(172, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:32:24'),
(173, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:37:01'),
(174, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:51:23'),
(175, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:53:48'),
(176, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:53:50'),
(177, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:54:01'),
(178, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:54:03'),
(179, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:58:51'),
(180, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:59:11'),
(181, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 11:59:13'),
(182, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:03:18'),
(183, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:19:30'),
(184, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:19:48'),
(185, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:20:12'),
(186, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:20:15'),
(187, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:22:28'),
(188, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:26:11'),
(189, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:28:03'),
(190, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:29:46'),
(191, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 12:30:16'),
(192, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 13:39:22'),
(193, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 13:40:07'),
(194, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-06 13:46:44'),
(195, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-07 11:13:50'),
(196, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-07 12:01:53'),
(197, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-07 12:13:44'),
(198, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-07 12:15:40'),
(199, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-13 18:53:24'),
(200, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-13 19:09:55'),
(201, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 19:32:30'),
(202, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 19:32:37'),
(203, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 19:32:42'),
(204, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 19:33:01'),
(205, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 19:33:52'),
(206, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-27 20:01:30'),
(207, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-01-30 07:37:09'),
(208, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-08 14:58:37'),
(209, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-18 15:51:50'),
(210, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-19 18:20:30'),
(211, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-24 13:38:45'),
(212, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-26 12:47:23'),
(213, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-02-26 12:47:50'),
(214, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-03-02 09:18:09'),
(215, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-03-02 09:20:47'),
(216, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-03-02 09:30:41'),
(217, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-03-02 11:12:27'),
(218, 'Bonus: Jedem Teilnehmer werden je 20 Punkte in der JM gutgeschrieben.\nDie drei schlechtesten Resultate werden gestrichen. Das EWS und die Sektionsmeisterschaft können nicht gestrichen werden. Sie müssen in Absprache mit dem Vorstand vorgeschossen werden.\nIm Falle einer Qualifikation in die zweite Runde der Sektionsmeisterschaft wird das bessere Resultat gezählt.', '2026-03-04 11:48:50');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jmresultate`
--

CREATE TABLE `jmresultate` (
  `ID` int(11) NOT NULL,
  `mitgliederID` int(11) NOT NULL,
  `jmdefinitionID` int(11) NOT NULL,
  `Punkte` int(11) NOT NULL,
  `Info` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `jmresultate`
--

INSERT INTO `jmresultate` (`ID`, `mitgliederID`, `jmdefinitionID`, `Punkte`, `Info`) VALUES
(51, 112108, 7, 97, ''),
(52, 112108, 27, 91, ''),
(53, 112114, 7, 95, ''),
(54, 112114, 27, 98, ''),
(55, 112109, 27, 83, ''),
(56, 831789, 27, 87, ''),
(57, 112126, 7, 92, ''),
(58, 112126, 27, 93, ''),
(59, 889594, 7, 98, ''),
(60, 889594, 27, 96, ''),
(61, 112131, 1, 20, ''),
(62, 112131, 2, 20, ''),
(63, 112131, 7, 96, ''),
(64, 112131, 9, 115, ''),
(65, 112131, 10, 98, ''),
(66, 112131, 13, 95, ''),
(67, 112131, 14, 95, ''),
(68, 112131, 15, 97, ''),
(69, 112131, 16, 193, ''),
(70, 112131, 17, 95, ''),
(71, 112131, 18, 95, ''),
(72, 112131, 19, 98, ''),
(73, 112131, 20, 91, ''),
(74, 112131, 21, 50, ''),
(75, 112131, 25, 97, ''),
(76, 112131, 26, 98, ''),
(77, 112131, 27, 99, ''),
(78, 112101, 27, 92, ''),
(79, 112102, 7, 93, ''),
(80, 112102, 27, 90, ''),
(81, 112103, 7, 90, ''),
(82, 112103, 27, 85, ''),
(83, 112137, 7, 90, ''),
(84, 112137, 27, 92, ''),
(85, 112139, 27, 89, ''),
(86, 548406, 27, 80, ''),
(87, 385067, 7, 92, ''),
(88, 385067, 27, 86, ''),
(89, 112140, 7, 97, ''),
(90, 112140, 27, 95, ''),
(91, 112141, 27, 90, ''),
(92, 112111, 7, 90, ''),
(93, 112111, 27, 83, ''),
(94, 112144, 7, 97, ''),
(95, 112144, 27, 96, ''),
(96, 112131, 8, 96, 'runde 1'),
(97, 112108, 75, 91, ''),
(98, 112114, 75, 96, ''),
(99, 112131, 75, 95, ''),
(100, 112101, 75, 90, ''),
(101, 112134, 75, 80, ''),
(102, 112102, 75, 90, ''),
(103, 385067, 75, 84, ''),
(104, 112140, 75, 96, ''),
(105, 112141, 75, 95, ''),
(106, 112144, 75, 96, ''),
(107, 112108, 85, 128, ''),
(108, 385067, 85, 137, ''),
(109, 112126, 85, 131, ''),
(111, 112114, 85, 186, ''),
(112, 112131, 85, 191, ''),
(113, 112101, 85, 179, ''),
(114, 112102, 85, 180, ''),
(115, 112103, 85, 179, ''),
(116, 548406, 85, 129, ''),
(117, 112140, 85, 192, ''),
(118, 112141, 85, 184, ''),
(119, 112111, 85, 121, ''),
(120, 112144, 85, 192, ''),
(121, 112108, 74, 92, ''),
(122, 112114, 74, 95, ''),
(123, 112126, 74, 87, ''),
(124, 112131, 74, 97, ''),
(125, 112101, 74, 95, ''),
(126, 112102, 74, 81, ''),
(127, 112103, 74, 89, ''),
(128, 112137, 74, 91, ''),
(129, 385067, 74, 91, ''),
(130, 112140, 74, 96, ''),
(131, 112141, 74, 90, ''),
(132, 112111, 74, 88, ''),
(133, 112144, 74, 93, ''),
(136, 112108, 90, 91, ''),
(137, 112114, 90, 92, ''),
(138, 112126, 90, 83, ''),
(139, 112131, 90, 97, ''),
(140, 112103, 90, 94, ''),
(141, 385067, 90, 88, ''),
(142, 112140, 90, 91, ''),
(143, 112141, 90, 92, ''),
(144, 112111, 90, 74, ''),
(145, 112144, 90, 94, ''),
(146, 112108, 91, 88, ''),
(147, 112114, 91, 94, ''),
(148, 112126, 91, 87, ''),
(149, 112131, 91, 92, ''),
(150, 112103, 91, 92, ''),
(151, 385067, 91, 90, ''),
(152, 112140, 91, 99, ''),
(153, 112141, 91, 96, ''),
(154, 112111, 91, 83, ''),
(155, 112144, 91, 96, ''),
(156, 112108, 104, 92, ''),
(157, 112114, 104, 93, ''),
(158, 112126, 104, 85, ''),
(159, 112131, 104, 97, ''),
(160, 112134, 104, 74, ''),
(161, 112103, 104, 93, ''),
(162, 385067, 104, 95, ''),
(163, 112140, 104, 93, ''),
(164, 112141, 104, 92, ''),
(165, 112111, 104, 82, ''),
(166, 112144, 104, 96, ''),
(167, 112108, 87, 92, ''),
(168, 112114, 87, 94, ''),
(169, 112126, 87, 94, ''),
(170, 112131, 87, 95, ''),
(171, 112101, 87, 95, ''),
(172, 112134, 87, 69, ''),
(173, 112103, 87, 92, ''),
(174, 385067, 87, 89, ''),
(175, 112140, 87, 92, ''),
(176, 112141, 87, 97, ''),
(177, 112144, 87, 98, ''),
(178, 112108, 103, 89, ''),
(179, 112114, 103, 89, ''),
(180, 112126, 103, 82, ''),
(181, 112131, 103, 96, ''),
(182, 112101, 103, 95, ''),
(183, 112103, 103, 95, ''),
(184, 385067, 103, 92, ''),
(185, 112140, 103, 96, ''),
(186, 112141, 103, 86, ''),
(187, 112144, 103, 94, ''),
(188, 112126, 75, 0, ''),
(189, 112101, 90, 0, ''),
(190, 112101, 91, 0, ''),
(191, 112134, 74, 0, ''),
(192, 112134, 85, 0, ''),
(193, 112134, 90, 0, ''),
(194, 112134, 91, 0, ''),
(195, 112102, 90, 0, ''),
(196, 112102, 91, 0, ''),
(197, 112102, 104, 0, ''),
(198, 112102, 87, 0, ''),
(199, 112102, 103, 0, ''),
(200, 112103, 75, 0, ''),
(201, 112137, 75, 0, ''),
(202, 112137, 85, 0, ''),
(203, 112137, 90, 0, ''),
(204, 112137, 91, 0, ''),
(205, 112137, 104, 0, ''),
(206, 112137, 87, 0, ''),
(207, 548406, 75, 0, ''),
(208, 548406, 74, 0, ''),
(209, 548406, 90, 0, ''),
(210, 548406, 91, 0, ''),
(211, 548406, 104, 0, ''),
(212, 548406, 87, 0, ''),
(213, 548406, 103, 0, ''),
(214, 112111, 75, 0, ''),
(215, 112111, 87, 0, ''),
(216, 112111, 103, 0, ''),
(217, 112108, 86, 0, ''),
(218, 112114, 86, 93, ''),
(219, 112109, 86, 0, ''),
(220, 831789, 86, 0, ''),
(221, 112126, 86, 84, ''),
(222, 889594, 86, 0, ''),
(225, 112104, 86, 0, ''),
(226, 112131, 86, 97, ''),
(228, 112101, 86, 91, ''),
(229, 112134, 86, 0, ''),
(230, 112102, 86, 0, ''),
(231, 112103, 86, 92, ''),
(232, 112137, 86, 0, ''),
(233, 548406, 86, 0, ''),
(234, 385067, 86, 93, ''),
(235, 112140, 86, 0, ''),
(236, 112141, 86, 90, ''),
(237, 112111, 86, 0, ''),
(238, 112144, 86, 99, ''),
(239, 29093, 86, 0, ''),
(240, 389561, 86, 0, ''),
(241, 112101, 104, 94, ''),
(242, 112101, 76, 95, ''),
(243, 112101, 93, 93, 'runde 1'),
(244, 112102, 93, 92, 'runde 1'),
(245, 112137, 93, 91, 'runde 1'),
(246, 112108, 93, 87, 'runde 1'),
(247, 112114, 93, 88, 'runde 1'),
(248, 112109, 93, 89, 'runde 1'),
(249, 831789, 93, 87, 'runde 1'),
(250, 112126, 93, 84, 'runde 1'),
(251, 112131, 93, 98, 'runde 1'),
(252, 112134, 93, 0, 'runde 1'),
(253, 112103, 93, 92, 'runde 1'),
(254, 548406, 93, 0, 'runde 1'),
(255, 385067, 93, 94, 'runde 1'),
(256, 112140, 93, 96, 'runde 1'),
(257, 112141, 93, 90, 'runde 1'),
(258, 112111, 93, 71, 'runde 1'),
(259, 112144, 93, 95, 'runde 1'),
(260, 112108, 76, 93, ''),
(261, 112114, 76, 93, ''),
(262, 112126, 76, 92, ''),
(263, 112131, 76, 96, ''),
(265, 112102, 76, 82, ''),
(266, 112103, 76, 89, ''),
(267, 385067, 76, 96, ''),
(268, 112140, 76, 94, ''),
(269, 112141, 76, 92, ''),
(270, 112111, 76, 93, ''),
(271, 112144, 76, 96, ''),
(277, 112101, 77, 49, ''),
(281, 112108, 77, 39, ''),
(282, 112114, 77, 48, ''),
(283, 112126, 77, 48, ''),
(284, 112134, 77, 34, ''),
(285, 112141, 77, 48, ''),
(286, 112111, 77, 46, ''),
(287, 112144, 77, 49, ''),
(288, 112131, 77, 50, ''),
(291, 112108, 80, 86, ''),
(292, 112114, 80, 97, ''),
(293, 112126, 80, 93, ''),
(294, 112131, 80, 95, ''),
(295, 112101, 80, 93, ''),
(296, 112103, 80, 88, ''),
(297, 385067, 80, 84, ''),
(298, 112140, 80, 94, ''),
(299, 112141, 80, 91, ''),
(300, 112144, 80, 93, ''),
(301, 112108, 79, 93, ''),
(302, 112114, 79, 89, ''),
(303, 112126, 79, 84, ''),
(304, 112131, 79, 95, ''),
(305, 112101, 79, 91, ''),
(306, 112103, 79, 84, ''),
(307, 385067, 79, 92, ''),
(308, 112140, 79, 91, ''),
(309, 112141, 79, 83, ''),
(310, 112144, 79, 96, ''),
(311, 112108, 84, 68, ''),
(312, 112114, 84, 74, ''),
(313, 112126, 84, 74, ''),
(314, 112131, 84, 69, ''),
(315, 112101, 84, 71, ''),
(316, 112103, 84, 64, ''),
(317, 385067, 84, 73, ''),
(318, 112140, 84, 74, ''),
(319, 112141, 84, 73, ''),
(320, 112144, 84, 73, ''),
(321, 112108, 83, 75, ''),
(322, 112114, 83, 71, ''),
(323, 112126, 83, 71, ''),
(324, 112131, 83, 79, ''),
(325, 112101, 83, 73, ''),
(326, 112103, 83, 72, ''),
(327, 385067, 83, 71, ''),
(328, 112140, 83, 74, ''),
(329, 112141, 83, 73, ''),
(330, 112144, 83, 73, ''),
(331, 112108, 82, 74, ''),
(332, 112114, 82, 74, ''),
(333, 112126, 82, 67, ''),
(334, 112131, 82, 79, ''),
(335, 112101, 82, 75, ''),
(336, 112103, 82, 76, ''),
(337, 385067, 82, 67, ''),
(338, 112140, 82, 77, ''),
(339, 112141, 82, 77, ''),
(340, 112144, 82, 77, ''),
(344, 112108, 69, 20, ''),
(345, 112108, 72, 20, ''),
(346, 112114, 69, 20, ''),
(347, 112114, 72, 20, ''),
(348, 112109, 69, 20, ''),
(349, 112109, 72, 20, ''),
(350, 831789, 69, 20, ''),
(351, 831789, 72, 20, ''),
(352, 112126, 69, 20, ''),
(353, 112126, 72, 20, ''),
(354, 889594, 69, 20, ''),
(355, 889594, 72, 20, ''),
(356, 112131, 69, 20, ''),
(357, 112131, 72, 20, ''),
(358, 112101, 69, 20, ''),
(359, 112101, 72, 20, ''),
(360, 112134, 69, 20, ''),
(361, 112134, 72, 20, ''),
(362, 112103, 69, 20, ''),
(363, 112103, 72, 20, ''),
(364, 112137, 69, 20, ''),
(365, 112137, 72, 20, ''),
(366, 548406, 72, 20, ''),
(367, 385067, 69, 20, ''),
(368, 385067, 72, 20, ''),
(369, 112140, 69, 20, ''),
(370, 112140, 72, 20, ''),
(371, 112141, 69, 20, ''),
(372, 112141, 72, 20, ''),
(373, 112111, 69, 20, ''),
(374, 112111, 72, 20, ''),
(375, 112144, 69, 20, ''),
(376, 112144, 72, 20, ''),
(377, 389561, 69, 20, ''),
(378, 389561, 72, 20, ''),
(379, 112108, 93, 88, 'runde 2'),
(380, 112114, 93, 96, 'runde 2'),
(381, 112109, 93, 91, 'runde 2'),
(382, 112126, 93, 89, 'runde 2'),
(383, 112131, 93, 94, 'runde 2'),
(384, 112101, 93, 93, 'runde 2'),
(385, 112103, 93, 95, 'runde 2'),
(386, 112137, 93, 80, 'runde 2'),
(387, 385067, 93, 90, 'runde 2'),
(388, 112111, 93, 94, 'runde 2'),
(389, 112144, 93, 96, 'runde 2'),
(390, 112140, 93, 93, 'runde 2'),
(391, 112097, 27, 0, ''),
(392, 112104, 27, 0, '');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jmresultate2024`
--

CREATE TABLE `jmresultate2024` (
  `ID` int(11) NOT NULL,
  `mitgliederID` int(11) NOT NULL,
  `jmdefinitionID` int(11) NOT NULL,
  `Punkte` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `jmresultate2024`
--

INSERT INTO `jmresultate2024` (`ID`, `mitgliederID`, `jmdefinitionID`, `Punkte`) VALUES
(1, 112114, 10, 93),
(2, 889594, 10, 91),
(3, 112131, 10, 98),
(4, 112101, 10, 94),
(5, 112098, 10, 94),
(6, 112103, 10, 95),
(7, 112108, 8, 85),
(8, 112108, 10, 91),
(9, 112108, 13, 91),
(10, 112108, 14, 92),
(11, 112108, 15, 88),
(12, 112108, 16, 139),
(13, 112108, 17, 85),
(14, 112108, 18, 90),
(15, 112108, 19, 86),
(16, 112108, 20, 86),
(17, 831789, 13, 92),
(18, 831789, 14, 85),
(19, 831789, 15, 90),
(20, 831789, 16, 119),
(21, 831789, 17, 65),
(22, 112126, 8, 92),
(23, 112126, 10, 88),
(24, 112126, 13, 91),
(25, 112126, 14, 90),
(26, 112126, 15, 82),
(27, 112126, 16, 130),
(28, 112126, 18, 91),
(29, 112126, 19, 85),
(30, 112126, 20, 81),
(31, 112134, 10, 71),
(32, 112134, 13, 60),
(33, 112134, 14, 73),
(34, 112134, 15, 65),
(35, 112134, 16, 113),
(36, 112134, 17, 80),
(37, 112134, 18, 73),
(38, 112134, 19, 70),
(39, 112137, 8, 77),
(40, 112139, 17, 88),
(41, 548406, 16, 120),
(42, 385067, 8, 78),
(43, 385067, 10, 84),
(44, 385067, 13, 88),
(45, 385067, 14, 91),
(46, 385067, 15, 94),
(47, 385067, 16, 134),
(48, 385067, 17, 78),
(49, 385067, 19, 85),
(50, 112111, 8, 93),
(51, 112111, 10, 82),
(52, 112111, 16, 125),
(53, 112111, 17, 85),
(54, 112111, 19, 87),
(55, 112111, 20, 78),
(56, 112108, 12, 92),
(57, 112114, 8, 94),
(58, 112114, 12, 94),
(59, 112114, 13, 94),
(60, 112114, 14, 91),
(61, 112114, 15, 85),
(62, 112114, 16, 181),
(63, 112114, 17, 92),
(64, 112114, 18, 92),
(65, 112114, 19, 98),
(66, 112114, 20, 93),
(67, 889594, 8, 96),
(68, 889594, 13, 96),
(69, 889594, 14, 98),
(70, 889594, 15, 93),
(71, 889594, 16, 190),
(72, 889594, 17, 96),
(73, 889594, 18, 96),
(74, 889594, 19, 96),
(75, 889594, 20, 93),
(76, 112131, 8, 95),
(77, 112131, 13, 95),
(78, 112131, 14, 95),
(79, 112131, 15, 97),
(80, 112131, 16, 193),
(81, 112131, 17, 95),
(82, 112131, 18, 95),
(83, 112131, 19, 98),
(84, 112131, 20, 91),
(85, 112101, 16, 191),
(86, 112101, 20, 89),
(87, 112098, 8, 97),
(88, 112098, 12, 92),
(89, 112098, 13, 97),
(90, 112098, 14, 98),
(91, 112098, 15, 79),
(92, 112098, 16, 182),
(93, 112098, 17, 97),
(94, 112098, 18, 93),
(95, 112098, 19, 94),
(96, 112098, 20, 96),
(97, 112103, 8, 93),
(98, 112103, 13, 92),
(99, 112103, 14, 90),
(100, 112103, 15, 87),
(101, 112103, 16, 185),
(102, 112103, 17, 91),
(103, 112103, 18, 85),
(104, 112103, 19, 91),
(105, 112137, 12, 92),
(106, 385067, 12, 95),
(107, 112140, 8, 96),
(108, 112140, 12, 92),
(109, 112140, 10, 97),
(110, 112140, 13, 94),
(111, 112140, 14, 93),
(112, 112140, 15, 93),
(113, 112140, 16, 192),
(114, 112140, 17, 98),
(115, 112140, 18, 92),
(116, 112140, 19, 93),
(117, 112140, 20, 91),
(118, 112141, 10, 91),
(119, 112141, 14, 88),
(120, 112141, 15, 92),
(121, 112141, 18, 91),
(122, 112141, 20, 85),
(123, 112144, 8, 95),
(124, 112144, 12, 98),
(125, 112144, 10, 96),
(126, 112144, 13, 96),
(127, 112144, 14, 96),
(128, 112144, 15, 96),
(129, 112144, 16, 193),
(130, 112144, 17, 95),
(131, 112144, 18, 92),
(132, 112144, 19, 98),
(133, 112144, 20, 92),
(134, 112108, 21, 45),
(135, 112108, 22, 68),
(136, 112108, 23, 71),
(137, 112108, 24, 75),
(138, 112114, 21, 41),
(139, 112114, 22, 74),
(140, 112114, 23, 73),
(141, 112114, 24, 74),
(142, 112126, 21, 48),
(143, 112126, 22, 72),
(144, 112126, 23, 73),
(145, 112126, 24, 71),
(146, 889594, 12, 96),
(147, 889594, 21, 47),
(148, 889594, 22, 73),
(149, 889594, 23, 76),
(150, 889594, 24, 75),
(151, 112131, 21, 50),
(152, 112131, 22, 76),
(153, 112131, 23, 76),
(154, 112131, 24, 74),
(155, 112134, 21, 14),
(156, 112098, 21, 47),
(157, 112098, 22, 76),
(158, 112098, 23, 75),
(159, 112098, 24, 74),
(160, 112103, 21, 45),
(161, 112103, 22, 73),
(162, 112103, 23, 72),
(163, 112103, 24, 74),
(164, 385067, 22, 66),
(165, 385067, 23, 72),
(166, 385067, 24, 75),
(167, 112140, 22, 78),
(168, 112140, 23, 76),
(169, 112140, 24, 79),
(170, 112141, 22, 77),
(171, 112141, 23, 75),
(172, 112141, 24, 72),
(173, 112144, 22, 74),
(174, 112144, 23, 74),
(175, 112144, 24, 78),
(176, 112108, 25, 87),
(177, 112108, 26, 95),
(178, 112114, 25, 95),
(179, 112114, 26, 90),
(180, 112126, 25, 85),
(181, 112126, 26, 85),
(182, 889594, 25, 92),
(183, 889594, 26, 95),
(184, 112131, 25, 97),
(185, 112131, 26, 98),
(186, 112134, 25, 73),
(187, 112134, 26, 70),
(188, 112098, 25, 95),
(189, 112098, 26, 91),
(190, 112103, 25, 89),
(191, 112103, 26, 85),
(192, 385067, 25, 95),
(193, 385067, 26, 89),
(194, 112140, 25, 94),
(195, 112140, 26, 93),
(196, 112141, 25, 91),
(197, 112141, 26, 96),
(198, 112111, 25, 88),
(199, 112111, 26, 89),
(200, 112144, 25, 93),
(201, 112144, 26, 95),
(202, 385067, 18, 0),
(203, 385067, 20, 0),
(204, 385067, 21, 0),
(205, 831789, 8, 0),
(206, 831789, 12, 0),
(207, 831789, 10, 0),
(208, 831789, 18, 0),
(209, 831789, 19, 0),
(210, 831789, 20, 0),
(211, 831789, 21, 0),
(212, 831789, 22, 0),
(213, 831789, 23, 0),
(214, 831789, 24, 0),
(215, 831789, 25, 0),
(216, 831789, 26, 0),
(217, 112126, 12, 86),
(218, 112126, 17, 0),
(219, 112131, 12, 96),
(220, 112101, 8, 0),
(221, 112101, 12, 94),
(222, 112101, 13, 0),
(223, 112101, 14, 0),
(224, 112101, 15, 0),
(225, 112101, 17, 0),
(226, 112101, 18, 0),
(227, 112101, 19, 0),
(228, 112101, 21, 0),
(229, 112101, 22, 0),
(230, 112101, 23, 0),
(231, 112101, 24, 0),
(232, 112101, 25, 0),
(233, 112101, 26, 0),
(234, 112134, 8, 0),
(235, 112134, 12, 0),
(236, 112134, 20, 0),
(237, 112134, 22, 0),
(238, 112134, 23, 0),
(239, 112134, 24, 0),
(240, 112103, 12, 0),
(241, 112103, 20, 0),
(242, 112137, 10, 0),
(243, 112137, 13, 0),
(244, 112137, 14, 0),
(245, 112137, 15, 0),
(246, 112137, 16, 0),
(247, 112137, 17, 0),
(248, 112137, 18, 0),
(249, 112137, 19, 0),
(250, 112137, 20, 0),
(251, 112137, 21, 0),
(252, 112137, 22, 0),
(253, 112137, 23, 0),
(254, 112137, 24, 0),
(255, 112137, 25, 0),
(256, 112137, 26, 0),
(257, 112140, 21, 0),
(258, 112141, 8, 0),
(259, 112141, 12, 0),
(260, 112141, 13, 0),
(261, 112141, 16, 0),
(262, 112141, 17, 0),
(263, 112141, 19, 0),
(264, 112141, 21, 0),
(265, 112111, 12, 0),
(266, 112111, 13, 0),
(267, 112111, 14, 0),
(268, 112111, 15, 0),
(269, 112111, 18, 0),
(270, 112111, 21, 0),
(271, 112111, 22, 0),
(272, 112111, 23, 0),
(273, 112111, 24, 0),
(274, 112144, 21, 0),
(451, 112139, 8, 0),
(452, 112139, 12, 0),
(453, 112139, 10, 0),
(454, 112139, 13, 0),
(455, 112139, 14, 0),
(456, 112139, 15, 0),
(457, 112139, 16, 0),
(458, 112139, 18, 0),
(459, 112139, 19, 0),
(460, 112139, 20, 0),
(461, 112139, 21, 0),
(462, 112139, 22, 0),
(463, 112139, 23, 0),
(464, 112139, 24, 0),
(465, 112139, 25, 0),
(466, 112139, 26, 0),
(468, 112108, 7, 97),
(469, 112114, 7, 95),
(470, 112108, 1, 20),
(471, 112108, 2, 20),
(472, 112114, 1, 20),
(473, 112114, 2, 20),
(474, 112109, 1, 20),
(475, 112109, 2, 20),
(476, 831789, 1, 20),
(477, 112126, 1, 20),
(478, 112126, 2, 20),
(479, 889594, 1, 20),
(480, 889594, 2, 20),
(481, 112102, 2, 20),
(482, 112131, 1, 20),
(483, 112131, 2, 20),
(484, 112101, 1, 20),
(485, 112101, 2, 20),
(486, 112134, 1, 20),
(487, 112134, 2, 20),
(488, 112098, 1, 20),
(489, 112098, 2, 20),
(490, 112103, 1, 20),
(491, 112103, 2, 20),
(492, 112137, 1, 20),
(493, 112137, 2, 20),
(494, 548406, 1, 20),
(495, 548406, 2, 0),
(496, 385067, 1, 20),
(497, 385067, 2, 20),
(498, 112140, 1, 20),
(499, 112140, 2, 20),
(500, 112141, 1, 20),
(501, 112141, 2, 20),
(502, 112111, 1, 20),
(503, 112111, 2, 20),
(504, 112144, 1, 20),
(505, 112144, 2, 20),
(506, 389561, 1, 20),
(507, 389561, 2, 20),
(508, 112103, 27, 85),
(509, 112098, 36, 97),
(510, 112101, 36, 94),
(511, 112103, 36, 93),
(512, 112108, 36, 92),
(513, 112111, 36, 93),
(514, 112114, 36, 94),
(515, 112126, 36, 92),
(516, 112131, 36, 96),
(517, 112134, 36, 0),
(518, 112137, 36, 92),
(519, 112139, 36, 0),
(520, 112140, 36, 96),
(521, 112141, 36, 0),
(522, 112144, 36, 98),
(523, 385067, 36, 95),
(524, 831789, 36, 0),
(525, 889594, 36, 96),
(526, 112114, 27, 98),
(527, 112109, 27, 83),
(528, 112101, 27, 92),
(529, 112103, 7, 90),
(530, 112137, 7, 90),
(531, 385067, 27, 86),
(532, 385067, 7, 92),
(533, 112140, 7, 97),
(534, 112144, 7, 97),
(535, 112126, 9, 101),
(536, 112131, 9, 115),
(537, 112098, 9, 109),
(538, 385067, 9, 106),
(539, 112140, 9, 114),
(540, 112114, 9, 111),
(541, 112144, 9, 116),
(542, 112109, 9, 0),
(543, 112109, 8, 0),
(544, 112109, 12, 0),
(545, 112109, 10, 0),
(546, 112109, 13, 0),
(547, 112109, 14, 0),
(548, 112109, 15, 0),
(549, 112109, 16, 0),
(550, 112109, 17, 0),
(551, 112109, 18, 0),
(552, 112109, 19, 0),
(553, 112109, 20, 0),
(554, 112109, 21, 0),
(555, 112109, 22, 0),
(556, 112109, 23, 0),
(557, 112109, 24, 0),
(558, 112109, 25, 0),
(559, 112109, 26, 0),
(560, 112109, 36, 0),
(561, 112098, 7, 93),
(562, 112108, 9, 110),
(563, 889594, 9, 108),
(564, 112108, 27, 91),
(565, 831789, 27, 87),
(566, 112126, 7, 92),
(567, 112126, 27, 93),
(568, 889594, 7, 98),
(569, 889594, 27, 96),
(570, 112131, 7, 96),
(571, 112131, 27, 99),
(572, 112098, 27, 90),
(573, 112137, 27, 92),
(574, 112139, 27, 89),
(575, 548406, 27, 80),
(576, 112140, 27, 95),
(577, 112141, 27, 90),
(578, 112111, 7, 90),
(579, 112111, 27, 83),
(580, 112144, 27, 96);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `JMSchiesstage`
--

CREATE TABLE `JMSchiesstage` (
  `ID` int(11) NOT NULL,
  `jm_id` int(11) NOT NULL,
  `schiesstag` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `year` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `JMSchiesstage`
--

INSERT INTO `JMSchiesstage` (`ID`, `jm_id`, `schiesstag`, `start_time`, `end_time`, `year`) VALUES
(8304, 153, '2026-09-25', '17:00:00', '19:00:00', 2026),
(8305, 153, '2026-09-26', '08:00:00', '12:00:00', 2026),
(8306, 153, '2026-09-26', '13:30:00', '16:00:00', 2026),
(8307, 153, '2026-10-03', '08:00:00', '12:00:00', 2026),
(8308, 153, '2026-10-03', '13:30:00', '17:00:00', 2026),
(9418, 97, '2025-03-06', '19:00:00', '23:00:00', 2025),
(9979, 160, '2026-10-10', '12:00:00', '13:00:00', 2026),
(10082, 139, '2026-09-25', '17:00:00', '19:00:00', 2026),
(10083, 139, '2026-09-26', '08:00:00', '12:00:00', 2026),
(10084, 139, '2026-09-26', '13:30:00', '16:00:00', 2026),
(10085, 139, '2026-10-03', '08:00:00', '12:00:00', 2026),
(10086, 139, '2026-10-03', '13:30:00', '17:00:00', 2026),
(10110, 159, '2026-04-11', '14:00:00', '16:30:00', 2026),
(10111, 159, '2026-04-12', '08:00:00', '11:30:00', 2026),
(10214, 130, '2026-03-13', '14:00:00', '17:00:00', 2026),
(10215, 130, '2026-03-14', '08:00:00', '12:00:00', 2026),
(10216, 130, '2026-03-14', '13:00:00', '15:30:00', 2026),
(10217, 130, '2026-03-15', '08:00:00', '12:00:00', 2026),
(10218, 130, '2026-03-21', '08:00:00', '12:00:00', 2026),
(10400, 155, '2026-09-18', '14:00:00', '19:00:00', 2026),
(10401, 155, '2026-09-19', '08:30:00', '12:00:00', 2026),
(10402, 155, '2026-09-19', '13:30:00', '16:30:00', 2026),
(10403, 155, '2026-09-26', '08:30:00', '12:00:00', 2026),
(10404, 155, '2026-09-26', '13:30:00', '16:30:00', 2026),
(10502, 154, '2026-09-25', '15:00:00', '19:00:00', 2026),
(10503, 154, '2026-09-26', '08:00:00', '12:00:00', 2026),
(10504, 154, '2026-09-26', '14:00:00', '16:00:00', 2026),
(10505, 154, '2026-10-03', '08:00:00', '12:00:00', 2026),
(10506, 154, '2026-10-03', '14:00:00', '16:00:00', 2026),
(10795, 73, '2025-03-14', '14:00:00', '17:00:00', 2025),
(10796, 73, '2025-03-15', '08:00:00', '12:00:00', 2025),
(10797, 73, '2025-03-15', '13:00:00', '15:30:00', 2025),
(10798, 73, '2025-03-16', '08:00:00', '11:30:00', 2025),
(10799, 73, '2025-03-22', '08:00:00', '12:00:00', 2025),
(10800, 74, '2025-04-12', '08:00:00', '12:00:00', 2025),
(10801, 74, '2025-04-12', '13:30:00', '17:00:00', 2025),
(10802, 74, '2025-04-13', '09:30:00', '11:30:00', 2025),
(10803, 74, '2025-04-26', '08:00:00', '12:00:00', 2025),
(10804, 75, '2025-04-04', '14:00:00', '18:00:00', 2025),
(10805, 75, '2025-04-05', '08:00:00', '12:00:00', 2025),
(10806, 75, '2025-04-05', '13:00:00', '18:00:00', 2025),
(10807, 75, '2025-04-07', '18:00:00', '19:30:00', 2025),
(10808, 75, '2025-04-11', '14:00:00', '18:00:00', 2025),
(10809, 75, '2025-04-12', '08:00:00', '12:00:00', 2025),
(10810, 75, '2025-04-12', '13:00:00', '18:00:00', 2025),
(10811, 75, '2025-04-13', '09:00:00', '11:30:00', 2025),
(10812, 88, '2025-04-12', '14:00:00', '16:30:00', 2025),
(10813, 88, '2025-04-13', '08:00:00', '11:30:00', 2025),
(10814, 85, '2025-04-26', '13:30:00', '17:30:00', 2025),
(10815, 85, '2025-04-27', '09:30:00', '11:30:00', 2025),
(10816, 90, '2025-04-26', '08:00:00', '12:00:00', 2025),
(10817, 90, '2025-04-26', '13:30:00', '17:00:00', 2025),
(10818, 90, '2025-05-09', '17:00:00', '20:00:00', 2025),
(10819, 90, '2025-05-10', '08:00:00', '12:00:00', 2025),
(10820, 90, '2025-05-10', '13:30:00', '16:30:00', 2025),
(10821, 91, '2025-04-26', '08:00:00', '11:30:00', 2025),
(10822, 91, '2025-04-26', '13:30:00', '16:30:00', 2025),
(10823, 91, '2025-05-09', '16:00:00', '19:30:00', 2025),
(10824, 91, '2025-05-10', '08:00:00', '11:30:00', 2025),
(10825, 91, '2025-05-10', '13:30:00', '16:30:00', 2025),
(10826, 78, '2025-05-03', '08:00:00', '12:00:00', 2025),
(10827, 78, '2025-05-03', '13:30:00', '17:00:00', 2025),
(10828, 78, '2025-05-04', '08:00:00', '12:00:00', 2025),
(10829, 104, '2025-05-09', '15:00:00', '19:30:00', 2025),
(10830, 104, '2025-05-10', '08:00:00', '12:00:00', 2025),
(10831, 104, '2025-05-10', '13:30:00', '17:30:00', 2025),
(10832, 104, '2025-05-17', '08:00:00', '12:00:00', 2025),
(10833, 104, '2025-05-17', '13:30:00', '17:30:00', 2025),
(10834, 104, '2025-05-18', '08:30:00', '11:30:00', 2025),
(10835, 87, '2025-05-09', '16:00:00', '20:00:00', 2025),
(10836, 87, '2025-05-10', '08:00:00', '12:00:00', 2025),
(10837, 87, '2025-05-10', '13:30:00', '17:00:00', 2025),
(10838, 87, '2025-05-17', '08:00:00', '12:00:00', 2025),
(10839, 87, '2025-05-17', '13:30:00', '17:00:00', 2025),
(10840, 87, '2025-05-18', '09:00:00', '11:30:00', 2025),
(10841, 103, '2025-05-09', '17:00:00', '20:00:00', 2025),
(10842, 103, '2025-05-10', '09:00:00', '12:00:00', 2025),
(10843, 103, '2025-05-10', '13:30:00', '17:00:00', 2025),
(10844, 103, '2025-05-17', '09:00:00', '12:00:00', 2025),
(10845, 103, '2025-05-17', '13:30:00', '17:00:00', 2025),
(10846, 103, '2025-05-18', '09:00:00', '12:00:00', 2025),
(10847, 105, '2025-05-10', '12:00:00', '13:00:00', 2025),
(10848, 72, '2025-05-23', '18:00:00', '20:00:00', 2025),
(10849, 72, '2025-05-24', '09:30:00', '11:30:00', 2025),
(10850, 72, '2025-05-25', '09:30:00', '11:30:00', 2025),
(10851, 86, '2025-05-24', '13:00:00', '17:00:00', 2025),
(10852, 109, '2025-05-31', '07:30:00', '17:00:00', 2025),
(10853, 76, '2025-06-13', '16:00:00', '20:00:00', 2025),
(10854, 76, '2025-06-14', '08:00:00', '12:00:00', 2025),
(10855, 76, '2025-06-14', '13:30:00', '17:00:00', 2025),
(10856, 77, '2025-08-08', '16:00:00', '20:00:00', 2025),
(10857, 77, '2025-08-09', '08:00:00', '12:00:00', 2025),
(10858, 77, '2025-08-09', '13:00:00', '17:00:00', 2025),
(10859, 77, '2025-08-10', '08:00:00', '12:00:00', 2025),
(10860, 82, '2025-08-08', '17:00:00', '20:00:00', 2025),
(10861, 82, '2025-08-09', '08:00:00', '12:00:00', 2025),
(10862, 82, '2025-08-09', '13:30:00', '16:00:00', 2025),
(10863, 82, '2025-08-22', '17:00:00', '20:00:00', 2025),
(10864, 82, '2025-08-23', '08:00:00', '12:00:00', 2025),
(10865, 82, '2025-08-23', '13:30:00', '16:00:00', 2025),
(10866, 83, '2025-08-08', '17:00:00', '20:00:00', 2025),
(10867, 83, '2025-08-09', '08:00:00', '12:00:00', 2025),
(10868, 83, '2025-08-09', '13:30:00', '16:00:00', 2025),
(10869, 83, '2025-08-22', '17:00:00', '20:00:00', 2025),
(10870, 83, '2025-08-23', '08:00:00', '12:00:00', 2025),
(10871, 83, '2025-08-23', '13:30:00', '16:00:00', 2025),
(10872, 84, '2025-08-08', '17:00:00', '20:00:00', 2025),
(10873, 84, '2025-08-09', '08:00:00', '12:00:00', 2025),
(10874, 84, '2025-08-09', '13:30:00', '16:00:00', 2025),
(10875, 84, '2025-08-22', '17:00:00', '20:00:00', 2025),
(10876, 84, '2025-08-23', '08:00:00', '12:00:00', 2025),
(10877, 84, '2025-08-23', '13:30:00', '16:00:00', 2025),
(10878, 80, '2025-08-15', '17:00:00', '19:30:00', 2025),
(10879, 80, '2025-08-16', '08:00:00', '12:00:00', 2025),
(10880, 80, '2025-08-16', '13:30:00', '18:00:00', 2025),
(10881, 80, '2025-08-23', '08:00:00', '12:00:00', 2025),
(10882, 80, '2025-08-23', '13:30:00', '18:00:00', 2025),
(10883, 80, '2025-08-24', '08:00:00', '12:00:00', 2025),
(10884, 79, '2025-08-15', '16:00:00', '20:00:00', 2025),
(10885, 79, '2025-08-16', '08:00:00', '12:00:00', 2025),
(10886, 79, '2025-08-23', '08:00:00', '12:00:00', 2025),
(10887, 79, '2025-08-23', '13:30:00', '13:30:00', 2025),
(10888, 79, '2025-08-24', '08:00:00', '12:00:00', 2025),
(10889, 106, '2025-08-23', '12:00:00', '13:00:00', 2025),
(10890, 99, '2025-09-26', '17:00:00', '19:00:00', 2025),
(10891, 99, '2025-09-27', '08:00:00', '12:00:00', 2025),
(10892, 99, '2025-09-27', '13:30:00', '16:00:00', 2025),
(10893, 99, '2025-10-04', '08:00:00', '12:00:00', 2025),
(10894, 99, '2025-10-04', '13:30:00', '17:00:00', 2025),
(10895, 101, '2025-09-19', '14:00:00', '19:00:00', 2025),
(10896, 101, '2025-09-20', '08:30:00', '12:00:00', 2025),
(10897, 101, '2025-09-20', '13:30:00', '16:30:00', 2025),
(10898, 101, '2025-09-27', '08:30:00', '12:00:00', 2025),
(10899, 101, '2025-09-27', '13:30:00', '16:30:00', 2025),
(10900, 100, '2025-09-26', '15:00:00', '19:00:00', 2025),
(10901, 100, '2025-09-27', '08:00:00', '12:00:00', 2025),
(10902, 100, '2025-09-27', '14:00:00', '16:00:00', 2025),
(10903, 100, '2025-10-04', '08:00:00', '12:00:00', 2025),
(10904, 100, '2025-10-04', '14:00:00', '16:00:00', 2025),
(10905, 92, '2025-10-11', '09:00:00', '11:30:00', 2025),
(10906, 92, '2025-10-11', '13:00:00', '16:00:00', 2025),
(10907, 108, '2025-10-11', '12:00:00', '13:00:00', 2025),
(10908, 98, '2025-11-29', '08:00:00', '12:00:00', 2025),
(10909, 98, '2025-11-29', '13:00:00', '16:00:00', 2025),
(10910, 98, '2025-12-06', '08:00:00', '12:00:00', 2025),
(10911, 98, '2025-12-06', '13:00:00', '16:00:00', 2025),
(10912, 98, '2025-12-07', '08:00:00', '12:00:00', 2025),
(10913, 94, '2025-11-15', '18:00:00', '23:30:00', 2025),
(11650, 126, '2026-03-06', '19:00:00', '23:00:00', 2026),
(11651, 128, '2026-04-18', '08:00:00', '12:00:00', 2026),
(11652, 128, '2026-04-18', '13:30:00', '17:00:00', 2026),
(11653, 128, '2026-04-19', '09:30:00', '11:30:00', 2026),
(11654, 128, '2026-04-25', '08:00:00', '12:00:00', 2026),
(11655, 131, '2026-04-18', '08:00:00', '12:00:00', 2026),
(11656, 131, '2026-04-18', '13:30:00', '17:00:00', 2026),
(11657, 131, '2026-04-19', '09:30:00', '11:30:00', 2026),
(11658, 131, '2026-04-24', '16:00:00', '20:00:00', 2026),
(11659, 131, '2026-04-25', '08:00:00', '12:00:00', 2026),
(11660, 131, '2026-04-25', '13:30:00', '17:00:00', 2026),
(11661, 145, '2026-04-24', '16:00:00', '19:00:00', 2026),
(11662, 145, '2026-04-25', '08:00:00', '12:00:00', 2026),
(11663, 145, '2026-04-25', '13:00:00', '18:00:00', 2026),
(11664, 145, '2026-05-02', '16:00:00', '19:00:00', 2026),
(11665, 145, '2026-05-03', '08:00:00', '12:00:00', 2026),
(11666, 145, '2026-05-03', '13:00:00', '18:00:00', 2026),
(11667, 146, '2026-04-24', '15:00:00', '19:00:00', 2026),
(11668, 146, '2026-04-25', '08:00:00', '12:00:00', 2026),
(11669, 146, '2026-04-25', '13:30:00', '18:00:00', 2026),
(11670, 146, '2026-05-02', '08:00:00', '12:00:00', 2026),
(11671, 146, '2026-05-02', '13:30:00', '16:00:00', 2026),
(11672, 147, '2026-04-25', '08:00:00', '11:30:00', 2026),
(11673, 147, '2026-04-25', '13:30:00', '16:30:00', 2026),
(11674, 147, '2026-05-08', '13:30:00', '19:30:00', 2026),
(11675, 147, '2026-05-09', '08:00:00', '11:30:00', 2026),
(11676, 147, '2026-05-09', '13:30:00', '16:30:00', 2026),
(11677, 150, '2026-04-25', '13:30:00', '17:30:00', 2026),
(11678, 150, '2026-04-26', '09:30:00', '11:30:00', 2026),
(11679, 156, '2026-04-25', '12:00:00', '13:00:00', 2026),
(11680, 149, '2026-05-23', '07:30:00', '17:00:00', 2026),
(11681, 142, '2026-05-29', '18:00:00', '20:00:00', 2026),
(11682, 142, '2026-05-30', '13:30:00', '15:30:00', 2026),
(11683, 142, '2026-05-31', '09:30:00', '11:30:00', 2026),
(11684, 151, '2026-05-30', '08:30:00', '12:00:00', 2026),
(11685, 132, '2026-06-05', '17:00:00', '20:00:00', 2026),
(11686, 132, '2026-06-06', '08:00:00', '11:30:00', 2026),
(11687, 132, '2026-06-06', '13:30:00', '17:00:00', 2026),
(11688, 132, '2026-06-12', '17:00:00', '20:00:00', 2026),
(11689, 132, '2026-05-13', '13:30:00', '17:00:00', 2026),
(11690, 133, '2026-06-09', '18:00:00', '20:00:00', 2026),
(11691, 133, '2026-06-12', '17:00:00', '20:00:00', 2026),
(11692, 133, '2026-06-13', '08:00:00', '12:00:00', 2026),
(11693, 133, '2026-06-13', '13:30:00', '17:00:00', 2026),
(11694, 127, '2026-06-26', '07:30:00', '12:00:00', 2026),
(11695, 134, '2026-08-07', '16:00:00', '20:00:00', 2026),
(11696, 134, '2026-08-08', '08:00:00', '12:00:00', 2026),
(11697, 134, '2026-08-08', '13:00:00', '17:00:00', 2026),
(11698, 134, '2026-08-09', '08:00:00', '12:00:00', 2026),
(11699, 135, '2026-08-07', '17:00:00', '20:00:00', 2026),
(11700, 135, '2026-08-08', '08:00:00', '12:00:00', 2026),
(11701, 135, '2026-08-08', '13:30:00', '16:00:00', 2026),
(11702, 135, '2026-08-21', '17:00:00', '20:00:00', 2026),
(11703, 135, '2026-08-22', '08:00:00', '12:00:00', 2026),
(11704, 135, '2026-08-22', '13:30:00', '16:00:00', 2026),
(11705, 136, '2026-08-07', '17:00:00', '20:00:00', 2026),
(11706, 136, '2026-08-08', '08:00:00', '12:00:00', 2026),
(11707, 136, '2026-08-08', '13:30:00', '16:00:00', 2026),
(11708, 136, '2026-08-21', '17:00:00', '20:00:00', 2026),
(11709, 136, '2026-08-22', '08:00:00', '12:00:00', 2026),
(11710, 136, '2026-08-22', '13:30:00', '16:00:00', 2026),
(11711, 161, '2026-08-07', '17:00:00', '20:00:00', 2026),
(11712, 161, '2026-08-08', '08:00:00', '12:00:00', 2026),
(11713, 161, '2026-08-08', '13:30:00', '16:00:00', 2026),
(11714, 161, '2026-08-21', '17:00:00', '20:00:00', 2026),
(11715, 161, '2026-08-22', '08:00:00', '12:00:00', 2026),
(11716, 161, '2026-08-22', '13:30:00', '16:00:00', 2026),
(11717, 137, '2026-08-14', '17:00:00', '19:30:00', 2026),
(11718, 137, '2026-08-15', '08:00:00', '12:00:00', 2026),
(11719, 137, '2026-08-15', '13:30:00', '18:00:00', 2026),
(11720, 137, '2026-08-22', '08:00:00', '12:00:00', 2026),
(11721, 137, '2026-08-22', '13:30:00', '18:00:00', 2026),
(11722, 137, '2026-08-23', '08:00:00', '12:00:00', 2026),
(11723, 138, '2026-08-14', '17:00:00', '19:30:00', 2026),
(11724, 138, '2026-08-15', '08:00:00', '12:00:00', 2026),
(11725, 138, '2026-08-15', '13:30:00', '18:00:00', 2026),
(11726, 138, '2026-08-22', '08:00:00', '12:00:00', 2026),
(11727, 138, '2026-08-22', '13:30:00', '18:00:00', 2026),
(11728, 138, '2026-08-23', '08:00:00', '12:00:00', 2026),
(11729, 157, '2026-08-22', '12:00:00', '13:00:00', 2026),
(11730, 152, '2026-10-10', '09:00:00', '11:30:00', 2026),
(11731, 152, '2026-10-10', '13:00:00', '16:00:00', 2026),
(11732, 162, '2026-10-10', '11:30:00', '13:00:00', 2026),
(11733, 140, '2026-11-07', '09:30:00', '11:30:00', 2026),
(11734, 140, '2026-11-07', '13:30:00', '15:30:00', 2026),
(11735, 129, '2026-11-14', '18:00:00', '23:59:00', 2026),
(11736, 148, '2026-11-28', '08:00:00', '12:00:00', 2026),
(11737, 148, '2026-11-28', '13:00:00', '16:00:00', 2026),
(11738, 148, '2026-12-05', '08:00:00', '12:00:00', 2026),
(11739, 148, '2026-12-05', '13:00:00', '16:00:00', 2026),
(11740, 148, '2026-12-06', '08:00:00', '12:00:00', 2026),
(11741, 158, '2026-03-05', '19:00:00', '23:00:00', 2026);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jsendschloesen_gaeste`
--

CREATE TABLE `jsendschloesen_gaeste` (
  `id` int(11) NOT NULL,
  `vorname` varchar(100) NOT NULL,
  `nachname` varchar(100) NOT NULL,
  `jahrgang` int(11) NOT NULL,
  `verein` varchar(200) DEFAULT NULL,
  `lizenz_nr` varchar(50) DEFAULT NULL,
  `jahr` int(11) NOT NULL,
  `paket_geloest` tinyint(1) DEFAULT 0,
  `munition_gp11` int(11) DEFAULT 0,
  `munition_gp90` int(11) DEFAULT 0,
  `total_preis` decimal(10,2) DEFAULT 0.00,
  `bemerkung` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jsendschloesen_stiche`
--

CREATE TABLE `jsendschloesen_stiche` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `preis` decimal(10,2) NOT NULL,
  `anzahl_schuss` int(11) NOT NULL,
  `aktiv` tinyint(1) DEFAULT 1,
  `reihenfolge` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `jsendschloesen_stiche`
--

INSERT INTO `jsendschloesen_stiche` (`id`, `name`, `preis`, `anzahl_schuss`, `aktiv`, `reihenfolge`, `created_at`) VALUES
(1, 'Endstich', 20.00, 10, 1, 1, '2025-09-26 08:00:53'),
(2, 'Schwini Passe 1', 20.00, 5, 1, 2, '2025-09-26 08:00:53'),
(3, 'Schwini Passe 2', 16.00, 5, 1, 3, '2025-09-26 08:00:53'),
(4, 'Zabigstich', 19.00, 5, 1, 4, '2025-09-26 08:00:53');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jungschuetzen`
--

CREATE TABLE `jungschuetzen` (
  `id` int(11) NOT NULL,
  `AHVNummer` varchar(16) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Vorname` varchar(255) NOT NULL,
  `Geburtsdatum` date NOT NULL,
  `Strasse` varchar(255) NOT NULL,
  `PLZ` varchar(10) NOT NULL,
  `Ort` varchar(255) NOT NULL,
  `KursNummer` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jungschuetzen_helfer`
--

CREATE TABLE `jungschuetzen_helfer` (
  `ID` int(11) NOT NULL,
  `eventID` int(11) DEFAULT NULL,
  `helferWilen` int(2) DEFAULT 0,
  `helferWollerau` int(2) DEFAULT 0,
  `angeletAM` datetime NOT NULL DEFAULT current_timestamp(),
  `freierTitel` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `jungschuetzen_resultate`
--

CREATE TABLE `jungschuetzen_resultate` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `Belehrungsschiessen1` int(11) DEFAULT NULL,
  `Belehrungsschiessen2` int(11) DEFAULT NULL,
  `Belehrungsschiessen3` int(11) DEFAULT NULL,
  `Praezisionsschiessen` int(11) DEFAULT NULL,
  `Pruefungsschiessen` int(11) DEFAULT NULL,
  `Wettkampfschiessen` int(11) DEFAULT NULL,
  `Hauptschiessen` int(11) DEFAULT NULL,
  `Wettschiessen` int(11) DEFAULT NULL,
  `OPResultat` int(11) DEFAULT NULL,
  `Anerkennungskarte1` int(11) DEFAULT NULL,
  `FSResultat` int(11) DEFAULT NULL,
  `Anerkennungskarte` int(11) DEFAULT NULL,
  `JU_VE_Durchgang1` int(11) DEFAULT NULL,
  `JU_VE_Durchgang2` int(11) DEFAULT NULL,
  `JU_VE_Kategorie` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kantidefinition`
--

CREATE TABLE `kantidefinition` (
  `ID` int(6) NOT NULL,
  `WaffenID` int(1) NOT NULL,
  `Limite` int(2) NOT NULL,
  `Alterskategorie` varchar(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `kantidefinition`
--

INSERT INTO `kantidefinition` (`ID`, `WaffenID`, `Limite`, `Alterskategorie`) VALUES
(1, 1, 90, 'E'),
(2, 1, 88, 'JV'),
(3, 1, 87, 'SV'),
(4, 4, 86, 'E'),
(5, 4, 84, 'JV'),
(6, 4, 83, 'SV'),
(7, 2, 83, 'E'),
(8, 2, 81, 'JV'),
(9, 2, 80, 'SV'),
(10, 3, 83, 'E'),
(11, 3, 81, 'JV'),
(12, 3, 80, 'SV');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kantiresultate`
--

CREATE TABLE `kantiresultate` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `Passe1` int(11) DEFAULT NULL,
  `Passe2` int(11) DEFAULT NULL,
  `Passe3` int(11) DEFAULT NULL,
  `Passe4` int(11) DEFAULT NULL,
  `Passe5` int(11) DEFAULT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `kantiresultate`
--

INSERT INTO `kantiresultate` (`ID`, `MitgliedID`, `Passe1`, `Passe2`, `Passe3`, `Passe4`, `Passe5`, `Jahr`) VALUES
(50, 112108, 85, 88, 91, 97, 89, 2024),
(51, 112114, 91, 94, 94, 93, 95, 2024),
(52, 112103, 90, 88, 0, 0, 0, 2024),
(53, 112137, 84, 90, 89, 87, 86, 2024),
(54, 385067, 87, 92, 0, 0, 0, 2024),
(55, 112140, 94, 95, 97, 95, 95, 2024),
(56, 112144, 97, 96, 94, 0, 0, 2024),
(57, 112102, 91, 87, 92, 93, 90, 2024),
(58, 112126, 92, 0, 0, 0, 0, 2024),
(59, 889594, 89, 98, 0, 0, 0, 2024),
(60, 112131, 93, 96, 93, 96, 94, 2024),
(61, 112111, 90, 0, 0, 0, 0, 2024),
(82, 112114, 95, 97, 96, 89, 93, 2025),
(83, 112109, 0, 0, 0, 0, 0, 2025),
(84, 385067, 90, 95, 0, 0, 0, 2025),
(85, 112140, 96, 95, 91, 93, 97, 2025),
(86, 112141, 0, 0, 0, 0, 0, 2025),
(87, 112108, 90, 94, 93, 95, 94, 2025),
(88, 112126, 82, 85, 0, 0, 0, 2025),
(89, 112131, 98, 94, 93, 96, 97, 2025),
(90, 112097, 0, 0, 0, 0, 0, 2025),
(91, 112101, 91, 98, 94, 97, 95, 2025),
(92, 112134, 0, 0, 0, 0, 0, 2025),
(93, 112103, 88, 92, 0, 0, 0, 2025),
(94, 112137, 84, 82, 86, 82, 85, 2025),
(95, 112139, 0, 0, 0, 0, 0, 2025),
(96, 112111, 85, 0, 0, 0, 0, 2025),
(97, 112144, 95, 98, 97, 95, 93, 2025),
(98, 112102, 90, 90, 0, 0, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kunst`
--

CREATE TABLE `kunst` (
  `ID` int(10) NOT NULL,
  `KSchuss1` int(3) NOT NULL,
  `KSchuss2` int(3) NOT NULL,
  `KSchuss3` int(3) NOT NULL,
  `KSchuss4` int(3) NOT NULL,
  `KSchuss5` int(3) NOT NULL,
  `MitgliedID` int(2) NOT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `kunst`
--

INSERT INTO `kunst` (`ID`, `KSchuss1`, `KSchuss2`, `KSchuss3`, `KSchuss4`, `KSchuss5`, `MitgliedID`, `Jahr`) VALUES
(10, 91, 91, 92, 80, 97, 112103, 2024),
(11, 85, 100, 94, 91, 94, 112114, 2024),
(12, 91, 86, 95, 91, 72, 112101, 2024),
(13, 81, 82, 51, 81, 83, 112109, 2024),
(14, 85, 81, 79, 92, 67, 385067, 2024),
(15, 81, 82, 84, 90, 72, 112108, 2024),
(16, 60, 52, 77, 51, 54, 831789, 2024),
(17, 94, 94, 84, 94, 92, 112102, 2024),
(18, 87, 92, 92, 91, 98, 112141, 2024),
(19, 92, 92, 87, 100, 87, 112140, 2024),
(20, 86, 74, 87, 93, 99, 112137, 2024),
(21, 94, 99, 93, 92, 98, 889594, 2024),
(22, 37, 80, 90, 87, 74, 112111, 2024),
(23, 94, 94, 83, 83, 86, 112131, 2024),
(24, 80, 61, 72, 80, 76, 548406, 2024),
(25, 97, 99, 93, 89, 99, 112144, 2024),
(26, 0, 0, 0, 0, 0, 112139, 2024),
(27, 75, 81, 84, 92, 73, 112126, 2024),
(28, 0, 0, 0, 0, 0, 112097, 2024),
(29, 0, 0, 0, 0, 0, 112104, 2024),
(66, 93, 89, 86, 83, 89, 112140, 2025),
(67, 95, 99, 85, 85, 99, 385067, 2025),
(68, 93, 91, 78, 75, 76, 112114, 2025),
(69, 83, 91, 85, 93, 71, 112109, 2025),
(75, 82, 83, 85, 77, 85, 112108, 2025),
(76, 90, 93, 63, 92, 90, 831789, 2025),
(77, 96, 82, 85, 75, 68, 112126, 2025),
(78, 94, 92, 89, 92, 91, 112131, 2025),
(79, 98, 81, 76, 87, 94, 112101, 2025),
(80, 83, 79, 91, 90, 92, 112102, 2025),
(81, 85, 78, 89, 92, 95, 112103, 2025),
(82, 86, 75, 85, 83, 85, 112137, 2025),
(83, 0, 0, 0, 0, 0, 112139, 2025),
(84, 88, 82, 91, 89, 62, 548406, 2025),
(85, 77, 89, 93, 80, 81, 112141, 2025),
(86, 100, 76, 80, 85, 89, 112111, 2025),
(87, 92, 94, 85, 82, 95, 112144, 2025),
(88, 0, 0, 0, 0, 0, 29093, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `kunst_jung`
--

CREATE TABLE `kunst_jung` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `KSchuss1` int(11) DEFAULT NULL,
  `KSchuss2` int(11) DEFAULT NULL,
  `KSchuss3` int(11) DEFAULT NULL,
  `KSchuss4` int(11) DEFAULT NULL,
  `KSchuss5` int(11) DEFAULT NULL,
  `Jahr` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglieder`
--

CREATE TABLE `mitglieder` (
  `ID` int(11) NOT NULL,
  `Anrede` enum('Herr','Frau') DEFAULT NULL,
  `Vorname` varchar(255) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Geburtsdatum` date NOT NULL,
  `WaffenID` int(11) NOT NULL,
  `Status` tinyint(1) NOT NULL DEFAULT 0,
  `Ehrenmitglied` tinyint(1) NOT NULL,
  `Strasse` varchar(255) DEFAULT NULL,
  `PLZ` varchar(10) DEFAULT NULL,
  `Ort` varchar(100) DEFAULT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `Telefon` varchar(50) DEFAULT NULL,
  `Mobile` varchar(50) DEFAULT NULL,
  `Notizen` text DEFAULT NULL,
  `Verstorben` tinyint(1) NOT NULL DEFAULT 0,
  `Vereinsaufnahme` year(4) DEFAULT NULL,
  `Kommunikation` enum('Briefpost','Whatsapp','Beides') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglieder`
--

INSERT INTO `mitglieder` (`ID`, `Anrede`, `Vorname`, `Name`, `Geburtsdatum`, `WaffenID`, `Status`, `Ehrenmitglied`, `Strasse`, `PLZ`, `Ort`, `Email`, `Telefon`, `Mobile`, `Notizen`, `Verstorben`, `Vereinsaufnahme`, `Kommunikation`) VALUES
(24853, 'Frau', 'Marina', 'Linggi', '1989-12-27', 2, 0, 0, 'Im Büehl 17', '8564', 'Wäldi', '', '', '', '', 0, NULL, NULL),
(29093, 'Herr', 'Ingo', 'Weinem', '1968-01-02', 1, 1, 0, 'Brandweid 26a', '8852', 'Altendorf', 'iweinem@me.com', '+41 79 855 05 45', '+41 79 855 05 45', '', 0, NULL, NULL),
(41011, NULL, 'Elin', 'Lienert', '2014-02-12', 2, 0, 0, '', '', '', '', '', NULL, NULL, 0, NULL, NULL),
(110379, 'Herr', 'Markus', 'Rickenbach', '1968-02-02', 3, 0, 0, 'Churerstrasse 85a', '8808', 'Pfäffikon SZ', 'rickenbach@swissonline.ch', '', '+41 79 216 77 36', '', 0, NULL, NULL),
(112097, 'Herr', 'Andreas', 'Linggi', '1957-08-30', 1, 1, 1, 'Felsenstrasse 156', '8832', 'Wollerau', 'ae.linggi@hispeed.ch', '', '+41 78 773 73 48', '', 0, '1981', NULL),
(112099, 'Frau', 'Ella', 'Linggi', '1961-10-23', 1, 0, 1, 'Felsenstrasse 156', '8832', 'Wollerau', '', '', '+41 78 773 81 76', '', 1, '1993', NULL),
(112101, 'Herr', 'Renato', 'Linggi', '1986-09-04', 1, 1, 1, 'Neuhusweg 4', '8805', 'Richterswil', 'renato.linggi@msvwilen.ch', '', '+41 78 602 96 72', '', 0, NULL, NULL),
(112102, 'Herr', 'Hanspeter', 'Schober', '1954-05-07', 1, 1, 1, 'Untere Burgwies 7', '8864', 'Reichenburg', 'hpschober@bluewin.ch', '', '+41 79 608 90 20', '', 0, '1975', NULL),
(112103, 'Herr', 'Marco', 'Schober', '1982-08-24', 1, 1, 0, 'Sihlboden 2', '8847', 'Egg SZ', 'marco.schober@bluemail.ch', '', '+41 79 519 11 88', '', 0, NULL, NULL),
(112104, 'Herr', 'Josef', 'Lienert', '1954-08-17', 3, 0, 1, 'Breitenstr. 81', '8832', 'Wilen', 'j.lienert@bluewin.ch', '', '+41 79 378 48 27', '', 0, '1973', 'Briefpost'),
(112105, 'Herr', 'Karl', 'Bachmann', '1936-12-06', 3, 0, 1, 'Schollenmatt 4', '8832', 'Wollerau', 'karlbachmann@hotmail.com', '+41 44 784 26 39', '', '', 0, '1955', 'Briefpost'),
(112108, 'Herr', 'Roger', 'Cavelti', '1978-05-20', 2, 1, 1, 'Summelenweg 49', '8808', 'Pfäffikon', 'roger.cavelti@msvwilen.ch', '', '+41 79 432 51 72', '', 0, NULL, NULL),
(112109, 'Herr', 'Stefan', 'Hiestand', '1978-12-04', 2, 1, 0, 'Kapellstrasse 9a', '8847', 'Egg SZ', 'st.hiestand@bluewin.ch', '', '+41 78 731 68 59', '', 0, NULL, NULL),
(112111, 'Frau', 'Judith', 'von Euw', '1973-10-02', 2, 1, 0, 'Windeggweg 10', '8832', 'Wilen b. Wollerau', 'siminik.jve@gmail.com', '', '+41 79 709 05 60', '', 0, NULL, NULL),
(112114, 'Herr', 'Michael', 'Fuchs', '1984-08-31', 1, 1, 0, 'Gutenbrunnen 21A', '8852', 'Altendorf', 'fuchs.michael@gmx.ch', '', '+41 79 519 20 59', '', 0, NULL, NULL),
(112126, 'Herr', 'Norbert', 'Kälin', '1972-06-17', 2, 1, 1, 'Gässlistrasse 1a', '8856', 'Tuggen', 'norbi.kaelin@gmx.ch', '', '+41 79 471 33 77', '', 0, '1991', NULL),
(112131, 'Herr', 'Roman', 'Lienert', '1987-10-30', 1, 1, 0, 'Wilenstrasse 90d', '8832', 'Wilen', 'roman.lienert@gmail.com', '', '+41 79 478 34 60', '', 0, NULL, NULL),
(112134, 'Herr', 'Hans', 'Müller', '1947-08-08', 2, 1, 1, 'Wilenstrasse 136', '8832', 'Wilen', '', '+41 44 784 68 39', NULL, NULL, 0, '1966', 'Briefpost'),
(112137, 'Herr', 'Paul', 'Sigrist', '1952-01-29', 3, 1, 1, 'Churerstrasse 92e', '8808', 'Pfäffikon', 'e.p.sigrist@bluewin.ch', '+41 55 410 11 89', '+41 79 590 17 61', '', 0, NULL, 'Briefpost'),
(112139, 'Herr', 'Robert', 'Sturzenegger', '1953-12-14', 4, 1, 1, 'Eulenbachstrasse 10', '8832', 'Wilen', 'sturzenegger.roebi@bluewin.ch', '', '+41 79 614 78 12', '', 0, '1973', 'Briefpost'),
(112140, 'Herr', 'Alexander', 'von Euw', '1978-11-02', 1, 1, 0, 'Sihlboden 4', '8847', 'Egg SZ', 'alex@voneuw-architektur.ch', '', '+41 79 476 54 01', '', 0, NULL, NULL),
(112141, 'Herr', 'Christian', 'von Euw', '1987-06-22', 1, 1, 0, 'Wilenstrasse 151', '8832', 'Wilen', 'christian-voneuw@hotmail.com', '', '+41 79 519 25 81', '', 0, NULL, NULL),
(112144, 'Herr', 'Stefan', 'von Euw', '1975-03-23', 1, 1, 0, 'Windeggweg 10', '8832', 'Wilen', '', '', '+41 79 476 54 03', NULL, 0, '1993', NULL),
(226960, NULL, 'Marco', 'Linggi', '1988-09-23', 1, 0, 0, 'Altmattstr. 25', '6418', 'Rothenthurm', 'marco.linggi@hispeed.ch', '', '+41 78 870 15 58', '', 0, NULL, NULL),
(385067, 'Herr', 'Mark', 'Unterkofler', '1986-06-30', 1, 1, 0, 'Rötlistrasse 2a', '8717', 'Benken SG', 'mark@unterkofler.ch', '', '+41 79 222 72 85', '', 0, NULL, NULL),
(389561, 'Herr', 'Robin', 'Wittek', '1994-01-24', 3, 1, 0, 'Rathausplatz 3', '8853', 'Lachen SZ', 'robin.wittek@msvwilen.ch', '', '+41 77 420 72 51', '', 0, NULL, NULL),
(548406, 'Herr', 'Ivo', 'Thomi', '1994-06-13', 2, 1, 0, 'Altmattstrasse 2a', '6418', 'Rothenthurm', 'thomi.ivo@bluewin.ch', '', '+41 79 534 43 07', '', 0, NULL, NULL),
(831789, 'Herr', 'Joshua', 'Kälin', '2001-04-27', 2, 1, 0, 'Langackerweg 5', '8807', 'Freienbach', '', '', NULL, NULL, 0, NULL, NULL),
(889594, 'Herr', 'Marcus', 'König', '1977-03-16', 1, 1, 0, 'Rosenhof 5', '8808', 'Pfäffikon SZ', 'mail@marcus-koenig.ch', '', '+41 79 288 16 21', '', 0, '2021', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglieder_aenderungen`
--

CREATE TABLE `mitglieder_aenderungen` (
  `id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `feld` varchar(50) NOT NULL,
  `alter_wert` varchar(255) DEFAULT NULL,
  `neuer_wert` varchar(255) DEFAULT NULL,
  `geaendert_am` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglieder_aenderungen`
--

INSERT INTO `mitglieder_aenderungen` (`id`, `mitglied_id`, `user_id`, `feld`, `alter_wert`, `neuer_wert`, `geaendert_am`) VALUES
(1, 112101, 1, 'Telefon', '0786029672', '', '2026-02-25 16:24:53'),
(2, 112101, 1, 'Mobile', '', '0786029672', '2026-02-25 16:24:53'),
(3, 112101, 1, 'Telefon', '', '+41 79 916 14 61', '2026-02-27 14:34:36'),
(4, 112101, 1, 'Telefon', '+41 79 916 14 61', '', '2026-02-27 14:34:44'),
(5, 112111, 16, 'Ort', 'Wilen', 'Wilen b. Wollerau', '2026-03-06 22:10:26'),
(6, 112111, 16, 'Email', 'j.voneuw@hispeed.ch', 'siminik.jve@gmail.com', '2026-03-06 22:10:26'),
(7, 112111, 16, 'Telefon', '', '+41 78 218 19 01', '2026-03-06 22:10:26'),
(8, 112111, 16, 'Telefon', '+41 78 218 19 01', '', '2026-03-06 22:10:43'),
(9, 29093, 15, 'Telefon', '', '+41 79 855 05 45', '2026-03-06 22:17:33'),
(10, 29093, 15, 'Mobile', '', '+41 79 855 05 45', '2026-03-06 22:17:33'),
(11, 112137, 23, 'Telefon', '', '+41 55 410 11 89', '2026-03-12 09:21:25'),
(12, 112137, 23, 'Mobile', '+41 77 460 19 61', '+41 79 590 17 61', '2026-03-12 09:21:25');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglieder_fragebogen`
--

CREATE TABLE `mitglieder_fragebogen` (
  `ID` int(11) NOT NULL,
  `mitgliedID` int(11) NOT NULL,
  `jahr` int(11) NOT NULL,
  `waffenID` int(11) NOT NULL,
  `mannschaft` varchar(20) NOT NULL,
  `gruppen` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglieder_fragebogen`
--

INSERT INTO `mitglieder_fragebogen` (`ID`, `mitgliedID`, `jahr`, `waffenID`, `mannschaft`, `gruppen`) VALUES
(25, 112105, 2025, 3, 'nicht', 'nicht'),
(26, 112108, 2025, 2, 'evtl', 'evtl'),
(27, 112114, 2025, 1, 'teil', 'teil'),
(28, 112109, 2025, 2, 'nicht', 'nicht'),
(29, 831789, 2025, 2, 'nicht', 'nicht'),
(30, 112126, 2025, 2, 'nicht', 'nicht'),
(31, 889594, 2025, 1, 'nicht', 'nicht'),
(32, 112104, 2025, 3, 'nicht', 'nicht'),
(33, 112131, 2025, 1, 'teil', 'teil'),
(34, 112097, 2025, 1, 'nicht', 'nicht'),
(35, 112101, 2025, 1, 'teil', 'nicht'),
(36, 112134, 2025, 2, 'nicht', 'nicht'),
(37, 110379, 2025, 3, 'nicht', 'nicht'),
(38, 112102, 2025, 1, 'evtl', 'nicht'),
(39, 112103, 2025, 1, 'nicht', 'nicht'),
(40, 112137, 2025, 3, 'nicht', 'nicht'),
(41, 112139, 2025, 4, 'nicht', 'nicht'),
(42, 548406, 2025, 2, 'nicht', 'nicht'),
(43, 385067, 2025, 2, 'teil', 'teil'),
(44, 112140, 2025, 1, 'teil', 'teil'),
(45, 112141, 2025, 1, 'nicht', 'evtl'),
(46, 112111, 2025, 2, 'nicht', 'nicht'),
(47, 112144, 2025, 1, 'teil', 'teil'),
(48, 389561, 2025, 3, 'nicht', 'nicht'),
(49, 29093, 2025, 1, 'nicht', 'nicht'),
(50, 112101, 2026, 1, 'teil', 'evtl'),
(51, 112103, 2026, 1, 'nicht', 'nicht'),
(52, 112108, 2026, 2, 'teil', 'evtl'),
(53, 112111, 2026, 2, 'nicht', 'nicht'),
(54, 889594, 2026, 1, 'nicht', 'nicht'),
(55, 112141, 2026, 1, 'nicht', 'evtl'),
(56, 112102, 2026, 1, 'nicht', 'nicht'),
(57, 112126, 2026, 2, 'nicht', 'nicht'),
(58, 112109, 2026, 2, 'nicht', 'nicht'),
(59, 385067, 2026, 1, 'teil', 'teil'),
(60, 548406, 2026, 2, 'teil', 'teil'),
(61, 112137, 2026, 3, 'nicht', 'nicht'),
(62, 112131, 2026, 1, 'teil', 'teil');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `mitglieder_fragebogen_erweitert`
--

CREATE TABLE `mitglieder_fragebogen_erweitert` (
  `ID` int(11) NOT NULL,
  `fragebogenID` int(11) NOT NULL,
  `jmdefinitionID` int(11) NOT NULL,
  `antwort` varchar(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `mitglieder_fragebogen_erweitert`
--

INSERT INTO `mitglieder_fragebogen_erweitert` (`ID`, `fragebogenID`, `jmdefinitionID`, `antwort`) VALUES
(169, 25, 73, 'nein'),
(170, 25, 88, 'nein'),
(171, 25, 78, 'nein'),
(172, 25, 99, 'nein'),
(173, 25, 100, 'nein'),
(174, 25, 101, 'nein'),
(175, 25, 98, 'nein'),
(176, 26, 73, 'nein'),
(177, 26, 88, 'nein'),
(178, 26, 78, 'nein'),
(179, 26, 99, 'nein'),
(180, 26, 100, 'nein'),
(181, 26, 101, 'nein'),
(182, 26, 98, 'nein'),
(183, 27, 73, 'nein'),
(184, 27, 88, 'nein'),
(185, 27, 78, 'nein'),
(186, 27, 99, 'nein'),
(187, 27, 100, 'nein'),
(188, 27, 101, 'nein'),
(189, 27, 98, 'nein'),
(190, 28, 73, 'nein'),
(191, 28, 88, 'nein'),
(192, 28, 78, 'nein'),
(193, 28, 99, 'nein'),
(194, 28, 100, 'nein'),
(195, 28, 101, 'nein'),
(196, 28, 98, 'nein'),
(197, 29, 73, 'nein'),
(198, 29, 88, 'nein'),
(199, 29, 78, 'nein'),
(200, 29, 99, 'nein'),
(201, 29, 100, 'nein'),
(202, 29, 101, 'nein'),
(203, 29, 98, 'nein'),
(204, 30, 73, 'nein'),
(205, 30, 88, 'nein'),
(206, 30, 78, 'nein'),
(207, 30, 99, 'nein'),
(208, 30, 100, 'nein'),
(209, 30, 101, 'nein'),
(210, 30, 98, 'nein'),
(211, 31, 73, 'nein'),
(212, 31, 88, 'nein'),
(213, 31, 78, 'nein'),
(214, 31, 99, 'nein'),
(215, 31, 100, 'nein'),
(216, 31, 101, 'nein'),
(217, 31, 98, 'nein'),
(218, 32, 73, 'nein'),
(219, 32, 88, 'nein'),
(220, 32, 78, 'nein'),
(221, 32, 99, 'nein'),
(222, 32, 100, 'nein'),
(223, 32, 101, 'nein'),
(224, 32, 98, 'nein'),
(225, 33, 73, 'nein'),
(226, 33, 88, 'nein'),
(227, 33, 78, 'nein'),
(228, 33, 99, 'nein'),
(229, 33, 100, 'nein'),
(230, 33, 101, 'nein'),
(231, 33, 98, 'nein'),
(232, 34, 73, 'nein'),
(233, 34, 88, 'nein'),
(234, 34, 78, 'nein'),
(235, 34, 99, 'nein'),
(236, 34, 100, 'nein'),
(237, 34, 101, 'nein'),
(238, 34, 98, 'nein'),
(239, 35, 73, 'nein'),
(240, 35, 88, 'nein'),
(241, 35, 78, 'nein'),
(242, 35, 99, 'nein'),
(243, 35, 100, 'nein'),
(244, 35, 101, 'nein'),
(245, 35, 98, 'nein'),
(246, 36, 73, 'nein'),
(247, 36, 88, 'nein'),
(248, 36, 78, 'nein'),
(249, 36, 99, 'nein'),
(250, 36, 100, 'nein'),
(251, 36, 101, 'nein'),
(252, 36, 98, 'nein'),
(253, 37, 73, 'nein'),
(254, 37, 88, 'nein'),
(255, 37, 78, 'nein'),
(256, 37, 99, 'nein'),
(257, 37, 100, 'nein'),
(258, 37, 101, 'nein'),
(259, 37, 98, 'nein'),
(260, 38, 73, 'nein'),
(261, 38, 88, 'nein'),
(262, 38, 78, 'nein'),
(263, 38, 99, 'ja'),
(264, 38, 100, 'ja'),
(265, 38, 101, 'nein'),
(266, 38, 98, 'ja'),
(267, 39, 73, 'nein'),
(268, 39, 88, 'nein'),
(269, 39, 78, 'nein'),
(270, 39, 99, 'nein'),
(271, 39, 100, 'nein'),
(272, 39, 101, 'nein'),
(273, 39, 98, 'nein'),
(274, 40, 73, 'nein'),
(275, 40, 88, 'nein'),
(276, 40, 78, 'nein'),
(277, 40, 99, 'nein'),
(278, 40, 100, 'nein'),
(279, 40, 101, 'nein'),
(280, 40, 98, 'ja'),
(281, 41, 73, 'nein'),
(282, 41, 88, 'nein'),
(283, 41, 78, 'nein'),
(284, 41, 99, 'nein'),
(285, 41, 100, 'nein'),
(286, 41, 101, 'nein'),
(287, 41, 98, 'nein'),
(288, 42, 73, 'nein'),
(289, 42, 88, 'nein'),
(290, 42, 78, 'nein'),
(291, 42, 99, 'nein'),
(292, 42, 100, 'nein'),
(293, 42, 101, 'nein'),
(294, 42, 98, 'nein'),
(295, 43, 73, 'nein'),
(296, 43, 88, 'nein'),
(297, 43, 78, 'nein'),
(298, 43, 99, 'nein'),
(299, 43, 100, 'nein'),
(300, 43, 101, 'nein'),
(301, 43, 98, 'ja'),
(302, 44, 73, 'nein'),
(303, 44, 88, 'nein'),
(304, 44, 78, 'nein'),
(305, 44, 99, 'nein'),
(306, 44, 100, 'nein'),
(307, 44, 101, 'nein'),
(308, 44, 98, 'nein'),
(309, 45, 73, 'nein'),
(310, 45, 88, 'nein'),
(311, 45, 78, 'nein'),
(312, 45, 99, 'ja'),
(313, 45, 100, 'ja'),
(314, 45, 101, 'ja'),
(315, 45, 98, 'ja'),
(316, 46, 73, 'nein'),
(317, 46, 88, 'nein'),
(318, 46, 78, 'nein'),
(319, 46, 99, 'nein'),
(320, 46, 100, 'nein'),
(321, 46, 101, 'nein'),
(322, 46, 98, 'nein'),
(323, 47, 73, 'nein'),
(324, 47, 88, 'nein'),
(325, 47, 78, 'nein'),
(326, 47, 99, 'ja'),
(327, 47, 100, 'ja'),
(328, 47, 101, 'ja'),
(329, 47, 98, 'ja'),
(330, 48, 73, 'nein'),
(331, 48, 88, 'nein'),
(332, 48, 78, 'nein'),
(333, 48, 99, 'nein'),
(334, 48, 100, 'nein'),
(335, 48, 101, 'nein'),
(336, 48, 98, 'nein'),
(337, 49, 73, 'nein'),
(338, 49, 88, 'nein'),
(339, 49, 78, 'nein'),
(340, 49, 99, 'nein'),
(341, 49, 100, 'nein'),
(342, 49, 101, 'nein'),
(343, 49, 98, 'nein'),
(344, 50, 140, 'ja'),
(345, 50, 148, 'nein'),
(346, 51, 140, 'nein'),
(347, 51, 148, 'nein'),
(348, 52, 140, 'nein'),
(349, 52, 148, 'nein'),
(350, 53, 140, 'nein'),
(351, 53, 148, 'nein'),
(352, 54, 140, 'nein'),
(353, 54, 148, 'nein'),
(354, 55, 140, 'nein'),
(355, 55, 148, 'ja'),
(356, 56, 140, 'ja'),
(357, 56, 148, 'ja'),
(358, 57, 140, 'nein'),
(359, 57, 148, 'nein'),
(360, 58, 140, 'nein'),
(361, 58, 148, 'nein'),
(362, 59, 140, 'nein'),
(363, 59, 148, 'nein'),
(364, 60, 140, 'ja'),
(365, 60, 148, 'nein'),
(366, 61, 140, 'ja'),
(367, 61, 148, 'nein'),
(368, 62, 140, 'ja'),
(369, 62, 148, 'ja');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `msv_refresh_tokens`
--

CREATE TABLE `msv_refresh_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `msv_refresh_tokens`
--

INSERT INTO `msv_refresh_tokens` (`id`, `user_id`, `token_hash`, `device_info`, `expires_at`, `created_at`) VALUES
(5, 1, '47c352ea89e57d13e77464891bb9eaff11d0852c5d8ba7a75947d8b719dfed60', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.3 Mobile/15E148 Safari/604.1', '2026-03-19 13:52:48', '2026-02-17 12:52:48');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `munitionskauf`
--

CREATE TABLE `munitionskauf` (
  `id` int(11) NOT NULL,
  `jahr` year(4) NOT NULL,
  `kauf_datum` date NOT NULL,
  `anlass` varchar(100) DEFAULT NULL,
  `mitglied_id` int(11) DEFAULT NULL,
  `gast_name` varchar(100) DEFAULT NULL,
  `gp11_total` int(11) NOT NULL DEFAULT 0,
  `gp90_total` int(11) NOT NULL DEFAULT 0,
  `total_preis` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `munitionskauf`
--

INSERT INTO `munitionskauf` (`id`, `jahr`, `kauf_datum`, `anlass`, `mitglied_id`, `gast_name`, `gp11_total`, `gp90_total`, `total_preis`, `created_at`, `updated_at`) VALUES
(7, '2025', '2025-08-27', '', 112101, NULL, 60, 0, 3000.00, '2025-09-17 15:43:00', NULL),
(8, '2025', '2025-09-17', '', 112140, NULL, 60, 0, 3000.00, '2025-09-17 16:37:30', NULL),
(10, '2025', '2025-09-24', '', 112101, NULL, 60, 50, 5500.00, '2025-09-24 16:10:04', NULL),
(11, '2025', '2025-10-08', '', 112101, NULL, 0, 50, 2500.00, '2025-10-08 16:16:06', NULL),
(12, '2025', '2025-10-08', '', 112126, NULL, 0, 100, 5000.00, '2025-10-08 16:25:39', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `munitionskauf_details`
--

CREATE TABLE `munitionskauf_details` (
  `id` int(11) NOT NULL,
  `bestellung_id` int(11) NOT NULL,
  `typ` varchar(50) NOT NULL COMMENT 'GP11_60, GP11_24, GP11_CUSTOM, GP90_50, GP90_24, GP90_CUSTOM',
  `anzahl` int(11) NOT NULL,
  `preis_pro_schuss` int(11) NOT NULL DEFAULT 50 COMMENT 'in Rappen'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `munitionskauf_details`
--

INSERT INTO `munitionskauf_details` (`id`, `bestellung_id`, `typ`, `anzahl`, `preis_pro_schuss`) VALUES
(7, 7, 'GP11_60', 60, 50),
(8, 8, 'GP11_60', 60, 50),
(10, 10, 'GP11_60', 60, 50),
(11, 10, 'GP90_50', 50, 50),
(12, 11, 'GP90_50', 50, 50),
(13, 12, 'GP90_CUSTOM', 100, 50);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `navigation`
--

CREATE TABLE `navigation` (
  `ID` int(11) NOT NULL,
  `Text` varchar(30) NOT NULL,
  `Link` varchar(30) NOT NULL,
  `ParentID` int(11) NOT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `navigation`
--

INSERT INTO `navigation` (`ID`, `Text`, `Link`, `ParentID`, `SortOrder`) VALUES
(1, 'Resultat Erfassung', '', 0, 0),
(2, 'Ranglisten', '', 0, 10),
(3, 'Jahresmeisterschaft Erfassung', 'jmresultate.php', 1, 0),
(6, 'Heimmeisterschaft Erfassung', 'heimresultate.php', 1, 10),
(7, 'Kantonalstich Erfassung', 'kantiresultate.php', 1, 20),
(8, 'Endschiessen Erfassung', '#', 1, 30),
(13, 'Definitionen / Ausdrucke', '', 0, 20),
(15, 'Jahresmeisterschaft Rangliste', 'jmrang.php', 2, 0),
(16, 'Heimmeisterschaft Rangliste', 'heimrang.php', 2, 10),
(17, 'Kantonalstich Rangliste', 'kantirang.php', 2, 20),
(18, 'Endschiessen Rangliste', 'endschrang.php', 2, 30),
(19, 'Vereinscup Rangliste', 'cuprang.php', 2, 40),
(20, 'Abrechnung', '', 0, 30),
(22, 'Kantonalstich', 'kantiabr.php', 20, 10),
(23, 'Schützenabrechnung', 'schuetzenabr.php', 20, 20),
(26, 'Vereinscup', 'cup.php', 1, 40),
(27, 'JSK', '', 0, 40),
(28, 'Jungschützenverwaltung', 'jsverwaltung.php', 27, 0),
(29, 'JSK Endschiessen', 'jsendschresultate.php', 27, 10),
(30, 'JSK Resultaterfassung', 'jungschuetzen_erfassung.php', 27, 20),
(89, 'Jahresmeisterschaft Definition', 'jmdefinition.php', 13, 10),
(90, 'Gruppenschiessen', 'jmdefinition_gruppen.php', 13, 20),
(91, 'Monatsblatt', 'monatsblatt.php', 13, 40),
(92, 'Weitere wichtige Termine', 'wichtigetermine.php', 13, 30),
(94, 'Mitgliederverwaltung', 'mitgliederverwaltung.php', 13, 0),
(95, 'Auswertung Fragebogen', 'mitgliederfragebogen.php', 13, 60),
(96, 'Sieger der letzten Jahre', 'sieger.php', 13, 70),
(10094, 'Helferstunden erfassen', 'jungschuetzen_helfer.php', 27, 30),
(10095, 'Sektionsabrechnungen', 'jmdurchschnitt.php ', 2, 50),
(10096, 'Sektionsrangierungen', 'sektionsrangierungen.php', 1, 50),
(10097, 'Einzelrangierungen', 'einzelrangierung.php', 1, 60),
(10098, 'Import Heim / Kanti', 'heimkanti_import.php', 10100, 0),
(10099, 'Import Endschiessen', 'endsch_import.php', 10100, 10),
(10100, 'Schiessdaten Import', '', 0, 50),
(10102, 'Stiche Imetron für Import', 'internestichedef.php', 10100, 20),
(10103, 'Imetron File prüfen', 'check_resultscsv.php', 10100, 30),
(10104, 'Resultaterfassung Partner', 'endresultate_partner.php', 8, 20),
(10106, 'Wanderpreise', 'wanderpreise.php', 2, 60),
(10107, 'Wanderpreis Regeln', 'wanderpreise_regeln.php', 13, 80),
(10113, 'Anmeldung / Stichverkauf', 'endschloesen.php', 8, 0),
(10114, 'Munitionsverkauf', 'munitionskauf.php', 20, 0),
(10116, 'Partner Scheiben Ausdruck', 'endsch_targetprint.php', 18, 0),
(10117, 'Standbelegung', 'standbelegung.php', 13, 50),
(10119, 'Passwort ändern', 'password_change.php', 0, 60),
(10120, 'Sichern & Wiederherstellen', 'backup_restore.php', 0, 70),
(10122, 'Resultaterfassung Mitglieder', 'endresultate.php', 8, 10);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Parameter`
--

CREATE TABLE `Parameter` (
  `year` year(4) NOT NULL,
  `excludeCount` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `Parameter`
--

INSERT INTO `Parameter` (`year`, `excludeCount`) VALUES
('2024', 3),
('2025', 3),
('2026', 3);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `remember_tokens`
--

CREATE TABLE `remember_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` varchar(64) NOT NULL COMMENT 'SHA-256-Hash des Cookie-Tokens',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `remember_tokens`
--

INSERT INTO `remember_tokens` (`id`, `user_id`, `token_hash`, `expires_at`, `created_at`) VALUES
(5, 6, '315ffd29f5cd2fb779218c4b865caf9f22a286427ea3cc8c62704b222995fedc', '2026-03-20 15:39:31', '2026-02-18 14:39:31'),
(6, 6, '41314ae1321e5d129cb6d50a40ebe5c5cec61ae068ee716688f95e4e83175254', '2026-03-20 15:43:32', '2026-02-18 14:43:32'),
(7, 6, 'b361f17e8716a366e853dbcc9b4d4df9e8faa82f410accc489021b23d199071d', '2026-03-20 15:43:49', '2026-02-18 14:43:49'),
(8, 6, 'a094203271fc9447fe6c344eef72bcda7f305884964a80a9eea0e6f7381e6ee6', '2026-03-20 15:47:34', '2026-02-18 14:47:34'),
(9, 6, '641e7a2ccda74ffa42a6ec14a0fe2f234789bd423101b27da8d72a0b022894b4', '2026-03-20 15:56:38', '2026-02-18 14:56:38'),
(10, 6, '1397a6289df1c266add7f15e9a88c16f5a737b1e30dccce88bf5cd46a85b4ad7', '2026-03-20 15:57:15', '2026-02-18 14:57:15'),
(11, 6, 'ae9b62b9463d8772455d7aa0de26819a0aa11449e56c4c8105a0609446eead84', '2026-03-20 15:58:52', '2026-02-18 14:58:52'),
(12, 6, '85a1afa59eb53110678b1426a55a16b76ea2fb8a7cc3184577e4a5f97d08a93c', '2026-03-20 16:01:17', '2026-02-18 15:01:17'),
(13, 6, '041d91d6120f89f6bdfb121006b56392271777db64cc83e036f19c32814458a5', '2026-03-20 16:09:13', '2026-02-18 15:09:13'),
(14, 6, '1b059372aa0d32caa22664f540ddf9a2cf43116921156620c27e0322851fdbcb', '2026-03-20 16:10:39', '2026-02-18 15:10:39'),
(15, 6, 'da58b8783427d53f77c69554d9425cd6126ef4c8b65ae065829d781064b4e10f', '2026-03-20 16:10:55', '2026-02-18 15:10:55'),
(16, 6, '7942891b28bff1c89aa8e4a09293d6ea68898035b42e118fac7d2a17edf3cca6', '2026-03-20 16:15:30', '2026-02-18 15:15:30'),
(17, 6, 'c3702cffb022ee6b0042a77f79428c1d192d600a6020394bc28db570ed4010c1', '2026-03-20 16:15:51', '2026-02-18 15:15:51'),
(18, 6, 'de0d36cd633c2e06aad95c2774d909eb7332718dd5f9a0a92ce953d9bea2e04d', '2026-03-20 16:48:17', '2026-02-18 15:48:17'),
(19, 6, '44d04f30f8a3f1dfc3568a7aad4be3736ff8fe1ce9a250e0cb198173cccc3f39', '2026-03-20 16:55:33', '2026-02-18 15:55:33'),
(26, 7, 'cd3ec89cab065b5f5ff0425cb730403eac1e105fcf9167e894818e986f60744a', '2026-03-20 18:11:21', '2026-02-18 17:11:21'),
(36, 11, 'ef756267747cae868d3b546fec9483b5c147855ef5d5bcbdc81ff4dddec4806e', '2026-03-21 15:41:44', '2026-02-19 14:41:44'),
(49, 12, '52be952de573304e6696639eff97940480d88b4e805debf88cd9386bc00fd015', '2026-03-24 01:35:53', '2026-02-22 00:35:53'),
(50, 12, '8e27e7382907fef879e72c94455b3333bbced53679950182853a2a83fae8fec4', '2026-03-24 09:59:35', '2026-02-22 08:59:35'),
(60, 12, '289f3d364ad2239c6f214da23b537993182943ed88d1e5a7b4cd76fd686d3cb7', '2026-03-25 08:21:30', '2026-02-23 07:21:30'),
(101, 11, '60d252c57b683084748140908bafeac025f4324ad0a50d7a16ff517e3d57bf42', '2026-03-27 08:37:34', '2026-02-25 07:37:34'),
(102, 11, '8be5b4fe4ee83e3a5fadff9ccf7bcbba496617eb81b99cc027c67b4bb27bc460', '2026-03-27 08:37:54', '2026-02-25 07:37:54'),
(103, 11, '72a61786296f5b6d3c025f6843a9039df208016b5d6f9489642257a2bd9061cd', '2026-03-27 09:04:12', '2026-02-25 08:04:12'),
(106, 1, 'b975ecba4b0eaea1ddffb133e6472068ed407e966233e0196d08a89f5e22378b', '2026-03-27 13:17:00', '2026-02-25 12:17:00'),
(107, 12, 'bc5e41664b7a6c4411a52a04e0faebe8db905acecf1b67ddae004b437762fb32', '2026-03-27 14:29:08', '2026-02-25 13:29:08'),
(108, 1, '3b0c0a817f161f1e0c9c468e966e81d290ab724ab9d22143f846b7d85b0aa70b', '2026-03-27 16:24:11', '2026-02-25 15:24:11'),
(109, 1, '8cb0393eaf267dbb7177f7c26844948b926f60cba3f3a959fefa8bcb7fb0c924', '2026-03-28 11:42:49', '2026-02-26 10:42:49'),
(110, 1, 'da5263810b1b8bccf014941a89deaaa691506d6ec3c9181561a9f52ae2b337c0', '2026-03-28 12:18:22', '2026-02-26 11:18:22'),
(111, 1, 'ff1a805c7b6b5b73dfd2225c08e6e0bc062c5c274e87e844a744c687231e0f82', '2026-03-28 13:46:19', '2026-02-26 12:46:19'),
(112, 1, 'bfd0120abab43cb208d5202fb7d15717da7d019f9cf73e174f6f05216a2832d2', '2026-03-29 10:25:37', '2026-02-27 08:25:37'),
(113, 1, '4a93e4af69c530e3c43a759eececb87c69ce590bce70dfe55f19121b46740f10', '2026-03-29 14:12:24', '2026-02-27 12:12:24'),
(114, 1, '389db3a617e456b808a67aca9fc85164167a5c5f749dc666b0eddff64bb39d56', '2026-03-29 15:18:15', '2026-02-27 13:18:15'),
(115, 1, '028fb155f4ff51b5577a4f18d044a11da9a87b930f2c20279efabe6311128bad', '2026-03-29 15:19:08', '2026-02-27 13:19:08'),
(116, 1, '1d3c25a3e4fed01000c19948f0a4fe83bfebf52fcb83595a2b5c0e10984c1744', '2026-03-29 15:57:28', '2026-02-27 13:57:28'),
(117, 1, 'c8fd58a9f796584eeda6775d4acf661d5b0d370733927dbf5a86a640487dc3a4', '2026-03-29 18:33:00', '2026-02-27 16:33:00'),
(118, 1, '5f0450b6f6ac65cca79c8c07d6adf2193d0bc3b87032474e6c9e3d59bbf6f65d', '2026-03-29 19:18:31', '2026-02-27 17:18:31'),
(119, 1, '78645d40a3b7e2eba2fc7dd1f73f1dd4f4d8913092fce0f12bf11daa16eadc55', '2026-03-29 20:54:45', '2026-02-27 18:54:45'),
(120, 13, '7f660109ef188afd4b073ed591ba19b17cbbc5d6bcb44baf285217ca157f5655', '2026-03-29 21:00:27', '2026-02-27 19:00:27'),
(121, 13, '9d84a61f3f9fe2051212bb397359511fc13fde2991f4df5e23ebd711673f50bf', '2026-03-29 21:01:50', '2026-02-27 19:01:50'),
(122, 1, '4e0e950926b3aa4df1da2b0d25fb862e3fd5c0525cb746fc2b74b68c69141295', '2026-03-29 21:25:29', '2026-02-27 19:25:29'),
(123, 13, '27c6ec02e0483a21d3dc72499a12ee54dcf2db63ad23108c5c9f822a9d42658f', '2026-03-30 00:23:31', '2026-02-27 22:23:31'),
(124, 13, '9f4bcddf65d596062f0f0f65fb66f6e5cd05cff549bc506ba978487e824f495d', '2026-03-30 11:07:54', '2026-02-28 09:07:54'),
(125, 13, 'de3ec65038f63c28d2f61556bd17161248b420bf03b0c763d56febe4b74507bc', '2026-03-30 12:28:25', '2026-02-28 10:28:25'),
(126, 13, '4e48b1fcd09615b2655cc294850b2e5713b8ce85bcd1b7851c85f6bd78b8ee9b', '2026-03-30 12:32:27', '2026-02-28 10:32:27'),
(127, 1, 'ffcd1d59f1e1284a49fa8d200a17a89c28ec9112e8d342156819f26a89fe8934', '2026-03-31 01:45:09', '2026-02-28 23:45:09'),
(128, 13, '7856305e314a6bed5b9f71e916a5ad138f59a8a81a3daf9efbd6f4b030f1bcbd', '2026-03-31 18:35:46', '2026-03-01 16:35:46'),
(129, 1, '2d3fe49f6bd534396e29ae58bb724e63904b0229f3fa05422da888d73cd68543', '2026-04-01 09:46:41', '2026-03-02 07:46:41'),
(130, 1, '83afb2cf30dc806c9a99fb7f8579dc26807ccc2f4e3a6a143eb08ce3463a58e0', '2026-04-01 10:50:34', '2026-03-02 08:50:34'),
(131, 1, 'b666e18a4c3fb738306f44f8572e4800b400b0003f2df99b21ad0b5a7102f48b', '2026-04-01 11:17:20', '2026-03-02 09:17:20'),
(132, 1, 'e9e8802ee1f7bd503f299c709c8c236ac59667505a3c5bedf6652780b8bb74cb', '2026-04-01 11:30:32', '2026-03-02 09:30:32'),
(133, 13, '4cbf172709993ad69367b1580e96e929b1108a9fefbb1a8dc7813390f920eb75', '2026-04-01 11:34:19', '2026-03-02 09:34:19'),
(134, 1, '8417097a6abdeeb0effee31a56758171e20188d6e5449921fb8fa204c67d43dc', '2026-04-01 13:12:06', '2026-03-02 11:12:06'),
(135, 13, '4b2c6de9eeb9444cc674bf6a11200061261927ffc4f118f227c5257624f1b22f', '2026-04-01 15:41:04', '2026-03-02 13:41:04'),
(136, 1, '435e962fa826f59d029b613b604596e01cb1bb9f281024c9a853713c2ea07dd8', '2026-04-01 16:51:05', '2026-03-02 14:51:05'),
(137, 1, '859ed999f7d8f098ff05919ca2eb93461754ab2b14c79efa13b0b0fe8d7c2598', '2026-04-02 00:35:41', '2026-03-02 22:35:41'),
(138, 13, '2300dcc65c4df4fadbe8a142d5837d00d140b892fa828b545f8d176b561a0a4b', '2026-04-02 07:22:29', '2026-03-03 05:22:29'),
(139, 1, '101cc06f8bca5e52eaaeb3e10ee44c7091c5ed68de0e52551d67e3916624c952', '2026-04-02 08:06:29', '2026-03-03 06:06:29'),
(140, 1, '6f285d236e36c099c3430474c829feb3b1ba70dd8632bcfe20e26d1bc6f8000f', '2026-04-02 12:39:15', '2026-03-03 10:39:15'),
(141, 1, '544a37dc4a199701a9ec5862ecd891bafdbdf5a402c84f9ddbdc4c240d459078', '2026-04-02 13:28:47', '2026-03-03 11:28:47'),
(142, 13, '86d9b53bd93c0af57f70d4844eeb0537ee45732ce1142f8ce963c152142fbbb1', '2026-04-02 19:28:49', '2026-03-03 17:28:49'),
(143, 13, 'e9e2876a5c876241a93f48583e13d00fe884112d4673d030704a46290d0e2054', '2026-04-02 23:33:59', '2026-03-03 21:33:59'),
(144, 1, '7614bd7e73e67edeb83c7bd7681397f311fab55e25be2a003441ca42c04aaf45', '2026-04-02 23:47:07', '2026-03-03 21:47:07'),
(145, 1, '8263cfde682bdf1edf3ba9e13b0d02352f2602c389f72d7eeb5074a5989c9c3f', '2026-04-02 23:52:02', '2026-03-03 21:52:02'),
(146, 1, 'e15806c5484a4142e9fc5a7aeec7058c85aa70e2694e42e98320ba48505a408a', '2026-04-03 07:21:07', '2026-03-04 05:21:07'),
(147, 1, '60db8745489e7f24516e12d609a87a5313e96374ce73ed690fc437f1a0d3caf7', '2026-04-03 13:45:39', '2026-03-04 11:45:39'),
(148, 12, '32c6a8d5f5c3f00d429909eddda9afd705f1c12a5bdff77096545349af46bec0', '2026-04-03 18:05:31', '2026-03-04 16:05:31'),
(149, 1, 'e195d19265add49f715cdf148b9c612bb1ea690418b4feec89d614dee6b9b52f', '2026-04-03 18:34:44', '2026-03-04 16:34:44'),
(150, 13, '87f3b22ea54933b1f3521a343c114a11be53179bd73a6e442f52b1b21d92f2b5', '2026-04-03 19:48:25', '2026-03-04 17:48:25'),
(151, 1, 'c0e57c78c78c68bef026d26c4b7632abf08835008c36417c83634646eed5590d', '2026-04-04 10:27:02', '2026-03-05 08:27:02'),
(152, 13, '8fe05b15e0e35df663bec2d0992735e562e2281728c81e7bf65a131de366f0bf', '2026-04-04 20:00:34', '2026-03-05 18:00:34'),
(153, 13, 'bfa39cf57aad29fa6ad918440e8b8df7f263a231e24bdded14a3b7f7c405caa3', '2026-04-04 21:33:33', '2026-03-05 19:33:33'),
(154, 1, '46dbf3f9d5499ca007cf8c4509b5af7f7ae77413ef40a024d21c9a45bdd1e278', '2026-04-05 10:45:58', '2026-03-06 08:45:58'),
(155, 1, '92c6324c760e537313235e7a12ff24ce4896f15082f95ae29f8e862a7b3680ec', '2026-04-05 10:46:18', '2026-03-06 08:46:18'),
(156, 1, '27d73c7490e80e179d8d5be1dd3149ec9b63a1232e676a69b8cace87b9c6b926', '2026-04-05 10:52:48', '2026-03-06 08:52:48'),
(157, 1, 'eb2bb3a691403f6770d25c98a426cdeb7e936953f73047a4ab80d2172a5288ce', '2026-04-05 13:16:58', '2026-03-06 11:16:58'),
(158, 1, '67484707e61967c0666deace151f1775e537c84d887fb09c439fb5b6fe03d173', '2026-04-05 16:56:56', '2026-03-06 14:56:56'),
(159, 1, 'ee6102f4713a7bfa804fe24c3075c364ddd15c7e9132a9455a46ac6c941937f8', '2026-04-05 19:02:51', '2026-03-06 17:02:51'),
(160, 1, '584e55856f85a454dd57ee1956d820ee23f76706bf14aeda8f3e382f0e357481', '2026-04-05 20:47:33', '2026-03-06 18:47:33'),
(161, 12, 'f4f5d61a5f3741be3d0762dcd42f863c3eb1b0d40825def11e0e5ec5937c47c2', '2026-04-05 21:32:31', '2026-03-06 19:32:31'),
(162, 13, '23fade089362966c677bcae65de8a1e5f1b2c3adbfe3fcd09ca8e5c213379a50', '2026-04-05 21:38:16', '2026-03-06 19:38:16'),
(163, 1, 'd0ba7a85de399cf6b49f974a582ac4e78ea16034f0dcb92f76d67e8617410c6c', '2026-04-05 22:28:24', '2026-03-06 20:28:24'),
(164, 14, 'e40bc14c4ffa034bc9c6fd8eb6cb20163b28bad4d252cf47c937a1e12c986155', '2026-04-05 22:39:06', '2026-03-06 20:39:06'),
(165, 15, '38518f400162eff1556af7b7b45853ceae16caee59bbe9fae4d6aa3a6000756b', '2026-04-05 23:08:00', '2026-03-06 21:08:00'),
(166, 4, '0f94085e4aaa224aac692cd3d81032d6456d80b2d29f4a2f60df2c984cecb933', '2026-04-05 23:09:09', '2026-03-06 21:09:09'),
(167, 16, 'ab25ab2e0c2d06b37cd405e366cd52b855c40a5dc004b4012ee013d6378d8c74', '2026-04-05 23:10:08', '2026-03-06 21:10:08'),
(168, 17, '80b3e58d33fa4c69dc6e62a242b59ceb67711dda1a9604f125c5da23252894fa', '2026-04-05 23:11:15', '2026-03-06 21:11:15'),
(169, 18, '6f7617e6421974fcac6e24cfc959564513a94c02472b0b2c4a052e49b7fef2fa', '2026-04-05 23:12:07', '2026-03-06 21:12:07'),
(170, 19, '5c301835d36603cc79bcce26c7da786a8883f391e7d0f6b90b45036d72771af1', '2026-04-05 23:14:23', '2026-03-06 21:14:23'),
(171, 3, '2ca9fe4faf0f8be5a50951c770e691786430769e6d314805e1aa8f24344c8df7', '2026-04-05 23:20:08', '2026-03-06 21:20:08'),
(172, 20, 'e14448ee2fa59f8041df55f6e0a5b8833c819bd6fcb03ca6276074abbf379236', '2026-04-05 23:22:49', '2026-03-06 21:22:49'),
(173, 20, '107b26394f6d25ac67041a3b43f6385e933d9546affcf600a6695d27b44d44d0', '2026-04-05 23:26:11', '2026-03-06 21:26:11'),
(174, 21, 'ff4bdda970f537b79fadfda8eea2cc897be99b0a7ca583bbba95199eba7ca91f', '2026-04-05 23:28:28', '2026-03-06 21:28:28'),
(175, 20, 'c69b9f42865b9ac14d7fe5550c6fbfeae577058c175303c6f63217ff2e852953', '2026-04-05 23:32:01', '2026-03-06 21:32:01'),
(176, 20, '6affa439307da2b9560a244052853f8bc0c820cd7147528b6f49fe220dd9fd4d', '2026-04-05 23:32:57', '2026-03-06 21:32:57'),
(177, 22, '671690edbfc62bdbc1af5127ae3a3a07badc9285f566a7a6653ba85ca15ab9ca', '2026-04-05 23:33:04', '2026-03-06 21:33:04'),
(178, 20, 'b0d3c815ba4eb3eb2e0d456e0357f563790c39dfd508ba7096502b49dbff84a8', '2026-04-05 23:33:24', '2026-03-06 21:33:24'),
(179, 20, 'f8e5b3425c7fc5f18dad62b1900452dd9ce9060438296d263ca15e017131eec3', '2026-04-05 23:34:29', '2026-03-06 21:34:29'),
(180, 23, 'edc2d088924ed40c7d445d340fd2692dd54371b8f0f413910068d2ff5347463a', '2026-04-05 23:35:28', '2026-03-06 21:35:28'),
(181, 22, '9d2292d0d82abf7f6abe2c7165bc9362c003c93d806d5275553f353542174ee9', '2026-04-05 23:35:40', '2026-03-06 21:35:40'),
(182, 20, 'a0ae1c4056b9de70e8120fb748b43971e04ed32072456c087471879ce12ebe4c', '2026-04-05 23:38:51', '2026-03-06 21:38:51'),
(183, 20, 'b52dd297c6110a3ca83638e97daf7d1c764d97e50d7530bcc0637153a0b7b88f', '2026-04-05 23:40:19', '2026-03-06 21:40:19'),
(184, 20, 'de5481ad3e2592d562eb54ac4beae6fa4b96fea18d9a4f67fe7079d28bb81daa', '2026-04-05 23:43:32', '2026-03-06 21:43:32'),
(185, 22, '288536ae9a46068b5b6e1a79f621e239033b16f2efa31e01c16d55d3f03ba44c', '2026-04-06 00:17:46', '2026-03-06 22:17:46'),
(186, 22, 'bee656c6e17ca66de52632e9a5c6a947071a60605f915d7d84797cf096be7b5b', '2026-04-06 00:27:00', '2026-03-06 22:27:00'),
(187, 14, '7fb4359fbffa306a6a3afbe67004c26bd6577d9dfd66922148902853e69fe876', '2026-04-06 00:48:13', '2026-03-06 22:48:13'),
(188, 1, '496f4f0f13fc02c4f9305e51cceba342611cf8033ebef8e0b6f1f20df56a45ce', '2026-04-06 00:54:52', '2026-03-06 22:54:52'),
(189, 23, '21ad70337367a1903aede85401000eeca1627573296d063ee9e1d9ec4f79da38', '2026-04-06 01:46:56', '2026-03-06 23:46:56'),
(190, 14, '52cc8e05f3c6bb4f93056528506965eaf3ac2548644eec3158b1c377a833e70f', '2026-04-06 01:48:01', '2026-03-06 23:48:01'),
(191, 14, '16f7dddc05e1e7ca5a460208768f610dc93254ae34a5bef32780aa92f03f9408', '2026-04-06 04:10:46', '2026-03-07 02:10:46'),
(192, 14, 'a0416ccc3582fb32a1f720fc3ff1c2271e5c88617c0b979db245c2e6d920b8e1', '2026-04-06 04:52:29', '2026-03-07 02:52:29'),
(193, 12, '23f39b3515f2cffbad61c82798585bd9cadd7e75d9a57433beff1472c6918a1d', '2026-04-06 09:01:42', '2026-03-07 07:01:42'),
(194, 1, 'ae2b5977e2c40fa406bd377a33e3b8a6029f363551432122838c0107662b9a11', '2026-04-06 09:27:00', '2026-03-07 07:27:00'),
(196, 1, '5b87e79d4107459df151a1d79cafe006fd952125b68ecba01563f7bf1d76f1a4', '2026-04-06 09:42:35', '2026-03-07 07:42:35'),
(197, 23, 'cb336cd2e3b4b47469cf053b4adcf59ade5e2955508b85db631d88622afa149b', '2026-04-06 09:42:56', '2026-03-07 07:42:56'),
(198, 1, '2c2937097c686ec59bed54feae746b4d83f0dba792f64bd5ea405adad8db50fc', '2026-04-06 09:47:08', '2026-03-07 07:47:08'),
(200, 11, '528b17d00d42ba2d9a7943d6a9da7c32711960ef6bba188259a495bfd5acfd27', '2026-04-06 09:47:36', '2026-03-07 07:47:36'),
(201, 14, 'db37d44dd96f27172937d2ef10d9636eb8ee832de33e85b124d845086de82448', '2026-04-06 09:49:13', '2026-03-07 07:49:13'),
(202, 19, '7645f59ec1ea311cbc8d6b158bb6d793b46ea4ec366a14b9371d7d9432fc8e53', '2026-04-06 10:02:55', '2026-03-07 08:02:55'),
(203, 23, 'd6f4d54aa0f456752764d8faa3b3d2cd5ef51b3dd7856afc1777cb324c3c98de', '2026-04-06 10:24:55', '2026-03-07 08:24:55'),
(204, 1, 'c512f1a097e797f640db49127a7b4296d89ed27792f9fce95a8ac90e078aaf81', '2026-04-06 10:28:35', '2026-03-07 08:28:35'),
(205, 16, 'bcd895cb14bf33fbbeabc4ab53aef181c25c99144a67c6786600f97bfe3990ab', '2026-04-06 10:46:21', '2026-03-07 08:46:21'),
(206, 1, 'e503d2c8c684e3332fef02e30c99027461fea89c855e690563f0b824d924d9c6', '2026-04-06 11:24:40', '2026-03-07 09:24:40'),
(208, 1, '8851f4001383b9b380334b34567fa2a5d95feb3eba8de0bc66617fbfd50c3db9', '2026-04-06 12:22:22', '2026-03-07 10:22:22'),
(209, 1, '01991b30fc4ea8e56f453601196a5917f813cfcf7ad71671a46c087efbf30559', '2026-04-06 12:23:11', '2026-03-07 10:23:11'),
(210, 1, '51c029fd445b28e57eacdfbebbe02d7e816387871f0320b7ad85f40f6d65e96d', '2026-04-06 12:24:16', '2026-03-07 10:24:16'),
(211, 23, '68735a05e76d4887819dff21b535f44480efefc75992d6e3449112a4e07939ca', '2026-04-06 12:33:42', '2026-03-07 10:33:42'),
(212, 25, '3b12a14ec088daceb7315b04ce7d8f207c3bfee2508d61d978e889d4de178b58', '2026-04-06 12:50:57', '2026-03-07 10:50:57'),
(213, 22, '251adfc122ce371121a9496e55d3e2b00ff3180b29ea44365aaf4234a6b36d29', '2026-04-06 13:04:52', '2026-03-07 11:04:52'),
(214, 3, '8a85dbb61866781fc7d085dfec52dc5576e27a573bf70346f4a011295fbc1c99', '2026-04-06 16:36:55', '2026-03-07 14:36:55'),
(215, 22, '63f67dcf1bbed8ce1bef68ca3cc7decd68847bde9276e5aefd0cc0470276e7ee', '2026-04-06 17:09:30', '2026-03-07 15:09:30'),
(216, 13, '1f7f373d376c1829618d359c5efe1d45ed83e03e12c25991d3c9e0536df09e37', '2026-04-06 17:20:00', '2026-03-07 15:20:00'),
(217, 3, '036f9bcdcaa0e4de5b581f8c481585cba4cd9c398fed33c3df310e730bb4fb02', '2026-04-06 19:02:33', '2026-03-07 17:02:33'),
(218, 3, 'cd1e915393e9a922c711eb2169d08a7ac815f3ef92c24caaa889e73554468b21', '2026-04-06 19:03:05', '2026-03-07 17:03:05'),
(219, 23, '1abc092edf9a8f42cf547977ec960b47fde73bdb404c87047ddd911211830d3b', '2026-04-06 20:27:25', '2026-03-07 18:27:25'),
(220, 21, '3982ce7a515298179ba495b90c2bfa580ff9c8bea83ec58d47d3cc7ee835403a', '2026-04-06 23:20:43', '2026-03-07 21:20:43'),
(221, 22, '21380f395f190694101f4f9afb94f1d47f9787449fd290b5ecefdad74af3eab0', '2026-04-07 00:10:28', '2026-03-07 22:10:28'),
(222, 14, '42b57216c3ee78ef85698eba791d7f4c4ab12fc9eb623f69410fd27bbb2c66f5', '2026-04-07 02:15:05', '2026-03-08 00:15:05'),
(223, 15, '026e2354c51c75f8867b26ccce5d57a0c6f07473e9dc5d0c8d18b883895186a5', '2026-04-07 09:35:57', '2026-03-08 07:35:57'),
(224, 23, '235b92ec7d58fc609cef6c6c38e2cdcc2fd3e1d0d63fa9dc0060779608179846', '2026-04-07 12:32:08', '2026-03-08 10:32:08'),
(225, 3, '9800215c51b1648a3f2065a686c1dc91f691bf05ae031bc2ad8c4f162ca05942', '2026-04-07 13:29:28', '2026-03-08 11:29:28'),
(226, 25, '8c802df94bf48c921415676138dc6f7c668b5484d0eba4664dabcd272ae5c2c0', '2026-04-07 16:57:07', '2026-03-08 14:57:07'),
(227, 23, '564a3695b40c1387bb7a6e34138da027cebabc2e45a5dc4a22642e6e7c5ec232', '2026-04-07 19:19:45', '2026-03-08 17:19:45'),
(228, 15, '7a2a4e79ffadcf54fdcfe2f34650151bb803dfae8693fa008a78aa7dc0aeba33', '2026-04-07 19:33:50', '2026-03-08 17:33:50'),
(229, 13, '4bdcd7357c35cff5774ade52243ab7b0230a0bf9fa7a93455fa4ff9535fd7451', '2026-04-07 20:41:03', '2026-03-08 18:41:03'),
(230, 18, 'e4995a6cadf060bf394193d52c9940d9eb6f65bd0a0002fd9ddbf7d809927fe2', '2026-04-08 06:56:36', '2026-03-09 04:56:36'),
(231, 22, 'fcefb8cb093d92417ce78afc7b5337e1ad874f26e5760f7f79bafe73eb888e5d', '2026-04-08 12:55:26', '2026-03-09 10:55:26'),
(232, 18, 'c993df776dd7c90fc4517581662dc35b7be3f35910541dce83b5fb45634a97a5', '2026-04-08 13:24:59', '2026-03-09 11:24:59'),
(233, 16, '1dda9e054ffa3626b6ecffa706fc1f2da02d99c2bfcbd51fea9233e5af44b1e2', '2026-04-08 15:32:15', '2026-03-09 13:32:15'),
(234, 13, 'a1d0eb62eb0949047b863d09564a9f3800e98ad73ead1f8a3249138a685e173b', '2026-04-08 15:44:29', '2026-03-09 13:44:29'),
(235, 3, '4ed38642b75412a7f1b302f48f599f7e8dbd5d5775c51eca356a5325b00d6a8c', '2026-04-08 18:11:52', '2026-03-09 16:11:52'),
(236, 13, '93457fb54411d59cef713b368c9188e15df78217ac97191fc34fe974c5100169', '2026-04-08 20:10:31', '2026-03-09 18:10:31'),
(237, 14, '9c3da721222cc9958b39304d4793eab469ab8c88524f9f13b6c565ec08eba006', '2026-04-09 03:28:06', '2026-03-10 01:28:06'),
(238, 13, 'a668ef5f665757e5bdf55e174f10a2066aea58cfa796318b94dce2e399cd3bc1', '2026-04-09 08:53:22', '2026-03-10 06:53:22'),
(239, 16, '31c6d2dde06c5cdae34f80c38faed4169abbb0556fb27dd16d0a5d6e36858de6', '2026-04-09 12:09:03', '2026-03-10 10:09:03'),
(240, 16, '967758bfda7121f1eb3db59f87c940d70a0e1b743bbddf9d37f3b8c66255514d', '2026-04-09 14:34:48', '2026-03-10 12:34:48'),
(241, 13, '38d3694c1fad69849415e0702f15116cc27741cc08678fb7d9aab3631efe5318', '2026-04-09 16:10:04', '2026-03-10 14:10:04'),
(242, 1, 'a44802998e4dce41a6ccc538e7052121418c44aad9374ef17c088e9ea0dbe7f4', '2026-04-09 16:32:25', '2026-03-10 14:32:25'),
(243, 25, '09d346e3fdc0a073a4081c9c915dffd25be2a14a3bd532c7d6509a2f94726729', '2026-04-09 19:07:30', '2026-03-10 17:07:30'),
(244, 23, '7e828bc073fe2492d8f8a5255b71367d0969bed45d52119bdbe23382a943cf77', '2026-04-09 19:46:43', '2026-03-10 17:46:43'),
(245, 23, '355e10f047e88a5904c01dbbcac012b5458af6a95c1974bbfdcde7374b64ef8c', '2026-04-09 20:55:03', '2026-03-10 18:55:03'),
(246, 3, '9cc155c8ffe851712ea7bb89228459c9dec365a8134598e30bc2fab32463186b', '2026-04-10 08:17:03', '2026-03-11 06:17:03'),
(247, 13, '573cf32fc212850c925538edfbbd25d22dc973c5943e3086f471727bb66cd0ac', '2026-04-10 09:27:48', '2026-03-11 07:27:48'),
(248, 21, '0c0b902907352872bcbe01a138513e19cda72db14ea6b234aad476427e4bb162', '2026-04-10 17:10:27', '2026-03-11 15:10:27'),
(249, 15, '1ece3cab232caaae26565c2e6166a1253429172f36a91f86c4808a64b94bd289', '2026-04-10 17:33:51', '2026-03-11 15:33:51'),
(250, 15, '346772d50cf0036076bcbb8f9c351452ce7a67e29004914313f317618d5230a2', '2026-04-10 18:09:49', '2026-03-11 16:09:49'),
(251, 18, '530c6be5bb8fdc7071614cff0e7ecee447b2352bba9adff5a95ae31071e0d16b', '2026-04-10 20:19:58', '2026-03-11 18:19:58'),
(254, 22, '4d3b1e0125db53d36e022ff2902a67b9d4bc6e5edfad1c6a95836905b09462a5', '2026-04-11 13:07:38', '2026-03-12 11:07:38'),
(255, 1, 'c761a7cece9daef4d19139274cd13cf8808f461376eb5da2d208f28b1c3626b9', '2026-04-11 14:18:18', '2026-03-12 12:18:18'),
(256, 22, '6ae7c4791940db8085b199c864755c4dbb24293ab4581ea8d1ae78e2de2d67a4', '2026-04-11 19:20:48', '2026-03-12 17:20:48'),
(257, 22, '8a2bc97101c84022f5ef4e06ac610130c71c25144489175b6527b7d9b8e1bd8b', '2026-04-11 19:33:12', '2026-03-12 17:33:12'),
(258, 18, '8c15e3418401b34d38d53dc9ac3436fd47060c08c6789685344d8626b960fff7', '2026-04-11 22:08:32', '2026-03-12 20:08:32'),
(259, 15, 'e2573fd703fed04b6dbdfa04235fcfe1d6a458eebbc79c47e8c266f4babe6d9a', '2026-04-11 22:10:52', '2026-03-12 20:10:52'),
(260, 22, '1dc84d15b1b953a4af3cf93401b0f4a4022a07d822a22a03eec7ae799cd66edd', '2026-04-11 22:40:56', '2026-03-12 20:40:56'),
(261, 16, '8fe008d656678dbf5225837b43e9c7ddc18ff8d444b3b2d5768969709e34254f', '2026-04-12 09:51:17', '2026-03-13 07:51:17'),
(262, 3, '06d820ebc12772c010f939cff25fd0e8d0a7b07f7c6f843e1603fd29f2bed2c6', '2026-04-12 11:51:06', '2026-03-13 09:51:06'),
(263, 15, '16df74f9fb94e36aab17396d3ffc4ac06fe96e92bdbbdde5be814291b0b88e62', '2026-04-12 12:07:14', '2026-03-13 10:07:14'),
(264, 3, '28fc9414abd7c6cbea73784aa3159da41f15f069625c1e723eea5b11d128529f', '2026-04-12 13:43:11', '2026-03-13 11:43:11'),
(265, 3, 'a7806f9aefd6f3dc02d4146bd30b930c331850e6da2beb15d0cf0644aac9060f', '2026-04-12 13:44:02', '2026-03-13 11:44:02'),
(266, 18, '631033c501bc8b5efffdc47cc3fb2d5c57cfd3ce7983ca7cef3f2b2aac46936f', '2026-04-12 14:14:24', '2026-03-13 12:14:24'),
(267, 3, '2fe5e5afb1ec3e1ad2c493109904e3d04744214357c96d9dbd12bd9fd1b60593', '2026-04-12 14:19:42', '2026-03-13 12:19:42'),
(268, 3, '414560d10ed9d53b898cbf4e3ffff80bd525e1f77395328c53e4711f91ee549f', '2026-04-12 14:22:47', '2026-03-13 12:22:47'),
(269, 3, '0df6221deaa4939101d26aedc9fbb44fcaf10937e7b9a3cb4bf5e2857c64d852', '2026-04-12 14:25:46', '2026-03-13 12:25:46'),
(270, 22, '3348dd800917f48ec711ad08b4e603046d25662d035f51272d1561d7111b24c1', '2026-04-12 15:10:48', '2026-03-13 13:10:48'),
(271, 3, '2043e2e8f9830252444ac73bf7a6d0226b9d4836ac1aacc7c002d2701097cee4', '2026-04-12 17:25:30', '2026-03-13 15:25:30'),
(272, 16, 'c8f34febe8e2f97f14d0b7c6d26b21585c0413961774b4036d8ecb88d892c1a0', '2026-04-12 20:24:03', '2026-03-13 18:24:03'),
(273, 3, '32af8173b34a472a56ee12eab99d660c41d48c9a54929e0937e80336ee7f9f6b', '2026-04-12 20:32:40', '2026-03-13 18:32:40'),
(274, 22, '19254860ced6ae1c617cd923b547244b9c94e3dbbc6ea309f7cd780ac857d272', '2026-04-12 21:09:08', '2026-03-13 19:09:08'),
(275, 25, 'd80e6b35d49bfaaf205db1fc7fd91b5cfa4f63a39ce31ac7391c1baf62b6856a', '2026-04-12 22:44:49', '2026-03-13 20:44:49'),
(276, 1, '06f8c7c57c64297417b6b488bdf7960e8abf512606d705ac86a5c0ed9778edc9', '2026-04-12 22:56:38', '2026-03-13 20:56:38'),
(277, 1, 'e8a9a48e49c2e6389c17508d4126aeb80217ed23f2e6bba4f327bd3db59c8052', '2026-04-13 00:04:23', '2026-03-13 22:04:23'),
(278, 15, '88d422885d26e806f7373ce559285c0b8d3559cd4d60e8268f428aef86c1805a', '2026-04-13 11:44:05', '2026-03-14 09:44:05'),
(279, 13, '96ed7df114c31b3e729d0a06c33b77238cead2216f64547c28b8a924b27d26f6', '2026-04-13 13:07:28', '2026-03-14 11:07:28'),
(280, 25, 'b94a2415c929276e8d24bdcb3ded7082583ac0fcde44f8de6a952552e0abb8ea', '2026-04-13 14:49:30', '2026-03-14 12:49:30'),
(281, 18, '5b828254f8545c05bd2177b3147ef52356732026145e84e2861f707dd5ddd1ff', '2026-04-14 12:14:06', '2026-03-15 10:14:06'),
(282, 25, '751ec8e69b4b22354fb55338a5e96f88dfa63fbf0bb80e312c92c7a2640bc698', '2026-04-14 14:47:55', '2026-03-15 12:47:55'),
(284, 15, 'b1451b1deeaa6aa6dfd34843a92f3cd735e58c85455b08752cddf856905dca56', '2026-04-15 08:03:32', '2026-03-16 06:03:32'),
(285, 22, '21a50eed9b06ac80c12f5d04dc5951d0b59a691ff8b2e646fc87af94f6c8c74e', '2026-04-15 11:34:08', '2026-03-16 09:34:08'),
(287, 16, 'ed96325a67fd76f69116bc4fc235f2662aaa1e20c8ef838ac0fa28c5d36569bd', '2026-04-15 20:55:38', '2026-03-16 18:55:38'),
(288, 22, '2b7da4b118a44217b51e0b8530eeacede8241f3f1f90bfe80d3dd38f2db5f057', '2026-04-15 21:02:08', '2026-03-16 19:02:08'),
(289, 22, '16eb7522b0eb4ec659dac903b11b9cc5f3a68842555cbe8e799dd0c3cdc287c9', '2026-04-15 22:32:05', '2026-03-16 20:32:05'),
(290, 25, '8d0d801b1767ddf4c51ff88d1855cd97ddff789f4ec586adef6bab5b4596cb83', '2026-04-16 09:28:17', '2026-03-17 07:28:17'),
(292, 3, '34fe62083d56182f67835032e28d11cf882c54e0dc523503987ac6822e7ac1f4', '2026-04-16 12:40:21', '2026-03-17 10:40:21'),
(293, 22, '41bc0db0d2d764678b59ce011abcee68edc792c95c5783dd63121a2fdd587ef5', '2026-04-16 17:30:13', '2026-03-17 15:30:13'),
(294, 3, '9579c3ce761a8b9840a3dadc2af19671384cb11b3594a7e04689746de775cf32', '2026-04-16 19:31:01', '2026-03-17 17:31:01'),
(295, 22, 'fa7b8c2ead3b8cea43e7b870245229cc7bd4a69adc2eb9df5f4d1e7298ea5d67', '2026-04-16 21:24:34', '2026-03-17 19:24:34'),
(296, 1, '989b746ee2768bd5eb5c8f2b653b18b68b17cd9897530a8d356188be3b67f928', '2026-04-16 22:21:41', '2026-03-17 20:21:41'),
(297, 15, '7dd691a11d94b22c050b9e69ba8e1923966cafd9ae5ee23638a96e53de5532f8', '2026-04-17 10:11:14', '2026-03-18 08:11:14'),
(298, 3, '2a65609418b431ccc3bb7803f9a8e0f328570c524e261f75abd4d7838e45e2e4', '2026-04-18 09:53:45', '2026-03-19 07:53:45'),
(299, 23, '7423e6d3d0dec62e674b29fc59a43cf99753d82cd21384ac34795e99f1c69bb5', '2026-04-18 10:51:50', '2026-03-19 08:51:50'),
(302, 12, 'a19afe6469b7ebd79a563acb291d8bf94dd004323684aa170fcb00ba8ee73090', '2026-04-18 18:06:11', '2026-03-19 16:06:11'),
(303, 1, '86495ad86bb20e54aa51077cbea3950ecff6c6166ee3cf7732ddff63b01f94c2', '2026-04-18 21:06:39', '2026-03-19 19:06:39'),
(304, 3, '28aadd3074ea919caf5636c4e25dbbbef13dec19965db409f4684afce939810b', '2026-04-19 20:23:51', '2026-03-20 18:23:51'),
(305, 22, 'de5944aba00c55f9a97f4ac62df8f5eda25320598046ca902beb5859fb6b52dc', '2026-04-20 00:48:41', '2026-03-20 22:48:41'),
(306, 19, '07cd2be17ebbe83407001a1a91c8db0f8a98b16923a2142afeee12f664b76f83', '2026-04-20 08:23:49', '2026-03-21 06:23:49'),
(307, 3, '26e52647a47da613a88f4ffe24e66e21ab15d5a19a34d1cd1b55664c8f476fbe', '2026-04-20 08:44:20', '2026-03-21 06:44:20'),
(308, 1, 'cc740f8795cfbf7ef14396cdf3af47f94e78349d91e2eb952083636659bddd68', '2026-04-20 08:47:59', '2026-03-21 06:47:59'),
(310, 3, '925cae51074d8371b2549566de8972d778ce2541d205285561f8d0e12f3bd0ee', '2026-04-21 10:16:53', '2026-03-22 08:16:53'),
(311, 18, '40c447fcd7699388306281e31de669797a02532cb52d7e497411d77a612642e3', '2026-04-21 13:39:46', '2026-03-22 11:39:46'),
(312, 22, '88b1cdd744f17de4d8b06577651c819d059bde50ffa14bade42465dc3ade0b80', '2026-04-22 09:36:56', '2026-03-23 07:36:56'),
(313, 1, 'fdf03be6061cd69238072455ab9f1c285fc51cdba696435fbc99328d06a6b66c', '2026-04-22 11:02:19', '2026-03-23 09:02:19'),
(314, 1, 'ac2fd9110c69999917dc8496d8e85822e724f2fb2a2e191cff6e9725f559a01f', '2026-04-22 13:57:36', '2026-03-23 11:57:36'),
(315, 13, '03542d082fd61a7f8b11bf9f89221a5e5de2b9c3cf51d8a49cbd4870cde04292', '2026-04-22 15:02:01', '2026-03-23 13:02:01'),
(317, 22, 'a5ad5e8961a4f2c0721893a21d2aa4aeea2d69f86c8df7da7a077e61a7f79877', '2026-04-22 17:50:33', '2026-03-23 15:50:33'),
(318, 3, '2bc318aaca638c9a7b40e09e8723157addf157092a68ac2956919f7feaebaa8a', '2026-04-22 22:57:33', '2026-03-23 20:57:33'),
(319, 1, '493e0cc185989ae751e8f15bddb2655977ac22e7b723301711b49668d366e842', '2026-04-22 23:56:35', '2026-03-23 21:56:35'),
(320, 1, 'b88b9d85ab2e05179bdb5c4e8ffec1c3fd588870e5a3e26b8c0c4f385f7f0848', '2026-04-23 08:45:56', '2026-03-24 06:45:56'),
(321, 22, '981ed42b05b4ca71ce0b96bf5ca6af3e996278d919ee4997593ae4163c62d474', '2026-04-23 15:36:17', '2026-03-24 13:36:17'),
(322, 22, 'e08279cda3628290fb137c8f2f3d23d533e7ac5ff7611ea55f256b2ff4a31f48', '2026-04-23 17:08:24', '2026-03-24 15:08:24'),
(323, 4, '032eac2622e15c60ecf98230b0e7cc9f89bd43ce34373e8388348831f77ab1eb', '2026-04-24 10:44:45', '2026-03-25 08:44:45'),
(324, 1, 'ba92bdb8d1098d88462705d27f270b911d65e2ac8f8b0f220e3e5c3226a76e2f', '2026-04-24 15:48:23', '2026-03-25 13:48:23'),
(325, 22, '952c7db9a7d0eeafed726bbef57c5a62041936b52847f644f91d05fd3952cc74', '2026-04-24 18:26:33', '2026-03-25 16:26:33'),
(326, 3, '666edf87060d2120f11216e269e905f88ff175bf45f57335801e677885c11644', '2026-04-24 18:50:13', '2026-03-25 16:50:13'),
(327, 3, '1e3cfa8ff0e3c700074e8c295e7dadb4e03d7cf219b52694ed644b8ca804fb84', '2026-04-24 20:23:34', '2026-03-25 18:23:34'),
(328, 22, '0cdb8e26e02ab52be684a7f16f457d877a21627163ecbdaeb88fd87c50495419', '2026-04-25 13:54:36', '2026-03-26 11:54:36'),
(329, 19, '46668599dd98b73390a4ae6c746bf86d67d05abecc6ca1ec35f30eefde631e54', '2026-04-25 17:46:44', '2026-03-26 15:46:44'),
(330, 22, '9ef611f1a5c891eafed51abb2d93c612b2addfa65424e0b69b367cccaf939227', '2026-04-25 18:43:21', '2026-03-26 16:43:21'),
(331, 13, '283b51cbeab6b87e0a446245ca445b375caaf25c148f0891e7e7b304a165e5c1', '2026-04-26 10:03:00', '2026-03-27 08:03:00'),
(333, 19, '492b9fe85710b17319fc71c8a4400a479ea23694314708afbf4f554598d4c438', '2026-04-26 10:45:58', '2026-03-27 08:45:58'),
(334, 1, '8540194266b636c5276ea0aaf674953264ca3369c1071d5fa8f3151ce4be6816', '2026-04-26 10:47:37', '2026-03-27 08:47:37'),
(335, 22, 'd21c375ef91c89b0acb7518d97bb6a52016024c34a880194640a0093f7a6fc46', '2026-04-26 12:00:17', '2026-03-27 10:00:17'),
(336, 1, '90cada975e5d3d308115bbbc192ee879edc3e364e555869cdfa25440f7ce08aa', '2026-04-26 14:03:49', '2026-03-27 12:03:49'),
(337, 1, '91bdf89a4871c3b0334105724651b26e8a4e1d86b3e1efc8f70df0c775b4d2c9', '2026-04-26 14:04:13', '2026-03-27 12:04:13');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sAltersKat`
--

CREATE TABLE `sAltersKat` (
  `ID` int(11) NOT NULL,
  `minalter` int(2) NOT NULL,
  `maxAlter` int(3) NOT NULL,
  `Bezeichnung` varchar(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `sAltersKat`
--

INSERT INTO `sAltersKat` (`ID`, `minalter`, `maxAlter`, `Bezeichnung`) VALUES
(1, 0, 16, 'U17'),
(2, 17, 20, 'U21'),
(3, 21, 59, 'E/S'),
(4, 60, 69, 'V'),
(5, 70, 110, 'SV');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `schwini`
--

CREATE TABLE `schwini` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `P1Schuss1` int(11) DEFAULT NULL,
  `P1Schuss2` int(11) DEFAULT NULL,
  `P1Schuss3` int(11) DEFAULT NULL,
  `P1Schuss4` int(11) DEFAULT NULL,
  `P1Schuss5` int(11) DEFAULT NULL,
  `P1Schuss6` int(11) DEFAULT NULL,
  `P2Schuss1` int(11) DEFAULT NULL,
  `P2Schuss2` int(11) DEFAULT NULL,
  `P2Schuss3` int(11) DEFAULT NULL,
  `P2Schuss4` int(11) DEFAULT NULL,
  `P2Schuss5` int(11) DEFAULT NULL,
  `P2Schuss6` int(11) DEFAULT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `schwini`
--

INSERT INTO `schwini` (`ID`, `MitgliedID`, `P1Schuss1`, `P1Schuss2`, `P1Schuss3`, `P1Schuss4`, `P1Schuss5`, `P1Schuss6`, `P2Schuss1`, `P2Schuss2`, `P2Schuss3`, `P2Schuss4`, `P2Schuss5`, `P2Schuss6`, `Jahr`) VALUES
(10, 112103, 0, 8, 8, 9, 10, 8, 0, 0, 0, 0, 0, 0, 2024),
(11, 112114, 8, 9, 9, 9, 9, 9, 10, 10, 0, 9, 9, 8, 2024),
(12, 112101, 7, 8, 7, 9, 8, 9, 10, 9, 9, 7, 9, 6, 2024),
(13, 112109, 0, 10, 9, 9, 9, 8, 9, 10, 9, 9, 8, 8, 2024),
(14, 385067, 10, 8, 9, 6, 9, 7, 10, 9, 8, 7, 10, 9, 2024),
(15, 112108, 9, 10, 10, 0, 0, 10, 8, 8, 10, 9, 8, 10, 2024),
(16, 831789, 5, 8, 0, 0, 0, 0, 0, 8, 5, 9, 8, 8, 2024),
(17, 112102, 5, 9, 9, 8, 9, 5, 9, 8, 9, 8, 8, 8, 2024),
(18, 112141, 9, 9, 8, 0, 9, 10, 0, 0, 0, 0, 0, 0, 2024),
(19, 112140, 8, 9, 10, 10, 10, 10, 10, 10, 9, 10, 10, 10, 2024),
(20, 112137, 8, 10, 9, 8, 9, 9, 8, 5, 8, 3, 8, 9, 2024),
(21, 889594, 10, 8, 8, 9, 9, 10, 9, 10, 8, 8, 8, 9, 2024),
(22, 112111, 3, 0, 10, 10, 0, 10, 8, 8, 10, 8, 10, 8, 2024),
(23, 112131, 10, 8, 10, 10, 8, 9, 10, 10, 10, 10, 10, 10, 2024),
(24, 548406, 8, 9, 8, 8, 8, 0, 0, 10, 8, 10, 0, 10, 2024),
(25, 112144, 9, 10, 9, 9, 9, 8, 9, 9, 9, 9, 10, 10, 2024),
(26, 112139, 9, 5, 5, 9, 10, 9, 0, 10, 9, 8, 9, 5, 2024),
(27, 112126, 0, 0, 8, 9, 5, 9, 0, 0, 0, 0, 0, 0, 2024),
(28, 112097, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2024),
(29, 112104, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2024),
(65, 112140, 10, 10, 9, 10, 8, 9, 0, 10, 9, 10, 10, 10, 2025),
(66, 385067, 7, 10, 8, 0, 9, 8, 8, 10, 9, 9, 10, 9, 2025),
(67, 112114, 0, 8, 9, 10, 7, 0, 6, 8, 9, 10, 9, 9, 2025),
(68, 112109, 0, 7, 10, 8, 8, 8, 0, 9, 0, 9, 7, 9, 2025),
(74, 112108, 9, 8, 7, 10, 7, 10, 9, 10, 7, 9, 10, 10, 2025),
(75, 831789, 8, 0, 9, 9, 7, 0, 7, 8, 0, 0, 10, 8, 2025),
(76, 112126, 9, 9, 9, 9, 8, 8, 0, 0, 0, 0, 0, 0, 2025),
(77, 112131, 9, 8, 6, 10, 9, 9, 8, 9, 9, 9, 7, 9, 2025),
(78, 112101, 0, 8, 9, 10, 9, 10, 9, 7, 6, 8, 9, 8, 2025),
(79, 112102, 8, 9, 10, 7, 9, 8, 10, 9, 8, 10, 9, 0, 2025),
(80, 112103, 9, 9, 8, 0, 10, 7, 0, 0, 0, 0, 0, 0, 2025),
(81, 112137, 10, 9, 7, 9, 7, 8, 9, 8, 0, 0, 0, 8, 2025),
(82, 112139, 9, 8, 10, 10, 9, 9, 9, 9, 7, 9, 7, 9, 2025),
(83, 548406, 8, 0, 7, 6, 0, 0, 0, 0, 8, 9, 8, 0, 2025),
(84, 112141, 9, 8, 8, 10, 7, 9, 0, 0, 0, 0, 0, 0, 2025),
(85, 112111, 6, 10, 0, 8, 0, 10, 7, 8, 9, 9, 10, 9, 2025),
(86, 112144, 0, 9, 10, 9, 10, 9, 9, 8, 8, 9, 9, 9, 2025),
(87, 29093, 8, 8, 8, 9, 8, 10, 0, 0, 0, 0, 0, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `schwini_jung`
--

CREATE TABLE `schwini_jung` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `P1Schuss1` int(11) DEFAULT NULL,
  `P1Schuss2` int(11) DEFAULT NULL,
  `P1Schuss3` int(11) DEFAULT NULL,
  `P1Schuss4` int(11) DEFAULT NULL,
  `P1Schuss5` int(11) DEFAULT NULL,
  `P1Schuss6` int(11) DEFAULT NULL,
  `P2Schuss1` int(11) DEFAULT NULL,
  `P2Schuss2` int(11) DEFAULT NULL,
  `P2Schuss3` int(11) DEFAULT NULL,
  `P2Schuss4` int(11) DEFAULT NULL,
  `P2Schuss5` int(11) DEFAULT NULL,
  `P2Schuss6` int(11) DEFAULT NULL,
  `Jahr` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `schwini_jung`
--

INSERT INTO `schwini_jung` (`ID`, `JungschuetzeID`, `P1Schuss1`, `P1Schuss2`, `P1Schuss3`, `P1Schuss4`, `P1Schuss5`, `P1Schuss6`, `P2Schuss1`, `P2Schuss2`, `P2Schuss3`, `P2Schuss4`, `P2Schuss5`, `P2Schuss6`, `Jahr`) VALUES
(4, 38, 10, 0, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0, 2025),
(5, 39, 9, 10, 0, 10, 8, 8, 0, 0, 0, 0, 0, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sektionsrangierungen`
--

CREATE TABLE `sektionsrangierungen` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `jmdefinition_id` int(11) NOT NULL,
  `rang` int(3) NOT NULL,
  `preis` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rangierungen für JM-Anlässe';

--
-- Daten für Tabelle `sektionsrangierungen`
--

INSERT INTO `sektionsrangierungen` (`id`, `year`, `jmdefinition_id`, `rang`, `preis`, `created_at`, `updated_at`) VALUES
(3, 2025, 77, 1, 200.00, '2025-08-11 13:35:10', NULL),
(4, 2025, 74, 4, 0.00, '2025-08-11 13:40:41', NULL),
(5, 2025, 75, 1, 250.00, '2025-08-11 13:41:27', NULL),
(6, 2025, 90, 2, 150.00, '2025-08-11 13:42:13', NULL),
(7, 2025, 91, 1, 200.00, '2025-08-11 13:42:51', NULL),
(8, 2025, 104, 3, 70.00, '2025-08-11 13:43:43', NULL),
(9, 2025, 87, 1, 150.00, '2025-08-11 13:44:11', NULL),
(10, 2025, 103, 4, 100.00, '2025-08-11 13:45:17', NULL),
(11, 2025, 76, 2, 80.00, '2025-08-11 13:45:56', NULL),
(12, 2025, 80, 4, 100.00, '2025-08-27 09:33:14', NULL),
(13, 2025, 79, 6, 70.00, '2025-10-16 09:23:37', NULL),
(14, 2025, 82, 2, 150.00, '2025-10-16 09:26:09', NULL),
(15, 2025, 83, 5, 50.00, '2025-10-16 09:26:35', NULL),
(16, 2025, 84, 10, 0.00, '2025-10-16 09:27:05', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sieger`
--

CREATE TABLE `sieger` (
  `ID` int(6) NOT NULL,
  `Name` varchar(255) NOT NULL,
  `Wert` text NOT NULL,
  `siegerdef` int(3) NOT NULL,
  `year` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `sieger`
--

INSERT INTO `sieger` (`ID`, `Name`, `Wert`, `siegerdef`, `year`) VALUES
(1, 'Linggi Andreas', '60', 3, '1999'),
(2, 'Schober Hanspeter', '59', 3, '2001'),
(3, 'Walder Ruedi', '60', 3, '2003'),
(4, 'Schober Hanspeter', '57', 3, '2005'),
(5, 'Linggi Renato', '58', 3, '2007'),
(6, 'Schober Marco', '59', 3, '2009'),
(7, 'Hiestand Stefan', '60', 3, '2011'),
(8, 'Linggi Renato', '59', 3, '2013'),
(9, 'Lienert Roman', '59', 3, '2015'),
(10, 'von Euw Stefan', '60', 3, '2017'),
(11, 'von Euw Alexander', '59', 3, '2019'),
(12, 'von Euw Stefan', '60', 3, '2021'),
(13, 'von Euw Stefan', '59', 3, '2023'),
(14, 'Müller Pascal', '60', 3, '2000'),
(15, 'Keine Auswertung', '0', 3, '2002'),
(16, 'von Euw Stefan', '58', 3, '2004'),
(17, 'von Euw Werner', '60', 3, '2006'),
(18, 'Lienert Josef', '59', 3, '2008'),
(19, 'Schober Hanspeter', '59', 3, '2010'),
(20, 'von Euw Judith', '59', 3, '2012'),
(21, 'Schober Hanspeter', '60', 3, '2014'),
(22, 'Hiestand Stefan', '60', 3, '2016'),
(23, 'von Euw Stefan', '60', 3, '2018'),
(24, 'Lienert Roman', '60', 3, '2020'),
(25, 'von Euw Stefan', '58', 3, '2022'),
(26, 'Bachmann Karl', '100', 2, '1999'),
(27, 'De Conti Willi', '100', 2, '2001'),
(28, 'Landolt Felix', '100', 2, '2003'),
(29, 'Walder Ruedi', '98', 2, '2005'),
(30, 'von Euw Stefan', '99', 2, '2007'),
(31, 'Sigrist Paul', '99', 2, '2009'),
(32, 'Schober Hanspeter', '100', 2, '2011'),
(33, 'Landolt Felix', '99', 2, '2013'),
(34, 'Linggi Renato', '99', 2, '2015'),
(35, 'von Euw Christian', '100', 2, '2017'),
(36, 'Schober Marco', '99', 2, '2019'),
(37, 'Fuchs Michael', '100', 2, '2021'),
(38, 'Schober Hanspeter', '100', 2, '2023'),
(39, 'von Euw Stefan', '99', 2, '2000'),
(40, 'Zingg Hans', '100', 2, '2002'),
(41, 'Lienert Josef', '100', 2, '2004'),
(42, 'Lienert Roman', '100', 2, '2006'),
(43, 'Landolt Felix', '100', 2, '2008'),
(44, 'von Euw Stefan', '100', 2, '2010'),
(45, 'Sigrist Paul', '100', 2, '2012'),
(46, 'Lienert Josef', '99', 2, '2014'),
(47, 'von Euw Stefan', '99', 2, '2016'),
(48, 'Lienert Roman', '100', 2, '2018'),
(49, 'Hiestand Stefan', '99', 2, '2020'),
(50, 'Sigrist Paul', '99', 2, '2022'),
(51, 'Lienert Josef', '460', 1, '1999'),
(52, 'von Euw Werner', '465', 1, '2001'),
(53, 'von Euw Werner', '462', 1, '2003'),
(54, 'Linggi Marco', '470', 1, '2005'),
(55, 'Lienert Roman', '470', 1, '2007'),
(56, 'Lienert Josef', '466', 1, '2009'),
(57, 'Schober Hanspeter', '472', 1, '2011'),
(58, 'Schober Hanspeter', '465', 1, '2013'),
(59, 'Lienert Roman', '466', 1, '2015'),
(60, 'Linggi Renato', '473', 1, '2017'),
(61, 'Schober Hanspeter', '470', 1, '2019'),
(62, 'von Euw Stefan', '477', 1, '2021'),
(63, 'Linggi Renato', '470', 1, '2023'),
(64, 'Bachmann Karl', '478', 1, '2000'),
(65, 'Cavelti Roger', '477', 1, '2002'),
(66, 'von Euw Stefan', '484', 1, '2004'),
(67, 'Bürgi August', '468', 1, '2006'),
(68, 'von Euw Stefan', '482', 1, '2008'),
(69, 'von Euw Werner', '465', 1, '2010'),
(70, 'von Euw Stefan', '478', 1, '2012'),
(71, 'Unterkofler Mark', '473', 1, '2014'),
(72, 'von Euw Stefan', '472', 1, '2016'),
(73, 'Lienert Roman', '471', 1, '2018'),
(74, 'Lienert Roman', '473', 1, '2020'),
(75, 'von Euw Stefan', '463', 1, '2022'),
(76, 'Linggi Andreas', '267.50', 8, '1999'),
(77, 'von Euw Stefan', '267.80', 8, '2001'),
(78, 'von Euw Stefan', '263.40', 8, '2003'),
(79, 'Lienert Josef', '264.30', 8, '2005'),
(80, 'Lienert Roman', '266.90', 8, '2007'),
(81, 'Lienert Roman', '264.10', 8, '2009'),
(82, 'Hiestand Stefan', '268.70', 8, '2011'),
(83, 'Linggi Ella', '265.50', 8, '2013'),
(84, 'Lienert Roman', '269.40', 8, '2015'),
(85, 'Schober Hanspeter', '265.40', 8, '2017'),
(86, 'Lienert Roman', '267.50', 8, '2019'),
(87, 'von Euw Stefan', '270.30', 8, '2021'),
(88, 'von Euw Stefan', '269.40', 8, '2023'),
(89, 'Kälin Bruno', '266.20', 8, '2000'),
(90, 'Greutmann Willy', '208.40', 8, '2002'),
(91, 'von Euw Stefan', '274.80', 8, '2004'),
(92, 'Schober Hanspeter', '268.70', 8, '2006'),
(93, 'von Euw Stefan', '268.90', 8, '2008'),
(94, 'Schober Hanspeter', '266.30', 8, '2010'),
(95, 'von Euw Stefan', '268.20', 8, '2012'),
(96, 'Linggi Ella', '266.20', 8, '2014'),
(97, 'von Euw Alexander', '264.80', 8, '2016'),
(98, 'von Euw Stefan', '270.70', 8, '2018'),
(99, 'Lienert Roman', '270.40', 8, '2020'),
(100, 'von Euw Stefan', '263.90', 8, '2022'),
(101, 'Linggi Silvio', '256.70', 9, '2009'),
(102, 'Sigrist Paul', '255.40', 9, '2011'),
(103, 'Sturzenegger Robert', '258.70', 9, '2013'),
(104, 'Wittek Robin', '257.30', 9, '2015'),
(105, 'Cavelti Roger', '249.40', 9, '2017'),
(106, 'Cavelti Roger', '252', 11, '2019'),
(107, 'Cavelti Roger', '248.40', 9, '2021'),
(108, 'Sigrist Paul', '241.00', 9, '2023'),
(109, 'Bachmann Karl', '258.30', 9, '2008'),
(110, 'Linggi Silvio', '255.70', 9, '2010'),
(111, 'Kälin Bruno', '254.30', 9, '2012'),
(112, 'Linggi Silvio', '249.10', 9, '2014'),
(113, 'Sturzenegger Robert', '249.50', 9, '2016'),
(114, 'Bachmann Karl', '245.80', 9, '2018'),
(115, 'Cavelti Roger', '245.60', 9, '2020'),
(116, 'Cavelti Roger', '250.00', 9, '2022'),
(117, 'Bachmann Karl', '1124', 15, '1997'),
(118, 'Bachmann Karl', '1298', 15, '1999'),
(119, 'Bachmann Karl', '1339', 15, '2001'),
(120, 'Bachmann Karl', '1557', 15, '2003'),
(121, 'Landolt Felix', '1271', 15, '2005'),
(122, 'Bachmann Karl', '1192', 15, '2007'),
(123, 'Bachmann Karl', '1431', 15, '2009'),
(124, 'Bachmann Karl', '1496', 15, '2011'),
(125, 'Bachmann Karl', '1629.75', 15, '2013'),
(126, 'Wittek Robin', '1533.25', 15, '2015'),
(127, 'Bachmann Karl', '1540', 15, '2017'),
(128, 'Bachmann Karl', '1650', 15, '2019'),
(129, 'Nicht durchgeführt', '0', 15, '2021'),
(130, 'Bachmann Karl', '1213', 15, '1998'),
(131, 'Bachmann Karl', '1537', 15, '2000'),
(132, 'Bachmann Karl', '1405', 15, '2002'),
(133, 'Bachmann Karl', '1380', 15, '2004'),
(134, 'Bachmann Karl', '1307', 15, '2006'),
(135, 'Bachmann Karl', '1388', 15, '2008'),
(136, 'Bachmann Karl', '1468', 15, '2010'),
(137, 'Bachmann Karl', '1330', 15, '2012'),
(138, 'Landolt Felix', '1644', 15, '2014'),
(139, 'Bachmann Karl', '1459.25', 15, '2016'),
(140, 'Bachmann Karl', '0', 15, '2018'),
(141, 'Nicht durchgeführt', '0', 15, '2020'),
(142, 'Cavelti Roger', '1708.75', 15, '2023'),
(143, 'Bachmann Karl', '1719.75', 15, '2022'),
(144, 'von Euw Werner', '1106', 14, '1997'),
(145, 'von Euw Werner', '1349', 14, '1999'),
(146, 'von Euw Stefan', '1368', 14, '2001'),
(147, 'von Euw Stefan', '1582', 14, '2003'),
(148, 'von Euw Stefan', '1501', 14, '2005'),
(149, 'Schober Hanspeter', '1263', 14, '2007'),
(150, 'von Euw Werner', '1519', 14, '2009'),
(151, 'Lienert Roman', '1601', 14, '2011'),
(152, 'Linggi Marco', '1778', 14, '2013'),
(153, 'Lienert Roman', '1666.50', 14, '2015'),
(154, 'Lienert Roman', '1680.75', 14, '2017'),
(155, 'Lienert Roman', '1779.25', 14, '2019'),
(156, 'Nicht durchgeführt', '0', 14, '2021'),
(157, 'Lienert Roman', '1849', 14, '2023'),
(158, 'von Euw Stefan', '1254', 14, '1998'),
(159, 'von Euw Stefan', '1584', 14, '2000'),
(160, 'von Euw Werner', '1452', 14, '2002'),
(161, 'von Euw Stefan', '1422', 14, '2004'),
(162, 'von Euw Stefan', '1587', 14, '2006'),
(163, 'von Euw Stefan', '1489', 14, '2008'),
(164, 'Linggi Renato', '1592', 14, '2010'),
(165, 'von Euw Stefan', '1433', 14, '2012'),
(166, 'Linggi Renato', '1783', 14, '2014'),
(167, 'Lienert Roman', '1576', 14, '2016'),
(168, 'Lienert Roman', '0', 14, '2018'),
(169, 'Nicht durchgeführt', '0', 14, '2020'),
(170, 'Lienert Roman', '1866', 14, '2022'),
(171, 'Kälin Bruno', '96', 10, '1997'),
(172, 'von Euw Stefan', '96', 10, '1999'),
(173, 'Linggi Ella', '770', 10, '2001'),
(174, 'von Euw Stefan', '771', 10, '2003'),
(175, 'von Euw Stefan', '761', 10, '2005'),
(176, 'Lienert Roman', '774', 10, '2007'),
(177, 'Linggi Renato', '777', 10, '2009'),
(178, 'Linggi Renato', '771', 10, '2011'),
(179, 'von Euw Christian', '762', 10, '2013'),
(180, 'Lienert Roman', '772', 10, '2015'),
(181, 'von Euw Alexander', '775', 10, '2017'),
(182, 'von Euw Stefan', '772', 10, '2019'),
(183, 'Lienert Roman', '768', 10, '2021'),
(184, 'von Euw Stefan', '763', 10, '2023'),
(185, 'Kälin Bruno', '96', 10, '1998'),
(186, 'von Euw Stefan', '97', 10, '2000'),
(187, 'von Euw Stefan', '774', 10, '2002'),
(188, 'Linggi Renato', '776', 10, '2004'),
(189, 'von Euw Stefan', '777', 10, '2006'),
(190, 'von Euw Stefan', '773', 10, '2008'),
(191, 'Lienert Roman', '769', 10, '2010'),
(192, 'von Euw Stefan', '768', 10, '2012'),
(193, 'Lienert Roman', '775', 10, '2014'),
(194, 'Linggi Renato', '772', 10, '2016'),
(195, 'von Euw Stefan', '772', 10, '2018'),
(196, 'Nicht durchgeführt', '0', 10, '2020'),
(197, 'von Euw Stefan', '771', 10, '2022'),
(198, 'Gassmann Jules', '727', 11, '1997'),
(199, 'Bachmann Karl', '736', 11, '1999'),
(200, 'Bachmann Karl', '746', 11, '2001'),
(201, 'Landolt Felix', '752', 11, '2003'),
(202, 'Landolt Felix', '745', 11, '2005'),
(203, 'Landolt Felix', '746', 11, '2007'),
(204, 'Bachmann Karl', '747', 11, '2009'),
(205, 'Bachmann Karl', '745', 11, '2011'),
(206, 'Sigrist Paul', '741', 11, '2013'),
(207, 'Landolt Felix', '722', 11, '2015'),
(208, 'Bachmann Karl', '728', 11, '2017'),
(209, 'Bachmann Karl', '736', 11, '2019'),
(210, 'Nicht durchgeführt', '0', 11, '2021'),
(211, 'Cavelti Roger', '723', 11, '2023'),
(212, 'Gassmann Jules', '715', 11, '1998'),
(213, 'Bachmann Karl', '741', 11, '2000'),
(214, 'Bachmann Karl', '755', 11, '2002'),
(215, 'Bachmann Karl', '741', 11, '2004'),
(216, 'Kälin Nadine', '754', 11, '2006'),
(217, 'Bachmann Karl', '753', 11, '2008'),
(218, 'Bachmann Karl', '744', 11, '2010'),
(219, 'Bachmann Karl', '742', 11, '2012'),
(220, 'Sigrist Paul', '740', 11, '2014'),
(221, 'Bachmann Karl', '744', 11, '2016'),
(222, 'Sigrist Paul', '725', 11, '2018'),
(223, 'Nicht druchgeführt', '0', 11, '2020'),
(224, 'Cavelti Roger', '727', 11, '2022'),
(225, 'von Euw Stefan', '290', 12, '1997'),
(226, 'Kälin Bruno', '289', 12, '1999'),
(227, 'von Euw Stefan', '286', 12, '2001'),
(228, 'von Euw Stefan', '289', 12, '2003'),
(229, 'von Euw Stefan', '291', 12, '2005'),
(230, 'von Euw Werner', '290', 12, '2007'),
(231, 'von Euw Werner', '482', 12, '2009'),
(232, 'Lienert Roman', '481', 12, '2011'),
(233, 'Lienert Roman', '484', 12, '2013'),
(234, 'Lienert Roman', '481', 12, '2015'),
(235, 'Lienert Roman', '485', 12, '2017'),
(236, 'von Euw Alexander', '484', 12, '2019'),
(237, 'Fuchs Michael', '459', 12, '2021'),
(238, 'Lienert Roman', '472', 12, '2023'),
(239, 'von Euw Stefan', '293', 12, '1998'),
(240, 'von Euw Stefan', '292', 12, '2000'),
(241, 'Kälin Bruno', '288', 12, '2002'),
(242, 'von Euw Werner', '291', 12, '2004'),
(243, 'Linggi Marco ', '289', 12, '2006'),
(244, 'von Euw Stefan', '291', 12, '2008'),
(245, 'von Euw Werner', '484', 12, '2010'),
(246, 'von Euw Stefan', '481', 12, '2012'),
(247, 'Lienert Roman', '474', 12, '2014'),
(248, 'Lienert Roman', '480', 12, '2016'),
(249, 'von Euw Alexander', '483', 12, '2018'),
(250, 'von Euw Stefan', '486', 12, '2020'),
(251, 'von Euw Stefan', '481', 12, '2022'),
(252, 'Zingg Hans', '278', 13, '1997'),
(253, 'Bachmann Karl', '282', 13, '1999'),
(254, 'Bachmann Karl', '288', 13, '2001'),
(255, 'Landolt Felix', '277', 13, '2003'),
(256, 'Landolt Felix', '283', 13, '2005'),
(257, 'Bachmann Karl', '285', 13, '2007'),
(258, 'Bachmann Karl', '466', 13, '2009'),
(259, 'Bachmann Karl', '464', 13, '2011'),
(260, 'Sigrist Paul', '462', 13, '2013'),
(261, 'Wittek Robin', '452', 13, '2015'),
(262, 'Sigrist Paul', '455', 13, '2017'),
(263, 'Sigrist Paul', '453', 13, '2019'),
(264, 'Sigrist Paul', '454', 13, '2021'),
(265, 'Cavelti Roger', '452', 13, '2023'),
(266, 'Bachmann Karl', '288', 13, '1998'),
(267, 'Bachmann Karl', '283', 13, '2000'),
(268, 'Bachmann Karl', '283', 13, '2002'),
(269, 'Landolt Felix', '281', 13, '2004'),
(270, 'Bachmann Karl', '278', 13, '2006'),
(271, 'Bachmann Karl', '279', 13, '2008'),
(272, 'Landolt Felix', '457', 13, '2010'),
(273, 'Bachmann Karl', '461', 13, '2012'),
(274, 'Sigrist Paul', '463', 13, '2014'),
(275, 'Sigrist Paul', '464', 13, '2016'),
(276, 'Bachmann Karl', '462', 13, '2018'),
(277, 'Cavelti Roger', '451', 13, '2020'),
(278, 'Bachmann Karl', '451', 13, '2022'),
(279, 'von Euw Stefan', '98', 6, '1999'),
(280, 'Schober Hanspeter', '98', 6, '2000'),
(281, 'Linggi Andreas', '98', 6, '2001'),
(282, 'von Euw Stefan', '97', 6, '2002'),
(283, 'Linggi Andreas', '59', 7, '1999'),
(284, 'Schober Marco', '58', 7, '2001'),
(285, 'von Euw Stefan', '59', 7, '2003'),
(286, 'Lienert Josef', '59', 7, '2005'),
(287, 'Lienert Roman', '58', 7, '2007'),
(288, 'Hiestand Stefan', '57', 7, '2009'),
(289, 'Schober Hanspeter', '59', 7, '2011'),
(290, 'von Euw Stefan', '58', 7, '2013'),
(291, 'Lienert Roman', '59', 7, '2015'),
(292, 'Linggi Renato', '57', 7, '2017'),
(293, 'Linggi Renato', '58', 7, '2019'),
(294, 'von Euw Stefan', '57', 7, '2021'),
(295, 'von Euw Stefan', '58', 7, '2023'),
(296, 'Bachmann Karl', '59', 7, '2000'),
(297, 'Bachmann Karl', '58', 7, '2002'),
(298, 'von Euw Stefan', '58', 7, '2004'),
(299, 'von Euw Stefan', '58', 7, '2006'),
(300, 'Lienert Roman', '58', 7, '2008'),
(301, 'Hiestand Stefan', '58', 7, '2010'),
(302, 'von Euw Stefan', '58', 7, '2012'),
(303, 'Lienert Roman', '59', 7, '2014'),
(304, 'Linggi Renato', '58', 7, '2016'),
(305, 'Linggi Renato', '59', 7, '2018'),
(306, 'von Euw Stefan', '57', 7, '2020'),
(307, 'von Euw Stefan', '56', 7, '2022'),
(308, 'Lienert Roman', '99', 6, '2024'),
(309, 'Lienert Roman', '60', 7, '2024'),
(311, 'von Euw Stefan', '99', 2, '2024'),
(312, 'von Euw Stefan', '477', 1, '2024'),
(313, 'Lienert Roman', '271.40', 8, '2024'),
(314, 'Sigrist Paul', '251.10', 9, '2024'),
(315, 'Lienert Roman', '1702', 14, '2024'),
(316, 'Cavelti Roger', '1561.50', 15, '2024'),
(317, 'König Marcus', '769', 10, '2024'),
(318, 'Unterkofler Mark', '732', 11, '2024'),
(319, 'von Euw Alexander', '476', 12, '2024'),
(320, 'Cavelti Roger', '450', 13, '2024'),
(321, 'Cavelti Roger', '252.40', 9, '2019'),
(322, 'Lienert Roman', '59', 3, '2024'),
(323, 'Lienert Roman', '261.40', 8, '2025'),
(324, 'Unterkofler Mark', '256.20', 9, '2025'),
(325, 'von Euw Stefan', '96', 6, '2025'),
(326, 'Lienert Roman', '96', 2, '2025'),
(327, 'Lienert Roman', '768', 10, '2025'),
(329, 'Lienert Roman', '1685.50', 14, '2025'),
(330, 'Unterkofler Mark', '1549', 15, '2025'),
(331, 'Lienert Roman', '478', 12, '2025'),
(332, 'Cavelti Roger', '466', 13, '2025'),
(333, 'Unterkofler Mark', '463', 1, '2025'),
(334, 'von Euw Alexander', '56', 7, '2025'),
(335, 'Lienert Roman', '59', 3, '2025'),
(336, 'Hiestand Stefan', '97', 6, '2003'),
(337, 'von Euw Stefan', '99', 6, '2004'),
(338, 'Linggi Ella', '95', 6, '2005'),
(339, 'Schober Hanspeter', '98', 6, '2006'),
(340, 'Linggi Ella', '97', 6, '2007'),
(341, 'von Euw Stefan', '98', 6, '2008'),
(342, 'Lienert Roman', '98', 6, '2009'),
(343, 'Linggi Ella', '97', 6, '2010'),
(344, 'Linggi Ella', '97', 6, '2011'),
(345, 'Schober Hanspeter', '99', 6, '2012'),
(346, 'Lienert Josef', '97', 6, '2013'),
(347, 'Linggi Ella', '98', 6, '2014'),
(348, 'Schober Hanspeter', '96', 6, '2015'),
(349, 'von Euw Alexander', '96', 6, '2016'),
(350, 'Schober Marco', '97', 6, '2017'),
(351, 'von Euw Stefan', '99', 6, '2018'),
(352, 'Lienert Roman', '98', 6, '2019'),
(353, 'Lienert Roman', '97', 6, '2020'),
(354, 'Linggi Renato', '96', 6, '2021'),
(355, 'Lienert Roman', '98', 6, '2022'),
(356, 'von Euw Stefan', '98', 6, '2023'),
(357, 'Judith von Euw', '730', 11, '2025');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `siegerdef`
--

CREATE TABLE `siegerdef` (
  `ID` int(2) NOT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `PlatzhalterWord` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `siegerdef`
--

INSERT INTO `siegerdef` (`ID`, `Bezeichnung`, `PlatzhalterWord`) VALUES
(1, 'Kunst', 'SK'),
(2, 'Glück', 'SG'),
(3, 'Zabigstich', 'SZ'),
(6, 'Endstich', 'SE'),
(7, 'Schwini', 'SS'),
(8, 'EndschiessenA', 'SESA'),
(9, 'EndschiessenB', 'SESB'),
(10, 'HeimmeisterschaftA', 'SHEA'),
(11, 'HeimmeisterschaftB', 'SHEB'),
(12, 'KantonalstichA', 'SKA'),
(13, 'KantonalstichB', 'SKB'),
(14, 'JahresmeisterschaftA', 'SJMA'),
(15, 'JahresmeisterschaftB', 'SJMB');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sieger_backup`
--

CREATE TABLE `sieger_backup` (
  `ID` int(6) NOT NULL DEFAULT 0,
  `Name` varchar(255) NOT NULL,
  `Wert` text NOT NULL,
  `siegerdef` int(3) NOT NULL,
  `year` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `sieger_backup`
--

INSERT INTO `sieger_backup` (`ID`, `Name`, `Wert`, `siegerdef`, `year`) VALUES
(1, 'Linggi Andreas', '60', 3, '1999'),
(2, 'Schober Hanspeter', '59', 3, '2001'),
(3, 'Walder Ruedi', '60', 3, '2003'),
(4, 'Schober Hanspeter', '57', 3, '2005'),
(5, 'Linggi Renato', '58', 3, '2007'),
(6, 'Schober Marco', '59', 3, '2009'),
(7, 'Hiestand Stefan', '60', 3, '2011'),
(8, 'Linggi Renato', '59', 3, '2013'),
(9, 'Lienert Roman', '59', 3, '2015'),
(10, 'von Euw Stefan', '60', 3, '2017'),
(11, 'von Euw Alexander', '59', 3, '2019'),
(12, 'von Euw Stefan', '60', 3, '2021'),
(13, 'von Euw Stefan', '59', 3, '2023'),
(14, 'Müller Pascal', '60', 3, '2000'),
(15, 'Keine Auswertung', '0', 3, '2002'),
(16, 'von Euw Stefan', '58', 3, '2004'),
(17, 'von Euw Werner', '60', 3, '2006'),
(18, 'Lienert Josef', '59', 3, '2008'),
(19, 'Schober Hanspeter', '59', 3, '2010'),
(20, 'von Euw Judith', '59', 3, '2012'),
(21, 'Schober Hanspeter', '60', 3, '2014'),
(22, 'Hiestand Stefan', '60', 3, '2016'),
(23, 'von Euw Stefan', '60', 3, '2018'),
(24, 'Lienert Roman', '60', 3, '2020'),
(25, 'von Euw Stefan', '58', 3, '2022'),
(26, 'Bachmann Karl', '100', 2, '1999'),
(27, 'De Conti Willi', '100', 2, '2001'),
(28, 'Landolt Felix', '100', 2, '2003'),
(29, 'Walder Ruedi', '98', 2, '2005'),
(30, 'von Euw Stefan', '99', 2, '2007'),
(31, 'Sigrist Paul', '99', 2, '2009'),
(32, 'Schober Hanspeter', '100', 2, '2011'),
(33, 'Landolt Felix', '99', 2, '2013'),
(34, 'Linggi Renato', '99', 2, '2015'),
(35, 'von Euw Christian', '100', 2, '2017'),
(36, 'Schober Marco', '99', 2, '2019'),
(37, 'Fuchs Michael', '100', 2, '2021'),
(38, 'Schober Hanspeter', '100', 2, '2023'),
(39, 'von Euw Stefan', '99', 2, '2000'),
(40, 'Zingg Hans', '100', 2, '2002'),
(41, 'Lienert Josef', '100', 2, '2004'),
(42, 'Lienert Roman', '100', 2, '2006'),
(43, 'Landolt Felix', '100', 2, '2008'),
(44, 'von Euw Stefan', '100', 2, '2010'),
(45, 'Sigrist Paul', '100', 2, '2012'),
(46, 'Lienert Josef', '99', 2, '2014'),
(47, 'von Euw Stefan', '99', 2, '2016'),
(48, 'Lienert Roman', '100', 2, '2018'),
(49, 'Hiestand Stefan', '99', 2, '2020'),
(50, 'Sigrist Paul', '99', 2, '2022'),
(51, 'Lienert Josef', '460', 1, '1999'),
(52, 'von Euw Werner', '465', 1, '2001'),
(53, 'von Euw Werner', '462', 1, '2003'),
(54, 'Linggi Marco', '470', 1, '2005'),
(55, 'Lienert Roman', '470', 1, '2007'),
(56, 'Lienert Josef', '466', 1, '2009'),
(57, 'Schober Hanspeter', '472', 1, '2011'),
(58, 'Schober Hanspeter', '465', 1, '2013'),
(59, 'Lienert Roman', '466', 1, '2015'),
(60, 'Linggi Renato', '473', 1, '2017'),
(61, 'Schober Hanspeter', '470', 1, '2019'),
(62, 'von Euw Stefan', '477', 1, '2021'),
(63, 'Linggi Renato', '470', 1, '2023'),
(64, 'Bachmann Karl', '478', 1, '2000'),
(65, 'Cavelti Roger', '477', 1, '2002'),
(66, 'von Euw Stefan', '484', 1, '2004'),
(67, 'Bürgi August', '468', 1, '2006'),
(68, 'von Euw Stefan', '482', 1, '2008'),
(69, 'von Euw Werner', '465', 1, '2010'),
(70, 'von Euw Stefan', '478', 1, '2012'),
(71, 'Unterkofler Mark', '473', 1, '2014'),
(72, 'von Euw Stefan', '472', 1, '2016'),
(73, 'Lienert Roman', '471', 1, '2018'),
(74, 'Lienert Roman', '473', 1, '2020'),
(75, 'von Euw Stefan', '463', 1, '2022'),
(76, 'Linggi Andreas', '267.50', 8, '1999'),
(77, 'von Euw Stefan', '267.80', 8, '2001'),
(78, 'von Euw Stefan', '263.40', 8, '2003'),
(79, 'Lienert Josef', '264.30', 8, '2005'),
(80, 'Lienert Roman', '266.90', 8, '2007'),
(81, 'Lienert Roman', '264.10', 8, '2009'),
(82, 'Hiestand Stefan', '268.70', 8, '2011'),
(83, 'Linggi Ella', '265.50', 8, '2013'),
(84, 'Lienert Roman', '269.40', 8, '2015'),
(85, 'Schober Hanspeter', '265.40', 8, '2017'),
(86, 'Lienert Roman', '267.50', 8, '2019'),
(87, 'von Euw Stefan', '270.30', 8, '2021'),
(88, 'von Euw Stefan', '269.40', 8, '2023'),
(89, 'Kälin Bruno', '266.20', 8, '2000'),
(90, 'Greutmann Willy', '208.40', 8, '2002'),
(91, 'von Euw Stefan', '274.80', 8, '2004'),
(92, 'Schober Hanspeter', '268.70', 8, '2006'),
(93, 'von Euw Stefan', '268.90', 8, '2008'),
(94, 'Schober Hanspeter', '266.30', 8, '2010'),
(95, 'von Euw Stefan', '268.20', 8, '2012'),
(96, 'Linggi Ella', '266.20', 8, '2014'),
(97, 'von Euw Alexander', '264.80', 8, '2016'),
(98, 'von Euw Stefan', '270.70', 8, '2018'),
(99, 'Lienert Roman', '270.40', 8, '2020'),
(100, 'von Euw Stefan', '263.90', 8, '2022'),
(101, 'Linggi Silvio', '256.70', 9, '2009'),
(102, 'Sigrist Paul', '255.40', 9, '2011'),
(103, 'Sturzenegger Robert', '258.70', 9, '2013'),
(104, 'Wittek Robin', '257.30', 9, '2015'),
(105, 'Cavelti Roger', '249.40', 9, '2017'),
(106, 'Cavelti Roger', '252', 11, '2019'),
(107, 'Cavelti Roger', '248.40', 9, '2021'),
(108, 'Sigrist Paul', '241.00', 9, '2023'),
(109, 'Bachmann Karl', '258.30', 9, '2008'),
(110, 'Linggi Silvio', '255.70', 9, '2010'),
(111, 'Kälin Bruno', '254.30', 9, '2012'),
(112, 'Linggi Silvio', '249.10', 9, '2014'),
(113, 'Sturzenegger Robert', '249.50', 9, '2016'),
(114, 'Bachmann Karl', '245.80', 9, '2018'),
(115, 'Cavelti Roger', '245.60', 9, '2020'),
(116, 'Cavelti Roger', '250.00', 9, '2022'),
(117, 'Bachmann Karl', '1124', 15, '1997'),
(118, 'Bachmann Karl', '1298', 15, '1999'),
(119, 'Bachmann Karl', '1339', 15, '2001'),
(120, 'Bachmann Karl', '1557', 15, '2003'),
(121, 'Landolt Felix', '1271', 15, '2005'),
(122, 'Bachmann Karl', '1192', 15, '2007'),
(123, 'Bachmann Karl', '1431', 15, '2009'),
(124, 'Bachmann Karl', '1496', 15, '2011'),
(125, 'Bachmann Karl', '1629.75', 15, '2013'),
(126, 'Wittek Robin', '1533.25', 15, '2015'),
(127, 'Bachmann Karl', '1540', 15, '2017'),
(128, 'Bachmann Karl', '1650', 15, '2019'),
(129, 'Nicht durchgeführt', '0', 15, '2021'),
(130, 'Bachmann Karl', '1213', 15, '1998'),
(131, 'Bachmann Karl', '1537', 15, '2000'),
(132, 'Bachmann Karl', '1405', 15, '2002'),
(133, 'Bachmann Karl', '1380', 15, '2004'),
(134, 'Bachmann Karl', '1307', 15, '2006'),
(135, 'Bachmann Karl', '1388', 15, '2008'),
(136, 'Bachmann Karl', '1468', 15, '2010'),
(137, 'Bachmann Karl', '1330', 15, '2012'),
(138, 'Landolt Felix', '1644', 15, '2014'),
(139, 'Bachmann Karl', '1459.25', 15, '2016'),
(140, 'Bachmann Karl', '0', 15, '2018'),
(141, 'Nicht durchgeführt', '0', 15, '2020'),
(142, 'Cavelti Roger', '1708.75', 15, '2023'),
(143, 'Bachmann Karl', '1719.75', 15, '2022'),
(144, 'von Euw Werner', '1106', 14, '1997'),
(145, 'von Euw Werner', '1349', 14, '1999'),
(146, 'von Euw Stefan', '1368', 14, '2001'),
(147, 'von Euw Stefan', '1582', 14, '2003'),
(148, 'von Euw Stefan', '1501', 14, '2005'),
(149, 'Schober Hanspeter', '1263', 14, '2007'),
(150, 'von Euw Werner', '1519', 14, '2009'),
(151, 'Lienert Roman', '1601', 14, '2011'),
(152, 'Linggi Marco', '1778', 14, '2013'),
(153, 'Lienert Roman', '1666.50', 14, '2015'),
(154, 'Lienert Roman', '1680.75', 14, '2017'),
(155, 'Lienert Roman', '1779.25', 14, '2019'),
(156, 'Nicht durchgeführt', '0', 14, '2021'),
(157, 'Lienert Roman', '1849', 14, '2023'),
(158, 'von Euw Stefan', '1254', 14, '1998'),
(159, 'von Euw Stefan', '1584', 14, '2000'),
(160, 'von Euw Werner', '1452', 14, '2002'),
(161, 'von Euw Stefan', '1422', 14, '2004'),
(162, 'von Euw Stefan', '1587', 14, '2006'),
(163, 'von Euw Stefan', '1489', 14, '2008'),
(164, 'Linggi Renato', '1592', 14, '2010'),
(165, 'von Euw Stefan', '1433', 14, '2012'),
(166, 'Linggi Renato', '1783', 14, '2014'),
(167, 'Lienert Roman', '1576', 14, '2016'),
(168, 'Lienert Roman', '0', 14, '2018'),
(169, 'Nicht durchgeführt', '0', 14, '2020'),
(170, 'Lienert Roman', '1866', 14, '2022'),
(171, 'Kälin Bruno', '96', 10, '1997'),
(172, 'von Euw Stefan', '96', 10, '1999'),
(173, 'Linggi Ella', '770', 10, '2001'),
(174, 'von Euw Stefan', '771', 10, '2003'),
(175, 'von Euw Stefan', '761', 10, '2005'),
(176, 'Lienert Roman', '774', 10, '2007'),
(177, 'Linggi Renato', '777', 10, '2009'),
(178, 'Linggi Renato', '771', 10, '2011'),
(179, 'von Euw Christian', '762', 10, '2013'),
(180, 'Lienert Roman', '772', 10, '2015'),
(181, 'von Euw Alexander', '775', 10, '2017'),
(182, 'von Euw Stefan', '772', 10, '2019'),
(183, 'Lienert Roman', '768', 10, '2021'),
(184, 'von Euw Stefan', '763', 10, '2023'),
(185, 'Kälin Bruno', '96', 10, '1998'),
(186, 'von Euw Stefan', '97', 10, '2000'),
(187, 'von Euw Stefan', '774', 10, '2002'),
(188, 'Linggi Renato', '776', 10, '2004'),
(189, 'von Euw Stefan', '777', 10, '2006'),
(190, 'von Euw Stefan', '773', 10, '2008'),
(191, 'Lienert Roman', '769', 10, '2010'),
(192, 'von Euw Stefan', '768', 10, '2012'),
(193, 'Lienert Roman', '775', 10, '2014'),
(194, 'Linggi Renato', '772', 10, '2016'),
(195, 'von Euw Stefan', '772', 10, '2018'),
(196, 'Nicht durchgeführt', '0', 10, '2020'),
(197, 'von Euw Stefan', '771', 10, '2022'),
(198, 'Gassmann Jules', '727', 11, '1997'),
(199, 'Bachmann Karl', '736', 11, '1999'),
(200, 'Bachmann Karl', '746', 11, '2001'),
(201, 'Landolt Felix', '752', 11, '2003'),
(202, 'Landolt Felix', '745', 11, '2005'),
(203, 'Landolt Felix', '746', 11, '2007'),
(204, 'Bachmann Karl', '747', 11, '2009'),
(205, 'Bachmann Karl', '745', 11, '2011'),
(206, 'Sigrist Paul', '741', 11, '2013'),
(207, 'Landolt Felix', '722', 11, '2015'),
(208, 'Bachmann Karl', '728', 11, '2017'),
(209, 'Bachmann Karl', '736', 11, '2019'),
(210, 'Nicht durchgeführt', '0', 11, '2021'),
(211, 'Cavelti Roger', '723', 11, '2023'),
(212, 'Gassmann Jules', '715', 11, '1998'),
(213, 'Bachmann Karl', '741', 11, '2000'),
(214, 'Bachmann Karl', '755', 11, '2002'),
(215, 'Bachmann Karl', '741', 11, '2004'),
(216, 'Kälin Nadine', '754', 11, '2006'),
(217, 'Bachmann Karl', '753', 11, '2008'),
(218, 'Bachmann Karl', '744', 11, '2010'),
(219, 'Bachmann Karl', '742', 11, '2012'),
(220, 'Sigrist Paul', '740', 11, '2014'),
(221, 'Bachmann Karl', '744', 11, '2016'),
(222, 'Sigrist Paul', '725', 11, '2018'),
(223, 'Nicht druchgeführt', '0', 11, '2020'),
(224, 'Cavelti Roger', '727', 11, '2022'),
(225, 'von Euw Stefan', '290', 12, '1997'),
(226, 'Kälin Bruno', '289', 12, '1999'),
(227, 'von Euw Stefan', '286', 12, '2001'),
(228, 'von Euw Stefan', '289', 12, '2003'),
(229, 'von Euw Stefan', '291', 12, '2005'),
(230, 'von Euw Werner', '290', 12, '2007'),
(231, 'von Euw Werner', '482', 12, '2009'),
(232, 'Lienert Roman', '481', 12, '2011'),
(233, 'Lienert Roman', '484', 12, '2013'),
(234, 'Lienert Roman', '481', 12, '2015'),
(235, 'Lienert Roman', '485', 12, '2017'),
(236, 'von Euw Alexander', '484', 12, '2019'),
(237, 'Fuchs Michael', '459', 12, '2021'),
(238, 'Lienert Roman', '472', 12, '2023'),
(239, 'von Euw Stefan', '293', 12, '1998'),
(240, 'von Euw Stefan', '292', 12, '2000'),
(241, 'Kälin Bruno', '288', 12, '2002'),
(242, 'von Euw Werner', '291', 12, '2004'),
(243, 'Linggi Marco ', '289', 12, '2006'),
(244, 'von Euw Stefan', '291', 12, '2008'),
(245, 'von Euw Werner', '484', 12, '2010'),
(246, 'von Euw Stefan', '481', 12, '2012'),
(247, 'Lienert Roman', '474', 12, '2014'),
(248, 'Lienert Roman', '480', 12, '2016'),
(249, 'von Euw Alexander', '483', 12, '2018'),
(250, 'von Euw Stefan', '486', 12, '2020'),
(251, 'von Euw Stefan', '481', 12, '2022'),
(252, 'Zingg Hans', '278', 13, '1997'),
(253, 'Bachmann Karl', '282', 13, '1999'),
(254, 'Bachmann Karl', '288', 13, '2001'),
(255, 'Landolt Felix', '277', 13, '2003'),
(256, 'Landolt Felix', '283', 13, '2005'),
(257, 'Bachmann Karl', '285', 13, '2007'),
(258, 'Bachmann Karl', '466', 13, '2009'),
(259, 'Bachmann Karl', '464', 13, '2011'),
(260, 'Sigrist Paul', '462', 13, '2013'),
(261, 'Wittek Robin', '452', 13, '2015'),
(262, 'Sigrist Paul', '455', 13, '2017'),
(263, 'Sigrist Paul', '453', 13, '2019'),
(264, 'Sigrist Paul', '454', 13, '2021'),
(265, 'Cavelti Roger', '452', 13, '2023'),
(266, 'Bachmann Karl', '288', 13, '1998'),
(267, 'Bachmann Karl', '283', 13, '2000'),
(268, 'Bachmann Karl', '283', 13, '2002'),
(269, 'Landolt Felix', '281', 13, '2004'),
(270, 'Bachmann Karl', '278', 13, '2006'),
(271, 'Bachmann Karl', '279', 13, '2008'),
(272, 'Landolt Felix', '457', 13, '2010'),
(273, 'Bachmann Karl', '461', 13, '2012'),
(274, 'Sigrist Paul', '463', 13, '2014'),
(275, 'Sigrist Paul', '464', 13, '2016'),
(276, 'Bachmann Karl', '462', 13, '2018'),
(277, 'Cavelti Roger', '451', 13, '2020'),
(278, 'Bachmann Karl', '451', 13, '2022'),
(279, 'von Euw Stefan', '98', 6, '1999'),
(280, 'Schober Hanspeter', '98', 6, '2000'),
(281, 'Linggi Andreas', '98', 6, '2001'),
(282, 'von Euw Stefan', '97', 6, '2002'),
(283, 'Andreas Linggi', '59', 7, '1999'),
(284, 'Marco Schober', '58', 7, '2001'),
(285, 'Stefan von Euw', '59', 7, '2003'),
(286, 'Josef Lienert', '59', 7, '2005'),
(287, 'Roman Lienert', '58', 7, '2007'),
(288, 'Stefan Hiestand', '57', 7, '2009'),
(289, 'Hanspeter Schober', '59', 7, '2011'),
(290, 'Stefan von Euw', '58', 7, '2013'),
(291, 'Roman Lienert', '59', 7, '2015'),
(292, 'Renato Linggi', '57', 7, '2017'),
(293, 'Renato Linggi', '58', 7, '2019'),
(294, 'Stefan von Euw', '57', 7, '2021'),
(295, 'Stefan von Euw', '58', 7, '2023'),
(296, 'Karl Bachmann', '59', 7, '2000'),
(297, 'Karl Bachmann', '58', 7, '2002'),
(298, 'Stefan von Euw', '58', 7, '2004'),
(299, 'Stefan von Euw', '58', 7, '2006'),
(300, 'Roman Lienert', '58', 7, '2008'),
(301, 'Stefan Hiestand', '58', 7, '2010'),
(302, 'Stefan von Euw', '58', 7, '2012'),
(303, 'Roman Lienert', '59', 7, '2014'),
(304, 'Renato Linggi', '58', 7, '2016'),
(305, 'Renato Linggi', '59', 7, '2018'),
(306, 'Stefan von Euw', '57', 7, '2020'),
(307, 'Stefan von Euw', '56', 7, '2022'),
(308, 'Roman Lienert', '99', 6, '2024'),
(309, 'Roman Lienert', '60', 7, '2024'),
(311, 'Stefan von Euw', '99', 2, '2024'),
(312, 'Stefan von Euw', '477', 1, '2024'),
(313, 'Roman Lienert', '271.40', 8, '2024'),
(314, 'Paul Sigrist', '251.10', 9, '2024'),
(315, 'Roman Lienert', '1702', 14, '2024'),
(316, 'Roger Cavelti', '1561.50', 15, '2024'),
(317, 'Marcus König', '769', 10, '2024'),
(318, 'Mark Unterkofler', '732', 11, '2024'),
(319, 'Alexander von Euw', '476', 12, '2024'),
(320, 'Roger Cavelti', '450', 13, '2024'),
(321, 'Cavelti Roger', '252.40', 9, '2019'),
(322, 'Roman Lienert', '59', 3, '2024'),
(323, 'Roman Lienert', '261.40', 8, '2025'),
(324, 'Mark Unterkofler', '256.20', 9, '2025'),
(325, 'Stefan von Euw', '96', 6, '2025'),
(326, 'Roman Lienert', '96', 2, '2025'),
(327, 'Roman Lienert', '768', 10, '2025'),
(328, 'Judith von Euw', '730', 9, '2025'),
(329, 'Roman Lienert', '1685.50', 14, '2025'),
(330, 'Mark Unterkofler', '1549', 15, '2025'),
(331, 'Roman Lienert', '478', 12, '2025'),
(332, 'Roger Cavelti', '466', 13, '2025'),
(333, 'Mark Unterkofler', '463', 1, '2025'),
(334, 'Alexander von Euw', '56', 7, '2025'),
(335, 'Roman Lienert', '59', 3, '2025'),
(336, 'Stefan Hiestand', '97', 6, '2003'),
(337, 'Stefan von Euw', '99', 6, '2004'),
(338, 'Ella Linggi', '95', 6, '2005'),
(339, 'Hanspeter Schober', '98', 6, '2006'),
(340, 'Ella Linggi', '97', 6, '2007'),
(341, 'Stefan von Euw', '98', 6, '2008'),
(342, 'Roman Lienert', '98', 6, '2009'),
(343, 'Ella Linggi', '97', 6, '2010'),
(344, 'Ella Linggi', '97', 6, '2011'),
(345, 'Hanspeter Schober', '99', 6, '2012'),
(346, 'Josef Lienert', '97', 6, '2013'),
(347, 'Ella Linggi', '98', 6, '2014'),
(348, 'Hanspeter Schober', '96', 6, '2015'),
(349, 'Alexander von Euw', '96', 6, '2016'),
(350, 'Marco Schober', '97', 6, '2017'),
(351, 'Stefan von Euw', '99', 6, '2018'),
(352, 'Roman Lienert', '98', 6, '2019'),
(353, 'Roman Lienert', '97', 6, '2020'),
(354, 'Renato Linggi', '96', 6, '2021'),
(355, 'Roman Lienert', '98', 6, '2022'),
(356, 'Stefan von Euw', '98', 6, '2023');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `sKranzLimiten`
--

CREATE TABLE `sKranzLimiten` (
  `ID` int(3) NOT NULL,
  `sAltersKatID` int(1) NOT NULL,
  `WaffenID` int(1) NOT NULL,
  `JMDefinitionID` int(2) NOT NULL,
  `Resultat` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `sKranzLimiten`
--

INSERT INTO `sKranzLimiten` (`ID`, `sAltersKatID`, `WaffenID`, `JMDefinitionID`, `Resultat`) VALUES
(1, 1, 1, 7, 87),
(2, 2, 1, 7, 88),
(3, 3, 1, 7, 87),
(4, 4, 1, 7, 88),
(5, 5, 1, 7, 87),
(6, 1, 2, 7, 80),
(7, 2, 2, 7, 81),
(8, 3, 2, 7, 83),
(9, 4, 2, 7, 81),
(10, 5, 2, 7, 80),
(11, 1, 3, 7, 80),
(12, 2, 3, 7, 81),
(13, 3, 3, 7, 83),
(14, 4, 3, 7, 81),
(15, 5, 3, 7, 80),
(16, 1, 4, 7, 83),
(17, 2, 4, 7, 84),
(18, 3, 4, 7, 85),
(19, 4, 4, 7, 84),
(20, 5, 4, 7, 83);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Standbelegung`
--

CREATE TABLE `Standbelegung` (
  `ID` int(11) NOT NULL,
  `Datum` date NOT NULL,
  `Wochentag` varchar(2) DEFAULT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `StartZeit` time DEFAULT NULL,
  `EndZeit` time DEFAULT NULL,
  `Kategorie` varchar(50) DEFAULT NULL,
  `Jahr` year(4) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `InKalender` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `Standbelegung`
--

INSERT INTO `Standbelegung` (`ID`, `Datum`, `Wochentag`, `Bezeichnung`, `StartZeit`, `EndZeit`, `Kategorie`, `Jahr`, `created_at`, `InKalender`) VALUES
(801, '2025-03-07', 'FR', 'GV MSV Wilen', '18:00:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(802, '2025-03-11', 'DI', 'GV Matchschützen March - Höfe', '19:30:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(803, '2025-03-12', 'MI', 'Frühlingsrap. Schiesskommission 2', '18:00:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(804, '2025-03-15', 'SA', 'Standreinigung', '08:00:00', '12:00:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(805, '2025-03-15', 'SA', '1. Match-Training MSMH 300 m', '13:00:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(806, '2025-03-20', 'DO', 'GV Pistolenschützen am Etzel', '19:30:00', '22:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(807, '2025-03-22', 'SA', '2. Match-Training MSMH 300 m', '09:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(808, '2025-03-22', 'SA', 'Schülerschiessen KK 50 m', '14:00:00', '16:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(809, '2025-03-23', 'SO', 'Nachwuchs-Final Linthverband 10 m', '09:00:00', '14:00:00', '10m', '2025', '2026-01-09 13:55:50', 0),
(810, '2025-03-25', 'DI', 'Trainingsbeginn KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(811, '2025-03-29', 'SA', 'Höfnerländlischiessen KK 50 m', '08:00:00', '12:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(812, '2025-03-29', 'SA', 'Eröffnugsmatch March-Höfe 300 m', '13:30:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(813, '2025-04-01', 'DI', 'Trainingsbeginn Pist. 25 m KK', '17:30:00', '19:30:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(814, '2025-04-02', 'MI', 'Trainingsbeginn 300m / 50/25 m GK', '17:30:00', '19:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(815, '2025-04-05', 'SA', 'Höfnerländlischiessen KK 50 m', '08:00:00', '16:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(816, '2025-04-06', 'SO', 'Höfnerländlischiessen KK 50 m', '08:00:00', '12:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(817, '2025-04-07', 'MO', 'Einschreiben JS-Kurs Gewehr 300 m', '17:30:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(818, '2025-04-10', 'DO', 'DV Standkommission Roggenacker', '20:00:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(819, '2025-04-11', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(820, '2025-04-12', 'SA', 'Schlossturmschiessen 300 m', '08:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(821, '2025-04-12', 'SA', 'Schlossturmschiessen 300 m', '13:30:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(822, '2025-04-13', 'SO', 'Schlossturmschiessen 300 m', '09:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(823, '2025-04-14', 'MO', '1. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(824, '2025-04-16', 'MI', 'Trainingsbeginn 25/50/300m neu ab', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(825, '2025-04-17', 'DO', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(826, '2025-04-24', 'DO', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(827, '2025-04-26', 'SA', 'Schlossturmschiessen 300 m', '08:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(828, '2025-04-26', 'SA', 'Schiesskurs Pistole 25 m GK', '10:00:00', '12:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(829, '2025-04-26', 'SA', 'Einzelwettschiessen Gewehr 300 m', '13:30:00', '17:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(830, '2025-04-27', 'SO', 'Einzelwettschiessen Gewehr 300 m', '09:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(831, '2025-04-28', 'MO', '2. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(832, '2025-05-03', 'SA', 'F-Match Zürcheroberland 300 m', '08:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(833, '2025-05-05', 'MO', '3. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(834, '2025-05-12', 'MO', '4. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(835, '2025-05-16', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(836, '2025-05-17', 'SA', 'Julius Bär Schiessen 300m Pist. 25m', '09:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(837, '2025-05-17', 'SA', 'Julius Bär Schiessen 300m Pist. 25m', '13:30:00', '16:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(838, '2025-05-19', 'MO', '5. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(839, '2025-05-21', 'MI', '1. Bundesprogramm 300m / P25m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(840, '2025-05-23', 'FR', 'Feldschiessen G 300 m / Pist. 25 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(841, '2025-05-23', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(842, '2025-05-24', 'SA', 'Feldschiessen G 300 m / Pist. 25 m', '09:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(843, '2025-05-24', 'SA', 'Stand-Cup Gewehr 300 m', '13:00:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(844, '2025-05-25', 'SO', 'Feldschiessen G 300 m / Pist. 25 m', '09:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(845, '2025-05-26', 'MO', '6. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(846, '2025-05-30', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(847, '2025-06-02', 'MO', '7. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(848, '2025-06-04', 'MI', 'Vorschiessen RSV March-Höfe, 300m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(849, '2025-06-07', 'SA', 'SSVL Verbandsschiessen KK 50 m', '13:30:00', '17:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(850, '2025-06-12', 'DO', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(851, '2025-06-13', 'FR', 'RSV March-Höfe, H-Schiessen 300m', '16:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(852, '2025-06-14', 'SA', 'RSV March-Höfe, H-Schiessen 300m', '13:30:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(853, '2025-06-14', 'SA', 'Schiesskurs Pistole 25 m GK', '10:00:00', '12:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(854, '2025-06-14', 'SA', 'RSV March-Höfe, H-Schiessen 300m', '08:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(855, '2025-06-15', 'SO', 'SSVL Verbandsschiessen KK 50 m', '09:00:00', '12:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(856, '2025-06-17', 'DI', 'SSVL-Verbandsschiessen KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(857, '2025-06-20', 'FR', '2. Bundesprogramm 300 m u. 25 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(858, '2025-06-21', 'SA', 'Verbandsmatch March-Höfe 300 m', '08:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(859, '2025-06-22', 'SO', 'SSVL Grp.-Final KK 50 m', '07:00:00', '14:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(860, '2025-06-24', 'DI', 'Feuerwehr-Schiessen KK 50 m', '18:00:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(861, '2025-06-26', 'DO', 'Absenden RSV-Schiessen 300 m', '20:00:00', '23:59:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(862, '2025-06-26', 'DO', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(863, '2025-06-27', 'FR', 'Kant. Vet. Schiessen Pist. 25/50 m', '17:00:00', '19:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(864, '2025-06-28', 'SA', 'Kant. Vet. Schiessen Pist. 25/50 m', '09:30:00', '11:30:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(865, '2025-07-03', 'DO', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(866, '2025-07-05', 'SA', 'DMM Verbands-Match 300 m', '08:30:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(867, '2025-07-05', 'SA', 'DMM Verbands-Match 300 m', '13:30:00', '17:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(868, '2025-07-08', 'DI', 'Ferienplausch KK 50 m', '18:00:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(869, '2025-07-09', 'MI', 'Letztes Training 300m / Pist. 50/25 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(870, '2025-08-06', 'MI', '1. Training n. Sommerp. 300/50/25m', '18:00:00', '20:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(871, '2025-08-08', 'FR', 'Vet. Schiessen SSVL  KK 50 m', '16:00:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(872, '2025-08-20', 'MI', 'Vet. Schiessen Höfe Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(873, '2025-08-22', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(874, '2025-08-23', 'SA', 'Offiziers-G. March-Höfe, 300 / P25 m', '13:00:00', '17:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(875, '2025-08-27', 'MI', '3. Bundesprogramm 300 m u. 25 m', '18:00:00', '20:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(876, '2025-08-29', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(877, '2025-08-30', 'SA', 'Gästeschiessen PS a. Etzel, 25 m', '13:00:00', '17:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(878, '2025-09-05', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(879, '2025-09-10', 'MI', 'Trainingsbeginn 25/50/300m neu ab', '17:30:00', '19:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(880, '2025-09-12', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(881, '2025-09-13', 'SA', 'Schlussmatch March-Höfe G 300 m', '08:30:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(882, '2025-09-13', 'SA', 'Schiesskurs Pistole 25 m GK', '10:00:00', '12:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(883, '2025-09-19', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(884, '2025-09-26', 'FR', 'Jungschützenkurs KK 50 m', '17:30:00', '20:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(885, '2025-10-08', 'MI', 'Letztes Training 300m / Pist. 50/25 m', '17:30:00', '19:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(886, '2025-10-09', 'DO', 'Herbstversammlung PS am Etzel', '19:30:00', '21:30:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(887, '2025-10-11', 'SA', 'Endschiessen 300 m / Pist. 25/50 m', '09:00:00', '11:30:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(888, '2025-10-11', 'SA', 'Endschiessen 300 m / Pist. 25/50 m', '13:00:00', '16:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(889, '2025-10-25', 'SA', 'Endschiessen KK 50 m', '13:00:00', '15:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(890, '2025-10-26', 'SO', 'Endschiessen KK 50 m', '09:00:00', '17:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(891, '2025-11-08', 'SA', 'Winterschiessen Gewehr 300 m', '10:00:00', '12:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(892, '2025-11-08', 'SA', 'Winterschiessen Gewehr 300 m', '13:00:00', '15:00:00', '300m', '2025', '2026-01-09 13:55:50', 0),
(893, '2025-11-08', 'SA', 'Morgartentraining Pist. 50 m', '13:30:00', '15:00:00', '50m', '2025', '2026-01-09 13:55:50', 0),
(894, '2025-11-12', 'MI', 'Höfner Luftpistolenmeisterschaft', '17:00:00', '22:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(895, '2025-11-13', 'DO', 'Höfner Luftpistolenmeisterschaft', '17:00:00', '22:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(896, '2025-11-14', 'FR', 'Höfner Luftpistolenmeisterschaft', '17:00:00', '22:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(897, '2025-11-15', 'SA', 'Höfner Luftpistolenmeisterschaft', '10:00:00', '17:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(898, '2025-11-15', 'SA', 'Absenden MSV Wilen', '18:00:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(899, '2025-11-17', 'MO', 'Höfner Luftpistolenmeisterschaft', '17:00:00', '22:00:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(900, '2025-12-13', 'SA', 'Absenden PS am Etzel', '18:00:00', '23:59:00', 'Sonstiges', '2025', '2026-01-09 13:55:50', 0),
(901, '2025-12-20', 'SA', 'Altjahrausschiessen Pist. 50/25 m GK', '13:30:00', '15:30:00', '25m', '2025', '2026-01-09 13:55:50', 0),
(902, '2026-03-06', 'FR', 'GV MSV Wilen', '18:00:00', '23:59:00', 'Sonstiges', '2026', '2026-01-21 13:28:28', 0),
(903, '2026-03-07', 'SA', '1. Match-Training 300 m', '13:00:00', '16:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(904, '2026-03-14', 'SA', '2. Match-Training 300 m', '08:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(905, '2026-03-21', 'SA', 'Standreinigung', '08:00:00', '12:00:00', 'Sonstiges', '2026', '2026-01-21 13:28:28', 1),
(906, '2026-03-30', 'MO', 'Einschreiben JS-Kurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(907, '2026-04-01', 'MI', 'Trainingsbeginn 25 / 50 / 300 m', '17:30:00', '19:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(908, '2026-04-09', 'DO', 'GV Standkommission Roggenacker', '20:00:00', '23:59:00', 'Sonstiges', '2026', '2026-01-21 13:28:28', 1),
(909, '2026-04-13', 'MO', '1. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(910, '2026-04-15', 'MI', 'Vorschiessen Schlossturm 300 m', '17:30:00', '19:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(911, '2026-04-18', 'SA', 'Schlossturmschiessen 300 m', '08:00:00', '12:00:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(912, '2026-04-18', 'SA', 'Schlossturmschiessen 300 m', '13:30:00', '17:00:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(913, '2026-04-19', 'SO', 'Schlossturmschiessen 300 m', '09:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(914, '2026-04-20', 'MO', '2. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(915, '2026-04-22', 'MI', 'Trainingsbeginn neu ab', '18:00:00', '20:00:00', 'Sonstiges', '2026', '2026-01-21 13:28:28', 1),
(916, '2026-04-25', 'SA', 'Schlossturmschiessen 300 m', '08:00:00', '12:00:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(917, '2026-04-25', 'SA', 'Einzellwettschiessen (EWS) 300 m', '13:30:00', '15:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(918, '2026-04-26', 'SO', 'Einzellwettschiessen (EWS) 300 m', '09:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(919, '2026-04-27', 'MO', '3. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(920, '2026-05-04', 'MO', '4. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(921, '2026-05-11', 'MO', '5. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(922, '2026-05-18', 'MO', '6. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(923, '2026-05-27', 'MI', '1. Bundesprogramm P25m / G300m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(924, '2026-05-29', 'FR', 'Eidg. Feldschiessen P25m / G300m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(925, '2026-05-30', 'SA', 'Stand-Cup-Schiessen G 300 m', '08:30:00', '12:00:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(926, '2026-05-30', 'SA', 'Eidg. Feldschiessen P25m / G300m', '13:30:00', '15:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(927, '2026-05-31', 'SO', 'Eidg. Feldschiessen P25m / G300m', '09:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(928, '2026-06-01', 'MO', '7. Jungschützenkurs Gewehr 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(929, '2026-06-10', 'MI', 'Jungschützenwettschiessen 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(930, '2026-06-13', 'SA', 'Jungschützenwettschiessen 300 m', '09:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(931, '2026-06-24', 'MI', '2. Bundesprogramm P25m / G300m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(932, '2026-06-25', 'DO', 'Absenden JS-Wettschiessen 300 m', '20:00:00', '23:59:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(933, '2026-07-08', 'MI', 'Letztes Training 300 m / Pist. 25/50m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(934, '2026-08-19', 'MI', 'Veteranen-Schiessen Höfe 300 m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(935, '2026-08-28', 'FR', '3. Bundesprogramm P25m / G300m', '18:00:00', '20:00:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(936, '2026-09-10', 'MI', 'Trainingsbeginn 25/50/300m neu ab', '17:30:00', '19:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(937, '2026-10-07', 'MI', 'Letztes Training 300m / Pist. 50/25 m', '17:30:00', '19:30:00', '300m', '2026', '2026-01-21 13:28:28', 1),
(938, '2026-10-10', 'SA', 'Endschiessen Gewehr 300 m', '09:00:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(939, '2026-11-07', 'SA', 'Winterschiessen Höfe Gewehr 300 m', '09:30:00', '11:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(940, '2026-11-07', 'SA', 'Winterschiessen Höfe Gewehr 300 m', '13:30:00', '15:30:00', '300m', '2026', '2026-01-21 13:28:28', 0),
(941, '2026-11-14', 'SA', 'Absenden MSV Wilen', '18:00:00', '23:59:00', 'Sonstiges', '2026', '2026-01-21 13:28:28', 0),
(942, '2026-10-10', 'SA', 'Endschiessen Gewehr 300 m', '13:00:00', '16:00:00', '300m', '2026', '2026-01-21 12:28:28', 0);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Standbelegung_ArtKeywords`
--

CREATE TABLE `Standbelegung_ArtKeywords` (
  `ID` int(11) NOT NULL,
  `Keyword` varchar(100) NOT NULL,
  `Art` varchar(10) NOT NULL DEFAULT 'SF',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `Standbelegung_ArtKeywords`
--

INSERT INTO `Standbelegung_ArtKeywords` (`ID`, `Keyword`, `Art`, `created_at`) VALUES
(1, 'Schlossturmschiessen', 'SF', '2026-01-07 15:29:44'),
(2, 'Winterschiessen', 'SF', '2026-01-07 15:29:44'),
(3, 'Veteranenmeisterschaft', 'SF', '2026-01-07 15:29:44'),
(4, 'Schützenfest', 'SF', '2026-01-07 15:29:44'),
(5, 'Endschiessen', 'SF', '2026-01-07 15:29:44'),
(6, 'Kantonalschiessen', 'SF', '2026-01-07 15:29:44'),
(7, 'Bezirksschiessen', 'SF', '2026-01-07 15:29:44'),
(8, 'Gruppenschiessen', 'SF', '2026-01-07 15:29:44'),
(9, 'Vereinsmeisterschaft', 'SF', '2026-01-07 15:29:44'),
(10, 'Herbstschiessen', 'SF', '2026-01-07 15:29:44'),
(11, 'Frühlingsschiessen', 'SF', '2026-01-07 15:29:44'),
(12, 'Jahresmeisterschaft', 'SF', '2026-01-07 15:29:44'),
(13, 'Feldschiessen', 'FS', '2026-01-07 15:33:22'),
(14, 'Eidgenössisches Feldschiessen', 'FS', '2026-01-07 15:33:22');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `umfragen`
--

CREATE TABLE `umfragen` (
  `id` int(11) NOT NULL,
  `titel` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `erstellt_von` int(11) NOT NULL,
  `erstellt_am` datetime DEFAULT current_timestamp(),
  `gueltig_bis` date DEFAULT NULL,
  `status` enum('entwurf','aktiv','geschlossen') DEFAULT 'entwurf',
  `zielgruppe` enum('alle','vorstand') DEFAULT 'alle',
  `kategorie` varchar(50) NOT NULL DEFAULT 'umfrage'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `umfragen`
--

INSERT INTO `umfragen` (`id`, `titel`, `beschreibung`, `erstellt_von`, `erstellt_am`, `gueltig_bis`, `status`, `zielgruppe`, `kategorie`) VALUES
(1, 'Gemeinsames Mittagessen 25.04.2026', 'Ort: Roggenacker', 1, '2026-02-23 16:49:11', '2026-04-22', 'aktiv', 'alle', 'umfrage'),
(2, 'Schlossturmschiessen 2026', 'Geschätzte Schützen\n\nDer Personalbedarf für  das Schlossturmschiessen liegt bei 24 Personen pro Schiesstag.\nDas heisst 8 Personen pro Verein, um die Arbeiten gleichmässig aufzuteilen.\nBitte tragt alle möglichen Einsätze in der Liste ein für die definitive Einteilung.\n\nHerzlichen Dank\nOK Schlossturmschiessen 2026', 1, '2026-03-02 08:48:41', '2026-03-15', 'aktiv', 'alle', 'arbeitseinsatz'),
(3, 'Gemeinsames Mittagessen 22.08.2026', 'Restaurant Sager', 1, '2026-03-02 08:56:47', '2026-08-17', 'aktiv', 'alle', 'umfrage'),
(4, 'Gemeinsames Mittagessen Endschiessen 10.10.2026', 'Roggenacker', 1, '2026-03-02 09:20:34', '2026-10-05', 'aktiv', 'alle', 'umfrage');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `umfragen_antworten`
--

CREATE TABLE `umfragen_antworten` (
  `id` int(11) NOT NULL,
  `umfrage_id` int(11) NOT NULL,
  `frage_id` int(11) NOT NULL,
  `mitglied_id` int(11) NOT NULL,
  `antwort` text DEFAULT NULL,
  `beantwortet_am` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `umfragen_antworten`
--

INSERT INTO `umfragen_antworten` (`id`, `umfrage_id`, `frage_id`, `mitglied_id`, `antwort`, `beantwortet_am`) VALUES
(1, 1, 2, 112101, 'Ja', '2026-02-27 18:21:26'),
(2, 1, 2, 112097, 'Ja', '2026-02-27 20:02:53'),
(6, 4, 12, 112101, 'Ja', '2026-03-02 09:20:45'),
(7, 4, 12, 112097, 'Ja', '2026-03-02 10:34:59'),
(9, 2, 20, 112097, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\",\"Sonntag, 19.04.2026, 09:30 - 11.30\",\"Samstag, 25.04.2026, 08:00 - 12:00\"]', '2026-03-02 10:36:08'),
(10, 2, 19, 112097, '[\"OK Schlossturm\"]', '2026-03-02 10:36:21'),
(11, 2, 20, 889594, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\",\"Sonntag, 19.04.2026, 09:30 - 11.30\",\"Samstag, 25.04.2026, 08:00 - 12:00\"]', '2026-03-06 21:45:16'),
(12, 2, 19, 889594, '[\"Schützenmeister\",\"Warner\",\"Türkontrolle\"]', '2026-03-06 21:44:54'),
(13, 2, 19, 112140, '[\"Schützenmeister\"]', '2026-03-06 22:08:35'),
(14, 2, 20, 112140, '[\"Samstag, 18.04.2026, 08:00 - 12:00\"]', '2026-03-06 22:24:54'),
(15, 4, 12, 112111, 'Ja', '2026-03-06 22:13:47'),
(17, 1, 2, 112111, 'Ja', '2026-03-06 22:18:31'),
(18, 2, 19, 29093, '[\"Warner\"]', '2026-03-06 22:19:27'),
(19, 2, 20, 29093, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\",\"Sonntag, 19.04.2026, 09:30 - 11.30\"]', '2026-03-06 22:19:46'),
(20, 1, 2, 29093, 'Ja', '2026-03-06 22:24:46'),
(21, 4, 12, 112137, 'Ja', '2026-03-07 01:40:47'),
(22, 4, 12, 889594, 'Ja', '2026-03-07 04:01:52'),
(24, 1, 2, 889594, 'Ja', '2026-03-07 04:02:02'),
(25, 4, 12, 385067, 'Ja', '2026-03-21 07:45:21'),
(26, 2, 19, 385067, '[\"Warner\"]', '2026-03-07 09:05:13'),
(27, 2, 20, 385067, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\"]', '2026-03-07 09:05:14'),
(28, 2, 19, 112111, '[\"Warner\"]', '2026-03-07 09:49:25'),
(29, 2, 20, 112111, '[\"Sonntag, 19.04.2026, 09:30 - 11.30\",\"Samstag, 25.04.2026, 08:00 - 12:00\"]', '2026-03-07 09:49:32'),
(30, 1, 2, 112137, 'Nein', '2026-03-07 11:35:45'),
(31, 2, 21, 112137, 'Bin im Ausland', '2026-03-07 11:36:43'),
(33, 2, 19, 112108, '[\"Büro\",\"Schützenmeister\",\"Warner\"]', '2026-03-07 15:37:34'),
(34, 2, 20, 112108, '[\"Sonntag, 19.04.2026, 09:30 - 11.30\"]', '2026-03-07 15:37:41'),
(35, 4, 12, 112108, 'Ja', '2026-03-07 15:48:57'),
(37, 1, 2, 112108, 'Ja', '2026-03-07 15:49:11'),
(38, 2, 19, 112101, '[\"OK Schlossturm\"]', '2026-03-13 23:14:35'),
(39, 2, 20, 112101, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\",\"Sonntag, 19.04.2026, 09:30 - 11.30\",\"Samstag, 25.04.2026, 08:00 - 12:00\"]', '2026-03-13 23:14:39'),
(40, 3, 22, 112101, 'Ja', '2026-03-13 23:15:01'),
(41, 4, 12, 112102, 'Ja', '2026-03-15 13:48:20'),
(42, 3, 22, 112102, 'Nein', '2026-03-15 13:49:25'),
(43, 2, 19, 112102, '[\"Büro\",\"Schützenmeister\",\"Warner\"]', '2026-03-15 13:49:41'),
(44, 2, 20, 112102, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\"]', '2026-03-15 13:49:49'),
(45, 2, 21, 112102, 'Nachmittag für Marco anstelle meines Obli-Einsatzes am 27. Mai', '2026-03-15 13:50:48'),
(46, 1, 2, 112102, 'Nein', '2026-03-15 13:51:24'),
(47, 3, 22, 112137, 'Nein', '2026-03-16 13:14:40'),
(48, 3, 22, 112108, 'Ja', '2026-03-20 19:24:15'),
(49, 1, 2, 385067, 'Ja', '2026-03-21 07:44:46'),
(50, 3, 22, 112103, 'Ja', '2026-03-25 09:45:15'),
(51, 1, 2, 112103, 'Ja', '2026-03-25 09:45:18'),
(52, 4, 12, 112103, 'Nein', '2026-03-25 09:45:29');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `umfragen_fragen`
--

CREATE TABLE `umfragen_fragen` (
  `id` int(11) NOT NULL,
  `umfrage_id` int(11) NOT NULL,
  `frage_text` varchar(500) NOT NULL,
  `frage_typ` enum('radio','checkbox','dropdown','text') NOT NULL,
  `pflichtfeld` tinyint(1) DEFAULT 1,
  `min_auswahl` int(11) DEFAULT NULL,
  `reihenfolge` int(11) DEFAULT 0,
  `optionen` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`optionen`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `umfragen_fragen`
--

INSERT INTO `umfragen_fragen` (`id`, `umfrage_id`, `frage_text`, `frage_typ`, `pflichtfeld`, `min_auswahl`, `reihenfolge`, `optionen`) VALUES
(2, 1, 'Nimmst du am Mittagessen teil?', 'radio', 1, NULL, 0, '[\"Ja\",\"Nein\"]'),
(12, 4, 'Nimmst du am Mittagessen teil?', 'radio', 1, NULL, 0, '[\"Ja\",\"Nein\"]'),
(19, 2, 'Welche Funktionen kannst du ausüben?', 'checkbox', 0, NULL, 0, '[\"Büro\",\"Schützenmeister\",\"Warner\",\"Türkontrolle\",\"Parkdienst\",\"OK Schlossturm\"]'),
(20, 2, 'An welchen Terminen bist du verfügbar?', 'checkbox', 0, 2, 1, '[\"Samstag, 18.04.2026, 08:00 - 12:00\",\"Samstag, 18.04.2026, 13:30 - 17:00\",\"Sonntag, 19.04.2026, 09:30 - 11.30\",\"Samstag, 25.04.2026, 08:00 - 12:00\"]'),
(21, 2, 'Bemerkungen', 'text', 0, NULL, 2, NULL),
(22, 3, 'Nimmst du am Mittagessen teil?', 'radio', 1, NULL, 0, '[\"Ja\",\"Nein\"]');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mitglied_id` int(11) DEFAULT NULL COMMENT 'Verknuepfung zu mitglieder.ID',
  `role` enum('admin','vorstand','mitglied') NOT NULL DEFAULT 'mitglied',
  `status` enum('pending','approved','rejected','disabled') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `calendar_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `users`
--

INSERT INTO `users` (`id`, `username`, `full_name`, `password_hash`, `email`, `mitglied_id`, `role`, `status`, `created_at`, `approved_at`, `approved_by`, `calendar_token`) VALUES
(1, 'Renato', 'Renato Linggi', '$2y$10$8Xxz4XxZJwHWuT7V0gqi3.r8xPFyDagOKO7ceHhdNRAOUOCSVpB3e', 'renato.linggi@msvwilen.ch', 112101, 'admin', 'approved', '2026-02-12 00:31:24', NULL, NULL, '88694df0d3d2fcb2c538970c8074dbe6b8c444fc6b23413d44c641ef74cea412'),
(3, 'Roger', 'Roger Cavelti', '$2y$10$p8QvmahOEis1CEUyqhvTlevstbgCM1PrcpBNmWQVWPUU1vnhwptva', 'roger.cavelti@msvwilen.ch', 112108, 'vorstand', 'approved', '2026-02-12 00:31:24', NULL, NULL, 'f72a5c270cc0083493b8c5330fa287d9eefdb1ea004ef13c943eaf282264cfae'),
(4, 'Marco', 'Marco Schober', '$2y$10$SB3CDKDRcDuU8JzPemBuZOnYMPh95Cx2ivBOv1YT9zc1d1zkLVwSW', 'marco.schober@msvwilen.ch', 112103, 'vorstand', 'approved', '2026-02-12 00:31:24', NULL, NULL, NULL),
(5, 'Karin', '', '$2y$10$paPjdbxkkcb2MoMwnPDToenaWtMw/Exm6qHGjruES1ehdgo3yC9Y2', 'karin.s.cavelti@gmx.com', NULL, 'vorstand', 'approved', '2026-02-12 00:31:24', NULL, NULL, NULL),
(11, 'debug', 'debug', '$2y$10$G6lowG6B60tCe8/QRDZQJuzjFTm0WqUf6WB/yYsC/9ximHsQWQ8J2', 'renato.linggi@gmail.com', NULL, 'mitglied', 'approved', '2026-02-19 15:38:05', NULL, NULL, NULL),
(12, 'Alex', 'Alexander von Euw', '$2y$10$YWKwYTzfDRc/yq2DShKIGeULldw.fxBCFqivOV9qwmjNBGy9SPFmm', 'alex@voneuw-architektur.ch', 112140, 'mitglied', 'approved', '2026-02-22 01:32:33', '2026-02-22 01:35:43', 1, NULL),
(13, 'ae.linggi@hispeed.ch', 'Andreas Linggi', '$2y$10$JF8DUSPUvOeHL38jnzGOCuvziUW.8R0kHzcjPpv8Vm4fBzq1Um/pq', 'ae.linggi@hispeed.ch', 112097, 'mitglied', 'approved', '2026-02-27 19:59:36', NULL, NULL, NULL),
(14, 'Marcus.Koenig', 'Marcus König', '$2y$10$CLcoT/d2IoSD9p7N70vcQ.VBMJusTIu1eoRUT/VZYfN2zXb7LpzPW', 'mail@marcus-koenig.ch', 889594, 'mitglied', 'approved', '2026-03-06 21:38:39', NULL, NULL, NULL),
(15, 'iweinem@me.com', 'Ingo Weinem', '$2y$10$abTTDov5FQ9G6gBtYyrHee68RHmAdSVQigcSknm5yCWhz35kv6Yeq', 'iweinem@me.com', 29093, 'mitglied', 'approved', '2026-03-06 22:07:45', NULL, NULL, 'c1c644ca9fc33ec094314225f8dbf25ea9392e2b03467ac893ec6b8bc17618ba'),
(16, 'Ju73', 'Judith von Euw', '$2y$10$KphdDEl9XeHFE2G/.VTR..I/sFdvc/lLl4udo6RUOFe1dCnjRoHhO', 'j.voneuw@hispeed.ch', 112111, 'mitglied', 'approved', '2026-03-06 22:09:53', NULL, NULL, NULL),
(17, 'Chrigi', 'Christian von Euw', '$2y$10$V/2Ue8M.HdPf7Y5o6FJHm.nAonzlexIEU3VDKI8NlOL95OE1Bqhti', 'christian-voneuw@hotmail.com', 112141, 'mitglied', 'approved', '2026-03-06 22:10:48', NULL, NULL, 'c4c0967da9b6ad54eefc9241bbb6c990c7ddd8bd226162fe3972a30a5c1ab882'),
(18, 'LIRO', 'Roman Lienert', '$2y$10$ra/KWTAO/TyEZ4xlgcYTKOQ4mPvh1YwTCQq/EXFPTigNIUfiMWIYy', 'roman.lienert@gmail.com', 112131, 'mitglied', 'approved', '2026-03-06 22:11:15', NULL, NULL, NULL),
(19, 'Mark', 'Mark Unterkofler', '$2y$10$bfqVQBsn/hgTlu5OaLUzpu6I/NI4pAB/fkBRP3C1/xwdexkJO2LIe', 'mark@unterkofler.ch', 385067, 'mitglied', 'approved', '2026-03-06 22:13:56', NULL, NULL, '5411263656d0603250f38dc19057036b844f89704ad392753829ed777748ab41'),
(20, 'Seibel75', 'Stefan von Euw', '$2y$10$FhrSOlxQywf5nLGSYwFfnON6KEspuDMuXWTJG9LEClO9xhHKNH1OG', 'stefan.voneuw@gmail.com', 112144, 'mitglied', 'approved', '2026-03-06 22:16:03', '2026-03-06 22:22:23', 1, NULL),
(21, 'Michi', 'Michael Fuchs', '$2y$10$iO5ciqvn1xpDByHYBIxRZ.HhEpgVVqLUnSIwgCW70LFr4MKWi9oj.', 'fuchs.michael@gmx.ch', 112114, 'mitglied', 'approved', '2026-03-06 22:28:07', NULL, NULL, NULL),
(22, 'Norbi', 'Norbert Kälin', '$2y$10$Er/oLdVh7xIQbNg/ZB9mlu9o2XjHkSuXOOk.lnQ7J.wKfIzQgvzLy', 'norbi.kaelin@gmx.ch', 112126, 'mitglied', 'approved', '2026-03-06 22:32:37', NULL, NULL, 'b37573d22e8909d20711f34b8323f9b3d824fa72fa881c9d8abbe69bbc5fa9c8'),
(23, 'Pauli', 'Paul Sigrist', '$2y$10$3hbnk1rZU92CK3sge/s/9up0PAM4wkGAfZu3QZU/dBZxfwdwZ51Ou', 'e.p.sigrist@bluewin.ch', 112137, 'mitglied', 'approved', '2026-03-06 22:34:22', NULL, NULL, NULL),
(25, 'HPS', 'Hanspeter Schober', '$2y$10$D9YwmUZvoC1EzKzIhq6fUeZqSuTkUyhTCZz2eAz/WhTS88vLTdicC', 'hpschober@bluewin.ch', 112102, 'mitglied', 'approved', '2026-03-07 11:50:25', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `vorstand_dokumente`
--

CREATE TABLE `vorstand_dokumente` (
  `id` int(11) NOT NULL,
  `typ` enum('einsatzplan','protokoll') NOT NULL,
  `titel` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `dateiname` varchar(255) NOT NULL,
  `dateipfad` varchar(500) NOT NULL,
  `dateigroesse` int(11) DEFAULT NULL,
  `hochgeladen_von` int(11) NOT NULL,
  `hochgeladen_am` datetime DEFAULT current_timestamp(),
  `sichtbar_fuer` enum('admin','vorstand','alle_mitglieder') NOT NULL DEFAULT 'vorstand',
  `datum` date DEFAULT NULL,
  `jahr` year(4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `vorstand_dokumente`
--

INSERT INTO `vorstand_dokumente` (`id`, `typ`, `titel`, `beschreibung`, `dateiname`, `dateipfad`, `dateigroesse`, `hochgeladen_von`, `hochgeladen_am`, `sichtbar_fuer`, `datum`, `jahr`) VALUES
(20, 'protokoll', 'GV Protokoll 2025', NULL, 'MSVWilen_GV_2025_Protokoll_mf.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/protokoll/1771860219_MSVWilen_GV_2025_Protokoll_mf.pdf', 193908, 1, '2026-02-23 16:23:39', 'alle_mitglieder', '2026-02-23', '2026'),
(21, 'protokoll', 'Jahresbericht 2025', NULL, 'Jahresbericht 2026.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/protokoll/1771860232_Jahresbericht_2026.pdf', 2428369, 1, '2026-02-23 16:23:52', 'alle_mitglieder', '2026-02-23', '2026'),
(22, 'einsatzplan', 'Obligatorisch 2026', NULL, 'Obligatorisch 2026.docx', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860289_Obligatorisch_2026.docx', 26708, 1, '2026-02-23 16:24:49', 'admin', '2026-02-23', '2026'),
(23, 'einsatzplan', 'Obligatorisch 2026', NULL, 'Obligatorisch 2026.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860300_Obligatorisch_2026.pdf', 66201, 1, '2026-02-23 16:25:00', 'alle_mitglieder', '2026-02-23', '2026'),
(24, 'einsatzplan', 'Feldschiessen 2026', NULL, 'Feldschiessen 2026.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860309_Feldschiessen_2026.pdf', 65073, 1, '2026-02-23 16:25:09', 'alle_mitglieder', '2026-02-23', '2026'),
(25, 'einsatzplan', 'Feldschiessen 2026', NULL, 'Feldschiessen 2026.docx', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860317_Feldschiessen_2026.docx', 24176, 1, '2026-02-23 16:25:17', 'admin', '2026-02-23', '2026'),
(26, 'einsatzplan', 'Wyler Chilbi 2026', NULL, 'Einsatzplan2026.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860330_Einsatzplan2026.pdf', 43805, 1, '2026-02-23 16:25:30', 'alle_mitglieder', '2026-02-23', '2026'),
(27, 'einsatzplan', 'Wyler Chilbi 2026', NULL, 'Einsatzplan2026.xlsx', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1771860347_Einsatzplan2026.xlsx', 12773, 1, '2026-02-23 16:25:47', 'admin', '2026-02-23', '2026'),
(28, 'einsatzplan', 'Rangeure Eidgenössisches Schützenfest GR 2026', NULL, 'ESF2026_MAIN_1.05.0.02.079_100119.pdf', '/home/bdebbd4/www/jahresmeisterschaft.msvwilen.ch/api/../portal/uploads/dokumente/einsatzplan/1772110382_ESF2026_MAIN_1_05_0_02_079_100119.pdf', 129776, 1, '2026-02-26 13:53:02', 'alle_mitglieder', '2026-02-26', '2026');

-- --------------------------------------------------------

--
-- Stellvertreter-Struktur des Views `v_cup_winners`
-- (Siehe unten für die tatsächliche Ansicht)
--
CREATE TABLE `v_cup_winners` (
`pair_id` int(11)
,`Round` int(11)
,`Year` int(4)
,`winner_id` int(11)
,`winner_type` varchar(7)
,`ManualWinnerReason` varchar(255)
);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Waffen`
--

CREATE TABLE `Waffen` (
  `ID` int(11) NOT NULL,
  `Bezeichnung` varchar(255) NOT NULL,
  `Kategorie` varchar(6) NOT NULL,
  `Kranz_Endstich` int(2) NOT NULL,
  `Kranz_Kunst` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `Waffen`
--

INSERT INTO `Waffen` (`ID`, `Bezeichnung`, `Kategorie`, `Kranz_Endstich`, `Kranz_Kunst`) VALUES
(1, 'Standardgewehr', 'Kat. A', 89, 421),
(2, 'Stgw90', 'Kat. B', 84, 401),
(3, 'Karabiner', 'Kat. B', 84, 401),
(4, 'Stgw57 /03', 'Kat. B', 85, 401);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wanderpreise`
--

CREATE TABLE `wanderpreise` (
  `id` int(11) NOT NULL,
  `bezeichnung` varchar(255) NOT NULL,
  `beschreibung` text DEFAULT NULL,
  `beschaffung_datum` int(4) NOT NULL COMMENT 'Anschaffungsjahr',
  `min_anzahl_gewinne` int(11) DEFAULT 3,
  `gewinner_id` int(11) DEFAULT NULL,
  `bild_pfad` varchar(255) DEFAULT NULL,
  `hersteller` varchar(100) DEFAULT NULL,
  `auto_verknuepfung` tinyint(1) DEFAULT 0,
  `verknuepfung_regel` varchar(50) DEFAULT NULL,
  `verknuepfung_jahr` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `wanderpreise`
--

INSERT INTO `wanderpreise` (`id`, `bezeichnung`, `beschreibung`, `beschaffung_datum`, `min_anzahl_gewinne`, `gewinner_id`, `bild_pfad`, `hersteller`, `auto_verknuepfung`, `verknuepfung_regel`, `verknuepfung_jahr`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'Glückstich', 'Schieferuhr', 2021, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'glueckstich', 0, '2025-08-27 13:07:40', 1, '2025-08-29 17:59:51', 1),
(4, 'Kunststich', 'Holzpreis Murmeli', 2019, 10, NULL, NULL, 'Schnitzerei Heinz Schild', 1, 'kunststich', 0, '2025-08-29 11:23:28', 1, '2025-08-29 18:00:27', NULL),
(5, 'Heimmeisterschaft Kat. A', 'Eule', 2016, 10, NULL, NULL, 'Schnitzerei Heinz Schild', 1, NULL, 0, '2025-08-29 12:18:51', NULL, '2025-10-16 08:49:03', NULL),
(7, 'Kantonalstich Kat. A', 'Holzpreis Luchs', 2018, 10, NULL, NULL, 'Schnitzerei Heinz Schild', 1, NULL, 0, '2025-08-29 12:25:10', NULL, '2025-10-16 08:49:25', NULL),
(9, 'Jahresmeisterschaft Kat. A', 'Zinnkanne', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'jahresmeisterschaftA', 0, '2025-08-29 15:57:20', NULL, '2025-08-29 19:16:46', NULL),
(10, 'Kantonalstich Kat. B', '', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'kantonalstichB', 0, '2025-08-29 18:00:53', NULL, '2025-08-29 19:16:58', NULL),
(11, 'Endschiessen Kat. A', '', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'endschiessenA', 0, '2025-08-29 18:01:29', NULL, '2025-08-29 19:16:21', NULL),
(12, 'Endschiessen Ordonanz', '', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'endschiessenB', 0, '2025-08-29 18:01:49', NULL, '2025-08-29 19:16:29', NULL),
(13, 'Jahresmeisterschaft Kat. B', '', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'jahresmeisterschaftB', 0, '2025-08-29 18:02:23', NULL, '2025-08-29 19:16:52', NULL),
(14, 'Heimmeisterschaft Kat. B', '', 2025, 10, NULL, NULL, 'Akura Einsiedeln', 1, 'heimmeisterschaftB', 0, '2025-08-29 18:02:56', NULL, '2025-08-29 19:16:38', NULL),
(15, 'MSV Wilen Vereinscup', 'Kristall', 2025, 10, NULL, NULL, 'MSV Wilen', 1, NULL, 0, '2025-08-29 18:03:41', NULL, '2025-10-13 07:31:24', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wanderpreise_gewinner`
--

CREATE TABLE `wanderpreise_gewinner` (
  `id` int(11) NOT NULL,
  `wanderpreis_id` int(11) NOT NULL,
  `gewinner_id` int(11) NOT NULL,
  `jahr` int(4) NOT NULL,
  `rang` varchar(50) DEFAULT NULL,
  `resultat` varchar(100) DEFAULT NULL,
  `bemerkung` text DEFAULT NULL,
  `ist_definitiv` tinyint(1) DEFAULT 0 COMMENT 'Hat mindestens X-mal gewonnen für definitiven Besitz',
  `anzahl_gewinne` int(11) DEFAULT 1 COMMENT 'Anzahl bisheriger Gewinne dieses Mitglieds für diesen Wanderpreis',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `wanderpreise_gewinner`
--

INSERT INTO `wanderpreise_gewinner` (`id`, `wanderpreis_id`, `gewinner_id`, `jahr`, `rang`, `resultat`, `bemerkung`, `ist_definitiv`, `anzahl_gewinne`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(4, 1, 112144, 2024, '', '', '', 0, 1, '2025-08-28 21:46:26', NULL, '2025-09-08 07:43:46', NULL),
(15, 10, 112108, 2024, '', '', '', 0, 1, '2025-08-29 18:11:05', NULL, '2025-08-29 18:11:05', NULL),
(16, 12, 112137, 2024, '', '', '', 0, 1, '2025-08-29 18:12:06', NULL, '2025-08-29 18:12:06', NULL),
(17, 9, 112131, 2024, '', '', '', 0, 2, '2025-08-29 18:12:42', NULL, '2025-08-29 18:12:42', NULL),
(18, 13, 112108, 2024, '', '', '', 0, 1, '2025-08-29 18:13:06', NULL, '2025-08-29 18:13:06', NULL),
(19, 14, 112108, 2024, '', '', '', 0, 1, '2025-08-29 18:13:29', NULL, '2025-08-29 18:13:29', NULL),
(20, 15, 889594, 2024, '', '', '', 0, 1, '2025-08-29 18:14:18', NULL, '2025-08-29 18:14:18', NULL),
(21, 7, 112140, 2024, '', '', '', 0, 1, '2025-08-29 18:16:02', NULL, '2025-08-29 18:16:02', NULL),
(22, 5, 889594, 2024, '', '', '', 0, 1, '2025-08-29 18:16:16', NULL, '2025-08-29 18:16:16', NULL),
(23, 4, 112144, 2024, '', '', '', 0, 1, '2025-08-29 18:16:41', NULL, '2025-08-29 18:16:41', NULL),
(26, 11, 112131, 2024, '', '', '', 0, 2, '2025-08-29 19:01:03', NULL, '2025-08-29 19:10:55', NULL),
(29, 11, 112131, 2023, '', '', '', 0, 2, '2025-08-29 19:10:39', NULL, '2025-08-29 19:10:39', NULL),
(36, 1, 112131, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(37, 4, 385067, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(38, 5, 112131, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(39, 7, 112131, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(40, 9, 112131, 2025, '', '', '', 0, 2, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(41, 10, 112108, 2025, '', '', '', 0, 2, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(42, 11, 112131, 2025, '', '', '', 0, 3, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(43, 12, 385067, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(44, 13, 385067, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(45, 14, 112111, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL),
(46, 15, 385067, 2025, '', '', '', 0, 1, '2025-10-13 06:44:24', NULL, '2025-10-13 06:44:24', NULL);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wanderpreise_regeln`
--

CREATE TABLE `wanderpreise_regeln` (
  `id` int(11) NOT NULL,
  `regel_code` varchar(50) NOT NULL,
  `regel_name` varchar(255) DEFAULT NULL,
  `regel_beschreibung` text DEFAULT NULL,
  `sql_query` text DEFAULT NULL,
  `aktiv` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `wanderpreise_regeln`
--

INSERT INTO `wanderpreise_regeln` (`id`, `regel_code`, `regel_name`, `regel_beschreibung`, `sql_query`, `aktiv`) VALUES
(1, 'glueckstich', 'Glückstich', '', 'SELECT\r\n            m.ID as  gewinner_id,\r\n            m.Name,\r\n            m.Vorname,\r\n            m.Geburtsdatum,\r\n            g.GSchuss1, g.GSchuss2, g.GSchuss3,\r\n            GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS MaxGlueck,\r\n            LEAST(\r\n                GREATEST(g.GSchuss1, g.GSchuss2),\r\n                GREATEST(g.GSchuss1, g.GSchuss3),\r\n                GREATEST(g.GSchuss2, g.GSchuss3)\r\n            ) AS ZweitHoechster,\r\n            LEAST(g.GSchuss1, g.GSchuss2, g.GSchuss3) AS DrittHoechster\r\n        FROM mitglieder m\r\n        LEFT JOIN glueck g ON m.ID = g.MitgliedID\r\n        LEFT JOIN Waffen w ON w.ID = m.WaffenID\r\n        WHERE g.GSchuss1 != 0 AND g.Jahr = {jahr}\r\n        GROUP BY m.ID\r\n        ORDER BY MaxGlueck DESC, ZweitHoechster DESC, DrittHoechster DESC, m.Geburtsdatum ASC', 1),
(2, 'kunststich', 'Kunst', '', 'SELECT\r\n            m.ID as gewinner_id,\r\n            m.Name,\r\n            m.Vorname,\r\n            m.Geburtsdatum,\r\n            k.KSchuss1, k.KSchuss2, k.KSchuss3, k.KSchuss4, k.KSchuss5,\r\n            w.Kranz_Kunst,\r\n            COALESCE(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5, 0) AS Kunst_Summe,\r\n            GREATEST(\r\n                COALESCE(k.KSchuss1, 0),\r\n                COALESCE(k.KSchuss2, 0),\r\n                COALESCE(k.KSchuss3, 0),\r\n                COALESCE(k.KSchuss4, 0),\r\n                COALESCE(k.KSchuss5, 0)\r\n            ) AS TS\r\n        FROM mitglieder m\r\n        LEFT JOIN kunst k ON m.ID = k.MitgliedID\r\n        LEFT JOIN Waffen w ON w.ID = m.WaffenID\r\n        WHERE k.KSchuss1 IS NOT NULL AND k.KSchuss1 != 0 AND k.Jahr = {jahr}\r\n \r\n        GROUP BY m.ID\r\n        ORDER BY Kunst_Summe DESC, TS DESC', 1),
(3, 'heimmeisterschaftA', 'Heimmeisterschaft Kat. A', '', 'SELECT \r\n		m.ID as gewinner_id,\r\n        w.Kategorie,\r\n        m.Name, \r\n        m.Vorname, \r\n        h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,\r\n        (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + \r\n         COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme\r\n    FROM heimresultate h\r\n    INNER JOIN mitglieder m ON m.ID = h.MitgliedID\r\n    INNER JOIN Waffen w ON w.ID = m.WaffenID \r\n    WHERE w.Kategorie = \'Kat. A\'\r\n      AND h.Jahr = {jahr}\r\n      AND (h.Passe1 > 0 OR h.Passe2 > 0 OR h.Passe3 > 0 OR h.Passe4 > 0 OR \r\n           h.Passe5 > 0 OR h.Passe6 > 0 OR h.Passe7 > 0 OR h.Passe8 > 0)\r\n    ORDER BY w.Kategorie, HeimSumme DESC, m.Name, m.Vorname', 1),
(4, 'heimmeisterschaftB', 'Heimmeisterschaft Kat. B', '', 'SELECT \r\n		m.ID as gewinner_id,\r\n        w.Kategorie,\r\n        m.Name, \r\n        m.Vorname, \r\n        h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,\r\n        (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) + \r\n         COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme\r\n    FROM heimresultate h\r\n    INNER JOIN mitglieder m ON m.ID = h.MitgliedID\r\n    INNER JOIN Waffen w ON w.ID = m.WaffenID \r\n    WHERE w.Kategorie = \'Kat. B\'\r\n      AND h.Jahr = {jahr}\r\n      AND (h.Passe1 > 0 OR h.Passe2 > 0 OR h.Passe3 > 0 OR h.Passe4 > 0 OR \r\n           h.Passe5 > 0 OR h.Passe6 > 0 OR h.Passe7 > 0 OR h.Passe8 > 0)\r\n    ORDER BY w.Kategorie, HeimSumme DESC, m.Name, m.Vorname', 1),
(5, 'kantonalstichA', 'Kantonalstich Kat. A', '', 'SELECT \r\n		m.ID as gewinner_id,\r\n        w.Kategorie,\r\n        m.Name, \r\n        m.Vorname, \r\n        k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,\r\n        (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + \r\n         COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantiSumme\r\n    FROM kantiresultate k\r\n    INNER JOIN mitglieder m ON m.ID = k.MitgliedID\r\n    INNER JOIN Waffen w ON w.ID = m.WaffenID \r\n    WHERE w.Kategorie = \'Kat. A\'\r\n      AND k.Jahr = {jahr}\r\n      AND (k.Passe1 > 0 OR k.Passe2 > 0 OR k.Passe3 > 0 OR k.Passe4 > 0 OR k.Passe5 > 0)\r\n    ORDER BY w.Kategorie, KantiSumme DESC, m.Name, m.Vorname', 1),
(6, 'kantonalstichB', 'Kantonalstich Kat. B', '', 'SELECT \r\n		m.ID as gewinner_id,\r\n        w.Kategorie,\r\n        m.Name, \r\n        m.Vorname, \r\n        k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5,\r\n        (COALESCE(k.Passe1, 0) + COALESCE(k.Passe2, 0) + COALESCE(k.Passe3, 0) + \r\n         COALESCE(k.Passe4, 0) + COALESCE(k.Passe5, 0)) AS KantiSumme\r\n    FROM kantiresultate k\r\n    INNER JOIN mitglieder m ON m.ID = k.MitgliedID\r\n    INNER JOIN Waffen w ON w.ID = m.WaffenID \r\n    WHERE w.Kategorie = \'Kat. B\'\r\n      AND k.Jahr = {jahr}\r\n      AND (k.Passe1 > 0 OR k.Passe2 > 0 OR k.Passe3 > 0 OR k.Passe4 > 0 OR k.Passe5 > 0)\r\n    ORDER BY w.Kategorie, KantiSumme DESC, m.Name, m.Vorname', 1),
(7, 'endschiessenA', 'Endschiessen Kat. A', '', 'SELECT\r\n  m.ID as gewinner_id,\r\n  m.Name,\r\n  m.Vorname,\r\n  m.Geburtsdatum,\r\n  COALESCE(zabig.ZabigTotal, 0)       AS ZabigTotal,\r\n  COALESCE(glueck.GlueckTotal, 0)     AS GlueckTotal,\r\n  COALESCE(endstich.EndstichTotal, 0) AS EndstichTotal,\r\n  COALESCE(kunst.KunstTotal, 0)       AS KunstTotal,\r\n  COALESCE(schwin.MaxSchwini, 0)      AS MaxSchwini,\r\n  (COALESCE(endstich.EndstichTotal, 0) +\r\n   COALESCE(glueck.GlueckTotal, 0) +\r\n   COALESCE(zabig.ZabigTotal, 0) +\r\n   COALESCE(kunst.KunstTotal, 0) +\r\n   COALESCE(schwin.MaxSchwini, 0))    AS GesamtTotal\r\nFROM mitglieder m\r\nLEFT JOIN Waffen w ON w.ID = m.WaffenID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    SUM(\r\n      COALESCE(Schuss1,0) + COALESCE(Schuss2,0) + COALESCE(Schuss3,0) + COALESCE(Schuss4,0) + COALESCE(Schuss5,0) +\r\n      COALESCE(Schuss6,0) + COALESCE(Schuss7,0) + COALESCE(Schuss8,0) + COALESCE(Schuss9,0) + COALESCE(Schuss10,0)\r\n    ) AS EndstichTotal\r\n  FROM endstich\r\n  WHERE Jahr = {jahr} AND Schuss1 <> 0\r\n  GROUP BY MitgliedID\r\n) endstich ON endstich.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    ROUND(SUM(\r\n      COALESCE(KSchuss1,0) + COALESCE(KSchuss2,0) + COALESCE(KSchuss3,0) + COALESCE(KSchuss4,0) + COALESCE(KSchuss5,0)\r\n    ) / 10, 1) AS KunstTotal\r\n  FROM kunst\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) kunst ON kunst.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    ROUND(GREATEST(\r\n      COALESCE(GSchuss1,0),\r\n      COALESCE(GSchuss2,0),\r\n      COALESCE(GSchuss3,0)\r\n    ) / 10, 1) AS GlueckTotal\r\n  FROM glueck\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) glueck ON glueck.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n      LEAST(10, CEILING(IFNULL(ZSchuss1,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss2,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss3,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss4,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss5,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss6,0)/10)) AS ZabigTotal\r\n  FROM zabig\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) zabig ON zabig.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    GREATEST(\r\n      COALESCE(P1Schuss1,0)+COALESCE(P1Schuss2,0)+COALESCE(P1Schuss3,0)+COALESCE(P1Schuss4,0)+COALESCE(P1Schuss5,0)+COALESCE(P1Schuss6,0),\r\n      COALESCE(P2Schuss1,0)+COALESCE(P2Schuss2,0)+COALESCE(P2Schuss3,0)+COALESCE(P2Schuss4,0)+COALESCE(P2Schuss5,0)+COALESCE(P2Schuss6,0)\r\n    ) AS MaxSchwini\r\n  FROM schwini\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) schwin ON schwin.MitgliedID = m.ID\r\nWHERE w.Kategorie = \'Kat. A\'\r\n  AND (m.Verstorben IS NULL OR m.Verstorben != 1)\r\nHAVING GesamtTotal > 0\r\nORDER BY GesamtTotal DESC, EndstichTotal DESC, m.Geburtsdatum ASC', 1),
(8, 'endschiessenB', 'Endschiessen Kat. B', '', 'SELECT\r\n  m.ID as gewinner_id,\r\n  m.Name,\r\n  m.Vorname,\r\n  m.Geburtsdatum,\r\n  COALESCE(zabig.ZabigTotal, 0)       AS ZabigTotal,\r\n  COALESCE(glueck.GlueckTotal, 0)     AS GlueckTotal,\r\n  COALESCE(endstich.EndstichTotal, 0) AS EndstichTotal,\r\n  COALESCE(kunst.KunstTotal, 0)       AS KunstTotal,\r\n  COALESCE(schwin.MaxSchwini, 0)      AS MaxSchwini,\r\n  (COALESCE(endstich.EndstichTotal, 0) +\r\n   COALESCE(glueck.GlueckTotal, 0) +\r\n   COALESCE(zabig.ZabigTotal, 0) +\r\n   COALESCE(kunst.KunstTotal, 0) +\r\n   COALESCE(schwin.MaxSchwini, 0))    AS GesamtTotal\r\nFROM mitglieder m\r\nLEFT JOIN Waffen w ON w.ID = m.WaffenID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    SUM(\r\n      COALESCE(Schuss1,0) + COALESCE(Schuss2,0) + COALESCE(Schuss3,0) + COALESCE(Schuss4,0) + COALESCE(Schuss5,0) +\r\n      COALESCE(Schuss6,0) + COALESCE(Schuss7,0) + COALESCE(Schuss8,0) + COALESCE(Schuss9,0) + COALESCE(Schuss10,0)\r\n    ) AS EndstichTotal\r\n  FROM endstich\r\n  WHERE Jahr = {jahr} AND Schuss1 <> 0\r\n  GROUP BY MitgliedID\r\n) endstich ON endstich.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    ROUND(SUM(\r\n      COALESCE(KSchuss1,0) + COALESCE(KSchuss2,0) + COALESCE(KSchuss3,0) + COALESCE(KSchuss4,0) + COALESCE(KSchuss5,0)\r\n    ) / 10, 1) AS KunstTotal\r\n  FROM kunst\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) kunst ON kunst.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    ROUND(GREATEST(\r\n      COALESCE(GSchuss1,0),\r\n      COALESCE(GSchuss2,0),\r\n      COALESCE(GSchuss3,0)\r\n    ) / 10, 1) AS GlueckTotal\r\n  FROM glueck\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) glueck ON glueck.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n      LEAST(10, CEILING(IFNULL(ZSchuss1,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss2,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss3,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss4,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss5,0)/10)) +\r\n      LEAST(10, CEILING(IFNULL(ZSchuss6,0)/10)) AS ZabigTotal\r\n  FROM zabig\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) zabig ON zabig.MitgliedID = m.ID\r\nLEFT JOIN (\r\n  SELECT \r\n    MitgliedID,\r\n    GREATEST(\r\n      COALESCE(P1Schuss1,0)+COALESCE(P1Schuss2,0)+COALESCE(P1Schuss3,0)+COALESCE(P1Schuss4,0)+COALESCE(P1Schuss5,0)+COALESCE(P1Schuss6,0),\r\n      COALESCE(P2Schuss1,0)+COALESCE(P2Schuss2,0)+COALESCE(P2Schuss3,0)+COALESCE(P2Schuss4,0)+COALESCE(P2Schuss5,0)+COALESCE(P2Schuss6,0)\r\n    ) AS MaxSchwini\r\n  FROM schwini\r\n  WHERE Jahr = {jahr}\r\n  GROUP BY MitgliedID\r\n) schwin ON schwin.MitgliedID = m.ID\r\nWHERE w.Kategorie = \'Kat. B\'\r\n  AND (m.Verstorben IS NULL OR m.Verstorben != 1)\r\nHAVING GesamtTotal > 0\r\nORDER BY GesamtTotal DESC, EndstichTotal DESC, m.Geburtsdatum ASC', 1),
(9, 'jahresmeisterschaftA', 'Jahresmeisterschaft Kat. A', '', '-- Parameter\r\nSET @year = {jahr};\r\nSET @kategorie = \'Kat. A\'; -- oder \'Kat. B\', oder \'\' für alle\r\n\r\n-- Haupt-Query mit CTEs\r\nWITH \r\n-- Wettbewerbe laden\r\ndefinitions AS (\r\n    SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge\r\n    FROM JMDefinition \r\n    WHERE year = @year AND Erweitert = 0 AND Info = 0\r\n),\r\n\r\n-- Mitglieder laden\r\nmembers AS (\r\n    SELECT m.ID, m.Vorname, m.Name \r\n    FROM mitglieder m\r\n    JOIN Waffen w ON w.ID = m.WaffenID\r\n    WHERE m.Status = 1 \r\n    AND (m.Verstorben IS NULL OR m.Verstorben != 1)\r\n    AND (@kategorie = \'\' OR w.Kategorie = @kategorie)\r\n),\r\n\r\n-- Normale Resultate sammeln\r\nnormal_results AS (\r\n    SELECT \r\n        jr.mitgliederID,\r\n        jr.jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Bezeichnung IN (\'Einzelwettschiessen\', \'Obligatorisch\', \'Feldschiessen\') THEN jr.Punkte\r\n            WHEN jd.Maxpunkte > 0 THEN ROUND((jr.Punkte * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE jr.Punkte\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM jmresultate jr\r\n    JOIN definitions jd ON jd.ID = jr.jmdefinitionID\r\n    WHERE jr.mitgliederID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Endstich Resultate\r\nendstich_results AS (\r\n    SELECT \r\n        e.MitgliedID as mitgliederID,\r\n        jd.ID as jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Maxpunkte > 0 THEN ROUND(((COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +\r\n                COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +\r\n                COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +\r\n                COALESCE(e.Schuss10,0)) * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE (COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +\r\n                COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +\r\n                COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +\r\n                COALESCE(e.Schuss10,0))\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM endstich e\r\n    CROSS JOIN definitions jd \r\n    WHERE jd.Bezeichnung = \'Endstich\'\r\n    AND e.Jahr = @year \r\n    AND e.MitgliedID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Kantonalstich Resultate (Bester)\r\nkanti_results AS (\r\n    SELECT \r\n        k.MitgliedID as mitgliederID,\r\n        jd.ID as jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Maxpunkte > 0 THEN ROUND((GREATEST(\r\n                COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),\r\n                COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)\r\n            ) * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE GREATEST(\r\n                COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),\r\n                COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)\r\n            )\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM kantiresultate k\r\n    CROSS JOIN definitions jd \r\n    WHERE jd.Bezeichnung = \'Bester Kantonalstich\'\r\n    AND k.Jahr = @year \r\n    AND k.MitgliedID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Alle Resultate zusammenführen\r\nall_results AS (\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM normal_results\r\n    UNION ALL\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM endstich_results\r\n    UNION ALL\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM kanti_results\r\n),\r\n\r\n-- Aktive Streicher-Wettbewerbe ermitteln\r\nactive_streicher AS (\r\n    SELECT DISTINCT jmdefinitionID\r\n    FROM all_results\r\n    WHERE streicher_flag = 1\r\n),\r\n\r\n-- Nicht-Teilnahmen als 0-Punkte hinzufügen\r\nresults_with_zeros AS (\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM all_results\r\n    UNION ALL\r\n    SELECT \r\n        m.ID as mitgliederID,\r\n        as_id.jmdefinitionID,\r\n        0 as scaled_points,\r\n        1 as streicher_flag\r\n    FROM members m\r\n    CROSS JOIN active_streicher as_id\r\n    WHERE NOT EXISTS (\r\n        SELECT 1 FROM all_results ar \r\n        WHERE ar.mitgliederID = m.ID \r\n        AND ar.jmdefinitionID = as_id.jmdefinitionID\r\n    )\r\n),\r\n\r\n-- Sektionsmeisterschaft filtern\r\nsm_filtered AS (\r\n    SELECT \r\n        r.mitgliederID,\r\n        r.jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Bezeichnung = \'Sektionsmeisterschaft\' THEN MAX(r.scaled_points)\r\n            ELSE r.scaled_points\r\n        END as scaled_points,\r\n        r.streicher_flag\r\n    FROM results_with_zeros r\r\n    JOIN definitions jd ON jd.ID = r.jmdefinitionID\r\n    GROUP BY r.mitgliederID, r.jmdefinitionID, \r\n        CASE WHEN jd.Bezeichnung = \'Sektionsmeisterschaft\' THEN 1 ELSE r.scaled_points END,\r\n        r.streicher_flag\r\n),\r\n\r\n-- Streicher berechnen\r\nstreicher_calc AS (\r\n    SELECT \r\n        mitgliederID,\r\n        jmdefinitionID,\r\n        scaled_points,\r\n        streicher_flag,\r\n        CASE \r\n            WHEN streicher_flag = 1 THEN \r\n                ROW_NUMBER() OVER (PARTITION BY mitgliederID, streicher_flag ORDER BY scaled_points ASC, jmdefinitionID)\r\n            ELSE NULL\r\n        END as streicher_rank\r\n    FROM sm_filtered\r\n),\r\n\r\n-- Finale Berechnung\r\nfinal_scores AS (\r\n    SELECT \r\n        m.ID,\r\n        m.Name,\r\n        m.Vorname,\r\n        SUM(CASE WHEN s.streicher_flag = 0 THEN s.scaled_points ELSE 0 END) as sumStreicher0,\r\n        SUM(CASE WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points ELSE 0 END) as sumStreicher1,\r\n        SUM(CASE \r\n            WHEN s.streicher_flag = 0 THEN s.scaled_points \r\n            WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points \r\n            ELSE 0 \r\n        END) as sumTotal\r\n    FROM members m\r\n    LEFT JOIN streicher_calc s ON m.ID = s.mitgliederID\r\n    GROUP BY m.ID, m.Name, m.Vorname\r\n    HAVING sumTotal > 0\r\n)\r\n\r\n-- Endergebnis mit Rangierung\r\nSELECT \r\n    ID as gewinner_id,\r\n    DENSE_RANK() OVER (ORDER BY sumTotal DESC) as Rang,\r\n    Name,\r\n    Vorname,\r\n    ROUND(sumStreicher0, 2) as Punkte_Fix,\r\n    ROUND(sumStreicher1, 2) as Punkte_Streicher,\r\n    ROUND(sumTotal, 2) as Total\r\nFROM final_scores\r\nORDER BY sumTotal DESC, Name ASC;', 1),
(10, 'jahresmeisterschaftB', 'Jahresmeisterschaft Kat. B', '', '-- Parameter\r\nSET @year = {jahr};\r\nSET @kategorie = \'Kat. B\'; -- oder \'Kat. B\', oder \'\' für alle\r\n\r\n-- Haupt-Query mit CTEs\r\nWITH \r\n-- Wettbewerbe laden\r\ndefinitions AS (\r\n    SELECT ID, Bezeichnung, Maxpunkte, Streicher, Reihenfolge\r\n    FROM JMDefinition \r\n    WHERE year = @year AND Erweitert = 0 AND Info = 0\r\n),\r\n\r\n-- Mitglieder laden\r\nmembers AS (\r\n    SELECT m.ID, m.Vorname, m.Name \r\n    FROM mitglieder m\r\n    JOIN Waffen w ON w.ID = m.WaffenID\r\n    WHERE m.Status = 1 \r\n    AND (m.Verstorben IS NULL OR m.Verstorben != 1)\r\n    AND (@kategorie = \'\' OR w.Kategorie = @kategorie)\r\n),\r\n\r\n-- Normale Resultate sammeln\r\nnormal_results AS (\r\n    SELECT \r\n        jr.mitgliederID,\r\n        jr.jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Bezeichnung IN (\'Einzelwettschiessen\', \'Obligatorisch\', \'Feldschiessen\') THEN jr.Punkte\r\n            WHEN jd.Maxpunkte > 0 THEN ROUND((jr.Punkte * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE jr.Punkte\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM jmresultate jr\r\n    JOIN definitions jd ON jd.ID = jr.jmdefinitionID\r\n    WHERE jr.mitgliederID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Endstich Resultate\r\nendstich_results AS (\r\n    SELECT \r\n        e.MitgliedID as mitgliederID,\r\n        jd.ID as jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Maxpunkte > 0 THEN ROUND(((COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +\r\n                COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +\r\n                COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +\r\n                COALESCE(e.Schuss10,0)) * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE (COALESCE(e.Schuss1,0) + COALESCE(e.Schuss2,0) + COALESCE(e.Schuss3,0) +\r\n                COALESCE(e.Schuss4,0) + COALESCE(e.Schuss5,0) + COALESCE(e.Schuss6,0) +\r\n                COALESCE(e.Schuss7,0) + COALESCE(e.Schuss8,0) + COALESCE(e.Schuss9,0) +\r\n                COALESCE(e.Schuss10,0))\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM endstich e\r\n    CROSS JOIN definitions jd \r\n    WHERE jd.Bezeichnung = \'Endstich\'\r\n    AND e.Jahr = @year \r\n    AND e.MitgliedID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Kantonalstich Resultate (Bester)\r\nkanti_results AS (\r\n    SELECT \r\n        k.MitgliedID as mitgliederID,\r\n        jd.ID as jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Maxpunkte > 0 THEN ROUND((GREATEST(\r\n                COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),\r\n                COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)\r\n            ) * 100.0) / jd.Maxpunkte, 2)\r\n            ELSE GREATEST(\r\n                COALESCE(k.Passe1,0), COALESCE(k.Passe2,0), COALESCE(k.Passe3,0),\r\n                COALESCE(k.Passe4,0), COALESCE(k.Passe5,0)\r\n            )\r\n        END AS scaled_points,\r\n        jd.Streicher AS streicher_flag\r\n    FROM kantiresultate k\r\n    CROSS JOIN definitions jd \r\n    WHERE jd.Bezeichnung = \'Bester Kantonalstich\'\r\n    AND k.Jahr = @year \r\n    AND k.MitgliedID IN (SELECT ID FROM members)\r\n),\r\n\r\n-- Alle Resultate zusammenführen\r\nall_results AS (\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM normal_results\r\n    UNION ALL\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM endstich_results\r\n    UNION ALL\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM kanti_results\r\n),\r\n\r\n-- Aktive Streicher-Wettbewerbe ermitteln\r\nactive_streicher AS (\r\n    SELECT DISTINCT jmdefinitionID\r\n    FROM all_results\r\n    WHERE streicher_flag = 1\r\n),\r\n\r\n-- Nicht-Teilnahmen als 0-Punkte hinzufügen\r\nresults_with_zeros AS (\r\n    SELECT mitgliederID, jmdefinitionID, scaled_points, streicher_flag FROM all_results\r\n    UNION ALL\r\n    SELECT \r\n        m.ID as mitgliederID,\r\n        as_id.jmdefinitionID,\r\n        0 as scaled_points,\r\n        1 as streicher_flag\r\n    FROM members m\r\n    CROSS JOIN active_streicher as_id\r\n    WHERE NOT EXISTS (\r\n        SELECT 1 FROM all_results ar \r\n        WHERE ar.mitgliederID = m.ID \r\n        AND ar.jmdefinitionID = as_id.jmdefinitionID\r\n    )\r\n),\r\n\r\n-- Sektionsmeisterschaft filtern\r\nsm_filtered AS (\r\n    SELECT \r\n        r.mitgliederID,\r\n        r.jmdefinitionID,\r\n        CASE \r\n            WHEN jd.Bezeichnung = \'Sektionsmeisterschaft\' THEN MAX(r.scaled_points)\r\n            ELSE r.scaled_points\r\n        END as scaled_points,\r\n        r.streicher_flag\r\n    FROM results_with_zeros r\r\n    JOIN definitions jd ON jd.ID = r.jmdefinitionID\r\n    GROUP BY r.mitgliederID, r.jmdefinitionID, \r\n        CASE WHEN jd.Bezeichnung = \'Sektionsmeisterschaft\' THEN 1 ELSE r.scaled_points END,\r\n        r.streicher_flag\r\n),\r\n\r\n-- Streicher berechnen\r\nstreicher_calc AS (\r\n    SELECT \r\n        mitgliederID,\r\n        jmdefinitionID,\r\n        scaled_points,\r\n        streicher_flag,\r\n        CASE \r\n            WHEN streicher_flag = 1 THEN \r\n                ROW_NUMBER() OVER (PARTITION BY mitgliederID, streicher_flag ORDER BY scaled_points ASC, jmdefinitionID)\r\n            ELSE NULL\r\n        END as streicher_rank\r\n    FROM sm_filtered\r\n),\r\n\r\n-- Finale Berechnung\r\nfinal_scores AS (\r\n    SELECT \r\n        m.ID,\r\n        m.Name,\r\n        m.Vorname,\r\n        SUM(CASE WHEN s.streicher_flag = 0 THEN s.scaled_points ELSE 0 END) as sumStreicher0,\r\n        SUM(CASE WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points ELSE 0 END) as sumStreicher1,\r\n        SUM(CASE \r\n            WHEN s.streicher_flag = 0 THEN s.scaled_points \r\n            WHEN s.streicher_flag = 1 AND s.streicher_rank > 3 THEN s.scaled_points \r\n            ELSE 0 \r\n        END) as sumTotal\r\n    FROM members m\r\n    LEFT JOIN streicher_calc s ON m.ID = s.mitgliederID\r\n    GROUP BY m.ID, m.Name, m.Vorname\r\n    HAVING sumTotal > 0\r\n)\r\n\r\n-- Endergebnis mit Rangierung\r\nSELECT \r\n    ID as gewinner_id,\r\n    DENSE_RANK() OVER (ORDER BY sumTotal DESC) as Rang,\r\n    Name,\r\n    Vorname,\r\n    ROUND(sumStreicher0, 2) as Punkte_Fix,\r\n    ROUND(sumStreicher1, 2) as Punkte_Streicher,\r\n    ROUND(sumTotal, 2) as Total\r\nFROM final_scores\r\nORDER BY sumTotal DESC, Name ASC;', 1),
(11, 'vereinscup', 'MSV Wilen Vereinscup', '', 'SELECT `ParticipantID` as gewinner_id,`Result` FROM `cupFinalResults` WHERE `Year` = {jahr} order by Result DESC, LowShot Desc', 1);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `wichtige_termine`
--

CREATE TABLE `wichtige_termine` (
  `ID` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `time` varchar(50) NOT NULL,
  `year` int(4) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `wichtige_termine`
--

INSERT INTO `wichtige_termine` (`ID`, `name`, `date`, `time`, `year`, `created_at`) VALUES
(1, 'Erstes Training', '2025-04-02', '17.30 - 19.30', 2025, '2025-02-25 11:03:15'),
(4, 'Letztes Training 300m / Pist. 50/25 m vor Sommerpause', '2025-07-09', '18.00 - 20.00', 2025, '2025-02-25 11:11:43'),
(17, 'Standreinigung', '2025-03-15', '07.30 - 13.00', 2025, '2025-02-25 13:48:27'),
(18, 'DV SKSG in Altendorf', '2025-03-08', '13.30 - 16.30', 2025, '2025-02-25 13:49:07'),
(19, '1. Training n. Sommerp. 300/50/25m  ', '2025-08-06', '18.00 - 20.00', 2025, '2025-02-25 14:10:03'),
(20, 'Trainingsbeginn 25/50/300m neu ab 17.30', '2025-09-10', '17.30 - 19.30', 2025, '2025-02-25 14:10:46'),
(21, '1. Match-Training MSMH 300 m ', '2025-03-15', '13.00 - 17.00', 2025, '2025-02-25 14:14:01'),
(22, 'DV Standkommission Roggenacker', '2025-04-10', '20.00 - 22.00', 2025, '2025-02-25 14:14:45'),
(23, 'Letztes Training 300m / Pist. 50/25 m', '2025-10-08', '17.30 - 19.30', 2025, '2025-02-25 14:15:17'),
(24, 'SSM Runde 1 letzte Möglichkeit ', '2025-06-11', '18.00 - 20.00', 2025, '2025-02-25 14:16:48'),
(25, 'SSM Runde 2 letzte Möglichkeit', '2025-09-10', '17.30 - 19.30', 2025, '2025-02-25 14:17:35'),
(26, 'ZSMM 1. Runde letzte Möglichkeit', '2025-04-30', '18.00 - 20.00', 2025, '2025-02-25 14:18:16'),
(27, 'ZSMM 2. Runde letzte Möglichkeit', '2025-05-28', '18.00 - 20.00', 2025, '2025-02-25 14:18:36'),
(28, 'ZSMM 3. Runde letzte Möglichkeit', '2025-07-09', '18.00 - 20.00', 2025, '2025-02-25 14:19:13'),
(29, 'ZSMM 4. Runde letzte Möglichkeit', '2025-09-17', '18.00 - 20.00', 2025, '2025-02-25 14:19:55'),
(30, 'Familientag', '2025-09-13', '13.00 - 20.00', 2025, '2025-03-07 07:43:11'),
(31, '1. Jungschützenkurs ', '2025-04-14', '18.00 - 20.00', 2025, '2025-03-07 07:44:55'),
(32, '2. Jungschützenkurs', '2025-04-28', '18.00 - 20.00', 2025, '2025-03-07 07:45:12'),
(33, '3. Jungschützenkurs', '2025-05-05', '18.00 - 20.00', 2025, '2025-03-07 07:45:28'),
(34, '4. Jungschützenkurs', '2025-05-12', '18.00 - 20.00', 2025, '2025-03-07 07:45:40'),
(35, '5. Jungschützenkurs', '2025-05-19', '18.00 - 20.00', 2025, '2025-03-07 07:45:53'),
(36, '6. Jungschützenkurs', '2025-05-26', '18.00 - 20.00', 2025, '2025-03-07 07:46:06'),
(37, '7. Jungschützenkurs', '2025-06-02', '18.00 - 20.00', 2025, '2025-03-07 07:46:20'),
(39, 'Letze obligatorische Bundesübung', '2025-08-29', '18.00 - 20.00', 2025, '2025-03-07 11:18:54'),
(40, 'Einschreiben Jungschützenkurs', '2025-04-07', '19.00 - 20.00', 2025, '2025-03-21 07:35:46'),
(41, 'Wyler Chilbi', '2025-11-22', '12:00-23:59', 2025, '2025-11-21 09:40:34'),
(42, 'Wyler Chilbi', '2025-11-23', '12:00-18:00', 2025, '2025-11-21 09:45:01'),
(46, 'DV SKSG in Rothenturm', '2026-03-14', '13.30 - 17:00', 2026, '2026-02-08 23:00:00'),
(52, 'SSM Runde 1 letzte Möglichkeit ', '2026-06-10', '18.00 - 20.00', 2026, '2026-02-08 23:00:00'),
(53, 'SSM Runde 2 letzte Möglichkeit', '2026-09-09', '17.30 - 19.30', 2026, '2026-02-08 23:00:00'),
(54, 'ZSMM 1. Runde letzte Möglichkeit', '2026-04-29', '18.00 - 20.00', 2026, '2026-02-08 23:00:00'),
(55, 'ZSMM 2. Runde letzte Möglichkeit', '2026-06-03', '18.00 - 20.00', 2026, '2026-02-08 23:00:00'),
(56, 'ZSMM 3. Runde letzte Möglichkeit', '2026-07-15', '18.00 - 20.00', 2026, '2026-02-08 23:00:00'),
(57, 'ZSMM 4. Runde letzte Möglichkeit', '2026-09-16', '18.00 - 20.00', 2026, '2026-02-08 23:00:00'),
(59, 'Wyler Chilbi', '2026-11-21', '12:00-23:59', 2026, '2026-02-08 23:00:00'),
(60, 'Wyler Chilbi', '2026-11-22', '12:00-18:00', 2026, '2026-02-08 23:00:00'),
(62, '2. Match-Training 300 m ', '2026-03-14', '08:30 - 11:30', 0, '2026-02-09 20:00:19');

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zabig`
--

CREATE TABLE `zabig` (
  `ID` int(11) NOT NULL,
  `MitgliedID` int(11) NOT NULL,
  `ZSchuss1` int(11) DEFAULT NULL,
  `ZSchuss2` int(11) DEFAULT NULL,
  `ZSchuss3` int(11) DEFAULT NULL,
  `ZSchuss4` int(11) DEFAULT NULL,
  `ZSchuss5` int(11) DEFAULT NULL,
  `ZSchuss6` int(11) DEFAULT NULL,
  `Ansage` int(3) NOT NULL,
  `Jahr` int(4) NOT NULL DEFAULT year(curdate())
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Daten für Tabelle `zabig`
--

INSERT INTO `zabig` (`ID`, `MitgliedID`, `ZSchuss1`, `ZSchuss2`, `ZSchuss3`, `ZSchuss4`, `ZSchuss5`, `ZSchuss6`, `Ansage`, `Jahr`) VALUES
(10, 112103, 91, 85, 83, 89, 88, 88, 537, 2024),
(11, 112114, 92, 94, 88, 95, 95, 93, 540, 2024),
(12, 112101, 91, 86, 94, 90, 85, 91, 500, 2024),
(13, 112109, 65, 56, 89, 84, 89, 71, 465, 2024),
(14, 385067, 78, 91, 82, 90, 93, 88, 540, 2024),
(15, 112108, 75, 79, 88, 80, 71, 61, 503, 2024),
(16, 831789, 70, 88, 74, 81, 76, 63, 375, 2024),
(17, 112102, 81, 70, 83, 88, 92, 76, 549, 2024),
(18, 112141, 64, 84, 86, 78, 91, 84, 523, 2024),
(19, 112140, 84, 89, 96, 79, 86, 83, 537, 2024),
(20, 112137, 79, 87, 92, 93, 74, 79, 510, 2024),
(21, 889594, 67, 87, 88, 85, 85, 85, 542, 2024),
(22, 112111, 84, 73, 80, 78, 95, 46, 512, 2024),
(23, 112131, 90, 93, 92, 95, 96, 94, 551, 2024),
(24, 548406, 90, 79, 80, 95, 90, 83, 470, 2024),
(25, 112144, 92, 87, 96, 79, 92, 92, 555, 2024),
(26, 112139, 86, 85, 61, 84, 80, 85, 465, 2024),
(27, 112126, 83, 90, 90, 74, 94, 60, 521, 2024),
(28, 112097, 0, 0, 0, 0, 0, 0, 0, 2024),
(29, 112104, 0, 0, 0, 0, 0, 0, 0, 2024),
(66, 112140, 87, 75, 87, 90, 99, 99, 537, 2025),
(67, 385067, 84, 78, 88, 79, 93, 85, 510, 2025),
(68, 112114, 81, 85, 91, 87, 84, 97, 543, 2025),
(69, 112109, 87, 83, 81, 79, 90, 97, 522, 2025),
(75, 112108, 85, 95, 89, 79, 83, 86, 533, 2025),
(76, 831789, 66, 67, 78, 56, 60, 53, 0, 2025),
(77, 112126, 88, 68, 74, 92, 88, 73, 521, 2025),
(78, 112131, 93, 98, 91, 90, 98, 93, 557, 2025),
(79, 112101, 80, 88, 76, 88, 83, 89, 519, 2025),
(80, 112102, 61, 66, 83, 78, 80, 66, 519, 2025),
(81, 112103, 82, 98, 93, 98, 87, 93, 536, 2025),
(82, 112137, 91, 94, 62, 80, 65, 80, 510, 2025),
(83, 112139, 65, 71, 70, 95, 52, 65, 0, 2025),
(84, 548406, 77, 78, 75, 72, 89, 78, 432, 2025),
(85, 112141, 98, 91, 92, 88, 79, 74, 497, 2025),
(86, 112111, 62, 83, 81, 90, 86, 72, 520, 2025),
(87, 112144, 89, 91, 100, 100, 85, 88, 555, 2025),
(88, 29093, 79, 82, 71, 67, 95, 88, 0, 2025);

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `zabig_jung`
--

CREATE TABLE `zabig_jung` (
  `ID` int(11) NOT NULL,
  `JungschuetzeID` int(11) NOT NULL,
  `ZSchuss1` int(11) DEFAULT NULL,
  `ZSchuss2` int(11) DEFAULT NULL,
  `ZSchuss3` int(11) DEFAULT NULL,
  `ZSchuss4` int(11) DEFAULT NULL,
  `ZSchuss5` int(11) DEFAULT NULL,
  `ZSchuss6` int(11) DEFAULT NULL,
  `Ansage` int(11) DEFAULT NULL,
  `Jahr` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Daten für Tabelle `zabig_jung`
--

INSERT INTO `zabig_jung` (`ID`, `JungschuetzeID`, `ZSchuss1`, `ZSchuss2`, `ZSchuss3`, `ZSchuss4`, `ZSchuss5`, `ZSchuss6`, `Ansage`, `Jahr`) VALUES
(4, 38, 80, 80, 81, 91, 66, 82, NULL, 2025),
(5, 39, 65, 99, 93, 59, 90, 82, NULL, 2025);

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `changelog`
--
ALTER TABLE `changelog`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sichtbar_erstellt` (`sichtbar`,`erstellt_am`),
  ADD KEY `idx_kategorie` (`kategorie`),
  ADD KEY `idx_jahr` (`jahr`),
  ADD KEY `idx_wp_slug` (`wp_slug`);

--
-- Indizes für die Tabelle `cupAuditLog`
--
ALTER TABLE `cupAuditLog`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_action` (`Action`),
  ADD KEY `idx_pair` (`PairID`),
  ADD KEY `idx_timestamp` (`Timestamp`);

--
-- Indizes für die Tabelle `cupFinalResults`
--
ALTER TABLE `cupFinalResults`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ParticipantID` (`ParticipantID`),
  ADD KEY `idx_year_participant` (`Year`,`ParticipantID`);

--
-- Indizes für die Tabelle `cupPairs`
--
ALTER TABLE `cupPairs`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `cupStandFinal`
--
ALTER TABLE `cupStandFinal`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `einsatz_zuweisungen`
--
ALTER TABLE `einsatz_zuweisungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mitglied` (`mitglied_id`,`event_datum`),
  ADD KEY `idx_datum` (`event_datum`),
  ADD KEY `idx_dokument` (`dokument_id`);

--
-- Indizes für die Tabelle `einzelrangierungen`
--
ALTER TABLE `einzelrangierungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_einzelrangierung_year_definition_mitglied` (`year`,`jmdefinition_id`,`mitglied_id`),
  ADD KEY `idx_einzelrangierung_year` (`year`),
  ADD KEY `idx_einzelrangierung_jmdefinition` (`jmdefinition_id`),
  ADD KEY `idx_einzelrangierung_mitglied` (`mitglied_id`),
  ADD KEY `idx_einzelrangierung_rang` (`rang`),
  ADD KEY `idx_einzelrangierung_resultat` (`resultat`);

--
-- Indizes für die Tabelle `endresultate_partner`
--
ALTER TABLE `endresultate_partner`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `unique_member_year` (`MitgliedID`,`Jahr`),
  ADD KEY `idx_mitglied_jahr` (`MitgliedID`,`Jahr`),
  ADD KEY `idx_jahr` (`Jahr`);

--
-- Indizes für die Tabelle `endstich`
--
ALTER TABLE `endstich`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `endstich_definition`
--
ALTER TABLE `endstich_definition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indizes für die Tabelle `endstich_gaeste`
--
ALTER TABLE `endstich_gaeste`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_gast_jahr` (`name`,`jahr`),
  ADD KEY `idx_jahr` (`jahr`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `idx_geburtsdatum` (`geburtsdatum`),
  ADD KEY `fk_gast_waffe` (`waffen_id`);

--
-- Indizes für die Tabelle `endstich_jung`
--
ALTER TABLE `endstich_jung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- Indizes für die Tabelle `endstich_selection`
--
ALTER TABLE `endstich_selection`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_mitglied_jahr_stich` (`mitglied_id`,`jahr`,`stich_id`),
  ADD KEY `fk_stich_selection_stich` (`stich_id`),
  ADD KEY `idx_stich_selection_mitglied_jahr` (`mitglied_id`,`jahr`),
  ADD KEY `idx_gast` (`gast_id`);

--
-- Indizes für die Tabelle `endstich_spezialpreise`
--
ALTER TABLE `endstich_spezialpreise`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_typ` (`typ`);

--
-- Indizes für die Tabelle `endstich_zusatz_schuss`
--
ALTER TABLE `endstich_zusatz_schuss`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mitglied_jahr` (`mitglied_id`,`jahr`),
  ADD KEY `idx_zusatz_jahr` (`jahr`),
  ADD KEY `idx_zusatz_gast` (`gast_id`);

--
-- Indizes für die Tabelle `glueck`
--
ALTER TABLE `glueck`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `glueck_jung`
--
ALTER TABLE `glueck_jung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- Indizes für die Tabelle `heimresultate`
--
ALTER TABLE `heimresultate`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `interne_stichdefinition`
--
ALTER TABLE `interne_stichdefinition`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_stich` (`stich`);

--
-- Indizes für die Tabelle `JMDefinition`
--
ALTER TABLE `JMDefinition`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ID` (`ID`);

--
-- Indizes für die Tabelle `JMDefinition2024`
--
ALTER TABLE `JMDefinition2024`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ID` (`ID`);

--
-- Indizes für die Tabelle `JMDefinition_bak20250315`
--
ALTER TABLE `JMDefinition_bak20250315`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `ID` (`ID`);

--
-- Indizes für die Tabelle `JMDefinition_Gruppen`
--
ALTER TABLE `JMDefinition_Gruppen`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `mitgliederID` (`mitgliederID`),
  ADD KEY `JMDefinitionID` (`JMDefinitionID`),
  ADD KEY `GruppenUID` (`GruppenUID`);

--
-- Indizes für die Tabelle `JMInformation`
--
ALTER TABLE `JMInformation`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `jmresultate`
--
ALTER TABLE `jmresultate`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `jmdefinitionID` (`jmdefinitionID`),
  ADD KEY `jmresultate_ibfk_1` (`mitgliederID`);

--
-- Indizes für die Tabelle `jmresultate2024`
--
ALTER TABLE `jmresultate2024`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `jmdefinitionID` (`jmdefinitionID`),
  ADD KEY `jmresultate_ibfk_1` (`mitgliederID`);

--
-- Indizes für die Tabelle `JMSchiesstage`
--
ALTER TABLE `JMSchiesstage`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `jm_id` (`jm_id`);

--
-- Indizes für die Tabelle `jsendschloesen_gaeste`
--
ALTER TABLE `jsendschloesen_gaeste`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jahr` (`jahr`),
  ADD KEY `idx_name` (`nachname`,`vorname`);

--
-- Indizes für die Tabelle `jsendschloesen_stiche`
--
ALTER TABLE `jsendschloesen_stiche`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name` (`name`);

--
-- Indizes für die Tabelle `jungschuetzen`
--
ALTER TABLE `jungschuetzen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `AHVNummer_UNIQUE` (`AHVNummer`);

--
-- Indizes für die Tabelle `jungschuetzen_helfer`
--
ALTER TABLE `jungschuetzen_helfer`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_event` (`eventID`);

--
-- Indizes für die Tabelle `jungschuetzen_resultate`
--
ALTER TABLE `jungschuetzen_resultate`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- Indizes für die Tabelle `kantidefinition`
--
ALTER TABLE `kantidefinition`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_kantidefinition_waffen` (`WaffenID`);

--
-- Indizes für die Tabelle `kantiresultate`
--
ALTER TABLE `kantiresultate`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `kunst`
--
ALTER TABLE `kunst`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `kunst_jung`
--
ALTER TABLE `kunst_jung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- Indizes für die Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `WaffenID` (`WaffenID`),
  ADD KEY `ID` (`ID`),
  ADD KEY `idx_status_name` (`Status`,`Name`,`Vorname`);

--
-- Indizes für die Tabelle `mitglieder_aenderungen`
--
ALTER TABLE `mitglieder_aenderungen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mitglied` (`mitglied_id`),
  ADD KEY `idx_datum` (`geaendert_am`),
  ADD KEY `fk_aenderungen_user` (`user_id`);

--
-- Indizes für die Tabelle `mitglieder_fragebogen`
--
ALTER TABLE `mitglieder_fragebogen`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `mitglieder_fragebogen_erweitert`
--
ALTER TABLE `mitglieder_fragebogen_erweitert`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `msv_refresh_tokens`
--
ALTER TABLE `msv_refresh_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token_hash` (`token_hash`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token_hash` (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indizes für die Tabelle `munitionskauf`
--
ALTER TABLE `munitionskauf`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jahr` (`jahr`),
  ADD KEY `idx_kauf_datum` (`kauf_datum`),
  ADD KEY `idx_mitglied` (`mitglied_id`),
  ADD KEY `idx_jahr_datum` (`jahr`,`kauf_datum`),
  ADD KEY `idx_stats` (`jahr`,`kauf_datum`,`total_preis`),
  ADD KEY `idx_gast` (`gast_name`);

--
-- Indizes für die Tabelle `munitionskauf_details`
--
ALTER TABLE `munitionskauf_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bestellung` (`bestellung_id`);

--
-- Indizes für die Tabelle `navigation`
--
ALTER TABLE `navigation`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_parent_sort` (`ParentID`,`SortOrder`);

--
-- Indizes für die Tabelle `Parameter`
--
ALTER TABLE `Parameter`
  ADD PRIMARY KEY (`year`);

--
-- Indizes für die Tabelle `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indizes für die Tabelle `remember_tokens`
--
ALTER TABLE `remember_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token_hash` (`token_hash`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indizes für die Tabelle `sAltersKat`
--
ALTER TABLE `sAltersKat`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `schwini`
--
ALTER TABLE `schwini`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `schwini_jung`
--
ALTER TABLE `schwini_jung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- Indizes für die Tabelle `sektionsrangierungen`
--
ALTER TABLE `sektionsrangierungen`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_year_definition` (`year`,`jmdefinition_id`),
  ADD KEY `idx_year` (`year`),
  ADD KEY `idx_rang` (`rang`),
  ADD KEY `fk_jmdefinition` (`jmdefinition_id`),
  ADD KEY `idx_year_rang` (`year`,`rang`);

--
-- Indizes für die Tabelle `sieger`
--
ALTER TABLE `sieger`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_siegerdef` (`siegerdef`);

--
-- Indizes für die Tabelle `siegerdef`
--
ALTER TABLE `siegerdef`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `sKranzLimiten`
--
ALTER TABLE `sKranzLimiten`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_WaffenID` (`WaffenID`),
  ADD KEY `fk_sAltersKatID` (`sAltersKatID`),
  ADD KEY `fk_JMDefinitionID` (`JMDefinitionID`);

--
-- Indizes für die Tabelle `Standbelegung`
--
ALTER TABLE `Standbelegung`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `idx_unique_entry` (`Datum`,`Bezeichnung`,`StartZeit`,`Jahr`),
  ADD KEY `idx_kategorie` (`Kategorie`),
  ADD KEY `idx_jahr` (`Jahr`),
  ADD KEY `idx_datum` (`Datum`);

--
-- Indizes für die Tabelle `Standbelegung_ArtKeywords`
--
ALTER TABLE `Standbelegung_ArtKeywords`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `unique_keyword` (`Keyword`);

--
-- Indizes für die Tabelle `umfragen`
--
ALTER TABLE `umfragen`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `umfragen_antworten`
--
ALTER TABLE `umfragen_antworten`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_frage_mitglied` (`frage_id`,`mitglied_id`),
  ADD KEY `umfrage_id` (`umfrage_id`);

--
-- Indizes für die Tabelle `umfragen_fragen`
--
ALTER TABLE `umfragen_fragen`
  ADD PRIMARY KEY (`id`),
  ADD KEY `umfrage_id` (`umfrage_id`);

--
-- Indizes für die Tabelle `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uk_mitglied_id` (`mitglied_id`),
  ADD KEY `idx_calendar_token` (`calendar_token`);

--
-- Indizes für die Tabelle `vorstand_dokumente`
--
ALTER TABLE `vorstand_dokumente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `hochgeladen_von` (`hochgeladen_von`);

--
-- Indizes für die Tabelle `Waffen`
--
ALTER TABLE `Waffen`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `wanderpreise`
--
ALTER TABLE `wanderpreise`
  ADD PRIMARY KEY (`id`),
  ADD KEY `gewinner_id` (`gewinner_id`),
  ADD KEY `idx_verknuepfung_jahr` (`verknuepfung_jahr`),
  ADD KEY `idx_beschaffung_datum` (`beschaffung_datum`),
  ADD KEY `idx_hersteller` (`hersteller`);

--
-- Indizes für die Tabelle `wanderpreise_gewinner`
--
ALTER TABLE `wanderpreise_gewinner`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_wanderpreis_jahr` (`wanderpreis_id`,`jahr`),
  ADD KEY `gewinner_id` (`gewinner_id`),
  ADD KEY `idx_jahr` (`jahr`),
  ADD KEY `idx_wanderpreis_gewinner` (`wanderpreis_id`,`gewinner_id`);

--
-- Indizes für die Tabelle `wanderpreise_regeln`
--
ALTER TABLE `wanderpreise_regeln`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `regel_code` (`regel_code`);

--
-- Indizes für die Tabelle `wichtige_termine`
--
ALTER TABLE `wichtige_termine`
  ADD PRIMARY KEY (`ID`);

--
-- Indizes für die Tabelle `zabig`
--
ALTER TABLE `zabig`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `MitgliedID` (`MitgliedID`);

--
-- Indizes für die Tabelle `zabig_jung`
--
ALTER TABLE `zabig_jung`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `JungschuetzeID` (`JungschuetzeID`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `changelog`
--
ALTER TABLE `changelog`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `cupAuditLog`
--
ALTER TABLE `cupAuditLog`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT für Tabelle `cupFinalResults`
--
ALTER TABLE `cupFinalResults`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT für Tabelle `cupPairs`
--
ALTER TABLE `cupPairs`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=118;

--
-- AUTO_INCREMENT für Tabelle `cupStandFinal`
--
ALTER TABLE `cupStandFinal`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT für Tabelle `einsatz_zuweisungen`
--
ALTER TABLE `einsatz_zuweisungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=384;

--
-- AUTO_INCREMENT für Tabelle `einzelrangierungen`
--
ALTER TABLE `einzelrangierungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT für Tabelle `endresultate_partner`
--
ALTER TABLE `endresultate_partner`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT für Tabelle `endstich`
--
ALTER TABLE `endstich`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT für Tabelle `endstich_definition`
--
ALTER TABLE `endstich_definition`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT für Tabelle `endstich_gaeste`
--
ALTER TABLE `endstich_gaeste`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT für Tabelle `endstich_jung`
--
ALTER TABLE `endstich_jung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `endstich_selection`
--
ALTER TABLE `endstich_selection`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=598;

--
-- AUTO_INCREMENT für Tabelle `endstich_spezialpreise`
--
ALTER TABLE `endstich_spezialpreise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `endstich_zusatz_schuss`
--
ALTER TABLE `endstich_zusatz_schuss`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT für Tabelle `glueck`
--
ALTER TABLE `glueck`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT für Tabelle `glueck_jung`
--
ALTER TABLE `glueck_jung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `heimresultate`
--
ALTER TABLE `heimresultate`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=87;

--
-- AUTO_INCREMENT für Tabelle `interne_stichdefinition`
--
ALTER TABLE `interne_stichdefinition`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT für Tabelle `JMDefinition`
--
ALTER TABLE `JMDefinition`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT für Tabelle `JMDefinition2024`
--
ALTER TABLE `JMDefinition2024`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT für Tabelle `JMDefinition_bak20250315`
--
ALTER TABLE `JMDefinition_bak20250315`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=120;

--
-- AUTO_INCREMENT für Tabelle `JMDefinition_Gruppen`
--
ALTER TABLE `JMDefinition_Gruppen`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT für Tabelle `JMInformation`
--
ALTER TABLE `JMInformation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=219;

--
-- AUTO_INCREMENT für Tabelle `jmresultate`
--
ALTER TABLE `jmresultate`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=428;

--
-- AUTO_INCREMENT für Tabelle `jmresultate2024`
--
ALTER TABLE `jmresultate2024`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=581;

--
-- AUTO_INCREMENT für Tabelle `JMSchiesstage`
--
ALTER TABLE `JMSchiesstage`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11742;

--
-- AUTO_INCREMENT für Tabelle `jsendschloesen_gaeste`
--
ALTER TABLE `jsendschloesen_gaeste`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `jsendschloesen_stiche`
--
ALTER TABLE `jsendschloesen_stiche`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `jungschuetzen`
--
ALTER TABLE `jungschuetzen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT für Tabelle `jungschuetzen_helfer`
--
ALTER TABLE `jungschuetzen_helfer`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT für Tabelle `jungschuetzen_resultate`
--
ALTER TABLE `jungschuetzen_resultate`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT für Tabelle `kantidefinition`
--
ALTER TABLE `kantidefinition`
  MODIFY `ID` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `kantiresultate`
--
ALTER TABLE `kantiresultate`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=104;

--
-- AUTO_INCREMENT für Tabelle `kunst`
--
ALTER TABLE `kunst`
  MODIFY `ID` int(10) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT für Tabelle `kunst_jung`
--
ALTER TABLE `kunst_jung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=123132127;

--
-- AUTO_INCREMENT für Tabelle `mitglieder_aenderungen`
--
ALTER TABLE `mitglieder_aenderungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT für Tabelle `mitglieder_fragebogen`
--
ALTER TABLE `mitglieder_fragebogen`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT für Tabelle `mitglieder_fragebogen_erweitert`
--
ALTER TABLE `mitglieder_fragebogen_erweitert`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=370;

--
-- AUTO_INCREMENT für Tabelle `msv_refresh_tokens`
--
ALTER TABLE `msv_refresh_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `munitionskauf`
--
ALTER TABLE `munitionskauf`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT für Tabelle `munitionskauf_details`
--
ALTER TABLE `munitionskauf_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `navigation`
--
ALTER TABLE `navigation`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10123;

--
-- AUTO_INCREMENT für Tabelle `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `remember_tokens`
--
ALTER TABLE `remember_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=338;

--
-- AUTO_INCREMENT für Tabelle `sAltersKat`
--
ALTER TABLE `sAltersKat`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `schwini`
--
ALTER TABLE `schwini`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT für Tabelle `schwini_jung`
--
ALTER TABLE `schwini_jung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT für Tabelle `sektionsrangierungen`
--
ALTER TABLE `sektionsrangierungen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT für Tabelle `sieger`
--
ALTER TABLE `sieger`
  MODIFY `ID` int(6) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=358;

--
-- AUTO_INCREMENT für Tabelle `siegerdef`
--
ALTER TABLE `siegerdef`
  MODIFY `ID` int(2) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT für Tabelle `sKranzLimiten`
--
ALTER TABLE `sKranzLimiten`
  MODIFY `ID` int(3) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT für Tabelle `Standbelegung`
--
ALTER TABLE `Standbelegung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=943;

--
-- AUTO_INCREMENT für Tabelle `Standbelegung_ArtKeywords`
--
ALTER TABLE `Standbelegung_ArtKeywords`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT für Tabelle `umfragen`
--
ALTER TABLE `umfragen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `umfragen_antworten`
--
ALTER TABLE `umfragen_antworten`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT für Tabelle `umfragen_fragen`
--
ALTER TABLE `umfragen_fragen`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT für Tabelle `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT für Tabelle `vorstand_dokumente`
--
ALTER TABLE `vorstand_dokumente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT für Tabelle `Waffen`
--
ALTER TABLE `Waffen`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT für Tabelle `wanderpreise`
--
ALTER TABLE `wanderpreise`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT für Tabelle `wanderpreise_gewinner`
--
ALTER TABLE `wanderpreise_gewinner`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT für Tabelle `wanderpreise_regeln`
--
ALTER TABLE `wanderpreise_regeln`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT für Tabelle `wichtige_termine`
--
ALTER TABLE `wichtige_termine`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT für Tabelle `zabig`
--
ALTER TABLE `zabig`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT für Tabelle `zabig_jung`
--
ALTER TABLE `zabig_jung`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

-- --------------------------------------------------------

--
-- Struktur des Views `v_cup_winners`
--
DROP TABLE IF EXISTS `v_cup_winners`;

CREATE ALGORITHM=UNDEFINED DEFINER=`bdebbd4_msvjm`@`10.1.115.34` SQL SECURITY DEFINER VIEW `v_cup_winners`  AS SELECT `cp`.`ID` AS `pair_id`, `cp`.`Round` AS `Round`, `cp`.`Year` AS `Year`, CASE WHEN `cp`.`ManualWinner` is not null THEN `cp`.`ManualWinner` WHEN `cp`.`Participant3` is null THEN CASE WHEN `cp`.`Result1` > `cp`.`Result2` OR `cp`.`Result1` = `cp`.`Result2` AND `cp`.`LowShot1` > `cp`.`LowShot2` THEN `cp`.`Participant1` ELSE NULL END AS `winner_id` END FROM `cupPairs` AS `cp` WHERE `cp`.`Result1` is not null AND `cp`.`Result2` is not null ;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `einzelrangierungen`
--
ALTER TABLE `einzelrangierungen`
  ADD CONSTRAINT `fk_einzelrangierung_jmdefinition` FOREIGN KEY (`jmdefinition_id`) REFERENCES `JMDefinition` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_einzelrangierung_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `endresultate_partner`
--
ALTER TABLE `endresultate_partner`
  ADD CONSTRAINT `endresultate_partner_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `endstich`
--
ALTER TABLE `endstich`
  ADD CONSTRAINT `endstich_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `endstich_gaeste`
--
ALTER TABLE `endstich_gaeste`
  ADD CONSTRAINT `fk_gast_waffe` FOREIGN KEY (`waffen_id`) REFERENCES `Waffen` (`ID`);

--
-- Constraints der Tabelle `endstich_jung`
--
ALTER TABLE `endstich_jung`
  ADD CONSTRAINT `endstich_jung_gaeste_fk` FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `endstich_selection`
--
ALTER TABLE `endstich_selection`
  ADD CONSTRAINT `fk_selection_gast` FOREIGN KEY (`gast_id`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stich_selection_stich` FOREIGN KEY (`stich_id`) REFERENCES `endstich_definition` (`id`) ON UPDATE CASCADE;

--
-- Constraints der Tabelle `endstich_zusatz_schuss`
--
ALTER TABLE `endstich_zusatz_schuss`
  ADD CONSTRAINT `fk_zusatz_gast` FOREIGN KEY (`gast_id`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_zusatz_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `glueck`
--
ALTER TABLE `glueck`
  ADD CONSTRAINT `glueck_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `glueck_jung`
--
ALTER TABLE `glueck_jung`
  ADD CONSTRAINT `glueck_jung_ibfk_1` FOREIGN KEY (`JungschuetzeID`) REFERENCES `jungschuetzen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `heimresultate`
--
ALTER TABLE `heimresultate`
  ADD CONSTRAINT `heimresultate_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`);

--
-- Constraints der Tabelle `JMDefinition_Gruppen`
--
ALTER TABLE `JMDefinition_Gruppen`
  ADD CONSTRAINT `JMDefinition_Gruppen_ibfk_1` FOREIGN KEY (`mitgliederID`) REFERENCES `mitglieder` (`ID`),
  ADD CONSTRAINT `JMDefinition_Gruppen_ibfk_2` FOREIGN KEY (`JMDefinitionID`) REFERENCES `JMDefinition` (`ID`);

--
-- Constraints der Tabelle `jmresultate`
--
ALTER TABLE `jmresultate`
  ADD CONSTRAINT `jmresultate_ibfk_1` FOREIGN KEY (`mitgliederID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `jmresultate_ibfk_2` FOREIGN KEY (`jmdefinitionID`) REFERENCES `JMDefinition` (`ID`);

--
-- Constraints der Tabelle `jungschuetzen_helfer`
--
ALTER TABLE `jungschuetzen_helfer`
  ADD CONSTRAINT `fk_event` FOREIGN KEY (`eventID`) REFERENCES `wichtige_termine` (`ID`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `jungschuetzen_resultate`
--
ALTER TABLE `jungschuetzen_resultate`
  ADD CONSTRAINT `jungschuetzen_resultate_ibfk_1` FOREIGN KEY (`JungschuetzeID`) REFERENCES `jungschuetzen` (`id`);

--
-- Constraints der Tabelle `kantidefinition`
--
ALTER TABLE `kantidefinition`
  ADD CONSTRAINT `fk_kantidefinition_waffen` FOREIGN KEY (`WaffenID`) REFERENCES `Waffen` (`ID`);

--
-- Constraints der Tabelle `kantiresultate`
--
ALTER TABLE `kantiresultate`
  ADD CONSTRAINT `kantiresultate_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kunst`
--
ALTER TABLE `kunst`
  ADD CONSTRAINT `kunst_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `kunst_jung`
--
ALTER TABLE `kunst_jung`
  ADD CONSTRAINT `kunst_jung_ibfk_1` FOREIGN KEY (`JungschuetzeID`) REFERENCES `jungschuetzen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `mitglieder`
--
ALTER TABLE `mitglieder`
  ADD CONSTRAINT `fk_waffen` FOREIGN KEY (`WaffenID`) REFERENCES `Waffen` (`ID`);

--
-- Constraints der Tabelle `mitglieder_aenderungen`
--
ALTER TABLE `mitglieder_aenderungen`
  ADD CONSTRAINT `fk_aenderungen_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_aenderungen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `msv_refresh_tokens`
--
ALTER TABLE `msv_refresh_tokens`
  ADD CONSTRAINT `msv_refresh_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `munitionskauf`
--
ALTER TABLE `munitionskauf`
  ADD CONSTRAINT `fk_munitionskauf_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `munitionskauf_details`
--
ALTER TABLE `munitionskauf_details`
  ADD CONSTRAINT `fk_munitionskauf_details` FOREIGN KEY (`bestellung_id`) REFERENCES `munitionskauf` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `schwini`
--
ALTER TABLE `schwini`
  ADD CONSTRAINT `schwini_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `schwini_jung`
--
ALTER TABLE `schwini_jung`
  ADD CONSTRAINT `schwini_jung_gaeste_fk` FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `sektionsrangierungen`
--
ALTER TABLE `sektionsrangierungen`
  ADD CONSTRAINT `fk_sektionsrangierungen_jmdefinition` FOREIGN KEY (`jmdefinition_id`) REFERENCES `JMDefinition` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `sieger`
--
ALTER TABLE `sieger`
  ADD CONSTRAINT `fk_siegerdef` FOREIGN KEY (`siegerdef`) REFERENCES `siegerdef` (`ID`);

--
-- Constraints der Tabelle `sKranzLimiten`
--
ALTER TABLE `sKranzLimiten`
  ADD CONSTRAINT `fk_JMDefinitionID` FOREIGN KEY (`JMDefinitionID`) REFERENCES `JMDefinition` (`ID`),
  ADD CONSTRAINT `fk_WaffenID` FOREIGN KEY (`WaffenID`) REFERENCES `Waffen` (`ID`),
  ADD CONSTRAINT `fk_sAltersKatID` FOREIGN KEY (`sAltersKatID`) REFERENCES `sAltersKat` (`ID`);

--
-- Constraints der Tabelle `umfragen_antworten`
--
ALTER TABLE `umfragen_antworten`
  ADD CONSTRAINT `umfragen_antworten_ibfk_1` FOREIGN KEY (`umfrage_id`) REFERENCES `umfragen` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `umfragen_antworten_ibfk_2` FOREIGN KEY (`frage_id`) REFERENCES `umfragen_fragen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `umfragen_fragen`
--
ALTER TABLE `umfragen_fragen`
  ADD CONSTRAINT `umfragen_fragen_ibfk_1` FOREIGN KEY (`umfrage_id`) REFERENCES `umfragen` (`id`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_mitglied` FOREIGN KEY (`mitglied_id`) REFERENCES `mitglieder` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints der Tabelle `vorstand_dokumente`
--
ALTER TABLE `vorstand_dokumente`
  ADD CONSTRAINT `vorstand_dokumente_ibfk_1` FOREIGN KEY (`hochgeladen_von`) REFERENCES `users` (`id`);

--
-- Constraints der Tabelle `wanderpreise`
--
ALTER TABLE `wanderpreise`
  ADD CONSTRAINT `wanderpreise_ibfk_1` FOREIGN KEY (`gewinner_id`) REFERENCES `mitglieder` (`ID`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `wanderpreise_gewinner`
--
ALTER TABLE `wanderpreise_gewinner`
  ADD CONSTRAINT `wanderpreise_gewinner_ibfk_1` FOREIGN KEY (`wanderpreis_id`) REFERENCES `wanderpreise` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `wanderpreise_gewinner_ibfk_2` FOREIGN KEY (`gewinner_id`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE;

--
-- Constraints der Tabelle `zabig`
--
ALTER TABLE `zabig`
  ADD CONSTRAINT `zabig_ibfk_1` FOREIGN KEY (`MitgliedID`) REFERENCES `mitglieder` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints der Tabelle `zabig_jung`
--
ALTER TABLE `zabig_jung`
  ADD CONSTRAINT `zabig_jung_gaeste_fk` FOREIGN KEY (`JungschuetzeID`) REFERENCES `endstich_gaeste` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
