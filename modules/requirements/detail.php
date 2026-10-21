<?php
ob_start();
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch Data Requirement
$stmt = $pdo->prepare("SELECT * FROM client_requirements WHERE id = ?");
$stmt->execute([$id]);
$req = $stmt->fetch();

if (!$req) {
    echo "<script>alert('Data requirement tidak ditemukan!'); window.location.href='index.php';</script>";
    exit;
}

// Handle Update Status Manual
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $stmtUpdate = $pdo->prepare("UPDATE client_requirements SET status = ? WHERE id = ?");
    $stmtUpdate->execute([$new_status, $id]);

    // Pesan sistem otomatis di chat
    $stmtSysChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) VALUES (?, 'admin', 'System CTC', ?, 1, 0, NOW())");
    $stmtSysChat->execute([$id, "Status pengajuan diperbarui menjadi: [ {$new_status} ]"]);

    header("Location: detail.php?id={$id}&msg=updated");
    exit;
}

// Fetch All Chats (untuk jumlah awal tombol)
$stmtChats = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? ORDER BY id ASC");
$stmtChats->execute([$id]);
$chats = $stmtChats->fetchAll();

// LOGIKA NOTIFIKASI ( permanen ): pesan dari klien yg belum dibaca admin
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM deal_chats WHERE requirement_id = ? AND sender_type = 'client' AND admin_read = 0");
$stmtUnread->execute([$id]);
$new_unread_count = (int) $stmtUnread->fetchColumn();

// Resolve lampiran BRD: cocokkan dengan file yg benar-benar ada di server
// (klien upload ke uploads/brd/, tapi data lama bisa ada di folder lain)
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
    if ($brd_href === '') $brd_href = $candidates[0]['url']; // fallback ke folder utama
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12); }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }

    @keyframes bellRing {
        0%, 100% { transform: rotate(0deg); }
        20%, 60% { transform: rotate(14deg); }
        40%, 80% { transform: rotate(-14deg); }
    }
    .bell-shake { animation: bellRing 1.2s infinite ease-in-out; display: inline-block; }
</style>

<main class="flex-1 p-4 md:p-6 overflow-y-auto bg-[#f8fafc] min-h-screen w-full font-sans antialiased text-slate-800 relative">

    <!-- Top Action Bar Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-5 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-2xs">
        <div class="flex items-center gap-3 min-w-0">
            <a href="index.php" class="btn-press w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0" title="Kembali">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2 mb-0.5 flex-wrap">
                    <span class="text-[9px] font-mono font-black uppercase tracking-wider text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 rounded-md">
                        REQ-#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                    <span class="text-[11px] font-bold text-slate-400">• <?= date('d M Y H:i', strtotime($req['created_at'])) ?> WIB</span>
                </div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight leading-snug truncate">
                    <?= htmlspecialchars($req['title']) ?>
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end shrink-0">
            <a href="../projects/index.php?convert_req=1&req_id=<?= $req['id'] ?>&client_name=<?= urlencode($req['client_name']) ?>&title=<?= urlencode($req['title']) ?>&budget=<?= urlencode($req['budget_estimation']) ?>"
               class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition shadow-md shadow-emerald-600/20 flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-xs"></i> Convert to Active Project
            </a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
        <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-bold flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-600"></i> Status pengajuan requirement telah berhasil diperbarui!
        </div>
    <?php endif; ?>

    <!-- Main Grid Workspace -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- KOLOM KIRI (2 Cols) -->
        <div class="lg:col-span-2 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-2xs space-y-5 text-xs">

            <div class="flex items-center justify-between pb-3 border-b border-slate-100 gap-2">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-file-contract text-emerald-600"></i> Spesifikasi Proyek & Klien
                </h3>
                <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 tracking-wider shrink-0">
                    STATUS: <?= htmlspecialchars($req['status']) ?>
                </span>
            </div>

            <!-- Identitas Klien -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/80 p-4 rounded-xl border border-slate-200/60">
                <div>
                    <span class="text-slate-400 font-extrabold uppercase text-[9px] tracking-wider block mb-0.5">Penanggung Jawab Klien</span>
                    <p class="font-black text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($req['client_name']) ?></p>
                    <p class="text-slate-500 font-semibold text-[11px] mt-0.5 break-all"><?= htmlspecialchars($req['client_email']) ?></p>
                </div>

                <div>
                    <span class="text-slate-400 font-extrabold uppercase text-[9px] tracking-wider block mb-0.5">Perusahaan / Perorangan</span>
                    <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($req['company_name'] ?? 'PT. / Individual') ?></p>
                    <p class="text-slate-500 font-semibold text-[11px] mt-0.5"><i class="fa-solid fa-phone text-slate-400 mr-1"></i><?= htmlspecialchars($req['client_phone'] ?? '-') ?></p>
                </div>
            </div>

            <!-- Budget & File BRD -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-0.5">Alokasi Budget Proyek</span>
                    <p class="font-mono font-black text-emerald-700 text-sm">
                        Rp <?= number_format($req['budget_estimation'], 0, ',', '.') ?>
                    </p>
                </div>

                <!-- Lampiran Dokumen BRD (path di-resolve otomatis sesuai file yg ada) -->
                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-0.5">Lampiran Dokumen BRD</span>
                    <?php if ($brd_href !== ''): ?>
                        <a href="<?= $brd_href ?>" target="_blank" class="text-emerald-700 font-extrabold hover:underline flex items-center gap-1.5 text-xs mt-1" title="Lihat Dokumen BRD">
                            <i class="fa-solid fa-file-pdf text-emerald-600 shrink-0"></i>
                            <span class="truncate"><?= htmlspecialchars($brd_filename) ?></span>
                        </a>
                    <?php else: ?>
                        <span class="text-slate-400 italic font-medium text-xs">Tidak ada lampiran dokumen</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deskripsi Scope -->
            <div>
                <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-1.5">Uraian Kebutuhan & Ruang Lingkup Sistem</span>
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-xl text-slate-800 text-xs leading-relaxed font-medium whitespace-pre-line min-h-[90px]">
                    <?= htmlspecialchars($req['description']) ?>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN (1 Col) -->
        <div class="space-y-4">

            <!-- Update Status Card (Tanpa opsi Pending) -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs text-xs space-y-4">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-emerald-600"></i> Kelola Status Proyek
                </h3>

                <form method="POST" class="space-y-3">
                    <div>
                        <label class="block text-[9px] font-extrabold uppercase text-slate-400 tracking-wider mb-1.5">Status Pengajuan Saat Ini</label>
                        <select name="status" class="input-glow w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-800 bg-white focus:outline-none">
                            <option value="Under Review" <?= $req['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review (Dalam Peninjauan)</option>
                            <option value="Proposal Sent" <?= $req['status'] === 'Proposal Sent' ? 'selected' : '' ?>>Proposal Sent (Penawaran Dikirim)</option>
                            <option value="Deal / Accepted" <?= $req['status'] === 'Deal / Accepted' ? 'selected' : '' ?>>Deal / Accepted (Disetujui)</option>
                            <option value="Rejected" <?= $req['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected (Ditolak)</option>
                        </select>
                    </div>

                    <button type="submit" name="update_status" class="btn-press w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl transition text-xs uppercase tracking-wider">
                        Simpan Perubahan Status
                    </button>
                </form>
            </div>

            <!-- CARD CHAT NEGOSIASI -->
            <div class="bg-slate-900 text-white p-5 rounded-2xl border border-slate-800 shadow-md space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2.5 gap-2">
                    <h4 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                        <i class="fa-solid fa-comments text-emerald-400"></i> Diskusi & Negosiasi Klien
                    </h4>

                    <span id="unreadBadge" class="bg-red-600 text-white text-[9px] font-black px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm animate-pulse border border-red-400/40 <?= $new_unread_count > 0 ? '' : 'hidden' ?>">
                        <i class="fa-solid fa-bell text-[9px] bell-shake text-amber-300"></i> <span id="unreadCount">+<?= $new_unread_count ?> Pesan Baru</span>
                    </span>
                </div>

                <p class="text-slate-300 text-[11px] font-medium leading-relaxed">
                    Kirim pesan balasan atau konsultasikan scope dengan klien secara <em>real-time</em>. Tekan <strong>Enter</strong> untuk mengirim.
                </p>

                <button onclick="openChatModal()" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl transition text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                    <i class="fa-solid fa-comments"></i> Buka Diskusi Negosiasi (<span id="totalChatCount"><?= count($chats) ?></span>)
                </button>
            </div>

        </div>

    </div>

    <!-- MODAL POPUP CHAT (REAL-TIME) — full screen di HP, centered di desktop -->
    <div id="chatModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-0 sm:p-6">
        <div class="bg-white border border-slate-200 sm:rounded-3xl shadow-2xl w-full h-[100dvh] sm:h-[85vh] max-w-3xl flex flex-col overflow-hidden relative animate-in fade-in zoom-in-95 duration-200">

            <div class="p-4 sm:px-6 bg-slate-900 text-white flex items-center justify-between shrink-0 z-10">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-white/10 border border-white/20 p-1 flex items-center justify-center shrink-0">
                        <img src="../../assets/images/logo.jpg" alt="Logo CTC" class="w-full h-full object-contain">
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-xs font-black uppercase tracking-wider text-white truncate">Lobi Negosiasi & Diskusi Scope</h3>
                        <p class="text-[10px] text-slate-300 font-medium truncate">Klien: <strong class="text-emerald-400"><?= htmlspecialchars($req['client_name']) ?></strong></p>
                    </div>
                </div>

                <button onclick="closeChatModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition shrink-0">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="flex-1 relative overflow-hidden bg-slate-50/50 min-h-0">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                    <img src="../../assets/images/logo.jpg" alt="Watermark CTC Chat" class="w-[400px] max-w-[80vw] h-auto object-contain opacity-[0.08] mix-blend-multiply">
                </div>

                <div id="modal-chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs relative z-10">
                    <!-- Chat Container via JS -->
                </div>
            </div>

            <form id="adminChatForm" onsubmit="sendAdminChat(event)" class="p-3.5 sm:p-4 bg-white border-t border-slate-200 flex gap-2 shrink-0 z-10">
                <input type="text" id="adminChatInput" required placeholder="Tulis balasan... (Enter untuk kirim)" autocomplete="off"
                       class="input-glow flex-1 px-4 py-3 rounded-2xl border border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white focus:outline-none font-semibold transition-all placeholder:font-normal min-w-0">
                <button type="submit" id="adminChatBtn" class="btn-press px-5 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-black rounded-2xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center gap-2 shrink-0 uppercase tracking-wider">
                    <i class="fa-solid fa-paper-plane"></i> <span class="hidden sm:inline">Kirim</span>
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

// ---- Utils: anti-XSS + newline ----
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, s => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[s]));
}
function nl2br(str) { return escapeHtml(str).replace(/\n/g, '<br>'); }

function renderSingleChat(c) {
    const isAdmin = (c.sender_type === 'admin');
    const containerClass = isAdmin ? 'items-end' : 'items-start';
    const nameClass = isAdmin ? 'text-emerald-700 font-bold' : 'text-slate-800';
    const bubbleClass = isAdmin
        ? 'bg-emerald-600 text-white rounded-tr-none'
        : 'bg-white text-slate-800 rounded-tl-none border border-slate-200';

    return `
        <div class="flex flex-col ${containerClass} w-full chat-bubble" data-id="${c.id}">
            <div class="text-[10px] text-slate-500 font-extrabold mb-1 px-1 flex items-center gap-1.5">
                <span class="${nameClass}">${escapeHtml(c.sender_name)}</span>
                <span>•</span>
                <span class="font-normal text-slate-400">${escapeHtml(c.created_at)} WIB</span>
            </div>
            <div class="max-w-[85%] sm:max-w-[75%] p-3.5 sm:p-4 rounded-2xl text-xs leading-relaxed break-words font-medium shadow-2xs ${bubbleClass}">
                ${nl2br(c.message)}
            </div>
        </div>
    `;
}

// ---- Append SATU pesan (tanpa re-render list -> tidak lag, tidak dobel) ----
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

// ---- Poll: hanya pesan baru (last_id). reader=admin -> tandai dibaca ----
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
                    box.innerHTML = '<div class="empty-chat-notice text-center py-24 text-slate-400 font-semibold"><i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>Belum ada percakapan. Mulai diskusi negosiasi dengan klien.</div>';
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

// ---- Kirim: Enter langsung kirim, tanpa reload, bubble langsung muncul ----
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
        box.innerHTML = '<div class="empty-chat-notice text-center py-24 text-slate-400 font-semibold"><i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>Memuat percakapan...</div>';
    }

    fetchChats(); // langsung mark-read -> notif hilang permanen
    startPolling();
}

function closeChatModal() {
    chatModalOpen = false;
    document.getElementById('chatModal').classList.add('hidden');
    document.body.style.overflow = '';
    fetchChats(); // mode background: hitung ulang unread tanpa mark-read
}

document.addEventListener('DOMContentLoaded', function() {
    fetchChats();
    startPolling();

    // langsung poll lagi saat tab kembali terlihat
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) fetchChats();
    });

    // tutup modal dengan tombol ESC
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