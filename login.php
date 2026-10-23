<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Jika sudah login, redirect sesuai session role
if (isset($_SESSION['user_id'])) {
    if (strtolower($_SESSION['role'] ?? '') === 'admin') {
        header("Location: index.php");
    } else {
        header("Location: my_tasks.php");
    }
    exit;
}

$error = '';
$success = '';

// Handle Ganti Password Mandiri
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password_action'])) {
    $email        = trim($_POST['email'] ?? '');
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';

    if (!empty($email) && !empty($old_password) && !empty($new_password)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && (password_verify($old_password, $user['password']) || md5($old_password) === $user['password'] || $old_password === $user['password'])) {
            $hashed_new = password_hash($new_password, PASSWORD_BCRYPT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmtUpdate->execute([$hashed_new, $user['id']]);

            $success = "Password untuk akun <strong>" . htmlspecialchars($email) . "</strong> berhasil diperbarui secara aman!";
        } else {
            $error = "Gagal memperbarui: Email atau Password lama yang Anda masukkan salah!";
        }
    } else {
        $error = "Mohon lengkapi seluruh kolom untuk mengganti password.";
    }
}

// Handle Login Form Standard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['change_password_action'])) {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $portal   = $_POST['portal_access'] ?? 'karyawan'; // admin atau karyawan

    if (!empty($email) && !empty($password)) {
        // Ambil data user dari database menggunakan Prepared Statement
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verifikasi Akun & Password (support bcrypt password_verify, md5, & legacy plaintext)
        if ($user && (password_verify($password, $user['password']) || md5($password) === $user['password'] || $password === $user['password'])) {
            
            $userRoleLower = strtolower($user['role'] ?? 'karyawan');

            // Pengecekan Validasi Portal Pas Berdasarkan Role
            if ($portal === 'admin' && $userRoleLower !== 'admin') {
                $error = "Akun Anda terdaftar sebagai Karyawan, tidak memiliki otorisasi ke Portal Admin / PM!";
            } elseif ($portal === 'karyawan' && $userRoleLower !== 'karyawan') {
                $error = "Akun Anda terdaftar sebagai Admin / PM! Silakan pilih portal Admin / PM untuk masuk.";
            } else {
                // Mencegah Session Fixation Attack
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['nama']      = $user['nama'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];

                // Redirect sesuai role akun
                if ($userRoleLower === 'admin') {
                    header("Location: index.php");
                } else {
                    header("Location: my_tasks.php");
                }
                exit;
            }
        } else {
            $error = "Kredensial salah! Periksa kembali Email dan Password Anda.";
        }
    } else {
        $error = "Mohon isi email dan password dengan lengkap.";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Auth - PT. Cipta Teknologi Cendekia</title>
    
    <!-- FAVICON LOGO CTC -->
    <link rel="icon" type="image/jpg" href="assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpg" href="assets/images/logo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .animate-shake { animation: shake 0.3s ease-in-out; }

        .btn-press { transition: transform .15s ease, background-color .2s ease; }
        .btn-press:active { transform: scale(.97); }

        .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
        .input-glow:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,.15);
        }

        /* ANIMASI CENTANG SUKSES MEKAR & MENTAL */
        @keyframes successPop {
            0% { transform: scale(0); opacity: 0; }
            60% { transform: scale(1.15); opacity: 1; }
            80% { transform: scale(0.95); }
            100% { transform: scale(1); }
        }
        @keyframes checkBounce {
            0% { transform: scale(0) rotate(-45deg); opacity: 0; }
            70% { transform: scale(1.2) rotate(0deg); opacity: 1; }
            100% { transform: scale(1) rotate(0deg); }
        }

        .animate-success-box {
            animation: successPop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
        .animate-check-icon {
            animation: checkBounce 0.6s 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
        }
    </style>
</head>
<body class="bg-slate-100/80 text-slate-800 min-h-screen flex flex-col items-center justify-center p-4 selection:bg-emerald-600 selection:text-white font-sans relative overflow-x-hidden">

    <!-- WATERMARK LOGO CTC DI BACKGROUND PUTIH -->
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="assets/images/logo.jpg" alt="CTC Watermark" class="w-[520px] max-w-[85vw] h-auto object-contain opacity-[0.05] mix-blend-multiply">
    </div>

    <!-- Card Container Utama -->
    <div class="max-w-md w-full bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/60 relative z-10 my-auto">
        
        <!-- Header & Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center p-2 bg-slate-50 border border-slate-200/80 rounded-2xl mb-3 shadow-2xs">
                <img src="assets/images/logo.jpg" 
                     alt="Logo PT CTC" 
                     class="w-16 h-16 object-contain rounded-xl"
                     onerror="this.onerror=null; this.src='assets/img/logo.jpg'; this.nextElementSibling.classList.remove('hidden'); if(this.naturalWidth===0) this.classList.add('hidden');">
                <div class="w-16 h-16 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 text-2xl hidden">
                    <i class="fa-solid fa-building-user"></i>
                </div>
            </div>

            <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight">
                PT. CIPTA TEKNOLOGI CENDEKIA
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5 font-bold">Management & Operational Workspace</p>
        </div>

        <!-- Alert Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-xs p-3.5 rounded-2xl mb-5 flex items-start gap-2.5 animate-shake font-bold">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-sm mt-0.5 shrink-0"></i>
                <div class="leading-relaxed"><?= $error ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form id="loginForm" action="" method="POST" class="space-y-4" onsubmit="handleFormSubmit(event)">
            
            <!-- Segmented Control: Pilihan Portal -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Akses Portal / Masuk Sebagai</label>
                <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 border border-slate-200/80 rounded-2xl">
                    <label class="cursor-pointer">
                        <input type="radio" name="portal_access" value="admin" class="peer hidden">
                        <div class="py-2.5 px-3 rounded-xl text-center text-xs font-black text-slate-500 transition-all duration-200 flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-2xs peer-checked:border peer-checked:border-slate-200/80 hover:text-slate-800">
                            <i class="fa-solid fa-user-shield text-xs"></i> Admin / PM
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="portal_access" value="karyawan" checked class="peer hidden">
                        <div class="py-2.5 px-3 rounded-xl text-center text-xs font-black text-slate-500 transition-all duration-200 flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-2xs peer-checked:border peer-checked:border-slate-200/80 hover:text-slate-800">
                            <i class="fa-solid fa-laptop-code text-xs"></i> Karyawan
                        </div>
                    </label>
                </div>
            </div>

            <!-- Field Email -->
            <div>
                <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400 mb-1.5">Email Perusahaan</label>
                <div class="relative flex items-center">
                    <input type="email" name="email" required placeholder="nama@ptctc.com" class="input-glow w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pl-9 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white font-semibold">
                    <i class="fa-regular fa-envelope absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                </div>
            </div>

            <!-- Field Password dengan Show/Hide Toggle Presisi -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="block text-[10px] font-black uppercase tracking-wider text-slate-400">Password</label>
                    <button type="button" onclick="openForgotModal()" class="text-[10px] font-black text-emerald-700 hover:underline">Lupa Password?</button>
                </div>
                <div class="relative flex items-center">
                    <input type="password" id="passwordInput" name="password" required placeholder="••••••••" class="input-glow w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pl-9 pr-10 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white font-semibold">
                    <i class="fa-solid fa-lock absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    
                    <button type="button" onclick="togglePasswordVisibility('passwordInput', 'passwordToggleIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                        <i id="passwordToggleIcon" class="fa-regular fa-eye text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit" id="submitBtn" class="btn-press w-full bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold py-3 px-4 rounded-xl transition duration-200 text-xs shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 mt-2 uppercase tracking-wider">
                <i id="btnIcon" class="fa-solid fa-right-to-bracket"></i>
                <span id="btnText">Masuk ke Portal</span>
            </button>
        </form>

        <!-- Footer Info & Akses Ganti Password Mandiri -->
        <div class="mt-6 pt-4 border-t border-slate-100 text-center space-y-2">
            <p class="text-[11px] text-slate-500 font-medium">
                Akun baru disetup dengan password bawaan? 
            </p>
            <button type="button" onclick="openChangePwdModal()" class="btn-press px-3.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-800 text-[11px] font-extrabold rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs">
                <i class="fa-solid fa-shield-keyhole text-emerald-600 text-xs"></i> Pembaruan Password Mandiri
            </button>
        </div>

    </div>

    <!-- Security Footnote Badge -->
    <div class="mt-4 text-center z-10">
        <p class="text-[10px] text-slate-400 font-bold flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-lock text-emerald-600"></i> 256-Bit Encrypted Security Portal &mdash; PT. Cipta Teknologi Cendekia
        </p>
    </div>

    <!-- POPUP MODAL SUKSES GANTI PASSWORD -->
    <?php if ($success): ?>
    <div id="successModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-in fade-in zoom-in-95 duration-200 text-xs text-center p-6 space-y-4">
            
            <div class="w-16 h-16 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto text-3xl shadow-inner border border-emerald-200/80 animate-success-box">
                <i class="fa-solid fa-check animate-check-icon"></i>
            </div>

            <div>
                <h3 class="text-sm font-black text-slate-900 uppercase tracking-wide">Password Berhasil Diperbarui!</h3>
                <p class="text-[11px] text-slate-500 font-semibold mt-1.5 leading-relaxed">
                    <?= $success ?>
                </p>
            </div>

            <div class="p-2.5 bg-emerald-50 border border-emerald-200/80 rounded-xl text-[10px] font-bold text-emerald-800 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-lock text-emerald-600"></i> Terenkripsi & Siap digunakan untuk Login
            </div>

            <button onclick="closeSuccessModal()" class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl text-xs uppercase tracking-wider shadow-md shadow-emerald-600/20">
                Masuk ke Portal
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL 1: LUPA PASSWORD -->
    <div id="forgotModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-in fade-in zoom-in-95 duration-200 text-xs">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-black uppercase tracking-wider">Bantuan Reset Kredensial</h3>
                </div>
                <button onclick="closeForgotModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <div class="p-5 space-y-3">
                <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-[11px] font-bold leading-relaxed flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 shrink-0"></i>
                    <span>Demi integritas keamanan workspace, reset password wajib diverifikasi oleh PM / Administrator.</span>
                </div>

                <div class="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-200/80">
                    <span class="text-[10px] font-black uppercase text-slate-400 block">Prosedur Reset Password:</span>
                    <ol class="list-decimal list-inside text-slate-700 font-bold space-y-1 text-[11px]">
                        <li>Hubungi Administrator / PM PT. CTC.</li>
                        <li>Sampaikan alamat email karyawan terdaftar.</li>
                        <li>Admin mereset password ke kode standar sementara <span class="font-mono text-slate-900 font-black">123456</span>.</li>
                        <li>Gunakan menu "Pembaruan Password Mandiri" untuk membuat password baru Anda.</li>
                    </ol>
                </div>

                <button onclick="closeForgotModal()" class="btn-press w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl text-xs uppercase tracking-wider">
                    Mengerti & Tutup
                </button>
            </div>

        </div>
    </div>

    <!-- MODAL 2: GANTI PASSWORD MANDIRI -->
    <div id="changePwdModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-in fade-in zoom-in-95 duration-200 text-xs">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-black uppercase tracking-wider">Pembaruan Password Akun</h3>
                </div>
                <button onclick="closeChangePwdModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form action="" method="POST" class="p-5 space-y-3.5">
                <input type="hidden" name="change_password_action" value="1">

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Email Perusahaan</label>
                    <div class="relative flex items-center">
                        <input type="email" name="email" required placeholder="nama@ptctc.com" class="input-glow w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 pl-9 text-xs text-slate-800 focus:outline-none focus:bg-white font-semibold">
                        <i class="fa-regular fa-envelope absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    </div>
                </div>

                <!-- Password Lama dengan Toggle Mata -->
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Password Lama / Standar</label>
                    <div class="relative flex items-center">
                        <input type="password" id="oldPwdInput" name="old_password" required placeholder="123456" class="input-glow w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 pl-9 pr-10 text-xs text-slate-800 focus:outline-none focus:bg-white font-semibold">
                        <i class="fa-solid fa-lock absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                        <button type="button" onclick="togglePasswordVisibility('oldPwdInput', 'oldPwdIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                            <i id="oldPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Baru dengan Toggle Mata -->
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Password Baru</label>
                    <div class="relative flex items-center">
                        <input type="password" id="newPwdInput" name="new_password" required placeholder="Kombinasi password rahasia baru" class="input-glow w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2 pl-9 pr-10 text-xs text-slate-800 focus:outline-none focus:bg-white font-semibold">
                        <i class="fa-solid fa-shield-check absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                        <button type="button" onclick="togglePasswordVisibility('newPwdInput', 'newPwdIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                            <i id="newPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 text-[10px] text-slate-500 font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-lock text-emerald-600"></i> Password baru akan langsung dienkripsi secara aman.
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" onclick="closeChangePwdModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl uppercase tracking-wider text-xs shadow-md shadow-emerald-600/20">
                        Simpan Password Baru
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const pwdInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function handleFormSubmit(event) {
            const btn = document.getElementById('submitBtn');
            const btnIcon = document.getElementById('btnIcon');
            const btnText = document.getElementById('btnText');

            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btnIcon.className = 'fa-solid fa-circle-notch fa-spin';
            btnText.textContent = 'Memproses...';
        }

        function openForgotModal() {
            document.getElementById('forgotModal').classList.remove('hidden');
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.add('hidden');
        }

        function openChangePwdModal() {
            document.getElementById('changePwdModal').classList.remove('hidden');
        }

        function closeChangePwdModal() {
            document.getElementById('changePwdModal').classList.add('hidden');
        }

        function closeSuccessModal() {
            const modal = document.getElementById('successModal');
            if (modal) modal.remove();
        }
    </script>
</body>
</html>