-- A importer dans phpMyAdmin APRES avoir clique sur ta base a gauche.
-- Ne cree pas de base. Ne pas lancer les fichiers 001 a 004.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    address VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_clients_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    address VARCHAR(500) NULL,
    city VARCHAR(150) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(100) DEFAULT 'Morocco',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sites_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    UNIQUE KEY uk_sites_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('ADMIN','MANAGER','STOCK_MANAGER') NOT NULL DEFAULT 'STOCK_MANAGER',
    active TINYINT(1) NOT NULL DEFAULT 1,
    site_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_users_email (email),
    CONSTRAINT fk_users_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(80) NULL,
    reference VARCHAR(80) NULL,
    description TEXT NULL,
    unit VARCHAR(40) NOT NULL DEFAULT 'unites',
    image_url VARCHAR(500) NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_products_sku (sku),
    UNIQUE KEY uk_products_reference (reference),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 0,
    minimum_quantity INT NOT NULL DEFAULT 10,
    maximum_quantity INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_stocks_site_product (site_id, product_id),
    CONSTRAINT fk_stocks_site FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    CONSTRAINT fk_stocks_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('INCREMENT','DECREMENT','TRANSFER_IN','TRANSFER_OUT','ADJUSTMENT') NOT NULL,
    quantity INT NOT NULL,
    balance_after INT NOT NULL,
    note VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movements_stock FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_id INT UNSIGNED NOT NULL,
    type ENUM('LOW_STOCK','OUT_OF_STOCK','OVER_STOCK') NOT NULL,
    message VARCHAR(500) NOT NULL,
    quantity_at_alert INT NOT NULL,
    threshold INT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    CONSTRAINT fk_alerts_stock FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_proposals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id INT UNSIGNED NOT NULL,
    stock_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    status ENUM('PENDING','CONFIRMED','REJECTED') NOT NULL DEFAULT 'PENDING',
    proposed_by INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED NULL,
    manager_confirmed_by INT UNSIGNED NULL,
    responsable_confirmed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL,
    CONSTRAINT fk_proposals_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposals_stock FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposals_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_proposals_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO clients (name, code, email, active)
SELECT 'Oracle', 'ORACLE', 'contact@oracle.ma', 1
WHERE NOT EXISTS (SELECT 1 FROM clients WHERE code = 'ORACLE');

INSERT INTO sites (client_id, code, name, city, country, active)
SELECT c.id, 'ORAC-RAB', 'Oracle Rabat HQ', 'Rabat', 'Morocco', 1
FROM clients c
WHERE c.code = 'ORACLE'
  AND NOT EXISTS (SELECT 1 FROM sites WHERE code = 'ORAC-RAB');

INSERT INTO users (first_name, last_name, email, password_hash, role, active, site_id)
SELECT 'Ahmed', 'Admin', 'admin@example.com', '$2y$12$rYoeI.AVp6x35gaeYgRF1eAfI8Sc08t9PZqxdU4SrZRcUpeAriIau', 'ADMIN', 1, NULL
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'admin@example.com');

INSERT INTO users (first_name, last_name, email, password_hash, role, active, site_id)
SELECT 'Leila', 'Manager', 'manager@example.com', '$2y$12$rYoeI.AVp6x35gaeYgRF1eAfI8Sc08t9PZqxdU4SrZRcUpeAriIau', 'MANAGER', 1, s.id
FROM sites s
WHERE s.code = 'ORAC-RAB'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'manager@example.com');

INSERT INTO users (first_name, last_name, email, password_hash, role, active, site_id)
SELECT 'Yassine', 'Benali', 'stock.manager@example.com', '$2y$12$rYoeI.AVp6x35gaeYgRF1eAfI8Sc08t9PZqxdU4SrZRcUpeAriIau', 'STOCK_MANAGER', 1, s.id
FROM sites s
WHERE s.code = 'ORAC-RAB'
  AND NOT EXISTS (SELECT 1 FROM users WHERE email = 'stock.manager@example.com');
