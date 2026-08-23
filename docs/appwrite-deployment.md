# Déploiement cible Appwrite (Sites + Databases + Auth + Functions)

## 1. Préparer Appwrite
1. Créer le projet Appwrite.
2. Créer la base `kolia_db`.
3. Créer les collections selon `/home/runner/work/KoliaBioRDC/KoliaBioRDC/appwrite/schema.json`.
4. Créer la team `admins`.

## 2. Importer les données legacy
1. Générer les exports JSON:
   ```bash
   python3 /home/runner/work/KoliaBioRDC/KoliaBioRDC/migrations/sql_to_appwrite.py \
     --sql /home/runner/work/KoliaBioRDC/KoliaBioRDC/luzolo_db.sql \
     --out-dir /home/runner/work/KoliaBioRDC/KoliaBioRDC/migrations/out
   ```
2. Importer `products.json` dans la collection `products`.
3. Importer `orders.json` puis `order_items.json` (si historiques disponibles).

## 3. Déployer les Functions
1. Déployer:
   - `functions/checkout-create-order`
   - `functions/admin-update-order-status`
   - `functions/admin-product-upsert`
   - `functions/admin-delete-order`
2. Variables obligatoires:
   - `APPWRITE_ENDPOINT`
   - `APPWRITE_PROJECT_ID`
   - `APPWRITE_API_KEY`
   - `APPWRITE_DATABASE_ID=kolia_db`
   - `ADMIN_TEAM_ID=admins`

## 4. Déployer le Site
1. Publier `/home/runner/work/KoliaBioRDC/KoliaBioRDC/site` comme Appwrite Site.
2. Compléter `site/config.js` avec:
   - endpoint
   - projectId
   - databaseId
   - IDs de collections
   - IDs de functions
3. Vérifier que le domaine utilise HTTPS.

## 5. Validation post-déploiement
1. Parcours client: inscription, connexion, panier, checkout.
2. Contrôler la baisse de stock après commande.
3. Contrôler le code de retrait généré.
4. Parcours admin: modification statut, création/modification produit, suppression commande.
