<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

// Guard Schema: Auto Migration Kolom phone & company_name pada Tabel clients
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN phone VARCHAR(50) NULL AFTER email");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN company_name VARCHAR(255) NULL AFTER phone");
} catch (Exception $e) {}

// Proteksi Sesi Login Client
if (
    !isset($_SESSION['client_logged_in']) ||
    $_SESSION['client_logged_in'] !== true ||
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id = intval($_SESSION['client_user_id']);

// AJAX Endpoint: Real-Time Sync Profil Klien tanpa Reload Page
if (isset($_GET['ajax_sync']) && $_GET['ajax_sync'] == '1') {
    header('Content-Type: application/json');
    
    $stmtC = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
    $stmtC->execute([$client_id]);
    $cData = $stmtC->fetch();

    if (!$cData || (int)$cData['is_verified'] !== 1) {
        unset(
            $_SESSION['client_logged_in'],
            $_SESSION['client_user_id'],
            $_SESSION['client_nama'],
            $_SESSION['client_email'],
            $_SESSION['client_token']
        );
        echo json_encode(['success' => false, 'logged_out' => true]);
        exit;
    }

    // Auto Sync Session
    $_SESSION['client_nama']  = $cData['nama'];
    $_SESSION['client_email'] = $cData['email'];

    $phone_val   = !empty($cData['phone']) ? $cData['phone'] : '';
    $company_val = !empty($cData['company_name']) ? $cData['company_name'] : '';

    if (empty($phone_val) || empty($company_val)) {
        $stmtReq = $pdo->prepare("SELECT client_phone, company_name FROM client_requirements WHERE client_id = ? OR LOWER(client_email) = LOWER(?) ORDER BY id DESC LIMIT 1");
        $stmtReq->execute([$client_id, $cData['email']]);
        $req_data = $stmtReq->fetch();

        if (empty($phone_val) && !empty($req_data['client_phone'])) $phone_val = $req_data['client_phone'];
        if (empty($company_val) && !empty($req_data['company_name'])) $company_val = $req_data['company_name'];
    }

    echo json_encode([
        'success'      => true,
        'nama'         => $cData['nama'],
        'email'        => $cData['email'],
        'phone'        => !empty($phone_val) ? $phone_val : '-',
        'company_name' => !empty($company_val) ? $company_val : '-',
        'created_at'   => date('d F Y - H:i', strtotime($cData['created_at'])) . ' WIB'
    ]);
    exit;
}

// Fetch Data Detail Klien dari Database Master clients
$stmtClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmtClient->execute([$client_id]);
$client_db = $stmtClient->fetch();

if (!$client_db || (int)$client_db['is_verified'] !== 1) {
    unset(
        $_SESSION['client_logged_in'],
        $_SESSION['client_user_id'],
        $_SESSION['client_nama'],
        $_SESSION['client_email'],
        $_SESSION['client_token']
    );
    header('Location: login.php');
    exit;
}

// Update Sesi
$client_nama  = $client_db['nama'];
$client_email = $client_db['email'];
$_SESSION['client_nama']  = $client_nama;
$_SESSION['client_email'] = $client_email;

// Prioritas Utama: Ambil dari Master Tabel clients
$client_phone = !empty($client_db['phone']) ? $client_db['phone'] : '';
$company_name = !empty($client_db['company_name']) ? $client_db['company_name'] : '';

// Fallback jika data master lama belum terisi: Ambil dari riwayat client_requirements
if (empty($client_phone) || empty($company_name)) {
    $stmtReq = $pdo->prepare("SELECT client_phone, company_name FROM client_requirements WHERE client_id = ? OR LOWER(client_email) = LOWER(?) ORDER BY id DESC LIMIT 1");
    $stmtReq->execute([$client_id, $client_email]);
    $req_data = $stmtReq->fetch();

    if (empty($client_phone) && !empty($req_data['client_phone'])) {
        $client_phone = $req_data['client_phone'];
    }
    if (empty($company_name) && !empty($req_data['company_name'])) {
        $company_name = $req_data['company_name'];
    }
}

$client_phone = !empty($client_phone) ? $client_phone : '-';
$company_name = !empty($company_name) ? $company_name : '-';
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
                    <h2 id="profNamaHeader" class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight">
                        <?= htmlspecialchars($client_db['nama']) ?>
                    </h2>
                    <p id="profEmailHeader" class="text-xs text-slate-500 font-normal mt-0.5">
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

        <!-- Readonly Information Fields (Auto Real-Time Sync) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 text-xs sm:text-sm">

            <!-- Nama Lengkap -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Lengkap</label>
                <div id="profNamaInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($client_db['nama']) ?>
                </div>
            </div>

            <!-- Email Client -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Alamat Email</label>
                <div id="profEmailInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none break-all">
                    <?= htmlspecialchars($client_db['email']) ?>
                </div>
            </div>

            <!-- Nomor Telepon -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nomor Telepon / WhatsApp</label>
                <div id="profPhoneInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($client_phone) ?>
                </div>
            </div>

            <!-- Nama Perusahaan -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Nama Perusahaan / Instansi</label>
                <div id="profCompanyInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
                    <?= htmlspecialchars($company_name) ?>
                </div>
            </div>

            <!-- Tanggal Registrasi -->
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Terdaftar Sejak</label>
                <div id="profCreatedAtInput" class="w-full px-4 py-3 rounded-xl border border-slate-200 bg-slate-50 text-slate-900 font-semibold cursor-not-allowed select-none">
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

<script>
    function fetchProfileSync() {
        fetch('profile.php?ajax_sync=1', { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (data && data.logged_out) {
                    window.location.href = 'login.php';
                    return;
                }

                if (data && data.success) {
                    const eHeaderNama = document.getElementById('profNamaHeader');
                    const eHeaderEmail = document.getElementById('profEmailHeader');
                    const eNama = document.getElementById('profNamaInput');
                    const eEmail = document.getElementById('profEmailInput');
                    const ePhone = document.getElementById('profPhoneInput');
                    const eCompany = document.getElementById('profCompanyInput');
                    const eCreated = document.getElementById('profCreatedAtInput');

                    if (eHeaderNama) eHeaderNama.innerText = data.nama;
                    if (eHeaderEmail) eHeaderEmail.innerText = data.email;
                    if (eNama) eNama.innerText = data.nama;
                    if (eEmail) eEmail.innerText = data.email;
                    if (ePhone) ePhone.innerText = data.phone;
                    if (eCompany) eCompany.innerText = data.company_name;
                    if (eCreated) eCreated.innerHTML = `<i class="fa-regular fa-calendar-check text-emerald-600 mr-2"></i> ${data.created_at}`;
                }
            })
            .catch(err => console.error('fetchProfileSync:', err));
    }

    document.addEventListener('DOMContentLoaded', function() {
        fetchProfileSync();
        setInterval(fetchProfileSync, 2000);
    });
</script>

</body>
</html>