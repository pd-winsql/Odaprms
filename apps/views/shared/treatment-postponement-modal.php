<div class="modal fade vd-postponement-modal" id="treatmentPostponementModal" tabindex="-1" aria-labelledby="postponementTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content vd-modal-content" id="postponementForm">
            <div class="modal-header">
                <div><div class="vd-action-modal-kicker">Pre-treatment assessment</div><h5 class="modal-title vd-modal-title" id="postponementTitle">Postpone treatment</h5></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="vd-postponement-patient" id="postponementPatient"></p>
                <p class="vd-action-modal-help">No treatment performed and no final billing. Any verified deposit can be applied to the replacement visit.</p>
                <label class="vd-label" for="postponementReason">Reason for postponement</label>
                <textarea class="form-control vd-input" id="postponementReason" name="reason" rows="2" required minlength="3" maxlength="255" placeholder="For example: Elevated blood pressure at assessment."></textarea>
                <fieldset class="vd-postponement-next">
                    <legend class="vd-label">Next appointment</legend>
                    <div class="vd-postponement-options">
                        <label><input type="radio" name="next_step" value="reschedule" checked><span>Reschedule</span></label>
                        <label><input type="radio" name="next_step" value="book_later"><span>Book later</span></label>
                    </div>
                </fieldset>
                <section id="postponementSchedulesPanel" aria-label="Available schedules">
                    <p class="vd-action-modal-help">Choose the schedule agreed with the patient. Full schedules cannot be selected.</p>
                    <div id="postponementSchedules" class="vd-postponement-schedules" aria-live="polite"></div>
                </section>
                <p id="postponementLaterNote" class="vd-action-modal-help" hidden>Deposit retained for rebooking.</p>
                <div id="postponementError" class="vd-action-modal-error" role="alert" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn vd-btn-gold" id="postponementConfirm">Confirm reschedule</button>
            </div>
        </form>
    </div>
</div>
