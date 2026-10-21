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
        <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[1000px] max-w-[90vw] h-auto object-contain opacity-[0.10] mix-blend-multiply">
    </div>

    <div class="w-full max-w-md bg-white/90 border border-white/80 rounded-3xl shadow-2xl shadow-slate-300/80 p-6 sm:p-8 relative z-10 backdrop-blur-md">
        
        <div class="text-center mb-6">
            <div class="w-16 h-16 mx-auto mb-3 flex items-center justify-center p-1 bg-white rounded-2xl shadow-sm border border-slate-200/80">
                <img src="../assets/images/logo.jpg" alt="Logo PT. CTC" class="w-full h-full object-contain">
            </div>

            <h1 class="text-lg font-black text-slate-900 tracking-tight uppercase">BUAT PASSWORD BARU</h1>
            <p class="text-xs text-slate-500 mt-1 font-bold">PT. Cipta Teknologi Cendekia</p>
        </div>

        <?php if (!empty($success_msg)): ?>
            <div class="mb-5 p-4 bg-emerald-50/90 border border-emerald-200 rounded-2xl text-emerald-800 text-xs flex items-center gap-3 shadow-sm">
                <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i>
                <div>
                    <p class="font-bold leading-relaxed"><?= $success_msg ?></p>
                    <a href="login.php" class="text-emerald-700 underline font-black hover:text-emerald-800 mt-2 inline-block">Kembali ke Halaman Login &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-5 p-4 bg-red-50/90 border border-red-200 rounded-2xl text-red-700 text-xs flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($valid_token): ?>
            <form method="POST" class="space-y-4 text-xs">
                <div>
                    <label class="block font-black text-slate-700 uppercase tracking-wider mb-1.5 text-[10px]">
                        PASSWORD BARU
                    </label>
                    <div class="relative">
                        <input type="password" id="newPwd" name="new_password" required placeholder="Masukkan password baru..." 
                               class="input-glow w-full px-4 py-3 pl-11 pr-11 rounded-2xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all">
                        <i class="fa-solid fa-lock absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <button type="button" onclick="togglePass('newPwd', 'eye1')" class="absolute right-4 top-3.5 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <i id="eye1" class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <div>
                    <label class="block font-black text-slate-700 uppercase tracking-wider mb-1.5 text-[10px]">
                        KONFIRMASI PASSWORD BARU
                    </label>
                    <div class="relative">
                        <input type="password" id="confirmPwd" name="confirm_password" required placeholder="Ketik ulang password baru..." 
                               class="input-glow w-full px-4 py-3 pl-11 pr-11 rounded-2xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all">
                        <i class="fa-solid fa-shield-check absolute left-4 top-3.5 text-slate-400 text-sm"></i>
                        <button type="button" onclick="togglePass('confirmPwd', 'eye2')" class="absolute right-4 top-3.5 text-slate-400 hover:text-slate-600 focus:outline-none">
                            <i id="eye2" class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-floppy-disk text-sm"></i> SIMPAN PASSWORD BARU
                </button>
            </form>
        <?php endif; ?>

        <div class="mt-6 pt-4 border-t border-slate-200/60 text-center text-xs text-slate-500 font-medium">
            <a href="login.php" class="text-emerald-700 font-bold hover:underline">&larr; Kembali ke Login</a>
        </div>
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