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

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }
        
        @keyframes pageFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px) scale(0.99);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .animate-fade-in {
            animation: pageFadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white relative">

    <!-- Decorative ambient background (murni visual, tanpa fungsi) -->
    <div class="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -top-32 -left-32 w-[420px] h-[420px] rounded-full bg-emerald-200/40 blur-3xl"></div>
        <div class="absolute -bottom-40 -right-24 w-[480px] h-[480px] rounded-full bg-teal-100/50 blur-3xl"></div>
    </div>

    <div class="animate-fade-in w-full max-w-md my-auto relative z-10">

        <!-- Card Login Utama Clean -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-200/60 p-6 sm:p-8 relative overflow-hidden">
            
            <!-- Watermark logo samar di dalam box (persis kaya box chat portal.php) -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none select-none z-0" aria-hidden="true">
                <img src="../assets/images/logo.png" alt="" class="w-[230px] sm:w-[300px] h-auto object-contain opacity-[0.04] mix-blend-multiply">
            </div>

            <div class="relative z-10">

                <!-- Header Login Clean -->
                <div class="text-center mb-6 pb-5 border-b border-slate-100">
                    <!-- Logo PNG Transparan Tanpa Box -->
                    <img src="../assets/images/logo.png" alt="Logo PT. CTC" class="h-16 sm:h-20 w-auto mx-auto mb-3 object-contain">

                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-800 bg-emerald-50 border border-emerald-200 px-3.5 py-1 rounded-full inline-block mb-2">
                        PT. CIPTA TEKNOLOGI CENDEKIA
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Client Portal Access</h1>
                    <p class="text-xs text-slate-500 mt-1">Masuk untuk memantau status proyek & negosiasi</p>
                </div>

                <!-- Pesan Sukses -->
                <?php if (!empty($success_msg)): ?>
                    <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs sm:text-sm flex items-start gap-3 shadow-xs">
                        <i class="fa-solid fa-circle-check text-emerald-600 text-lg shrink-0 mt-0.5"></i>
                        <span class="font-medium leading-relaxed"><?= $success_msg ?></span>
                    </div>
                <?php endif; ?>

                <!-- Pesan Error -->
                <?php if (!empty($error_msg)): ?>
                    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs sm:text-sm flex items-start gap-3 shadow-xs">
                        <i class="fa-solid fa-circle-exclamation text-red-500 text-lg shrink-0 mt-0.5"></i>
                        <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                            Email Resmi Klien / Perusahaan <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="email" name="email" required placeholder="nama@perusahaan.com" 
                                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-envelope text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs sm:text-sm font-medium text-slate-700">
                                Password Portal <span class="text-red-500">*</span>
                            </label>
                            <button type="button" onclick="openForgotModal()" class="text-xs font-semibold text-emerald-600 hover:underline focus:outline-none">
                                Lupa Password?
                            </button>
                        </div>
                        <div class="relative">
                            <input type="password" id="loginPassword" name="password" required placeholder="Masukkan password Anda..." 
                                   class="input-glow w-full px-3.5 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-lock text-sm"></i>
                            </div>
                            <button type="button" onclick="toggleLoginPassword()" 
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none transition-colors">
                                <i id="eyeIconLogin" class="fa-solid fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg text-sm flex items-center justify-center gap-2 transition-all">
                            <i class="fa-solid fa-right-to-bracket text-sm"></i> Masuk ke Portal Klien
                        </button>
                    </div>
                </form>

                <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs sm:text-sm text-slate-500">
                    Belum mengajukan proyek? <a href="request.php" class="text-emerald-600 font-semibold hover:underline">Kirim Requirement Baru</a>
                </div>

            </div>
        </div>

        <div class="text-center text-xs text-slate-400 font-medium mt-4">
            &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Software Solution.
        </div>
    </div>

    <!-- MODAL POPUP LUPA PASSWORD -->
    <div id="forgotModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs sm:text-sm">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Reset Password Portal</h3>
                </div>
                <button onclick="closeForgotModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form method="POST" class="p-5 space-y-4">
                <input type="hidden" name="forgot_password_action" value="1">

                <p class="text-slate-600 font-normal leading-relaxed">
                    Masukkan email resmi terdaftar Anda. Kami akan mengirimkan link verifikasi khusus untuk mereset password Anda.
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Email Perusahaan</label>
                    <div class="relative">
                        <input type="email" name="forgot_email" required placeholder="nama@perusahaan.com" 
                               class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 pl-10 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope text-sm"></i>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeForgotModal()" class="btn-press px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-sm">
                        Kirim Link Reset
                    </button>
                </div>
            </form>

        </div>
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