-- Store patient-confirmed details read from an uploaded GCash receipt.
-- `amount` remains the clinic's required deposit; `receipt_amount` is the
-- amount printed on the submitted proof.

ALTER TABLE `appointment_deposits`
  ADD COLUMN IF NOT EXISTS `receipt_amount` decimal(10,2) NULL AFTER `amount`,
  ADD COLUMN IF NOT EXISTS `gcash_transaction_at` datetime NULL AFTER `gcash_reference`;

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_appointment_payment_summary` AS
SELECT `a`.`appointment_id` AS `appointment_id`, `d`.`deposit_id` AS `deposit_id`,
  `d`.`amount` AS `deposit_amount`, `d`.`receipt_amount` AS `receipt_amount`,
  case when `d`.`status` in ('Verified','Transferred') then coalesce(`d`.`amount`,0) else 0 end AS `verified_deposit`,
  `d`.`gcash_reference` AS `gcash_reference`, `d`.`gcash_transaction_at` AS `gcash_transaction_at`,
  `d`.`receipt_path` AS `receipt_path`, `d`.`receipt_mime` AS `receipt_mime`, `d`.`status` AS `deposit_status`,
  `d`.`submitted_at` AS `submitted_at`, `d`.`verified_at` AS `verified_at`,
  `d`.`rejection_reason` AS `payment_rejection_reason`,
  `d`.`resubmission_deadline_at` AS `resubmission_deadline_at`, `d`.`refund_reason` AS `refund_reason`,
  `d`.`refunded_at` AS `refunded_at`, case when `d`.`receipt_path` is null then 0 else 1 end AS `has_receipt`,
  `verifier`.`email` AS `payment_verified_by`, `verifier`.`user_role` AS `payment_verified_by_role`,
  coalesce(nullif(trim(concat_ws(' ',`verifier_staff`.`firstname`,`verifier_staff`.`middlename`,`verifier_staff`.`lastname`)),''),`verifier`.`email`) AS `payment_verified_by_name`,
  `b`.`billing_id` AS `billing_id`, `b`.`actual_service_amount` AS `actual_service_amount`,
  `b`.`deposit_applied` AS `deposit_applied`, `b`.`remaining_balance` AS `remaining_balance`,
  `b`.`cash_received` AS `cash_received`, coalesce(`b`.`payment_status`,'Unpaid') AS `payment_status`,
  `b`.`recorded_at` AS `billing_recorded_at`, `b`.`paid_at` AS `paid_at`, `b`.`notes` AS `billing_notes`,
  coalesce(nullif(trim(concat_ws(' ',`recorder_staff`.`firstname`,`recorder_staff`.`middlename`,`recorder_staff`.`lastname`)),''),`recorder`.`email`,'Staff') AS `billing_recorded_by`
FROM ((((((`appointments` `a` LEFT JOIN `appointment_deposits` `d` ON (`d`.`appointment_id` = `a`.`appointment_id`))
LEFT JOIN `users` `verifier` ON (`verifier`.`id` = `d`.`verified_by_user_id`))
LEFT JOIN `staffs` `verifier_staff` ON (`verifier_staff`.`user_id` = `verifier`.`id`))
LEFT JOIN `appointment_billings` `b` ON (`b`.`appointment_id` = `a`.`appointment_id`))
LEFT JOIN `users` `recorder` ON (`recorder`.`id` = `b`.`recorded_by_user_id`))
LEFT JOIN `staffs` `recorder_staff` ON (`recorder_staff`.`user_id` = `recorder`.`id`));
