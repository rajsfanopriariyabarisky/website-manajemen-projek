<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

// Proteksi Sesi Login Client
if (
    !isset($_SESSION['client_logged_in']) ||
    $_SESSION['client_logged_in'] !== true ||
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id = intval($_SESSION['client_user_id']);

// Sinkronkan variabel Sesi Klien dengan Database Master Terbaru
$stmtSyncClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmtSyncClient->execute([$client_id]);
$client_db_sync = $stmtSyncClient->fetch();

if (!$client_db_sync || (int)$client_db_sync['is_verified'] !== 1) {
    unset(
        $_SESSION['client_logged_in'],
        $_SESSION['client_user_id'],
        $_SESSION['client_nama'],
        $_SESSION['client_email'],
        $_SESSION['client_token']
    );
    header('Location: login.php');
    exit;
}

// Update variabel lokal & Sesi PHP
$client_nama  = $client_db_sync['nama'];
$client_email = $client_db_sync['email'];
$_SESSION['client_nama']  = $client_nama;
$_SESSION['client_email'] = $client_email;

// AJAX Endpoint: Real-Time Metadata & Client Profile Sync (Status, Project ID, Contract ID, & Profil)
if (isset($_GET['ajax_meta']) && $_GET['ajax_meta'] == '1' && isset($_GET['req_id'])) {
    header('Content-Type: application/json');
    $req_id = intval($_GET['req_id']);
    
    // Verifikasi real-time status akun dari DB
    $stmtCCheck = $pdo->prepare("SELECT nama, email, is_verified FROM clients WHERE id = ? LIMIT 1");
    $stmtCCheck->execute([$client_id]);
    $cCheck = $stmtCCheck->fetch();

    if (!$cCheck || (int)$cCheck['is_verified'] !== 1) {
        unset(
            $_SESSION['client_logged_in'],
            $_SESSION['client_user_id'],
            $_SESSION['client_nama'],
            $_SESSION['client_email'],
            $_SESSION['client_token']
        );
        echo json_encode(['success' => false, 'logged_out' => true]);
        exit;
    }

    // Auto-update Sesi & Variabel
    $_SESSION['client_nama']  = $cCheck['nama'];
    $_SESSION['client_email'] = $cCheck['email'];
    $client_nama  = $cCheck['nama'];
    $client_email = $cCheck['email'];

    $stmtReqMeta = $pdo->prepare("SELECT id, title, status FROM client_requirements WHERE id = ? AND (client_id = ? OR LOWER(client_email) = LOWER(?)) LIMIT 1");
    $stmtReqMeta->execute([$req_id, $client_id, $client_email]);
    $rMeta = $stmtReqMeta->fetch();
    
    $pId = 0;
    $cId = 0;
    $status = $rMeta['status'] ?? 'Under Review';
    
    if ($rMeta) {
        $stmtPMeta = $pdo->prepare("SELECT id FROM projects WHERE requirement_id = ? OR (LOWER(nama_projek) = LOWER(?) AND LOWER(klien) LIKE LOWER(?)) ORDER BY id DESC LIMIT 1");
        $stmtPMeta->execute([$req_id, $rMeta['title'], '%' . $client_nama . '%']);
        $pMeta = $stmtPMeta->fetch();
        if ($pMeta) {
            $pId = intval($pMeta['id']);
            $stmtCMeta = $pdo->prepare("SELECT id FROM contracts WHERE project_id = ? ORDER BY id DESC LIMIT 1");
            $stmtCMeta->execute([$pId]);
            $cMeta = $stmtCMeta->fetch();
            if ($cMeta) {
                $cId = intval($cMeta['id']);
            }
        }
    }
    
    echo json_encode([
        'success'      => true,
        'status'       => $status,
        'project_id'   => $pId,
        'contract_id'  => $cId,
        'client_nama'  => $cCheck['nama'],
        'client_email' => $cCheck['email']
    ]);
    exit;
}

// Handle Logout Khusus Client
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset(
        $_SESSION['client_logged_in'],
        $_SESSION['client_user_id'],
        $_SESSION['client_nama'],
        $_SESSION['client_email'],
        $_SESSION['client_token']
    );

    header('Location: login.php');
    exit;
}

// Fetch All Requirements milik Client ini
$stmtReqs = $pdo->prepare(
    "SELECT * FROM client_requirements
     WHERE client_id = ? OR LOWER(client_email) = LOWER(?)
     ORDER BY id DESC"
);
$stmtReqs->execute([$client_id, $client_email]);
$requirements = $stmtReqs->fetchAll();

// Target requirement yang dipilih
$selected_id = isset($_GET['req_id'])
    ? intval($_GET['req_id'])
    : ($requirements[0]['id'] ?? 0);

// Auto-Update client_read = 1 untuk requirement yang sedang dibuka aktif
if ($selected_id > 0) {
    $stmtMarkRead = $pdo->prepare("
        UPDATE deal_chats 
        SET client_read = 1 
        WHERE requirement_id = ? AND sender_type = 'admin' AND client_read = 0
    ");
    $stmtMarkRead->execute([$selected_id]);
}

// Unread chat per requirement
$stmtUnread = $pdo->prepare(
    "SELECT requirement_id, COUNT(*) AS c
     FROM deal_chats
     WHERE sender_type = 'admin'
       AND client_read = 0
       AND requirement_id IN (
           SELECT id
           FROM client_requirements
           WHERE client_id = ? OR LOWER(client_email) = LOWER(?)
       )
     GROUP BY requirement_id"
);
$stmtUnread->execute([$client_id, $client_email]);
$unreadMap = $stmtUnread->fetchAll(PDO::FETCH_KEY_PAIR);

$active_req = null;
$linked_project = null;
$linked_contract = null;

if ($selected_id > 0) {
    $stmtActive = $pdo->prepare(
        "SELECT * FROM client_requirements
         WHERE id = ?
           AND (client_id = ? OR LOWER(client_email) = LOWER(?))"
    );

    $stmtActive->execute([
        $selected_id,
        $client_id,
        $client_email
    ]);

    $active_req = $stmtActive->fetch();

    if ($active_req) {
        $stmtProj = $pdo->prepare("
            SELECT * FROM projects 
            WHERE requirement_id = ? 
               OR (LOWER(nama_projek) = LOWER(?) AND LOWER(klien) LIKE LOWER(?))
            ORDER BY id DESC LIMIT 1
        ");
        $stmtProj->execute([
            $active_req['id'], 
            $active_req['title'], 
            '%' . $client_nama . '%'
        ]);
        $linked_project = $stmtProj->fetch();

        if ($linked_project) {
            $stmtContract = $pdo->prepare("SELECT * FROM contracts WHERE project_id = ? ORDER BY id DESC LIMIT 1");
            $stmtContract->execute([$linked_project['id']]);
            $linked_contract = $stmtContract->fetch();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - PT. Cipta Teknologi Cendekia</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-press:active { transform: translateY(1px); }

        @keyframes chatPop {
            from { opacity: 0; transform: translateY(8px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }
        .chat-pop { animation: chatPop 0.25s cubic-bezier(0.16, 1, 0.3, 1) both; }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white flex flex-col">

<!-- Top Navigation Header -->
<header class="bg-white border-b border-slate-200/90 px-4 sm:px-8 py-3 flex items-center justify-between sticky top-0 z-30 shadow-xs">
    <div class="flex items-center gap-3 min-w-0">
        <img src="../assets/images/logo.png" alt="Logo CTC" class="h-10 sm:h-11 w-auto object-contain shrink-0">

        <div class="min-w-0 border-l border-slate-200 pl-3">
            <h1 class="text-xs font-bold text-slate-900 uppercase tracking-wider">CLIENT PORTAL</h1>
            <p class="text-[11px] text-slate-500 font-medium truncate">PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <a href="profile.php" class="group relative btn-press flex items-center gap-2 px-3.5 py-1.5 bg-slate-50 hover:bg-emerald-50/80 hover:border-emerald-300 border border-slate-200/80 rounded-xl text-xs font-semibold text-slate-700 hover:text-emerald-800 transition-all shadow-2xs" title="Lihat Profil">
            <i class="fa-solid fa-circle-user text-emerald-600 text-sm group-hover:scale-110 transition-transform"></i>
            <span id="hdrClientNama" class="truncate max-w-[110px] sm:max-w-none"><?= htmlspecialchars($client_nama) ?></span>
            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 group-hover:text-emerald-600 group-hover:translate-x-0.5 transition-all"></i>

            <div class="absolute top-full left-1/2 -translate-x-1/2 mt-2 hidden group-hover:flex items-center gap-1.5 bg-slate-900 text-white text-[10px] font-medium py-1 px-2.5 rounded-lg shadow-xl whitespace-nowrap z-50 pointer-events-none transition-all">
                <i class="fa-solid fa-id-card text-emerald-400"></i>
                <span>Profile Saya</span>
                <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-2 h-2 bg-slate-900 rotate-45"></div>
            </div>
        </a>

        <a href="portal.php?action=logout" class="btn-press px-3.5 py-1.5 bg-red-50 hover:bg-red-100/80 text-red-600 border border-red-200/80 rounded-xl text-xs font-semibold transition flex items-center gap-2">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="hidden sm:inline">Logout</span>
        </a>
    </div>
</header>

<!-- Main Portal Container -->
<main class="max-w-7xl w-full mx-auto p-4 md:p-6 flex-1 flex flex-col gap-4">

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-2xl text-xs sm:text-sm font-semibold flex items-center gap-3 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i>
            <span>Pengajuan proyek baru berhasil dikirim! Tim kami akan meninjau dan merespon pengajuan Anda.</span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6 flex-1">

        <!-- Sidebar Requirements -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 space-y-4 shadow-sm flex flex-col h-[400px] lg:h-[680px]">

            <div class="flex items-center justify-between pb-3 border-b border-slate-100 shrink-0 gap-2">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-folder-open text-emerald-600"></i>
                    Pengajuan Proyek
                </h2>

                <a href="ajukan.php" class="btn-press px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/80 rounded-xl text-xs font-semibold transition flex items-center gap-1.5 shrink-0">
                    <i class="fa-solid fa-plus text-[10px]"></i>
                    Ajukan Baru
                </a>
            </div>

            <?php if (empty($requirements)): ?>

                <div class="flex-1 flex flex-col items-center justify-center text-center py-8 text-slate-400 text-xs font-normal">
                    <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                    Belum ada pengajuan requirement.
                </div>

            <?php else: ?>

                <div class="space-y-2 overflow-y-auto flex-1 pr-1 min-h-0">

                    <?php foreach ($requirements as $r): ?>
                        <?php 
                            $uc = (int) ($unreadMap[$r['id']] ?? 0);
                            $rStatus = $r['status'] ?? 'Under Review';
                            
                            $stClass = 'bg-slate-50 text-slate-600 border-slate-200/70';
                            if ($rStatus === 'Under Review') {
                                $stClass = 'bg-rose-100 text-rose-700 border-rose-300 font-bold';
                            } elseif ($rStatus === 'Proposal Sent') {
                                $stClass = 'bg-amber-50 text-amber-800 border-amber-200 font-bold';
                            } elseif (in_array($rStatus, ['Deal / Accepted', 'Approved', 'In Progress', 'Completed'])) {
                                $stClass = 'bg-emerald-50 text-emerald-700 border-emerald-300 font-bold';
                            } elseif ($rStatus === 'Rejected') {
                                $stClass = 'bg-slate-200 text-slate-700 border-slate-300 font-bold';
                            }
                        ?>

                        <a href="portal.php?req_id=<?= $r['id'] ?>"
                           class="block p-3.5 rounded-xl border transition-all duration-150 <?= ($active_req && $active_req['id'] == $r['id']) ? 'bg-emerald-50/60 border-emerald-500/80 shadow-xs ring-1 ring-emerald-500/20' : 'bg-white border-slate-200/80 hover:bg-slate-50 hover:border-slate-300' ?>">

                            <div class="flex items-start justify-between gap-2">
                                <p class="text-xs font-semibold text-slate-900 truncate leading-snug min-w-0">
                                    <?= htmlspecialchars($r['title']) ?>
                                </p>

                                <span id="unreadBadge-<?= $r['id'] ?>"
                                      class="unread-badge shrink-0 bg-red-500 text-white text-[9px] font-bold min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center border border-white shadow-xs <?= $uc > 0 ? '' : 'hidden' ?>">
                                    <?= $uc ?>
                                </span>
                            </div>

                            <div class="flex items-center justify-between mt-2.5 text-[10px] gap-2">
                                <span class="text-slate-400 font-normal">
                                    <i class="fa-regular fa-calendar mr-1"></i>
                                    <?= date('d M Y', strtotime($r['created_at'])) ?>
                                </span>

                                <span id="sidebarStatusBadge-<?= $r['id'] ?>" class="px-2 py-0.5 rounded-md text-[9px] uppercase tracking-wider truncate border <?= $stClass ?>">
                                    <?= htmlspecialchars($rStatus) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>

                </div>

            <?php endif; ?>
        </div>

        <!-- Chat Lobby Container -->
        <div class="lg:col-span-2 bg-white border border-slate-200/90 rounded-2xl flex flex-col h-[560px] sm:h-[620px] lg:h-[680px] shadow-sm overflow-hidden relative">

            <?php if (!$active_req): ?>

                <div class="flex-1 flex items-center justify-center text-slate-400 text-xs font-normal">
                    <div class="text-center">
                        <i class="fa-solid fa-comments text-4xl mb-3 text-slate-300 block"></i>
                        Pilih pengajuan proyek di sebelah kiri untuk memulai diskusi.
                    </div>
                </div>

            <?php else: ?>

                <!-- Chat Header -->
                <div class="p-4 sm:px-6 bg-white border-b border-slate-200/90 shrink-0 z-10 space-y-3">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="pr-2 min-w-0">
                            <h2 class="text-xs sm:text-sm font-bold text-slate-900 tracking-tight uppercase truncate">
                                <?= htmlspecialchars($active_req['title']) ?>
                            </h2>
                            <p class="text-[11px] text-slate-500 font-normal mt-0.5">
                                Diskusi Scope & Surat Penawaran dengan Tim PT. CTC
                            </p>
                        </div>

                        <!-- Container Akses Cepat PDF & Badge Status (Dynamic Sync Real-Time) -->
                        <div class="flex items-center gap-2 flex-wrap justify-end">
                            <div id="hdrActionButtons" class="flex items-center gap-2 flex-wrap">
                                <!-- Rendered dynamically by updateHeaderUI JS -->
                            </div>

                            <span id="hdrStatusBadge" class="px-2.5 py-1 text-[9.5px] uppercase rounded-lg border tracking-wider shrink-0">
                                STATUS: <?= htmlspecialchars($active_req['status'] ?? 'Under Review') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Panel Info Metadata Requirement & Lampiran BRD -->
                    <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-3 text-slate-600 flex-wrap">
                            <span>
                                <strong class="text-slate-800 font-semibold">Estimasi Anggaran:</strong> 
                                <span class="font-mono text-emerald-700 font-bold">Rp <?= number_format(floatval($active_req['budget_estimation'] ?? 0), 0, ',', '.') ?></span>
                                <span class="text-[10px] text-slate-400 font-normal">(Belum inc. PPN)</span>
                            </span>
                            <span>•</span>
                            <span class="text-slate-500 text-[11px]">
                                Tanggal: <?= date('d M Y H:i', strtotime($active_req['created_at'])) ?> WIB
                            </span>
                        </div>

                        <?php if (!empty($active_req['file_attachment'])): ?>
                            <a href="../uploads/brd/<?= htmlspecialchars($active_req['file_attachment']) ?>" target="_blank" class="btn-press text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 bg-white border border-emerald-200 px-2.5 py-1 rounded-lg flex items-center gap-1.5 transition">
                                <i class="fa-solid fa-paperclip text-emerald-600"></i>
                                <span>Unduh Lampiran BRD</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Messages Box -->
                <div class="flex-1 relative overflow-hidden bg-slate-50/50 min-h-0">
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                        <img src="../assets/images/logo.png" alt="Watermark CTC" class="w-[320px] sm:w-[380px] h-auto object-contain opacity-[0.04] mix-blend-multiply">
                    </div>

                    <div id="chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs relative z-10">
                        <!-- Content render via JS AJAX -->
                    </div>
                </div>

                <!-- Chat Form Input -->
                <form id="clientChatForm" onsubmit="sendClientChat(event)" class="p-3.5 sm:p-4 bg-white border-t border-slate-200/90 flex gap-2 shrink-0 z-10">
                    <input type="text" id="clientChatInput" required placeholder="Tulis pesan... (Enter untuk kirim)" autocomplete="off"
                           class="input-glow flex-1 px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs sm:text-sm text-slate-900 focus:outline-none font-normal transition-all placeholder:text-slate-400 min-w-0">

                    <button type="submit" id="clientChatBtn" class="btn-press px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold rounded-xl text-xs transition shadow-xs flex items-center gap-2 shrink-0 uppercase tracking-wider">
                        <i class="fa-solid fa-paper-plane text-xs"></i>
                        <span class="hidden sm:inline">Kirim</span>
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($active_req) { ?>
<script>
    const activeReqId = <?= (int) $active_req['id'] ?>;
    let activeProjId = <?= (int) ($linked_project['id'] ?? 0) ?>;
    let activeContractId = <?= (int) ($linked_contract['id'] ?? 0) ?>;
    let activeStatus = '<?= htmlspecialchars($active_req['status']) ?>';

    let lastChatId = 0;
    let chatHistoryLoaded = false;
    let sendingChat = false;
    let chatPollingInterval = null;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[s]));
    }

    function nl2br(str) {
        return escapeHtml(str).replace(/\n/g, '<br>');
    }

    // UPDATE UI HEADER & STATUS BADGE DYNAMICALLY WITHOUT REFRESH
    function updateHeaderUI(status, projId, contractId) {
        activeStatus = status;
        if (projId > 0) activeProjId = projId;
        if (contractId > 0) activeContractId = contractId;

        // 1. Update Status Badge Header
        const badge = document.getElementById('hdrStatusBadge');
        if (badge) {
            let hdrStClass = 'bg-slate-50 text-slate-600 border-slate-200';
            if (status === 'Under Review') {
                hdrStClass = 'bg-rose-100 text-rose-700 border-rose-300 font-bold';
            } else if (status === 'Proposal Sent') {
                hdrStClass = 'bg-amber-50 text-amber-800 border-amber-200 font-bold';
            } else if (['Deal / Accepted', 'Approved', 'In Progress', 'Completed'].includes(status)) {
                hdrStClass = 'bg-emerald-50 text-emerald-700 border-emerald-300 font-bold';
            } else if (status === 'Rejected') {
                hdrStClass = 'bg-slate-200 text-slate-700 border-slate-300 font-bold';
            }
            badge.className = `px-2.5 py-1 text-[9.5px] uppercase rounded-lg border tracking-wider shrink-0 ${hdrStClass}`;
            badge.innerText = `STATUS: ${status}`;
        }

        // 2. Update Status Badge Sidebar Kiri
        const sbBadge = document.getElementById('sidebarStatusBadge-' + activeReqId);
        if (sbBadge) {
            let sbClass = 'bg-slate-50 text-slate-600 border-slate-200/70';
            if (status === 'Under Review') {
                sbClass = 'bg-rose-100 text-rose-700 border-rose-300 font-bold';
            } else if (status === 'Proposal Sent') {
                sbClass = 'bg-amber-50 text-amber-800 border-amber-200 font-bold';
            } else if (['Deal / Accepted', 'Approved', 'In Progress', 'Completed'].includes(status)) {
                sbClass = 'bg-emerald-50 text-emerald-700 border-emerald-300 font-bold';
            } else if (status === 'Rejected') {
                sbClass = 'bg-slate-200 text-slate-700 border-slate-300 font-bold';
            }
            sbBadge.className = `px-2 py-0.5 rounded-md text-[9px] uppercase tracking-wider truncate border ${sbClass}`;
            sbBadge.innerText = status;
        }

        // 3. Update Tombol PDF (SPH & SPK) di Header secara Real-Time
        const btnContainer = document.getElementById('hdrActionButtons');
        if (btnContainer) {
            let html = '';
            const validProposal = ['Proposal Sent', 'Deal / Accepted', 'Approved', 'In Progress', 'Completed'];
            const validDeal     = ['Deal / Accepted', 'Approved', 'In Progress', 'Completed'];

            if (activeProjId > 0 && validProposal.includes(status)) {
                html += `
                    <a href="../modules/proposals/print_proposal.php?project_id=${activeProjId}" target="_blank" class="btn-press px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 shrink-0 transition">
                        <i class="fa-solid fa-file-pdf text-xs"></i>
                        <span>Proposal SPH (PDF)</span>
                    </a>
                `;
            }

            if (activeContractId > 0 && validDeal.includes(status)) {
                html += `
                    <a href="../modules/contracts/print_pdf.php?id=${activeContractId}" target="_blank" class="btn-press px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 shrink-0 transition">
                        <i class="fa-solid fa-file-contract text-xs"></i>
                        <span>Kontrak SPK (PDF)</span>
                    </a>
                `;
            }

            btnContainer.innerHTML = html;
        }
    }

    // SYNC DATABASE METADATA REAL-TIME (Menjamin SPH, SPK, dan Profil Klien langsung aktif tanpa manual reload)
    function syncRequirementMetadata() {
        fetch(`portal.php?ajax_meta=1&req_id=${activeReqId}`, { cache: 'no-store' })
            .then(res => res.json())
            .then(meta => {
                if (meta && meta.logged_out) {
                    window.location.href = 'login.php';
                    return;
                }

                if (meta && meta.success) {
                    activeStatus     = meta.status || activeStatus;
                    activeProjId     = parseInt(meta.project_id || 0, 10);
                    activeContractId = parseInt(meta.contract_id || 0, 10);
                    updateHeaderUI(activeStatus, activeProjId, activeContractId);

                    // Auto Sync Nama Klien di Header secara Real-Time jika diubah oleh Admin
                    if (meta.client_nama) {
                        const hdrNamaElem = document.getElementById('hdrClientNama');
                        if (hdrNamaElem) hdrNamaElem.innerText = meta.client_nama;
                    }
                }
            })
            .catch(err => console.error('syncRequirementMetadata:', err));
    }

    // RENDER SINGLE CHAT (BEBAS DOUBLE BUTTON & AUTOMATIC SYSTEM NOTIFICATION DISPATCHER)
    function renderSingleChat(c, animate) {
        const isClient = c.sender_type === 'client';
        const isSystem = (c.sender_type === 'system' || (c.sender_name && c.sender_name.toLowerCase().includes('system')));
        const popClass = animate ? ' chat-pop' : '';

        // DETEKSI AKSI SISTEM / CHAT INFORMASI AUTOMATED CTC
        if (isSystem) {
            const msgLower = c.message.toLowerCase();
            const isStatusChange = msgLower.includes('status pengajuan diperbarui menjadi') || msgLower.includes('status pengajuan:');

            // Trigger sync metadata jika ada perubahan status pada pesan chat
            if (c.message.includes('Proposal Sent') || c.message.includes('Deal / Accepted')) {
                syncRequirementMetadata();
            }

            if (isStatusChange) {
                const isDealAccepted = c.message.includes('Deal / Accepted');

                // 1. Render Pill Status Terpusat di Tengah
                let htmlPill = `
                    <div class="flex justify-center my-3 w-full chat-bubble${popClass}" data-id="${c.id}">
                        <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-200/80 border border-slate-300/80 text-slate-700 text-[11px] font-semibold shadow-2xs">
                            <i class="fa-solid fa-circle-info text-emerald-600 text-xs"></i>
                            <span>${escapeHtml(c.message)}</span>
                            <span class="text-[9px] text-slate-400 font-normal ml-1">• ${escapeHtml(c.created_at)} WIB</span>
                        </div>
                    </div>
                `;

                // 2. Jika Deal / Accepted, LANGSUNG PUSH OTOMATIS Card Notifikasi Resmi SPK di bawahnya!
                if (isDealAccepted) {
                    htmlPill += `
                        <div class="flex justify-center my-3 w-full chat-bubble${popClass}">
                            <div class="max-w-[85%] sm:max-w-[75%] p-3.5 bg-emerald-50/90 border border-emerald-300 text-emerald-900 rounded-2xl shadow-2xs space-y-1.5 w-full sm:w-auto">
                                <div class="flex items-center justify-between border-b border-emerald-200 pb-1.5 gap-2">
                                    <span class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                                        <i class="fa-solid fa-shield-halved text-emerald-600"></i> Notifikasi Resmi System CTC
                                    </span>
                                    <span class="text-[10px] text-emerald-600 font-normal">${escapeHtml(c.created_at)} WIB</span>
                                </div>
                                <p class="text-xs text-slate-700 leading-relaxed font-normal">Surat Perjanjian Kerja Sama (SPK) telah diterbitkan dan dapat diunduh melalui portal.</p>
                            </div>
                        </div>
                    `;
                }

                return htmlPill;
            } else {
                // Tampilan CARD NOTIFIKASI RESMI SYSTEM CTC (Untuk SPH, SPK manual, atau Notifikasi Lainnya)
                return `
                    <div class="flex justify-center my-3 w-full chat-bubble${popClass}" data-id="${c.id}">
                        <div class="max-w-[85%] sm:max-w-[75%] p-3.5 bg-emerald-50/90 border border-emerald-300 text-emerald-900 rounded-2xl shadow-2xs space-y-1.5 w-full sm:w-auto">
                            <div class="flex items-center justify-between border-b border-emerald-200 pb-1.5 gap-2">
                                <span class="text-xs font-bold text-emerald-900 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-emerald-600"></i> Notifikasi Resmi System CTC
                                </span>
                                <span class="text-[10px] text-emerald-600 font-normal">${escapeHtml(c.created_at)} WIB</span>
                            </div>
                            <p class="text-xs text-slate-700 leading-relaxed font-normal">${nl2br(c.message)}</p>
                        </div>
                    </div>
                `;
            }
        }

        // TAMPILAN BUBBLE CHAT HUMAN (CLIENT / ADMIN)
        const containerClass = isClient ? 'items-end' : 'items-start';
        const nameClass      = isClient ? 'text-emerald-700 font-semibold' : 'text-slate-700 font-semibold';
        const bubbleClass    = isClient 
            ? 'bg-emerald-600 text-white rounded-tr-none shadow-2xs' 
            : 'bg-white text-slate-800 rounded-tl-none border border-slate-200/90 shadow-2xs';

        return `
            <div class="flex flex-col ${containerClass} w-full chat-bubble${popClass}" data-id="${c.id}">
                <div class="text-[10px] text-slate-400 font-normal mb-1 px-1 flex items-center gap-1.5">
                    <span class="${nameClass}">${escapeHtml(c.sender_name)}</span>
                    <span>•</span>
                    <span>${escapeHtml(c.created_at)} WIB</span>
                </div>

                <div class="max-w-[85%] sm:max-w-[75%] p-3 sm:p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed break-words ${bubbleClass}">
                    ${nl2br(c.message)}
                </div>
            </div>
        `;
    }

    function appendChat(c, animate) {
        if (!c || c.id <= lastChatId) return;

        const box = document.getElementById('chat-box');
        const emptyState = box.querySelector('.empty-chat-notice');

        if (emptyState) emptyState.remove();

        const nearBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 80;

        box.insertAdjacentHTML('beforeend', renderSingleChat(c, animate));
        lastChatId = c.id;

        if (nearBottom || !chatHistoryLoaded) {
            box.scrollTop = box.scrollHeight;
        }
    }

    function updateActiveUnreadBadge(unread) {
        const badge = document.getElementById('unreadBadge-' + activeReqId);
        if (!badge) return;

        if (unread > 0) {
            badge.innerText = unread;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
    }

    function fetchClientChats() {
        syncRequirementMetadata();

        fetch(`../api_chat.php?action=fetch&req_id=${activeReqId}&last_id=${lastChatId}&reader=client`, { cache: 'no-store' })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                if (data.messages && data.messages.length > 0) {
                    const now = new Date();
                    data.messages.forEach(c => {
                        let chatDate = new Date(c.created_at.replace(/-/g, '/'));
                        if (chatDate <= now) {
                            appendChat(c, chatHistoryLoaded);
                            chatHistoryLoaded = true;
                        }
                    });
                } else if (!chatHistoryLoaded) {
                    chatHistoryLoaded = true;

                    const box = document.getElementById('chat-box');
                    if (box.children.length === 0) {
                        box.innerHTML = `
                            <div class="empty-chat-notice text-center py-20 text-slate-400 font-normal">
                                <i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>
                                Belum ada percakapan. Sapa tim PT. CTC untuk mulai diskusi.
                            </div>
                        `;
                    }
                }

                updateActiveUnreadBadge(parseInt(data.unread, 10) || 0);
            })
            .catch(err => console.error('fetchClientChats:', err));
    }

    function sendClientChat(e) {
        e.preventDefault();

        if (sendingChat) return;

        const input = document.getElementById('clientChatInput');
        const btn = document.getElementById('clientChatBtn');
        const msg = input.value.trim();

        if (!msg) return;

        sendingChat = true;
        btn.disabled = true;
        input.value = '';

        const formData = new FormData();
        formData.append('action', 'send');
        formData.append('req_id', activeReqId);
        formData.append('message', msg);
        formData.append('sender_type', 'client');

        fetch('../api_chat.php', {
            method: 'POST',
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.message) {
                    appendChat(data.message, true);

                    const box = document.getElementById('chat-box');
                    box.scrollTop = box.scrollHeight;
                }
            })
            .catch(err => console.error('sendClientChat:', err))
            .finally(() => {
                sendingChat = false;
                btn.disabled = false;
                input.focus();
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateHeaderUI(activeStatus, activeProjId, activeContractId);

        const box = document.getElementById('chat-box');
        box.innerHTML = `
            <div class="empty-chat-notice text-center py-20 text-slate-400 font-normal">
                <i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>
                Memuat percakapan...
            </div>
        `;

        fetchClientChats();
        chatPollingInterval = setInterval(fetchClientChats, 2000);

        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                fetchClientChats();
            }
        });
    });
</script>
<?php } ?>

</body>
</html>