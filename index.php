<?php
session_start();
require_once __DIR__ . '/config/database.php';

// 1. Proteksi Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. STRICT ROLE GUARD: Karyawan dilarang masuk ke Dashboard Admin
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: my_tasks.php");
    exit;
}

// Fetch Ringkasan Statistik
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
$totalBudget   = $pdo->query("SELECT SUM(budget) FROM projects")->fetchColumn() ?? 0;

// Perhitungan Realisasi Biaya Dinamis dari Tabel Expenses
$realisasiBiaya = $pdo->query("SELECT SUM(jumlah_biaya) FROM expenses")->fetchColumn() ?? 0; 
$profitMargin   = $totalBudget > 0 ? round((($totalBudget - $realisasiBiaya) / $totalBudget) * 100, 1) : 0;
$profitRp       = $totalBudget - $realisasiBiaya;

// 3. LOGIKA PAGINATION PROYEK
$limit = 5; // Maksimal 5 proyek per halaman
$page  = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$totalPages = $totalProjects > 0 ? (int)ceil($totalProjects / $limit) : 1;
if ($page > $totalPages) $page = $totalPages;

$offset = ($page - 1) * $limit;

// Fetch Daftar Proyek Terbatas per Halaman
$stmtProjects = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmtProjects->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmtProjects->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtProjects->execute();
$projects = $stmtProjects->fetchAll();

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- HEADER HERO BANNER -->
    <div class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white p-5 sm:p-6 rounded-2xl shadow-sm border border-emerald-900/30 mb-6 flex flex-col md:flex-row justify-between md:items-center gap-4">
        <!-- Background subtle glow -->
        <div class="absolute -top-20 -right-20 w-60 h-60 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-4 relative z-10 min-w-0">
            <img src="assets/images/logo.jpg" 
                 alt="Logo PT CTC" 
                 onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=CTC&background=10b981&color=fff';"
                 class="w-12 h-12 sm:w-14 sm:h-14 object-cover rounded-xl border border-emerald-400/30 p-0.5 bg-slate-900 shadow-md shrink-0">
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-300">
                        System Overview
                    </span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-white tracking-tight truncate">PT. Cipta Teknologi Cendekia</h1>
                <p class="text-xs text-slate-300 font-normal mt-0.5 truncate sm:whitespace-normal">Monitoring real-time progres, budget, dan profitabilitas proyek.</p>
            </div>
        </div>

        <div class="shrink-0 relative z-10 w-full md:w-auto">
            <a href="modules/projects/detail.php" 
               class="w-full md:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-semibold transition shadow-md shadow-emerald-600/20 active:scale-95 flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus text-[11px]"></i> Tambah Proyek Baru
            </a>
        </div>
    </div>

    <!-- METRIC SUMMARY CARDS GRID -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- Total Proyek -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow transition-shadow">
            <div class="flex justify-between items-start mb-2.5">
                <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Total Proyek</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900"><?= number_format($totalProjects, 0, ',', '.') ?></div>
                <p class="text-[11px] text-slate-500 font-medium mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-check text-emerald-500 text-[9px]"></i> Proyek terdaftar aktif
                </p>
            </div>
        </div>

        <!-- Total Budget -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow transition-shadow">
            <div class="flex justify-between items-start mb-2.5">
                <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Total Budget</span>
                <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900 truncate" title="Rp <?= number_format($totalBudget, 0, ',', '.') ?>">
                    Rp <?= number_format($totalBudget, 0, ',', '.') ?>
                </div>
                <p class="text-[11px] text-emerald-600 font-semibold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-arrow-up-right-dots text-[10px]"></i> Estimasi Nilai Kontrak
                </p>
            </div>
        </div>

        <!-- Realisasi Biaya -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow transition-shadow">
            <div class="flex justify-between items-start mb-2.5">
                <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Realisasi Biaya</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <div>
                <div class="text-xl font-bold text-slate-900 truncate" title="Rp <?= number_format($realisasiBiaya, 0, ',', '.') ?>">
                    Rp <?= number_format($realisasiBiaya, 0, ',', '.') ?>
                </div>
                <p class="text-[11px] text-slate-500 font-medium mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-money-bill-transfer text-amber-500 text-[10px]"></i> Pengeluaran operasional
                </p>
            </div>
        </div>

        <!-- Margin Profit -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-4 sm:p-5 shadow-sm hover:shadow transition-shadow">
            <div class="flex justify-between items-start mb-2.5">
                <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">Margin Profit</span>
                <div class="w-9 h-9 rounded-xl <?= $profitMargin >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' ?> flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl font-bold text-slate-900"><?= $profitMargin ?>%</div>
                <p class="text-[11px] <?= $profitRp >= 0 ? 'text-emerald-600' : 'text-red-600' ?> font-semibold mt-1 truncate">
                    Rp <?= number_format($profitRp, 0, ',', '.') ?> keuntungannya
                </p>
            </div>
        </div>

    </div>

    <!-- SEKSI DAFTAR PROYEK DENGAN PAGINATION -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-5 sm:p-6 shadow-sm">
        
        <div class="flex items-center justify-between mb-5 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900 tracking-tight">Daftar Proyek</h2>
                <p class="text-xs text-slate-400 font-normal">Menampilkan <?= count($projects) ?> dari total <?= $totalProjects ?> proyek aktif.</p>
            </div>
            <a href="modules/projects/detail.php" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 flex items-center gap-1.5">
                Kelola Proyek <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- 1. TABEL DESKTOP (Screen >= md) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 text-slate-400 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200/70">
                        <th class="py-3 px-4">Nama Proyek</th>
                        <th class="py-3 px-4">Klien</th>
                        <th class="py-3 px-4">Budget</th>
                        <th class="py-3 px-4">Deadline</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-normal">
                    <?php if (empty($projects)): ?>
                        <tr><td colspan="5" class="text-center py-8 text-slate-400">Belum ada proyek terdaftar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 font-semibold text-slate-800">
                                    <a href="modules/projects/detail.php?id=<?= $p['id'] ?>" class="hover:text-emerald-600 transition flex items-center gap-2">
                                        <i class="fa-solid fa-folder text-emerald-500/80 text-sm"></i>
                                        <?= htmlspecialchars($p['nama_projek']) ?>
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600"><?= htmlspecialchars($p['klien']) ?></td>
                                <td class="py-3.5 px-4 font-semibold text-slate-800">Rp <?= number_format($p['budget'], 0, ',', '.') ?></td>
                                <td class="py-3.5 px-4 text-slate-500"><?= date('d M Y', strtotime($p['deadline'])) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-md text-[9px] uppercase font-bold inline-block border tracking-wide
                                        <?php
                                            switch($p['status']) {
                                                case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                default: echo 'bg-amber-50 text-amber-700 border-amber-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 2. KARTU MOBILE (Screen < md) -->
        <div class="block md:hidden space-y-3">
            <?php if (empty($projects)): ?>
                <div class="text-center py-6 text-slate-400 text-xs">Belum ada proyek terdaftar.</div>
            <?php else: ?>
                <?php foreach ($projects as $p): ?>
                    <a href="modules/projects/detail.php?id=<?= $p['id'] ?>" 
                       class="block bg-slate-50/60 hover:bg-emerald-50/40 border border-slate-200/70 rounded-xl p-3.5 transition active:scale-[0.99]">
                        <div class="flex items-start justify-between gap-2 mb-1.5">
                            <h3 class="text-xs font-semibold text-slate-900 leading-snug line-clamp-1">
                                <?= htmlspecialchars($p['nama_projek']) ?>
                            </h3>
                            <span class="px-2 py-0.5 rounded text-[8px] uppercase font-bold shrink-0 border
                                <?php
                                    switch($p['status']) {
                                        case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                        case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                        default: echo 'bg-amber-50 text-amber-700 border-amber-200';
                                    }
                                ?>">
                                <?= htmlspecialchars($p['status']) ?>
                            </span>
                        </div>
                        
                        <div class="text-[11px] text-slate-500 mb-2.5">
                            Klien: <span class="text-slate-700 font-medium"><?= htmlspecialchars($p['klien']) ?></span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/50 text-[10px]">
                            <div>
                                <span class="text-slate-400 block font-medium uppercase text-[8px]">Budget</span>
                                <span class="font-bold text-slate-800 text-xs">Rp <?= number_format($p['budget'], 0, ',', '.') ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 block font-medium uppercase text-[8px]">Deadline</span>
                                <span class="font-medium text-slate-600"><?= date('d M Y', strtotime($p['deadline'])) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 3. KOMPONEN NAVIGASI PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-5 mt-4 border-t border-slate-100 text-xs">
                
                <span class="text-slate-500 text-[11px] font-medium text-center sm:text-left">
                    Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong>
                </span>

                <div class="flex items-center gap-1.5">
                    <!-- Tombol Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" 
                           class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-emerald-600 font-medium transition flex items-center gap-1">
                            <i class="fa-solid fa-chevron-left text-[9px]"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-slate-300 font-medium cursor-not-allowed flex items-center gap-1">
                            <i class="fa-solid fa-chevron-left text-[9px]"></i> Prev
                        </span>
                    <?php endif; ?>

                    <!-- Number Badges -->
                    <div class="hidden sm:flex items-center gap-1 px-1">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-bold flex items-center justify-center text-xs shadow-sm">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" 
                                   class="w-7 h-7 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-medium flex items-center justify-center text-xs transition">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- Tombol Next -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" 
                           class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-emerald-600 font-medium transition flex items-center gap-1">
                            Next <i class="fa-solid fa-chevron-right text-[9px]"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-lg border border-slate-100 bg-slate-50 text-slate-300 font-medium cursor-not-allowed flex items-center gap-1">
                            Next <i class="fa-solid fa-chevron-right text-[9px]"></i>
                        </span>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>