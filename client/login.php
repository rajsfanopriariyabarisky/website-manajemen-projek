<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

if (file_exists(__DIR__ . '/mail.php')) {
    require_once __DIR__ . '/mail.php';
}

$error_msg   = '';
$success_msg = '';

if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header('Location: portal.php');
    exit;
}

// 1. HANDLE REQUEST LINK RESET PASSWORD VIA EMAIL (STANDAR GOOGLE/TOKOPEDIA)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password_action'])) {
    $forgot_email = trim($_POST['forgot_email'] ?? '');

    if (empty($forgot_email)) {
        $error_msg = 'Harap masukkan alamat email resmi Anda.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$forgot_email]);
        $client = $stmt->fetch();

        if ($client) {
            // Generate Secure Random Token & Expiry (15 Menit)
            $token  = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            // Simpan Token di DB
            $stmtToken = $pdo->prepare("UPDATE clients SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmtToken->execute([$token, $expiry, $client['id']]);

            // Susun Link Reset Password
            $protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $domain    = $_SERVER['HTTP_HOST'];
            $baseDir   = dirname($_SERVER['PHP_SELF']);
            $resetLink = $protocol . $domain . $baseDir . "/reset_password.php?token=" . $token;

            // Kirim Email Link Reset via SMTP
            $isSent = false;
            if (function_exists('sendResetLinkEmail')) {
                $isSent = sendResetLinkEmail($client['email'], $resetLink, $client['nama']);
            }

            if ($isSent) {
                $success_msg = 'Link verifikasi reset password telah dikirim ke email <strong>' . htmlspecialchars($client['email']) . '</strong>. Silakan periksa inbox/spam Gmail Anda!';
            } else {
                $error_msg = 'Gagal mengirimkan email reset password. Pastikan koneksi internet Anda stabil.';
            }
        } else {
            $error_msg = 'Email tidak terdaftar dalam database kami. Silakan periksa kembali.';
        }
    }
}

// 2. HANDLE LOGIN STANDARD
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['forgot_password_action'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error_msg = 'Harap masukkan alamat email resmi dan password Anda.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if ($client) {
            if (password_verify($password, $client['password'])) {
                
                if (isset($client['is_verified']) && (int)$client['is_verified'] === 1) {
                    $_SESSION['client_logged_in'] = true;
                    $_SESSION['client_user_id']   = $client['id'];
                    $_SESSION['client_nama']      = $client['nama'];
                    $_SESSION['client_email']     = $client['email'];
                    $_SESSION['client_token']     = hash('sha256', bin2hex(random_bytes(16)) . time());

                    header('Location: portal.php');
                    exit;
                } else {
                    $otp_code   = sprintf("%06d", mt_rand(100000, 999999));
                    $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

                    $isSent = false;
                    if (function_exists('sendOtpViaSmtp')) {
                        $isSent = sendOtpViaSmtp($client['email'], $otp_code, $client['nama']);
                    }

                    if ($isSent) {
                        $stmtOtp = $pdo->prepare("UPDATE clients SET otp_code = ?, otp_expiry = ? WHERE id = ?");
                        $stmtOtp->execute([$otp_code, $otp_expiry, $client['id']]);

                        $_SESSION['pending_client_id']    = $client['id'];
                        $_SESSION['pending_client_email'] = $client['email'];

                        header('Location: verify.php');
                        exit;
                    } else {
                        $error_msg = 'Gagal mengirimkan kode OTP verifikasi pertama. Pastikan koneksi internet Anda stabil.';
                    }
                }
            } else {
                $error_msg = 'Password yang Anda masukkan salah. Silakan periksa kembali.';
            }
        } else {
            $error_msg = 'Email tidak terdaftar dalam sistem PT. CTC! Harap ajukan requirement terlebih dahulu.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Access - PT. Cipta Teknologi Cendekia</title>

    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .gpu {
            will-change: opacity, transform;
            transform: translateZ(0);
        }
        
        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: translateY(12px) scale(0.98);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .animate-fade-in {
            animation: pageFadeIn 0.65s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        .btn-press { transition: all .2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-press:active { transform: scale(.97); }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.15); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4 font-sans relative overflow-hidden select-none">

    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[1000px] max-w-[90vw] h-auto object-contain opacity-[0.10] mix-blend-multiply">
    </div>

    <div class="animate-fade-in gpu w-full max-w-md bg-white/90 border border-white/80 rounded-3xl shadow-2xl shadow-slate-300/80 p-6 sm:p-8 relative z-10 backdrop-blur-md">
        
        <div class="text-center mb-6">
            <div class="w-20 h-20 sm:w-24 sm:h-24 mx-auto mb-3 flex items-center justify-center p-1 bg-white rounded-2xl shadow-sm border border-slate-200/80">
                <img src="../assets/images/logo.jpg" alt="Logo PT. CTC" class="w-full h-full object-contain">
            </div>

            <span class="text-[10px] font-mono font-black uppercase tracking-[0.2em] text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-3 py-1 rounded-full inline-block mb-2 shadow-sm">
                Welcome CTC Client
            </span>
            <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase">CLIENT PORTAL ACCESS</h1>
            <p class="text-xs text-slate-500 mt-1 font-bold">PT. Cipta Teknologi Cendekia</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-5 p-4 bg-emerald-50/90 border border-emerald-200 rounded-2xl text-emerald-800 text-xs flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                <span class="font-bold leading-relaxed"><?= $success_msg ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-5 p-4 bg-red-50/90 border border-red-200 rounded-2xl text-red-700 text-xs flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 text-xs">
            <div>
                <label class="block font-black text-slate-700 uppercase tracking-wider mb-2 text-[10px]">
                    EMAIL RESMI KLIEN / PERUSAHAAN
                </label>
                <div class="relative">
                    <input type="email" name="email" required placeholder="nama@perusahaan.com" 
                           class="input-glow w-full px-4 py-3.5 pl-11 rounded-2xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                    <i class="fa-solid fa-envelope absolute left-4 top-4 text-slate-400 text-sm"></i>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-black text-slate-700 uppercase tracking-wider text-[10px]">
                        PASSWORD PORTAL
                    </label>
                    <button type="button" onclick="openForgotModal()" class="text-[11px] font-extrabold text-emerald-700 hover:underline focus:outline-none">
                        Lupa Password?
                    </button>
                </div>
                <div class="relative">
                    <input type="password" id="loginPassword" name="password" required placeholder="Masukkan password Anda..." 
                           class="input-glow w-full px-4 py-3.5 pl-11 pr-11 rounded-2xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                    <i class="fa-solid fa-lock absolute left-4 top-4 text-slate-400 text-sm"></i>
                    <button type="button" onclick="toggleLoginPassword()" class="absolute right-4 top-4 text-slate-400 hover:text-slate-600 focus:outline-none">
                        <i id="eyeIconLogin" class="fa-solid fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-2">
                <i class="fa-solid fa-right-to-bracket text-sm"></i> MASUK KE PORTAL
            </button>
        </form>

        <div class="mt-8 pt-5 border-t border-slate-200/60 text-center text-xs text-slate-500 font-medium">
            Belum mengajukan proyek? <a href="request.php" class="text-emerald-700 font-bold hover:underline">Kirim Requirement Baru</a>
        </div>
    </div>

    <!-- MODAL POPUP LUPA PASSWORD -->
    <div id="forgotModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-black uppercase tracking-wider">Reset Password Portal</h3>
                </div>
                <button onclick="closeForgotModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="forgot_password_action" value="1">

                <p class="text-slate-600 font-medium leading-relaxed">
                    Masukkan email resmi terdaftar Anda. Kami akan mengirimkan link verifikasi khusus untuk mereset password Anda[cite: 32].
                </p>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Email Perusahaan</label>
                    <div class="relative flex items-center">
                        <input type="email" name="forgot_email" required placeholder="nama@perusahaan.com" class="input-glow w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 pl-9 text-xs text-slate-800 focus:outline-none focus:bg-white font-semibold">
                        <i class="fa-regular fa-envelope absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" onclick="closeForgotModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl uppercase tracking-wider text-xs shadow-md shadow-emerald-600/20">
                        Kirim Link Reset
                    </button>
                </div>
            </form>

        </div>
    </div>

    <div class="absolute bottom-4 text-center text-[10px] text-slate-400 font-bold font-mono">
        &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Client Portal.
    </div>

    <script>
        function toggleLoginPassword() {
            const input = document.getElementById('loginPassword');
            const icon = document.getElementById('eyeIconLogin');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        function openForgotModal() {
            document.getElementById('forgotModal').classList.remove('hidden');
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.add('hidden');
        }
    </script>
</body>
</html>