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
$stmt = $pdo->query("
    SELECT c.*, 
           COALESCE(p.nama_projek, 'Kontrak Mandiri / Pengajuan') as nama_projek, 
           COALESCE(p.klien, '-') as klien, 
           COALESCE(p.budget, c.nilai_kontrak) as budget 
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    ORDER BY c.created_at DESC
");
$contracts = $stmt->fetchAll();
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { 
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

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation (Konsisten dengan Proposals) -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL KONTRAK KERJA & PERJANJIAN
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Generator Kontrak Kerja & PDF</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Pembuatan & cetak dokumen perjanjian proyek resmi PT. Cipta Teknologi Cendekia</p>
        </div>
        <a href="generate.php" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 shrink-0">
            <i class="fa-solid fa-file-circle-plus text-xs sm:text-sm"></i> Buat Kontrak Baru
        </a>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="contractSearchInput" onkeyup="filterContracts()" placeholder="Cari no. kontrak, proyek, atau klien..." 
                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="text-xs sm:text-sm text-slate-500 font-medium flex items-center gap-1.5 self-end sm:self-auto">
            <i class="fa-solid fa-file-contract text-emerald-600"></i> Total Arsip: <span class="font-semibold text-slate-800"><?= count($contracts) ?> Dokumen</span>
        </div>
    </div>

    <!-- Tabel Daftar Kontrak Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-slate-800 text-xs sm:text-sm uppercase tracking-wider">Arsip Kontrak Proyek</h3>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm table-fixed min-w-[850px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-[20%]">NO. KONTRAK</th>
                        <th class="py-3.5 px-4 w-[28%]">PROYEK & KLIEN</th>
                        <th class="py-3.5 px-4 w-[20%]">DURASI PENGERJAAN</th>
                        <th class="py-3.5 px-4 w-[16%] text-right">NILAI KONTRAK</th>
                        <th class="py-3.5 px-4 w-[10%] text-center">CETAK</th>
                        <th class="py-3.5 px-4 w-[6%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="contractTableBody">
                    <?php if (empty($contracts)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 font-normal">
                                Belum ada kontrak proyek yang dibuat. Klik tombol "Buat Kontrak Baru" untuk membuat dokumen perjanjian resmi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contracts as $c): ?>
                            <tr class="hover:bg-slate-50/70 transition contract-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower(($c['nomor_kontrak'] ?? '') . ' ' . ($c['nama_projek'] ?? '') . ' ' . ($c['klien'] ?? ''))) ?>">
                                
                                <td class="py-3.5 px-4 font-mono font-semibold text-slate-900 text-xs sm:text-sm truncate">
                                    <?= htmlspecialchars($c['nomor_kontrak']) ?>
                                </td>
                                
                                <td class="py-3.5 px-4">
                                    <p class="font-semibold text-slate-900 text-xs sm:text-sm truncate"><?= htmlspecialchars($c['nama_projek']) ?></p>
                                    <p class="text-xs text-slate-400 font-normal truncate mt-0.5">Klien: <span class="font-medium text-slate-600"><?= htmlspecialchars($c['klien']) ?></span></p>
                                </td>
                                
                                <td class="py-3.5 px-4 whitespace-nowrap">
                                    <p class="font-semibold text-emerald-700 text-xs sm:text-sm"><?= $c['durasi_bulan'] ?> Bulan</p>
                                    <p class="text-xs text-slate-400 font-normal mt-0.5">
                                        <?= !empty($c['tanggal_mulai']) ? date('d/m/Y', strtotime($c['tanggal_mulai'])) : '-' ?> s/d <?= !empty($c['tanggal_selesai']) ? date('d/m/Y', strtotime($c['tanggal_selesai'])) : '-' ?>
                                    </p>
                                </td>
                                
                                <td class="py-3.5 px-4 text-right font-mono font-semibold text-slate-900 text-xs sm:text-sm whitespace-nowrap">
                                    Rp <?= number_format($c['nilai_kontrak'], 0, ',', '.') ?>
                                </td>
                                
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="print_pdf.php?id=<?= $c['id'] ?>" target="_blank" class="btn-press px-2.5 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition inline-flex items-center gap-1.5 shadow-2xs">
                                        <i class="fa-solid fa-print text-emerald-600"></i> Cetak / PDF
                                    </a>
                                </td>
                                
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <a href="index.php?delete=<?= $c['id'] ?>" onclick="return confirm('Hapus arsip kontrak ini?')" class="btn-press p-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition inline-flex items-center justify-center" title="Hapus Data">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500 bg-slate-50/50">
            <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1.5" id="paginationControls"></div>
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
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Page Numbers
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage--; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>