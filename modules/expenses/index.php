<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Proteksi Role: Wajib Admin (Keamanan Finansial)
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../my_tasks.php");
    exit;
}

// Auto-Migration Schema: Pastikan tabel expenses tersedia
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `expenses` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `project_id` INT NOT NULL,
          `kategori` VARCHAR(100) NOT NULL,
          `keterangan` TEXT DEFAULT NULL,
          `jumlah_biaya` DECIMAL(15,2) NOT NULL,
          `tanggal_pengeluaran` DATE NOT NULL,
          FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

// Query Rekapitulasi Seluruh Proyek (Total Nilai Kontrak, Paid Cash, Total Expense, & Sisa Kas)
$sqlProjects = "
    SELECT 
        p.id AS project_id,
        p.nama_projek,
        p.klien,
        p.budget,
        COALESCE(NULLIF(p.persen_pajak, 0), 11.00) AS persen_pajak_final,
        COALESCE(NULLIF(p.total_nilai, 0), p.budget + ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS total_nilai_final,
        COALESCE((
            SELECT SUM(COALESCE(NULLIF(i.total_tagihan, 0), i.jumlah_tagihan + ROUND((i.jumlah_tagihan * COALESCE(NULLIF(i.persen_pajak, 0), 11.00))/100, 2))) 
            FROM invoices i 
            WHERE i.project_id = p.id AND LOWER(i.status) = 'paid'
        ), 0) AS total_paid_cash,
        COALESCE((
            SELECT SUM(e.jumlah_biaya) 
            FROM expenses e 
            WHERE e.project_id = p.id
        ), 0) AS total_expense,
        COALESCE((
            SELECT COUNT(*) 
            FROM expenses e 
            WHERE e.project_id = p.id
        ), 0) AS total_items_count
    FROM projects p
    ORDER BY p.created_at DESC
";

$allProjects = $pdo->query($sqlProjects)->fetchAll();

// Kalkulasi Status Kas per Proyek
$projectsList = [];
foreach ($allProjects as $p) {
    $paidCash  = floatval($p['total_paid_cash']);
    $expense   = floatval($p['total_expense']);
    $sisaKas   = $paidCash - $expense;

    if ($paidCash <= 0) {
        $kasStatus = 'NoCash'; // Belum Ada Dana Masuk
    } elseif ($sisaKas > 0) {
        $kasStatus = 'Aman';   // Kas Masih Siap Pakai
    } else {
        $kasStatus = 'Habis';  // Kas Terpakai Penuh
    }

    $p['kas_status'] = $kasStatus;
    $p['sisa_kas']   = max(0, $sisaKas);
    $projectsList[]  = $p;
}

// Metrics Totals Konsolidasi Perusahaan
$totalCompanyPaid    = (float)($pdo->query("SELECT SUM(COALESCE(NULLIF(total_tagihan, 0), jumlah_tagihan + ROUND((jumlah_tagihan * COALESCE(NULLIF(persen_pajak, 0), 11.00))/100, 2))) FROM invoices WHERE LOWER(status) = 'paid'")->fetchColumn() ?: 0);
$totalCompanyExpense = (float)($pdo->query("SELECT SUM(jumlah_biaya) FROM expenses")->fetchColumn() ?: 0);
$totalCompanySisaKas = max(0, $totalCompanyPaid - $totalCompanyExpense);
$totalCompanyItems   = (int)($pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn() ?: 0);

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
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white relative">
    
    <!-- Top Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    BIAYA OPERASIONAL
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Pengeluaran & Kontrol Kas Proyek</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Pantau rasio pengeluaran operasional per proyek berdasarkan dana masuk aktual dari klien.</p>
        </div>
    </div>

    <!-- Metric Counter Cards Overview Global -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-emerald-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Total Kas Masuk (Paid)</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs border border-emerald-100">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($totalCompanyPaid, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Total dana diterima</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-amber-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Total Realisasi Biaya</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs border border-amber-100">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($totalCompanyExpense, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Biaya operasional keluar</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-blue-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider">Sisa Saldo Kas Siap Pakai</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs border border-blue-100">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-blue-600 font-mono">Rp <?= number_format($totalCompanySisaKas, 0, ',', '.') ?></h3>
            <span class="text-xs text-blue-700 font-medium mt-1 block">Batas belanja perusahaan</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Catatan Biaya</span>
                <div class="w-7 h-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center text-xs border border-slate-200">
                    <i class="fa-solid fa-list-check"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono"><?= number_format($totalCompanyItems) ?> <span class="text-xs font-sans font-normal text-slate-500">Item</span></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Tercatat di sistem</span>
        </div>
    </div>

    <!-- Search & Instant Tab Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <div class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            <!-- Instant Search Input -->
            <div class="relative w-full md:w-80">
                <input type="text" id="projectSearchInput" onkeyup="applyExpenseFilters()" oninput="applyExpenseFilters()" placeholder="Cari nama proyek atau klien..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>

            <!-- Instant Filter Tabs (Zero Reload) -->
            <div class="flex items-center gap-1 bg-slate-100/90 p-1 rounded-xl w-full md:w-auto overflow-x-auto">
                <button type="button" onclick="setKasFilter('All', this)" class="kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-white text-emerald-800 shadow-xs whitespace-nowrap">
                    Semua Status Kas
                </button>
                <button type="button" onclick="setKasFilter('Aman', this)" class="kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 text-slate-500 hover:text-slate-800 bg-transparent shadow-none whitespace-nowrap">
                    Kas Aman
                </button>
                <button type="button" onclick="setKasFilter('Habis', this)" class="kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 text-slate-500 hover:text-slate-800 bg-transparent shadow-none whitespace-nowrap">
                    Kas Terpakai Penuh
                </button>
                <button type="button" onclick="setKasFilter('NoCash', this)" class="kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 text-slate-500 hover:text-slate-800 bg-transparent shadow-none whitespace-nowrap">
                    Belum Ada Kas
                </button>
            </div>
        </div>
    </div>

    <!-- Main Table Container: Daftar Proyek & Kontrol Biaya -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden mb-8">
        <div class="p-5 border-b border-slate-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-folder-tree text-emerald-600"></i> Rekapitulasi Biaya Operasional per Proyek
            </h3>
            <span class="text-xs text-slate-400 font-medium">Klik "Kelola Biaya" untuk rincian detail</span>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm table-fixed min-w-[980px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-5 w-[28%]">PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4 w-[16%] text-center">STATUS KAS</th>
                        <th class="py-3.5 px-5 w-[26%]">KAS MASUK VS EXPENSE</th>
                        <th class="py-3.5 px-5 w-[18%]">SISA KAS SIAP PAKAI</th>
                        <th class="py-3.5 px-5 w-[12%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="projectTableBody">
                    <?php if (empty($projectsList)): ?>
                        <tr id="noDataRow">
                            <td colspan="5" class="p-8 text-center text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <p class="font-bold text-slate-800 text-sm">Belum ada data proyek terdaftar.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr id="noDataRow" style="display: none;">
                            <td colspan="5" class="p-8 text-center text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <p class="font-bold text-slate-800 text-sm">Tidak ada proyek yang sesuai dengan kriteria filter.</p>
                            </td>
                        </tr>
                        <?php foreach ($projectsList as $p): 
                            $paid  = floatval($p['total_paid_cash']);
                            $exp   = floatval($p['total_expense']);
                            $sisa  = floatval($p['sisa_kas']);
                            $ratio = $paid > 0 ? min(100, round(($exp / $paid) * 100)) : 0;
                        ?>
                            <tr class="project-expense-row hover:bg-slate-50/70 transition-all duration-200 align-middle"
                                data-kas-status="<?= $p['kas_status'] ?>"
                                data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' . $p['klien'])) ?>">
                                
                                <!-- Proyek & Klien -->
                                <td class="py-4 px-5">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-snug truncate">
                                        <?= htmlspecialchars($p['nama_projek']) ?>
                                    </p>
                                    <p class="text-xs text-slate-500 font-normal truncate mt-0.5">
                                        <i class="fa-solid fa-building text-slate-400 mr-1 text-[11px]"></i>Klien: <span class="font-semibold text-slate-800"><?= htmlspecialchars($p['klien']) ?></span>
                                    </p>
                                </td>

                                <!-- Status Kas Badge -->
                                <td class="py-4 px-4 text-center">
                                    <?php if ($p['kas_status'] === 'Aman'): ?>
                                        <span class="px-2.5 py-1 text-[10px] uppercase font-extrabold rounded-full bg-emerald-50 text-emerald-700 border border-emerald-300 inline-block whitespace-nowrap shadow-2xs">
                                            <i class="fa-solid fa-shield-check mr-1"></i> Kas Aman
                                        </span>
                                    <?php elseif ($p['kas_status'] === 'Habis'): ?>
                                        <span class="px-2.5 py-1 text-[10px] uppercase font-extrabold rounded-full bg-amber-50 text-amber-700 border border-amber-300 inline-block whitespace-nowrap shadow-2xs">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Kas Terpakai Penuh
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 text-[10px] uppercase font-extrabold rounded-full bg-slate-100 text-slate-600 border border-slate-300 inline-block whitespace-nowrap shadow-2xs">
                                            <i class="fa-solid fa-lock mr-1"></i> Belum Ada Kas (Paid)
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Kas Masuk VS Expense (Rapi Stacked Layout) -->
                                <td class="py-4 px-5">
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-500 font-medium">Terpakai:</span>
                                            <span class="font-mono font-bold text-amber-600">Rp <?= number_format($exp, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="flex items-center justify-between text-[11px]">
                                            <span class="text-slate-500 font-medium">Dana Paid:</span>
                                            <span class="font-mono font-bold text-emerald-700">Rp <?= number_format($paid, 0, ',', '.') ?></span>
                                        </div>
                                        <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden border border-slate-200/70 mt-1">
                                            <div class="bg-gradient-to-r from-amber-500 to-emerald-500 h-full transition-all duration-500" style="width: <?= $ratio ?>%"></div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Sisa Kas Siap Pakai -->
                                <td class="py-4 px-5 font-mono">
                                    <div class="text-xs">
                                        <span class="text-[10px] text-slate-400 font-sans block uppercase font-bold tracking-wider mb-0.5">Sisa Saldo Kas:</span>
                                        <span class="font-extrabold text-sm <?= $sisa > 0 ? 'text-emerald-700' : 'text-slate-400' ?>">
                                            Rp <?= number_format($sisa, 0, ',', '.') ?>
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-slate-500 font-sans block mt-0.5 font-normal">Total Transaksi: <strong><?= $p['total_items_count'] ?> item</strong></span>
                                </td>

                                <!-- Tombol Aksi Kelola -->
                                <td class="py-4 px-5 text-center">
                                    <a href="detail.php?project_id=<?= $p['project_id'] ?>" class="btn-press px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition inline-flex items-center justify-center gap-1.5 shadow-xs whitespace-nowrap">
                                        <i class="fa-solid fa-sliders text-xs"></i> Kelola Biaya
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
let activeKasFilter = 'All';

function setKasFilter(filter, btnElement) {
    activeKasFilter = filter;
    
    // Reset styling semua tombol filter
    document.querySelectorAll('.kas-filter-btn').forEach(btn => {
        btn.className = 'kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 text-slate-500 hover:text-slate-800 bg-transparent shadow-none whitespace-nowrap';
    });

    // Terapkan style aktif sesuai tab yang diklik
    if (filter === 'All') {
        btnElement.className = 'kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-white text-emerald-800 shadow-xs whitespace-nowrap';
    } else if (filter === 'Aman') {
        btnElement.className = 'kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-emerald-600 text-white shadow-xs whitespace-nowrap';
    } else if (filter === 'Habis') {
        btnElement.className = 'kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-amber-500 text-white shadow-xs whitespace-nowrap';
    } else if (filter === 'NoCash') {
        btnElement.className = 'kas-filter-btn btn-press px-3 py-1.5 rounded-lg text-xs font-semibold transition-all duration-200 bg-slate-700 text-white shadow-xs whitespace-nowrap';
    }

    applyExpenseFilters();
}

function applyExpenseFilters() {
    const searchVal = document.getElementById('projectSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.project-expense-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-kas-status');
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesStatus = (activeKasFilter === 'All' || rowStatus === activeKasFilter);
        const matchesSearch = (searchVal === '' || rowSearch.includes(searchVal));

        if (matchesStatus && matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    const noDataRow = document.getElementById('noDataRow');
    if (noDataRow) {
        if (visibleCount === 0) {
            noDataRow.style.display = '';
        } else {
            noDataRow.style.display = 'none';
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>