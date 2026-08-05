-- Logbook and simplified GCash deposit foundation
-- Target: av-clinica-dental-feature
-- Existing appointments remain deposit-exempt; new appointments require payment.

START TRANSACTION;

ALTER TABLE appointments
    MODIFY status ENUM(
        'Pending',
        'Awaiting Payment',
        'Confirmed',
        'Completed',
        'Cancelled',
        'No-show',
        'Rescheduled',
        'Rejected'
    ) NOT NULL DEFAULT 'Awaiting Payment',
    ADD COLUMN deposit_required TINYINT(1) NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN payment_deadline_at DATETIME NULL AFTER deposit_required,
    ADD COLUMN payment_access_token_hash CHAR(64) NULL AFTER payment_deadline_at,
    ADD COLUMN confirmed_at DATETIME NULL AFTER payment_access_token_hash,
    ADD COLUMN cancelled_at DATETIME NULL AFTER confirmed_at,
    ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER cancelled_at,
    ADD KEY idx_appointments_patient (patient_id),
    ADD KEY idx_appointments_daily_logbook (date, status, clinic_id),
    ADD KEY idx_appointments_expiry (status, deposit_required, payment_deadline_at),
    ADD UNIQUE KEY uq_appointments_payment_token (payment_access_token_hash),
    ADD CONSTRAINT fk_appointments_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- Preserve legacy appointments as deposit-exempt, then make deposits the
-- default for every appointment inserted after this migration.
UPDATE appointments SET deposit_required = 0;
ALTER TABLE appointments
    ALTER deposit_required SET DEFAULT 1;

ALTER TABLE patients
    ADD COLUMN profile_completed_at DATETIME NULL AFTER created_at,
    ADD COLUMN profile_completed_by_user_id INT(11) NULL AFTER profile_completed_at,
    ADD KEY idx_patients_profile_completion (profile_completed_at),
    ADD KEY idx_patients_profile_completed_by (profile_completed_by_user_id),
    ADD CONSTRAINT fk_patients_profile_completed_by
        FOREIGN KEY (profile_completed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE patient_dental_history
    ADD UNIQUE KEY uq_patient_dental_history_patient (patient_id);
ALTER TABLE patient_medical_history
    ADD UNIQUE KEY uq_patient_medical_history_patient (patient_id);
ALTER TABLE patient_consent
    ADD UNIQUE KEY uq_patient_consent_patient (patient_id);

-- Treat legacy records as complete only when all major patient-form sections
-- already exist. Partial booking profiles intentionally remain incomplete.
UPDATE patients p
SET p.profile_completed_at = p.created_at
WHERE EXISTS (
        SELECT 1 FROM patient_dental_history dh WHERE dh.patient_id = p.patient_id
    )
  AND EXISTS (
        SELECT 1 FROM patient_medical_history mh WHERE mh.patient_id = p.patient_id
    )
  AND EXISTS (
        SELECT 1 FROM patient_consent pc WHERE pc.patient_id = p.patient_id
    );

ALTER TABLE site_settings
    ADD COLUMN deposit_amount DECIMAL(10,2) NOT NULL DEFAULT 400.00 AFTER contact_email,
    ADD COLUMN payment_deadline_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER deposit_amount,
    ADD COLUMN gcash_account_name VARCHAR(100) NULL AFTER payment_deadline_minutes,
    ADD COLUMN gcash_account_number VARCHAR(30) NULL AFTER gcash_account_name,
    ADD COLUMN gcash_qr_path VARCHAR(255) NULL AFTER gcash_account_number;

CREATE TABLE appointment_deposits (
    deposit_id INT(11) NOT NULL AUTO_INCREMENT,
    appointment_id INT(11) NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 400.00,
    gcash_reference VARCHAR(100) NULL,
    receipt_path VARCHAR(255) NULL,
    receipt_mime VARCHAR(100) NULL,
    status ENUM(
        'Awaiting Submission',
        'Under Review',
        'Verified',
        'Rejected',
        'Expired',
        'Transferred',
        'Forfeited'
    ) NOT NULL DEFAULT 'Awaiting Submission',
    submitted_at DATETIME NULL,
    verified_by_user_id INT(11) NULL,
    verified_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    resubmission_deadline_at DATETIME NULL,
    transferred_from_appointment_id INT(11) NULL,
    transferred_by_user_id INT(11) NULL,
    transferred_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (deposit_id),
    UNIQUE KEY uq_appointment_deposits_appointment (appointment_id),
    UNIQUE KEY uq_appointment_deposits_reference (gcash_reference),
    KEY idx_appointment_deposits_review (status, submitted_at),
    KEY idx_appointment_deposits_verifier (verified_by_user_id),
    KEY idx_appointment_deposits_transfer_source (transferred_from_appointment_id),
    KEY idx_appointment_deposits_transfer_actor (transferred_by_user_id),
    CONSTRAINT fk_deposits_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_deposits_verifier
        FOREIGN KEY (verified_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_deposits_transfer_source
        FOREIGN KEY (transferred_from_appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_deposits_transfer_actor
        FOREIGN KEY (transferred_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE appointment_checkins (
    checkin_id INT(11) NOT NULL AUTO_INCREMENT,
    appointment_id INT(11) NOT NULL,
    arrived_at DATETIME NOT NULL,
    checked_in_by_user_id INT(11) NULL,
    checkin_status ENUM('Profile Required', 'Ready') NOT NULL,
    profile_required_at_arrival TINYINT(1) NOT NULL DEFAULT 0,
    ready_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (checkin_id),
    UNIQUE KEY uq_appointment_checkins_appointment (appointment_id),
    KEY idx_appointment_checkins_arrival (arrived_at),
    KEY idx_appointment_checkins_actor (checked_in_by_user_id),
    CONSTRAINT fk_checkins_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_checkins_actor
        FOREIGN KEY (checked_in_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
