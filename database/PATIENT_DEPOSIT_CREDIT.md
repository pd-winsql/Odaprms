# Patient deposit credit

Patient → Deposit: an accepted appointment awaiting payment shows **Use existing deposit** if the signed-in patient has eligible credit. Confirmation applies the whole credit and confirms the replacement without another receipt submission or staff payment approval. The ordinary GCash receipt flow remains available.

Eligible sources are Cancelled + For Refund or Treatment Postponed + Retained for Rebooking, previously verified, not used in billing, and sufficient to cover the required deposit. No splitting or top-ups. Pending Review is not eligible as a target. Expired payment windows cannot use self-service.

POST `depositController.php`, action `applyCredit`, requires Patient role and CSRF. The shared transfer transaction locks both appointment/deposit records, verifies the authenticated user's ownership of both, rechecks states, and commits source consumption, replacement confirmation/code, audit entries and queued email together. Repeated use is rejected. Patient is the audit/transfer actor; the original staff verifier is preserved. The existing staff-only `transfer` route remains unchanged. Refunds and exceptions remain staff-managed. No schema changes required.

Regression checks: `php tests/depositTransferWorkflowTest.php` (temporary records cleaned up) and `node tests/patientDepositCreditBrowserTest.js` (real rendered credit controls, mocked HTTP; desktop/mobile, confirmation, Escape/focus, rejection and success).
