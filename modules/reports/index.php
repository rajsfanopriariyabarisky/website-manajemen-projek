<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle Export Excel (.xls) Berbasis XML Excel Native
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $project_filter = $_GET['project_id'] ?? 'All';
    $search_filter  = trim($_GET['search'] ?? '');
    $filename       = "Pembukuan_Keuangan_PT_CTC_" . date('Y-m-d_H-i') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    if ($project_filter !== 'All') {
        // Mode 1: Export Detail Pembukuan 1 Proyek
        $stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmtP->execute([$project_filter]);
        $proj = $stmtP->fetch();

        $stmtE = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran ASC");
        $stmtE->execute([$project_filter]);
        $expensesList = $stmtE->fetchAll();

        $stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY tanggal_tagihan ASC");
        $stmtInv->execute([$project_filter]);
        $invoicesList = $stmtInv->fetchAll();

        $calcTotalInv  = array_sum(array_column($invoicesList, 'jumlah_tagihan'));
        $calcPaidRev   = 0;
        $calcUnpaid    = 0;
        foreach ($invoicesList as $iv) {
            if (strtolower($iv['status']) === 'paid') {
                $calcPaidRev += $iv['jumlah_tagihan'];
            } else {
                $calcUnpaid += $iv['jumlah_tagihan'];
            }
        }
        $calcTotalExp  = array_sum(array_column($expensesList, 'jumlah_biaya'));
        $calcNetProfit = $calcPaidRev - $calcTotalExp;

        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
                .title-company { font-size: 16pt; font-weight: bold; color: #064e3b; }
                .title-doc { font-size: 13pt; font-weight: bold; color: #0f172a; }
                .bg-header { background-color: #047857; color: #ffffff; font-weight: bold; text-align: center; }
                .bg-section { background-color: #065f46; color: #ffffff; font-weight: bold; }
                .bg-total { background-color: #f1f5f9; font-weight: bold; }
                .border-all { border: 1px solid #cbd5e1; }
                .num-curr { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; }
                .num-bold { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; font-weight: bold; }
            </style>
        </head>
        <body>
            <table>
                <tr><td colspan="6" class="title-company">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="6" class="title-doc">PEMBUKUAN KEUANGAN & AUDIT LEDGER PROYEK</td></tr>
                <tr><td colspan="6" style="font-size: 9pt; color: #64748b;">Tanggal Ekspor: <?= date('d F Y H:i:s') ?> WIB</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="6">INFORMASI PROYEK & METRIK KONTRAK</td></tr>
                <tr>
                    <td><b>Nama Proyek:</b></td><td colspan="2"><?= htmlspecialchars($proj['nama_projek']) ?></td>
                    <td><b>Klien / Status:</b></td><td colspan="2"><?= htmlspecialchars($proj['klien']) ?> (<?= $proj['status'] ?>)</td>
                </tr>
                <tr>
                    <td><b>Nilai Total Kontrak:</b></td>
                    <td colspan="5" class="num-bold"><?= $proj['budget'] ?></td>
                </tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-section"><td colspan="6">I. LEDGER INVOICE & PENERIMAAN DANA (REVENUE)</td></tr>
                <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
                    <th>No</th><th>Tanggal Tagihan</th><th>No. Invoice</th><th>Keterangan Termin</th><th>Status</th><th>Jumlah Tagihan (Rp)</th>
                </tr>
                <?php 
                $startRowInv = 16;
                $rowInvCount = 0;
                if (!empty($invoicesList)): 
                    foreach ($invoicesList as $idx => $inv): 
                        $rowInvCount++;
                ?>
                    <tr>
                        <td align="center"><?= $idx + 1 ?></td>
                        <td align="center"><?= date('d/m/Y', strtotime($inv['tanggal_tagihan'])) ?></td>
                        <td align="center"><?= htmlspecialchars($inv['nomor_invoice']) ?></td>
                        <td><?= htmlspecialchars($inv['keterangan_termin']) ?></td>
                        <td align="center"><b><?= htmlspecialchars($inv['status']) ?></b></td>
                        <td class="num-curr"><?= (float)$inv['jumlah_tagihan'] ?></td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="6" align="center" style="color: #94a3b8;">Belum ada data invoice terbit.</td></tr>
                <?php endif; ?>
                
                <?php $totalInvRow = $startRowInv + max(1, $rowInvCount); ?>
                <tr class="bg-total">
                    <td colspan="5" align="right"><b>TOTAL SELURUH TAGIHAN (INVOICES):</b></td>
                    <td class="num-bold" x:fmla="=SUM(F<?= $startRowInv ?>:F<?= $totalInvRow - 1 ?>)"><?= $calcTotalInv ?></td>
                </tr>
                <tr class="bg-total">
                    <td colspan="5" align="right" style="color: #047857;"><b>TOTAL DANA MASUK (REALIZED PAID REVENUE):</b></td>
                    <td class="num-bold" style="color: #047857;" x:fmla='=SUMIF(E<?= $startRowInv ?>:E<?= $totalInvRow - 1 ?>; "Paid"; F<?= $startRowInv ?>:F<?= $totalInvRow - 1 ?>)'><?= $calcPaidRev ?></td>
                </tr>
                <tr class="bg-total">
                    <td colspan="5" align="right" style="color: #b45309;"><b>TOTAL PIUTANG / UNPAID INVOICES:</b></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla='=SUMIF(E<?= $startRowInv ?>:E<?= $totalInvRow - 1 ?>; "<>Paid"; F<?= $startRowInv ?>:F<?= $totalInvRow - 1 ?>)'><?= $calcUnpaid ?></td>
                </tr>
            </table>
            <br>
            <?php $startRowExp = $totalInvRow + 6; ?>
            <table class="border-all" cellpadding="5">
                <tr class="bg-section"><td colspan="6">II. LEDGER TRANSAKSI PENGELUARAN OPERASIONAL (EXPENSES)</td></tr>
                <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
                    <th>No</th><th>Tanggal</th><th>Ref ID</th><th>Kategori Biaya</th><th>Keterangan Pengeluaran</th><th>Nominal Biaya (Rp)</th>
                </tr>
                <?php 
                $rowExpCount = 0;
                if (!empty($expensesList)): 
                    foreach ($expensesList as $idxE => $ex): 
                        $rowExpCount++;
                ?>
                    <tr>
                        <td align="center"><?= $idxE + 1 ?></td>
                        <td align="center"><?= date('d/m/Y', strtotime($ex['tanggal_pengeluaran'])) ?></td>
                        <td align="center">EXP-<?= sprintf("%04d", $ex['id']) ?></td>
                        <td><?= htmlspecialchars($ex['kategori']) ?></td>
                        <td><?= htmlspecialchars($ex['keterangan'] ?: '-') ?></td>
                        <td class="num-curr"><?= (float)$ex['jumlah_biaya'] ?></td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="6" align="center" style="color: #94a3b8;">Belum ada catatan pengeluaran operasional.</td></tr>
                <?php endif; ?>

                <?php $totalExpRow = $startRowExp + max(1, $rowExpCount); ?>
                <tr class="bg-total">
                    <td colspan="5" align="right" style="color: #b45309;"><b>TOTAL REALISASI BIAYA OPERASIONAL:</b></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(F<?= $startRowExp ?>:F<?= $totalExpRow - 1 ?>)"><?= $calcTotalExp ?></td>
                </tr>
            </table>
            <br>
            <?php $paidRevRow = $totalInvRow + 1; ?>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="2">III. RINGKASAN REKAPITULASI KEUANGAN AKHIR (FORMULA ACTIVE)</td></tr>
                <tr>
                    <td width="300">Total Realized Revenue (Dana Masuk)</td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=F<?= $paidRevRow ?>"><?= $calcPaidRev ?></td>
                </tr>
                <tr>
                    <td>Total Realisasi Pengeluaran Operasional</td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=F<?= $totalExpRow ?>"><?= $calcTotalExp ?></td>
                </tr>
                <tr style="background-color: #d1fae5; font-size: 12pt;">
                    <td><b>NET PROFIT REAL (BERSIH DANA MASUK)</b></td>
                    <td class="num-bold" style="color: #047857; font-size: 12pt;" x:fmla="=F<?= $paidRevRow ?>-F<?= $totalExpRow ?>"><?= $calcNetProfit ?></td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    } else {
        // Mode 2: Export Konsolidasi Seluruh Proyek
        $sqlExcel = "
            SELECT p.*, 
                   COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status = 'Paid'), 0) AS realized_paid,
                   COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status != 'Paid'), 0) AS unpaid_piutang,
                   COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
            FROM projects p
            WHERE 1=1
        ";
        $paramsExcel = [];
        if (!empty($search_filter)) {
            $sqlExcel .= " AND (p.nama_projek LIKE ? OR p.klien LIKE ?)";
            $paramsExcel[] = "%$search_filter%";
            $paramsExcel[] = "%$search_filter%";
        }
        $sqlExcel .= " ORDER BY p.created_at DESC";
        $stmtAll = $pdo->prepare($sqlExcel);
        $stmtAll->execute($paramsExcel);
        $allReports = $stmtAll->fetchAll();

        $cBudget = 0; $cPaid = 0; $cUnpaid = 0; $cExp = 0; $cProfit = 0;
        foreach ($allReports as $ar) {
            $cBudget += $ar['budget'];
            $cPaid   += $ar['realized_paid'];
            $cUnpaid += $ar['unpaid_piutang'];
            $cExp    += $ar['total_expense'];
            $cProfit += ($ar['realized_paid'] - $ar['total_expense']);
        }
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
            <style>
                body { font-family: 'Calibri', Arial, sans-serif; font-size: 11pt; }
                .title-company { font-size: 16pt; font-weight: bold; color: #064e3b; }
                .title-doc { font-size: 13pt; font-weight: bold; color: #0f172a; }
                .bg-header { background-color: #047857; color: #ffffff; font-weight: bold; text-align: center; }
                .bg-sub-header { background-color: #065f46; color: #ffffff; font-weight: bold; text-align: center; }
                .bg-total { background-color: #d1fae5; font-weight: bold; }
                .border-all { border: 1px solid #cbd5e1; }
                .num-curr { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; }
                .num-bold { mso-number-format:"Rp\ \#\,\#\#0"; text-align: right; font-weight: bold; }
            </style>
        </head>
        <body>
            <table>
                <tr><td colspan="9" class="title-company">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="9" class="title-doc">PEMBUKUAN KONSOLIDASI KEUANGAN SELURUH PORTOFOLIO PROYEK</td></tr>
                <tr><td colspan="9" style="font-size: 9pt; color: #64748b;">Tanggal Ekspor: <?= date('d F Y H:i:s') ?> WIB</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="9">REKAPITULASI LEDGER FINANCIAL PORTOFOLIO</td></tr>
                <tr class="bg-sub-header">
                    <th>No</th><th>Nama Proyek</th><th>Klien</th><th>Status Proyek</th><th>Nilai Kontrak (Rp)</th><th>Realized Paid (Rp)</th><th>Unpaid / Piutang (Rp)</th><th>Operational Expense (Rp)</th><th>Net Profit Real (Rp)</th>
                </tr>
                <?php 
                $startRowCons = 9;
                $rowCountCons = count($allReports);
                foreach ($allReports as $idx => $r): 
                    $rowNum = $startRowCons + $idx;
                    $np = $r['realized_paid'] - $r['total_expense'];
                ?>
                    <tr>
                        <td align="center"><?= $idx + 1 ?></td>
                        <td><b><?= htmlspecialchars($r['nama_projek']) ?></b></td>
                        <td><?= htmlspecialchars($r['klien']) ?></td>
                        <td align="center"><?= htmlspecialchars($r['status']) ?></td>
                        <td class="num-curr"><?= (float)$r['budget'] ?></td>
                        <td class="num-curr"><?= (float)$r['realized_paid'] ?></td>
                        <td class="num-curr"><?= (float)$r['unpaid_piutang'] ?></td>
                        <td class="num-curr"><?= (float)$r['total_expense'] ?></td>
                        <td class="num-bold" style="color: #047857;" x:fmla="=F<?= $rowNum ?>-H<?= $rowNum ?>"><?= $np ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php $endRowCons = $startRowCons + max(0, $rowCountCons - 1); ?>
                <tr class="bg-total" style="font-size: 11pt;">
                    <td colspan="4" align="right"><b>TOTAL KONSOLIDASI PORTOFOLIO:</b></td>
                    <td class="num-bold" x:fmla="=SUM(E<?= $startRowCons ?>:E<?= $endRowCons ?>)"><?= $cBudget ?></td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=SUM(F<?= $startRowCons ?>:F<?= $endRowCons ?>)"><?= $cPaid ?></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(G<?= $startRowCons ?>:G<?= $endRowCons ?>)"><?= $cUnpaid ?></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(H<?= $startRowCons ?>:H<?= $endRowCons ?>)"><?= $cExp ?></td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=SUM(I<?= $startRowCons ?>:I<?= $endRowCons ?>)"><?= $cProfit ?></td>
                </tr>
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
$search_query     = trim($_GET['search'] ?? '');
$page             = max(1, (int)($_GET['page'] ?? 1));
$limit            = 6;
$offset           = ($page - 1) * $limit;

$projectsList = $pdo->query("SELECT id, nama_projek, klien FROM projects ORDER BY nama_projek ASC")->fetchAll();

$sqlGrand = "
    SELECT p.*, 
           COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status = 'Paid'), 0) AS realized_paid,
           COALESCE((SELECT SUM(i.jumlah_tagihan) FROM invoices i WHERE i.project_id = p.id AND i.status != 'Paid'), 0) AS unpaid_piutang,
           COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
    FROM projects p
    WHERE 1=1
";
$paramsGrand = [];
if ($selected_project !== 'All') {
    $sqlGrand .= " AND p.id = ?";
    $paramsGrand[] = $selected_project;
}
if (!empty($search_query)) {
    $sqlGrand .= " AND (p.nama_projek LIKE ? OR p.klien LIKE ?)";
    $paramsGrand[] = "%$search_query%";
    $paramsGrand[] = "%$search_query%";
}

$stmtGrand = $pdo->prepare($sqlGrand);
$stmtGrand->execute($paramsGrand);
$allGrandReports = $stmtGrand->fetchAll();

$grandBudget       = 0;
$grandRealizedPaid = 0;
$grandUnpaid       = 0;
$grandExpense      = 0;

foreach ($allGrandReports as $r) {
    $grandExpense      += $r['total_expense'];
    $grandUnpaid       += $r['unpaid_piutang'];
    $grandRealizedPaid += $r['realized_paid'];

    if (!in_array($r['status'], ['On Hold', 'Planning'])) {
        $grandBudget += $r['budget'];
    }
}
$grandNetProfit = $grandRealizedPaid - $grandExpense;

$totalRows  = count($allGrandReports);
$totalPages = max(1, ceil($totalRows / $limit));

$sqlPaged = $sqlGrand . " ORDER BY p.created_at DESC LIMIT $limit OFFSET $offset";
$stmtPaged = $pdo->prepare($sqlPaged);
$stmtPaged->execute($paramsGrand);
$reports = $stmtPaged->fetchAll();

if ($selected_project !== 'All') {
    $stmtExpDetail = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran DESC");
    $stmtExpDetail->execute([$selected_project]);
    $expensesDetails = $stmtExpDetail->fetchAll();
} else {
    $expensesDetails = [];
}
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
        .pdf-summary-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 15px !important; }
        .pdf-summary-table th { background-color: #047857 !important; color: #ffffff !important; font-weight: 800 !important; text-transform: uppercase !important; font-size: 8.5px !important; padding: 6px 8px !important; border: 1px solid #047857 !important; text-align: center !important; }
        .pdf-summary-table td { font-weight: 800 !important; color: #0f172a !important; font-size: 10px !important; padding: 6px 8px !important; border: 1px solid #cbd5e1 !important; text-align: center !important; background-color: #f8fafc !important; }
        .pdf-badge { border: 1px solid #047857 !important; background: #ecfdf5 !important; padding: 1px 5px !important; font-weight: 800 !important; font-size: 7.5px !important; text-transform: uppercase !important; white-space: nowrap !important; color: #047857 !important; border-radius: 3px !important; }
        .pdf-signatures { display: flex !important; justify-content: space-between !important; margin-top: 30px !important; page-break-inside: avoid !important; width: 100% !important; }
        .pdf-sig-box { text-align: center !important; width: 30% !important; font-size: 9.5px !important; color: #334155 !important; }
        .pdf-sig-line { margin-top: 45px !important; font-weight: 800 !important; text-decoration: underline !important; color: #0f172a !important; }
    }
</style>

<!-- PDF Print Template Area -->
<div id="print-area-pdf">
    <div class="print-header-pdf">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="/website%20manajemen%20projek/assets/images/logo.jpg" alt="Logo PT CTC" class="print-logo-img">
                <div>
                    <h1 style="font-size: 13pt; font-weight: 900; color: #0f172a; margin: 0; text-transform: uppercase;">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p style="font-size: 8.5pt; font-weight: 700; color: #047857; margin: 2px 0 0 0;">Enterprise IT Solution, Software Development & Financial Control</p>
                    <p style="font-size: 7.5pt; color: #64748b; margin: 2px 0 0 0;">Perumahan Cahaya Garuda Residence Kav. 31, Bojongsari, Kota Depok, Jawa Barat |</p>
                    <p style="font-size: 7.5pt; color: #64748b; margin: 1px 0 0 0;">Email: ciptateknologicendekia@gmail.com | Website: https://ctc-nusantara.com</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 8pt; color: #475569;">
                <span style="font-weight: 900; color: #047857; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; text-transform: uppercase;">CONFIDENTIAL AUDIT</span>
                <p style="margin-top: 4px;">Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; padding-top: 6px;">
            <h2 style="font-size: 10pt; font-weight: 900; text-transform: uppercase; color: #0f172a; border-bottom: 2px solid #0f172a; display: inline-block; padding-bottom: 2px; margin: 0;">
                <?= $selected_project === 'All' ? 'LAPORAN KONSOLIDASI KEUANGAN SELURUH PROYEK' : 'LAPORAN DETAIL KEUANGAN PROYEK' ?>
            </h2>
        </div>
    </div>

    <table class="pdf-summary-table">
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
                <td>Rp <?= number_format($grandRealizedPaid, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($grandExpense, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($grandNetProfit, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($grandUnpaid, 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <table class="pdf-table">
        <thead>
            <tr>
                <th>Nama Proyek</th>
                <th>Klien</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: right;">Nilai Kontrak</th>
                <th style="text-align: right;">Paid (Dana Masuk)</th>
                <th style="text-align: right;">Unpaid (Piutang)</th>
                <th style="text-align: right;">Pengeluaran</th>
                <th style="text-align: right;">Net Profit Real</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #94a3b8; font-style: italic;">Data laporan tidak ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reports as $r): 
                    $isHold = in_array($r['status'], ['On Hold', 'Planning']);
                    $paidRevenue = $r['realized_paid'];
                    $unpaid = $r['unpaid_piutang'];
                    $expense = $r['total_expense'];
                    $netProfitReal = $paidRevenue - $expense;
                ?>
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($r['nama_projek']) ?></td>
                        <td><?= htmlspecialchars($r['klien']) ?></td>
                        <td style="text-align: center;">
                            <span class="pdf-badge"><?= htmlspecialchars($r['status']) ?></span>
                        </td>
                        <td style="text-align: right;">
                            <?= $isHold ? 'Rp 0 (Hold)' : 'Rp ' . number_format($r['budget'], 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: bold; color: #047857;">
                            Rp <?= number_format($paidRevenue, 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; color: #64748b;">
                            Rp <?= number_format($unpaid, 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; color: #b45309;">
                            Rp <?= number_format($expense, 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; font-weight: bold; color: <?= $netProfitReal >= 0 ? '#047857' : '#dc2626' ?>;">
                            Rp <?= number_format($netProfitReal, 0, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($selected_project !== 'All'): ?>
        <div style="margin-top: 15px;">
            <h3 style="font-size: 10pt; font-weight: 800; color: #0f172a; margin-bottom: 6px;">Item Pengeluaran Operasional Proyek</h3>
            <table class="pdf-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Biaya (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expensesDetails)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; color: #94a3b8;">Belum ada catatan biaya operasional pada proyek ini.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expensesDetails as $ex): ?>
                            <tr>
                                <td><?= date('d M Y', strtotime($ex['tanggal_pengeluaran'])) ?></td>
                                <td style="font-weight: 600;"><?= htmlspecialchars($ex['kategori']) ?></td>
                                <td><?= htmlspecialchars($ex['keterangan'] ?: '-') ?></td>
                                <td style="text-align: right; font-weight: bold;">Rp <?= number_format($ex['jumlah_biaya'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="pdf-signatures">
        <div class="pdf-sig-box">
            <p><strong>Dibuat Oleh:</strong></p>
            <p class="pdf-sig-line">( Finance Staff )</p>
            <p style="font-size: 8.5px; margin-top: 2px;">PT. CTC Finance Dept</p>
        </div>
        <div class="pdf-sig-box">
            <p><strong>Diterima Oleh:</strong></p>
            <p class="pdf-sig-line">( Lead PM )</p>
            <p style="font-size: 8.5px; margin-top: 2px;">Project Manager</p>
        </div>
        <div class="pdf-sig-box">
            <p><strong>Disetujui Oleh:</strong></p>
            <p class="pdf-sig-line">( Executive VP )</p>
            <p style="font-size: 8.5px; margin-top: 2px;">VP Operations</p>
        </div>
    </div>
</div>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white relative">

    <!-- Top Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL 4 • LAPORAN KEUANGAN & PROFITABILITAS
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Laporan Keuangan & Profitabilitas</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Analisis realisasi dana masuk, pengeluaran operasional, dan margin profit PT. CTC.</p>
        </div>
        
        <div class="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap">
            <a href="index.php?project_id=<?= $selected_project ?>&search=<?= urlencode($search_query) ?>&export=excel" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-file-excel text-emerald-200 text-xs sm:text-sm"></i> Export Excel (.xls Formula Active)
            </a>
            <button onclick="window.print()" class="btn-press bg-slate-900 hover:bg-slate-800 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-print text-xs sm:text-sm"></i> Cetak PDF / Print
            </button>
        </div>
    </div>

    <!-- Stat Counter Cards Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">REALIZED REVENUE (PAID)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($grandRealizedPaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Dana terbayar di kas</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-amber-200 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">OPERATIONAL EXPENSE</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($grandExpense, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Realisasi pengeluaran</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">NET PROFIT REAL</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold font-mono <?= $grandNetProfit >= 0 ? 'text-emerald-600' : 'text-red-600' ?>">
                Rp <?= number_format($grandNetProfit, 0, ',', '.') ?>
            </h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-arrow-up text-[10px]"></i> Profit terbayar bersih</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">UNPAID / PIUTANG KLIEN</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-800 font-mono">Rp <?= number_format($grandUnpaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Tagihan belum lunas</span>
        </div>
    </div>

    <!-- Search Bar & Filter Scope Laporan Integrated -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <form method="GET" class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            <div class="relative w-full md:w-80">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari nama proyek / klien..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>

            <div class="flex items-center gap-2 w-full md:w-auto">
                <label class="text-xs font-bold text-slate-400 uppercase tracking-wider shrink-0 flex items-center gap-1.5">
                    <i class="fa-solid fa-filter text-emerald-600"></i> Scope:
                </label>
                <select name="project_id" onchange="this.form.submit()" class="input-glow w-full md:w-auto px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold bg-white text-slate-700 focus:outline-none">
                    <option value="All" <?= $selected_project === 'All' ? 'selected' : '' ?>>📊 Seluruh Proyek (Konsolidasi Portfolio)</option>
                    <?php foreach ($projectsList as $pl): ?>
                        <option value="<?= $pl['id'] ?>" <?= $selected_project == $pl['id'] ? 'selected' : '' ?>>
                            📂 <?= htmlspecialchars($pl['nama_projek']) ?> (<?= htmlspecialchars($pl['klien']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs">
                    Cari
                </button>

                <?php if ($selected_project !== 'All' || !empty($search_query)): ?>
                    <a href="index.php" class="btn-press px-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-xs font-semibold border border-red-200 transition flex items-center gap-1">
                        <i class="fa-solid fa-xmark"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-chart-line text-emerald-600"></i> <?= $selected_project === 'All' ? 'Laporan Profitabilitas Seluruh Proyek' : 'Ringkasan Keuangan Proyek' ?>
        </h3>

        <!-- Desktop View (Tabel) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[800px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">NAMA PROYEK</th>
                        <th class="py-3.5 px-4">KLIEN</th>
                        <th class="py-3.5 px-4 text-center">STATUS</th>
                        <th class="py-3.5 px-4 text-right">NILAI KONTRAK</th>
                        <th class="py-3.5 px-4 text-right">PAID (DANA MASUK)</th>
                        <th class="py-3.5 px-4 text-right">UNPAID (PIUTANG)</th>
                        <th class="py-3.5 px-4 text-right">PENGELUARAN</th>
                        <th class="py-3.5 px-4 text-right">NET PROFIT REAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                Data laporan tidak ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): 
                            $isHold = in_array($r['status'], ['On Hold', 'Planning']);
                            $paidRevenue = $r['realized_paid'];
                            $unpaid = $r['unpaid_piutang'];
                            $expense = $r['total_expense'];
                            $netProfitReal = $paidRevenue - $expense;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 font-bold text-slate-900"><?= htmlspecialchars($r['nama_projek']) ?></td>
                                <td class="py-3.5 px-4 text-slate-600 font-normal"><?= htmlspecialchars($r['klien']) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase inline-block border tracking-wider
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
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-800">
                                    <?php if ($isHold): ?>
                                        <span class="text-amber-600 font-normal">Rp 0 <span class="text-[10px] bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 font-semibold">Hold</span></span>
                                    <?php else: ?>
                                        Rp <?= number_format($r['budget'], 0, ',', '.') ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-600">
                                    Rp <?= number_format($paidRevenue, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-500">
                                    Rp <?= number_format($unpaid, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-amber-600">
                                    Rp <?= number_format($expense, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold <?= $netProfitReal >= 0 ? 'text-emerald-700' : 'text-red-600' ?>">
                                    Rp <?= number_format($netProfitReal, 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="md:hidden space-y-3">
            <?php if (empty($reports)): ?>
                <p class="text-center py-6 text-xs text-slate-400 font-normal">Data laporan tidak ditemukan.</p>
            <?php else: ?>
                <?php foreach ($reports as $r): 
                    $paidRevenue = $r['realized_paid'];
                    $expense = $r['total_expense'];
                    $netProfitReal = $paidRevenue - $expense;
                ?>
                    <div class="p-4 border border-slate-200/90 rounded-xl bg-slate-50/50 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <h4 class="font-bold text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($r['nama_projek']) ?></h4>
                                <p class="text-xs text-slate-500 font-normal">Klien: <?= htmlspecialchars($r['klien']) ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase bg-white border border-slate-200 text-slate-700 shrink-0">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </div>
                        <div class="pt-2 border-t border-slate-200/80 grid grid-cols-2 gap-2 text-xs font-mono">
                            <div>
                                <span class="text-slate-400 block font-sans text-[10px]">Paid Revenue:</span>
                                <span class="font-bold text-emerald-600">Rp <?= number_format($paidRevenue, 0, ',', '.') ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-sans text-[10px]">Net Profit:</span>
                                <span class="font-bold <?= $netProfitReal >= 0 ? 'text-emerald-700' : 'text-red-600' ?>">Rp <?= number_format($netProfitReal, 0, ',', '.') ?></span>
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
                    Menampilkan Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong> (Total <?= $totalRows ?> Proyek)
                </span>

                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?project_id=<?= $selected_project ?>&search=<?= urlencode($search_query) ?>&page=<?= $page - 1 ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?project_id=<?= $selected_project ?>&search=<?= urlencode($search_query) ?>&page=<?= $i ?>" class="btn-press px-3 py-1.5 rounded-xl border text-xs font-semibold transition <?= $page == $i ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?project_id=<?= $selected_project ?>&search=<?= urlencode($search_query) ?>&page=<?= $page + 1 ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
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

    <!-- RINCIAN EXPENSE SPESIFIK PROYEK WEB -->
    <?php if ($selected_project !== 'All'): ?>
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
            <h3 class="text-base font-bold text-slate-900 mb-4 flex items-center gap-2">
                <i class="fa-solid fa-receipt text-emerald-600"></i> Item Pengeluaran Operasional Proyek
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[600px]">
                    <thead>
                        <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                            <th class="py-3 px-4">TANGGAL</th>
                            <th class="py-3 px-4">KATEGORI</th>
                            <th class="py-3 px-4">KETERANGAN</th>
                            <th class="py-3 px-4 text-right">BIAYA (RP)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (empty($expensesDetails)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-6 text-slate-400 font-normal">Belum ada catatan biaya operasional pada proyek ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expensesDetails as $ex): ?>
                                <tr class="hover:bg-slate-50/80 transition">
                                    <td class="py-3 px-4 text-slate-500 font-normal whitespace-nowrap"><?= date('d M Y', strtotime($ex['tanggal_pengeluaran'])) ?></td>
                                    <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($ex['kategori']) ?></td>
                                    <td class="py-3 px-4 text-slate-600 font-normal"><?= htmlspecialchars($ex['keterangan'] ?: '-') ?></td>
                                    <td class="py-3 px-4 text-right font-bold font-mono text-slate-900">Rp <?= number_format($ex['jumlah_biaya'], 0, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>