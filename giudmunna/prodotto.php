<?php
// -----------------------------
// prodotto.php
// Pagina dettaglio prodotto:
// - valida id da querystring
// - legge la variante selezionata dal DB
// - carica tutte le varianti dello stesso modello
// - costruisce opzioni (capacità/colore/condizione) mostrando solo combinazioni reali
// - permette "aggiungi al carrello" e "acquisto rapido"
// -----------------------------
include 'config.php';

/** Solo combinazioni realmente in catalogo (niente fallback che “sposta” la selezione). */
function trova_variante_esatta(array $varianti, string $cap, string $col, string $gra): ?array
{
    foreach ($varianti as $v) {
        if ($v['capacita'] === $cap && $v['colore'] === $col && $v['grado_estetico'] === $gra) {
            return $v;
        }
    }
    return null;
}

// id prodotto dalla query string
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    // Caso id mancante/non valido: pagina di errore semplice
    $page_title = 'Prodotto | Giudmunna';
    include 'header.php';
    echo '<section class="content-page"><h1>Prodotto non trovato</h1><p><a href="catalogo.php">Torna al catalogo</a></p></section>';
    include 'footer.php';
    exit;
}

// Lettura variante selezionata
$select = prodotti_select_fields($conn);
$stmt = $conn->prepare("SELECT $select " . prodotti_join_clause() . " WHERE p.id_prodotto = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$prodotto = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prodotto) {
    // Prodotto inesistente in DB
    $page_title = 'Prodotto | Giudmunna';
    include 'header.php';
    echo '<section class="content-page"><h1>Prodotto non trovato</h1><p><a href="catalogo.php">Torna al catalogo</a></p></section>';
    include 'footer.php';
    exit;
}

// Carica tutte le varianti dello stesso modello (serve per opzioni cliccabili)
$modello = $prodotto['modello'];
$stmt = $conn->prepare("SELECT $select " . prodotti_join_clause() . " WHERE m.nome = ? ORDER BY c.valore, co.nome, g.descrizione");
$stmt->bind_param("s", $modello);
$stmt->execute();
$varianti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$c = $prodotto['capacita'];
$o = $prodotto['colore'];
$g = $prodotto['grado_estetico'];

// Liste opzioni uniche per dimensione
$capacita_opts = array_values(array_unique(array_column($varianti, 'capacita')));
$colori_opts = array_values(array_unique(array_column($varianti, 'colore')));
$gradi_opts = array_values(array_unique(array_column($varianti, 'grado_estetico')));

// Conta quante opzioni risultano "non disponibili" nella combinazione corrente (per mostrare la legenda)
$conta_non_disp = static function (array $varianti, array $vals, string $c, string $o, string $g, string $dim): int {
    $n = 0;
    foreach ($vals as $x) {
        $cap = $dim === 'cap' ? $x : $c;
        $col = $dim === 'col' ? $x : $o;
        $gra = $dim === 'gra' ? $x : $g;
        if (!trova_variante_esatta($varianti, $cap, $col, $gra)) {
            $n++;
        }
    }
    return $n;
};
$tot_non_disp = $conta_non_disp($varianti, $capacita_opts, $c, $o, $g, 'cap')
    + $conta_non_disp($varianti, $colori_opts, $c, $o, $g, 'col')
    + $conta_non_disp($varianti, $gradi_opts, $c, $o, $g, 'gra');

// Titolo dinamico in base al prodotto selezionato
$page_title = htmlspecialchars($prodotto['modello']) . ' | Giudmunna';
include 'header.php';
?>

<!-- Layout principale: immagine + informazioni + opzioni -->
<div class="product-container">
  <?php $imgPath = trim((string)($prodotto['path'] ?? '')); ?>
  <?php if ($imgPath !== ''): ?>
    <div class="product-detail-image-wrap">
      <img class="product-detail-image" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($prodotto['modello']); ?>" loading="eager" width="800" height="1000" decoding="async" onerror="this.onerror=null;this.src='https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg';">
    </div>
  <?php else: ?>
    <div class="product-detail-image-wrap product-detail-image-wrap--empty">
      <div class="img-placeholder"><?php echo htmlspecialchars($prodotto['modello']); ?></div>
    </div>
  <?php endif; ?>

  <div class="product-info">
    <h1><?php echo htmlspecialchars($prodotto['modello']); ?></h1>
    <div class="price">€<?php echo number_format((float)$prodotto['prezzo'], 2, ',', '.'); ?></div>

    <!-- Opzioni: ogni voce disponibile è un link che ricarica la pagina con un id_prodotto diverso -->
    <div class="option-group">
      <p><strong>Capacità</strong></p>
      <div class="option-list">
        <?php foreach ($capacita_opts as $cap): ?>
          <?php $v = trova_variante_esatta($varianti, $cap, $o, $g); ?>
          <?php if ($v): ?>
            <a class="opt<?php echo $cap === $c ? ' active' : ''; ?>" href="prodotto.php?id=<?php echo (int)$v['id_prodotto']; ?>"><?php echo htmlspecialchars($cap); ?></a>
          <?php else: ?>
            <span class="opt opt-unavailable" title="Non in catalogo con colore e condizione attuali: scegli altre varianti." aria-label="<?php echo htmlspecialchars($cap . ', non disponibile con questa combinazione'); ?>"><?php echo htmlspecialchars($cap); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="option-group">
      <p><strong>Colore</strong></p>
      <div class="option-list">
        <?php foreach ($colori_opts as $col): ?>
          <?php $v = trova_variante_esatta($varianti, $c, $col, $g); ?>
          <?php if ($v): ?>
            <a class="opt<?php echo $col === $o ? ' active' : ''; ?>" href="prodotto.php?id=<?php echo (int)$v['id_prodotto']; ?>"><?php echo htmlspecialchars($col); ?></a>
          <?php else: ?>
            <span class="opt opt-unavailable" title="Non in catalogo con capacità e condizione attuali: scegli altre varianti." aria-label="<?php echo htmlspecialchars($col . ', non disponibile con questa combinazione'); ?>"><?php echo htmlspecialchars($col); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="option-group">
      <p><strong>Condizione estetica</strong></p>
      <div class="option-list">
        <?php foreach ($gradi_opts as $grado): ?>
          <?php $v = trova_variante_esatta($varianti, $c, $o, $grado); ?>
          <?php if ($v): ?>
            <a class="opt<?php echo $grado === $g ? ' active' : ''; ?>" href="prodotto.php?id=<?php echo (int)$v['id_prodotto']; ?>"><?php echo htmlspecialchars($grado); ?></a>
          <?php else: ?>
            <span class="opt opt-unavailable" title="Non in catalogo con capacità e colore attuali: scegli altre varianti." aria-label="<?php echo htmlspecialchars($grado . ', non disponibile con questa combinazione'); ?>"><?php echo htmlspecialchars($grado); ?></span>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($tot_non_disp > 0): ?>
      <!-- Legenda: spiega le opzioni disabilitate -->
      <p class="option-legend">Opzioni in grigio: quella combinazione non è in magazzino. Cambia capacità, colore o condizione per vedere cosa è disponibile.</p>
    <?php endif; ?>

    <!-- Form "aggiungi al carrello": POST verso carrello.php con action=add -->
    <form method="post" action="carrello.php">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="id_prodotto" value="<?php echo (int)$prodotto['id_prodotto']; ?>">
      <input type="hidden" name="redirect" value="carrello.php">
      <label style="display:block;margin:16px 0 8px;font-weight:600;">Quantità</label>
      <input type="number" name="quantita" value="1" min="1" max="99" style="width:80px;padding:10px;border-radius:8px;border:1px solid #ddd;">
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">Aggiungi al carrello</button>
    </form>

    <!-- Blocchetto informativo -->
    <div class="product-meta">
      <p>✅ 12 mesi di garanzia Giudmunna</p>
      <p>✅ Spedizione in 48 ore (indicativa)</p>
      <p>✅ Batteria controllata</p>
    </div>

    <!-- Acquisto rapido: crea un ordine per un singolo prodotto (senza passare dal carrello) -->
    <p style="margin-top:24px;"><a href="ordine.php?id_prodotto=<?php echo (int)$prodotto['id_prodotto']; ?>">Acquisto rapido (un solo articolo, senza carrello)</a></p>
  </div>
</div>

<?php include 'footer.php'; ?>
