# GCash Manual Receipt Security Test Report

**Date:** 2026-09-20  
**Environment:** Local, isolated schema-only test database  
**Scope:** Receipt-upload attacks, IDOR, forged proof, duplicate references, unauthorized approval, and receipt exposure  
**Result:** **22 passed, 1 failed**

## Executive conclusion

The manual GCash workflow has strong access-control and storage protections. No IDOR, unauthorized approval, direct receipt disclosure, path traversal, duplicate-reference bypass, active SVG upload, renamed HTML upload, or oversized-file bypass was reproduced.

Manual verification is an essential trust boundary: the application cannot establish that a valid-looking image and typed reference correspond to a real GCash transaction. It correctly places such submissions into **Under Review** and does not confirm the appointment until an authorized Dental Assistant approves them.

One upload-validation defect was confirmed: a truncated PNG carrying a valid PNG signature and MIME classification is accepted even though the image is corrupt. A second fraud-detection limitation was confirmed: arbitrarily old transaction timestamps are accepted. The latter is not an authorization vulnerability, but it increases reliance on careful manual review.

## Test isolation and cleanup

- Created a schema-only database named with the guarded `vd_gcash_test_*` prefix.
- Used synthetic users, patients, clinic, appointments, deposits, references, and image files only.
- Used isolated session files and a loopback-only PHP test server.
- Removed the isolated database, session files, source fixtures, and uploaded synthetic receipts after each run.
- The populated application database was not changed.

## Results by requested area

### 1. Receipt-upload attacks

| Test | Result | Evidence |
|---|---|---|
| Text/HTML renamed to `.png` | Pass | Rejected as not JPG/PNG; database unchanged. |
| Active SVG renamed to `.png` | Pass | Rejected; database unchanged. |
| File over 5 MB | Pass | Rejected by server size limit; database unchanged. |
| Invalid short reference | Pass | Rejected before storage. |
| Amount not equal to required deposit | Pass | Rejected before storage. |
| Future transaction timestamp | Pass | Rejected before storage. |
| Truncated PNG with valid PNG signature | **Fail** | Accepted and moved to `Under Review`; the controller checks `finfo` MIME but not decoded image validity. |
| Randomized stored filename | Pass | Accepted files receive a cryptographically random `.png`/`.jpg` filename. |

### 2. IDOR and ownership

| Test | Result | Evidence |
|---|---|---|
| Patient A uploads to Patient B appointment by changing `appointment_id` | Pass | Rejected as access denied; database unchanged. |
| Patient retrieves another receipt through staff endpoint | Pass | HTTP 403. |
| Patient-controlled payment context | Pass | Server derives patient identity from the authenticated session rather than a submitted patient ID. |

### 3. Forged or fabricated receipt proof

| Test | Result | Interpretation |
|---|---|---|
| Valid PNG, arbitrary 12-digit reference, matching amount, syntactically valid date | Pass as designed | Accepted only into `Under Review`; no automatic confirmation. Manual comparison against the GCash account remains required. |
| Amount mismatch | Pass | Rejected. |
| Future date | Pass | Rejected. |
| Arbitrarily old date | Limitation | Accepted because only a future upper bound exists. Staff must notice implausible dates manually. |
| Automatic appointment confirmation on upload | Pass | Did not occur. Confirmation required an authorized assistant verification action. |

Because there is no GCash API/webhook integration, the system cannot cryptographically validate reference ownership, sender, destination account, transaction status, or whether the same screenshot was edited outside the application. This is expected for a manual workflow, but the UI should explicitly instruct staff to verify those details in the clinic's official GCash transaction history—not from the image alone.

### 4. Duplicate references

| Test | Result | Evidence |
|---|---|---|
| Reuse the same reference on a second appointment | Pass | Rejected with duplicate-reference response. |
| Database enforcement | Pass | `gcash_reference` has a unique index. |
| Failed duplicate upload cleanup | Pass | Newly moved temporary receipt was removed and the target deposit remained `Awaiting Submission`. |

### 5. Unauthorized approval

| Actor/action | Result |
|---|---|
| Anonymous approval | Rejected; database unchanged. |
| Patient approval | HTTP 403; database unchanged. |
| Admin approval | HTTP 403 under the current assistant-only policy; database unchanged. |
| Assistant approval without CSRF | Rejected; database unchanged. |
| Authorized assistant approval | Succeeded, changed deposit to `Verified`, and appointment to `Confirmed`. |
| Repeated approval | Rejected without a second transition. |

The current policy makes receipt verification Dental Assistant-only. If the business expects the Admin/Dentist to approve deposits too, that is a policy/product decision rather than a bypass; update the centralized permission contract and tests deliberately.

### 6. Sensitive receipt exposure

| Test | Result | Evidence |
|---|---|---|
| Direct URL under `storage/payment_receipts` | Pass | Apache returned HTTP 403. |
| Anonymous controller access | Pass | HTTP 403. |
| Patient controller access | Pass | HTTP 403. |
| Admin controller access | Pass under current policy | HTTP 403. |
| Authorized assistant controller access | Pass | HTTP 200 with expected image MIME. |
| MIME-sniffing defense | Pass | `X-Content-Type-Options: nosniff`. |
| Sensitive response caching | Pass | Session response included `Cache-Control: no-store`. |
| Database path changed to `../../.env` | Pass | HTTP 404; no secret content disclosed. |

## Confirmed defect

### GCASH-SEC-001 — Corrupted PNG files pass upload validation

- **Severity/confidence:** P2 Medium / High
- **Affected role:** Patient submission; Dental Assistant review
- **Scope:** All clinics
- **Endpoint:** `apps/controllers/depositController.php`, actions `submit` and potentially `extract`
- **Preconditions:** Patient owns an appointment awaiting deposit and has a valid CSRF token.
- **Test data:** Synthetic appointment and a PNG truncated after 33 bytes.
- **Steps:** Submit correct reference format, exact required amount, non-future timestamp, and the truncated PNG.
- **Expected:** Server rejects the file as a corrupt/unreadable image and leaves deposit `Awaiting Submission`.
- **Actual:** Server accepts the upload and changes the deposit to `Under Review`.
- **Consistency:** Reproduced in the isolated test.
- **Impact:** Broken/unrenderable receipts enter the review queue, waste staff time, permit low-cost storage abuse, and may block the payment deadline workflow until rejection/resubmission. No code execution or direct disclosure was reproduced because files use generated image extensions and protected storage.
- **Likely source:** `receiptImageUpload()` validates `finfo` MIME but does not decode the image with `getimagesize`, `imagecreatefromstring`, or safe re-encoding.
- **Remediation:** Require successful image decoding, enforce pixel/dimension limits, and preferably re-encode to a fresh raster image before storage. Apply the same validator to OCR extraction and final submission. Keep byte-size, generated-name, protected-storage, and `nosniff` controls.
- **Regression test:** Reject truncated PNG/JPEG, corrupt chunks, decompression bombs, extreme dimensions, MIME/extension mismatch, SVG/polyglot content, and zero-byte files; accept normal bounded PNG/JPEG images.

## Recommended fraud-control improvements for a manual workflow

These are defense-in-depth recommendations, not reproduced authorization vulnerabilities:

1. Reject transaction times older than a configurable plausible window relative to submission and appointment creation, while allowing a documented staff override.
2. Display receipt amount, reference, and transaction time beside the image and require the reviewer to affirm they checked the clinic's official GCash history.
3. Record reviewer, timestamp, and an immutable audit event; these controls already exist and should remain.
4. Keep duplicate reference uniqueness and add normalized reference matching if GCash formatting variants occur.
5. Consider a second confirmation for unusual cases such as very old transactions, amount discrepancies, or reused receipt-image hashes.
6. Optionally hash uploaded image content to flag exact screenshot reuse across different appointments; treat matches as review signals, not automatic fraud decisions.

## Reproducible test command

```powershell
php tests\gcashSecurityAuditTest.php
```

The test contains a strict isolated-database prefix guard and cleans its database and synthetic files in a `finally` block.

