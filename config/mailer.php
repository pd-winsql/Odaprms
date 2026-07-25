<?php
require_once __DIR__ .  '/../vendor/autoload.php'; // Ensure PHPMailer is loaded via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;


function sendOTPEmail($toEmail, $toName, $otp, $type = 'register') {
    $mail = new PHPMailer(true);

    try {
        // SMTP Configuration — Gmail credentials
        $mail->isSMTP();

        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'noreply.draprilleclinic@gmail.com';   // ← gmail
        $mail->Password   = 'vvlgsozjjpwlivsf';       // ← Gmail App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        if ($type === 'register') {
            $subject = 'Verify Your Email Address';
            $intro = 'Thank you for creating an account with Dr. Aprille Ventura Clinica Dental.';
            $message = 'Use the verification code below to verify your email address. This code expires in <strong>10 minutes</strong>.';
            $buttonTitle = 'Email Verification Code';
        } else {
            $subject = 'Password Reset OTP';
            $intro = 'We received a request to reset your password.';
            $message = 'Use the OTP below to reset your password. This code expires in <strong>10 minutes</strong>.';
            $buttonTitle = 'Password Reset Code';
        }

        // Sender and recipient
        $mail->setFrom('ronjcorpuz11@gmail.com', 'Dr. Aprille Ventura Clinica Dental');

        $mail->addAddress($toEmail, $toName);

        // Email content
        $mail->isHTML(true);
        $mail->Subject = $subject . ' — Dr. Aprille Ventura Clinica Dental';
        $mail->Body    = '
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
            ' . $intro . '<br><br>
            ' . $message . '
          </p>

          <div style="text-align: center; background: #f5efe4; border: 1px solid #d9c9a8; border-radius: 6px; padding: 24px; margin-bottom: 24px;">
            <div style="font-size: 11px; letter-spacing: 0.2em; color: #b5924c; text-transform: uppercase; margin-bottom: 10px;">' . $buttonTitle . '</div>
            <div style="font-size: 38px; font-weight: 600; letter-spacing: 0.3em; color: #1a1612;">' . $otp . '</div>
          </div>

          <p style="font-size: 12px; color: #4a3f30; line-height: 1.6;">
            If you did not request a password reset, you can safely ignore this email. Your account remains secure.
          </p>

          <hr style="border: none; border-top: 1px solid #d9c9a8; margin: 24px 0;">
          <p style="font-size: 11px; color: #b5924c; text-align: center; letter-spacing: 0.08em;">
            Dr. Aprille Ventura Clinica Dental
          </p>
        </div>';

        $mail->AltBody =
        "Dr. Aprille Ventura Clinica Dental

        Hello $toName,

        $intro

        $buttonTitle:
        $otp

        This code expires in 10 minutes.

        If you did not request this, you may safely ignore this email.";

        error_log("===== PHPMailer Recipients =====");
        foreach ($mail->getToAddresses() as $recipient) {
            error_log("Recipient: " . $recipient[0]);
        }

        $mail->send();
        return ['success' => true];

    } catch (Exception $e) {
        error_log("Mailer error: " . $mail->ErrorInfo);
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}