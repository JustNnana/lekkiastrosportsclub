-- ============================================================
--  Push Subscriptions — Web Push API subscription storage
--  Run this migration after the main schema.sql
-- ============================================================

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NOT NULL,
    endpoint     TEXT          NOT NULL,
    p256dh_key   TEXT          NOT NULL  COMMENT 'Browser public key',
    auth_key     VARCHAR(100)  NOT NULL  COMMENT 'Auth secret',
    user_agent   VARCHAR(255)  NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    -- One subscription endpoint per user (upsert-friendly)
    UNIQUE KEY uq_user_endpoint (user_id, endpoint(200)),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
