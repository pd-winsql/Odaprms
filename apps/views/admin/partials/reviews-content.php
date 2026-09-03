<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Admin') {
    http_response_code(403);
    echo '<div class="vd-empty-state">Patient feedback is available to administrators only.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/reviewModel.php';

function reviewEscape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function reviewPatientName(array $review): string {
    $name = trim(($review['firstname'] ?? '') . ' ' . ($review['lastname'] ?? ''));
    return $name !== '' ? $name : 'Former patient';
}

function reviewInitials(array $review): string {
    $first = trim((string) ($review['firstname'] ?? ''));
    $last = trim((string) ($review['lastname'] ?? ''));
    $initials = ($first !== '' ? $first[0] : '') . ($last !== '' ? $last[0] : '');
    return strtoupper($initials !== '' ? $initials : 'P');
}

$conn = (new Database())->connect();
if (!$conn) {
    echo '<div class="vd-empty-state">The patient feedback database is unavailable.</div>';
    exit;
}

$reviewModel = new ReviewModel($conn);
$summary = $reviewModel->getAdminSummary();
$reviews = $reviewModel->getAdminReviews();
$average = (float) $summary['average_rating'];
$roundedAverage = (int) round($average);
?>

<section class="vd-feedback-page" aria-labelledby="patientFeedbackHeading">
    <header class="vd-feedback-overview">
        <div class="vd-feedback-score" aria-label="Average patient rating <?= reviewEscape(number_format($average, 1)) ?> out of 5">
            <span class="vd-feedback-eyebrow">Patient experience</span>
            <div class="vd-feedback-score-line">
                <strong><?= reviewEscape(number_format($average, 1)) ?></strong>
                <span>/ 5</span>
            </div>
            <div class="vd-feedback-stars" aria-hidden="true">
                <?php for ($star = 1; $star <= 5; $star++): ?>
                    <span><?= $star <= $roundedAverage ? '★' : '☆' ?></span>
                <?php endfor; ?>
            </div>
        </div>
        <div class="vd-feedback-intro">
            <span class="vd-feedback-kicker">Visit Rating &amp; Feedback</span>
            <h1 id="patientFeedbackHeading">Listen to every completed visit.</h1>
            <p>Ratings are submitted by verified patients after their treatment and final billing are completed.</p>
        </div>
        <dl class="vd-feedback-counts">
            <div>
                <dt>Total ratings</dt>
                <dd><?= (int) $summary['total_reviews'] ?></dd>
            </div>
            <div>
                <dt>Written feedback</dt>
                <dd><?= (int) $summary['written_feedback_count'] ?></dd>
            </div>
        </dl>
    </header>

    <div class="vd-feedback-list-heading">
        <div>
            <span class="vd-dash-card-title">Recent patient feedback</span>
            <p>Private clinic feedback, newest first</p>
        </div>
        <span class="vd-feedback-total"><?= count($reviews) ?> <?= count($reviews) === 1 ? 'response' : 'responses' ?></span>
    </div>

    <?php if (!$reviews): ?>
        <div class="vd-feedback-empty">
            <span class="vd-feedback-empty-icon"><i class="ti ti-star" aria-hidden="true"></i></span>
            <h2>No visit ratings yet</h2>
            <p>Patient feedback will appear here after a completed visit is rated.</p>
        </div>
    <?php else: ?>
        <div class="vd-feedback-list">
            <?php foreach ($reviews as $review): ?>
                <article class="vd-feedback-entry">
                    <div class="vd-feedback-entry-person">
                        <span class="vd-feedback-avatar" aria-hidden="true"><?= reviewEscape(reviewInitials($review)) ?></span>
                        <div>
                            <h2><?= reviewEscape(reviewPatientName($review)) ?></h2>
                            <span>Appointment #<?= (int) $review['appointment_id'] ?></span>
                        </div>
                    </div>

                    <div class="vd-feedback-entry-body">
                        <div class="vd-feedback-entry-rating" aria-label="<?= (int) $review['rating'] ?> out of 5 stars">
                            <span aria-hidden="true">
                                <?php for ($star = 1; $star <= 5; $star++): ?>
                                    <span><?= $star <= (int) $review['rating'] ? '★' : '☆' ?></span>
                                <?php endfor; ?>
                            </span>
                            <strong><?= (int) $review['rating'] ?>/5</strong>
                        </div>
                        <?php if (trim((string) ($review['feedback'] ?? '')) !== ''): ?>
                            <blockquote><?= nl2br(reviewEscape($review['feedback'])) ?></blockquote>
                        <?php else: ?>
                            <p class="vd-feedback-no-comment">Star rating submitted without a written comment.</p>
                        <?php endif; ?>
                    </div>

                    <dl class="vd-feedback-entry-meta">
                        <div>
                            <dt><i class="ti ti-calendar-check" aria-hidden="true"></i> Visit</dt>
                            <dd><?= reviewEscape(date('M j, Y', strtotime($review['appointment_date']))) ?></dd>
                        </div>
                        <div>
                            <dt><i class="ti ti-building-hospital" aria-hidden="true"></i> Clinic</dt>
                            <dd><?= reviewEscape($review['clinic_name'] ?: 'Not listed') ?></dd>
                        </div>
                        <div>
                            <dt><i class="ti ti-tooth" aria-hidden="true"></i> Service</dt>
                            <dd><?= reviewEscape($review['service_names'] ?: 'Not listed') ?></dd>
                        </div>
                        <div>
                            <dt><i class="ti ti-clock" aria-hidden="true"></i> Received</dt>
                            <dd><?= reviewEscape(date('M j, Y · g:i A', strtotime($review['created_at']))) ?></dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
