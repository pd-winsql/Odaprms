<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../../config/conn.php';
require_once __DIR__.'/../helpers/authorization.php';
require_once __DIR__.'/../models/paymentReceiptModel.php';
require_once __DIR__.'/../helpers/paymentReceiptImage.php';
vdRequireRoleJson(['Patient']);
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$billingId=(int)($_GET['billing_id']??0);
$receipt=(new PaymentReceiptModel((new Database())->connect()))->forPatient($billingId,(int)$_SESSION['user_id']);
session_write_close();
if (!$receipt) { http_response_code(404); exit('Receipt not found.'); }
if (isset($_GET['preview'])) {
    $base=vdAppUrl('apps/controllers/paymentReceiptController.php').'?billing_id='.$billingId;
    header('Content-Type: text/html; charset=utf-8');
    include __DIR__.'/../views/shared/payment-receipt-preview.php';
    exit;
}
try {
    $bytes=vdPaymentReceiptPng($receipt);
    header('Content-Type: image/png');
    header('Content-Disposition: '.(isset($_GET['download'])?'attachment':'inline').'; filename="'.$receipt['number'].'.png"');
    echo $bytes;
} catch (Throwable $e) {
    error_log('Receipt rendering error: '.$e->getMessage());
    http_response_code(503); header('Content-Type: text/plain; charset=utf-8');
    echo 'Receipt is temporarily unavailable. Please try again or contact the clinic.';
}
