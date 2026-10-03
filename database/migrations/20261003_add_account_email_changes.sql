CREATE TABLE IF NOT EXISTS account_email_changes (
    user_id INT NOT NULL PRIMARY KEY,
    new_email VARCHAR(255) NOT NULL,
    old_email VARCHAR(255) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    password_fingerprint CHAR(64) NOT NULL,
    request_token CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    sent_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    CONSTRAINT fk_account_email_changes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
