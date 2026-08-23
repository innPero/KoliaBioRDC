╔══════════════════════════════════════════════════════════╗
║          FERME DUBOIS — Site e-commerce PHP              ║
║          Projet académique · Version 1.0                 ║
╚══════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
DÉMARRAGE RAPIDE (sans serveur Apache/MySQL)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Pré-requis : PHP 7.4 ou supérieur

1. Ouvrez un terminal dans le dossier ferme-dubois/
2. Lancez le serveur intégré PHP :

     php -S localhost:8000

3. Ouvrez votre navigateur sur :

     http://localhost:8000

C'est tout ! Aucune base de données requise.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
AVEC XAMPP / WAMP / MAMP
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Copiez le dossier ferme-dubois/ dans htdocs/ (XAMPP)
   ou www/ (WAMP)
2. Démarrez Apache
3. Accédez à : http://localhost/ferme-dubois/

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PAGES DISPONIBLES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  SITE PUBLIC
  ┌─────────────────────────────────────────────────────┐
  │ http://localhost:8000/              → Accueil        │
  │ http://localhost:8000/index.php?page=produits        │
  │ http://localhost:8000/index.php?page=panier          │
  │ http://localhost:8000/index.php?page=contact         │
  │ http://localhost:8000/client.php    → Mon Espace     │
  └─────────────────────────────────────────────────────┘

  PANNEAU ADMIN
  ┌─────────────────────────────────────────────────────┐
  │ http://localhost:8000/admin.php                      │
  │                                                     │
  │  Identifiant : admin                                │
  │  Mot de passe : admin123                            │
  └─────────────────────────────────────────────────────┘

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FONCTIONNALITÉS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  SITE PUBLIC :
  ✅ Accueil avec hero, stats et produits vedettes
  ✅ Catalogue produits (filtres par catégorie, recherche)
  ✅ Panier interactif (ajout, modification quantité, supp.)
  ✅ Formulaire de commande complet
  ✅ Page contact
  ✅ Mon Espace : inscription + connexion client

  ADMIN :
  ✅ Tableau de bord (stats, alertes stock faible)
  ✅ Ajouter un produit (nom, prix, image, catégorie, stock)
  ✅ Modifier un produit (formulaire pré-rempli)
  ✅ Supprimer un produit (confirmation)
  ✅ Liste des commandes avec filtres par statut
  ✅ Détail commande (client, articles, adresse, note)
  ✅ Mise à jour statut (en attente → confirmée → expédiée…)
  ✅ Suppression commande

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STRUCTURE DES FICHIERS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

  ferme-dubois/
  ├── index.php        → Site public (toutes pages)
  ├── admin.php        → Panneau d'administration
  ├── client.php       → Espace client (login/register)
  ├── config.php       → Config, sessions, fonctions
  ├── css/
  │   └── style.css    → Feuille de styles complète
  ├── data/
  │   ├── products.json → Données produits
  │   ├── orders.json   → Commandes
  │   └── users.json    → Clients inscrits
  └── README.txt        → Ce fichier

  Les données sont stockées en JSON (pas de MySQL requis).
  Pour réinitialiser, videz orders.json et users.json.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CHANGER LES IDENTIFIANTS ADMIN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Ouvrez config.php et modifiez les lignes :

  define('ADMIN_USER', 'admin');     ← changer ici
  define('ADMIN_PASS', 'admin123'); ← changer ici

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Palette couleurs (CSS) : vert forêt #2a5c45
Technologies : PHP 7.4+, HTML5, CSS3 (vanilla)
Stockage : JSON flat-files (data/)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
