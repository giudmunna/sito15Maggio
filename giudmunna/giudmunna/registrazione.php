<?php
// -----------------------------
// registrazione.php
// Registrazione utente:
// - al POST valida presenza campi
// - salva password con password_hash (mai in chiaro)
// - inserisce nuovo record in tabella utenti
// -----------------------------
include 'config.php';

$messaggio = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dati form
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username && $email && $password) {
        // Hash password (sicuro)
        $password_salvata = password_hash($password, PASSWORD_DEFAULT);
        if ($password_salvata === false) {
            $messaggio = "Errore durante la creazione della password sicura.";
        } else {
            // Inserisce utente con ruolo base "utente"
            $stmt = $conn->prepare("INSERT INTO utenti (username, email, password, ruolo) VALUES (?, ?, ?, 'utente')");
            $stmt->bind_param("sss", $username, $email, $password_salvata);
            if ($stmt->execute()) {
                $messaggio = "Registrazione avvenuta con successo. Ora puoi effettuare il login.";
            } else {
                $messaggio = "Errore nella registrazione. Forse l'username è già in uso.";
            }
            $stmt->close();
        }

    } else {
        $messaggio = "Compila tutti i campi.";
    }
}

// Render pagina
$page_title = 'Registrati | Giudmunna';
include 'header.php';
?>
<section class="content-page">
  <h1>Registrazione</h1>
  <?php if ($messaggio): ?>
    <p class="messaggio"><?php echo htmlspecialchars($messaggio); ?></p>
  <?php endif; ?>

  <!-- Form registrazione: validazione base lato client in js/site.js -->
  <form method="post" class="form" data-gm-registrazione>
    <label>Username
      <input type="text" name="username" id="username" autocomplete="username">
    </label>
    <label>Email
      <input type="email" name="email" id="email" autocomplete="email">
    </label>
    <label>Password
      <input type="password" name="password" id="password" autocomplete="new-password">
    </label>
    <button type="submit" class="btn">Registrati</button>
  </form>

  <p>Hai già un account? <a href="login.php">Vai al login</a>.</p>
</section>
<?php
include 'footer.php';
?>
