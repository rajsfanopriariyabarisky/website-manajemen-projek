<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';
$success = '';

// Handle Tambah Pengeluaran Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $project_id          = $_POST['project_id'];
    $kategori            = trim($_POST['kategori']);
    $keterangan          = trim($_POST['keterangan']);
    $jumlah_biaya        = str_replace(['.', ','], '', $_POST['jumlah_biaya']);
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'];

    if (!empty($project_id) && !empty($kategori) && !empty($jumlah_biaya)) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO expenses (project_id, kategori, keterangan, jumlah_biaya, tanggal_pengeluaran) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$project_id, $kategori, $keterangan, $jumlah_biaya, $tanggal_pengeluaran]);
        $success = "Pengeluaran proyek berhasil dicatat!";
    } else {
        $error = "Mohon lengkapi seluruh kolom yang diwajibkan!";
    }
}

// Fetch Proyek untuk Dropdown Form
$projects = $pdo->query("SELECT id, nama_projek, klien, budget FROM projects ORDER BY nama_projek ASC")->fetchAll();

// Parameter Filter, Search, & Pagination
$filter_project  = $_GET['project_id'] ?? 'All';
$filter_kategori = $_GET['kategori'] ?? 'All';
$search_query    = trim($_GET['search'] ?? '');
$page            = max(1, (int)($_GET['page'] ?? 1));
$limit           = 7;
$offset          = ($page - 1) * $limit;

// Build Base Query Expenses
$queryBase = "
    FROM expenses e 
    JOIN projects p ON e.project_id = p.id 
    WHERE 1=1
";
$params = [];

if ($filter_project !== 'All') {
    $queryBase .= " AND e.project_id = ?";
    $params[] = $filter_project;
}

if ($filter_kategori !== 'All') {
    $queryBase .= " AND e.kategori = ?";
    $params[] = $filter_kategori;
}

if (!empty($search_query)) {
    $queryBase .= " AND (p.nama_projek LIKE ? OR p.klien LIKE ? OR e.keterangan LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

// Hitung Total Rows untuk Pagination
$stmtCount = $pdo->prepare("SELECT COUNT(*) " . $queryBase);
$stmtCount->execute($params);
$totalRows  = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRows / $limit));

// Fetch Data Paginasi Expenses
$queryExpenses = "SELECT e.*, p.nama_projek, p.klien, p.budget " . $queryBase . " ORDER BY e.tanggal_pengeluaran DESC, e.id DESC LIMIT $limit OFFSET $offset";
$stmt = $pdo->prepare($queryExpenses);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Metrics Statistics (Perhitungan Keseluruhan Database)
$totalAllExpenses = $pdo->query("SELECT SUM(jumlah_biaya) FROM expenses")->fetchColumn() ?: 0;
$totalCountAll    = $pdo->query("SELECT COUNT(*) FROM expenses")->fetchColumn() ?: 0;
$avgExpense       = $totalCountAll > 0 ? $totalAllExpenses / $totalCountAll : 0;

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
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white relative">
    
    <!-- Top Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL 3 • TRACKING BIAYA OPERASIONAL
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Biaya Operasional Proyek</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Pencatatan realisasi pengeluaran, vendor, lisensi, dan infrastruktur PT. CTC.</p>
        </div>

        <button onclick="openExpenseModal()" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 shrink-0">
            <i class="fa-solid fa-plus-circle text-xs sm:text-sm"></i> Catat Pengeluaran Baru
        </button>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Metric Counter Cards Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Realisasi Biaya</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($totalAllExpenses, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> Akumulasi seluruh proyek</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Transaksi</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono"><?= number_format($totalCountAll) ?> <span class="text-xs font-sans font-normal text-slate-500">Transaksi</span></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Tercatat di sistem</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-amber-200 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">Rata-Rata per Transaksi</span>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($avgExpense, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Estimasi pengeluaran rata-rata</span>
        </div>
    </div>

    <!-- Filter & Search Bar Integrated -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <form method="GET" class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            
            <!-- Live Search Bar -->
            <div class="relative w-full md:w-80">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari proyek, klien, atau keterangan..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>

            <!-- Dropdown Filters -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                <select name="project_id" onchange="this.form.submit()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold bg-white text-slate-700 focus:outline-none">
                    <option value="All" <?= $filter_project === 'All' ? 'selected' : '' ?>>Semua Proyek</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_projek']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="kategori" onchange="this.form.submit()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold bg-white text-slate-700 focus:outline-none">
                    <option value="All" <?= $filter_kategori === 'All' ? 'selected' : '' ?>>Semua Kategori</option>
                    <option value="Infrastruktur & Cloud" <?= $filter_kategori === 'Infrastruktur & Cloud' ? 'selected' : '' ?>>Infrastruktur & Cloud</option>
                    <option value="Lisensi Software & API" <?= $filter_kategori === 'Lisensi Software & API' ? 'selected' : '' ?>>Lisensi Software & API</option>
                    <option value="Vendor / Freelancer" <?= $filter_kategori === 'Vendor / Freelancer' ? 'selected' : '' ?>>Vendor / Freelancer</option>
                    <option value="Operasional & Tim" <?= $filter_kategori === 'Operasional & Tim' ? 'selected' : '' ?>>Operasional & Tim</option>
                    <option value="Desain & Assets" <?= $filter_kategori === 'Desain & Assets' ? 'selected' : '' ?>>Desain & Assets</option>
                </select>

                <button type="submit" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs">
                    Cari
                </button>

                <?php if ($filter_project !== 'All' || $filter_kategori !== 'All' || !empty($search_query)): ?>
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
            <i class="fa-solid fa-receipt text-emerald-600"></i> Riwayat Transaksi Pengeluaran
        </h3>

        <!-- Desktop View (Tabel) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">TANGGAL</th>
                        <th class="py-3.5 px-4">NAMA PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4">KATEGORI</th>
                        <th class="py-3.5 px-4">KETERANGAN / DESKRIPSI</th>
                        <th class="py-3.5 px-4 text-right">JUMLAH BIAYA</th>
                        <th class="py-3.5 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                Belum ada data pengeluaran yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 text-slate-500 font-normal whitespace-nowrap">
                                    <i class="fa-regular fa-calendar text-slate-400 mr-1.5 text-xs"></i> <?= date('d M Y', strtotime($e['tanggal_pengeluaran'])) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-tight mb-0.5"><?= htmlspecialchars($e['nama_projek']) ?></p>
                                    <span class="text-xs text-slate-400 font-normal flex items-center gap-1"><i class="fa-solid fa-building text-slate-300 text-[10px]"></i> <?= htmlspecialchars($e['klien']) ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase inline-block border tracking-wider whitespace-nowrap
                                        <?php
                                            switch($e['kategori']) {
                                                case 'Infrastruktur & Cloud': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                case 'Vendor / Freelancer': echo 'bg-purple-50 text-purple-700 border-purple-200'; break;
                                                case 'Lisensi Software & API': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'Desain & Assets': echo 'bg-pink-50 text-pink-700 border-pink-200'; break;
                                                default: echo 'bg-amber-50 text-amber-700 border-amber-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($e['kategori']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate font-normal">
                                    <?= htmlspecialchars($e['keterangan'] ?: '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-900 font-mono whitespace-nowrap">
                                    Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="delete.php?id=<?= $e['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan biaya ini?')" class="btn-press w-7 h-7 rounded-lg bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-400 inline-flex items-center justify-center transition" title="Hapus Biaya">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile View (Cards) -->
        <div class="md:hidden space-y-3">
            <?php if (empty($expenses)): ?>
                <p class="text-center py-6 text-xs text-slate-400 font-normal">Belum ada data pengeluaran.</p>
            <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                    <div class="p-4 border border-slate-200/90 rounded-xl bg-slate-50/50 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <div>
                                <h4 class="font-bold text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($e['nama_projek']) ?></h4>
                                <p class="text-xs text-slate-500 font-normal">Klien: <?= htmlspecialchars($e['klien']) ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase bg-white border border-slate-200 text-slate-700 shrink-0">
                                <?= htmlspecialchars($e['kategori']) ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-600 italic font-normal"><?= htmlspecialchars($e['keterangan'] ?: '-') ?></p>
                        <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-xs">
                            <span class="font-bold text-slate-900 font-mono">Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?></span>
                            <a href="delete.php?id=<?= $e['id'] ?>" onclick="return confirm('Hapus biaya ini?')" class="text-red-600 font-semibold hover:underline">Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- FITUR PAGINASI NUMERIK -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3 border-t border-slate-100 pt-4 text-xs text-slate-500">
                <span class="font-medium">
                    Menampilkan Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong> (Total <?= $totalRows ?> Transaksi)
                </span>

                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-300 bg-slate-50 cursor-not-allowed">
                            <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Prev
                        </span>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="btn-press px-3 py-1.5 rounded-xl border text-xs font-semibold transition <?= $page == $i ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
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

<!-- Modal Input Expense -->
<div id="expenseModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 p-6 pb-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-emerald-600"></i> Catat Biaya Operasional Baru
            </h3>
            <button onclick="closeExpenseModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 pt-2 space-y-3.5">
            <input type="hidden" name="add_expense" value="1">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Proyek Target <span class="text-red-500">*</span></label>
                <select name="project_id" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>">
                            <?= htmlspecialchars($proj['nama_projek']) ?> (<?= htmlspecialchars($proj['klien']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Kategori Pengeluaran <span class="text-red-500">*</span></label>
                <select name="kategori" required class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Infrastruktur & Cloud">Infrastruktur & Cloud Server</option>
                    <option value="Lisensi Software & API">Lisensi Software & API</option>
                    <option value="Vendor / Freelancer">Gaji Vendor / Freelancer</option>
                    <option value="Operasional & Tim">Operasional & Konsumsi Tim</option>
                    <option value="Desain & Assets">Pembelian Assets & Desain</option>
                    <option value="Lainnya">Lain-lain</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Jumlah Biaya (Rp) <span class="text-red-500">*</span></label>
                <input type="text" name="jumlah_biaya" id="input_biaya" required placeholder="Contoh: 1.500.000" onkeyup="formatRupiah(this)" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none placeholder:text-slate-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Transaksi <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_pengeluaran" required value="<?= date('Y-m-d') ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Keterangan / Deskripsi</label>
                <textarea name="keterangan" rows="3" placeholder="Misal: Pembelian domain & SSL server AWS tahunan" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeExpenseModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openExpenseModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
}

function closeExpenseModal() {
    document.getElementById('expenseModal').classList.add('hidden');
}

function formatRupiah(el) {
    let value = el.value.replace(/[^0-9]/g, '');
    if (value) {
        el.value = new Intl.NumberFormat('id-ID').format(value);
    } else {
        el.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>