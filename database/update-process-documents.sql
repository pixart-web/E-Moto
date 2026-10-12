CREATE TABLE IF NOT EXISTS process_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    process_id VARCHAR(50) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(100) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_process_document_file (stored_name),
    KEY idx_process_documents_process (process_id),
    KEY idx_process_documents_user (uploaded_by),
    CONSTRAINT fk_process_documents_user FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
