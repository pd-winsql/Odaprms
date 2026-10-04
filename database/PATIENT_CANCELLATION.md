# Patient cancellation notice

Admin → System Settings → Appointment Rules → Patient Cancellation. Default 2 days, editable 0–30 whole days. Changes apply immediately to existing and future confirmed appointments. Booking/reschedule notice settings remain independent.

Patient → Home shows cancellation/withdrawal on each upcoming eligible appointment. Confirmed appointments may be cancelled only strictly before clinic-window start minus the configured days, in Asia/Manila. Exactly 48 hours remaining is blocked with the default. Disabled actions explain that the clinic must be contacted. Unconfirmed Pending Review, Awaiting Deposit, and Payment Under Review requests may still be withdrawn. Checked-in, in-treatment, and terminal appointments are not eligible.

Patient-only POST `appointmentController.php`, action `cancelPatient`, requires CSRF, a reason, and authenticated ownership. The appointment row is locked and status/check-in/deadline are rechecked before committing cancellation, deposit handling, audit, email queue, and release of pending reschedule holds. Verified deposits become For Refund (existing refund/transfer process); unpaid/under-review deposits expire. No automatic cash refund. Staff cancellation continues without the patient notice restriction.

Apply the idempotent migration: `php database/migrations/20261004_add_cancellation_notice.php`.

Checks: `php tests/patientCancellationPolicyTest.php`, `php tests/patientCancellationWorkflowTest.php`, `node tests/patientCancellationBrowserTest.js`. Integration fixtures are removed; browser HTTP submissions are mocked.
