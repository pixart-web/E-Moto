SET NAMES utf8mb4;
CREATE TABLE IF NOT EXISTS cheque_notification_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 stock_id VARCHAR(80) NOT NULL,
 process_id VARCHAR(80) NULL,
 cheque_number VARCHAR(120) NOT NULL,
 notification_type VARCHAR(40) NOT NULL,
 status VARCHAR(30) NOT NULL,
 error_message TEXT NULL,
 sent_by BIGINT UNSIGNED NULL,
 sent_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 KEY idx_cheque_stock(stock_id,notification_type,status),
 CONSTRAINT fk_cheque_log_user FOREIGN KEY(sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
