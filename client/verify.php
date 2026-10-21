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
        
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-press { transition: all .2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-press:active { transform: scale(.97); }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4 font-sans relative overflow-hidden select-none">

    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[1000px] max-w-[90vw] h-auto object-contain opacity-[0.12] mix-blend-multiply">
    </div>

    <div class="w-full max-w-md bg-white/90 border border-white/80 rounded-3xl shadow-2xl shadow-slate-300/80 p-6 sm:p-8 relative z-10 backdrop-blur-md">
        
        <div class="text-center mb-6">
            <div class="w-24 h-24 mx-auto mb-3 flex items-center justify-center p-1 bg-white rounded-2xl shadow-sm border border-slate-200/80">
                <img src="../assets/images/logo.jpg" alt="Logo PT. CTC" class="w-full h-full object-contain">
            </div>

            <span class="text-[10px] font-mono font-black uppercase tracking-[0.2em] text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-3 py-1 rounded-full inline-block mb-2 shadow-sm">
                <i class="fa-solid fa-user-shield mr-1 text-emerald-600"></i> Verifikasi Akun Baru
            </span>
            <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase">VERIFIKASI KODE OTP</h1>
            <p class="text-xs text-slate-500 mt-1 font-medium">
                Kode verifikasi pertama kali dikirim ke:<br>
                <strong class="text-emerald-700 font-bold"><?= htmlspecialchars($email) ?></strong>
            </p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-5 p-4 bg-emerald-50/90 border border-emerald-200 rounded-2xl text-emerald-800 text-xs flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                <span class="font-bold leading-relaxed"><?= htmlspecialchars($success_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div id="errorBox" class="mb-5 p-4 bg-red-50/90 border border-red-200 rounded-2xl text-red-700 text-xs flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <div class="text-center mb-4">
            <span id="timerContainer" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-mono font-bold bg-slate-100 text-slate-700 border border-slate-200/80 shadow-2xs">
                <i class="fa-regular fa-clock text-emerald-600"></i>
                Sisa Waktu: <span id="countdownTimer" class="text-emerald-700 font-extrabold">02:00</span>
            </span>
        </div>

        <form method="POST" class="space-y-5 text-xs">
            <div>
                <label class="block font-black text-slate-700 uppercase tracking-wider mb-2 text-[10px] text-center">
                    MASUKKAN 6 DIGIT KODE KEAMANAN
                </label>
                <div class="relative">
                    <input type="text" id="otpInput" name="otp_code" maxlength="6" required placeholder="• • • • • •" autocomplete="off"
                           class="input-glow w-full text-center text-3xl font-mono font-black tracking-[0.4em] px-4 py-3.5 rounded-2xl border border-slate-300 bg-slate-50/80 text-emerald-800 focus:bg-white focus:outline-none transition-all placeholder:text-slate-300 placeholder:font-normal">
                </div>
            </div>

            <button type="submit" id="submitBtn" name="submit_otp" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                <i class="fa-solid fa-circle-check text-sm"></i> VERIFIKASI & MASUK PORTAL
            </button>
        </form>

        <div class="mt-8 pt-5 border-t border-slate-200/60 flex items-center justify-between text-xs font-semibold">
            <?php if ($_SESSION['otp_resend_count'] >= $max_resend): ?>
                <span class="text-red-500 font-bold flex items-center gap-1.5 cursor-not-allowed">
                    <i class="fa-solid fa-ban text-red-500"></i> Batas Resend Habis (<?= $max_resend ?>/<?= $max_resend ?>)
                </span>
            <?php else: ?>
                <a href="verify.php?action=resend" class="text-emerald-700 hover:text-emerald-800 hover:underline flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-rotate-right text-emerald-600"></i> Kirim Ulang OTP (<?= $_SESSION['otp_resend_count'] ?>/<?= $max_resend ?>)
                </a>
            <?php endif; ?>

            <a href="login.php" class="text-slate-400 hover:text-slate-600 transition">
                Ganti Email
            </a>
        </div>
    </div>

    <div class="absolute bottom-4 text-center text-[10px] text-slate-400 font-bold font-mono">
        &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Client Portal.
    </div>

    <script>
        let remainingSeconds = <?= (int)$remaining_seconds ?>;
        const timerElement = document.getElementById('countdownTimer');
        const timerContainer = document.getElementById('timerContainer');
        const otpInput = document.getElementById('otpInput');
        const submitBtn = document.getElementById('submitBtn');

        function updateTimer() {
            if (remainingSeconds <= 0) {
                timerElement.innerText = "00:00";
                timerContainer.className = "inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full text-xs font-mono font-bold bg-red-50 text-red-600 border border-red-200 shadow-2xs";
                timerElement.className = "text-red-600 font-extrabold";
                
                if (otpInput) {
                    otpInput.disabled = true;
                    otpInput.classList.add('bg-slate-100', 'cursor-not-allowed');
                }
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
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