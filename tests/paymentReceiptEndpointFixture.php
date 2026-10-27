<?php
// CLI-only controller harness; never an HTTP authentication bypass.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
// Windows shell pipes may translate binary CRLF; encode captured controller output.
ob_start();
register_shutdown_function(static function() { $output=ob_get_clean(); echo base64_encode($output); });
session_start();
$_SESSION['user_role']=$argv[1]; $_SESSION['user_id']=(int)$argv[2];
$_GET['billing_id']=(int)$argv[3];
if (($argv[4]??'')==='preview') $_GET['preview']=1;
if (($argv[4]??'')==='download') $_GET['download']=1;
$_SERVER['SCRIPT_NAME']='/Capstone%20System/apps/controllers/paymentReceiptController.php';
include __DIR__.'/../apps/controllers/paymentReceiptController.php';
