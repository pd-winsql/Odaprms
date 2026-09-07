<?php
require_once __DIR__ . '/../../helpers/csrf.php';
$chatPatient = ($_SESSION['user_role'] ?? '') === 'Patient';
?>
<link rel="stylesheet" href="../../../public/css/clinic-chat.css?v=<?= filemtime(__DIR__ . '/../../../public/css/clinic-chat.css') ?>">
<div id="clinicChatConfig" data-patient="<?= $chatPatient ? '1' : '0' ?>" data-user="<?= (int) $_SESSION['user_id'] ?>"
    data-endpoint="../../controllers/chatController.php" data-csrf="<?= htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') ?>" hidden></div>
<?php if ($chatPatient): ?>
<button type="button" class="vd-chat-launch" data-bs-toggle="modal" data-bs-target="#clinicChatModal">
    <i class="ti ti-message-circle" aria-hidden="true"></i>
    <span class="vd-chat-launch-label">Message clinic</span>
    <span data-chat-unread hidden></span>
</button>
<div class="modal fade vd-chat-modal" id="clinicChatModal" tabindex="-1" aria-labelledby="clinicChatTitle">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><div><span class="vd-chat-kicker">PATIENT SUPPORT</span><h2 id="clinicChatTitle">Message the clinic</h2></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close messages"></button></div>
        <div data-clinic-chat data-patient="1"></div>
    </div></div>
</div>
<?php endif; ?>
<script src="../../../public/js/clinic-chat.js?v=<?= filemtime(__DIR__ . '/../../../public/js/clinic-chat.js') ?>"></script>
