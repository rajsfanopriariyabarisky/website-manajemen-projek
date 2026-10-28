<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Data Requirement HANYA jika Klien Terverifikasi OTP (is_verified = 1)
$stmt = $pdo->prepare("SELECT cr.* FROM client_requirements cr 
                       INNER JOIN clients c ON (cr.client_id = c.id OR LOWER(cr.client_email) = LOWER(c.email)) 
                       WHERE cr.id = ? AND c.is_verified = 1");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    echo "<script>alert('Data requirement tidak ditemukan atau akun klien belum terverifikasi!'); window.location.href='index.php';</script>";
    exit;
}

// Cek apakah ada proyek aktif & kontrak terhubung dengan requirement ini
$stmtLinkedProj = $pdo->prepare("
    SELECT p.*, c_contract.id AS linked_contract_id 
    FROM projects p 
    LEFT JOIN contracts c_contract ON c_contract.project_id = p.id 
    WHERE p.requirement_id = ? LIMIT 1
");
$stmtLinkedProj->execute([$id]);
$linkedProject = $stmtLinkedProj->fetch();

// Handle Update Status Manual & SINKRONISASI KE PROYEK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmtUpdate = $pdo->prepare("UPDATE client_requirements SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$new_status, $id]);

    // Sinkronkan status ke Proyek Aktif (jika terhubung)
    if ($linkedProject) {
        if ($new_status === 'Deal / Accepted') {
            $pdo->prepare("UPDATE projects SET status = 'In Progress' WHERE id = ?")->execute([$linkedProject['id']]);
        } elseif ($new_status === 'Proposal Sent' || $new_status === 'Rejected') {
            $pdo->prepare("UPDATE projects SET status = 'On Hold' WHERE id = ?")->execute([$linkedProject['id']]);
        }
    }

    // Pesan sistem otomatis di chat
    $stmtSysChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) VALUES (?, 'admin', 'System CTC', ?, 1, 0, NOW())");
    $stmtSysChat->execute([$id, "Status pengajuan diperbarui menjadi: [ {$new_status} ]"]);

    header("Location: detail.php?id={$id}&msg=updated");
    exit;
}

// Fetch All Chats
$stmtChats = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? ORDER BY id ASC");
$stmtChats->execute([$id]);
$chats = $stmtChats->fetchAll();

// LOGIKA NOTIFIKASI pesan dari klien yg belum dibaca admin
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM deal_chats WHERE requirement_id = ? AND sender_type = 'client' AND admin_read = 0");
$stmtUnread->execute([$id]);
$new_unread_count = (int) $stmtUnread->fetchColumn();

// Resolve lampiran BRD
$brd_filename = trim((string) ($req['file_attachment'] ?? ''));
$brd_href = '';
if ($brd_filename !== '') {
    $candidates = [
        ['path' => __DIR__ . '/../../uploads/brd/' . $brd_filename,       'url' => '../../uploads/brd/' . rawurlencode($brd_filename)],
        ['path' => __DIR__ . '/../../uploads/' . $brd_filename,          'url' => '../../uploads/' . rawurlencode($brd_filename)],
        ['path' => __DIR__ . '/../../assets/uploads/' . $brd_filename,   'url' => '../../assets/uploads/' . rawurlencode($brd_filename)],
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand['path'])) { $brd_href = $cand['url']; break; }
    }
    if ($brd_href === '') $brd_href = $candidates[0]['url'];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, textarea, button { font-family: 'Plus Jakarta Sans', sans-serif !important; }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 md:p-6 overflow-y-auto bg-[#f8fafc] min-h-screen w-full font-sans text-slate-800 relative selection:bg-emerald-600 selection:text-white">

    <!-- Top Action Bar Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="flex items-center gap-3.5 min-w-0">
            <a href="index.php" class="btn-press w-10 h-10 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0" title="Kembali">
                <i class="fa-solid fa-arrow-left text-sm"></i>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                        REQ-#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                    <span class="text-xs font-normal text-slate-400">• <?= date('d M Y H:i', strtotime($req['created_at'])) ?> WIB</span>
                </div>
                <h1 class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight leading-snug truncate">
                    <?= htmlspecialchars($req['title']) ?>
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end shrink-0 flex-wrap">
            <?php if ($linkedProject): ?>
                <!-- Quick SPH Button -->
                <a href="../proposals/print_proposal.php?project_id=<?= $linkedProject['id'] ?>" target="_blank" class="btn-press px-3 py-2 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-xl text-xs font-bold flex items-center gap-1.5 transition" title="Tinjau Surat Penawaran (SPH)">
                    <i class="fa-solid fa-file-signature text-xs"></i> SPH
                </a>

                <!-- Quick SPK Button -->
                <?php if (!empty($linkedProject['linked_contract_id'])): ?>
                    <a href="../contracts/print_pdf.php?id=<?= $linkedProject['linked_contract_id'] ?>" target="_blank" class="btn-press px-3 py-2 bg-emerald-50 border border-emerald-200 text-emerald-800 hover:bg-emerald-100 rounded-xl text-xs font-bold flex items-center gap-1.5 transition" title="Tinjau Kontrak SPK">
                        <i class="fa-solid fa-file-contract text-xs"></i> SPK
                    </a>
                <?php else: ?>
                    <a href="../contracts/generate.php?project_id=<?= $linkedProject['id'] ?>" class="btn-press px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition shadow-xs" title="Buat Kontrak SPK Baru">
                        <i class="fa-solid fa-plus text-[10px]"></i> SPK
                    </a>
                <?php endif; ?>

                <a href="../projects/detail.php?id=<?= $linkedProject['id'] ?>" class="btn-press px-3.5 py-2 bg-blue-50 border border-blue-200 text-blue-700 hover:bg-blue-100 rounded-xl text-xs font-semibold flex items-center gap-2 transition" title="Lihat Workspace Proyek">
                    <i class="fa-solid fa-folder-open text-xs"></i> Proyek Workspace
                </a>
            <?php else: ?>
                <a href="../projects/index.php?convert_req=1&req_id=<?= $req['id'] ?>&client_name=<?= urlencode($req['client_name']) ?>&title=<?= urlencode($req['title']) ?>&budget=<?= urlencode($req['budget_estimation']) ?>"
                   class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs sm:text-sm font-semibold transition shadow-md shadow-emerald-600/20 flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Convert to Active Project
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="mb-5 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs sm:text-sm font-medium flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-lg"></i> Status pengajuan requirement & proyek terkait berhasil disinkronkan!
        </div>
    <?php endif; ?>

    <!-- Main Grid Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- KOLOM KIRI (2 Cols) -->
        <div class="lg:col-span-2 bg-white p-6 rounded-2xl border border-slate-200/90 shadow-xs space-y-6 text-xs sm:text-sm">

            <div class="flex items-center justify-between pb-4 border-b border-slate-100 gap-2">
                <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-file-contract text-emerald-600"></i> Spesifikasi Proyek & Klien
                </h3>
                <span class="px-3 py-1 text-xs font-semibold uppercase rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 tracking-wider shrink-0">
                    STATUS: <?= htmlspecialchars($req['status']) ?>
                </span>
            </div>

            <!-- Identitas Klien -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/80 p-5 rounded-xl border border-slate-200/60">
                <div>
                    <span class="text-slate-400 font-medium uppercase text-xs tracking-wider block mb-1">Penanggung Jawab Klien</span>
                    <p class="font-semibold text-slate-900 text-sm sm:text-base"><?= htmlspecialchars($req['client_name']) ?></p>
                    <p class="text-slate-500 font-normal text-xs sm:text-sm mt-0.5 break-all"><?= htmlspecialchars($req['client_email']) ?></p>
                </div>

                <div>
                    <span class="text-slate-400 font-medium uppercase text-xs tracking-wider block mb-1">Perusahaan / Perorangan</span>
                    <p class="font-semibold text-slate-800 text-sm"><?= htmlspecialchars($req['company_name'] ?? 'PT. / Individual') ?></p>
                    <p class="text-slate-500 font-normal text-xs sm:text-sm mt-0.5"><i class="fa-solid fa-phone text-slate-400 mr-1.5"></i><?= htmlspecialchars($req['client_phone'] ?? '-') ?></p>
                </div>
            </div>

            <!-- Budget & File BRD -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-medium uppercase text-slate-400 tracking-wider block mb-1">Alokasi Budget Proyek</span>
                    <p class="font-mono font-bold text-emerald-700 text-base sm:text-lg">
                        Rp <?= number_format($req['budget_estimation'], 0, ',', '.') ?>
                    </p>
                </div>

                <!-- Lampiran Dokumen BRD -->
                <div class="bg-white p-4 rounded-xl border border-slate-200/80 shadow-xs">
                    <span class="text-xs font-medium uppercase text-slate-400 tracking-wider block mb-1">Lampiran Dokumen BRD</span>
                    <?php if ($brd_href !== ''): ?>
                        <a href="<?= $brd_href ?>" target="_blank" class="text-emerald-700 font-semibold hover:underline flex items-center gap-1.5 text-xs sm:text-sm mt-1" title="Lihat Dokumen BRD">
                            <i class="fa-solid fa-file-pdf text-emerald-600 shrink-0 text-base"></i>
                            <span class="truncate"><?= htmlspecialchars($brd_filename) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="text-slate-400 italic font-normal text-xs sm:text-sm">Tidak ada lampiran dokumen</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deskripsi Scope -->
            <div>
                <span class="text-xs font-medium uppercase text-slate-400 tracking-wider block mb-2">Uraian Kebutuhan & Ruang Lingkup Sistem</span>
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-xl text-slate-800 text-xs sm:text-sm leading-relaxed font-normal whitespace-pre-line min-h-[100px]">
                    <?= htmlspecialchars($req['description']) ?>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN (1 Col) -->
        <div class="space-y-5">

            <!-- Update Status Card -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-xs space-y-4">
                <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-emerald-600"></i> Kelola Status Proyek
                </h3>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium uppercase text-slate-500 tracking-wider mb-2">Status Pengajuan Saat Ini</label>
                        <select name="status" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-800 bg-white focus:outline-none">
                            <option value="Under Review" <?= $req['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review (Dalam Peninjauan)</option>
                            <option value="Proposal Sent" <?= $req['status'] === 'Proposal Sent' ? 'selected' : '' ?>>Proposal Sent (Penawaran Dikirim)</option>
                            <option value="Deal / Accepted" <?= $req['status'] === 'Deal / Accepted' ? 'selected' : '' ?>>Deal / Accepted (Disetujui)</option>
                            <option value="Rejected" <?= $req['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected (Ditolak)</option>
                        </select>
                    </div>

                    <button type="submit" name="update_status" class="btn-press w-full py-3 bg-slate-900 hover:bg-slate-800 text-white font-semibold rounded-xl transition text-xs sm:text-sm uppercase tracking-wider shadow-xs">
                        Simpan Perubahan Status
                    </button>
                </form>
            </div>

            <!-- CARD CHAT NEGOSIASI -->
            <div class="bg-slate-900 text-white p-5 sm:p-6 rounded-2xl border border-slate-800 shadow-md space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3 gap-2">
                    <h4 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-white flex items-center gap-2">
                        <i class="fa-solid fa-comments text-emerald-400"></i> Diskusi & Negosiasi Klien
                    </h4>

                    <span id="unreadBadge" class="bg-red-600 text-white text-xs font-bold px-3 py-1 rounded-full flex items-center gap-1.5 shadow-sm animate-pulse border border-red-400/40 <?= $new_unread_count > 0 ? '' : 'hidden' ?>">
                        <i class="fa-solid fa-bell text-xs bell-shake text-amber-300"></i> <span id="unreadCount">+<?= $new_unread_count ?> Pesan Baru</span>
                    </span>
                </div>

                <p class="text-slate-300 text-xs sm:text-sm font-normal leading-relaxed">
                    Kirim pesan balasan atau konsultasikan scope dengan klien secara <em>real-time</em>. Tekan <strong>Enter</strong> untuk mengirim.
                </p>

                <button onclick="openChatModal()" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl transition text-xs sm:text-sm uppercase tracking-wider flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                    <i class="fa-solid fa-comments text-sm"></i> Buka Diskusi Negosiasi (<span id="totalChatCount"><?= count($chats) ?></span>)
                </button>
            </div>

        </div>

    </div>

    <!-- MODAL POPUP CHAT (REAL-TIME) -->
    <div id="chatModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-0 sm:p-6">
        <div class="bg-white border border-slate-200 sm:rounded-2xl shadow-2xl w-full h-[100dvh] sm:h-[85vh] max-w-3xl flex flex-col overflow-hidden relative">

            <!-- Modal Header -->
            <div class="p-4 sm:px-6 bg-slate-900 text-white flex items-center justify-between shrink-0 z-10">
                <div class="flex items-center gap-3 min-w-0">
                    <img src="../../assets/images/logo.png" alt="Logo CTC" class="h-8 w-auto object-contain shrink-0">
                    <div class="min-w-0 border-l border-slate-700 pl-3">
                        <h3 class="text-xs sm:text-sm font-bold uppercase tracking-wider text-white truncate">Lobi Negosiasi & Diskusi Scope</h3>
                        <p class="text-xs text-slate-300 font-normal truncate">Klien: <strong class="text-emerald-400"><?= htmlspecialchars($req['client_name']) ?></strong></p>
                    </div>
                </div>

                <button onclick="closeChatModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition shrink-0">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Modal Chat Box -->
            <div class="flex-1 relative overflow-hidden bg-slate-50/50 min-h-0">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                    <img src="../../assets/images/logo.png" alt="Watermark CTC Chat" class="w-[350px] max-w-[80vw] h-auto object-contain opacity-[0.04] mix-blend-multiply">
                </div>

                <div id="modal-chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs sm:text-sm relative z-10">
                    <!-- Chat Container via JS -->
                </div>
            </div>

            <!-- Modal Input Form -->
            <form id="adminChatForm" onsubmit="sendAdminChat(event)" class="p-3.5 sm:p-4 bg-white border-t border-slate-200 flex gap-2 shrink-0 z-10">
                <input type="text" id="adminChatInput" required placeholder="Tulis balasan... (Enter untuk kirim)" autocomplete="off"
                       class="input-glow flex-1 px-4 py-2.5 rounded-xl border border-slate-300 bg-white text-xs sm:text-sm text-slate-900 focus:outline-none font-normal transition-all placeholder:text-slate-400 min-w-0">
                <button type="submit" id="adminChatBtn" class="btn-press px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-semibold rounded-xl text-xs sm:text-sm transition shadow-xs flex items-center gap-2 shrink-0 uppercase tracking-wider">
                    <i class="fa-solid fa-paper-plane text-xs"></i> <span class="hidden sm:inline">Kirim</span>
                </button>
            </form>

        </div>
    </div>

</main>

<script>
const reqId = <?= $id ?>;
let lastChatId = 0;
let chatPollingInterval = null;
let chatHistoryLoaded = false;
let chatModalOpen = false;
let sendingChat = false;

function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, s => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[s]));
}
function nl2br(str) { return escapeHtml(str).replace(/\n/g, '<br>'); }

function renderSingleChat(c) {
    const isAdmin = (c.sender_type === 'admin');
    const isSystem = (c.sender_type === 'system' || (c.sender_name && c.sender_name.toLowerCase().includes('system')));
    
    const containerClass = isAdmin ? 'items-end' : 'items-start';
    
    let nameClass = 'text-slate-700 font-semibold';
    if (isAdmin) { nameClass = 'text-emerald-700 font-semibold'; }
    if (isSystem) { nameClass = 'text-emerald-800 font-bold'; }

    let bubbleClass = 'bg-white text-slate-800 rounded-tl-none border border-slate-200/90 shadow-2xs';
    let messageFormatted = nl2br(c.message);

    if (isAdmin && !isSystem) {
        bubbleClass = 'bg-emerald-600 text-white rounded-tr-none';
    } else if (isSystem) {
        bubbleClass = 'bg-emerald-50/90 text-emerald-700 border border-emerald-300 rounded-tl-none font-semibold italic shadow-2xs';
        messageFormatted = `*** ${nl2br(c.message)} ***`;
    }

    return `
        <div class="flex flex-col ${containerClass} w-full chat-bubble" data-id="${c.id}">
            <div class="text-[10px] text-slate-400 font-normal mb-1 px-1 flex items-center gap-1.5">
                <span class="${nameClass}">${escapeHtml(c.sender_name)}</span>
                <span>•</span>
                <span>${escapeHtml(c.created_at)} WIB</span>
            </div>
            <div class="max-w-[85%] sm:max-w-[75%] p-3 sm:p-3.5 rounded-2xl text-xs sm:text-sm leading-relaxed break-words ${bubbleClass}">
                ${messageFormatted}
            </div>
        </div>
    `;
}

function appendChat(c) {
    if (!c || c.id <= lastChatId) return;
    const box = document.getElementById('modal-chat-box');
    const emptyState = box.querySelector('.empty-chat-notice');
    if (emptyState) emptyState.remove();

    const nearBottom = box.scrollTop + box.clientHeight >= box.scrollHeight - 80;
    box.insertAdjacentHTML('beforeend', renderSingleChat(c));
    lastChatId = c.id;

    document.getElementById('totalChatCount').innerText = box.querySelectorAll('.chat-bubble').length;

    if (nearBottom || !chatHistoryLoaded) {
        box.scrollTop = box.scrollHeight;
    }
}

function updateUnreadBadge(unread) {
    const badge = document.getElementById('unreadBadge');
    const countEl = document.getElementById('unreadCount');
    if (unread > 0) {
        countEl.innerText = '+' + unread + ' Pesan Baru';
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

function fetchChats() {
    const reader = chatModalOpen ? '&reader=admin' : '';
    fetch(`../../api_chat.php?action=fetch&req_id=${reqId}&last_id=${lastChatId}${reader}`, { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            if (data.messages.length > 0) {
                data.messages.forEach(appendChat);
                chatHistoryLoaded = true;
            } else if (!chatHistoryLoaded) {
                chatHistoryLoaded = true;
                const box = document.getElementById('modal-chat-box');
                if (box.children.length === 0) {
                    box.innerHTML = '<div class="empty-chat-notice text-center py-20 text-slate-400 font-normal"><i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>Belum ada percakapan. Mulai diskusi negosiasi dengan klien.</div>';
                }
            }

            updateUnreadBadge(parseInt(data.unread) || 0);
        })
        .catch(err => console.error('fetchChats:', err));
}

function startPolling() {
    if (chatPollingInterval) return;
    chatPollingInterval = setInterval(fetchChats, 2000);
}

function sendAdminChat(e) {
    e.preventDefault();
    if (sendingChat) return;

    const input = document.getElementById('adminChatInput');
    const btn = document.getElementById('adminChatBtn');
    const msg = input.value.trim();
    if (!msg) return;

    sendingChat = true;
    btn.disabled = true;
    input.value = '';

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('req_id', reqId);
    formData.append('message', msg);

    fetch('../../api_chat.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.message) {
                appendChat(data.message);
                const box = document.getElementById('modal-chat-box');
                box.scrollTop = box.scrollHeight;
            }
        })
        .catch(err => console.error('sendAdminChat:', err))
        .finally(() => {
            sendingChat = false;
            btn.disabled = false;
            input.focus();
        });
}

function openChatModal() {
    chatModalOpen = true;
    document.getElementById('chatModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    const box = document.getElementById('modal-chat-box');
    if (box.children.length === 0) {
        box.innerHTML = '<div class="empty-chat-notice text-center py-20 text-slate-400 font-normal"><i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>Memuat percakapan...</div>';
    }

    fetchChats();
    startPolling();
}

function closeChatModal() {
    chatModalOpen = false;
    document.getElementById('chatModal').classList.add('hidden');
    document.body.style.overflow = '';
    fetchChats();
}

document.addEventListener('DOMContentLoaded', function() {
    fetchChats();
    startPolling();

    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) fetchChats();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && chatModalOpen) closeChatModal();
    });

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_chat') === '1' || window.location.hash === '#chat-box') {
        openChatModal();
    }
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
ob_end_flush();
?>