-- Optional service catalog pricing and its billing unit.
-- Prices remain nullable because some treatments require an assessed charge.

ALTER TABLE `services`
  ADD COLUMN IF NOT EXISTS `billing_unit` enum('service','tooth') NOT NULL DEFAULT 'service'
    COMMENT 'How the optional default price is multiplied during final billing.'
    AFTER `default_price`;

ALTER TABLE `appointment_services`
  ADD COLUMN IF NOT EXISTS `billing_unit_snapshot` enum('service','tooth') DEFAULT NULL
    COMMENT 'Billing unit captured for this appointment.'
    AFTER `unit_price_snapshot`;

ALTER TABLE `appointment_billing_items`
  ADD COLUMN IF NOT EXISTS `billing_unit` enum('service','tooth') NOT NULL DEFAULT 'service'
    COMMENT 'Billing unit used for the settled line item.'
    AFTER `unit_price`;

