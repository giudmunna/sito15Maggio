<?php
// -----------------------------
// profilo.php
// Dati anagrafici cliente:
// - richiede login
// - carica dati presenti da tabella clienti (se esistono)
// - al POST fa INSERT (prima volta) o UPDATE (modifica)
// -----------------------------
include 'config.php';

// Accesso: solo utenti autenticati
// SESSIONE: profilo solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

// SESSIONE: id utente per salvare/leggere dati cliente
$id_utente = $_SESSION['id_utente'];
$messaggio = "";
$id_cliente = null;
$nome = $cognome = $indirizzo = $citta = $cap = $telefono = "";

// Carico eventuali dati già presenti
$stmt = $conn->prepare("SELECT id_cliente, nome, cognome, indirizzo, citta, cap, telefono FROM clienti WHERE id_utente = ?");
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$stmt->bind_result($id_cliente_db, $nome_db, $cognome_db, $indirizzo_db, $citta_db, $cap_db, $telefono_db);
$ha_cliente = $stmt->fetch();
if ($ha_cliente) {
    $id_cliente = $id_cliente_db;
    $nome = $nome_db;
    $cognome = $cognome_db;
    $indirizzo = $indirizzo_db;
    $citta = $citta_db;
    $cap = $cap_db;
    $telefono = $telefono_db;
}
$stmt->close();

// POST: salva i dati (update se record già presente, insert altrimenti)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Legge i campi inviati dal form, ripulisce gli spazi
    // e li riutilizza per ripopolare il form in caso di errore.
    $nome = trim($_POST['nome'] ?? '');
    $cognome = trim($_POST['cognome'] ?? '');
    $indirizzo = trim($_POST['indirizzo'] ?? '');
    $citta = trim($_POST['citta'] ?? '');
    $cap = trim($_POST['cap'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validazione server-side: se un campo manca, non tocca il DB.
    // Questo evita record incompleti anche se il browser ignora il "required".
    if ($nome === '' || $cognome === '' || $indirizzo === '' || $citta === '' || $cap === '' || $telefono === '') {
        $messaggio = "Compila tutti i campi prima di salvare il profilo.";
    } else {
        // Se esiste già una riga cliente facciamo UPDATE, altrimenti INSERT.
        if ($ha_cliente) {
            $stmt = $conn->prepare("UPDATE clienti SET nome=?, cognome=?, indirizzo=?, citta=?, cap=?, telefono=? WHERE id_cliente=?");
            $stmt->bind_param("ssssssi", $nome, $cognome, $indirizzo, $citta, $cap, $telefono, $id_cliente);
        } else {
            $stmt = $conn->prepare("INSERT INTO clienti (id_utente, nome, cognome, indirizzo, citta, cap, telefono)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssss", $id_utente, $nome, $cognome, $indirizzo, $citta, $cap, $telefono);
        }

        if ($stmt->execute()) {
            $messaggio = "Dati anagrafici salvati correttamente.";
            $ha_cliente = true;
        } else {
            $messaggio = "Errore nel salvataggio dei dati.";
        }
        $stmt->close();
    }
}

// Render pagina
$page_title = 'Profilo | Giudmunna';
include 'header.php';
?>
<section class="content-page">
  <h1>I tuoi dati anagrafici</h1>
  <?php if ($messaggio): ?>
    <p class="messaggio"><?php echo htmlspecialchars($messaggio); ?></p>
  <?php endif; ?>

  <!-- Form dati anagrafici: invia POST a questa stessa pagina.
       "required" aiuta lato client, ma il controllo reale resta lato server. -->
  <form method="post" class="form">
    <label>Nome
      <input type="text" name="nome" value="<?php echo htmlspecialchars($nome); ?>" required>
    </label>
    <label>Cognome
      <input type="text" name="cognome" value="<?php echo htmlspecialchars($cognome); ?>" required>
    </label>
    <label>Indirizzo
      <input type="text" name="indirizzo" value="<?php echo htmlspecialchars($indirizzo); ?>" required>
    </label>
    <label>Città
      <input type="text" name="citta" value="<?php echo htmlspecialchars($citta); ?>" required>
    </label>
    <label>CAP
      <input type="text" name="cap" value="<?php echo htmlspecialchars($cap); ?>" required>
    </label>
    <label>Telefono
      <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>" required>
    </label>
    <button type="submit" class="btn">Salva dati</button>
  </form>
</section>
<?php
include 'footer.php';
?>