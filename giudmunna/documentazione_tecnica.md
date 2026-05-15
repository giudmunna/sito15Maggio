1. Sanitizzazione input e output

1.1 Sanitizzazione e validazione lato server

Nelle pagine `login.php` e `registrazione.php` vengono ripuliti i dati inviati dal form:

```php
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
```

Questa operazione elimina spazi inutili prima e dopo il valore.

1.2 Escape dell'output HTML

Per evitare cross-site scripting (XSS) viene usata la funzione `htmlspecialchars()` quando si stampano dati dinamici nell'HTML:

```php
<p class="messaggio"><?php echo htmlspecialchars($messaggio); ?></p>
```

Esempi in molte pagine:
- `catalogo.php`
- `prodotto.php`
- `header.php`
- `carrello.php`
- `checkout.php`

Un helper dedicato è definito in `admin_prodotti.php`:

```php
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
```

Esempio JavaScript di protezione XSS:

```javascript
const userMessage = getMessageFromServer();
const target = document.getElementById('messaggio');
if (target) {
    // Usa textContent per inserire testo senza interpretare HTML.
    target.textContent = userMessage;
}
```

Questo evita di eseguire codice HTML/JavaScript fornito dall'utente, proteggendo la pagina da XSS.

1.3 Validazione di campi specifici

Nel pannello di amministrazione esiste una funzione che normalizza il prezzo inserito:

```php
function normalize_price(string $input): ?float
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (strlen($input) > 32) {
        return null;
    }
    $input = str_replace(['€', ' '], '', $input);
    $input = str_replace(',', '.', $input);
    if (!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/', $input)) {
        return null;
    }
    $p = (float)$input;
    if ($p <= 0 || $p > 999999.99) {
        return null;
    }
    return round($p, 2);
}
```

Questa funzione accetta solo numeri validi e converte la virgola in punto.

1.4 Validazione URL immagini

Per i link alle immagini è presente questa validazione:

```php
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
```

---
2. Gestione delle variabili di sessione

2.1 Avvio e configurazione della sessione

In `config.php` la sessione viene inizializzata con cookie più sicuri:

```php
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
```

Questo riduce il rischio che il cookie di sessione venga letto o usato da script o altri siti.

2.2 Variabili di sessione nel login

Dopo l'autenticazione valida, il sistema salva informazioni importanti in `$_SESSION`:

```php
session_regenerate_id(true);
$_SESSION['id_utente'] = $id_utente;
$_SESSION['username'] = $username;
$_SESSION['ruolo'] = $ruolo_db ?: 'utente';
```

Il `session_regenerate_id(true)` impedisce la session fixation.

2.3 Controllo autorizzazioni

Più pagine controllano l'accesso controllando le variabili di sessione, ad esempio in `admin_prodotti.php`:

```php
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('admin_prodotti.php'));
    exit;
}
if (($_SESSION['ruolo'] ?? 'utente') !== 'admin') {
    // accesso negato per chi non è admin
}
```

E in `checkout.php`:

```php
if (!isset($_SESSION['id_utente'])) {
    header('Location: login.php?next=' . urlencode('checkout.php'));
    exit;
}
```

---

3. Password hash e confronto

3.1 Creazione hash sicuro

In `registrazione.php`, la password viene salvata con `password_hash()`:

```php
$password_salvata = password_hash($password, PASSWORD_DEFAULT);
```

Questo usa algoritmi moderni raccomandati da PHP e non memorizza mai la password in chiaro.

3.2 Verifica password durante il login

In `login.php` la verifica avviene con `password_verify()`:

```php
if (is_string($password_db) && $password_db !== '' && str_starts_with($password_db, '$')) {
    $ok = password_verify($password, $password_db);
    if ($ok && password_needs_rehash($password_db, PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
    }
} else {
    $ok = hash_equals((string)$password_db, $password);
    if ($ok) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
    }
}
```

3.3 Migrazione da password in chiaro

Il progetto supporta anche utenti legacy con password memorizzate in chiaro. Se la password corrisponde, viene rigenerato un hash sicuro e salvato:

```php
if ($ok) {
    $up = $conn->prepare("UPDATE utenti SET password = ? WHERE id_utente = ?");
    if ($up) {
        $up->bind_param("si", $newHash, $id_utente);
        $up->execute();
        $up->close();
    }
}
```

Questo aiuta a migliorare la sicurezza senza forzare subito tutti gli utenti a cambiare password.

---

4. Protezione CSRF

4.1 Generazione token CSRF

In `config.php` è definita la funzione per creare un token unico per il form:

```php
function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
```

4.2 Verifica token CSRF

Per verificare il token inviato dal form:

```php
function csrf_is_valid(?string $token): bool
{
    if (!is_string($token) || $token === '') {
        return false;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}
```

4.3 Uso nei form sensibili

Un esempio in `checkout.php`:

```php
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
```

E la verifica al POST:

```php
if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    $errore = 'Richiesta non valida. Ricarica la pagina e riprova.';
}
```

---

5. Protezione da SQL injection

Il progetto usa query preparate con `bind_param()` in molte parti importanti.
Esempio da `login.php`:

```php
$stmt = $conn->prepare("SELECT id_utente, password, ruolo FROM utenti WHERE username = ?");
$stmt->bind_param("s", $username);
```

Nella registrazione:

```php
$stmt = $conn->prepare("INSERT INTO utenti (username, email, password, ruolo) VALUES (?, ?, ?, 'utente')");
$stmt->bind_param("sss", $username, $email, $password_salvata);
```

Nelle funzioni carrello di `config.php` sono usate preparazioni simili per tutte le query con parametri dinamici.

---

6. Altre parti importanti per interrogazione

6.1 Uso di `session_regenerate_id(true)`

Questa operazione cambia l'ID della sessione dopo il login per impedire che un attaccante riutilizzi il vecchio ID.

6.2 Controllo del ruolo utente

Le pagine admin controllano il ruolo tramite `$_SESSION['ruolo']`.

6.3 Validazione `next` per redirect sicuri

In `login.php` si controlla che il redirect `next` sia un file locale valido:

```php
if (!is_string($next) || !preg_match('/^[a-z0-9_\\-\\.]+\\.php$/i', $next)) {
    $next = 'index.php';
}
```

Questo evita redirect esterni malevoli.
