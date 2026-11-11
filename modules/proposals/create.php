<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Auto-Migration Guard: Pastikan tabel proposals dan kolom project_id terbuat otomatis
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `proposals` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `project_id` INT NULL,
            `nomor_proposal` VARCHAR(100) NOT NULL,
            `client_name` VARCHAR(250) NOT NULL,
            `client_email` VARCHAR(250) NOT NULL,
            `title` VARCHAR(250) NOT NULL,
            `nilai_penawaran` DECIMAL(15,2) DEFAULT 0.00,
            `persen_pajak` DECIMAL(5,2) DEFAULT 11.00,
            `nominal_pajak` DECIMAL(15,2) DEFAULT 0.00,
            `total_penawaran` DECIMAL(15,2) DEFAULT 0.00,
            `estimasi_pengerjaan` VARCHAR(100) NULL,
            `ringkasan_scope` TEXT NULL,
            `status` ENUM('Draft', 'Sent', 'Accepted', 'Rejected') DEFAULT 'Draft',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $checkCol = $pdo->query("SHOW COLUMNS FROM `proposals` LIKE 'project_id'")->fetch();
    if (!$checkCol) {
        $pdo->exec("ALTER TABLE `proposals` ADD COLUMN `project_id` INT NULL AFTER `id`");
    }
} catch (Exception $e) {}

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Menangkap parameter auto-fill dari Client Requirement / Proyek
$client_name  = trim($_GET['client_name'] ?? '');
$client_email = trim($_GET['client_email'] ?? '');
$title        = trim($_GET['title'] ?? '');
$budget_raw   = trim($_GET['budget'] ?? '');
$project_id   = intval($_GET['project_id'] ?? 0);

// Filter angka budget jika ada
$budget_clean = preg_replace('/[^0-9.]/', '', $budget_raw);
$budget_val   = is_numeric($budget_clean) ? (float)$budget_clean : 0.00;

$error_msg = '';

// Auto-Generate Nomor Proposal Berbasis DB Max ID
try {
    $stmtMax = $pdo->query("SELECT MAX(id) FROM proposals");
    $nextId  = (int)$stmtMax->fetchColumn() + 1;
    $auto_no_proposal = 'SPH/CTC/' . date('Ym') . '/' . sprintf('%04d', $nextId);
} catch (Exception $e) {
    $auto_no_proposal = 'SPH/CTC/' . date('Ym') . '/' . rand(1000, 9999);
}

// Proses Simpan Surat Penawaran
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomor_proposal      = trim($_POST['nomor_proposal'] ?? '');
    $client_name         = trim($_POST['client_name'] ?? '');
    $client_email        = trim($_POST['client_email'] ?? '');
    $title               = trim($_POST['title'] ?? '');
    $nilai_penawaran     = !empty($_POST['nilai_penawaran']) ? floatval($_POST['nilai_penawaran']) : 0.00; // DPP
    $persen_pajak        = isset($_POST['persen_pajak']) ? floatval($_POST['persen_pajak']) : 11.00;
    $estimasi_pengerjaan = trim($_POST['estimasi_pengerjaan'] ?? '');
    $ringkasan_scope     = trim($_POST['ringkasan_scope'] ?? '');
    $target_project_id   = isset($_POST['project_id']) && intval($_POST['project_id']) > 0 ? intval($_POST['project_id']) : ($project_id > 0 ? $project_id : null);

    // Kalkulasi Pajak & Grand Total
    $nominal_pajak   = round(($nilai_penawaran * $persen_pajak) / 100, 2);
    $total_penawaran = $nilai_penawaran + $nominal_pajak;

    if (empty($nomor_proposal) || empty($client_name) || empty($title) || $nilai_penawaran <= 0) {
        $error_msg = 'Mohon lengkapi seluruh kolom bertanda bintang (*) dan nilai penawaran (DPP) harus lebih dari 0!';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO proposals 
                (project_id, nomor_proposal, client_name, client_email, title, nilai_penawaran, persen_pajak, nominal_pajak, total_penawaran, estimasi_pengerjaan, ringkasan_scope, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Draft')
            ");
            $stmt->execute([
                $target_project_id,
                $nomor_proposal, 
                $client_name, 
                $client_email, 
                $title, 
                $nilai_penawaran, 
                $persen_pajak, 
                $nominal_pajak, 
                $total_penawaran, 
                $estimasi_pengerjaan, 
                $ringkasan_scope
            ]);

            // Auto-Sync Ke Data Proyek Terkait
            if ($target_project_id) {
                $stmtUpProj = $pdo->prepare("
                    UPDATE projects 
                    SET budget = ?, persen_pajak = ?, nominal_pajak = ?, total_nilai = ? 
                    WHERE id = ?
                ");
                $stmtUpProj->execute([$nilai_penawaran, $persen_pajak, $nominal_pajak, $total_penawaran, $target_project_id]);
            }

            header("Location: index.php?msg=created");
            exit;
        } catch (Exception $e) {
            $error_msg = 'Terjadi kesalahan sistem: ' . $e->getMessage();
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
            <?php if ($project_id > 0): ?>
                <input type="hidden" name="project_id" value="<?= $project_id ?>">
            <?php endif; ?>
            
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

            <!-- SECTION KALKULASI PAJAK & PENAWARAN -->
            <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-4">
                <span class="text-xs font-bold text-slate-800 uppercase tracking-wider block border-b border-slate-200 pb-2">
                    <i class="fa-solid fa-calculator text-emerald-600 mr-1"></i> Rincian Nilai Penawaran & Pajak
                </span>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Nilai Penawaran Bersih / DPP (Rp) <span class="text-red-500">*</span>
                        </label>
                        <input type="number" id="nilaiPenawaran" name="nilai_penawaran" value="<?= $budget_val > 0 ? $budget_val : '' ?>" placeholder="Contoh: 100000000" step="any" required 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white font-bold text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">*Dasar Pengenaan Pajak (DPP) sebelum penambahan pajak</p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Pajak PPN (%)
                        </label>
                        <input type="number" id="persenPajak" name="persen_pajak" value="11" min="0" max="100" step="0.1" 
                               class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white font-bold text-emerald-700 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <p class="text-[10px] text-slate-400 mt-1">*Default 11% sesuai standar SPH CTC</p>
                    </div>
                </div>

                <!-- REAL-TIME DISPLAY BREAKDOWN -->
                <div class="bg-white p-3.5 rounded-lg border border-slate-200/80 grid grid-cols-1 md:grid-cols-3 gap-3 text-xs font-mono">
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-sans font-semibold">Subtotal (DPP)</span>
                        <span id="previewSubtotal" class="font-bold text-slate-800">Rp 0</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-sans font-semibold">Nominal Pajak</span>
                        <span id="previewNominalPajak" class="font-bold text-amber-600">Rp 0</span>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-400 block uppercase font-sans font-semibold">Total Penawaran (Inc. Pajak)</span>
                        <span id="previewTotalPenawaran" class="font-bold text-emerald-700 text-sm">Rp 0</span>
                    </div>
                </div>
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

<script>
function hitungPajakRealtime() {
    const inputNilai = parseFloat(document.getElementById('nilaiPenawaran').value) || 0;
    const inputPajak = parseFloat(document.getElementById('persenPajak').value) || 0;

    const nominalPajak = Math.round((inputNilai * inputPajak) / 100);
    const totalPenawaran = inputNilai + nominalPajak;

    const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

    document.getElementById('previewSubtotal').innerText = 'Rp ' + formatter.format(inputNilai);
    document.getElementById('previewNominalPajak').innerText = 'Rp ' + formatter.format(nominalPajak);
    document.getElementById('previewTotalPenawaran').innerText = 'Rp ' + formatter.format(totalPenawaran);
}

document.getElementById('nilaiPenawaran').addEventListener('input', hitungPajakRealtime);
document.getElementById('persenPajak').addEventListener('input', hitungPajakRealtime);

document.addEventListener('DOMContentLoaded', hitungPajakRealtime);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>