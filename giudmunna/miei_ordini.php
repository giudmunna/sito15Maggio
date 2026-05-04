<?php
// -----------------------------
// miei_ordini.php
// Area personale ordini:
// - richiede login
// - risolve id_cliente associato all'utente
// - legge elenco ordini e relative righe (JOIN prodotti/attributi)
// - raggruppa le righe per id_ordine per stamparle sotto ogni ordine
// -----------------------------
include 'config.php';

// Accesso: solo utenti autenticati
// SESSIONE: pagina ordini solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('miei_ordini.php'));
    exit;
}

// SESSIONE: id utente per trovare il cliente
$id_utente = (int)$_SESSION['id_utente'];

// Ricava id_cliente dalla tabella clienti
$stmt = $conn->prepare("SELECT id_cliente FROM clienti WHERE id_utente = ?");
$stmt->bind_param("i", $id_utente);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
$id_cliente = $row ? (int)$row['id_cliente'] : 0;

// Legge gli ordini del cliente
$ordini = [];
if ($id_cliente > 0) {
    $stmt = $conn->prepare("SELECT id_ordine, data_ordine, stato, totale
                            FROM ordini
                            WHERE id_cliente = ?
                            ORDER BY data_ordine DESC, id_ordine DESC");
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();
    $ordini = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Carica le righe ordine per tutti gli ordini trovati (in un'unica query IN (...))
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

    // Raggruppa le righe per id_ordine
    foreach ($rows as $r) {
        $oid = (int)$r['id_ordine'];
        if (!isset($righePerOrdine[$oid])) {
            $righePerOrdine[$oid] = [];
        }
        $righePerOrdine[$oid][] = $r;
    }
}

// Render pagina
$page_title = 'I miei ordini | Giudmunna';
include 'header.php';
?>

<section class="content-page">
  <h1>I miei ordini</h1>

  <!-- Stati: profilo mancante / nessun ordine / lista ordini -->
  <?php if ($id_cliente <= 0): ?>
    <p>Per vedere gli ordini devi prima compilare i tuoi dati anagrafici.</p>
    <p><a class="btn btn-primary" href="profilo.php">Vai al profilo</a></p>
  <?php elseif (count($ordini) === 0): ?>
    <p>Non hai ancora effettuato ordini.</p>
    <p><a class="btn btn-primary" href="catalogo.php">Vai al catalogo</a></p>
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
          <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:160px;">
            <strong>Totale: €<?php echo number_format((float)$o['totale'], 2, ',', '.'); ?></strong>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php include 'footer.php'; ?>

