SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS email_report_recipients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_recipient_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scheduled_report_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    report_date DATE NOT NULL,
    report_type VARCHAR(60) NOT NULL DEFAULT 'daily_overview',
    status VARCHAR(30) NOT NULL,
    recipients TEXT NULL,
    error_message TEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_report_run (report_date, report_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO email_report_recipients (email, active) VALUES
('info@motogreen.pt', 1),
('marketing@motogreen.pt', 1),
('l.dalogli@motogreen.pt', 1)
ON DUPLICATE KEY UPDATE active = VALUES(active);
