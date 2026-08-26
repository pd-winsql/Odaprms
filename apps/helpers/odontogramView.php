<?php

function vdRenderOdontogramWorkspace(string $id, bool $readOnly, bool $billingContext = false): void
{
    $permanentUpper = ['18','17','16','15','14','13','12','11','21','22','23','24','25','26','27','28'];
    $permanentLower = ['48','47','46','45','44','43','42','41','31','32','33','34','35','36','37','38'];
    $primaryUpper = ['55','54','53','52','51','61','62','63','64','65'];
    $primaryLower = ['85','84','83','82','81','71','72','73','74','75'];
    $renderArch = static function (array $teeth, string $class): void {
        echo '<div class="vd-odontogram-arch ' . htmlspecialchars($class) . '">';
        foreach ($teeth as $tooth) {
            echo '<button type="button" class="vd-odontogram-tooth" data-tooth="' . $tooth . '" aria-label="Tooth ' . $tooth . '">';
            echo '<span class="vd-tooth-number">' . $tooth . '</span>';
            echo '<svg class="vd-tooth-shape" viewBox="0 0 48 48" aria-hidden="true"><path class="vd-tooth-outline" d="M13 5C8 7 5 13 6 20c1 8 4 15 7 20 3 5 7 4 11 4s8 1 11-4c3-5 6-12 7-20 1-7-2-13-7-15-4-2-7 1-11 1s-7-3-11-1Z"/><path class="vd-tooth-center" d="M17 16h14v16H17z"/><path d="M8 12l9 4m14 0 9-4M8 36l9-4m14 0 9 4"/></svg>';
            echo '<span class="vd-tooth-markers" aria-hidden="true"></span>';
            echo '</button>';
        }
        echo '</div>';
    };
    ?>
    <section class="vd-odontogram" id="<?= htmlspecialchars($id) ?>" data-odontogram-root data-read-only="<?= $readOnly ? '1' : '0' ?>" data-billing-context="<?= $billingContext ? '1' : '0' ?>" data-controller="../../controllers/odontogramController.php" data-csrf="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" aria-label="Patient odontogram">
        <header class="vd-odontogram-heading">
            <div>
                <span class="vd-odontogram-kicker">Clinical dental record</span>
                <h2>Odontogram</h2>
                <p data-odontogram-patient>Loading patient dental chart…</p>
            </div>
            <div class="vd-odontogram-meta">
                <span data-odontogram-access><?= $readOnly ? 'Read-only' : 'Admin / Dentist' ?></span>
                <small data-odontogram-updated>No chart recorded</small>
            </div>
        </header>

        <div class="vd-odontogram-toolbar" role="group" aria-label="Dentition displayed">
            <span>Dentition</span>
            <button type="button" data-dentition="Permanent" class="is-active">Permanent</button>
            <button type="button" data-dentition="Primary">Primary</button>
            <button type="button" data-dentition="Mixed">Both</button>
        </div>

        <div class="vd-odontogram-workspace">
            <div class="vd-odontogram-chart" aria-label="FDI dental chart">
                <div class="vd-odontogram-orientation"><span>Patient’s right</span><span>Patient’s left</span></div>
                <div data-dentition-chart="Permanent">
                    <?php $renderArch($permanentUpper, 'is-upper'); ?>
                    <div class="vd-odontogram-midline" aria-hidden="true"></div>
                    <?php $renderArch($permanentLower, 'is-lower'); ?>
                </div>
                <div data-dentition-chart="Primary" hidden>
                    <?php $renderArch($primaryUpper, 'is-upper is-primary'); ?>
                    <div class="vd-odontogram-midline" aria-hidden="true"></div>
                    <?php $renderArch($primaryLower, 'is-lower is-primary'); ?>
                </div>
                <p class="vd-odontogram-chart-help">Select a tooth to review its recorded findings.</p>
            </div>

            <aside class="vd-odontogram-inspector" aria-live="polite">
                <div class="vd-odontogram-inspector-empty" data-inspector-empty>
                    <i class="ti ti-tooth" aria-hidden="true"></i>
                    <strong>Select a tooth</strong>
                    <span>Its conditions, restorations, and surgery records will appear here.</span>
                </div>
                <div data-inspector-content hidden>
                    <div class="vd-odontogram-inspector-title"><span>Tooth</span><strong data-selected-tooth>—</strong></div>
                    <div class="vd-odontogram-findings" data-tooth-findings></div>
                    <?php if (!$readOnly): ?>
                    <form class="vd-odontogram-finding-form" data-finding-form>
                        <div class="vd-odontogram-field-row">
                            <label>Finding type<select data-finding-category class="form-select vd-input"><option>Condition</option><option>Restoration</option><option>Surgery</option></select></label>
                            <label>Code<select data-finding-code class="form-select vd-input"></select></label>
                        </div>
                        <fieldset><legend>Tooth surface</legend><div class="vd-odontogram-surfaces" data-surface-options></div></fieldset>
                        <label>Clinical note<textarea data-finding-notes class="form-control vd-input" rows="2" maxlength="255" placeholder="Optional finding detail"></textarea></label>
                        <button type="submit" class="btn vd-btn-outline vd-odontogram-add"><i class="ti ti-plus"></i>Add finding</button>
                    </form>
                    <?php endif; ?>
                </div>
            </aside>
        </div>

        <details class="vd-odontogram-legend">
            <summary><span>Clinic charting legend</span><span>Conditions, restorations and surgery codes</span><i class="ti ti-chevron-down"></i></summary>
            <div data-odontogram-legend-grid></div>
        </details>

        <section class="vd-odontogram-assessment" aria-labelledby="<?= htmlspecialchars($id) ?>AssessmentTitle">
            <header><span>Supplementary assessment</span><h3 id="<?= htmlspecialchars($id) ?>AssessmentTitle">Clinical observations</h3></header>
            <div class="vd-odontogram-assessment-grid">
                <label>Periodontal screening<select data-chart-field="periodontal_status" class="form-select vd-input" <?= $readOnly ? 'disabled' : '' ?>><option>None</option><option>Gingivitis</option><option>Early Periodontitis</option><option>Moderate Periodontitis</option><option>Advanced Periodontitis</option></select></label>
                <label>Occlusion class<select data-chart-field="occlusion_class" class="form-select vd-input" <?= $readOnly ? 'disabled' : '' ?>><option value="">Not recorded</option><option>Class I</option><option>Class II</option><option>Class III</option></select></label>
                <label>Occlusion findings<input data-chart-field="occlusion_findings" class="form-control vd-input" maxlength="255" placeholder="Overjet, overbite, midline deviation, crossbite" <?= $readOnly ? 'disabled' : '' ?>></label>
                <label>Appliances<input data-chart-field="appliances" class="form-control vd-input" maxlength="255" placeholder="Orthodontic, space maintainer, others" <?= $readOnly ? 'disabled' : '' ?>></label>
                <label>TMD findings<input data-chart-field="tmd_findings" class="form-control vd-input" maxlength="255" placeholder="Clenching, clicking, trismus, muscle spasm" <?= $readOnly ? 'disabled' : '' ?>></label>
                <label class="is-wide">Clinical notes<textarea data-chart-field="clinical_notes" class="form-control vd-input" rows="3" maxlength="2000" placeholder="Overall dental chart notes" <?= $readOnly ? 'disabled' : '' ?>></textarea></label>
            </div>
        </section>

        <?php if (!$readOnly): ?>
        <div class="vd-odontogram-savebar">
            <div><strong data-savebar-title><?= $billingContext ? 'Review required for settlement' : 'Unsaved dental chart' ?></strong><span data-savebar-message><?= $billingContext ? 'Review the chart and save it before completing final billing.' : 'Changes are recorded in the patient audit trail.' ?></span></div>
            <button type="button" class="btn vd-btn-gold" data-save-odontogram><?= $billingContext ? 'Save & mark reviewed' : 'Save dental chart' ?></button>
        </div>
        <?php endif; ?>

        <section class="vd-odontogram-ledger" aria-labelledby="<?= htmlspecialchars($id) ?>LedgerTitle">
            <header><span>Treatment history</span><h3 id="<?= htmlspecialchars($id) ?>LedgerTitle">Procedure and settlement ledger</h3><p>Financial values come from finalized billing records.</p></header>
            <div class="vd-odontogram-ledger-wrap"><table><thead><tr><th>Date</th><th>Tooth no/s</th><th>Procedure</th><th>Dentist</th><th>Charge</th><th>Paid</th><th>Balance</th><th>Remarks</th></tr></thead><tbody data-odontogram-ledger></tbody></table></div>
        </section>
    </section>
    <?php
}
