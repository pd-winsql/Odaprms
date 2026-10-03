# Treatment postponement

Admin / Dentist: Today's Queue → an In Treatment patient → Postpone treatment.
The dialog requires a reason. This is for an assessment that stops treatment before any service is performed.

The transaction closes the visit as Treatment Postponed, records its reason and timestamp, retains any Verified deposit as Retained for Rebooking, and queues a neutral patient email. It creates no billing. Assistant accounts cannot perform this action.

The visit remains in Finished today, the logbook, patient history and the printable dental record. Booked services are not represented as performed treatment.

Rebooking uses the existing DA appointment workflow: accept the same patient's replacement booking, then choose Apply existing deposit. Enter the original postponed appointment number and transfer reason. The replacement is confirmed and the original deposit becomes Transferred. Its receipt remains with the source payment and both appointments receive transfer audit entries. A retained deposit smaller than the new requirement cannot automatically confirm a replacement.

Migration: `php database/migrations/20261004_add_treatment_postponement.php` (idempotent; preserves existing enum values).

Checks: `php tests/treatmentPostponementTest.php` and `node tests/treatmentPostponementBrowserTest.js`.
Tests create fictional records and remove their fixtures. Browser submission is intercepted; backend mutations are verified separately by the PHP integration test.
