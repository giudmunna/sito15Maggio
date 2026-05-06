<?php
// -----------------------------
// admin_prodotti.php
// Pannello admin: inserimento prodotti.
// - richiede login + ruolo admin
// - carica liste (modelli/capacità/colori/gradi) per le select
// - permette upload immagine o URL (validato)
// - inserisce nuova variante in tabella prodotti (evita duplicati)
// -----------------------------
include 'config.php';

// Accesso: solo utenti autenticati
// SESSIONE: admin solo se loggato
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('admin_prodotti.php'));
    exit;
}

// Autorizzazione: solo admin
// SESSIONE: ruolo per autorizzare (admin/utente)
if (($_SESSION['ruolo'] ?? 'utente') !== 'admin') {
    $page_title = 'Accesso negato | Giudmunna';
    include 'header.php';
    echo '<section class="content-page"><h1>Accesso negato</h1><p>Questa pagina è riservata agli amministratori.</p></section>';
    include 'footer.php';
    exit;
}

$messaggio = '';
$tipoMessaggio = 'info';

// Helper escaping HTML
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Crea la cartella uploads/ se non esiste (per upload file)
function ensure_uploads_dir(string $dirAbs): bool
{
    if (is_dir($dirAbs)) {
        return true;
    }
    return @mkdir($dirAbs, 0755, true);
}

// Normalizza prezzo inserito dall'admin (accetta virgola o punto, max 2 decimali)
function normalize_price(string $input): ?float
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    // Limite semplice per evitare payload eccessivi/non realistici.
    if (strlen($input) > 32) {
        return null;
    }
    $input = str_replace(['€', ' '], '', $input);
    $input = str_replace(',', '.', $input);
    // Consenti solo formato decimale semplice (max 2 decimali), no notazione scientifica.
    if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $input)) {
        return null;
    }
    $p = (float)$input;
    // Prezzo utile di business: > 0 e con tetto massimo.
    if ($p <= 0 || $p > 999999.99) {
        return null;
    }
    // Arrotonda in modo deterministico a 2 decimali.
    return round($p, 2);
}

// MIME immagine consentiti
function allowed_image_mime_to_ext(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
}

// Genera un nome file univoco per l'upload (token casuale)
function pick_upload_filename(string $extension): string
{
    $token = bin2hex(random_bytes(6));
    return 'prod_' . date('Ymd_His') . '_' . $token . '.' . $extension;
}

// Valida URL immagine (http/https)
function is_valid_image_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array(strtolower((string)$scheme), ['http', 'https'], true)) {
        return false;
    }
    $urlPath = (string)parse_url($url, PHP_URL_PATH);
    return (bool)preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $urlPath);
}

// Carica liste per select (valori ordinati)
$modelli = [];
$capacita = [];
$colori = [];
$gradi = [];

if ($res = $conn->query("SELECT id_modello, nome FROM modelli ORDER BY nome")) {
    $modelli = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}
if ($res = $conn->query("SELECT id_capacita, valore FROM capacita ORDER BY valore")) {
    $capacita = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}
if ($res = $conn->query("SELECT id_colore, nome FROM colori ORDER BY nome")) {
    $colori = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}
if ($res = $conn->query("SELECT id_grado_estetico, descrizione FROM gradi_estetici ORDER BY descrizione")) {
    $gradi = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}

// Mostra ultimi prodotti inseriti (preview)
$lastProducts = [];
$select = prodotti_select_fields($conn);
$res = $conn->query("SELECT $select " . prodotti_join_clause() . " ORDER BY p.id_prodotto DESC LIMIT 12");
if ($res) {
    $lastProducts = $res->fetch_all(MYSQLI_ASSOC);
    $res->free();
}

// POST: inserimento nuovo prodotto/variante
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $messaggio = 'Richiesta non valida. Ricarica la pagina e riprova.';
        $tipoMessaggio = 'error';
    } else {
    $id_modello = (int)($_POST['id_modello'] ?? 0);
    $id_capacita = (int)($_POST['id_capacita'] ?? 0);
    $id_colore = (int)($_POST['id_colore'] ?? 0);
    $id_grado_estetico = (int)($_POST['id_grado_estetico'] ?? 0);
    $prezzo = normalize_price((string)($_POST['prezzo'] ?? ''));
    $path_url = trim((string)($_POST['path_url'] ?? ''));

    if ($id_modello <= 0 || $id_capacita <= 0 || $id_colore <= 0 || $id_grado_estetico <= 0) {
        $messaggio = 'Seleziona modello, capacità, colore e grado estetico.';
        $tipoMessaggio = 'error';
    } elseif ($prezzo === null) {
        $messaggio = 'Prezzo non valido (es. 499,99).';
        $tipoMessaggio = 'error';
    } else {
        // Se tutto ok, prova a determinare il path immagine: upload oppure URL
        $path = '';
        $hasUpload = isset($_FILES['immagine']) && is_array($_FILES['immagine']);
        $fileErr = $hasUpload ? (int)($_FILES['immagine']['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;

        if ($hasUpload && $fileErr !== UPLOAD_ERR_NO_FILE) {
            // Gestione upload file
            if ($fileErr !== UPLOAD_ERR_OK) {
                $messaggio = 'Errore durante il caricamento del file (codice ' . $fileErr . ').';
                $tipoMessaggio = 'error';
            } else {
                $origName = (string)($_FILES['immagine']['name'] ?? '');
                $tmpName = (string)($_FILES['immagine']['tmp_name'] ?? '');
                if ($origName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
                    $messaggio = 'Upload non valido.';
                    $tipoMessaggio = 'error';
                } else {
                    $maxBytes = 5 * 1024 * 1024; // 5MB
                    $size = (int)($_FILES['immagine']['size'] ?? 0);
                    if ($size <= 0 || $size > $maxBytes) {
                        $messaggio = 'Immagine non valida: dimensione massima 5MB.';
                        $tipoMessaggio = 'error';
                    } else {
                        $mimeToExt = allowed_image_mime_to_ext();
                        $detectedMime = '';
                        if (function_exists('finfo_open')) {
                            $finfo = finfo_open(FILEINFO_MIME_TYPE);
                            if ($finfo) {
                                $detectedMime = (string)finfo_file($finfo, $tmpName);
                                finfo_close($finfo);
                            }
                        }
                        if ($detectedMime === '' || !isset($mimeToExt[$detectedMime])) {
                            $messaggio = 'Formato immagine non supportato.';
                            $tipoMessaggio = 'error';
                        } elseif (@getimagesize($tmpName) === false) {
                            $messaggio = 'Il file caricato non è una vera immagine.';
                            $tipoMessaggio = 'error';
                        } else {
                            $uploadsAbs = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
                            if (!ensure_uploads_dir($uploadsAbs)) {
                                $messaggio = 'Impossibile creare la cartella uploads/. Controlla i permessi.';
                                $tipoMessaggio = 'error';
                            } else {
                                $newName = pick_upload_filename($mimeToExt[$detectedMime]);
                                $destAbs = $uploadsAbs . DIRECTORY_SEPARATOR . $newName;
                                if (!move_uploaded_file($tmpName, $destAbs)) {
                                    $messaggio = 'Impossibile salvare l’immagine caricata.';
                                    $tipoMessaggio = 'error';
                                } else {
                                    // Salva path relativo da usare nel frontend
                                    $path = 'uploads/' . $newName;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Se non c'è upload, permette URL immagine
        if ($tipoMessaggio !== 'error' && $path === '') {
            if ($path_url !== '') {
                if (!is_valid_image_url($path_url)) {
                    $messaggio = 'URL immagine non valido (solo http/https).';
                    $tipoMessaggio = 'error';
                } else {
                    $path = $path_url;
                }
            }
        }

        if ($tipoMessaggio !== 'error') {
            // Inserisce nel DB (vincolo unique atteso su combinazione variante)
            $stmt = $conn->prepare("INSERT INTO prodotti (id_modello, id_capacita, id_colore, id_grado_estetico, path, prezzo)
                                    VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iiiisd', $id_modello, $id_capacita, $id_colore, $id_grado_estetico, $path, $prezzo);
            if ($stmt->execute()) {
                $messaggio = 'Prodotto inserito correttamente.';
                $tipoMessaggio = 'success';
                header('Location: admin_prodotti.php?ok=1');
                exit;
            } else {
                if ((int)$conn->errno === 1062) {
                    $messaggio = 'Questa variante esiste già (modello/capacità/colore/grado).';
                } else {
                    $messaggio = 'Errore DB: ' . $conn->error;
                }
                $tipoMessaggio = 'error';
            }
            $stmt->close();
        }
    }
    }
}

// Messaggio post-redirect (PRG pattern semplice)
if (isset($_GET['ok'])) {
    $messaggio = 'Prodotto inserito correttamente.';
    $tipoMessaggio = 'success';
}

// Render pagina
$page_title = 'Admin prodotti | Giudmunna';
include 'header.php';
?>

<section class="content-page">
  <h1>Prodotti (Admin)</h1>

  <p style="color:#555;margin-top:6px;">Qui puoi inserire un nuovo prodotto nel catalogo.</p>

  <?php if ($messaggio): ?>
    <p class="messaggio" style="<?php echo $tipoMessaggio === 'success' ? 'color:#0f7a3b;' : ($tipoMessaggio === 'error' ? 'color:#b00020;' : ''); ?>">
      <?php echo h($messaggio); ?>
    </p>
  <?php endif; ?>

  <form method="post" class="form" enctype="multipart/form-data" style="max-width:720px;">
    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <label>Modello
        <select name="id_modello" required>
          <option value="">Seleziona…</option>
          <?php foreach ($modelli as $m): ?>
            <option value="<?php echo (int)$m['id_modello']; ?>" <?php echo ((int)($_POST['id_modello'] ?? 0) === (int)$m['id_modello']) ? 'selected' : ''; ?>>
              <?php echo h($m['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Capacità
        <select name="id_capacita" required>
          <option value="">Seleziona…</option>
          <?php foreach ($capacita as $c): ?>
            <option value="<?php echo (int)$c['id_capacita']; ?>" <?php echo ((int)($_POST['id_capacita'] ?? 0) === (int)$c['id_capacita']) ? 'selected' : ''; ?>>
              <?php echo h($c['valore']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Colore
        <select name="id_colore" required>
          <option value="">Seleziona…</option>
          <?php foreach ($colori as $c): ?>
            <option value="<?php echo (int)$c['id_colore']; ?>" <?php echo ((int)($_POST['id_colore'] ?? 0) === (int)$c['id_colore']) ? 'selected' : ''; ?>>
              <?php echo h($c['nome']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Grado estetico
        <select name="id_grado_estetico" required>
          <option value="">Seleziona…</option>
          <?php foreach ($gradi as $g): ?>
            <option value="<?php echo (int)$g['id_grado_estetico']; ?>" <?php echo ((int)($_POST['id_grado_estetico'] ?? 0) === (int)$g['id_grado_estetico']) ? 'selected' : ''; ?>>
              <?php echo h($g['descrizione']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <label style="margin-top:10px;">Prezzo (€)
      <input type="number" name="prezzo" inputmode="decimal" min="0.01" max="999999.99" step="0.01" placeholder="es. 499,99" value="<?php echo h((string)($_POST['prezzo'] ?? '')); ?>" required>
    </label>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px;">
      <label>Immagine (upload)
        <input type="file" name="immagine" accept=".jpg,.jpeg,.png,.webp,.gif,image/*">
      </label>

      <label>Oppure URL immagine (http/https)
        <input type="url" name="path_url" placeholder="https://..." value="<?php echo h((string)($_POST['path_url'] ?? '')); ?>">
      </label>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:12px;">Inserisci prodotto</button>
  </form>

  <hr style="margin:22px 0;border:none;border-top:1px solid #eee;">

  <h2 style="margin:0 0 12px;">Ultimi prodotti inseriti</h2>
  <?php if (count($lastProducts) === 0): ?>
    <p>Nessun prodotto presente.</p>
  <?php else: ?>
    <div class="product-grid" style="margin-top:0;">
      <?php foreach ($lastProducts as $p): ?>
        <article class="product-card">
          <?php $imgPath = trim((string)($p['path'] ?? '')); ?>
          <?php if ($imgPath !== ''): ?>
            <img class="product-image" src="<?php echo h($imgPath); ?>" alt="<?php echo h($p['modello']); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://fdn2.gsmarena.com/vv/bigpic/apple-iphone-15.jpg';">
          <?php else: ?>
            <div class="img-placeholder"><?php echo h($p['modello']); ?></div>
          <?php endif; ?>
          <h3><?php echo h($p['modello']); ?></h3>
          <p class="price-tag">€<?php echo number_format((float)$p['prezzo'], 2, ',', '.'); ?></p>
          <p style="font-size:0.9rem;color:#555;margin:0 0 12px;">
            <?php echo h($p['capacita']); ?> · <?php echo h($p['colore']); ?> · <?php echo h($p['grado_estetico']); ?>
          </p>
          <a href="prodotto.php?id=<?php echo (int)$p['id_prodotto']; ?>" class="btn">Apri</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php include 'footer.php'; ?>

