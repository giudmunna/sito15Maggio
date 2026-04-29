<?php
// -----------------------------
// header.php
// Layout condiviso: <head>, header con navigazione, apertura <main>.
// Nota: questo file va incluso DOPO `config.php` (sessione + funzioni).
// -----------------------------

// Titolo pagina: se non definito dalla pagina chiamante, usa un default
$page_title = $page_title ?? 'Giudmunna | iPhone ricondizionati';
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($page_title); ?></title>
  <?php // Cache-busting CSS: cambia querystring quando cambia il file ?>
  <?php $cssV = @filemtime(__DIR__ . DIRECTORY_SEPARATOR . 'style.css') ?: time(); ?>
  <link rel="stylesheet" href="style.css?v=<?php echo (int)$cssV; ?>"> <!-- CSS principale del sito (layout, componenti, responsive) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"> <!-- Libreria icone usata per search/cart e altre icone UI -->
  <link rel="preconnect" href="https://fonts.googleapis.com"> <!-- Pre-connessione al provider dei font per ridurre la latenza -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin> <!-- Pre-connessione al dominio che serve i file font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"> <!-- Import del font "Inter" con i pesi usati nel tema -->
</head>
<body>
<header>
  <!-- Logo/link alla home -->
  <a href="index.php" class="logo">GIUDMUNNA</a>
  <!-- Bottone hamburger (mobile), gestito da `js/site.js` -->
  <button type="button" class="nav-toggle" aria-label="Apri o chiudi menu" aria-expanded="false" aria-controls="nav-main">
    <span class="nav-toggle-bar" aria-hidden="true"></span>
    <span class="nav-toggle-bar" aria-hidden="true"></span>
    <span class="nav-toggle-bar" aria-hidden="true"></span>
  </button>
  <!-- Navigazione principale: voci diverse in base a login/admin -->
  <nav class="nav-main" id="nav-main">
    <a href="index.php#prodotti">Acquista</a>
    <a href="catalogo.php">Catalogo</a>
    <a href="sostenibilita.php">Sostenibilità</a>
    <a href="chi-siamo.php">Perché noi?</a>
    <?php // SESSIONE: se id_utente esiste -> utente autenticato ?>
    <?php if (isset($_SESSION['id_utente'])): ?>
      <!-- Utente autenticato: area personale -->
      <a href="profilo.php">Profilo</a>
      <a href="miei_ordini.php">I miei ordini</a>
      <?php // SESSIONE: se ruolo=admin -> link admin ?>
      <?php if (($_SESSION['ruolo'] ?? 'utente') === 'admin'): ?>
        <!-- Utente admin: pagine gestione -->
        <a href="admin_ordini.php">Admin ordini</a>
        <a href="admin_prodotti.php">Admin prodotti</a>
      <?php endif; ?>
      <a href="logout.php">Esci (<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>)</a>
    <?php endif; ?>
  </nav>
  <!-- Icone: ricerca e carrello (badge da `carrello_count()`) -->
  <div class="header-icons">
    <a href="catalogo.php" class="header-icon-link" title="Cerca nel catalogo"><i class="fas fa-search"></i></a>
    <?php if (!isset($_SESSION['id_utente'])): ?>
      <!-- Ospite: l'icona utente apre il dropdown con accesso/registrazione -->
      <div class="guest-menu">
        <button type="button" class="guest-menu-toggle" aria-label="Apri menu accesso" aria-expanded="false">
          <i class="fas fa-user" aria-hidden="true"></i>
        </button>
        <!-- Questo pannello viene mostrato/nascosto dal JS (`guest-menu--open`) -->
        <div class="guest-menu-dropdown" aria-label="Menu accesso">
          <a href="login.php">Accedi</a>
          <a href="registrazione.php">Registrati</a>
        </div>
      </div>
    <?php endif; ?>
    <a href="carrello.php" class="header-cart"><i class="fas fa-shopping-cart"></i> <span class="cart-badge">(<?php echo carrello_count(); ?>)</span></a>
  </div>
</header>
<main class="page-main">
