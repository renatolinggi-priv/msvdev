# Endresultate - Stich-Mapping Dokumentation

## Übersicht der Stich-Codes

Die folgenden Codes werden in der Datenbank-Tabelle `endstich_definition` verwendet und müssen korrekt gemappt werden:

| DB Code      | Name in DB      | HTML Element ID      | Backend Tabelle       | Besonderheit |
|--------------|-----------------|----------------------|-----------------------|--------------|
| END          | Endstich        | #endstichSchuesse    | endstich             | -            |
| SCHWINI_P1   | Schwini P. 1    | #schwiniSchuesse     | schwini              | Beide Passen verwenden die gleiche Tabelle |
| SCHWINI_P2   | Schwini P. 2    | #schwiniSchuesse     | schwini              | Beide Passen verwenden die gleiche Tabelle |
| KUNST        | Kunst           | #kunstSchuesse       | kunst                | -            |
| GLUECK       | Glück           | #glueckSchuesse      | glueck               | -            |
| ZABIG        | Zabig           | #zabigSchuesse       | zabig                | -            |
| DIFF         | Differenzler    | -                    | -                    | Wird separat behandelt (Ansage) |
| SIEUNDER     | Sie und Er      | #sieunderSchuesse    | endresultate_partner | Nur speichern wenn Werte vorhanden |
| PROBE        | Probeschüsse    | -                    | -                    | Kostenlos, 0 CHF |

## Funktionsweise

### Backend (load_schussdaten.php)

1. **Ermittlung gelöster Stiche:**
   - Query auf `endstich_selection` mit JOIN auf `endstich_definition`
   - Liefert Array mit Codes (z.B. ['END', 'SCHWINI_P1', 'KUNST'])

2. **Laden der Daten:**
   - Nur Daten aus Tabellen laden, deren Stich gelöst wurde
   - Spezialfall Schwini: Wenn P1 ODER P2 gelöst → schwini-Tabelle laden

3. **Rückgabe:**
   ```json
   {
     "geloesteStiche": ["END", "SCHWINI_P1", "KUNST"],
     "Schuss1": 8,
     "Schuss2": 9,
     ...
   }
   ```

### Frontend (endresultate.php)

1. **Mapping im JavaScript:**
   ```javascript
   const stichElements = {
       'END': '#endstichSchuesse',
       'SCHWINI_P1': '#schwiniSchuesse',
       'SCHWINI_P2': '#schwiniSchuesse',
       'KUNST': '#kunstSchuesse',
       'GLUECK': '#glueckSchuesse',
       'ZABIG': '#zabigSchuesse',
       'SIEUNDER': '#sieunderSchuesse'
   };
   ```

2. **Aktivierung/Deaktivierung:**
   - Nicht gelöste Stiche: `.disabled` Klasse, Inputs disabled
   - Gelöste Stiche: Klasse entfernt, Inputs enabled
   - Visuelles Feedback: "Nicht gelöst" Badge über deaktivierten Bereichen

### Backend (save_schuss.php)

**Spezialbehandlung "Sie und Er":**
- Nur speichern wenn mindestens ein Wert > 0
- Verhindert leere Einträge in `endresultate_partner`

## Wichtige Hinweise

1. **Schwini-Besonderheit:**
   - Zwei separate Stiche in der Definition (P1 und P2)
   - Aber nur EINE Tabelle (schwini) mit beiden Passen
   - Wenn EINER der beiden Stiche gelöst ist, wird die ganze Tabelle aktiviert

2. **Sie und Er:**
   - Stichcode: `SIEUNDER`
   - Tabelle: `endresultate_partner`
   - Nur speichern wenn Werte eingegeben wurden

3. **Differenzler (DIFF):**
   - Wird nicht als eigenes Eingabefeld behandelt
   - Ist Teil der Zabig-Sektion als "Ansage"

4. **Probeschüsse (PROBE):**
   - Kostenlos (0 CHF)
   - Keine eigene Eingabemaske in Endresultate
   - Vermutlich für Testschüsse vor dem eigentlichen Wettkampf

## Testing-Checkliste

- [ ] Schütze hat nur END gelöst → Nur Endstich editierbar
- [ ] Schütze hat SCHWINI_P1 gelöst → Beide Schwini-Passen editierbar
- [ ] Schütze hat SCHWINI_P2 gelöst → Beide Schwini-Passen editierbar
- [ ] Schütze hat nichts gelöst → Alle Felder deaktiviert
- [ ] Sie und Er Werte eingegeben → Speichert in endresultate_partner
- [ ] Sie und Er leer → Kein Eintrag in endresultate_partner
- [ ] Visuelles Feedback bei deaktivierten Stichen korrekt

## Datenbankstruktur-Abhängigkeiten

Die Lösung erwartet folgende Tabellenstruktur:

**endstich_definition:**
- `id` (int)
- `code` (varchar) - MUSS die oben genannten Codes enthalten
- `name` (varchar)
- `active` (tinyint) - MUSS 1 sein für aktive Stiche

**endstich_selection:**
- `mitglied_id` (int) - NULL für Gäste
- `gast_id` (int) - NULL für Mitglieder
- `jahr` (int)
- `stich_id` (int) - FK zu endstich_definition.id

**Resultat-Tabellen:**
- `endstich` - Endstich-Schüsse
- `schwini` - Schwini P1 und P2
- `kunst` - Kunst
- `glueck` - Glück
- `zabig` - Zabig + Ansage
- `endresultate_partner` - Sie und Er
