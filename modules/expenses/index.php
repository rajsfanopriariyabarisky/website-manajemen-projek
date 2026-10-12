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

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 min-h-screen w-full font-sans">
    
    <!-- Top Header Bar -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md border border-emerald-200">
                MODUL 3: TRACKING BIAYA
            </span>
            <h1 class="text-xl md:text-2xl font-bold text-slate-800 mt-2">Biaya Operasional Proyek</h1>
            <p class="text-xs text-slate-500 mt-0.5">Pencatatan realisasi pengeluaran, vendor, lisensi, dan infrastruktur PT. CTC.</p>
        </div>

        <button onclick="openExpenseModal()" class="w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 shrink-0">
            <i class="fa-solid fa-plus-circle"></i> Catat Pengeluaran Baru
        </button>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs font-semibold mb-6 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-base"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-xs font-semibold mb-6 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-base"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Stat Cards Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Realisasi Biaya</span>
            <h3 class="text-xl font-bold text-slate-800">Rp <?= number_format($totalAllExpenses, 0, ',', '.') ?></h3>
            <span class="text-[11px] text-emerald-600 font-semibold mt-1 block"><i class="fa-solid fa-wallet mr-1"></i> Akumulasi seluruh proyek</span>
        </div>

        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Total Transaksi</span>
            <h3 class="text-xl font-bold text-slate-800"><?= number_format($totalCountAll) ?> Transaksi</h3>
            <span class="text-[11px] text-slate-400 mt-1 block">Tercatat di sistem</span>
        </div>

        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm">
            <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block mb-1">Rata-Rata per Transaksi</span>
            <h3 class="text-xl font-bold text-amber-600">Rp <?= number_format($avgExpense, 0, ',', '.') ?></h3>
            <span class="text-[11px] text-amber-600 font-semibold mt-1 block"><i class="fa-solid fa-chart-line mr-1"></i> Estimasi pengeluaran rata-rata</span>
        </div>
    </div>

    <!-- Filter & Search Bar Integrated -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-6 shadow-sm">
        <form method="GET" class="flex flex-col md:flex-row items-center justify-between gap-3 w-full">
            
            <!-- Live Search Bar -->
            <div class="relative w-full md:w-80">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari proyek, klien, atau keterangan..." class="w-full pl-9 pr-4 py-2 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            </div>

            <!-- Dropdown Filters -->
            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto justify-end">
                <select name="project_id" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="All" <?= $filter_project === 'All' ? 'selected' : '' ?>>Semua Proyek</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_projek']) ?></option>
                    <?php endforeach; ?>
                </select>

                <select name="kategori" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <option value="All" <?= $filter_kategori === 'All' ? 'selected' : '' ?>>Semua Kategori</option>
                    <option value="Infrastruktur & Cloud" <?= $filter_kategori === 'Infrastruktur & Cloud' ? 'selected' : '' ?>>Infrastruktur & Cloud</option>
                    <option value="Lisensi Software & API" <?= $filter_kategori === 'Lisensi Software & API' ? 'selected' : '' ?>>Lisensi Software & API</option>
                    <option value="Vendor / Freelancer" <?= $filter_kategori === 'Vendor / Freelancer' ? 'selected' : '' ?>>Vendor / Freelancer</option>
                    <option value="Operasional & Tim" <?= $filter_kategori === 'Operasional & Tim' ? 'selected' : '' ?>>Operasional & Tim</option>
                    <option value="Desain & Assets" <?= $filter_kategori === 'Desain & Assets' ? 'selected' : '' ?>>Desain & Assets</option>
                </select>

                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-3 py-2 rounded-xl text-xs transition">
                    Cari
                </button>

                <?php if ($filter_project !== 'All' || $filter_kategori !== 'All' || !empty($search_query)): ?>
                    <a href="index.php" class="text-xs text-red-500 font-semibold hover:underline ml-1"><i class="fa-solid fa-xmark mr-1"></i> Reset</a>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- Main Table Card -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm mb-8">
        <h3 class="font-bold text-slate-800 text-base mb-5">Riwayat Transaksi Pengeluaran</h3>

        <!-- Desktop View (Tabel) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 uppercase font-bold text-[10px] border-b border-slate-200">
                        <th class="py-3 px-4">Tanggal</th>
                        <th class="py-3 px-4">Nama Proyek & Klien</th>
                        <th class="py-3 px-4">Kategori</th>
                        <th class="py-3 px-4">Keterangan / Deskripsi</th>
                        <th class="py-3 px-4 text-right">Jumlah Biaya</th>
                        <th class="py-3 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400 font-medium">
                                <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                                Belum ada data pengeluaran yang sesuai kriteria pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 text-slate-500 font-medium whitespace-nowrap">
                                    <i class="fa-regular fa-calendar text-slate-400 mr-1.5"></i> <?= date('d M Y', strtotime($e['tanggal_pengeluaran'])) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-800 text-xs leading-tight"><?= htmlspecialchars($e['nama_projek']) ?></p>
                                    <span class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5"><i class="fa-solid fa-building text-slate-300"></i> <?= htmlspecialchars($e['klien']) ?></span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold inline-block border
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
                                <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate">
                                    <?= htmlspecialchars($e['keterangan'] ?: '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-slate-800 whitespace-nowrap">
                                    Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="delete.php?id=<?= $e['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan biaya ini?')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-400 inline-flex items-center justify-center transition" title="Hapus Biaya">
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
                <p class="text-center py-6 text-xs text-slate-400">Belum ada data pengeluaran.</p>
            <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                    <div class="p-4 border border-slate-200 rounded-xl bg-slate-50 space-y-2">
                        <div class="flex justify-between items-start">
                            <div>
                                <h4 class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($e['nama_projek']) ?></h4>
                                <p class="text-[11px] text-slate-500">Klien: <?= htmlspecialchars($e['klien']) ?></p>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-white border border-slate-200 text-slate-700">
                                <?= htmlspecialchars($e['kategori']) ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-600 italic"><?= htmlspecialchars($e['keterangan'] ?: '-') ?></p>
                        <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-[11px]">
                            <span class="font-bold text-slate-800">Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?></span>
                            <a href="delete.php?id=<?= $e['id'] ?>" onclick="return confirm('Hapus biaya ini?')" class="text-red-500 font-bold">Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- FITUR PAGINASI NUMERIK -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex flex-col sm:flex-row justify-between items-center gap-3 border-t border-slate-100 pt-4">
                <span class="text-xs font-semibold text-slate-500">
                    Menampilkan Halaman <strong><?= $page ?></strong> dari <strong><?= $totalPages ?></strong> (Total <?= $totalRows ?> Transaksi Filtered)
                </span>

                <div class="flex items-center gap-1.5">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                            <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="px-3 py-1.5 rounded-lg border text-xs font-bold transition <?= $page == $i ? 'bg-emerald-600 text-white border-emerald-600' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search_query) ?>&project_id=<?= $filter_project ?>&kategori=<?= urlencode($filter_kategori) ?>" class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 transition">
                            Next <i class="fa-solid fa-chevron-right ml-1"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Modal Input Expense -->
<div id="expenseModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden relative">
        <div class="px-6 py-4 bg-emerald-950 text-white flex justify-between items-center">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-emerald-400"></i> Catat Biaya Operasional Baru
            </h3>
            <button onclick="closeExpenseModal()" class="text-emerald-300 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_expense" value="1">

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pilih Proyek Target *</label>
                <select name="project_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>">
                            <?= htmlspecialchars($proj['nama_projek']) ?> (<?= htmlspecialchars($proj['klien']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Kategori Pengeluaran *</label>
                <select name="kategori" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <option value="Infrastruktur & Cloud">Infrastruktur & Cloud Server</option>
                    <option value="Lisensi Software & API">Lisensi Software & API</option>
                    <option value="Vendor / Freelancer">Gaji Vendor / Freelancer</option>
                    <option value="Operasional & Tim">Operasional & Konsumsi Tim</option>
                    <option value="Desain & Assets">Pembelian Assets & Desain</option>
                    <option value="Lainnya">Lain-lain</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Jumlah Biaya (Rp) *</label>
                <input type="text" name="jumlah_biaya" id="input_biaya" required placeholder="Contoh: 1.500.000" onkeyup="formatRupiah(this)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tanggal Transaksi *</label>
                <input type="date" name="tanggal_pengeluaran" required value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Keterangan / Deskripsi</label>
                <textarea name="keterangan" rows="3" placeholder="Misal: Pembelian domain & SSL server AWS tahunan" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeExpenseModal()" class="px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition">Batal</button>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-md shadow-emerald-600/20 transition">Simpan Transaksi</button>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>x