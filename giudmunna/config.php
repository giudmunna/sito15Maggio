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

// Sessione: gestisce login/ruoli + carrello
session_start();

// SESSIONE: inizializza carrello (persistenza tra pagine)
if (!isset($_SESSION['carrello']) || !is_array($_SESSION['carrello'])) {
    $_SESSION['carrello'] = [];
}

// -----------------------------
// Funzioni carrello (tutte basate su $_SESSION['carrello'])
// -----------------------------

// Conta gli articoli (somma quantità) per badge header
function carrello_count(): int
{
    $tot = 0;
    foreach ($_SESSION['carrello'] as $r) {
        $tot += max(1, (int)($r['quantita'] ?? 1));
    }
    return $tot;
}

// Trova l'indice della riga di un prodotto nel carrello, -1 se assente
function carrello_find_index(int $id_prodotto): int
{
    foreach ($_SESSION['carrello'] as $i => $r) {
        if ((int)($r['id_prodotto'] ?? 0) === $id_prodotto) {
            return (int)$i;
        }
    }
    return -1;
}

// Aggiunge un prodotto al carrello (se già presente incrementa la quantità)
function carrello_aggiungi(int $id_prodotto, int $quantita = 1): void
{
    $quantita = max(1, $quantita);
    $i = carrello_find_index($id_prodotto);
    if ($i >= 0) {
        $_SESSION['carrello'][$i]['quantita'] = max(1, (int)$_SESSION['carrello'][$i]['quantita']) + $quantita;
    } else {
        $_SESSION['carrello'][] = ['id_prodotto' => $id_prodotto, 'quantita' => $quantita];
    }
}

// Rimuove completamente un prodotto dal carrello
function carrello_rimuovi(int $id_prodotto): void
{
    $i = carrello_find_index($id_prodotto);
    if ($i >= 0) {
        array_splice($_SESSION['carrello'], $i, 1);
    }
}

// Aggiorna la quantità: se < 1 equivale a rimozione
function carrello_aggiorna_quantita(int $id_prodotto, int $quantita): void
{
    if ($quantita < 1) {
        carrello_rimuovi($id_prodotto);
        return;
    }
    $i = carrello_find_index($id_prodotto);
    if ($i >= 0) {
        $_SESSION['carrello'][$i]['quantita'] = $quantita;
    }
}

// Svuota il carrello
function carrello_svuota(): void
{
    $_SESSION['carrello'] = [];
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
