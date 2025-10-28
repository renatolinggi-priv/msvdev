<?
//functions.inc.php

//require '../config.php';
function getSiegerJM()
{
}
function getSiegerHeim()
{
}

function getKKEnd($MitgliedID, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    $sql = "
        SELECT
            m.ID,
            m.Name,
            m.Vorname,
            e.Schuss1,
            e.Schuss2,
            e.Schuss3,
            e.Schuss4,
            e.Schuss5,
            e.Schuss6,
            e.Schuss7,
            e.Schuss8,
            e.Schuss9,
            e.Schuss10,
            e.Tiefschuss,
            w.Kranz_Endstich,
            COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS Endstich_Summe
            FROM
            mitglieder m
            LEFT JOIN endstich e ON m.ID = e.MitgliedID
            LEFT JOIN Waffen w ON w.ID = m.WaffenID
            WHERE e.Schuss1 !=0 and m.ID = $MitgliedID AND e.Jahr = $year
            GROUP BY
            m.ID, m.Vorname, m.Name
            ORDER BY Endstich_Summe DESC, e.Tiefschuss DESC;
    ";
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows == 0) {
        $conn->close();
        return "0";
    }
    
    $row = $result->fetch_assoc();
    $conn->close();
    
    if ($row['Endstich_Summe'] >= $row['Kranz_Endstich']) {
        return "10";
    }
    
    return "0";
}

function getKKKunst($MitgliedID, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    
    $sql = "
       SELECT
    m.ID,
    m.Name,
    m.Vorname,
    m.Geburtsdatum,
    k.KSchuss1,
    k.KSchuss2,
    k.KSchuss3,
    k.KSchuss4,
    k.KSchuss5,
    w.Kranz_Kunst,
    COALESCE(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5), 0) AS Kunst_Summe,
    GREATEST(
        COALESCE(k.KSchuss1, 0),
        COALESCE(k.KSchuss2, 0),
        COALESCE(k.KSchuss3, 0),
        COALESCE(k.KSchuss4, 0),
        COALESCE(k.KSchuss5, 0)
    ) AS TS
FROM
    mitglieder m
LEFT JOIN kunst k ON m.ID = k.MitgliedID
LEFT JOIN Waffen w ON w.ID = m.WaffenID
WHERE k.KSchuss1 != 0 and m.ID = $MitgliedID AND k.Jahr = $year
GROUP BY
    m.ID, m.Vorname, m.Name, m.Geburtsdatum
ORDER BY
    Kunst_Summe DESC,
    TS DESC,
    m.Geburtsdatum ASC;
    ";
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows == 0) {
        $conn->close();
        return "0";
    }
    
    $row = $result->fetch_assoc();
    $conn->close();
    
    if ($row['Kunst_Summe'] >= $row['Kranz_Kunst']) {
        return "10";
    }
    
    return "0";
}

function getKKEndschiessen($MitgliedID, $kat, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    $sql = "SELECT
        m.Name,
        m.Vorname,
        m.ID,
        (CASE
            WHEN z.ZSchuss1 >= 91 THEN 10
            WHEN z.ZSchuss1 >= 81 THEN 9
            WHEN z.ZSchuss1 >= 71 THEN 8
            WHEN z.ZSchuss1 >= 61 THEN 7
            WHEN z.ZSchuss1 >= 51 THEN 6
            WHEN z.ZSchuss1 >= 41 THEN 5
            WHEN z.ZSchuss1 >= 31 THEN 4
            WHEN z.ZSchuss1 >= 21 THEN 3
            WHEN z.ZSchuss1 >= 11 THEN 2
            WHEN z.ZSchuss1 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss2 >= 91 THEN 10
            WHEN z.ZSchuss2 >= 81 THEN 9
            WHEN z.ZSchuss2 >= 71 THEN 8
            WHEN z.ZSchuss2 >= 61 THEN 7
            WHEN z.ZSchuss2 >= 51 THEN 6
            WHEN z.ZSchuss2 >= 41 THEN 5
            WHEN z.ZSchuss2 >= 31 THEN 4
            WHEN z.ZSchuss2 >= 21 THEN 3
            WHEN z.ZSchuss2 >= 11 THEN 2
            WHEN z.ZSchuss2 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss3 >= 91 THEN 10
            WHEN z.ZSchuss3 >= 81 THEN 9
            WHEN z.ZSchuss3 >= 71 THEN 8
            WHEN z.ZSchuss3 >= 61 THEN 7
            WHEN z.ZSchuss3 >= 51 THEN 6
            WHEN z.ZSchuss3 >= 41 THEN 5
            WHEN z.ZSchuss3 >= 31 THEN 4
            WHEN z.ZSchuss3 >= 21 THEN 3
            WHEN z.ZSchuss3 >= 11 THEN 2
            WHEN z.ZSchuss3 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss4 >= 91 THEN 10
            WHEN z.ZSchuss4 >= 81 THEN 9
            WHEN z.ZSchuss4 >= 71 THEN 8
            WHEN z.ZSchuss4 >= 61 THEN 7
            WHEN z.ZSchuss4 >= 51 THEN 6
            WHEN z.ZSchuss4 >= 41 THEN 5
            WHEN z.ZSchuss4 >= 31 THEN 4
            WHEN z.ZSchuss4 >= 21 THEN 3
            WHEN z.ZSchuss4 >= 11 THEN 2
            WHEN z.ZSchuss4 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss5 >= 91 THEN 10
            WHEN z.ZSchuss5 >= 81 THEN 9
            WHEN z.ZSchuss5 >= 71 THEN 8
            WHEN z.ZSchuss5 >= 61 THEN 7
            WHEN z.ZSchuss5 >= 51 THEN 6
            WHEN z.ZSchuss5 >= 41 THEN 5
            WHEN z.ZSchuss5 >= 31 THEN 4
            WHEN z.ZSchuss5 >= 21 THEN 3
            WHEN z.ZSchuss5 >= 11 THEN 2
            WHEN z.ZSchuss5 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss6 >= 91 THEN 10
            WHEN z.ZSchuss6 >= 81 THEN 9
            WHEN z.ZSchuss6 >= 71 THEN 8
            WHEN z.ZSchuss6 >= 61 THEN 7
            WHEN z.ZSchuss6 >= 51 THEN 6
            WHEN z.ZSchuss6 >= 41 THEN 5
            WHEN z.ZSchuss6 >= 31 THEN 4
            WHEN z.ZSchuss6 >= 21 THEN 3
            WHEN z.ZSchuss6 >= 11 THEN 2
            WHEN z.ZSchuss6 >= 1 THEN 1
            ELSE 0
        END) AS ZabigTotal,
        COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1)) AS GlueckTotal,
        COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
        COALESCE(SUM(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6 ), 0) AS Schwini_Summe1,
        COALESCE(SUM(s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6 ), 0) AS Schwini_Summe2,
        COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal, 
        GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MaxSchwini,
        LEAST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
            s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6) as MinSchwini,
        -- Summe der oben genannten Werte
        (COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) +
        COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1)) +
        (CASE
            WHEN z.ZSchuss1 >= 91 THEN 10
            WHEN z.ZSchuss1 >= 81 THEN 9
            WHEN z.ZSchuss1 >= 71 THEN 8
            WHEN z.ZSchuss1 >= 61 THEN 7
            WHEN z.ZSchuss1 >= 51 THEN 6
            WHEN z.ZSchuss1 >= 41 THEN 5
            WHEN z.ZSchuss1 >= 31 THEN 4
            WHEN z.ZSchuss1 >= 21 THEN 3
            WHEN z.ZSchuss1 >= 11 THEN 2
            WHEN z.ZSchuss1 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss2 >= 91 THEN 10
            WHEN z.ZSchuss2 >= 81 THEN 9
            WHEN z.ZSchuss2 >= 71 THEN 8
            WHEN z.ZSchuss2 >= 61 THEN 7
            WHEN z.ZSchuss2 >= 51 THEN 6
            WHEN z.ZSchuss2 >= 41 THEN 5
            WHEN z.ZSchuss2 >= 31 THEN 4
            WHEN z.ZSchuss2 >= 21 THEN 3
            WHEN z.ZSchuss2 >= 11 THEN 2
            WHEN z.ZSchuss2 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss3 >= 91 THEN 10
            WHEN z.ZSchuss3 >= 81 THEN 9
            WHEN z.ZSchuss3 >= 71 THEN 8
            WHEN z.ZSchuss3 >= 61 THEN 7
            WHEN z.ZSchuss3 >= 51 THEN 6
            WHEN z.ZSchuss3 >= 41 THEN 5
            WHEN z.ZSchuss3 >= 31 THEN 4
            WHEN z.ZSchuss3 >= 21 THEN 3
            WHEN z.ZSchuss3 >= 11 THEN 2
            WHEN z.ZSchuss3 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss4 >= 91 THEN 10
            WHEN z.ZSchuss4 >= 81 THEN 9
            WHEN z.ZSchuss4 >= 71 THEN 8
            WHEN z.ZSchuss4 >= 61 THEN 7
            WHEN z.ZSchuss4 >= 51 THEN 6
            WHEN z.ZSchuss4 >= 41 THEN 5
            WHEN z.ZSchuss4 >= 31 THEN 4
            WHEN z.ZSchuss4 >= 21 THEN 3
            WHEN z.ZSchuss4 >= 11 THEN 2
            WHEN z.ZSchuss4 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss5 >= 91 THEN 10
            WHEN z.ZSchuss5 >= 81 THEN 9
            WHEN z.ZSchuss5 >= 71 THEN 8
            WHEN z.ZSchuss5 >= 61 THEN 7
            WHEN z.ZSchuss5 >= 51 THEN 6
            WHEN z.ZSchuss5 >= 41 THEN 5
            WHEN z.ZSchuss5 >= 31 THEN 4
            WHEN z.ZSchuss5 >= 21 THEN 3
            WHEN z.ZSchuss5 >= 11 THEN 2
            WHEN z.ZSchuss5 >= 1 THEN 1
            ELSE 0
        END +
        CASE
            WHEN z.ZSchuss6 >= 91 THEN 10
            WHEN z.ZSchuss6 >= 81 THEN 9
            WHEN z.ZSchuss6 >= 71 THEN 8
            WHEN z.ZSchuss6 >= 61 THEN 7
            WHEN z.ZSchuss6 >= 51 THEN 6
            WHEN z.ZSchuss6 >= 41 THEN 5
            WHEN z.ZSchuss6 >= 31 THEN 4
            WHEN z.ZSchuss6 >= 21 THEN 3
            WHEN z.ZSchuss6 >= 11 THEN 2
            WHEN z.ZSchuss6 >= 1 THEN 1
            ELSE 0
        END) +
        COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) +
        GREATEST(s.P1Schuss1 + s.P1Schuss2 + s.P1Schuss3 + s.P1Schuss4 + s.P1Schuss5 + s.P1Schuss6,
                s.P2Schuss1 + s.P2Schuss2 + s.P2Schuss3 + s.P2Schuss4 + s.P2Schuss5 + s.P2Schuss6)
        ) AS GesamtTotal
    FROM
        mitglieder m
    LEFT JOIN endstich e ON m.ID = e.MitgliedID
    LEFT JOIN schwini s ON m.ID = s.MitgliedID
    LEFT JOIN kunst k ON m.ID = k.MitgliedID
    LEFT JOIN glueck g ON m.ID = g.MitgliedID
    LEFT JOIN zabig z ON m.ID = z.MitgliedID
    LEFT JOIN Waffen w ON w.ID = m.WaffenID
    WHERE w.Kategorie = '$kat' AND e.Schuss1 != 0 AND e.Jahr = $year
    GROUP BY
        m.ID, m.Vorname, m.Name
    ORDER BY
    GesamtTotal DESC, EndstichTotal DESC, m.Geburtsdatum ASC";



    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result) {
        $conn->close();
        return "0";
    }
    
    $i = 1;
    $preis = "0";
    while ($row = $result->fetch_assoc()) {
        if ($i <= 3 && $MitgliedID == $row['ID']) {
            $preis = "10";
            break;
        }
        $i++;
    }
    
    $conn->close();
    return $preis;
}


function getKKHeimmeisterschaft($MitgliedID, $kat, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    
    $sql = "SELECT m.ID, m.Name, m.Vorname, h.Passe1, h.Passe2, h.Passe3, h.Passe4, h.Passe5, h.Passe6, h.Passe7, h.Passe8,
       (COALESCE(h.Passe1, 0) + COALESCE(h.Passe2, 0) + COALESCE(h.Passe3, 0) + COALESCE(h.Passe4, 0) +
        COALESCE(h.Passe5, 0) + COALESCE(h.Passe6, 0) + COALESCE(h.Passe7, 0) + COALESCE(h.Passe8, 0)) AS HeimSumme
        FROM heimresultate h
        INNER JOIN mitglieder m ON m.ID = h.MitgliedID
        INNER JOIN Waffen w ON w.ID = m.WaffenID
        WHERE w.Kategorie = '$kat' and h.Passe1 > 0 AND h.Jahr = $year
        ORDER BY HeimSumme DESC";



    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result) {
        $conn->close();
        return "0";
    }
    
    $i = 1;
    $preis = "0";
    while ($row = $result->fetch_assoc()) {
        if ($MitgliedID == $row['ID']) {
            switch ($i) {
                case 1:
                    $preis = "30";
                    break;
                case 2:
                    $preis = "20";
                    break;
                case 3:
                    $preis = "10";
                    break;
            }
            break; // Beende die Schleife, wir haben den Schützen gefunden
        }
        $i++;
    }
    
    $conn->close();
    return $preis;
}

function getKantiCost($MitgliedID, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    
    $sql = "  SELECT
    SUM(CASE WHEN Passe1 IS NOT NULL AND Passe1 != 0 THEN 1 ELSE 0 END) AS Passe1,
    SUM(CASE WHEN Passe2 IS NOT NULL AND Passe2 != 0 THEN 1 ELSE 0 END) AS Passe2,
    SUM(CASE WHEN Passe3 IS NOT NULL AND Passe3 != 0 THEN 1 ELSE 0 END) AS Passe3,
    SUM(CASE WHEN Passe4 IS NOT NULL AND Passe4 != 0 THEN 1 ELSE 0 END) AS Passe4,
    SUM(CASE WHEN Passe5 IS NOT NULL AND Passe5 != 0 THEN 1 ELSE 0 END) AS Passe5,
    m.ID
        
    FROM kantiresultate k
    join mitglieder m on m.ID = k.MitgliedID
    WHERE Jahr = $year and m.ID = $MitgliedID";

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result || $result->num_rows == 0) {
        $conn->close();
        return "0";
    }
    
    $Cost = 0;
    while ($row = $result->fetch_assoc()) {
        if ($row['Passe1'] > 0) {
            $Cost = 13;
        }
        if ($row['Passe2'] > 0) {
            $Cost += 3;
        }
        if ($row['Passe3'] > 0) {
            $Cost += 3;
        }
        if ($row['Passe4'] > 0) {
            $Cost += 3;
        }
        if ($row['Passe5'] > 0) {
            $Cost += 3;
        }
    }
    
    $conn->close();
    return strval($Cost);
}
function checkKantiKranzStatus($mitgliedID, $conn) {

    $sql = "
      WITH SchuetzeMitAlterskategorie AS (
    SELECT 
        m.ID AS MitgliedID,
        m.Name,
        m.Vorname,
        YEAR(CURDATE()) - YEAR(m.Geburtsdatum) AS Schuetze_Alter,
        ak.ID AS AlterskategorieID,
        ak.Bezeichnung AS Alterskategorie,
        ak.minAlter,
        ak.maxAlter
    FROM 
        mitglieder m
    JOIN sAltersKat ak 
        ON YEAR(CURDATE()) - YEAR(m.Geburtsdatum) BETWEEN ak.minAlter AND ak.maxAlter
),
MaxKategorie AS (
    SELECT 
        MitgliedID, 
        MAX(AlterskategorieID) AS hoechsteAltersKategorieID
    FROM SchuetzeMitAlterskategorie
    GROUP BY MitgliedID
),
EndKategorie AS (
    SELECT 
        sm.MitgliedID,
        sm.Name,
        sm.Vorname,
        sm.Schuetze_Alter,
        sm.AlterskategorieID,
        sm.Alterskategorie
    FROM 
        SchuetzeMitAlterskategorie sm
    JOIN MaxKategorie mk 
      ON sm.MitgliedID = mk.MitgliedID 
     AND sm.AlterskategorieID = mk.hoechsteAltersKategorieID
)
SELECT 
    ek.MitgliedID,
    ek.Name,
    ek.Vorname,
    ek.Schuetze_Alter,
    ek.Alterskategorie,
    w.Bezeichnung AS Waffe,
    kr.Resultat AS Kranzlimite,
    k.Passe1,
    CASE 
        WHEN k.Passe1 >= kr.Resultat THEN 'Kranz erreicht' 
        ELSE 'Kranz nicht erreicht' 
    END AS Passe1_Status,
    k.Passe2,
    CASE 
        WHEN k.Passe2 >= kr.Resultat THEN 'Kranz erreicht' 
        ELSE 'Kranz nicht erreicht' 
    END AS Passe2_Status,
    k.Passe3,
    CASE 
        WHEN k.Passe3 >= kr.Resultat THEN 'Kranz erreicht' 
        ELSE 'Kranz nicht erreicht' 
    END AS Passe3_Status,
    k.Passe4,
    CASE 
        WHEN k.Passe4 >= kr.Resultat THEN 'Kranz erreicht' 
        ELSE 'Kranz nicht erreicht' 
    END AS Passe4_Status,
    k.Passe5,
    CASE 
        WHEN k.Passe5 >= kr.Resultat THEN 'Kranz erreicht' 
        ELSE 'Kranz nicht erreicht' 
    END AS Passe5_Status
FROM 
    EndKategorie ek
JOIN kantiresultate k ON ek.MitgliedID = k.MitgliedID
JOIN Waffen w ON w.ID = (SELECT WaffenID FROM mitglieder m WHERE m.ID = ek.MitgliedID)
JOIN sKranzLimiten kr ON ek.AlterskategorieID = kr.sAltersKatID AND w.ID = kr.WaffenID
JOIN JMDefinition jm ON kr.JMDefinitionID = jm.ID
WHERE 
    k.Jahr = YEAR(CURDATE()) 
    AND jm.Bezeichnung = 'Bester Kantonalstich'
    AND k.MitgliedID = $mitgliedID
GROUP BY 
    ek.MitgliedID, ek.Name, ek.Vorname, ek.Schuetze_Alter, ek.Alterskategorie, w.Bezeichnung, kr.Resultat, k.Passe1, k.Passe2, k.Passe3, k.Passe4, k.Passe5
ORDER BY ek.Name ASC, ek.Vorname ASC;
 ";
    
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row;
    } else {
        return "Keine Daten gefunden";
    }
    
    $conn->close();
}

function getKKCupFinal($MitgliedID, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    
    // Verwende cupFinalResults um die Top 3 zu ermitteln
    $sql = "SELECT 
        ParticipantID,
        Result,
        LowShot
    FROM cupFinalResults
    WHERE Year = $year
    ORDER BY Result DESC, LowShot DESC
    LIMIT 3";

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result) {
        $conn->close();
        return "0";
    }
    
    $rang = 1;
    $preis = "0";
    while ($row = $result->fetch_assoc()) {
        if ($row['ParticipantID'] == $MitgliedID) {
            switch ($rang) {
                case 1:
                    $preis = "30";
                    break;
                case 2:
                    $preis = "20";
                    break;
                case 3:
                    $preis = "10";
                    break;
            }
            break;
        }
        $rang++;
    }
    
    $conn->close();
    return $preis;
}

function calculatePoints($schuss) {
    if ($schuss >= 91) return 10;
    if ($schuss >= 81) return 9;
    if ($schuss >= 71) return 8;
    if ($schuss >= 61) return 7;
    if ($schuss >= 51) return 6;
    if ($schuss >= 41) return 5;
    if ($schuss >= 31) return 4;
    if ($schuss >= 21) return 3;
    if ($schuss >= 11) return 2;
    if ($schuss >= 1) return 1;
    return 0;
}

function getKKEndschiessenGesamt($MitgliedID, $kat, $year = null)
{
    if ($year === null) {
        $year = date('Y');
    }
    
    $sql = "SELECT
        m.ID,
        m.Name,
        m.Vorname,
        m.Geburtsdatum,
        COALESCE(ROUND(GREATEST(g.GSchuss1, g.GSchuss2, g.GSchuss3)/10,1), 0) AS GlueckTotal,
        COALESCE(SUM(e.Schuss1 + e.Schuss2 + e.Schuss3 + e.Schuss4 + e.Schuss5 + e.Schuss6 + e.Schuss7 + e.Schuss8 + e.Schuss9 + e.Schuss10), 0) AS EndstichTotal,
        COALESCE(ROUND(SUM(k.KSchuss1 + k.KSchuss2 + k.KSchuss3 + k.KSchuss4 + k.KSchuss5) / 10, 1), 0) AS KunstTotal, 
        GREATEST(
            COALESCE(s.P1Schuss1, 0) + COALESCE(s.P1Schuss2, 0) + COALESCE(s.P1Schuss3, 0) + COALESCE(s.P1Schuss4, 0) + COALESCE(s.P1Schuss5, 0) + COALESCE(s.P1Schuss6, 0),
            COALESCE(s.P2Schuss1, 0) + COALESCE(s.P2Schuss2, 0) + COALESCE(s.P2Schuss3, 0) + COALESCE(s.P2Schuss4, 0) + COALESCE(s.P2Schuss5, 0) + COALESCE(s.P2Schuss6, 0)
        ) as MaxSchwini,
        z.ZSchuss1, z.ZSchuss2, z.ZSchuss3, z.ZSchuss4, z.ZSchuss5, z.ZSchuss6
    FROM
        mitglieder m
    LEFT JOIN endstich e ON m.ID = e.MitgliedID AND e.Jahr = $year
    LEFT JOIN schwini s ON m.ID = s.MitgliedID AND s.Jahr = $year
    LEFT JOIN kunst k ON m.ID = k.MitgliedID AND k.Jahr = $year
    LEFT JOIN glueck g ON m.ID = g.MitgliedID AND g.Jahr = $year
    LEFT JOIN zabig z ON m.ID = z.MitgliedID AND z.Jahr = $year
    LEFT JOIN Waffen w ON w.ID = m.WaffenID
    WHERE w.Kategorie = '$kat' AND e.Schuss1 != 0
    GROUP BY
        m.ID, m.Vorname, m.Name, m.Geburtsdatum
    ORDER BY
        m.Geburtsdatum ASC";

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query($sql);
    
    if (!$result) {
        $conn->close();
        return "0";
    }
    
    // Daten sammeln und ZabigTotal berechnen
    $data = array();
    while ($row = $result->fetch_assoc()) {
        // Berechne ZabigTotal
        $zabigTotal = 0;
        for ($i = 1; $i <= 6; $i++) {
            $schuss = $row['ZSchuss' . $i];
            if ($schuss !== null) {
                $zabigTotal += calculatePoints($schuss);
            }
        }
        
        // Berechne GesamtTotal
        $gesamtTotal = $row['EndstichTotal'] + $row['GlueckTotal'] + 
                       $zabigTotal + $row['KunstTotal'] + $row['MaxSchwini'];
        
        $data[] = array(
            'ID' => $row['ID'],
            'GesamtTotal' => $gesamtTotal,
            'EndstichTotal' => $row['EndstichTotal'],
            'Geburtsdatum' => $row['Geburtsdatum']
        );
    }
    
    // Sortiere nach GesamtTotal DESC, EndstichTotal DESC, Geburtsdatum ASC
    usort($data, function($a, $b) {
        if ($a['GesamtTotal'] != $b['GesamtTotal']) {
            return $b['GesamtTotal'] - $a['GesamtTotal'];
        }
        if ($a['EndstichTotal'] != $b['EndstichTotal']) {
            return $b['EndstichTotal'] - $a['EndstichTotal'];
        }
        return strcmp($a['Geburtsdatum'], $b['Geburtsdatum']);
    });
    
    // Prüfe ob Mitglied in den ersten 3 ist
    $rang = 1;
    $preis = "0";
    foreach ($data as $row) {
        if ($row['ID'] == $MitgliedID && $rang <= 3) {
            $preis = "10";
            break;
        }
        $rang++;
    }
    
    $conn->close();
    return $preis;
}