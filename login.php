<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
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
    <link rel="icon" type="image/jpg" href="assets/images/logo.png">
    <link rel="shortcut icon" type="image/jpg" href="assets/images/logo.png">

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

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .animate-shake { animation: shake 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white relative">

    <div class="animate-fade-in w-full max-w-md my-auto relative z-10">

        <!-- Card Login Utama Clean -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-200/60 p-6 sm:p-8 relative overflow-hidden">
            
            <!-- Watermark logo samar di dalam box (persis seperti client/login.php) -->
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none select-none z-0" aria-hidden="true">
                <img src="assets/images/logo.png" 
                     alt="" 
                     class="w-[230px] sm:w-[300px] h-auto object-contain opacity-[0.04] mix-blend-multiply"
                     onerror="this.onerror=null; this.src='assets/img/logo.png';">
            </div>

            <div class="relative z-10">
                <!-- Header Login Clean -->
                <div class="text-center mb-6 pb-5 border-b border-slate-100">
                    <img src="assets/images/logo.png" 
                         alt="Logo PT. CTC" 
                         class="h-16 sm:h-20 w-auto mx-auto mb-3 object-contain rounded-xl"
                         onerror="this.onerror=null; this.src='assets/img/logo.png';">

                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-800 bg-emerald-50 border border-emerald-200 px-3.5 py-1 rounded-full inline-block mb-2">
                        PT. CIPTA TEKNOLOGI CENDEKIA
                    </span>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Internal Workspace Auth</h1>
                    <p class="text-xs text-slate-500 mt-1">Management & Operational Workspace</p>
                </div>

                <!-- Alert Error Message -->
                <?php if ($error): ?>
                    <div class="mb-5 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs sm:text-sm flex items-start gap-3 shadow-xs animate-shake">
                        <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                        <span class="font-medium leading-relaxed"><?= $error ?></span>
                    </div>
                <?php endif; ?>

                <!-- Form Login -->
                <form id="loginForm" action="" method="POST" class="space-y-4" onsubmit="handleFormSubmit(event)">
                    
                    <!-- Segmented Control: Pilihan Portal -->
                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Akses Portal / Masuk Sebagai</label>
                        <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 border border-slate-200 rounded-xl">
                            <label class="cursor-pointer">
                                <input type="radio" name="portal_access" value="admin" class="peer hidden">
                                <div class="py-2 px-3 rounded-lg text-center text-xs font-semibold text-slate-600 transition-all flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-xs hover:text-slate-900">
                                    <i class="fa-solid fa-user-shield text-xs"></i> Admin / PM
                                </div>
                            </label>

                            <label class="cursor-pointer">
                                <input type="radio" name="portal_access" value="karyawan" checked class="peer hidden">
                                <div class="py-2 px-3 rounded-lg text-center text-xs font-semibold text-slate-600 transition-all flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-xs hover:text-slate-900">
                                    <i class="fa-solid fa-laptop-code text-xs"></i> Karyawan
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Field Email -->
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                            Email <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="email" name="email" required placeholder="nama@gmail.com" 
                                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Field Password -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs sm:text-sm font-medium text-slate-700">
                                Password <span class="text-red-500">*</span>
                            </label>
                            <button type="button" onclick="openForgotModal()" class="text-xs font-semibold text-emerald-600 hover:underline focus:outline-none">
                                Lupa Password?
                            </button>
                        </div>
                        <div class="relative">
                            <input type="password" id="passwordInput" name="password" required placeholder="••••••••" 
                                   class="input-glow w-full px-3.5 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i class="fa-solid fa-lock text-sm"></i>
                            </div>
                            <button type="button" onclick="togglePasswordVisibility('passwordInput', 'passwordToggleIcon')" 
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none transition-colors">
                                <i id="passwordToggleIcon" class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tombol Submit -->
                    <div class="pt-2">
                        <button type="submit" id="submitBtn" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg text-sm flex items-center justify-center gap-2 transition-all">
                            <i id="btnIcon" class="fa-solid fa-right-to-bracket text-sm"></i>
                            <span id="btnText">Masuk ke Portal</span>
                        </button>
                    </div>
                </form>

                <!-- Footer Info & Akses Ganti Password Mandiri -->
                <div class="mt-6 pt-4 border-t border-slate-100 text-center space-y-2">
                    <p class="text-xs text-slate-500 font-normal">
                        Akun baru disetup dengan password bawaan?
                    </p>
                    <button type="button" onclick="openChangePwdModal()" class="btn-press px-3.5 py-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 text-emerald-700 text-xs font-semibold rounded-xl transition inline-flex items-center gap-1.5 shadow-2xs">
                        <i class="fa-solid fa-key text-emerald-600 text-xs"></i> Pembaruan Password Mandiri
                    </button>
                </div>
            </div>

        </div>

        <div class="text-center text-xs text-slate-400 font-medium mt-4 flex items-center justify-center gap-1.5">
            <i class="fa-solid fa-shield-halved text-emerald-600"></i> 256-Bit Encrypted Security Portal &mdash; PT. Cipta Teknologi Cendekia
        </div>
    </div>

    <!-- POPUP MODAL SUKSES GANTI PASSWORD -->
    <?php if ($success): ?>
    <div id="successModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs sm:text-sm text-center p-6 space-y-4">
            
            <div class="w-14 h-14 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto text-2xl border border-emerald-200">
                <i class="fa-solid fa-check"></i>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900">Password Berhasil Diperbarui!</h3>
                <p class="text-xs text-slate-500 font-normal mt-1 leading-relaxed">
                    <?= $success ?>
                </p>
            </div>

            <div class="p-2.5 bg-emerald-50 border border-emerald-200 rounded-xl text-xs font-medium text-emerald-800 flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-lock text-emerald-600"></i> Terenkripsi & Siap digunakan untuk Login
            </div>

            <button onclick="closeSuccessModal()" class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-sm">
                Masuk ke Portal
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODAL 1: LUPA PASSWORD -->
    <div id="forgotModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs sm:text-sm">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Bantuan Reset Kredensial</h3>
                </div>
                <button onclick="closeForgotModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <div class="p-5 space-y-3.5">
                <div class="p-3 bg-amber-50 border border-amber-200 text-amber-900 rounded-xl text-xs font-medium leading-relaxed flex items-start gap-2.5">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 shrink-0 text-sm"></i>
                    <span>Demi integritas keamanan workspace, reset password wajib diverifikasi oleh PM / Administrator.</span>
                </div>

                <div class="space-y-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200/80">
                    <span class="text-xs font-semibold text-slate-700 block">Prosedur Reset Password:</span>
                    <ol class="list-decimal list-inside text-slate-600 font-normal space-y-1 text-xs leading-relaxed">
                        <li>Hubungi Administrator / PM PT. CTC.</li>
                        <li>Sampaikan alamat email karyawan terdaftar.</li>
                        <li>Admin mereset password ke kode standar sementara <span class="font-mono text-slate-900 font-semibold bg-slate-200/60 px-1 py-0.5 rounded">123456</span>.</li>
                        <li>Gunakan menu "Pembaruan Password Mandiri" untuk membuat password baru Anda.</li>
                    </ol>
                </div>

                <button onclick="closeForgotModal()" class="btn-press w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl text-xs">
                    Mengerti & Tutup
                </button>
            </div>

        </div>
    </div>

    <!-- MODAL 2: GANTI PASSWORD MANDIRI -->
    <div id="changePwdModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs sm:text-sm">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Pembaruan Password Akun</h3>
                </div>
                <button onclick="closeChangePwdModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form action="" method="POST" class="p-5 space-y-3.5">
                <input type="hidden" name="change_password_action" value="1">

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Email Perusahaan</label>
                    <div class="relative">
                        <input type="email" name="email" required placeholder="nama@ptctc.com" 
                               class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-regular fa-envelope text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- Password Lama -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Password Lama / Standar</label>
                    <div class="relative">
                        <input type="password" id="oldPwdInput" name="old_password" required placeholder="123456" 
                               class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <button type="button" onclick="togglePasswordVisibility('oldPwdInput', 'oldPwdIcon')" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none">
                            <i id="oldPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Baru -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Password Baru</label>
                    <div class="relative">
                        <input type="password" id="newPwdInput" name="new_password" required placeholder="Kombinasi password baru" 
                               class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-shield-halved text-xs"></i>
                        </div>
                        <button type="button" onclick="togglePasswordVisibility('newPwdInput', 'newPwdIcon')" 
                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none">
                            <i id="newPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 text-xs text-slate-500 font-normal flex items-center gap-2">
                    <i class="fa-solid fa-lock text-emerald-600"></i> Password baru akan langsung dienkripsi secara aman.
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeChangePwdModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs">
                        Simpan Password Baru
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
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
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            btnIcon.className = 'fa-solid fa-circle-notch fa-spin text-sm';
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