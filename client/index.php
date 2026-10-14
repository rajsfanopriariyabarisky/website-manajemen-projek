<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error_msg = 'Harap isi email dan password Anda.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'Client'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['client_logged_in'] = true;
            $_SESSION['client_user_id']   = $user['id'];
            $_SESSION['client_nama']      = $user['nama'];
            $_SESSION['client_email']     = $user['email'];

            header('Location: portal.php');
            exit;
        } else {
            $error_msg = 'Email atau password salah! (Password default: CTC12345)';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal Login - PT. Cipta Teknologi Cendekia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans relative">

    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 md:p-8 relative z-10">
        <div class="text-center mb-6">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold text-xl mx-auto mb-3">
                <i class="fa-solid fa-user-lock"></i>
            </div>
            <h1 class="text-lg font-bold text-white uppercase tracking-wider">Client Portal Access</h1>
            <p class="text-xs text-slate-400 mt-1">PT. Cipta Teknologi Cendekia</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-4 p-3.5 bg-red-950/80 border border-red-500/40 rounded-xl text-red-300 text-xs flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-red-400"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4 text-xs">
            <div>
                <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Email Resmi</label>
                <input type="email" name="email" required placeholder="email@perusahaan.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500">
                <span class="text-[10px] text-slate-500 block mt-1">Password default: <strong class="text-emerald-400">CTC12345</strong></span>
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition shadow-lg text-xs uppercase tracking-wider">
                Masuk ke Portal
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-slate-800 text-center text-[11px] text-slate-500">
            Belum mengajukan proyek? <a href="request.php" class="text-emerald-400 font-bold hover:underline">Kirim Requirement Baru</a>
        </div>
    </div>

</body>
</html>