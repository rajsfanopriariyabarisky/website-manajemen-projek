<?php
// Konfigurasi Email Pengirim PT. CTC
define('SMTP_EMAIL_SENDER', 'ciptateknologicendekia@gmail.com');
define('SMTP_EMAIL_NAME', 'PT. Cipta Teknologi Cendekia');

// PASANG 16 DIGIT APP PASSWORD GMAIL KAMU DI SINI (Tanpa Spasi)
define('SMTP_APP_PASSWORD', 'xxxx xxxx xxxx xxxx'); 

function sendOtpViaSmtp($toEmail, $otpCode, $clientName) {
    $smtpHost = 'ssl://smtp.gmail.com';
    $smtpPort = 465;
    $username = SMTP_EMAIL_SENDER;
    $password = str_replace(' ', '', SMTP_APP_PASSWORD);

    $subject = "Kode OTP Akses Portal Klien - PT. CTC";
    $body = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #0f172a; color: #f8fafc; padding: 20px;'>
        <div style='background-color: #1e293b; padding: 25px; border-radius: 16px; border: 1px solid #334155; max-width: 450px; margin: auto;'>
            <h2 style='color: #ffffff; text-align: center;'>PT. CIPTA TEKNOLOGI CENDEKIA</h2>
            <p style='font-size: 13px; color: #cbd5e1;'>Halo <strong>" . htmlspecialchars($clientName) . "</strong>,</p>
            <p style='font-size: 13px; color: #cbd5e1;'>Berikut adalah Kode OTP rahasia untuk mengakses Portal Klien Anda:</p>
            <div style='font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #10b981; background-color: #022c22; padding: 15px; text-align: center; border-radius: 12px; margin: 20px 0; border: 1px solid #059669;'>{$otpCode}</div>
            <p style='font-size: 11px; color: #f59e0b;'>* Kode ini berlaku selama 10 menit. Jangan berikan kode ini kepada siapapun.</p>
        </div>
    </body>
    </html>";

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_EMAIL_NAME . " <" . $username . ">\r\n";
    $headers .= "To: {$toEmail}\r\n";
    $headers .= "Subject: {$subject}\r\n";

    $smtp = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 15);
    if (!$smtp) return false;

    fgets($smtp, 515);
    fputs($smtp, "EHLO localhost\r\n"); fgets($smtp, 515);
    fputs($smtp, "AUTH LOGIN\r\n"); fgets($smtp, 515);
    fputs($smtp, base64_encode($username) . "\r\n"); fgets($smtp, 515);
    fputs($smtp, base64_encode($password) . "\r\n"); fgets($smtp, 515);
    fputs($smtp, "MAIL FROM: <{$username}>\r\n"); fgets($smtp, 515);
    fputs($smtp, "RCPT TO: <{$toEmail}>\r\n"); fgets($smtp, 515);
    fputs($smtp, "DATA\r\n"); fgets($smtp, 515);
    fputs($smtp, $headers . "\r\n" . $body . "\r\n.\r\n"); fgets($smtp, 515);
    fputs($smtp, "QUIT\r\n"); fclose($smtp);

    return true;
}