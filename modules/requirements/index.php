<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Guard Schema: Pastikan kolom requirement_id pada tabel projects & kolom master clients siap
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN requirement_id INT NULL AFTER client_token");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN phone VARCHAR(50) NULL AFTER email");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN company_name VARCHAR(255) NULL AFTER phone");
} catch (Exception $e) {}

// Handle Hapus Requirement & File Fisik BRD
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

// FETCH DATA REQUIREMENT & TERHUBUNG DENGAN PROYEK + KONTRAK (Dengan GROUP BY Mencegah Duplikasi)
$stmt = $pdo->query("
    SELECT cr.*, 
           p.id AS linked_project_id, 
           p.status AS linked_project_status,
           p.budget AS proj_budget,
           p.persen_pajak AS proj_persen_pajak,
           p.nominal_pajak AS proj_nominal_pajak,
           p.total_nilai AS proj_total_nilai,
           c_contract.id AS linked_contract_id
    FROM client_requirements cr 
    INNER JOIN clients c ON (cr.client_id = c.id OR LOWER(cr.client_email) = LOWER(c.email)) 
    LEFT JOIN projects p ON p.requirement_id = cr.id
    LEFT JOIN contracts c_contract ON c_contract.project_id = p.id
    WHERE c.is_verified = 1 
    GROUP BY cr.id
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

// Metrics Counter
$totalReq   = count($requirements);
$reviewReq  = 0;
$proposalReq = 0;
$dealReq    = 0;

foreach ($requirements as $r) {
    if ($r['status'] === 'Under Review') $reviewReq++;
    elseif ($r['status'] === 'Proposal Sent') $proposalReq++;
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
                    CLIENT REQUIREMENTS & NEGOTIATION HUB
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Requirement & Negosiasi Klien</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Kelola pengajuan kebutuhan klien, terbitkan SPH & SPK, serta pantau status negosiasi secara real-time.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-trash-can text-red-500 text-base"></i> Data requirement berhasil dihapus dari sistem!
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition">
            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">TOTAL PERMINTAAN</span>
            <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1 font-mono"><?= number_format($totalReq) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-blue-200 transition">
            <span class="text-[10px] font-bold uppercase text-blue-600 tracking-wider">UNDER REVIEW</span>
            <p class="text-xl sm:text-2xl font-bold text-blue-600 mt-1 font-mono"><?= number_format($reviewReq) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-amber-200 transition">
            <span class="text-[10px] font-bold uppercase text-amber-600 tracking-wider">PROPOSAL SENT</span>
            <p class="text-xl sm:text-2xl font-bold text-amber-600 mt-1 font-mono"><?= number_format($proposalReq) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-emerald-200 transition">
            <span class="text-[10px] font-bold uppercase text-emerald-600 tracking-wider">DEAL / ACCEPTED</span>
            <p class="text-xl sm:text-2xl font-bold text-emerald-600 mt-1 font-mono"><?= number_format($dealReq) ?></p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="reqSearchInput" onkeyup="filterRequirements()" placeholder="Cari klien, instansi, atau judul..." 
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
            <table class="w-full text-left border-collapse text-xs sm:text-sm min-w-[1000px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">KLIEN & INSTANSI</th>
                        <th class="py-3.5 px-4">JUDUL PROYEK</th>
                        <th class="py-3.5 px-4 text-right">EST. BUDGET</th>
                        <th class="py-3.5 px-4 text-right">PAJAK (PPN)</th>
                        <th class="py-3.5 px-4 text-center">LAMPIRAN</th>
                        <th class="py-3.5 px-4 text-center">STATUS</th>
                        <th class="py-3.5 px-4 text-center whitespace-nowrap">AKSES CEPAT (AKSI)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="reqTableBody">
                    <?php if (empty($requirements)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 font-normal">
                                Belum ada pengajuan requirement klien yang terverifikasi.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($requirements as $r): ?>
                            <?php
                                $brd_url     = resolveBrdUrl($r['file_attachment'] ?? '');
                                $unread      = (int) ($unreadMap[$r['id']] ?? 0);
                                $hasProject  = !empty($r['linked_project_id']);
                                $hasContract = !empty($r['linked_contract_id']);
                                $status      = $r['status'] ?? 'Under Review';

                                $rowClass     = "hover:bg-slate-50/80 border-l-4 border-l-transparent";
                                $badgeClass   = "bg-slate-50 text-slate-600 border-slate-200";
                                $badgeText    = htmlspecialchars($status);
                                $hasPulseDot  = false;

                                if ($status === 'Under Review') {
                                    $rowClass     = "bg-rose-50/90 hover:bg-rose-100/80 border-l-4 border-l-rose-500";
                                    $badgeClass   = "bg-rose-100 text-rose-700 border-rose-300 font-extrabold shadow-2xs";
                                    $badgeText    = "UNDER REVIEW";
                                    $hasPulseDot  = true;
                                } elseif ($status === 'Proposal Sent') {
                                    $rowClass     = "bg-amber-50/20 hover:bg-amber-50/60 border-l-4 border-l-amber-400";
                                    $badgeClass   = "bg-amber-50 text-amber-800 border-amber-200 font-bold";
                                    $badgeText    = "PROPOSAL SENT";
                                } elseif ($status === 'Deal / Accepted') {
                                    $rowClass     = "bg-emerald-50/50 hover:bg-emerald-100/60 border-l-4 border-l-emerald-500";
                                    $badgeClass   = "bg-emerald-50 text-emerald-700 border-emerald-300 font-bold";
                                    $badgeText    = "DEAL / ACCEPTED";
                                } elseif ($status === 'Rejected') {
                                    $rowClass     = "bg-slate-100/80 hover:bg-slate-200/60 border-l-4 border-l-slate-400 opacity-75";
                                    $badgeClass   = "bg-slate-200 text-slate-700 border-slate-300 font-bold";
                                    $badgeText    = "REJECTED";
                                }

                                $pctPajak = isset($r['proj_persen_pajak']) && floatval($r['proj_persen_pajak']) > 0 ? floatval($r['proj_persen_pajak']) : 11.00;
                                $nomPajak = floatval($r['proj_nominal_pajak'] ?? 0);
                                if ($hasProject && $nomPajak <= 0 && isset($r['proj_budget']) && floatval($r['proj_budget']) > 0) {
                                    $nomPajak = round((floatval($r['proj_budget']) * $pctPajak) / 100, 2);
                                }
                            ?>
                            <tr class="<?= $rowClass ?> transition-all duration-150 req-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower(($r['client_name'] ?? '') . ' ' . ($r['client_email'] ?? '') . ' ' . ($r['title'] ?? ''))) ?>"
                                data-status="<?= htmlspecialchars($status) ?>">

                                <!-- Klien & Instansi -->
                                <td class="py-3.5 px-4 max-w-[220px]">
                                    <p class="font-bold text-slate-900 text-xs sm:text-sm truncate mb-0.5"><?= htmlspecialchars($r['client_name'] ?? '-') ?></p>
                                    <p class="text-xs text-slate-400 font-normal truncate"><?= htmlspecialchars($r['client_email'] ?? '-') ?></p>
                                    <p class="text-[11px] text-teal-700 font-semibold truncate mt-0.5"><?= htmlspecialchars($r['company_name'] ?? 'PT. / Individual') ?></p>
                                </td>

                                <!-- Judul Proyek -->
                                <td class="py-3.5 px-4 max-w-[250px]">
                                    <p class="font-semibold text-slate-800 leading-snug truncate mb-0.5" title="<?= htmlspecialchars($r['title'] ?? '-') ?>"><?= htmlspecialchars($r['title'] ?? '-') ?></p>
                                    <p class="text-xs text-slate-400 font-normal flex items-center gap-1">
                                        <i class="fa-regular fa-clock text-[10px]"></i> <?= !empty($r['created_at']) ? date('d M Y', strtotime($r['created_at'])) : '-' ?>
                                    </p>
                                </td>

                                <!-- Est. Budget -->
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-slate-900 text-xs sm:text-sm whitespace-nowrap">
                                    Rp <?= number_format($r['budget_estimation'] ?? 0, 0, ',', '.') ?>
                                </td>

                                <!-- Kolom Pajak Dinamis -->
                                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                    <?php if ($hasProject): ?>
                                        <p class="font-mono font-bold text-amber-600 text-xs sm:text-sm">
                                            Rp <?= number_format($nomPajak, 0, ',', '.') ?>
                                        </p>
                                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 px-1.5 py-0.2 rounded inline-block mt-0.5">
                                            PPN <?= number_format($pctPajak, 1) ?>%
                                        </span>
                                    <?php else: ?>
                                        <span class="block text-center text-slate-400 font-normal italic text-xs" title="Belum dikonversi ke proyek">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Dokumen BRD -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($brd_url !== ''): ?>
                                        <a href="<?= $brd_url ?>" target="_blank" class="btn-press px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition inline-flex items-center gap-1 shadow-2xs" title="Unduh BRD">
                                            <i class="fa-solid fa-file-pdf text-emerald-600"></i> Dokumen
                                        </a>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic text-xs font-normal">-</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Status Badge -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg text-[10px] uppercase inline-flex items-center gap-1.5 border tracking-wider <?= $badgeClass ?>">
                                        <?php if ($hasPulseDot): ?>
                                            <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                                        <?php endif; ?>
                                        <?= $badgeText ?>
                                    </span>
                                </td>

                                <!-- Akses Cepat (AKSI) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center justify-center gap-1.5">
                                        
                                        <!-- Detail & Chat Lobby -->
                                        <a href="detail.php?id=<?= $r['id'] ?>" class="btn-press relative px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1.5 shadow-xs" title="Detail Spec & Chat Lobby">
                                            <i class="fa-solid fa-comments text-xs"></i> Chat
                                            <?php if ($unread > 0): ?>
                                                <span class="absolute -top-1.5 -right-1.5 bg-red-500 text-white text-[9px] font-bold min-w-[16px] h-[16px] px-1 rounded-full flex items-center justify-center border border-white animate-pulse"><?= $unread ?></span>
                                            <?php endif; ?>
                                        </a>

                                        <?php if ($status === 'Rejected'): ?>
                                            <a href="index.php?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Pengajuan ini berstatus Rejected. Yakin ingin menghapus data ini beserta seluruh catatan negosiasinya?')" 
                                               class="btn-press px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1 shadow-xs" title="Hapus Data Rejected">
                                                <i class="fa-solid fa-trash-can text-xs"></i> Hapus
                                            </a>
                                        <?php else: ?>
                                            <?php if ($hasProject): ?>
                                                <a href="../proposals/print_proposal.php?project_id=<?= $r['linked_project_id'] ?>" target="_blank" class="btn-press px-2.5 py-1.5 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 rounded-xl text-xs font-bold transition inline-flex items-center gap-1" title="Tinjau & Kirim Surat Penawaran (SPH)">
                                                    <i class="fa-solid fa-file-signature text-xs"></i>
                                                </a>

                                                <?php if ($hasContract): ?>
                                                    <a href="../contracts/print_pdf.php?id=<?= $r['linked_contract_id'] ?>" target="_blank" class="btn-press px-2.5 py-1.5 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 rounded-xl text-xs font-bold transition inline-flex items-center gap-1" title="Tinjau & Kirim Kontrak SPK">
                                                        <i class="fa-solid fa-file-contract text-xs"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="../contracts/generate.php?project_id=<?= $r['linked_project_id'] ?>" class="btn-press px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition inline-flex items-center gap-1 shadow-xs" title="Buat Kontrak SPK Baru">
                                                        <i class="fa-solid fa-plus text-[10px]"></i> SPK
                                                    </a>
                                                <?php endif; ?>

                                                <a href="../projects/detail.php?id=<?= $r['linked_project_id'] ?>" class="btn-press p-1.5 bg-blue-50 text-blue-600 hover:bg-blue-100 border border-blue-200 rounded-xl text-xs font-semibold inline-flex items-center justify-center" title="Lihat Workspace Proyek">
                                                    <i class="fa-solid fa-folder-open"></i>
                                                </a>

                                            <?php else: ?>
                                                <a href="../projects/index.php?convert_req=1&req_id=<?= $r['id'] ?>&client_name=<?= urlencode($r['client_name']) ?>&title=<?= urlencode($r['title']) ?>&budget=<?= urlencode($r['budget_estimation']) ?>" 
                                                   class="btn-press px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1.5 shadow-xs" title="Konversi Kebutuhan Klien ke Proyek Aktif">
                                                    <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Convert
                                                </a>
                                            <?php endif; ?>

                                            <a href="index.php?action=delete&id=<?= $r['id'] ?>" onclick="return confirm('Yakin ingin menghapus data requirement ini beserta obrolan negosiasinya?')" 
                                               class="btn-press p-1.5 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition inline-flex items-center justify-center" title="Hapus Data">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </a>
                                        <?php endif; ?>

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