<?php $requestClinics = $conn->query('SELECT clinic_id,clinic_name FROM clinics ORDER BY clinic_name')->fetchAll(PDO::FETCH_ASSOC); ?>
<section id="profileRequestsUI" data-mode="patient" class="vd-print-request-widget">
    <div id="profileRequestStatus" aria-live="polite"></div>
    <dialog id="profileRequestDialog" class="vd-print-request-dialog" aria-labelledby="profileRequestTitle">
        <header class="vd-request-dialog-header"><h2 id="profileRequestTitle">Request printed profile</h2><button type="button" class="vd-request-close" data-close-request aria-label="Close request form"><i class="ti ti-x" aria-hidden="true"></i></button></header>
        <form id="profileRequestForm">
            <div class="vd-print-request-fields">
                <div><label for="requestPickupClinic" class="vd-label form-label">Pickup clinic</label>
                    <select id="requestPickupClinic" name="clinic_id" class="form-select vd-input" required><option value="">Select clinic</option>
                    <?php foreach ($requestClinics as $clinic): ?><option value="<?= (int) $clinic['clinic_id'] ?>"><?= htmlspecialchars($clinic['clinic_name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div><label for="requestPurpose" class="vd-label form-label">Purpose</label>
                    <select id="requestPurpose" name="purpose" class="form-select vd-input" required><option value="Personal copy">Personal copy</option><option value="Transfer to another clinic">Transfer to another clinic</option><option value="Other">Other</option></select>
                </div>
            </div>
            <div id="requestReasonField" class="mt-3" hidden><label for="requestReason" class="vd-label form-label">Reason</label><textarea id="requestReason" name="reason" maxlength="500" class="form-control vd-input" rows="2"></textarea></div>
            <label class="vd-request-billing"><input type="checkbox" name="include_billing" value="1" checked> Include billing / settlement history</label>
            <p class="text-danger" data-request-error role="alert" hidden></p>
            <footer class="vd-request-dialog-footer"><button type="button" class="btn vd-request-secondary" data-close-request>Cancel</button><button type="submit" class="btn vd-btn-gold">Submit request</button></footer>
        </form>
    </dialog>
    <p id="profileRequestError" class="text-danger mt-2" data-request-error role="alert" hidden></p>
</section>
<?php require __DIR__ . '/profile-print-request-script.php'; ?>
