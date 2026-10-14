<?php
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['client_logged_in']) || $_SESSION['client_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$client_id = $_SESSION['client_user_id'];

// Logout Client
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['client_logged_in'], $_SESSION['client_user_id'], $_SESSION['client_nama'], $_SESSION['client_email']);
    header('Location: login.php');
    exit;
}

// Fetch All Requirements milik Client ini dari tabel clients
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

// Handle Kirim Pesan Chat dari Klien
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_client_chat']) && $active_req) {
    $message = trim($_POST['message'] ?? '');
    if (!empty($message)) {
        $stmtChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message) VALUES (?, 'client', ?, ?)");
        $stmtChat->execute([$active_req['id'], $_SESSION['client_nama'], $message]);

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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen font-sans">

    <!-- Navbar -->
    <header class="bg-slate-900 border-b border-slate-800 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <h1 class="text-sm font-bold text-white">CLIENT PORTAL</h1>
                <p class="text-[10px] text-slate-400">PT. Cipta Teknologi Cendekia</p>
            </div>
        </div>

        <div class="flex items-center gap-4">
            <span class="text-xs text-slate-300 font-bold"><i class="fa-solid fa-user-circle mr-1 text-emerald-400"></i> <?= htmlspecialchars($_SESSION['client_nama']) ?></span>
            <a href="portal.php?action=logout" class="px-3 py-1.5 bg-red-500/10 text-red-400 hover:bg-red-500/20 rounded-lg text-xs font-bold transition">
                Logout
            </a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto p-4 md:p-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Sidebar Daftar Requirement -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 space-y-3">
            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-300">Daftar Pengajuan Proyek</h2>
                <a href="request.php" class="text-[11px] font-bold text-emerald-400 hover:underline">+ Ajukan Baru</a>
            </div>

            <?php if (empty($requirements)): ?>
                <p class="text-xs text-slate-500 py-4 text-center">Belum ada pengajuan requirement.</p>
            <?php else: ?>
                <div class="space-y-2">
                    <?php foreach ($requirements as $r): ?>
                        <a href="portal.php?req_id=<?= $r['id'] ?>" class="block p-3 rounded-xl border transition <?= ($active_req && $active_req['id'] == $r['id']) ? 'bg-emerald-950/40 border-emerald-500/50' : 'bg-slate-950/50 border-slate-800 hover:border-slate-700' ?>">
                            <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($r['title']) ?></p>
                            <div class="flex items-center justify-between mt-2 text-[10px]">
                                <span class="text-slate-400"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                                <span class="px-2 py-0.5 rounded font-bold uppercase text-[9px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                    <?= $r['status'] ?>
                                </span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Chat Lobi Negosiasi -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl flex flex-col h-[600px]">
            <?php if (!$active_req): ?>
                <div class="flex-1 flex items-center justify-center text-slate-500 text-xs">Pilih pengajuan proyek di sebelah kiri untuk berdiskusi.</div>
            <?php else: ?>
                <div class="p-4 bg-slate-950 border-b border-slate-800 rounded-t-2xl flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold text-white"><?= htmlspecialchars($active_req['title']) ?></h2>
                        <p class="text-[10px] text-slate-400">Diskusi Scope & Surat Penawaran dengan Tim PT. CTC</p>
                    </div>
                    <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        Status: <?= $active_req['status'] ?>
                    </span>
                </div>

                <div id="chat-box" class="flex-1 p-4 overflow-y-auto space-y-3 text-xs">
                    <?php foreach ($chats as $c): ?>
                        <?php $isClient = ($c['sender_type'] === 'client'); ?>
                        <div class="flex flex-col <?= $isClient ? 'items-end' : 'items-start' ?>">
                            <div class="text-[10px] text-slate-500 mb-1">
                                <strong><?= htmlspecialchars($c['sender_name']) ?></strong> • <?= date('d M H:i', strtotime($c['created_at'])) ?>
                            </div>
                            <div class="max-w-md p-3 rounded-xl text-xs <?= $isClient ? 'bg-emerald-600 text-white rounded-tr-none' : 'bg-slate-800 text-slate-200 rounded-tl-none border border-slate-700' ?>">
                                <?= nl2br(htmlspecialchars($c['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="POST" class="p-3 bg-slate-950 border-t border-slate-800 rounded-b-2xl flex gap-2">
                    <input type="text" name="message" required placeholder="Tulis pesan atau pertanyaan ke tim CTC..." class="flex-1 px-3.5 py-2 rounded-xl border border-slate-800 bg-slate-900 text-xs text-white focus:outline-none focus:border-emerald-500">
                    <button type="submit" name="send_client_chat" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition">
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