<?
include "../config.php";

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

// Beispielnutzung
print_r(checkKantiKranzStatus(112101, $conn));
?>