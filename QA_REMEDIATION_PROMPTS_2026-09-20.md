# QA Remediation Implementation Prompts

These prompts correspond one-to-one with the recommended fixes in `QA_AUDIT_REPORT_2026-09-20.md`. Each can be copied into a separate implementation task. Complexity estimates assume one experienced PHP engineer familiar with the repository and include implementation, automated tests, and focused manual verification.

## Complexity scale

- **Low:** Localized change, usually under one day, with limited regression surface.
- **Medium:** Several files or shared behavior, typically one to three days, requiring integration tests.
- **High:** Cross-cutting architecture, schema/infrastructure work, concurrency, or broad regression testing; typically three or more days.

---

## Before any release

### Prompt 1 — Add authentication and OTP abuse controls

**Complexity: High**  
**Why:** This affects login, registration verification, password recovery, database schema, client messaging, audit behavior, concurrent requests, and operational configuration. Incorrect limits could either leave takeover paths open or lock out legitimate patients.

> Act as a senior PHP security engineer. Fix QA-001 in the dental clinic system at `C:\xampp\htdocs\Capstone System` by adding robust throttling and bounded attempts to login, registration OTP generation/resend/verification, and password-reset OTP generation/resend/verification.
>
> Start by reviewing `apps/controllers/userController.php`, `apps/controllers/passwordResetController.php`, the relevant views/JavaScript, `email_verifications`, `password_resets`, and `auditLogModel.php`. Preserve the existing three roles and do not weaken identity matching, OTP expiry, or single-use behavior.
>
> Produce a short implementation plan before editing. Implement layered limits by normalized account identity and source IP, a resend cooldown, a maximum number of verification attempts per issued code, escalating temporary login delays/lockouts, HTTP 429 with `Retry-After`, and generic responses that do not disclose whether an account exists. Make thresholds configurable rather than scattering constants. Hash newly stored OTPs using an appropriate keyed or password-hash design and support a safe migration strategy for existing rows. Ensure simultaneous attempts cannot bypass counters; use transactions or atomic SQL updates.
>
> Record privacy-safe security events without passwords, OTPs, reset tokens, session IDs, or raw IP addresses. Do not send additional emails while a resend cooldown is active. Make UI messages clear and accessible without revealing account existence.
>
> Add isolated tests for: threshold boundaries, cooldown expiry, per-code invalidation, new-code invalidation of the old code, parallel attempts, account and IP dimensions, successful reset after cooldown, HTTP 429 headers, email enumeration resistance, and unchanged legitimate login behavior. Tests must refuse to run against a database whose name does not match a dedicated test prefix. Do not test against the populated application database.
>
> Acceptance criteria: no unlimited guessing path remains; counters survive new browser sessions; race tests cannot exceed the attempt limit; valid users can recover after the configured window; no secret appears in responses, logs, or audit records; all existing authorization and password-reset tests pass.

### Prompt 2 — Harden PHP session lifecycle and cookies

**Complexity: Medium-High**  
**Why:** The change is conceptually small but cross-cutting. Every entry point that starts a session must use identical configuration, and rotation/logout tests must account for asynchronous dashboard requests.

> Act as a senior application-security engineer. Fix QA-002 by centralizing and hardening PHP session initialization throughout `C:\xampp\htdocs\Capstone System`.
>
> Inventory every `session_start()` and all authentication/logout paths. Create one reusable session bootstrap that sets strict mode, an appropriate inactivity and absolute lifetime, cookie path, `HttpOnly`, `SameSite=Lax` or stricter, and `Secure` whenever HTTPS is active. Prevent callers from starting a session before these settings are applied. Regenerate the session ID with deletion immediately after successful login, registration completion, password reset, and any privilege change. Preserve only required session state. On logout, clear session data, destroy the server-side session, expire the cookie using identical attributes, and prevent cached protected pages from reappearing.
>
> Define the local HTTP development behavior explicitly while ensuring production cannot silently run insecure cookies. Do not embed environment secrets or hard-code a production hostname.
>
> Add integration tests proving: anonymous and authenticated session IDs differ; the pre-login ID cannot access protected endpoints; a caller-supplied ID is rejected under strict mode; cookie attributes are correct under HTTP-development and simulated HTTPS-production configurations; logout invalidates the current ID and cookie; concurrent AJAX requests remain usable after rotation; protected responses use no-store caching.
>
> Acceptance criteria: one session bootstrap is used consistently; no successful authentication retains the anonymous identifier; logout leaves no reusable session; all role dashboards and existing authorization tests continue to pass.

### Prompt 3 — Repair the Dental Assistant Billing Records contract

**Complexity: Medium**  
**Why:** The UI failure is localized, but the correct fix requires a deliberate permission contract and field-level data scoping rather than simply loosening an Admin guard.

> Act as a senior PHP authorization and UX engineer. Fix QA-003: the Dental Assistant navigation advertises Billing Records, but `apps/views/dental_asst/partials/cash-billing-content.php` includes an Admin-only partial and returns an error.
>
> First determine the intended assistant permission from existing policy/tests: assistants may see only the billing information needed for daily operations and handoff, while analytics, final settlement, refunds, and dentist-only financial approvals remain Admin-only. Document the exact read-only fields the assistant needs.
>
> Implement a dedicated assistant billing-record view and read endpoint, or remove the navigation item if no assistant visibility is intended. Do not reuse the Admin analytics endpoint by weakening its role guard. Ensure sensitive analytics, change/cash-received details, exports, settlement actions, refund controls, and privileged drill-downs remain unavailable unless explicitly required by policy. Return correct 403 JSON/HTML responses for direct unauthorized requests.
>
> Add tests for anonymous, Patient, Assistant, and Admin access; verify response status and displayed fields, not only hidden buttons. Assert assistant POST attempts cannot settle, edit, refund, export, or alter records and leave the database unchanged. Add browser coverage for loading, empty, populated, error, pagination, clinic filter, and mobile states.
>
> Acceptance criteria: the assistant navigation never displays `Error loading content`; permitted records are read-only and correctly clinic-associated; prohibited direct endpoints remain 403; Admin billing behavior is unchanged.

### Prompt 4 — Restore reliable, privacy-safe email delivery

**Complexity: Medium-High**  
**Why:** It involves environment configuration, multiple notification flows, error contracts, a test mail sink, privacy-safe logging, and potentially retry semantics.

> Act as a senior backend and operations engineer. Fix QA-005 in the mail subsystem. Review `config/mailer.php`, `.env` loading, all OTP/appointment email callers, and `appointment_email_notifications`.
>
> Separate local/test/production mail configuration. Configure local development to use a mail sink and make production fail closed with an actionable health error when required credentials are absent. Remove logging of recipient addresses, OTPs, tokens, message bodies, or PHPMailer protocol details. Log only an opaque notification ID, template key, sanitized error category, attempt number, and correlation ID.
>
> Define consistent caller behavior when email fails: registration/reset codes that were not delivered must not remain usable; appointment notification failures must be retained for retry without rolling back the clinical/financial transaction unless the business rule requires it. Add bounded exponential retry and a dead-letter/final-failure state or clearly separate that work if the existing notification table cannot support it safely.
>
> Add mail-sink integration tests for every template, delivery success, authentication failure, timeout, retry, duplicate suppression, and recovery. Assert no email address, OTP, password, reset token, medical detail, or session value appears in logs. Add an Admin-visible operational status that does not expose message content.
>
> Acceptance criteria: local mail tests are deterministic; production configuration is validated at startup/health check; failures are visible and retryable; users receive truthful UI messages; logs are privacy-safe.

### Prompt 5 — Harden site-logo uploads

**Complexity: Medium**  
**Why:** Validation is localized, but safe replacement/removal, existing SVG compatibility, public serving behavior, and test fixtures require care.

> Act as a senior PHP file-upload security engineer. Fix QA-006 in `apps/controllers/siteSettingsController.php::updateLogo()`.
>
> Prefer accepting only JPG, PNG, and WebP. Validate upload status, maximum byte size, server-detected MIME using `finfo`, decoded image validity using `getimagesize`, reasonable width/height/pixel limits, and extension derived from trusted MIME rather than the original filename. Generate a cryptographically random filename. Store only under the intended assets directory and verify the resolved target remains inside it. Preserve the current logo until the new file is fully validated and the database update commits; then remove only a validated old managed file.
>
> If SVG support is an explicit business requirement, do not implement a home-grown regex sanitizer. Use a maintained SVG sanitizer, strip scripts/external references/events/foreign objects, serve with a restrictive CSP and correct MIME, and document residual risk.
>
> Add isolated tests using valid images plus renamed executables, SVG-with-script, HTML, polyglots, corrupt files, zero-byte files, oversized dimensions/bytes, traversal names, duplicate submissions, and write/database failures. Confirm rejected files leave no residue and existing branding unchanged.
>
> Acceptance criteria: only validated image content is persisted; filenames are unpredictable; no active same-origin content can be uploaded; replacement is atomic from the user's perspective; audit entries contain no unsafe filename metadata.

---

## Before pilot use

### Prompt 6 — Self-host patient Bootstrap JavaScript and test offline operation

**Complexity: Low-Medium**  
**Why:** The code change is small, but all modal/dropdown/off-canvas behavior must be checked because the missing dependency caused a runtime error.

> Fix QA-004 by removing the patient dashboard's critical runtime dependency on the Bootstrap CDN. Use the repository's local `public/js/bootstrap.bundle.min.js`, matching the locally served Bootstrap CSS version. Review other pages for critical third-party scripts and fonts; do not broaden scope beyond assets required for core workflows.
>
> Verify load order relative to `action-modal.js`, logout confirmation, notifications, dynamic partial scripts, and clinic chat. Add a browser regression test that blocks all external network requests, signs in as a Patient, visits every patient page, and exercises logout confirmation, appointment/reschedule/cancellation dialogs, notifications, profile steps, receipt UI, chat, Escape, and focus restoration.
>
> Acceptance criteria: no `bootstrap is not defined` or failed critical network request appears; core patient workflows remain functional offline from external CDNs; there is no version mismatch between Bootstrap CSS and JS.

### Prompt 7 — Make registration consent accessible

**Complexity: Low-Medium**  
**Why:** Markup is localized, but correct behavior includes modal focus, scrolling, state announcements, keyboard flow, and screen-reader verification.

> Act as an accessibility-focused frontend engineer. Fix QA-007 on the registration page so the terms consent control has a clear accessible name and the entire terms-to-consent flow meets WCAG 2.2 AA expectations.
>
> Associate the checkbox with a real `<label>`, connect explanatory text with `aria-describedby`, and expose disabled/enabled changes through an appropriate live status without excessive announcements. Ensure the Terms button has an unambiguous name and state. In the terms dialog, trap focus, move focus to the dialog heading on open, support Escape, provide a visible close control, maintain logical reading order, and return focus to the opener. When reaching the end enables consent, do not move focus unexpectedly.
>
> Preserve server-side consent recording and do not rely on client state alone. Test keyboard-only operation, 200% and 400% zoom/reflow, visible focus, reduced motion, Chromium/Firefox/Edge, and NVDA. Add automated accessible-name and focus-flow assertions.
>
> Acceptance criteria: the checkbox is announced with its purpose and state; all steps work without a pointer; validation errors are announced; consent cannot be forged by omitting required server-side proof.

### Prompt 8 — Add centralized security headers and suppress stack disclosure

**Complexity: Medium**  
**Why:** Headers are easy to add, but a usable CSP requires inventorying inline scripts, AJAX, images, fonts, maps, and downloads without breaking dashboards.

> Act as a web application security engineer. Fix QA-009 by adding centrally managed security headers for public pages, authenticated HTML, JSON, CSV, and receipt responses.
>
> Inventory actual script/style/font/image/frame/connect sources. Introduce a deployable Content Security Policy, preferably using nonces or hashes while progressively removing inline scripts. Include `frame-ancestors`, `object-src 'none'`, `base-uri`, and restrictive defaults. Add `X-Content-Type-Options: nosniff`, a suitable `Referrer-Policy`, a minimal `Permissions-Policy`, and explicit cache policy for sensitive pages. Use HSTS only on real HTTPS production deployments. Disable PHP/Apache version disclosure (`expose_php` and unnecessary server tokens).
>
> Account for Google Maps embeds and current local assets without using an unrestricted wildcard or `unsafe-eval`. If a report-only rollout is needed, document the transition and collection endpoint without sensitive data.
>
> Add response-header tests for representative public, role dashboard, JSON, CSV, error, and receipt routes. Add browser tests that fail on CSP violations affecting core workflows.
>
> Acceptance criteria: baseline headers are present consistently; no version header leaks PHP; CSP blocks inline injection/object embedding/clickjacking while all intended application behavior continues to work.

### Prompt 9 — Convert logout to CSRF-protected POST and fully clear the session

**Complexity: Low-Medium**  
**Why:** The endpoint is small, but every role and public navigation entry must be updated and tested with modal behavior and back/forward caching.

> Replace GET logout with a POST-only, CSRF-protected action for all roles. Reuse the centralized secure-session work from QA-002. Update landing/dashboard logout links and confirmation dialogs to submit a form or fetch request with the session CSRF token. Return 405 for non-POST methods and 403 for missing/invalid CSRF.
>
> On success, clear session data, expire the session cookie with matching attributes, destroy the server session, and redirect to a safe local URL. Ensure back/forward cache and direct protected URLs cannot redisplay authenticated data after logout. Avoid placing CSRF tokens in URLs or logs.
>
> Add tests for valid logout, GET forced logout, missing/altered token, cross-role behavior, back/forward navigation, two tabs sharing a session, and post-logout AJAX requests.
>
> Acceptance criteria: third-party pages cannot force logout through a GET/image link; valid logout is reliable; no session is reusable afterward.

### Prompt 10 — Normalize unauthorized view/partial responses

**Complexity: Low**  
**Why:** This should be a mechanical centralization change with a small regression surface.

> Fix QA-008 by inventorying every directly reachable view and dashboard partial. Replace ad-hoc role checks with shared authorization helpers that return correct status codes: 401 only where authentication is required and no session exists if that convention is adopted, otherwise consistent 403 for authenticated role denial; dashboards may redirect unauthenticated browser navigation to login, while AJAX partials should return status plus a safe body.
>
> Specifically correct `apps/views/admin/partials/den-assist-content.php`, which currently emits an unauthorized body with HTTP 200. Prevent data queries from running before authorization. Do not expose whether protected records exist.
>
> Add a data-driven authorization test across every view/partial for anonymous, Patient, Dental Assistant, and Admin. Assert status, safe content type/body, and unchanged database.
>
> Acceptance criteria: no denial is returned as 200; allowed roles retain access; client-side loaders present a clear session/permission message rather than a generic network failure.

### Prompt 11 — Run mutation suites in a disposable clone and validate both-clinic lifecycles

**Complexity: High**  
**Why:** This is test-environment and workflow orchestration work spanning nearly every module, with cleanup and evidence requirements.

> Build a safe integration-test workflow for all currently skipped mutation suites. Provision a disposable database by copying schema only, load synthetic fixtures, configure a local mail sink and isolated receipt/upload directories, and point the application/test server to them through explicit environment variables. The runner must abort unless the database name matches a strict test prefix and storage paths resolve inside a dedicated temporary directory.
>
> Run every existing mutation test, then automate a full appointment lifecycle at each clinic: registration/verification, profile/questionnaire, booking, review, deposit, verification, confirmation, reschedule or cancellation, arrival/check-in, queue, in-progress, odontogram, settlement, completion, notification, history, and review. At each transition assert UI state, database state, audit entry, authorization, notification, dashboard counts, financial state, clinic association, and history.
>
> Include an adjacent cross-clinic schedule scenario respecting the configured transition. Capture machine-readable results and always clean isolated databases/files, even on failure. Never use real recipients or copied patient data.
>
> Acceptance criteria: one repeatable command creates the environment, runs all suites/lifecycles, reports results, verifies cleanup, and cannot connect to the normal configured database.

### Prompt 12 — Add durable notification queue, retry, and operational alerting

**Complexity: High**  
**Why:** Durable background processing, idempotency, retry policy, observability, and UI consistency form a cross-cutting subsystem.

> Design and implement durable email-notification delivery using the existing `appointment_email_notifications` foundation. Keep clinical/financial transactions separate from external SMTP latency: commit the authoritative state and an outbox row atomically, then deliver asynchronously or through a bounded worker.
>
> Define states such as Pending, Processing with lease, Sent, Retryable Failure, and Permanent Failure; include attempt count, next-attempt time, template/version, idempotency key, and sanitized error category. Use exponential backoff with jitter, a maximum attempt count, stuck-job recovery, and duplicate suppression. Do not store rendered sensitive content longer than necessary.
>
> Add Admin operational visibility for failed/stale jobs without exposing patient medical details, addresses, OTPs, or tokens. Define actionable alerts for queue age/failure rate. Keep patient UI notification state truthful when email is delayed or fails.
>
> Add concurrency tests for two workers, crash-after-send ambiguity, duplicate enqueue, SMTP timeout/auth failure, retry recovery, permanent failure, and metrics/log privacy.
>
> Acceptance criteria: no duplicate emails under normal retry; failed messages are visible and recoverable; application requests do not block on SMTP; UI/database/audit/email states remain reconcilable.

---

## Later improvements

### Prompt 13 — Strengthen database constraints and simplify schedule foreign keys

**Complexity: High**  
**Why:** Schema changes touch existing data and deletion semantics. They require a preflight audit, backward-compatible migrations, and rollback planning.

> Act as a senior MySQL data-integrity engineer. Strengthen the database without losing historical clinical or financial records.
>
> Audit existing rows for violations before adding constraints. Remove the duplicate foreign-key definitions on `appointments.schedule_id` and choose one explicit retention policy that prevents schedule deletion from cascading historical appointments. Add appropriate CHECK constraints where supported for nonnegative monetary amounts, capacity bounds, schedule start before end, valid boolean/range values, and timestamp/state consistency. Evaluate how to enforce appointment clinic/date consistency with its schedule; use a safe schema or transactional application approach rather than an invalid cross-table CHECK.
>
> Preserve completed appointments, deposits, billing items, odontograms, messages, and audit history. Provide forward and rollback migrations, preflight queries, backups instructions, and an application compatibility plan. Do not run the migration on the populated database during development.
>
> Add tests for valid/invalid inserts and updates, deletion attempts, historical retention, cascade behavior, and migration of representative legacy rows.
>
> Acceptance criteria: schema rules match business invariants, no ambiguous duplicate FK remains, historical records cannot be orphaned or accidentally cascaded, and migration preflight reports every blocking row.

### Prompt 14 — Add large-history performance benchmarks and query-plan monitoring

**Complexity: Medium-High**  
**Why:** Useful performance work needs realistic synthetic data, stable thresholds, representative endpoints, and query-plan analysis rather than ad-hoc timing.

> Create an isolated performance-test suite using synthetic data at realistic and stress volumes across patients, appointments, services, deposits, messages, audits, odontograms, and billings. Include skewed histories and multiple clinics while retaining the single-dentist model.
>
> Benchmark dashboard summaries, appointment lists, patient history, chat pagination, analytics, reports, billing, audit logs, and exports. Record p50/p95 latency, query count, rows examined, memory, and response size. Use `EXPLAIN`/`EXPLAIN ANALYZE` where supported to identify missing indexes, N+1 queries, filesorts, and full scans. Add pagination/limits where absent without changing totals or clinic filters.
>
> Keep thresholds environment-aware and avoid flaky microbenchmarks. Store sanitized baseline results and flag material regressions in CI. Never copy production PII.
>
> Acceptance criteria: repeatable fixture generation and teardown; documented baseline; critical endpoints meet agreed budgets; query plans use appropriate indexes; large histories remain paginated and correct.

### Prompt 15 — Build a unified CI test runner with isolated database and browser automation

**Complexity: High**  
**Why:** The repository has many useful but ad-hoc scripts. Unification involves environment provisioning, categorization, browser control, reporting, and platform compatibility.

> Replace the ad-hoc test invocation process with one documented runner suitable for local Windows/XAMPP and CI. Categorize tests as static, read-only, rollback integration, isolated mutation, browser, concurrency, and performance. Ensure every mutation test receives an isolated prefixed database and temporary storage/session paths.
>
> Fix `renderSmokeTest.php` so supported cases are discoverable and `--help` succeeds. Fix `webAccessRestrictionsTest.php` to use a test-owned writable `session.save_path`. Add deterministic setup/teardown, local web-server lifecycle, port allocation, mail sink configuration, and cleanup on failure. Produce human-readable and JUnit/TAP output with exact commands and durations.
>
> Add browser automation for all roles, direct endpoint authorization, responsive widths, console/network errors, offline assets, and the complete lifecycle. Mask secrets and prohibit screenshots/logs containing PII.
>
> Acceptance criteria: a clean-checkout command runs the intended suite, exits nonzero on any failure, cannot target the normal database, leaves no database/files/processes behind, and is documented for contributors.

### Prompt 16 — Establish cross-browser and WCAG 2.2 AA coverage

**Complexity: High**  
**Why:** This combines automation, manual assistive-technology testing, responsive/reflow evaluation, and remediation across many dynamic components.

> Create a browser/accessibility quality program for Chromium, Firefox, Edge, and Safari/WebKit where available. Cover public/auth pages and every role module at 360, 768, 1024, desktop, 200% zoom, and 400% reflow.
>
> Automate semantic checks, accessible names, labels, headings, landmarks, focus visibility/order, keyboard reachability, ARIA state changes, error announcements, contrast, reduced motion, and horizontal overflow. Manually verify NVDA on Windows and at least one additional screen reader/platform. Exercise dynamic partial loading, notifications, modal focus trap/restore/Escape, charts with text alternatives, tables, chat updates, validation, queue controls, and financial/clinical action distinctions.
>
> Define a severity rubric and evidence template. Avoid treating an automated scanner score as proof of conformance. Add regression tests for each confirmed barrier and document remaining exceptions.
>
> Acceptance criteria: no keyboard trap, unlabeled actionable control, unannounced blocking validation, or critical contrast/reflow failure remains; results are reproducible and mapped to WCAG 2.2 AA criteria.

### Prompt 17 — Add privacy-safe structured security and audit monitoring

**Complexity: Medium-High**  
**Why:** Logging touches authentication, authorization, uploads, clinical/financial workflows, operations, retention, and alerting. Poor design can create a second sensitive-data store.

> Design structured security and operational audit events without logging PII, medical details, passwords, OTPs, tokens, session IDs, full IP addresses, uploaded filenames, or message bodies.
>
> Define an event schema with timestamp, category, outcome, actor ID/role when authenticated, opaque target ID/type, clinic ID when operationally necessary, correlation ID, sanitized reason code, and source fingerprint using a rotating keyed hash if needed. Cover authentication success/failure/throttle, authorization denial, session rotation/logout, OTP issuance/verification outcome, upload rejection, sensitive status change, settlement/refund, queue state, notification failure, and configuration changes.
>
> Keep clinical/business audit records distinct from security telemetry while allowing correlation. Specify retention, access control, integrity protection, rotation, alert thresholds, and redaction tests. Provide useful dashboards/alerts for brute force, repeated denials, mail queue failures, unusual exports, and settlement anomalies.
>
> Acceptance criteria: events support incident investigation without reconstructing sensitive content; only authorized Admin/operations users can access them; tampering/retention behavior is defined; automated tests scan logs for forbidden secret/PII patterns.

---

## Suggested execution order

1. Prompt 1 and Prompt 2 can proceed in parallel but must share final authentication integration tests.
2. Prompt 3, Prompt 5, Prompt 6, Prompt 7, and Prompt 10 are mostly independent.
3. Prompt 4 should establish the mail-sink baseline before Prompt 12 builds the durable queue.
4. Prompt 8 should follow local asset work from Prompt 6 so the CSP is easier to restrict.
5. Prompt 9 should reuse the centralized session bootstrap from Prompt 2.
6. Prompt 11 should establish the safe disposable environment before Prompts 13–17 are validated broadly.
7. Prompt 15 should absorb all new suites into the unified runner as the remediation program progresses.

