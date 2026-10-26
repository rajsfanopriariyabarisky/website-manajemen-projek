<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Auto-Generate Nomor Invoice (e.g. INV/CTC/2026/10/001)
$currentYear  = date('Y');
$currentMonth = date('m');

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = ?");
$stmtCount->execute([$currentYear]);
$nextNo = $stmtCount->fetchColumn() + 1;

$autoNomorInvoice = "INV/CTC/" . $currentYear . "/" . $currentMonth . "/" . sprintf("%03d", $nextNo);

$error   = '';
$success = '';

// Handle Tambah Invoice Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_invoice'])) {
    $project_id        = $_POST['project_id'];
    $nomor_invoice     = trim($_POST['nomor_invoice']);
    $termin_ke         = (int)$_POST['termin_ke'];
    $keterangan_termin = trim($_POST['keterangan_termin']);
    $jumlah_tagihan    = str_replace(['.', ','], '', $_POST['jumlah_tagihan']);
    $tanggal_tagihan   = $_POST['tanggal_tagihan'];
    $jatuh_tempo       = $_POST['jatuh_tempo'];
    $status            = $_POST['status'];

    if (!empty($project_id) && !empty($nomor_invoice) && !empty($jumlah_tagihan)) {
        
        // Validasi Prevent Duplicate Termin
        $stmtCheckTermin = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE project_id = ? AND termin_ke = ?");
        $stmtCheckTermin->execute([$project_id, $termin_ke]);
        $hasTermin = $stmtCheckTermin->fetchColumn();

        if ($hasTermin > 0) {
            $error = "Gagal! Invoice untuk Termin " . $termin_ke . " pada proyek ini sudah pernah diterbitkan. Silakan pilih termin selanjutnya!";
        } else {
            // Cek Keamanan Server-side: Apakah proyek ini sudah lunas 100%?
            $stmtCheck = $pdo->prepare("
                SELECT p.budget, COALESCE(SUM(i.jumlah_tagihan), 0) AS total_paid
                FROM projects p
                LEFT JOIN invoices i ON p.id = i.project_id AND i.status = 'Paid'
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmtCheck->execute([$project_id]);
            $checkData = $stmtCheck->fetch();

            if ($checkData && $checkData['total_paid'] >= $checkData['budget']) {
                $error = "Gagal! Proyek ini sudah lunas 100% dan tidak dapat diterbitkan invoice baru lagi.";
            } else {
                $stmtInsert = $pdo->prepare("
                    INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtInsert->execute([$project_id, $nomor_invoice, $termin_ke, $keterangan_termin, $jumlah_tagihan, $tanggal_tagihan, $jatuh_tempo, $status]);
                
                header("Location: index.php?msg=created");
                exit;
            }
        }
    } else {
        $error = "Mohon lengkapi seluruh kolom wajib!";
    }
}

// Params Filter & Pagination
$search_query   = trim($_GET['search'] ?? '');
$filter_status  = $_GET['status_filter'] ?? 'All';
$filter_termin  = $_GET['termin_filter'] ?? 'All';
$page           = max(1, (int)($_GET['page'] ?? 1));
$limit          = 7;
$offset         = ($page - 1) * $limit;

// Fetch Proyek Available
$sqlAvailableProjects = "
    SELECT p.id, p.nama_projek, p.klien, p.budget,
           COALESCE(SUM(CASE WHEN i.status = 'Paid' THEN i.jumlah_tagihan ELSE 0 END), 0) AS total_paid,
           COUNT(i.id) AS total_invoices_count,
           GROUP_CONCAT(i.termin_ke) AS existing_termins
    FROM projects p
    LEFT JOIN invoices i ON p.id = i.project_id
    GROUP BY p.id
    HAVING total_paid < p.budget OR total_invoices_count = 0
    ORDER BY p.nama_projek ASC
";
$projectsAvailable = $pdo->query($sqlAvailableProjects)->fetchAll();

// Query Base Invoices
$sqlBase = "
    FROM invoices i
    JOIN projects p ON i.project_id = p.id
    WHERE 1=1
";
$params = [];

if (!empty($search_query)) {
    $sqlBase .= " AND (i.nomor_invoice LIKE ? OR p.nama_projek LIKE ? OR p.klien LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

if ($filter_status !== 'All') {
    $sqlBase .= " AND i.status = ?";
    $params[] = $filter_status;
}

if ($filter_termin !== 'All') {
    $sqlBase .= " AND i.termin_ke = ?";
    $params[] = (int)$filter_termin;
}

// Total Data
$stmtCountInv = $pdo->prepare("SELECT COUNT(*) " . $sqlBase);
$stmtCountInv->execute($params);
$totalRows  = $stmtCountInv->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

// Fetch Paginasi
$sqlPaged = "
    SELECT i.*, p.nama_projek, p.klien, p.budget,
           (SELECT COALESCE(SUM(inv.jumlah_tagihan), 0) FROM invoices inv WHERE inv.project_id = p.id AND inv.status = 'Paid') AS total_project_paid,
           (SELECT MAX(inv2.termin_ke) FROM invoices inv2 WHERE inv2.project_id = p.id) AS max_termin_project
    " . $sqlBase . " 
    ORDER BY i.created_at DESC LIMIT $limit OFFSET $offset
";
$stmtInvoices = $pdo->prepare($sqlPaged);
$stmtInvoices->execute($params);
$invoices = $stmtInvoices->fetchAll();

// Stats Summary
$totalInvoiced = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices")->fetchColumn();
$totalPaid     = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE status = 'Paid'")->fetchColumn();
$totalUnpaid   = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE status != 'Paid'")->fetchColumn();

// Data Rekap PDF
$allInvoicesPrint = $pdo->query("
    SELECT i.*, p.nama_projek, p.klien 
    FROM invoices i
    JOIN projects p ON i.project_id = p.id
    ORDER BY i.created_at DESC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6, textarea { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }

    .btn-press { 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    .btn-press:active { 
        transform: translateY(1px); 
    }

    .input-glow { 
        transition: box-shadow .2s ease, border-color .2s ease; 
    }
    .input-glow:focus { 
        border-color: #059669; 
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
    }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    #print-area-pdf { display: none; }

    @media print {
        body > * { display: none !important; }
        #print-area-pdf {
            display: block !important;
            position: absolute !important;
            left: 0 !important; top: 0 !important;
            width: 100% !important; margin: 0 !important; padding: 0 !important;
            background: #ffffff !important; color: #0f172a !important;
            font-family: 'Segoe UI', Arial, sans-serif !important;
            -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;
        }
        @page { size: A4 portrait; margin: 10mm 12mm 10mm 12mm; }
        .print-header-pdf { width: 100% !important; margin-bottom: 15px !important; padding-bottom: 8px !important; border-bottom: 3px double #047857 !important; }
        .print-logo-img { width: 52px !important; height: 52px !important; object-fit: cover !important; border-radius: 6px !important; }
        .pdf-table { width: 100% !important; border-collapse: collapse !important; font-size: 9.5px !important; margin-bottom: 15px !important; }
        .pdf-table th { background-color: #065f46 !important; color: #ffffff !important; font-weight: 800 !important; text-transform: uppercase !important; font-size: 8.5px !important; padding: 6px 6px !important; border: 1px solid #065f46 !important; }
        .pdf-table td { border: 1px solid #cbd5e1 !important; padding: 6px 7px !important; color: #1e293b !important; }
        .pdf-badge { border: 1px solid #047857 !important; background: #ecfdf5 !important; padding: 1px 5px !important; font-weight: 800 !important; font-size: 7.5px !important; text-transform: uppercase !important; white-space: nowrap !important; color: #047857 !important; border-radius: 3px !important; }
    }
</style>

<!-- PDF Print Template Area -->
<div id="print-area-pdf">
    <div class="print-header-pdf">
        <div style="display: flex; justify-between: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="/website%20manajemen%20projek/assets/images/logo.jpg" alt="Logo PT CTC" class="print-logo-img">
                <div>
                    <h1 style="font-size: 13pt; font-weight: 900; color: #0f172a; margin: 0; text-transform: uppercase;">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p style="font-size: 8.5pt; font-weight: 700; color: #047857; margin: 2px 0 0 0;">Enterprise IT Solution, Software Development & Financial Control</p>
                    <p style="font-size: 7.5pt; color: #64748b; margin: 2px 0 0 0;">Perumahan Cahaya Garuda Residence Kav. 31, Bojongsari, Kota Depok, Jawa Barat |</p>
                    <p style="font-size: 7.5pt; color: #64748b; margin: 1px 0 0 0;">Email: ciptateknologicendekia@gmail.com | Website: https://ctc-nusantara.com/</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 8pt; color: #475569;">
                <span style="font-weight: 900; color: #047857; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; text-transform: uppercase;">BILLING & INVOICE RECAP</span>
                <p style="margin-top: 4px;">Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; padding-top: 6px;">
            <h2 style="font-size: 10pt; font-weight: 900; text-transform: uppercase; color: #0f172a; border-bottom: 2px solid #0f172a; display: inline-block; padding-bottom: 2px; margin: 0;">
                REKAPITULASI PENAGIHAN & STATUS INVOICE KLIEN
            </h2>
        </div>
    </div>

    <table class="pdf-table">
        <thead>
            <tr>
                <th>No. Invoice</th>
                <th>Proyek & Klien</th>
                <th>Termin</th>
                <th style="text-align: right;">Jumlah Tagihan</th>
                <th>Jatuh Tempo</th>
                <th style="text-align: center;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($allInvoicesPrint as $invPrint): ?>
                <tr>
                    <td style="font-weight: bold; font-family: monospace;"><?= htmlspecialchars($invPrint['nomor_invoice']) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($invPrint['nama_projek']) ?></strong>
                        <span style="display: block; font-size: 8pt; color: #64748b;"><?= htmlspecialchars($invPrint['klien']) ?></span>
                    </td>
                    <td>Termin <?= $invPrint['termin_ke'] ?> (<?= htmlspecialchars($invPrint['keterangan_termin']) ?>)</td>
                    <td style="text-align: right; font-weight: bold;">Rp <?= number_format($invPrint['jumlah_tagihan'], 0, ',', '.') ?></td>
                    <td><?= date('d M Y', strtotime($invPrint['jatuh_tempo'])) ?></td>
                    <td style="text-align: center;"><span class="pdf-badge"><?= $invPrint['status'] ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white relative">
    
    <!-- Top Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL BILLING SYSTEM • INVOICE & PENAGIHAN
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Invoice & Penagihan Klien</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Kelola tagihan termin, status penagihan, dan cetak kuitansi resmi PT. CTC.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap">
            <button onclick="window.print()" class="btn-press px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-print text-xs sm:text-sm"></i> Cetak Rekap PDF
            </button>
            <button onclick="openInvoiceModal()" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus-circle text-xs sm:text-sm"></i> Terbitkan Invoice Baru
            </button>
        </div>
    </div>

    <!-- Instruction Banner -->
    <div class="mb-6 bg-gradient-to-r from-emerald-900 to-teal-900 border border-emerald-700/80 p-4 rounded-2xl text-white shadow-xs">
        <div class="flex items-start gap-3.5">
            <div class="p-2.5 bg-emerald-500/20 rounded-xl text-emerald-300 border border-emerald-400/30 shrink-0">
                <i class="fa-solid fa-shield-halved text-xl"></i>
            </div>
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-300 flex items-center gap-2">
                    Instruksi Prosedur & Otomatisasi Ledger Keuangan
                </h4>
                <p class="text-xs text-emerald-100/90 leading-relaxed mt-1 font-normal">
                    <strong>Penting untuk Tim Finance & Billing:</strong> Pastikan untuk selalu melakukan pembaharuan status tagihan menjadi <span class="bg-emerald-500/30 text-white px-1.5 py-0.5 rounded border border-emerald-400/30 font-bold">Paid (Lunas)</span> segera setelah pembayaran termin diverifikasi masuk. Kuitansi Pelunasan BAST Gabungan 100% otomatis aktif khusus pada baris termin terakhir jika seluruh termin telah dilunasi.
                </p>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> Invoice penagihan baru berhasil diterbitkan!
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Stat Counter Cards Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Tagihan Diterbitkan</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($totalInvoiced, 0, ',', '.') ?></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Akumulasi seluruh termin</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Sudah Dilunasi (Paid)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($totalPaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Masuk ke kas perusahaan</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-amber-200 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Piutang / Unpaid</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($totalUnpaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Menunggu pembayaran klien</span>
        </div>
    </div>

    <!-- Filter & Search Bar Integrated -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <form method="GET" class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            
            <div class="relative w-full md:w-80">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari no. invoice, proyek, atau klien..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                    <a href="?status_filter=All&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'All' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Semua</a>
                    <a href="?status_filter=Unpaid&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'Unpaid' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Unpaid</a>
                    <a href="?status_filter=Paid&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'Paid' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Paid</a>
                </div>

                <select name="termin_filter" onchange="this.form.submit()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold bg-white text-slate-700 focus:outline-none">
                    <option value="All" <?= $filter_termin === 'All' ? 'selected' : '' ?>>Semua Termin</option>
                    <option value="1" <?= $filter_termin === '1' ? 'selected' : '' ?>>Termin 1 (DP 50%)</option>
                    <option value="2" <?= $filter_termin === '2' ? 'selected' : '' ?>>Termin 2 (Pelunasan)</option>
                    <option value="3" <?= $filter_termin === '3' ? 'selected' : '' ?>>Full Payment (100%)</option>
                </select>

                <button type="submit" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs">
                    Cari
                </button>
            </div>
        </form>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-list-check text-emerald-600"></i> Daftar Tagihan & Monitoring Termin
        </h3>

        <!-- Desktop View (Tabel) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[750px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">NO. INVOICE</th>
                        <th class="py-3.5 px-4">PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4">STATUS TERMIN</th>
                        <th class="py-3.5 px-4 text-right">JUMLAH TAGIHAN</th>
                        <th class="py-3.5 px-4">JATUH TEMPO</th>
                        <th class="py-3.5 px-4 text-center">STATUS BAYAR</th>
                        <th class="py-3.5 px-4 text-center">AKSI / DOKUMEN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </div>
                                Belum ada data invoice yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $isOverdue = (strtotime($inv['jatuh_tempo']) < time()) && $inv['status'] !== 'Paid';
                            
                            // SYARAT KHUSUS MUNCUL TOMBOL KUITANSI PELUNASAN GABUNGAN 100%:
                            $canPrintPelunasan = ($inv['total_project_paid'] >= $inv['budget']) 
                                              && ($inv['termin_ke'] == $inv['max_termin_project']) 
                                              && ($inv['status'] === 'Paid');
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 whitespace-nowrap"><?= htmlspecialchars($inv['nomor_invoice']) ?></td>
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-tight mb-0.5"><?= htmlspecialchars($inv['nama_projek']) ?></p>
                                    <span class="text-xs text-slate-400 font-normal"><?= htmlspecialchars($inv['klien']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700">
                                    <span class="font-semibold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200 inline-block text-[10px] uppercase tracking-wider mb-0.5">
                                        Termin <?= $inv['termin_ke'] ?>
                                    </span>
                                    <span class="block text-xs text-slate-400 font-normal"><?= htmlspecialchars($inv['keterangan_termin']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-900 font-mono whitespace-nowrap">
                                    Rp <?= number_format($inv['jumlah_tagihan'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 whitespace-nowrap font-normal">
                                    <?= date('d M Y', strtotime($inv['jatuh_tempo'])) ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="block text-[9px] font-bold uppercase tracking-wider text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200 mt-0.5 w-max">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <form action="update_status.php" method="POST" class="inline-block">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <select name="status" onchange="this.form.submit()" class="input-glow px-2.5 py-1 rounded-lg text-xs font-semibold border cursor-pointer focus:outline-none shadow-2xs
                                            <?php
                                                switch($inv['status']) {
                                                    case 'Paid': echo 'bg-emerald-50 text-emerald-700 border-emerald-300'; break;
                                                    case 'Partially Paid': echo 'bg-blue-50 text-blue-700 border-blue-300'; break;
                                                    default: echo 'bg-amber-50 text-amber-700 border-amber-300';
                                                }
                                            ?>">
                                            <option value="Unpaid" <?= $inv['status'] === 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
                                            <option value="Partially Paid" <?= $inv['status'] === 'Partially Paid' ? 'selected' : '' ?>>Partially Paid</option>
                                            <option value="Paid" <?= $inv['status'] === 'Paid' ? 'selected' : '' ?>>Paid (Lunas)</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <!-- PDF STANDAR PER TERMIN -->
                                        <a href="print_pdf.php?id=<?= $inv['id'] ?>" target="_blank" title="Cetak Invoice Termin Ini" class="btn-press px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition inline-flex items-center gap-1 shadow-xs">
                                            <i class="fa-solid fa-print text-xs"></i> PDF
                                        </a>

                                        <!-- KUITANSI PELUNASAN GABUNGAN 100% -->
                                        <?php if ($canPrintPelunasan): ?>
                                            <a href="print_pdf.php?project_id=<?= $inv['project_id'] ?>&type=pelunasan" target="_blank" title="Proyek Lunas 100%! Cetak Kuitansi Pelunasan BAST Gabungan" class="btn-press px-2.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition inline-flex items-center gap-1 shadow-xs">
                                                <i class="fa-solid fa-certificate text-xs"></i> Kuitansi Pelunasan
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="md:hidden space-y-3">
            <?php if (!empty($invoices)): ?>
                <?php foreach ($invoices as $inv): 
                    $canPrintPelunasan = ($inv['total_project_paid'] >= $inv['budget']) 
                                      && ($inv['termin_ke'] == $inv['max_termin_project']) 
                                      && ($inv['status'] === 'Paid');
                ?>
                    <div class="p-4 border border-slate-200/90 rounded-xl bg-slate-50/50 space-y-3">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <span class="font-mono text-xs font-bold text-emerald-700 block mb-0.5"><?= htmlspecialchars($inv['nomor_invoice']) ?></span>
                                <h4 class="font-bold text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($inv['nama_projek']) ?></h4>
                                <p class="text-xs text-slate-500 font-normal">Klien: <?= htmlspecialchars($inv['klien']) ?></p>
                            </div>
                        </div>

                        <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-xs">
                            <div>
                                <span class="text-slate-400 block font-normal">Tagihan (Termin <?= $inv['termin_ke'] ?>):</span>
                                <span class="font-bold text-slate-900 font-mono">Rp <?= number_format($inv['jumlah_tagihan'], 0, ',', '.') ?></span>
                            </div>
                            <div class="flex gap-1.5">
                                <a href="print_pdf.php?id=<?= $inv['id'] ?>" target="_blank" class="btn-press px-2.5 py-1 rounded-lg bg-slate-900 text-white text-xs font-semibold shadow-2xs">
                                    <i class="fa-solid fa-print text-[10px]"></i> PDF
                                </a>
                                <?php if ($canPrintPelunasan): ?>
                                    <a href="print_pdf.php?project_id=<?= $inv['project_id'] ?>&type=pelunasan" target="_blank" class="btn-press px-2.5 py-1 rounded-lg bg-emerald-600 text-white text-xs font-semibold shadow-2xs">
                                        Lunas
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- FITUR PAGINASI NUMERIK -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500">
                <span class="font-medium">
                    Menampilkan Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong> (Total <?= $totalRows ?> Invoice)
                </span>

                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_query) ?>&status_filter=<?= urlencode($filter_status) ?>&termin_filter=<?= urlencode($filter_termin) ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&status_filter=<?= urlencode($filter_status) ?>&termin_filter=<?= urlencode($filter_termin) ?>" class="btn-press px-3 py-1.5 rounded-xl border text-xs font-semibold transition <?= $page == $i ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_query) ?>&status_filter=<?= urlencode($filter_status) ?>&termin_filter=<?= urlencode($filter_termin) ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            Next <i class="fa-solid fa-chevron-right ml-1 text-[10px]"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            Next <i class="fa-solid fa-chevron-right ml-1 text-[10px]"></i>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Modal Pop-up Terbit Invoice Baru -->
<div id="invoiceModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 p-6 pb-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-emerald-600"></i> Terbitkan Invoice Baru
            </h3>
            <button onclick="closeInvoiceModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 pt-2 space-y-3.5">
            <input type="hidden" name="add_invoice" value="1">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nomor Invoice Auto</label>
                <input type="text" name="nomor_invoice" readonly value="<?= $autoNomorInvoice ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono font-bold bg-slate-50 text-emerald-800 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Proyek Acuan <span class="text-red-500">*</span></label>
                <select name="project_id" id="project_select" onchange="autoFillInvoice()" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <?php if (empty($projectsAvailable)): ?>
                        <option value="">-- Semua Proyek Sudah Lunas 100% --</option>
                    <?php else: ?>
                        <option value="">-- Pilih Proyek Target --</option>
                        <?php foreach ($projectsAvailable as $p): ?>
                            <option value="<?= $p['id'] ?>" data-budget="<?= $p['budget'] ?>" data-existing-termins="<?= $p['existing_termins'] ?: '' ?>">
                                <?= htmlspecialchars($p['nama_projek']) ?> (Klien: <?= htmlspecialchars($p['klien']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Termin Ke <span class="text-red-500">*</span></label>
                    <select name="termin_ke" id="termin_select" onchange="calcTermin()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                        <option value="1" id="opt_termin_1">Termin 1 (DP 50%)</option>
                        <option value="2" id="opt_termin_2">Termin 2 (Pelunasan 50%)</option>
                        <option value="3" id="opt_termin_3">Full Payment (100%)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Keterangan Termin <span class="text-red-500">*</span></label>
                    <input type="text" name="keterangan_termin" id="keterangan_termin" required value="DP 50% Penandatanganan SPK" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Jumlah Tagihan (Rp) <span class="text-red-500">*</span></label>
                <input type="text" name="jumlah_tagihan" id="jumlah_tagihan" required onkeyup="formatRupiah(this)" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Tagihan <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_tagihan" required value="<?= date('Y-m-d') ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Jatuh Tempo <span class="text-red-500">*</span></label>
                    <input type="date" name="jatuh_tempo" required value="<?= date('Y-m-d', strtotime('+14 days')) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Status Awal</label>
                <select name="status" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Unpaid" selected>Unpaid (Belum Dibayar)</option>
                    <option value="Partially Paid">Partially Paid</option>
                    <option value="Paid">Paid (Lunas)</option>
                </select>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeInvoiceModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Terbitkan Tagihan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openInvoiceModal() { document.getElementById('invoiceModal').classList.remove('hidden'); }
function closeInvoiceModal() { document.getElementById('invoiceModal').classList.add('hidden'); }

let currentBudget = 0;

function autoFillInvoice() {
    const select = document.getElementById('project_select');
    const selectedOption = select.options[select.selectedIndex];
    const terminSelect = document.getElementById('termin_select');
    
    const opt1 = document.getElementById('opt_termin_1');
    const opt2 = document.getElementById('opt_termin_2');
    const opt3 = document.getElementById('opt_termin_3');

    opt1.disabled = false; opt1.innerText = "Termin 1 (DP 50%)";
    opt2.disabled = false; opt2.innerText = "Termin 2 (Pelunasan 50%)";
    opt3.disabled = false; opt3.innerText = "Full Payment (100%)";

    if (selectedOption.value !== '') {
        currentBudget = parseFloat(selectedOption.getAttribute('data-budget')) || 0;
        const existingTerminsStr = selectedOption.getAttribute('data-existing-termins') || '';
        const existingTermins = existingTerminsStr.split(',').map(item => item.trim());

        if (existingTermins.includes('1')) {
            opt1.disabled = true;
            opt1.innerText = "Termin 1 (Sudah Diterbitkan 🔒)";
        }
        if (existingTermins.includes('2')) {
            opt2.disabled = true;
            opt2.innerText = "Termin 2 (Sudah Diterbitkan 🔒)";
        }
        if (existingTermins.includes('3') || existingTermins.length > 0) {
            opt3.disabled = true;
            opt3.innerText = "Full Payment (Sudah Ada Termin 🔒)";
        }

        for (let i = 0; i < terminSelect.options.length; i++) {
            if (!terminSelect.options[i].disabled) {
                terminSelect.selectedIndex = i;
                break;
            }
        }

        calcTermin();
    }
}

function calcTermin() {
    const termin = document.getElementById('termin_select').value;
    const ketInput = document.getElementById('keterangan_termin');
    const tagihanInput = document.getElementById('jumlah_tagihan');
    
    let tagihan = 0;
    if (termin === '1') {
        ketInput.value = 'DP 50% Penandatanganan SPK';
        tagihan = currentBudget * 0.5;
    } else if (termin === '2') {
        ketInput.value = 'Pelunasan 50% BAST Selesai';
        tagihan = currentBudget * 0.5;
    } else {
        ketInput.value = 'Pelunasan 100% Pembayaran Penuh';
        tagihan = currentBudget;
    }
    
    tagihanInput.value = new Intl.NumberFormat('id-ID').format(tagihan);
}

function formatRupiah(el) {
    let value = el.value.replace(/[^0-9]/g, '');
    el.value = value ? new Intl.NumberFormat('id-ID').format(value) : '';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>