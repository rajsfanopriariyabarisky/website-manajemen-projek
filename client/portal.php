<?php
session_start();
require_once __DIR__ . '/../config/database.php';

// 1. Proteksi Sesi Login & Token Akses Valid
if (
    !isset($_SESSION['client_logged_in']) || 
    $_SESSION['client_logged_in'] !== true || 
    empty($_SESSION['client_token']) || 
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id   = intval($_SESSION['client_user_id']);
$client_nama = $_SESSION['client_nama'] ?? 'Klien';

// Logout Client
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['client_logged_in'], $_SESSION['client_user_id'], $_SESSION['client_nama'], $_SESSION['client_email'], $_SESSION['client_token']);
    session_destroy();
    header('Location: login.php');
    exit;
}

// 2. Fetch All Requirements milik Client ini (Strict Data Isolation)
$stmtReqs = $pdo->prepare("SELECT * FROM client_requirements WHERE client_id = ? ORDER BY id DESC");
$stmtReqs->execute([$client_id]);
$requirements = $stmtReqs->fetchAll();

// Target requirement yang dipilih
$selected_id = isset($_GET['req_id']) ? intval($_GET['req_id']) : ($requirements[0]['id'] ?? 0);

$active_req = null;
if ($selected_id > 0) {
    $stmtActive = $pdo->prepare("SELECT * FROM client_requirements WHERE id = ? AND client_id = ?");
    $stmtActive->execute([$selected_id, $client_id]);
    $active_req = $stmtActive->fetch();
}

// 3. Handle Kirim Pesan Chat dari Klien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_client_chat']) && $active_req) {
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($message)) {
        $clean_message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        $stmtChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message) VALUES (?, 'client', ?, ?)");
        $stmtChat->execute([$active_req['id'], $client_nama, $clean_message]);

        header("Location: portal.php?req_id={$active_req['id']}#chat-box");
        exit;
    }
}

// Fetch Chat untuk Requirement aktif
$chats = [];
if ($active_req) {
    $stmtChats = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? ORDER BY id ASC");
    $stmtChats->execute([$active_req['id']]);
    $chats = $stmtChats->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Portal - PT. Cipta Teknologi Cendekia</title>

    <!-- Favicon Logo CTC untuk Tab Browser Chrome/Google -->
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-press { transition: all .15s ease; }
        .btn-press:active { transform: scale(.97); }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white">

    <!-- Top Navigation Header -->
    <header class="bg-white border-b border-slate-200/90 px-4 sm:px-8 py-3.5 flex items-center justify-between sticky top-0 z-30 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-white border border-slate-200/80 p-1 shadow-sm flex items-center justify-center shrink-0">
                <img src="../assets/images/logo.jpg" alt="Logo CTC" class="w-full h-full object-contain">
            </div>
            <div>
                <h1 class="text-xs font-black text-slate-900 uppercase tracking-wider">CLIENT PORTAL</h1>
                <p class="text-[11px] text-slate-500 font-bold">PT. Cipta Teknologi Cendekia</p>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="hidden sm:flex items-center gap-2 px-3.5 py-2 bg-slate-50 border border-slate-200/80 rounded-2xl text-xs font-extrabold text-slate-700 shadow-sm">
                <i class="fa-solid fa-circle-user text-emerald-600 text-sm"></i>
                <span><?= htmlspecialchars($client_nama) ?></span>
            </div>
            <a href="portal.php?action=logout" class="btn-press px-4 py-2 bg-red-50 hover:bg-red-100/80 text-red-600 border border-red-200 rounded-2xl text-xs font-black transition flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left Sidebar: Requirements List -->
        <div class="bg-white border border-slate-200/90 rounded-3xl p-4 sm:p-5 space-y-4 shadow-xl shadow-slate-200/60 flex flex-col h-[650px]">
            <div class="flex items-center justify-between pb-3.5 border-b border-slate-100 shrink-0">
                <h2 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-folder-open text-emerald-600"></i> Pengajuan Proyek
                </h2>
                <a href="request.php" class="btn-press px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/80 rounded-xl text-[11px] font-black transition flex items-center gap-1">
                    <i class="fa-solid fa-plus text-[10px]"></i> Ajukan Baru
                </a>
            </div>

            <?php if (empty($requirements)): ?>
                <div class="flex-1 flex flex-col items-center justify-center text-center py-8 text-slate-400 text-xs font-medium">
                    <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                    Belum ada pengajuan requirement.
                </div>
            <?php else: ?>
                <div class="space-y-2.5 overflow-y-auto flex-1 pr-1">
                    <?php foreach ($requirements as $r): ?>
                        <a href="portal.php?req_id=<?= $r['id'] ?>" class="block p-4 rounded-2xl border transition-all duration-150 <?= ($active_req && $active_req['id'] == $r['id']) ? 'bg-emerald-50/70 border-emerald-500/80 shadow-md ring-1 ring-emerald-500/20' : 'bg-slate-50/70 border-slate-200/80 hover:bg-white hover:border-slate-300' ?>">
                            <p class="text-xs font-black text-slate-900 truncate leading-snug"><?= htmlspecialchars($r['title']) ?></p>
                            <div class="flex items-center justify-between mt-3 text-[10px]">
                                <span class="text-slate-500 font-bold"><i class="fa-regular fa-calendar mr-1"></i><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                                <span class="px-2.5 py-1 rounded-lg font-black uppercase text-[9px] bg-emerald-100 text-emerald-800 border border-emerald-200/80 tracking-wider shadow-2xs">
                                    <?= htmlspecialchars($r['status']) ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right Main Column: Chat Lobby -->
        <div class="lg:col-span-2 bg-white border border-slate-200/90 rounded-3xl flex flex-col h-[650px] shadow-xl shadow-slate-200/60 overflow-hidden relative">
            <?php if (!$active_req): ?>
                <div class="flex-1 flex items-center justify-center text-slate-400 text-xs font-semibold">
                    <div class="text-center">
                        <i class="fa-solid fa-comments text-4xl mb-3 text-slate-300 block"></i>
                        Pilih pengajuan proyek di sebelah kiri untuk memulai diskusi.
                    </div>
                </div>
            <?php else: ?>
                <!-- Chat Box Header -->
                <div class="p-4 sm:px-6 bg-slate-50/90 border-b border-slate-200/90 flex items-center justify-between shrink-0 z-10 backdrop-blur-sm">
                    <div class="pr-2">
                        <h2 class="text-xs font-black text-slate-900 tracking-tight uppercase truncate"><?= htmlspecialchars($active_req['title']) ?></h2>
                        <p class="text-[11px] text-slate-500 font-semibold mt-0.5">Diskusi Scope & Surat Penawaran dengan Tim PT. CTC</p>
                    </div>
                    <span class="px-3 py-1 text-[9px] font-black uppercase rounded-xl bg-emerald-100 text-emerald-800 border border-emerald-200 tracking-wider shrink-0">
                        STATUS: <?= htmlspecialchars($active_req['status']) ?>
                    </span>
                </div>

                <!-- Wrapper Chat Box Utama -->
                <div class="flex-1 relative overflow-hidden bg-slate-50/40">
                    
                    <!-- Watermark Logo CTC Terkunci di Tengah (Tatap Muka & Tidak Ikut Ter-scroll) -->
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                        <img src="../assets/images/logo.jpg" alt="Watermark Fixed CTC" class="w-[380px] sm:w-[420px] h-auto object-contain opacity-[0.08] mix-blend-multiply">
                    </div>

                    <!-- Area Scroll Pesan Chat -->
                    <div id="chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs relative z-10">
                        <?php if (empty($chats)): ?>
                            <div class="text-center py-20 text-slate-400 font-semibold">
                                <i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>
                                Belum ada pesan dalam diskusi ini.<br>
                                <span class="text-[11px] text-slate-400 font-normal">Tuliskan pertanyaan atau kebutuhan Anda di bawah ini.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($chats as $c): ?>
                                <?php $isClient = ($c['sender_type'] === 'client'); ?>
                                <div class="flex flex-col <?= $isClient ? 'items-end' : 'items-start' ?> w-full">
                                    <div class="text-[10px] text-slate-500 font-extrabold mb-1 px-1 flex items-center gap-1.5">
                                        <span class="<?= $isClient ? 'text-emerald-700' : 'text-slate-700' ?>"><?= htmlspecialchars($c['sender_name']) ?></span>
                                        <span>•</span>
                                        <span class="font-normal text-slate-400"><?= date('d M H:i', strtotime($c['created_at'])) ?></span>
                                    </div>
                                    <div class="max-w-[85%] sm:max-w-[75%] p-3.5 sm:p-4 rounded-2xl text-xs leading-relaxed break-words font-medium shadow-sm <?= $isClient ? 'bg-emerald-600 text-white rounded-tr-none' : 'bg-white text-slate-800 rounded-tl-none border border-slate-200/90' ?>">
                                        <?= nl2br(htmlspecialchars($c['message'])) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Input Form -->
                <form method="POST" class="p-3.5 sm:p-4 bg-white border-t border-slate-200/90 flex gap-2 shrink-0 z-10">
                    <input type="text" name="message" required placeholder="Tulis pesan atau pertanyaan ke tim CTC..." class="input-glow flex-1 px-4 py-3 rounded-2xl border border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white focus:outline-none font-semibold transition-all placeholder:font-normal">
                    <button type="submit" name="send_client_chat" class="btn-press px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center gap-2 shrink-0 uppercase tracking-wider">
                        <i class="fa-solid fa-paper-plane"></i> Kirim
                    </button>
                </form>
            <?php endif; ?>
        </div>

    </main>

    <script>
        const chatBox = document.getElementById('chat-box');
        if (chatBox) chatBox.scrollTop = chatBox.scrollHeight;
    </script>
</body>
</html>