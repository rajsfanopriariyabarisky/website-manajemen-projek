<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Jika sudah login, redirect sesuai session role
if (isset($_SESSION['user_id'])) {
    if (strtolower($_SESSION['role']) === 'admin') {
        header("Location: index.php");
    } else {
        header("Location: my_tasks.php");
    }
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $portal   = $_POST['portal_access'] ?? 'karyawan'; // admin atau karyawan

    if (!empty($email) && !empty($password)) {
        // Ambil data user dari database menggunakan Prepared Statement
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verifikasi Akun & Password (support legacy plaintext, md5, & password_verify)
        if ($user && ($password === $user['password'] || md5($password) === $user['password'] || password_verify($password, $user['password']))) {
            
            $userRoleLower = strtolower($user['role'] ?? 'karyawan');

            // Pengecekan Validasi Portal
            if ($portal === 'admin' && $userRoleLower !== 'admin') {
                $error = "Akun Anda terdaftar sebagai Karyawan, tidak memiliki hak akses ke Portal Admin / PM!";
            } else {
                // Mencegah Session Fixation Attack
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['nama']      = $user['nama'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['email']     = $user['email'];

                // Redirect sesuai pilihan portal / role akun
                if ($userRoleLower === 'admin' && $portal === 'admin') {
                    header("Location: index.php");
                } else {
                    header("Location: my_tasks.php");
                }
                exit;
            }
        } else {
            $error = "Email atau Password yang Anda masukkan salah!";
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
    <title>Login System - PT. Cipta Teknologi Cendekia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-4px); }
            40%, 80% { transform: translateX(4px); }
        }
        .animate-shake {
            animation: shake 0.3s ease-in-out;
        }
    </style>
</head>
<body class="bg-slate-100/80 text-slate-800 min-h-screen flex items-center justify-center p-4 selection:bg-emerald-600 selection:text-white font-sans">

    <!-- Card Container Utama (Putih Bersih) -->
    <div class="max-w-md w-full bg-white border border-slate-200/80 rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/60 relative">
        
        <!-- Header & Logo -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center p-2 bg-slate-50 border border-slate-200 rounded-2xl mb-3 shadow-sm">
                <img src="assets/images/logo.jpg" 
                     alt="Logo PT CTC" 
                     class="w-16 h-16 object-contain rounded-xl"
                     onerror="this.onerror=null; this.src='assets/img/logo.jpg'; this.nextElementSibling.classList.remove('hidden'); if(this.naturalWidth===0) this.classList.add('hidden');">
                <div class="w-16 h-16 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-600 text-2xl hidden">
                    <i class="fa-solid fa-building-user"></i>
                </div>
            </div>

            <h1 class="text-lg sm:text-xl font-bold text-slate-800 tracking-tight">
                PT. CIPTA TEKNOLOGI CENDEKIA
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Management & Operational Workspace</p>
        </div>

        <!-- Alert Error Message -->
        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 text-xs p-3.5 rounded-2xl mb-5 flex items-start gap-2.5 animate-shake">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-sm mt-0.5 shrink-0"></i>
                <div class="leading-relaxed font-medium"><?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- Form Login -->
        <form id="loginForm" action="" method="POST" class="space-y-4" onsubmit="handleFormSubmit(event)">
            
            <!-- Segmented Control: Pilihan Portal -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Akses Portal / Masuk Sebagai</label>
                <div class="grid grid-cols-2 gap-1 p-1 bg-slate-100 border border-slate-200/80 rounded-2xl">
                    <label class="cursor-pointer">
                        <input type="radio" name="portal_access" value="admin" class="peer hidden">
                        <div class="py-2 px-3 rounded-xl text-center text-xs font-semibold text-slate-500 transition-all duration-200 flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-sm peer-checked:border peer-checked:border-slate-200/80 hover:text-slate-800">
                            <i class="fa-solid fa-user-shield text-xs"></i> Admin / PM
                        </div>
                    </label>

                    <label class="cursor-pointer">
                        <input type="radio" name="portal_access" value="karyawan" checked class="peer hidden">
                        <div class="py-2 px-3 rounded-xl text-center text-xs font-semibold text-slate-500 transition-all duration-200 flex items-center justify-center gap-2 peer-checked:bg-white peer-checked:text-emerald-700 peer-checked:shadow-sm peer-checked:border peer-checked:border-slate-200/80 hover:text-slate-800">
                            <i class="fa-solid fa-laptop-code text-xs"></i> Karyawan
                        </div>
                    </label>
                </div>
            </div>

            <!-- Field Email -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Email Perusahaan</label>
                <div class="relative">
                    <input type="email" name="email" required placeholder="nama@ptctc.com" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pl-9 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 transition">
                    <i class="fa-regular fa-envelope absolute left-3 top-3 text-slate-400 text-xs"></i>
                </div>
            </div>

            <!-- Field Password dengan Show/Hide Toggle -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-1.5">Password</label>
                <div class="relative">
                    <input type="password" id="passwordInput" name="password" required placeholder="••••••••" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 pl-9 pr-9 text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/15 transition">
                    <i class="fa-solid fa-lock absolute left-3 top-3 text-slate-400 text-xs"></i>
                    <button type="button" onclick="togglePasswordVisibility()" class="absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 focus:outline-none p-1 transition">
                        <i id="passwordToggleIcon" class="fa-regular fa-eye text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Tombol Submit -->
            <button type="submit" id="submitBtn" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2.5 px-4 rounded-xl transition duration-200 text-xs shadow-md shadow-emerald-600/20 active:scale-[0.99] flex items-center justify-center gap-2 mt-2">
                <i id="btnIcon" class="fa-solid fa-right-to-bracket"></i>
                <span id="btnText">Masuk ke Portal</span>
            </button>
        </form>

        <!-- Footer Info -->
        <div class="mt-6 pt-4 border-t border-slate-100 text-center">
            <p class="text-[11px] text-slate-400">
                Lupa kredensial akun? Silakan hubungi Administrator / PM PT. CTC.
            </p>
        </div>
    </div>

    <script>
        // Toggle Show/Hide Password
        function togglePasswordVisibility() {
            const pwdInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
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

        // Animated Loading State pada tombol Submit
        function handleFormSubmit(event) {
            const btn = document.getElementById('submitBtn');
            const btnIcon = document.getElementById('btnIcon');
            const btnText = document.getElementById('btnText');

            btn.disabled = true;
            btn.classList.add('opacity-75', 'cursor-not-allowed');
            btnIcon.className = 'fa-solid fa-circle-notch fa-spin';
            btnText.textContent = 'Memproses...';
        }
    </script>
</body>
</html>