<?php
include 'inc/config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];

    // Überprüfen, ob die E-Mail-Adresse existiert
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    // Immer dieselbe Antwort senden, um nicht preiszugeben, ob die E-Mail existiert
    $message = "Wenn die E-Mail-Adresse registriert ist, erhalten Sie eine E-Mail mit weiteren Anweisungen.";

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($user_id);
        $stmt->fetch();

        // Token generieren
        $token = bin2hex(random_bytes(32));
        $expires = date("U") + 3600; // Token ist 1 Stunde gültig

        // Vorher vorhandene Tokens für diesen Benutzer löschen
        $delete_stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $delete_stmt->bind_param("i", $user_id);
        $delete_stmt->execute();
        $delete_stmt->close();

        // Token in der Datenbank speichern
        $insert_stmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("isi", $user_id, $token, $expires);
        $insert_stmt->execute();
        $insert_stmt->close();

        // E-Mail senden
        $reset_link = "https://jahresmeisterschaft.msvwilen.ch/password_reset.php?token=$token";
        $subject = "Passwort zurücksetzen";
        $message_email = "Hallo,\n\nKlicken Sie auf den folgenden Link, um Ihr Passwort zurückzusetzen:\n\n$reset_link\n\nFalls Sie diese Anfrage nicht gestellt haben, ignorieren Sie diese E-Mail bitte.";
        $headers = "From: noreply@msvwilen.ch\r\n";

        mail($email, $subject, $message_email, $headers);
    }

    $stmt->close();
    $conn->close();

    // Antwort im JSON-Format senden
    echo json_encode(['message' => $message]);
}
?>
