<?php
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_projek   = trim($_POST['nama_projek']);
    $klien         = trim($_POST['klien']);
    $budget        = $_POST['budget'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $deadline      = $_POST['deadline'];
    $status        = $_POST['status'];

    if (!empty($nama_projek) && !empty($klien) && !empty($budget)) {
        $stmtUpdate = $pdo->prepare("
            UPDATE projects 
            SET nama_projek = ?, klien = ?, budget = ?, tanggal_mulai = ?, deadline = ?, status = ? 
            WHERE id = ?
        ");
        $stmtUpdate->execute([$nama_projek, $klien, $budget, $tanggal_mulai, $deadline, $status, $id]);

        header("Location: index.php");
        exit;
    } else {
        $error = "Mohon isi seluruh kolom wajib!";
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }

    .btn-press { 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    .btn-press:active { 
        transform: translateY(1px); 
    }

    .input-glow { 
        transition: box-shadow .2s ease, border-color .2s ease; 
    }
    .input-glow:focus { 
        border-color: #059669; 
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
    }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">
    <div class="max-w-2xl mx-auto">
        
        <!-- Top Navigation -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="index.php" class="btn-press text-xs font-semibold text-emerald-700 hover:text-emerald-800 mb-1.5 inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Batal Edit
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Edit Data Proyek</h1>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
                <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4 text-xs sm:text-sm">
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Proyek <span class="text-red-500">*</span></label>
                <input type="text" name="nama_projek" required value="<?= htmlspecialchars($project['nama_projek']) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Klien / Perusahaan <span class="text-red-500">*</span></label>
                    <input type="text" name="klien" required value="<?= htmlspecialchars($project['klien']) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Budget Proyek (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="budget" required value="<?= $project['budget'] ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_mulai" required value="<?= $project['tanggal_mulai'] ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Deadline Selesai <span class="text-red-500">*</span></label>
                    <input type="date" name="deadline" required value="<?= $project['deadline'] ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Status Proyek</label>
                <select name="status" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Planning" <?= $project['status'] === 'Planning' ? 'selected' : '' ?>>Planning</option>
                    <option value="In Progress" <?= $project['status'] === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="On Hold" <?= $project['status'] === 'On Hold' ? 'selected' : '' ?>>On Hold</option>
                    <option value="Completed" <?= $project['status'] === 'Completed' ? 'selected' : '' ?>>Completed</option>
                </select>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <a href="index.php" class="btn-press px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="btn-press px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Perbarui Proyek
                </button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>