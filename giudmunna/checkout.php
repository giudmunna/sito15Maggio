<?php
// -----------------------------
// checkout.php
// Checkout "didattico":
// - richiede login
// - verifica che l'utente abbia i dati anagrafici (tabella clienti)
// - ricostruisce il carrello e calcola totale dal DB
// - al POST registra ordine + righe_ordine, poi svuota carrello
// -----------------------------
include 'config.php';

// Accesso: checkout solo per utenti autenticati
// SESSIONE: checkout solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('checkout.php'));
    exit;
}

$id_utente = (int)$_SESSION['id_utente'];
$errore = '';
$messaggio = '';

// Trova id_cliente collegato all'utente (serve per spedizione/fatturazione)
$stmt = $conn->prepare("SELECT id_cliente FROM clienti WHERE id_utente = ?");
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$id_cliente = $row ? (int)$row['id_cliente'] : 0;

if ($id_cliente <= 0) {
    // Se manca il profilo anagrafico, rimanda alla pagina profilo
    $page_title = 'Checkout | Giudmunna';
    include 'header.php';
    echo '<section class="content-page"><h1>Completa il profilo</h1><p>Per procedere servono i dati anagrafici di spedizione.</p><p><a class="btn btn-primary" href="profilo.php">Vai ai dati anagrafici</a></p></section>';
    include 'footer.php';
    exit;
}

// Ricostruisce righe carrello e totale leggendo prezzi dal DB
$righe = [];
$totale = 0.0;
foreach (carrello_righe_dettaglio() as $p) {
    $pid = (int)$p['id_prodotto'];
    $qty = max(1, (int)$p['quantita']);
    $prezzo = (float)$p['prezzo'];
    $sub = $prezzo * $qty;
    $totale += $sub;
    $righe[] = ['id_prodotto' => $pid, 'quantita' => $qty, 'prezzo_unitario' => $prezzo, 'modello' => $p['modello']];
}

// Se carrello vuoto, torna al carrello
if (count($righe) === 0) {
    header('Location: carrello.php');
    exit;
}

// POST: registra ordine e righe ordine
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Anti-CSRF anche in checkout: evita conferme ordine forzate da terzi.
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errore = 'Richiesta non valida. Ricarica la pagina e riprova.';
    } else {
    $data_ordine = date('Y-m-d H:i:s');
    $stato = 'In lavorazione';

    // Inserisce record ordine
    $stmt = $conn->prepare("INSERT INTO ordini (id_cliente, data_ordine, stato, totale) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("issd", $id_cliente, $data_ordine, $stato, $totale);
    if ($stmt->execute()) {
        $id_ordine = (int)$stmt->insert_id;
        $stmt->close();

        $ok = true;
        // Le righe_ordine vengono salvate con prezzo_unitario "fotografato"
        // al momento dell'acquisto (storico ordini coerente nel tempo).
        // Inserisce una riga_ordine per ogni item in carrello
        $ins = $conn->prepare("INSERT INTO righe_ordine (id_ordine, id_prodotto, quantita, prezzo_unitario) VALUES (?, ?, ?, ?)");
        foreach ($righe as $r) {
            $pid = $r['id_prodotto'];
            $q = $r['quantita'];
            $pu = $r['prezzo_unitario'];
            $ins->bind_param("iiid", $id_ordine, $pid, $q, $pu);
            if (!$ins->execute()) {
                $ok = false;
                break;
            }
        }
        $ins->close();

        if ($ok) {
            // Checkout completato: svuota carrello
            carrello_svuota();
            // Messaggio mostrato a schermo dopo la registrazione corretta dell'ordine.
            $messaggio = 'Ordine registrato con successo. Numero ordine: ' . $id_ordine;
        } else {
            $errore = 'Errore nel salvataggio delle righe d\'ordine.';
        }
    } else {
        $errore = 'Errore nel salvataggio dell\'ordine.';
        $stmt->close();
    }
    }
}

$page_title = 'Checkout | Giudmunna';
include 'header.php';
?>

<section class="content-page">
  <h1>Conferma ordine</h1>

  <!-- Tre stati: successo / errore / pagina riepilogo (prima del POST) -->
  <?php if ($messaggio): ?>
    <p class="messaggio successo"><?php echo htmlspecialchars($messaggio); ?></p>
    <p><a class="btn btn-primary" href="catalogo.php">Torna al catalogo</a></p>
  <?php elseif ($errore): ?>
    <p class="messaggio errore"><?php echo htmlspecialchars($errore); ?></p>
    <p><a class="btn btn-primary" href="carrello.php">Torna al carrello</a></p>
  <?php else: ?>
    <p>Riepilogo prima del pagamento (simulazione didattica):</p>
    <ul style="padding-left:1.2rem;">
      <?php foreach ($righe as $r): ?>
        <li>
          <?php echo htmlspecialchars($r['modello']); ?> × <?php echo (int)$r['quantita']; ?>
          — €<?php echo number_format($r['prezzo_unitario'] * $r['quantita'], 2, ',', '.'); ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p><strong>Totale: €<?php echo number_format($totale, 2, ',', '.'); ?></strong></p>

    <!-- Conferma: invia POST per registrare ordine+righe -->
    <form method="post" style="margin-top:24px;">
      <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
      <button type="submit" class="btn btn-primary">Conferma e registra ordine</button>
      <a href="carrello.php" class="btn btn-outline" style="margin-left:12px;">Indietro</a>
    </form>
  <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
