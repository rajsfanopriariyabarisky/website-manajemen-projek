<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login & Role Guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../my_tasks.php");
    exit;
}

$id = $_GET['id'] ?? null;
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

// 1. Handle Update Data Proyek (Termasuk Kategori RAB & Custom RAB)
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

        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Memperbarui konfigurasi proyek, status '$status' & Kategori RAB '$kategori_rab'", 'info');

        header("Location: detail.php?id=" . $id . "&msg=project_updated");
        exit;
    }
}

// Fetch Detail Proyek
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
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

        // Simpan ke task_assignees
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

        header("Location: detail.php?id=" . $id . "&msg=approved");
        exit;
    } elseif ($action === 'revision') {
        $stmtRev = $pdo->prepare("UPDATE tasks SET status = 'In Progress', revision_notes = ? WHERE id = ?");
        $stmtRev->execute([$revision_notes, $task_id]);

        logProjectActivity($pdo, $id, $current_user_id, $current_user_nama, "Mengirimkan catatan instruksi revisi pada modul '$taskTitle'", 'warning');

        header("Location: detail.php?id=" . $id . "&msg=revision_sent");
        exit;
    }
}

// Fetch List Karyawan & Daftar Jabatan Unik
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

// Parse existing Custom RAB Data if any
$existingCustomRab = [];
if (!empty($project['rab_custom_data'])) {
    $existingCustomRab = json_decode($project['rab_custom_data'], true) ?? [];
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

    .overdue-row {
        background-color: rgba(254, 242, 242, 0.75) !important;
    }
    .overdue-row:hover {
        background-color: rgba(254, 226, 226, 0.95) !important;
    }
</style>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-[#f8fafc] min-h-screen w-full min-w-0 font-sans">
    
    <!-- Top Navigation Bar -->
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <a href="index.php" class="btn-press text-xs font-extrabold text-emerald-800 hover:text-emerald-900 flex items-center gap-2 bg-emerald-50 hover:bg-emerald-100/80 px-3.5 py-2 rounded-xl border border-emerald-200/80 transition shadow-sm">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Portfolio Proyek
        </a>
        <span class="text-xs font-mono font-bold px-3 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-600 shadow-sm flex items-center gap-1.5">
            <i class="fa-solid fa-hashtag text-emerald-600"></i> PROJ-<?= sprintf("%03d", $project['id']) ?>
        </span>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'project_updated'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> Konfigurasi, Kategori RAB & milestone proyek berhasil diperbarui!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'task_added'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> Alokasi modul baru berhasil didistribusikan ke tim terkait!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'approved'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3.5 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> Modul disetujui (Approved)! Status otomatis diperbarui ke Selesai (Done).
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'revision_sent'): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3.5 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-base text-amber-600"></i> Catatan instruksi revisi telah berhasil dikirimkan ke tim teknis.
        </div>
    <?php endif; ?>

    <!-- Banner Header Proyek & Executive Summary -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-sm mb-6 relative overflow-hidden">
        <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-6">
            
            <div class="space-y-3 flex-1 min-w-0">
                <div class="flex items-center gap-2.5 flex-wrap">
                    <span class="px-3 py-1 rounded-lg text-[10px] uppercase font-black border tracking-wider
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

                    <span class="px-3 py-1 rounded-lg text-[10px] uppercase font-black bg-indigo-50 text-indigo-700 border border-indigo-200 tracking-wider">
                        RAB: <?= htmlspecialchars($project['kategori_rab'] ?? 'Website') ?>
                    </span>

                    <span class="text-xs text-slate-500 font-bold flex items-center gap-1.5 bg-slate-50 px-3 py-1 rounded-lg border border-slate-200/80">
                        <i class="fa-regular fa-calendar-check text-emerald-600"></i> Target Deadline: <strong class="text-slate-800 font-extrabold"><?= date('d M Y', strtotime($project['deadline'])) ?></strong>
                    </span>
                </div>

                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight leading-tight truncate"><?= htmlspecialchars($project['nama_projek']) ?></h1>
                
                <div class="text-xs text-slate-500 flex items-center gap-3 flex-wrap">
                    <span>Mitra Klien: <strong class="text-slate-800 font-extrabold"><?= htmlspecialchars($project['klien']) ?></strong></span>
                    <span class="text-slate-300">•</span>
                    <span>Nilai Kontrak Workload: <strong class="text-emerald-700 font-black font-mono text-sm">Rp <?= number_format($project['budget'], 0, ',', '.') ?></strong></span>
                </div>
            </div>

            <!-- Visual Donut Chart Summary -->
            <div class="flex items-center gap-4 bg-slate-50/90 p-3.5 rounded-2xl border border-slate-200/80 shrink-0">
                <div class="w-20 h-20 relative">
                    <canvas id="projectProgressChart"></canvas>
                </div>
                <div class="text-xs space-y-1">
                    <span class="text-[9px] uppercase font-black tracking-wider text-slate-400 block">Status Modul</span>
                    <p class="font-extrabold text-emerald-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-emerald-500"></i> <?= $completedTasks ?> Done</p>
                    <p class="font-extrabold text-purple-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-purple-500"></i> <?= $readyReviewTasks ?> Siap QC</p>
                    <p class="font-extrabold text-blue-700 flex items-center gap-1.5"><i class="fa-solid fa-square text-[9px] text-blue-500"></i> <?= $inProgressTasks ?> In Progress</p>
                </div>
            </div>

            <!-- TOMBOL DIKEMBALIKAN KELAPORAN PDF ORIGINAL -->
            <div class="flex items-center gap-2 shrink-0">
                <button onclick="openEditProjectModal()" class="btn-press px-4 py-2.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300/80 rounded-xl text-xs font-extrabold transition shadow-sm flex items-center justify-center gap-2">
                    <i class="fa-regular fa-pen-to-square"></i> Konfigurasi Proyek
                </button>
                <a href="print_report.php?id=<?= $id ?>" target="_blank" class="btn-press px-4 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-extrabold transition shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-print"></i> Laporan PDF
                </a>
            </div>
        </div>

        <div class="pt-5 mt-5 border-t border-slate-100">
            <div class="flex justify-between text-xs font-black mb-2">
                <span class="text-slate-500 uppercase tracking-wider text-[10px]">Progress Completion Milestone</span>
                <span class="text-emerald-700 font-black"><?= $progressPercent ?>% Completed (<?= $completedTasks ?>/<?= $totalTasks ?> Modul Selesai)</span>
            </div>
            <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden border border-slate-200/80 p-0.5">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-2 rounded-full transition-all duration-700" style="width: <?= $progressPercent ?>%"></div>
            </div>
        </div>
    </div>

    <!-- Grid Layout Utama -->
    <div class="flex flex-col xl:flex-row gap-6 w-full min-w-0">
        
        <!-- SEKSI KIRI: FORM ALOKASIKAN MODUL BARU -->
        <div class="w-full xl:w-80 shrink-0">
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm sticky top-6">
                <h3 class="font-black text-slate-800 text-xs uppercase tracking-wider mb-4 flex items-center gap-2 pb-3 border-b border-slate-100">
                    <i class="fa-solid fa-users-gear text-emerald-600"></i> Alokasikan Modul Baru
                </h3>
                
                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="add_task" value="1">
                    
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Modul / Fitur *</label>
                        <input type="text" name="nama_tugas" required placeholder="Contoh: Development Core Engine" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Pilih Tim Pelaksana (Multi-Select) *</label>
                        
                        <div class="space-y-1.5 mb-2">
                            <div class="relative">
                                <input type="text" id="userSearchInput" onkeyup="filterUserList()" placeholder="Cari nama karyawan..." class="input-glow w-full px-3 py-1.5 pl-8 rounded-lg border border-slate-200 text-[11px] font-medium focus:outline-none">
                                <i class="fa-solid fa-magnifying-glass absolute left-2.5 top-2 text-slate-400 text-[10px]"></i>
                            </div>

                            <select id="userRoleFilter" onchange="filterUserList()" class="input-glow w-full px-2.5 py-1.5 rounded-lg border border-slate-200 text-[11px] font-bold text-slate-700 bg-white focus:outline-none">
                                <option value="all">Semua Divisi / Jabatan</option>
                                <?php foreach ($jabatans as $jb): ?>
                                    <option value="<?= htmlspecialchars(strtolower($jb)) ?>"><?= htmlspecialchars($jb) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-xl p-2 space-y-1 bg-slate-50/80" id="userListContainer">
                            <?php foreach ($users as $u): ?>
                                <?php 
                                    $userJabatanLower = strtolower($u['jabatan'] ?? 'staff');
                                    $userNamaLower    = strtolower($u['nama']);
                                ?>
                                <label class="user-item flex items-center gap-2 text-xs text-slate-700 font-bold cursor-pointer hover:bg-white p-1.5 rounded-lg transition" 
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
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Prioritas</label>
                            <select name="prioritas" class="input-glow w-full px-3 py-2.5 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Target Deadline</label>
                            <input type="date" name="deadline" required value="<?= date('Y-m-d') ?>" class="input-glow w-full px-2.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none">
                        </div>
                    </div>

                    <button type="submit" class="btn-press w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs rounded-xl transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2">
                        <i class="fa-solid fa-plus text-xs"></i> Pendistribusikan Modul
                    </button>
                </form>
            </div>
        </div>

        <!-- SEKSI KANAN: TABEL MANAJEMEN MODUL & QC UTAMA -->
        <div class="flex-1 min-w-0">
            <div class="bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm">
                
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 mb-4 border-b border-slate-100">
                    <div class="min-w-0">
                        <h3 class="font-black text-slate-900 text-base sm:text-lg flex items-center gap-2.5 whitespace-nowrap">
                            <i class="fa-solid fa-list-check text-emerald-600 text-xl"></i> Manajemen Modul & QC
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5 truncate font-medium">Monitoring komunikasi progress dan verifikasi hasil pengerjaan tim</p>
                    </div>

                    <div class="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap w-full sm:w-auto">
                        <div class="relative w-full sm:w-48">
                            <input type="text" id="taskSearchInput" onkeyup="filterTasks()" placeholder="Cari modul / tim..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-200 text-xs focus:outline-none font-medium">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>

                        <select id="statusFilterSelect" onchange="filterTasks()" class="input-glow px-3 py-2 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                            <option value="all">Semua Status</option>
                            <option value="To Do">To Do</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Ready for Review">Ready for Review (Siap QC)</option>
                            <option value="Done">Done (Selesai)</option>
                        </select>
                    </div>
                </div>

                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[720px]" id="tasksTable">
                        <thead class="uppercase bg-slate-50 text-slate-500 font-black border-b border-slate-200 text-[10px] tracking-wider">
                            <tr>
                                <th class="py-3 px-3.5">Modul & Laporan</th>
                                <th class="py-3 px-3.5 text-center">Prioritas</th>
                                <th class="py-3 px-3.5">Tim Pelaksana</th>
                                <th class="py-3 px-3.5 text-center">Status</th>
                                <th class="py-3 px-3.5 text-center">Deliverables</th>
                                <th class="py-3 px-3.5 text-center">Verifikasi PM</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" id="tasksTableBody">
                            <?php if (empty($allTasks)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-10 text-slate-400 font-bold">
                                        <i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 block"></i>
                                        Belum ada modul pengerjaan dialokasikan untuk proyek ini.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allTasks as $t): ?>
                                    <?php 
                                        $stLower = strtolower(trim($t['status']));
                                        $prioLower = strtolower(trim($t['prioritas'] ?? 'medium'));
                                        $assignees = $taskAssigneesMap[$t['id']] ?? [];
                                        $updaterName = !empty($t['updater_nama']) ? $t['updater_nama'] : 'Karyawan';
                                        $hasNote = !empty($t['progress_notes']);
                                        $hasRev  = !empty($t['revision_notes']);

                                        $taskDeadlineTS = strtotime($t['deadline']);
                                        $isOverdue = ($taskDeadlineTS < $todayTimestamp) && ($stLower !== 'done');
                                        
                                        $diffDays = 0;
                                        if ($isOverdue) {
                                            $diffDays = max(1, floor(($todayTimestamp - $taskDeadlineTS) / (60 * 60 * 24)));
                                        }
                                    ?>
                                    <tr class="task-row hover:bg-slate-50/80 transition align-middle <?= $isOverdue ? 'overdue-row' : '' ?>" data-status="<?= htmlspecialchars($t['status']) ?>">
                                        
                                        <td class="py-3.5 px-3.5">
                                            <div class="font-extrabold text-slate-900 text-sm task-title flex items-center gap-1.5">
                                                <?php if ($isOverdue): ?>
                                                    <i class="fa-solid fa-bell text-red-500 text-xs animate-bounce shrink-0" title="PRIORITAS! Modul ini telah melewati deadline."></i>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($t['nama_tugas']) ?></span>
                                            </div>

                                            <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                                <span class="text-[10px] font-bold flex items-center gap-1 shrink-0 <?= $isOverdue ? 'text-red-600 font-black' : 'text-slate-400' ?>">
                                                    <i class="fa-regular fa-calendar-xmark text-[10px]"></i>
                                                    Deadline: <?= date('d M Y', strtotime($t['deadline'])) ?>
                                                </span>

                                                <?php if ($isOverdue): ?>
                                                    <span class="px-2 py-0.5 rounded-md text-[9px] bg-red-600 text-white font-black uppercase tracking-wider shadow-sm flex items-center gap-1 shrink-0">
                                                        <i class="fa-solid fa-circle-exclamation text-[8px]"></i> Terlambat <?= $diffDays ?> Hari
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <?php if ($hasNote || $hasRev): ?>
                                                    <button onclick="openNoteModal('<?= htmlspecialchars(addslashes($t['nama_tugas'])) ?>', '<?= htmlspecialchars(addslashes($updaterName)) ?>', '<?= htmlspecialchars(addslashes($t['progress_notes'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($t['revision_notes'] ?? '')) ?>', '<?= htmlspecialchars($t['drive_link'] ?? '') ?>')" 
                                                        class="btn-press px-2 py-0.5 rounded-md bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 font-black text-[10px] flex items-center gap-1 shadow-sm shrink-0" title="Klik untuk lihat laporan lengkap">
                                                        <i class="fa-solid fa-comments text-blue-600"></i> Laporan
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-3.5 text-center shrink-0">
                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase inline-block border tracking-wider whitespace-nowrap
                                                <?php
                                                    switch($prioLower) {
                                                        case 'urgent': echo 'bg-red-50 text-red-700 border-red-200'; break;
                                                        case 'high': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                                        case 'medium': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                        default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                                    }
                                                ?>">
                                                <?= htmlspecialchars($t['prioritas'] ?? 'Medium') ?>
                                            </span>
                                        </td>

                                        <td class="py-3.5 px-3.5">
                                            <div class="flex flex-col gap-1 task-assignees">
                                                <?php foreach ($assignees as $asg): ?>
                                                    <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200 font-bold text-[10px] inline-flex items-center gap-1 w-max shadow-sm shrink-0">
                                                        <i class="fa-solid fa-user text-[9px] text-emerald-600"></i> <?= htmlspecialchars($asg['nama']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>

                                        <td class="py-3.5 px-3.5 text-center shrink-0">
                                            <span class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase inline-block border tracking-wide whitespace-nowrap
                                                <?php
                                                    switch($stLower) {
                                                        case 'done': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                        case 'ready for review': echo 'bg-purple-50 text-purple-700 border-purple-200'; break;
                                                        case 'in progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                        default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                                    }
                                                ?>">
                                                <?= htmlspecialchars($t['status']) ?>
                                            </span>
                                        </td>

                                        <td class="py-3.5 px-3.5 text-center shrink-0">
                                            <?php if (!empty($t['drive_link'])): ?>
                                                <a href="<?= htmlspecialchars($t['drive_link']) ?>" target="_blank" class="btn-press px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-[10px] font-bold border border-emerald-200 transition inline-flex items-center gap-1 shadow-sm whitespace-nowrap">
                                                    <i class="fa-brands fa-google-drive text-emerald-600"></i> Drive
                                                </a>
                                            <?php else: ?>
                                                <span class="text-slate-400 text-[10px] font-semibold italic whitespace-nowrap">Belum ada</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="py-3.5 px-3.5 text-center shrink-0">
                                            <?php if ($stLower === 'done'): ?>
                                                <span class="text-emerald-600 font-black text-xs inline-flex items-center gap-1 whitespace-nowrap"><i class="fa-solid fa-circle-check"></i> Approved</span>
                                            <?php else: ?>
                                                <div class="flex items-center justify-center gap-1.5 whitespace-nowrap">
                                                    <form action="" method="POST" onsubmit="return confirm('Setujui pengerjaan modul ini?')">
                                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                                        <input type="hidden" name="qc_action" value="approve">
                                                        <button type="submit" class="btn-press px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold shadow-sm transition">Approve</button>
                                                    </form>
                                                    <button onclick="openRevisionModal('<?= $t['id'] ?>', '<?= htmlspecialchars(addslashes($t['nama_tugas'])) ?>')" class="btn-press px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-[10px] font-bold shadow-sm transition">Revisi</button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-4 mt-4 border-t border-slate-100 text-xs">
                    <span class="text-slate-500 font-bold text-[11px]" id="paginationInfo">Menampilkan 0 data</span>
                    <div class="flex items-center gap-1" id="paginationControls"></div>
                </div>

            </div>
        </div>

    </div>
</main>

<!-- MODAL POPUP CHAT BUBBLE LAPORAN & CATATAN KARYAWAN -->
<div id="noteModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="font-black text-slate-800 text-base flex items-center gap-2">
                <i class="fa-solid fa-comments text-blue-600"></i> Detail Laporan & Catatan
            </h3>
            <button onclick="closeNoteModal()" class="text-slate-400 hover:text-slate-600 text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="space-y-4">
            <div>
                <span class="text-[9px] font-black uppercase text-slate-400 block mb-1">Nama Modul</span>
                <p class="font-black text-slate-900 text-sm" id="noteModalTaskName">-</p>
            </div>

            <div id="noteModalUserBox" class="bg-blue-50 border border-blue-200 rounded-2xl p-4">
                <div class="flex items-center gap-2 font-black text-blue-900 mb-1 text-xs">
                    <div class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span id="noteModalUser">-</span>
                </div>
                <p class="text-blue-800 text-xs italic leading-relaxed pl-8 font-medium" id="noteModalUserText">"-"</p>
            </div>

            <div id="noteModalRevBox" class="bg-red-50 border border-red-200 rounded-2xl p-4 hidden">
                <div class="flex items-center gap-2 font-black text-red-900 mb-1 text-xs">
                    <div class="w-6 h-6 rounded-full bg-red-600 text-white flex items-center justify-center text-[10px]">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <span>Catatan Instruksi Revisi PM:</span>
                </div>
                <p class="text-red-800 text-xs leading-relaxed pl-8 font-semibold" id="noteModalRevText">-</p>
            </div>

            <div id="noteModalDriveBox" class="hidden pt-2">
                <a id="noteModalDriveLink" href="#" target="_blank" class="btn-press w-full py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-xl text-xs font-bold flex items-center justify-center gap-2 transition">
                    <i class="fa-brands fa-google-drive text-emerald-600 text-sm"></i> Buka File / Google Drive Terlampir
                </a>
            </div>
        </div>

        <div class="flex justify-end pt-5 mt-4 border-t border-slate-100">
            <button onclick="closeNoteModal()" class="btn-press px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow">Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT DETAIL PROYEK & KATEGORI RAB -->
<div id="editProjectModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative my-8">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="font-black text-slate-800 text-base flex items-center gap-2">
                <i class="fa-regular fa-pen-to-square text-amber-500"></i> Edit Detail & Status Proyek
            </h3>
            <button onclick="closeEditProjectModal()" class="text-slate-400 hover:text-slate-600 text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="" method="POST" onsubmit="return validateEditCustomRabSubmit()" class="space-y-4">
            <input type="hidden" name="update_project" value="1">

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Status Proyek *</label>
                <select name="status" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-extrabold text-slate-700 focus:outline-none">
                    <option value="In Progress" <?= $project['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress (Sedang Berjalan)</option>
                    <option value="On Hold" <?= $project['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold (Ditunda sementara)</option>
                    <option value="Completed" <?= $project['status'] === 'Completed' ? 'selected' : '' ?>>Completed (Selesai)</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Kategori & Template RAB *</label>
                <select name="kategori_rab" id="editKategoriRabSelect" onchange="toggleEditCustomRabSection()" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-extrabold text-slate-700 focus:outline-none">
                    <option value="Website" <?= ($project['kategori_rab'] ?? '') === 'Website' ? 'selected' : '' ?>>RAB Website (Auto-Calculate)</option>
                    <option value="IoT" <?= ($project['kategori_rab'] ?? '') === 'IoT' ? 'selected' : '' ?>>RAB IoT Hardware & Cloud (Auto-Calculate)</option>
                    <option value="Mobile" <?= ($project['kategori_rab'] ?? '') === 'Mobile' ? 'selected' : '' ?>>RAB Mobile App Android/iOS (Auto-Calculate)</option>
                    <option value="Custom" <?= ($project['kategori_rab'] ?? '') === 'Custom' ? 'selected' : '' ?>>Custom RAB (Input Manual Komponen & % Persen)</option>
                </select>
            </div>

            <!-- SECTION DYNAMIC CUSTOM RAB (UNTUK EDITING) -->
            <div id="editCustomRabSection" class="<?= ($project['kategori_rab'] ?? '') === 'Custom' ? '' : 'hidden' ?> bg-slate-50 border border-slate-200/80 p-3.5 rounded-xl space-y-3">
                <div class="flex justify-between items-center border-b border-slate-200/80 pb-2">
                    <div>
                        <span class="text-[10px] font-black uppercase text-emerald-800 block">Rincian Custom RAB (%)</span>
                        <span class="text-[9px] font-bold text-slate-400">Total alokasi persen harus tepat 100%</span>
                    </div>
                    <button type="button" onclick="addEditCustomRabRow()" class="text-[10px] font-black text-emerald-700 bg-emerald-100 hover:bg-emerald-200 px-2 py-1 rounded-lg transition">
                        + Tambah Baris
                    </button>
                </div>

                <div id="editCustomRabRows" class="space-y-2">
                    <?php if (!empty($existingCustomRab)): ?>
                        <?php foreach ($existingCustomRab as $cr): ?>
                            <div class="grid grid-cols-12 gap-1.5 items-center edit-custom-rab-row">
                                <input type="text" name="custom_komponen[]" value="<?= htmlspecialchars($cr['komponen'] ?? '') ?>" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none">
                                <input type="text" name="custom_deskripsi[]" value="<?= htmlspecialchars($cr['deskripsi'] ?? '') ?>" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none">
                                <div class="col-span-3 flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1.5">
                                    <input type="number" name="custom_persen[]" value="<?= $cr['persen'] ?? 0 ?>" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
                                    <span class="text-xs font-black text-slate-400 ml-0.5">%</span>
                                </div>
                                <button type="button" onclick="this.parentElement.remove(); calculateEditCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
                                    &times;
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="grid grid-cols-12 gap-1.5 items-center edit-custom-rab-row">
                            <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none">
                            <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none">
                            <div class="col-span-3 flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1.5">
                                <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
                                <span class="text-xs font-black text-slate-400 ml-0.5">%</span>
                            </div>
                            <button type="button" onclick="this.parentElement.remove(); calculateEditCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
                                &times;
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-200/80 text-xs font-black">
                    <span class="text-slate-500 text-[10px] uppercase">Total Persentase:</span>
                    <span id="editTotalPersenBadge" class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-xs font-mono">
                        0% / 100%
                    </span>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Proyek *</label>
                <input type="text" name="nama_projek" required value="<?= htmlspecialchars($project['nama_projek']) ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Klien *</label>
                <input type="text" name="klien" required value="<?= htmlspecialchars($project['klien']) ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Budget Proyek (Rp)</label>
                    <input type="number" name="budget" value="<?= $project['budget'] ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-mono font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Target Deadline *</label>
                    <input type="date" name="deadline" required value="<?= $project['deadline'] ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-3">
                <button type="button" onclick="closeEditProjectModal()" class="btn-press px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                <button type="submit" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20">
                    <i class="fa-solid fa-floppy-disk mr-1"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Catatan Revisi Admin -->
<div id="revisionModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <h3 class="font-black text-slate-800 text-base mb-1 flex items-center gap-2">
            <i class="fa-solid fa-pen-to-square text-amber-500"></i> Kirim Catatan Instruksi Revisi
        </h3>
        <p class="text-xs text-slate-500 font-bold mb-4" id="modalTaskTitle">Tugas: -</p>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="task_id" id="modalTaskId">
            <input type="hidden" name="qc_action" value="revision">

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Detail Poin Revisi *</label>
                <textarea name="revision_notes" required rows="3" placeholder="Tuliskan instruksi penyesuaian untuk tim..." class="input-glow w-full px-3 py-2 rounded-xl border border-slate-200 text-xs focus:outline-none font-medium"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeRevisionModal()" class="btn-press px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold">Batal</button>
                <button type="submit" class="btn-press px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-xl text-xs font-bold shadow">Kirim Instruksi</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle & Calculation untuk Custom RAB di Edit Modal
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
        <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-bold focus:outline-none">
        <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-200 rounded-lg text-xs font-medium focus:outline-none">
        <div class="col-span-3 flex items-center bg-white border border-slate-200 rounded-lg px-2 py-1.5">
            <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateEditCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none edit-custom-persen-input">
            <span class="text-xs font-black text-slate-400 ml-0.5">%</span>
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

// Filter Live Search Nama & Divisi Karyawan
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

// Modal Toggles
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

function openNoteModal(taskName, userName, userNote, revNote, driveLink) {
    document.getElementById('noteModalTaskName').innerText = taskName;
    document.getElementById('noteModalUser').innerText = userName + ' (Pengirim Laporan)';
    document.getElementById('noteModalUserText').innerText = '"' + (userNote || 'Tidak ada catatan tertulis.') + '"';

    const revBox = document.getElementById('noteModalRevBox');
    if (revNote && revNote.trim() !== '') {
        document.getElementById('noteModalRevText').innerText = revNote;
        revBox.classList.remove('hidden');
    } else {
        revBox.classList.add('hidden');
    }

    const driveBox = document.getElementById('noteModalDriveBox');
    if (driveLink && driveLink.trim() !== '') {
        document.getElementById('noteModalDriveLink').href = driveLink;
        driveBox.classList.remove('hidden');
    } else {
        driveBox.classList.add('hidden');
    }

    document.getElementById('noteModal').classList.remove('hidden');
}
function closeNoteModal() { document.getElementById('noteModal').classList.add('hidden'); }

// Chart.js Donut Graphic
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

// Live Search & Paginasi
const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

function initPagination() {
    filterTasks();
}

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
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.className = `px-2.5 py-1 rounded-lg font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `px-3 py-1 rounded-lg font-bold ${i === currentPage ? 'bg-emerald-600 text-white shadow-sm' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.className = `px-2.5 py-1 rounded-lg font-bold ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage--; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>