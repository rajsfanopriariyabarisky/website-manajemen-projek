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

$project_id = $_GET['project_id'] ?? $_GET['id'] ?? null;

if (!$project_id) {
    header("Location: index.php");
    exit;
}

// Fetch Data Proyek
$stmtP = $pdo->prepare("
    SELECT p.*,
           COALESCE(NULLIF(p.persen_pajak, 0), 11.00) AS persen_pajak_final,
           COALESCE(NULLIF(p.total_nilai, 0), p.budget + ROUND((p.budget * COALESCE(NULLIF(p.persen_pajak, 0), 11.00)) / 100, 2)) AS total_nilai_final 
    FROM projects p 
    WHERE p.id = ?
");
$stmtP->execute([$project_id]);
$project = $stmtP->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

$error   = '';
$success = '';

// Handle Tambah Pengeluaran Baru untuk Proyek Ini
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $kategori            = trim($_POST['kategori'] ?? '');
    $keterangan          = trim($_POST['keterangan'] ?? '');
    $tanggal_pengeluaran = $_POST['tanggal_pengeluaran'] ?? date('Y-m-d');

    // Sanitasi Nominal Rupiah
    $raw_biaya    = $_POST['jumlah_biaya'] ?? '0';
    $clean_raw    = explode(',', $raw_biaya)[0];
    $clean_number = preg_replace('/[^0-9]/', '', $clean_raw);
    $jumlah_biaya = floatval($clean_number);

    if (!empty($kategori) && $jumlah_biaya > 0) {
        
        // 1. Hitung Dana Masuk Kas Murni dari Invoice Paid
        $stmtPaid = $pdo->prepare("
            SELECT COALESCE(SUM(COALESCE(NULLIF(total_tagihan, 0), jumlah_tagihan + ROUND((jumlah_tagihan * COALESCE(NULLIF(persen_pajak, 0), 11.00))/100, 2))), 0) 
            FROM invoices 
            WHERE project_id = ? AND LOWER(status) = 'paid'
        ");
        $stmtPaid->execute([$project_id]);
        $total_paid_cash = floatval($stmtPaid->fetchColumn());

        // 2. Hitung Pengeluaran Eksisting Proyek Ini
        $stmtExpCurrent = $pdo->prepare("SELECT COALESCE(SUM(jumlah_biaya), 0) FROM expenses WHERE project_id = ?");
        $stmtExpCurrent->execute([$project_id]);
        $total_current_expense = floatval($stmtExpCurrent->fetchColumn());

        // 3. Saldo Kas Siap Pakai
        $saldo_kas_tersedia = $total_paid_cash - $total_current_expense;

        // STRICT CASH GUARD VALIDATION:
        if ($total_paid_cash <= 0) {
            $error = "Gagal mencatat pengeluaran! Klien belum melakukan pembayaran invoice (Paid) untuk proyek ini. Pengeluaran hanya bisa diinput jika dana kas dari termin telah diterima.";
        } elseif ($jumlah_biaya > $saldo_kas_tersedia) {
            $error = "Gagal mencatat pengeluaran! Nominal biaya Rp " . number_format($jumlah_biaya, 0, ',', '.') . " melebihi sisa kas yang tersedia (Rp " . number_format(max(0, $saldo_kas_tersedia), 0, ',', '.') . "). Silakan tunggu pelunasan termin invoice berikutnya.";
        } else {
            // Insert Pengeluaran Baru
            $stmtInsert = $pdo->prepare("
                INSERT INTO expenses (project_id, kategori, keterangan, jumlah_biaya, tanggal_pengeluaran) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtInsert->execute([$project_id, $kategori, $keterangan, $jumlah_biaya, $tanggal_pengeluaran]);
            
            // Log Aktivitas
            $user_id   = $_SESSION['user_id'] ?? null;
            $nama_user = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? 'System Admin';
            $aktivitas = "Mencatat biaya operasional baru Rp " . number_format($jumlah_biaya, 0, ',', '.') . " ({$kategori})";
            try {
                $stmtLog = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe, created_at) VALUES (?, ?, ?, ?, 'info', NOW())");
                $stmtLog->execute([$project_id, $user_id, $nama_user, $aktivitas]);
            } catch (Exception $e) {}

            header("Location: detail.php?project_id=" . $project_id . "&msg=added");
            exit;
        }
    } else {
        $error = "Mohon isi kategori dan nominal pengeluaran secara valid!";
    }
}

// Re-Fetch Metrics Proyek Ini
$stmtPaid = $pdo->prepare("
    SELECT COALESCE(SUM(COALESCE(NULLIF(total_tagihan, 0), jumlah_tagihan + ROUND((jumlah_tagihan * COALESCE(NULLIF(persen_pajak, 0), 11.00))/100, 2))), 0) 
    FROM invoices 
    WHERE project_id = ? AND LOWER(status) = 'paid'
");
$stmtPaid->execute([$project_id]);
$total_paid_cash = floatval($stmtPaid->fetchColumn());

$stmtExp = $pdo->prepare("SELECT COALESCE(SUM(jumlah_biaya), 0) FROM expenses WHERE project_id = ?");
$stmtExp->execute([$project_id]);
$total_expense = floatval($stmtExp->fetchColumn());

$sisa_kas_tersedia = $total_paid_cash - $total_expense;
$total_nilai_kontrak = floatval($project['total_nilai_final']);
$alokasi_sosial = round($total_nilai_kontrak * 0.10, 2);

// Fetch Daftar Expenses Proyek Ini
$stmtList = $pdo->prepare("SELECT * FROM expenses WHERE project_id = ? ORDER BY tanggal_pengeluaran DESC, id DESC");
$stmtList->execute([$project_id]);
$expenses = $stmtList->fetchAll();

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
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 relative">

    <!-- Navigation Back -->
    <div class="mb-4">
        <a href="index.php" class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-500 hover:text-emerald-700 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Rekap Biaya Operasional Proyek
        </a>
    </div>

    <!-- Header Banner Navigation -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase border tracking-wider
                    <?php
                        if ($total_paid_cash <= 0) {
                            echo 'bg-slate-100 text-slate-600 border-slate-300';
                        } elseif ($sisa_kas_tersedia > 0) {
                            echo 'bg-emerald-50 text-emerald-700 border-emerald-300';
                        } else {
                            echo 'bg-amber-50 text-amber-700 border-amber-300';
                        }
                    ?>">
                    KAS: <?= $total_paid_cash <= 0 ? 'BELUM ADA PAIN INVOICE' : ($sisa_kas_tersedia > 0 ? 'KAS SIAP PAKAI' : 'KAS TERPAKAI PENUH') ?>
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight"><?= htmlspecialchars($project['nama_projek']) ?></h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Klien Mitra: <strong class="text-slate-800"><?= htmlspecialchars($project['klien']) ?></strong> | Total Kontrak Inc. PPN: <strong class="text-emerald-800 font-mono">Rp <?= number_format($total_nilai_kontrak, 0, ',', '.') ?></strong></p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <button onclick="openExpenseModal()" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-xs flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-xs"></i> Catat Biaya Baru Proyek Ini
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-start gap-2.5 shadow-xs leading-relaxed">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0 mt-0.5"></i>
            <div>
                <strong class="font-bold block">Peringatan Real-Time Cash Guard:</strong>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> Biaya operasional proyek berhasil dicatat!
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-trash-can text-red-500 text-base shrink-0"></i> Catatan pengeluaran berhasil dihapus! Sisa saldo kas dikembalikan.
        </div>
    <?php endif; ?>

    <!-- Stat Counter Cards Proyek Ini -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Nilai Kontrak Inc. PPN</span>
            <h3 class="text-xl md:text-2xl font-bold text-slate-900 font-mono">Rp <?= number_format($total_nilai_kontrak, 0, ',', '.') ?></h3>
            <span class="text-xs text-slate-400 font-normal mt-1 block">Total estimasi proyek</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">Kas Masuk (Paid Invoices)</span>
            <h3 class="text-xl md:text-2xl font-bold text-emerald-600 font-mono">Rp <?= number_format($total_paid_cash, 0, ',', '.') ?></h3>
            <span class="text-xs text-emerald-700 font-medium mt-1 flex items-center gap-1"><i class="fa-solid fa-vault text-[10px]"></i> Batas maksimum belanja kas</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block mb-1">Realisasi Biaya Terpakai</span>
            <h3 class="text-xl md:text-2xl font-bold text-amber-600 font-mono">Rp <?= number_format($total_expense, 0, ',', '.') ?></h3>
            <span class="text-xs text-amber-700 font-medium mt-1 block">Akumulasi pengeluaran</span>
        </div>

        <div class="bg-white border border-slate-200/90 p-5 rounded-2xl shadow-xs">
            <span class="text-[10px] font-bold text-blue-600 uppercase tracking-wider block mb-1">Sisa Saldo Kas Siap Pakai</span>
            <h3 class="text-xl md:text-2xl font-bold <?= $sisa_kas_tersedia > 0 ? 'text-blue-600' : 'text-slate-400' ?> font-mono">Rp <?= number_format(max(0, $sisa_kas_tersedia), 0, ',', '.') ?></h3>
            <span class="text-xs text-blue-700 font-medium mt-1 block">Dana tersedia untuk di-expense</span>
        </div>
    </div>

    <!-- Box Notifikasi Jika Proyek Belum Ada Dana Masuk Paid -->
    <?php if ($total_paid_cash <= 0): ?>
        <div class="mb-6 bg-slate-800 border border-slate-700 p-5 rounded-2xl text-white shadow-md flex items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-xl bg-amber-500/20 flex items-center justify-center text-amber-400 text-2xl border border-amber-400/30 shrink-0">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold uppercase tracking-wider text-amber-300">PENGELUARAN KAS KUNCI SEMENTARA</h4>
                    <p class="text-xs text-slate-300 mt-0.5">Klien belum melakukan pembayaran invoice (Paid) untuk proyek ini. Admin belum dapat menginput biaya operasional sampai ada kas masuk.</p>
                </div>
            </div>
            <a href="../invoices/detail.php?project_id=<?= $project_id ?>" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-xl transition shrink-0 flex items-center gap-1.5">
                <i class="fa-solid fa-receipt text-xs"></i> Kelola Invoice & Penagihan
            </a>
        </div>
    <?php endif; ?>

    <!-- Main Table Container: Riwayat Expenses Proyek Ini -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-8">
        <h3 class="font-bold text-slate-900 text-base mb-4 flex items-center gap-2">
            <i class="fa-solid fa-receipt text-emerald-600"></i> Riwayat Transaksi Pengeluaran Proyek Ini
        </h3>

        <!-- Desktop Table View -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 text-slate-500 uppercase font-semibold text-xs tracking-wider border-b border-slate-200">
                        <th class="py-3.5 px-4">TANGGAL</th>
                        <th class="py-3.5 px-4">KATEGORI BIAYA</th>
                        <th class="py-3.5 px-4">DESKRIPSI / KETERANGAN</th>
                        <th class="py-3.5 px-4 text-right">JUMLAH BIAYA</th>
                        <th class="py-3.5 px-4 text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400 font-normal">
                                <div class="w-12 h-12 mx-auto mb-2 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl">
                                    <i class="fa-solid fa-receipt"></i>
                                </div>
                                Belum ada pengeluaran operasional yang dicatat untuk proyek ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $e): ?>
                            <tr class="hover:bg-slate-50/80 transition">
                                <td class="py-3.5 px-4 text-slate-500 font-normal whitespace-nowrap">
                                    <i class="fa-regular fa-calendar text-slate-400 mr-1.5 text-xs"></i> <?= date('d M Y', strtotime($e['tanggal_pengeluaran'])) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-lg text-xs font-semibold uppercase inline-block border tracking-wider whitespace-nowrap
                                        <?php
                                            switch($e['kategori']) {
                                                case 'Infrastruktur & Cloud': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                case 'Vendor / Freelancer': echo 'bg-purple-50 text-purple-700 border-purple-200'; break;
                                                case 'Lisensi Software & API': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'Desain & Assets': echo 'bg-pink-50 text-pink-700 border-pink-200'; break;
                                                case 'Sosial': echo 'bg-teal-50 text-teal-700 border-teal-200'; break;
                                                default: echo 'bg-amber-50 text-amber-700 border-amber-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($e['kategori']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-slate-700 font-normal">
                                    <?= htmlspecialchars($e['keterangan'] ?: '-') ?>
                                </td>
                                <td class="py-3.5 px-4 text-right font-bold text-amber-600 font-mono whitespace-nowrap">
                                    Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <a href="delete.php?id=<?= $e['id'] ?>&project_id=<?= $project_id ?>" onclick="return confirm('Apakah Anda yakin ingin menghapus catatan biaya ini?')" class="btn-press w-7 h-7 rounded-lg bg-slate-100 hover:bg-red-50 hover:text-red-600 text-slate-400 inline-flex items-center justify-center transition" title="Hapus Biaya">
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
                <p class="text-center py-6 text-xs text-slate-400 font-normal">Belum ada catatan pengeluaran.</p>
            <?php else: ?>
                <?php foreach ($expenses as $e): ?>
                    <div class="p-4 border border-slate-200/90 rounded-xl bg-slate-50/50 space-y-2">
                        <div class="flex justify-between items-start gap-2">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase bg-white border border-slate-200 text-slate-700">
                                <?= htmlspecialchars($e['kategori']) ?>
                            </span>
                            <span class="text-xs text-slate-400"><?= date('d M Y', strtotime($e['tanggal_pengeluaran'])) ?></span>
                        </div>
                        <p class="text-xs text-slate-700 font-normal"><?= htmlspecialchars($e['keterangan'] ?: '-') ?></p>
                        <div class="pt-2 border-t border-slate-200/80 flex justify-between items-center text-xs">
                            <span class="font-bold text-amber-600 font-mono">Rp <?= number_format($e['jumlah_biaya'], 0, ',', '.') ?></span>
                            <a href="delete.php?id=<?= $e['id'] ?>&project_id=<?= $project_id ?>" onclick="return confirm('Hapus biaya ini?')" class="text-red-600 font-semibold hover:underline">Hapus</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal Input Expense Proyek Ini -->
<div id="expenseModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 flex items-center justify-center hidden p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100 p-6 pb-0">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-circle-plus text-emerald-600"></i> Catat Biaya Operasional Proyek
            </h3>
            <button onclick="closeExpenseModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" class="p-6 pt-2 space-y-3.5">
            <input type="hidden" name="add_expense" value="1">

            <!-- Card Dynamic Sisa Kas Guard -->
            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 space-y-1.5 text-xs">
                <div class="flex justify-between text-slate-600">
                    <span>Dana Kas Masuk (Invoice Paid):</span>
                    <strong class="font-mono text-emerald-700 font-bold">Rp <?= number_format($total_paid_cash, 0, ',', '.') ?></strong>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Pengeluaran Terpakai:</span>
                    <strong class="font-mono text-amber-700">Rp <?= number_format($total_expense, 0, ',', '.') ?></strong>
                </div>
                <div class="flex justify-between pt-1 border-t border-slate-200/80 items-center">
                    <span class="font-bold text-slate-800">Sisa Kas Siap Pakai:</span>
                    <strong class="font-mono font-extrabold text-sm <?= $sisa_kas_tersedia > 0 ? 'text-emerald-700' : 'text-red-600' ?>">
                        Rp <?= number_format(max(0, $sisa_kas_tersedia), 0, ',', '.') ?>
                    </strong>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Kategori Pengeluaran <span class="text-red-500">*</span></label>
                <select name="kategori" id="select_kategori" required onchange="handleKategoriChange()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Infrastruktur & Cloud">Infrastruktur & Cloud Server</option>
                    <option value="Lisensi Software & API">Lisensi Software & API</option>
                    <option value="Vendor / Freelancer">Gaji Vendor / Freelancer</option>
                    <option value="Operasional & Tim">Operasional & Konsumsi Tim</option>
                    <option value="Desain & Assets">Pembelian Assets & Desain</option>
                    <option value="Sosial">Sosial (Dana Bantuan 10%)</option>
                    <option value="Lainnya">Lain-lain</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Jumlah Biaya (Rp) <span class="text-red-500">*</span></label>
                <input type="text" name="jumlah_biaya" id="input_biaya" required placeholder="Contoh: 1.500.000" onkeyup="formatRupiah(this); validateCashLimit();" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none placeholder:text-slate-400">
                
                <span id="sosial_notice" class="hidden text-[11px] text-teal-700 font-medium mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-circle-info"></i> Nominal diisi 10% dari total nilai proyek (Rp <?= number_format($alokasi_sosial, 0, ',', '.') ?>).
                </span>

                <span id="cash_warning" class="hidden text-[11px] text-red-600 font-bold mt-1 flex items-center gap-1">
                    <i class="fa-solid fa-triangle-exclamation"></i> Nominal melebihi saldo kas siap pakai (Rp <?= number_format(max(0, $sisa_kas_tersedia), 0, ',', '.') ?>)!
                </span>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Transaksi <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_pengeluaran" required value="<?= date('Y-m-d') ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Deskripsi / Keterangan</label>
                <textarea name="keterangan" id="input_keterangan" rows="3" placeholder="Misal: Pembelian domain & SSL server AWS tahunan" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400"></textarea>
            </div>

            <div class="pt-3 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeExpenseModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                    Batal
                </button>
                <button type="submit" id="btn_submit_expense" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const currentSisaKas = <?= (float)max(0, $sisa_kas_tersedia) ?>;
const alokasiSosial  = <?= (float)$alokasi_sosial ?>;

function openExpenseModal() {
    document.getElementById('expenseModal').classList.remove('hidden');
    handleKategoriChange();
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

function validateCashLimit() {
    const inputBiaya = document.getElementById('input_biaya');
    const warning = document.getElementById('cash_warning');
    const btnSubmit = document.getElementById('btn_submit_expense');

    let rawVal = inputBiaya.value.replace(/[^0-9]/g, '');
    let val = parseFloat(rawVal || 0);

    if (val > currentSisaKas) {
        warning.classList.remove('hidden');
        btnSubmit.disabled = true;
        btnSubmit.classList.add('opacity-50', 'cursor-not-allowed');
    } else {
        warning.classList.add('hidden');
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('opacity-50', 'cursor-not-allowed');
    }
}

function handleKategoriChange() {
    const kategori = document.getElementById('select_kategori').value;
    const inputBiaya = document.getElementById('input_biaya');
    const notice = document.getElementById('sosial_notice');
    const inputKet = document.getElementById('input_keterangan');

    if (kategori === 'Sosial') {
        notice.classList.remove('hidden');
        if (alokasiSosial > 0) {
            inputBiaya.value = new Intl.NumberFormat('id-ID').format(alokasiSosial);
            if (!inputKet.value) {
                inputKet.value = "Penyaluran dana bantuan sosial 10% proyek <?= htmlspecialchars($project['nama_projek']) ?>";
            }
        }
    } else {
        notice.classList.add('hidden');
    }
    validateCashLimit();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>