<?php
require_once 'config.php';

// --- ACTIONS POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf()) {
        die("Erreur de sécurité CSRF.");
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'client_register') {
        $email = trim($_POST['email'] ?? '');
        $nom   = trim($_POST['nom'] ?? '');
        $pwd   = $_POST['password'] ?? '';
        $pwd2  = $_POST['password2'] ?? '';

        if (empty($email) || empty($nom) || empty($pwd)) {
            $_SESSION['client_error'] = 'Tous les champs sont obligatoires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['client_error'] = 'L\'adresse email n\'est pas valide.';
        } elseif (strlen($pwd) < 8) {
            $_SESSION['client_error'] = 'Le mot de passe doit contenir au moins 8 caractères.';
        } elseif ($pwd !== $pwd2) {
            $_SESSION['client_error'] = 'Les mots de passe ne correspondent pas.';
        } else {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            
            if ($stmtCheck->fetchColumn() > 0) {
                $_SESSION['client_error'] = 'Cet email est déjà utilisé.';
            } else {
                $user_id = genId();
                $stmtInsert = $db->prepare("INSERT INTO users (id, nom, email, password, created_at) VALUES (?, ?, ?, ?, ?)");
                $stmtInsert->execute([
                    $user_id,
                    $nom,
                    $email,
                    password_hash($pwd, PASSWORD_DEFAULT),
                    date('Y-m-d')
                ]);

                $_SESSION['client_id']    = $user_id;
                $_SESSION['client_nom']   = $nom;
                $_SESSION['client_email'] = $email;
                $_SESSION['flash']        = ['type' => 'success', 'msg' => "Bienvenue, {$nom} ! Votre compte a été créé avec succès."];

                header('Location: client.php?page=mon-espace');
                exit;
            }
        }
        $_SESSION['client_tab'] = 'register';
        header('Location: client.php');
        exit;
    }

    if ($action === 'client_login') {
        $email = trim($_POST['email'] ?? '');
        $pwd   = $_POST['password'] ?? '';

        $stmtUser = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmtUser->execute([$email]);
        $found = $stmtUser->fetch();

        if ($found && password_verify($pwd, $found['password'])) {
            $_SESSION['client_id']    = $found['id'];
            $_SESSION['client_nom']   = $found['nom'];
            $_SESSION['client_email'] = $found['email'];
            $_SESSION['flash']        = ['type' => 'success', 'msg' => "Bon retour parmi nous, {$found['nom']} !"];
            header('Location: client.php?page=mon-espace');
        } else {
            $_SESSION['client_error'] = 'Email ou mot de passe incorrect.';
            header('Location: client.php');
        }
        exit;
    }
}

if (($_GET['action'] ?? '') === 'logout') {
    unset($_SESSION['client_id'], $_SESSION['client_nom'], $_SESSION['client_email']);
    header('Location: client.php');
    exit;
}

$page = $_GET['page'] ?? 'auth';
$flash = $_SESSION['flash'] ?? null;
$clientError = $_SESSION['client_error'] ?? null;
$tab = $_SESSION['client_tab'] ?? 'login';
unset($_SESSION['flash'], $_SESSION['client_error'], $_SESSION['client_tab']);

// --- HTML HEADER/FOOTER ---
function client_header(string $title): void { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($title) ?> - Ferme Luzolo</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .auth-wrap {
            background-image: linear-gradient(rgba(0,0,0,0.35), rgba(0,0,0,0.35)), url('images/jardin.jpeg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: calc(100vh - 68px);
            padding: 40px 20px;
        }

        .auth-card {
            background: var(--blanc);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
            border-radius: 8px;
            overflow: hidden;
            width: 100%;
            max-width: 480px;
        }

        .client-banner {
            background-image: linear-gradient(rgba(0, 0, 0, 0.55), rgba(0, 0, 0, 0.55)), url('images/client2.jpeg');
            background-size: cover;
            background-position: center;
            color: #ffffff;
            padding: 60px 0;
            text-align: center;
            border-bottom: 1px solid var(--bordure);
        }

        .client-banner .section-title-sm {
            color: rgba(255, 255, 255, 0.85);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 2px;
            margin-bottom: 8px;
        }

        .client-banner h1 {
            color: #ffffff;
            font-size: 38px;
            margin: 0;
        }

        .client-banner h1 em {
            color: #a8d4bc !important;
            font-style: italic;
        }

        /* Style spécifique pour le Ticket / Bon de retrait unique */
        .ticket-box {
            border: 2px dashed var(--vert);
            background-color: var(--fond);
            border-radius: 8px;
            padding: 20px;
            margin-top: 14px;
            text-align: center;
            position: relative;
        }
        .ticket-code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            font-weight: 800;
            color: var(--vert);
            letter-spacing: 2px;
            background: #fff;
            padding: 8px 16px;
            border: 1px solid var(--bordure);
            display: inline-block;
            margin: 10px 0;
            border-radius: 4px;
        }
        .ticket-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--gris);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container">
            <a class="logo" href="index.php">🥬 Ferme Luzolo</a>
            <nav class="nav">
                <a href="index.php?page=produits">Produits</a>
                <a href="index.php?page=panier">Panier</a>
                <?php if (isClient()): ?>
                <span style="font-size:12px;color:var(--vert)">👤 <?= escape($_SESSION['client_nom'] ?? '') ?></span>
                <a href="client.php?action=logout" style="font-size:12px;color:var(--gris)">Déconnexion</a>
                <?php else: ?>
                <a href="client.php" style="background:var(--vert);color:#fff;padding:8px 18px;font-size:11px;letter-spacing:1px;text-transform:uppercase">Mon Espace</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
<?php }

function client_footer(): void { ?>
    <footer class="site-footer">
        <div class="container">
            <div class="footer-bottom">
                <span>© <?= date('Y') ?> Ferme Luzolo. Tous droits réservés.</span>
                <a href="index.php">← Retour au site public</a>
            </div>
        </div>
    </footer>
</body>
</html>
<?php }

// --- PAGE AUTH (Login / Register) ---
if (!isClient() || $page === 'auth') {
    client_header('Mon Espace');
?>
    <div class="auth-wrap">
        <div class="auth-card">
            <div class="auth-tabs">
                <button class="auth-tab <?= $tab === 'login' ? 'active' : '' ?>" onclick="showTab('login')">Se connecter</button>
                <button class="auth-tab <?= $tab === 'register' ? 'active' : '' ?>" onclick="showTab('register')">Créer un compte</button>
            </div>

            <?php if ($clientError): ?>
            <div style="padding:0 36px;margin-top:16px"><div class="flash flash-error"><?= escape($clientError) ?></div></div>
            <?php endif; ?>

            <!-- Login Form -->
            <div id="tab-login" class="auth-body" style="display:<?= $tab === 'login' ? 'block' : 'none' ?>">
                <div class="auth-title">Se connecter</div>
                <div class="auth-sub">Accédez à votre espace personnel</div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="client_login">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" required class="form-input" placeholder="votre@email.cd" autofocus value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="password" required class="form-input" placeholder="********">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Se connecter</button>
                </form>
                <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--gris)">
                    Pas encore de compte ? <a href="#" onclick="showTab('register')" style="color:var(--vert)">S'inscrire</a>
                </div>
            </div>

            <!-- Register Form -->
            <div id="tab-register" class="auth-body" style="display:<?= $tab === 'register' ? 'block' : 'none' ?>">
                <div class="auth-title">Créer un compte</div>
                <div class="auth-sub">Rejoignez la communauté Ferme Luzolo</div>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="client_register">
                    <div class="form-group">
                        <label class="form-label">Nom complet *</label>
                        <input type="text" name="nom" required class="form-input" placeholder="Jean Luzolo">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" required class="form-input" placeholder="jean@email.cd" value="<?= escape($_POST['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mot de passe *</label>
                        <input type="password" name="password" required minlength="8" class="form-input" placeholder="Min. 8 caractères">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Confirmer le mot de passe *</label>
                        <input type="password" name="password2" required minlength="8" class="form-input" placeholder="********">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;margin-top:8px">Créer mon compte</button>
                </form>
                <div style="text-align:center;margin-top:16px;font-size:13px;color:var(--gris)">
                    Déjà un compte ? <a href="#" onclick="showTab('login')" style="color:var(--vert)">Se connecter</a>
                </div>
            </div>
        </div>
    </div>
    <script>
    function showTab(t) {
        document.getElementById('tab-login').style.display = (t === 'login') ? 'block' : 'none';
        document.getElementById('tab-register').style.display = (t === 'register') ? 'block' : 'none';
        document.querySelectorAll('.auth-tab').forEach((el, i) => {
            el.classList.toggle('active', (i === 0 && t === 'login') || (i === 1 && t === 'register'));
        });
    }
    </script>
<?php client_footer(); exit; }

// Mon Espace Connecté
client_header('Mon Espace');
?>
    <?php if ($flash): ?>
    <div class="container" style="padding-top:20px">
        <div class="flash" style="background:#151b2c; border-left:4px solid var(--vert); padding:16px; margin-bottom:24px; color:#fff;"><?= $flash['msg'] ?></div>
    </div>
    <?php endif; ?>

    <div class="client-banner">
        <div class="container">
            <div class="section-title-sm">Mon Espace Personnel</div>
            <h1 class="serif">Bonjour, <em><?= escape($_SESSION['client_nom']) ?></em></h1>
        </div>
    </div>

    <div class="section">
        <div class="container">
            <div class="grid-2" style="gap:50px;align-items:start">
                
                <!-- Mes commandes et tickets de retrait -->
                <div>
                    <h2 style="font-size:20px;margin-bottom:20px">🎫 Mes Bons de Retrait Actifs</h2>
                    <?php
                    $stmtMyOrders = $db->prepare("SELECT * FROM orders WHERE email = ? ORDER BY date DESC");
                    $stmtMyOrders->execute([$_SESSION['client_email']]);
                    $myOrders = $stmtMyOrders->fetchAll();

                    if (empty($myOrders)): ?>
                    <div style="background:var(--fond);padding:28px;text-align:center">
                        <div style="font-size:36px;margin-bottom:12px">🛒</div>
                        <p style="color:var(--gris);margin-bottom:16px">Vous n'avez pas encore de bons de retrait de panier.</p>
                        <a href="index.php?page=produits" class="btn btn-primary btn-sm">Faire mes achats</a>
                    </div>
                    <?php else: foreach ($myOrders as $o): 
                        $stmtItems = $db->prepare("SELECT nom, quantite, unite FROM order_items WHERE order_id = ?");
                        $stmtItems->execute([$o['id']]);
                        $lines = $stmtItems->fetchAll();
                        
                        $countItems = array_sum(array_column($lines, 'quantite'));
                    ?>
                    <div style="border:1px solid var(--bordure);padding:18px;margin-bottom:20px;background:var(--blanc); border-radius:8px;">
                        <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:10px">
                            <div>
                                <div style="font-family:Georgia,serif;font-size:15px; font-weight:bold;">Achat du <?= formatDate($o['date']) ?></div>
                                <div style="font-size:12px;color:var(--gris);margin-top:2px"><?= $countItems ?> article<?= $countItems > 1 ? 's' : '' ?></div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-family:Georgia,serif;color:var(--vert);font-size:16px; font-weight:bold;"><?= number_format($o['total'], 0, ',', ' ') ?> FC</div>
                                <?php
                                $statuts = [
                                    'en_attente' => ['⏳ En attente', '#92400e', '#fef3c7'],
                                    'prete'      => ['📦 Prête en boutique', '#1e40af', '#dbeafe'], // Prêt à être retiré
                                    'retiree'    => ['✅ Retirée (Livrée)', '#065f46', '#d1fae5'], // Retrait effectué
                                    'annulee'    => ['❌ Annulée', '#7f1d1d', '#fee2e2'],
                                ];
                                [$lbl, $col, $bg] = $statuts[$o['statut']] ?? ['⏳ En traitement', '#000', '#eee'];
                                echo "<span class='badge' style='background:$bg;color:$col;padding:2px 10px;font-size:11px;font-weight:600;border-radius:2px;display:inline-block;margin-top:4px;'>$lbl</span>";
                                ?>
                            </div>
                        </div>

                        <!-- Ticket de retrait unique généré -->
                        <div class="ticket-box">
                            <span class="ticket-title">🔑 CODE DE RETRAIT UNIQUE</span><br>
                            <span class="ticket-code"><?= escape($o['code_retrait']) ?></span><br>
                            <span style="font-size:12px; font-weight:bold; color:var(--text-dark); display:block; margin-top:6px;">🏬 Point de retrait :</span>
                            <span style="font-size:12px; color:var(--vert); font-weight:600;"><?= escape($o['point_retrait']) ?></span>
                        </div>

                        <div style="font-size:12px;color:var(--gris); margin-top:14px; border-top:1px solid var(--bordure); padding-top:10px;">
                            <strong>Contenu :</strong>
                            <?php foreach ($lines as $index => $l): ?>
                            <span><?= escape($l['nom']) ?> ×<?= (int)$l['quantite'] ?></span><?= $index < count($lines) - 1 ? ', ' : '' ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>

                <!-- Profil et Informations -->
                <div>
                    <h2 style="font-size:20px;margin-bottom:20px">👤 Mon Profil</h2>
                    <div style="background:var(--fond);padding:28px;margin-bottom:24px">
                        <div style="font-size:13px;color:var(--gris);line-height:2.2">
                            <div><strong>Nom complet :</strong> <?= escape($_SESSION['client_nom']) ?></div>
                            <div><strong>E-mail de liaison :</strong> <?= escape($_SESSION['client_email']) ?></div>
                            <div><strong>Statut du compte :</strong> <span style="color:var(--success);font-weight:600">✅ Vérifié</span></div>
                        </div>
                    </div>

                    <!-- Fiche d'information -->
                    <div style="border: 1px solid var(--bordure); border-radius: 8px; overflow: hidden; margin-bottom: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                        <img src="images/client3.jpeg" alt="Fruits frais" style="width: 100%; height: 180px; object-fit: cover; display: block;">
                        <div style="padding: 16px; background: var(--blanc);">
                            <h3 style="font-size: 15px; margin: 0 0 6px 0; color: var(--vert); font-family: Georgia, serif;">🥬 Retrait sécurisé et écologique</h3>
                            <p style="font-size: 12px; color: var(--gris); margin: 0; line-height: 1.5;">Présentez simplement le code unique de retrait généré sur votre écran au gérant de votre boutique pour récupérer votre panier frais.</p>
                        </div>
                    </div>

                    <a href="index.php?page=produits" class="btn btn-primary" style="margin-bottom:12px;display:block;text-align:center">🥬 Acheter des produits</a>
                    <a href="client.php?action=logout" class="btn btn-outline" style="display:block;text-align:center">Fermer la session</a>
                </div>

            </div>
        </div>
    </div>
<?php client_footer(); ?>