<div class="modal fade" id="staffActionModal" tabindex="-1" aria-labelledby="staffActionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal vd-staff-action-modal">
            <div class="modal-header border-0">
                <div class="d-flex align-items-center gap-3">
                    <span class="vd-action-modal-icon" id="staffActionModalIcon" aria-hidden="true">
                        <i class="ti ti-help"></i>
                    </span>
                    <div>
                        <div class="vd-action-modal-kicker" id="staffActionModalKicker">Please confirm</div>
                        <h5 class="modal-title vd-modal-title mb-0" id="staffActionModalTitle">Confirm Action</h5>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="vd-action-modal-message mb-0" id="staffActionModalMessage"></p>
                <div class="vd-action-modal-details d-none" id="staffActionModalDetails"></div>
                <div class="vd-action-modal-fields d-none" id="staffActionModalFields"></div>
                <div class="vd-action-modal-error d-none" id="staffActionModalError" role="alert"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal" id="staffActionModalCancel">Cancel</button>
                <button type="button" class="btn vd-btn-gold" id="staffActionModalConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>
