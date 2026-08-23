# Matrice de migration PHP → Appwrite

## `index.php` (catalogue, panier, checkout, contact)
- **Catalogue produits** → `site/src/app.js` (lecture `products` via Appwrite Databases)
- **Panier session PHP** → stockage local navigateur (`localStorage`) + état JS
- **Checkout transactionnel** → Function `checkout-create-order`
- **Validation commande** → Function `checkout-create-order`
- **Création commande + lignes** → collections `orders` + `order_items`
- **Décrément stock** → Function `checkout-create-order` (mise à jour `products.stock`)

## `client.php` (inscription, connexion, espace client)
- **Inscription / connexion** → Appwrite Auth (`account.create`, `createEmailPasswordSession`)
- **Session client** → session Appwrite web
- **Historique commandes** → lecture `orders` filtrée `userId`
- **Déconnexion** → `account.deleteSession("current")`

## `admin.php` (auth admin, CRUD produits, commandes, dashboard)
- **Auth admin statique** (`ADMIN_USER`, `ADMIN_PASS`) → team `admins` Appwrite
- **CRUD produits** → Function `admin-product-upsert`
- **Mise à jour statut commande** → Function `admin-update-order-status`
- **Suppression commande** → Function `admin-delete-order`
- **Dashboard** → requêtes Databases (`orders`, `products`) dans le front admin

## `config.php` (DB, sécurité, helpers)
- **Connexion PDO MySQL** → Appwrite Databases SDK/Functions
- **Sessions PHP/CSRF** → sessions Appwrite + ACL Appwrite
- **Règles validation** → front JS + validation serverless dans Functions
- **Gestion erreurs serveur** → logs Functions + retours API JSON
