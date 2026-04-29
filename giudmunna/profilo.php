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
    // Legge i campi inviati dal form e li riutilizza anche per ripopolare la UI.
    $nome = $_POST['nome'] ?? '';
    $cognome = $_POST['cognome'] ?? '';
    $indirizzo = $_POST['indirizzo'] ?? '';
    $citta = $_POST['citta'] ?? '';
    $cap = $_POST['cap'] ?? '';
    $telefono = $_POST['telefono'] ?? '';

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

// Render pagina
$page_title = 'Profilo | Giudmunna';
include 'header.php';
?>
<section class="content-page">
  <h1>I tuoi dati anagrafici</h1>
  <?php if ($messaggio): ?>
    <p class="messaggio"><?php echo htmlspecialchars($messaggio); ?></p>
  <?php endif; ?>

  <!-- Form dati anagrafici: invia POST a questa stessa pagina -->
  <form method="post" class="form">
    <label>Nome
      <input type="text" name="nome" value="<?php echo htmlspecialchars($nome); ?>">
    </label>
    <label>Cognome
      <input type="text" name="cognome" value="<?php echo htmlspecialchars($cognome); ?>">
    </label>
    <label>Indirizzo
      <input type="text" name="indirizzo" value="<?php echo htmlspecialchars($indirizzo); ?>">
    </label>
    <label>Città
      <input type="text" name="citta" value="<?php echo htmlspecialchars($citta); ?>">
    </label>
    <label>CAP
      <input type="text" name="cap" value="<?php echo htmlspecialchars($cap); ?>">
    </label>
    <label>Telefono
      <input type="text" name="telefono" value="<?php echo htmlspecialchars($telefono); ?>">
    </label>
    <button type="submit" class="btn">Salva dati</button>
  </form>
</section>
<?php
include 'footer.php';
?>