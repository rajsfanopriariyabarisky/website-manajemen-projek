<?php
require_once __DIR__ . '/../../config/database.php';

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

// Filter Parameter
$filter_project  = $_GET['project_id'] ?? 'All';
$filter_kategori = $_GET['kategori'] ?? 'All';

// Build Query Expenses
$queryExpenses = "
    SELECT e.*, p.nama_projek, p.klien, p.budget 
    FROM expenses e 
    JOIN projects p ON e.project_id = p.id 
    WHERE 1=1
";
$params = [];

if ($filter_project !== 'All') {
    $queryExpenses .= " AND e.project_id = ?";
    $params[] = $filter_project;
}

if ($filter_kategori !== 'All') {
    $queryExpenses .= " AND e.kategori = ?";
    $params[] = $filter_kategori;
}

$queryExpenses .= " ORDER BY e.tanggal_pengeluaran DESC, e.id DESC";

$stmt = $pdo->prepare($queryExpenses);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Metrics Statistics
$totalAllExpenses = $pdo->query("SELECT SUM(jumlah_biaya) FROM expenses")->fetchColumn() ?: 0;
$totalCount       = count($expenses);
$avgExpense       = $totalCount > 0 ? $totalAllExpenses / $totalCount : 0;

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <!-- Top Header Bar -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-emerald-700 font-semibold text-xs tracking-wider uppercase mb-1">
                <i class="fa-solid fa-circle text-[8px]"></i> Modul 3
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Tracking Biaya Operasional Proyek</h2>
            <p class="text-slate-500 text-sm">Pencatatan realisasi pengeluaran, vendor, dan infrastruktur proyek PT. CTC</p>
        </div>

        <!-- Tombol Trigger Modal Tambah Pengeluaran -->
        <button onclick="openExpenseModal()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl flex items-center justify-center gap-2 transition shadow-md shadow-emerald-600/20 text-sm">
            <i class="fa-solid fa-plus-circle"></i> Catat Pengeluaran Baru
        </button>
    </div>

    <!-- Alert Success / Error -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-6 flex items-center justify-between">
            <span><i class="fa-solid fa-triangle-exclamation mr-2"></i> <?= $error ?></span>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm mb-6 flex items-center justify-between">
            <span><i class="fa-solid fa-circle-check mr-2"></i> <?= $success ?></span>
        </div>
    <?php endif; ?>

    <!-- Stat Cards Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center gap-4">
            <div class="p-3 bg-emerald-50 text-emerald-600 rounded-2xl text-xl">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Total Realisasi Biaya</span>
                <p class="text-2xl font-bold text-slate-800">Rp <?= number_format($totalAllExpenses, 0, ',', '.') ?></p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 text-blue-600 rounded-2xl text-xl">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Jumlah Transaksi Biaya</span>
                <p class="text-2xl font-bold text-slate-800"><?= number_format($totalCount) ?> Transaksi</p>
            </div>
        </div>

        <div class="bg-white border border-slate-200 p-5 rounded-2xl shadow-sm flex items-center gap-4">
            <div class="p-3 bg-amber-50 text-amber-600 rounded-2xl text-xl">
                <i class="fa-solid fa-chart-line-down"></i>
            </div>
            <div>
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider block">Rata-Rata per Transaksi</span>
                <p class="text-2xl font-bold text-slate-800">Rp <?= number_format($avgExpense, 0, ',', '.') ?></p>
            </div>
        </div>
    </div>

    <!-- Main Table Card & Filters -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        
        <!-- Filter Header Bar -->
        <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-4 mb-6 pb-5 border-b border-slate-100">
            <h3 class="font-bold text-slate-800 text-lg">Riwayat Transaksi Pengeluaran</h3>

            <form method="GET" class="flex flex-wrap items-center gap-3">
                <!-- Filter Proyek -->
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-400 uppercase">Proyek:</label>
                    <select name="project_id" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="All" <?= $filter_project === 'All' ? 'selected' : '' ?>>Semua Proyek</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $filter_project == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama_projek']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Filter Kategori -->
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-400 uppercase">Kategori:</label>
                    <select name="kategori" onchange="this.form.submit()" class="px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-semibold bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <option value="All" <?= $filter_kategori === 'All' ? 'selected' : '' ?>>Semua Kategori</option>
                        <option value="Infrastruktur & Cloud" <?= $filter_kategori === 'Infrastruktur & Cloud' ? 'selected' : '' ?>>Infrastruktur & Cloud</option>
                        <option value="Lisensi Software & API" <?= $filter_kategori === 'Lisensi Software & API' ? 'selected' : '' ?>>Lisensi Software & API</option>
                        <option value="Vendor / Freelancer" <?= $filter_kategori === 'Vendor / Freelancer' ? 'selected' : '' ?>>Vendor / Freelancer</option>
                        <option value="Operasional & Tim" <?= $filter_kategori === 'Operasional & Tim' ? 'selected' : '' ?>>Operasional & Tim</option>
                        <option value="Desain & Assets" <?= $filter_kategori === 'Desain & Assets' ? 'selected' : '' ?>>Desain & Assets</option>
                    </select>
                </div>

                <?php if ($filter_project !== 'All' || $filter_kategori !== 'All'): ?>
                    <a href="index.php" class="text-xs text-red-500 font-semibold hover:underline ml-2"><i class="fa-solid fa-xmark mr-1"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabel Log Expenses -->
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-xs uppercase bg-slate-100/80 text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Nama Proyek & Klien</th>
                        <th class="px-4 py-3.5">Kategori</th>
                        <th class="px-4 py-3.5">Keterangan / Deskripsi</th>
                        <th class="px-4 py-3.5 text-right">Jumlah Biaya</th>
                        <th class="px-4 py-3.5 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-10 text-slate-400 font-medium">
                                <i class="fa-solid fa-receipt text-3xl mb-2 text-slate-300 block"></i>
                                Belum ada catatan pengeluaran. Klik tombol "Catat Pengeluaran Baru" di atas untuk menambahkan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-4 text-xs font-medium text-slate-500">
                                    <i class="fa-regular fa-calendar text-slate-400 mr-1.5"></i> <?= date('d M Y', strtotime($e['tanggal_pengeluaran'])) ?>
                                </td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-800 text-xs leading-tight"><?= htmlspecialchars($e['nama_projek']) ?></p>
                                    <span class="text-[11px] text-slate-400 flex items-center gap-1 mt-0.5"><i class="fa-solid fa-building text-slate-300"></i> <?= htmlspecialchars($e['klien']) ?></span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold inline-block
                                        <?php
                                            switch($e['kategori']) {
                                                case 'Infrastruktur & Cloud': echo 'bg-blue-50 text-blue-700 border border-blue-200'; break;
                                                case 'Vendor / Freelancer': echo 'bg-purple-50 text-purple-700 border border-purple-200'; break;
                                                case 'Lisensi Software & API': echo 'bg-emerald-50 text-emerald-700 border border-emerald-200'; break;
                                                case 'Desain & Assets': echo 'bg-pink-50 text-pink-700 border border-pink-200'; break;
                                                default: echo 'bg-amber-50 text-amber-700 border border-amber-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($e['kategori']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600 max-w-xs truncate">
                                    <?= htmlspecialchars($e['keterangan'] ?: '-') ?>
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-slate-800">
                                    Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="delete.php?id=<?= $e['id'] ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan biaya ini?')" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-400 inline-flex items-center justify-center transition" title="Hapus Biaya">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
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

<!-- Modal Input Expense (Pop-up Interactive) -->
<div id="expenseModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden transform transition-all">
        <!-- Modal Header -->
        <div class="px-6 py-4 bg-emerald-950 text-white flex justify-between items-center">
            <h3 class="font-bold text-base flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-emerald-400"></i> Catat Biaya Operasional Baru
            </h3>
            <button onclick="closeExpenseModal()" class="text-emerald-300 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Modal Body Form -->
        <form action="" method="POST" class="p-6 space-y-4">
            <input type="hidden" name="add_expense" value="1">

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Pilih Proyek Target *</label>
                <select name="project_id" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>">
                            <?= htmlspecialchars($proj['nama_projek']) ?> (<?= htmlspecialchars($proj['klien']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Kategori Pengeluaran *</label>
                <select name="kategori" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:ring-2 focus:ring-emerald-500 focus:outline-none font-medium">
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
                <input type="text" name="jumlah_biaya" id="input_biaya" required placeholder="Contoh: 1.500.000" onkeyup="formatRupiah(this)" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-bold text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tanggal Transaksi *</label>
                <input type="date" name="tanggal_pengeluaran" required value="<?= date('Y-m-d') ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Keterangan / Deskripsi</label>
                <textarea name="keterangan" rows="3" placeholder="Misal: Pembelian domain & SSL server AWS tahunan" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm focus:ring-2 focus:ring-emerald-500 focus:outline-none"></textarea>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>