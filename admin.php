<?php
require_once 'config.php';

// --- AUTHENTIFICATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
    if (!validate_csrf()) {
        die("Erreur de sécurité CSRF.");
    }
    if ($_POST['username'] === ADMIN_USER && $_POST['password'] === ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php?section=dashboard');
    } else {
        $_SESSION['admin_error'] = 'Identifiants d\'administration incorrects.';
        header('Location: admin.php');
    }
    exit;
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['admin_logged_in']);
    header('Location: admin.php');
    exit;
}

// --- ACTIONS POST SÉCURISÉES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isAdmin()) {
    if (!validate_csrf()) {
        die("Erreur de sécurité CSRF.");
    }

    $action = $_POST['action'] ?? '';

    // Ajouter un produit
    if ($action === 'add_product') {
        $stmt = $db->prepare("INSERT INTO products (id, nom, prix, unite, categorie, stock, description, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $id = genId();
        $stmt->execute([
            $id,
            trim($_POST['nom'] ?? ''),
            max(0, (int)($_POST['prix'] ?? 0)),
            trim($_POST['unite'] ?? ''),
            trim($_POST['categorie'] ?? ''),
            max(0, (int)($_POST['stock'] ?? 0)),
            trim($_POST['description'] ?? ''),
            trim($_POST['image'] ?? 'images/imagesproduits/pondu.jpeg'),
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Produit ajouté avec succès.'];
        header('Location: admin.php?section=produits');
        exit;
    }

    // Modifier un produit
    if ($action === 'edit_product') {
        $stmt = $db->prepare("UPDATE products SET nom = ?, prix = ?, unite = ?, categorie = ?, stock = ?, description = ?, image = ? WHERE id = ?");
        $stmt->execute([
            trim($_POST['nom'] ?? ''),
            max(0, (int)($_POST['prix'] ?? 0)),
            trim($_POST['unite'] ?? ''),
            trim($_POST['categorie'] ?? ''),
            max(0, (int)($_POST['stock'] ?? 0)),
            trim($_POST['description'] ?? ''),
            trim($_POST['image'] ?? ''),
            $_POST['id'] ?? ''
        ]);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Produit modifié avec succès.'];
        header('Location: admin.php?section=produits');
        exit;
    }

    // Supprimer un produit
    if ($action === 'delete_product') {
        $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['id'] ?? '']);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Produit supprimé.'];
        header('Location: admin.php?section=produits');
        exit;
    }

    // Changer statut commande / Valider retrait physique
    if ($action === 'update_order_status') {
        $id = $_POST['order_id'] ?? '';
        $statut = $_POST['statut'] ?? '';
        $valides = ['en_attente', 'prete', 'retiree', 'annulee']; // prete = Dispo en boutique | retiree = Retrait effectué
        
        if (in_array($statut, $valides)) {
            $stmt = $db->prepare("UPDATE orders SET statut = ? WHERE id = ?");
            $stmt->execute([$statut, $id]);
        }
        
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Statut du bon de retrait mis à jour.'];
        header('Location: admin.php?section=commandes');
        exit;
    }

    // Supprimer une commande
    if ($action === 'delete_order') {
        $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$_POST['order_id'] ?? '']);

        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Dossier de commande supprimé.'];
        header('Location: admin.php?section=commandes');
        exit;
    }
}

$section = $_GET['section'] ?? 'login';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$adminError = $_SESSION['admin_error'] ?? null;
unset($_SESSION['admin_error']);

// Redirection si non connecté
if (!isAdmin()) {
    $section = 'login';
} elseif ($section === 'login') {
    header('Location: admin.php?section=dashboard');
    exit;
}

// --- HELPERS HTML ---
function badgeStatut(string $s): string {
    $map = [
        'en_attente' => ['badge-wait', '⏳ En attente'],
        'prete'      => ['badge-confirm', '📦 Prête en boutique'],
        'retiree'    => ['badge-deliver', '✅ Retirée (Livrée)'],
        'annulee'    => ['badge-cancel', '❌ Annulée'],
    ];
    [$cls, $lbl] = $map[$s] ?? ['badge-wait', $s];
    return "<span class='badge $cls'>$lbl</span>";
}

// --- PAGE LOGIN ---
if ($section === 'login') { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Ferme Luzolo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-wrap {
            background-image: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url('images/ferme.jpeg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 12%;
            min-height: 100vh;
        }

        .auth-card {
            background: var(--blanc);
            box-shadow: 0 15px 35px rgba(0,0,0,0.4);
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            max-width: 440px;
            animation: slideIn 0.5s ease-out;
        }

        @media (max-width: 900px) {
            .auth-wrap {
                justify-content: center;
                padding-right: 20px;
                padding-left: 20px;
            }
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <div style="background:var(--vert);padding:32px;text-align:center">
                <div style="font-family:Georgia,serif;font-size:22px;color:#fff;margin-bottom:4px">🥬 Ferme Luzolo</div>
                <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.7)">Administration</div>
            </div>
            <div class="auth-body">
                <?php if ($adminError): ?>
                <div class="flash flash-error"><?= escape($adminError) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="admin_login">
                    <div class="form-group">
                        <label class="form-label">Identifiant d'administration</label>
                        <input type="text" name="username" class="form-input" placeholder="admin" required autofocus>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mot de passe de sécurité</label>
                        <input type="password" name="password" class="form-input" placeholder="********" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%">Ouvrir la session</button>
                </form>
                <div style="margin-top:20px;text-align:center">
                    <a href="index.php" style="font-size:12px;color:var(--vert)">← Retour au site public</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php exit; }

// --- LAYOUT ADMIN ---
function admin_header(string $section, string $title, string $breadcrumb): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?> - Admin Ferme Luzolo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="admin-wrap">
        <aside class="admin-sidebar">
            <div class="sidebar-brand">
                <div class="sidebar-brand-name">🥬 Ferme Luzolo</div>
                <div class="sidebar-brand-sub">Administration</div>
            </div>
            <nav class="sidebar-nav">
                <a href="admin.php?section=dashboard" class="<?= $section === 'dashboard' ? 'active' : '' ?>">📊 Tableau de bord</a>
                <a href="admin.php?section=produits" class="<?= $section === 'produits' ? 'active' : '' ?>">🥬 Gestion produits</a>
                <a href="admin.php?section=commandes" class="<?= $section === 'commandes' ? 'active' : '' ?>">📦 Commandes</a>
                <a href="index.php" style="margin-top:20px;border-top:1px solid #1e3328;padding-top:20px">🌍 Voir le site</a>
            </nav>
            <div class="sidebar-footer">
                Connecté en tant que <strong style="color:#fff">admin</strong><br>
                <a href="admin.php?action=logout">Déconnexion</a>
            </div>
        </aside>
        <main class="admin-main">
            <div class="admin-header">
                <div class="breadcrumb"><?= escape($breadcrumb) ?></div>
                <h1 class="serif"><?= escape($title) ?></h1>
            </div>
<?php }

function admin_footer(): void { ?>
        </main>
    </div>
</body>
</html>
<?php }

// --- SECTIONS ADMIN ---
if ($section === 'dashboard') {
    // STATS DEPUIS MYSQL
    $cntProds = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $cntOrders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $cntPending = $db->query("SELECT COUNT(*) FROM orders WHERE statut = 'en_attente'")->fetchColumn();
    $sumTotal = $db->query("SELECT SUM(total) FROM orders")->fetchColumn() ?? 0;
    
    // FAIBLE STOCK
    $stmtStock = $db->query("SELECT * FROM products WHERE stock <= 5");
    $faible_stock = $stmtStock->fetchAll();

    // DERNIÈRES COMMANDES
    $stmtRecent = $db->query("SELECT * FROM orders ORDER BY date DESC LIMIT 5");
    $recent = $stmtRecent->fetchAll();

    admin_header('dashboard', 'Tableau de bord', 'Admin');
    if ($flash): ?>
        <div class="flash flash-<?= escape($flash['type']) ?>"><?= escape($flash['msg']) ?></div>
    <?php endif; ?>

    <div class="stat-cards">
        <div class="stat-card">
            <div class="stat-card-num"><?= $cntProds ?></div>
            <div class="stat-card-lbl">Produits actifs</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num"><?= $cntOrders ?></div>
            <div class="stat-card-lbl">Commandes totales</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num" style="color:var(--warning)"><?= $cntPending ?></div>
            <div class="stat-card-lbl">En attente</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-num"><?= number_format($sumTotal, 0, ',', ' ') ?> FC</div>
            <div class="stat-card-lbl">Chiffre d'affaires</div>
        </div>
    </div>

    <?php if (!empty($faible_stock)): ?>
    <div class="panel">
        <div class="panel-header"><span class="panel-title">⚠️ Stock faible</span></div>
        <table class="admin-table">
            <thead><tr><th>Produit</th><th>Catégorie</th><th>Stock</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($faible_stock as $p): ?>
                <tr>
                    <td><strong><?= escape($p['nom']) ?></strong></td>
                    <td><?= escape($p['categorie']) ?></td>
                    <td style="color:var(--danger);font-weight:600"><?= (int)$p['stock'] ?> <?= escape($p['unite']) ?></td>
                    <td><a href="admin.php?section=produits&edit=<?= escape($p['id']) ?>" class="btn btn-primary btn-sm">Modifier</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Dernières commandes</span>
            <a href="admin.php?section=commandes" class="btn btn-outline btn-sm">Tout voir</a>
        </div>
        <?php if (empty($recent)): ?>
        <p style="color:var(--gris);font-size:13px">Aucune commande pour le moment.</p>
        <?php else: ?>
        <table class="admin-table">
            <thead><tr><th>Client</th><th>Date</th><th>Total</th><th>Statut</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $o): ?>
                <tr>
                    <td><strong><?= escape($o['client']) ?></strong><br><small style="color:var(--gris)"><?= escape($o['email']) ?></small></td>
                    <td><?= formatDate($o['date']) ?></td>
                    <td><?= number_format($o['total'], 0, ',', ' ') ?> FC</td>
                    <td><?= badgeStatut($o['statut']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
<?php admin_footer(); }

if ($section === 'produits') {
    $editId = $_GET['edit'] ?? null;
    $editProd = null;
    if ($editId) {
        $stmtEdit = $db->prepare("SELECT * FROM products WHERE id = ?");
        $stmtEdit->execute([$editId]);
        $editProd = $stmtEdit->fetch();
    }
    
    $cats = ['Légumes', 'Fruits', 'Œufs & Laitages', 'Épicerie', 'Plantes', 'Autre'];

    // Recherche de produits
    $searchQuery = trim($_GET['q'] ?? '');
    
    // Pagination
    $limit = 10;
    $pageQuery = max(1, (int)($_GET['p'] ?? 1));
    $offset = ($pageQuery - 1) * $limit;

    if ($searchQuery !== '') {
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM products WHERE nom LIKE ? OR categorie LIKE ?");
        $stmtCount->execute(["%$searchQuery%", "%$searchQuery%"]);
        $totalProds = $stmtCount->fetchColumn();

        $stmtList = $db->prepare("SELECT * FROM products WHERE nom LIKE ? OR categorie LIKE ? LIMIT :limit OFFSET :offset");
        $stmtList->bindValue(1, "%$searchQuery%", PDO::PARAM_STR);
        $stmtList->bindValue(2, "%$searchQuery%", PDO::PARAM_STR);
        $stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtList->execute();
        $paginatedProds = $stmtList->fetchAll();
    } else {
        $totalProds = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();

        $stmtList = $db->prepare("SELECT * FROM products LIMIT :limit OFFSET :offset");
        $stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtList->execute();
        $paginatedProds = $stmtList->fetchAll();
    }
    
    $totalPages = ceil($totalProds / $limit);

    admin_header('produits', 'Gestion des produits', 'Admin / Produits');
    if ($flash): ?>
        <div class="flash flash-<?= escape($flash['type']) ?>"><?= escape($flash['msg']) ?></div>
    <?php endif; ?>

    <!-- Formulaire ajout / édition -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title"><?= $editProd ? '✏️ Modifier le produit' : '➕ Nouveau produit' ?></span>
            <?php if ($editProd): ?><a href="admin.php?section=produits" class="btn btn-outline btn-sm">Annuler</a><?php endif; ?>
        </div>
        <form method="post" id="prod-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editProd ? 'edit_product' : 'add_product' ?>">
            <?php if ($editProd): ?><input type="hidden" name="id" value="<?= escape($editProd['id']) ?>"><?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">Nom du produit *</label>
                    <input type="text" name="nom" required class="form-input" placeholder="Tomates" value="<?= escape($editProd['nom'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Catégorie *</label>
                    <select name="categorie" class="form-select" required>
                        <option value="">-- Choisir --</option>
                        <?php foreach ($cats as $c): ?>
                        <option value="<?= escape($c) ?>" <?= ($editProd['categorie'] ?? '') === $c ? 'selected' : '' ?>><?= escape($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Prix (FC) *</label>
                    <input type="number" name="prix" required class="form-input" placeholder="2500" value="<?= escape((string)($editProd['prix'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Unité *</label>
                    <input type="text" name="unite" required class="form-input" placeholder="kg, botte..." value="<?= escape($editProd['unite'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Stock disponible</label>
                    <input type="number" name="stock" min="0" class="form-input" placeholder="50" value="<?= (int)($editProd['stock'] ?? 0) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Chemin d'accès ou URL de l'image *</label>
                    <!-- CHANGEMENT : 'type="text"' au lieu de 'type="url"' pour autoriser les chemins d'images relatifs locaux -->
                    <input type="text" name="image" required class="form-input" placeholder="images/imagesproduits/..." value="<?= escape($editProd['image'] ?? '') ?>">
                </div>
                <div class="form-group form-full">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Description du produit..."><?= escape($editProd['description'] ?? '') ?></textarea>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:16px;">
                <button type="submit" class="btn btn-primary"><?= $editProd ? '💾 Modifier' : '➕ Ajouter' ?></button>
                <a href="admin.php?section=produits" class="btn btn-outline">Réinitialiser</a>
            </div>
        </form>
    </div>

    <!-- Barre de recherche -->
    <div class="panel">
        <form method="get" class="admin-search-box">
            <input type="hidden" name="section" value="produits">
            <input type="text" name="q" placeholder="Rechercher un produit (nom, catégorie)..." value="<?= escape($searchQuery) ?>" class="form-input" style="flex:1;">
            <button type="submit" class="btn btn-primary">Rechercher</button>
            <?php if ($searchQuery !== ''): ?>
            <a href="admin.php?section=produits" class="btn btn-outline">Réinitialiser</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Liste produits -->
    <div class="panel">
        <div class="panel-header">
            <span class="panel-title">Produits actifs (<?= $totalProds ?>)</span>
        </div>
        <?php if (empty($paginatedProds)): ?>
        <p style="color:var(--gris);font-size:13px">Aucun produit trouvé.</p>
        <?php else: ?>
        <div style="overflow-x:auto">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Photo</th><th>Nom</th><th>Catégorie</th>
                        <th>Prix</th><th>Stock</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paginatedProds as $p): ?>
                    <tr>
                        <td><img src="<?= escape($p['image']) ?>" alt="" style="width:72px; height:48px; object-fit:cover; border-radius:4px;"></td>
                        <td><strong><?= escape($p['nom']) ?></strong></td>
                        <td><?= escape($p['categorie']) ?></td>
                        <td><?= number_format($p['prix'], 0, ',', ' ') ?> FC / <?= escape($p['unite']) ?></td>
                        <td>
                            <span style="color:<?= $p['stock'] <= 5 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:<?= $p['stock'] <= 5 ? '600' : '400' ?>">
                                <?= (int)$p['stock'] ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            <a href="admin.php?section=produits&edit=<?= escape($p['id']) ?>" class="btn btn-info btn-sm">✏️ Modifier</a>
                            <form method="post" style="display:inline" onsubmit="return confirm('Supprimer ce produit ?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="id" value="<?= escape($p['id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm">🗑️ Suppr.</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination produits -->
        <?php if ($totalPages > 1): ?>
        <div style="margin-top:20px; display:flex; gap:8px; justify-content:center; align-items:center;">
            <?php if ($currentPage > 1): ?>
            <a href="admin.php?section=produits&p=<?= $currentPage - 1 ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-btn">Précédent</a>
            <?php endif; ?>
            <span style="font-size:13px; color:var(--text-muted);">Page <?= $currentPage ?> sur <?= $totalPages ?></span>
            <?php if ($currentPage < $totalPages): ?>
            <a href="admin.php?section=produits&p=<?= $currentPage + 1 ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-btn">Suivant</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
<?php admin_footer(); }

if ($section === 'commandes') {
    $detailId = $_GET['detail'] ?? null;
    $filterStatut = $_GET['statut'] ?? '';
    $statusOptions = [
        ''           => 'Toutes',
        'en_attente' => '⏳ En attente',
        'prete'      => '📦 Prête en boutique',
        'retiree'    => '✅ Retirée (Livrée)',
        'annulee'    => '❌ Annulée',
    ];

    admin_header('commandes', 'Gestion des bons de retrait', 'Admin / Commandes');
    if ($flash): ?>
        <div class="flash flash-<?= escape($flash['type']) ?>"><?= escape($flash['msg']) ?></div>
    <?php endif;

    if ($detailId):
        // FETCH SPÉCIFIQUE COMMANDE DEPUIS MYSQL
        $stmtOrder = $db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmtOrder->execute([$detailId]);
        $order = $stmtOrder->fetch();
        
        if ($order):
            $stmtItems = $db->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmtItems->execute([$detailId]);
            $order_items = $stmtItems->fetchAll();
        ?>
        <div style="margin-bottom:16px"><a href="admin.php?section=commandes" class="btn btn-outline btn-sm">← Retour</a></div>
        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Bon de Retrait #<?= escape($order['code_retrait']) ?></span>
                <?= badgeStatut($order['statut']) ?>
            </div>
            <div class="grid-2" style="gap:30px;align-items:start">
                <div>
                    <div class="order-detail">
                        <div class="order-detail-name"><?= escape($order['client']) ?></div>
                        <div class="order-detail-info">
                            ✉️ <?= escape($order['email']) ?><br>
                            📞 <?= escape($order['telephone'] ?? 'Non spécifié') ?><br>
                            🏬 <strong>Point de retrait choisi :</strong> <?= escape($order['point_retrait']) ?><br>
                            📅 <?= date('d/m/Y à H:i', strtotime($order['date'])) ?>
                        </div>
                        <?php if (!empty($order['note'])): ?>
                        <div style="margin-top:10px;font-style:italic;color:var(--gris);font-size:13px">💬 <?= escape($order['note']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <table class="order-lines" style="width:100%">
                        <thead>
                            <tr style="font-size:11px;letter-spacing:.5px;text-transform:uppercase;color:var(--gris)">
                                <th style="padding:8px 0">Produit</th><th>Qté</th><th>Prix unit.</th><th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order_items as $l): ?>
                            <tr>
                                <td><?= escape($l['nom']) ?></td>
                                <td><?= (int)$l['quantite'] ?> <?= escape($l['unite']) ?></td>
                                <td><?= number_format($l['prix'], 0, ',', ' ') ?> FC</td>
                                <td><?= number_format($l['prix'] * $l['quantite'], 0, ',', ' ') ?> FC</td>
                            </tr>
                            <?php endforeach; ?>
                            <tr style="font-weight:600">
                                <td colspan="3" style="padding-top:12px">Total</td>
                                <td style="font-family:Georgia,serif;color:var(--vert);font-size:18px;padding-top:12px"><?= number_format($order['total'], 0, ',', ' ') ?> FC</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div style="margin-top:28px;border-top:1px solid var(--bordure);padding-top:20px;display:flex;justify-content:space-between;align-items:center;">
                <div class="order-detail" style="margin:0;display:flex;gap:12px;align-items:center;">
                    <div class="order-detail-name" style="margin:0;">Mettre à jour le statut :</div>
                    <form method="post" style="display:flex;gap:8px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_order_status">
                        <input type="hidden" name="order_id" value="<?= escape($order['id']) ?>">
                        <select name="statut" class="form-select" style="width:auto;">
                            <?php foreach ($statusOptions as $val => $lbl): if ($val === '') continue; ?>
                            <option value="<?= escape($val) ?>" <?= $order['statut'] === $val ? 'selected' : '' ?>><?= escape($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm">Enregistrer</button>
                    </form>
                </div>
                <form method="post" onsubmit="return confirm('Supprimer cette commande de la base de données ?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_order">
                    <input type="hidden" name="order_id" value="<?= escape($order['id']) ?>">
                    <button type="submit" class="btn btn-danger btn-sm">🗑/th> Supprimer</button>
                </form>
            </div>
        </div>
        <?php endif;
    else: // Liste des commandes
        // RECHERCHE + FILTRE STATUT COMMANDE
        $searchQuery = trim($_GET['q'] ?? '');
        $sql = "SELECT * FROM orders WHERE 1=1";
        $params = [];

        if ($filterStatut !== '') {
            $sql .= " AND statut = ?";
            $params[] = $filterStatut;
        }

        if ($searchQuery !== '') {
            $sql .= " AND (client LIKE ? OR email LIKE ? OR code_retrait LIKE ? OR telephone LIKE ?)";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
            $params[] = "%$searchQuery%";
        }

        // COMPTER LE TOTAL POUR PAGINATION
        $stmtCount = $db->prepare($sql);
        $stmtCount->execute($params);
        $totalOrders = $stmtCount->rowCount();

        // PAGINATION
        $limit = 10;
        $currentPage = max(1, (int)($_GET['p'] ?? 1));
        $offset = ($currentPage - 1) * $limit;

        $sql .= " ORDER BY date DESC LIMIT $limit OFFSET $offset";
        $stmtList = $db->prepare($sql);
        $stmtList->execute($params);
        $paginatedOrders = $stmtList->fetchAll();

        $totalPages = ceil($totalOrders / $limit);
    ?>
        <div class="panel">
            <div class="panel-header"><span class="panel-title">Filtrer par statut de retrait</span></div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <?php foreach ($statusOptions as $val => $lbl): ?>
                <a href="admin.php?section=commandes<?= $val ? '&statut=' . urlencode($val) : '' ?>" class="filter-btn <?= $filterStatut === $val ? 'active' : '' ?>"><?= escape($lbl) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Formulaire de recherche Admin -->
        <div class="panel">
            <form method="get" class="admin-search-box">
                <input type="hidden" name="section" value="commandes">
                <?php if ($filterStatut): ?>
                <input type="hidden" name="statut" value="<?= escape($filterStatut) ?>">
                <?php endif; ?>
                <input type="text" name="q" placeholder="Rechercher par Code de Retrait, Nom, Email..." value="<?= escape($searchQuery) ?>" class="form-input" style="flex:1;">
                <button type="submit" class="btn btn-primary">Rechercher</button>
                <?php if ($searchQuery !== ''): ?>
                <a href="admin.php?section=commandes<?= $filterStatut ? '&statut='.urlencode($filterStatut) : '' ?>" class="btn btn-outline">Réinitialiser</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <span class="panel-title">Bons de retrait trouvés (<?= $totalOrders ?>)</span>
            </div>
            <?php if (empty($paginatedOrders)): ?>
            <p style="color:var(--gris);font-size:13px">Aucune commande trouvée.</p>
            <?php else: ?>
            <div style="overflow-x:auto">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Code Retrait</th>
                            <th>Client</th>
                            <th>Date d'Achat</th>
                            <th>Boutique Choisie</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginatedOrders as $o): ?>
                        <tr>
                            <td><strong style="color:var(--vert); font-family:monospace; font-size:15px;"><?= escape($o['code_retrait']) ?></strong></td>
                            <td><strong><?= escape($o['client']) ?></strong><br><small><?= escape($o['telephone']) ?></small></td>
                            <td><?= formatDate($o['date']) ?></td>
                            <td><small><?= escape($o['point_retrait']) ?></small></td>
                            <td style="font-family:Georgia,serif;color:var(--vert);font-weight:bold;"><?= number_format($o['total'], 0, ',', ' ') ?> FC</td>
                            <td><?= badgeStatut($o['statut']) ?></td>
                            <td><a href="admin.php?section=commandes&detail=<?= escape($o['id']) ?>" class="btn btn-info btn-sm">🔍 Gérer</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Liens de pagination -->
            <?php if ($totalPages > 1): ?>
            <div style="margin-top:20px; display:flex; gap:8px; justify-content:center; align-items:center;">
                <?php if ($currentPage > 1): ?>
                <a href="admin.php?section=commandes&p=<?= $currentPage - 1 ?><?= $filterStatut ? '&statut='.urlencode($filterStatut) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-btn">Précédent</a>
                <?php endif; ?>
                <span style="font-size:13px; color:var(--text-muted);">Page <?= $currentPage ?> sur <?= $totalPages ?></span>
                <?php if ($currentPage < $totalPages): ?>
                <a href="admin.php?section=commandes&p=<?= $currentPage + 1 ?><?= $filterStatut ? '&statut='.urlencode($filterStatut) : '' ?><?= $searchQuery ? '&q='.urlencode($searchQuery) : '' ?>" class="filter-btn">Suivant</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>
    <?php endif; admin_footer();
}
?>