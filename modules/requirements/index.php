<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Pastikan kolom requirement_id ada di tabel projects untuk sinkronisasi
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN requirement_id INT NULL AFTER client_token");
} catch (Exception $e) {}

// Handle Hapus Requirement & Hapus File Fisik BRD
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);

    // 1. Ambil data nama file BRD sebelum rekord database dihapus
    $stmtFile = $pdo->prepare("SELECT file_attachment FROM client_requirements WHERE id = ?");
    $stmtFile->execute([$delete_id]);
    $req_data = $stmtFile->fetch();

    if ($req_data && !empty($req_data['file_attachment'])) {
        $file_name = $req_data['file_attachment'];
        $possible_paths = [
            __DIR__ . '/../../uploads/brd/' . $file_name,
            __DIR__ . '/../../uploads/' . $file_name,
            __DIR__ . '/../../assets/uploads/' . $file_name
        ];

        // Hapus file fisik dari folder penyimpanan laptop/server
        foreach ($possible_paths as $path) {
            if (file_exists($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    // 2. Hapus pesan chat terkait
    $stmtDelChat = $pdo->prepare("DELETE FROM deal_chats WHERE requirement_id = ?");
    $stmtDelChat->execute([$delete_id]);

    // 3. Hapus data requirement
    $stmtDel = $pdo->prepare("DELETE FROM client_requirements WHERE id = ?");
    $stmtDel->execute([$delete_id]);

    header("Location: index.php?msg=deleted");
    exit;
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// FETCH DATA REQUIREMENT & CEK SINKRONISASI PROYEK TERKAIT
$stmt = $pdo->query("
    SELECT cr.*, p.id AS linked_project_id, p.status AS linked_project_status
    FROM client_requirements cr 
    INNER JOIN clients c ON (cr.client_id = c.id OR LOWER(cr.client_email) = LOWER(c.email)) 
    LEFT JOIN projects p ON p.requirement_id = cr.id
    WHERE c.is_verified = 1 
    ORDER BY cr.created_at DESC
");
$requirements = $stmt->fetchAll();

// Unread chat per requirement
$unreadMap = $pdo->query("
    SELECT d.requirement_id, COUNT(*) AS c 
    FROM deal_chats d
    INNER JOIN client_requirements cr ON d.requirement_id = cr.id
    INNER JOIN clients c ON (cr.client_id = c.id OR LOWER(cr.client_email) = LOWER(c.email))
    WHERE d.sender_type = 'client' AND d.admin_read = 0 AND c.is_verified = 1 
    GROUP BY d.requirement_id
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Resolve link file BRD
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
    return $candidates[0]['url'];
}

// Metrics Bar
$totalReq  = count($requirements);
$reviewReq = 0;
$dealReq   = 0;

foreach ($requirements as $r) {
    if ($r['status'] === 'Under Review') $reviewReq++;
    elseif ($r['status'] === 'Deal / Accepted') $dealReq++;
}
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
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
                    MODUL REQUIREMENTS & CLIENT INQUIRIES
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Client Requirements & Inquiries</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Daftar pengajuan requirement proyek dari calon klien & status negosiasi penawaran</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-trash-can text-red-500 text-base"></i> Data requirement berhasil dihapus dari sistem!
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs">
            <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider">TOTAL PERMINTAAN</span>
            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $totalReq ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs">
            <span class="text-xs font-semibold uppercase text-blue-600 tracking-wider">DALAM PENINJAUAN (UNDER REVIEW)</span>
            <p class="text-2xl font-bold text-blue-600 mt-1"><?= $reviewReq ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs">
            <span class="text-xs font-semibold uppercase text-emerald-600 tracking-wider">DEAL / ACCEPTED</span>
            <p class="text-2xl font-bold text-emerald-600 mt-1"><?= $dealReq ?></p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="reqSearchInput" onkeyup="filterRequirements()" placeholder="Cari nama klien, email, atau judul..." 
                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterRequirements()" 
                    class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status</option>
                <option value="Under Review">Under Review</option>
                <option value="Proposal Sent">Proposal Sent</option>
                <option value="Deal / Accepted">Deal / Accepted</option>
                <option value="Rejected">Rejected</option>
            </select>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm table-fixed min-w-[920px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-[22%]">KLIEN & INSTANSI</th>
                        <th class="py-3.5 px-4 w-[23%]">JUDUL PROYEK</th>
                        <th class="py-3.5 px-4 w-[15%]">EST. BUDGET</th>
                        <th class="py-3.5 px-4 w-[10%] text-center">BRD</th>
                        <th class="py-3.5 px-4 w-[14%] text-center">STATUS</th>
                        <th class="py-3.5 px-4 w-[16%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="reqTableBody">
                    <?php if (empty($requirements)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-400 font-normal">
                                Belum ada pengajuan requirement klien yang terverifikasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requirements as $r): ?>
                            <?php
                                $brd_url = resolveBrdUrl($r['file_attachment'] ?? '');
                                $unread  = (int) ($unreadMap[$r['id']] ?? 0);
                                $hasProject = !empty($r['linked_project_id']);
                            ?>
                            <tr class="hover:bg-slate-50/70 transition req-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower(($r['client_name'] ?? '') . ' ' . ($r['client_email'] ?? '') . ' ' . ($r['title'] ?? ''))) ?>"
                                data-status="<?= htmlspecialchars($r['status'] ?? 'Under Review') ?>">

                                <!-- Klien & Instansi -->
                                <td class="py-3.5 px-4">
                                    <p class="font-semibold text-slate-900 text-xs sm:text-sm truncate"><?= htmlspecialchars($r['client_name'] ?? '-') ?></p>
                                    <p class="text-xs text-slate-400 font-normal truncate"><?= htmlspecialchars($r['client_email'] ?? '-') ?></p>
                                    <p class="text-[11px] text-emerald-700 font-semibold truncate mt-0.5"><?= htmlspecialchars($r['company_name'] ?? 'PT. / Individual') ?></p>
                                </td>

                                <!-- Judul Proyek -->
                                <td class="py-3.5 px-4">
                                    <p class="font-medium text-slate-800 leading-snug truncate"><?= htmlspecialchars($r['title'] ?? '-') ?></p>
                                    <p class="text-xs text-slate-400 font-normal mt-0.5">
                                        <i class="fa-regular fa-clock mr-1"></i><?= !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '-' ?>
                                    </p>
                                </td>

                                <!-- Est. Budget -->
                                <td class="py-3.5 px-4 font-mono font-semibold text-slate-800 text-xs sm:text-sm truncate">
                                    Rp <?= number_format($r['budget_estimation'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <!-- Dokumen BRD -->
                                <td class="py-3.5 px-4 text-center">
                                    <?php if ($brd_url !== ''): ?>
                                        <a href="<?= $brd_url ?>" target="_blank" class="btn-press px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200/80 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition inline-flex items-center gap-1 shadow-2xs">
                                            <i class="fa-solid fa-file-pdf text-emerald-600"></i> Unduh
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-xs font-normal">Tidak Ada</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-lg text-[11px] font-semibold uppercase inline-block border tracking-wider whitespace-nowrap
                                        <?php
                                            switch($r['status']) {
                                                case 'Under Review': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                case 'Proposal Sent': echo 'bg-amber-50 text-amber-800 border-amber-200'; break;
                                                case 'Deal / Accepted': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                case 'Rejected': echo 'bg-slate-100 text-slate-600 border-slate-200'; break;
                                                default: echo 'bg-slate-50 text-slate-600 border-slate-200';
                                            }
                                        ?>">
                                        <?= htmlspecialchars($r['status'] ?? 'Under Review') ?>
                                    </span>
                                </td>

                                <!-- Aksi -->
                                <td class="py-3.5 px-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <a href="detail.php?id=<?= $r['id'] ?>" class="btn-press relative px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1 shadow-xs">
                                            <i class="fa-solid fa-comments text-xs"></i> Detail
                                            <?php if ($unread > 0): ?>
                                                <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[9px] font-bold min-w-[16px] h-[16px] px-1 rounded-full flex items-center justify-center border border-white animate-pulse"><?= $unread ?></span>
                                            <?php endif; ?>
                                        </a>

                                        <?php if ($hasProject): ?>
                                            <a href="../projects/detail.php?id=<?= $r['linked_project_id'] ?>" class="btn-press p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-semibold" title="Lihat Proyek Aktif">
                                                <i class="fa-solid fa-folder"></i>
                                            </a>
                                        <?php else: ?>
                                            <a href="../projects/index.php?convert_req=1&req_id=<?= $r['id'] ?>&client_name=<?= urlencode($r['client_name']) ?>&title=<?= urlencode($r['title']) ?>&budget=<?= urlencode($r['budget_estimation']) ?>" 
                                               class="btn-press p-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-xl text-xs font-semibold" title="Convert to Active Project">
                                                <i class="fa-solid fa-plus"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="index.php?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Yakin ingin menghapus data requirement ini beserta obrolan negosiasinya?')" 
                                           class="btn-press p-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition inline-flex items-center justify-center" title="Hapus Data">
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
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>