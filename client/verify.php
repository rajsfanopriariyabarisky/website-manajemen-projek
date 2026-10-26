<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

if (file_exists(__DIR__ . '/mail.php')) {
    require_once __DIR__ . '/mail.php';
}

if (!isset($_SESSION['pending_client_id'])) {
    header('Location: login.php');
    exit;
}

$error_msg   = '';
$success_msg = '';
$client_id   = $_SESSION['pending_client_id'];
$email       = $_SESSION['pending_client_email'];

if (!isset($_SESSION['otp_resend_count'])) {
    $_SESSION['otp_resend_count'] = 0;
}

$max_resend = 3;

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $success_msg = 'Kode OTP verifikasi baru telah berhasil dikirimkan ke email Anda!';
}

// 1. HANDLE RESEND OTP
if (isset($_GET['action']) && $_GET['action'] === 'resend') {
    if ($_SESSION['otp_resend_count'] >= $max_resend) {
        $error_msg = 'Anda telah mencapai batas maksimal pengiriman ulang OTP (' . $max_resend . 'x). Silakan coba lagi nanti dari halaman login.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$client_id]);
        $client = $stmt->fetch();

        if ($client) {
            $otp_code   = sprintf("%06d", mt_rand(100000, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

            $isSent = false;
            if (function_exists('sendOtpViaSmtp')) {
                $isSent = sendOtpViaSmtp($client['email'], $otp_code, $client['nama']);
            }

            if ($isSent) {
                $stmtOtp = $pdo->prepare("UPDATE clients SET otp_code = ?, otp_expiry = ? WHERE id = ?");
                $stmtOtp->execute([$otp_code, $otp_expiry, $client_id]);

                $_SESSION['otp_resend_count']++;

                header('Location: verify.php?sent=1');
                exit;
            } else {
                $error_msg = 'Gagal mengirim ulang kode OTP. Silakan periksa koneksi internet Anda.';
            }
        }
    }
}

// 2. FETCH DATA CLIENT DARI DB
$stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: login.php');
    exit;
}

$now_timestamp     = time();
$expiry_timestamp  = !empty($client['otp_expiry']) ? strtotime($client['otp_expiry']) : 0;
$remaining_seconds = max(0, $expiry_timestamp - $now_timestamp);

// 3. VERIFIKASI OTP (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_otp'])) {
    $user_otp = trim($_POST['otp_code'] ?? '');

    if (empty($user_otp) || strlen($user_otp) !== 6) {
        $error_msg = 'Harap masukkan 6 digit kode keamanan OTP secara lengkap.';
    } elseif ($remaining_seconds <= 0) {
        $error_msg = 'Kode OTP telah kadaluarsa! Silakan klik "Kirim Ulang OTP" untuk mendapatkan kode baru.';
    } else {
        $current_time = date('Y-m-d H:i:s');
        
        if ($client['otp_code'] === $user_otp && $client['otp_expiry'] >= $current_time) {
            // VERIFIKASI BERHASIL
            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_user_id']   = $client['id'];
            $_SESSION['client_nama']      = $client['nama'];
            $_SESSION['client_email']     = $client['email'];
            $_SESSION['client_token']     = hash('sha256', bin2hex(random_bytes(16)) . time());

            // TANDAI AKUN TERVERIFIKASI PERMANEN (is_verified = 1) DAN RESET OTP
            $pdo->prepare("UPDATE clients SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE id = ?")->execute([$client_id]);

            unset($_SESSION['pending_client_id'], $_SESSION['pending_client_email'], $_SESSION['otp_resend_count']);

            header('Location: portal.php');
            exit;
        } else {
            $error_msg = 'Kode OTP yang Anda masukkan salah. Silakan periksa kembali email Anda.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi OTP Pertama - PT. Cipta Teknologi Cendekia</title>
        
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body, input, button, select, textarea {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .btn-press { 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .btn-press:active { 
            transform: translateY(1px); 
        }

        .input-glow:focus { 
            border-color: #059669; 
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4 font-sans antialiased relative overflow-x-hidden selection:bg-emerald-600 selection:text-white">

    <!-- Watermark Background Logo -->
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="../assets/images/logo.png" alt="Watermark CTC" class="w-[380px] sm:w-[480px] h-auto object-contain opacity-[0.04] mix-blend-multiply">
    </div>

    <!-- Container Card Utama -->
    <div class="w-full max-w-md bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 relative z-10 shadow-sm flex flex-col justify-between">
        
        <div>
            <!-- Header Section -->
            <div class="text-center mb-6">
                <div class="h-12 sm:h-14 w-auto mx-auto mb-4 flex items-center justify-center">
                    <img src="../assets/images/logo.png" alt="Logo PT. CTC" class="h-full w-auto object-contain">
                </div>

                <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-3 py-1 rounded-lg inline-flex items-center gap-1.5 mb-2.5">
                    <i class="fa-solid fa-user-shield text-emerald-600"></i> Verifikasi Akun Baru
                </span>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight uppercase">Verifikasi Kode OTP</h1>
                <p class="text-xs text-slate-500 mt-1.5 font-normal leading-relaxed">
                    Kode verifikasi telah dikirimkan ke email:<br>
                    <strong class="text-slate-800 font-semibold"><?= htmlspecialchars($email) ?></strong>
                </p>
            </div>

            <!-- Alert Notifikasi -->
            <?php if (!empty($success_msg)): ?>
                <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200/80 rounded-xl text-emerald-800 text-xs flex items-center gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                    <span class="font-medium leading-relaxed"><?= htmlspecialchars($success_msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div id="errorBox" class="mb-5 p-3.5 bg-red-50 border border-red-200/80 rounded-xl text-red-700 text-xs flex items-start gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                    <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Badge Countdown Timer -->
            <div class="text-center mb-5">
                <span id="timerContainer" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-mono font-semibold bg-slate-50 text-slate-600 border border-slate-200/80 shadow-2xs">
                    <i class="fa-regular fa-clock text-emerald-600"></i>
                    Sisa Waktu: <span id="countdownTimer" class="text-emerald-700 font-bold">02:00</span>
                </span>
            </div>

            <!-- Form Verifikasi OTP -->
            <form method="POST" class="space-y-5 text-xs">
                <div>
                    <label class="block font-semibold text-slate-700 uppercase tracking-wider mb-2 text-[10px] text-center">
                        Masukkan 6 Digit Kode Keamanan
                    </label>
                    <div class="relative">
                        <input type="text" id="otpInput" name="otp_code" maxlength="6" required placeholder="• • • • • •" autocomplete="off"
                               class="input-glow w-full text-center text-2xl sm:text-3xl font-mono font-bold tracking-[0.35em] px-4 py-3 rounded-xl border border-slate-300 bg-slate-50/50 text-slate-900 focus:bg-white focus:outline-none transition-all placeholder:text-slate-300 placeholder:font-normal">
                    </div>
                </div>

                <button type="submit" id="submitBtn" name="submit_otp" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-xs text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check text-xs"></i> Verifikasi & Masuk Portal
                </button>
            </form>
        </div>

        <!-- Footer Card Options -->
        <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-between text-xs font-medium">
            <?php if ($_SESSION['otp_resend_count'] >= $max_resend): ?>
                <span class="text-red-500 font-semibold flex items-center gap-1.5 cursor-not-allowed text-[11px]">
                    <i class="fa-solid fa-ban text-red-500"></i> Batas Resend Habis (<?= $max_resend ?>/<?= $max_resend ?>)
                </span>
            <?php else: ?>
                <a href="verify.php?action=resend" class="text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1.5 transition text-[11px]">
                    <i class="fa-solid fa-rotate-right text-emerald-600"></i> Kirim Ulang OTP (<?= $_SESSION['otp_resend_count'] ?>/<?= $max_resend ?>)
                </a>
            <?php endif; ?>

            <a href="login.php" class="text-slate-400 hover:text-slate-600 transition text-[11px]">
                Ganti Email
            </a>
        </div>
    </div>

    <!-- Copyright Footer -->
    <div class="absolute bottom-4 text-center text-[11px] text-slate-400 font-normal">
        &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Client Portal.
    </div>

    <!-- Script Countdown Timer -->
    <script>
        let remainingSeconds = <?= (int)$remaining_seconds ?>;
        const timerElement = document.getElementById('countdownTimer');
        const timerContainer = document.getElementById('timerContainer');
        const otpInput = document.getElementById('otpInput');
        const submitBtn = document.getElementById('submitBtn');

        function updateTimer() {
            if (remainingSeconds <= 0) {
                timerElement.innerText = "00:00";
                timerContainer.className = "inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-mono font-semibold bg-red-50 text-red-600 border border-red-200/80 shadow-2xs";
                timerElement.className = "text-red-600 font-bold";
                
                if (otpInput) {
                    otpInput.disabled = true;
                    otpInput.classList.add('bg-slate-100', 'cursor-not-allowed');
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-60', 'cursor-not-allowed');
                }
                return;
            }

            let minutes = Math.floor(remainingSeconds / 60);
            let seconds = remainingSeconds % 60;
            timerElement.innerText = 
                (minutes < 10 ? '0' : '') + minutes + ':' + 
                (seconds < 10 ? '0' : '') + seconds;

            remainingSeconds--;
        }

        updateTimer();
        if (remainingSeconds > 0) {
            const interval = setInterval(() => {
                if (remainingSeconds <= 0) {
                    clearInterval(interval);
                    updateTimer();
                } else {
                    updateTimer();
                }
            }, 1000);
        }
    </script>
</body>
</html>