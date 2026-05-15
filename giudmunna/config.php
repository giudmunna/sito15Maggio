<?php
// -----------------------------
// config.php
// Punto di ingresso comune: connessione DB + sessione + funzioni condivise
// -----------------------------

// Configurazione database (in locale: host/user/pass/dbname)
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "giudmunna";

// Connessione MySQLi usata da tutte le pagine
$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connessione fallita: " . $conn->connect_error);
}

// Sessione: gestisce login/ruoli + carrello.
// Hardening cookie per ridurre rischio furto/fissazione sessione.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// -----------------------------
// Funzioni carrello (persistente su DB, legato a id_cliente)
// -----------------------------
function carrello_cliente_corrente_id(): int
{
    global $conn;
    static $cached = null;
    // Cache per request: evita query ripetute alla tabella clienti.
    if ($cached !== null) {
        return $cached;
    }
    $idUtente = (int)($_SESSION['id_utente'] ?? 0);
    if ($idUtente <= 0) {
        $cached = 0;
        return 0;
    }
    $stmt = $conn->prepare("SELECT id_cliente FROM clienti WHERE id_utente = ? LIMIT 1");
    if (!$stmt) {
        $cached = 0;
        return 0;
    }
    $stmt->bind_param("i", $idUtente);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $cached = $row ? (int)$row['id_cliente'] : 0;
    return $cached;
}

// Conta gli articoli (somma quantità) per badge header
function carrello_count(): int
{
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantita), 0) AS tot FROM carrello WHERE id_cliente = ?");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)($row['tot'] ?? 0);
}

// Aggiunge un prodotto al carrello (se già presente incrementa la quantità)
function carrello_aggiungi(int $id_prodotto, int $quantita = 1): bool
{
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0 || $id_prodotto <= 0) {
        return false;
    }
    $quantita = max(1, min(99, $quantita));
    // Upsert: se la riga esiste già incrementa quantità (cap massimo 99).
    $stmt = $conn->prepare("INSERT INTO carrello (id_cliente, id_prodotto, quantita)
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE quantita = LEAST(99, quantita + VALUES(quantita))");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("iii", $idCliente, $id_prodotto, $quantita);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Elenco righe carrello completo (con dati prodotto aggiornati)
function carrello_righe_dettaglio(): array
{
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0) {
        return [];
    }
    $pathSelect = prodotti_path_select($conn, 'p');
    // Join completo: restituiamo direttamente i dati prodotto necessari alla UI.
    $sql = "SELECT cart.id_prodotto,
                   cart.quantita,
                   p.id_prodotto,
                   m.nome AS modello,
                   c.valore AS capacita,
                   co.nome AS colore,
                   g.descrizione AS grado_estetico,
                   $pathSelect,
                   p.prezzo
            FROM carrello cart
            JOIN prodotti p ON cart.id_prodotto = p.id_prodotto
            JOIN modelli m ON p.id_modello = m.id_modello
            JOIN capacita c ON p.id_capacita = c.id_capacita
            JOIN colori co ON p.id_colore = co.id_colore
            JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico
            WHERE cart.id_cliente = ?
            ORDER BY cart.id_carrello DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Rimuove completamente un prodotto dal carrello
function carrello_rimuovi(int $id_prodotto): void
{
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0 || $id_prodotto <= 0) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM carrello WHERE id_cliente = ? AND id_prodotto = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("ii", $idCliente, $id_prodotto);
    $stmt->execute();
    $stmt->close();
}

// Aggiorna la quantità: se < 1 equivale a rimozione
function carrello_aggiorna_quantita(int $id_prodotto, int $quantita): void
{
    if ($quantita < 1) {
        carrello_rimuovi($id_prodotto);
        return;
    }
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0 || $id_prodotto <= 0) {
        return;
    }
    // Vincolo applicativo: quantità minima 1, massima 99 per riga.
    $quantita = max(1, min(99, $quantita));
    $stmt = $conn->prepare("UPDATE carrello SET quantita = ? WHERE id_cliente = ? AND id_prodotto = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("iii", $quantita, $idCliente, $id_prodotto);
    $stmt->execute();
    $stmt->close();
}

// Svuota il carrello
function carrello_svuota(): void
{
    global $conn;
    $idCliente = carrello_cliente_corrente_id();
    if ($idCliente <= 0) {
        return;
    }
    $stmt = $conn->prepare("DELETE FROM carrello WHERE id_cliente = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $stmt->close();
}

// -----------------------------
// CSRF helpers (token per form sensibili)
// -----------------------------
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_is_valid(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

// -----------------------------
// Image helpers (hardening output/src)
// -----------------------------
function is_safe_image_src(string $path): bool
{
    $path = trim($path);
    if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return false;
    }

    // Permettiamo file locali in uploads/ con estensioni immagine comuni.
    if (preg_match('#^uploads/[a-zA-Z0-9._-]+\.(?:jpg|jpeg|png|webp|gif)$#i', $path)) {
        return true;
    }

    // Permettiamo URL assoluti solo http/https e con estensione immagine esplicita.
    if (!preg_match('#^https?://#i', $path)) {
        return false;
    }
    if (!filter_var($path, FILTER_VALIDATE_URL)) {
        return false;
    }

    $urlPath = (string)parse_url($path, PHP_URL_PATH);
    return (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $urlPath);
}

function safe_image_src(string $path): string
{
    return is_safe_image_src($path) ? $path : '';
}

// -----------------------------
// Helper prodotti (select/join) per evitare di duplicare SQL in ogni pagina
// -----------------------------

// Rende selezionabile `path` anche se la colonna non esiste (fallback a stringa vuota)
function prodotti_path_select(mysqli $conn, string $alias = 'p'): string
{
    static $selectExprCache = [];
    if (isset($selectExprCache[$alias])) {
        return $selectExprCache[$alias];
    }

    $hasPath = false;
    $res = $conn->query("SHOW COLUMNS FROM prodotti LIKE 'path'");
    if ($res instanceof mysqli_result) {
        $hasPath = $res->num_rows > 0;
        $res->free();
    }

    $selectExprCache[$alias] = $hasPath ? "$alias.path" : "'' AS path";
    return $selectExprCache[$alias];
}

// Campi standard per una card prodotto (modelllo/capacità/colore/grado + path + prezzo)
function prodotti_select_fields(mysqli $conn): string
{
    $pathSelect = prodotti_path_select($conn, 'p');
    return "p.id_prodotto, m.nome AS modello, c.valore AS capacita, co.nome AS colore, g.descrizione AS grado_estetico, $pathSelect, p.prezzo";
}

// JOIN standard per ricostruire la "scheda" prodotto dalle tabelle normalizzate
function prodotti_join_clause(): string
{
    return "FROM prodotti p
            JOIN modelli m ON p.id_modello = m.id_modello
            JOIN capacita c ON p.id_capacita = c.id_capacita
            JOIN colori co ON p.id_colore = co.id_colore
            JOIN gradi_estetici g ON p.id_grado_estetico = g.id_grado_estetico";
}
