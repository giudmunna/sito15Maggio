</main>
<footer>
  <!-- Footer comune: informazioni e link rapidi -->
  <div class="footer-content">
    <div class="footer-section">
      <h4>GIUDMUNNA</h4>
      <p>La scelta intelligente per te e per il pianeta.</p>
    </div>
    <div class="footer-section">
      <h4>Link utili</h4>
      <a href="chi-siamo.php">Chi siamo</a> <!-- Rimanda alla pagina di presentazione del progetto -->
      <a href="sostenibilita.php">Sostenibilità</a> <!-- Approfondimento su approccio green e ricondizionato -->
      <a href="catalogo.php">Catalogo</a> <!-- Accesso rapido all'elenco prodotti -->
      <a href="carrello.php">Carrello</a> <!-- Accesso diretto agli articoli selezionati -->
    </div>
    <div class="footer-section">
      <h4>Contatti</h4>
      <p>Email: info@giudmunna.it</p>
      <p>Tel: +39 012 345 6789</p>
    </div>
  </div>
  <div class="footer-bottom">
    &copy; 2026 Giudmunna e-commerce. P.IVA di 01825680810 </br>
    Sito web realizzato per motivi didattici e non a fini commerciali
  </div>
</footer>
<?php // Cache-busting JS: cambia querystring quando cambia `js/site.js` ?>
<?php $jsV = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'site.js') ?: time(); ?>
<script src="js/site.js?v=<?php echo (int)$jsV; ?>"></script>
</body>
</html>
