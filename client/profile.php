<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

// Proteksi Sesi Login Client
if (
    !isset($_SESSION['client_logged_in']) ||
    $_SESSION['client_logged_in'] !== true ||
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id    = intval($_SESSION['client_user_id']);
$client_nama  = $_SESSION['client_nama'] ?? 'Klien';
$client_email = $_SESSION['client_email'] ?? '';

// Fetch Data Detail Klien dari Database
$stmtClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmtClient->execute([$client_id]);
$client_db = $stmtClient->fetch();

if (!$client_db) {
    header('Location: login.php');
    exit;
}

// Fetch Data Nomor HP & Nama Perusahaan dari client_requirements
$stmtReq = $pdo->prepare("SELECT client_phone, company_name FROM client_requirements WHERE client_id = ? OR LOWER(client_email) = LOWER(?) ORDER BY id DESC LIMIT 1");
$stmtReq->execute([$client_id, $client_email]);
$req_data = $stmtReq->fetch();

$client_phone = !empty($req_data['client_phone']) ? $req_data['client_phone'] : '-';
$company_name = !empty($req_data['company_name']) ? $req_data['company_name'] : '-';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Akun Klien - PT. Cipta Teknologi Cendekia</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-press:active { transform: translateY(1px); }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white flex flex-col">

<!-- Top Navigation Header -->
<header class="bg-white border-b border-slate-200/90 px-4 sm:px-8 py-3 flex items-center justify-between sticky top-0 z-30 shadow-xs">
    <div class="flex items-center gap-3 min-w-0">
        <a href="portal.php" class="btn-press w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0" title="Kembali ke Portal">
            <i class="fa-solid fa-arrow-left text-xs"></i>
        </a>
        <img src="../assets/images/logo.png" alt="Logo CTC" class="h-9 sm:h-10 w-auto object-contain shrink-0">

        <div class="min-w-0 border-l border-slate-200 pl-3">
            <h1 class="text-xs font-bold text-slate-900 uppercase tracking-wider">PROFIL KLIEN</h1>
            <p class="text-[11px] text-slate-500 font-medium truncate">PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <a href="portal.php?action=logout" class="btn-press px-3.5 py-1.5 bg-red-50 hover:bg-red-100/80 text-red-600 border border-red-200/80 rounded-xl text-xs font-semibold transition flex items-center gap-2">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="hidden sm:inline">Logout</span>
        </a>
    </div>
</header>

<!-- Main Profile Container -->
<main class="max-w-3xl w-full mx-auto p-4 sm:p-6 md:p-8 flex-1">

    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">

        <!-- Header Profile Banner -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between border-b border-slate-100 pb-5 gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl font-bold shadow-md shadow-emerald-600/20 shrink-0">
                    <i class="fa-solid fa-user-tie"></i>
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                        <?= htmlspecialchars($client_db['nama']) ?>
                    </h2>
                    <p class="text-xs text-slate-500 font-normal mt-0.5">
                        <?= htmlspecialchars($client_db['email']) ?>
                    </p>
                </div>
            </div>

            <span class="px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full text-xs font-semibold uppercase tracking-wider flex items-center gap-1.5">
                <i class="fa-solid fa-circle-check text-emerald-600"></i> Akun Terverifikasi
            </span>
        </div>

        <div class="bg-slate-50 border border-slate-200/80 p-4 rounded-xl text-xs text-slate-600 flex items-center gap-2.5">
            <i class="fa-solid fa-lock text-slate-400 text-base shrink-0"></i>
            <span>Informasi detail akun dikunci oleh sistem untuk menjaga privasi & validitas entitas klien.</span>
        </div>

        <!-- Readonly Information Fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs sm:text-sm">

            <!-- Nama Lengkap -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Lengkap</label>
                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($client_db['nama']) ?>
                </div>
            </div>

            <!-- Email Client -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Alamat Email</label>
                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none break-all">
                    <?= htmlspecialchars($client_db['email']) ?>
                </div>
            </div>

            <!-- Nomor Telepon -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nomor Telepon / WhatsApp</label>
                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($client_phone) ?>
                </div>
            </div>

            <!-- Nama Perusahaan -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Perusahaan / Instansi</label>
                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($company_name) ?>
                </div>
            </div>

            <!-- Tanggal Registrasi -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Terdaftar Sejak</label>
                <div class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <i class="fa-regular fa-calendar-check text-emerald-600 mr-2"></i>
                    <?= date('d F Y - H:i', strtotime($client_db['created_at'])) ?> WIB
                </div>
            </div>

        </div>

        <!-- Back Button -->
        <div class="border-t border-slate-100 pt-5 flex justify-end">
            <a href="portal.php" class="btn-press px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition shadow-xs flex items-center gap-2">
                <i class="fa-solid fa-arrow-left text-xs"></i>
                <span>Kembali ke Portal</span>
            </a>
        </div>

    </div>

</main>

</body>
</html>