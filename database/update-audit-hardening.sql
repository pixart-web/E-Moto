SET NAMES utf8mb4;

-- Proteção contra tentativas repetidas de login. Esta migração é aditiva:
-- não altera nem elimina clientes, processos, stock ou outros dados existentes.
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_login_attempt_ip_time (ip_address, attempted_at),
    KEY idx_login_attempt_user_time (username, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
