# Project context: Online Appointment and Patient Records Management System

Last reviewed: 2026-09-25

## Purpose

This repository contains the web system for **Dr. Aprille Ventura Clinica Dental**. It lets patients request dental appointments at the Alcala and Tuguegarao clinics, manage their profiles and visit history, submit appointment deposits, and message the clinic. Clinic staff manage schedules, requests, patient records, check-ins, treatment, payments, and operational reports.

This document describes the implementation visible in the repository. It is a starting point for development and onboarding, not a substitute for checking the current database and deployed configuration.

## Users and main workflows

| Role | Main capabilities |
| --- | --- |
| Visitor | Browse clinics and services; register or sign in. |
| Patient | Maintain a profile and medical/dental history; request an appointment; submit a deposit after review; view notifications, billing and visit history; request rescheduling; message the clinic. |
| Dental Assistant | Manage daily appointments and check-ins, schedules, services, patient records, deposit reviews, and clinic messages. |
| Admin | Oversee staff and operations, activity logs, analytics, reports, site settings, and final billing/settlement. |

The booking flow starts with a patient selecting a clinic, schedule, and one or more active services. New requests enter **Pending Review**. Staff review the request, after which the deposit and confirmation stages can proceed. Check-in, treatment, completion, cancellation, no-show, and rescheduling have separate states and records. The booked services represent the patient's request; final billing items represent what was actually charged after treatment.

## Technical shape

- **Runtime:** server-rendered PHP with JavaScript enhancements, served by Apache/XAMPP. PHP uses PDO with MySQL/MariaDB.
- **Dependencies:** Composer provides `phpmailer/phpmailer` and `vlucas/phpdotenv`. npm is used for Playwright regression tests, not the application runtime.
- **Entry points:** `index.php` is the public landing page. Authentication pages are in `apps/views/`. Role dashboards are `apps/views/patient/dashboard.php`, `apps/views/dental_asst/dashboard.php`, and `apps/views/admin/dashboard.php`.
- **Application structure:** request handlers are in `apps/controllers/`; database and business operations are in `apps/models/`; reusable policy, URL, CSRF, and rendering functions are in `apps/helpers/`. Dashboard content lives in role-specific `partials/` directories. CSS, JavaScript, and public images are in `public/`.
- **Routing pattern:** views and browser scripts call PHP controller files directly, generally with an `action` query parameter. Dashboard navigation loads PHP partials into the dashboard shell. There is no framework router or build step evident in the repository.
- **Configuration:** `config/conn.php` loads `.env`, sets the application timezone, and connects to a local MySQL server. `config/appointment.php` holds appointment defaults. Site-editable settings are stored in `site_settings`.

## Data model

The main relational groups are:

- **Identity and people:** `users`, `patients`, `staffs`, email verification and password reset tables.
- **Patient clinical records:** medical and dental histories, conditions, consent, odontograms, tooth findings, and chart snapshots.
- **Appointments:** `clinics`, `schedules`, `services`, `service_categories`, `appointments`, selected appointment services, check-ins, reschedule requests, and reviews.
- **Payments:** appointment deposits, final billings, and billing line items.
- **Communications and oversight:** clinic conversations/messages, appointment email notifications, audit logs, and site settings.

`db-oaprms-system.sql` is the checked-in database export. Later schema changes are in `database/migrations/`. **Do not assume the export alone represents the entire current schema:** for example, the clinic chat tables are supplied by `database/migrations/20260905_add_clinic_chat.sql` and are not in the export. Before setting up or upgrading a database, compare its actual schema with the migrations and apply only the changes it still needs. See `DATABASE_SCHEMA_AUDIT_2026-09-21.md` for a prior schema inventory; its row counts and conclusions describe that audit date, not necessarily the current database.

## Security and operational boundaries

- Dashboard pages require a logged-in session and the matching role. Controller actions also need their own role and record-ownership checks; hiding a dashboard control is insufficient.
- State-changing requests use the session CSRF token from `apps/helpers/csrf.php`. Patient-facing operations should derive ownership from the authenticated session rather than trusting supplied patient or appointment IDs.
- Final settlement is restricted to Admin in the current controller authorization policy. Dental Assistant access to clinic chat is separate from Admin access.
- `.env` contains local credentials and is ignored by Git. Never copy its values into documentation or test output. Apache `.htaccess` blocks direct access to private directories, database exports, and secret/backup files.
- Uploaded receipts and patient records are sensitive. Changes to upload, download, printing, or logging paths should preserve authorization and avoid exposing private files through public URLs.

## Local development

1. Use a PHP/Apache/MySQL environment such as the existing XAMPP setup and make this directory available under the web root.
2. Run `composer install` for the PHP dependencies. Run `npm install` only if working on the Playwright tests.
3. Configure a local `.env` for the database name, timezone, mail transport, and optional Tesseract path. `config/conn.php` currently uses `localhost`, MySQL user `root`, and an empty password; adapt that file for a different local database account.
4. Import `db-oaprms-system.sql` into a disposable local database, then reconcile `database/migrations/` against the imported schema before exercising newer features. Do not import sample data into a production database.
5. Open the application through Apache rather than opening PHP files directly from the filesystem.

The repository has many focused PHP regression scripts in `tests/`. Run the tests relevant to the feature being changed, for example `php tests/clinicChatTest.php` for messaging, `php tests/appointmentCancellationWorkflowTest.php` for cancellation, or `php tests/authorizationIntegrationTest.php` for role and ownership checks. Browser checks use the npm scripts in `package.json`. Some tests create temporary databases or require a running local web server; read the individual test before running it against an environment with important data.

## Where to start when changing a feature

1. Find the corresponding dashboard partial or public view in `apps/views/`.
2. Follow its form or fetch URL to the controller in `apps/controllers/`.
3. Inspect the model in `apps/models/` and related helpers or configuration.
4. Check the relevant tables in the SQL export and migration files.
5. Run the focused test under `tests/` and verify the affected role's browser flow.

Related repository documents include `database/CLINIC_CHAT.md`, `tests/AUTHORIZATION_EVIDENCE.md`, the schema audit, and the dated QA/security reports. Treat dated reports as historical evidence and verify their findings against current code before relying on them.
