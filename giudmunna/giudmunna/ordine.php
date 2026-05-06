<?php
// -----------------------------
// ordine.php
// "Acquisto rapido" di un singolo prodotto (senza carrello):
// - richiede login
// - verifica che esistano dati anagrafici cliente
// - legge i dati del prodotto richiesto
// - al POST crea ordine + riga ordine
// -----------------------------
include 'config.php';

// Accesso: solo utenti autenticati
// SESSIONE: ordine rapido solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header("Location: login.php");
    exit;
}

// SESSIONE: id utente per trovare id_cliente
$id_utente = $_SESSION['id_utente'];
$messaggio = "";
$errore = "";

// Verifico che l'utente abbia i dati anagrafici
$stmt = $conn->prepare("SELECT id_cliente FROM clienti WHERE id_utente = ?");
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$stmt->bind_result($id_cliente);
$ha_cliente = $stmt->fetch();
$stmt->close();

if (!$ha_cliente) {
    $errore = "Per effettuare un ordine devi prima compilare i tuoi dati anagrafici.";
}

// Prodotto richiesto dalla query string
$id_prodotto = intval($_GET['id_prodotto'] ?? 0);
if ($id_prodotto <= 0) {
    $errore = "Nessun prodotto selezionato.";
}

// Recupero dati prodotto
if (!$errore) {
    // Query variante: dati essenziali per riepilogo e calcolo totale
    $stmt = $conn->prepare("SELECT m.nome AS modello, c.valore AS capacita, co.nome AS colore, g.descrizione AS grado_estetico, p.prezzo
                           FROM prodotti p
                           JOIN modelli m ON p.id_modello = m.id_modello
                           JOIN capacita c ON p.id_capacita = c.id_capacita
                           JOIN colori co ON p.id_colore = co.id_colore
                           JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
                           WHERE p.id_prodotto = ?");
    $stmt->bind_param("i", $id_prodotto);
    $stmt->execute();
    $stmt->bind_result($modello, $capacita, $colore, $grado_estetico, $prezzo);
    if (!$stmt->fetch()) {
        $errore = "Prodotto non trovato.";
    }
    $stmt->close();
}

// POST: crea ordine + riga ordine
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errore) {
    $quantita = intval($_POST['quantita'] ?? 1);
    if ($quantita < 1) $quantita = 1;

    // Totale ordine rapido = prezzo unitario corrente * quantità richiesta.
    $totale = $prezzo * $quantita;
    $data_ordine = date('Y-m-d H:i:s');
    $stato = "In lavorazione";

    // Inserisco ordine
    $stmt = $conn->prepare("INSERT INTO ordini (id_cliente, data_ordine, stato, totale) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $id_cliente, $data_ordine, $stato, $totale);
    if ($stmt->execute()) {
        $id_ordine = $stmt->insert_id;
        $stmt->close();

        // Inserisco riga ordine
        $stmt = $conn->prepare("INSERT INTO righe_ordine (id_ordine, id_prodotto, quantita, prezzo_unitario)
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_ordine, $id_prodotto, $quantita, $prezzo);
        if ($stmt->execute()) {
            $messaggio = "Ordine effettuato con successo! Numero ordine: " . $id_ordine;
        } else {
            $errore = "Errore nel salvataggio della riga d'ordine.";
        }
        $stmt->close();
    } else {
        $errore = "Errore nel salvataggio dell'ordine.";
        $stmt->close();
    }
}

// Render pagina
$page_title = 'Ordine | Giudmunna';
include 'header.php';
?>
<section class="content-page">
  <h1>Effettua ordine</h1>

  <!-- Stati: errore / successo / form conferma -->
  <?php if ($errore): ?>
    <p class="messaggio errore"><?php echo htmlspecialchars($errore); ?></p>
    <?php if (!$ha_cliente): ?>
      <p><a class="btn" href="profilo.php">Vai ai dati anagrafici</a></p>
    <?php endif; ?>
  <?php elseif ($messaggio): ?>
    <p class="messaggio successo"><?php echo htmlspecialchars($messaggio); ?></p>
    <p><a class="btn" href="catalogo.php">Torna al catalogo</a></p>
  <?php else: ?>
    <!-- Riepilogo prodotto selezionato -->
    <div class="ordine-dettaglio">
      <h2><?php echo htmlspecialchars($modello); ?></h2>
      <p><strong>Memoria:</strong> <?php echo htmlspecialchars($capacita); ?></p>
      <p><strong>Colore:</strong> <?php echo htmlspecialchars($colore); ?></p>
      <p><strong>Grado estetico:</strong> <?php echo htmlspecialchars($grado_estetico); ?></p>
      <p class="prezzo"><?php echo number_format($prezzo, 2, ',', '.'); ?> €</p>
    </div>

    <!-- Form ordine rapido: invia POST a questa stessa pagina -->
    <form method="post" class="form">
      <label>Quantità
        <input type="number" name="quantita" value="1" min="1">
      </label>
      <button type="submit" class="btn">Conferma ordine</button>
    </form>
  <?php endif; ?>
</section>
<?php
include 'footer.php';
?>