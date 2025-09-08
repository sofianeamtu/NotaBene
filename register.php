<?php
include 'db_connection.php';

$errore = "";
$successo = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["regUsername"]);
    $password = trim($_POST["regPassword"]);
    $confirmPassword = trim($_POST["regConfirmPassword"]);

    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $errore = "Compila tutti i campi.";
    } elseif ($password !== $confirmPassword) {
        $errore = "Le password non coincidono.";
    } else {
        // Controlla se l'utente esiste già
        $stmt = $conn->prepare("SELECT username FROM Utente WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errore = "Username già esistente.";
        } else {
            // Crea una password hashata
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Inserisci i dati nel database
            $stmt = $conn->prepare("INSERT INTO Utente (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hashedPassword);

            if ($stmt->execute()) {
                $successo = "Registrazione completata con successo! Ora puoi accedere.";
            } else {
                $errore = "Si è verificato un errore durante la registrazione. Riprova.";
            }
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8" />
  <title>Registrazione</title>
  <style>
    body {
      background: #e8f4f8;
      font-family: Arial, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      color: #333;
    }

    .container {
      background-color: #fff;
      padding: 30px 25px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
      width: 100%;
      max-width: 400px;
      text-align: center;
      box-sizing: border-box;
    }

    h2 {
      margin-bottom: 20px;
      color: #16a085;
      text-align: center;
    }

    #message {
      color: red;
      margin-bottom: 15px;
      font-size: 13px;
      text-align: center;
      min-height: 18px;
    }

    #successMessage {
      color: green;
      margin-bottom: 15px;
      font-size: 13px;
      text-align: center;
      min-height: 18px;
    }

    form {
      display: flex;
      flex-direction: column;
      gap: 15px;
    }

    label {
      text-align: left;
      font-weight: bold;
      margin-bottom: 5px;
      color: #27ae60;
      font-size: 14px;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 10px 8px;
      border-radius: 8px;
      border: 1.5px solid #1abc9c;
      font-size: 14px;
      transition: border-color 0.3s ease;
      outline: none;
      box-sizing: border-box;
    }

    input:focus {
      border-color: #16a085;
      box-shadow: 0 0 5px rgba(22,160,133,0.5);
    }

    button[type="submit"],
    button {
      width: 100%;
      padding: 12px 0;
      margin-top: 10px;
      background-color: #16a085;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: bold;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.25s ease;
    }

    button:hover {
      background-color: #117a65;
    }

    p {
      text-align: center;
      font-size: 14px;
      margin-top: 20px;
    }

    p a {
      color: #16a085;
      text-decoration: none;
    }

    p a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="container">
    <h2>Registrazione</h2>

    <!-- Mostra l'errore, se presente -->
    <?php if (!empty($errore)): ?>
      <div id="message"><?php echo $errore; ?></div>
    <?php endif; ?>

    <!-- Mostra il messaggio di successo, se presente -->
    <?php if (!empty($successo)): ?>
      <div id="successMessage"><?php echo $successo; ?></div>
    <?php endif; ?>

    <form method="POST">
      <label for="regUsername">Username:</label>
      <input type="text" id="regUsername" name="regUsername" required />

      <label for="regPassword">Password:</label>
      <input type="password" id="regPassword" name="regPassword" required />

      <label for="regConfirmPassword">Conferma Password:</label>
      <input type="password" id="regConfirmPassword" name="regConfirmPassword" required />

      <button type="submit">Registrati</button>
    </form>
    <p>Hai già un account? <a href="login.php">Accedi</a></p>
  </div>
</body>
</html>
