CREATE DATABASE IF NOT EXISTS odyssiavault CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE odyssiavault;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(32) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    balance BIGINT UNSIGNED NOT NULL DEFAULT 0,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    provider_order_id VARCHAR(64) NULL,
    service_id INT UNSIGNED NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    category VARCHAR(120) NOT NULL,
    target TEXT NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_buy_price BIGINT UNSIGNED NOT NULL,
    unit_sell_price BIGINT UNSIGNED NOT NULL,
    total_buy_price BIGINT UNSIGNED NOT NULL,
    total_sell_price BIGINT UNSIGNED NOT NULL,
    profit BIGINT UNSIGNED NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Processing',
    payment_deadline_at DATETIME NULL,
    payment_confirmed_at DATETIME NULL,
    payment_confirmed_by_admin_at DATETIME NULL,
    payment_method VARCHAR(30) NULL,
    payment_channel_name VARCHAR(120) NULL,
    payment_account_name VARCHAR(120) NULL,
    payment_account_number VARCHAR(80) NULL,
    payment_payer_name VARCHAR(120) NULL,
    payment_reference VARCHAR(120) NULL,
    payment_note TEXT NULL,
    provider_status VARCHAR(80) NULL,
    provider_start_count INT NULL,
    provider_remains INT NULL,
    payload_json LONGTEXT NULL,
    provider_response_json LONGTEXT NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_orders_user_created (user_id, created_at),
    INDEX idx_orders_provider (provider_order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_refills (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    provider_order_id VARCHAR(64) NOT NULL,
    provider_refill_id VARCHAR(64) NOT NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'Diproses',
    provider_status VARCHAR(80) NULL,
    provider_response_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_order_refills_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_refills_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_order_refills_provider_refill (provider_refill_id),
    INDEX idx_order_refills_user_created (user_id, created_at),
    INDEX idx_order_refills_order (order_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS balance_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('credit', 'debit') NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_balance_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_balance_user_created (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS deposit_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount BIGINT UNSIGNED NOT NULL,
    unique_code SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    amount_final BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('qris') NOT NULL DEFAULT 'qris',
    payer_name VARCHAR(120) NULL,
    payer_note TEXT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
    admin_note VARCHAR(255) NULL,
    approved_by INT UNSIGNED NULL,
    approved_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_deposits_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_deposits_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_deposits_user_created (user_id, created_at),
    INDEX idx_deposits_status_created (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS news_posts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    content LONGTEXT NOT NULL,
    source_name VARCHAR(120) NOT NULL DEFAULT 'BuzzerPanel',
    source_url VARCHAR(500) NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    published_at DATETIME NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    CONSTRAINT fk_news_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_news_published (is_published, published_at),
    INDEX idx_news_created (created_at)
) ENGINE=InnoDB;
