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

// Query Data Tasks (Menarik drive_link, progress_notes, revision_notes sesuai modul projects)
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
$tasks =$stmt->fetchAll();

// Data Master Karyawan & Stats
$users =$pdo->query("SELECT * FROM users ORDER BY nama ASC")->fetchAll();
$totalTasks =$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
$urgentTasks =$pdo->query("SELECT COUNT(*) FROM tasks WHERE prioritas = 'Urgent' AND status != 'Done'")->fetchColumn();
$completedTasks =$pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'Done'")->fetchColumn();
$inProgressTasks =$pdo->query("SELECT COUNT(*) FROM tasks WHERE status = 'In Progress'")->fetchColumn();

function getPaginationUrl($pageNum) {
    $params =$_GET;
    $params['page'] =$pageNum;
    return '?' . http_build_query($params);
}
?>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 relative">
    <!-- Top Header Bar -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-2 text-emerald-700 font-semibold text-xs tracking-wider uppercase mb-1">
                <i class="fa-solid fa-circle text-[8px]"></i> Modul 2
            </div>
            <h2 class="text-2xl font-bold text-slate-800">Workload & Priority Hub</h2>
            <p class="text-slate-500 text-sm">Monitoring tugas prioritas harian karyawan PT. CTC & update status pengerjaan</p>
        </div>
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
                        <?php foreach ($users as$u): ?>
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

                <?php if ($filter_status !== 'All' ||$filter_user !== 'All' || $search !== '' ||$sort !== 'priority'): ?>
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
            <?php foreach ($tasks as$t): ?>
                <div onclick="openTaskDrawer(<?= htmlspecialchars(json_encode($t), ENT_QUOTES, 'UTF-8') ?>)" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm hover:shadow-md hover:border-emerald-500/40 transition cursor-pointer flex flex-col justify-between group">
                    <div>
                        <!-- Card Header -->
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

                        <!-- Task Name & Project Info -->
                        <h4 class="font-bold text-slate-800 text-base mb-1 line-clamp-2 group-hover:text-emerald-700 transition"><?= htmlspecialchars($t['nama_tugas']) ?></h4>
                        
                        <p class="text-xs text-emerald-700 font-semibold mb-4 flex items-center gap-1.5">
                            <i class="fa-solid fa-folder text-emerald-600"></i>
                            <span class="truncate max-w-[200px]"><?= htmlspecialchars($t['nama_projek']) ?></span>
                        </p>

                        <!-- Karyawan Info -->
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

                    <!-- Status Update Form -->
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
                Menampilkan <span class="font-bold text-slate-800"><?= min($offset + 1,$total_rows) ?></span> - <span class="font-bold text-slate-800"><?= min($offset + $limit,$total_rows) ?></span> dari <span class="font-bold text-slate-800"><?= $total_rows ?></span> tugas
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

<!-- SLIDE-OVER DRAWER DETAIL TUGAS & GOOGLE DRIVE -->
<div id="drawerOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden opacity-0 transition-opacity duration-300" onclick="closeTaskDrawer()"></div>

<div id="taskDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col justify-between overflow-y-auto">
    <!-- Header Drawer -->
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

    <!-- Body Drawer -->
    <div class="p-6 space-y-6 flex-1">
        
        <!-- Status & Deadline Quick Badge -->
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

        <!-- Penanggung Jawab Karyawan -->
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

        <!-- Google Drive Deliverables (Diambil dari drive_link DB) -->
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

        <!-- Catatan Progress & Revisi -->
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

    <!-- Footer Drawer -->
    <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
        <a id="drawerProjectLink" href="#" class="text-xs text-emerald-700 hover:underline font-bold flex items-center gap-1">
            <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i> Buka Proyek
        </a>
        <button type="button" onclick="closeTaskDrawer()" class="px-5 py-2 text-xs font-bold text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 transition">
            Tutup
        </button>
    </div>
</div>

<!-- JavaScript Interaktivitas Drawer -->
<script>
function openTaskDrawer(task) {
    const overlay = document.getElementById('drawerOverlay');
    const drawer = document.getElementById('taskDrawer');

    // Populate Data Utama
    document.getElementById('drawerTaskTitle').innerText = task.nama_tugas;
    document.getElementById('drawerProjectName').querySelector('span').innerText = task.nama_projek + ' (' + task.klien + ')';
    document.getElementById('drawerPriority').innerText = task.prioritas;
    document.getElementById('drawerStatus').innerText = task.status;
    document.getElementById('drawerDeadline').innerText = task.deadline;
    document.getElementById('drawerAssignee').innerText = task.nama_karyawan;
    document.getElementById('drawerRole').innerText = task.jabatan;
    document.getElementById('drawerAvatar').innerText = task.nama_karyawan.charAt(0).toUpperCase();

    // Link Proyek ke Modul Detail
    document.getElementById('drawerProjectLink').href = `../projects/detail.php?id=${task.project_id}`;

    // Google Drive Link (Sesuai kolom drive_link DB)
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

    // Progress Notes & Revision Notes
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

    // Show Drawer
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