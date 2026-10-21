<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
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
    $stmtDelChat = $pdo->prepare("DELETE FROM deal_chats WHERE requirement_id = ?");
    $stmtDelChat->execute([$delete_id]);

    // Hapus data requirement
    $stmtDel = $pdo->prepare("DELETE FROM client_requirements WHERE id = ?");
    $stmtDel->execute([$delete_id]);

    header("Location: index.php?msg=deleted");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Fetch Seluruh Requirement
$stmt = $pdo->query("SELECT * FROM client_requirements ORDER BY created_at DESC");
$requirements = $stmt->fetchAll();

// Unread chat per requirement (dari klien, belum dibaca admin)
$unreadMap = $pdo->query("SELECT requirement_id, COUNT(*) AS c FROM deal_chats WHERE sender_type = 'client' AND admin_read = 0 GROUP BY requirement_id")
    ->fetchAll(PDO::FETCH_KEY_PAIR);

// Resolve link file BRD: cari file yang benar-benar ada di server
function resolveBrdUrl($filename) {
    $filename = trim((string) $filename);
    if ($filename === '') return '';
    $candidates = [
        ['path' => __DIR__ . '/../../uploads/brd/' . $filename,     'url' => '../../uploads/brd/' . rawurlencode($filename)],
        ['path' => __DIR__ . '/../../uploads/' . $filename,        'url' => '../../uploads/' . rawurlencode($filename)],
        ['path' => __DIR__ . '/../../assets/uploads/' . $filename, 'url' => '../../assets/uploads/' . rawurlencode($filename)],
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand['path'])) return $cand['url'];
    }
    return $candidates[0]['url']; // fallback folder utama
}

// Hitung Metrics Bar
$totalReq  = count($requirements);
$reviewReq = 0;
$dealReq   = 0;

foreach ($requirements as $r) {
    if ($r['status'] === 'Under Review') $reviewReq++;
    elseif ($r['status'] === 'Deal / Accepted') $dealReq++;
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

<main class="flex-1 p-3 sm:p-5 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">

    <!-- Top Header Banner -->
    <div class="mb-4 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs">
        <div class="flex items-center gap-2 mb-0.5">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span class="text-[9px] font-black uppercase tracking-wider text-emerald-700">CLIENT HUB</span>
        </div>
        <h1 class="text-lg md:text-xl font-black text-slate-900 tracking-tight">Client Requirements & Inquiries</h1>
        <p class="text-[11px] text-slate-500 font-medium">Daftar pengajuan requirement proyek dari calon klien & status negosiasi penawaran</p>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-3.5 py-2.5 rounded-xl text-xs font-bold mb-4 flex items-center gap-2 shadow-2xs">
            <i class="fa-solid fa-trash-can text-red-500"></i> Data requirement berhasil dihapus dari sistem!
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-2xs">
            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider">TOTAL PERMINTAAN</span>
            <p class="text-xl font-black text-slate-800 mt-0.5"><?= $totalReq ?></p>
        </div>
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-2xs">
            <span class="text-[9px] font-black uppercase text-blue-500 tracking-wider">DALAM PENINJAUAN (UNDER REVIEW)</span>
            <p class="text-xl font-black text-blue-600 mt-0.5"><?= $reviewReq ?></p>
        </div>
        <div class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-2xs">
            <span class="text-[9px] font-black uppercase text-emerald-500 tracking-wider">DEAL / ACCEPTED</span>
            <p class="text-xl font-black text-emerald-600 mt-0.5"><?= $dealReq ?></p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-3 mb-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-2.5">
        <div class="relative w-full sm:w-80">
            <input type="text" id="reqSearchInput" onkeyup="filterRequirements()" placeholder="Cari nama klien, email, atau judul..." class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
            <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2.5 text-slate-400 text-xs"></i>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterRequirements()" class="input-glow px-3 py-2 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status</option>
                <option value="Under Review">Under Review</option>
                <option value="Proposal Sent">Proposal Sent</option>
                <option value="Deal / Accepted">Deal / Accepted</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-2xs overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs table-fixed min-w-[760px]">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-200 text-[9px] font-black text-slate-400 uppercase tracking-wider">
                        <th class="py-3 px-3 w-[22%]">KLIEN & INSTANSI</th>
                        <th class="py-3 px-3 w-[28%]">JUDUL PROYEK / FEAT</th>
                        <th class="py-3 px-3 w-[15%]">EST. BUDGET</th>
                        <th class="py-3 px-3 w-[10%] text-center">DOKUMEN</th>
                        <th class="py-3 px-3 w-[12%] text-center">STATUS</th>
                        <th class="py-3 px-3 w-[13%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="reqTableBody">
                    <?php if (empty($requirements)): ?>
                        <tr>
                            <td colspan="6" class="p-6 text-center text-slate-400 font-bold">
                                Belum ada pengajuan requirement klien.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requirements as $r): ?>
                            <?php
                                $brd_url = resolveBrdUrl($r['file_attachment'] ?? '');
                                $unread  = (int) ($unreadMap[$r['id']] ?? 0);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition req-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower(($r['client_name'] ?? '') . ' ' . ($r['client_email'] ?? '') . ' ' . ($r['title'] ?? ''))) ?>"
                                data-status="<?= htmlspecialchars($r['status'] ?? 'Under Review') ?>">

                                <!-- Klien & Instansi -->
                                <td class="py-3 px-3">
                                    <p class="font-black text-slate-900 text-xs truncate"><?= htmlspecialchars($r['client_name'] ?? '-') ?></p>
                                    <p class="text-[10px] text-slate-400 font-medium truncate"><?= htmlspecialchars($r['client_email'] ?? '-') ?></p>
                                    <p class="text-[9px] text-emerald-700 font-extrabold truncate"><?= htmlspecialchars($r['company_name'] ?? 'PT. / Individual') ?></p>
                                </td>

                                <!-- Judul Proyek -->
                                <td class="py-3 px-3">
                                    <p class="font-bold text-slate-800 leading-snug truncate"><?= htmlspecialchars($r['title'] ?? '-') ?></p>
                                    <p class="text-[10px] text-slate-400 font-medium">
                                        <i class="fa-regular fa-clock mr-1"></i><?= !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '-' ?>
                                    </p>
                                </td>

                                <!-- Est. Budget -->
                                <td class="py-3 px-3 font-mono font-bold text-slate-800 text-[11px] truncate">
                                    Rp <?= number_format($r['budget_estimation'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <!-- Dokumen BRD (path otomatis sesuai file yg ada) -->
                                <td class="py-3 px-3 text-center">
                                    <?php if ($brd_url !== ''): ?>
                                        <a href="<?= $brd_url ?>" target="_blank" class="btn-press p-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-[10px] font-extrabold hover:bg-emerald-100 transition inline-flex items-center gap-1 shadow-2xs" title="Unduh File BRD">
                                            <i class="fa-solid fa-file-pdf text-emerald-600"></i> Unduh
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-[10px]">Tidak Ada</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3 px-3 text-center">
                                    <span class="px-2 py-1 rounded-lg text-[8px] font-black uppercase inline-block border tracking-wider shadow-2xs truncate max-w-full
                                        <?php
                                            switch($r['status']) {
                                                case 'Under Review': echo 'bg-blue-50 text-blue-600 border-blue-200'; break;
                                                case 'Proposal Sent': echo 'bg-indigo-50 text-indigo-600 border-indigo-200'; break;
                                                case 'Deal / Accepted': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'Rejected': echo 'bg-slate-100 text-slate-500 border-slate-200'; break;
                                                default: echo 'bg-slate-50 text-slate-600 border-slate-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($r['status'] ?? 'Under Review') ?>
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="detail.php?id=<?= $r['id'] ?>" class="btn-press relative px-2.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-extrabold text-[10px] rounded-lg transition inline-flex items-center gap-1 shadow-2xs">
                                            <i class="fa-solid fa-comments text-emerald-400"></i> Detail
                                            <?php if ($unread > 0): ?>
                                                <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[8px] font-black min-w-[15px] h-[15px] px-0.5 rounded-full flex items-center justify-center border border-white animate-pulse"><?= $unread ?></span>
                                            <?php endif; ?>
                                        </a>
                                        <a href="index.php?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Yakin ingin menghapus data requirement ini beserta obrolan negosiasinya?')" class="btn-press p-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-lg transition inline-flex items-center justify-center" title="Hapus Data">
                                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-3 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-2 text-[11px] bg-slate-50/50">
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

    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.className = `btn-press px-2 py-0.5 rounded-md font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-2.5 py-0.5 rounded-md font-bold ${i === currentPage ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.className = `btn-press px-2 py-0.5 rounded-md font-bold ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>