<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

$error_msg   = '';
$success_msg = '';
$token       = trim($_GET['token'] ?? '');
$valid_token = false;
$client_data = null;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM clients WHERE reset_token = ? AND reset_token_expiry >= NOW()");
    $stmt->execute([$token]);
    $client_data = $stmt->fetch();

    if ($client_data) {
        $valid_token = true;
    } else {
        $error_msg = 'Link reset password tidak valid atau sudah kadaluarsa (lebih dari 15 menit). Silakan minta link baru.';
    }
} else {
    $error_msg = 'Token reset password tidak ditemukan.';
}

// HANDLE SIMPAN PASSWORD BARU
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    if (empty($new_password) || empty($confirm_password)) {
        $error_msg = 'Harap isi seluruh kolom password.';
    } elseif ($new_password !== $confirm_password) {
        $error_msg = 'Konfirmasi password tidak cocok dengan password baru.';
    } elseif (strlen($new_password) < 6) {
        $error_msg = 'Password minimal terdiri dari 6 karakter.';
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

        // Update Password Baru & Hapus Token
        $stmtUpd = $pdo->prepare("UPDATE clients SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        $stmtUpd->execute([$hashed_password, $client_data['id']]);

        $success_msg = 'Password Anda berhasil diperbarui! Silakan login menggunakan password baru Anda.';
        $valid_token = false; // Sembunyikan form
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buat Password Baru - PT. CTC</title>

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
                    <i class="fa-solid fa-key text-emerald-600"></i> Pemulihan Akun
                </span>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight uppercase">Buat Password Baru</h1>
                <p class="text-xs text-slate-500 mt-1.5 font-normal leading-relaxed">
                    PT. Cipta Teknologi Cendekia
                </p>
            </div>

            <!-- Alert Notifikasi -->
            <?php if (!empty($success_msg)): ?>
                <div class="mb-5 p-3.5 bg-emerald-50 border border-emerald-200/80 rounded-xl text-emerald-800 text-xs flex items-start gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-medium leading-relaxed"><?= htmlspecialchars($success_msg) ?></p>
                        <a href="login.php" class="text-emerald-700 font-semibold hover:underline mt-2 inline-flex items-center gap-1 transition">
                            Kembali ke Halaman Login <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-5 p-3.5 bg-red-50 border border-red-200/80 rounded-xl text-red-700 text-xs flex items-start gap-2.5 shadow-2xs">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                    <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <form method="POST" class="space-y-4 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 uppercase tracking-wider mb-1.5 text-[10px]">
                            Password Baru
                        </label>
                        <div class="relative">
                            <input type="password" id="newPwd" name="new_password" required placeholder="Masukkan password baru..." 
                                   class="input-glow w-full px-4 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-slate-50/50 text-slate-900 font-normal focus:bg-white focus:outline-none transition-all placeholder:text-slate-400">
                            <i class="fa-solid fa-lock absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <button type="button" onclick="togglePass('newPwd', 'eye1')" class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i id="eye1" class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 uppercase tracking-wider mb-1.5 text-[10px]">
                            Konfirmasi Password Baru
                        </label>
                        <div class="relative">
                            <input type="password" id="confirmPwd" name="confirm_password" required placeholder="Ketik ulang password baru..." 
                                   class="input-glow w-full px-4 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-slate-50/50 text-slate-900 font-normal focus:bg-white focus:outline-none transition-all placeholder:text-slate-400">
                            <i class="fa-solid fa-shield-check absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                            <button type="button" onclick="togglePass('confirmPwd', 'eye2')" class="absolute right-3.5 top-3 text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i id="eye2" class="fa-solid fa-eye text-xs"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-xs text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-2">
                        <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Password Baru
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Footer Link -->
        <div class="mt-8 pt-4 border-t border-slate-100 text-center text-xs font-medium">
            <a href="login.php" class="text-emerald-700 hover:text-emerald-800 hover:underline inline-flex items-center gap-1.5 transition text-[11px]">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Kembali ke Login
            </a>
        </div>
    </div>

    <!-- Copyright Footer -->
    <div class="absolute bottom-4 text-center text-[11px] text-slate-400 font-normal">
        &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Client Portal.
    </div>

    <script>
        function togglePass(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
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
    </script>
</body>
</html>