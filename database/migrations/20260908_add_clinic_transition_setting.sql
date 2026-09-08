-- Editable separation between clinic schedule windows
--
-- The schedule model reads this value for all cross-clinic conflict checks.
-- Existing installations retain the previous 90-minute operating rule.

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `clinic_transition_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 90
    COMMENT 'Minimum separation between different clinic windows on the same date.'
    AFTER `payment_deadline_minutes`;
