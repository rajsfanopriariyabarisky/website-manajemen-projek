<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle Hapus Requirement
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    
    // Hapus pesan chat terkait
    $stmtDelChat =$pdo->prepare("DELETE FROM deal_chats WHERE requirement_id = ?");
    $stmtDelChat->execute([$delete_id]);

    // Hapus data requirement
    $stmtDel =$pdo->prepare("DELETE FROM client_requirements WHERE id = ?");
    $stmtDel->execute([$delete_id]);

    header("Location: index.php?msg=deleted");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Seluruh Requirement
$stmt =$pdo->query("SELECT * FROM client_requirements ORDER BY created_at DESC");
$requirements =$stmt->fetchAll();

// Hitung Metrics Bar
$totalReq   = count($requirements);$pendingReq = 0;
$reviewReq  = 0;
$dealReq    = 0;

foreach ($requirements as$r) {
    if ($r['status'] === 'Pending')$pendingReq++;
    elseif ($r['status'] === 'Under Review')$reviewReq++;
    elseif ($r['status'] === 'Deal / Accepted')$dealReq++;
}
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- Top Header Banner -->
    <div class="mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-sm">
        <div class="flex items-center gap-2 mb-1">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700">CLIENT HUB</span>
        </div>
        <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Client Requirements & Inquiries</h1>
        <p class="text-xs text-slate-500 font-medium mt-0.5">Daftar pengajuan requirement proyek dari calon klien & status negosiasi penawaran</p>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) &&$_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-trash-can text-red-500"></i> Data requirement berhasil dihapus dari sistem!
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm">
            <span class="text-[10px] font-black uppercase text-slate-400 tracking-wider">TOTAL PERMINTAAN</span>
            <p class="text-2xl font-black text-slate-800 mt-1"><?= $totalReq ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm">
            <span class="text-[10px] font-black uppercase text-red-500 tracking-wider">PERLU DITINJAU (PENDING)</span>
            <p class="text-2xl font-black text-red-600 mt-1"><?= $pendingReq ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm">
            <span class="text-[10px] font-black uppercase text-blue-500 tracking-wider">DALAM PENINJAUAN</span>
            <p class="text-2xl font-black text-blue-600 mt-1"><?= $reviewReq ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm">
            <span class="text-[10px] font-black uppercase text-emerald-500 tracking-wider">DEAL / ACCEPTED</span>
            <p class="text-2xl font-black text-emerald-600 mt-1"><?= $dealReq ?></p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-96">
            <input type="text" id="reqSearchInput" onkeyup="filterRequirements()" placeholder="Cari nama klien, email, atau judul proyek..." class="input-glow w-full px-3.5 py-2.5 pl-9 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterRequirements()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status</option>
                <option value="Pending">Pending</option>
                <option value="Under Review">Under Review</option>
                <option value="Proposal Sent">Proposal Sent</option>
                <option value="Deal / Accepted">Deal / Accepted</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse text-xs min-w-[900px]">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 px-4">KLIEN & INSTANSI</th>
                        <th class="py-3.5 px-4">JUDUL PROYEK / SYSTEM</th>
                        <th class="py-3.5 px-4 whitespace-nowrap">EST. BUDGET</th>
                        <th class="py-3.5 px-4 text-center whitespace-nowrap">DOKUMEN</th>
                        <th class="py-3.5 px-4 text-center whitespace-nowrap">STATUS</th>
                        <th class="py-3.5 px-4 whitespace-nowrap">TANGGAL</th>
                        <th class="py-3.5 px-4 text-center whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="reqTableBody">
                    <?php if (empty($requirements)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 font-bold">
                                Belum ada pengajuan requirement klien.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requirements as$r): ?>
                            <tr class="hover:bg-slate-50/80 transition req-row align-middle" 
                                data-search="<?= htmlspecialchars(strtolower(($r['client_name'] ?? $r['nama_klien'] ?? '') . ' ' . ($r['client_email'] ?? $r['email'] ?? '') . ' ' . ($r['title'] ?? $r['judul_projek'] ?? ''))) ?>"
                                data-status="<?= htmlspecialchars($r['status'] ?? 'Pending') ?>">
                                
                                <!-- Klien & Instansi -->
                                <td class="py-4 px-4 min-w-[200px]">
                                    <p class="font-black text-slate-900 text-xs"><?= htmlspecialchars($r['client_name'] ?? $r['nama_klien'] ?? '-') ?></p>
                                    <p class="text-[11px] text-slate-400 font-medium truncate max-w-[220px]"><?= htmlspecialchars($r['client_email'] ?? $r['email'] ?? '-') ?></p>
                                    <p class="text-[10px] text-emerald-700 font-extrabold mt-0.5 truncate"><?= htmlspecialchars($r['company_name'] ?? 'PT. / Individual') ?></p>
                                </td>

                                <!-- Judul Proyek -->
                                <td class="py-4 px-4 min-w-[220px]">
                                    <p class="font-bold text-slate-800 leading-snug"><?= htmlspecialchars($r['title'] ?? $r['judul_projek'] ?? '-') ?></p>
                                    <p class="text-[11px] text-slate-400 truncate max-w-[240px] mt-0.5 font-medium"><?= htmlspecialchars($r['description'] ?? $r['deskripsi_projek'] ?? '') ?></p>
                                </td>

                                <!-- Est. Budget -->
                                <td class="py-4 px-4 font-mono font-bold text-slate-800 whitespace-nowrap">
                                    Rp <?= number_format($r['budget_estimation'] ?? $r['estimasi_budget'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <!-- Dokumen BRD -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <?php if (!empty($r['file_attachment'])): ?>
                                        <a href="../../uploads/requirements/<?= urlencode($r['file_attachment']) ?>" target="_blank" class="btn-press px-3 py-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[11px] font-extrabold hover:bg-emerald-100 transition inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fa-solid fa-file-pdf text-emerald-600"></i> Unduh File
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[11px] font-medium">Tidak Ada</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <span class="px-3 py-1.5 rounded-lg text-[9px] font-black uppercase inline-block border tracking-wider whitespace-nowrap shadow-sm
                                        <?php
                                            switch($r['status']) {
                                                case 'Pending': echo 'bg-red-50 text-red-600 border-red-200'; break;
                                                case 'Under Review': echo 'bg-blue-50 text-blue-600 border-blue-200'; break;
                                                case 'Proposal Sent': echo 'bg-indigo-50 text-indigo-600 border-indigo-200'; break;
                                                case 'Deal / Accepted': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'Rejected': echo 'bg-slate-100 text-slate-500 border-slate-200'; break;
                                                default: echo 'bg-slate-50 text-slate-600 border-slate-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($r['status'] ?? 'Pending') ?>
                                    </span>
                                </td>

                                <!-- Tanggal -->
                                <td class="py-4 px-4 text-slate-400 font-semibold text-[11px] whitespace-nowrap">
                                    <?= !empty($r['created_at']) ? date('d M Y H:i', strtotime($r['created_at'])) : '-' ?>
                                </td>

                                <!-- Aksi -->
                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <div class="flex items-center justify-center gap-2">
                                        <a href="detail.php?id=<?= $r['id'] ?>" class="btn-press px-3.5 py-2 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-[11px] rounded-xl transition inline-flex items-center gap-1.5 shadow-sm">
                                            <i class="fa-solid fa-comments text-emerald-400"></i> Negosiasi & Chat
                                        </a>
                                        <a href="index.php?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Yakin ingin menghapus data requirement ini beserta obrolan negosiasinya?')" class="btn-press p-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition inline-flex items-center justify-center" title="Hapus Data">
                                            <i class="fa-solid fa-trash-can text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs bg-slate-50/50">
            <span class="text-slate-500 font-bold text-[11px]" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1" id="paginationControls"></div>
        </div>
    </div>

</main>

<script>
const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    filterRequirements();
});

function filterRequirements() {
    const searchVal = document.getElementById('reqSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const allRows   = Array.from(document.querySelectorAll('.req-row'));

    filteredRows = allRows.filter(row => {
        const searchData = row.getAttribute('data-search') || '';
        const statusData = row.getAttribute('data-status') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (statusData === statusVal);

        return matchesSearch && matchesStatus;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.req-row');
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
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Page Numbers
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
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage--; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>