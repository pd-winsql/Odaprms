<div class="vd-topbar-notifications">
    <button type="button" class="vd-topbar-bell" id="staffNotificationButton"
        aria-label="Appointment notifications, no unread updates"
        aria-controls="staffNotificationPanel" aria-expanded="false">
        <i class="ti ti-bell" aria-hidden="true"></i>
        <span class="vd-topbar-bell-dot" id="staffNotificationDot" hidden></span>
    </button>
    <section class="vd-notification-panel" id="staffNotificationPanel"
        aria-labelledby="staffNotificationTitle" hidden>
        <div class="vd-notification-header">
            <div>
                <span class="vd-notification-kicker">Clinic activity</span>
                <h2 id="staffNotificationTitle">Appointment updates</h2>
            </div>
            <button type="button" class="vd-notification-mark-all" id="staffNotificationMarkAll" hidden>
                Mark all read
            </button>
        </div>
        <div class="vd-notification-list" id="staffNotificationList"></div>
        <p class="vd-notification-empty" id="staffNotificationEmpty">
            No appointment updates yet.
        </p>
    </section>
</div>
