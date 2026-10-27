<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$project_id = $_GET['project_id'] ?? $_GET['id'] ?? null;

if (!$project_id) {
    header("Location: index.php");
    exit;
}

// Fetch Data Proyek
$stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmtP->execute([$project_id]);
$project = $stmtP->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

$error   = '';
$success = '';

// Auto-Generate Nomor Invoice
function getNextInvoiceNumber($pdo) {
    $currentYear  = date('Y');
    $currentMonth = date('m');
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at) = ?");
    $stmtCount->execute([$currentYear]);
    $nextNo = $stmtCount->fetchColumn() + 1;
    return "INV/CTC/" . $currentYear . "/" . $currentMonth . "/" . sprintf("%03d", $nextNo);
}

// Fetch All Existing Invoices for this Project
$stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY termin_ke ASC");
$stmtInv->execute([$project_id]);
$invoices = $stmtInv->fetchAll();

// DETEKSI SKEMA TERMIN TERKUNCI (Anti-Bentrok)
$locked_scheme = null; // null = bebas memilih, 'normal' = 2 termin, 'alternatif' = 3 termin
if (!empty($invoices)) {
    foreach ($invoices as $inv) {
        if ((int)$inv['termin_ke'] === 3) {
            $locked_scheme = 'alternatif';
            break;
        }
    }

    if (!$locked_scheme) {
        foreach ($invoices as $inv) {
            $ratio = $project['budget'] > 0 ? ($inv['jumlah_tagihan'] / $project['budget']) : 0;
            if (abs($ratio - 0.3) < 0.05 || abs($ratio - 0.2) < 0.05 || strpos(strtolower($inv['keterangan_termin']), '30%') !== false || strpos(strtolower($inv['keterangan_termin']), '20%') !== false) {
                $locked_scheme = 'alternatif';
                break;
            } elseif (abs($ratio - 0.5) < 0.05 || strpos(strtolower($inv['keterangan_termin']), '50%') !== false) {
                $locked_scheme = 'normal';
                break;
            }
        }
    }

    if (!$locked_scheme) {
        $locked_scheme = 'normal';
    }
}

// Handle 1-Click Batch Generate Semua Termin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type']) && $_POST['action_type'] === 'generate_all_scheme') {
    $chosen_scheme = $_POST['scheme_choice'] ?? 'normal';

    if (!empty($invoices)) {
        $error = "Gagal! Proyek ini sudah memiliki invoice termin yang diterbitkan.";
    } else {
        $budget       = (float)$project['budget'];
        $startDate    = !empty($project['tanggal_mulai']) && $project['tanggal_mulai'] !== '0000-00-00' ? $project['tanggal_mulai'] : date('Y-m-d');
        $deadlineDate = !empty($project['deadline']) && $project['deadline'] !== '0000-00-00' ? $project['deadline'] : date('Y-m-d', strtotime('+30 days'));

        if ($chosen_scheme === 'normal') {
            // Termin 1 (50%) & Termin 2 (50%)
            $t1_nomor  = getNextInvoiceNumber($pdo);
            $t1_amount = round($budget * 0.50, 2);
            $t1_due    = date('Y-m-d', strtotime($startDate . ' +7 days'));

            $stmtIns = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, 1, 'Termin 1 (DP 50% Penandatanganan SPK)', ?, ?, ?, 'Unpaid')");
            $stmtIns->execute([$project_id, $t1_nomor, $t1_amount, $startDate, $t1_due]);

            $t2_nomor  = getNextInvoiceNumber($pdo);
            $t2_amount = round($budget * 0.50, 2);

            $stmtIns2 = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, 2, 'Termin 2 (Pelunasan 50% BAST Selesai)', ?, ?, ?, 'Unpaid')");
            $stmtIns2->execute([$project_id, $t2_nomor, $t2_amount, $startDate, $deadlineDate]);

        } elseif ($chosen_scheme === 'alternatif') {
            // Termin 1 (30%), Termin 2 (20%), Termin 3 (50%)
            $t1_nomor  = getNextInvoiceNumber($pdo);
            $t1_amount = round($budget * 0.30, 2);
            $t1_due    = date('Y-m-d', strtotime($startDate . ' +7 days'));

            $stmtIns1 = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, 1, 'Termin 1 (DP 30% Penandatanganan SPK)', ?, ?, ?, 'Unpaid')");
            $stmtIns1->execute([$project_id, $t1_nomor, $t1_amount, $startDate, $t1_due]);

            $t2_nomor  = getNextInvoiceNumber($pdo);
            $t2_amount = round($budget * 0.20, 2);
            $t2_date   = date('Y-m-d', strtotime($startDate . ' +7 days'));
            $t2_due    = date('Y-m-d', strtotime($startDate . ' +14 days'));

            $stmtIns2 = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, 2, 'Termin 2 (Angsuran 20% Progress Pengerjaan)', ?, ?, ?, 'Unpaid')");
            $stmtIns2->execute([$project_id, $t2_nomor, $t2_amount, $t2_date, $t2_due]);

            $t3_nomor  = getNextInvoiceNumber($pdo);
            $t3_amount = round($budget * 0.50, 2);
            $t3_date   = date('Y-m-d', strtotime($startDate . ' +14 days'));

            $stmtIns3 = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, 3, 'Termin 3 (Pelunasan 50% BAST Selesai)', ?, ?, ?, 'Unpaid')");
            $stmtIns3->execute([$project_id, $t3_nomor, $t3_amount, $t3_date, $deadlineDate]);
        }

        header("Location: detail.php?project_id=" . $project_id . "&msg=generated");
        exit;
    }
}

// Handle Tambah Single Invoice Termin Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_single_invoice'])) {
    $termin_ke         = (int)$_POST['termin_ke'];
    $scheme_selected   = $_POST['scheme_selected'] ?? 'normal';
    $nomor_invoice     = trim($_POST['nomor_invoice']);
    $keterangan_termin = trim($_POST['keterangan_termin']);
    $jumlah_tagihan    = (float)str_replace(['.', ','], '', $_POST['jumlah_tagihan']);
    $tanggal_tagihan   = $_POST['tanggal_tagihan'];
    $jatuh_tempo       = $_POST['jatuh_tempo'];
    $status            = $_POST['status'];

    // Prevent cross-scheme clash
    if ($locked_scheme && $locked_scheme !== $scheme_selected) {
        $error = "Gagal! Proyek ini telah terkunci menggunakan Skema " . strtoupper($locked_scheme) . ". Tidak dapat mencampur dengan skema lain.";
    } else {
        // Check duplicate termin
        $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE project_id = ? AND termin_ke = ?");
        $stmtChk->execute([$project_id, $termin_ke]);
        if ($stmtChk->fetchColumn() > 0) {
            $error = "Gagal! Invoice untuk Termin " . $termin_ke . " sudah pernah diterbitkan.";
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO invoices (project_id, nomor_invoice, termin_ke, keterangan_termin, jumlah_tagihan, tanggal_tagihan, jatuh_tempo, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtIns->execute([$project_id, $nomor_invoice, $termin_ke, $keterangan_termin, $jumlah_tagihan, $tanggal_tagihan, $jatuh_tempo, $status]);

            header("Location: detail.php?project_id=" . $project_id . "&msg=created");
            exit;
        }
    }
}

// Handle Delete Single Invoice
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['inv_id'])) {
    $inv_id = (int)$_GET['inv_id'];
    $stmtDel = $pdo->prepare("DELETE FROM invoices WHERE id = ? AND project_id = ?");
    $stmtDel->execute([$inv_id, $project_id]);

    header("Location: detail.php?project_id=" . $project_id . "&msg=deleted");
    exit;
}

// Re-fetch Invoices & Calculate Project Specific Metrics
$stmtInv->execute([$project_id]);
$invoices = $stmtInv->fetchAll();

$total_budget   = (float)$project['budget'];
$total_invoiced = 0;
$total_paid     = 0;

foreach ($invoices as $inv) {
    $total_invoiced += (float)$inv['jumlah_tagihan'];
    if ($inv['status'] === 'Paid') {
        $total_paid += (float)$inv['jumlah_tagihan'];
    }
}

$total_unpaid = $total_budget - $total_paid;
if ($total_unpaid < 0) $total_unpaid = 0;

if ($total_budget > 0 && $total_paid >= $total_budget) {
    $project_payment_status = 'Lunas';
} elseif ($total_paid > 0) {
    $project_payment_status = 'Partially Paid';
} else {
    $project_payment_status = 'Unpaid';
}

$autoNomorInvoice = getNextInvoiceNumber($pdo);

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6, textarea { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 relative">

    <!-- Navigation Back & Header -->
    <div class="mb-4">
        <a href="index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Billing Proyek
        </a>
    </div>

    <!-- Header Banner Navigation -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border tracking-wider
                    <?php
                        switch($project_payment_status) {
                            case 'Lunas': echo 'bg-emerald-50 text-emerald-700 border-emerald-300'; break;
                            case 'Partially Paid': echo 'bg-blue-50 text-blue-700 border-blue-300'; break;
                            default: echo 'bg-amber-50 text-amber-700 border-amber-300';
                        }
                    ?>">
                    STATUS BILLING: <?= $project_payment_status === 'Lunas' ? 'LUNAS 100%' : $project_payment_status ?>
                </span>
                <?php if ($locked_scheme): ?>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-slate-100 text-slate-700 border border-slate-200 flex items-center gap-1">
                        <i class="fa-solid fa-lock text-[9px]"></i> Skema: <?= ucfirst($locked_scheme) ?>
                    </span>
                <?php endif; ?>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($project['nama_projek']) ?></h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Klien Mitra: <strong class="text-slate-800"><?= htmlspecialchars($project['klien']) ?></strong> | Batas Deadline: <strong><?= date('d M Y', strtotime($project['deadline'])) ?></strong></p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <?php if (empty($invoices)): ?>
                <button onclick="openGenerateAllModal()" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-xs"></i> 1-Click Generate Semua Termin
                </button>
            <?php else: ?>
                <button onclick="openInvoiceModal()" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center gap-2">
                    <i class="fa-solid fa-plus-circle text-xs"></i> Terbitkan Invoice Termin Baru
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> Invoice penagihan baru berhasil diterbitkan!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'generated'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> Seluruh invoice termin skema berhasil dibuat secara otomatis!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-trash-can text-red-500 text-base shrink-0"></i> Invoice berhasil dihapus dari sistem!
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <!-- Stat Counter Cards Proyek Ini -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Nilai Kontrak Proyek</span>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($total_budget, 0, ',', '.') ?></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Sesuai kesepakatan SPK</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">Total Sudah Dilunasi (Paid)</span>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($total_paid, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Masuk kas perusahaan</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block mb-1">Sisa Piutang / Unpaid</span>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($total_unpaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Belum diterima</span>
        </div>
    </div>

    <!-- Box Notifikasi Jika Proyek Sudah Lunas 100% -->
    <?php if ($project_payment_status === 'Lunas'): ?>
        <div class="mb-6 bg-emerald-900 border border-emerald-700 p-5 rounded-2xl text-white shadow-md flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center text-emerald-300 text-2xl border border-emerald-400/30 shrink-0">
                    <i class="fa-solid fa-certificate"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-emerald-300">PROYEK INI SUDAH LUNAS 100%!</h4>
                    <p class="text-xs text-emerald-100 mt-0.5">Seluruh tagihan termin telah terverifikasi lunas di kas perusahaan. Kuitansi BAST Pelunasan Gabungan siap dicetak.</p>
                </div>
            </div>
            <a href="print_pdf.php?project_id=<?= $project_id ?>&type=pelunasan" target="_blank" class="btn-press px-4 py-2.5 bg-emerald-500 hover:bg-emerald-400 text-emerald-950 text-xs font-extrabold rounded-xl transition shadow-xs shrink-0 flex items-center gap-2">
                <i class="fa-solid fa-print text-sm"></i> Cetak Kuitansi Pelunasan BAST
            </a>
        </div>
    <?php endif; ?>

    <!-- Main Table Container: Daftar Invoice Termin -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-receipt text-emerald-600"></i> Riwayat Invoice Termin Penagihan
        </h3>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">NO. INVOICE</th>
                        <th class="py-3.5 px-4">TERMIN & KETERANGAN</th>
                        <th class="py-3.5 px-4">TGL TAGIHAN</th>
                        <th class="py-3.5 px-4">JATUH TEMPO</th>
                        <th class="py-3.5 px-4 text-right">JUMLAH TAGIHAN</th>
                        <th class="py-3.5 px-4 text-center">STATUS BAYAR</th>
                        <th class="py-3.5 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-file-invoice"></i>
                                </div>
                                Belum ada invoice termin yang diterbitkan untuk proyek ini.<br>
                                <button onclick="openGenerateAllModal()" class="mt-3 text-emerald-600 font-bold hover:underline text-xs">
                                    Klik di sini untuk Generate Otomatis Skema Termin
                                </button>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): 
                            $isOverdue = (strtotime($inv['jatuh_tempo']) < time()) && $inv['status'] !== 'Paid';
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-slate-900 whitespace-nowrap"><?= htmlspecialchars($inv['nomor_invoice']) ?></td>
                                <td class="py-3.5 px-4">
                                    <span class="font-bold text-emerald-800 bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200 inline-block text-[10px] uppercase tracking-wider mb-0.5">
                                        Termin <?= $inv['termin_ke'] ?>
                                    </span>
                                    <span class="block text-xs text-slate-600 font-normal"><?= htmlspecialchars($inv['keterangan_termin']) ?></span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap font-normal">
                                    <?= date('d M Y', strtotime($inv['tanggal_tagihan'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 whitespace-nowrap font-normal">
                                    <?= date('d M Y', strtotime($inv['jatuh_tempo'])) ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="block text-[9px] font-bold uppercase tracking-wider text-red-600 bg-red-50 px-1.5 py-0.5 rounded border border-red-200 mt-0.5 w-max">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-900 font-mono whitespace-nowrap">
                                    Rp <?= number_format($inv['jumlah_tagihan'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <form action="update_status.php" method="POST" class="inline-block">
                                        <input type="hidden" name="invoice_id" value="<?= $inv['id'] ?>">
                                        <input type="hidden" name="project_id" value="<?= $project_id ?>">
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
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="print_pdf.php?id=<?= $inv['id'] ?>" target="_blank" title="Cetak Invoice PDF" class="btn-press px-2.5 py-1.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold transition inline-flex items-center gap-1 shadow-xs">
                                            <i class="fa-solid fa-print text-xs"></i> PDF
                                        </a>
                                        <a href="detail.php?project_id=<?= $project_id ?>&action=delete&inv_id=<?= $inv['id'] ?>" onclick="return confirm('Hapus invoice termin ini?')" class="btn-press p-1.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Modal 1: 1-Click Generate Semua Termin -->
<div id="generateAllModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 p-6 pb-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-bolt text-emerald-600"></i> Generate Otomatis Skema Termin
            </h3>
            <button onclick="closeGenerateAllModal()" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 pt-2 space-y-4">
            <input type="hidden" name="action_type" value="generate_all_scheme">

            <p class="text-xs text-slate-500">Pilih metode skema penagihan termin untuk proyek ini. Seluruh invoice termin akan langsung dibuat otomatis dengan perhitungan presisi.</p>

            <div class="space-y-3">
                <label class="block p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-emerald-500 transition font-sans">
                    <div class="flex items-start gap-3">
                        <input type="radio" name="scheme_choice" value="normal" checked class="mt-1 text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <span class="font-bold text-slate-900 block text-xs sm:text-sm">Skema Normal (2 Termin)</span>
                            <span class="text-xs text-slate-500 block mt-0.5">• Termin 1: DP 50% (Saat SPK)<br>• Termin 2: Pelunasan 50% (Saat BAST / Deadline)</span>
                        </div>
                    </div>
                </label>

                <label class="block p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer hover:border-emerald-500 transition font-sans">
                    <div class="flex items-start gap-3">
                        <input type="radio" name="scheme_choice" value="alternatif" class="mt-1 text-emerald-600 focus:ring-emerald-500">
                        <div>
                            <span class="font-bold text-slate-900 block text-xs sm:text-sm">Skema Alternatif (3 Termin)</span>
                            <span class="text-xs text-slate-500 block mt-0.5">• Termin 1: DP 30% (Saat SPK)<br>• Termin 2: Angsuran 20% (Progress)<br>• Termin 3: Pelunasan 50% (Saat BAST / Deadline)</span>
                        </div>
                    </div>
                </label>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeGenerateAllModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Generate Sekarang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 2: Terbitkan Single Invoice Termin Baru -->
<div id="invoiceModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 p-6 pb-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-file-invoice text-emerald-600"></i> Terbitkan Invoice Termin Baru
            </h3>
            <button onclick="closeInvoiceModal()" class="w-7 h-7 rounded-full bg-slate-100 text-slate-500 flex items-center justify-center hover:bg-slate-200 transition">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 pt-2 space-y-3.5">
            <input type="hidden" name="add_single_invoice" value="1">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nomor Invoice Auto</label>
                <input type="text" name="nomor_invoice" readonly value="<?= $autoNomorInvoice ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-mono font-bold bg-slate-50 text-emerald-800 focus:outline-none">
            </div>

            <!-- Pilihan Skema Anti-Bentrok -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Metode Skema Termin</label>
                <select name="scheme_selected" id="scheme_selected" onchange="updateTerminOptions()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <?php if (!$locked_scheme || $locked_scheme === 'normal'): ?>
                        <option value="normal" <?= ($locked_scheme === 'normal') ? 'selected' : '' ?>>Skema Normal (50% - 50%)</option>
                    <?php endif; ?>
                    <?php if (!$locked_scheme || $locked_scheme === 'alternatif'): ?>
                        <option value="alternatif" <?= ($locked_scheme === 'alternatif') ? 'selected' : '' ?>>Skema Alternatif (30% - 20% - 50%)</option>
                    <?php endif; ?>
                </select>
                <?php if ($locked_scheme): ?>
                    <p class="text-[11px] text-amber-700 font-medium mt-1">🔒 Skema terkunci ke <?= strtoupper($locked_scheme) ?> untuk mencegah bentrok.</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Termin</label>
                    <select name="termin_ke" id="termin_ke" onchange="autoCalcSingleTermin()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                        <!-- Populated by JS -->
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Keterangan Termin</label>
                    <input type="text" name="keterangan_termin" id="keterangan_termin" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Jumlah Tagihan (Rp)</label>
                <input type="text" name="jumlah_tagihan" id="jumlah_tagihan" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Tagihan</label>
                    <input type="date" name="tanggal_tagihan" id="tanggal_tagihan" required onchange="calcDueDate()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Jatuh Tempo</label>
                    <input type="date" name="jatuh_tempo" id="jatuh_tempo" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
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
const projectBudget   = <?= (float)$project['budget'] ?>;
const projectStart    = "<?= !empty($project['tanggal_mulai']) && $project['tanggal_mulai'] !== '0000-00-00' ? $project['tanggal_mulai'] : date('Y-m-d') ?>";
const projectDeadline = "<?= !empty($project['deadline']) && $project['deadline'] !== '0000-00-00' ? $project['deadline'] : date('Y-m-d', strtotime('+30 days')) ?>";

function openGenerateAllModal() { document.getElementById('generateAllModal').classList.remove('hidden'); }
function closeGenerateAllModal() { document.getElementById('generateAllModal').classList.add('hidden'); }

function openInvoiceModal() { 
    document.getElementById('invoiceModal').classList.remove('hidden'); 
    updateTerminOptions();
}
function closeInvoiceModal() { document.getElementById('invoiceModal').classList.add('hidden'); }

function updateTerminOptions() {
    const scheme = document.getElementById('scheme_selected').value;
    const terminSelect = document.getElementById('termin_ke');
    terminSelect.innerHTML = '';

    if (scheme === 'normal') {
        terminSelect.innerHTML = `
            <option value="1">Termin 1 (50%)</option>
            <option value="2">Termin 2 (50%)</option>
        `;
    } else {
        terminSelect.innerHTML = `
            <option value="1">Termin 1 (30%)</option>
            <option value="2">Termin 2 (20%)</option>
            <option value="3">Termin 3 (50%)</option>
        `;
    }
    autoCalcSingleTermin();
}

function autoCalcSingleTermin() {
    const scheme = document.getElementById('scheme_selected').value;
    const termin = document.getElementById('termin_ke').value;
    const ket = document.getElementById('keterangan_termin');
    const amt = document.getElementById('jumlah_tagihan');
    const tglTagihan = document.getElementById('tanggal_tagihan');

    let calculatedAmt = 0;

    if (scheme === 'normal') {
        if (termin === '1') {
            ket.value = 'Termin 1 (DP 50% Penandatanganan SPK)';
            calculatedAmt = projectBudget * 0.50;
            tglTagihan.value = projectStart;
        } else {
            ket.value = 'Termin 2 (Pelunasan 50% BAST Selesai)';
            calculatedAmt = projectBudget * 0.50;
            tglTagihan.value = projectStart;
        }
    } else {
        if (termin === '1') {
            ket.value = 'Termin 1 (DP 30% Penandatanganan SPK)';
            calculatedAmt = projectBudget * 0.30;
            tglTagihan.value = projectStart;
        } else if (termin === '2') {
            ket.value = 'Termin 2 (Angsuran 20% Progress Pengerjaan)';
            calculatedAmt = projectBudget * 0.20;
            tglTagihan.value = addDays(projectStart, 7);
        } else {
            ket.value = 'Termin 3 (Pelunasan 50% BAST Selesai)';
            calculatedAmt = projectBudget * 0.50;
            tglTagihan.value = addDays(projectStart, 14);
        }
    }

    amt.value = new Intl.NumberFormat('id-ID').format(calculatedAmt);
    calcDueDate();
}

function calcDueDate() {
    const scheme = document.getElementById('scheme_selected').value;
    const termin = document.getElementById('termin_ke').value;
    const tglTagihan = document.getElementById('tanggal_tagihan').value;
    const jatuhTempo = document.getElementById('jatuh_tempo');

    const isFinalTermin = (scheme === 'normal' && termin === '2') || (scheme === 'alternatif' && termin === '3');

    if (isFinalTermin) {
        jatuhTempo.value = projectDeadline;
    } else {
        if (tglTagihan) {
            jatuhTempo.value = addDays(tglTagihan, 7);
        }
    }
}

function addDays(dateStr, days) {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);
    return d.toISOString().split('T')[0];
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>