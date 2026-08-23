<?php

$isHttpsRequest =
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $isHttpsRequest ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttpsRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function envOrDefault(string $key, string $default): string {
    $value = getenv($key);
    return ($value === false || $value === '') ? $default : $value;
}

function failRequest(string $message = 'Une erreur est survenue.', int $statusCode = 400): void {
    http_response_code($statusCode);
    exit($message);
}

function logServerError(string $context, string $message): void {
    error_log('[' . $context . '] ' . $message);
}

function sendSecurityHeaders(bool $isHttpsRequest): void {
    if (headers_sent()) {
        return;
    }

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header('X-XSS-Protection: 1; mode=block');

    if ($isHttpsRequest) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

sendSecurityHeaders($isHttpsRequest);

// --- CONFIGURATION DE LA BASE DE DONNÉES ---
define('DB_HOST', envOrDefault('DB_HOST', 'localhost'));
define('DB_NAME', envOrDefault('DB_NAME', 'luzolo_db'));
define('DB_USER', envOrDefault('DB_USER', 'root'));
define('DB_PASS', envOrDefault('DB_PASS', '')); // Par défaut vide sur XAMPP Windows

try {
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    logServerError('DB_CONNECTION', $e->getMessage());
    failRequest('Service temporairement indisponible. Merci de réessayer plus tard.', 500);
}

// --- CONFIGURATION ADMIN PANNEAU ---
define('ADMIN_USER', envOrDefault('ADMIN_USER', 'admin'));
define('ADMIN_PASS', envOrDefault('ADMIN_PASS', 'admin123'));
define('ADMIN_PASS_HASH', envOrDefault('ADMIN_PASS_HASH', ''));
define('SITE_NAME', 'Ferme Luzolo');

// --- SÉCURITÉ CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . escape($_SESSION['csrf_token']) . '">';
}

function validate_csrf(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
}

function requireValidCsrf(string $context = 'CSRF'): void {
    if (!validate_csrf()) {
        logServerError($context, 'Token CSRF invalide.');
        failRequest('Erreur de sécurité. Veuillez actualiser la page et réessayer.', 403);
    }
}

// --- FONCTIONS UTILITAIRES ---
function isAdmin(): bool {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: admin.php');
        exit;
    }
}

function isClient(): bool {
    return isset($_SESSION['client_id']);
}

function verifyAdminPassword(string $input): bool {
    if (ADMIN_PASS_HASH !== '') {
        return password_verify($input, ADMIN_PASS_HASH);
    }

    return hash_equals(ADMIN_PASS, $input);
}

function genId(): string {
    return uniqid('', true);
}

function escape(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date): string {
    return date('d/m/Y', strtotime($date));
}

function isValidEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone(string $phone): bool {
    return preg_match('/^\+?[0-9\s\-\(\)]{8,20}$/', $phone) === 1;
}

function isValidFullName(string $name): bool {
    $name = trim($name);
    if ($name === '' || strlen($name) < 2 || strlen($name) > 100) {
        return false;
    }

    function getClientIp(): string {
        $remote = trim($_SERVER['REMOTE_ADDR'] ?? '');
        return $remote !== '' ? $remote : 'unknown-ip';
    }

    function getLoginIdentifier(string $secondary = ''): string {
        $secondary = strtolower(trim($secondary));
        $base = getClientIp();
        return $secondary === '' ? $base : ($base . '|' . $secondary);
    }

    function readLoginAttemptsStore(string $path): array {
        if (!file_exists($path)) {
            return [];
        }

        $raw = file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    function writeLoginAttemptsStore(string $path, array $store): void {
        $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }
        file_put_contents($path, $json, LOCK_EX);
    }

    function loginAttemptsPath(): string {
        $dir = __DIR__ . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/login_attempts.json';
    }

    function loginAttemptStatus(
        string $scope,
        string $identifier,
        int $maxAttempts = 5,
        int $windowSeconds = 900,
        int $lockSeconds = 900
    ): array {
        $path = loginAttemptsPath();
        $store = readLoginAttemptsStore($path);
        $now = time();
        $ttl = max($windowSeconds, $lockSeconds) * 2;

        foreach ($store as $k => $entry) {
            $last = (int)($entry['last_attempt_at'] ?? 0);
            $lockedUntil = (int)($entry['locked_until'] ?? 0);
            if (($last > 0 && ($now - $last) > $ttl) && ($lockedUntil <= 0 || ($now - $lockedUntil) > $ttl)) {
                unset($store[$k]);
            }
        }

        $key = hash('sha256', $scope . '|' . $identifier);
        $entry = $store[$key] ?? null;

        if (is_array($entry)) {
            $last = (int)($entry['last_attempt_at'] ?? 0);
            $attempts = (int)($entry['attempts'] ?? 0);
            if ($last > 0 && ($now - $last) > $windowSeconds) {
                $attempts = 0;
                $entry['attempts'] = 0;
                $entry['first_attempt_at'] = $now;
            }

            $lockedUntil = (int)($entry['locked_until'] ?? 0);
            if ($lockedUntil > $now) {
                writeLoginAttemptsStore($path, $store);
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => $lockedUntil - $now,
                ];
            }

            if ($lockedUntil > 0 && $lockedUntil <= $now) {
                $entry['locked_until'] = 0;
                $entry['attempts'] = 0;
                $entry['first_attempt_at'] = $now;
                $store[$key] = $entry;
                writeLoginAttemptsStore($path, $store);
                return [
                    'allowed' => true,
                    'remaining' => $maxAttempts,
                    'retry_after' => 0,
                ];
            }

            writeLoginAttemptsStore($path, $store);
            return [
                'allowed' => true,
                'remaining' => max(0, $maxAttempts - $attempts),
                'retry_after' => 0,
            ];
        }

        writeLoginAttemptsStore($path, $store);
        return [
            'allowed' => true,
            'remaining' => $maxAttempts,
            'retry_after' => 0,
        ];
    }

    function registerFailedLoginAttempt(
        string $scope,
        string $identifier,
        int $maxAttempts = 5,
        int $windowSeconds = 900,
        int $lockSeconds = 900
    ): void {
        $path = loginAttemptsPath();
        $store = readLoginAttemptsStore($path);
        $now = time();
        $key = hash('sha256', $scope . '|' . $identifier);

        $entry = $store[$key] ?? [
            'attempts' => 0,
            'first_attempt_at' => $now,
            'last_attempt_at' => $now,
            'locked_until' => 0,
        ];

        if (($now - (int)$entry['last_attempt_at']) > $windowSeconds) {
            $entry['attempts'] = 0;
            $entry['first_attempt_at'] = $now;
            $entry['locked_until'] = 0;
        }

        $entry['attempts'] = (int)$entry['attempts'] + 1;
        $entry['last_attempt_at'] = $now;

        if ((int)$entry['attempts'] >= $maxAttempts) {
            $entry['locked_until'] = $now + $lockSeconds;
        }

        $store[$key] = $entry;
        writeLoginAttemptsStore($path, $store);
    }

    function clearLoginAttempts(string $scope, string $identifier): void {
        $path = loginAttemptsPath();
        $store = readLoginAttemptsStore($path);
        $key = hash('sha256', $scope . '|' . $identifier);
        if (isset($store[$key])) {
            unset($store[$key]);
            writeLoginAttemptsStore($path, $store);
        }
    }
    return preg_match('/^[\p{L}\p{N}\s\-\'.]+$/u', $name) === 1;
}

// --- GESTION DU PANIER ---
function getCart(): array {
    return $_SESSION['cart'] ?? [];
}

function cartCount(): int {
    return array_sum(array_column(getCart(), 'quantite'));
}

function cartTotal(): float {
    $total = 0;
    foreach (getCart() as $item) {
        $total += $item['prix'] * $item['quantite'];
    }
    return $total;
}

function addToCart(string $produitId): void {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$produitId]);
    $produit = $stmt->fetch();

    if (!$produit) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Produit introuvable.'];
        return;
    }
    if ($produit['stock'] <= 0) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Ce produit est actuellement en rupture de stock.'];
        return;
    }

    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    
    if (isset($_SESSION['cart'][$produitId])) {
        if ($_SESSION['cart'][$produitId]['quantite'] < $produit['stock']) {
            $_SESSION['cart'][$produitId]['quantite']++;
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'La quantité a été mise à jour dans le panier.'];
        } else {
            $_SESSION['flash'] = ['type' => 'warning', 'msg' => "Désolé, le stock maximal disponible pour '{$produit['nom']}' est atteint."];
        }
    } else {
        $_SESSION['cart'][$produitId] = [
            'id'       => $produit['id'],
            'nom'      => $produit['nom'],
            'prix'     => $produit['prix'],
            'unite'    => $produit['unite'],
            'image'    => $produit['image'],
            'quantite' => 1,
        ];
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "L'article '{$produit['nom']}' a été ajouté au panier."];
    }
}

function removeFromCart(string $id): void {
    unset($_SESSION['cart'][$id]);
}

function updateCartQty(string $id, int $qty): void {
    global $db;
    if ($qty <= 0) { 
        removeFromCart($id); 
        return; 
    }
    if (isset($_SESSION['cart'][$id])) {
        $stmt = $db->prepare("SELECT stock, nom FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $p = $stmt->fetch();
        
        if ($p) {
            if ($qty > $p['stock']) {
                $_SESSION['cart'][$id]['quantite'] = $p['stock'];
                $_SESSION['flash'] = ['type' => 'warning', 'msg' => "La quantité pour '{$p['nom']}' a été ajustée au stock disponible maximum ({$p['stock']})."];
            } else {
                $_SESSION['cart'][$id]['quantite'] = $qty;
            }
        }
    }
}

function clearCart(): void {
    $_SESSION['cart'] = [];
}