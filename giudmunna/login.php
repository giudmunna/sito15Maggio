<?php
// -----------------------------
// login.php
// Login utente:
// - legge eventuale parametro next (redirect dopo login) e lo valida
// - al POST verifica credenziali su DB
// - gestisce password hashate (password_verify) e migrazione da password in chiaro
// - se ok imposta variabili di sessione e reindirizza
// -----------------------------
include 'config.php';

$messaggio = "";
// Redirect post-login.
// Whitelist: accettiamo solo filename locali con estensione ".php"
// per evitare redirect/URL injection.
$next = $_GET['next'] ?? '';
if (!is_string($next) || !preg_match('/^[a-z0-9_\\-\\.]+\\.php$/i', $next)) {
    $next = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Dati form
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $next_post = $_POST['next'] ?? $next;
    if (!is_string($next_post) || !preg_match('/^[a-z0-9_\\-\\.]+\\.php$/i', $next_post)) {
        $next_post = 'index.php';
    }

    if ($username && $password) {
        // Cerca utente + password salvata (può essere hash moderno o testo in chiaro legacy).
        $stmt = $conn->prepare("SELECT id_utente, password, ruolo FROM utenti WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($id_utente, $password_db, $ruolo_db);
        if ($stmt->fetch()) {
            $ok = false;
            $newHash = null;

            // Distinzione:
            // - `password_hash()` produce stringhe che iniziano con `$` (es. $2y$... per bcrypt)
            // - le password legacy invece sono stringhe "normali"
            if (is_string($password_db) && $password_db !== '' && str_starts_with($password_db, '$')) {
                $ok = password_verify($password, $password_db);

                // Se l'hash non usa più i parametri "migliori" (costo/algoritmo),
                // lo rigeneriamo e lo salviamo dopo il login riuscito.
                if ($ok && password_needs_rehash($password_db, PASSWORD_DEFAULT)) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                }
            } else {
                // Retro-compatibilità: vecchie installazioni con password in chiaro
                $ok = hash_equals((string)$password_db, $password);
                if ($ok) {
                    // Se il plaintext combacia, facciamo migrazione automatica:
                    // salviamo la password come hash moderno (non più in chiaro).
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                }
            }

            /*
             * Importante:
             * "Commands out of sync" succede quando esegui una nuova query (UPDATE)
             * sulla stessa connessione mentre lo statement del SELECT è ancora attivo.
             * Chiudiamo quindi $stmt prima della migrazione.
             */
            $stmt->close();

            // Se abbiamo ottenuto un nuovo hash, facciamo l'UPDATE dopo
            // la chiusura del SELECT per evitare "Commands out of sync" (500).
            if ($newHash !== false && $newHash !== null) {
                $up = $conn->prepare("UPDATE utenti SET password = ? WHERE id_utente = ?");
                if ($up) {
                    $up->bind_param("si", $newHash, $id_utente);
                    $up->execute();
                    $up->close();
                }
            }

            if ($ok) {
                // Previene session fixation dopo autenticazione riuscita.
                session_regenerate_id(true);
                // SESSIONE: memorizza utente loggato
                $_SESSION['id_utente'] = $id_utente;
                // SESSIONE: nome per UI/header
                $_SESSION['username'] = $username;
                // SESSIONE: abilita pagine admin
                $_SESSION['ruolo'] = $ruolo_db ?: 'utente';
                header('Location: ' . $next_post);
                exit;
            }

            $messaggio = "Password errata.";
        } else {
            $messaggio = "Utente non trovato.";
        }
        // Nota: $stmt viene chiuso dentro al ramo fetch(), perché serve prima della migrazione UPDATE.
    } else {
        $messaggio = "Inserisci username e password.";
    }
}

// Render pagina
$page_title = 'Accedi | Giudmunna';
include 'header.php';
?>
<section class="content-page">
  <h1>Login</h1>
  <?php if ($messaggio): ?>
    <p class="messaggio"><?php echo htmlspecialchars($messaggio); ?></p>
  <?php endif; ?>

  <!-- Form login: validazione base lato client in js/site.js -->
  <form method="post" class="form" data-gm-login>
    <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>">
    <label>Username
      <input type="text" name="username" autocomplete="username">
    </label>
    <label>Password
      <input type="password" name="password" autocomplete="current-password">
    </label>
    <button type="submit" class="btn">Accedi</button>
  </form>

  <p>Non hai un account? <a href="registrazione.php">Registrati</a>.</p>
</section>
<?php
include 'footer.php';
?>