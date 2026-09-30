<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/odontogramModel.php';

$conn = (new Database())->connect();
$patient = (new Patient($conn))->getPatientByUserId((int) $_SESSION['user_id']);
$snapshot = $patient ? (new OdontogramModel($conn))->getLatestCompletedSnapshot((int) $patient['patient_id']) : null;

$labels = [
    'Condition' => [
        'D' => 'Tooth decay', 'M' => 'Tooth missing because of decay', 'F' => 'Previously filled tooth',
        'X' => 'Tooth marked for removal', 'RF' => 'Part of the tooth root remains',
        'MO' => 'Tooth missing for another reason', 'IM' => 'Tooth has not come through normally',
    ],
    'Restoration' => [
        'JC' => 'Crown', 'AM' => 'Metal filling', 'CO' => 'Tooth-colored filling',
        'AB' => 'Support tooth for a bridge', 'P' => 'Replacement tooth in a bridge',
        'IN' => 'Inlay filling', 'S' => 'Protective sealant', 'RD' => 'Removable denture',
    ],
    'Surgery' => [
        'X' => 'Tooth removed because of decay', 'XO' => 'Tooth removed for another reason',
        'CM' => 'Tooth did not develop', 'SP' => 'Extra tooth', 'UN' => 'Tooth has not erupted yet',
    ],
];
$surfaceLabels = [
    'Whole' => 'Whole tooth', 'Occlusal' => 'Chewing surface', 'Mesial' => 'Side toward the front',
    'Distal' => 'Side toward the back', 'Buccal' => 'Cheek side', 'Lingual' => 'Tongue side',
];
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$chart = $snapshot['chart'] ?? [];
$dentition = in_array($chart['dentition_type'] ?? '', ['Permanent', 'Primary', 'Mixed'], true)
    ? $chart['dentition_type'] : 'Permanent';
$groups = [
    'Permanent' => [
        'Upper · patient’s right' => ['18','17','16','15','14','13','12','11'],
        'Upper · patient’s left' => ['21','22','23','24','25','26','27','28'],
        'Lower · patient’s right' => ['48','47','46','45','44','43','42','41'],
        'Lower · patient’s left' => ['31','32','33','34','35','36','37','38'],
    ],
    'Primary' => [
        'Upper · patient’s right' => ['55','54','53','52','51'],
        'Upper · patient’s left' => ['61','62','63','64','65'],
        'Lower · patient’s right' => ['85','84','83','82','81'],
        'Lower · patient’s left' => ['71','72','73','74','75'],
    ],
];
$visibleGroups = $dentition === 'Mixed' ? ['Permanent', 'Primary'] : [$dentition];
$findingsByTooth = [];
foreach ((array) ($chart['teeth'] ?? []) as $finding) {
    if (!is_array($finding)) continue;
    $tooth = (string) ($finding['tooth_number'] ?? '');
    if (!preg_match('/^[1-8][1-8]$/', $tooth)) continue;
    $findingsByTooth[$tooth][] = $finding;
}
$findingCount = array_sum(array_map('count', $findingsByTooth));
?>

<section class="vd-patient-chart" aria-labelledby="patientChartTitle">
    <header class="vd-patient-chart-intro">
        <span class="vd-patient-chart-eyebrow">Your dental record</span>
        <h2 id="patientChartTitle">Dental chart</h2>
        <p>Explore your latest dentist-reviewed chart from a completed visit. Tap a tooth to see what the clinic recorded.</p>
    </header>

    <?php if (!$snapshot): ?>
        <div class="vd-patient-chart-empty">
            <i class="ti ti-dental" aria-hidden="true"></i>
            <h3>No reviewed chart yet</h3>
            <p>Your dental chart will appear here after a completed visit has been reviewed by the dentist. For questions about your record, message the clinic.</p>
        </div>
    <?php else: ?>
        <div class="vd-patient-chart-meta">
            <span><i class="ti ti-calendar-check" aria-hidden="true"></i> Visit: <?= $escape(date('M j, Y', strtotime($snapshot['visit_date']))) ?></span>
            <span><i class="ti ti-eye" aria-hidden="true"></i> Read-only record</span>
        </div>
        <p class="vd-patient-chart-guidance">Numbers follow the clinic’s tooth chart. “Patient’s right” and “patient’s left” refer to your own sides. A marked tooth has a recorded finding; an unmarked tooth simply has no finding in this chart.</p>
        <div class="vd-patient-chart-layout" data-patient-chart>
            <div class="vd-patient-chart-map">
                <?php foreach ($visibleGroups as $type): ?>
                    <?php if ($dentition === 'Mixed'): ?><h3 class="vd-patient-chart-set-title"><?= $type === 'Primary' ? 'Baby teeth' : 'Adult teeth' ?></h3><?php endif; ?>
                    <div class="vd-patient-chart-quadrants">
                        <?php foreach ($groups[$type] as $groupLabel => $teeth): ?>
                            <section class="vd-patient-chart-quadrant<?= $type === 'Primary' ? ' is-primary' : '' ?>" aria-label="<?= $escape($groupLabel) ?>">
                                <h3><?= $escape($groupLabel) ?></h3>
                                <div class="vd-patient-chart-teeth">
                                    <?php foreach ($teeth as $tooth): ?>
                                        <?php $hasFinding = !empty($findingsByTooth[$tooth]); ?>
                                        <button type="button" class="vd-patient-chart-tooth<?= $hasFinding ? ' has-finding' : '' ?>" data-chart-tooth="<?= $tooth ?>" aria-label="Tooth <?= $tooth ?><?= $hasFinding ? ', recorded finding' : ', no finding recorded' ?>" aria-pressed="false">
                                            <span class="vd-patient-chart-tooth-number"><?= $tooth ?></span>
                                            <svg viewBox="0 0 48 48" aria-hidden="true"><path d="M13 5C8 7 5 13 6 20c1 8 4 15 7 20 3 5 7 4 11 4s8 1 11-4c3-5 6-12 7-20 1-7-2-13-7-15-4-2-7 1-11 1s-7-3-11-1Z"/><path d="M17 16h14v16H17z"/><path d="M8 12l9 4m14 0 9-4M8 36l9-4m14 0 9 4"/></svg>
                                            <span class="vd-patient-chart-dot" aria-hidden="true"></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <aside class="vd-patient-chart-detail" aria-live="polite">
                <div class="vd-patient-chart-detail-placeholder" data-chart-placeholder>
                    <i class="ti ti-hand-click" aria-hidden="true"></i>
                    <h3>Select a tooth</h3>
                    <p>Tap any tooth number to review its recorded findings.</p>
                </div>
                <?php foreach ($groups as $typeGroups): foreach ($typeGroups as $teeth): foreach ($teeth as $tooth): ?>
                    <div class="vd-patient-chart-detail-panel" data-chart-detail="<?= $tooth ?>" hidden>
                        <span class="vd-patient-chart-detail-kicker">Tooth <?= $tooth ?></span>
                        <h3><?= !empty($findingsByTooth[$tooth]) ? 'Recorded findings' : 'No findings recorded' ?></h3>
                        <?php if (empty($findingsByTooth[$tooth])): ?>
                            <p>This chart has no recorded finding for this tooth. This does not replace a dental examination.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($findingsByTooth[$tooth] as $finding): ?>
                                    <?php
                                    $category = (string) ($finding['category'] ?? '');
                                    $code = (string) ($finding['finding_code'] ?? '');
                                    $label = $labels[$category][$code] ?? 'Dental finding';
                                    $surface = $surfaceLabels[(string) ($finding['surface'] ?? '')] ?? '';
                                    ?>
                                    <li><strong><?= $escape($label) ?></strong><?php if ($surface): ?><span><?= $escape($surface) ?></span><?php endif; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                <?php endforeach; endforeach; endforeach; ?>
            </aside>
        </div>
        <p class="vd-patient-chart-footnote"><?= $findingCount ?> recorded <?= $findingCount === 1 ? 'finding' : 'findings' ?> in this reviewed chart. Need help understanding it? Message the clinic for an explanation from your dentist.</p>
    <?php endif; ?>
</section>

<script>
(() => {
    const chart = document.querySelector('[data-patient-chart]');
    if (!chart) return;
    chart.querySelectorAll('[data-chart-tooth]').forEach(button => button.addEventListener('click', () => {
        const tooth = button.dataset.chartTooth;
        chart.querySelectorAll('[data-chart-tooth]').forEach(item => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        chart.querySelectorAll('[data-chart-detail]').forEach(panel => { panel.hidden = panel.dataset.chartDetail !== tooth; });
        chart.querySelector('[data-chart-placeholder]').hidden = true;
        if (window.matchMedia('(max-width: 1050px)').matches) {
            chart.querySelector('.vd-patient-chart-detail').scrollIntoView({
                behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                block: 'start'
            });
        }
    }));
})();
</script>
