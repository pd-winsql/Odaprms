CREATE TABLE IF NOT EXISTS patient_odontograms (
    odontogram_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id INT NOT NULL,
    dentition_type ENUM('Permanent','Primary','Mixed') NOT NULL DEFAULT 'Permanent',
    periodontal_status ENUM('None','Gingivitis','Early Periodontitis','Moderate Periodontitis','Advanced Periodontitis') NOT NULL DEFAULT 'None',
    occlusion_class VARCHAR(32) DEFAULT NULL,
    occlusion_findings VARCHAR(255) DEFAULT NULL,
    appliances VARCHAR(255) DEFAULT NULL,
    tmd_findings VARCHAR(255) DEFAULT NULL,
    clinical_notes TEXT DEFAULT NULL,
    updated_by_user_id INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (odontogram_id),
    UNIQUE KEY uq_patient_odontogram (patient_id),
    KEY idx_odontogram_updated_by (updated_by_user_id),
    CONSTRAINT fk_odontogram_patient FOREIGN KEY (patient_id) REFERENCES patients (patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_odontogram_updated_by FOREIGN KEY (updated_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS patient_odontogram_teeth (
    odontogram_tooth_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    odontogram_id BIGINT UNSIGNED NOT NULL,
    tooth_number CHAR(2) NOT NULL,
    surface ENUM('Whole','Occlusal','Mesial','Distal','Buccal','Lingual') NOT NULL DEFAULT 'Whole',
    category ENUM('Condition','Restoration','Surgery') NOT NULL,
    finding_code VARCHAR(8) NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (odontogram_tooth_id),
    UNIQUE KEY uq_odontogram_tooth_finding (odontogram_id, tooth_number, surface, category),
    KEY idx_odontogram_tooth_number (tooth_number),
    CONSTRAINT fk_odontogram_tooth_chart FOREIGN KEY (odontogram_id) REFERENCES patient_odontograms (odontogram_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS patient_odontogram_snapshots (
    odontogram_snapshot_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id INT NOT NULL,
    appointment_id INT NOT NULL,
    chart_payload LONGTEXT NOT NULL,
    reviewed_by_user_id INT DEFAULT NULL,
    reviewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (odontogram_snapshot_id),
    UNIQUE KEY uq_odontogram_snapshot_appointment (appointment_id),
    KEY idx_odontogram_snapshot_patient (patient_id, reviewed_at),
    KEY idx_odontogram_snapshot_reviewer (reviewed_by_user_id),
    CONSTRAINT fk_odontogram_snapshot_patient FOREIGN KEY (patient_id) REFERENCES patients (patient_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_odontogram_snapshot_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_odontogram_snapshot_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
