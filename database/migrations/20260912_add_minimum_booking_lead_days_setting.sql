-- Global minimum notice required for patient-created appointment requests.
-- Existing installations begin with the agreed seven-calendar-day policy.

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `minimum_booking_lead_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 7
    COMMENT 'Minimum calendar days required between a patient booking request and its appointment date.'
    AFTER `minimum_patient_age_years`;
