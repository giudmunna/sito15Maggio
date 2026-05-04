<?php
// -----------------------------
// logout.php
// Logout: svuota la sessione e torna alla home.
// -----------------------------
include 'config.php';

// SESSIONE: rimuove dati login+carrello
session_unset();
session_destroy();

header("Location: index.php");
exit;