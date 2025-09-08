<?php
include 'db_connection.php'; // Assumo che questa apra la connessione $conn MySQLi
session_start();

$errore = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        $errore = "Compila tutti i campi obbligatori.";
    } else {
        $stmt = $conn->prepare("SELECT password FROM Utente WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($hashedPassword);
            $stmt->fetch();

            if (password_verify($password, $hashedPassword)) {
                $_SESSION["loggedin"] = true;
                $_SESSION["utente"] = $username;  // <-- importante: usa 'utente' come nelle altre pagine

                $stmt->close();
                $conn->close();

                header("Location: profile.php");
                exit;
            } else {
                $errore = "Password errata.";
            }
        } else {
            $errore = "Utente non trovato.";
        }

        $stmt->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
</head>
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
<body>
  <div class="container">
    <h2>Login</h2>
    
    <?php if (!empty($errore)): ?>
      <div id="message"><?= htmlspecialchars($errore) ?></div>
    <?php else: ?>
      <div id="message"></div>
    <?php endif; ?>

    <form method="post" action="">
        <label for="loginUsername">Username:</label>
        <input type="text" name="username" id="loginUsername" required>

        <label for="loginPassword">Password:</label>
        <input type="password" name="password" id="loginPassword" required>

        <button type="submit">Accedi</button>
    </form>


    <p>Non hai un account? <a href="register.php">Registrati</a></p>
  </div>
</body>
</html>