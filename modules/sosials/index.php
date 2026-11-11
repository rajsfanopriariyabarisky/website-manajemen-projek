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

// Parameter Filter & Search
$search_query = trim($_GET['search'] ?? '');

// Fetch Seluruh Proyek & Kalkulasi Alokasi Sosial 10%
$sqlSocialProjects = "
    SELECT p.id, p.nama_projek, p.klien, p.budget, p.persen_pajak, p.nominal_pajak, p.total_nilai, p.status,
           COALESCE(NULLIF(p.total_nilai, 0), p.budget + ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS total_nilai_final,
           COALESCE((SELECT SUM(e.jumlah_biaya) FROM expenses e WHERE e.project_id = p.id AND e.kategori = 'Sosial'), 0) AS realized_sosial
    FROM projects p
    WHERE 1=1
";

$params = [];
if (!empty($search_query)) {
    $sqlSocialProjects .= " AND (p.nama_projek LIKE ? OR p.klien LIKE ?)";
    $params[] = "%$search_query%";
    $params[] = "%$search_query%";
}

$sqlSocialProjects .= " ORDER BY p.created_at DESC";
$stmt = $pdo->prepare($sqlSocialProjects);
$stmt->execute($params);
$socialProjects = $stmt->fetchAll();

// Kalkulasi Grand Metrics Dana Sosial
$grandTotalNilaiProyek = 0;
$grandTargetSosial     = 0;
$grandRealizedSosial   = 0;
$countContributing     = 0;

foreach ($socialProjects as $sp) {
    $totVal   = floatval($sp['total_nilai_final']);
    $realised = floatval($sp['realized_sosial']);
    $target10 = round($totVal * 0.10, 2);

    $grandTotalNilaiProyek += $totVal;
    $grandTargetSosial     += $target10;
    $grandRealizedSosial   += $realised;
    if ($totVal > 0) {
        $countContributing++;
    }
}

$grandSisaSosialTersedia = max(0, $grandTargetSosial - $grandRealizedSosial);

// Fetch Riwayat Transaksi Pengeluaran Kategori 'Sosial'
$sqlSocialLogs = "
    SELECT e.*, p.nama_projek, p.klien 
    FROM expenses e
    JOIN projects p ON e.project_id = p.id
    WHERE e.kategori = 'Sosial'
    ORDER BY e.tanggal_pengeluaran DESC, e.id DESC
    LIMIT 10
";
$socialLogs = $pdo->query($sqlSocialLogs)->fetchAll();

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
        border-color: #0d9488; 
        box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12); 
    }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-teal-600 selection:text-white relative">

    <!-- Top Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-teal-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-teal-700 bg-teal-50 border border-teal-200 px-3 py-0.5 rounded-full">
                    MODUL KEMANUSIAAN & SOSIAL • CSR 10%
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Alokasi & Rekapitulasi Dana Sosial Proyek</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Monitoring penyisihan otomatis 10% dari setiap proyek PT. CTC untuk kegiatan sosial & kemanusiaan.</p>
        </div>
    </div>

    <!-- Stat Counter Cards Overview -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">TOTAL POTENSI DANA SOSIAL (10%)</span>
                <div class="w-7 h-7 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($grandTargetSosial, 0, ',', '.') ?></h3>
            <span class="text-xs text-teal-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-circle-check text-[10px]"></i> 10% dari total nilai portofolio</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">REALISASI DISALURKAN</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-hand-holding-dollar"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($grandRealizedSosial, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 block">Tercatat terdistribusi</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-teal-200 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider">SALDO TERSIDIA / BELUM DISALURKAN</span>
                <div class="w-7 h-7 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-vault"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-teal-700 font-mono">Rp <?= number_format($grandSisaSosialTersedia, 0, ',', '.') ?></h3>
            <span class="text-xs text-teal-800 font-semibold mt-1 block">Siap didistribusikan</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs hover:border-slate-300 transition">
            <div class="flex items-center justify-between mb-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">PROYEK BERKONTRIBUSI</span>
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-diagram-project"></i>
                </div>
            </div>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono"><?= number_format($countContributing) ?> <span class="text-xs font-sans font-normal text-slate-500">Proyek</span></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Menyisihkan 10% dana sosial</span>
        </div>
    </div>

    <!-- Search Bar Filter -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs">
        <form method="GET" class="flex items-center justify-between gap-3 w-full">
            <div class="relative w-full md:w-80">
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Cari nama proyek / klien..." class="input-glow w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-sm"></i>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn-press bg-teal-600 hover:bg-teal-700 text-white font-semibold px-4 py-2.5 rounded-xl text-xs sm:text-sm transition shadow-xs">
                    Cari
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="index.php" class="btn-press px-3 py-2.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-xs font-semibold border border-red-200 transition">
                        <i class="fa-solid fa-xmark"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Main Table Breakdown Per Proyek -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-heart-circle-check text-teal-600"></i> Breakdown Alokasi Dana Sosial per Proyek
        </h3>

        <!-- Desktop View (Tabel) -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[850px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">NAMA PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4 text-right">TOTAL NILAI PROYEK</th>
                        <th class="py-3.5 px-4 text-right">ALOKASI SOSIAL (10%)</th>
                        <th class="py-3.5 px-4 text-right">DISALURKAN</th>
                        <th class="py-3.5 px-4 text-right">SALDO TERSISA</th>
                        <th class="py-3.5 px-4 text-center">STATUS</th>
                        <th class="py-3.5 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($socialProjects)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-10 text-slate-400 font-normal">
                                Data proyek tidak ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($socialProjects as $sp): 
                            $totVal     = floatval($sp['total_nilai_final']);
                            $target10   = round($totVal * 0.10, 2);
                            $realised   = floatval($sp['realized_sosial']);
                            $sisaSosial = max(0, $target10 - $realised);

                            $isLunas    = ($realised >= $target10 && $target10 > 0);
                            $isPartial  = ($realised > 0 && $realised < $target10);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm leading-tight mb-0.5"><?= htmlspecialchars($sp['nama_projek']) ?></p>
                                    <span class="text-xs text-slate-400 font-normal">Klien: <strong class="text-slate-600 font-semibold"><?= htmlspecialchars($sp['klien']) ?></strong></span>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-800">
                                    Rp <?= number_format($totVal, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-teal-700">
                                    Rp <?= number_format($target10, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-emerald-600">
                                    Rp <?= number_format($realised, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-700">
                                    Rp <?= number_format($sisaSosial, 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($isLunas): ?>
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            Sudah Dialokasikan
                                        </span>
                                    <?php elseif ($isPartial): ?>
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                            Dialokasikan Parsial
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase bg-slate-100 text-slate-600 border border-slate-200">
                                            Belum Dialokasikan
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($isLunas): ?>
                                        <span class="text-slate-400 font-bold">-</span>
                                    <?php else: ?>
                                        <a href="../expenses/index.php?project_id=<?= $sp['id'] ?>&kategori=Sosial&open_modal=1" class="btn-press px-2.5 py-1 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 font-semibold rounded-lg text-xs inline-flex items-center gap-1">
                                            <i class="fa-solid fa-receipt text-[10px]"></i> Catat Pengeluaran
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel Riwayat Penyaluran Dana Sosial -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-teal-600"></i> 10 Transaksi Penyaluran Sosial Terakhir
        </h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[650px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3 px-4">TANGGAL</th>
                        <th class="py-3 px-4">PROYEK</th>
                        <th class="py-3 px-4">KETERANGAN PENYALURAN</th>
                        <th class="py-3 px-4 text-right">DANA DISALURKAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($socialLogs)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-6 text-slate-400 font-normal">Belum ada riwayat transaksi penyaluran sosial.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($socialLogs as $log): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3 px-4 text-slate-500 font-normal whitespace-nowrap"><?= date('d M Y', strtotime($log['tanggal_pengeluaran'])) ?></td>
                                <td class="py-3 px-4 font-semibold text-slate-800"><?= htmlspecialchars($log['nama_projek']) ?></td>
                                <td class="py-3 px-4 text-slate-600 font-normal"><?= htmlspecialchars($log['keterangan'] ?: '-') ?></td>
                                <td class="py-3 px-4 text-right font-bold font-mono text-teal-700">Rp <?= number_format($log['jumlah_biaya'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>