-- Configurable patient eligibility policy.
-- Existing installations begin with the agreed one-year minimum age.

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `minimum_patient_age_years` tinyint(3) UNSIGNED NOT NULL DEFAULT 1
    COMMENT 'Minimum age required for patient registration and appointment booking.'
    AFTER `clinic_transition_minutes`;
