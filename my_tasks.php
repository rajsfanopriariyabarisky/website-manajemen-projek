<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = $_SESSION['user_id'];
$user_nama = $_SESSION['nama'] ?? 'Karyawan';
$user_role = $_SESSION['role'] ?? 'Karyawan';

$pwd_error   = '';
$pwd_success = '';

// Handle Ganti Password Mandiri Karyawan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password_action'])) {
    $old_password     = $_POST['old_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (!empty($old_password) && !empty($new_password) && !empty($confirm_password)) {
        if ($new_password !== $confirm_password) {
            $pwd_error = "Konfirmasi password baru tidak cocok! Harap periksa kembali.";
        } elseif (strlen($new_password) < 6) {
            $pwd_error = "Password baru minimal terdiri dari 6 karakter!";
        } else {
            // Fetch password user saat ini dari database
            $stmtUser = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $stmtUser->execute([$user_id]);
            $uData = $stmtUser->fetch();

            if ($uData && (password_verify($old_password, $uData['password']) || md5($old_password) === $uData['password'] || $old_password === $uData['password'])) {
                $hashed_new = password_hash($new_password, PASSWORD_BCRYPT);
                $stmtUpdatePwd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtUpdatePwd->execute([$hashed_new, $user_id]);

                $pwd_success = "Password akun Anda berhasil diperbarui secara aman!";
            } else {
                $pwd_error = "Password lama yang Anda masukkan salah!";
            }
        }
    } else {
        $pwd_error = "Mohon lengkapi seluruh kolom untuk mengganti password.";
    }
}

// Handle Update Progress + Upload File dari Karyawan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    $task_id        = $_POST['task_id'];
    $status         = $_POST['status'];
    $progress_notes = trim($_POST['progress_notes']);
    $drive_link     = trim($_POST['drive_link']);

    // Validasi Tugas Karyawan
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

// Fetch Daftar Tugas Khusus Karyawan Ini
$stmtMyTasks = $pdo->prepare("
    SELECT DISTINCT t.*, p.nama_projek, p.klien 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN task_assignees ta ON t.id = ta.task_id
    WHERE t.user_id = ? OR t.assigned_to = ? OR ta.user_id = ?
    ORDER BY p.nama_projek ASC, FIELD(t.prioritas, 'Urgent', 'High', 'Medium', 'Low'), FIELD(t.status, 'In Progress', 'Ready for Review', 'To Do', 'Done'), t.deadline ASC
");
$stmtMyTasks->execute([$user_id, $user_id, $user_id]);
$myTasks = $stmtMyTasks->fetchAll();

// Map Anggota Tim Terlibat
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

// Map File Terunggah per Task
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

// Kelompokkan Tugas berdasarkan Proyek & Hitung Statistik
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
            'tasks'        => [],
            'urgent_count' => 0 // Counter tugas urgent per proyek
        ];
    }
    $projectsWithTasks[$pId]['tasks'][] = $tk;

    $st = strtolower($tk['status']);
    $prio = strtolower($tk['prioritas']);

    if ($st === 'in progress') $countInProgress++;
    elseif ($st === 'ready for review') $countReview++;
    elseif ($st === 'done') $countDone++;

    if (strtotime($tk['deadline']) < strtotime($todayDate) && $st !== 'done') {
        $countOverdue++;
    }

    // Hitung berapa tugas URGENT/HIGH/DEADLINE HARI INI yang belum selesai di proyek ini
    if ($st !== 'done' && $st !== 'ready for review') {
        if ($prio === 'urgent' || $prio === 'high' || $tk['deadline'] === $todayDate) {
            $projectsWithTasks[$pId]['urgent_count']++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Penugasan Tim - PT. CTC</title>
    
    <!-- FAVICON LOGO CTC -->
    <link rel="icon" type="image/png" href="assets/images/logo2.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/logo2.png">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body, input, select, textarea, button { 
            font-family: 'Plus Jakarta Sans', sans-serif !important; 
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* INTERAKTIF & INTERACTION ANIMATIONS (HARDWARE ACCELERATED - ZERO LAG) */
        .btn-press { 
            transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), 
                        background-color 0.18s ease, 
                        border-color 0.18s ease, 
                        box-shadow 0.18s ease,
                        color 0.18s ease; 
            will-change: transform;
        }
        .btn-press:hover { 
            transform: translateY(-1.5px); 
        }
        .btn-press:active { 
            transform: translateY(0.5px) scale(0.98); 
        }

        .stat-card {
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), 
                        box-shadow 0.2s ease, 
                        border-color 0.2s ease;
            will-change: transform;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -8px rgba(15,23,42,0.08);
            border-color: rgba(16,185,129,0.35);
        }

        .status-tab { 
            transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1); 
            will-change: transform;
        }
        .status-tab:hover:not(.active-tab) {
            background-color: #f1f5f9;
            transform: translateY(-1px);
        }
        .status-tab.active-tab {
            background: #059669;
            color: #ffffff;
            box-shadow: 0 3px 10px -2px rgba(5,150,105,0.35);
        }

        .input-glow:focus { 
            border-color: #059669; 
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
        }

        .chevron-icon { transition: transform .25s ease; }

        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: pageFadeIn 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes bellRing {
            0%, 100% { transform: rotate(0deg); }
            20%, 60% { transform: rotate(12deg); }
            40%, 80% { transform: rotate(-12deg); }
        }
        .bell-shake { animation: bellRing 1.2s infinite ease-in-out; display: inline-block; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 min-h-screen selection:bg-emerald-600 selection:text-white relative overflow-x-hidden antialiased">

    <!-- WATERMARK LOGO CTC DI BACKGROUND -->
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="assets/images/logo2.png" alt="CTC Watermark" class="w-[600px] max-w-[85vw] h-auto object-contain opacity-[0.04] mix-blend-multiply select-none">
    </div>

    <!-- TOPBAR HEADER CLEAN & INTERAKTIF -->
    <header class="sticky top-0 z-40 bg-slate-900/95 text-white border-b border-slate-800 shadow-md backdrop-blur-md">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2.5 flex justify-between items-center gap-3">
            
            <!-- Logo PNG Transparan Tanpa Box Border -->
            <div class="flex items-center gap-3 min-w-0">
                <img src="assets/images/logo2.png" 
                     alt="Logo PT. CTC" 
                     class="h-9 sm:h-10 w-auto object-contain shrink-0 transition-transform duration-200 hover:scale-105 cursor-pointer"
                     onerror="this.onerror=null; this.src='assets/img/logo2.png';">
                <div class="min-w-0">
                    <h1 class="font-bold text-xs sm:text-sm tracking-tight truncate text-slate-100">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <span class="text-[10px] text-emerald-400 font-medium block">Workspace Penugasan Tim Teknis</span>
                </div>
            </div>

            <!-- Profile Info & Tombol Aksi -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                
                <!-- Badge Identitas User -->
                <div class="hidden md:flex items-center gap-2.5 pl-1.5 pr-3.5 py-1 bg-slate-800/80 border border-slate-700/60 rounded-xl transition hover:border-slate-600">
                    <div class="w-6 h-6 rounded-lg bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-[10px] font-bold text-emerald-300 uppercase">
                        <?= strtoupper(substr($user_nama, 0, 1)) ?>
                    </div>
                    <div class="leading-tight">
                        <span class="block text-[11px] font-semibold text-slate-100"><?= htmlspecialchars($user_nama) ?></span>
                        <span class="block text-[9px] text-slate-400 uppercase font-medium tracking-wider"><?= htmlspecialchars($user_role) ?></span>
                    </div>
                </div>

                <!-- Tombol Ganti Password Mandiri -->
                <button onclick="openPwdModal()" class="btn-press text-xs bg-slate-800 hover:bg-slate-700 hover:text-emerald-400 border border-slate-700/80 text-slate-200 px-3 py-1.5 rounded-xl flex items-center gap-1.5 font-medium shadow-2xs">
                    <i class="fa-solid fa-key text-emerald-400 text-xs"></i><span class="hidden sm:inline">Ganti Password</span>
                </button>

                <?php if (strtolower($user_role) === 'admin'): ?>
                    <a href="modules/projects/index.php" class="btn-press text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded-xl flex items-center gap-1.5 font-semibold shadow-xs">
                        <i class="fa-solid fa-gauge text-xs"></i><span class="hidden sm:inline">Panel Admin</span>
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn-press text-xs bg-red-500/10 hover:bg-red-600 hover:text-white text-red-400 border border-red-500/20 hover:border-red-600 px-3 py-1.5 rounded-xl flex items-center gap-1.5 font-medium shadow-2xs">
                    <i class="fa-solid fa-right-from-bracket text-xs"></i><span class="hidden sm:inline">Keluar</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 relative z-10 animate-fade-in">

        <!-- PAGE HEADER & SEARCH BAR -->
        <div class="mb-5 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-[10px] font-bold uppercase tracking-widest text-emerald-800 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full inline-block">
                        WORKSPACE TIM
                    </span>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Workload & Penugasan Saya</h2>
                <p class="text-xs text-slate-500 font-normal mt-0.5">Klik banner proyek di bawah untuk membuka detail modul tugas & kirim progress.</p>
            </div>

            <!-- Search Bar -->
            <div class="relative w-full md:w-72">
                <input type="text" id="globalTaskSearch" onkeyup="applyCombinedFilters()" placeholder="Cari modul atau proyek..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-300 bg-white text-xs text-slate-900 font-normal focus:outline-none placeholder:text-slate-400 shadow-xs transition-all">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            </div>
        </div>

        <!-- NOTIFIKASI HASIL GANTI PASSWORD -->
        <?php if ($pwd_error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs font-medium mb-5 flex items-center gap-2.5 shadow-xs">
                <i class="fa-solid fa-circle-exclamation text-base text-red-500 shrink-0"></i>
                <div><?= $pwd_error ?></div>
            </div>
        <?php endif; ?>

        <?php if ($pwd_success): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium mb-5 flex items-center gap-2.5 shadow-xs">
                <i class="fa-solid fa-circle-check text-base text-emerald-600 shrink-0"></i>
                <div><?= $pwd_success ?></div>
            </div>
        <?php endif; ?>

        <!-- STAT CARDS INTERAKTIF -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
            <div class="stat-card bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-bold text-slate-900 leading-none"><?= $countAll ?></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mt-1">Total Tugas</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-spinner"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-bold text-blue-600 leading-none"><?= $countInProgress ?></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-blue-500 mt-1">Dikerjakan</span>
                </div>
            </div>

            <div class="stat-card bg-white rounded-2xl border border-slate-200/90 p-4 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-bold text-purple-600 leading-none"><?= $countReview ?></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-purple-500 mt-1">Siap QC</span>
                </div>
            </div>

            <?php if ($countOverdue > 0): ?>
            <div class="stat-card bg-white rounded-2xl border border-red-200 p-4 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-bold text-red-600 leading-none"><?= $countOverdue ?></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-red-500 mt-1">Terlambat</span>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card bg-white rounded-2xl border border-emerald-200/80 p-4 shadow-xs flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm shrink-0">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="min-w-0">
                    <span class="block text-xl font-bold text-emerald-600 leading-none"><?= $countDone ?></span>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-emerald-600 mt-1">Selesai</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- ALERT UPDATE STATUS -->
        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'updated'): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs font-medium mb-5 flex items-center gap-2.5 shadow-xs">
                <i class="fa-solid fa-circle-check text-base text-emerald-600 shrink-0"></i>
                Progress pengerjaan & berkas berhasil dikirimkan ke Admin untuk ditinjau (QC)!
            </div>
        <?php endif; ?>

        <!-- FILTER TABS -->
        <div id="filterTabs" class="bg-white border border-slate-200/90 rounded-2xl p-1.5 mb-5 shadow-xs flex items-center gap-1 overflow-x-auto">
            <button onclick="filterStatus('all')" class="status-tab active-tab px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap text-slate-600" data-status-tab="all">
                Semua (<?= $countAll ?>)
            </button>
            <button onclick="filterStatus('in_progress')" class="status-tab px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap text-slate-600" data-status-tab="in_progress">
                <i class="fa-solid fa-spinner mr-1 text-blue-600"></i>In Progress (<?= $countInProgress ?>)
            </button>
            <button onclick="filterStatus('ready_for_review')" class="status-tab px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap text-slate-600" data-status-tab="ready_for_review">
                <i class="fa-solid fa-magnifying-glass-chart mr-1 text-purple-600"></i>Siap QC (<?= $countReview ?>)
            </button>
            <button onclick="filterStatus('overdue')" class="status-tab px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap text-slate-600" data-status-tab="overdue">
                <i class="fa-solid fa-triangle-exclamation mr-1 text-red-500"></i>Terlambat (<?= $countOverdue ?>)
            </button>
            <button onclick="filterStatus('done')" class="status-tab px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap text-slate-600" data-status-tab="done">
                <i class="fa-solid fa-circle-check mr-1 text-emerald-600"></i>Selesai (<?= $countDone ?>)
            </button>
        </div>

        <!-- LIST PROYEK ACCORDION CONTAINER -->
        <div class="space-y-4" id="projectsContainer">
            <?php if (empty($projectsWithTasks)): ?>
                <div class="bg-white border border-slate-200/90 rounded-2xl p-10 text-center shadow-xs">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <p class="font-semibold text-slate-700 text-xs sm:text-sm">Tidak ada modul tugas yang dialokasikan untuk Anda saat ini.</p>
                </div>
            <?php else: ?>
                <?php foreach ($projectsWithTasks as $pId => $projectGroup): ?>

                    <div class="project-section bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden" data-project-id="<?= $pId ?>">

                        <!-- ACCORDION HEADER BANNER -->
                        <div onclick="toggleAccordion('project-content-<?= $pId ?>', 'project-chevron-<?= $pId ?>')" 
                             class="p-4 sm:p-5 bg-slate-900 text-white cursor-pointer hover:bg-slate-800 transition-colors flex items-center justify-between gap-4 select-none">
                            
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-9 h-9 rounded-xl bg-white/10 text-emerald-400 flex items-center justify-center text-sm shrink-0 border border-white/10">
                                    <i class="fa-solid fa-folder-open"></i>
                                </div>
                                <div class="min-w-0">
                                    <span class="inline-block text-[9px] font-bold uppercase tracking-widest text-emerald-400 mb-0.5">
                                        PROYEK TIM
                                    </span>
                                    <h3 class="text-xs sm:text-sm font-bold text-white tracking-tight truncate">
                                        <?= htmlspecialchars($projectGroup['nama_projek']) ?>
                                    </h3>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 sm:gap-2.5 shrink-0">
                                <!-- BADGE LONCENG NOTIFIKASI URGENT -->
                                <?php if ($projectGroup['urgent_count'] > 0): ?>
                                    <span class="bg-red-600 text-white text-[10px] font-bold px-2.5 py-1 rounded-lg border border-red-500/50 flex items-center gap-1.5 shadow-xs" title="Ada <?= $projectGroup['urgent_count'] ?> tugas urgent/deadline hari ini!">
                                        <i class="fa-solid fa-bell bell-shake text-amber-300 text-[10px]"></i>
                                        <span>+<?= $projectGroup['urgent_count'] ?> Urgent</span>
                                    </span>
                                <?php endif; ?>

                                <div class="bg-white/5 border border-white/10 text-slate-300 text-[11px] px-2.5 py-1 rounded-lg font-medium hidden sm:flex items-center gap-1.5">
                                    <i class="fa-solid fa-building text-emerald-400 text-[10px]"></i>
                                    <span class="text-slate-400">Klien:</span> <strong class="text-white font-semibold"><?= htmlspecialchars($projectGroup['klien']) ?></strong>
                                </div>

                                <div class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 text-[11px] px-2.5 py-1 rounded-lg font-bold">
                                    <?= count($projectGroup['tasks']) ?> Modul
                                </div>

                                <div class="w-6 h-6 rounded-lg bg-white/10 flex items-center justify-center text-slate-300 text-xs chevron-icon" id="project-chevron-<?= $pId ?>">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <!-- BODY DETAIL TUGAS -->
                        <div id="project-content-<?= $pId ?>" class="hidden p-4 sm:p-5 border-t border-slate-100 bg-slate-50/50 space-y-4">
                            <?php foreach ($projectGroup['tasks'] as $task): ?>
                                <?php
                                    $stLower   = strtolower($task['status']);
                                    $prioLower = strtolower($task['prioritas']);
                                    $statusClassKey = str_replace(' ', '_', $stLower);
                                    
                                    $isDone    = ($stLower === 'done');
                                    $isPending = ($stLower !== 'done' && $stLower !== 'ready for review'); 
                                    $isOverdue = (strtotime($task['deadline']) < strtotime($todayDate)) && !$isDone;
                                    
                                    $isNeedFocus = ($prioLower === 'urgent' || $prioLower === 'high' || $task['deadline'] === $todayDate) && $isPending;
                                    
                                    $hasFiles    = isset($taskFilesMap[$task['id']]) && !empty($taskFilesMap[$task['id']]);
                                    $teamMembers = $taskAssigneesMap[$task['id']] ?? [];

                                    $accentClass = 'bg-slate-300';
                                    if ($isNeedFocus) $accentClass = 'bg-red-500';
                                    elseif ($stLower === 'in progress') $accentClass = 'bg-blue-500';
                                    elseif ($stLower === 'ready for review') $accentClass = 'bg-purple-500';
                                    elseif ($stLower === 'done') $accentClass = 'bg-emerald-500';
                                ?>

                                <div class="task-card bg-white border rounded-2xl shadow-xs overflow-hidden transition-all <?= $isNeedFocus ? 'border-red-300 shadow-sm' : 'border-slate-200/90' ?>"
                                     data-status="<?= $statusClassKey ?>"
                                     data-overdue="<?= $isOverdue ? 'true' : 'false' ?>"
                                     data-search-title="<?= htmlspecialchars(strtolower($task['nama_tugas'] . ' ' . $projectGroup['nama_projek'])) ?>">

                                    <div class="flex">
                                        <div class="w-1.5 shrink-0 <?= $accentClass ?> <?= $isOverdue ? '!bg-red-500' : '' ?>"></div>

                                        <div class="flex-1 p-4 text-xs sm:text-sm">
                                            <!-- Header Card -->
                                            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-3 mb-3">
                                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 leading-snug flex-1 flex items-center gap-2">
                                                    <?php if ($isNeedFocus): ?>
                                                        <span class="text-red-500 font-bold text-xs" title="Perlu Pengerjaan Prioritas"><i class="fa-solid fa-circle-exclamation"></i></span>
                                                    <?php endif; ?>
                                                    <?= htmlspecialchars($task['nama_tugas']) ?>
                                                </h4>

                                                <div class="flex flex-wrap items-center gap-2 shrink-0">
                                                    
                                                    <?php if ($isNeedFocus): ?>
                                                        <span class="px-2.5 py-1 bg-red-600 text-white font-bold text-[10px] rounded-lg uppercase tracking-wider flex items-center gap-1 shadow-2xs">
                                                            <i class="fa-solid fa-fire text-[9px]"></i> Wajib Dikerjakan
                                                        </span>
                                                    <?php endif; ?>

                                                    <!-- Badge Deadline -->
                                                    <div class="px-2.5 py-1 rounded-lg text-[11px] font-medium flex items-center gap-1.5 border
                                                        <?php
                                                            if ($isOverdue) {
                                                                echo 'bg-red-50 text-red-700 border-red-200 font-semibold';
                                                            } elseif ($task['deadline'] === $todayDate && !$isDone) {
                                                                echo 'bg-amber-50 text-amber-800 border-amber-200 font-semibold';
                                                            } elseif ($isDone) {
                                                                echo 'bg-slate-100 text-slate-500 border-slate-200';
                                                            } else {
                                                                echo 'bg-slate-50 text-slate-700 border-slate-200';
                                                            }
                                                        ?>">
                                                        <i class="fa-regular fa-calendar-check text-xs"></i>
                                                        <span><?= date('d M Y', strtotime($task['deadline'])) ?></span>
                                                    </div>

                                                    <!-- Badge Prioritas -->
                                                    <span class="px-2.5 py-1 text-[10px] uppercase font-bold rounded-lg border tracking-wider
                                                        <?php
                                                            switch($task['prioritas']) {
                                                                case 'Urgent': echo $isDone ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-red-50 text-red-700 border-red-200'; break;
                                                                case 'High': echo $isDone ? 'bg-slate-100 text-slate-600 border-slate-200' : 'bg-red-50 text-red-600 border-red-200'; break;
                                                                case 'Medium': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                                                default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                                            }
                                                        ?>">
                                                        <?= htmlspecialchars($task['prioritas']) ?>
                                                    </span>

                                                    <!-- Badge Status -->
                                                    <span class="px-2.5 py-1 text-[10px] uppercase font-bold rounded-lg border tracking-wider
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

                                            <!-- Tim Terlibat -->
                                            <?php if (!empty($teamMembers)): ?>
                                                <div class="mb-3 bg-slate-50/80 p-2.5 rounded-xl border border-slate-200/80">
                                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 block mb-1">
                                                        <i class="fa-solid fa-users text-emerald-600 mr-1"></i> Tim Terlibat Pengerjaan:
                                                    </span>
                                                    <div class="flex flex-wrap gap-1.5">
                                                        <?php foreach ($teamMembers as $tm): ?>
                                                            <span class="px-2 py-0.5 bg-white rounded-md border border-slate-200 text-xs font-medium text-slate-700 flex items-center gap-1 shadow-2xs">
                                                                <?= htmlspecialchars($tm['nama']) ?>
                                                                <?php if ($tm['user_id'] == $user_id): ?>
                                                                    <span class="text-[10px] text-emerald-600 font-semibold">(Anda)</span>
                                                                <?php endif; ?>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>

                                            <!-- Form Progress / Approved Status -->
                                            <?php if ($isDone): ?>
                                                <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3 flex items-center justify-between gap-2">
                                                    <span class="text-xs font-medium text-emerald-800 flex items-center gap-2">
                                                        <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i> Modul telah disetujui (Approved) oleh Project Manager.
                                                    </span>
                                                    <span class="text-[10px] bg-emerald-200/80 font-bold px-2 py-0.5 rounded text-emerald-900 uppercase">Selesai</span>
                                                </div>
                                            <?php else: ?>
                                                <form action="" method="POST" enctype="multipart/form-data" class="bg-slate-50/80 rounded-xl border border-slate-200/80 p-3.5 space-y-3">
                                                    <input type="hidden" name="update_progress" value="1">
                                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">

                                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                                        <div>
                                                            <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Status Pengerjaan</label>
                                                            <select name="status" class="input-glow w-full px-3 py-2 rounded-xl border border-slate-300 text-xs bg-white focus:outline-none font-medium text-slate-800">
                                                                <option value="To Do" <?= $task['status'] === 'To Do' ? 'selected' : '' ?>>To Do (Belum Dimulai)</option>
                                                                <option value="In Progress" <?= $task['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress (Sedang Dikerjakan)</option>
                                                                <option value="Ready for Review" <?= $task['status'] === 'Ready for Review' ? 'selected' : '' ?>>Ready for Review (Siap Di-QC)</option>
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Link Google Drive / Repo</label>
                                                            <input type="url" name="drive_link" value="<?= htmlspecialchars($task['drive_link'] ?? '') ?>" placeholder="https://drive.google.com/..." class="input-glow w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none font-normal text-slate-800 bg-white placeholder:text-slate-400">
                                                        </div>

                                                        <div>
                                                            <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Upload File Deliverable</label>
                                                            <input type="file" name="task_file" class="w-full text-xs text-slate-500 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 transition">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label class="block text-[11px] font-semibold uppercase text-slate-500 mb-1">Catatan Laporan Progress</label>
                                                        <textarea name="progress_notes" rows="2" placeholder="Tulis ringkasan hasil kerja..." class="input-glow w-full px-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none bg-white font-normal text-slate-900 placeholder:text-slate-400"><?= htmlspecialchars($task['progress_notes'] ?? '') ?></textarea>
                                                    </div>

                                                    <div class="flex justify-end pt-1">
                                                        <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold shadow-xs flex items-center gap-1.5 uppercase tracking-wider">
                                                            <i class="fa-solid fa-paper-plane text-xs"></i> Kirim Progress
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

        <!-- PAGINASI INTERAKTIF -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mt-5 shadow-xs flex flex-col sm:flex-row justify-between items-center gap-3 text-xs">
            <span class="text-slate-500 font-medium" id="paginationInfo">Menampilkan 0 proyek</span>
            <div class="flex items-center gap-1.5" id="paginationControls"></div>
        </div>

        <footer class="mt-8 pb-6 text-center">
            <p class="text-xs text-slate-400 font-medium">&copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Workspace Penugasan Tim Teknis.</p>
        </footer>

    </main>

    <!-- MODAL FORM GANTI PASSWORD MANDIRI -->
    <div id="changePasswordModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative animate-fade-in text-xs sm:text-sm">
            
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Pembaruan Password Akun</h3>
                </div>
                <button onclick="closePwdModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <form action="" method="POST" class="p-5 space-y-3.5">
                <input type="hidden" name="change_password_action" value="1">

                <!-- Password Saat Ini -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Password Saat Ini / Standar <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" id="curPwdInput" name="old_password" required placeholder="••••••••" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-lock text-xs"></i>
                        </div>
                        <button type="button" onclick="togglePasswordVisibility('curPwdInput', 'curPwdIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none">
                            <i id="curPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Baru -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Password Baru <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" id="newPwdInput" name="new_password" required minlength="6" placeholder="Minimal 6 karakter" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-shield-halved text-xs"></i>
                        </div>
                        <button type="button" onclick="togglePasswordVisibility('newPwdInput', 'newPwdIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none">
                            <i id="newPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Konfirmasi Password Baru -->
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Konfirmasi Password Baru <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <input type="password" id="confirmPwdInput" name="confirm_password" required minlength="6" placeholder="Ulangi password baru" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-9 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                            <i class="fa-solid fa-circle-check text-xs"></i>
                        </div>
                        <button type="button" onclick="togglePasswordVisibility('confirmPwdInput', 'confirmPwdIcon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none">
                            <i id="confirmPwdIcon" class="fa-regular fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 text-xs text-slate-500 font-normal flex items-center gap-2">
                    <i class="fa-solid fa-lock text-emerald-600"></i> Password baru akan langsung dienkripsi secara aman.
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closePwdModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs">
                        Simpan Password Baru
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- JAVASCRIPT HANDLERS -->
    <script>
        function openPwdModal() {
            document.getElementById('changePasswordModal').classList.remove('hidden');
        }

        function closePwdModal() {
            document.getElementById('changePasswordModal').classList.add('hidden');
        }

        function togglePasswordVisibility(inputId, iconId) {
            const pwdInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        let currentStatusFilter = 'all';
        const projectsPerPage = 3;
        let currentPage = 1;
        let filteredProjects = [];

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
                    tab.classList.remove('text-slate-600');
                } else {
                    tab.classList.remove('active-tab');
                    tab.classList.add('text-slate-600');
                }
            });

            applyCombinedFilters();
        }

        function applyCombinedFilters() {
            const searchVal = document.getElementById('globalTaskSearch').value.toLowerCase();
            const allProjects = Array.from(document.querySelectorAll('.project-section'));

            filteredProjects = allProjects.filter(sec => {
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

                return visibleCount > 0;
            });

            currentPage = 1;
            renderPagination();
        }

        function renderPagination() {
            const allProjects = document.querySelectorAll('.project-section');
            allProjects.forEach(sec => sec.classList.add('hidden'));

            const totalItems = filteredProjects.length;
            const totalPages = Math.ceil(totalItems / projectsPerPage) || 1;

            const startIdx = (currentPage - 1) * projectsPerPage;
            const endIdx   = startIdx + projectsPerPage;

            filteredProjects.slice(startIdx, endIdx).forEach(sec => {
                sec.classList.remove('hidden');
            });

            const displayStart = totalItems === 0 ? 0 : startIdx + 1;
            const displayEnd   = Math.min(endIdx, totalItems);
            document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} proyek`;

            const controls = document.getElementById('paginationControls');
            controls.innerHTML = '';

            if (totalPages <= 1) return;

            // Prev Button
            const prevBtn = document.createElement('button');
            prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
            prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
            prevBtn.disabled = currentPage === 1;
            prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
            controls.appendChild(prevBtn);

            // Page Numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageBtn = document.createElement('button');
                pageBtn.innerText = i;
                pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
                pageBtn.onclick = () => { currentPage = i; renderPagination(); };
                controls.appendChild(pageBtn);
            }

            // Next Button
            const nextBtn = document.createElement('button');
            nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
            nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
            nextBtn.disabled = currentPage === totalPages;
            nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
            controls.appendChild(nextBtn);
        }

        document.addEventListener('DOMContentLoaded', function() {
            applyCombinedFilters();
        });
    </script>
</body>
</html>