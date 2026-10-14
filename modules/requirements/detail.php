<?php
ob_start();
session_start();
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
    $stmtSysChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message) VALUES (?, 'admin', 'System CTC', ?)");
    $stmtSysChat->execute([$id, "Status pengajuan diperbarui menjadi: [ {$new_status} ]"]);

    header("Location: detail.php?id={$id}&msg=updated");
    exit;
}

// Handle Kirim Pesan Chat dari Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_chat'])) {
    $message = trim($_POST['message'] ?? '');
    $admin_name = $_SESSION['nama'] ?? 'Tim Admin CTC';

    if (!empty($message)) {
        $stmtChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message) VALUES (?, 'admin', ?, ?)");
        $stmtChat->execute([$id, $admin_name, $message]);
        
        // Auto update status ke Under Review jika masih Pending
        if ($req['status'] === 'Pending') {
            $pdo->prepare("UPDATE client_requirements SET status = 'Under Review' WHERE id = ?")->execute([$id]);
        }

        header("Location: detail.php?id={$id}#chat-box");
        exit;
    }
}

// Fetch All Chats
$stmtChats = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? ORDER BY id ASC");
$stmtChats->execute([$id]);
$chats = $stmtChats->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 md:p-6 overflow-y-auto bg-[#f8fafc] min-h-screen w-full font-sans antialiased text-slate-800">
    
    <!-- Top Action Bar Header -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-5 bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-sm">
        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-press w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div>
                <span class="text-[10px] font-mono font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 rounded-md inline-block mb-1">
                    REQ-#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?>
                </span>
                <h1 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight leading-snug line-clamp-1">
                    <?= htmlspecialchars($req['title'] ?? $req['judul_projek'] ?? 'Requirement') ?>
                </h1>
            </div>
        </div>

        <!-- Single Action Button Focus -->
        <div class="flex items-center gap-2 w-full sm:w-auto justify-end shrink-0">
            <a href="../projects/index.php?convert_req=1&req_id=<?= $req['id'] ?>&client_name=<?= urlencode($req['client_name'] ?? $req['nama_klien'] ?? '') ?>&title=<?= urlencode($req['title'] ?? $req['judul_projek'] ?? '') ?>&budget=<?= urlencode($req['budget_estimation'] ?? $req['estimasi_budget'] ?? 0) ?>" 
               class="btn-press w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2">
                <i class="fa-solid fa-folder-plus text-xs"></i> Convert to Active Project
            </a>
        </div>
    </div>

    <!-- Main Grid Workspace Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
        
        <!-- LEFT COLUMN: Client Identity Card & Status Change -->
        <div class="space-y-5">
            
            <!-- Identitas Card -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm space-y-4 text-xs">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider">Identitas Klien & Proyek</h3>
                    <div class="w-7 h-7 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-400 text-xs">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                </div>

                <div>
                    <span class="text-slate-400 font-black block uppercase text-[9px] tracking-wider mb-0.5">Klien / Instansi</span>
                    <p class="font-black text-slate-900 text-xs sm:text-sm leading-snug"><?= htmlspecialchars($req['client_name'] ?? $req['nama_klien'] ?? '-') ?></p>
                    <p class="text-slate-500 font-medium text-[11px] mt-0.5"><?= htmlspecialchars($req['client_email'] ?? $req['email'] ?? '-') ?></p>
                    <p class="text-slate-500 font-medium text-[11px]"><?= htmlspecialchars($req['client_phone'] ?? $req['nomor_telepon'] ?? '-') ?> <?= !empty($req['company_name']) ? '• ' . htmlspecialchars($req['company_name']) : '' ?></p>
                </div>

                <hr class="border-slate-100">

                <div>
                    <span class="text-slate-400 font-black block uppercase text-[9px] tracking-wider mb-0.5">Estimasi Budget Klien</span>
                    <p class="font-black text-emerald-700 font-mono text-sm sm:text-base">
                        Rp <?= number_format($req['budget_estimation'] ?? $req['estimasi_budget'] ?? 0, 0, ',', '.') ?>
                    </p>
                </div>

                <div>
                    <span class="text-slate-400 font-black block uppercase text-[9px] tracking-wider mb-1">Deskripsi Ruang Lingkup</span>
                    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-slate-700 leading-relaxed whitespace-pre-line font-medium text-[11px] max-h-48 overflow-y-auto">
                        <?= htmlspecialchars($req['description'] ?? $req['deskripsi_projek'] ?? '-') ?>
                    </div>
                </div>

                <div>
                    <span class="text-slate-400 font-black block uppercase text-[9px] tracking-wider mb-1">Dokumen Requirement BRD</span>
                    <?php if (!empty($req['file_attachment'])): ?>
                        <a href="../../uploads/requirements/<?= urlencode($req['file_attachment']) ?>" target="_blank" 
                           class="btn-press p-2.5 bg-emerald-50 hover:bg-emerald-100/80 border border-emerald-200/80 rounded-xl text-emerald-800 font-extrabold flex items-center justify-between transition shadow-sm text-[11px]">
                            <span class="truncate pr-2 flex items-center gap-1.5"><i class="fa-solid fa-file-pdf text-emerald-600 text-xs"></i> <?= htmlspecialchars($req['file_attachment']) ?></span>
                            <i class="fa-solid fa-download text-emerald-600 text-xs"></i>
                        </a>
                    <?php else: ?>
                        <p class="text-slate-400 italic font-semibold text-[11px]">Tidak ada dokumen dilampirkan.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Update Status Card -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-sm text-xs">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider mb-3">Update Status Pengajuan</h3>
                <form method="POST" class="space-y-3">
                    <select name="status" class="input-glow w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
                        <option value="Pending" <?= $req['status'] === 'Pending' ? 'selected' : '' ?>>Pending (Belum Ditinjau)</option>
                        <option value="Under Review" <?= $req['status'] === 'Under Review' ? 'selected' : '' ?>>Under Review (Dalam Peninjauan)</option>
                        <option value="Proposal Sent" <?= $req['status'] === 'Proposal Sent' ? 'selected' : '' ?>>Proposal Sent (Penawaran Dikirim)</option>
                        <option value="Deal / Accepted" <?= $req['status'] === 'Deal / Accepted' ? 'selected' : '' ?>>Deal / Accepted (Disetujui)</option>
                        <option value="Rejected" <?= $req['status'] === 'Rejected' ? 'selected' : '' ?>>Rejected (Ditolak)</option>
                    </select>

                    <button type="submit" name="update_status" class="btn-press w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white font-black rounded-xl transition shadow-sm text-xs">
                        Simpan Perubahan Status
                    </button>
                </form>
            </div>

        </div>

        <!-- RIGHT COLUMN: Negotiation Lobby & Chat Portal -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-slate-200/90 shadow-sm flex flex-col h-[580px] sm:h-[620px] overflow-hidden">
            
            <!-- Chat Box Header -->
            <div class="p-3.5 sm:p-4 bg-slate-900 text-white flex items-center justify-between shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-400 font-bold text-xs shrink-0">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-black text-xs tracking-wide truncate">Lobi Negosiasi & Diskusi Scope</h3>
                        <p class="text-[10px] text-slate-400 font-medium truncate">Klien: <?= htmlspecialchars($req['client_name'] ?? $req['nama_klien'] ?? 'Klien') ?></p>
                    </div>
                </div>

                <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-lg bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 tracking-wider shrink-0 ml-2">
                    <?= htmlspecialchars($req['status']) ?>
                </span>
            </div>

            <!-- Chat Message List Area -->
            <div id="chat-box" class="flex-1 p-3.5 sm:p-4 overflow-y-auto space-y-3.5 bg-slate-50/60 text-xs">
                <?php if (empty($chats)): ?>
                    <div class="text-center py-16 text-slate-400">
                        <i class="fa-solid fa-message text-3xl mb-2 block text-slate-300"></i>
                        <p class="font-bold">Belum ada obrolan untuk requirement ini.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($chats as $c): ?>
                        <?php $isAdmin = ($c['sender_type'] === 'admin'); ?>
                        <div class="flex flex-col <?= $isAdmin ? 'items-end' : 'items-start' ?> w-full">
                            <div class="flex items-center gap-1.5 mb-1 text-[10px] text-slate-400 font-bold px-1">
                                <span class="<?= $isAdmin ? 'text-emerald-700 font-black' : 'text-slate-700' ?>"><?= htmlspecialchars($c['sender_name']) ?></span>
                                <span>•</span>
                                <span><?= date('d M H:i', strtotime($c['created_at'])) ?></span>
                            </div>
                            <div class="max-w-[85%] sm:max-w-[75%] p-3 rounded-2xl shadow-sm text-xs leading-relaxed break-words font-medium <?= $isAdmin ? 'bg-emerald-600 text-white rounded-tr-none' : 'bg-white border border-slate-200/90 text-slate-800 rounded-tl-none' ?>">
                                <?= nl2br(htmlspecialchars($c['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Chat Input Footer Form -->
            <div class="p-3 sm:p-3.5 bg-white border-t border-slate-200/90 shrink-0">
                <form method="POST" class="flex gap-2">
                    <input type="text" name="message" required placeholder="Tulis balasan pesan / penawaran ke klien..." class="input-glow flex-1 px-3.5 py-2 rounded-xl border border-slate-200 text-xs bg-slate-50/80 focus:bg-white font-medium focus:outline-none">
                    <button type="submit" name="send_chat" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl transition shadow-md shadow-emerald-600/20 flex items-center gap-1.5 text-xs shrink-0">
                        <i class="fa-solid fa-paper-plane"></i> Kirim
                    </button>
                </form>
            </div>

        </div>

    </div>

</main>

<script>
    const chatBox = document.getElementById('chat-box');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
</script>

<?php 
require_once __DIR__ . '/../../includes/footer.php'; 
ob_end_flush();
?>