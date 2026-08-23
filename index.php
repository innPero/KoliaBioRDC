<?php
require_once 'config.php';

// --- ACTIONS POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        die("Erreur de sécurité : requête non autorisée.");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_cart') {
        addToCart($_POST['produit_id'] ?? '');
        header('Location: index.php?page=panier');
        exit;
    }

    if ($action === 'update_cart') {
        foreach ($_POST['qty'] ?? [] as $id => $qty) {
            updateCartQty($id, (int)$qty);
        }
        if (empty($_SESSION['flash'])) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Votre panier a été mis à jour.'];
        }
        header('Location: index.php?page=panier');
        exit;
    }

    if ($action === 'remove_cart') {
        removeFromCart($_POST['produit_id'] ?? '');
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Article retiré du panier.'];
        header('Location: index.php?page=panier');
        exit;
    }

    if ($action === 'clear_cart') {
        clearCart();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Votre panier a été entièrement vidé.'];
        header('Location: index.php?page=panier');
        exit;
    }

    if ($action === 'commander') {
        $panier = getCart();
        if (empty($panier)) {
            header('Location: index.php?page=panier');
            exit;
        }

        try {
            $db->beginTransaction();

            // 1. Décrémentation des stocks
            foreach ($panier as $item) {
                $stmt = $db->prepare("SELECT stock, nom FROM products WHERE id = ? FOR UPDATE");
                $stmt->execute([$item['id']]);
                $prod = $stmt->fetch();

                if (!$prod || $prod['stock'] < $item['quantite']) {
                    throw new Exception("Le produit '" . ($prod['nom'] ?? 'Inconnu') . "' n'est plus disponible en quantité suffisante.");
                }

                $stmtUpdate = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmtUpdate->execute([$item['quantite'], $item['id']]);
            }

            // 2. Générer le Code Unique de Retrait (ex: LZL-A89E)
            $code_retrait = 'LZL-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

            $payment_method = $_POST['payment_method'] ?? 'cod';
            $paiement_statut = ($payment_method === 'mobile_money') ? 'Payé (Simulé)' : 'À payer en boutique';
            $mobile_operator = ($payment_method === 'mobile_money') ? ($_POST['mobile_operator'] ?? null) : null;
            $mobile_number = ($payment_method === 'mobile_money') ? ($_POST['mobile_number'] ?? null) : null;
            $order_id = genId();

            // Enregistrement de la commande avec point de retrait et code
            $stmtOrder = $db->prepare("INSERT INTO orders (id, date, client, email, telephone, point_retrait, code_retrait, note, total, statut, paiement_methode, paiement_statut, paiement_operateur, paiement_numero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'en_attente', ?, ?, ?, ?)");
            $stmtOrder->execute([
                $order_id,
                date('Y-m-d H:i:s'),
                trim($_POST['client_nom'] ?? ''),
                trim($_POST['client_email'] ?? ''),
                trim($_POST['client_tel'] ?? ''),
                $_POST['point_retrait'] ?? '', // Point de retrait physique sélectionné
                $code_retrait, // Code unique généré
                trim($_POST['note'] ?? null),
                cartTotal(),
                $payment_method,
                $paiement_statut,
                $mobile_operator,
                $mobile_number
            ]);

            // 3. Enregistrer les articles
            $stmtItem = $db->prepare("INSERT INTO order_items (order_id, product_id, nom, prix, unite, quantite) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($panier as $item) {
                $stmtItem->execute([
                    $order_id,
                    $item['id'],
                    $item['nom'],
                    $item['prix'],
                    $item['unite'],
                    $item['quantite']
                ]);
            }

            $db->commit();
            clearCart();
            
            // Envoi du flash d'information de succès avec le code de retrait généré
            $_SESSION['flash'] = [
                'type' => 'success', 
                'msg' => "Félicitations ! Votre commande est validée. Votre code de retrait unique est : <strong>$code_retrait</strong>. Présentez-le lors de votre retrait en boutique."
            ];
            header('Location: client.php?page=mon-espace');
            exit;

        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash'] = ['type' => 'error', 'msg' => $e->getMessage()];
            header('Location: index.php?page=panier');
            exit;
        }
    }

    if ($action === 'contact_send') {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Votre message a bien été envoyé. Nous vous répondrons sous 48h.'];
        header('Location: index.php?page=contact');
        exit;
    }
}

$page = $_GET['page'] ?? 'accueil';
$search = trim($_GET['q'] ?? '');
$cat = $_GET['cat'] ?? '';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// --- FONCTIONS D'AFFICHAGE ---
function header_site(string $title, string $page): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?> - Ferme Luzolo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .qty-box {
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            border: 1px solid var(--bordure);
            border-radius: 4px;
            padding: 2px;
        }
        .qty-btn {
            background: none;
            border: none;
            width: 28px;
            height: 28px;
            font-size: 16px;
            font-weight: bold;
            color: var(--vert);
            cursor: pointer;
            transition: background 0.2s, color 0.2s;
            border-radius: 3px;
        }
        .qty-btn:hover {
            background: var(--fond);
        }
        .qty-input {
            border: none !important;
            text-align: center;
            font-weight: 600;
            width: 36px !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .qty-input::-webkit-outer-spin-button,
        .qty-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .qty-input[type=number] {
            -moz-appearance: textfield;
        }
        .cart-actions-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a class="logo" href="index.php">🥬 Ferme Luzolo</a>
            <nav class="nav">
                <a href="index.php?page=accueil" <?= $page === 'accueil' ? 'class="active"' : '' ?>>Accueil</a>
                <a href="index.php?page=produits" <?= $page === 'produits' ? 'class="active"' : '' ?>>Produits</a>
                <a href="index.php?page=panier" <?= $page === 'panier' ? 'class="active"' : '' ?>>Panier <?php $c = cartCount(); if ($c > 0) echo "<span style='background:var(--vert);color:#fff;border-radius:2px;padding:1px 6px;font-size:11px;'>$c</span>"; ?></a>
                <a href="index.php?page=contact" <?= $page === 'contact' ? 'class="active"' : '' ?>>Contact</a>
                <a href="client.php" style="background:var(--vert);color:#fff;padding:8px 18px;font-size:11px;letter-spacing:1px;text-transform:uppercase;">Mon Espace</a>
            </nav>
        </div>
    </header>
<?php }

function footer_site(): void { ?>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-grid">
                <div>
                    <div class="footer-brand">🥬 Ferme Luzolo</div>
                    <p class="footer-desc">Cultivée avec passion depuis 2013, notre ferme kinoise vous propose des produits frais, récoltés chaque matin et distribués à travers nos points de retrait de Kinshasa.</p>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">Navigation</div>
                    <a href="index.php?page=produits">Nos produits</a>
                    <a href="index.php?page=panier">Panier</a>
                    <a href="index.php?page=contact">Contact</a>
                    <a href="client.php">Mon Espace</a>
                </div>
                <div class="footer-col">
                    <div class="footer-col-title">Boutiques de retrait</div>
                    <p>🏬 <strong>Gombe :</strong> Boulevard du 30 Juin (Face Immeuble SOZACOM)</p>
                    <p>🏬 <strong>Limete :</strong> 7ème Rue (Près de la Place commerciale)</p>
                    <p>🏬 <strong>Bandal :</strong> Av. Kimbondo (Près du Bloc)</p>
                    <p>🕒 Lun-Sam, 7h30-17h00</p>
                </div>
            </div>
            <div class="footer-bottom">
                <span>© <?= date('Y') ?> Ferme Luzolo. Tous droits réservés.</span>
                <span>Fait à Kinshasa, RDC 🇨🇩</span>
            </div>
        </div>
    </footer>
</body>
</html>
<?php }

function page_accueil(): void {
    global $flash, $db;
    
    $stmt = $db->query("SELECT * FROM products LIMIT 4");
    $vedettes = $stmt->fetchAll();
?>
    <?php if ($flash): ?>
    <div class="container" style="padding-top:20px">
        <div class="flash" style="background:#151b2c; border-left:4px solid var(--vert); padding:16px; margin-bottom:24px; color:#fff;"><?= $flash['msg'] ?></div>
    </div>
    <?php endif; ?>

    <!-- Hero -->
    <div class="hero">
        <img src="https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=1400&h=700&fit=crop" alt="Ferme">
        <div class="hero-overlay">
            <div class="container" style="padding:0;">
                <div class="section-title-sm" style="color:var(--vert); letter-spacing:2px; font-weight:bold; margin-bottom:8px;">CLICK & COLLECT KINSHASA</div>
                <h1>Achetez en ligne,<br>Retirez en <em>Boutique Physique</em></h1>
                <p>Vos fruits et légumes frais produits à Kinshasa, payés en ligne et disponibles sous forme de panier dans la boutique de votre commune.</p>
                <a href="index.php?page=produits" class="btn btn-primary">Découvrir la récolte</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-bar">
        <div class="container">
            <div class="stat"><div class="stat-num">4</div><div class="stat-lbl">Points de retrait</div></div>
            <div class="stat"><div class="stat-num">100%</div><div class="stat-lbl">Circuit court bio</div></div>
            <div class="stat"><div class="stat-num">0 FC</div><div class="stat-lbl">Frais de livraison</div></div>
            <div class="stat"><div class="stat-num">Code</div><div class="stat-lbl">Retrait sécurisé</div></div>
        </div>
    </div>

    <!-- Notre histoire -->
    <div class="section">
        <div class="container">
            <div class="grid-2" style="align-items:center;gap:60px">
                <div>
                    <div class="section-title-sm">Notre concept</div>
                    <h2 class="section-title" style="margin-bottom:20px">Pourquoi choisir le retrait en boutique ?</h2>
                    <p style="color:var(--gris);line-height:1.8;margin-bottom:16px">À Kinshasa, la livraison à domicile est souvent difficile en raison des adresses imprécises ou des embouteillages. Avec la Ferme Luzolo, vous passez commande et réglez en toute sécurité.</p>
                    <p style="color:var(--gris);line-height:1.8;margin-bottom:28px">Nous préparons votre panier frais et vous le récupérez à l'heure qui vous convient dans la boutique la plus proche de chez vous (Gombe, Limete, Bandal ou Kintambo), simplement en présentant votre code unique.</p>
                    <a href="index.php?page=contact" class="btn btn-outline">Nous écrire</a>
                </div>
                <div>
                    <img src="https://images.unsplash.com/photo-1464226184884-fa280b87c399?w=700&h=500&fit=crop" alt="Ferme" style="width:100%;height:350px;object-fit:cover;">
                </div>
            </div>
        </div>
    </div>

    <!-- Produits vedettes -->
    <div class="section section-fond">
        <div class="container">
            <div style="text-align:center;margin-bottom:36px">
                <div class="section-title-sm">Sélection du jour</div>
                <h2 class="section-title">Nos produits <em>frais du matin</em></h2>
            </div>
            <div class="grid-4">
                <?php foreach ($vedettes as $p): ?>
                <div class="card">
                    <img src="<?= escape($p['image']) ?>" alt="<?= escape($p['nom']) ?>">
                    <div class="card-body">
                        <div class="card-cat"><?= escape($p['categorie']) ?></div>
                        <div class="card-nom serif"><?= escape($p['nom']) ?></div>
                        <div class="card-footer" style="margin-top:14px">
                            <div class="card-prix serif"><?= number_format($p['prix'], 0, ',', ' ') ?> FC<small>/<?= escape($p['unite']) ?></small></div>
                            <?php if ($p['stock'] > 0): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_cart">
                                <input type="hidden" name="produit_id" value="<?= escape($p['id']) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">+ Panier</button>
                            </form>
                            <?php else: ?>
                            <span class="btn btn-sm" style="background:#eee;color:#999;cursor:not-allowed">Épuisé</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align:center;margin-top:36px">
                <a href="index.php?page=produits" class="btn btn-outline">Voir tout le catalogue</a>
            </div>
        </div>
    </div>
<?php }

function page_produits(): void {
    global $search, $cat, $flash, $db;
    
    $stmtCats = $db->query("SELECT DISTINCT categorie FROM products");
    $cats = $stmtCats->fetchAll(PDO::FETCH_COLUMN);

    $sql = "SELECT * FROM products WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $sql .= " AND (nom LIKE ? OR description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    if ($cat !== '') {
        $sql .= " AND categorie = ?";
        $params[] = $cat;
    }

    $stmtProds = $db->prepare($sql);
    $stmtProds->execute($params);
    $produits = $stmtProds->fetchAll();
?>
    <div class="page-banner">
        <div class="container">
            <div class="section-title-sm">Catalogue</div>
            <h1 class="serif">Nos <em style="color:var(--vert);font-style:italic">Produits Frais</em></h1>
        </div>
    </div>
    <div class="section">
        <div class="container">
            <?php if ($flash): ?>
            <div class="flash"><?= $flash['msg'] ?></div>
            <?php endif; ?>

            <div class="filters">
                <form method="get" style="display:flex;gap:10px;flex:1;flex-wrap:wrap">
                    <input type="hidden" name="page" value="produits">
                    <input type="text" name="q" placeholder="Rechercher un produit..." value="<?= escape($search) ?>" class="form-input" style="flex:1;min-width:160px">
                    <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
                </form>
                <a href="index.php?page=produits" class="filter-btn <?= $cat === '' ? 'active' : '' ?>">Tous</a>
                <?php foreach ($cats as $c): ?>
                <a href="index.php?page=produits&cat=<?= urlencode($c) ?>" class="filter-btn <?= $cat === $c ? 'active' : '' ?>"><?= escape($c) ?></a>
                <?php endforeach; ?>
            </div>

            <div class="grid-4">
                <?php foreach ($produits as $p): ?>
                <div class="card">
                    <img src="<?= escape($p['image']) ?>" alt="<?= escape($p['nom']) ?>">
                    <div class="card-body">
                        <div class="card-cat"><?= escape($p['categorie']) ?></div>
                        <div class="card-nom serif"><?= escape($p['nom']) ?></div>
                        <div class="card-desc"><?= escape($p['description']) ?></div>
                        <div class="card-stock <?= $p['stock'] <= 5 ? 'low' : '' ?>">
                            <?= $p['stock'] > 5 ? "En stock ({$p['stock']} {$p['unite']})" : ($p['stock'] > 0 ? "⚡ Seulement {$p['stock']} restant(s)" : "❌ Indisponible") ?>
                        </div>
                        <div class="card-footer">
                            <div class="card-prix serif"><?= number_format($p['prix'], 0, ',', ' ') ?> FC<small>/<?= escape($p['unite']) ?></small></div>
                            <?php if ($p['stock'] > 0): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add_cart">
                                <input type="hidden" name="produit_id" value="<?= escape($p['id']) ?>">
                                <button type="submit" class="btn btn-primary btn-sm">+ Panier</button>
                            </form>
                            <?php else: ?>
                            <span class="btn btn-sm" style="background:#eee;color:#999;cursor:not-allowed">Indisponible</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php }

function page_panier(): void {
    global $flash;
    $panier = getCart();
?>
    <div class="page-banner">
        <div class="container">
            <div class="section-title-sm">Votre sélection</div>
            <h1 class="serif">Mon <em style="color:var(--vert);font-style:italic">Panier</em></h1>
        </div>
    </div>
    <div class="section">
        <div class="container">
            <?php if ($flash): ?>
            <div class="flash" style="background:#1e293b; border-left:4px solid var(--vert); padding:16px; margin-bottom:24px; color:#fff;"><?= $flash['msg'] ?></div>
            <?php endif; ?>

            <?php if (empty($panier)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">🛒</div>
                <h2>Votre panier est vide</h2>
                <p>Découvrez nos produits frais de la ferme.</p>
                <a href="index.php?page=produits" class="btn btn-primary">Voir les produits</a>
            </div>
            <?php else: ?>
            <div class="cart-layout">
                <div>
                    <form method="post" id="cart-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_cart">
                        <?php foreach ($panier as $item): ?>
                        <div class="cart-item">
                            <img src="<?= escape($item['image']) ?>" alt="<?= escape($item['nom']) ?>">
                            <div class="cart-item-info">
                                <div class="cart-item-nom"><?= escape($item['nom']) ?></div>
                                <div class="cart-item-prix"><?= number_format($item['prix'], 0, ',', ' ') ?> FC / <?= escape($item['unite']) ?></div>
                            </div>
                            
                            <div class="qty-box">
                                <button type="button" class="qty-btn" onclick="stepQty('<?= escape($item['id']) ?>', -1)">-</button>
                                <input type="number" id="qty_<?= escape($item['id']) ?>" name="qty[<?= escape($item['id']) ?>]" value="<?= (int)$item['quantite'] ?>" min="1" max="99" class="form-input qty-input" readonly>
                                <button type="button" class="qty-btn" onclick="stepQty('<?= escape($item['id']) ?>', 1)">+</button>
                            </div>

                            <div class="cart-total-prix"><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FC</div>
                            <button type="submit" form="remove_<?= escape($item['id']) ?>" class="remove-btn" title="Supprimer">×</button>
                        </div>
                        <?php endforeach; ?>
                        
                        <div class="cart-actions-row">
                            <button type="submit" class="btn btn-outline">🔄 Mettre à jour le panier</button>
                            <button type="submit" form="clear-cart-form" class="btn btn-outline" style="border-color:var(--danger);color:var(--danger);background:none;" onclick="return confirm('Voulez-vous vraiment vider tout votre panier ?');">🗑️ Vider le panier</button>
                        </div>
                    </form>

                    <form method="post" id="clear-cart-form" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="clear_cart">
                    </form>

                    <?php foreach ($panier as $item): ?>
                    <form method="post" id="remove_<?= escape($item['id']) ?>" style="display:none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_cart">
                        <input type="hidden" name="produit_id" value="<?= escape($item['id']) ?>">
                    </form>
                    <?php endforeach; ?>
                </div>
                <div>
                    <div class="order-box">
                        <h3>Récapitulatif</h3>
                        <?php foreach ($panier as $item): ?>
                        <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--bordure)">
                            <span><?= escape($item['nom']) ?> × <?= (int)$item['quantite'] ?></span>
                            <span><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FC</span>
                        </div>
                        <?php endforeach; ?>
                        <div class="order-total">
                            <span>Total</span>
                            <strong><?= number_format(cartTotal(), 0, ',', ' ') ?> FC</strong>
                        </div>
                        <a href="index.php?page=commande" class="btn btn-primary" style="width:100%;display:block;text-align:center">Passer la commande</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function stepQty(id, step) {
        const input = document.getElementById('qty_' + id);
        if (input) {
            let val = parseInt(input.value) || 1;
            val += step;
            if (val < 1) val = 1;
            if (val > 99) val = 99;
            input.value = val;
            document.querySelector('.cart-actions-row button[type="submit"]').style.background = 'var(--vert-pale)';
        }
    }
    </script>
<?php }

function page_commande(): void {
    $panier = getCart();
    if (empty($panier)) { 
        header('Location: index.php?page=panier'); 
        exit; 
    }
?>
    <div class="page-banner">
        <div class="container">
            <div class="section-title-sm">Click & Collect</div>
            <h1 class="serif">Finaliser ma <em style="color:var(--vert);font-style:italic">Commande</em></h1>
        </div>
    </div>
    <div class="section">
        <div class="container">
            <div class="cart-layout">
                <div>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="commander">
                        <div class="panel">
                            <div class="panel-header"><span class="panel-title">Vos coordonnées</span></div>
                            <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Nom complet *</label>
                                    <input type="text" name="client_nom" required class="form-input" placeholder="Jean Dupont" value="<?= escape($_SESSION['client_nom'] ?? '') ?>">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Email *</label>
                                    <input type="email" name="client_email" required class="form-input" placeholder="client@luzolo.cd" value="<?= escape($_SESSION['client_email'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Téléphone de contact *</label>
                                    <input type="tel" name="client_tel" required class="form-input" placeholder="Ex: +243 890 000 000">
                                </div>
                                <div class="form-group" style="margin:0;">
                                    <label class="form-label">Sélectionnez la boutique de retrait physique à Kinshasa *</label>
                                    <select name="point_retrait" required class="form-select">
                                        <option value="🏬 Boutique Gombe (Boulevard du 30 Juin)">🏬 Boutique Gombe (Boulevard du 30 Juin - Face Immeuble SOZACOM)</option>
                                        <option value="🏬 Boutique Limete (7ème Rue)">🏬 Boutique Limete (7ème Rue - Près de la Place commerciale)</option>
                                        <option value="🏬 Boutique Bandalungwa (Avenue Kimbondo)">🏬 Boutique Bandalungwa (Avenue Kimbondo - Près du Bloc)</option>
                                        <option value="🏬 Boutique Kintambo (Rond-point Magasin)">🏬 Boutique Kintambo (Rond-point Magasin)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="panel">
                            <div class="panel-header"><span class="panel-title">Mode de Paiement</span></div>
                            <div style="padding:10px 0;">
                                <label style="display:block; margin-bottom:12px; cursor:pointer;">
                                    <input type="radio" name="payment_method" value="cod" checked onclick="togglePayment(false)"> 💵 Payer cash lors du retrait physique en boutique
                                </label>
                                <label style="display:block; cursor:pointer;">
                                    <input type="radio" name="payment_method" value="mobile_money" onclick="togglePayment(true)"> 📱 Payer d'avance par Mobile Money (M-Pesa, Orange, Airtel)
                                </label>
                            </div>

                            <div id="momo-fields" style="display:none; border-top:1px dashed var(--bordure); padding-top:16px; margin-top:16px;">
                                <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label">Opérateur Mobile Money *</label>
                                        <select name="mobile_operator" id="mobile_operator" class="form-select">
                                            <option value="M-Pesa">M-Pesa (Vodacom)</option>
                                            <option value="Orange Money">Orange Money</option>
                                            <option value="Airtel Money">Airtel Money</option>
                                            <option value="Africell Cash">Africell Cash</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin:0;">
                                        <label class="form-label">Numéro de Téléphone Mobile Money *</label>
                                        <input type="tel" name="mobile_number" id="mobile_number" class="form-input" placeholder="Ex: +243 81 000 0000">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;padding:14px;font-size:13px">Confirmer et payer (<?= number_format(cartTotal(), 0, ',', ' ') ?> FC)</button>
                    </form>
                </div>
                <div>
                    <div class="order-box">
                        <h3>Votre panier</h3>
                        <?php foreach ($panier as $item): ?>
                        <div style="display:flex;justify-content:space-between;font-size:13px;padding:6px 0;border-bottom:1px solid var(--bordure)">
                            <span><?= escape($item['nom']) ?> × <?= (int)$item['quantite'] ?></span>
                            <span><?= number_format($item['prix'] * $item['quantite'], 0, ',', ' ') ?> FC</span>
                        </div>
                        <?php endforeach; ?>
                        <div class="order-total">
                            <span>Total</span>
                            <strong><?= number_format(cartTotal(), 0, ',', ' ') ?> FC</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function togglePayment(show) {
        const fields = document.getElementById('momo-fields');
        const operator = document.getElementById('mobile_operator');
        const number = document.getElementById('mobile_number');
        if (show) {
            fields.style.display = 'block';
            operator.required = true;
            number.required = true;
        } else {
            fields.style.display = 'none';
            operator.required = false;
            number.required = false;
        }
    }
    </script>
<?php }

function page_contact(): void {
    global $flash;
?>
    <div class="page-banner">
        <div class="container">
            <div class="section-title-sm">Nous écrire</div>
            <h1 class="serif">Nous <em style="color:var(--vert);font-style:italic">Contacter</em></h1>
        </div>
    </div>
    <div class="section">
        <div class="container">
            <?php if ($flash): ?>
            <div class="flash flash-<?= escape($flash['type']) ?>"><?= escape($flash['msg']) ?></div>
            <?php endif; ?>
            <div class="grid-2" style="gap:60px;align-items:start">
                <div>
                    <h2 style="font-size:24px;margin-bottom:16px">Envoyez-nous un message</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="contact_send">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Nom</label>
                                <input type="text" name="nom" required class="form-input" placeholder="Votre nom">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" required class="form-input" placeholder="votre@email.cd">
                            </div>
                            <div class="form-group form-full">
                                <label class="form-label">Sujet</label>
                                <input type="text" name="sujet" class="form-input" placeholder="Objet de votre message">
                            </div>
                            <div class="form-group form-full">
                                <label class="form-label">Message</label>
                                <textarea name="message" required class="form-input" rows="6" placeholder="Votre message..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Envoyer le message</button>
                    </form>
                </div>
                <div>
                    <h2 style="font-size:24px;margin-bottom:20px">Ferme Luzolo à Kinshasa</h2>
                    <div style="background:var(--fond);padding:28px;margin-bottom:20px">
                        <p style="font-size:14px;color:var(--gris);line-height:2">
                            📍 <strong>Adresse :</strong> Boulevard du 30 Juin, Kinshasa/Gombe, RDC<br>
                            📞 <strong>Téléphone :</strong> +243 89 123 45 67<br>
                            ✉️ <strong>Email :</strong> contact@fermeluzolo.cd<br>
                            🕒 <strong>Horaires :</strong> Lun-Sam, 7h30-17h00<br>
                            🌐 <strong>Web :</strong> www.fermeluzolo.cd
                        </p>
                    </div>
                    <img src="https://images.unsplash.com/photo-1500651230702-0e2d8a49d4e6?w=700&h=400&fit=crop" alt="Ferme" style="width:100%;height:220px;object-fit:cover;">
                </div>
            </div>
        </div>
    </div>
<?php }

// --- ROUTING & RENDU ---
$titles = [
    'accueil'  => 'Accueil',
    'produits' => 'Produits',
    'panier'   => 'Panier',
    'commande' => 'Commande',
    'contact'  => 'Contact',
];
$title = $titles[$page] ?? 'Ferme Luzolo';

header_site($title, $page);

switch ($page) {
    case 'produits': page_produits(); break;
    case 'panier':   page_panier(); break;
    case 'commande': page_commande(); break;
    case 'contact':  page_contact(); break;
    default:         page_accueil(); break;
}

footer_site();