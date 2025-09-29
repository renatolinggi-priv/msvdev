<?php
// debug_munition.php - Debug-Tool für Munitionskauf API

// Fehler anzeigen für Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h2>Munitionskauf API Debug</h2>";
echo "<pre>";

// 1. Check database connection
echo "1. Database Connection Test:\n";
echo "----------------------------\n";
try {
    $dbFile = __DIR__ . '/../dbconnect.inc.php';
    
    if (!file_exists($dbFile)) {
        echo "ERROR: DB file not found at: " . $dbFile . "\n";
    } else {
        echo "OK: DB file found\n";
        
        require_once $dbFile;
        
        if (isset($conn)) {
            echo "OK: Connection variable exists\n";
            
            if ($conn->connect_error) {
                echo "ERROR: " . $conn->connect_error . "\n";
            } else {
                echo "OK: Connected to database\n";
                
                // Test query
                $result = $conn->query("SELECT 1 as test");
                if ($result) {
                    echo "OK: Test query successful\n";
                } else {
                    echo "ERROR: Test query failed - " . $conn->error . "\n";
                }
            }
        } else {
            echo "ERROR: Connection variable not set\n";
        }
    }
} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n2. Table Structure Check:\n";
echo "-------------------------\n";

// Check if tables exist
if (isset($conn)) {
    $tables = ['mitglieder', 'munitionskauf', 'munitionskauf_details'];
    
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "OK: Table '$table' exists\n";
            
            // Show columns
            $cols = $conn->query("DESCRIBE $table");
            if ($cols) {
                echo "    Columns: ";
                $colNames = [];
                while ($col = $cols->fetch_assoc()) {
                    $colNames[] = $col['Field'];
                }
                echo implode(', ', $colNames) . "\n";
            }
        } else {
            echo "ERROR: Table '$table' NOT found\n";
        }
    }
}

echo "\n3. Session Check:\n";
echo "-----------------\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
echo "Session ID: " . session_id() . "\n";
echo "Session Status: " . session_status() . "\n";
echo "CSRF Token exists: " . (isset($_SESSION['csrf_token']) ? 'Yes' : 'No') . "\n";
if (isset($_SESSION['csrf_token'])) {
    echo "CSRF Token (first 10 chars): " . substr($_SESSION['csrf_token'], 0, 10) . "...\n";
}

echo "\n4. PHP Configuration:\n";
echo "----------------------\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Error Reporting: " . error_reporting() . "\n";
echo "Display Errors: " . ini_get('display_errors') . "\n";
echo "Log Errors: " . ini_get('log_errors') . "\n";
echo "Error Log: " . ini_get('error_log') . "\n";

echo "\n5. Test API Calls:\n";
echo "------------------\n";

// Test list_mitglieder
if (isset($conn)) {
    echo "Testing list_mitglieder query...\n";
    $sql = "SELECT ID as id, Vorname, Name 
            FROM mitglieder 
            WHERE Status = 1 
            ORDER BY Name, Vorname 
            LIMIT 5";
    
    $result = $conn->query($sql);
    
    if ($result) {
        echo "OK: Query successful, found " . $result->num_rows . " records\n";
        while ($row = $result->fetch_assoc()) {
            echo "    - " . $row['Name'] . " " . $row['Vorname'] . " (ID: " . $row['id'] . ")\n";
        }
    } else {
        echo "ERROR: Query failed - " . $conn->error . "\n";
    }
}

echo "</pre>";

// Link to test actual API
echo "<h3>Test API Endpoints:</h3>";
echo "<ul>";
echo "<li><a href='munitionskauf_api.php?action=list_mitglieder' target='_blank'>Test list_mitglieder</a></li>";
echo "<li><a href='munitionskauf_api.php?action=get_statistics&jahr=2024' target='_blank'>Test get_statistics</a></li>";
echo "<li><a href='munitionskauf_api.php?action=get_bestellungen&jahr=2024&filter=year' target='_blank'>Test get_bestellungen</a></li>";
echo "</ul>";
?>
