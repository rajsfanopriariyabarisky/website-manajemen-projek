<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Seluruh Proyek Terdaftar
$projects =$pdo->query("
    SELECT p.*, 
           COUNT(c.id) as total_contracts
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
")->fetchAll();
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    .proposal-card { transition: transform .25s cubic-bezier(.22,.68,0,1), box-shadow .25s ease, border-color .25s ease; }
    .proposal-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 26px -10px rgba(15,23,42,.12);
        border-color: rgba(16,185,129,.4);
    }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-sm">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                    MODUL PROPOSAL PENAWARAN
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Surat Penawaran & Commercial Proposal</h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Otomatisasi pencetakan Surat Penawaran Proyek resmi PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="proposalSearchInput" onkeyup="filterProposals()" placeholder="Cari nama proyek / klien..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterProposals()" class="input-glow px-3 py-2 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
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
            <div class="col-span-full bg-white border border-slate-200/90 rounded-2xl p-12 text-center text-slate-400 shadow-sm">
                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-file-signature"></i>
                </div>
                <p class="font-extrabold text-slate-800 text-sm">Belum ada proyek terdaftar.</p>
                <p class="text-xs text-slate-400 mt-1 font-medium">Tambahkan proyek baru terlebih dahulu di menu Proyek & Timeline untuk mencetak penawaran.</p>
            </div>
        <?php else: ?>
            <?php foreach ($projects as$p): ?>
                <div class="proposal-card bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm flex flex-col justify-between relative overflow-hidden"
                     data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' .$p['klien'])) ?>"
                     data-status="<?= htmlspecialchars($p['status']) ?>">
                    
                    <div>
                        <!-- Header Status Badge & Tanggal -->
                        <div class="flex justify-between items-center mb-3.5">
                            <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <i class="fa-solid fa-bolt mr-1 text-[8px]"></i> Generate Otomatis
                            </span>
                            <span class="text-[11px] text-slate-400 font-bold flex items-center gap-1">
                                <i class="fa-regular fa-calendar text-[10px]"></i> <?= date('d M Y', strtotime($p['tanggal_mulai'])) ?>
                            </span>
                        </div>

                        <!-- Info Proyek -->
                        <h3 class="font-black text-slate-900 text-base mb-1 truncate leading-snug" title="<?= htmlspecialchars($p['nama_projek']) ?>">
                            <?= htmlspecialchars($p['nama_projek']) ?>
                        </h3>
                        
                        <p class="text-xs text-slate-500 font-bold mb-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-building text-slate-400 text-[11px]"></i> Klien Target: <span class="font-extrabold text-slate-800"><?= htmlspecialchars($p['klien']) ?></span>
                        </p>

                        <!-- Box Metric Nilai Penawaran & Status -->
                        <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3 grid grid-cols-2 gap-2 text-xs mb-5">
                            <div>
                                <span class="text-[9px] text-slate-400 font-black block uppercase tracking-wider mb-0.5">Nilai Penawaran</span>
                                <span class="font-black text-emerald-700 text-xs font-mono">
                                    Rp <?= number_format($p['budget'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[9px] text-slate-400 font-black block uppercase tracking-wider mb-0.5">Status Proyek</span>
                                <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase inline-block border tracking-wide
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
                    <a href="print_proposal.php?project_id=<?= $p['id'] ?>" target="_blank" class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl transition text-center flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20">
                        <i class="fa-solid fa-print text-xs"></i> Generate & Cetak Penawaran PDF
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginasi Interaktif -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-6 mt-6 border-t border-slate-200/80 text-xs">
        <span class="text-slate-500 font-bold text-[11px]" id="paginationInfo">Menampilkan 0 data</span>
        <div class="flex items-center gap-1" id="paginationControls"></div>
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
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Page Numbers (1, 2, 3...)
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-bold ${i === currentPage ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>