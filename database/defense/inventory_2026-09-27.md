# Defense database inventory — step 1

Source: live MariaDB database `db-oaprms-system` at localhost:3306, read at 2026-09-27 21:04 Asia/Manila, plus `db-oaprms-system.sql` and `database/migrations/`. All 31 base tables were counted successfully after the database repair.

## Keep for the defense

| Tables | Reason |
| --- | --- |
| `clinics` | Existing clinic records and schedule defaults. |
| `service_categories`, `services` | Service catalog, images, and pricing. |
| `site_settings` | Brand, booking rules, payment settings; set `deposit_amount` to PHP 150.00 after backup. |
| `users`, `staffs` | Retain the admin and dental assistant accounts needed for access; remove patient accounts only after identifying them. |

## Clear and reseed

| Group | Tables |
| --- | --- |
| Patient records | `patients`, `patient_account_link_authorizations`, `patient_conditions`, `patient_consent`, `patient_dental_history`, `patient_medical_history`, `patient_odontograms`, `patient_odontogram_teeth`, `patient_odontogram_snapshots`, `patient_duplicate_reviews` |
| Scheduling and visits | `schedules`, `appointments`, `appointment_services`, `appointment_checkins`, `appointment_reschedule_requests`, `appointment_reviews` |
| Payments | `appointment_deposits`, `appointment_billings`, `appointment_billing_items` |
| Communication and activity | `appointment_email_notifications`, `audit_logs`, `clinic_conversations`, `clinic_messages`, `email_verifications`, `password_resets` |

The daily logbook is derived from appointments and check-ins; there is no separate logbook table in the checked-in schema. `clinic_conversations` and `clinic_messages` come from the September 5 migration and are absent from the export. `vw_patient_information` and other `vw_*` objects are views, not rows to clear.

There are 48 stored receipt image files under `storage/payment_receipts/` (5,700,816 bytes). All 48 are referenced by deposit records; none are missing or unreferenced. The 13 service images under `public/uploads/services/`, clinic images, and site assets are retained.

## Live row counts

| Table | Rows | Table | Rows |
| --- | ---: | --- | ---: |
| `appointment_billing_items` | 39 | `appointment_billings` | 30 |
| `appointment_checkins` | 37 | `appointment_deposits` | 56 |
| `appointment_email_notifications` | 83 | `appointment_reschedule_requests` | 2 |
| `appointment_reviews` | 3 | `appointment_services` | 84 |
| `appointments` | 70 | `audit_logs` | 399 |
| `clinic_conversations` | 4 | `clinic_messages` | 14 |
| `clinics` | 2 | `email_verifications` | 14 |
| `password_resets` | 3 | `patient_account_link_authorizations` | 0 |
| `patient_conditions` | 3 | `patient_consent` | 17 |
| `patient_dental_history` | 17 | `patient_duplicate_reviews` | 0 |
| `patient_medical_history` | 17 | `patient_odontogram_snapshots` | 2 |
| `patient_odontogram_teeth` | 5 | `patient_odontograms` | 2 |
| `patients` | 21 | `schedules` | 40 |
| `service_categories` | 4 | `services` | 13 |
| `site_settings` | 1 | `staffs` | 5 |
| `users` | 27 | | |

Five views exist: `vw_appointment_latest_status_change`, `vw_appointment_overview`, `vw_appointment_payment_summary`, `vw_patient_information`, and `vw_schedule_utilization`. The live schema has 56 foreign keys and includes the later chat, reschedule, review, pricing, schedule-window, and odontogram structures checked in with migrations.

## Accounts to retain

The 27 users are 21 Patient, 2 Admin, and 4 Dental Assistant. Keep all six Admin/Dental Assistant accounts during the reset; names can be updated later. The role-linked user IDs are 7 (Admin, Test Admin), 16 (Dental Assistant, stephanie unista), 18 (Dental Assistant, Winje Corpuz), 22 (Dental Assistant, Pogi Naman), 30 (Admin, no `staffs` row), and 65 (Dental Assistant, Dental Assistant). All five staff rows link to a user. One patient record has no linked user account.

## Current defense readiness

The site setting is currently PHP 200.00 for the deposit, with a 480-minute payment deadline, seven-day minimum booking lead, and three-day minimum reschedule lead. The prepared PHP 150.00 update has **not** been applied; it belongs after the fresh backup.

Appointments total 70: 2 Confirmed, 2 Checked In, 2 In Progress, 32 Completed, 12 Cancelled, 16 No-show, and 4 Rejected. Deposits total 56: 1 Under Review, 39 Verified, 7 Expired, 1 Transferred, and 8 Forfeited. Three patient names match `test` or `pogi`. Appointment dates span July 27–September 30; schedule dates span July 27–October 6.

For September 28, one confirmed appointment (ID 718) already has a complete profile, verified deposit, appointment code, and no check-in, so it is eligible for the planned check-in demo. October 2 has a schedule at clinic 1 but no appointment. October 9 has no schedule yet. These will be prepared in the seed stage.

## Step 2 backup

Completed September 27: `database/backups/defense-pre-reset-20260927-210750/` contains a fresh `database.sql`, all 48 payment receipt files, and `manifest.json` with SHA-256 hashes. The SQL restored successfully into a temporary database with the same 31 tables, five working views, and all table row counts matching the live database. The receipt copies had no missing files or hash mismatches. The temporary database was dropped after verification. The backup directory is Git-ignored and contains private data.

No live records were changed during inventory or backup. The prepared PHP 150.00 setting update has not yet been applied. An earlier inventory attempt hit a MariaDB table-read failure; the full retry and backup completed after the user's repair.
