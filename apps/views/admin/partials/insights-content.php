<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Clinic Insights are available to administrators only.</div>';
    exit;
}

$insightTabs = [
    'analytics' => ['label' => 'Analytics', 'icon' => 'ti-chart-bar', 'partial' => 'analytics-content.php'],
    'reports' => ['label' => 'Reports & Export', 'icon' => 'ti-report-analytics', 'partial' => 'reports-content.php'],
    'feedback' => ['label' => 'Patient Feedback', 'icon' => 'ti-message-star', 'partial' => 'reviews-content.php'],
    'billing' => ['label' => 'Billing Summary', 'icon' => 'ti-receipt', 'partial' => 'cash-billing-content.php'],
];
$activeInsightTab = (string) ($_GET['tab'] ?? 'analytics');
if (!isset($insightTabs[$activeInsightTab])) $activeInsightTab = 'analytics';
?>

<section id="insightsPage" class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">CLINIC OVERSIGHT</div>
        <div class="vd-welcome-name">Clinic Insights</div>
        <p class="text-muted small mb-0 mt-2">Review clinic performance, reports, patient feedback, and completed billing records.</p>
    </div>
    <div class="vd-toggle-bar" role="tablist" aria-label="Clinic insight sections">
        <?php foreach ($insightTabs as $key => $tab): ?>
            <button type="button" class="vd-toggle-btn <?= $key === $activeInsightTab ? 'active' : '' ?>"
                data-insight-tab="<?= htmlspecialchars($key) ?>" role="tab"
                aria-selected="<?= $key === $activeInsightTab ? 'true' : 'false' ?>">
                <i class="ti <?= htmlspecialchars($tab['icon']) ?>" aria-hidden="true"></i>
                <?= htmlspecialchars($tab['label']) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <div id="insightPanel">
        <?php require __DIR__ . '/' . $insightTabs[$activeInsightTab]['partial']; ?>
    </div>
</section>

<script>
(function () {
    document.getElementById('dashTitle').textContent = 'Clinic Insights';
    document.querySelectorAll('[data-insight-tab]').forEach(button => {
        button.addEventListener('click', () => {
            loadpage(`insights-content.php?tab=${encodeURIComponent(button.dataset.insightTab)}`);
        });
    });
})();
</script>
