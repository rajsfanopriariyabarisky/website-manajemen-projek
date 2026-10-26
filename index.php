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

// Fetch Ringkasan Statistik Utama
$totalProjects = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn() ?? 0;
$totalBudget   = $pdo->query("SELECT SUM(budget) FROM projects")->fetchColumn() ?? 0;

// Perhitungan Realisasi Biaya Dinamis dari Tabel Expenses
$realisasiBiaya = $pdo->query("SELECT SUM(jumlah_biaya) FROM expenses")->fetchColumn() ?? 0; 
$profitMargin   = $totalBudget > 0 ? round((($totalBudget - $realisasiBiaya) / $totalBudget) * 100, 1) : 0;
$profitRp       = $totalBudget - $realisasiBiaya;

// Metrik Tambahan Portofolio Keuangan & Status Proyek
$realizedRevenue = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE status = 'Paid'")->fetchColumn() ?? 0;
$unpaidRevenue   = $pdo->query("SELECT COALESCE(SUM(jumlah_tagihan), 0) FROM invoices WHERE status != 'Paid'")->fetchColumn() ?? 0;

$completedProjects  = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Completed'")->fetchColumn() ?? 0;
$inProgressProjects = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'In Progress'")->fetchColumn() ?? 0;
$onHoldProjects     = $pdo->query("SELECT COUNT(*) FROM projects WHERE status IN ('On Hold', 'Planning')")->fetchColumn() ?? 0;

// Perhitungan Persentase Proyek Selesai
$completionPercentage = $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100) : 0;

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

<style>
    /* Custom Micro-Animations & Glow Effects */
    .btn-press {
        transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, box-shadow 0.2s ease;
        will-change: transform;
    }
    .btn-press:active {
        transform: scale(0.97);
    }

    .card-dashboard {
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.2s ease, box-shadow 0.2s ease;
        will-change: transform;
    }
    .card-dashboard:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .hero-glow {
        background: radial-gradient(circle at 85% 20%, rgba(16, 185, 129, 0.22) 0%, transparent 60%);
    }

    /* Custom Progress Bar Motion */
    .progress-bar-fill {
        transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- HEADER HERO BANNER (Executive Deep Forest) -->
    <div class="relative overflow-hidden bg-gradient-to-r from-[#064e3b] via-[#043829] to-[#02241a] text-white p-6 sm:p-7 rounded-3xl shadow-md border border-emerald-800/60 mb-6 flex flex-col lg:flex-row justify-between lg:items-center gap-6">
        <!-- Background Ambient Glow -->
        <div class="absolute inset-0 hero-glow pointer-events-none"></div>
        <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

        <div class="flex items-center gap-4 sm:gap-5 relative z-10 min-w-0">
            <div class="relative shrink-0">
                <img src="assets/images/logo2.png" 
                     alt="Logo PT CTC" 
                     onerror="this.onerror=null; this.src='assets/images/logo2.png';"
                     class="w-12 h-12 sm:w-14 sm:h-14 object-contain filter drop-shadow-[0_3px_8px_rgba(0,0,0,0.4)] brightness-110">
            </div>
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="text-[10px] sm:text-xs font-extrabold uppercase tracking-wider text-emerald-300 bg-white/10 px-2.5 py-0.5 rounded-full border border-white/15 backdrop-blur-md">
                        RINGKASAN EKSEKUTIF & PEMBUKUAN
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight truncate">PT. Cipta Teknologi Cendekia</h1>
                <p class="text-xs sm:text-sm text-emerald-100/80 font-medium mt-0.5 truncate sm:whitespace-normal">
                    Pantau progres pengerjaan, anggaran proyek, dan realisasi profitabilitas perusahaan secara konsolidasi.
                </p>
            </div>
        </div>

        <!-- Action Buttons / Quick Link Navigation -->
        <div class="shrink-0 relative z-10 flex items-center gap-3 flex-wrap sm:flex-nowrap">
            <!-- Tombol Tambah Proyek Baru (Warna Putih Bersih + Teks Hijau Emerald) -->
            <a href="modules/projects/index.php" 
               class="btn-press px-4 py-2.5 bg-white hover:bg-emerald-50 text-emerald-950 font-extrabold rounded-2xl text-xs transition shadow-md shadow-emerald-950/30 flex items-center justify-center gap-2 border border-white/40">
                <i class="fa-solid fa-plus text-xs text-emerald-600"></i>
                <span>Tambah Proyek Baru</span>
            </a>
            
            <!-- Tombol Laporan Keuangan (Semi-Transparent White) -->
            <a href="modules/reports/index.php" 
               class="btn-press px-4 py-2.5 bg-white/10 hover:bg-white/20 text-white font-bold rounded-2xl text-xs backdrop-blur-md transition border border-white/20 flex items-center justify-center gap-2">
                <i class="fa-solid fa-chart-line text-xs text-emerald-300"></i>
                <span>Laporan Keuangan</span>
            </a>
        </div>
    </div>

    <!-- METRIK UTAMA: 4 KARTU STATISTIK UTAMA -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        
        <!-- 1. Total Proyek -->
        <div class="card-dashboard bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:border-emerald-300">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">TOTAL PROYEK</span>
                    <span class="text-[11px] text-slate-500 font-medium">Seluruh proyek terdaftar</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base shrink-0 border border-emerald-100">
                    <i class="fa-solid fa-folder-tree"></i>
                </div>
            </div>
            <div>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-mono tracking-tight">
                    <?= number_format($totalProjects, 0, ',', '.') ?>
                </div>
                <div class="flex items-center gap-2 mt-2 pt-2 border-t border-slate-100 text-[11px]">
                    <span class="text-emerald-700 font-bold flex items-center gap-1">
                        <i class="fa-solid fa-circle-check text-[10px]"></i> <?= $completedProjects ?> Selesai
                    </span>
                    <span class="text-slate-300">•</span>
                    <span class="text-blue-600 font-semibold"><?= $inProgressProjects ?> Berjalan</span>
                </div>
            </div>
        </div>

        <!-- 2. Total Budget / Nilai Kontrak -->
        <div class="card-dashboard bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:border-emerald-300">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">TOTAL ANGGARAN</span>
                    <span class="text-[11px] text-slate-500 font-medium">Nilai akumulasi kontrak</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-base shrink-0 border border-blue-100">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-extrabold text-slate-900 font-mono tracking-tight truncate" title="Rp <?= number_format($totalBudget, 0, ',', '.') ?>">
                    Rp <?= number_format($totalBudget, 0, ',', '.') ?>
                </div>
                <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-slate-100 text-[11px] text-blue-600 font-semibold">
                    <i class="fa-solid fa-file-contract text-[10px]"></i> Estimasi total pengerjaan
                </div>
            </div>
        </div>

        <!-- 3. Realisasi Biaya (Expenses) -->
        <div class="card-dashboard bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:border-amber-300">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">TOTAL PENGELUARAN</span>
                    <span class="text-[11px] text-slate-500 font-medium">Biaya operasional keluar</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-base shrink-0 border border-amber-100">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>
            <div>
                <div class="text-xl sm:text-2xl font-extrabold text-amber-600 font-mono tracking-tight truncate" title="Rp <?= number_format($realisasiBiaya, 0, ',', '.') ?>">
                    Rp <?= number_format($realisasiBiaya, 0, ',', '.') ?>
                </div>
                <div class="flex items-center gap-1.5 mt-2 pt-2 border-t border-slate-100 text-[11px] text-amber-700 font-semibold">
                    <i class="fa-solid fa-money-bill-transfer text-[10px]"></i> Pengeluaran operasional
                </div>
            </div>
        </div>

        <!-- 4. Margin Profit -->
        <div class="card-dashboard bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs hover:border-emerald-300">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">MARGIN PROFIT</span>
                    <span class="text-[11px] text-slate-500 font-medium">Estimasi sisa anggaran</span>
                </div>
                <div class="w-10 h-10 rounded-xl <?= $profitMargin >= 0 ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-600 border-red-100' ?> flex items-center justify-center text-base shrink-0 border">
                    <i class="fa-solid fa-chart-pie"></i>
                </div>
            </div>
            <div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 font-mono tracking-tight"><?= $profitMargin ?>%</span>
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-md uppercase tracking-wider <?= $profitRp >= 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                        <?= $profitRp >= 0 ? 'Untung' : 'Rugi' ?>
                    </span>
                </div>
                <div class="mt-2 pt-2 border-t border-slate-100 text-[11px] <?= $profitRp >= 0 ? 'text-emerald-700' : 'text-red-600' ?> font-bold truncate">
                    Keuntungan: Rp <?= number_format($profitRp, 0, ',', '.') ?>
                </div>
            </div>
        </div>

    </div>

    <!-- SEKSI ANALITIK DETIL & AKSES MODUL -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        
        <!-- Analytics Card 1: Arus Kas Realized vs Piutang -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 tracking-wider">ARUS KAS & BILLING</h3>
                        <p class="text-[11px] text-slate-400">Realisasi pembayaran invoice klien</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs border border-emerald-100">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                </div>
                <div class="space-y-3 my-2">
                    <div class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-100">
                        <span class="text-[10px] font-bold text-emerald-800 uppercase block mb-0.5">Dana Masuk (Invoice Terbayar / Paid)</span>
                        <span class="text-base font-extrabold text-emerald-700 font-mono">Rp <?= number_format($realizedRevenue, 0, ',', '.') ?></span>
                    </div>
                    <div class="p-3 bg-amber-50/60 rounded-xl border border-amber-100">
                        <span class="text-[10px] font-bold text-amber-800 uppercase block mb-0.5">Piutang Klien (Invoice Belum Lunas)</span>
                        <span class="text-base font-extrabold text-amber-700 font-mono">Rp <?= number_format($unpaidRevenue, 0, ',', '.') ?></span>
                    </div>
                </div>
            </div>
            <a href="modules/invoices/index.php" class="btn-press mt-3 text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50/70 hover:bg-emerald-100/70 px-3.5 py-2 rounded-xl transition flex items-center justify-between">
                <span>Buka Kelola Invoice & Penagihan</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Analytics Card 2: Progres Penyelesaian Portofolio -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 tracking-wider">PROGRES PORTOFOLIO</h3>
                        <p class="text-[11px] text-slate-400">Rasio status pengerjaan seluruh proyek</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xs border border-blue-100">
                        <i class="fa-solid fa-bars-progress"></i>
                    </div>
                </div>
                
                <div class="my-3">
                    <div class="flex justify-between items-center mb-1.5 text-xs font-bold">
                        <span class="text-slate-700">Persentase Proyek Selesai</span>
                        <span class="text-emerald-700 font-mono text-sm"><?= $completionPercentage ?>%</span>
                    </div>
                    <div class="w-full bg-slate-100 h-3 rounded-full overflow-hidden border border-slate-200/60 p-0.5">
                        <div class="bg-gradient-to-r from-emerald-600 to-emerald-400 h-full rounded-full progress-bar-fill" style="width: <?= $completionPercentage ?>%;"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center pt-2">
                    <div class="p-2 rounded-xl bg-slate-50 border border-slate-100">
                        <span class="text-[9px] font-bold text-slate-400 uppercase block">Total</span>
                        <span class="text-sm font-extrabold text-slate-800 font-mono"><?= $totalProjects ?></span>
                    </div>
                    <div class="p-2 rounded-xl bg-emerald-50/50 border border-emerald-100">
                        <span class="text-[9px] font-bold text-emerald-700 uppercase block">Selesai</span>
                        <span class="text-sm font-extrabold text-emerald-700 font-mono"><?= $completedProjects ?></span>
                    </div>
                    <div class="p-2 rounded-xl bg-amber-50/50 border border-amber-100">
                        <span class="text-[9px] font-bold text-amber-700 uppercase block">Hold</span>
                        <span class="text-sm font-extrabold text-amber-700 font-mono"><?= $onHoldProjects ?></span>
                    </div>
                </div>
            </div>
            <a href="modules/projects/index.php" class="btn-press mt-3 text-xs font-bold text-blue-700 hover:text-blue-800 bg-blue-50/70 hover:bg-blue-100/70 px-3.5 py-2 rounded-xl transition flex items-center justify-between">
                <span>Lihat Timeline & Detail Proyek</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- Analytics Card 3: Akses Cepat Modul Dokumen -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase text-slate-900 tracking-wider">NAVIGASI DOKUMEN & MODUL</h3>
                        <p class="text-[11px] text-slate-400">Pintasan ke modul operasional utama</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-xs border border-slate-200">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                </div>
                
                <div class="space-y-2 my-1">
                    <a href="modules/requirements/index.php" class="btn-press flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200/80 hover:border-emerald-200 text-xs font-semibold text-slate-800 transition">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-inbox text-emerald-600"></i>
                            <span>Kebutuhan Klien (Requirements)</span>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>
                    <a href="modules/proposals/index.php" class="btn-press flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200/80 hover:border-emerald-200 text-xs font-semibold text-slate-800 transition">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-file-signature text-emerald-600"></i>
                            <span>Surat Penawaran</span>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>
                    <a href="modules/contracts/index.php" class="btn-press flex items-center justify-between p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50/70 border border-slate-200/80 hover:border-emerald-200 text-xs font-semibold text-slate-800 transition">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-file-contract text-emerald-600"></i>
                            <span>Cetak Kontrak PDF</span>
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                    </a>
                </div>
            </div>
            
            <div class="mt-3 pt-2 text-[11px] text-slate-400 font-medium flex items-center gap-1.5">
                <i class="fa-solid fa-shield-halved text-emerald-600"></i> Terkoneksi ke sistem internal PT. CTC
            </div>
        </div>

    </div>

    <!-- SEKSI DAFTAR PROYEK TERBARU DENGAN PAGINATION -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs">
        
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                    <i class="fa-solid fa-folder-open text-emerald-600"></i> Daftar Proyek Terbaru
                </h2>
                <p class="text-xs text-slate-500 font-medium mt-0.5">Menampilkan <?= count($projects) ?> dari total <?= $totalProjects ?> proyek terdaftar.</p>
            </div>
            <a href="modules/projects/index.php" class="btn-press text-xs font-bold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 px-3.5 py-2 rounded-xl transition flex items-center gap-1.5 self-start sm:self-auto">
                Kelola Semua Proyek <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>

        <!-- 1. TABEL DESKTOP (Screen >= md) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-400 uppercase font-extrabold text-[10px] tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">Nama Proyek</th>
                        <th class="py-3.5 px-4">Klien</th>
                        <th class="py-3.5 px-4">Anggaran Kontrak</th>
                        <th class="py-3.5 px-4">Tenggat Waktu (Deadline)</th>
                        <th class="py-3.5 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-normal">
                    <?php if (empty($projects)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400 font-medium">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                Belum ada proyek terdaftar di database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($projects as $p): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="py-3.5 px-4 font-bold text-slate-900">
                                    <a href="modules/projects/detail.php?id=<?= $p['id'] ?>" class="hover:text-emerald-700 transition flex items-center gap-2.5">
                                        <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs border border-emerald-100 shrink-0">
                                            <i class="fa-solid fa-folder"></i>
                                        </div>
                                        <span><?= htmlspecialchars($p['nama_projek']) ?></span>
                                    </a>
                                </td>
                                <td class="py-3.5 px-4 text-slate-600 font-medium"><?= htmlspecialchars($p['klien']) ?></td>
                                <td class="py-3.5 px-4 font-bold font-mono text-slate-800">
                                    Rp <?= number_format($p['budget'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500 font-medium">
                                    <i class="fa-regular fa-calendar-check text-slate-400 mr-1 text-xs"></i>
                                    <?= date('d M Y', strtotime($p['deadline'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-extrabold uppercase inline-block border tracking-wider
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
                <div class="text-center py-8 text-slate-400 text-xs font-medium">Belum ada proyek terdaftar.</div>
            <?php else: ?>
                <?php foreach ($projects as $p): ?>
                    <a href="modules/projects/detail.php?id=<?= $p['id'] ?>" 
                       class="block bg-slate-50/60 hover:bg-emerald-50/40 border border-slate-200/80 rounded-2xl p-4 transition active:scale-[0.99] space-y-2.5">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs border border-emerald-100 shrink-0">
                                    <i class="fa-solid fa-folder"></i>
                                </div>
                                <h3 class="text-xs font-bold text-slate-900 truncate">
                                    <?= htmlspecialchars($p['nama_projek']) ?>
                                </h3>
                            </div>
                            <span class="px-2 py-0.5 rounded-md text-[9px] uppercase font-extrabold shrink-0 border
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
                        
                        <div class="text-xs text-slate-500 font-medium">
                            Klien: <span class="text-slate-800 font-bold"><?= htmlspecialchars($p['klien']) ?></span>
                        </div>

                        <div class="flex items-center justify-between pt-2 border-t border-slate-200/60 text-xs">
                            <div>
                                <span class="text-slate-400 block font-bold uppercase text-[9px]">Budget Kontrak</span>
                                <span class="font-extrabold text-slate-900 font-mono">Rp <?= number_format($p['budget'], 0, ',', '.') ?></span>
                            </div>
                            <div class="text-right">
                                <span class="text-slate-400 block font-bold uppercase text-[9px]">Deadline</span>
                                <span class="font-semibold text-slate-600"><?= date('d M Y', strtotime($p['deadline'])) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 3. KOMPONEN NAVIGASI PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-5 mt-4 border-t border-slate-100 text-xs text-slate-500">
                
                <span class="font-medium text-center sm:text-left">
                    Menampilkan Halaman <strong class="text-slate-800"><?= $page ?></strong> dari <strong class="text-slate-800"><?= $totalPages ?></strong> (Total <?= $totalProjects ?> Proyek)
                </span>

                <div class="flex items-center gap-1.5">
                    <!-- Tombol Previous -->
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" 
                           class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-semibold transition flex items-center gap-1">
                            <i class="fa-solid fa-chevron-left text-[9px]"></i> Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-300 font-semibold cursor-not-allowed flex items-center gap-1">
                            <i class="fa-solid fa-chevron-left text-[9px]"></i> Prev
                        </span>
                    <?php endif; ?>

                    <!-- Number Badges -->
                    <div class="hidden sm:flex items-center gap-1 px-1">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="w-7 h-7 rounded-xl bg-emerald-600 text-white font-extrabold flex items-center justify-center text-xs shadow-xs">
                                    <?= $i ?>
                                </span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" 
                                   class="btn-press w-7 h-7 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 font-semibold flex items-center justify-center text-xs transition">
                                    <?= $i ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>

                    <!-- Tombol Next -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" 
                           class="btn-press px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 font-semibold transition flex items-center gap-1">
                            Next <i class="fa-solid fa-chevron-right text-[9px]"></i>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 text-slate-300 font-semibold cursor-not-allowed flex items-center gap-1">
                            Next <i class="fa-solid fa-chevron-right text-[9px]"></i>
                        </span>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

    </div>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>