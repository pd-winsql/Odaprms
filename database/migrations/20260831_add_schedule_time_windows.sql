-- Clinic schedule time windows
--
-- Existing date-only schedules are backfilled from their clinic's default
-- hours. Both clinics may then use the same date, provided their windows do
-- not overlap and the application-enforced transition interval is respected.

ALTER TABLE `clinics`
  ADD COLUMN IF NOT EXISTS `default_start_time` time NOT NULL DEFAULT '08:00:00'
    COMMENT 'Default opening time copied into newly created schedules.'
    AFTER `clinic_image`,
  ADD COLUMN IF NOT EXISTS `default_end_time` time NOT NULL DEFAULT '17:00:00'
    COMMENT 'Default closing time copied into newly created schedules.'
    AFTER `default_start_time`;

ALTER TABLE `schedules`
  ADD COLUMN IF NOT EXISTS `start_time` time NULL AFTER `sched_date`,
  ADD COLUMN IF NOT EXISTS `end_time` time NULL AFTER `start_time`;

UPDATE `schedules` AS `schedule_row`
INNER JOIN `clinics` AS `clinic`
  ON `clinic`.`clinic_id` = `schedule_row`.`clinic_id`
SET
  `schedule_row`.`start_time` = COALESCE(`schedule_row`.`start_time`, `clinic`.`default_start_time`, '08:00:00'),
  `schedule_row`.`end_time` = COALESCE(`schedule_row`.`end_time`, `clinic`.`default_end_time`, '17:00:00');

ALTER TABLE `schedules`
  MODIFY `start_time` time NOT NULL DEFAULT '08:00:00',
  MODIFY `end_time` time NOT NULL DEFAULT '17:00:00',
  DROP INDEX `uq_schedules_sched_date`,
  ADD UNIQUE KEY `uq_schedules_clinic_date` (`clinic_id`, `sched_date`),
  ADD KEY `idx_schedules_date_window` (`sched_date`, `start_time`, `end_time`);

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_appointment_overview` AS
SELECT `a`.`appointment_id` AS `appointment_id`, `a`.`patient_id` AS `patient_id`,
  `a`.`schedule_id` AS `schedule_id`, `a`.`clinic_id` AS `clinic_id`, `a`.`date` AS `date`,
  `schedule_row`.`start_time` AS `start_time`, `schedule_row`.`end_time` AS `end_time`,
  `a`.`status` AS `status`, `a`.`deposit_required` AS `deposit_required`,
  `a`.`payment_deadline_at` AS `payment_deadline_at`, `a`.`reviewed_at` AS `reviewed_at`,
  `a`.`accepted_for_payment_at` AS `accepted_for_payment_at`, `a`.`rejected_at` AS `rejected_at`,
  `a`.`rejection_reason` AS `rejection_reason`, `a`.`appointment_code` AS `appointment_code`,
  `a`.`code_generated_at` AS `code_generated_at`, `a`.`confirmed_at` AS `confirmed_at`,
  `a`.`treatment_started_at` AS `treatment_started_at`, `a`.`completed_at` AS `completed_at`,
  `a`.`cancelled_at` AS `cancelled_at`, `a`.`cancellation_reason` AS `cancellation_reason`,
  `a`.`created_at` AS `created_at`, `p`.`firstname` AS `firstname`, `p`.`middlename` AS `middlename`,
  `p`.`lastname` AS `lastname`, `p`.`suffix` AS `suffix`,
  concat(`p`.`lastname`,', ',`p`.`firstname`,case when nullif(trim(`p`.`middlename`),'') is null then '' else concat(' ',left(trim(`p`.`middlename`),1),'.') end,case when nullif(trim(`p`.`suffix`),'') is null then '' else concat(' ',trim(`p`.`suffix`)) end) AS `patient_name`,
  `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`phone_number` AS `phone_number`,
  `p`.`email` AS `email`, `p`.`profile_status` AS `profile_status`,
  `p`.`profile_completed_at` AS `profile_completed_at`, `c`.`clinic_name` AS `clinic_name`,
  (SELECT group_concat(`service`.`service_name` order by `service`.`display_order`,`service`.`service_name` separator ', ')
   FROM (`appointment_services` `appointment_service` JOIN `services` `service` ON (`service`.`service_id` = `appointment_service`.`service_id`))
   WHERE `appointment_service`.`appointment_id` = `a`.`appointment_id`) AS `service_name`
FROM (((`appointments` `a` JOIN `patients` `p` ON (`p`.`patient_id` = `a`.`patient_id`))
LEFT JOIN `clinics` `c` ON (`c`.`clinic_id` = `a`.`clinic_id`))
LEFT JOIN `schedules` `schedule_row` ON (`schedule_row`.`schedule_id` = `a`.`schedule_id`));

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_schedule_utilization` AS
SELECT `s`.`schedule_id` AS `schedule_id`, `s`.`clinic_id` AS `clinic_id`,
  `c`.`clinic_name` AS `clinic_name`, `s`.`sched_date` AS `sched_date`,
  `s`.`start_time` AS `start_time`, `s`.`end_time` AS `end_time`,
  `s`.`max_appointments` AS `capacity`,
  count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) AS `booked`,
  count(case when `a`.`status` = 'Completed' then `a`.`appointment_id` end) AS `completed`,
  count(case when `a`.`status` in ('Cancelled','Rejected') then `a`.`appointment_id` end) AS `cancelled`,
  greatest(`s`.`max_appointments` - count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end),0) AS `available_slots`,
  case when `s`.`max_appointments` = 0 then 0 else round(count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) * 100 / `s`.`max_appointments`,1) end AS `utilization_rate`
FROM ((`schedules` `s` JOIN `clinics` `c` ON (`c`.`clinic_id` = `s`.`clinic_id`))
LEFT JOIN `appointments` `a` ON (`a`.`schedule_id` = `s`.`schedule_id`))
GROUP BY `s`.`schedule_id`,`s`.`clinic_id`,`c`.`clinic_name`,`s`.`sched_date`,`s`.`start_time`,`s`.`end_time`,`s`.`max_appointments`;
