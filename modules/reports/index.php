<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Auto-Migration Schema: Pastikan kolom pajak pada tabel projects siap digunakan
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN persen_pajak DECIMAL(5,2) DEFAULT 11.00 AFTER budget");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN nominal_pajak DECIMAL(15,2) DEFAULT 0.00 AFTER persen_pajak");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN total_nilai DECIMAL(15,2) DEFAULT 0.00 AFTER nominal_pajak");
} catch (Exception $e) {}

// Handle Export Excel (.xls) Berbasis XML Excel Native dengan Active Formula
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $project_filter = $_GET['project_id'] ?? 'All';
    $search_filter  = trim($_GET['search'] ?? '');
    $filename       = "Pembukuan_Keuangan_PT_CTC_" . date('Y-m-d_H-i') . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    if ($project_filter !== 'All') {
        // Mode 1: Export Detail Pembukuan 1 Proyek Spesifik
        $stmtP = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmtP->execute([$project_filter]);
        $proj = $stmtP->fetch();

        if (!$proj) {
            echo "Proyek tidak ditemukan.";
            exit;
        }

        $dpp_val     = floatval($proj['budget'] ?? 0);
        $pct_pajak   = floatval($proj['persen_pajak'] ?? 11.00);
        $nom_pajak   = floatval($proj['nominal_pajak'] ?? 0) > 0 ? floatval($proj['nominal_pajak']) : round(($dpp_val * $pct_pajak) / 100, 2);
        $tot_kontrak = floatval($proj['total_nilai'] ?? 0) > 0 ? floatval($proj['total_nilai']) : ($dpp_val + $nom_pajak);

        $stmtE = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran ASC");
        $stmtE->execute([$project_filter]);
        $expensesList = $stmtE->fetchAll();

        $stmtInv = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY tanggal_tagihan ASC");
        $stmtInv->execute([$project_filter]);
        $invoicesList = $stmtInv->fetchAll();

        $calcTotalInv  = 0;
        $calcPaidRev   = 0;
        foreach ($invoicesList as $iv) {
            $iv_pct = floatval($iv['persen_pajak'] ?? $pct_pajak);
            $iv_nom = floatval($iv['nominal_pajak'] ?? 0) > 0 ? floatval($iv['nominal_pajak']) : round((floatval($iv['jumlah_tagihan']) * $iv_pct) / 100, 2);
            $iv_tot = floatval($iv['total_tagihan'] ?? 0) > 0 ? floatval($iv['total_tagihan']) : (floatval($iv['jumlah_tagihan']) + $iv_nom);

            $calcTotalInv += $iv_tot;
            if (strtolower($iv['status']) === 'paid') {
                $calcPaidRev += $iv_tot;
            }
        }
        $calcUnpaid    = max(0, $tot_kontrak - $calcPaidRev);
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
                <tr><td colspan="7" class="title-company">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="7" class="title-doc">PEMBUKUAN KEUANGAN & AUDIT LEDGER PROYEK</td></tr>
                <tr><td colspan="7" style="font-size: 9pt; color: #64748b;">Tanggal Ekspor: <?= date('d F Y H:i:s') ?> WIB</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="7">INFORMASI PROYEK & METRIK KONTRAK</td></tr>
                <tr>
                    <td><b>Nama Proyek:</b></td><td colspan="2"><?= htmlspecialchars($proj['nama_projek'] ?? '') ?></td>
                    <td><b>Klien / Status:</b></td><td colspan="3"><?= htmlspecialchars($proj['klien'] ?? '') ?> (<?= htmlspecialchars($proj['status'] ?? '') ?>)</td>
                </tr>
                <tr>
                    <td><b>Subtotal Proyek (DPP):</b></td>
                    <td colspan="2" class="num-bold"><?= $dpp_val ?></td>
                    <td><b>PPN (<?= number_format($pct_pajak, 1) ?>%):</b></td>
                    <td colspan="3" class="num-bold" style="color: #b45309;"><?= $nom_pajak ?></td>
                </tr>
                <tr>
                    <td><b>Total Nilai Kontrak (Inc. Pajak):</b></td>
                    <td colspan="6" class="num-bold" style="color: #047857; font-size: 12pt;"><?= $tot_kontrak ?></td>
                </tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-section"><td colspan="7">I. LEDGER INVOICE & PENERIMAAN DANA (REVENUE INC. PPN)</td></tr>
                <tr style="background-color: #e2e8f0; font-weight: bold; text-align: center;">
                    <th>No</th><th>Tanggal Tagihan</th><th>No. Invoice</th><th>Keterangan Termin</th><th>Status</th><th>Jumlah Tagihan (DPP)</th><th>Total Inc. PPN (Rp)</th>
                </tr>
                <?php 
                $startRowInv = 13;
                $rowInvCount = 0;
                if (!empty($invoicesList)): 
                    foreach ($invoicesList as $idx => $inv): 
                        $rowInvCount++;
                        $iv_pct = floatval($inv['persen_pajak'] ?? $pct_pajak);
                        $iv_nom = floatval($inv['nominal_pajak'] ?? 0) > 0 ? floatval($inv['nominal_pajak']) : round((floatval($inv['jumlah_tagihan']) * $iv_pct) / 100, 2);
                        $iv_tot = floatval($inv['total_tagihan'] ?? 0) > 0 ? floatval($inv['total_tagihan']) : (floatval($inv['jumlah_tagihan']) + $iv_nom);
                ?>
                    <tr>
                        <td align="center"><?= $idx + 1 ?></td>
                        <td align="center"><?= date('d/m/Y', strtotime($inv['tanggal_tagihan'])) ?></td>
                        <td align="center"><?= htmlspecialchars($inv['nomor_invoice']) ?></td>
                        <td><?= htmlspecialchars($inv['keterangan_termin']) ?></td>
                        <td align="center"><b><?= htmlspecialchars($inv['status']) ?></b></td>
                        <td class="num-curr"><?= (float)$inv['jumlah_tagihan'] ?></td>
                        <td class="num-curr"><?= $iv_tot ?></td>
                    </tr>
                <?php 
                    endforeach; 
                else: 
                ?>
                    <tr><td colspan="7" align="center" style="color: #94a3b8;">Belum ada data invoice terbit.</td></tr>
                <?php endif; ?>
                
                <?php $totalInvRow = $startRowInv + max(1, $rowInvCount); ?>
                <tr class="bg-total">
                    <td colspan="6" align="right"><b>TOTAL SELURUH TAGIHAN (INVOICES INC. PPN):</b></td>
                    <td class="num-bold" x:fmla="=SUM(G<?= $startRowInv ?>:G<?= $totalInvRow - 1 ?>)"><?= $calcTotalInv ?></td>
                </tr>
                <tr class="bg-total">
                    <td colspan="6" align="right" style="color: #047857;"><b>TOTAL DANA MASUK (REALIZED PAID REVENUE INC. PPN):</b></td>
                    <td class="num-bold" style="color: #047857;" x:fmla='=SUMIF(E<?= $startRowInv ?>:E<?= $totalInvRow - 1 ?>; "Paid"; G<?= $startRowInv ?>:G<?= $totalInvRow - 1 ?>)'><?= $calcPaidRev ?></td>
                </tr>
                <tr class="bg-total">
                    <td colspan="6" align="right" style="color: #b45309;"><b>TOTAL SISA PIUTANG / UNPAID (INC. PPN):</b></td>
                    <td class="num-bold" style="color: #b45309;"><?= $calcUnpaid ?></td>
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
                <tr class="bg-header"><td colspan="2">III. RINGKASAN REKAPITULASI KEUANGAN AKHIR</td></tr>
                <tr>
                    <td width="300">Total Realized Revenue (Dana Masuk Inc. PPN)</td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=G<?= $paidRevRow ?>"><?= $calcPaidRev ?></td>
                </tr>
                <tr>
                    <td>Total Realisasi Pengeluaran Operasional</td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=F<?= $totalExpRow ?>"><?= $calcTotalExp ?></td>
                </tr>
                <tr style="background-color: #d1fae5; font-size: 12pt;">
                    <td><b>NET PROFIT REAL (BERSIH DANA MASUK)</b></td>
                    <td class="num-bold" style="color: #047857; font-size: 12pt;" x:fmla="=G<?= $paidRevRow ?>-F<?= $totalExpRow ?>"><?= $calcNetProfit ?></td>
                </tr>
            </table>
        </body>
        </html>
        <?php
    } else {
        // Mode 2: Export Konsolidasi Seluruh Portofolio Proyek (DENGAN REVENUE INC PPN)
        $sqlExcel = "
            SELECT p.*, 
                   COALESCE(NULLIF(p.persen_pajak, 0), 11.00) AS persen_pajak_final,
                   COALESCE(NULLIF(p.nominal_pajak, 0), ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS nominal_pajak_final,
                   COALESCE(NULLIF(p.total_nilai, 0), p.budget + ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS total_nilai_final,
                   COALESCE((SELECT SUM(COALESCE(NULLIF(i.total_tagihan, 0), i.jumlah_tagihan + ROUND((i.jumlah_tagihan * COALESCE(NULLIF(i.persen_pajak, 0), 11.00))/100, 2))) FROM invoices i WHERE i.project_id = p.id AND LOWER(i.status) = 'paid'), 0) AS realized_paid,
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

        $cBudget = 0; $cPajak = 0; $cTotalVal = 0; $cPaid = 0; $cUnpaid = 0; $cExp = 0; $cProfit = 0;
        foreach ($allReports as $ar) {
            $dpp    = floatval($ar['budget']);
            $pajak  = floatval($ar['nominal_pajak_final']);
            $tot    = floatval($ar['total_nilai_final']);
            $paid   = floatval($ar['realized_paid']);
            $unpaid = max(0, $tot - $paid);
            $exp    = floatval($ar['total_expense']);

            $cBudget   += $dpp;
            $cPajak    += $pajak;
            $cTotalVal += $tot;
            $cPaid     += $paid;
            $cUnpaid   += $unpaid;
            $cExp      += $exp;
            $cProfit   += ($paid - $exp);
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
                <tr><td colspan="11" class="title-company">PT. CIPTA TEKNOLOGI CENDEKIA</td></tr>
                <tr><td colspan="11" class="title-doc">PEMBUKUAN KONSOLIDASI KEUANGAN SELURUH PORTOFOLIO PROYEK</td></tr>
                <tr><td colspan="11" style="font-size: 9pt; color: #64748b;">Tanggal Ekspor: <?= date('d F Y H:i:s') ?> WIB</td></tr>
            </table>
            <br>
            <table class="border-all" cellpadding="5">
                <tr class="bg-header"><td colspan="11">REKAPITULASI LEDGER FINANCIAL PORTOFOLIO</td></tr>
                <tr class="bg-sub-header">
                    <th>No</th><th>Nama Proyek</th><th>Klien</th><th>Status Proyek</th><th>Subtotal DPP (Rp)</th><th>PPN Pajak (Rp)</th><th>Total Inc. Pajak (Rp)</th><th>Realized Paid Inc PPN (Rp)</th><th>Unpaid / Piutang Inc PPN (Rp)</th><th>Operational Expense (Rp)</th><th>Net Profit Real (Rp)</th>
                </tr>
                <?php 
                $startRowCons = 8;
                $rowCountCons = count($allReports);
                foreach ($allReports as $idx => $r): 
                    $rowNum = $startRowCons + $idx;
                    $paid   = floatval($r['realized_paid']);
                    $dpp    = floatval($r['budget']);
                    $tot    = floatval($r['total_nilai_final']);
                    $unpaid = max(0, $tot - $paid);
                    $exp    = floatval($r['total_expense']);
                    $np     = $paid - $exp;
                ?>
                    <tr>
                        <td align="center"><?= $idx + 1 ?></td>
                        <td><b><?= htmlspecialchars($r['nama_projek']) ?></b></td>
                        <td><?= htmlspecialchars($r['klien']) ?></td>
                        <td align="center"><?= htmlspecialchars($r['status']) ?></td>
                        <td class="num-curr"><?= $dpp ?></td>
                        <td class="num-curr"><?= floatval($r['nominal_pajak_final']) ?></td>
                        <td class="num-curr"><?= $tot ?></td>
                        <td class="num-curr"><?= $paid ?></td>
                        <td class="num-curr"><?= $unpaid ?></td>
                        <td class="num-curr"><?= $exp ?></td>
                        <td class="num-bold" style="color: #047857;" x:fmla="=H<?= $rowNum ?>-J<?= $rowNum ?>"><?= $np ?></td>
                    </tr>
                <?php endforeach; ?>

                <?php $endRowCons = $startRowCons + max(0, $rowCountCons - 1); ?>
                <tr class="bg-total" style="font-size: 11pt;">
                    <td colspan="4" align="right"><b>TOTAL KONSOLIDASI PORTOFOLIO:</b></td>
                    <td class="num-bold" x:fmla="=SUM(E<?= $startRowCons ?>:E<?= $endRowCons ?>)"><?= $cBudget ?></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(F<?= $startRowCons ?>:F<?= $endRowCons ?>)"><?= $cPajak ?></td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=SUM(G<?= $startRowCons ?>:G<?= $endRowCons ?>)"><?= $cTotalVal ?></td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=SUM(H<?= $startRowCons ?>:H<?= $endRowCons ?>)"><?= $cPaid ?></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(I<?= $startRowCons ?>:I<?= $endRowCons ?>)"><?= $cUnpaid ?></td>
                    <td class="num-bold" style="color: #b45309;" x:fmla="=SUM(J<?= $startRowCons ?>:J<?= $endRowCons ?>)"><?= $cExp ?></td>
                    <td class="num-bold" style="color: #047857;" x:fmla="=SUM(K<?= $startRowCons ?>:K<?= $endRowCons ?>)"><?= $cProfit ?></td>
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

// SQL Query Utama: Mengkalkulasi Realized Paid Inc PPN
$sqlGrand = "
    SELECT p.*, 
           COALESCE(NULLIF(p.persen_pajak, 0), 11.00) AS persen_pajak_final,
           COALESCE(NULLIF(p.nominal_pajak, 0), ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS nominal_pajak_final,
           COALESCE(NULLIF(p.total_nilai, 0), p.budget + ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS total_nilai_final,
           COALESCE((SELECT SUM(COALESCE(NULLIF(i.total_tagihan, 0), i.jumlah_tagihan + ROUND((i.jumlah_tagihan * COALESCE(NULLIF(i.persen_pajak, 0), 11.00))/100, 2))) FROM invoices i WHERE i.project_id = p.id AND LOWER(i.status) = 'paid'), 0) AS realized_paid,
           COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id), 0) AS total_expense
    FROM projects p
    WHERE 1=1
";
$paramsGrand = [];
if ($selected_project !== 'All') {
    $sqlGrand .= " AND p.id = ?";
    $paramsGrand[] = intval($selected_project);
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
$grandPajak        = 0;
$grandTotalVal     = 0;
$grandRealizedPaid = 0;
$grandUnpaid       = 0;
$grandExpense      = 0;

foreach ($allGrandReports as $r) {
    $dpp          = floatval($r['budget']);
    $tot          = floatval($r['total_nilai_final']);
    $paid         = floatval($r['realized_paid']);
    $unpaid       = max(0, $tot - $paid);
    $exp          = floatval($r['total_expense']);

    $grandBudget       += $dpp;
    $grandPajak        += floatval($r['nominal_pajak_final']);
    $grandTotalVal     += $tot;
    $grandRealizedPaid += $paid;
    $grandUnpaid       += $unpaid;
    $grandExpense      += $exp;
}
$grandNetProfit = $grandRealizedPaid - $grandExpense;

$totalRows  = count($allGrandReports);
$totalPages = max(1, ceil($totalRows / $limit));

$sqlPaged = $sqlGrand . " ORDER BY p.created_at DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);
$stmtPaged = $pdo->prepare($sqlPaged);
$stmtPaged->execute($paramsGrand);
$reports = $stmtPaged->fetchAll();

if ($selected_project !== 'All') {
    $stmtExpDetail = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran DESC");
    $stmtExpDetail->execute([intval($selected_project)]);
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
        @page { size: A4 landscape; margin: 8mm 10mm 8mm 10mm; }
        .print-header-pdf { width: 100% !important; margin-bottom: 12px !important; padding-bottom: 6px !important; border-bottom: 3px double #047857 !important; }
        .print-logo-img { width: 48px !important; height: 48px !important; object-fit: cover !important; border-radius: 6px !important; }
        .pdf-table { width: 100% !important; border-collapse: collapse !important; font-size: 8.5px !important; margin-bottom: 12px !important; }
        .pdf-table th { background-color: #065f46 !important; color: #ffffff !important; font-weight: 800 !important; text-transform: uppercase !important; font-size: 8px !important; padding: 5px 4px !important; border: 1px solid #065f46 !important; }
        .pdf-table td { border: 1px solid #cbd5e1 !important; padding: 5px 5px !important; color: #1e293b !important; }
        .pdf-summary-table { width: 100% !important; border-collapse: collapse !important; margin-bottom: 12px !important; }
        .pdf-summary-table th { background-color: #047857 !important; color: #ffffff !important; font-weight: 800 !important; text-transform: uppercase !important; font-size: 8px !important; padding: 5px 6px !important; border: 1px solid #047857 !important; text-align: center !important; }
        .pdf-summary-table td { font-weight: 800 !important; color: #0f172a !important; font-size: 9px !important; padding: 5px 6px !important; border: 1px solid #cbd5e1 !important; text-align: center !important; background-color: #f8fafc !important; }
        .pdf-badge { border: 1px solid #047857 !important; background: #ecfdf5 !important; padding: 1px 4px !important; font-weight: 800 !important; font-size: 7px !important; text-transform: uppercase !important; white-space: nowrap !important; color: #047857 !important; border-radius: 3px !important; }
        .pdf-signatures { display: flex !important; justify-content: space-between !important; margin-top: 25px !important; page-break-inside: avoid !important; width: 100% !important; }
        .pdf-sig-box { text-align: center !important; width: 30% !important; font-size: 8.5px !important; color: #334155 !important; }
        .pdf-sig-line { margin-top: 40px !important; font-weight: 800 !important; text-decoration: underline !important; color: #0f172a !important; }
    }
</style>

<!-- PDF Print Template Area -->
<div id="print-area-pdf">
    <div class="print-header-pdf">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <img src="../../assets/images/logo.png" alt="Logo PT CTC" class="print-logo-img" onerror="this.onerror=null; this.src='../../assets/img/logo.png';">
                <div>
                    <h1 style="font-size: 12pt; font-weight: 900; color: #0f172a; margin: 0; text-transform: uppercase;">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p style="font-size: 8pt; font-weight: 700; color: #047857; margin: 2px 0 0 0;">Enterprise IT Solution, Software Development & Financial Control</p>
                    <p style="font-size: 7pt; color: #64748b; margin: 1px 0 0 0;">Email: ciptateknologicendekia@gmail.com | Website: https://ctc-nusantara.com</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 7.5pt; color: #475569;">
                <span style="font-weight: 900; color: #047857; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; text-transform: uppercase;">CONFIDENTIAL AUDIT</span>
                <p style="margin-top: 4px;">Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 8px; padding-top: 4px;">
            <h2 style="font-size: 9.5pt; font-weight: 900; text-transform: uppercase; color: #0f172a; border-bottom: 2px solid #0f172a; display: inline-block; padding-bottom: 2px; margin: 0;">
                <?= $selected_project === 'All' ? 'LAPORAN KONSOLIDASI KEUANGAN SELURUH PROYEK' : 'LAPORAN DETAIL KEUANGAN PROYEK' ?>
            </h2>
        </div>
    </div>

    <table class="pdf-summary-table">
        <thead>
            <tr>
                <th>SUBTOTAL PROYEK (DPP)</th>
                <th>TOTAL PAJAK (PPN)</th>
                <th>REALIZED REVENUE (INC PPN)</th>
                <th>OPERATIONAL EXPENSE</th>
                <th>NET PROFIT BERSIH REAL</th>
                <th>PIUTANG UNPAID (INC PPN)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Rp <?= number_format($grandBudget, 0, ',', '.') ?></td>
                <td>Rp <?= number_format($grandPajak, 0, ',', '.') ?></td>
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
                <th style="text-align: right;">Subtotal DPP</th>
                <th style="text-align: right;">PPN Pajak</th>
                <th style="text-align: right;">Total Inc. Pajak</th>
                <th style="text-align: right;">Paid (Inc. PPN)</th>
                <th style="text-align: right;">Unpaid (Inc. PPN)</th>
                <th style="text-align: right;">Pengeluaran</th>
                <th style="text-align: right;">Net Profit Real</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr>
                    <td colspan="10" style="text-align: center; color: #94a3b8; font-style: italic;">Data laporan tidak ditemukan.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($reports as $r): 
                    $dpp_val       = floatval($r['budget']);
                    $pct_pajak     = floatval($r['persen_pajak_final']);
                    $nom_pajak     = floatval($r['nominal_pajak_final']);
                    $tot_kontrak   = floatval($r['total_nilai_final']);
                    
                    $paidRevenue   = floatval($r['realized_paid']);
                    $unpaid        = max(0, $tot_kontrak - $paidRevenue);
                    $expense       = floatval($r['total_expense']);
                    $netProfitReal = $paidRevenue - $expense;
                ?>
                    <tr>
                        <td style="font-weight: bold; color: #0f172a;"><?= htmlspecialchars($r['nama_projek']) ?></td>
                        <td><?= htmlspecialchars($r['klien']) ?></td>
                        <td style="text-align: center;">
                            <span class="pdf-badge"><?= htmlspecialchars($r['status']) ?></span>
                        </td>
                        <td style="text-align: right;">
                            Rp <?= number_format($dpp_val, 0, ',', '.') ?>
                        </td>
                        <td style="text-align: right; color: #b45309;">
                            Rp <?= number_format($nom_pajak, 0, ',', '.') ?> (<?= number_format($pct_pajak, 1) ?>%)
                        </td>
                        <td style="text-align: right; font-weight: bold;">
                            Rp <?= number_format($tot_kontrak, 0, ',', '.') ?>
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
        <div style="margin-top: 12px;">
            <h3 style="font-size: 9pt; font-weight: 800; color: #0f172a; margin-bottom: 5px;">Item Pengeluaran Operasional Proyek</h3>
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
            <p style="font-size: 8px; margin-top: 2px;">PT. CTC Finance Dept</p>
        </div>
        <div class="pdf-sig-box">
            <p><strong>Diterima Oleh:</strong></p>
            <p class="pdf-sig-line">( Lead PM )</p>
            <p style="font-size: 8px; margin-top: 2px;">Project Manager</p>
        </div>
        <div class="pdf-sig-box">
            <p><strong>Disetujui Oleh:</strong></p>
            <p class="pdf-sig-line">( Executive VP )</p>
            <p style="font-size: 8px; margin-top: 2px;">VP Operations</p>
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
                    LAPORAN KEUANGAN & PROFITABILITAS
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Laporan Keuangan & Profitabilitas</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Analisis realisasi dana masuk, pengeluaran operasional, pajak PPN, dan margin profit PT. CTC.</p>
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
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">REALIZED REVENUE (INC PPN)</span>
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
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">UNPAID / PIUTANG (INC PPN)</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-800 font-mono">Rp <?= number_format($grandUnpaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Tagihan belum lunas (Inc PPN)</span>
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
                    <option value="All" <?= $selected_project === 'All' ? 'selected' : '' ?>>Seluruh Proyek (Konsolidasi Portfolio)</option>
                    <?php foreach ($projectsList as $pl): ?>
                        <option value="<?= $pl['id'] ?>" <?= $selected_project == $pl['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($pl['nama_projek']) ?> (<?= htmlspecialchars($pl['klien']) ?>)
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
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[950px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">NAMA PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4 text-center">STATUS</th>
                        <th class="py-3.5 px-4 text-right">SUBTOTAL DPP</th>
                        <th class="py-3.5 px-4 text-right">PPN PAJAK</th>
                        <th class="py-3.5 px-4 text-right">TOTAL INC. PAJAK</th>
                        <th class="py-3.5 px-4 text-right">PAID (INC. PPN)</th>
                        <th class="py-3.5 px-4 text-right">UNPAID (PIUTANG)</th>
                        <th class="py-3.5 px-4 text-right">PENGELUARAN</th>
                        <th class="py-3.5 px-4 text-right">NET PROFIT REAL</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-chart-line"></i>
                                </div>
                                Data laporan tidak ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): 
                            $dpp_val       = floatval($r['budget']);
                            $pct_pajak     = floatval($r['persen_pajak_final']);
                            $nom_pajak     = floatval($r['nominal_pajak_final']);
                            $tot_kontrak   = floatval($r['total_nilai_final']);

                            $paidRevenue   = floatval($r['realized_paid']);
                            $unpaid        = max(0, $tot_kontrak - $paidRevenue);
                            $expense       = floatval($r['total_expense']);
                            $netProfitReal = $paidRevenue - $expense;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-tight mb-0.5"><?= htmlspecialchars($r['nama_projek']) ?></p>
                                    <span class="text-xs text-slate-400 font-normal">Klien: <strong class="text-slate-600 font-semibold"><?= htmlspecialchars($r['klien']) ?></strong></span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase inline-block border tracking-wider
                                        <?php
                                            switch($r['status']) {
                                                case 'Planning': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                                case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'On Hold': echo 'bg-red-50 text-red-700 border-red-200'; break;
                                                default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($r['status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-800">
                                    Rp <?= number_format($dpp_val, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono text-xs text-amber-600 font-semibold">
                                    Rp <?= number_format($nom_pajak, 0, ',', '.') ?>
                                    <span class="text-[10px] font-sans block text-amber-700">(PPN <?= number_format($pct_pajak, 1) ?>%)</span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900">
                                    Rp <?= number_format($tot_kontrak, 0, ',', '.') ?>
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
                    $paidRevenue   = floatval($r['realized_paid']);
                    $expense       = floatval($r['total_expense']);
                    $netProfitReal = $paidRevenue - $expense;
                    $tot_kontrak   = floatval($r['total_nilai_final']);
                    $unpaid        = max(0, $tot_kontrak - $paidRevenue);
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
                                <span class="text-slate-400 block font-sans text-[10px]">Total Inc. Pajak:</span>
                                <span class="font-bold text-slate-800">Rp <?= number_format($tot_kontrak, 0, ',', '.') ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-sans text-[10px]">Paid Revenue (Inc PPN):</span>
                                <span class="font-bold text-emerald-600">Rp <?= number_format($paidRevenue, 0, ',', '.') ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-sans text-[10px]">Unpaid / Piutang:</span>
                                <span class="font-bold text-slate-600">Rp <?= number_format($unpaid, 0, ',', '.') ?></span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-sans text-[10px]">Net Profit Real:</span>
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