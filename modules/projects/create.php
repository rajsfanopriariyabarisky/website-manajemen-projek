<?php
require_once __DIR__ . '/../../config/database.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_projek   = trim($_POST['nama_projek']);
    $klien         = trim($_POST['klien']);
    $budget        = str_replace('.', '', $_POST['budget']);
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $deadline      = $_POST['deadline'];
    $status        = $_POST['status'];

    if (!empty($nama_projek) && !empty($klien) && !empty($budget)) {
        $stmt = $pdo->prepare("INSERT INTO projects (nama_projek, klien, budget, tanggal_mulai, deadline, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nama_projek, $klien, $budget, $tanggal_mulai, $deadline, $status]);

        header("Location: index.php");
        exit;
    } else {
        $error = "Mohon isi seluruh kolom wajib!";
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="flex-1 p-8 overflow-y-auto bg-slate-50">
    <div class="max-w-2xl mx-auto">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="index.php" class="text-xs text-emerald-600 font-semibold hover:underline mb-1 inline-block"><i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar Proyek</a>
                <h2 class="text-2xl font-bold text-slate-800">Tambah Proyek Baru</h2>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm mb-6">
                <?= $error ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm space-y-5">
            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Proyek *</label>
                <input type="text" name="nama_projek" required placeholder="Contoh: Pengembangan Website Sistem Informasi" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Nama Klien / Perusahaan *</label>
                    <input type="text" name="klien" required placeholder="Contoh: PT. Maju Bersama" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Budget Proyek (Rp) *</label>
                    <input type="number" name="budget" required placeholder="50000000" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Tanggal Mulai *</label>
                    <input type="date" name="tanggal_mulai" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Deadline Selesai *</label>
                    <input type="date" name="deadline" required value="<?= date('Y-m-d', strtotime('+1 month')) ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">Status Proyek awal</label>
                <select name="status" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500 text-sm bg-white">
                    <option value="Planning">Planning</option>
                    <option value="In Progress" selected>In Progress</option>
                    <option value="On Hold">On Hold</option>
                    <option value="Completed">Completed</option>
                </select>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-3">
                <a href="index.php" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-medium text-sm hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-sm shadow-md shadow-emerald-600/20">Simpan Proyek</button>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>