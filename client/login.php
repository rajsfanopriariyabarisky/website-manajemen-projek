<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (file_exists(__DIR__ . '/mail.php')) {
    require_once __DIR__ . '/mail.php';
}

$error_msg = '';

if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header('Location: portal.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email)) {
        $error_msg = 'Harap masukkan alamat email resmi Anda.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if (!$client) {
            $stmtReq = $pdo->prepare("SELECT client_name, client_email FROM client_requirements WHERE LOWER(client_email) = LOWER(?) ORDER BY id DESC LIMIT 1");
            $stmtReq->execute([$email]);
            $req = $stmtReq->fetch();

            if ($req) {
                $stmtIns = $pdo->prepare("INSERT INTO clients (nama, email, password) VALUES (?, ?, ?)");
                $stmtIns->execute([$req['client_name'], $req['client_email'], password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT)]);
                
                $stmtNew = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
                $stmtNew->execute([$pdo->lastInsertId()]);
                $client = $stmtNew->fetch();
            }
        }

        if ($client) {
            $otp_code = sprintf("%06d", mt_rand(100000, 999999));
            $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));

            $stmtOtp = $pdo->prepare("UPDATE clients SET otp_code = ?, otp_expiry = ? WHERE id = ?");
            $stmtOtp->execute([$otp_code, $otp_expiry, $client['id']]);

            if (function_exists('sendOtpViaSmtp')) {
                sendOtpViaSmtp($client['email'], $otp_code, $client['nama']);
            }

            $_SESSION['pending_client_id']    = $client['id'];
            $_SESSION['pending_client_email'] = $client['email'];

            header('Location: verify.php');
            exit;
        } else {
            $error_msg = 'Email tidak terdaftar dalam database requirement PT. CTC! Harap ajukan requirement terlebih dahulu.';
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

    <!-- Favicon Logo CTC untuk Tab Browser Chrome/Google -->
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

    <!-- Watermark Logo CTC Raksasa Full Background -->
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[1000px] max-w-[90vw] h-auto object-contain opacity-[0.12] mix-blend-multiply">
    </div>

    <!-- Soft Glassmorphism Card Container -->
    <div class="w-full max-w-md bg-white/90 border border-white/80 rounded-3xl shadow-2xl shadow-slate-300/80 p-6 sm:p-8 relative z-10 backdrop-blur-md">
        
        <!-- Header Branding & Logo CTC Utama -->
        <div class="text-center mb-6">
            <div class="w-24 h-24 mx-auto mb-3 flex items-center justify-center p-1 bg-white rounded-2xl shadow-sm border border-slate-200/80">
                <img src="../assets/images/logo.jpg" alt="Logo PT. CTC" class="w-full h-full object-contain">
            </div>

            <span class="text-[10px] font-mono font-black uppercase tracking-[0.2em] text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-3 py-1 rounded-full inline-block mb-2 shadow-sm">
                Welcome CTC Client
            </span>
            <h1 class="text-xl font-black text-slate-900 tracking-tight uppercase">CLIENT PORTAL ACCESS</h1>
            <p class="text-xs text-slate-500 mt-1 font-bold">PT. Cipta Teknologi Cendekia</p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="mb-5 p-4 bg-red-50/90 border border-red-200 rounded-2xl text-red-700 text-xs flex items-start gap-3 shadow-sm">
                <i class="fa-solid fa-circle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
                <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5 text-xs">
            <div>
                <label class="block font-black text-slate-700 uppercase tracking-wider mb-2 text-[10px]">
                    EMAIL RESMI KLIEN / PERUSAHAAN
                </label>
                <div class="relative">
                    <input type="email" name="email" required placeholder="nama@perusahaan.com" 
                           class="input-glow w-full px-4 py-3.5 pl-11 rounded-2xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                    <i class="fa-solid fa-envelope absolute left-4 top-4 text-slate-400 text-sm"></i>
                </div>
                <span class="text-[11px] text-slate-500 block mt-2 font-medium">
                    <i class="fa-solid fa-lock text-emerald-600 mr-1"></i> Kode OTP 6-digit akan dikirimkan langsung ke inbox email Anda.
                </span>
            </div>

            <button type="submit" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl shadow-lg shadow-emerald-600/30 text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                <i class="fa-solid fa-paper-plane text-sm"></i> KIRIM KODE OTP
            </button>
        </form>

        <div class="mt-8 pt-5 border-t border-slate-200/60 text-center text-xs text-slate-500 font-medium">
            Belum mengajukan proyek? <a href="request.php" class="text-emerald-700 font-bold hover:underline">Kirim Requirement Baru</a>
        </div>
    </div>

    <div class="absolute bottom-4 text-center text-[10px] text-slate-400 font-bold font-mono">
        &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Client Portal.
    </div>

</body>
</html>