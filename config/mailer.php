<?php
require_once __DIR__ .  '/../vendor/autoload.php'; // Ensure PHPMailer + phpdotenv are loaded via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load .env from the project root (one level up from config/)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function getMailConfig() {
    $useMailtrap = filter_var($_ENV['USE_MAILTRAP'] ?? false, FILTER_VALIDATE_BOOLEAN);

    if ($useMailtrap) {
        return [
            'host'     => $_ENV['MAILTRAP_HOST'],
            'username' => $_ENV['MAILTRAP_USERNAME'],
            'password' => $_ENV['MAILTRAP_PASSWORD'],
            'port'     => (int) $_ENV['MAILTRAP_PORT'],
        ];
    }

    return [
        'host'     => $_ENV['GMAIL_HOST'],
        'username' => $_ENV['GMAIL_USERNAME'],
        'password' => $_ENV['GMAIL_PASSWORD'],
        'port'     => (int) $_ENV['GMAIL_PORT'],
    ];
}

// ── Load a single template from emailTemplates.php ──
function getEmailTemplate($key) {
    $templates = require __DIR__ . '/emailTemplates.php';
    return $templates[$key] ?? null;
}

// ── Shared gold/cream email HTML shell ──
function buildEmailHtml($toName, $template, $value) {
    return '
    <div style="font-family: Georgia, serif; max-width: 480px; margin: 0 auto; padding: 32px 24px; background: #fffdf9; border: 1px solid #d9c9a8; border-radius: 6px;">
      <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 11px; letter-spacing: 0.22em; color: #b5924c; font-style: italic;">Dr. Aprille</div>
        <div style="font-size: 32px; font-weight: 300; letter-spacing: 0.12em; color: #1a1612;">
          VEN<span style="display:inline-block; background:#b5924c; color:#fff; font-size:18px; font-weight:600; padding:2px 6px; border-radius:2px; margin:0 2px;">✚</span>URA
        </div>
        <div style="font-size: 9px; letter-spacing: 0.28em; color: #b5924c; margin-top: 4px;">CLINICA DENTAL</div>
      </div>

      <p style="font-size: 14px; color: #4a3f30; margin-bottom: 8px;">Hello, <strong>' . htmlspecialchars($toName) . '</strong></p>
      <p style="font-size: 13px; color: #4a3f30; line-height: 1.6; margin-bottom: 24px;">
        ' . htmlspecialchars($template['intro']) . '<br><br>
        ' . htmlspecialchars($template['instruction']) . '
      </p>

      <div style="text-align: center; background: #f5efe4; border: 1px solid #d9c9a8; border-radius: 6px; padding: 24px; margin-bottom: 24px;">
        <div style="font-size: 11px; letter-spacing: 0.2em; color: #b5924c; text-transform: uppercase; margin-bottom: 10px;">' . htmlspecialchars($template['label']) . '</div>
        <div style="font-size: 30px; font-weight: 600; letter-spacing: 0.15em; color: #1a1612;">' . htmlspecialchars($value) . '</div>
      </div>

      <p style="font-size: 12px; color: #4a3f30; line-height: 1.6;">
        ' . htmlspecialchars($template['footer']) . '
      </p>

      <hr style="border: none; border-top: 1px solid #d9c9a8; margin: 24px 0;">
      <p style="font-size: 11px; color: #b5924c; text-align: center; letter-spacing: 0.08em;">
        Dr. Aprille Ventura Clinica Dental
      </p>
    </div>';
}

// ── Generic sender: looks up a template by key, sends it with $value as the highlighted code/status ──
function sendTemplateEmail($toEmail, $toName, $templateKey, $value) {
    $template = getEmailTemplate($templateKey);

    if (!$template) {
        error_log("sendTemplateEmail error: unknown template key '$templateKey'");
        return ['success' => false, 'message' => 'Unknown email template.'];
    }

    $config = getMailConfig();
    $mail   = new PHPMailer(true);

    try {
        // SMTP Configuration — switches between Mailtrap (dev) and Gmail (prod) via USE_MAILTRAP
        $mail->isSMTP();

        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['port'];

        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($toEmail, $toName);

        $mail->CharSet = 'UTF-8';
        $mail->isHTML(true);
        $mail->Subject = $template['subject'] . ' — Dr. Aprille Ventura Clinica Dental';
        $mail->Body    = buildEmailHtml($toName, $template, $value);
        $mail->AltBody =
            "Dr. Aprille Ventura Clinica Dental\n\n" .
            "Hello $toName,\n\n" .
            $template['intro'] . "\n\n" .
            $template['label'] . ": $value\n\n" .
            $template['footer'];

        error_log("===== PHPMailer Recipients =====");
        foreach ($mail->getToAddresses() as $recipient) {
            error_log("Recipient: " . $recipient[0]);
        }

        $mail->send();
        return ['success' => true];

    } catch (Exception $e) {
        error_log("Mailer error ($templateKey): " . $mail->ErrorInfo);
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}

// ── Backward-compatible wrapper: existing register / forgot-password OTP calls keep working unchanged ──
function sendOTPEmail($toEmail, $toName, $otp, $type = 'register') {
    $templateKey = $type === 'register' ? 'register' : 'forgot_password';
    return sendTemplateEmail($toEmail, $toName, $templateKey, $otp);
}

// ── New: appointment status notification (used by admin "Save & Notify") ──
function sendAppointmentStatusEmail($toEmail, $toName, $status) {
    $map = [
        'Pending'   => 'appointment_pending',
        'Confirmed' => 'appointment_confirmed',
        'Cancelled' => 'appointment_cancelled',
    ];

    $templateKey = $map[$status] ?? null;

    if (!$templateKey) {
        // e.g. 'Completed' has no email template yet — don't error, just skip sending
        return ['success' => true, 'skipped' => true];
    }

    return sendTemplateEmail($toEmail, $toName, $templateKey, $status);
}