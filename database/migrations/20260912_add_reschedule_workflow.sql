ALTER TABLE site_settings
    ADD COLUMN IF NOT EXISTS minimum_reschedule_lead_days TINYINT UNSIGNED NOT NULL DEFAULT 3
    COMMENT 'Minimum calendar days required before the requested replacement schedule.'
    AFTER minimum_booking_lead_days;

CREATE TABLE IF NOT EXISTS appointment_reschedule_requests (
    request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    requested_by_user_id INT NULL,
    original_schedule_id INT NOT NULL,
    original_clinic_id INT NOT NULL,
    original_date DATE NOT NULL,
    target_schedule_id INT NOT NULL,
    target_clinic_id INT NOT NULL,
    target_date DATE NOT NULL,
    reason VARCHAR(500) NOT NULL,
    status ENUM('Pending','Approved','Rejected','Withdrawn','Expired') NOT NULL DEFAULT 'Pending',
    lead_days_snapshot TINYINT UNSIGNED NOT NULL,
    expires_at DATETIME NOT NULL,
    reviewed_by_user_id INT NULL,
    reviewed_at DATETIME NULL,
    rejection_reason VARCHAR(500) NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (request_id),
    KEY idx_reschedule_appointment_status (appointment_id, status),
    KEY idx_reschedule_target_hold (target_schedule_id, status, expires_at),
    KEY idx_reschedule_expiry (status, expires_at),
    CONSTRAINT fk_reschedule_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_requester FOREIGN KEY (requested_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_original_schedule FOREIGN KEY (original_schedule_id) REFERENCES schedules (schedule_id) ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_original_clinic FOREIGN KEY (original_clinic_id) REFERENCES clinics (clinic_id) ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_target_schedule FOREIGN KEY (target_schedule_id) REFERENCES schedules (schedule_id) ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_target_clinic FOREIGN KEY (target_clinic_id) REFERENCES clinics (clinic_id) ON UPDATE CASCADE,
    CONSTRAINT fk_reschedule_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
