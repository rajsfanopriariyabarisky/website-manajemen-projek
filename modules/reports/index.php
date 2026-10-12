<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle Export Excel (.xls)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $project_filter = $_GET['project_id'] ?? 'All';
    $filename = "Laporan_Keuangan_PT_CTC_" . date('Y-m-d_H-i') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    if ($project_filter !== 'All') {
        $stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmtP->execute([$project_filter]);
        $proj = $stmtP->fetch();

        // Expenses
        $stmtE = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran ASC");
        $stmtE->execute([$project_filter]);
        $expensesList = $stmtE->fetchAll();

        // Realized Paid Invoices
        $stmtInvPaid = $pdo->prepare("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE project_id = ? AND status = 'Paid'");
        $stmtInvPaid->execute([$project_filter]);
        $realizedPaid = $stmtInvPaid->fetchColumn() ?? 0;

        $stmtInvUnpaid = $pdo->prepare("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE project_id = ? AND status != 'Paid'");
        $stmtInvUnpaid->execute([$project_filter]);
        $unpaidAmount = $stmtInvUnpaid->fetchColumn() ?? 0;

        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
                .title-header { font-size: 16pt; font-weight: bold; color: #064e3b; }
                .bg-header { background-color: #047857; color: #ffffff; font-weight: bold; text-align: center; }
                .bg-sub-header { background-color: #065f46; color: #ffffff; font-weight: bold; }
                .border-all { border: 1px solid #cbd5e1; }
                .num-currency { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; }
            </style>
        </head>
        <body>
            <table>
                <tr><td colspan="6" class="title-header">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="6" style="font-weight: bold; font-size: 12pt; color: #0f172a;">LAPORAN DETAIL FINANCIAL & PROFITABILITAS PROYEK</td></tr>
                <tr><td colspan="6" style="font-size: 9pt; color: #64748b;">Tanggal Ekspor: <?= date('d F Y H:i:s') ?> WIB</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="6" align="center">I. RINGKASAN EKSEKUTIF PROYEK</td></tr>
                <tr>
                    <td width="180"><b>Nama Proyek:</b></td><td colspan="2"><?= htmlspecialchars($proj['nama_projek']) ?></td>
                    <td width="180"><b>Klien / Status:</b></td><td colspan="2"><?= htmlspecialchars($proj['klien']) ?> (<?= $proj['status'] ?>)</td>
                </tr>
                <tr class="bg-sub-header"><td colspan="2" align="center">METRIK KEUANGAN</td><td colspan="4" align="center">NILAI NOMINAL (IDR)</td></tr>
                <tr><td colspan="2">Nilai Total Kontrak Proyek</td><td colspan="4" class="num-currency"><?= $proj['budget'] ?></td></tr>
                <tr><td colspan="2">Realized Revenue (Invoices Paid)</td><td colspan="4" class="num-currency"><?= $realizedPaid ?></td></tr>
                <tr><td colspan="2">Unpaid / Piutang Klien</td><td colspan="4" class="num-currency"><?= $unpaidAmount ?></td></tr>
                <tr><td colspan="2">Total Realisasi Biaya Operasional</td><td colspan="4" class="num-currency"><?= array_sum(array_column($expensesList, 'jumlah_biaya')) ?></td></tr>
                <tr><td colspan="2"><b>Net Profit Real (Dana Masuk - Expense)</b></td><td colspan="4" class="num-currency"><?= $realizedPaid - array_sum(array_column($expensesList, 'jumlah_biaya')) ?></td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="6" align="center">II. RINCIAN LEDGER TRANSAKSI PENGELUARAN</td></tr>
                <tr class="bg-sub-header">
                    <th>No</th><th>Tanggal</th><th>Ref ID</th><th>Kategori</th><th>Keterangan</th><th>Nominal (Rp)</th>
                </tr>
                <?php $no = 1; foreach ($expensesList as $ex): ?>
                    <tr>
                        <td align="center"><?= $no ?></td>
                        <td align="center"><?= date('d/m/Y', strtotime($ex['tanggal_pengeluaran'])) ?></td>
                        <td align="center">EXP-<?= sprintf("%04d", $ex['id']) ?></td>
                        <td><?= htmlspecialchars($ex['kategori']) ?></td>
                        <td><?= htmlspecialchars($ex['keterangan'] ?: '-') ?></td>
                        <td class="num-currency"><?= $ex['jumlah_biaya'] ?></td>
                    </tr>
                <?php $no++; endforeach; ?>
            </table>
        </body>
        </html>
        <?php
    } else {
        $stmtAll = $pdo->query("
            SELECT p.*, 
                   COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status = 'Paid'), 0) AS realized_paid,
                   COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status != 'Paid'), 0) AS unpaid_piutang,
                   COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
            FROM projects p
            ORDER BY p.created_at DESC
        ");
        $allReports = $stmtAll->fetchAll();
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
                .title-header { font-size: 16pt; font-weight: bold; color: #064e3b; }
                .bg-header { background-color: #047857; color: #ffffff; font-weight: bold; text-align: center; }
                .bg-sub-header { background-color: #065f46; color: #ffffff; font-weight: bold; }
                .border-all { border: 1px solid #cbd5e1; }
                .num-currency { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; }
            </style>
        </head>
        <body>
            <table>
                <tr><td colspan="8" class="title-header">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="8" style="font-weight: bold; font-size: 12pt; color: #0f172a;">LAPORAN KONSOLIDASI KEUANGAN SELURUH PROYEK</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="8" align="center">RINGKASAN PORTOFOLIO KEUANGAN</td></tr>
                <tr class="bg-sub-header">
                    <th>No</th><th>Nama Proyek</th><th>Klien</th><th>Status</th><th>Total Kontrak (Rp)</th><th>Paid Revenue (Rp)</th><th>Piutang / Unpaid (Rp)</th><th>Realisasi Biaya (Rp)</th>
                </tr>
                <?php $no = 1; foreach ($allReports as $r): ?>
                    <tr>
                        <td align="center"><?= $no ?></td>
                        <td><b><?= htmlspecialchars($r['nama_projek']) ?></b></td>
                        <td><?= htmlspecialchars($r['klien']) ?></td>
                        <td align="center"><?= htmlspecialchars($r['status']) ?></td>
                        <td class="num-currency"><?= $r['budget'] ?></td>
                        <td class="num-currency"><?= $r['realized_paid'] ?></td>
                        <td class="num-currency"><?= $r['unpaid_piutang'] ?></td>
                        <td class="num-currency"><?= $r['total_expense'] ?></td>
                    </tr>
                <?php $no++; endforeach; ?>
            </table>
        </body>
        </html>
        <?php
    }
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$selected_project = $_GET['project_id'] ?? 'All';
$projectsList = $pdo->query("SELECT id, nama_projek, klien FROM projects ORDER BY nama_projek ASC")->fetchAll();

if ($selected_project !== 'All') {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status = 'Paid'), 0) AS realized_paid,
               COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status != 'Paid'), 0) AS unpaid_piutang,
               COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
        FROM projects p
        WHERE p.id = ?
    ");
    $stmt->execute([$selected_project]);
    $reports = $stmt->fetchAll();

    $stmtExpDetail = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran DESC");
    $stmtExpDetail->execute([$selected_project]);
    $expensesDetails = $stmtExpDetail->fetchAll();
} else {
    $stmt = $pdo->query("
        SELECT p.*, 
               COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status = 'Paid'), 0) AS realized_paid,
               COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status != 'Paid'), 0) AS unpaid_piutang,
               COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
        FROM projects p
        ORDER BY p.created_at DESC
    ");
    $reports = $stmt->fetchAll();
    $expensesDetails = [];
}

// Akumulasi Konsolidasi
$grandBudget       = 0;
$grandRealizedPaid = 0;
$grandUnpaid       = 0;
$grandExpense      = 0;

foreach ($reports as $r) {
    $grandExpense      += $r['total_expense'];
    $grandUnpaid       += $r['unpaid_piutang'];
    $grandRealizedPaid += $r['realized_paid'];

    if (!in_array($r['status'], ['On Hold', 'Planning'])) {
        $grandBudget += $r['budget'];
    }
}

$grandNetProfit = $grandRealizedPaid - $grandExpense;
?>

<!-- STYLE KHUSUS MODE CETAK PRINT & PDF -->
<style>
@media print {
    /* Sembunyikan elemen bawaan web termasuk header paling atas agar logo tidak terduplikasi */
    aside, 
    #sidebar, 
    .sidebar, 
    [class*="sidebar"], 
    header, 
    nav, 
    .no-print, 
    button, 
    a.bg-emerald-700 {
        display: none !important;
        visibility: hidden !important;
        width: 0 !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        position: absolute !important;
        left: -9999px !important;
    }

    html, body {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        font-family: Arial, sans-serif !important;
    }

    div.flex, main, body {
        display: block !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        box-sizing: border-box !important;
    }

    /* Kop Surat Cetak */
    .print-header { 
        display: block !important; 
        width: 100% !important;
        margin-bottom: 15px !important;
        padding-bottom: 8px !important;
        border-bottom: 2px solid #000000 !important;
    }

    .screen-stats { display: none !important; }

    /* Tabel Ringkasan Cetak */
    .print-summary-table {
        display: table !important;
        width: 100% !important;
        border-collapse: collapse !important;
        margin-bottom: 15px !important;
    }

    .print-summary-table th, .print-summary-table td {
        border: 1px solid #000000 !important;
        padding: 6px 8px !important;
        text-align: center !important;
        font-size: 10px !important;
    }

    .print-summary-table th { 
        background-color: #f2f2f2 !important; 
        font-weight: bold !important; 
    }

    /* Tabel Detail Keuangan Cetak */
    .report-table-container { 
        border: none !important; 
        box-shadow: none !important; 
        padding: 0 !important; 
        width: 100% !important;
    }

    table { 
        width: 100% !important; 
        border-collapse: collapse !important; 
        font-size: 10px !important; 
    }

    th, td { 
        border: 1px solid #000000 !important; 
        padding: 5px 6px !important; 
    }

    th { 
        background-color: #f2f2f2 !important; 
        color: #000000 !important; 
        text-transform: uppercase !important; 
        font-weight: bold !important; 
    }

    /* Format Badge Status Sebaris dan Rapi Saat Diprint */
    .status-badge-print {
        border: none !important;
        background: transparent !important;
        padding: 0 !important;
        font-weight: bold !important;
        font-size: 9px !important;
        text-transform: uppercase !important;
        white-space: nowrap !important;
        color: #000000 !important;
    }

    /* Blok Tanda Tangan Cetak */
    .print-signatures {
        display: flex !important;
        justify-content: space-between !important;
        margin-top: 35px !important;
        page-break-inside: avoid !important;
        width: 100% !important;
    }

    .signature-box { 
        text-align: center !important; 
        width: 30% !important; 
        font-size: 10px !important; 
    }
}

/* Sembunyikan Elemen Cetak Saat Di Layar Web Normal */
.print-header, .print-summary-table, .print-signatures { 
    display: none; 
}
</style>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 min-h-screen w-full">
    
    <!-- KOP SURAT FORMAL PT. CTC (Hanya Muncul Saat Diprint/PDF) -->
    <div class="print-header">
        <div class="flex justify-between items-center pb-3 border-b border-slate-300">
            <div class="flex items-center gap-3">
                <img src="/website%20manajemen%20projek/assets/images/logo.jpg" alt="Logo PT CTC" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px;">
                <div>
                    <h1 class="text-base font-bold text-slate-900 uppercase tracking-wide">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p class="text-[10px] text-slate-600 font-semibold">Enterprise IT Solution, Software Development & Financial Control</p>
                    <p class="text-[9px] text-slate-500">Gedung CTC Tower Lt. 8, Jl. Jend. Sudirman, Jakarta | Website: www.ctc.co.id</p>
                </div>
            </div>
            <div class="text-right text-[10px] text-slate-600">
                <p><strong>DOKUMEN KEUANGAN RESMI</strong></p>
                <p>Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>
        <div class="text-center mt-2">
            <h2 class="text-xs font-bold uppercase underline tracking-wider">
                <?= $selected_project === 'All' ? 'LAPORAN KONSOLIDASI KEUANGAN SELURUH PROYEK' : 'LAPORAN DETAIL KEUANGAN PROYEK' ?>
            </h2>
        </div>
    </div>

    <!-- Top Header Bar (Tampilan Layar Web Normal) -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6 no-print bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">
                MODUL 4
            </span>
            <h1 class="text-xl md:text-2xl font-bold text-slate-800 mt-2">Laporan Keuangan & Profitabilitas</h1>
            <p class="text-xs text-slate-500 mt-0.5">Analisis realisasi dana masuk, pengeluaran operasional, dan margin profit PT. CTC.</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-2">
            <a href="index.php?project_id=<?= $selected_project ?>&export=excel" class="bg-emerald-700 hover:bg-emerald-800 text-white font-medium px-4 py-2.5 rounded-xl flex items-center justify-center gap-2 transition text-xs shadow-md">
                <i class="fa-solid fa-file-excel text-emerald-300"></i> Export Excel (.xls)
            </a>
            <button onclick="window.print()" class="bg-slate-800 hover:bg-slate-900 text-white font-medium px-4 py-2.5 rounded-xl flex items-center justify-center gap-2 transition text-xs shadow-md">
                <i class="fa-solid fa-print"></i> Cetak PDF / Print
            </button>
        </div>
    </div>

    <!-- Filter Scope Laporan -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-6 shadow-sm no-print flex items-center justify-between">
        <form method="GET" class="flex items-center gap-3 w-full">
            <label class="text-xs font-bold text-slate-500 uppercase flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-filter text-emerald-600"></i> Scope Laporan:
            </label>
            <select name="project_id" onchange="this.form.submit()" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="All" <?= $selected_project === 'All' ? 'selected' : '' ?>>📊 Seluruh Proyek (Konsolidasi Portfolio)</option>
                <?php foreach ($projectsList as $pl): ?>
                    <option value="<?= $pl['id'] ?>" <?= $selected_project == $pl['id'] ? 'selected' : '' ?>>
                        📂 <?= htmlspecialchars($pl['nama_projek']) ?> (<?= htmlspecialchars($pl['klien']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- METRIC STATS CARDS (Khusus Tampilan Layar Web) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 screen-stats">
        
        <!-- Realized Revenue (Paid) -->
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">REALIZED REVENUE (PAID)</span>
            <h3 class="text-xl font-bold text-slate-800">Rp <?= number_format($grandRealizedPaid, 0, ',', '.') ?></h3>
            <p class="text-[11px] text-emerald-600 font-semibold mt-1 flex items-center gap-1">
                <i class="fa-solid fa-circle-check"></i> Dana terbayar di kas
            </p>
        </div>

        <!-- Operational Expense -->
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">OPERATIONAL EXPENSE</span>
            <h3 class="text-xl font-bold text-amber-600">Rp <?= number_format($grandExpense, 0, ',', '.') ?></h3>
            <span class="text-[11px] text-slate-400 mt-1 block">Realisasi pengeluaran</span>
        </div>

        <!-- Net Profit Real -->
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">NET PROFIT REAL</span>
            <h3 class="text-xl font-bold <?= $grandNetProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                Rp <?= number_format($grandNetProfit, 0, ',', '.') ?>
            </h3>
            <span class="text-[11px] text-emerald-600 font-semibold mt-1 block"><i class="fa-solid fa-arrow-up"></i> Profit terbayar bersih</span>
        </div>

        <!-- Unpaid / Piutang Status -->
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">UNPAID / PIUTANG KLIENT</span>
            <h3 class="text-xl font-bold text-slate-700">Rp <?= number_format($grandUnpaid, 0, ',', '.') ?></h3>
            <span class="text-[11px] text-amber-600 font-semibold mt-1 block"><i class="fa-solid fa-clock"></i> Tagihan belum lunas</span>
        </div>
    </div>

    <!-- TAMPILAN TABEL PRINT RINGKAS (Khusus PDF Cetak) -->
    <table class="print-summary-table">
        <thead>
            <tr>
                <th>REALIZED REVENUE (PAID)</th>
                <th>OPERATIONAL EXPENSE</th>
                <th>NET PROFIT BERSIH REAL</th>
                <th>PIUTANG (UNPAID)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Rp <?= number_format($grandRealizedPaid, 0, ',', '.') ?></strong></td>
                <td><strong>Rp <?= number_format($grandExpense, 0, ',', '.') ?></strong></td>
                <td><strong>Rp <?= number_format($grandNetProfit, 0, ',', '.') ?></strong></td>
                <td><strong>Rp <?= number_format($grandUnpaid, 0, ',', '.') ?></strong></td>
            </tr>
        </tbody>
    </table>

    <!-- TABEL UTAMA LAPORAN PROFITABILITAS -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8 report-table-container">
        <h3 class="text-base font-bold text-slate-800 mb-5 no-print">
            <?= $selected_project === 'All' ? 'Laporan Profitabilitas Seluruh Proyek' : 'Ringkasan Keuangan Proyek' ?>
        </h3>

        <!-- Desktop View & Print Table View -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 uppercase font-bold text-[10px] border-b border-slate-200">
                        <th class="py-3 px-4">Nama Proyek</th>
                        <th class="py-3 px-4">Klien</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4 text-right">Nilai Kontrak</th>
                        <th class="py-3 px-4 text-right">Paid (Dana Masuk)</th>
                        <th class="py-3 px-4 text-right">Unpaid (Piutang)</th>
                        <th class="py-3 px-4 text-right">Pengeluaran</th>
                        <th class="py-3 px-4 text-right">Net Profit Real</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($reports as $r): 
                        $isHold = in_array($r['status'], ['On Hold', 'Planning']);
                        $paidRevenue = $r['realized_paid'];
                        $unpaid = $r['unpaid_piutang'];
                        $expense = $r['total_expense'];
                        $netProfitReal = $paidRevenue - $expense;
                    ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-4 font-bold text-slate-800"><?= htmlspecialchars($r['nama_projek']) ?></td>
                            <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($r['klien']) ?></td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="status-badge-print px-2.5 py-0.5 rounded-full text-[10px] uppercase font-bold inline-block border
                                    <?php
                                        switch($r['status']) {
                                            case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                            case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                            case 'On Hold': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                            default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                        }
                                    ?>">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-slate-800">
                                <?php if ($isHold): ?>
                                    <span class="text-amber-600 font-normal">Rp 0 <span class="text-[10px] bg-amber-50 px-1 py-0.5 rounded border border-amber-200 font-semibold">(Hold On)</span></span>
                                <?php else: ?>
                                    Rp <?= number_format($r['budget'], 0, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-emerald-600">
                                Rp <?= number_format($paidRevenue, 0, ',', '.') ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-slate-500">
                                Rp <?= number_format($unpaid, 0, ',', '.') ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-semibold text-amber-600">
                                Rp <?= number_format($expense, 0, ',', '.') ?>
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold <?= $netProfitReal >= 0 ? 'text-emerald-700' : 'text-red-600' ?>">
                                Rp <?= number_format($netProfitReal, 0, ',', '.') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- RINCIAN EXPENSE SPESIFIK PROYEK -->
    <?php if ($selected_project !== 'All'): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm report-table-container">
            <h3 class="text-base font-bold text-slate-800 mb-4">Item Pengeluaran Operasional Proyek</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 border-collapse">
                    <thead class="uppercase bg-slate-50 text-slate-400 font-bold border-b border-slate-200 text-[10px]">
                        <tr>
                            <th class="py-2.5 px-3">Tanggal</th>
                            <th class="py-2.5 px-3">Kategori</th>
                            <th class="py-2.5 px-3">Keterangan</th>
                            <th class="py-2.5 px-3 text-right">Biaya (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($expensesDetails)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-slate-400">Belum ada catatan biaya operasional pada proyek ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expensesDetails as $ex): ?>
                                <tr>
                                    <td class="py-3 px-3 text-slate-500"><?= date('d M Y', strtotime($ex['tanggal_pengeluaran'])) ?></td>
                                    <td class="py-3 px-3 font-semibold text-slate-700"><?= htmlspecialchars($ex['kategori']) ?></td>
                                    <td class="py-3 px-3 text-slate-600"><?= htmlspecialchars($ex['keterangan'] ?: '-') ?></td>
                                    <td class="py-3 px-3 text-right font-bold text-slate-800">Rp <?= number_format($ex['jumlah_biaya'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- BLOK PENGESAHAN TANDA TANGAN (Khusus Dokumen Cetak) -->
    <div class="print-signatures">
        <div class="signature-box">
            <p><strong>Dibuat Oleh:</strong></p>
            <p style="margin-top: 50px;"><u>( Finance Staff )</u></p>
            <p>PT. CTC Finance Dept</p>
        </div>
        <div class="signature-box">
            <p><strong>Diperiksa Oleh:</strong></p>
            <p style="margin-top: 50px;"><u>( Lead PM )</u></p>
            <p>Project Manager</p>
        </div>
        <div class="signature-box">
            <p><strong>Disetujui Oleh:</strong></p>
            <p style="margin-top: 50px;"><u>( Executive VP )</u></p>
            <p>VP Operations</p>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>