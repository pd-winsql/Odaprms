
<style>
  @import url('https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300&family=Jost:wght@300;400;500&display=swap');
  .vd-dash * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Jost', sans-serif; font-weight: 300; }
  .vd-dash { display: flex; height: 560px; background: #e8e0d4; border-radius: 12px; overflow: hidden; font-size: 13px; color: #1a1612; }

  .vd-sidebar { width: 210px; flex-shrink: 0; background: #fffdf9; border-right: 1px solid #d9c9a8; display: flex; flex-direction: column; }
  .vd-sidebar-brand { padding: 20px 18px 16px; border-bottom: 1px solid #d9c9a8; }
  .vd-brand-name { font-family: 'Cormorant Garamond', serif; font-size: 10px; font-style: italic; color: #b5924c; letter-spacing: 0.16em; }
  .vd-brand-ventura { font-family: 'Cormorant Garamond', serif; font-size: 22px; font-weight: 300; color: #1a1612; letter-spacing: 0.1em; display: flex; align-items: center; line-height: 1; }
  .vd-cross { display: inline-flex; align-items: center; justify-content: center; width: 17px; height: 17px; background: #b5924c; color: #fff; font-size: 11px; margin: 0 1px; border-radius: 2px; }
  .vd-brand-sub { font-size: 8px; letter-spacing: 0.26em; color: #b5924c; margin-top: 3px; }

  .vd-nav { flex: 1; padding: 12px 0; }
  .vd-nav-section { font-size: 8.5px; font-weight: 500; letter-spacing: 0.18em; text-transform: uppercase; color: #b5924c; padding: 12px 18px 6px; }
  .vd-nav-item { display: flex; align-items: center; gap: 9px; padding: 9px 18px; color: #4a3f30; font-size: 12px; font-weight: 400; cursor: pointer; transition: background 0.15s; border-left: 2px solid transparent; }
  .vd-nav-item:hover { background: #f5efe4; }
  .vd-nav-item.active { background: #f5efe4; border-left-color: #b5924c; color: #b5924c; font-weight: 500; }
  .vd-nav-item i { font-size: 15px; }

  .vd-sidebar-footer { padding: 14px 18px; border-top: 1px solid #d9c9a8; }
  .vd-user-chip { display: flex; align-items: center; gap: 9px; }
  .vd-avatar { width: 30px; height: 30px; border-radius: 50%; background: #f5efe4; border: 1px solid #d9c9a8; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 500; color: #b5924c; flex-shrink: 0; }
  .vd-user-name { font-size: 12px; font-weight: 400; color: #1a1612; }
  .vd-user-role { font-size: 10px; color: #b5924c; letter-spacing: 0.08em; }

  .vd-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
  .vd-topbar { background: #fffdf9; border-bottom: 1px solid #d9c9a8; padding: 14px 24px; display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
  .vd-page-title { font-family: 'Cormorant Garamond', serif; font-size: 20px; font-weight: 300; color: #1a1612; letter-spacing: 0.04em; }
  .vd-topbar-right { display: flex; align-items: center; gap: 10px; }
  .vd-badge { background: #b5924c; color: #fff; font-size: 9px; font-weight: 500; letter-spacing: 0.12em; text-transform: uppercase; padding: 4px 10px; border-radius: 2px; }

  .vd-content { flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 16px; }

  .vd-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
  .vd-stat { background: #fffdf9; border: 1px solid #d9c9a8; border-radius: 6px; padding: 14px 16px; }
  .vd-stat-label { font-size: 9px; font-weight: 500; letter-spacing: 0.16em; text-transform: uppercase; color: #b5924c; margin-bottom: 6px; }
  .vd-stat-value { font-family: 'Cormorant Garamond', serif; font-size: 28px; font-weight: 300; color: #1a1612; line-height: 1; }
  .vd-stat-sub { font-size: 10px; color: #4a3f30; margin-top: 4px; }

  .vd-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
  .vd-card { background: #fffdf9; border: 1px solid #d9c9a8; border-radius: 6px; }
  .vd-card-header { padding: 12px 16px; border-bottom: 1px solid #d9c9a8; display: flex; align-items: center; justify-content: space-between; }
  .vd-card-title { font-size: 9.5px; font-weight: 500; letter-spacing: 0.18em; text-transform: uppercase; color: #b5924c; }
  .vd-card-link { font-size: 10px; color: #b5924c; letter-spacing: 0.08em; cursor: pointer; }
  .vd-card-body { padding: 12px 16px; display: flex; flex-direction: column; gap: 8px; }

  .vd-appt-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0e8d8; }
  .vd-appt-row:last-child { border-bottom: none; }
  .vd-appt-date { width: 36px; height: 36px; background: #f5efe4; border: 1px solid #d9c9a8; border-radius: 4px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
  .vd-appt-day { font-size: 14px; font-weight: 500; color: #b5924c; line-height: 1; font-family: 'Cormorant Garamond', serif; }
  .vd-appt-mon { font-size: 8px; letter-spacing: 0.1em; color: #4a3f30; text-transform: uppercase; }
  .vd-appt-info { flex: 1; min-width: 0; }
  .vd-appt-name { font-size: 12px; font-weight: 400; color: #1a1612; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .vd-appt-meta { font-size: 10px; color: #4a3f30; }
  .vd-pill { font-size: 9px; font-weight: 500; letter-spacing: 0.1em; text-transform: uppercase; padding: 3px 7px; border-radius: 2px; flex-shrink: 0; }
  .vd-pill-pending  { background: #faeeda; color: #854f0b; }
  .vd-pill-confirmed { background: #eaf3de; color: #3b6d11; }
  .vd-pill-cancelled { background: #fcebeb; color: #a32d2d; }

  .vd-clinic-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f0e8d8; }
  .vd-clinic-row:last-child { border-bottom: none; }
  .vd-clinic-icon { width: 32px; height: 32px; background: #f5efe4; border: 1px solid #d9c9a8; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #b5924c; flex-shrink: 0; }
  .vd-clinic-name { font-size: 12px; font-weight: 400; color: #1a1612; }
  .vd-clinic-addr { font-size: 10px; color: #4a3f30; }
  .vd-dot { width: 7px; height: 7px; border-radius: 50%; background: #b5924c; flex-shrink: 0; }
</style>

<div class="vd-dash">
  <aside class="vd-sidebar">
    <div class="vd-sidebar-brand">
      <div class="vd-brand-name">Dr. Aprille</div>
      <div class="vd-brand-ventura">VEN<span class="vd-cross">✚</span>URA</div>
      <div class="vd-brand-sub">Clinica Dental</div>
    </div>

    <nav class="vd-nav">
      <div class="vd-nav-section">Main</div>
      <div class="vd-nav-item active"><i class="ti ti-layout-dashboard" aria-hidden="true"></i> Dashboard</div>
      <div class="vd-nav-item"><i class="ti ti-calendar" aria-hidden="true"></i> Appointments</div>
      <div class="vd-nav-item"><i class="ti ti-users" aria-hidden="true"></i> Patients</div>

      <div class="vd-nav-section">Manage</div>
      <div class="vd-nav-item"><i class="ti ti-building" aria-hidden="true"></i> Clinics</div>
      <div class="vd-nav-item"><i class="ti ti-clock" aria-hidden="true"></i> Schedules</div>

      <div class="vd-nav-section">Account</div>
      <div class="vd-nav-item"><i class="ti ti-settings" aria-hidden="true"></i> Settings</div>
      <div class="vd-nav-item"><i class="ti ti-logout" aria-hidden="true"></i> Logout</div>
    </nav>

    <div class="vd-sidebar-footer">
      <div class="vd-user-chip">
        <div class="vd-avatar">AD</div>
        <div>
          <div class="vd-user-name">Admin</div>
          <div class="vd-user-role">Administrator</div>
        </div>
      </div>
    </div>
  </aside>

  <main class="vd-main">
    <div class="vd-topbar">
      <div class="vd-page-title">Dashboard</div>
      <div class="vd-topbar-right">
        <span style="font-size:11px; color:#4a3f30;">Saturday, May 16 2026</span>
        <span class="vd-badge">Admin</span>
      </div>
    </div>

    <div class="vd-content">
      <div class="vd-stats">
        <div class="vd-stat">
          <div class="vd-stat-label">Today's Appointments</div>
          <div class="vd-stat-value">4</div>
          <div class="vd-stat-sub">Across 2 clinics</div>
        </div>
        <div class="vd-stat">
          <div class="vd-stat-label">Pending</div>
          <div class="vd-stat-value">9</div>
          <div class="vd-stat-sub">Awaiting confirmation</div>
        </div>
        <div class="vd-stat">
          <div class="vd-stat-label">This Month</div>
          <div class="vd-stat-value">37</div>
          <div class="vd-stat-sub">Total appointments</div>
        </div>
        <div class="vd-stat">
          <div class="vd-stat-label">Clinics</div>
          <div class="vd-stat-value">2</div>
          <div class="vd-stat-sub">Active locations</div>
        </div>
      </div>

      <div class="vd-grid2">
        <div class="vd-card">
          <div class="vd-card-header">
            <span class="vd-card-title">Upcoming Appointments</span>
            <span class="vd-card-link">View all →</span>
          </div>
          <div class="vd-card-body">
            <div class="vd-appt-row">
              <div class="vd-appt-date"><div class="vd-appt-day">17</div><div class="vd-appt-mon">May</div></div>
              <div class="vd-appt-info"><div class="vd-appt-name">Dela Cruz, Juan</div><div class="vd-appt-meta">10:00 AM · Tooth Cleaning · Alcala</div></div>
              <span class="vd-pill vd-pill-confirmed">Confirmed</span>
            </div>
            <div class="vd-appt-row">
              <div class="vd-appt-date"><div class="vd-appt-day">17</div><div class="vd-appt-mon">May</div></div>
              <div class="vd-appt-info"><div class="vd-appt-name">Santos, Maria</div><div class="vd-appt-meta">11:00 AM · Tooth Extraction · Tuguegarao</div></div>
              <span class="vd-pill vd-pill-pending">Pending</span>
            </div>
            <div class="vd-appt-row">
              <div class="vd-appt-date"><div class="vd-appt-day">18</div><div class="vd-appt-mon">May</div></div>
              <div class="vd-appt-info"><div class="vd-appt-name">Reyes, Ana</div><div class="vd-appt-meta">2:00 PM · Dental Check-up · Alcala</div></div>
              <span class="vd-pill vd-pill-pending">Pending</span>
            </div>
            <div class="vd-appt-row">
              <div class="vd-appt-date"><div class="vd-appt-day">20</div><div class="vd-appt-mon">May</div></div>
              <div class="vd-appt-info"><div class="vd-appt-name">Cruz, Pedro</div><div class="vd-appt-meta">3:30 PM · Orthodontics · Tuguegarao</div></div>
              <span class="vd-pill vd-pill-cancelled">Cancelled</span>
            </div>
          </div>
        </div>

        <div class="vd-card">
          <div class="vd-card-header">
            <span class="vd-card-title">Clinic Locations</span>
            <span class="vd-card-link">Manage →</span>
          </div>
          <div class="vd-card-body">
            <div class="vd-clinic-row">
              <div class="vd-clinic-icon"><i class="ti ti-building" style="font-size:15px;" aria-hidden="true"></i></div>
              <div style="flex:1;">
                <div class="vd-clinic-name">Alcala Branch</div>
                <div class="vd-clinic-addr">Zone 4, Tupang, Alcala, Cagayan</div>
              </div>
              <div class="vd-dot"></div>
            </div>
            <div class="vd-clinic-row">
              <div class="vd-clinic-icon"><i class="ti ti-building" style="font-size:15px;" aria-hidden="true"></i></div>
              <div style="flex:1;">
                <div class="vd-clinic-name">Tuguegarao Branch</div>
                <div class="vd-clinic-addr">Bartolome St., Tuguegarao City</div>
              </div>
              <div class="vd-dot"></div>
            </div>
          </div>

          <div class="vd-card-header" style="margin-top:8px;">
            <span class="vd-card-title">Quick Actions</span>
          </div>
          <div class="vd-card-body" style="flex-direction: row; flex-wrap: wrap; gap: 8px;">
            <button onclick="sendPrompt('Draft the admin appointments page for Ventura Dental')" style="font-size:10px; font-weight:500; letter-spacing:0.14em; text-transform:uppercase; background:transparent; border:1px solid #d9c9a8; color:#4a3f30; padding:7px 12px; border-radius:2px; cursor:pointer; font-family:Jost,sans-serif;">Manage Appointments ↗</button>
            <button onclick="sendPrompt('Draft the clinics management page for Ventura Dental')" style="font-size:10px; font-weight:500; letter-spacing:0.14em; text-transform:uppercase; background:transparent; border:1px solid #d9c9a8; color:#4a3f30; padding:7px 12px; border-radius:2px; cursor:pointer; font-family:Jost,sans-serif;">Manage Clinics ↗</button>
          </div>
        </div>
      </div>
    </div>
  </main>
</div>
