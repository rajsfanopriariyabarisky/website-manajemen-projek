<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login & Role Guard Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../my_tasks.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: index.php");
    exit;
}

$current_user_nama = $_SESSION['nama'] ?? 'System Admin';
$current_user_id   = $_SESSION['user_id'] ?? null;

// Helper Log Aktivitas
function logProjectActivity($pdo, $project_id, $user_id, $nama_user, $aktivitas, $tipe = 'info') {
    $stmt = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$project_id, $user_id, $nama_user, $aktivitas, $tipe]);
}

// 1. Handle Update Data Proyek & SINKRONISASI BIDIREKSIONAL KE KEBUTUHAN KLIEN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    $nama_projek  = trim($_POST['nama_projek']);
    $klien        = trim($_POST['klien']);
    $budget       = $_POST['budget'] ?? 0;
    $status       = $_POST['status'];
    $deadline     = $_POST['deadline'];
    $kategori_rab = $_POST['kategori_rab'] ?? 'Website';

    // Olah Custom RAB jika kategori RAB = Custom
    $rab_custom_json = null;
    if ($kategori_rab === 'Custom' && isset($_POST['custom_komponen'])) {
        $custom_items = [];
        $komponens  = $_POST['custom_komponen'];
        $deskripsis = $_POST['custom_deskripsi'];
        $persens    = $_POST['custom_persen'];

        for ($i = 0; $i < count($komponens); $i++) {
            if (!empty(trim($komponens[$i]))) {
                $pVal = (float)($persens[$i] ?? 0);
                $custom_items[] = [
                    'komponen'  => trim($komponens[$i]),
                    'deskripsi' => trim($deskripsis[$i] ?? ''),
                    'persen'    => $pVal,
                    'biaya'     => round(($pVal / 100) * $budget)
                ];
            }
        }
        $rab_custom_json = json_encode($custom_items);
    }

    if (!empty($nama_projek) && !empty($klien)) {
        $stmtUp = $pdo->prepare("UPDATE projects SET nama_projek = ?, klien = ?, budget = ?, status = ?, deadline = ?, kategori_rab = ?, rab_custom_data = ? WHERE id = ?");
        $stmtUp->execute([$nama_projek, $klien, $budget, $status, $deadline, $kategori_rab, $rab_custom_json, $id]);

        // Cek jika proyek terhubung ke Kebutuhan Klien (requirement_id)
        $stmtGetReq = $pdo->prepare("SELECT requirement_id FROM projects WHERE id = ?");
        $stmtGetReq->execute([$id]);
        $reqId = $stmtGetReq->fetchColumn();

        if ($reqId) {
            if ($status === 'In Progress' || $status === 'Completed') {
                $pdo->prepare("UPDATE client_requirements SET status = 'Deal / Accepted' WHERE id = ?")->execute([$reqId]);
            } elseif ($status === 'On Hold') {
                $pdo->prepare("UPDATE client_requirements SET status = 'Proposal Sent' WHERE id = ?")->execute([$reqId]);
            }
        }

        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Memperbarui konfigurasi proyek, status '$status' & Kategori RAB '$kategori_rab'", 'info');

        header("Location: detail.php?id=" . $id . "&msg=project_updated");
        exit;
    }
}

// Fetch Detail Proyek & Kontrak Terkait
$stmt = $pdo->prepare("
    SELECT p.*, c.id AS linked_contract_id 
    FROM projects p 
    LEFT JOIN contracts c ON c.project_id = p.id 
    WHERE p.id = ?
");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

// 2. Handle Tambah Task Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $nama_tugas     = trim($_POST['nama_tugas']);
    $assigned_users = $_POST['user_ids'] ?? [];
    $prioritas      = $_POST['prioritas'];
    $deadline       = $_POST['deadline'];

    if (!empty($nama_tugas) && !empty($assigned_users)) {
        $primary_user_id = $assigned_users[0];

        $stmtTask = $pdo->prepare("INSERT INTO tasks (project_id, user_id, assigned_to, nama_tugas, prioritas, status, deadline) VALUES (?, ?, ?, ?, ?, 'To Do', ?)");
        $stmtTask->execute([$id, $primary_user_id, $primary_user_id, $nama_tugas, $prioritas, $deadline]);
        $new_task_id = $pdo->lastInsertId();

        $stmtAssign = $pdo->prepare("INSERT INTO task_assignees (task_id, user_id) VALUES (?, ?)");
        $names = [];
        foreach ($assigned_users as $u_id) {
            $stmtAssign->execute([$new_task_id, $u_id]);

            $stmtU = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
            $stmtU->execute([$u_id]);
            $uName = $stmtU->fetchColumn();
            if ($uName) $names[] = $uName;
        }

        $assignedStr = implode(', ', $names);
        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Mengalokasikan modul baru '$nama_tugas' ke Tim: ($assignedStr)", 'task');

        header("Location: detail.php?id=" . $id . "&msg=task_added");
        exit;
    }
}

// 3. Handle QC Approval & Revisi oleh PM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qc_action'])) {
    $task_id        = $_POST['task_id'];
    $action         = $_POST['qc_action'];
    $revision_notes = trim($_POST['revision_notes'] ?? '');

    $stmtT = $pdo->prepare("SELECT nama_tugas FROM tasks WHERE id = ?");
    $stmtT->execute([$task_id]);
    $tData = $stmtT->fetch();
    $taskTitle = $tData['nama_tugas'] ?? 'Modul';

    if ($action === 'approve') {
        $stmtApprove = $pdo->prepare("UPDATE tasks SET status = 'Done', revision_notes = NULL WHERE id = ?");
        $stmtApprove->execute([$task_id]);

        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Menyetujui (Approved) pengerjaan modul '$taskTitle'", 'success');

        // Notif Auto ke Chatbox (bubble hijau khusus, tampil sama di dua sisi)
        $pdo->prepare("INSERT INTO task_chats (task_id, sender_id, sender_name, sender_role, message) VALUES (?, ?, ?, 'admin', '✅ Modul ini telah disetujui (Approved) oleh Project Manager.')")->execute([$task_id, $current_user_id, $current_user_nama]);

        header("Location: detail.php?id=" . $id . "&msg=approved");
        exit;
    } elseif ($action === 'revision') {
        $stmtRev = $pdo->prepare("UPDATE tasks SET status = 'In Progress', revision_notes = ? WHERE id = ?");
        $stmtRev->execute([$revision_notes, $task_id]);

        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Mengirimkan catatan instruksi revisi pada modul '$taskTitle'", 'warning');

        // Notif Auto ke Chatbox (bubble AMBER khusus di dua sisi -> beda dari chat biasa)
        $pesanRevisi = "⚠️ *Instruksi Revisi dari PM:*\n" . $revision_notes;
        $pdo->prepare("INSERT INTO task_chats (task_id, sender_id, sender_name, sender_role, message) VALUES (?, ?, ?, 'admin', ?)")->execute([$task_id, $current_user_id, $current_user_nama, $pesanRevisi]);

        header("Location: detail.php?id=" . $id . "&msg=revision_sent");
        exit;
    }
}

// Fetch Karyawan & Jabatan Unik
$users = $pdo->query("SELECT * FROM users ORDER BY nama ASC")->fetchAll();
$jabatans = $pdo->query("SELECT DISTINCT jabatan FROM users WHERE jabatan IS NOT NULL AND jabatan != '' ORDER BY jabatan ASC")->fetchAll(PDO::FETCH_COLUMN);

// Fetch List Tugas
$stmtTasks = $pdo->prepare("
    SELECT t.*, u_updater.nama AS updater_nama
    FROM tasks t
    LEFT JOIN users u_updater ON t.updated_by_user_id = u_updater.id
    WHERE t.project_id = ?
    ORDER BY t.deadline ASC
");
$stmtTasks->execute([$id]);
$allTasks = $stmtTasks->fetchAll();

// Map Assignees per Task
$taskAssigneesMap = [];
$stmtAllAssignees = $pdo->prepare("
    SELECT ta.task_id, u.id AS user_id, u.nama, u.jabatan 
    FROM task_assignees ta
    JOIN users u ON ta.user_id = u.id
    JOIN tasks t ON ta.task_id = t.id
    WHERE t.project_id = ?
");
$stmtAllAssignees->execute([$id]);
$rawAssignees = $stmtAllAssignees->fetchAll();

foreach ($rawAssignees as $ra) {
    $taskAssigneesMap[$ra['task_id']][] = $ra;
}

// Mapping File Deliverables (FITUR BARU UNTUK BACA UPLOAD FILE KARYAWAN)
$taskFilesMap = [];
$stmtFiles = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? AND task_id IS NOT NULL");
$stmtFiles->execute([$id]);
$rawFiles = $stmtFiles->fetchAll();
foreach ($rawFiles as $rf) {
    $taskFilesMap[$rf['task_id']][] = $rf;
}

// Mapping Notifikasi Unread Chats per Task
$unreadChatsMap = [];
$stmtUnread = $pdo->query("SELECT task_id, COUNT(*) as unread_count FROM task_chats WHERE is_read = 0 AND sender_role = 'karyawan' GROUP BY task_id");
$rawUnread = $stmtUnread->fetchAll();
foreach ($rawUnread as $ru) {
    $unreadChatsMap[$ru['task_id']] = $ru['unread_count'];
}

// Nama modul untuk judul chatbox (anti error quote & XSS)
$taskNameMap = [];
foreach ($allTasks as $tk) {
    $taskNameMap[(int) $tk['id']] = $tk['nama_tugas'];
}

// Tanggal Acuan
$todayDate      = date('Y-m-d');
$todayTimestamp = strtotime($todayDate);

$totalTasks       = count($allTasks);
$completedTasks   = 0;
$inProgressTasks  = 0;
$readyReviewTasks = 0;
$todoTasks        = 0;

foreach ($allTasks as $tk) {
    $st = strtolower(trim($tk['status']));
    if ($st === 'done') {
        $completedTasks++;
    } elseif ($st === 'ready for review') {
        $readyReviewTasks++;
    } elseif ($st === 'in progress') {
        $inProgressTasks++;
    } else {
        $todoTasks++;
    }
}

$progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

$existingCustomRab = [];
if (!empty($project['rab_custom_data'])) {
    $existingCustomRab = json_decode($project['rab_custom_data'], true) ?? [];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6, textarea { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    .overdue-row { background-color: rgba(254, 242, 242, 0.75) !important; }
    .overdue-row:hover { background-color: rgba(254, 226, 226, 0.95) !important; }

    /* =====================================================================
     * CHAT BUBBLE — WARNA KONSISTEN ADMIN & KARYAWAN
     * ===================================================================== */
    .chat-bubble { max-width: 85%; padding: 9px 13px; border-radius: 14px; font-size: 13px; line-height: 1.5; word-wrap: break-word; }
    .chat-bubble-self   { background-color: #059669; color: #ffffff; border-bottom-right-radius: 3px; }
    .chat-bubble-other  { background-color: #ffffff; color: #1e293b; border: 1px solid #e2e8f0; border-bottom-left-radius: 3px; }
    /* Pesan revisi (prefix ⚠️) -> AMBER, beda dari chat biasa, sama di dua sisi */
    .chat-bubble-revision { background-color: #fef3c7 !important; color: #92400e !important; border: 1px solid #f59e0b !important; border-bottom-left-radius: 3px; }
    /* Pesan approval sistem (prefix ✅) -> hijau muda */
    .chat-bubble-success  { background-color: #d1fae5 !important; color: #065f46 !important; border: 1px solid #34d399 !important; border-bottom-left-radius: 3px; }

    /* Animasi pop-in ala WhatsApp */
    @keyframes chatPop {
        from { opacity: 0; transform: translateY(12px) scale(0.8); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    .chat-pop { animation: chatPop 0.28s cubic-bezier(0.16, 1, 0.3, 1) both; }

    /* Toast notifikasi */
    @keyframes toastIn  { from { opacity: 0; transform: translateX(24px); } to { opacity: 1; transform: none; } }
    @keyframes toastOut { to   { opacity: 0; transform: translateX(24px); } }
    .toast-pop  { animation: toastIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) both; }
    .toast-leave { animation: toastOut 0.3s ease both; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">

    <!-- Top Navigation Bar -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="index.php" class="btn-press text-xs font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-2 bg-white hover:bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200/90 transition shadow-2xs">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Portfolio Proyek
        </a>
        <span class="text-xs font-mono font-semibold px-3 py-1.5 rounded-xl bg-white border border-slate-200/90 text-slate-600 shadow-2xs flex items-center gap-1.5">
            <i class="fa-solid fa-hashtag text-emerald-600"></i> PROJ-<?= sprintf("%03d", $project['id']) ?>
        </span>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'project_updated'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> Konfigurasi & status proyek berhasil diperbarui! Sinkronisasi status kebutuhan klien di-update secara otomatis.
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'task_added'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> Alokasi modul baru berhasil didistribusikan ke tim terkait!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'approved'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> Modul disetujui (Approved)! Status otomatis diperbarui ke Selesai (Done).
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'revision_sent'): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base"></i> Catatan instruksi revisi telah berhasil dikirimkan ke tim teknis.
        </div>
    <?php endif; ?>

    <!-- Banner Header Proyek & Executive Summary -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs mb-6 relative overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6">

            <div class="space-y-3 flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-1 rounded-lg text-[10px] uppercase font-semibold border tracking-wider
                        <?php
                            switch($project['status']) {
                                case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                case 'On Hold': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                            }
                        ?>">
                        <i class="fa-solid fa-circle text-[6px] mr-1.5 align-middle"></i><?= htmlspecialchars($project['status']) ?>
                    </span>

                    <span class="px-2.5 py-1 rounded-lg text-[10px] uppercase font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 tracking-wider">
                        RAB: <?= htmlspecialchars($project['kategori_rab'] ?? 'Website') ?>
                    </span>

                    <span class="text-xs text-slate-500 font-normal flex items-center gap-1.5 bg-slate-50 px-2.5 py-1 rounded-lg border border-slate-200/80">
                        <i class="fa-regular fa-calendar-check text-emerald-600"></i> Target Deadline: <strong class="text-slate-800 font-semibold"><?= date('d M Y', strtotime($project['deadline'])) ?></strong>
                    </span>
                </div>

                <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight leading-tight truncate"><?= htmlspecialchars($project['nama_projek']) ?></h1>

                <div class="text-xs sm:text-sm text-slate-500 flex items-center gap-3 flex-wrap font-normal">
                    <span>Mitra Klien: <strong class="text-slate-800 font-semibold"><?= htmlspecialchars($project['klien']) ?></strong></span>
                    <span class="text-slate-300">•</span>
                    <span>Nilai Kontrak Workload: <strong class="text-emerald-700 font-bold font-mono text-sm">Rp <?= number_format($project['budget'], 0, ',', '.') ?></strong></span>
                </div>
            </div>

            <!-- Visual Donut Chart Summary -->
            <div class="flex items-center gap-4 bg-slate-50/90 p-3.5 rounded-xl border border-slate-200/80 shrink-0">
                <div class="w-20 h-20 relative">
                    <canvas id="projectProgressChart"></canvas>
                </div>
                <div class="text-xs space-y-1">
                    <span class="text-[10px] uppercase font-semibold tracking-wider text-slate-400 block mb-0.5">Status Modul</span>
                    <p class="font-semibold text-emerald-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-emerald-500"></i> <?= $completedTasks ?> Done</p>
                    <p class="font-semibold text-purple-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-purple-500"></i> <?= $readyReviewTasks ?> Siap QC</p>
                    <p class="font-semibold text-blue-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-blue-500"></i> <?= $inProgressTasks ?> In Progress</p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 flex-wrap">
                <button onclick="openEditProjectModal()" class="btn-press px-3.5 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300/80 rounded-xl text-xs font-semibold transition shadow-2xs flex items-center justify-center gap-2">
                    <i class="fa-regular fa-pen-to-square"></i> Konfigurasi
                </button>
                <a href="../proposals/print_proposal.php?project_id=<?= $id ?>" target="_blank" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-2">
                    <i class="fa-solid fa-file-signature"></i> Proposal SPH
                </a>
                <?php if (!empty($project['linked_contract_id'])): ?>
                    <a href="../contracts/print_pdf.php?id=<?= $project['linked_contract_id'] ?>" target="_blank" class="btn-press px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-file-contract"></i> Kontrak SPK
                    </a>
                <?php else: ?>
                    <a href="../contracts/generate.php?project_id=<?= $id ?>" class="btn-press px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus text-[10px]"></i> SPK Baru
                    </a>
                <?php endif; ?>
                <a href="print_report.php?id=<?= $id ?>" target="_blank" class="btn-press px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-semibold transition shadow-xs flex items-center justify-center gap-2">
                    <i class="fa-solid fa-print"></i> Laporan PDF
                </a>
            </div>
        </div>

        <div class="pt-5 mt-5 border-t border-slate-100">
            <div class="flex justify-between text-xs font-semibold mb-1.5">
                <span class="text-slate-500 uppercase tracking-wider text-[10px]">Progress Completion Milestone</span>
                <span class="text-emerald-700 font-bold"><?= $progressPercent ?>% Completed (<?= $completedTasks ?>/<?= $totalTasks ?> Modul Selesai)</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden border border-slate-200/80 p-0.5">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-1.5 rounded-full transition-all duration-700" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Grid Layout Utama -->
    <div class="flex flex-col xl:flex-row gap-6 w-full min-w-0">

        <!-- SEKSI KIRI: FORM ALOKASIKAN MODUL BARU -->
        <div class="w-full xl:w-80 shrink-0">
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs sticky top-6">
                <h3 class="font-bold text-slate-800 text-xs sm:text-sm uppercase tracking-wider mb-4 flex items-center gap-2 pb-3 border-b border-slate-100">
                    <i class="fa-solid fa-users-gear text-emerald-600"></i> Alokasikan Modul Baru
                </h3>

                <form action="" method="POST" class="space-y-3.5 text-xs sm:text-sm">
                    <input type="hidden" name="add_task" value="1">

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Modul / Fitur <span class="text-red-500">*</span></label>
                        <input type="text" name="nama_tugas" required placeholder="Contoh: Development Core Engine" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-700 mb-1.5">Pilih Tim Pelaksana <span class="text-red-500">*</span></label>

                        <div class="space-y-1.5 mb-2">
                            <div class="relative">
                                <input type="text" id="userSearchInput" onkeyup="filterUserList()" placeholder="Cari nama karyawan..." class="input-glow w-full bg-white border border-slate-300 rounded-lg px-3 py-1.5 pl-8 text-xs font-normal focus:outline-none placeholder:text-slate-400">
                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[10px]"></i>
                            </div>

                            <select id="userRoleFilter" onchange="filterUserList()" class="input-glow w-full bg-white border border-slate-300 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:outline-none">
                                <option value="all">Semua Divisi / Jabatan</option>
                                <?php foreach ($jabatans as $jb): ?>
                                    <option value="<?= htmlspecialchars(strtolower($jb)) ?>"><?= htmlspecialchars($jb) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="max-h-44 overflow-y-auto border border-slate-200 rounded-xl p-2 space-y-1 bg-slate-50/50" id="userListContainer">
                            <?php foreach ($users as $u): ?>
                                <?php 
                                    $userJabatanLower = strtolower($u['jabatan'] ?? 'staff');
                                    $userNamaLower    = strtolower($u['nama']);
                                ?>
                                <label class="user-item flex items-center gap-2 text-xs text-slate-700 font-medium cursor-pointer hover:bg-white p-1.5 rounded-lg transition" 
                                       data-name="<?= htmlspecialchars($userNamaLower) ?>" 
                                       data-role="<?= htmlspecialchars($userJabatanLower) ?>">
                                    <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 shrink-0">
                                    <span class="truncate">
                                        <?= htmlspecialchars($u['nama']) ?> 
                                        <span class="text-[10px] text-slate-400 font-normal block truncate">(<?= htmlspecialchars($u['jabatan'] ?? 'Staff') ?>)</span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Prioritas</label>
                            <select name="prioritas" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-semibold text-slate-800 focus:outline-none">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-700 mb-1.5">Deadline</label>
                            <input type="date" name="deadline" required value="<?= date('Y-m-d') ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2.5 text-xs font-semibold text-slate-900 focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-xl transition shadow-xs flex items-center justify-center gap-2 mt-2">
                        <i class="fa-solid fa-plus text-xs"></i> Pendistribusikan Modul
                    </button>
                </form>
            </div>
        </div>

        <!-- SEKSI KANAN: TABEL MANAJEMEN MODUL & QC UTAMA -->
        <div class="flex-1 min-w-0">
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-xs">

                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 mb-1 border-b border-slate-100">
                    <div class="min-w-0">
                        <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-emerald-600"></i> Manajemen Modul & QC
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5 font-normal">Monitoring progress dan verifikasi hasil pengerjaan tim</p>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap w-full sm:w-auto">
                        <div class="relative w-full sm:w-48">
                            <input type="text" id="taskSearchInput" onkeyup="filterTasks()" placeholder="Cari modul / tim..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-300 text-xs focus:outline-none font-normal placeholder:text-slate-400">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>

                        <select id="statusFilterSelect" onchange="filterTasks()" class="input-glow px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                            <option value="all">Semua Status</option>
                            <option value="To Do">To Do</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Ready for Review">Ready for Review (Siap QC)</option>
                            <option value="Done">Done (Selesai)</option>
                        </select>
                    </div>
                </div>

                <div class="w-full overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs sm:text-sm min-w-[760px]" id="tasksTable">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-semibold text-slate-400 uppercase tracking-wider">
                                <th class="py-3 px-3.5">Modul & Diskusi</th>
                                <th class="py-3 px-3.5 text-center w-24">Prioritas</th>
                                <th class="py-3 px-3.5 w-44">Tim Pelaksana</th>
                                <th class="py-3 px-3.5 text-center w-28">Status</th>
                                <th class="py-3 px-3.5 text-center w-40">Deliverables</th>
                                <th class="py-3 px-3.5 text-center w-36">Verifikasi PM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="tasksTableBody">
                            <?php if (empty($allTasks)): ?>
                                <tr>
                                    <td colspan="6" class="p-10 text-center">
                                        <div class="w-11 h-11 mx-auto mb-2.5 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-base">
                                            <i class="fa-solid fa-inbox"></i>
                                        </div>
                                        <p class="text-slate-400 text-xs font-medium">Belum ada modul pengerjaan yang dialokasikan untuk proyek ini.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allTasks as $t): ?>
                                    <?php 
                                        $stLower = strtolower(trim($t['status']));
                                        $prioLower = strtolower(trim($t['prioritas'] ?? 'medium'));
                                        $assignees = $taskAssigneesMap[$t['id']] ?? [];
                                        $unread = $unreadChatsMap[$t['id']] ?? 0;

                                        $taskDeadlineTS = strtotime($t['deadline']);
                                        $isOverdue = ($taskDeadlineTS < $todayTimestamp) && ($stLower !== 'done');

                                        $diffDays = 0;
                                        if ($isOverdue) {
                                            $diffDays = max(1, floor(($todayTimestamp - $taskDeadlineTS) / (60 * 60 * 24)));
                                        }

                                        $prioBadge = match($prioLower) {
                                            'urgent' => 'bg-red-50 text-red-700 border-red-200',
                                            'high'   => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'medium' => 'bg-blue-50 text-blue-700 border-blue-200',
                                            default  => 'bg-slate-100 text-slate-600 border-slate-200',
                                        };
                                        $statusBadge = match($stLower) {
                                            'done'               => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'ready for review'   => 'bg-purple-50 text-purple-700 border-purple-200',
                                            'in progress'        => 'bg-blue-50 text-blue-700 border-blue-200',
                                            default              => 'bg-slate-100 text-slate-600 border-slate-200',
                                        };
                                    ?>
                                    <tr class="task-row hover:bg-slate-50/70 transition align-middle <?= $isOverdue ? 'overdue-row' : '' ?>" data-status="<?= htmlspecialchars($t['status']) ?>">

                                        <!-- KOLOM 1: MODUL & DISKUSI -->
                                        <td class="py-3 px-3.5">
                                            <div class="font-semibold text-slate-900 text-[13px] task-title flex items-center gap-1.5 leading-snug">
                                                <?php if ($isOverdue): ?>
                                                    <i class="fa-solid fa-bell text-red-500 text-xs shrink-0" title="PRIORITAS! Modul ini telah melewati deadline."></i>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($t['nama_tugas']) ?></span>
                                            </div>

                                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                                <span class="text-[11px] font-normal inline-flex items-center gap-1 shrink-0 <?= $isOverdue ? 'text-red-600 font-semibold' : 'text-slate-400' ?>">
                                                    <i class="fa-regular fa-calendar-xmark"></i>
                                                    <?= date('d M Y', strtotime($t['deadline'])) ?>
                                                </span>

                                                <?php if ($isOverdue): ?>
                                                    <span class="px-1.5 py-0.5 rounded-md text-[9px] bg-red-600 text-white font-semibold uppercase tracking-wider shadow-2xs flex items-center gap-1 shrink-0">
                                                        Terlambat <?= $diffDays ?> Hari
                                                    </span>
                                                <?php endif; ?>

                                                <button onclick="openChatModal(<?= (int) $t['id'] ?>)" 
                                                    class="relative btn-press px-2 py-1 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-semibold text-[10px] inline-flex items-center gap-1.5 shadow-2xs shrink-0" title="Buka Chatbox Modul">
                                                    <i class="fa-solid fa-comments text-blue-600"></i> Chatbox
                                                    <span id="unreadBadge-<?= (int) $t['id'] ?>" class="unread-badge absolute -top-2 -right-2 bg-red-500 text-white rounded-full text-[9px] font-bold h-4 min-w-4 px-0.5 flex items-center justify-center shadow-sm border-2 border-white <?= $unread > 0 ? '' : 'hidden' ?>"><?= $unread ?></span>
                                                </button>
                                            </div>
                                        </td>

                                        <!-- KOLOM 2: PRIORITAS -->
                                        <td class="py-3 px-3.5 text-center">
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-semibold uppercase inline-block border tracking-wider whitespace-nowrap <?= $prioBadge ?>">
                                                <?= htmlspecialchars($t['prioritas'] ?? 'Medium') ?>
                                            </span>
                                        </td>

                                        <!-- KOLOM 3: TIM PELAKSANA -->
                                        <td class="py-3 px-3.5">
                                            <div class="flex flex-wrap gap-1 task-assignees">
                                                <?php foreach ($assignees as $asg): ?>
                                                    <span class="px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/80 font-medium text-[11px] inline-flex items-center gap-1 shadow-2xs">
                                                        <i class="fa-solid fa-user text-[9px] text-emerald-600"></i> <?= htmlspecialchars($asg['nama']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>

                                        <!-- KOLOM 4: STATUS -->
                                        <td class="py-3 px-3.5 text-center">
                                            <span class="px-2 py-1 rounded-lg text-[10px] font-semibold uppercase inline-block border tracking-wider whitespace-nowrap <?= $statusBadge ?>">
                                                <?= htmlspecialchars($t['status']) ?>
                                            </span>
                                        </td>

                                        <!-- KOLOM 5: DELIVERABLES -->
                                        <td class="py-3 px-3.5">
                                            <div class="flex flex-col gap-1 items-stretch w-36 mx-auto">
                                                <?php if (!empty($t['drive_link'])): ?>
                                                    <a href="<?= htmlspecialchars($t['drive_link']) ?>" target="_blank" class="btn-press px-2 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-[11px] font-semibold border border-emerald-200 transition inline-flex items-center justify-center gap-1.5 shadow-2xs whitespace-nowrap">
                                                        <i class="fa-brands fa-google-drive text-emerald-600"></i> Google Drive
                                                    </a>
                                                <?php endif; ?>

                                                <?php if (!empty($taskFilesMap[$t['id']])): ?>
                                                    <?php foreach ($taskFilesMap[$t['id']] as $file): ?>
                                                        <a href="../../uploads/<?= htmlspecialchars($file['file_path']) ?>" download class="btn-press px-2 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 rounded-lg text-[11px] font-semibold border border-blue-200 transition inline-flex items-center justify-center gap-1.5 shadow-2xs whitespace-nowrap" title="<?= htmlspecialchars($file['nama_file']) ?>">
                                                            <i class="fa-solid fa-file-arrow-down text-blue-600"></i> Unduh File
                                                        </a>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>

                                                <?php if (empty($t['drive_link']) && empty($taskFilesMap[$t['id']])): ?>
                                                    <span class="text-slate-300 text-[11px] font-normal italic text-center whitespace-nowrap">Belum ada</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <!-- KOLOM 6: VERIFIKASI PM -->
                                        <td class="py-3 px-3.5">
                                            <?php if ($stLower === 'done'): ?>
                                                <div class="flex items-center justify-center gap-1.5 text-emerald-600 font-semibold text-[11px] whitespace-nowrap">
                                                    <i class="fa-solid fa-circle-check"></i> Approved
                                                </div>
                                            <?php else: ?>
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <form action="" method="POST" onsubmit="return confirm('Setujui pengerjaan modul ini?')">
                                                        <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                                                        <input type="hidden" name="qc_action" value="approve">
                                                        <button type="submit" class="btn-press px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[11px] font-semibold shadow-xs transition inline-flex items-center gap-1 whitespace-nowrap">
                                                            <i class="fa-solid fa-check text-[9px]"></i> Approve
                                                        </button>
                                                    </form>
                                                    <button type="button" onclick="openRevisionModal('<?= (int) $t['id'] ?>', '<?= htmlspecialchars(addslashes($t['nama_tugas'])) ?>')" class="btn-press px-2.5 py-1.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[11px] font-semibold shadow-xs transition inline-flex items-center gap-1 whitespace-nowrap">
                                                        <i class="fa-solid fa-pen text-[9px]"></i> Revisi
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500 bg-slate-50/50 mt-4">
                    <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
                    <div class="flex items-center gap-1.5" id="paginationControls"></div>
                </div>

            </div>
        </div>

    </div>
</main>

<!-- ========================================== -->
<!-- MODAL CHATBOX REAL-TIME -->
<!-- ========================================== -->
<div id="chatModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-3 sm:p-4">
    <div class="bg-white rounded-2xl w-full max-w-lg h-[80vh] max-h-[640px] flex flex-col shadow-2xl overflow-hidden relative">

        <!-- Chat Header -->
        <div class="bg-emerald-600 px-4 sm:px-5 py-3.5 flex justify-between items-center shrink-0 shadow-sm z-10">
            <div class="min-w-0">
                <h3 class="font-bold text-white text-sm flex items-center gap-2 truncate">
                    <i class="fa-regular fa-message shrink-0"></i>
                    <span class="truncate">Chat: <span id="chatTaskName" class="text-emerald-100"></span></span>
                </h3>
                <p class="text-emerald-200 text-[10px] mt-0.5"><i class="fa-solid fa-circle text-[8px] text-emerald-400"></i> Real-time aktif • tersinkron dengan tim</p>
            </div>
            <button type="button" onclick="openDeleteConfirm()" id="chatDeleteBtn" title="Hapus seluruh riwayat chat modul ini (bersihkan database)" class="btn-press shrink-0 mr-1.5 px-3 py-1.5 rounded-xl bg-white/15 hover:bg-red-500 text-white text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5 transition focus:outline-none">
                <i class="fa-solid fa-trash-can text-[10px]"></i><span class="hidden sm:inline">Hapus Chat</span>
            </button>
            <button onclick="closeChatModal()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 text-white flex items-center justify-center transition focus:outline-none shrink-0">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- Chat Body / Messages Area -->
        <div class="relative flex-1 min-h-0 bg-[#eef2f5]">
            <div class="absolute inset-0 flex items-center justify-center pointer-events-none overflow-hidden">
                <img src="../../assets/images/logo2.png" alt="CTC Watermark" class="w-[280px] h-auto object-contain opacity-[0.05] mix-blend-multiply select-none" onerror="this.onerror=null; this.src='../../assets/img/logo2.png';">
            </div>
            <div id="chatBody" class="absolute inset-0 z-10 overflow-y-auto p-4 sm:p-5 flex flex-col gap-3"></div>
            <button id="jumpLatestBtn" onclick="scrollChatBottom(true)" class="hidden absolute bottom-3 right-3 z-10 pl-3 pr-2 py-1.5 rounded-full bg-white shadow-lg border border-slate-200 text-[11px] font-semibold text-slate-700 items-center gap-1.5 btn-press">
                <i class="fa-solid fa-arrow-down text-emerald-600 text-[10px]"></i> Pesan baru
            </button>
        </div>

        <!-- Chat Input Footer -->
        <div class="p-3 bg-white border-t border-slate-200 shrink-0">
            <form id="chatForm" onsubmit="submitTaskChat(event)" class="flex gap-2 items-center">
                <input type="hidden" id="chatTaskId">
                <input type="text" id="chatInputMessage" placeholder="Ketik pesan untuk tim..." required autocomplete="off" class="flex-1 bg-slate-100 border border-slate-200 rounded-full px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:bg-white transition min-w-0">
                <button type="submit" id="chatSendBtn" class="w-10 h-10 rounded-full bg-emerald-600 hover:bg-emerald-700 text-white flex items-center justify-center shrink-0 transition btn-press shadow-md disabled:opacity-50">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KONFIRMASI HAPUS CHAT (CUSTOM — ADMIN ONLY) -->
<div id="deleteChatConfirmModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-xs p-5 sm:p-6 shadow-2xl relative animate-fade-in text-center border border-slate-200">
        <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-red-50 border border-red-100 text-red-500 flex items-center justify-center text-lg">
            <i class="fa-solid fa-trash-can"></i>
        </div>
        <h3 class="font-bold text-slate-900 text-sm mb-1">Hapus Riwayat Chat?</h3>
        <p class="text-xs text-slate-500 font-normal mb-4 leading-relaxed">Seluruh pesan pada modul ini akan dihapus <strong class="text-slate-700 font-semibold">permanen</strong> dari database. Tindakan ini tidak dapat dibatalkan.</p>
        <div class="flex gap-2">
            <button type="button" onclick="closeDeleteConfirm()" class="btn-press flex-1 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition">Batal</button>
            <button type="button" onclick="performDeleteChat()" id="deleteChatConfirmBtn" class="btn-press flex-1 py-2 bg-red-500 hover:bg-red-600 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center justify-center gap-1.5 transition">
                <i class="fa-solid fa-trash-can text-[10px]"></i> Ya, Hapus
            </button>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastWrap" class="fixed bottom-4 right-4 z-[100] flex flex-col gap-2 w-72 max-w-[calc(100vw-2rem)] pointer-events-none"></div>

<!-- MODAL EDIT DETAIL PROYEK & KATEGORI RAB -->
<div id="editProjectModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-regular fa-pen-to-square text-amber-500"></i> Edit Detail & Status Proyek
            </h3>
            <button onclick="closeEditProjectModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" onsubmit="return validateEditCustomRabSubmit()" class="space-y-3.5">
            <input type="hidden" name="update_project" value="1">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Status Proyek <span class="text-red-500">*</span></label>
                <select name="status" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="In Progress" <?= $project['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress (Sedang Berjalan)</option>
                    <option value="On Hold" <?= $project['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold (Ditunda / Proposal Sent)</option>
                    <option value="Completed" <?= $project['status'] === 'Completed' ? 'selected' : '' ?>>Completed (Selesai)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Kategori & Template RAB <span class="text-red-500">*</span></label>
                <select name="kategori_rab" id="editKategoriRabSelect" onchange="toggleEditCustomRabSection()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Website" <?= ($project['kategori_rab'] ?? '') === 'Website' ? 'selected' : '' ?>>RAB Website (Auto-Calculate)</option>
                    <option value="IoT" <?= ($project['kategori_rab'] ?? '') === 'IoT' ? 'selected' : '' ?>>RAB IoT Hardware & Cloud (Auto-Calculate)</option>
                    <option value="Mobile" <?= ($project['kategori_rab'] ?? '') === 'Mobile' ? 'selected' : '' ?>>RAB Mobile App Android/iOS (Auto-Calculate)</option>
                    <option value="Custom" <?= ($project['kategori_rab'] ?? '') === 'Custom' ? 'selected' : '' ?>>Custom RAB (Input Manual Komponen & % Persen)</option>
                </select>
            </div>

            <!-- SECTION DYNAMIC CUSTOM RAB (UNTUK EDITING) -->
            <div id="editCustomRabSection" class="<?= ($project['kategori_rab'] ?? '') === 'Custom' ? '' : 'hidden' ?> bg-slate-50 border border-slate-200 p-3.5 rounded-xl space-y-3">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase block">Rincian Custom RAB (%)</span>
                        <span class="text-[11px] font-normal text-slate-400">Total alokasi persen harus tepat 100%</span>
                    </div>
                    <button type="button" onclick="addEditCustomRabRow()" class="text-xs font-semibold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 px-2.5 py-1 rounded-lg transition">
                        + Tambah Baris
                    </button>
                </div>

                <div id="editCustomRabRows" class="space-y-2">
                    <?php if (!empty($existingCustomRab)): ?>
                        <?php foreach ($existingCustomRab as $cr): ?>
                            <div class="grid grid-cols-12 gap-1.5 items-center edit-custom-rab-row">
                                <input type="text" name="custom_komponen[]" value="<?= htmlspecialchars($cr['komponen'] ?? '') ?>" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                                <input type="text" name="custom_deskripsi[]" value="<?= htmlspecialchars($cr['deskripsi'] ?? '') ?>" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                                <div class="col-span-3 flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1.5">
                                    <input type="number" name="custom_persen[]" value="<?= $cr['persen'] ?? 0 ?>" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
                                    <span class="text-xs font-bold text-slate-400 ml-0.5">%</span>
                                </div>
                                <button type="button" onclick="this.parentElement.remove(); calculateEditCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
                                    &times;
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="grid grid-cols-12 gap-1.5 items-center edit-custom-rab-row">
                            <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                            <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                            <div class="col-span-3 flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1.5">
                                <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
                                <span class="text-xs font-bold text-slate-400 ml-0.5">%</span>
                            </div>
                            <button type="button" onclick="this.parentElement.remove(); calculateEditCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
                                &times;
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-200 text-xs font-semibold">
                    <span class="text-slate-500 uppercase text-[10px]">Total Persentase:</span>
                    <span id="editTotalPersenBadge" class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-xs font-mono">
                        0% / 100%
                    </span>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Proyek <span class="text-red-500">*</span></label>
                <input type="text" name="nama_projek" required value="<?= htmlspecialchars($project['nama_projek']) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Klien <span class="text-red-500">*</span></label>
                <input type="text" name="klien" required value="<?= htmlspecialchars($project['klien']) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Budget Proyek (Rp)</label>
                    <input type="number" name="budget" value="<?= $project['budget'] ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Target Deadline <span class="text-red-500">*</span></label>
                    <input type="date" name="deadline" required value="<?= $project['deadline'] ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeEditProjectModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">Batal</button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Catatan Revisi Admin -->
<div id="revisionModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative text-xs sm:text-sm">
        <h3 class="font-bold text-slate-900 text-base mb-1 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-amber-500"></i> Kirim Catatan Instruksi Revisi
        </h3>
        <p class="text-xs text-slate-500 font-normal mb-4" id="modalTaskTitle">Tugas: -</p>

        <form action="" method="POST" class="space-y-3.5">
            <input type="hidden" name="task_id" id="modalTaskId">
            <input type="hidden" name="qc_action" value="revision">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Detail Poin Revisi <span class="text-red-500">*</span></label>
                <textarea name="revision_notes" required rows="3" placeholder="Tuliskan instruksi penyesuaian untuk tim..." class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeRevisionModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">Batal</button>
                <button type="submit" class="btn-press px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold rounded-xl text-xs shadow-xs">Kirim Instruksi</button>
            </div>
        </form>
    </div>
</div>

<script>
/* =====================================================================
 * KONFIGURASI CHAT — SATU API UNTUK ADMIN & KARYAWAN
 * ===================================================================== */
const CHAT_API   = '../../api_task_chat.php';
const READER     = 'admin';
const SELF_ROLE  = 'admin';
const BASE_TITLE = document.title;
const taskNames  = <?= json_encode($taskNameMap) ?>;

let currentChatTaskId = null;
let lastChatId        = 0;
let chatHistoryLoaded = false;
let sendingChat       = false;
let chatPollTimer     = null;
let newMsgCount       = 0;

const chatBody  = document.getElementById('chatBody');
const jumpBtn   = document.getElementById('jumpLatestBtn');

/* ---------- Utils ---------- */
function escapeHtml(str) {
    return String(str ?? '').replace(/[&<>"']/g, s => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[s]));
}
function nl2br(str) { return escapeHtml(str).replace(/\n/g, '<br>'); }

/* ---------- Toast ---------- */
function showToast(title, subtitle) {
    const wrap = document.getElementById('toastWrap');
    const el = document.createElement('div');
    el.className = 'toast-pop pointer-events-auto bg-slate-900 text-white rounded-xl px-3.5 py-3 shadow-2xl border border-slate-700/60 text-xs flex items-start gap-2.5 cursor-pointer btn-press';
    el.innerHTML =
        '<i class="fa-solid fa-comment-dots text-emerald-400 mt-0.5 shrink-0"></i>' +
        '<div class="min-w-0 flex-1">' +
            '<p class="font-semibold leading-tight">' + escapeHtml(title) + '</p>' +
            (subtitle ? '<p class="text-slate-400 text-[11px] mt-0.5 truncate">' + escapeHtml(subtitle) + '</p>' : '') +
        '</div>';
    wrap.appendChild(el);
    setTimeout(() => {
        el.classList.add('toast-leave');
        setTimeout(() => el.remove(), 300);
    }, 4500);
}

/* ---------- Bubble ---------- */
function renderChatBubble(c, animate) {
    const isSelf = c.sender_role === SELF_ROLE;
    const m = (c.message || '').trim();

    // Warna khusus: revisi = AMBER, approval sistem = hijau muda (konsisten dua sisi)
    let bubble = isSelf ? 'chat-bubble-self' : 'chat-bubble-other';
    if (/^(⚠️|❗|🔴)/.test(m))      bubble = 'chat-bubble-revision';
    else if (/^✅/.test(m))          bubble = 'chat-bubble-success';

    const align     = isSelf ? 'items-end self-end' : 'items-start self-start';
    const nameAlign = isSelf ? 'text-right' : 'text-left';
    const pop       = animate ? ' chat-pop' : '';

    return '<div class="flex flex-col max-w-[85%] ' + align + pop + '">'
        + '<span class="text-[10px] text-slate-400 font-medium mb-1 px-1 ' + nameAlign + '">'
        + escapeHtml(c.sender_name) + ' • ' + escapeHtml(c.created_at) + '</span>'
        + '<div class="chat-bubble ' + bubble + ' shadow-2xs">' + nl2br(c.message) + '</div>'
        + '</div>';
}

/* ---------- Scroll & Jump-to-latest ---------- */
function isNearBottom() {
    return chatBody.scrollHeight - chatBody.clientHeight - chatBody.scrollTop < 80;
}
function hideJumpBtn() {
    newMsgCount = 0;
    jumpBtn.classList.add('hidden');
    jumpBtn.classList.remove('flex');
}
function scrollChatBottom(smooth) {
    hideJumpBtn();
    chatBody.scrollTo({ top: chatBody.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
}
function showJumpBtn() {
    newMsgCount++;
    const old = jumpBtn.querySelector('span');
    if (old) old.remove();
    jumpBtn.insertAdjacentHTML('beforeend', '<span class="ml-1 bg-emerald-600 text-white rounded-full px-1.5 text-[10px] font-bold">' + newMsgCount + '</span>');
    jumpBtn.classList.remove('hidden');
    jumpBtn.classList.add('flex');
}
chatBody.addEventListener('scroll', () => { if (isNearBottom()) hideJumpBtn(); });

/* ---------- Append satu pesan ---------- */
function appendTaskChat(c, animate) {
    if (!c || c.id <= lastChatId) return false;

    const empty = chatBody.querySelector('.empty-chat-notice');
    if (empty) empty.remove();

    const near = isNearBottom();
    chatBody.insertAdjacentHTML('beforeend', renderChatBubble(c, animate));
    lastChatId = c.id;

    if (near || !chatHistoryLoaded) {
        scrollChatBottom(true);
    } else {
        showJumpBtn();
    }
    return true;
}

function showEmptyChat() {
    chatBody.innerHTML =
        '<div class="empty-chat-notice text-center py-10 text-slate-400 text-xs self-center">'
        + '<i class="fa-solid fa-comments text-3xl mb-2 block text-slate-300"></i>'
        + 'Belum ada obrolan di modul ini. Mulai diskusi dengan tim!</div>';
}

/* ---------- Fetch incremental (ringan, anti-lag) ---------- */
function fetchTaskChats() {
    if (!currentChatTaskId) return;

    fetch(CHAT_API + '?action=fetch&task_id=' + currentChatTaskId + '&last_id=' + lastChatId + '&reader=' + READER, { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            // Deteksi real-time: chat dihapus di sisi lain -> bersihkan layar tanpa refresh
            const totalNow = parseInt(data.total, 10) || 0;
            if (totalNow === 0 && lastChatId > 0) {
                lastChatId = 0;
                chatHistoryLoaded = false;
                chatBody.innerHTML = '';
                showEmptyChat();
                hideJumpBtn();
            }

            if (data.messages && data.messages.length > 0) {
                // Riwayat pertama tanpa animasi, pesan baru ber-animasi pop
                data.messages.forEach(c => appendTaskChat(c, chatHistoryLoaded));
                chatHistoryLoaded = true;
            } else if (!chatHistoryLoaded) {
                chatHistoryLoaded = true;
                showEmptyChat();
            }

            updateUnreadBadge(currentChatTaskId, parseInt(data.unread, 10) || 0);
        })
        .catch(() => {});
}

/* ---------- Modal ---------- */
function openChatModal(taskId) {
    currentChatTaskId   = taskId;
    lastChatId          = 0;
    chatHistoryLoaded   = false;
    newMsgCount         = 0;

    document.getElementById('chatTaskId').value = taskId;
    document.getElementById('chatTaskName').innerText = taskNames[taskId] || ('Modul #' + taskId);
    chatBody.innerHTML = '<div class="empty-chat-notice text-center py-10 text-slate-400 text-xs self-center"><i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 block text-slate-300"></i>Memuat diskusi...</div>';
    hideJumpBtn();
    document.getElementById('chatModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');

    fetchTaskChats();
    if (chatPollTimer) clearInterval(chatPollTimer);
    chatPollTimer = setInterval(fetchTaskChats, 2500);
}

function closeChatModal() {
    document.getElementById('chatModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
    if (chatPollTimer) { clearInterval(chatPollTimer); chatPollTimer = null; }
    currentChatTaskId = null;
    // Tidak reload halaman — badge langsung dipoles oleh poll unread
    pollUnreadSummary();
}

/* ---------- Hapus seluruh chat modul (ADMIN ONLY, konfirmasi custom) ---------- */
let deleteConfirmOpen = false;

function openDeleteConfirm() {
    if (!currentChatTaskId || deleteConfirmOpen) return;
    deleteConfirmOpen = true;
    document.getElementById('deleteChatConfirmModal').classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeDeleteConfirm() {
    deleteConfirmOpen = false;
    document.getElementById('deleteChatConfirmModal').classList.add('hidden');
    // Tetap kunci body selama chatbox utama masih terbuka
    if (!document.getElementById('chatModal').classList.contains('hidden')) {
        document.body.classList.add('overflow-hidden');
    } else {
        document.body.classList.remove('overflow-hidden');
    }
}

function performDeleteChat() {
    if (!currentChatTaskId) { closeDeleteConfirm(); return; }

    const btn = document.getElementById('deleteChatConfirmBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin text-[10px]"></i> Menghapus...';
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('task_id', currentChatTaskId);

    fetch(CHAT_API, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                lastChatId = 0;
                chatHistoryLoaded = false;
                chatBody.innerHTML = '';
                showEmptyChat();
                updateUnreadBadge(currentChatTaskId, 0);
                showToast('🗑️ Chat dibersihkan', 'Seluruh riwayat diskusi modul ini telah dihapus dari database.');
            } else {
                showToast('⚠️ Gagal menghapus', data.error || 'Terjadi kesalahan tidak dikenal.');
            }
        })
        .catch(() => { showToast('⚠️ Koneksi bermasalah', 'Gagal terhubung ke server saat menghapus chat.'); })
        .finally(() => {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash-can text-[10px]"></i> Ya, Hapus';
            }
            closeDeleteConfirm();
        });
}

/* ---------- Kirim pesan ---------- */
function submitTaskChat(e) {
    e.preventDefault();
    if (sendingChat || !currentChatTaskId) return;

    const input = document.getElementById('chatInputMessage');
    const btn   = document.getElementById('chatSendBtn');
    const msg   = input.value.trim();
    if (!msg) return;

    sendingChat = true;
    btn.disabled = true;
    input.value = '';

    const formData = new FormData();
    formData.append('action', 'send');
    formData.append('task_id', currentChatTaskId);
    formData.append('message', msg);

    fetch(CHAT_API, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success && data.message) {
                appendTaskChat(data.message, true);
                scrollChatBottom(true);
            }
        })
        .catch(() => {})
        .finally(() => {
            sendingChat = false;
            btn.disabled = false;
            input.focus();
        });
}

/* =====================================================================
 * BADGE UNREAD + TOAST PESAN BARU (GLOBAL, dua sisi sama)
 * ===================================================================== */
function updateUnreadBadge(taskId, count) {
    const badge = document.getElementById('unreadBadge-' + taskId);
    if (!badge) return;
    if (count > 0) {
        badge.innerText = count;
        badge.classList.remove('hidden');
    } else {
        badge.classList.add('hidden');
    }
}

let unreadInitialized = false;
let lastSeenChatIds   = {};

function pollUnreadSummary() {
    if (document.hidden) return;

    fetch(CHAT_API + '?action=unread_summary', { cache: 'no-store' })
        .then(res => res.json())
        .then(data => {
            if (!data.success) return;

            const map = data.tasks || {};
            let total = 0;

            Object.keys(map).forEach(tid => {
                const info = map[tid];
                tid = parseInt(tid, 10);
                total += info.count;
                updateUnreadBadge(tid, info.count);

                // Toast hanya untuk pesan yang benar-benar baru
                if (unreadInitialized
                    && info.last_id > (lastSeenChatIds[tid] || 0)
                    && tid !== currentChatTaskId) {
                    showToast('Pesan baru dari ' + info.sender, (taskNames[tid] || ('Modul #' + tid)) + ' • ' + info.preview);
                }
                lastSeenChatIds[tid] = Math.max(lastSeenChatIds[tid] || 0, info.last_id);
            });

            // Bersihkan badge task yang sudah tidak unread
            document.querySelectorAll('.unread-badge').forEach(b => {
                const id = parseInt(b.id.replace('unreadBadge-', ''), 10);
                if (!map[id]) updateUnreadBadge(id, 0);
            });

            unreadInitialized = true;
            document.title = total > 0 ? '(' + total + ') ' + BASE_TITLE : BASE_TITLE;
        })
        .catch(() => {});
}

setInterval(pollUnreadSummary, 8000);

// Pesan sukses dari URL (QC / konfigurasi) -> tampilkan juga sebagai toast
document.addEventListener('DOMContentLoaded', function() {
    const msg = new URLSearchParams(location.search).get('msg');
    if (msg === 'approved')       showToast('✅ Modul Approved', 'Status modul diperbarui menjadi Done.');
    if (msg === 'revision_sent')  showToast('⚠️ Instruksi revisi terkirim', 'Tim menerima catatan revisi di chatbox modul.');

    pollUnreadSummary();
});

/* =====================================================================
 * JS LAINNYA (tidak diubah fungsinya)
 * ===================================================================== */
function toggleEditCustomRabSection() {
    const val = document.getElementById('editKategoriRabSelect').value;
    const sec = document.getElementById('editCustomRabSection');
    if (val === 'Custom') {
        sec.classList.remove('hidden');
        calculateEditCustomPercentageTotal();
    } else {
        sec.classList.add('hidden');
    }
}

function addEditCustomRabRow() {
    const container = document.getElementById('editCustomRabRows');
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-12 gap-1.5 items-center edit-custom-rab-row';
    newRow.innerHTML = `
        <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
        <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
        <div class="col-span-3 flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1.5">
            <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
            <span class="text-xs font-bold text-slate-400 ml-0.5">%</span>
        </div>
        <button type="button" onclick="this.parentElement.remove(); calculateEditCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
            &times;
        </button>
    `;
    container.appendChild(newRow);
    calculateEditCustomPercentageTotal();
}

function calculateEditCustomPercentageTotal() {
    let total = 0;
    document.querySelectorAll('.edit-custom-persen-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    const badge = document.getElementById('editTotalPersenBadge');
    if (badge) {
        badge.innerText = `${total.toFixed(1)}% / 100%`;
        if (Math.abs(total - 100) < 0.01) {
            badge.className = "px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 text-xs font-mono font-bold";
        } else {
            badge.className = "px-2 py-0.5 rounded-md bg-red-100 text-red-700 text-xs font-mono font-bold";
        }
    }
    return total;
}

function validateEditCustomRabSubmit() {
    const val = document.getElementById('editKategoriRabSelect').value;
    if (val === 'Custom') {
        const totalPct = calculateEditCustomPercentageTotal();
        if (Math.abs(totalPct - 100) > 0.01) {
            alert(`Total persentase Custom RAB saat ini ${totalPct.toFixed(1)}%. Total harus pas 100% agar tidak ada selisih budget!`);
            return false;
        }
    }
    return true;
}

function filterUserList() {
    const searchVal = document.getElementById('userSearchInput').value.toLowerCase();
    const roleVal   = document.getElementById('userRoleFilter').value.toLowerCase();
    const items     = document.querySelectorAll('#userListContainer .user-item');

    items.forEach(item => {
        const nameData = item.getAttribute('data-name') || '';
        const roleData = item.getAttribute('data-role') || '';

        const matchesName = nameData.includes(searchVal);
        const matchesRole = (roleVal === 'all') || roleData.includes(roleVal);

        if (matchesName && matchesRole) {
            item.classList.remove('hidden');
            item.classList.add('flex');
        } else {
            item.classList.add('hidden');
            item.classList.remove('flex');
        }
    });
}

function openEditProjectModal() { 
    document.getElementById('editProjectModal').classList.remove('hidden');
    calculateEditCustomPercentageTotal();
}
function closeEditProjectModal() { document.getElementById('editProjectModal').classList.add('hidden'); }

function openRevisionModal(taskId, title) {
    document.getElementById('modalTaskId').value = taskId;
    document.getElementById('modalTaskTitle').innerText = 'Modul: ' + title;
    document.getElementById('revisionModal').classList.remove('hidden');
}
function closeRevisionModal() { document.getElementById('revisionModal').classList.add('hidden'); }

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('projectProgressChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Done', 'Siap QC', 'In Progress', 'To Do'],
            datasets: [{
                data: [<?= $completedTasks ?>, <?= $readyReviewTasks ?>, <?= $inProgressTasks ?>, <?= $todoTasks ?>],
                backgroundColor: ['#10b981', '#a855f7', '#3b82f6', '#cbd5e1'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            cutout: '70%'
        }
    });

    initPagination();
});

const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

function initPagination() { filterTasks(); }

function filterTasks() {
    const searchVal = document.getElementById('taskSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const allRows = Array.from(document.querySelectorAll('.task-row'));

    filteredRows = allRows.filter(row => {
        const titleText = row.querySelector('.task-title').innerText.toLowerCase();
        const assigneesText = row.querySelector('.task-assignees').innerText.toLowerCase();
        const rowStatus = row.getAttribute('data-status');

        const matchesSearch = titleText.includes(searchVal) || assigneesText.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (rowStatus === statusVal);

        return matchesSearch && matchesStatus;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.task-row');
    allRows.forEach(row => row.style.display = 'none');

    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / rowsPerPage) || 1;

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx = startIdx + rowsPerPage;

    filteredRows.slice(startIdx, endIdx).forEach(row => {
        row.style.display = 'table-row';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} modul`;

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