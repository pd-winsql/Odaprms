CREATE TABLE IF NOT EXISTS patient_profile_print_requests (
    request_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    clinic_id INT NOT NULL,
    purpose VARCHAR(40) NOT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    include_billing TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('Pending', 'Ready for pickup', 'Collected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    handled_by_user_id INT NULL,
    requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_profile_request_patient (patient_id, status),
    INDEX idx_profile_request_status (status, requested_at),
    CONSTRAINT fk_profile_request_patient FOREIGN KEY (patient_id) REFERENCES patients(patient_id),
    CONSTRAINT fk_profile_request_clinic FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id),
    CONSTRAINT fk_profile_request_handler FOREIGN KEY (handled_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
