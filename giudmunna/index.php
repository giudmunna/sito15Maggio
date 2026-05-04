<?php
// Pagina home "Giudmunna":
// - carica configurazione e layout comune (header/footer)
// - legge dal DB una piccola anteprima di prodotti (LIMIT 3)
// - stampa sezioni HTML (hero, features, griglia prodotti) usando i dati letti
include 'config.php';

// Titolo pagina usato nel template `header.php` (es. nel tag <title>)
$page_title = 'Giudmunna | iPhone ricondizionati';
include 'header.php';

// Array che conterrà le righe prodotto per la sezione "In evidenza"
$anteprima = [];

// Costruzione dinamica della query: campi e join sono delegati a funzioni helper
// (così la logica SQL resta centralizzata e riutilizzabile)
$select = prodotti_select_fields($conn);

// Query: prende i primi 3 prodotti ordinati per id
$res = $conn->query("SELECT $select " . prodotti_join_clause() . " ORDER BY p.id_prodotto ASC LIMIT 3");
if ($res) {
    // Trasforma il result set in un array PHP semplice ($anteprima)
    while ($row = $res->fetch_assoc()) {
        $anteprima[] = $row;
    }
}
?>

<section class="hero">
  <div class="hero-text">
    <h1>L'iPhone che ami,<br>amico del pianeta.</h1>
    <p>Ricondizionati professionali con garanzia 12 mesi, test rigorosi e massima trasparenza.</p>
    <div class="hero-actions">
      <a href="#prodotti" class="btn btn-primary">Scopri i modelli</a>
      <a href="catalogo.php" class="btn btn-outline">Tutto il catalogo</a>
    </div>
  </div>

  <!-- Il div mantiene le dimensioni originali definite nel tuo CSS -->
  <div class="img-placeholder">
    <img src="image/copertina.png" 
         alt="Anteprima iPhone" 
         style="width: 100%; height: 100%; object-fit: cover; display: block;">
  </div>
</section>



<!-- STRISCIA FEATURE: tre punti di valore (qualità/garanzia/sostenibilità) -->
<section class="features-strip">
  <div class="feature">
    <h2>Qualità testata</h2>
    <p>Controlli tecnici su ogni dispositivo prima della spedizione.</p>
  </div>
  <div class="feature">
    <h2>Garanzia 12 mesi</h2>
    <p>Acquisti coperti da garanzia Giudmunna.</p>
  </div>
  <div class="feature">
    <h2>Sostenibilità</h2>
    <p>Dai nuova vita agli smartphone e riduci gli sprechi.</p>
  </div>
</section>

<!-- Titolo sezione + CTA al catalogo. L'id "prodotti" è l'ancora usata nel link del hero. -->
<div class="section-title-wrap" id="prodotti">
  <h2>In evidenza</h2>
  <a href="catalogo.php" class="btn btn-ghost">Vedi tutti i prodotti</a>
</div>

<!-- GRIGLIA PRODOTTI: stampa 3 card.
     Se il DB ha meno di 3 risultati, mostra card "placeholder" per quelle mancanti. -->
<section class="product-grid" aria-label="Anteprima prodotti">
  <?php for ($i = 0; $i < 3; $i++): ?>
    <?php // Prende il prodotto i-esimo se esiste, altrimenti null ?>
    <?php $p = $anteprima[$i] ?? null; ?>
    <article class="product-card">
      <?php if ($p): ?>
        <?php // Path immagine prodotto (se vuoto si mostra un placeholder) ?>
        <?php $imgPath = trim((string)($p['path'] ?? '')); ?>
        <?php if ($imgPath !== ''): ?>
          <!-- Immagine: src dal DB; fallback via onerror a un'immagine di default -->
          <img class="product-image" src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($p['modello']); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg';">
        <?php else: ?>
          <div class="img-placeholder"><?php echo htmlspecialchars($p['modello']); ?></div>
        <?php endif; ?>

        <!-- Dati principali prodotto -->
        <h3><?php echo htmlspecialchars($p['modello']); ?></h3>
        <p class="price-tag">€<?php echo number_format((float)$p['prezzo'], 2, ',', '.'); ?></p>
        <p style="font-size:0.9rem;color:#555;margin:0 0 12px;">
          <?php echo htmlspecialchars($p['capacita']); ?> · <?php echo htmlspecialchars($p['colore']); ?> · <?php echo htmlspecialchars($p['grado_estetico']); ?>
        </p>

        <!-- Link alla pagina dettaglio prodotto -->
        <a href="prodotto.php?id=<?php echo (int)$p['id_prodotto']; ?>" class="btn btn-primary">Vedi dettagli</a>
      <?php else: ?>
        <!-- Caso: meno di 3 prodotti nel DB -> card vuota per non "rompere" la griglia -->
        <div class="img-placeholder">Prodotto in arrivo</div>
        <h3>Scheda vuota</h3>
        <p class="price-tag">--</p>
        <p style="font-size:0.9rem;color:#555;margin:0 0 12px;">Nessun prodotto disponibile</p>
        <span class="btn btn-ghost" style="opacity:0.6;cursor:not-allowed;">Non disponibile</span>
      <?php endif; ?>
    </article>
  <?php endfor; ?>
</section>

<?php // Footer comune (chiusura layout + eventuali script condivisi) ?>
<?php include 'footer.php'; ?>
