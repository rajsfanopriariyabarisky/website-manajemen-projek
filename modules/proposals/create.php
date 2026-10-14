<?php
require_once __DIR__ . '/../../config/database.php';

// Menangkap parameter auto-fill
$client_name  = trim($_GET['client_name'] ?? '');
$client_email = trim($_GET['client_email'] ?? '');
$title        = trim($_GET['title'] ?? '');

$error_msg = '';

// Auto-Generate Nomor Proposal
$auto_no_proposal = 'SPH/CTC/' . date('Ym') . '/' . rand(100, 999);

// Proses Simpan Surat Penawaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {$nomor_proposal      = trim($_POST['nomor_proposal'] ?? '');$client_name         = trim($_POST['client_name'] ?? '');$client_email        = trim($_POST['client_email'] ?? '');$title               = trim($_POST['title'] ?? '');$nilai_penawaran     = !empty($_POST['nilai_penawaran']) ? floatval($_POST['nilai_penawaran']) : 0;
    $estimasi_pengerjaan = trim($_POST['estimasi_pengerjaan'] ?? '');
    $ringkasan_scope     = trim($_POST['ringkasan_scope'] ?? '');

if (empty($nomor_proposal) || empty($client_name) || empty($title) || $nilai_penawaran <= 0) {
        $error_msg = 'Mohon lengkapi seluruh kolom bertanda bintang (*) dan nilai penawaran harus lebih dari 0!';
    } else {
        try {
            // Pastikan tabel proposals siap
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `proposals` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nomor_proposal` VARCHAR(100) NOT NULL,
                    `client_name` VARCHAR(250) NOT NULL,
                    `client_email` VARCHAR(250) NOT NULL,
                    `title` VARCHAR(250) NOT NULL,
                    `nilai_penawaran` DECIMAL(15,2) DEFAULT 0.00,
                    `estimasi_pengerjaan` VARCHAR(100) NULL,
                    `ringkasan_scope` TEXT NULL,
                    `status` ENUM('Draft', 'Sent', 'Accepted', 'Rejected') DEFAULT 'Sent',
                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");

            $stmt =$pdo->prepare("
                INSERT INTO proposals (nomor_proposal, client_name, client_email, title, nilai_penawaran, estimasi_pengerjaan, ringkasan_scope, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Sent')
            ");
            $stmt->execute([$nomor_proposal, $client_name,$client_email, $title,$nilai_penawaran, $estimasi_pengerjaan,$ringkasan_scope]);

            header("Location: index.php?msg=created");
            exit;
        } catch (Exception $e) {
            $error_msg = 'Terjadi kesalahan sistem: ' .$e->getMessage();
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 relative font-sans min-w-0">
    
    <!-- Header Navigation -->
    <div class="flex items-center justify-between gap-4 mb-6 bg-white p-5 md:p-6 rounded-2xl border border-slate-200/80 shadow-sm">
        <div class="flex items-center gap-4">
            <a href="index.php" class="w-10 h-10 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600">Modul Commercial Proposal</span>
                <h2 class="text-xl font-bold text-slate-800">Penyusunan Surat Penawaran Resmi (SPH)</h2>
            </div>
        </div>
    </div>

    <!-- Form Container -->
    <div class="max-w-4xl bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm">
        
        <?php if (!empty($error_msg)): ?>
            <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-xs flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-base"></i>
                <span><?= htmlspecialchars($error_msg) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5 text-xs">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Nomor SPH / Penawaran <span class="text-red-500">*</span></label>
                    <input type="text" name="nomor_proposal" value="<?= htmlspecialchars($auto_no_proposal) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 font-mono font-bold text-slate-800 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Estimasi Durasi Pengerjaan</label>
                    <input type="text" name="estimasi_pengerjaan" placeholder="Contoh: 30 Hari Kerja / 1 Bulan" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Nama Klien / Perusahaan <span class="text-red-500">*</span></label>
                    <input type="text" name="client_name" value="<?= htmlspecialchars($client_name) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
                <div>
                    <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Email Klien Resmi</label>
                    <input type="email" name="client_email" value="<?= htmlspecialchars($client_email) ?>" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
                </div>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Judul Sistem / Penawaran Proyek <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="<?= htmlspecialchars($title) ?>" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Nilai Penawaran Komersial (Rp) <span class="text-red-500">*</span></label>
                <input type="number" name="nilai_penawaran" placeholder="Contoh: 50000000" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 font-bold text-emerald-700 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">Ringkasan Scope & Layanan Garansi</label>
                <textarea name="ringkasan_scope" rows="4" placeholder="Uraikan item fitur utama, arsitektur sistem, layanan maintenance, serta garansi pengerjaan..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-slate-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-500 leading-relaxed"></textarea>
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <a href="index.php" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-bold transition">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition shadow-md flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane"></i> Simpan & Terbitkan Proposal
                </button>
            </div>

        </form>
    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>