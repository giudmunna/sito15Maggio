<?php
// -----------------------------
// admin_ordini.php
// Pannello admin: lista di tutti gli ordini.
// - richiede login
// - richiede ruolo admin
// - legge ordini con dati utente/cliente
// - legge righe_ordine e le raggruppa per id_ordine
// -----------------------------
include 'config.php';

// Accesso: solo utenti autenticati
// SESSIONE: admin solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('admin_ordini.php'));
    exit;
}

// Autorizzazione: solo admin
// SESSIONE: ruolo per autorizzare (admin/utente)
if (($_SESSION['ruolo'] ?? 'utente') !== 'admin') {
    $page_title = 'Accesso negato | Giudmunna';
    include 'header.php';
    echo '<section class="content-page"><h1>Accesso negato</h1><p>Questa pagina è riservata agli amministratori.</p></section>';
    include 'footer.php';
    exit;
}

// Query principale: ordini + dati cliente/utente
$ordini = [];
$res = $conn->query("SELECT o.id_ordine,
                            o.data_ordine,
                            o.stato,
                            o.totale,
                            u.username,
                            u.email,
                            c.nome,
                            c.cognome,
                            c.citta
                     FROM ordini o
                     JOIN clienti c ON o.id_cliente = c.id_cliente
                     JOIN utenti u ON c.id_utente = u.id_utente
                     ORDER BY o.data_ordine DESC, o.id_ordine DESC");
if ($res) {
    $ordini = $res->fetch_all(MYSQLI_ASSOC);
}

// Query righe ordine (una sola query IN) e raggruppamento per ordine
$righePerOrdine = [];
if (count($ordini) > 0) {
    $ids = array_map(static fn($o) => (int)$o['id_ordine'], $ordini);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));

    $sql = "SELECT ro.id_ordine,
                   ro.quantita,
                   ro.prezzo_unitario,
                   m.nome AS modello,
                   c.valore AS capacita,
                   co.nome AS colore,
                   g.descrizione AS grado_estetico
            FROM righe_ordine ro
            JOIN prodotti p ON ro.id_prodotto = p.id_prodotto
            JOIN modelli m ON p.id_modello = m.id_modello
            JOIN capacita c ON p.id_capacita = c.id_capacita
            JOIN colori co ON p.id_colore = co.id_colore
            JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
            WHERE ro.id_ordine IN ($placeholders)
            ORDER BY ro.id_ordine DESC, ro.id_riga ASC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$ids);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $r) {
        $oid = (int)$r['id_ordine'];
        if (!isset($righePerOrdine[$oid])) {
            $righePerOrdine[$oid] = [];
        }
        $righePerOrdine[$oid][] = $r;
    }
}

// Render pagina
$page_title = 'Admin ordini | Giudmunna';
include 'header.php';
?>

<section class="content-page">
  <h1>Ordini (Admin)</h1>

  <?php if (count($ordini) === 0): ?>
    <p>Nessun ordine presente.</p>
  <?php else: ?>
    <?php foreach ($ordini as $o): ?>
      <?php $oid = (int)$o['id_ordine']; ?>
      <div class="cart-item" style="margin-bottom:14px;">
        <div class="cart-item-inner" style="align-items:flex-start;gap:16px;">
          <div style="flex:1;">
            <h3 style="margin:0 0 8px;">Ordine #<?php echo (int)$o['id_ordine']; ?></h3>
            <p style="margin:0;color:#555;font-size:0.95rem;">
              Data: <?php echo htmlspecialchars($o['data_ordine']); ?> |
              Stato: <?php echo htmlspecialchars($o['stato']); ?>
            </p>
            <p style="margin:8px 0 0;color:#555;font-size:0.95rem;">
              Cliente: <?php echo htmlspecialchars(trim(($o['nome'] ?? '') . ' ' . ($o['cognome'] ?? ''))); ?>
              (<?php echo htmlspecialchars($o['username']); ?>, <?php echo htmlspecialchars($o['email']); ?>)
              — <?php echo htmlspecialchars($o['citta'] ?? ''); ?>
            </p>
            <?php $righe = $righePerOrdine[$oid] ?? []; ?>
            <?php if (count($righe) > 0): ?>
              <!-- Dettaglio: righe prodotti dell'ordine -->
              <ul style="margin:12px 0 0;padding-left:1.2rem;">
                <?php foreach ($righe as $r): ?>
                  <li>
                    <?php echo htmlspecialchars($r['modello']); ?>
                    (<?php echo htmlspecialchars($r['capacita']); ?>, <?php echo htmlspecialchars($r['colore']); ?>, <?php echo htmlspecialchars($r['grado_estetico']); ?>)
                    × <?php echo (int)$r['quantita']; ?>
                    — €<?php echo number_format((float)$r['prezzo_unitario'] * (int)$r['quantita'], 2, ',', '.'); ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:170px;">
            <strong>Totale: €<?php echo number_format((float)$o['totale'], 2, ',', '.'); ?></strong>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php include 'footer.php'; ?>

