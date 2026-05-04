<?php
// -----------------------------
// chi-siamo.php
// Pagina informativa statica ("Perché noi?"):
// - include config + header/footer
// - contenuto testuale senza query DB
// -----------------------------
include 'config.php';
$page_title = 'Perché Giudmunna | Chi siamo';
include 'header.php';
?>

<section class="content-page">
  <h1>Perché scegliere Giudmunna?</h1>
  <p>Giudmunna è un progetto didattico di e-commerce dedicato alla vendita di iPhone ricondizionati. L'obiettivo è proporre prodotti affidabili, con schede chiare e informazioni trasparenti per aiutare l'utente nella scelta.</p>
  <ul>
    <li><strong>Controlli tecnici:</strong> ogni dispositivo viene verificato prima della pubblicazione nel catalogo.</li>
    <li><strong>Qualità del servizio:</strong> il sito permette registrazione utente, gestione profilo e acquisto online.</li>
    <li><strong>Trasparenza:</strong> capacità, colore, grado estetico e prezzo sono indicati in modo esplicito.</li>
  </ul>

  <h2 style="color:var(--primary-green);margin-top:2rem;">Dove siamo</h2>
  <p>La sede operativa di riferimento è a <strong>Caltanissetta (CL)</strong>. Il servizio è orientato principalmente alla vendita online, con assistenza ai clienti tramite email.</p>
  <ul>
    <li>Ragione sociale del progetto: Giudmunna (progetto didattico)</li>
    <li>Località: Caltanissetta (CL)</li>
    <li>Email: info@giudmunna.it</li>
  </ul>

  <!-- 
  prof appena riesco capire come funziona lo metto,
   su w3scholl non mi e' chiaro , poi cerco un tutorial
   ora mi siddia, questo e' un promemoria]
  <div class="map-placeholder">

  </div>
  -->
</section>

<?php include 'footer.php'; ?>
