<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} elseif (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!defined('SMTP_EMAIL_SENDER')) {
    define('SMTP_EMAIL_SENDER', 'usernotfound991@gmail.com');
}
if (!defined('SMTP_EMAIL_NAME')) {
    define('SMTP_EMAIL_NAME', 'PT. Cipta Teknologi Cendekia');
}
if (!defined('SMTP_APP_PASSWORD')) {
    define('SMTP_APP_PASSWORD', 'dhfafvrrpjqhfbpz');
}

if (!function_exists('sendOtpViaSmtp')) {
    function sendOtpViaSmtp($toEmail, $otpCode, $clientName) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_EMAIL_SENDER;
            $mail->Password   = str_replace(' ', '', SMTP_APP_PASSWORD);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->Timeout    = 10;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom(SMTP_EMAIL_SENDER, SMTP_EMAIL_NAME);
            $mail->addAddress($toEmail, $clientName);

            $mail->isHTML(true);
            $mail->Subject = "Kode OTP Akses Portal Klien - PT. CTC";
            $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px;'>
                <div style='background-color: #1e293b; padding: 25px; border-radius: 16px; border: 1px solid #334155; max-width: 450px; margin: auto;'>
                    <h2 style='color: #ffffff; text-align: center;'>PT. CIPTA TEKNOLOGI CENDEKIA</h2>
                    <p style='font-size: 13px; color: #cbd5e1;'>Halo <strong>" . htmlspecialchars($clientName) . "</strong>,</p>
                    <p style='font-size: 13px; color: #cbd5e1;'>Berikut adalah Kode OTP rahasia untuk mengakses Portal Klien Anda:</p>
                    <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #10b981; background-color: #022c22; padding: 15px; text-align: center; border-radius: 12px; margin: 20px 0; border: 1px solid #059669;'>{$otpCode}</div>
                    <p style='font-size: 11px; color: #f59e0b;'>* Kode ini berlaku selama 2 menit. Jangan berikan kode ini kepada siapapun.</p>
                </div>
            </body>
            </html>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('sendResetLinkEmail')) {
    function sendResetLinkEmail($toEmail, $resetLink, $clientName) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_EMAIL_SENDER;
            $mail->Password   = str_replace(' ', '', SMTP_APP_PASSWORD);
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = 465;
            $mail->Timeout    = 10;

            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                )
            );

            $mail->setFrom(SMTP_EMAIL_SENDER, SMTP_EMAIL_NAME);
            $mail->addAddress($toEmail, $clientName);

            $mail->isHTML(true);
            $mail->Subject = "Reset Password Akun Portal Klien - PT. CTC";
            $mail->Body    = "
            <html>
            <body style='font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px;'>
                <div style='background-color: #1e293b; padding: 25px; border-radius: 16px; border: 1px solid #334155; max-width: 480px; margin: auto;'>
                    <h2 style='color: #ffffff; text-align: center;'>PT. CIPTA TEKNOLOGI CENDEKIA</h2>
                    <p style='font-size: 13px; color: #cbd5e1;'>Halo <strong>" . htmlspecialchars($clientName) . "</strong>,</p>
                    <p style='font-size: 13px; color: #cbd5e1;'>Kami menerima permintaan untuk mereset password akun Client Portal Anda. Klik tombol di bawah ini untuk membuat password baru Anda:</p>
                    <div style='text-align: center; margin: 25px 0;'>
                        <a href='{$resetLink}' style='background-color: #10b981; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 10px; font-weight: bold; font-size: 13px; display: inline-block;'>RESET PASSWORD SAYA</a>
                    </div>
                    <p style='font-size: 11px; color: #cbd5e1;'>Jika tombol tidak bisa diklik, salin link berikut ke browser Anda:</p>
                    <p style='font-size: 10px; color: #38bdf8; word-break: break-all;'>{$resetLink}</p>
                    <p style='font-size: 10px; color: #f59e0b; margin-top: 15px;'>* Link ini hanya berlaku selama 15 menit demi keamanan akun Anda.</p>
                </div>
            </body>
            </html>";

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("PHPMailer Error: " . $mail->ErrorInfo);
            return false;
        }
    }
}