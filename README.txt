============================================================
KoliaBioRDC / Ferme Luzolo
Site e-commerce PHP (Click & Collect)
============================================================

1) Prérequis
------------
- PHP 8.0+ avec extension PDO MySQL activée
- MySQL / MariaDB
- Serveur local (XAMPP, WAMP, Laragon, MAMP, Apache+PHP, etc.)

2) Installation locale
----------------------
1. Copier le projet dans votre dossier serveur web.
2. Créer la base et les tables :
   - Importer le fichier :
     /home/runner/work/KoliaBioRDC/KoliaBioRDC/luzolo_db.sql
3. Ouvrir l'application dans le navigateur (ex: http://localhost/KoliaBioRDC/).

3) Configuration (variables d'environnement)
--------------------------------------------
Le projet lit d'abord les variables d'environnement puis applique les valeurs
par défaut définies dans config.php.

Variables supportées :
- DB_HOST (défaut: localhost)
- DB_NAME (défaut: luzolo_db)
- DB_USER (défaut: root)
- DB_PASS (défaut: vide)
- ADMIN_USER (défaut: admin)
- ADMIN_PASS (défaut: admin123)

Exemple (Linux/macOS) :
export DB_HOST=localhost
export DB_NAME=luzolo_db
export DB_USER=root
export DB_PASS=
export ADMIN_USER=admin
export ADMIN_PASS=changez_ce_mot_de_passe

4) Sécurité (important)
-----------------------
- Changez ADMIN_USER et ADMIN_PASS en production.
- N'exposez jamais de secrets dans le code source.
- Les erreurs serveur sont journalisées via error_log, et les messages affichés
  à l'utilisateur restent génériques.
- Les formulaires critiques utilisent une protection CSRF.
- Des validations serveur sont appliquées (email, nom, téléphone, paiement).

5) Accès application
--------------------
- Site public : index.php
- Espace client : client.php
- Administration : admin.php

6) Vérification rapide
----------------------
Pour vérifier la syntaxe PHP :
php -l config.php
php -l index.php
php -l client.php
php -l admin.php

7) Notes
--------
- Le projet est actuellement structuré en gros fichiers PHP (public/admin/client).
- Une prochaine amélioration recommandée est la séparation en modules
  (routes, contrôleurs, services, vues).
