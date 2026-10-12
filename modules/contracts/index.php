<?php
require_once __DIR__ . '/../../config/database.php';

// Handle Hapus Kontrak
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmtDel = $pdo->prepare("DELETE FROM contracts WHERE id = ?");
    $stmtDel->execute([$delete_id]);
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch semua kontrak yang tersimpan
$stmt = $pdo->query("
    SELECT c.*, p.nama_projek, p.klien, p.budget 
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    ORDER BY c.created_at DESC
");
$contracts = $stmt->fetchAll();
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 text-emerald-700 font-semibold text-xs tracking-wider uppercase mb-1">
                <i class="fa-solid fa-circle text-[8px]"></i> Modul 5
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Generator Kontrak Kerja & PDF</h2>
            <p class="text-slate-500 text-sm">Pembuatan & cetak dokumen perjanjian proyek resmi PT. Cipta Teknologi Cendekia</p>
        </div>
        <a href="generate.php" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl flex items-center justify-center gap-2 transition shadow-md shadow-emerald-600/20 text-sm">
            <i class="fa-solid fa-file-circle-plus"></i> Buat Kontrak Baru
        </a>
    </div>

    <!-- Tabel Daftar Kontrak -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <h3 class="font-bold text-slate-800 text-base mb-4">Arsip Kontrak Proyek</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-600">
                <thead class="text-xs uppercase bg-slate-100 text-slate-500 font-bold border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3">No. Kontrak</th>
                        <th class="px-4 py-3">Proyek & Klien</th>
                        <th class="px-4 py-3">Durasi Pengerjaan</th>
                        <th class="px-4 py-3 text-right">Nilai Kontrak</th>
                        <th class="px-4 py-3 text-center">Cetak / Akses</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400 font-medium">
                                Belum ada kontrak proyek yang dibuat. Klik tombol "Buat Kontrak Baru" untuk membuat dokumen perjanjian resmi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-4 font-mono text-xs font-bold text-slate-800"><?= htmlspecialchars($c['nomor_kontrak']) ?></td>
                                <td class="px-4 py-4">
                                    <p class="font-bold text-slate-800 text-xs leading-tight"><?= htmlspecialchars($c['nama_projek']) ?></p>
                                    <span class="text-[11px] text-slate-400">Klien: <?= htmlspecialchars($c['klien']) ?></span>
                                </td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    <span class="font-bold text-emerald-700"><?= $c['durasi_bulan'] ?> Bulan</span>
                                    <span class="block text-[10px] text-slate-400"><?= date('d/m/Y', strtotime($c['tanggal_mulai'])) ?> s/d <?= date('d/m/Y', strtotime($c['tanggal_selesai'])) ?></span>
                                </td>
                                <td class="px-4 py-4 text-right font-bold text-slate-800">
                                    Rp <?= number_format($c['nilai_kontrak'], 0, ',', '.') ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="print_pdf.php?id=<?= $c['id'] ?>" target="_blank" class="px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-semibold hover:bg-emerald-100 transition inline-flex items-center gap-1.5">
                                        <i class="fa-solid fa-print"></i> Cetak / PDF
                                    </a>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <a href="index.php?delete=<?= $c['id'] ?>" onclick="return confirm('Hapus arsip kontrak ini?')" class="text-slate-400 hover:text-red-500 text-xs p-1" title="Hapus">
                                        <i class="fa-solid fa-trash-can"></i>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>