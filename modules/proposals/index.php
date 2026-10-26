<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Seluruh Proyek Terdaftar
$projects = $pdo->query("
    SELECT p.*, 
           COUNT(c.id) as total_contracts
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
")->fetchAll();
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

    .proposal-card { 
        transition: transform .25s cubic-bezier(.22,.68,0,1), box-shadow .25s ease, border-color .25s ease; 
    }
    .proposal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px -8px rgba(15,23,42,.1);
        border-color: rgba(5,150,105,.4);
    }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL PROPOSAL PENAWARAN
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Surat Penawaran & Commercial Proposal</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Otomatisasi pencetakan Surat Penawaran Proyek resmi PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="proposalSearchInput" onkeyup="filterProposals()" placeholder="Cari nama proyek / klien..." 
                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterProposals()" 
                    class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status Proyek</option>
                <option value="In Progress">In Progress (Berjalan)</option>
                <option value="Completed">Completed (Selesai)</option>
                <option value="On Hold">On Hold (Ditunda)</option>
            </select>
        </div>
    </div>

    <!-- Project Proposal Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="proposalGrid">
        <?php if (empty($projects)): ?>
            <div class="col-span-full bg-white border border-slate-200/90 rounded-2xl p-12 text-center text-slate-400 shadow-xs">
                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <p class="font-bold text-slate-800 text-sm sm:text-base">Belum ada proyek terdaftar.</p>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 font-normal">Tambahkan proyek baru terlebih dahulu di menu Proyek & Timeline untuk mencetak penawaran.</p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <div class="proposal-card bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs flex flex-col justify-between relative overflow-hidden"
                     data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' . $p['klien'])) ?>"
                     data-status="<?= htmlspecialchars($p['status']) ?>">
                    
                    <div>
                        <!-- Header Status Badge & Tanggal -->
                        <div class="flex justify-between items-center mb-3">
                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i class="fa-solid fa-bolt mr-1 text-[9px]"></i> Generate Otomatis
                            </span>
                            <span class="text-xs text-slate-400 font-normal flex items-center gap-1">
                                <i class="fa-regular fa-calendar text-xs"></i> <?= date('d M Y', strtotime($p['tanggal_mulai'])) ?>
                            </span>
                        </div>

                        <!-- Info Proyek -->
                        <h3 class="font-bold text-slate-900 text-base mb-1 truncate leading-snug" title="<?= htmlspecialchars($p['nama_projek']) ?>">
                            <?= htmlspecialchars($p['nama_projek']) ?>
                        </h3>
                        
                        <p class="text-xs sm:text-sm text-slate-500 font-normal mb-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-building text-slate-400 text-xs"></i> Klien Target: <span class="font-semibold text-slate-800"><?= htmlspecialchars($p['klien']) ?></span>
                        </p>

                        <!-- Box Metric Nilai Penawaran & Status -->
                        <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3.5 grid grid-cols-2 gap-2 text-xs mb-5">
                            <div>
                                <span class="text-[10px] text-slate-400 font-medium block uppercase tracking-wider mb-0.5">Nilai Penawaran</span>
                                <span class="font-bold text-emerald-700 text-xs sm:text-sm font-mono">
                                    Rp <?= number_format($p['budget'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-medium block uppercase tracking-wider mb-0.5">Status Proyek</span>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold uppercase inline-block border tracking-wider
                                    <?php
                                        switch($p['status']) {
                                            case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                            case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                            case 'On Hold': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                            default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                        }
                                    ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Action Button -->
                    <a href="print_proposal.php?project_id=<?= $p['id'] ?>" target="_blank" 
                       class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm rounded-xl transition text-center flex items-center justify-center gap-2 shadow-xs">
                        <i class="fa-solid fa-print text-xs sm:text-sm"></i> Generate & Cetak Penawaran PDF
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginasi Interaktif -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-6 mt-6 border-t border-slate-200/80 text-xs text-slate-500">
        <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
        <div class="flex items-center gap-1.5" id="paginationControls"></div>
    </div>
</main>

<script>
const cardsPerPage = 6;
let currentPage = 1;
let filteredCards = [];

document.addEventListener('DOMContentLoaded', function() {
    filterProposals();
});

function filterProposals() {
    const searchVal = document.getElementById('proposalSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const allCards = Array.from(document.querySelectorAll('.proposal-card'));

    filteredCards = allCards.filter(card => {
        const searchData = card.getAttribute('data-search') || '';
        const statusData = card.getAttribute('data-status') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (statusData === statusVal);

        return matchesSearch && matchesStatus;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allCards = document.querySelectorAll('.proposal-card');
    allCards.forEach(card => card.style.display = 'none');

    const totalItems = filteredCards.length;
    const totalPages = Math.ceil(totalItems / cardsPerPage) || 1;

    const startIdx = (currentPage - 1) * cardsPerPage;
    const endIdx = startIdx + cardsPerPage;

    filteredCards.slice(startIdx, endIdx).forEach(card => {
        card.style.display = 'flex';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} proposal`;

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

    // Page Numbers (1, 2, 3...)
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
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>