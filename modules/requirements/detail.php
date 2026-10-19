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

        header("Location: detail.php?id={$id}&open_chat=1#chat-box");
        exit;
    }
}

// Fetch All Chats
$stmtChats = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? ORDER BY id ASC");
$stmtChats->execute([$id]);
$chats = $stmtChats->fetchAll();

// =========================================================================
// LOGIKA HITUNG NOTIFIKASI PESAN BARU DARI KLIEN (PRESISI)
// =========================================================================
// Cari ID pesan terakhir yang dikirim oleh Admin
$last_admin_msg_id = 0;
foreach ($chats as $c) {
    if ($c['sender_type'] === 'admin') {
        $last_admin_msg_id = $c['id'];
    }
}

// Hitung hanya pesan Klien yang masuk SETELAH balasan Admin terakhir
$new_unread_count = 0;
foreach ($chats as $c) {
    if ($c['sender_type'] === 'client' && $c['id'] > $last_admin_msg_id) {
        $new_unread_count++;
    }
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
        <div class="flex items-center gap-3">
            <a href="index.php" class="btn-press w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0" title="Kembali">
                <i class="fa-solid fa-arrow-left text-xs"></i>
            </a>
            <div>
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="text-[9px] font-mono font-black uppercase tracking-wider text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 rounded-md">
                        REQ-#<?= str_pad($req['id'], 4, '0', STR_PAD_LEFT) ?>
                    </span>
                    <span class="text-[11px] font-bold text-slate-400">• <?= date('d M Y H:i', strtotime($req['created_at'])) ?></span>
                </div>
                <h1 class="text-lg font-black text-slate-900 tracking-tight leading-snug">
                    <?= htmlspecialchars($req['title'] ?? $req['judul_projek'] ?? 'Requirement Detail') ?>
                </h1>
            </div>
        </div>

        <!-- Single Clean Action Header -->
        <div class="flex items-center gap-2 w-full sm:w-auto justify-end shrink-0">
            <a href="../projects/index.php?convert_req=1&req_id=<?= $req['id'] ?>&client_name=<?= urlencode($req['client_name'] ?? $req['nama_klien'] ?? '') ?>&title=<?= urlencode($req['title'] ?? $req['judul_projek'] ?? '') ?>&budget=<?= urlencode($req['budget_estimation'] ?? $req['estimasi_budget'] ?? 0) ?>" 
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
        
        <!-- KOLOM KIRI (2 Cols): Detail Requirement & Deskripsi -->
        <div class="lg:col-span-2 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-2xs space-y-5 text-xs">
            
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-file-contract text-emerald-600"></i> Spesifikasi Proyek & Klien
                </h3>
                <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 tracking-wider">
                    STATUS: <?= htmlspecialchars($req['status']) ?>
                </span>
            </div>

            <!-- Identitas Klien -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50/80 p-4 rounded-xl border border-slate-200/60">
                <div>
                    <span class="text-slate-400 font-extrabold uppercase text-[9px] tracking-wider block mb-0.5">Penanggung Jawab Klien</span>
                    <p class="font-black text-slate-900 text-xs sm:text-sm"><?= htmlspecialchars($req['client_name'] ?? $req['nama_klien'] ?? '-') ?></p>
                    <p class="text-slate-500 font-semibold text-[11px] mt-0.5"><?= htmlspecialchars($req['client_email'] ?? $req['email'] ?? '-') ?></p>
                </div>

                <div>
                    <span class="text-slate-400 font-extrabold uppercase text-[9px] tracking-wider block mb-0.5">Perusahaan / Perorangan</span>
                    <p class="font-bold text-slate-800 text-xs"><?= htmlspecialchars($req['company_name'] ?? 'PT. / Individual') ?></p>
                    <p class="text-slate-500 font-semibold text-[11px] mt-0.5"><i class="fa-solid fa-phone text-slate-400 mr-1"></i><?= htmlspecialchars($req['client_phone'] ?? $req['nomor_telepon'] ?? '-') ?></p>
                </div>
            </div>

            <!-- Budget & File BRD -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-0.5">Alokasi Budget Proyek</span>
                    <p class="font-mono font-black text-emerald-700 text-sm">
                        Rp <?= number_format($req['budget_estimation'] ?? $req['estimasi_budget'] ?? 0, 0, ',', '.') ?>
                    </p>
                </div>

                <div class="bg-white p-3.5 rounded-xl border border-slate-200/80 shadow-2xs">
                    <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-0.5">Lampiran Dokumen BRD</span>
                    <?php if (!empty($req['file_attachment'])): ?>
                        <a href="../../uploads/requirements/<?= urlencode($req['file_attachment']) ?>" target="_blank" class="text-emerald-700 font-extrabold hover:underline flex items-center gap-1.5 text-xs mt-1 truncate">
                            <i class="fa-solid fa-file-pdf text-emerald-600"></i> <?= htmlspecialchars($req['file_attachment']) ?>
                        </a>
                    <?php else: ?>
                        <span class="text-slate-400 italic font-medium text-xs">Tidak ada lampiran dokumen</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Deskripsi Scope (Text Paling Atas) -->
            <div>
                <span class="text-[9px] font-extrabold uppercase text-slate-400 tracking-wider block mb-1.5">Uraian Kebutuhan & Ruang Lingkup Sistem</span>
                <div class="p-4 bg-slate-50 border border-slate-200/80 rounded-xl text-slate-800 text-xs leading-relaxed font-medium whitespace-pre-line flex items-start justify-start min-h-[90px] text-left">
                    <div class="w-full text-left self-start mt-0 pt-0">
                        <?= htmlspecialchars($req['description'] ?? $req['deskripsi_projek'] ?? 'Tidak ada deskripsi detail.') ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- KOLOM KANAN (1 Col): Update Status & Card Chat Negosiasi Bawah -->
        <div class="space-y-4">
            
            <!-- Update Status Card -->
            <div class="bg-white p-5 rounded-2xl border border-slate-200/90 shadow-2xs text-xs space-y-4">
                <h3 class="text-xs font-black text-slate-800 uppercase tracking-wider pb-3 border-b border-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-emerald-600"></i> Kelola Status Proyek
                </h3>

                <form method="POST" class="space-y-3">
                    <div>
                        <label class="block text-[9px] font-extrabold uppercase text-slate-400 tracking-wider mb-1.5">Status Pengajuan Saat Ini</label>
                        <select name="status" class="input-glow w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-800 bg-white focus:outline-none">
                            <option value="Pending" <?= $req['status'] === 'Pending' ? 'selected' : '' ?>>Pending (Belum Ditinjau)</option>
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

            <!-- CARD CHAT NEGOSIASI BAWAH (NOTIFIKASI HANYA MUNCUL JIKA ADA PESAN UNREAD DARI KLIEN) -->
            <div class="bg-slate-900 text-white p-5 rounded-2xl border border-slate-800 shadow-md space-y-3">
                <div class="flex items-center justify-between border-b border-slate-800 pb-2.5">
                    <h4 class="text-xs font-black uppercase tracking-wider text-white flex items-center gap-2">
                        <i class="fa-solid fa-comments text-emerald-400"></i> Diskusi & Negosiasi Klien
                    </h4>
                    
                    <!-- Badge Notifikasi Kedip & Loncat Hanya Jika Ada Pesan Baru ($new_unread_count > 0) -->
                    <?php if ($new_unread_count > 0): ?>
                        <span class="bg-red-600 text-white text-[9px] font-black px-2.5 py-1 rounded-full flex items-center gap-1.5 shadow-sm animate-pulse border border-red-400/40">
                            <i class="fa-solid fa-bell text-[9px] bell-shake text-amber-300"></i> +<?= $new_unread_count ?> Pesan Baru
                        </span>
                    <?php endif; ?>
                </div>

                <p class="text-slate-300 text-[11px] font-medium leading-relaxed">
                    Kirim pesan balasan, konsultasikan alur sistem, atau lampirkan kesepakatan penawaran dengan klien.
                </p>

                <button onclick="openChatModal()" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-black rounded-xl transition text-xs uppercase tracking-wider flex items-center justify-center gap-2 shadow-md shadow-emerald-600/30">
                    <i class="fa-solid fa-comments"></i> Buka Diskusi Negosiasi (<?= count($chats) ?>)
                </button>
            </div>

        </div>

    </div>

    <!-- MODAL POPUP CHAT -->
    <div id="chatModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-3 sm:p-6">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-3xl h-[85vh] flex flex-col overflow-hidden relative animate-in fade-in zoom-in-95 duration-200">
            
            <div class="p-4 sm:px-6 bg-slate-900 text-white flex items-center justify-between shrink-0 z-10">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-white/10 border border-white/20 p-1 flex items-center justify-center">
                        <img src="../../assets/images/logo.jpg" alt="Logo CTC" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-white">Lobi Negosiasi & Diskusi Scope</h3>
                        <p class="text-[10px] text-slate-300 font-medium">Klien: <strong class="text-emerald-400"><?= htmlspecialchars($req['client_name'] ?? $req['nama_klien'] ?? 'Klien') ?></strong></p>
                    </div>
                </div>

                <button onclick="closeChatModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="flex-1 relative overflow-hidden bg-slate-50/50">
                <div class="absolute inset-0 flex items-center justify-center pointer-events-none z-0">
                    <img src="../../assets/images/logo.jpg" alt="Watermark CTC Chat" class="w-[400px] max-w-[80vw] h-auto object-contain opacity-[0.08] mix-blend-multiply">
                </div>

                <div id="modal-chat-box" class="h-full p-4 sm:p-6 overflow-y-auto space-y-4 text-xs relative z-10">
                    <?php if (empty($chats)): ?>
                        <div class="text-center py-24 text-slate-400 font-semibold">
                            <i class="fa-solid fa-comments text-4xl mb-3 block text-slate-300"></i>
                            Belum ada riwayat obrolan dalam requirement ini.<br>
                            <span class="text-[11px] text-slate-400 font-normal">Kirimkan balasan pesan / penawaran pertama Anda di bawah ini.</span>
                        </div>
                    <?php else: ?>
                        <?php foreach ($chats as $c): ?>
                            <?php $isAdmin = ($c['sender_type'] === 'admin'); ?>
                            <div class="flex flex-col <?= $isAdmin ? 'items-end' : 'items-start' ?> w-full">
                                <div class="text-[10px] text-slate-500 font-extrabold mb-1 px-1 flex items-center gap-1.5">
                                    <span class="<?= $isAdmin ? 'text-emerald-700 font-bold' : 'text-slate-800' ?>"><?= htmlspecialchars($c['sender_name']) ?></span>
                                    <span>•</span>
                                    <span class="font-normal text-slate-400"><?= date('d M H:i', strtotime($c['created_at'])) ?></span>
                                </div>
                                <div class="max-w-[85%] sm:max-w-[75%] p-3.5 sm:p-4 rounded-2xl text-xs leading-relaxed break-words font-medium shadow-2xs <?= $isAdmin ? 'bg-emerald-600 text-white rounded-tr-none' : 'bg-white text-slate-800 rounded-tl-none border border-slate-200' ?>">
                                    <?= nl2br(htmlspecialchars($c['message'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" class="p-3.5 sm:p-4 bg-white border-t border-slate-200 flex gap-2 shrink-0 z-10">
                <input type="text" name="message" required placeholder="Tulis balasan pesan / penawaran ke klien..." class="input-glow flex-1 px-4 py-3 rounded-2xl border border-slate-300 bg-slate-50 text-xs text-slate-900 focus:bg-white focus:outline-none font-semibold transition-all placeholder:font-normal">
                <button type="submit" name="send_chat" class="btn-press px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-2xl text-xs transition shadow-md shadow-emerald-600/20 flex items-center gap-2 shrink-0 uppercase tracking-wider">
                    <i class="fa-solid fa-paper-plane"></i> Kirim
                </button>
            </form>

        </div>
    </div>

</main>

<script>
    function openChatModal() {
        const modal = document.getElementById('chatModal');
        modal.classList.remove('hidden');
        const modalChatBox = document.getElementById('modal-chat-box');
        if (modalChatBox) modalChatBox.scrollTop = modalChatBox.scrollHeight;
    }

    function closeChatModal() {
        const modal = document.getElementById('chatModal');
        modal.classList.add('hidden');
    }

    document.addEventListener('DOMContentLoaded', function() {
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