<?php
// csv_parser.php - CSV Parsing Logik
class CSVParser {
    
    /**
     * Parse CSV content und extrahiere Programme 133 und 134
     */
    public static function parseCSV($csvContent) {
        $lines = explode("\n", $csvContent);
        $programs133 = [];
        $programs134 = [];
        $allPrograms = [];
        
        $currentProgram = null;
        
        for ($i = 0; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            
            // Check if it's a program header line
            if ($line && !str_starts_with($line, 'Nr;') && str_contains($line, 'Total:')) {
                $parts = explode(';', $line);
                $programNumber = $parts[0];
                
                // Extract total value
                preg_match('/Total:\s*(\d+)/', $line, $totalMatch);
                $total = $totalMatch ? intval($totalMatch[1]) : 0;
                
                // Save previous program if it was 133 or 134
                if ($currentProgram) {
                    self::saveProgram($currentProgram, $programs133, $programs134);
                }
                
                // Start new program
                $currentProgram = [
                    'number' => $programNumber,
                    'total' => $total,
                    'fullLine' => $line,
                    'lineNumber' => $i + 1,
                    'title' => isset($parts[1]) ? $parts[1] : '',
                    'datetime' => isset($parts[2]) ? $parts[2] : '',
                    'data' => []
                ];
                
                // Track all programs
                if (!isset($allPrograms[$programNumber])) {
                    $allPrograms[$programNumber] = [];
                }
                $allPrograms[$programNumber][] = [
                    'total' => $total,
                    'line' => $i + 1
                ];
            }
            // Collect data lines for current program
            else if ($currentProgram && $line && !str_starts_with($line, 'Nr;')) {
                $parts = explode(';', $line);
                if (count($parts) >= 4) {
                    $currentProgram['data'][] = [
                        'nr' => $parts[0],
                        'wertung' => isset($parts[3]) ? $parts[3] : null
                    ];
                }
            }
        }
        
        // Don't forget the last program
        if ($currentProgram) {
            self::saveProgram($currentProgram, $programs133, $programs134);
        }
        
        return [
            'programs133' => $programs133,
            'programs134' => $programs134,
            'allPrograms' => $allPrograms,
            'totalLines' => count($lines)
        ];
    }
    
    /**
     * Helper function to save program to correct array
     */
    private static function saveProgram($program, &$programs133, &$programs134) {
        if ($program['number'] === '133' && $program['total'] > 0) {
            $programs133[] = $program;
        } else if ($program['number'] === '134' && $program['total'] > 0) {
            $programs134[] = $program;
        }
    }
    
    /**
     * Validiere die Programme für den Import
     */
    public static function validatePrograms($programs133, $programs134) {
        $warnings = [];
        $errors = [];
        
        // Prüfe Anzahl Programme 133
        if (count($programs133) > 4) {
            $warnings[] = sprintf(
                "Es wurden %d Programme 133 gefunden (erwartet: max. 4). Bitte wähle die zu importierenden Programme aus.",
                count($programs133)
            );
        } else if (count($programs133) == 0) {
            $warnings[] = "Keine Programme 133 gefunden.";
        }
        
        // Prüfe Anzahl Programme 134
        if (count($programs134) > 4) {
            $warnings[] = sprintf(
                "Es wurden %d Programme 134 gefunden (erwartet: max. 4). Bitte wähle die zu importierenden Programme aus.",
                count($programs134)
            );
        } else if (count($programs134) == 0) {
            $warnings[] = "Keine Programme 134 gefunden.";
        }
        
        // Prüfe ob überhaupt etwas importiert werden kann
        if (count($programs133) == 0 && count($programs134) == 0) {
            $errors[] = "Keine importierbaren Programme gefunden.";
        }
        
        return [
            'valid' => empty($errors),
            'warnings' => $warnings,
            'errors' => $errors
        ];
    }
    
    /**
     * Bereite Programme für den Import vor
     */
    public static function prepareForImport($programs, $programNumber, $maxCount = 4) {
        $prepared = [];
        $passeMapping = [];
        
        if ($programNumber === '133') {
            // 133er gehen in ungerade Passen: 1, 3, 5, 7
            $passeMapping = [1, 3, 5, 7];
        } else if ($programNumber === '134') {
            // 134er gehen in gerade Passen: 2, 4, 6, 8
            $passeMapping = [2, 4, 6, 8];
        }
        
        foreach ($programs as $index => $program) {
            if ($index >= $maxCount) break;
            
            $prepared[] = [
                'program' => $program,
                'passe' => $passeMapping[$index],
                'index' => $index + 1,
                'selected' => true
            ];
        }
        
        return $prepared;
    }
}
?>