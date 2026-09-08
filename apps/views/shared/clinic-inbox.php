<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Dental Assistant') {
    http_response_code(403);
    exit('Unauthorized.');
}
?>
<section class="vd-chat-page" aria-label="Clinic messages">
    <div data-clinic-chat data-patient="0"></div>
</section>
<script>window.ClinicChat?.mount(document.querySelector('.vd-chat-page [data-clinic-chat]'));</script>
