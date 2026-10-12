<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Handle Tambah Proyek Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $nama_projek   = trim($_POST['nama_projek']);
    $klien         = trim($_POST['klien']);
    $budget        = $_POST['budget'] ?? 0;
    $status        = $_POST['status'] ?? 'On Hold';
    $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-d');
    $deadline      = $_POST['deadline'];

    if (!empty($nama_projek) && !empty($klien)) {
        $stmtIns = $pdo->prepare("INSERT INTO projects (nama_projek, klien, budget, status, tanggal_mulai, deadline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtIns->execute([$nama_projek, $klien, $budget, $status, $tanggal_mulai, $deadline]);
        header("Location: index.php?msg=created");
        exit;
    }
}

// Handle Hapus Proyek
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $projId = $_GET['id'];
    $stmtDel = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $stmtDel->execute([$projId]);
    header("Location: index.php?msg=deleted");
    exit;
}

// Fetch Daftar Proyek Beserta Statistik Task & Realisasi Pengeluaran dari Tabel Expenses
$stmtProjects = $pdo->query("
    SELECT p.*, 
           COUNT(t.id) AS total_tasks,
           SUM(CASE WHEN LOWER(t.status) = 'done' THEN 1 ELSE 0 END) AS completed_tasks,
           COALESCE((SELECT SUM(jumlah_biaya) FROM expenses WHERE project_id = p.id), 0) AS total_pengeluaran
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$projects = $stmtProjects->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    .project-card { transition: transform .25s cubic-bezier(.22,.68,0,1), box-shadow .25s ease, border-color .25s ease; }
    .project-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 26px -10px rgba(15,23,42,.12);
        border-color: rgba(16,185,129,.4);
    }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation -->
    <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-sm">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                    MODUL 1 &bull; PORTFOLIO PROYEK
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Manajemen Proyek & Timeline</h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Kelola daftar seluruh proyek PT. CTC beserta progress pengerjaan dan alokasi budget.</p>
        </div>

        <button onclick="openAddProjectModal()" class="btn-press w-full sm:w-auto px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-extrabold transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-2 shrink-0">
            <i class="fa-solid fa-plus text-xs"></i> Tambah Proyek Baru
        </button>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'created'): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> Proyek baru berhasil ditambahkan ke dalam portofolio!
        </div>
    <?php elseif (isset($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-trash-can text-base text-red-600"></i> Proyek telah berhasil dihapus dari sistem.
        </div>
    <?php endif; ?>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-72">
            <input type="text" id="projectSearchInput" onkeyup="filterProjects()" placeholder="Cari nama proyek / klien..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 bg-white focus:outline-none">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterProjects()" class="input-glow px-3 py-2 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status Proyek</option>
                <option value="In Progress">In Progress (Berjalan)</option>
                <option value="On Hold">On Hold (Ditunda)</option>
                <option value="Completed">Completed (Selesai)</option>
            </select>
        </div>
    </div>

    <!-- Project Cards Grid (Responsive Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5" id="projectsGrid">
        <?php if (empty($projects)): ?>
            <div class="col-span-full bg-white border border-slate-200/90 rounded-2xl p-12 text-center text-slate-400 shadow-sm">
                <div class="w-16 h-16 mx-auto mb-3 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <p class="font-extrabold text-slate-800 text-sm">Belum ada proyek yang terdaftar saat ini.</p>
                <p class="text-xs text-slate-400 mt-1 font-medium mb-4">Mulai dengan membuat proyek baru untuk alokasi pengerjaan tim.</p>
                <button onclick="openAddProjectModal()" class="btn-press px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-extrabold shadow-md">
                    Buat Proyek Pertama
                </button>
            </div>
        <?php else: ?>
            <?php foreach ($projects as $p): ?>
                <?php
                    $totalT = $p['total_tasks'] ?? 0;
                    $compT  = $p['completed_tasks'] ?? 0;
                    $progressPct = $totalT > 0 ? round(($compT / $totalT) * 100) : 0;
                ?>
                <div class="project-card bg-white border border-slate-200/90 rounded-2xl p-5 shadow-sm flex flex-col justify-between relative overflow-hidden" 
                     data-search="<?= htmlspecialchars(strtolower($p['nama_projek'] . ' ' . $p['klien'])) ?>"
                     data-status="<?= htmlspecialchars($p['status']) ?>">
                    
                    <div>
                        <!-- Header Status Card & Action Buttons -->
                        <div class="flex justify-between items-center mb-3.5">
                            <span class="px-2.5 py-1 text-[9px] uppercase font-black rounded-lg border tracking-wide
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

                            <div class="flex items-center gap-1.5 z-10">
                                <a href="detail.php?id=<?= $p['id'] ?>" class="btn-press text-slate-400 hover:text-emerald-700 p-1.5 rounded-lg hover:bg-slate-100 transition" title="Konfigurasi Proyek">
                                    <i class="fa-regular fa-pen-to-square text-xs"></i>
                                </a>
                                <a href="index.php?action=delete&id=<?= $p['id'] ?>" onclick="return confirm('Yakin ingin menghapus proyek ini beserta seluruh modul di dalamnya?')" class="btn-press text-slate-400 hover:text-red-600 p-1.5 rounded-lg hover:bg-slate-100 transition" title="Hapus Proyek">
                                    <i class="fa-regular fa-trash-can text-xs"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Info Proyek -->
                        <a href="detail.php?id=<?= $p['id'] ?>" class="block group">
                            <h3 class="text-base font-black text-slate-900 leading-snug line-clamp-1 group-hover:text-emerald-700 transition mb-1">
                                <?= htmlspecialchars($p['nama_projek']) ?>
                            </h3>
                            <p class="text-xs text-slate-500 font-bold flex items-center gap-1.5 mb-4">
                                <i class="fa-solid fa-building text-slate-400 text-[11px]"></i> Klien: <span class="text-slate-800 font-extrabold"><?= htmlspecialchars($p['klien']) ?></span>
                            </p>
                        </a>

                        <!-- Progress Bar Milestone -->
                        <div class="mb-4">
                            <div class="flex justify-between text-[11px] font-black mb-1">
                                <span class="text-slate-400 uppercase text-[9px] tracking-wider">Progress Pengerjaan</span>
                                <span class="text-emerald-700 font-black"><?= $progressPct ?>% (<?= $compT ?>/<?= $totalT ?> Modul)</span>
                            </div>
                            <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200/80 p-0.5">
                                <div class="bg-gradient-to-r from-emerald-500 to-teal-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $progressPct ?>%"></div>
                            </div>
                        </div>

                        <!-- Metric Box Budget & Pengeluaran -->
                        <div class="bg-slate-50 border border-slate-200/70 rounded-xl p-3 grid grid-cols-2 gap-2 text-xs mb-4">
                            <div>
                                <span class="text-[9px] text-slate-400 font-black block uppercase tracking-wider">Budget Proyek</span>
                                <span class="font-black text-slate-900 text-xs font-mono">
                                    Rp <?= number_format($p['budget'], 0, ',', '.') ?>
                                </span>
                            </div>
                            <div>
                                <span class="text-[9px] text-slate-400 font-black block uppercase tracking-wider">Pengeluaran</span>
                                <span class="font-black text-amber-600 text-xs font-mono">
                                    Rp <?= number_format($p['total_pengeluaran'], 0, ',', '.') ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Footer & Link Detail -->
                    <div class="pt-3 border-t border-slate-100 flex justify-between items-center text-xs">
                        <span class="text-slate-500 font-bold text-[11px] flex items-center gap-1">
                            <i class="fa-regular fa-calendar-check text-slate-400"></i> <?= date('d M Y', strtotime($p['deadline'])) ?>
                        </span>
                        <a href="detail.php?id=<?= $p['id'] ?>" class="font-extrabold text-emerald-700 hover:text-emerald-900 flex items-center gap-1 transition text-xs">
                            Detail & Modul <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

</main>

<!-- Modal Pop-Up Tambah Proyek Baru -->
<div id="addProjectModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl relative">
        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
            <h3 class="font-black text-slate-800 text-base flex items-center gap-2">
                <i class="fa-solid fa-folder-plus text-emerald-600"></i> Buat Proyek Baru
            </h3>
            <button onclick="closeAddProjectModal()" class="text-slate-400 hover:text-slate-600 text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="" method="POST" class="space-y-4">
            <input type="hidden" name="add_project" value="1">

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Proyek *</label>
                <input type="text" name="nama_projek" required placeholder="Contoh: Landing Page FanTravel" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Klien *</label>
                <input type="text" name="klien" required placeholder="Contoh: PT. FV Travel Indonesia" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nilai Budget (Rp)</label>
                    <input type="number" name="budget" placeholder="50000000" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-mono font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Status Proyek *</label>
                    <select name="status" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs bg-white font-extrabold text-slate-700 focus:outline-none">
                        <option value="On Hold" selected>On Hold (Ditunda)</option>
                        <option value="In Progress">In Progress (Berjalan)</option>
                        <option value="Completed">Completed (Selesai)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Target Deadline *</label>
                <input type="date" name="deadline" required value="<?= date('Y-m-d') ?>" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs focus:outline-none font-bold">
            </div>

            <div class="flex justify-end gap-2 pt-3">
                <button type="button" onclick="closeAddProjectModal()" class="btn-press px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20">
                    <i class="fa-solid fa-plus mr-1"></i> Simpan Proyek
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddProjectModal() {
    document.getElementById('addProjectModal').classList.remove('hidden');
}

function closeAddProjectModal() {
    document.getElementById('addProjectModal').classList.add('hidden');
}

function filterProjects() {
    const searchVal = document.getElementById('projectSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const cards = document.querySelectorAll('.project-card');

    cards.forEach(card => {
        const searchData = card.getAttribute('data-search') || '';
        const statusData = card.getAttribute('data-status') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (statusData === statusVal);

        if (matchesSearch && matchesStatus) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>