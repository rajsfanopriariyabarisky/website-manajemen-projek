<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Detail Proyek
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

// Fetch Tasks Proyek & PIC
$stmtTasks = $pdo->prepare("
    SELECT t.*, u.nama AS nama_karyawan 
    FROM tasks t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.project_id = ?
    ORDER BY t.deadline ASC
");
$stmtTasks->execute([$id]);
$tasks = $stmtTasks->fetchAll();

// Fetch Dokumen Vault Terlampir
$stmtFiles = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY created_at DESC");
$stmtFiles->execute([$id]);
$files = $stmtFiles->fetchAll();

// Hitung Statistik Task
$totalTasks = count($tasks);
$completedTasks = 0;
foreach ($tasks as $tk) {
    if (isset($tk['status']) && strtolower($tk['status']) === 'done') {
        $completedTasks++;
    }
}
$progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Proyek - <?= htmlspecialchars($project['nama_projek']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-card { border: 1px solid #cbd5e1 !important; box-shadow: none !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 font-sans p-6 md:p-12">

    <!-- Tombol Aksi Non-Print -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print">
        <a href="detail.php?id=<?= $id ?>" class="text-xs font-semibold text-slate-600 hover:text-slate-900 flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Proyek
        </a>
        <button onclick="window.print()" class="bg-emerald-700 hover:bg-emerald-800 text-white text-xs font-bold px-4 py-2 rounded-xl transition shadow flex items-center gap-2">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Lembar Dokumen Laporan -->
    <div class="max-w-4xl mx-auto bg-white border border-slate-200 rounded-2xl p-8 shadow-sm print-card">
        
        <!-- Kop Surat / Header Perusahaan -->
        <div class="flex justify-between items-start border-b border-slate-200 pb-6 mb-6">
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-wide">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                <p class="text-xs text-slate-500 mt-1">IT Consulting, Enterprise Software Development & Operational Workspace</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Jl. Raya CTC No. 88, Jakarta | Email: contact@ptctc.com</p>
            </div>
            <div class="text-right">
                <span class="inline-block bg-slate-100 border border-slate-200 px-3 py-1 rounded-full text-xs font-bold text-slate-700">
                    LAPORAN RINGKASAN PROYEK
                </span>
                <p class="text-[11px] text-slate-400 mt-2">ID Proyek: <span class="font-mono font-bold text-slate-700">#PROJ-<?= sprintf("%03d", $project['id']) ?></span></p>
                <p class="text-[11px] text-slate-400">Dicetak Pada: <?= date('d M Y, H:i') ?> WIB</p>
            </div>
        </div>

        <!-- Ringkasan Informasi Proyek -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200/80 mb-6 text-xs">
            <div>
                <span class="text-slate-400 font-semibold uppercase block text-[10px]">Nama Proyek</span>
                <span class="font-bold text-slate-800 text-sm block mt-0.5"><?= htmlspecialchars($project['nama_projek']) ?></span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold uppercase block text-[10px]">Klien</span>
                <span class="font-semibold text-slate-700 block mt-0.5"><?= htmlspecialchars($project['klien']) ?></span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold uppercase block text-[10px]">Total Budget</span>
                <span class="font-bold text-emerald-700 block mt-0.5">Rp <?= number_format($project['budget'], 0, ',', '.') ?></span>
            </div>
            <div>
                <span class="text-slate-400 font-semibold uppercase block text-[10px]">Status & Deadline</span>
                <span class="font-semibold text-slate-700 block mt-0.5">
                    <?= htmlspecialchars($project['status']) ?> (<?= date('d M Y', strtotime($project['deadline'])) ?>)
                </span>
            </div>
        </div>

        <!-- Progress Milestone Status Bar -->
        <div class="mb-8">
            <div class="flex justify-between text-xs font-semibold mb-1.5">
                <span class="text-slate-600">Pencapaian Progress Milestone</span>
                <span class="text-emerald-700 font-bold"><?= $progressPercent ?>% Completed (<?= $completedTasks ?>/<?= $totalTasks ?> Tugas Selesai)</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200">
                <div class="bg-emerald-600 h-3 rounded-full" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>

        <!-- Tabel Detail Workload & Status Tugas -->
        <div class="mb-8">
            <h3 class="font-bold text-slate-800 text-sm mb-3 border-b pb-2 flex items-center justify-between">
                <span>Rincian Tugas & Penugasan Tim</span>
                <span class="text-xs font-normal text-slate-400">Total: <?= $totalTasks ?> Modul</span>
            </h3>

            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 font-bold border-y border-slate-200 uppercase text-[10px]">
                        <th class="py-2.5 px-3">No</th>
                        <th class="py-2.5 px-3">Tugas / Modul</th>
                        <th class="py-2.5 px-3">Penanggung Jawab (PIC)</th>
                        <th class="py-2.5 px-3 text-center">Prioritas</th>
                        <th class="py-2.5 px-3 text-center">Status</th>
                        <th class="py-2.5 px-3 text-center">Deadline</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($tasks)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-slate-400 italic">Belum ada tugas dialokasikan.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($tasks as $t): ?>
                            <tr>
                                <td class="py-2.5 px-3 text-slate-400"><?= $no++ ?></td>
                                <td class="py-2.5 px-3 font-semibold text-slate-800">
                                    <?= htmlspecialchars($t['nama_tugas']) ?>
                                    <?php if (!empty($t['progress_notes'])): ?>
                                        <p class="text-[10px] text-slate-500 font-normal italic mt-0.5">Note: <?= htmlspecialchars($t['progress_notes']) ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2.5 px-3 text-slate-600"><?= htmlspecialchars($t['nama_karyawan'] ?? 'Unassigned') ?></td>
                                <td class="py-2.5 px-3 text-center font-bold uppercase text-[10px]"><?= htmlspecialchars($t['prioritas']) ?></td>
                                <td class="py-2.5 px-3 text-center font-bold text-[10px] uppercase">
                                    <?= htmlspecialchars($t['status']) ?>
                                </td>
                                <td class="py-2.5 px-3 text-center text-slate-500"><?= date('d M Y', strtotime($t['deadline'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Daftar Berkas Document Vault Terlampir -->
        <div class="mb-8">
            <h3 class="font-bold text-slate-800 text-sm mb-3 border-b pb-2">Berkas & Dokumen Vault Terlampir</h3>
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-100 text-slate-600 font-bold border-y border-slate-200 uppercase text-[10px]">
                        <th class="py-2.5 px-3">Nama Berkas</th>
                        <th class="py-2.5 px-3">Kategori</th>
                        <th class="py-2.5 px-3 text-center">Ukuran</th>
                        <th class="py-2.5 px-3 text-center">Tanggal Upload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-slate-400 italic">Tidak ada berkas dokumen terlampir.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($files as $f): ?>
                            <tr>
                                <td class="py-2 px-3 font-medium text-slate-800"><?= htmlspecialchars($f['nama_file']) ?></td>
                                <td class="py-2 px-3 text-slate-500"><?= htmlspecialchars($f['kategori']) ?></td>
                                <td class="py-2 px-3 text-center text-slate-400 font-mono"><?= round($f['ukuran_file'] / 1024, 1) ?> KB</td>
                                <td class="py-2 px-3 text-center text-slate-400"><?= date('d M Y', strtotime($f['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Kolom Tanda Tangan / Verifikasi -->
        <div class="mt-12 pt-6 border-t border-slate-200 grid grid-cols-2 text-center text-xs">
            <div>
                <p class="text-slate-400 mb-12">Disetujui Oleh,<br><strong>Project Manager PT. CTC</strong></p>
                <p class="font-bold text-slate-800 underline">( ________________________ )</p>
            </div>
            <div>
                <p class="text-slate-400 mb-12">Mengetahui,<br><strong>Perwakilan Klien / Direksi</strong></p>
                <p class="font-bold text-slate-800 underline">( ________________________ )</p>
            </div>
        </div>

    </div>

</body>
</html>