<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

// Proteksi Sesi Login
if (
    !isset($_SESSION['client_logged_in']) ||
    $_SESSION['client_logged_in'] !== true ||
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id = intval($_SESSION['client_user_id']);
$client_nama = $_SESSION['client_nama'] ?? 'Klien';
$client_email = $_SESSION['client_email'] ?? '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset(
        $_SESSION['client_logged_in'],
        $_SESSION['client_user_id'],
        $_SESSION['client_nama'],
        $_SESSION['client_email'],
        $_SESSION['client_token']
    );

    session_destroy();
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

// Target requirement yang dipilih
$selected_id = isset($_GET['req_id'])
    ? intval($_GET['req_id'])
    : ($requirements[0]['id'] ?? 0);

$active_req = null;

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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - PT. Cipta Teknologi Cendekia</title>

    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .btn-press { transition: all .15s ease; }
        .btn-press:active { transform: scale(.97); }

        .input-glow:focus {
            border-color: #059669;
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12);
        }

        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 99px;
        }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white">

<header class="bg-white border-b border-slate-200/90 px-4 sm:px-8 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-sm">
    <div class="flex items-center gap-3.5 min-w-0">
        <div class="w-11 h-11 rounded-2xl bg-white border border-slate-200/80 p-1 shadow-sm flex items-center justify-center shrink-0">
            <img src="../assets/images/logo.jpg" alt="Logo CTC" class="w-full h-full object-contain">
        </div>

        <div class="min-w-0">
            <h1 class="text-xs font-black text-slate-900 uppercase tracking-wider">CLIENT PORTAL</h1>
            <p class="text-[11px] text-slate-500 font-bold truncate">PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <div class="hidden sm:flex items-center gap-2 px-3.5 py-2 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs font-extrabold text-slate-700 shadow-sm">
            <i class="fa-solid fa-circle-user text-emerald-600 text-sm"></i>
            <span><?= htmlspecialchars($client_nama) ?></span>
        </div>

        <a href="portal.php?action=logout" class="btn-press px-4 py-2 bg-red-50 hover:bg-red-100/80 text-red-600 border border-red-200 rounded-2xl text-xs font-black transition flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span class="hidden sm:inline">Logout</span>
        </a>
    </div>
</header>

<main class="max-w-7xl mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">

    <!-- Sidebar Requirements -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 space-y-4 shadow-xl shadow-slate-200/60 flex flex-col h-[420px] lg:h-[650px]">

        <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 shrink-0 gap-2">
            <h2 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-folder-open text-emerald-600"></i>
                Pengajuan Proyek
            </h2>

            <a href="request.php" class="btn-press px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/80 rounded-xl text-[11px] font-black transition flex items-center gap-1 shrink-0">
                <i class="fa-solid fa-plus text-[10px]"></i>
                Ajukan Baru
            </a>
        </div>

        <?php if (empty($requirements)): ?>

            <div class="flex-1 flex flex-col items-center justify-center text-center py-8 text-slate-400 text-xs font-medium">
                <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                Belum ada pengajuan requirement.
            </div>

        <?php else: ?>

            <div class="space-y-2.5 overflow-y-auto flex-1 pr-1 min-h-0">

                <?php foreach ($requirements as $r): ?>
                    <?php $uc = (int) ($unreadMap[$r['id']] ?? 0); ?>

                    <a href="portal.php?req_id=<?= $r['id'] ?>"
                       class="block p-4 rounded-2xl border transition-all duration-150 <?= ($active_req && $active_req['id'] == $r['id']) ? 'bg-emerald-50/70 border-emerald-500/80 shadow-md ring-1 ring-emerald-500/20' : 'bg-slate-50/70 border-slate-200/80 hover:bg-white hover:border-slate-300' ?>">

                        <div class="flex items-start justify-between gap-2">
                            <p class="text-xs font-black text-slate-900 truncate leading-snug min-w-0">
                                <?= htmlspecialchars($r['title']) ?>
                            </p>

                            <span id="unreadBadge-<?= $r['id'] ?>"
                                  class="unread-badge shrink-0 bg-red-500 text-white text-[9px] font-black min-w-[18px] h-[18px] px-1 rounded-full flex items-center justify-center border border-white shadow-sm <?= $uc > 0 ? '' : 'hidden' ?>">
                                <?= $uc ?>
                            </span>
                        </div>

                        <div class="flex items-center justify-between mt-3 text-[10px] gap-2">
                            <span class="text-slate-500 font-bold">
                                <i class="fa-regular fa-calendar mr-1"></i>
                                <?= date('d M Y', strtotime($r['created_at'])) ?>
                            </span>

                            <span class="px-2.5 py-1 rounded-lg font-black uppercase text-[9px] bg-emerald-100 text-emerald-800 border border-emerald-200/80 tracking-wider shadow-2xs truncate">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
                        </div>
                    </a>
                <?php endforeach; ?>

            </div>

        <?php endif; ?>
    </div>

    <!-- Chat Lobby -->
    <div class="lg:col-span-2 bg-white border border-slate-200/90 rounded-3xl flex flex-col h-[560px] sm:h-[620px] lg:h-[650px] shadow-xl shadow-slate-200/60 overflow-hidden relative">

        <?php if (!$active_req): ?>

            <div class="flex-1 flex items-center justify-center text-slate-400 text-xs font-semibold">
                <div class="text-center">
                    <i class="fa-solid fa-comments text-4xl mb-3 text-slate-300 block"></i>
                    Pilih pengajuan proyek di sebelah kiri untuk memulai diskusi.
                </div>
            </div>

        <?php else: ?>

            <div class="p-4 sm:px-6 bg-slate-50/90 border-b border-slate-200/90 flex items-center justify-between shrink-0 z-10 backdrop-blur-sm gap-2">
                <div class="pr-2 min-w-0">
                    <h2 class="text-xs font-black text-slate-900 tracking-tight uppercase truncate">
                        <?= htmlspecialchars($active_req['title']) ?>
                    </h2>
                    <p class="text-[11px] text-slate-500 font-semibold mt-0.5">
                        Diskusi Scope & Surat Penawaran dengan Tim PT. CTC
                    </p>
                </div>

                <span class="px-3 py-1 text-[9px] font-black uppercase rounded-xl bg-emerald-100 text-emerald-800 border border-emerald-200 tracking-wider shrink-0">
                    STATUS: <?= htmlspecialchars($active_req['status']) ?>
                </span>
            </div>

            <div class="flex-1 relative overflow-hidden bg-slate-50/40 min-h-0">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                    <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[380px] sm:w-[420px] h-auto object-contain opacity-[0.08] mix-blend-multiply">
                </div>

                <div id="chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs relative z-10">
                    <!-- Content render via JS AJAX -->
                </div>
            </div>

            <form id="clientChatForm" onsubmit="sendClientChat(event)" class="p-3.5 sm:p-4 bg-white border-t border-slate-200/90 flex gap-2 shrink-0 z-10">
                <input type="text" id="clientChatInput" required placeholder="Tulis pesan... (Enter untuk kirim)" autocomplete="off"
                       class="input-glow flex-1 px-4 py-3 rounded-2xl border border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white focus:outline-none font-semibold transition-all placeholder:font-normal min-w-0">

                <button type="submit" id="clientChatBtn" class="btn-press px-5 py-3 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-60 text-white font-black rounded-2xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center gap-2 shrink-0 uppercase tracking-wider">
                    <i class="fa-solid fa-paper-plane"></i>
                    <span class="hidden sm:inline">Kirim</span>
                </button>
            </form>

        <?php endif; ?>
    </div>
</main>

<?php if ($active_req): ?>
<script>
    const activeReqId = <?= (int) $active_req['id'] ?>;

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

    function renderSingleChat(c) {
        const isClient = c.sender_type === 'client';
        const containerClass = isClient ? 'items-end' : 'items-start';
        const nameClass = isClient ? 'text-emerald-700 font-bold' : 'text-slate-700';

        const bubbleClass = isClient
            ? 'bg-emerald-600 text-white rounded-tr-none'
            : 'bg-white text-slate-800 rounded-tl-none border border-slate-200/90';

        return `
            <div class="flex flex-col ${containerClass} w-full chat-bubble" data-id="${c.id}">
                <div class="text-[10px] text-slate-500 font-extrabold mb-1 px-1 flex items-center gap-1.5">
                    <span class="${nameClass}">${escapeHtml(c.sender_name)}</span>
                    <span>•</span>
                    <span class="font-normal text-slate-400">${escapeHtml(c.created_at)} WIB</span>
                </div>

                <div class="max-w-[85%] sm:max-w-[75%] p-3.5 sm:p-4 rounded-2xl text-xs leading-relaxed break-words font-medium shadow-sm ${bubbleClass}">
                    ${nl2br(c.message)}
                </div>
            </div>
        `;
    }

    function appendChat(c) {
        if (!c || c.id <= lastChatId) return;

        const box = document.getElementById('chat-box');
        const emptyState = box.querySelector('.empty-chat-notice');

        if (emptyState) emptyState.remove();

        const nearBottom =
            box.scrollTop + box.clientHeight >= box.scrollHeight - 80;

        box.insertAdjacentHTML('beforeend', renderSingleChat(c));
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
        fetch(
            `../api_chat.php?action=fetch&req_id=${activeReqId}&last_id=${lastChatId}&reader=client`,
            { cache: 'no-store' }
        )
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                if (data.messages.length > 0) {
                    data.messages.forEach(appendChat);
                    chatHistoryLoaded = true;
                } else if (!chatHistoryLoaded) {
                    chatHistoryLoaded = true;

                    const box = document.getElementById('chat-box');

                    if (box.children.length === 0) {
                        box.innerHTML = `
                            <div class="empty-chat-notice text-center py-20 text-slate-400 font-semibold">
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
                    appendChat(data.message);

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
        const box = document.getElementById('chat-box');

        box.innerHTML = `
            <div class="empty-chat-notice text-center py-20 text-slate-400 font-semibold">
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
<?php endif; ?>

</body>
</html>