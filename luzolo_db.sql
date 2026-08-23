CREATE DATABASE IF NOT EXISTS luzolo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE luzolo_db;

-- Table des utilisateurs (Clients)
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATE NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table des produits (Catalogue)
CREATE TABLE IF NOT EXISTS products (
    id VARCHAR(50) PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prix INT NOT NULL,
    unite VARCHAR(20) NOT NULL,
    categorie VARCHAR(50) NOT NULL,
    stock INT NOT NULL,
    description TEXT,
    image VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertion des 8 produits locaux de Kinshasa
INSERT INTO products (id, nom, prix, unite, categorie, stock, description, image) VALUES
('p1', 'Pondu frais (Feuilles de manioc)', 3500, 'botte', 'Légumes', 45, 'Feuilles de manioc fraîches, idéales pour préparer le traditionnel pondu congolais.', 'images/imagesproduits/pondu.jpeg'),
('p2', 'Makemba (Bananes plantains)', 7500, 'régime', 'Fruits', 30, 'Bananes plantains mûres de premier choix, prêtes à être frites ou bouillies.', 'images/imagesproduits/Bananes-plantains.jpeg'),
('p3', 'Dongodongo frais (Gombos)', 4000, 'kg', 'Légumes', 25, 'Gombos frais et bien tendres récoltés le matin même dans notre jardin.', 'images/imagesproduits/Dongodongo-frais.jpeg'),
('p4', 'Safous mûrs (Prunes locales)', 8000, 'panier', 'Fruits', 15, 'Safous savoureux et charnus, parfaits à griller au charbon de bois.', 'images/imagesproduits/Safous-mûrs.jpeg'),
('p5', 'Piment Pilipili', 2500, 'sachet', 'Légumes', 60, 'Piments rouges locaux, très piquants et parfumés pour relever vos plats.', 'images/imagesproduits/pilipili.jpeg'),
('p6', 'Chikwangue traditionnelle', 1500, 'pièce', 'Épicerie', 100, 'Pain de manioc fermenté traditionnel, cuit et emballé dans des feuilles locales.', 'images/imagesproduits/Chikwangue.jpeg'),
('p7', 'Manioc doux de Kinshasa', 5000, 'sac', 'Épicerie', 40, 'Racines de manioc doux frais, riches en amidon, idéales pour accompagner vos plats.', 'images/imagesproduits/manioc.jpeg'),
('p8', 'Farine de Maïs blanche', 15000, 'sac de 5kg', 'Épicerie', 50, 'Farine de maïs blanche de qualité supérieure, idéale pour la préparation du Foufou.', 'images/imagesproduits/Maïs.jpeg');

-- Table des commandes avec Click & Collect (Boutiques de Retrait et Code Unique)
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(50) PRIMARY KEY,
    date DATETIME NOT NULL,
    client VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telephone VARCHAR(50) NOT NULL,
    point_retrait VARCHAR(100) NOT NULL, -- Boutique physique choisie
    code_retrait VARCHAR(20) NOT NULL UNIQUE, -- Code unique de retrait (ex: LZL-8A4F2B)
    note TEXT,
    total INT NOT NULL,
    statut VARCHAR(30) NOT NULL, -- en_attente, prete (dispo en boutique), retiree (livree), annulee
    paiement_methode VARCHAR(30) NOT NULL,
    paiement_statut VARCHAR(50) NOT NULL,
    paiement_operateur VARCHAR(50),
    paiement_numero VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table de liaison pour les lignes de commande
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id VARCHAR(50) NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    nom VARCHAR(100) NOT NULL,
    prix INT NOT NULL,
    unite VARCHAR(20) NOT NULL,
    quantite INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;