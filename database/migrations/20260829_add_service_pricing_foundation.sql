-- Service pricing foundation
--
-- This migration deliberately leaves catalog prices nullable. It prepares the
-- database for itemized receipts without inventing prices for historical
-- appointments that contain more than one service.

ALTER TABLE `services`
  ADD COLUMN IF NOT EXISTS `default_price` decimal(10,2) DEFAULT NULL
    COMMENT 'Current catalog price; NULL until service pricing is configured.'
    AFTER `service_icon`;

ALTER TABLE `appointment_services`
  ADD COLUMN IF NOT EXISTS `quantity` decimal(8,2) NOT NULL DEFAULT 1.00
    COMMENT 'Number of units of this service for the appointment.'
    AFTER `service_id`,
  ADD COLUMN IF NOT EXISTS `unit_price_snapshot` decimal(10,2) DEFAULT NULL
    COMMENT 'Price captured for this appointment; NULL for legacy/unpriced records.'
    AFTER `quantity`;

CREATE TABLE IF NOT EXISTS `appointment_billing_items` (
  `billing_item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `billing_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name_snapshot` varchar(100) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `line_total` decimal(10,2) GENERATED ALWAYS AS
    (CASE
      WHEN `unit_price` IS NULL THEN NULL
      ELSE round(`quantity` * `unit_price`, 2)
    END) STORED,
  `pricing_source` varchar(32) NOT NULL DEFAULT 'legacy-unknown',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`billing_item_id`),
  UNIQUE KEY `uq_billing_item_service` (`billing_id`, `service_id`),
  KEY `idx_billing_items_service` (`service_id`),
  CONSTRAINT `fk_billing_items_billing`
    FOREIGN KEY (`billing_id`) REFERENCES `appointment_billings` (`billing_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_billing_items_service`
    FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Preserve every service on an existing billed appointment. A single-service
-- bill has one defensible historical price: the bill's actual service amount.
-- Multi-service bills remain explicitly unpriced so later UI work cannot show
-- a fabricated per-service allocation.
INSERT IGNORE INTO `appointment_billing_items` (
  `billing_id`,
  `service_id`,
  `service_name_snapshot`,
  `quantity`,
  `unit_price`,
  `pricing_source`,
  `sort_order`
)
SELECT
  `billing`.`billing_id`,
  `service`.`service_id`,
  `service`.`service_name`,
  1.00,
  CASE
    WHEN `service_count`.`total_services` = 1
      THEN `billing`.`actual_service_amount`
    ELSE NULL
  END,
  CASE
    WHEN `service_count`.`total_services` = 1
      AND `billing`.`actual_service_amount` IS NOT NULL
      THEN 'legacy-total'
    ELSE 'legacy-unknown'
  END,
  `service`.`display_order`
FROM `appointment_billings` AS `billing`
INNER JOIN (
  SELECT `appointment_id`, count(*) AS `total_services`
  FROM `appointment_services`
  GROUP BY `appointment_id`
) AS `service_count`
  ON `service_count`.`appointment_id` = `billing`.`appointment_id`
INNER JOIN `appointment_services` AS `appointment_service`
  ON `appointment_service`.`appointment_id` = `billing`.`appointment_id`
INNER JOIN `services` AS `service`
  ON `service`.`service_id` = `appointment_service`.`service_id`;
