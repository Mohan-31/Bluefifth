-- order_returns_migration.sql
-- Run this in phpMyAdmin BEFORE registering the Shiprocket return webhook.
-- The return_webhook.php endpoint requires this table to exist.

CREATE TABLE IF NOT EXISTS order_returns (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    order_id             INT          NOT NULL,
    shiprocket_return_id VARCHAR(100) DEFAULT NULL,
    return_status        VARCHAR(50)  DEFAULT 'requested',
    return_awb           VARCHAR(100) DEFAULT NULL,
    return_reason        TEXT         DEFAULT NULL,
    photo_path           VARCHAR(500) DEFAULT NULL,
    created_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_order_id            (order_id),
    INDEX idx_shiprocket_return   (shiprocket_return_id),
    INDEX idx_return_status       (return_status),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
