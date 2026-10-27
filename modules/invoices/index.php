<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Params Filter & Pagination
$search_query  = trim($_GET['search'] ?? '');
$filter_status = $_GET['status_filter'] ?? 'All';

// Fetch All Projects with Invoices Summary
$sqlProjects = "
    SELECT 
        p.id AS project_id,
        p.nama_projek,
        p.klien,
        p.budget,
        p.tanggal_mulai,
        p.deadline,
        p.status AS project_status,
        COALESCE(SUM(i.jumlah_tagihan), 0) AS total_invoiced,
        COALESCE(SUM(CASE WHEN i.status = 'Paid' THEN i.jumlah_tagihan ELSE 0 END), 0) AS total_paid,
        COALESCE(SUM(CASE WHEN i.status != 'Paid' THEN i.jumlah_tagihan ELSE 0 END), 0) AS total_unpaid,
        COUNT(i.id) AS total_invoices_count,
        MAX(i.termin_ke) AS max_termin
    FROM projects p
    LEFT JOIN invoices i ON p.id = i.project_id
    WHERE 1=1
";

$params = [];

if (!empty($search_query)) {
    $sqlProjects .= " AND (p.nama_projek LIKE ? OR p.klien LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sqlProjects .= " GROUP BY p.id ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sqlProjects);
$stmt->execute($params);
$allProjects = $stmt->fetchAll();

// Filter Status Pembayaran di Level PHP
$filteredProjects = [];
foreach ($allProjects as $proj) {
    $budget    = (float)$proj['budget'];
    $totalPaid = (float)$proj['total_paid'];

    if ($budget > 0 && $totalPaid >= $budget) {
        $paymentStatus = 'Lunas';
    } elseif ($totalPaid > 0) {
        $paymentStatus = 'Partially Paid';
    } else {
        $paymentStatus = 'Unpaid';
    }

    $proj['payment_status'] = $paymentStatus;

    if ($filter_status === 'All' || $filter_status === $paymentStatus) {
        $filteredProjects[] = $proj;
    }
}

// Grand Totals Rekapitulasi Keseluruhan Perusahaan
$totalCompanyBudget   = $pdo->query("SELECT COALESCE(SUM(budget), 0) FROM projects")->fetchColumn();
$totalCompanyPaid     = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE status = 'Paid'")->fetchColumn();
$totalCompanyUnpaid   = $totalCompanyBudget - $totalCompanyPaid;
if ($totalCompanyUnpaid < 0) $totalCompanyUnpaid = 0;

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
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6, textarea { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
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
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 14px;">
                <img src="../../assets/images/logo.jpg" alt="Logo PT CTC" class="print-logo-img" onerror="this.style.display='none'">
                <div>
                    <h1 style="font-size: 13pt; font-weight: 900; color: #0f172a; margin: 0; text-transform: uppercase;">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p style="font-size: 8.5pt; font-weight: 700; color: #047857; margin: 2px 0 0 0;">Enterprise IT Solution, Software Development & Financial Control</p>
                </div>
            </div>
            <div style="text-align: right; font-size: 8pt; color: #475569;">
                <span style="font-weight: 900; color: #047857; background: #ecfdf5; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; text-transform: uppercase;">BILLING & INVOICE RECAP</span>
                <p style="margin-top: 4px;">Tanggal Cetak: <?= date('d F Y') ?></p>
            </div>
        </div>
        <div style="text-align: center; margin-top: 10px; padding-top: 6px;">
            <h2 style="font-size: 10pt; font-weight: 900; text-transform: uppercase; color: #0f172a; border-bottom: 2px solid #0f172a; display: inline-block; padding-bottom: 2px; margin: 0;">
                REKAPITULASI PENAGIHAN & STATUS INVOICE PER PROYEK
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
                    MODUL BILLING SYSTEM • INVOICE PER PROYEK
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Invoice & Penagihan Proyek</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Ringkasan status termin, penagihan pembayaran, dan pelunasan kuitansi per proyek.</p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <button onclick="window.print()" class="btn-press px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center justify-center gap-2">
                <i class="fa-solid fa-print text-xs sm:text-sm"></i> Cetak Rekap PDF
            </button>
        </div>
    </div>

    <!-- Stat Counter Cards Overview (Grand Totals All Projects) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Nilai Kontrak Proyek</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($totalCompanyBudget, 0, ',', '.') ?></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Akumulasi seluruh proyek</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-emerald-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Masuk Kas (Paid)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-check-double"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($totalCompanyPaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Total penerimaan lunas</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-amber-200 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Sisa Piutang Penagihan</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-clock"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($totalCompanyUnpaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Menunggu pembayaran termin</span>
        </div>
    </div>

    <!-- Filter & Search Bar Integrated -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <form method="GET" id="billingFilterForm" class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            <div class="relative w-full md:w-80">
                <input type="text" id="billingSearchInput" name="search" value="<?= htmlspecialchars($search_query) ?>" onkeyup="filterInvoiceTable()" placeholder="Cari nama proyek atau klien..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                    <a href="?status_filter=All&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'All' ? 'bg-white text-emerald-800 shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Semua Status</a>
                    <a href="?status_filter=Lunas&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'Lunas' ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Lunas 100%</a>
                    <a href="?status_filter=Partially Paid&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'Partially Paid' ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Partially Paid</a>
                    <a href="?status_filter=Unpaid&search=<?= urlencode($search_query) ?>" class="btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition <?= $filter_status === 'Unpaid' ? 'bg-amber-500 text-white shadow-xs' : 'text-slate-500 hover:text-slate-800' ?>">Unpaid</a>
                </div>

                <button type="submit" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs">
                    Cari
                </button>
            </div>
        </form>
    </div>

    <!-- Main Table Container: Daftar Proyek & Status Billing (Horizontal Table View) -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden mb-8">
        <div class="p-5 border-b border-slate-200/80 flex items-center justify-between">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-folder-tree text-emerald-600"></i> Daftar Proyek & Pengaturan Billing
            </h3>
            <span class="text-xs text-slate-400 font-medium">Tampilan Tabel Horizontal</span>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm table-fixed min-w-[980px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-[28%]">PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4 w-[16%] text-center">STATUS BILLING</th>
                        <th class="py-3.5 px-4 w-[22%]">PROGRESS PELUNASAN</th>
                        <th class="py-3.5 px-4 w-[20%]">RINGKASAN NOMINAL</th>
                        <th class="py-3.5 px-4 w-[14%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="billingTableBody">
                    <?php if (empty($filteredProjects)): ?>
                        <tr>
                            <td colspan="5" class="p-8 text-center text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <p class="font-bold text-slate-800 text-sm">Belum ada data proyek yang sesuai kriteria pencarian.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredProjects as $p): 
                            $pBudget = (float)$p['budget'];
                            $pPaid   = (float)$p['total_paid'];
                            $pUnpaid = $pBudget - $pPaid;
                            if ($pUnpaid < 0) $pUnpaid = 0;
                            $percent = $pBudget > 0 ? min(100, round(($pPaid / $pBudget) * 100)) : 0;
                        ?>
                            <tr class="invoice-row hover:bg-slate-50/70 transition align-middle"
                                data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' . $p['klien'])) ?>"
                                data-status="<?= htmlspecialchars($p['payment_status']) ?>">

                                <!-- Proyek & Klien -->
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-snug truncate">
                                        <?= htmlspecialchars($p['nama_projek']) ?>
                                    </p>
                                    <p class="text-xs text-slate-500 font-normal truncate mt-0.5">
                                        <i class="fa-solid fa-building text-slate-400 mr-1 text-[11px]"></i>Klien: <span class="font-semibold text-slate-800"><?= htmlspecialchars($p['klien']) ?></span>
                                    </p>
                                </td>

                                <!-- Status Billing Badge -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 text-[10px] uppercase font-extrabold rounded-full border tracking-wider inline-block whitespace-nowrap
                                        <?php
                                            switch($p['payment_status']) {
                                                case 'Lunas': echo 'bg-emerald-50 text-emerald-700 border-emerald-300'; break;
                                                case 'Partially Paid': echo 'bg-blue-50 text-blue-700 border-blue-300'; break;
                                                default: echo 'bg-amber-50 text-amber-700 border-amber-300';
                                            }
                                        ?>">
                                        <?= $p['payment_status'] === 'Lunas' ? 'LUNAS 100%' : $p['payment_status'] ?>
                                    </span>
                                </td>

                                <!-- Progress Pelunasan -->
                                <td class="py-3.5 px-4">
                                    <div class="flex justify-between text-xs font-semibold mb-1">
                                        <span class="text-slate-400 text-[10px] uppercase font-normal">Terbayar</span>
                                        <span class="text-emerald-700 font-bold font-mono text-xs"><?= $percent ?>% (Rp <?= number_format($pPaid, 0, ',', '.') ?>)</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200/80 p-0.5">
                                        <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-1 rounded-full transition-all duration-500" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </td>

                                <!-- Ringkasan Nominal -->
                                <td class="py-3.5 px-4 font-mono">
                                    <div class="text-xs">
                                        <span class="text-[10px] text-slate-400 font-sans block uppercase">Budget:</span>
                                        <span class="font-bold text-slate-900">Rp <?= number_format($pBudget, 0, ',', '.') ?></span>
                                    </div>
                                    <div class="text-xs mt-0.5">
                                        <span class="text-[10px] text-slate-400 font-sans block uppercase">Sisa Piutang:</span>
                                        <span class="font-semibold text-amber-600">Rp <?= number_format($pUnpaid, 0, ',', '.') ?></span>
                                    </div>
                                </td>

                                <!-- Tombol Aksi Kelola -->
                                <td class="py-3.5 px-4 text-center">
                                    <a href="detail.php?project_id=<?= $p['project_id'] ?>" class="btn-press px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1.5 shadow-xs">
                                        <i class="fa-solid fa-sliders text-xs"></i> Kelola Billing
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi 10 Data Per Halaman -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500 bg-slate-50/50">
            <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1.5" id="paginationControls"></div>
        </div>
    </div>
</main>

<script>
const rowsPerPage = 10;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    filterInvoiceTable();
});

function filterInvoiceTable() {
    const searchVal = document.getElementById('billingSearchInput').value.toLowerCase();
    const allRows   = Array.from(document.querySelectorAll('.invoice-row'));

    filteredRows = allRows.filter(row => {
        const searchData = row.getAttribute('data-search') || '';
        return searchData.includes(searchVal);
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.invoice-row');
    allRows.forEach(row => row.style.display = 'none');

    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / rowsPerPage) || 1;

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx   = startIdx + rowsPerPage;

    filteredRows.slice(startIdx, endIdx).forEach(row => {
        row.style.display = 'table-row';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd   = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} proyek`;

    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';

    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.type = 'button';
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.type = 'button';
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.type = 'button';
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>