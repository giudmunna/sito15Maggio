<?php
// -----------------------------
// carrello.php
// Endpoint + pagina carrello:
// - gestisce azioni POST (add/update/remove/clear) aggiornando $_SESSION['carrello']
// - ricalcola le righe leggendo i prodotti dal DB (prezzo, modello, varianti)
// - mostra riepilogo e link al checkout
// -----------------------------
include 'config.php';

// Azioni sul carrello (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Tutte le operazioni mutative del carrello richiedono token CSRF valido.
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        header('Location: carrello.php');
        exit;
    }
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        // Aggiunge prodotto e poi reindirizza (sanificando il nome pagina)
        // Carrello DB legato a cliente: senza login non possiamo associare la riga.
        if (!isset($_SESSION['id_utente'])) {
            header('Location: login.php?next=' . urlencode('carrello.php'));
            exit;
        }
        $pid = (int)($_POST['id_prodotto'] ?? 0);
        $q = (int)($_POST['quantita'] ?? 1);
        if ($pid > 0) {
            carrello_aggiungi($pid, $q);
        }
        $to = $_POST['redirect'] ?? 'carrello.php';
        if (!preg_match('/^[a-z0-9_\\-\\.]+\\.php$/i', $to)) {
            $to = 'carrello.php';
        }
        header('Location: ' . $to);
        exit;
    }

    if ($action === 'update') {
        // Aggiorna quantità di una riga
        $pid = (int)($_POST['id_prodotto'] ?? 0);
        $q = (int)($_POST['quantita'] ?? 1);
        if ($pid > 0) {
            carrello_aggiorna_quantita($pid, $q);
        }
        header('Location: carrello.php');
        exit;
    }

    if ($action === 'remove') {
        // Rimuove una riga
        $pid = (int)($_POST['id_prodotto'] ?? 0);
        if ($pid > 0) {
            carrello_rimuovi($pid);
        }
        header('Location: carrello.php');
        exit;
    }

    if ($action === 'clear') {
        // Svuota tutto il carrello
        carrello_svuota();
        header('Location: carrello.php');
        exit;
    }
}

// Render pagina
$page_title = 'Il tuo carrello | Giudmunna';
include 'header.php';

// Carrello persistente su DB (legato al cliente loggato)
$righe = [];
$totale = 0.0;
$rows = carrello_righe_dettaglio();
// Totali sempre ricalcolati dal prezzo DB corrente (no fiducia lato client).
foreach ($rows as $p) {
    $qty = max(1, (int)($p['quantita'] ?? 1));
    $sub = (float)$p['prezzo'] * $qty;
    $totale += $sub;
    $righe[] = ['prodotto' => $p, 'quantita' => $qty, 'subtotale' => $sub];
}
?>

<!-- UI carrello -->
<section class="cart-container">
  <h1>Il tuo carrello</h1>
  <?php if (!isset($_SESSION['id_utente'])): ?>
    <p>Per usare il carrello devi accedere. <a href="login.php?next=<?php echo urlencode('carrello.php'); ?>">Vai al login</a>.</p>
  <?php elseif (carrello_cliente_corrente_id() <= 0): ?>
    <p>Completa prima i tuoi dati anagrafici. <a href="profilo.php">Vai al profilo</a>.</p>
  <?php elseif (count($righe) === 0): ?>
    <p>Il carrello è vuoto. <a href="catalogo.php">Sfoglia il catalogo</a>.</p>
  <?php else: ?>

    <?php foreach ($righe as $r): ?>
      <?php $p = $r['prodotto']; ?>
      <div class="cart-item">
        <div class="cart-item-inner">
          <?php $imgPath = trim((string)($p['path'] ?? '')); ?>
          <?php if ($imgPath !== ''): ?>
            <img class="cart-thumb-image" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($p['modello']); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg';">
          <?php else: ?>
            <div class="img-placeholder cart-thumb"><?php echo htmlspecialchars($p['modello']); ?></div>
          <?php endif; ?>
          <div>
            <h3 style="margin:0 0 8px;"><?php echo htmlspecialchars($p['modello']); ?></h3>
            <p style="margin:0;color:#555;font-size:0.95rem;">
              Colore: <?php echo htmlspecialchars($p['colore']); ?> |
              Memoria: <?php echo htmlspecialchars($p['capacita']); ?> |
              Condizione: <?php echo htmlspecialchars($p['grado_estetico']); ?>
            </p>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:10px;">
          <strong>€<?php echo number_format($r['subtotale'], 2, ',', '.'); ?></strong>
          <!-- Form update quantità -->
          <form method="post" class="cart-qty-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id_prodotto" value="<?php echo (int)$p['id_prodotto']; ?>">
            <label>Qtà <input type="number" name="quantita" value="<?php echo (int)$r['quantita']; ?>" min="1" max="99"></label>
            <button type="submit" class="btn btn-ghost" style="padding:8px 14px;">Aggiorna</button>
          </form>
          <!-- Form rimozione riga -->
          <form method="post" onsubmit="return confirm('Rimuovere questo articolo?');">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="id_prodotto" value="<?php echo (int)$p['id_prodotto']; ?>">
            <button type="submit" class="btn btn-outline" style="padding:8px 14px;">Rimuovi</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>

    <!-- Riepilogo totale e CTA -->
    <div class="cart-summary">
      <h3>Totale: €<?php echo number_format($totale, 2, ',', '.'); ?></h3>
      <div class="cart-actions">
        <!-- Svuota carrello -->
        <form method="post" onsubmit="return confirm('Svuotare il carrello?');">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="clear">
          <button type="submit" class="btn btn-outline">Svuota carrello</button>
        </form>
        <!-- Checkout (richiede login + profilo completo) -->
        <a href="checkout.php" class="btn btn-primary">Procedi al pagamento</a>
      </div>
    </div>
  <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
