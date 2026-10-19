<?php
require_once __DIR__ . '/../../config/database.php';

// Handle Hapus Kontrak
if (isset($_GET['delete'])) {
    $delete_id =$_GET['delete'];
    $stmtDel =$pdo->prepare("DELETE FROM contracts WHERE id = ?");
    $stmtDel->execute([$delete_id]);
    header("Location: index.php");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Pastikan struktur tabel contracts siap
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `contracts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `project_id` INT NULL,
        `nomor_kontrak` VARCHAR(100) NOT NULL,
        `tanggal_mulai` DATE NULL,
        `tanggal_selesai` DATE NULL,
        `durasi_bulan` INT DEFAULT 1,
        `nilai_kontrak` DECIMAL(15,2) DEFAULT 0.00,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Fetch semua kontrak yang tersimpan
$stmt =$pdo->query("
    SELECT c.*, 
           COALESCE(p.nama_projek, 'Kontrak Mandiri / Pengajuan') as nama_projek, 
           COALESCE(p.klien, '-') as klien, 
           COALESCE(p.budget, c.nilai_kontrak) as budget 
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    ORDER BY c.created_at DESC
");
$contracts =$stmt->fetchAll();
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto bg-slate-50 min-w-0 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Page Banner -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs">
        <div>
            <div class="flex items-center gap-2 text-emerald-700 font-extrabold text-[10px] tracking-wider uppercase mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span> MODUL 5
            </div>
            <h2 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Generator Kontrak Kerja & PDF</h2>
            <p class="text-slate-500 text-xs font-medium mt-0.5">Pembuatan & cetak dokumen perjanjian proyek resmi PT. Cipta Teknologi Cendekia</p>
        </div>
        <a href="generate.php" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2.5 rounded-xl flex items-center justify-center gap-2 transition shadow-md shadow-emerald-600/20 text-xs uppercase tracking-wider shrink-0">
            <i class="fa-solid fa-file-circle-plus"></i> Buat Kontrak Baru
        </a>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-3.5 mb-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-96">
            <input type="text" id="contractSearchInput" onkeyup="filterContracts()" placeholder="Cari no. kontrak, nama proyek, atau klien..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
        </div>
        <div class="text-[11px] text-slate-400 font-bold hidden sm:block">
            <i class="fa-solid fa-file-contract text-emerald-600 mr-1"></i> Total Arsip: <?= count($contracts) ?> Dokumen
        </div>
    </div>

    <!-- Tabel Daftar Kontrak Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-2xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-black text-slate-800 text-xs uppercase tracking-wider">Arsip Kontrak Proyek</h3>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="w-full text-left text-xs text-slate-600 border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[9px] font-black uppercase text-slate-400 tracking-wider">
                        <th class="px-4 py-3">No. Kontrak</th>
                        <th class="px-4 py-3">Proyek & Klien</th>
                        <th class="px-4 py-3">Durasi Pengerjaan</th>
                        <th class="px-4 py-3 text-right">Nilai Kontrak</th>
                        <th class="px-4 py-3 text-center">Cetak / Akses</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="contractTableBody">
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400 font-bold">
                                Belum ada kontrak proyek yang dibuat. Klik tombol "Buat Kontrak Baru" untuk membuat dokumen perjanjian resmi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as$c): ?>
                            <tr class="hover:bg-slate-50/80 transition contract-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower(($c['nomor_kontrak'] ?? '') . ' ' . ($c['nama_projek'] ?? '') . ' ' . ($c['klien'] ?? ''))) ?>">
                                
                                <td class="px-4 py-3.5 font-mono text-xs font-bold text-slate-800 whitespace-nowrap">
                                    <?= htmlspecialchars($c['nomor_kontrak']) ?>
                                </td>
                                
                                <td class="px-4 py-3.5">
                                    <p class="font-bold text-slate-800 text-xs leading-tight"><?= htmlspecialchars($c['nama_projek']) ?></p>
                                    <span class="text-[10px] text-slate-400 font-medium">Klien: <?= htmlspecialchars($c['klien']) ?></span>
                                </td>
                                
                                <td class="px-4 py-3.5 text-xs text-slate-600 whitespace-nowrap">
                                    <span class="font-bold text-emerald-700"><?= $c['durasi_bulan'] ?> Bulan</span>
                                    <span class="block text-[10px] text-slate-400 font-medium">
                                        <?= !empty($c['tanggal_mulai']) ? date('d/m/Y', strtotime($c['tanggal_mulai'])) : '-' ?> s/d <?= !empty($c['tanggal_selesai']) ? date('d/m/Y', strtotime($c['tanggal_selesai'])) : '-' ?>
                                    </span>
                                </td>
                                
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                    Rp <?= number_format($c['nilai_kontrak'], 0, ',', '.') ?>
                                </td>
                                
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <a href="print_pdf.php?id=<?= $c['id'] ?>" target="_blank" class="btn-press px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-extrabold hover:bg-emerald-100 transition inline-flex items-center gap-1.5 shadow-2xs">
                                        <i class="fa-solid fa-print text-emerald-600"></i> Cetak / PDF
                                    </a>
                                </td>
                                
                                <td class="px-4 py-3.5 text-center whitespace-nowrap">
                                    <a href="index.php?delete=<?= $c['id'] ?>" onclick="return confirm('Hapus arsip kontrak ini?')" class="btn-press p-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg transition inline-flex items-center justify-center" title="Hapus">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls (Slide Halaman 1, 2, 3 dst) -->
        <div class="p-3.5 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs bg-slate-50/50">
            <span class="text-slate-500 font-bold text-[10px]" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1" id="paginationControls"></div>
        </div>
    </div>
</main>

<script>
const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    filterContracts();
});

function filterContracts() {
    const searchVal = document.getElementById('contractSearchInput').value.toLowerCase();
    const allRows   = Array.from(document.querySelectorAll('.contract-row'));

    filteredRows = allRows.filter(row => {
        const searchData = row.getAttribute('data-search') || '';
        return searchData.includes(searchVal);
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.contract-row');
    allRows.forEach(row => row.style.display = 'none');

    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / rowsPerPage) || 1;

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx   = startIdx + rowsPerPage;

    filteredRows.slice(startIdx, endIdx).forEach(row => {
        row.style.display = 'table-row';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd   = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} data`;

    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';

    if (totalPages <= 1) return;

    // Previous Button
    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-2xs'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Page Numbers
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-bold ${i === currentPage ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-2xs'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50 shadow-2xs'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>