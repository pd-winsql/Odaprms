# Treatment postponement

Admin / Dentist: Today's Queue → an In Treatment patient → Postpone treatment.
The dialog requires a reason. This is for an assessment that stops treatment before any service is performed.
Choose **Reschedule** or **Book later**. Reschedule lists future clinic windows eligible under the existing rescheduling lead-time policy, excludes dates the patient already booked, and displays remaining capacity (including active reschedule holds). Full windows remain visible but disabled. The dentist agrees the schedule verbally with the patient.

Confirmation locks the patient and target schedule and rechecks capacity and date conflicts. Postponement, the accepted replacement booking, copied original services, audit links and any verified deposit transfer commit together. Failure keeps the dialog open and rolls back all changes. A stale full slot refreshes the choices without clearing the reason. No new tables or migrations are needed for this extension: replacement links are recorded in audit logs and the existing deposit transfer source field.

A sufficient verified deposit confirms the replacement and queues its check-in code email. Without a verified deposit, the accepted replacement awaits its deposit with the normal deadline. If retained credit is below the current required deposit, the existing transfer policy prevents automatic rescheduling; choose Book later and arrange the difference with the clinic. Book later retains any verified credit without creating a replacement.

The transaction closes the visit as Treatment Postponed, records its reason and timestamp, retains any Verified deposit as Retained for Rebooking, and queues a neutral patient email. It creates no billing. Assistant accounts cannot perform this action.

The visit remains in Finished today, the logbook, patient history and the printable dental record. Booked services are not represented as performed treatment.

Rebooking uses the existing DA appointment workflow: accept the same patient's replacement booking, then choose Apply existing deposit. Enter the original postponed appointment number and transfer reason. The replacement is confirmed and the original deposit becomes Transferred. Its receipt remains with the source payment and both appointments receive transfer audit entries. A retained deposit smaller than the new requirement cannot automatically confirm a replacement.

Migration: `php database/migrations/20261004_add_treatment_postponement.php` (idempotent; preserves existing enum values).

Checks: `php tests/treatmentPostponementTest.php`, `php tests/treatmentPostponementRescheduleTest.php`, `php tests/depositTransferWorkflowTest.php` and `node tests/treatmentPostponementBrowserTest.js`.
Tests create fictional records and remove their fixtures. Browser submission is intercepted; backend mutations are verified separately by the PHP integration test.
