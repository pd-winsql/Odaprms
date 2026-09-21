# Database Schema Consolidation Audit

Date: 2026-09-21  
Database reviewed: `db-oaprms-system`  
Scope: read-only schema, usage, relationship, row-count, and lifecycle review

## Executive conclusion

The database currently contains **30 physical tables and 5 views**. All 30 physical tables are referenced by active application code or support a currently implemented workflow. No table is safe to drop as unused.

The current table count is not a scaling problem. The database is small, foreign-key relationships are meaningful, and the separate tables mostly represent different security, audit, or workflow lifecycles. Aggressively merging them would reduce the visible table count while increasing nullability, coupling, and migration risk.

Recommended outcome:

1. Keep all current business-domain tables.
2. Apply constraint and index cleanup independently of table consolidation.
3. Optionally consolidate the two short-lived account-token tables into one purpose-based table, but only on a separate test database and only if reducing table count is itself a project requirement.
4. Prefer retention and archival policies for growing event tables over merging tables.

No database rows or schema objects were changed during this audit.

## Inventory

| Domain | Tables | Assessment |
| --- | --- | --- |
| Accounts and people | `users`, `staffs`, `patients` | Keep. Role-specific profile data is correctly separated from authentication identity. |
| Patient clinical profile | `patient_medical_history`, `patient_dental_history`, `patient_conditions`, `patient_consent` | Keep. These have different privacy, cardinality, and update/consent lifecycles. |
| Patient identity review | `patient_account_link_authorizations`, `patient_duplicate_reviews` | Keep. Currently empty, but both are referenced by active registration/account-linking code and model explicit security workflows. |
| Appointments | `appointments`, `appointment_services`, `appointment_reschedule_requests`, `appointment_reviews` | Keep. The child tables preserve multi-service selection and independent request/review history. |
| Arrival and treatment | `appointment_checkins` | Keep. Queue state and check-in actors have a separate operational lifecycle. |
| Payments | `appointment_deposits`, `appointment_billings`, `appointment_billing_items` | Keep. Deposits, final settlement, and performed line items are materially different records. |
| Communications | `clinic_conversations`, `clinic_messages`, `appointment_email_notifications` | Keep. Conversation identity, message history, and outbound delivery attempts are separate concerns. |
| Dental chart | `patient_odontograms`, `patient_odontogram_teeth`, `patient_odontogram_snapshots` | Keep. Current findings and immutable appointment snapshots must not be collapsed. |
| Clinic catalog and capacity | `clinics`, `schedules`, `service_categories`, `services` | Keep. These are normalized, actively managed reference/availability data. |
| Configuration and audit | `site_settings`, `audit_logs` | Keep. Singleton configuration does not belong in source code, and audit history must remain append-oriented. |
| Account challenges | `email_verifications`, `password_resets` | Optional consolidation candidate described below. |

## Evidence from live data

- `appointments`: 70 rows
- `appointment_deposits`: 56 rows
- `appointment_billings`: 30 rows
- `appointment_checkins`: 37 rows
- `appointment_services`: 84 rows
- `appointment_email_notifications`: 82 rows
- `audit_logs`: 391 rows
- `patients`: 21 rows
- `patient_medical_history`: 17 patients covered
- `patient_dental_history`: 17 patients covered
- `patient_consent`: 17 patients covered
- `patient_conditions`: 2 patients covered, correctly reflecting a sparse one-to-many relationship
- `patient_account_link_authorizations` and `patient_duplicate_reviews`: zero rows, but both have active code references and represent exceptional workflows rather than obsolete storage

The appointment data also spans completed, cancelled, no-show, rejected, confirmed, checked-in, in-progress, and payment-review states. This supports keeping the existing workflow boundaries rather than flattening them.

## Why the tempting merges are not recommended

### Patient profile extension tables into `patients`

`patients` already has 27 columns. Medical history has 26 more, dental history has 10, and consent has 6. Combining them would create a record of more than 60 columns, mix identity with clinical and legal data, and make partial profile completion harder to model. Conditions are multi-valued and cannot be safely flattened without JSON or repeated columns.

### Deposits and final billing

A deposit reserves an appointment and has receipt verification, expiration, transfer, refund, and forfeiture state. Final billing records actual treatment value, applied deposit, cash received, balance, and settlement actor. Combining them would create ambiguous status and actor fields and weaken the existing rule that only an administrator performs final settlement.

### Booked services and billing items

Booked services express patient intent. Billing items express services actually charged after treatment and may preserve price/description snapshots. They must remain separate to support changes between booking and treatment.

### Conversations and messages

Removing `clinic_conversations` would duplicate patient/thread identity on every message and complicate unread state, pagination, and future assignment metadata. The current one-conversation-per-patient model is conventional and scalable.

### Odontogram tables

The current chart, individual tooth findings, and appointment snapshots have different cardinality and mutability. Merging them would either duplicate chart metadata per tooth or lose immutable clinical history.

## Optional consolidation: account challenge tokens

`email_verifications` and `password_resets` overlap substantially: email, token/OTP, expiry, used state, and creation time. They could become one table such as `account_challenges` with:

- `challenge_id`
- nullable `user_id`
- `email`
- `purpose` (`email_verification`, `password_reset`)
- `secret_hash`
- `expires_at`
- `used_at`
- `created_at`
- optional attempt/rate-limit metadata

This would reduce the physical-table count from 30 to 29 and provide one place for token hashing and expiry rules. The benefit is modest, and both current tables are active. Treat this as a security-oriented refactor, not an urgent optimization.

Required migration safeguards:

1. Create the new table without removing either source table.
2. Backfill all records and preserve used/expired state.
3. Update registration and password-reset code behind a reversible compatibility layer.
4. Test verification, resend, expiry, replay prevention, reset, and rate limiting on a cloned database.
5. Run both paths long enough to verify parity.
6. Remove the old tables only in a later migration with a verified backup and rollback script.

## Safe schema cleanup unrelated to table count

The following duplicate metadata was found:

- `appointments.schedule_id` has two foreign keys to `schedules.schedule_id`: `fk_appointment` and `fk_appointments_schedule`. Retain one after confirming both use the same update/delete actions.
- `patients.user_id` has a unique index (`uq_patients_user_account`) and an additional non-unique index (`user_id`) on the same single column.
- `patient_consent.patient_id` has both a unique index and an additional non-unique single-column index.
- `patient_dental_history.patient_id` has both a unique index and an additional non-unique single-column index.
- `patient_medical_history.patient_id` has both a unique index and an additional non-unique single-column index.

MySQL can use the unique indexes for the same lookups and foreign-key support, so the redundant non-unique indexes are candidates for removal after verifying the target MySQL/MariaDB version and testing the migration. These changes improve schema clarity and write overhead without weakening domain modeling.

## Growth recommendations

For scale, focus on row growth and query behavior rather than table count:

- Define retention/archive rules for `audit_logs` and old `appointment_email_notifications`.
- Monitor slow queries and actual index usage before adding or removing performance indexes.
- Keep immutable billing, consent, odontogram snapshot, and audit history.
- Paginate operational lists and avoid loading full histories into dashboard requests.
- Preserve the five active views; they centralize repeated joins without duplicating stored data.

## Decision

Recommended now: **keep 30 tables, clean duplicate constraints/indexes in a separately approved migration, and document retention policies.**

Optional later: **30 to 29 tables** by unifying account challenge tokens, after a cloned-database migration and security regression test.
