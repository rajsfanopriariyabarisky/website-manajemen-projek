<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_nama = $_SESSION['nama'] ?? 'Karyawan';
$user_role = $_SESSION['role'] ?? 'Karyawan';

// Handle Update Progress + Upload File dari Karyawan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    $task_id        = $_POST['task_id'];
    $status         = $_POST['status'];
    $progress_notes = trim($_POST['progress_notes']);
    $drive_link     = trim($_POST['drive_link']);

    // Validasi Tugas Karyawan: Mendukung Cek di task_assignees maupun user_id/assigned_to
    $stmtCheck = $pdo->prepare("
        SELECT t.id, t.project_id, t.nama_tugas, t.status AS status_lama 
        FROM tasks t 
        WHERE t.id = ? 
          AND (
              t.user_id = ? 
              OR t.assigned_to = ? 
              OR t.id IN (SELECT task_id FROM task_assignees WHERE user_id = ?)
          )
    ");
    $stmtCheck->execute([$task_id, $user_id, $user_id, $user_id]);
    $taskData = $stmtCheck->fetch();

    if ($taskData && $taskData['status_lama'] !== 'Done') {
        $projId   = $taskData['project_id'];
        $taskName = $taskData['nama_tugas'];

        // Karyawan tidak boleh set sendiri ke 'Done'
        if ($status === 'Done') {
            $status = 'Ready for Review';
        }

        // 1. Update data tugas
        $stmtUp = $pdo->prepare("UPDATE tasks SET status = ?, progress_notes = ?, drive_link = ?, updated_by_user_id = ? WHERE id = ?");
        $stmtUp->execute([$status, $progress_notes, $drive_link, $user_id, $task_id]);

        // 2. Handle Upload File Fisik ke Vault ( project_files )
        if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath   = $_FILES['task_file']['tmp_name'];
            $fileName      = $_FILES['task_file']['name'];
            $fileSize      = $_FILES['task_file']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'png', 'jpg', 'jpeg'];

            if (in_array($fileExtension, $allowedExtensions)) {
                $uploadDir = __DIR__ . '/uploads/projects/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $newFileName = md5(time() . '_' . $fileName) . '.' . $fileExtension;
                $destPath    = $uploadDir . $newFileName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $kategori = 'Deliverable / Code';
                    $stmtFile = $pdo->prepare("INSERT INTO project_files (project_id, task_id, nama_file, kategori, file_path, ukuran_file, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmtFile->execute([$projId, $task_id, $fileName, $kategori, $newFileName, $fileSize, $user_id]);
                }
            }
        }

        // 3. Catat ke Log Aktivitas
        if ($projId) {
            $stmtLog = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe) VALUES (?, ?, ?, ?, ?)");
            $stmtLog->execute([$projId, $user_id, $user_nama, "Memperbarui progress '$taskName' menjadi status: $status", 'info']);
        }

        header("Location: my_tasks.php?msg=updated");
        exit;
    }
}

// Fetch Daftar Tugas Khusus Karyawan Ini (Mendukung Relasi Multi-User task_assignees)
$stmtMyTasks = $pdo->prepare("
    SELECT DISTINCT t.*, p.nama_projek, p.klien 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN task_assignees ta ON t.id = ta.task_id
    WHERE t.user_id = ? OR t.assigned_to = ? OR ta.user_id = ?
    ORDER BY p.nama_projek ASC, FIELD(t.status, 'In Progress', 'Ready for Review', 'To Do', 'Done'), t.deadline ASC
");
$stmtMyTasks->execute([$user_id, $user_id, $user_id]);
$myTasks = $stmtMyTasks->fetchAll();

// Map Anggota Tim Terlibat untuk Setiap Tugas (Transparansi Rekan Tim)
$taskAssigneesMap = [];
$taskIds = array_column($myTasks, 'id');
if (!empty($taskIds)) {
    $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
    $stmtAllAssignees = $pdo->prepare("
        SELECT ta.task_id, u.id AS user_id, u.nama, u.jabatan 
        FROM task_assignees ta
        JOIN users u ON ta.user_id = u.id
        WHERE ta.task_id IN ($inQuery)
    ");
    $stmtAllAssignees->execute($taskIds);
    $rawAssignees = $stmtAllAssignees->fetchAll();

    foreach ($rawAssignees as $ra) {
        $taskAssigneesMap[$ra['task_id']][] = $ra;
    }
}

// Map File Terunggah per Task dari project_files
$taskFilesMap = [];
if (!empty($taskIds)) {
    $inQuery = implode(',', array_fill(0, count($taskIds), '?'));
    $stmtTaskFiles = $pdo->prepare("SELECT * FROM project_files WHERE task_id IN ($inQuery) ORDER BY created_at DESC");
    $stmtTaskFiles->execute($taskIds);
    $rawFiles = $stmtTaskFiles->fetchAll();
    foreach ($rawFiles as $rf) {
        $taskFilesMap[$rf['task_id']][] = $rf;
    }
}

// Kelompokkan Tugas berdasarkan Proyek (Project Sectioning)
$projectsWithTasks = [];
$todayDate = date('Y-m-d');
$countAll = count($myTasks);
$countInProgress = 0;
$countReview = 0;
$countDone = 0;
$countOverdue = 0;

foreach ($myTasks as $tk) {
    $pId = $tk['project_id'];
    if (!isset($projectsWithTasks[$pId])) {
        $projectsWithTasks[$pId] = [
            'project_id'   => $pId,
            'nama_projek'  => $tk['nama_projek'],
            'klien'        => $tk['klien'],
            'tasks'        => []
        ];
    }
    $projectsWithTasks[$pId]['tasks'][] = $tk;

    // Hitung Statistik
    $st = strtolower($tk['status']);
    if ($st === 'in progress') $countInProgress++;
    elseif ($st === 'ready for review') $countReview++;
    elseif ($st === 'done') $countDone++;

    if (strtotime($tk['deadline']) < strtotime($todayDate) && $st !== 'done') {
        $countOverdue++;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Penugasan Tim - PT. CTC</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom scrollbar halus */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Stat cards hover */
        .stat-card {
            transition: transform .25s cubic-bezier(.22,.68,0,1), box-shadow .25s ease, border-color .25s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 22px -10px rgba(15,23,42,.12);
            border-color: rgba(16,185,129,.4);
        }

        /* Buttons & Tabs */
        .btn-press { transition: transform .15s ease, background-color .2s ease; }
        .btn-press:active { transform: scale(.96); }

        .status-tab { transition: all .2s ease; }
        .status-tab.active-tab {
            background: #065f46;
            color: #ffffff;
            box-shadow: 0 4px 12px -3px rgba(5,150,105,.4);
        }

        /* Inputs */
        .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
        .input-glow:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16,185,129,.15);
        }

        /* Chevron rotasi animasi */
        .chevron-icon { transition: transform .25s cubic-bezier(.22,.68,0,1); }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white">

    <!-- ================= TOPBAR ================= -->
    <header class="sticky top-0 z-50 bg-gradient-to-r from-emerald-950 via-emerald-900 to-emerald-950 text-white shadow-lg border-b border-emerald-800/60 backdrop-blur">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 flex justify-between items-center gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 shrink-0 bg-gradient-to-br from-emerald-400 to-emerald-600 text-white rounded-xl flex items-center justify-center shadow-md">
                    <i class="fa-solid fa-list-check text-sm"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="font-black text-[13px] sm:text-sm tracking-wide leading-tight truncate">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <span class="text-[11px] text-emerald-300/90 font-medium hidden sm:block">Workspace Penugasan Tim Teknis</span>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <div class="hidden md:flex items-center gap-2.5 pl-1 pr-3 py-1.5 bg-white/10 border border-white/15 rounded-xl">
                    <div class="w-7 h-7 rounded-lg bg-emerald-500/30 border border-emerald-400/30 flex items-center justify-center text-[11px] font-black text-emerald-200 uppercase">
                        <?= strtoupper(substr($user_nama, 0, 1)) ?>
                    </div>
                    <div class="leading-tight">
                        <span class="block text-[11px] font-bold text-white"><?= htmlspecialchars($user_nama) ?></span>
                        <span class="block text-[9px] text-emerald-300/80 uppercase font-bold tracking-wider"><?= htmlspecialchars($user_role) ?></span>
                    </div>
                </div>

                <?php if (strtolower($user_role) === 'admin'): ?>
                    <a href="modules/projects/index.php" class="btn-press text-[11px] bg-white/10 hover:bg-white/20 border border-white/20 text-white px-3 py-2 rounded-xl flex items-center gap-1.5 font-bold">
                        <i class="fa-solid fa-gauge text-emerald-300"></i><span class="hidden sm:inline">Panel Admin</span>
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn-press text-[11px] bg-red-500/80 hover:bg-red-500 text-white px-3 py-2 rounded-xl flex items-center gap-1.5 shadow-sm font-bold">
                    <i class="fa-solid fa-right-from-bracket"></i><span class="hidden sm:inline">Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

        <!-- ================= PAGE HEADER & LIVE SEARCH ================= -->
        <div class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">Workspace Tim</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight">Workload & Penugasan Saya</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Klik banner proyek di bawah untuk membuka detail modul tugas & kirim progress.</p>
            </div>

            <!-- Global Live Search Bar -->
            <div class="relative w-full md:w-72">
                <input type="text" id="globalTaskSearch" onkeyup="applyCombinedFilters()" placeholder="Cari nama modul / proyek..." class="input-glow w-full px-3.5 py-2.5 pl-9 rounded-xl border border-slate-200 text-xs font-semibold focus:outline-none bg-white shadow-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
            </div>
        </div>

        <!-- ================= STAT CARDS ================= -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="stat-card bg-white rounded-2xl border border-slate-200/80 p-4 shadow-sm flex items-center gap-3.5 cursor-default">
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-black text-slate-900 leading-none"><?= $countAll ?></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 mt-1">Total Tugas</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-2xl border border-blue-200/70 p-4 shadow-sm flex items-center gap-3.5 cursor-default">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-black text-blue-700 leading-none"><?= $countInProgress ?></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-blue-400 mt-1">Dikerjakan</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-2xl border border-purple-200/70 p-4 shadow-sm flex items-center gap-3.5 cursor-default">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-black text-purple-700 leading-none"><?= $countReview ?></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-purple-400 mt-1">Siap QC</span>
                </div>
            </div>

            <?php if ($countOverdue > 0): ?>
            <div class="stat-card bg-white rounded-2xl border border-red-300 p-4 shadow-sm flex items-center gap-3.5 cursor-default">
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-black text-red-700 leading-none"><?= $countOverdue ?></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-red-400 mt-1">Terlambat</span>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card bg-white rounded-2xl border border-emerald-200/70 p-4 shadow-sm flex items-center gap-3.5 cursor-default">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-black text-emerald-700 leading-none"><?= $countDone ?></span>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-emerald-400 mt-1">Selesai</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================= ALERT NOTIFICATION ================= -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 text-[11px]">
                    <i class="fa-solid fa-check"></i>
                </span>
                Progress pengerjaan & berkas berhasil dikirimkan ke Admin untuk ditinjau (QC)!
            </div>
        <?php endif; ?>

        <!-- ================= STATUS FILTER TABS ================= -->
        <div id="filterTabs" class="bg-white border border-slate-200/90 rounded-2xl p-1.5 mb-6 shadow-sm flex items-center gap-1 overflow-x-auto">
            <button onclick="filterStatus('all')" class="status-tab active-tab px-4 py-2 rounded-xl text-[11px] font-extrabold whitespace-nowrap text-slate-500 hover:bg-slate-100" data-status-tab="all">
                Semua (<?= $countAll ?>)
            </button>
            <button onclick="filterStatus('in_progress')" class="status-tab px-4 py-2 rounded-xl text-[11px] font-extrabold whitespace-nowrap text-slate-500 hover:bg-slate-100" data-status-tab="in_progress">
                <i class="fa-solid fa-spinner mr-1 text-blue-600"></i>In Progress (<?= $countInProgress ?>)
            </button>
            <button onclick="filterStatus('ready_for_review')" class="status-tab px-4 py-2 rounded-xl text-[11px] font-extrabold whitespace-nowrap text-slate-500 hover:bg-slate-100" data-status-tab="ready_for_review">
                <i class="fa-solid fa-magnifying-glass-chart mr-1 text-purple-600"></i>Siap QC (<?= $countReview ?>)
            </button>
            <button onclick="filterStatus('overdue')" class="status-tab px-4 py-2 rounded-xl text-[11px] font-extrabold whitespace-nowrap text-slate-500 hover:bg-slate-100" data-status-tab="overdue">
                <i class="fa-solid fa-triangle-exclamation mr-1 text-red-500"></i>Terlambat (<?= $countOverdue ?>)
            </button>
            <button onclick="filterStatus('done')" class="status-tab px-4 py-2 rounded-xl text-[11px] font-extrabold whitespace-nowrap text-slate-500 hover:bg-slate-100" data-status-tab="done">
                <i class="fa-solid fa-circle-check mr-1 text-emerald-500"></i>Selesai (<?= $countDone ?>)
            </button>
        </div>

        <!-- ================= LIST PROYEK (ACCORDION STYLE: RINGKAS & CLEAN) ================= -->
        <div class="space-y-4" id="projectsContainer">
            <?php if (empty($projectsWithTasks)): ?>
                <div class="bg-white border border-slate-200/90 rounded-2xl p-12 text-center shadow-sm">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <p class="font-bold text-slate-800 text-sm">Tidak ada modul tugas yang dialokasikan untuk Anda saat ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($projectsWithTasks as $pId => $projectGroup): ?>

                    <!-- WRAPPER ACCORDION PROYEK -->
                    <div class="project-section bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden" data-project-id="<?= $pId ?>">

                        <!-- BANNER HEADER PROYEK (KLIK UNTUK BUKA/TUTUP DETAIL) -->
                        <div onclick="toggleAccordion('project-content-<?= $pId ?>', 'project-chevron-<?= $pId ?>')" 
                             class="p-4 sm:p-5 bg-slate-900 text-white cursor-pointer hover:bg-slate-800/95 transition flex items-center justify-between gap-4 select-none">
                            
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 flex items-center justify-center text-base shrink-0">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="inline-block text-[9px] font-black uppercase tracking-[0.18em] text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-md border border-emerald-500/20 mb-1">
                                        Proyek
                                    </span>
                                    <h3 class="text-sm sm:text-base font-extrabold text-white tracking-wide truncate">
                                        <?= htmlspecialchars($projectGroup['nama_projek']) ?>
                                    </h3>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5 shrink-0">
                                <div class="bg-white/5 border border-white/10 text-slate-300 text-[11px] px-3 py-1.5 rounded-lg font-semibold hidden sm:flex items-center gap-2">
                                    <i class="fa-solid fa-building text-emerald-400 text-[10px]"></i>
                                    <span class="text-slate-400">Klien:</span> <strong class="text-white"><?= htmlspecialchars($projectGroup['klien']) ?></strong>
                                </div>

                                <div class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] px-3 py-1.5 rounded-lg font-bold">
                                    <?= count($projectGroup['tasks']) ?> Modul
                                </div>

                                <div class="w-7 h-7 rounded-lg bg-white/10 flex items-center justify-center text-slate-300 text-xs chevron-icon" id="project-chevron-<?= $pId ?>">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <!-- BODY DETAIL TUGAS/MODUL (DEFAULT TERSEMBUNYI) -->
                        <div id="project-content-<?= $pId ?>" class="hidden p-4 sm:p-5 border-t border-slate-100 bg-slate-50/50 space-y-4">
                            <?php foreach ($projectGroup['tasks'] as $task): ?>
                                <?php
                                    $stLower = strtolower($task['status']);
                                    $statusClassKey = str_replace(' ', '_', $stLower);
                                    $isOverdue = (strtotime($task['deadline']) < strtotime($todayDate)) && $stLower !== 'done';
                                    $isDone = ($stLower === 'done');
                                    $hasFiles = isset($taskFilesMap[$task['id']]) && !empty($taskFilesMap[$task['id']]);
                                    $teamMembers = $taskAssigneesMap[$task['id']] ?? [];

                                    $accentClass = 'bg-slate-300';
                                    if ($stLower === 'in progress')       $accentClass = 'bg-blue-500';
                                    elseif ($stLower === 'ready for review') $accentClass = 'bg-purple-500';
                                    elseif ($stLower === 'done')          $accentClass = 'bg-emerald-500';
                                ?>

                                <div class="task-card bg-white border border-slate-200/90 rounded-2xl shadow-sm overflow-hidden"
                                     data-status="<?= $statusClassKey ?>"
                                     data-overdue="<?= $isOverdue ? 'true' : 'false' ?>"
                                     data-search-title="<?= htmlspecialchars(strtolower($task['nama_tugas'] . ' ' . $projectGroup['nama_projek'])) ?>">

                                    <div class="flex">
                                        <div class="w-1.5 shrink-0 <?= $accentClass ?> <?= $isOverdue ? '!bg-red-500' : '' ?>"></div>

                                        <div class="flex-1 p-4 sm:p-5">
                                            <!-- Header Card -->
                                            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-3 mb-4">
                                                <h4 class="text-sm sm:text-base font-extrabold text-slate-900 leading-snug flex-1">
                                                    <?= htmlspecialchars($task['nama_tugas']) ?>
                                                </h4>

                                                <!-- Badges -->
                                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                                    <div class="px-3 py-1 rounded-lg text-[11px] font-extrabold flex items-center gap-1.5 border
                                                        <?php
                                                            if ($isOverdue) {
                                                                echo 'bg-red-600 text-white border-red-700';
                                                            } elseif ($isDone) {
                                                                echo 'bg-slate-100 text-slate-500 border-slate-200';
                                                            } else {
                                                                echo 'bg-amber-50 text-amber-800 border-amber-300';
                                                            }
                                                        ?>">
                                                        <i class="fa-regular fa-calendar-check"></i>
                                                        <span><?= date('d M Y', strtotime($task['deadline'])) ?></span>
                                                        <?php if ($isOverdue): ?>
                                                            <span class="text-[9px] uppercase font-black bg-white text-red-700 px-1.5 py-0.5 rounded">Terlambat</span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <span class="px-2.5 py-1 text-[9px] uppercase font-black rounded-lg border tracking-wide
                                                        <?php
                                                            switch($task['prioritas']) {
                                                                case 'Urgent':
                                                                case 'High': echo 'bg-red-50 text-red-600 border-red-200'; break;
                                                                case 'Medium': echo 'bg-amber-50 text-amber-600 border-amber-200'; break;
                                                                default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                                            }
                                                        ?>">
                                                        <?= htmlspecialchars($task['prioritas']) ?>
                                                    </span>

                                                    <span class="px-2.5 py-1 text-[9px] uppercase font-black rounded-lg border tracking-wide
                                                        <?php
                                                            switch($task['status']) {
                                                                case 'Done': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                                                case 'Ready for Review': echo 'bg-purple-50 text-purple-700 border-purple-200'; break;
                                                                case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                                                default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                                            }
                                                        ?>">
                                                        <?= htmlspecialchars($task['status']) ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Anggota Tim Pengerjaan -->
                                            <?php if (!empty($teamMembers)): ?>
                                                <div class="mb-4 bg-slate-50/90 border border-slate-200/80 rounded-xl p-3">
                                                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400 block mb-1.5">
                                                        <i class="fa-solid fa-users text-emerald-600 mr-1"></i> Tim Terlibat Pengerjaan Modul Ini:
                                                    </span>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <?php foreach ($teamMembers as $tm): ?>
                                                            <span class="px-2.5 py-1 bg-white rounded-lg border border-slate-200 text-[11px] font-bold text-slate-700 flex items-center gap-1.5 shadow-sm">
                                                                <span class="w-4 h-4 rounded-full bg-emerald-100 text-emerald-800 text-[9px] font-black flex items-center justify-center">
                                                                    <?= strtoupper(substr($tm['nama'], 0, 1)) ?>
                                                                </span>
                                                                <?= htmlspecialchars($tm['nama']) ?>
                                                                <?php if ($tm['user_id'] == $user_id): ?>
                                                                    <span class="text-[9px] bg-emerald-100 text-emerald-800 px-1 rounded font-extrabold">(Anda)</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Catatan Revisi dari PM -->
                                            <?php if (!empty($task['revision_notes'])): ?>
                                                <div class="bg-red-50 border border-red-200 rounded-xl p-3.5 mb-4 text-xs flex gap-2.5">
                                                    <i class="fa-solid fa-triangle-exclamation text-red-500 mt-0.5 shrink-0"></i>
                                                    <div>
                                                        <span class="font-extrabold block text-red-800 mb-0.5">Catatan Instruksi Revisi PM:</span>
                                                        <p class="leading-relaxed font-semibold text-red-900"><?= htmlspecialchars($task['revision_notes']) ?></p>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Berkas Terlampir -->
                                            <?php if ($hasFiles || !empty($task['drive_link'])): ?>
                                                <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3.5 mb-4">
                                                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-slate-400 block mb-2">Deliverables / Berkas Terlampir</span>
                                                    <div class="flex flex-wrap gap-2">
                                                        <?php if (!empty($task['drive_link'])): ?>
                                                            <a href="<?= htmlspecialchars($task['drive_link']) ?>" target="_blank"
                                                                class="px-3 py-1.5 bg-white text-emerald-700 hover:bg-emerald-50 rounded-lg border border-emerald-200 text-[11px] font-bold flex items-center gap-1.5 shadow-sm transition">
                                                                <i class="fa-brands fa-google-drive text-emerald-600"></i> Google Drive / Repo
                                                            </a>
                                                        <?php endif; ?>

                                                        <?php if ($hasFiles): ?>
                                                            <?php foreach ($taskFilesMap[$task['id']] as $file): ?>
                                                                <a href="uploads/projects/<?= htmlspecialchars($file['file_path']) ?>" download="<?= htmlspecialchars($file['nama_file']) ?>"
                                                                    class="px-3 py-1.5 bg-white text-slate-700 hover:bg-slate-50 rounded-lg border border-slate-200 text-[11px] font-bold flex items-center gap-1.5 shadow-sm max-w-full transition">
                                                                    <i class="fa-solid fa-file-arrow-down text-slate-400 shrink-0"></i>
                                                                    <span class="truncate max-w-[140px] sm:max-w-[200px]"><?= htmlspecialchars($file['nama_file']) ?></span>
                                                                    <span class="text-[9px] text-slate-400 font-mono shrink-0">(<?= round($file['ukuran_file'] / 1024, 1) ?> KB)</span>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Form Update / Read Only -->
                                            <?php if ($isDone): ?>
                                                <div class="bg-emerald-50/80 border border-emerald-200 rounded-xl p-4 flex items-center justify-between gap-3">
                                                    <span class="text-xs font-bold text-emerald-800 flex items-center gap-2">
                                                        <i class="fa-solid fa-circle-check text-emerald-600"></i>
                                                        Pengerjaan modul telah disetujui (Approved) oleh Project Manager.
                                                    </span>
                                                    <span class="text-[9px] bg-emerald-200/80 font-black px-2.5 py-1 rounded-lg text-emerald-900 uppercase shrink-0">Read Only</span>
                                                </div>
                                            <?php else: ?>
                                                <form action="" method="POST" enctype="multipart/form-data" class="bg-slate-50/80 rounded-xl border border-slate-200/70 p-4 space-y-3.5">
                                                    <input type="hidden" name="update_progress" value="1">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">

                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <label class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400 mb-1.5">Status Pengerjaan</label>
                                                            <select name="status" class="input-glow w-full px-3 py-2.5 rounded-lg border border-slate-200 text-xs bg-white focus:outline-none font-bold text-slate-700">
                                                                <option value="To Do" <?= $task['status'] === 'To Do' ? 'selected' : '' ?>>To Do (Belum Dimulai)</option>
                                                                <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress (Sedang Dikerjakan)</option>
                                                                <option value="Ready for Review" <?= $task['status'] === 'Ready for Review' ? 'selected' : '' ?>>Ready for Review (Siap Di-QC)</option>
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400 mb-1.5">Link Google Drive / Repo</label>
                                                            <div class="relative">
                                                                <input type="url" name="drive_link" value="<?= htmlspecialchars($task['drive_link'] ?? '') ?>"
                                                                    placeholder="https://drive.google.com/..."
                                                                    class="input-glow w-full px-3 py-2.5 pl-8 rounded-lg border border-slate-200 text-xs focus:outline-none font-mono text-emerald-700 bg-white">
                                                                <i class="fa-brands fa-google-drive absolute left-2.5 top-3 text-slate-400 text-xs"></i>
                                                            </div>
                                                        </div>

                                                        <div>
                                                            <label class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400 mb-1.5">Upload File Deliverable</label>
                                                            <input type="file" name="task_file"
                                                                class="w-full px-2 py-1.5 border border-slate-200 rounded-lg text-[11px] text-slate-500 bg-white file:mr-2 file:py-1.5 file:px-2.5 file:rounded-md file:border-0 file:text-[10px] file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-[9px] font-extrabold uppercase tracking-wider text-slate-400 mb-1.5">Catatan Laporan Progress</label>
                                                        <textarea name="progress_notes" rows="2"
                                                            placeholder="Tuliskan ringkasan hasil kerja atau instruksi untuk Admin..."
                                                            class="input-glow w-full px-3 py-2.5 rounded-lg border border-slate-200 text-xs focus:outline-none bg-white font-medium"><?= htmlspecialchars($task['progress_notes'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="flex justify-end pt-0.5">
                                                        <button type="submit"
                                                            class="btn-press px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold shadow-md shadow-emerald-600/20 flex items-center gap-2">
                                                            <i class="fa-solid fa-paper-plane text-[10px]"></i> Kirim Progress & Deliverables
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>

                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <footer class="mt-14 pb-6 text-center">
            <p class="text-[10px] text-slate-400 font-medium">PT. Cipta Teknologi Cendekia &mdash; Workspace Penugasan Tim Teknis</p>
        </footer>

    </main>

    <script>
        let currentStatusFilter = 'all';

        // Toggle Accordion Buka / Tutup Proyek
        function toggleAccordion(contentId, chevronId) {
            const content = document.getElementById(contentId);
            const chevron = document.getElementById(chevronId);

            if (content.classList.contains('hidden')) {
                content.classList.remove('hidden');
                chevron.classList.add('rotate-180');
            } else {
                content.classList.add('hidden');
                chevron.classList.remove('rotate-180');
            }
        }

        function filterStatus(selectedStatusKey) {
            currentStatusFilter = selectedStatusKey;

            document.querySelectorAll('.status-tab').forEach(tab => {
                if (tab.getAttribute('data-status-tab') === selectedStatusKey) {
                    tab.classList.add('active-tab');
                    tab.classList.remove('text-slate-500');
                } else {
                    tab.classList.remove('active-tab');
                    tab.classList.add('text-slate-500');
                }
            });

            applyCombinedFilters();
        }

        function applyCombinedFilters() {
            const searchVal = document.getElementById('globalTaskSearch').value.toLowerCase();

            document.querySelectorAll('.project-section').forEach(sec => {
                const cards = sec.querySelectorAll('.task-card');
                let visibleCount = 0;

                cards.forEach(card => {
                    const cardStatus = card.getAttribute('data-status');
                    const isOverdue = card.getAttribute('data-overdue') === 'true';
                    const cardTitle = card.getAttribute('data-search-title') || '';

                    let isStatusMatch = false;
                    if (currentStatusFilter === 'all') {
                        isStatusMatch = true;
                    } else if (currentStatusFilter === 'overdue') {
                        isStatusMatch = isOverdue;
                    } else {
                        isStatusMatch = (cardStatus === currentStatusFilter);
                    }

                    const isSearchMatch = cardTitle.includes(searchVal);

                    if (isStatusMatch && isSearchMatch) {
                        card.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        card.classList.add('hidden');
                    }
                });

                // Otomatis buka accordion jika user sedang melakukan pencarian
                const contentId = 'project-content-' + sec.getAttribute('data-project-id');
                const chevronId = 'project-chevron-' + sec.getAttribute('data-project-id');
                const content = document.getElementById(contentId);
                const chevron = document.getElementById(chevronId);

                if (visibleCount > 0) {
                    sec.classList.remove('hidden');
                    if (searchVal.trim() !== '') {
                        content.classList.remove('hidden');
                        if (chevron) chevron.classList.add('rotate-180');
                    }
                } else {
                    sec.classList.add('hidden');
                }
            });
        }
    </script>
</body>
</html>