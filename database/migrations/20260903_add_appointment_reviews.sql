CREATE TABLE IF NOT EXISTS appointment_reviews (
    review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    feedback VARCHAR(1000) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (review_id),
    UNIQUE KEY uq_appointment_reviews_appointment (appointment_id),
    KEY idx_appointment_reviews_created (created_at),
    CONSTRAINT fk_appointment_reviews_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_appointment_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
