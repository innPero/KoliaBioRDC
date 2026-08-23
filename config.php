<?php
session_start();

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