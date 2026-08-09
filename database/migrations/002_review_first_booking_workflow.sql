-- Review-first booking, appointment-code check-in, and final cash settlement
-- Target database only: av-clinica-dental-feature
-- Run this after 001_logbook_deposit_foundation.sql.

USE `av-clinica-dental-feature`;

-- Registration remains OTP-verified. Existing accounts are treated as verified
-- because the current application creates patient accounts only after OTP validation.
ALTER TABLE users
    ADD COLUMN email_verified_at DATETIME NULL AFTER password;

UPDATE users
SET email_verified_at = NOW()
WHERE email_verified_at IS NULL;

-- Store the separated registration name and a concurrency-safe exact-identity key.
-- profile_status supports saving the front-desk patient form as a draft.
ALTER TABLE patients
    ADD COLUMN suffix VARCHAR(20) NULL AFTER middlename,
    ADD COLUMN profile_status ENUM('Incomplete', 'Draft', 'Complete')
        NOT NULL DEFAULT 'Incomplete' AFTER profile_completed_by_user_id,
    ADD COLUMN identity_match_key CHAR(64) NULL AFTER profile_status,
    ADD KEY idx_patients_possible_duplicate
        (firstname, lastname, birthdate),
    ADD UNIQUE KEY uq_patients_identity_match (identity_match_key),
    ADD UNIQUE KEY uq_patients_user_account (user_id);

UPDATE patients
SET profile_status = CASE
    WHEN profile_completed_at IS NOT NULL THEN 'Complete'
    ELSE 'Incomplete'
END;

-- Normalize legacy names and Philippine mobile formats before generating the key.
-- New registrations must generate the same SHA-256 key in application code.
UPDATE patients
SET identity_match_key = SHA2(
    CONCAT(
        LOWER(REGEXP_REPLACE(TRIM(firstname), '[[:space:]]+', ' ')), '|',
        LOWER(REGEXP_REPLACE(TRIM(COALESCE(middlename, '')), '[[:space:]]+', ' ')), '|',
        LOWER(REGEXP_REPLACE(TRIM(lastname), '[[:space:]]+', ' ')), '|',
        LOWER(REGEXP_REPLACE(TRIM(COALESCE(suffix, '')), '[[:space:]]+', ' ')), '|',
        DATE_FORMAT(birthdate, '%Y-%m-%d'), '|',
        CASE
            WHEN LENGTH(REGEXP_REPLACE(phone_number, '[^0-9]', '')) = 12
                 AND REGEXP_REPLACE(phone_number, '[^0-9]', '') LIKE '63%'
                THEN CONCAT('0', SUBSTRING(REGEXP_REPLACE(phone_number, '[^0-9]', ''), 3))
            WHEN LENGTH(REGEXP_REPLACE(phone_number, '[^0-9]', '')) = 10
                 AND REGEXP_REPLACE(phone_number, '[^0-9]', '') LIKE '9%'
                THEN CONCAT('0', REGEXP_REPLACE(phone_number, '[^0-9]', ''))
            ELSE REGEXP_REPLACE(phone_number, '[^0-9]', '')
        END
    ),
    256
)
WHERE firstname IS NOT NULL
  AND lastname IS NOT NULL
  AND birthdate IS NOT NULL
  AND phone_number IS NOT NULL
  AND TRIM(phone_number) <> '';

-- An exact match is blocked until staff authorizes a verified email to link to
-- the existing patient record. This avoids creating a duplicate clinical record.
CREATE TABLE patient_account_link_authorizations (
    authorization_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id INT(11) NOT NULL,
    authorized_email VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Used', 'Revoked', 'Expired') NOT NULL DEFAULT 'Active',
    authorized_by_user_id INT(11) NULL,
    authorized_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    used_by_user_id INT(11) NULL,
    used_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (authorization_id),
    KEY idx_link_authorization_lookup (authorized_email, status, expires_at),
    KEY idx_link_authorization_patient (patient_id),
    KEY idx_link_authorization_actor (authorized_by_user_id),
    KEY idx_link_authorization_used_user (used_by_user_id),
    CONSTRAINT fk_link_authorization_patient
        FOREIGN KEY (patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_link_authorization_actor
        FOREIGN KEY (authorized_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_link_authorization_used_user
        FOREIGN KEY (used_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- A name-and-birthdate match with a different contact number is allowed to
-- register, but staff must resolve this flag before another profile is completed.
CREATE TABLE patient_duplicate_reviews (
    duplicate_review_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    new_patient_id INT(11) NOT NULL,
    possible_existing_patient_id INT(11) NOT NULL,
    match_basis VARCHAR(100) NOT NULL DEFAULT 'Name and birthdate',
    status ENUM('Pending', 'Linked', 'Dismissed') NOT NULL DEFAULT 'Pending',
    reviewed_by_user_id INT(11) NULL,
    reviewed_at DATETIME NULL,
    resolution_notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (duplicate_review_id),
    UNIQUE KEY uq_duplicate_review_pair
        (new_patient_id, possible_existing_patient_id),
    KEY idx_duplicate_review_queue (status, created_at),
    KEY idx_duplicate_review_actor (reviewed_by_user_id),
    CONSTRAINT fk_duplicate_review_new_patient
        FOREIGN KEY (new_patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_duplicate_review_existing_patient
        FOREIGN KEY (possible_existing_patient_id) REFERENCES patients(patient_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_duplicate_review_actor
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Keep legacy status values temporarily so existing rows migrate without loss.
-- Application code will only create/use the new controlled workflow values.
ALTER TABLE appointments
    MODIFY status ENUM(
        'Pending Review',
        'Awaiting Deposit',
        'Payment Under Review',
        'Confirmed',
        'Checked In',
        'In Progress',
        'Completed',
        'Cancelled',
        'No-show',
        'Rejected',
        'Pending',
        'Awaiting Payment',
        'Rescheduled'
    ) NOT NULL DEFAULT 'Pending Review',
    ADD COLUMN reviewed_by_user_id INT(11) NULL AFTER payment_access_token_hash,
    ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by_user_id,
    ADD COLUMN accepted_for_payment_at DATETIME NULL AFTER reviewed_at,
    ADD COLUMN rejected_at DATETIME NULL AFTER accepted_for_payment_at,
    ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER rejected_at,
    ADD COLUMN appointment_code VARCHAR(20) NULL AFTER rejection_reason,
    ADD COLUMN code_generated_at DATETIME NULL AFTER appointment_code,
    ADD COLUMN treatment_started_at DATETIME NULL AFTER confirmed_at,
    ADD COLUMN completed_at DATETIME NULL AFTER treatment_started_at,
    ADD UNIQUE KEY uq_appointments_code (appointment_code),
    ADD KEY idx_appointments_review_queue (status, created_at),
    ADD KEY idx_appointments_reviewer (reviewed_by_user_id),
    ADD CONSTRAINT fk_appointments_reviewer
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

UPDATE appointments
SET status = 'Pending Review'
WHERE status = 'Pending';

UPDATE appointments
SET status = 'Awaiting Deposit'
WHERE status = 'Awaiting Payment';

-- Preserve check-in usability for appointments confirmed before this migration.
UPDATE appointments
SET appointment_code = CONCAT(
        'AVC-',
        UPPER(SUBSTRING(MD5(CONCAT(UUID(), '-', appointment_id)), 1, 8))
    ),
    code_generated_at = COALESCE(confirmed_at, NOW())
WHERE status = 'Confirmed'
  AND appointment_code IS NULL;

-- Deposit verification remains independent from the appointment lifecycle.
ALTER TABLE appointment_deposits
    MODIFY status ENUM(
        'Awaiting Submission',
        'Under Review',
        'Verified',
        'Rejected',
        'Expired',
        'Transferred',
        'Forfeited',
        'For Refund',
        'Refunded'
    ) NOT NULL DEFAULT 'Awaiting Submission',
    ADD COLUMN deadline_extended_by_user_id INT(11) NULL AFTER resubmission_deadline_at,
    ADD COLUMN deadline_extended_at DATETIME NULL AFTER deadline_extended_by_user_id,
    ADD COLUMN deadline_extension_reason VARCHAR(255) NULL AFTER deadline_extended_at,
    ADD COLUMN transfer_reason VARCHAR(255) NULL AFTER transferred_at,
    ADD COLUMN refund_reason VARCHAR(255) NULL AFTER transfer_reason,
    ADD COLUMN refunded_by_user_id INT(11) NULL AFTER refund_reason,
    ADD COLUMN refunded_at DATETIME NULL AFTER refunded_by_user_id,
    ADD COLUMN refund_notes VARCHAR(255) NULL AFTER refunded_at,
    ADD UNIQUE KEY uq_deposit_transfer_source (transferred_from_appointment_id),
    ADD KEY idx_deposit_deadline_extension_actor (deadline_extended_by_user_id),
    ADD KEY idx_deposit_refund_actor (refunded_by_user_id),
    ADD CONSTRAINT fk_deposit_deadline_extension_actor
        FOREIGN KEY (deadline_extended_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    ADD CONSTRAINT fk_deposit_refund_actor
        FOREIGN KEY (refunded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- The eight-hour timer starts only after staff accepts an appointment for payment.
UPDATE site_settings
SET payment_deadline_minutes = 480
WHERE id = 1;

-- Record how the appointment was found and any audited same-day override.
ALTER TABLE appointment_checkins
    ADD COLUMN lookup_method ENUM('Code', 'Patient Search', 'Date Override')
        NOT NULL DEFAULT 'Code' AFTER checked_in_by_user_id,
    ADD COLUMN date_override_reason VARCHAR(255) NULL AFTER notes,
    ADD COLUMN date_override_by_user_id INT(11) NULL AFTER date_override_reason,
    ADD KEY idx_checkin_override_actor (date_override_by_user_id),
    ADD CONSTRAINT fk_checkin_override_actor
        FOREIGN KEY (date_override_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE;

-- The final service amount and remaining cash balance are recorded manually.
-- Appointment completion is intentionally not dependent on this row being Paid.
CREATE TABLE appointment_billings (
    billing_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT(11) NOT NULL,
    actual_service_amount DECIMAL(10,2) NULL,
    deposit_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_balance DECIMAL(10,2) NULL,
    cash_received DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('Unpaid', 'Partially Paid', 'Paid')
        NOT NULL DEFAULT 'Unpaid',
    recorded_by_user_id INT(11) NULL,
    recorded_at DATETIME NULL,
    paid_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (billing_id),
    UNIQUE KEY uq_appointment_billing (appointment_id),
    KEY idx_appointment_billing_status (payment_status, updated_at),
    KEY idx_appointment_billing_actor (recorded_by_user_id),
    CONSTRAINT fk_appointment_billing_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_appointment_billing_actor
        FOREIGN KEY (recorded_by_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Queue/log appointment emails so sends can be retried and duplicate messages avoided.
CREATE TABLE appointment_email_notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    appointment_id INT(11) NOT NULL,
    recipient_user_id INT(11) NULL,
    notification_type VARCHAR(50) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    payload LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL
        CHECK (payload IS NULL OR JSON_VALID(payload)),
    deduplication_key VARCHAR(150) NULL,
    delivery_status ENUM('Pending', 'Sent', 'Failed') NOT NULL DEFAULT 'Pending',
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(500) NULL,
    scheduled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    UNIQUE KEY uq_email_notification_deduplication (deduplication_key),
    KEY idx_email_notification_queue (delivery_status, scheduled_at),
    KEY idx_email_notification_appointment (appointment_id),
    KEY idx_email_notification_recipient (recipient_user_id),
    CONSTRAINT fk_email_notification_appointment
        FOREIGN KEY (appointment_id) REFERENCES appointments(appointment_id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_email_notification_recipient
        FOREIGN KEY (recipient_user_id) REFERENCES users(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
