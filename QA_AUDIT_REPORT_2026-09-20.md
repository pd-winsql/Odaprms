# End-to-End QA, UX, Security, and Backend Audit

**System:** Dr. Aprille Ventura Clinica Dental management system  
**Environment:** Local XAMPP, PHP 8.2.12, Apache 2.4.58, MySQL/MariaDB via PDO  
**Audit date:** 2026-09-20 (Asia/Manila)  
**Repository:** `C:\xampp\htdocs\Capstone System`  
**Application:** `http://localhost/Capstone%20System/`

## 1. Executive summary

The application has a coherent custom PHP/PDO architecture, broad role-specific functionality, and unusually good automated coverage for several high-risk domain rules. Important controls passed: patient object ownership, Admin-only final settlement, CSRF checks on most authenticated mutations, schedule-row locking for booking capacity, patient-row locking for same-day duplicate booking, one billing/deposit record per appointment, randomized receipt filenames, protected receipt delivery, cross-clinic schedule separation, and transaction-backed settlement.

Release is nevertheless **No-Go**. Two security weaknesses materially affect account security: authentication does not rotate the session identifier and the PHP session cookie lacks `HttpOnly` and `SameSite`; login and six-digit OTP verification have no throttling or attempt lockout. The latter allows unlimited online guessing during the ten-minute OTP validity period. Operationally, the Dental Assistant's Billing Records page is broken by a direct authorization mismatch, patient pages depend on a remotely hosted Bootstrap script and generate a runtime error when it is unavailable, and configured SMTP delivery is currently failing.

The audit was intentionally non-destructive. The configured database contains established records and was treated as production-like. No appointments, patients, clinical entries, payments, settings, or staff records were changed. One isolated synthetic authorization database was created by the existing integration suite, passed 21/21 checks, and was deleted after evidence collection.

## 2. Release recommendation

**No-Go.** Resolve QA-001 and QA-002 before any release. Resolve QA-003 and validate outbound email before a pilot because the assistant workflow and patient notifications are operational dependencies. After fixes, repeat authorization, authentication, OTP, role UI, responsive, and transaction/concurrency tests in an isolated cloned database.

## 3. Overall quality score

**62/100**

| Area | Score | Summary |
|---|---:|---|
| Functional correctness | 68 | Core pages and rules largely work; assistant billing page fails and a full lifecycle was unsafe on the populated database. |
| UI consistency | 78 | Consistent brand, cards, navigation, states, and terminology across roles. |
| Usability | 72 | Clear booking/profile steps and empty states; broken assistant page and external-script dependency reduce reliability. |
| Accessibility | 58 | Good landmarks, headings, progress labels, and control names overall; registration consent lacks an accessible name and full WCAG contrast/screen-reader testing remains. |
| Responsiveness | 84 | No document-level horizontal overflow at 360, 768, 1024, or 1440 px on tested top-level surfaces. |
| Security | 35 | Session fixation, weak cookie attributes, unthrottled OTP/login attempts, missing baseline headers, and weak SVG validation. |
| Authorization | 88 | 21/21 isolated integration checks passed; one denial partial returns HTTP 200 instead of 403. |
| Data integrity | 80 | Strong transactions, row locks, FKs, unique deposit/billing/chat keys; duplicate schedule FKs and limited database CHECK constraints remain. |
| Performance | 66 | UI responses were usable on the current data volume; no load test, query plan analysis, or large-history benchmark was safe in this run. |
| Maintainability and automated-test coverage | 67 | Broad standalone tests exist, but no unified runner/configuration, some tests write to the live database, and one test has an undocumented required argument. |

## 4. Detected architecture, modules, and safety assessment

### Architecture

- Custom PHP application with a front controller/public landing page and direct PHP view/controller endpoints.
- PDO-based MySQL/MariaDB models; no full-stack framework or migration runner was detected.
- Server-rendered dashboards with JavaScript/AJAX partial loading.
- Session-based authentication and role values `Admin`, `Dental Assistant`, and `Patient`.
- Admin represents the sole dentist; scheduling models clinic windows and a configurable inter-clinic transition interval.
- PHPMailer handles OTP and appointment email delivery.

### Available modules

- Public landing/services, contact, terms, registration/verification, login, forgot/reset password.
- Patient: home/notifications, booking, deposits, appointment history/reviews, profile/questionnaire, password, clinic chat.
- Dental Assistant: dashboard, appointments, messages, services, clinics, schedules, patients, deposits, billing navigation, logbook/queue, password.
- Admin/Dentist: today's queue, upcoming appointments, assistant accounts, analytics, reports/export, feedback, billing summary, audit logs, settings, complete visit/odontogram/final settlement.
- Backend: appointments, deposits, rescheduling, chat, queue/check-in, billing, analytics, reports, reviews, odontograms, settings, email notifications, and audit logs.

### Existing test suites

The repository contains standalone PHP/JavaScript scripts rather than PHPUnit/Jest configuration. Coverage includes authorization, booking lead time, schedule windows/hours/deletion, transition rules, deposits/transfers, rescheduling/cancellation/no-show, queue/logbook, billing/analytics, profiles/questionnaires, odontograms, chat, notifications, reviews, terms, uploads/OCR, public access restrictions, and rendering.

### Test-data safety

- Live configured database at audit start: 70 appointments, 21 patients, 27 users, 56 deposits, 30 billings, and 389 audit rows.
- It was treated as production-like; tests using live `INSERT`, `UPDATE`, or cleanup-based `DELETE` were not run unless enclosed in a verified rollback transaction.
- Isolated authorization database: `vd_auth_test_5b74e1a8118f`; synthetic only; deleted after the suite.
- Temporary cookie jars/bodies were deleted. No persistent test records were created.

## 5. Role-by-role coverage matrix

Legend: **Pass** = exercised and acceptable; **Partial** = inspected or safe branch tested but not end-to-end; **Fail** = confirmed defect; **Not run** = unsafe without an isolated full clone or working email sink.

| Area | Public/Auth | Patient | Assistant | Admin/Dentist |
|---|---|---|---|---|
| Landing, services, clinics, terms | Pass | — | — | — |
| Login and incorrect credentials | Pass | Pass | Pass | Pass |
| Inactive assistant | Partial: code + automated test passed | — | Partial | — |
| Registration, age and consent | Partial: UI/server rules inspected | — | — | — |
| OTP/reset expiration/reuse | Partial: code inspection | — | — | — |
| OTP/login brute-force resistance | **Fail** | **Fail** | **Fail** | **Fail** |
| Protected deep links | Pass | Pass | Pass | Pass |
| Dashboard/navigation/empty states | — | Pass | Pass except billing | Pass |
| Profile/questionnaire | — | Partial: six-step UI + rules | Partial: permissions inspected | Read/update split passed in tests |
| Booking, clinic/date/service selection | — | Partial: non-submitting path | Review UI inspected | Oversight UI inspected |
| Deposit/receipt access | — | Partial: empty state + IDOR tests | Pass: list/access controls | Partial |
| Reschedule/cancel/refund | — | Isolated ownership tests pass; lifecycle not run | Static/integration coverage | Static/integration coverage |
| Chat | — | Ownership tests pass | UI/pass | Not exposed by design |
| Queue/check-in/hold/no-show | — | — | Partial: UI + automated rules | Partial: queue UI + automated rules |
| Odontogram | — | Read-only rule passed by tests | Read-only rule passed | Update permission passed |
| Final billing/settlement | — | Denied in isolated test | Denied in isolated test | Positive control passed in isolated test |
| Assistant management | — | Denied | Denial body returned, wrong status | UI inspected |
| Analytics/reports/audit | — | Denied | 403 passed | UI inspected; model tests passed |
| Settings/schedule defaults | — | Denied | Schedule defaults permitted | Admin settings UI inspected |
| Password change | — | UI inspected only | UI inspected only | UI inspected only |
| Logout/session invalidation | Pass for navigation | Partial | Partial | Partial |

## 6. Single-dentist, multi-clinic invariant results

| Invariant | Result | Evidence |
|---|---|---|
| Exactly one dentist/Admin operating model | Pass by design | No dentist entity or dentist selector; Admin/Dentist role owns treatment and settlement. |
| No overlapping clinic windows | Pass | `scheduleTimeWindowTest.php` rejected overlap. |
| Transition time between clinics | Pass | Saved policy loaded dynamically; shorter-than-policy rejected; exact boundary accepted. |
| Correct clinic association | Pass with caveat | FKs and model validation present; UI filters showed two clinics. Appointment `clinic_id` and `schedule_id` are both stored. |
| Clinic filter stale-data leakage | Partial | Top-level UI navigation checked; no high-volume filter race test. |
| Clinic deletion/deactivation integrity | Partial | FK blocks clinic deletion; schedule deletion guard test passed. No deactivation column/workflow was found. |
| Appointment clinic change revalidation | Partial | Reschedule workflow exists; full live branch not run. |
| Cross-clinic analytics double counting | Pass in model test | Analytics totals and clinic comparison tests passed. |
| Patient identity across clinics | Pass structurally | Global patient table; unique `identity_match_key`; duplicate matching/link authorization exists. |
| Time-zone/midnight boundaries | Partial | Application timezone configured and date boundaries tested in classification; DST is not relevant locally, but midnight concurrency was not run. |
| Zero/max transition values | Partial | Validation permits 0–240 in five-minute increments; full boundary suite for both extremes was not run. |
| Simultaneous booking/capacity | Pass by code design; no load test | Transaction locks patient and schedule rows `FOR UPDATE` before duplicate/capacity checks. |

## 7. Confirmed defects, ordered by severity

### QA-001 — Unlimited login and OTP attempts enable online guessing

- **Severity / confidence:** P1 High / High
- **Roles/scope:** All accounts; cross-clinic account security
- **Module:** `userController.php` login and registration OTP; `passwordResetController.php` reset OTP
- **Preconditions/test data:** Existing synthetic test-account email; invalid passwords/codes only
- **Steps:** Submit ten invalid passwords to login, then twenty invalid six-digit reset codes for the same test account.
- **Expected:** Rate limit, progressive delay, lockout/challenge, HTTP 429, or bounded OTP-attempt counter.
- **Actual/evidence:** All login attempts returned HTTP 200 with the generic invalid-credentials response; all twenty OTP attempts returned HTTP 200 and were processed as invalid/expired. Code has no attempt counter, throttle, lockout, IP/user limiter, or 429 path. OTP space is only 1,000,000 values and remains valid for ten minutes.
- **Consistency:** 30/30 tested requests.
- **Impact:** Account takeover risk, especially for password reset; operational abuse and mail-trigger abuse.
- **Likely layer/source:** `apps/controllers/userController.php`; `apps/controllers/passwordResetController.php`; database reset/verification tables lack attempt/rate metadata.
- **Remediation:** Add per-account and per-IP sliding-window limits, bounded attempts per code, single-use invalidation after threshold, resend cooldown, exponential delay, HTTP 429 with `Retry-After`, and audited security events. Store OTP using a keyed hash rather than plaintext.
- **Regression test:** Verify attempt N is accepted as invalid, N+1 is blocked, a new code invalidates the old code, limits survive new sessions, and legitimate recovery resumes after cooldown.

### QA-002 — Session identifier is not rotated and cookie lacks core protections

- **Severity / confidence:** P1 High / High
- **Roles/scope:** All authenticated roles; cross-clinic privacy/clinical/financial data
- **Module:** Authentication/session bootstrap
- **Preconditions:** Anonymous session established before login
- **Steps:** Request login page, record the anonymous session identifier internally, authenticate, compare identifier and response cookie attributes.
- **Expected:** New session identifier after authentication; `HttpOnly`; `SameSite=Lax` or stricter; `Secure` in HTTPS deployment; strict session mode; logout clears cookie.
- **Actual/evidence:** `session_id_rotated=False`; no `session_regenerate_id()` exists. PHP settings were `session.use_strict_mode=0`, empty `session.cookie_httponly`, `session.cookie_secure=0`, and empty `session.cookie_samesite`. Root response set only `PHPSESSID=…; path=/`. Logout only calls `session_destroy()`.
- **Consistency:** Reproduced once and confirmed in source/config.
- **Impact:** Session fixation and increased session theft exposure; compromise grants clinical, financial, or administrative access according to the victim role.
- **Likely layer/source:** `apps/controllers/userController.php:8, login(), logout()` and PHP session configuration.
- **Remediation:** Enable strict mode; set cookie parameters before every `session_start`; regenerate ID with deletion after login/privilege change; clear server session and cookie on logout; deploy HTTPS and use `Secure`, `HttpOnly`, and `SameSite=Lax/Strict`.
- **Regression test:** Assert anonymous and authenticated IDs differ; old ID cannot access; cookie attributes are present; logout invalidates both current and pre-login IDs.

### QA-003 — Dental Assistant “Billing Records” always fails to load

- **Severity / confidence:** P2 Medium / High
- **Role/scope:** Dental Assistant; all clinics
- **Page/module:** `dashboard.php#cash-billing-content.php`
- **Preconditions:** Active assistant login
- **Steps:** Open Dental Assistant dashboard → Billing Records.
- **Expected:** Permitted read-only billing visibility, or no navigation item if the module is intentionally Admin-only.
- **Actual/evidence:** Main region displays `Error loading content.` Browser console records `Network response was not ok`. Assistant partial requires the Admin partial, whose guard returns HTTP 403 for assistants.
- **Consistency:** Reproduced on two navigations.
- **Impact:** Staff cannot use an advertised records workflow; creates uncertainty around cash/payment handoff.
- **Likely layer/source:** `apps/views/dental_asst/partials/cash-billing-content.php` requires `apps/views/admin/partials/cash-billing-content.php`, which explicitly requires `Admin`.
- **Remediation:** Create a dedicated assistant read-only view/endpoint with scoped fields, or remove the assistant navigation entry and adjust workflow copy.
- **Regression test:** Assistant page returns 200 and read-only data with no settlement actions; direct mutation remains 403; Admin summary remains available.

### QA-004 — Patient dashboard depends on remote Bootstrap and throws at runtime

- **Severity / confidence:** P2 Medium / High
- **Role/scope:** Patient; all clinics
- **Page/module:** Patient dashboard/modal infrastructure
- **Preconditions:** Patient login while CDN asset is unavailable/blocked
- **Steps:** Sign in as patient and inspect console/load dashboard.
- **Expected:** Locally bundled critical JS loads; dashboard has no runtime errors; dialogs/notifications initialize.
- **Actual/evidence:** Console: `ReferenceError: bootstrap is not defined` at patient dashboard. The page loads CSS locally but Bootstrap JS from a CDN, while staff/admin dashboards use local assets.
- **Consistency:** Reproduced in the in-app browser; source confirms external Bootstrap bundle.
- **Impact:** Logout/confirmation/modal behavior may fail on restricted or offline clinic networks.
- **Likely layer/source:** `apps/views/patient/dashboard.php` external Bootstrap script.
- **Remediation:** Use the committed local `public/js/bootstrap.bundle.min.js`, add integrity/fallback if retaining CDN, and fail gracefully.
- **Regression test:** Block external network and confirm no console errors and all patient modals open/close by keyboard and mouse.

### QA-005 — Outbound mail is failing and logs recipient addresses

- **Severity / confidence:** P2 Medium / High
- **Roles/scope:** Patients/staff; all clinics
- **Module:** PHPMailer/appointment and OTP notifications
- **Preconditions:** Existing mail configuration and normal notification attempts
- **Steps:** Inspect Apache log after notification attempts.
- **Expected:** Delivery succeeds or a privacy-safe, actionable failure is queued/retried; logs omit recipient PII.
- **Actual/evidence:** Repeated `SMTP Error: Could not authenticate` entries are present. The mailer explicitly logs every recipient address before send. No addresses are reproduced in this report.
- **Consistency:** Multiple historical occurrences.
- **Impact:** Verification/notifications may not arrive; email addresses are unnecessarily retained in server logs.
- **Likely layer/source:** `config/mailer.php:177-185` and SMTP configuration.
- **Remediation:** Correct credentials/environment separation, use a test mail sink locally, remove/redact recipients, record only opaque notification IDs, and implement a bounded retry/dead-letter state.
- **Regression test:** Simulate success/failure; verify delivery state, retry behavior, user-facing message, and absence of address/token/OTP in logs.

### QA-006 — Admin logo upload trusts extension and allows SVG without content validation

- **Severity / confidence:** P2 Medium / Medium-High
- **Role/scope:** Admin; public and all dashboards
- **Module:** Site logo upload
- **Preconditions:** Authenticated Admin and CSRF token
- **Steps:** Review upload validation for `updateLogo`.
- **Expected:** MIME and decoded-image validation, SVG sanitization or SVG prohibition, randomized filenames.
- **Actual/evidence:** Validation checks only the original extension and permits SVG. Filename uses `time()` and the file is written under a public same-origin assets directory. This was not exploit-tested against the populated system.
- **Consistency:** Deterministic source path.
- **Impact:** A compromised/malicious Admin could store active same-origin SVG content; predictable names and no size/MIME checks also enable storage abuse.
- **Likely layer/source:** `apps/controllers/siteSettingsController.php::updateLogo()`.
- **Remediation:** Prefer raster-only JPG/PNG/WebP with `finfo` + `getimagesize`, enforce size/dimensions, randomize filename, or sanitize SVG with a mature allowlist and serve it with restrictive headers from a non-executable origin.
- **Regression test:** Reject SVG-with-script, renamed non-image, polyglot, corrupt and oversized files; accept valid bounded raster images.

### QA-007 — Registration terms checkbox has no accessible name

- **Severity / confidence:** P2 Medium / High
- **Role/scope:** Public/patient registration
- **Page:** Registration
- **Preconditions:** Open registration form
- **Steps:** Inspect accessibility tree before accepting terms.
- **Expected:** Checkbox exposed with a programmatic name describing agreement; disabled state and enabling instructions announced.
- **Actual/evidence:** Accessibility tree exposes `checkbox [disabled]` with no name while adjacent text/button is separate.
- **Consistency:** Reproduced at desktop viewport.
- **Impact:** Screen-reader users may not understand or operate the consent gate, blocking registration.
- **Likely layer/source:** `apps/views/register.php` terms agreement markup.
- **Remediation:** Associate a `<label for>`, provide `aria-describedby` for the scroll requirement, announce enabled-state changes, and preserve keyboard focus.
- **Regression test:** Accessible-name assertion plus keyboard-only open/scroll/close/check flow in Chromium, Firefox, and NVDA.

### QA-008 — Unauthorized assistant-management partial responds 200

- **Severity / confidence:** P3 Low / High
- **Roles/scope:** Unauthenticated, Patient, Dental Assistant; all clinics
- **Endpoint:** `apps/views/admin/partials/den-assist-content.php`
- **Preconditions:** No Admin session
- **Steps:** Direct GET to the partial.
- **Expected:** HTTP 403 and denial body.
- **Actual/evidence:** HTTP 200 with an unauthorized denial body. Other Admin partials returned 403.
- **Consistency:** Reproduced unauthenticated and as assistant.
- **Impact:** Weakens monitoring/caching semantics and can make automated clients interpret denial as success; no staff data was disclosed.
- **Likely layer/source:** Guard echoes and exits without `http_response_code(403)`.
- **Remediation:** Use centralized `vdRequireAdmin*` behavior and return 403 consistently.
- **Regression test:** Every Admin partial returns 403 for anonymous/patient/assistant and 200 for Admin.

### QA-009 — Baseline security headers are absent from normal HTML responses

- **Severity / confidence:** P3 Low / High
- **Roles/scope:** All roles; all clinics
- **Endpoint:** Landing and dashboard HTML responses
- **Preconditions:** Standard request
- **Steps:** Inspect root response headers.
- **Expected:** At minimum CSP, `X-Content-Type-Options: nosniff`, clickjacking protection (`frame-ancestors`), and `Referrer-Policy`; suppress version header.
- **Actual/evidence:** Response exposed `X-Powered-By: PHP/8.2.12`; no CSP, frame restriction, nosniff, referrer, or permissions policy was returned. A few download endpoints set `nosniff`, but normal HTML did not.
- **Consistency:** Confirmed on root response and source search.
- **Impact:** Reduced defense in depth for XSS/clickjacking/content-sniffing and unnecessary stack disclosure.
- **Likely layer/source:** Apache/PHP global response configuration.
- **Remediation:** Add centrally tested headers, migrate third-party assets locally or nonce/hash them for CSP, and disable `expose_php`.
- **Regression test:** Header assertions for public, authenticated HTML, JSON, CSV, and receipt responses.

### QA-010 — Automated smoke/restriction tests are not reliably runnable as documented

- **Severity / confidence:** P3 Low / High
- **Role/scope:** Engineering/release process
- **Module:** Test harness
- **Preconditions:** Run tests from repository root
- **Steps:** `php tests\renderSmokeTest.php`; `php tests\webAccessRestrictionsTest.php`.
- **Expected:** Self-describing invocation and deterministic pass/fail.
- **Actual/evidence:** Render smoke exits 1 (`Unknown smoke-test case`) and even `--help` exits 2. Web restriction test completed its public 403 checks but then fatally failed because CLI could not create its temporary session in `C:\xampp\tmp`.
- **Consistency:** Reproduced once each.
- **Impact:** CI/release results can be incomplete or misinterpreted.
- **Likely layer/source:** `tests/renderSmokeTest.php`; `tests/webAccessRestrictionsTest.php`; test session-path setup.
- **Remediation:** Add a unified runner, documented cases, isolated writable `session.save_path`, fixtures, database-name safety guard, and machine-readable exit/reporting.
- **Regression test:** Clean checkout command runs all non-destructive tests with zero manual arguments and produces JUnit/TAP output.

## 8. Security and privacy findings

### Passed

- Password verification uses `password_verify`; new passwords use `password_hash`.
- Reset tokens are random 32-byte values and stored as SHA-256 hashes; reuse/expiry checks are present.
- Protected dashboards redirect anonymous requests to login.
- Sensitive filesystem targets (`.env`, SQL dump, `.git`, tests, database, storage, backups) returned 403.
- Receipt storage files returned 403 directly; controller-mediated receipt access is role/ownership checked and sets `nosniff`.
- Patient profile, appointments, deposits, reschedules, chat, and history resisted tested ID tampering.
- Assistant and Patient cannot perform final settlement; Admin positive control passed.
- CSRF validation is present on authenticated controller mutations reviewed, including appointment, deposit, reschedule, chat, staff, clinic, service, schedule, settings, logbook, odontogram, billing, and password change.
- SQL reviewed generally uses prepared statements. Dynamic placeholder lists are built from integer-normalized values.
- Deposit/service image handling checks size, server MIME, decoded image validity where appropriate, randomized names, and controlled storage paths.

### Risks/limitations

- QA-001, QA-002, QA-006, QA-008, and QA-009 require remediation.
- OTPs themselves are stored plaintext in `password_resets` and `email_verifications`; hash them so database read access does not immediately expose valid codes.
- Logout is a GET endpoint with no CSRF protection and does not clear the cookie. This permits forced logout and should be changed to CSRF-protected POST.
- No penetration payloads were submitted to the populated database; stored XSS and SQL injection were assessed through source review and safe negative requests, not destructive exploit attempts.
- HTTPS was not available on the supplied URL, so Secure-cookie and transport behavior could not be validated end-to-end.

## 9. Backend and data-integrity findings

### Passed

- Booking starts a transaction, locks the patient row and schedule row, revalidates clinic/date/lead time, checks same-day duplicate bookings, capacity, reschedule holds, and active services, then writes services/audit atomically.
- Status updates and treatment starts use transactions and row locks. Only one `In Progress` appointment is allowed in application logic, matching the single-dentist model.
- Settlement starts a transaction and one billing row per appointment is enforced by a unique index.
- Unique constraints cover user email, patient identity key/account, clinic/date schedules, appointment payment token/code, one deposit per appointment, GCash reference, one billing per appointment, and chat request idempotency.
- Monetary columns use `DECIMAL(10,2)`.
- Foreign keys cover major patient, schedule, appointment, billing, deposit, staff-actor, chat, and audit relationships.
- Schedule operating hours, five-minute increments, capacity range, transition policy, and overlap logic passed automated checks.

### Concerns

- `appointments.schedule_id` has two foreign keys, one with `ON DELETE CASCADE` and one without. The application deletion guard passed, but duplicate constraints are confusing and a future schema/tooling change could make retention behavior unsafe. Use one explicit restrictive retention policy.
- Database CHECK constraints are sparse for positive monetary values, capacity/time ordering, appointment clinic/date matching its schedule, and state/timestamp consistency. Most protection is application-layer only.
- No database constraint enforces globally one `In Progress` appointment; the transactional application check is sound for current code, but a generated guard/locking sentinel would strengthen invariance across future writers.
- SMTP failures are logged but production-grade retry/monitoring behavior was not demonstrated.

## 10. Automated test results

### Commands and results

All commands were run from the repository root.

- `php tests\authorizationPolicyTest.php` — PASS.
- `php tests\clinicChatUiTest.php` — PASS.
- `php tests\gcashReceiptOcrTest.php` — PASS.
- `php tests\landingServicesTest.php` — PASS.
- `php tests\medicalQuestionnaireTest.php` — PASS.
- `php tests\odontogramFeatureTest.php` — PASS.
- `php tests\passwordResetLoggingTest.php` — PASS.
- `php tests\patientNotificationTest.php` — PASS.
- `php tests\scheduleDeletionGuardTest.php` — PASS; all 38 schedules checked, no data changed.
- `php tests\scheduleOperatingHoursTest.php` — PASS.
- `php tests\staffStatusConnectionTest.php` — PASS.
- `php tests\termsConsentTest.php` — PASS.
- `php tests\analyticsModelTest.php` — PASS; rollback transaction.
- `php tests\appointmentListClassificationTest.php` — PASS; rollback transaction.
- `php tests\billingInsightsModelTest.php` — PASS; rollback transaction.
- `php tests\scheduleTimeWindowTest.php` — PASS; rollback transaction.
- `php tests\siteSettingsTest.php` — PASS; rollback transaction.
- `node tests\keyboardShortcutsTest.js` — PASS.
- `php tests\authorizationIntegrationTest.php` — PASS, 21 checks/0 failures in isolated synthetic database; database removed afterward.
- `php tests\renderSmokeTest.php` — FAIL: unknown required case.
- `php tests\webAccessRestrictionsTest.php` — PARTIAL: all listed HTTP access checks passed, then CLI session creation failed due permission on `C:\xampp\tmp`.

### Deliberately not run against the populated database

Tests with live inserts/settings changes and cleanup-based deletion were withheld: appointment cancellation, booking lead time, clinic chat, deposit transfer, feature workflow, logbook queue, questionnaire persistence, no-show, patient profile editing, reschedule workflow, staff notification, upcoming appointment overview, and visit review. They should run only against a disposable clone selected by an explicit environment variable and guarded database-name prefix.

## 11. Browser/device coverage

- Browser engine: Codex in-app Chromium browser.
- Viewports: approximately 360×800, 768×900, 1024×900, and 1440×900.
- Public landing page: no document-level horizontal overflow at all four widths; navigation changes at expected breakpoint.
- Patient and Admin dashboards: no document-level horizontal overflow at 360 px; off-canvas sidebar settles fully outside viewport.
- Desktop traversal covered every visible top-level navigation entry for Patient, Dental Assistant, and Admin/Dentist.
- Keyboard behavior has a repository JavaScript regression test that passed. Manual exhaustive focus order, focus trapping, Escape behavior, zoom/reflow, contrast measurement, NVDA/JAWS, touch devices, Firefox, Safari, and Edge were not run.

## 12. Checks that passed

- Landing page content, clinic/service presentation, public terms link, and booking calls to action.
- Incorrect login uses a generic error without account disclosure.
- Patient booking UI explains seven-day lead time and exposes only an eligible future date in the tested account.
- Clear empty states for no upcoming appointments, deposits, history, and queue.
- Dashboard role labels and navigation are understandable and visually consistent.
- Patient profile presents a six-step flow with progress state and labeled fields.
- Assistant appointment/message/service/clinic/schedule/patient/deposit/logbook pages loaded successfully.
- Admin upcoming appointments, assistant accounts, insights, logs, settings, and password pages loaded successfully.
- No horizontal page overflow at tested public breakpoints.
- Automated authorization, schedule, analytics, billing, notification, terms, questionnaire, odontogram, OCR, and keyboard tests listed above.

## 13. Areas not tested and why

- Full appointment lifecycle, reschedule/cancellation branch, receipt upload, check-in/queue/treatment/odontogram/settlement/review: would mutate a populated production-like database. A disposable full clone was not supplied.
- Real registration/verification and password reset delivery: configured SMTP is failing and these flows create persistent/reset records and send email.
- Invalid/oversized/corrupt file uploads: not submitted to the populated environment; validation was reviewed in code.
- True concurrent HTTP load, capacity last-slot race, queue race, and double settlement: model locking was reviewed and isolated authorization settlement ran, but a disposable clone/load harness is needed.
- CSV download contents and totals: Admin UI/model paths inspected, but exporting operational data would create an artifact containing sensitive information.
- Large-data performance, N+1/query plans, memory use, and slow endpoint thresholds: current dataset is small; no anonymized large fixture database.
- Session expiration duration and HTTPS behavior: no explicit short expiry/HTTPS endpoint was available.
- Cross-browser, screen-reader, color-contrast instrumentation, zoom to 400%, and real mobile hardware.

## 14. Recommended fixes

### Before any release

1. Fix QA-001: throttle login, OTP generation/resend, and OTP verification; bound attempts per code and account/IP.
2. Fix QA-002: secure session cookie, strict mode, ID rotation, and complete logout invalidation; deploy/test HTTPS.
3. Correct the assistant Billing Records authorization/view contract (QA-003).
4. Restore reliable outbound email with privacy-safe logging (QA-005).
5. Harden logo upload, preferably raster-only (QA-006).

### Before pilot use

1. Self-host critical patient Bootstrap JS and add offline browser coverage (QA-004).
2. Fix the accessible registration consent name and run keyboard/NVDA testing (QA-007).
3. Add centralized security headers and suppress version disclosure (QA-009).
4. Convert logout to CSRF-protected POST and clear cookie state.
5. Normalize all unauthorized partials to HTTP 403 (QA-008).
6. Run every mutation suite against a sanitized disposable clone and execute one complete lifecycle in both clinics.
7. Add production mail queue/retry alerting and verify notification/UI consistency.

### Later improvements

1. Add DB CHECK constraints and simplify duplicate schedule foreign keys.
2. Add paginated/large-history benchmarks and SQL query-plan monitoring.
3. Replace ad-hoc scripts with a single CI runner, isolated test database provisioning, and browser automation.
4. Add Firefox/Edge/Safari and WCAG 2.2 AA automated/manual coverage.
5. Add structured security/audit event monitoring without PII in logs.

## 15. Suggested regression-test backlog

1. Authentication: session rotation, old-ID invalidation, cookie attributes, strict mode, logout cookie clearing, forced-login/logout CSRF.
2. Abuse controls: login/OTP/reset per-account and per-IP thresholds, resend cooldown, lock expiry, audit events, parallel attempts.
3. Authorization matrix: every view and controller for anonymous, Patient, Assistant, Admin; assert both response status and unchanged database.
4. Billing page contract: assistant read-only view vs Admin actions; direct settlement 403; finalized values immutable.
5. Offline asset test: block all external requests and exercise every modal, dropdown, notification, and chart fallback.
6. Booking concurrency: two patients racing for final slot; same patient double-submit; duplicate POST retry; reschedule hold vs booking.
7. Single-dentist scheduling: overlap, adjacent branches at 0/5/90/240 minutes, midnight boundaries, policy change with existing schedules.
8. Deposit concurrency: duplicate GCash reference, repeated upload, verification/rejection race, transfer/refund idempotency.
9. Queue concurrency: serve-next race, hold/return ordering, only one in-progress visit, completion retry.
10. Upload matrix: MIME/extension mismatch, SVG active content, oversized, corrupt, polyglot, traversal filename, direct retrieval authorization.
11. Accessibility: named consent checkbox, error live regions, modal focus trap/restore/Escape, focus visibility/order, 400% zoom, reduced motion.
12. Data retention: clinic/schedule deletion with historical appointments, messages, deposits, billing, odontograms, and audit logs.
13. Analytics: cross-clinic patient de-duplication, clinic switching, empty database, large data, date/timezone boundaries, CSV formula injection.
14. Email: sink-based OTP/appointment templates, failure/retry/dead-letter, duplicate suppression, no secrets/PII in logs.

## 16. Concise retest plan

1. Provision a sanitized disposable database clone and a local mail sink; require the application/test runner to reject non-test database names.
2. Apply security fixes, then rerun the authentication/OTP/session abuse suite first.
3. Rerun all existing PHP/JS tests through one runner; require zero failures and database cleanup verification.
4. Execute the complete appointment lifecycle once per clinic, plus reschedule and cancellation branches, checking UI/database/audit/notification/financial state after every transition.
5. Run parallel last-slot booking, duplicate settlement, deposit, and serve-next tests.
6. Repeat all role endpoint authorization tests and assistant billing navigation.
7. Repeat responsive/keyboard/screen-reader testing at 360/768/1024/desktop in Chromium, Firefox, and Edge with external network blocked.
8. Perform a final log/secrets/PII review and compare before/after database counts to ensure no test residue.

