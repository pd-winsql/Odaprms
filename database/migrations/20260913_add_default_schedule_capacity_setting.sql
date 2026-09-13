-- Configurable patient capacity prefilled for newly created schedules.
-- Existing schedules retain their saved max_appointments values.

ALTER TABLE `site_settings`
  ADD COLUMN IF NOT EXISTS `default_schedule_capacity` tinyint(3) UNSIGNED NOT NULL DEFAULT 15
    COMMENT 'Default maximum patients for each newly created schedule.'
    AFTER `clinic_transition_minutes`;
