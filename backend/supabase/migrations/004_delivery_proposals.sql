CREATE TABLE IF NOT EXISTS delivery_proposals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alert_id INT UNSIGNED NOT NULL,
    stock_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    status ENUM('PENDING','CONFIRMED','REJECTED') NOT NULL DEFAULT 'PENDING',
    proposed_by INT UNSIGNED NOT NULL,
    confirmed_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at DATETIME NULL,
    CONSTRAINT fk_proposals_alert FOREIGN KEY (alert_id) REFERENCES alerts(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposals_stock FOREIGN KEY (stock_id) REFERENCES stocks(id) ON DELETE CASCADE,
    CONSTRAINT fk_proposals_proposed_by FOREIGN KEY (proposed_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_proposals_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_proposals_status (status),
    INDEX idx_proposals_alert (alert_id),
    INDEX idx_proposals_stock (stock_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
