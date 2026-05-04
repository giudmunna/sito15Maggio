<?php
// -----------------------------
// catalogo.php
// Mostra l'elenco completo dei prodotti e abilita una ricerca client-side.
// - carica config + header
// - legge tutti i prodotti dal DB (join standard)
// - stampa card con attributo data-search usato dal JS per filtrare
// -----------------------------
include 'config.php';
$page_title = 'Catalogo | Giudmunna';
include 'header.php';

// Caricamento prodotti dal DB (tutte le varianti disponibili)
$prodotti = [];
$select = prodotti_select_fields($conn);
$res = $conn->query("SELECT $select " . prodotti_join_clause() . " ORDER BY modello, capacita, colore, grado_estetico, p.id_prodotto");
if ($res) {
    // Converte il result set in array associativo
    while ($row = $res->fetch_assoc()) {
        $prodotti[] = $row;
    }
}

// Liste uniche usate per i filtri del pannello sinistro.
$modelli = [];
$capacita_list = [];
$colori = [];
$condizioni = [];
foreach ($prodotti as $p) {
    $modelli[$p['modello']] = true;
    $capacita_list[$p['capacita']] = true;
    $colori[$p['colore']] = true;
    $condizioni[$p['grado_estetico']] = true;
}
krsort($capacita_list);
ksort($modelli);
ksort($colori);
ksort($condizioni);
?>

<!-- Titolo pagina -->
<div class="section-title-wrap" id="catalogo">
  <h2>Catalogo iPhone ricondizionati</h2>
</div>

<div class="catalog-layout">
  <?php if (count($prodotti) > 0): ?>
  <aside class="catalog-filters" aria-label="Filtri catalogo">
    <form id="catalog-filters-form" class="catalog-filters-form">
      <h3>Filtri</h3>

      <label for="filter-model">Modello</label>
      <select id="filter-model" data-catalog-filter="model">
        <option value="">Tutti i modelli</option>
        <?php foreach (array_keys($modelli) as $modello): ?>
          <option value="<?php echo htmlspecialchars($modello); ?>"><?php echo htmlspecialchars($modello); ?></option>
        <?php endforeach; ?>
      </select>

      <label for="filter-price-min">Fascia prezzo (€)</label>
      <div class="catalog-price-row">
        <input type="number" id="filter-price-min" min="0" step="1" placeholder="Min" data-catalog-filter="price-min">
        <input type="number" id="filter-price-max" min="0" step="1" placeholder="Max" data-catalog-filter="price-max">
      </div>

      <label for="filter-storage">Spazio di archiviazione</label>
      <select id="filter-storage" data-catalog-filter="storage">
        <option value="">Tutti</option>
        <option value="0-128">Fino a 128 GB</option>
        <option value="129-256">Da 129 a 256 GB</option>
        <option value="257-2048">Oltre 256 GB</option>
      </select>

      <label for="filter-battery">Batteria</label>
      <select id="filter-battery" data-catalog-filter="battery">
        <option value="">Tutte</option>
        <option value="certificata">Certificata</option>
      </select>

      <label for="filter-color">Colore</label>
      <select id="filter-color" data-catalog-filter="color">
        <option value="">Tutti i colori</option>
        <?php foreach (array_keys($colori) as $colore): ?>
          <option value="<?php echo htmlspecialchars($colore); ?>"><?php echo htmlspecialchars($colore); ?></option>
        <?php endforeach; ?>
      </select>

      <label for="filter-condition">Condizione</label>
      <select id="filter-condition" data-catalog-filter="condition">
        <option value="">Tutte le condizioni</option>
        <?php foreach (array_keys($condizioni) as $condizione): ?>
          <option value="<?php echo htmlspecialchars($condizione); ?>"><?php echo htmlspecialchars($condizione); ?></option>
        <?php endforeach; ?>
      </select>

      <button type="button" id="catalog-filters-reset" class="btn btn-ghost btn-block">Azzera filtri</button>
    </form>
  </aside>
  <?php endif; ?>

  <div class="catalog-results">
    <?php if (count($prodotti) > 0): ?>
    <!-- Barra strumenti: input ricerca (il filtro viene gestito da js/site.js) -->
    <div class="catalog-toolbar">
      <label for="catalog-search">Cerca nel catalogo</label>
      <input type="search" id="catalog-search" name="catalog_search" placeholder="Modello, memoria, colore, condizione…" autocomplete="off" enterkeyhint="search">
    </div>
    <?php endif; ?>

    <!-- Griglia prodotti: ogni card ha data-search per filtrare senza nuove query -->
    <section class="product-grid" aria-label="Elenco prodotti" data-catalog-grid>
      <?php if (count($prodotti) === 0): ?>
        <p style="grid-column: 1/-1;">Nessun prodotto disponibile al momento.</p>
      <?php else: ?>
        <?php foreach ($prodotti as $p): ?>
          <?php
          // Stringa “cercabile” (in minuscolo) usata dal filtro client-side
          $searchBlob = strtolower(
              trim($p['modello'] . ' ' . $p['capacita'] . ' ' . $p['colore'] . ' ' . $p['grado_estetico'] . ' ' . $p['prezzo'])
          );
          ?>
          <article
            class="product-card"
            data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES, 'UTF-8'); ?>"
            data-modello="<?php echo htmlspecialchars($p['modello'], ENT_QUOTES, 'UTF-8'); ?>"
            data-capacita="<?php echo htmlspecialchars($p['capacita'], ENT_QUOTES, 'UTF-8'); ?>"
            data-colore="<?php echo htmlspecialchars($p['colore'], ENT_QUOTES, 'UTF-8'); ?>"
            data-condizione="<?php echo htmlspecialchars($p['grado_estetico'], ENT_QUOTES, 'UTF-8'); ?>"
            data-prezzo="<?php echo htmlspecialchars((string)((float)$p['prezzo']), ENT_QUOTES, 'UTF-8'); ?>"
            data-batteria="certificata"
          >
            <?php // Immagine: da DB (path) o placeholder se non presente ?>
            <?php $imgPath = trim((string)($p['path'] ?? '')); ?>
            <?php if ($imgPath !== ''): ?>
              <img class="product-image" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($p['modello']); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg';">
            <?php else: ?>
              <div class="img-placeholder"><?php echo htmlspecialchars($p['modello']); ?></div>
            <?php endif; ?>
            <!-- Dati principali -->
            <h3><?php echo htmlspecialchars($p['modello']); ?></h3>
            <p class="price-tag">€<?php echo number_format((float)$p['prezzo'], 2, ',', '.'); ?></p>
            <p style="font-size:0.9rem;color:#555;margin:0 0 12px;">
              <?php echo htmlspecialchars($p['capacita']); ?> · <?php echo htmlspecialchars($p['colore']); ?> · <?php echo htmlspecialchars($p['grado_estetico']); ?>
            </p>
            <!-- Link alla pagina prodotto con opzioni e acquisto -->
            <a href="prodotto.php?id=<?php echo (int)$p['id_prodotto']; ?>" class="btn btn-primary">Dettagli e acquisto</a>
          </article>
        <?php endforeach; ?>
        <!-- Mostrato solo quando il filtro nasconde tutte le card -->
        <p id="catalog-empty-filter" class="catalog-no-match" hidden>Nessun prodotto compatibile con i filtri selezionati.</p>
      <?php endif; ?>
    </section>
  </div>
</div>

<?php include 'footer.php'; ?>
