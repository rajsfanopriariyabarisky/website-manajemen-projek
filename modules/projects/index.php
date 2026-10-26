<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Pastikan kolom requirement_id ada di tabel projects
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN requirement_id INT NULL AFTER client_token");
} catch (Exception $e) {}

// Menangkap Data Auto-Fill jika dipicu dari Convert to Project
$req_id      = intval($_GET['req_id'] ?? 0);
$auto_title  = trim($_GET['title'] ?? '');
$auto_client = trim($_GET['client_name'] ?? '');
$auto_budget = floatval($_GET['budget'] ?? 0);
$open_modal  = isset($_GET['convert_req']);

// Proteksi: Cegah pembukaan modal & auto-fill jika requirement ini SUDAH memiliki proyek aktif yang masih ada
if ($req_id > 0) {
    $stmtCheckActiveProj = $pdo->prepare("SELECT id FROM projects WHERE requirement_id = ?");
    $stmtCheckActiveProj->execute([$req_id]);
    if ($stmtCheckActiveProj->fetch()) {
        $open_modal = false;
        header("Location: index.php?msg=already_converted");
        exit;
    }
}

// Handle Tambah Proyek Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $nama_projek   = trim($_POST['nama_projek']);
    $klien         = trim($_POST['klien']);
    $budget        = $_POST['budget'] ?? 0;
    $status        = $_POST['status'] ?? 'On Hold'; // Default ke On Hold pas dikonversi
    $kategori_rab  = $_POST['kategori_rab'] ?? 'Website';
    $durasi_bulan  = max(1, (int)($_POST['durasi_bulan'] ?? 3));
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-d');
    $origin_req_id = intval($_POST['origin_req_id'] ?? 0);
    
    // Hitung tanggal deadline berdasarkan durasi bulan
    $deadline = date('Y-m-d', strtotime("+$durasi_bulan months", strtotime($tanggal_mulai)));

    // Olah data Custom RAB berbasis Persentase (%)
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
        // 1. Insert data Proyek Baru beserta requirement_id
        $stmtIns = $pdo->prepare("INSERT INTO projects (nama_projek, klien, budget, status, kategori_rab, rab_custom_data, tanggal_mulai, deadline, requirement_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$nama_projek, $klien, $budget, $status, $kategori_rab, $rab_custom_json, $tanggal_mulai, $deadline, $origin_req_id > 0 ? $origin_req_id : null]);
        $newProjectId = $pdo->lastInsertId();

        // 2. Auto-create draf Kontrak
        $nomorKontrak = sprintf("%03d", $newProjectId) . "/CTC-SPK/" . date('m/Y');
        $stmtContract = $pdo->prepare("INSERT INTO contracts (project_id, nomor_kontrak, durasi_bulan, tanggal_mulai, tanggal_selesai, nilai_kontrak) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtContract->execute([$newProjectId, $nomorKontrak, $durasi_bulan, $tanggal_mulai, $deadline, $budget]);

        // 3. Auto-update status Requirement ke 'Proposal Sent' (BUKAN Deal / Accepted)
        if ($origin_req_id > 0) {
            $stmtUpdReq = $pdo->prepare("UPDATE client_requirements SET status = 'Proposal Sent' WHERE id = ?");
            $stmtUpdReq->execute([$origin_req_id]);

            // Pesan otomatis di Lobi Chat Klien
            $stmtChatSys = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) VALUES (?, 'admin', 'System CTC', ?, 1, 0, NOW())");
            $stmtChatSys->execute([$origin_req_id, "Penawaran resmi proyek [ {$nama_projek} ] telah dibuat (Proposal Sent). Status proyek diset 'On Hold' hingga persetujuan Klien."]);
        }

        header("Location: index.php?msg=created");
        exit;
    }
}

// Handle Hapus Proyek & RESET STATUS REQUIREMENT KEMBALI KE 'Under Review'
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $projId = intval($_GET['id']);
    
    // Ambil requirement_id dari proyek ini
    $stmtGetReqId = $pdo->prepare("SELECT requirement_id FROM projects WHERE id = ?");
    $stmtGetReqId->execute([$projId]);
    $linkedReqId = $stmtGetReqId->fetchColumn();

    // Hapus proyek
    $stmtDel = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmtDel->execute([$projId]);

    // Jika proyek terhubung dengan requirement, kembalikan statusnya ke 'Under Review' agar bisa dikonversi ulang
    if ($linkedReqId) {
        $stmtResetReq = $pdo->prepare("UPDATE client_requirements SET status = 'Under Review' WHERE id = ?");
        $stmtResetReq->execute([$linkedReqId]);
    }

    header("Location: index.php?msg=deleted");
    exit;
}

// Fetch Daftar Proyek
$stmtProjects = $pdo->query("
    SELECT p.*, 
           COALESCE(c.durasi_bulan, 3) AS durasi_bulan,
           COUNT(t.id) AS total_tasks,
           SUM(CASE WHEN LOWER(t.status) = 'done' THEN 1 ELSE 0 END) AS completed_tasks,
           COALESCE((SELECT SUM(jumlah_biaya) FROM expenses WHERE project_id = p.id), 0) AS total_pengeluaran
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    LEFT JOIN tasks t ON p.id = t.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$projects = $stmtProjects->fetchAll();

// Metrics
$totalProjectCount = count($projects);
$inProgressCount   = 0;
$completedCount    = 0;

foreach ($projects as $pr) {
    if ($pr['status'] === 'In Progress') { $inProgressCount++; }
    elseif ($pr['status'] === 'Completed') { $completedCount++; }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { font-family: 'Plus Jakarta Sans', sans-serif !important; }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
    .project-card { transition: transform .25s cubic-bezier(.22,.68,0,1), box-shadow .25s ease, border-color .25s ease; }
    .project-card:hover { transform: translateY(-3px); box-shadow: 0 12px 24px -8px rgba(15,23,42,.1); border-color: rgba(5,150,105,.4); }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation & Metric Tracker -->
    <div class="flex flex-col lg:flex-row justify-between lg:items-center gap-5 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div class="shrink-0">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    MODUL 1 • PORTFOLIO PROYEK
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Manajemen Proyek & Timeline</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Kelola daftar seluruh proyek PT. CTC beserta progress pengerjaan dan alokasi budget.</p>
        </div>

        <!-- Metric Counter Badges & Action Button -->
        <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto justify-start lg:justify-end">
            <div class="flex items-center gap-3 bg-slate-50 border border-slate-200/80 p-2 px-3.5 rounded-xl text-xs font-semibold shrink-0">
                <div class="flex items-center gap-2 pr-3 border-r border-slate-200">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <span class="text-slate-500 text-xs">Total: <strong class="text-slate-900 font-bold"><?= $totalProjectCount ?></strong></span>
                </div>
                <div class="flex items-center gap-2 pr-3 border-r border-slate-200">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <span class="text-slate-500 text-xs">Berjalan: <strong class="text-blue-700 font-bold"><?= $inProgressCount ?></strong></span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-teal-500"></span>
                    <span class="text-slate-500 text-xs">Selesai: <strong class="text-teal-700 font-bold"><?= $completedCount ?></strong></span>
                </div>
            </div>

            <button onclick="openAddProjectModal()" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 shrink-0">
                <i class="fa-solid fa-plus text-xs sm:text-sm"></i> Tambah Proyek Baru
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i> Proyek baru berhasil ditambahkan! Status Kebutuhan Klien diperbarui menjadi Proposal Sent.
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'already_converted'): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base"></i> Requirement ini sudah dikonversi menjadi Proyek Aktif sebelumnya!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-trash-can text-red-500 text-base"></i> Proyek telah dihapus dari sistem & status Kebutuhan Klien telah di-reset ke Under Review.
        </div>
    <?php endif; ?>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="projectSearchInput" onkeyup="filterProjects()" placeholder="Cari nama proyek / klien..." class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterProjects()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status Proyek</option>
                <option value="In Progress">In Progress (Berjalan)</option>
                <option value="On Hold">On Hold (Ditunda)</option>
                <option value="Completed">Completed (Selesai)</option>
            </select>
        </div>
    </div>

    <!-- Project Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="projectsGrid">
        <?php if (empty($projects)): ?>
            <div class="col-span-full bg-white border border-slate-200/90 rounded-2xl p-12 text-center text-slate-400 shadow-xs">
                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <p class="font-bold text-slate-800 text-sm sm:text-base">Belum ada proyek yang terdaftar saat ini.</p>
                <p class="text-xs sm:text-sm text-slate-400 mt-1 font-normal mb-4">Mulai dengan membuat proyek baru untuk alokasi pengerjaan tim.</p>
                <button onclick="openAddProjectModal()" class="btn-press px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-xs sm:text-sm font-semibold shadow-xs">
                    Buat Proyek Pertama
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <?php
                    $totalT = $p['total_tasks'] ?? 0;
                    $compT  = $p['completed_tasks'] ?? 0;
                    $progressPct = $totalT > 0 ? round(($compT / $totalT) * 100) : 0;

                    $todayTime    = new DateTime();
                    $deadlineTime = new DateTime($p['deadline']);
                    $intervalDays = (int)$todayTime->diff($deadlineTime)->format("%r%a");
                    $isUrgent     = ($intervalDays <= 7 && $p['status'] !== 'Completed');
                ?>
                <div class="project-card bg-white border <?= $isUrgent ? 'border-red-300 ring-2 ring-red-500/10' : 'border-slate-200/90' ?> rounded-2xl p-5 shadow-xs flex flex-col justify-between relative overflow-hidden" 
                     data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' . $p['klien'])) ?>"
                     data-status="<?= htmlspecialchars($p['status']) ?>">
                    
                    <div>
                        <!-- Header Status Card & Action Buttons -->
                        <div class="flex justify-between items-center mb-3.5">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2.5 py-1 text-[10px] uppercase font-semibold rounded-lg border tracking-wider
                                    <?php
                                        switch($p['status']) {
                                            case 'In Progress': echo 'bg-blue-50 text-blue-700 border-blue-200'; break;
                                            case 'Completed': echo 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                                            case 'On Hold': echo 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                            default: echo 'bg-slate-100 text-slate-600 border-slate-200';
                                        }
                                    ?>">
                                    <?= htmlspecialchars($p['status']) ?>
                                </span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-md bg-slate-100 text-slate-600 border border-slate-200">
                                    <?= htmlspecialchars($p['durasi_bulan']) ?> Bulan
                                </span>
                            </div>

                            <div class="flex items-center gap-1 z-10">
                                <a href="detail.php?id=<?= $p['id'] ?>" class="btn-press text-slate-400 hover:text-emerald-700 p-1.5 rounded-lg hover:bg-slate-50 transition" title="Detail & Konfigurasi">
                                    <i class="fa-regular fa-pen-to-square text-xs"></i>
                                </a>
                                <a href="index.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('Yakin ingin menghapus proyek ini? Requirement terkait akan di-reset kembali ke Under Review.')" class="btn-press text-slate-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-red-50 transition" title="Hapus Proyek">
                                    <i class="fa-regular fa-trash-can text-xs"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Info Proyek -->
                        <a href="detail.php?id=<?= $p['id'] ?>" class="block group">
                            <h3 class="text-base font-bold text-slate-900 leading-snug line-clamp-1 group-hover:text-emerald-700 transition mb-1">
                                <?= htmlspecialchars($p['nama_projek']) ?>
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-500 font-normal flex items-center gap-1.5 mb-4">
                                <i class="fa-solid fa-building text-slate-400 text-xs"></i> Klien: <span class="text-slate-800 font-semibold"><?= htmlspecialchars($p['klien']) ?></span>
                            </p>
                        </a>

                        <!-- Progress Bar Milestone -->
                        <div class="mb-4">
                            <div class="flex justify-between text-xs font-semibold mb-1">
                                <span class="text-slate-400 uppercase text-[10px] tracking-wider font-normal">Progress Pengerjaan</span>
                                <span class="text-emerald-700 font-bold"><?= $progressPct ?>% (<?= $compT ?>/<?= $totalT ?> Modul)</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200/80 p-0.5">
                                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $progressPct ?>%"></div>
                            </div>
                        </div>

                        <!-- Metric Box Budget & Pengeluaran -->
                        <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3 grid grid-cols-2 gap-2 text-xs mb-4">
                            <div>
                                <span class="text-[10px] text-slate-400 font-medium block uppercase tracking-wider mb-0.5">Budget Proyek</span>
                                <span class="font-bold text-slate-900 text-xs sm:text-sm font-mono">
                                    Rp <?= number_format($p['budget'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-medium block uppercase tracking-wider mb-0.5">Pengeluaran</span>
                                <span class="font-bold text-amber-600 text-xs sm:text-sm font-mono">
                                    Rp <?= number_format($p['total_pengeluaran'], 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer & Deadline Alert -->
                    <div class="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
                        <div class="flex items-center gap-1.5">
                            <?php if ($isUrgent): ?>
                                <div class="relative flex items-center justify-center text-red-600 animate-bounce" title="Deadline proyek kurang dari 7 hari!">
                                    <i class="fa-solid fa-bell text-xs"></i>
                                </div>
                                <span class="text-[10px] font-bold uppercase text-red-600 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded">
                                    <?= $intervalDays < 0 ? 'Overdue' : ($intervalDays == 0 ? 'Hari Ini' : 'H-' . $intervalDays) ?>
                                </span>
                            <?php else: ?>
                                <i class="fa-regular fa-calendar-check text-slate-400"></i>
                                <span class="text-slate-500 font-medium text-xs"><?= date('d M Y', strtotime($p['deadline'])) ?></span>
                            <?php endif; ?>
                        </div>

                        <a href="detail.php?id=<?= $p['id'] ?>" class="font-semibold text-emerald-700 hover:text-emerald-800 flex items-center gap-1 transition text-xs">
                            Detail & Modul <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Paginasi -->
    <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-6 mt-6 border-t border-slate-200/80 text-xs text-slate-500">
        <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
        <div class="flex items-center gap-1.5" id="paginationControls"></div>
    </div>

</main>

<!-- Modal Pop-Up Tambah Proyek Baru -->
<div id="addProjectModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-50 <?= $open_modal ? '' : 'hidden' ?> flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full p-6 shadow-2xl relative my-8 text-xs sm:text-sm">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="font-bold text-slate-900 text-base flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-emerald-600"></i> Buat Proyek Baru
            </h3>
            <button onclick="closeAddProjectModal()" class="w-7 h-7 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <form action="" method="POST" onsubmit="return validateCustomRabSubmit()" class="space-y-3.5">
            <input type="hidden" name="add_project" value="1">
            <input type="hidden" name="origin_req_id" value="<?= $req_id ?>">

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Proyek <span class="text-red-500">*</span></label>
                <input type="text" name="nama_projek" value="<?= htmlspecialchars($auto_title) ?>" required placeholder="Contoh: Website Company Profile" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Klien <span class="text-red-500">*</span></label>
                <input type="text" name="klien" value="<?= htmlspecialchars($auto_client) ?>" required placeholder="Contoh: PT. Puth Family" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nilai Budget (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="budget" id="projectBudgetInput" value="<?= $auto_budget > 0 ? $auto_budget : '' ?>" required placeholder="20000000" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none placeholder:text-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Status Proyek <span class="text-red-500">*</span></label>
                    <select name="status" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                        <option value="On Hold" selected>On Hold (Draft Proposal)</option>
                        <option value="In Progress">In Progress (Berjalan)</option>
                        <option value="Completed">Completed (Selesai)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Kategori & Template RAB <span class="text-red-500">*</span></label>
                <select name="kategori_rab" id="kategoriRabSelect" onchange="toggleCustomRabSection()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Website" selected>RAB Website (Auto-Calculate)</option>
                    <option value="IoT">RAB IoT Hardware & Cloud (Auto-Calculate)</option>
                    <option value="Mobile">RAB Mobile App Android/iOS (Auto-Calculate)</option>
                    <option value="Custom">Custom RAB (Input Manual Komponen & % Persen)</option>
                </select>
            </div>

            <div id="customRabSection" class="hidden bg-slate-50 border border-slate-200 p-3.5 rounded-xl space-y-3">
                <div class="flex justify-between items-center border-b border-slate-200 pb-2">
                    <div>
                        <span class="text-xs font-bold text-emerald-800 uppercase block">Rincian Custom RAB (%)</span>
                        <span class="text-[11px] font-normal text-slate-400">Total alokasi persen harus tepat 100%</span>
                    </div>
                    <button type="button" onclick="addCustomRabRow()" class="text-xs font-semibold text-emerald-700 bg-emerald-100 hover:bg-emerald-200 px-2.5 py-1 rounded-lg transition">
                        + Tambah Baris
                    </button>
                </div>

                <div id="customRabRows" class="space-y-2">
                    <div class="grid grid-cols-12 gap-1.5 items-center custom-rab-row">
                        <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                        <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-5 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
                        <div class="col-span-3 flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1.5">
                            <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none custom-persen-input">
                            <span class="text-xs font-bold text-slate-400 ml-0.5">%</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center pt-2 border-t border-slate-200 text-xs font-semibold">
                    <span class="text-slate-500 uppercase text-[10px]">Total Persentase:</span>
                    <span id="totalPersenBadge" class="px-2 py-0.5 rounded-md bg-amber-100 text-amber-700 text-xs font-mono">
                        0% / 100%
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Durasi Proyek (Bulan) <span class="text-red-500">*</span></label>
                    <input type="number" name="durasi_bulan" min="1" max="36" required value="3" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_mulai" required value="<?= date('Y-m-d') ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="closeAddProjectModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-plus text-xs"></i> Simpan Proyek
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const cardsPerPage = 6;
let currentPage = 1;
let filteredCards = [];

document.addEventListener('DOMContentLoaded', function() {
    filterProjects();
});

function openAddProjectModal() {
    document.getElementById('addProjectModal').classList.remove('hidden');
}

function closeAddProjectModal() {
    document.getElementById('addProjectModal').classList.add('hidden');
}

function toggleCustomRabSection() {
    const val = document.getElementById('kategoriRabSelect').value;
    const sec = document.getElementById('customRabSection');
    if (val === 'Custom') {
        sec.classList.remove('hidden');
        calculateCustomPercentageTotal();
    } else {
        sec.classList.add('hidden');
    }
}

function addCustomRabRow() {
    const container = document.getElementById('customRabRows');
    const newRow = document.createElement('div');
    newRow.className = 'grid grid-cols-12 gap-1.5 items-center custom-rab-row';
    newRow.innerHTML = `
        <input type="text" name="custom_komponen[]" placeholder="Komponen" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
        <input type="text" name="custom_deskripsi[]" placeholder="Deskripsi/Output" class="col-span-4 px-2.5 py-1.5 border border-slate-300 bg-white rounded-lg text-xs font-normal focus:outline-none">
        <div class="col-span-3 flex items-center bg-white border border-slate-300 rounded-lg px-2 py-1.5">
            <input type="number" name="custom_persen[]" step="0.1" min="0" max="100" oninput="calculateCustomPercentageTotal()" placeholder="0" class="w-full text-xs font-mono font-bold focus:outline-none custom-persen-input">
            <span class="text-xs font-bold text-slate-400 ml-0.5">%</span>
        </div>
        <button type="button" onclick="this.parentElement.remove(); calculateCustomPercentageTotal();" class="col-span-1 text-red-500 hover:text-red-700 font-bold text-center text-xs">
            &times;
        </button>
    `;
    container.appendChild(newRow);
    calculateCustomPercentageTotal();
}

function calculateCustomPercentageTotal() {
    let total = 0;
    document.querySelectorAll('.custom-persen-input').forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    const badge = document.getElementById('totalPersenBadge');
    badge.innerText = `${total.toFixed(1)}% / 100%`;

    if (Math.abs(total - 100) < 0.01) {
        badge.className = "px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 text-xs font-mono font-bold";
    } else {
        badge.className = "px-2 py-0.5 rounded-md bg-red-100 text-red-700 text-xs font-mono font-bold";
    }
    return total;
}

function validateCustomRabSubmit() {
    const val = document.getElementById('kategoriRabSelect').value;
    if (val === 'Custom') {
        const totalPct = calculateCustomPercentageTotal();
        if (Math.abs(totalPct - 100) > 0.01) {
            alert(`Total persentase Custom RAB saat ini ${totalPct.toFixed(1)}%. Total harus pas 100% agar tidak ada selisih budget!`);
            return false;
        }
    }
    return true;
}

function filterProjects() {
    const searchVal = document.getElementById('projectSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const allCards  = Array.from(document.querySelectorAll('.project-card'));

    filteredCards = allCards.filter(card => {
        const searchData = card.getAttribute('data-search') || '';
        const statusData = card.getAttribute('data-status') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (statusData === statusVal);

        return matchesSearch && matchesStatus;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allCards = document.querySelectorAll('.project-card');
    allCards.forEach(card => card.style.display = 'none');

    const totalItems = filteredCards.length;
    const totalPages = Math.ceil(totalItems / cardsPerPage) || 1;

    const startIdx = (currentPage - 1) * cardsPerPage;
    const endIdx   = startIdx + cardsPerPage;

    filteredCards.slice(startIdx, endIdx).forEach(card => {
        card.style.display = 'flex';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd   = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} proyek`;

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