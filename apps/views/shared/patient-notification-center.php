<div class="vd-topbar-notifications">
    <button type="button" class="vd-topbar-bell" id="patientNotificationButton"
        aria-label="Appointment notifications, no unread updates"
        aria-controls="patientNotificationPanel" aria-expanded="false">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span class="vd-topbar-bell-dot" id="patientNotificationDot" hidden></span>
    </button>
    <section class="vd-notification-panel" id="patientNotificationPanel"
        aria-labelledby="patientNotificationTitle" hidden>
        <div class="vd-notification-header">
            <div>
                <span class="vd-notification-kicker">Your visits</span>
                <h2 id="patientNotificationTitle">Appointment updates</h2>
            </div>
            <button type="button" class="vd-notification-mark-all" id="patientNotificationMarkAll" hidden>
                Mark all as read
            </button>
        </div>
        <p class="vd-notification-caught-up" id="patientNotificationCaughtUp" role="status" hidden>
            <i class="ti ti-check" aria-hidden="true"></i>
            <span>You’re all caught up.</span>
        </p>
        <div class="vd-notification-list" id="patientNotificationList"></div>
        <p class="vd-notification-empty" id="patientNotificationEmpty">
            No appointment updates yet.
        </p>
    </section>
</div>
