<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Filter Parameter, Search & Pagination
$filter_status = $_GET['status'] ?? 'All';
$filter_user   = $_GET['user_id'] ?? 'All';
$search        = trim($_GET['q'] ?? '');
$sort          = $_GET['sort'] ?? 'priority';

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 6;
$offset = ($page - 1) * $limit;

// Query Condition
$where_clauses = ["1=1"];
$params = [];

if ($filter_status !== 'All') {
    $where_clauses[] = "t.status = ?";
    $params[] = $filter_status;
}

if ($filter_user !== 'All') {
    $where_clauses[] = "t.user_id = ?";
    $params[] = $filter_user;
}

if ($search !== '') {
    $where_clauses[] = "(t.nama_tugas LIKE ? OR p.nama_projek LIKE ? OR u.nama LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$where_sql = implode(" AND ", $where_clauses);

// Sorting
$order_sql = match ($sort) {
    'deadline' => "t.deadline ASC",
    'latest'   => "t.id DESC",
    default    => "CASE t.prioritas WHEN 'Urgent' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 ELSE 4 END ASC, t.deadline ASC"
};

// Hitung Total Rows
$count_query = "
    SELECT COUNT(*) 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.user_id = u.id
    WHERE {$where_sql}
";
$stmt_count = $pdo->prepare($count_query);
$stmt_count->execute($params);
$total_rows = $stmt_count->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Query Data Tasks
$query = "
    SELECT t.*, 
           p.nama_projek, 
           p.klien, 
           p.status AS project_status,
           p.id AS project_id,
           u.nama AS nama_karyawan,
           u.jabatan
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.user_id = u.id
    WHERE {$where_sql}
    ORDER BY {$order_sql}
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Fetch Data Proyek untuk Gantt Chart
$projectsGanttRaw = $pdo->query("
    SELECT p.*, 
           COALESCE(c.durasi_bulan, 3) AS durasi_bulan
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    ORDER BY p.id DESC
")->fetchAll();

// Query hanya kolom yang PASTI ADA di tabel tasks
$allTasksStmt = $pdo->query("SELECT id, project_id, status, deadline FROM tasks");
$allTasks = $allTasksStmt->fetchAll();

$projectsGantt = [];
foreach ($projectsGanttRaw as $p) {
    $pId = $p['id'];
    $pStart = (!empty($p['tanggal_mulai'])) ? new DateTime($p['tanggal_mulai']) : new DateTime();
    
    $pTasks = array_filter($allTasks, fn($tk) => $tk['project_id'] == $pId);
    
    $weekStatusMap = [];
    
    foreach ($pTasks as $tk) {
        if (!empty($tk['deadline'])) {
            $taskDeadline = new DateTime($tk['deadline']);
            $diffDays = (int)$pStart->diff($taskDeadline)->format("%r%a");
            $targetWeek = max(1, (int)ceil(($diffDays + 1) / 7));
            
            if (!isset($weekStatusMap[$targetWeek])) {
                $weekStatusMap[$targetWeek] = [];
            }
            $weekStatusMap[$targetWeek][] = strtolower($tk['status']);
        }
    }
    
    $p['week_status_map'] = $weekStatusMap;
    $projectsGantt[] = $p;
}

// Master Karyawan & Stats
$users = $pdo->query("SELECT * FROM users ORDER BY nama ASC")->fetchAll();
$totalTasks = $pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$urgentTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE prioritas = 'Urgent' AND status != 'Done'")->fetchColumn();
$completedTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Done'")->fetchColumn();
$inProgressTasks = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn();

function getPaginationUrl($pageNum) {
    $params = $_GET;
    $params['page'] = $pageNum;
    return '?' . http_build_query($params);
}
?>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 relative font-sans">
    
    <!-- Top Header Bar -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div>
            <div class="flex items-center gap-2 text-emerald-700 font-semibold text-xs tracking-wider uppercase mb-1">
                <i class="fa-solid fa-circle text-[8px]"></i> Modul 2
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Workload & Priority Hub</h2>
            <p class="text-slate-500 text-xs mt-0.5">Monitoring tugas prioritas harian karyawan PT. CTC & update status pengerjaan</p>
        </div>

        <button onclick="openGanttModal()" class="px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition shadow-md flex items-center justify-center gap-2 shrink-0">
            <i class="fa-solid fa-chart-gantt text-emerald-400"></i> Lihat Gantt Chart Interactive
        </button>
    </div>

    <!-- Stats Bar -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Tugas</span>
                <i class="fa-solid fa-list-check text-slate-400"></i>
            </div>
            <p class="text-2xl font-bold text-slate-800"><?= number_format($totalTasks) ?></p>
        </div>
        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-red-500 uppercase tracking-wider">Prioritas Urgent</span>
                <i class="fa-solid fa-triangle-exclamation text-red-500"></i>
            </div>
            <p class="text-2xl font-bold text-red-600"><?= number_format($urgentTasks) ?></p>
        </div>
        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-blue-500 uppercase tracking-wider">Sedang Dikerjakan</span>
                <i class="fa-solid fa-spinner text-blue-500"></i>
            </div>
            <p class="text-2xl font-bold text-blue-600"><?= number_format($inProgressTasks) ?></p>
        </div>
        <div class="bg-white border border-slate-200 p-4 rounded-2xl shadow-sm">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Selesai (Done)</span>
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
            </div>
            <p class="text-2xl font-bold text-emerald-600"><?= number_format($completedTasks) ?></p>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white border border-slate-200 rounded-2xl p-4 md:p-5 mb-6 shadow-sm">
        <form method="GET" class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4">
            
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Cari tugas, projek, atau nama karyawan..." class="w-full pl-10 pr-4 py-2 text-xs rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 transition">
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-500 uppercase">Status:</label>
                    <select name="status" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="All" <?= $filter_status === 'All' ? 'selected' : '' ?>>Semua Status</option>
                        <option value="To Do" <?= $filter_status === 'To Do' ? 'selected' : '' ?>>To Do</option>
                        <option value="In Progress" <?= $filter_status === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Ready for Review" <?= $filter_status === 'Ready for Review' ? 'selected' : '' ?>>Ready for Review</option>
                        <option value="Done" <?= $filter_status === 'Done' ? 'selected' : '' ?>>Done</option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-500 uppercase">Karyawan:</label>
                    <select name="user_id" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="All" <?= $filter_user === 'All' ? 'selected' : '' ?>>Semua Karyawan</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs font-bold text-slate-500 uppercase">Urutan:</label>
                    <select name="sort" onchange="this.form.submit()" class="px-3 py-2 rounded-xl border border-slate-200 text-xs bg-white font-medium focus:outline-none focus:ring-2 focus:ring-emerald-500 cursor-pointer">
                        <option value="priority" <?= $sort === 'priority' ? 'selected' : '' ?>>Prioritas Utama</option>
                        <option value="deadline" <?= $sort === 'deadline' ? 'selected' : '' ?>>Deadline Terdekat</option>
                        <option value="latest" <?= $sort === 'latest' ? 'selected' : '' ?>>Tugas Terbaru</option>
                    </select>
                </div>

                <?php if ($filter_status !== 'All' || $filter_user !== 'All' || $search !== '' || $sort !== 'priority'): ?>
                    <a href="index.php" class="text-xs text-red-500 hover:text-red-700 font-semibold ml-auto lg:ml-0 flex items-center gap-1 transition">
                        <i class="fa-solid fa-xmark"></i> Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Tasks Grid Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
        <?php if (empty($tasks)): ?>
            <div class="col-span-full bg-white border border-slate-200 rounded-2xl p-12 text-center text-slate-400 shadow-sm">
                <i class="fa-solid fa-clipboard-check text-4xl mb-3 text-slate-300"></i>
                <p class="font-medium text-slate-600">Tidak ada tugas ditemukan.</p>
                <p class="text-sm text-slate-400 mt-1">Atur ulang kata kunci atau filter pencarian Anda.</p>
            </div>
        <?php else: ?>
            <?php foreach ($tasks as $t): ?>
                <div onclick="openTaskDrawer(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-emerald-500/40 transition cursor-pointer flex flex-col justify-between group">
                    <div>
                        <div class="flex justify-between items-start mb-3">
                            <span class="px-2.5 py-1 text-[10px] font-bold uppercase rounded-lg tracking-wider
                                <?php
                                    switch($t['prioritas']) {
                                        case 'Urgent': echo 'bg-red-50 text-red-600 border border-red-200'; break;
                                        case 'High': echo 'bg-amber-50 text-amber-600 border border-amber-200'; break;
                                        case 'Medium': echo 'bg-blue-50 text-blue-600 border border-blue-200'; break;
                                        default: echo 'bg-slate-100 text-slate-600 border border-slate-200';
                                    }
                                ?>">
                                <i class="fa-solid fa-flag mr-1"></i> <?= $t['prioritas'] ?>
                            </span>

                            <span class="text-xs font-semibold text-slate-400 flex items-center gap-1">
                                <i class="fa-regular fa-calendar text-slate-400"></i> <?= date('d M Y', strtotime($t['deadline'])) ?>
                            </span>
                        </div>

                        <h4 class="font-bold text-slate-800 text-base mb-1 line-clamp-2 group-hover:text-emerald-700 transition"><?= htmlspecialchars($t['nama_tugas']) ?></h4>
                        
                        <p class="text-xs text-emerald-700 font-semibold mb-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-folder text-emerald-600"></i>
                            <span class="truncate max-w-[200px]"><?= htmlspecialchars($t['nama_projek']) ?></span>
                        </p>

                        <div class="flex items-center justify-between bg-slate-50 p-2.5 rounded-xl border border-slate-100 mb-4">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shadow-sm">
                                    <?= strtoupper(substr($t['nama_karyawan'], 0, 1)) ?>
                                </div>
                                <div>
                                    <p class="text-xs font-bold text-slate-800 leading-none mb-0.5"><?= htmlspecialchars($t['nama_karyawan']) ?></p>
                                    <span class="text-[10px] text-slate-400 font-medium"><?= htmlspecialchars($t['jabatan']) ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-400 bg-white px-2 py-1 rounded-lg border border-slate-200">
                                Detail <i class="fa-solid fa-chevron-right ml-0.5 text-[8px]"></i>
                            </span>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between" onclick="event.stopPropagation()">
                        <span class="text-xs font-semibold text-slate-400">Status:</span>
                        <form action="update.php" method="POST" class="inline-block">
                            <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                            <select name="status" onchange="this.form.submit()" class="px-3 py-1 rounded-lg border text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500 cursor-pointer shadow-sm
                                <?php
                                    switch($t['status']) {
                                        case 'Done': echo 'text-emerald-700 bg-emerald-50 border-emerald-300'; break;
                                        case 'Ready for Review': echo 'text-purple-700 bg-purple-50 border-purple-300'; break;
                                        case 'In Progress': echo 'text-blue-700 bg-blue-50 border-blue-300'; break;
                                        default: echo 'text-slate-600 bg-slate-50 border-slate-200';
                                    }
                                ?>">
                                <option value="To Do" <?= $t['status'] === 'To Do' ? 'selected' : '' ?>>To Do</option>
                                <option value="In Progress" <?= $t['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                                <option value="Ready for Review" <?= $t['status'] === 'Ready for Review' ? 'selected' : '' ?>>Ready for Review</option>
                                <option value="Done" <?= $t['status'] === 'Done' ? 'selected' : '' ?>>Done</option>
                            </select>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <p class="text-xs text-slate-500 font-medium">
                Menampilkan <span class="font-bold text-slate-800"><?= min($offset + 1, $total_rows) ?></span> - <span class="font-bold text-slate-800"><?= min($offset + $limit, $total_rows) ?></span> dari <span class="font-bold text-slate-800"><?= $total_rows ?></span> tugas
            </p>

            <div class="flex items-center gap-1.5">
                <?php if ($page > 1): ?>
                    <a href="<?= getPaginationUrl($page - 1) ?>" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 rounded-xl hover:bg-emerald-600 hover:text-white transition">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 rounded-xl cursor-not-allowed">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Prev
                    </span>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <?php if ($i ==$page): ?>
                        <span class="px-3 py-1.5 text-xs font-bold bg-emerald-600 text-white rounded-xl shadow-sm"><?= $i ?></span>
                    <?php else: ?>
                        <a href="<?= getPaginationUrl($i) ?>" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-50 hover:bg-slate-200 rounded-xl transition"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page <$total_pages): ?>
                    <a href="<?= getPaginationUrl($page + 1) ?>" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 rounded-xl hover:bg-emerald-600 hover:text-white transition">
                        Next <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1.5 text-xs font-semibold text-slate-300 bg-slate-50 rounded-xl cursor-not-allowed">
                        Next <i class="fa-solid fa-chevron-right ml-1"></i>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- MODAL POP-UP GANTT CHART INTERACTIVE -->
<div id="ganttModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-5xl shadow-2xl overflow-hidden relative flex flex-col max-h-[92vh]">
        
        <div class="px-6 py-4 bg-emerald-950 text-white flex justify-between items-center shrink-0">
            <div>
                <h3 class="font-bold text-base flex items-center gap-2">
                    <i class="fa-solid fa-chart-gantt text-emerald-400"></i> Gantt Chart & Status Progress Realtime
                </h3>
                <p class="text-xs text-emerald-300 mt-0.5">Pantau estimasi jadwal, progres modul, dan indikator minggu pengerjaan</p>
            </div>
            <button onclick="closeGanttModal()" class="text-emerald-300 hover:text-white transition">
                <i class="fa-solid fa-xmark text-xl"></i>
            </button>
        </div>

        <div class="p-4 bg-slate-50 border-b border-slate-200 flex flex-col md:flex-row justify-between items-stretch md:items-center gap-3 shrink-0">
            <div class="flex flex-1 items-center gap-2">
                <div class="relative flex-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="ganttSearchInput" onkeyup="filterGanttProjects()" placeholder="Cari nama proyek..." class="w-full pl-9 pr-3 py-1.5 text-xs rounded-xl border border-slate-300 bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 font-semibold text-slate-700">
                </div>
                <select id="ganttStatusFilter" onchange="filterGanttProjects()" class="px-3 py-1.5 text-xs font-bold rounded-xl border border-slate-300 bg-white text-slate-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 shrink-0">
                    <option value="ALL">Semua Status Proyek</option>
                    <option value="In Progress">In Progress</option>
                    <option value="Completed">Completed</option>
                    <option value="On Hold">On Hold</option>
                </select>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button onclick="exportGanttExcel()" class="px-3.5 py-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded-xl text-xs font-bold transition flex items-center gap-1.5 border border-emerald-300">
                    <i class="fa-solid fa-file-excel text-emerald-700"></i> Export Excel
                </button>
                <button onclick="printGanttPDF()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center gap-1.5">
                    <i class="fa-solid fa-print"></i> Cetak PDF / Print
                </button>
            </div>
        </div>

        <div class="p-6 overflow-y-auto space-y-5 flex-1">
            
            <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                <label class="text-xs font-bold text-slate-600 uppercase shrink-0">Pilih Proyek Target:</label>
                <select id="ganttProjectSelect" onchange="renderGanttChart()" class="w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                    <?php foreach ($projectsGantt as$pG): ?>
                        <option value="<?= $pG['id'] ?>" 
                                data-nama="<?= htmlspecialchars($pG['nama_projek']) ?>"
                                data-klien="<?= htmlspecialchars($pG['klien']) ?>"
                                data-status="<?= htmlspecialchars($pG['status']) ?>"
                                data-start="<?= $pG['tanggal_mulai'] ?>"
                                data-durasi="<?= $pG['durasi_bulan'] ?>"
                                data-tasksmap='<?= json_encode($pG['week_status_map']) ?>'>
                            <?= htmlspecialchars($pG['nama_projek']) ?> (Klien: <?= htmlspecialchars($pG['klien']) ?>) - [<?=$pG['status'] ?>]
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Legend Warna Keterangan Status -->
            <div class="flex flex-wrap items-center justify-between text-[11px] font-bold text-slate-600 bg-slate-100/70 px-4 py-2 rounded-xl border border-slate-200">
                <div class="flex items-center gap-4 flex-wrap">
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-600 inline-block"></span> Selesai (Done)</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-600 inline-block"></span> Sedang Dikerjakan</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-amber-500 inline-block"></span> Terlambat / Warning</span>
                    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-slate-800 inline-block"></span> Rencana Pengerjaan</span>
                </div>
                <div class="flex items-center gap-1 text-emerald-800 font-extrabold">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span> Garis Hijau: Minggu Aktif Saat Ini
                </div>
            </div>

            <div id="ganttTableContainer" class="overflow-x-auto border border-slate-300 rounded-xl bg-white">
                <!-- Render Gantt Chart JS -->
            </div>

        </div>

        <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-between items-center text-xs shrink-0">
            <span class="text-slate-500 font-medium">Standardization SPK BAST Document PT. CTC</span>
            <button onclick="closeGanttModal()" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-semibold transition">
                Tutup Window
            </button>
        </div>
    </div>
</div>

<!-- SLIDE-OVER DRAWER DETAIL TUGAS & GOOGLE DRIVE -->
<div id="drawerOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300" onclick="closeTaskDrawer()"></div>

<div id="taskDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col justify-between overflow-y-auto">
    <div class="p-6 bg-slate-900 text-white flex justify-between items-start">
        <div>
            <span id="drawerPriority" class="px-2.5 py-0.5 text-[10px] font-bold uppercase rounded-md bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 mb-2 inline-block">
                Urgent
            </span>
            <h3 id="drawerTaskTitle" class="text-xl font-bold leading-snug">Nama Tugas</h3>
            <p id="drawerProjectName" class="text-xs text-emerald-400 mt-1 font-medium flex items-center gap-1">
                <i class="fa-solid fa-folder"></i> <span>-</span>
            </p>
        </div>
        <button type="button" onclick="closeTaskDrawer()" class="w-8 h-8 rounded-full bg-slate-800 text-slate-400 hover:text-white flex items-center justify-center transition">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="p-6 space-y-6 flex-1">
        <div class="grid grid-cols-2 gap-3 bg-slate-50 p-3 rounded-2xl border border-slate-100">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Status Modul</span>
                <span id="drawerStatus" class="text-xs font-bold text-slate-700 bg-white px-2.5 py-1 rounded-lg border border-slate-200 inline-block shadow-sm">-</span>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Tenggat Waktu</span>
                <span id="drawerDeadline" class="text-xs font-bold text-slate-700 bg-white px-2.5 py-1 rounded-lg border border-slate-200 inline-block shadow-sm">-</span>
            </div>
        </div>

        <div>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Penanggung Jawab</h4>
            <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                <div id="drawerAvatar" class="w-10 h-10 rounded-xl bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shadow-sm">
                    A
                </div>
                <div>
                    <p id="drawerAssignee" class="text-xs font-bold text-slate-800">Nama Karyawan</p>
                    <p id="drawerRole" class="text-[10px] text-slate-400">Jabatan</p>
                </div>
            </div>
        </div>

        <div>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Lampiran Google Drive</h4>
            <div id="drawerDriveBox" class="p-3 bg-emerald-50/60 rounded-xl border border-emerald-200/60 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <i class="fa-brands fa-google-drive text-emerald-600 text-xl"></i>
                    <div>
                        <p class="text-xs font-bold text-slate-800">File & Deliverables</p>
                        <p id="drawerDriveText" class="text-[10px] text-slate-400">Google Drive Repository</p>
                    </div>
                </div>
                <a href="#" target="_blank" id="driveButton" class="px-3.5 py-1.5 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl transition shadow-sm">
                    Buka Drive
                </a>
            </div>
        </div>

        <div class="space-y-3">
            <div>
                <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">Laporan Karyawan</h4>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 text-xs text-slate-600 leading-relaxed italic" id="drawerProgressNotes">
                    Belum ada catatan pengerjaan.
                </div>
            </div>

            <div id="drawerRevSection">
                <h4 class="text-xs font-bold text-amber-600 uppercase tracking-wider mb-1.5">Instruksi Revisi PM</h4>
                <div class="p-3 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-800 leading-relaxed font-medium" id="drawerRevisionNotes">
                    Tidak ada revisi.
                </div>
            </div>
        </div>
    </div>

    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
        <a id="drawerProjectLink" href="#" class="text-xs text-emerald-700 hover:underline font-bold flex items-center gap-1">
            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Buka Proyek
        </a>
        <button type="button" onclick="closeTaskDrawer()" class="px-5 py-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition">
            Tutup
        </button>
    </div>
</div>

<script>
function openGanttModal() {
    document.getElementById('ganttModal').classList.remove('hidden');
    renderGanttChart();
}

function closeGanttModal() {
    document.getElementById('ganttModal').classList.add('hidden');
}

function filterGanttProjects() {
    const searchVal = document.getElementById('ganttSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('ganttStatusFilter').value;
    const select = document.getElementById('ganttProjectSelect');
    const options = select.getElementsByTagName('option');

    let firstIndex = -1;

    for (let i = 0; i < options.length; i++) {
        const text = options[i].text.toLowerCase();
        const pStatus = options[i].getAttribute('data-status') || '';

        const matchSearch = text.includes(searchVal);
        const matchStatus = (statusVal === 'ALL' || pStatus === statusVal);

        if (matchSearch && matchStatus) {
            options[i].style.display = "";
            if (firstIndex === -1) firstIndex = i;
        } else {
            options[i].style.display = "none";
        }
    }

    if (firstIndex !== -1) {
        select.selectedIndex = firstIndex;
        renderGanttChart();
    }
}

function renderGanttChart() {
    const select = document.getElementById('ganttProjectSelect');
    if (!select || select.options.length === 0) return;

    const opt = select.options[select.selectedIndex];
    const namaProjek = opt.getAttribute('data-nama');
    const startDateStr = opt.getAttribute('data-start');
    const durasiBulan = parseInt(opt.getAttribute('data-durasi')) || 3;
    const tasksMapRaw = opt.getAttribute('data-tasksmap');
    
    let weekTasksMap = {};
    try {
        weekTasksMap = JSON.parse(tasksMapRaw) || {};
    } catch(e) {
        weekTasksMap = {};
    }

    const totalMinggu = durasiBulan * 4;

    // HITUNG MINGGU AKTIF SAAT INI (GARIS HIJAU)
    let currentWeek = -1;
    if (startDateStr) {
        const startDate = new Date(startDateStr);
        const today = new Date();
        const diffTime = today - startDate;
        const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays >= 0) {
            currentWeek = Math.floor(diffDays / 7) + 1;
        }
    }

    const phases = [
        {
            fase: "Fase 1: Inisiasi & UI/UX Design",
            deskripsi: "Analisis kebutuhan, PM-AAS, desain UI/UX, dan arsitektur database.",
            weeks: [1, 2]
        },
        {
            fase: "Fase 2: Core Development & API Integration",
            deskripsi: "Development Aplikasi, Core Logic & Integrasi API Sistem.",
            weeks: Array.from({length: Math.max(1, Math.floor(totalMinggu * 0.35))}, (_, i) => i + 3)
        },
        {
            fase: "Fase 3: Algoritma & Modul Spesifik",
            deskripsi: "Implementasi Modul Spesifik, Algoritma, dan Fitur Khusus.",
            weeks: Array.from({length: Math.max(1, Math.floor(totalMinggu * 0.25))}, (_, i) => i + Math.floor(totalMinggu * 0.5) + 1)
        },
        {
            fase: "Fase 4: Testing, QA & Bug Fixing",
            deskripsi: "Pengujian fungsi, integrasi Web Dashboard Admin, dan bug fixing.",
            weeks: [totalMinggu - 1]
        },
        {
            fase: "Fase 5: UAT, Training & Deployment Go-Live",
            deskripsi: "UAT bersama Klien, Pelatihan, & Deployment Go-Live.",
            weeks: [totalMinggu]
        }
    ];

    let html = `
        <div class="p-3 bg-slate-100 border-b border-slate-300 font-bold text-xs text-slate-800 flex justify-between items-center">
            <span>PROYEKSI & JADWAL PELAKSANAAN PROYEK "${namaProjek.toUpperCase()}"</span>
            <span class="text-emerald-700 bg-emerald-100 border border-emerald-200 px-2 py-0.5 rounded font-mono text-[11px]">Durasi: ${durasiBulan} Bulan (${totalMinggu} Minggu)</span>
        </div>
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-slate-200 text-slate-700 uppercase font-bold border-b border-slate-300">
                    <th class="p-2.5 border-r border-slate-300 w-1/4">Tahapan Pekerjaan</th>
                    <th class="p-2.5 border-r border-slate-300 w-2/5">Deskripsi</th>
    `;

    for (let m = 1; m <= totalMinggu; m++) {
        const isCurrent = (m === currentWeek);
        html += `<th class="p-2 text-center border-r border-slate-300 text-[10px] ${isCurrent ? 'bg-emerald-600 text-white font-black' : 'bg-slate-100'}" title="${isCurrent ? 'Minggu Aktif Saat Ini' : ''}">M${m}</th>`;
    }

    html += `</tr></thead><tbody class="divide-y divide-slate-200">`;

    phases.forEach((p) => {
        html += `
            <tr class="hover:bg-slate-50 transition">
                <td class="p-2.5 font-bold text-slate-800 border-r border-slate-300">${p.fase}</td>
                <td class="p-2.5 text-slate-600 text-[11px] border-r border-slate-300">${p.deskripsi}</td>
        `;

        for (let m = 1; m <= totalMinggu; m++) {
            const isScheduleFilled = p.weeks.includes(m);
            const isCurrent = (m === currentWeek);
            const weekTasks = weekTasksMap[m] || [];

            let blockBg = "bg-slate-900"; 
            let titleText = "Minggu M" + m + ": Jadwal Rencana Pengerjaan";

            if (isScheduleFilled) {
                if (weekTasks.length > 0) {
                    const isAllDone = weekTasks.every(st => st === 'done');
                    const hasInProgress = weekTasks.some(st => st === 'in progress' || st === 'ready for review');

                    if (isAllDone) {
                        blockBg = "bg-emerald-600"; 
                        titleText = "Minggu M" + m + ": Semua Modul Selesai (Done)";
                    } else if (hasInProgress) {
                        blockBg = "bg-blue-600 animate-pulse"; 
                        titleText = "Minggu M" + m + ": Sedang Dikerjakan (In Progress)";
                    } else if (m < currentWeek) {
                        blockBg = "bg-amber-500"; 
                        titleText = "Minggu M" + m + ": Peringatan Keterlambatan!";
                    }
                } else if (m < currentWeek) {
                    blockBg = "bg-amber-500"; 
                }
            }

            html += `
                <td class="p-1 text-center border-r border-slate-300 ${isCurrent ? 'bg-emerald-50/80 border-x-2 border-x-emerald-500' : ''}">
                    ${isScheduleFilled ? `<div class="w-4 h-4 ${blockBg} rounded mx-auto shadow-sm" title="${titleText}"></div>` : ''}
                </td>
            `;
        }

        html += `</tr>`;
    });

    html += `</tbody></table>`;

    document.getElementById('ganttTableContainer').innerHTML = html;
}

function exportGanttExcel() {
    const select = document.getElementById('ganttProjectSelect');
    if (!select || select.options.length === 0) return;
    const projectId = select.value;
    window.location.href = `export_excel.php?project_id=${projectId}`;
}

function printGanttPDF() {
    const select = document.getElementById('ganttProjectSelect');
    if (!select || select.options.length === 0) return;
    const projectId = select.value;
    window.open(`print_pdf.php?project_id=${projectId}`, '_blank');
}

function openTaskDrawer(task) {
    const overlay = document.getElementById('drawerOverlay');
    const drawer = document.getElementById('taskDrawer');

    document.getElementById('drawerTaskTitle').innerText = task.nama_tugas;
    document.getElementById('drawerProjectName').querySelector('span').innerText = task.nama_projek + ' (' + task.klien + ')';
    document.getElementById('drawerPriority').innerText = task.prioritas;
    document.getElementById('drawerStatus').innerText = task.status;
    document.getElementById('drawerDeadline').innerText = task.deadline;
    document.getElementById('drawerAssignee').innerText = task.nama_karyawan;
    document.getElementById('drawerRole').innerText = task.jabatan;
    document.getElementById('drawerAvatar').innerText = task.nama_karyawan.charAt(0).toUpperCase();

    document.getElementById('drawerProjectLink').href = `../projects/detail.php?id=${task.project_id}`;

    const driveBtn = document.getElementById('driveButton');
    const driveText = document.getElementById('drawerDriveText');
    if (task.drive_link && task.drive_link.trim() !== '') {
        driveBtn.href = task.drive_link;
        driveBtn.classList.remove('hidden');
        driveText.innerText = "Tersedia link berkas";
    } else {
        driveBtn.classList.add('hidden');
        driveText.innerText = "Belum ada link drive terlampir";
    }

    document.getElementById('drawerProgressNotes').innerText = (task.progress_notes && task.progress_notes.trim() !== '') 
        ? '"' + task.progress_notes + '"' 
        : 'Belum ada catatan pengerjaan.';

    const revSec = document.getElementById('drawerRevSection');
    if (task.revision_notes && task.revision_notes.trim() !== '') {
        document.getElementById('drawerRevisionNotes').innerText = task.revision_notes;
        revSec.classList.remove('hidden');
    } else {
        revSec.classList.add('hidden');
    }

    overlay.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        drawer.classList.remove('translate-x-full');
    }, 10);
}

function closeTaskDrawer() {
    const overlay = document.getElementById('drawerOverlay');
    const drawer = document.getElementById('taskDrawer');

    drawer.classList.add('translate-x-full');
    overlay.classList.add('opacity-0');

    setTimeout(() => {
        overlay.classList.add('hidden');
    }, 300);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>