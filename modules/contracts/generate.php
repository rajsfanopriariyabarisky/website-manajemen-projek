<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Auto-Generate Nomor Kontrak SPK
$currentYear = date('Y');
$currentMonthRoman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][(int)date('m') - 1];

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE YEAR(created_at) = ?");
$stmtCount->execute([$currentYear]);
$nextNo = $stmtCount->fetchColumn() + 1;

$autoNoKontrak = sprintf("%03d", $nextNo) . "/CTC-SPK/" . $currentMonthRoman . "/" . $currentYear;

$error = '';

// Handle Submit Form Kontrak
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_contract'])) {
    $project_id      = $_POST['project_id'];
    $nomor_kontrak   = trim($_POST['nomor_kontrak']);
    $tanggal_mulai   = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    $durasi_bulan    = (int)$_POST['durasi_bulan'];

    // SANITASI NOMINAL: Membuang koma desimal (,00) agar tidak berubah jadi 100x lipat
    $raw_budget = $_POST['nilai_kontrak'];
    $clean_before_comma = explode(',', $raw_budget)[0]; 
    $nilai_kontrak = (float)str_replace(['.', ' '], '', $clean_before_comma);

    if (!empty($project_id) && !empty($nomor_kontrak) && $nilai_kontrak > 0) {
        $stmtInsert = $pdo->prepare("
            INSERT INTO contracts (project_id, nomor_kontrak, tanggal_mulai, tanggal_selesai, durasi_bulan, nilai_kontrak)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$project_id, $nomor_kontrak, $tanggal_mulai, $tanggal_selesai, $durasi_bulan, $nilai_kontrak]);

        $contract_id = $pdo->lastInsertId();

        // Redirect langsung ke cetak PDF
        header("Location: print_pdf.php?id=" . $contract_id);
        exit;
    } else {
        $error = "Mohon pilih proyek acuan dan pastikan nominal valid!";
    }
}

// Fetch Proyek Terdaftar
$projects = $pdo->query("SELECT id, nama_projek, klien, budget, tanggal_mulai, deadline FROM projects ORDER BY nama_projek ASC")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="flex-1 p-4 md:p-8 overflow-y-auto bg-slate-50 min-h-screen w-full">
    
    <!-- Top Bar -->
    <div class="mb-6">
        <a href="index.php" class="text-xs font-bold text-emerald-700 hover:text-emerald-800 flex items-center gap-1.5 w-fit bg-emerald-50 px-3 py-1.5 rounded-lg border border-emerald-200 mb-3">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Arsip Kontrak
        </a>
        <h1 class="text-2xl font-bold text-slate-800">Generator Kontrak Resmi PT. CTC</h1>
        <p class="text-xs text-slate-500 mt-0.5">Lengkapi formulir di bawah ini untuk menerbitkan dokumen perjanjian SPK resmi.</p>
    </div>

    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs font-semibold mb-6 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation mr-1"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 shadow-sm max-w-4xl">
        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="generate_contract" value="1">

            <div>
                <label class="block text-xs font-bold uppercase text-slate-500 mb-1">PILIH PROYEK ACUAN *</label>
                <select name="project_id" id="project_select" onchange="autoFillForm()" required class="w-full px-4 py-3 rounded-xl border border-slate-200 text-xs font-semibold bg-white focus:ring-2 focus:ring-emerald-500">
                    <option value="">-- Pilih Proyek Target --</option>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $p['id'] ?>" 
                                data-budget="<?= (float)$p['budget'] ?>" 
                                data-start="<?= $p['tanggal_mulai'] ?>" 
                                data-deadline="<?= $p['deadline'] ?>">
                            <?= htmlspecialchars($p['nama_projek']) ?> (Klien: <?= htmlspecialchars($p['klien']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">NOMOR KONTRAK (AUTO-GENERATED)</label>
                    <input type="text" name="nomor_kontrak" readonly value="<?= $autoNoKontrak ?>" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-mono font-bold bg-slate-50 text-emerald-800">
                    <span class="text-[10px] text-slate-400 mt-1 block">Format otomatis berurutan sesuai nomor agenda perusahaan.</span>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">NILAI KONTRAK (RP) *</label>
                    <input type="text" name="nilai_kontrak" id="nilai_kontrak" required onkeyup="formatRupiah(this)" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-800 font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">DURASI PENGERJAAN (BULAN) *</label>
                    <input type="number" name="durasi_bulan" id="durasi_bulan" required min="1" value="1" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">TANGGAL MULAI *</label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" required onchange="calcDuration()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium">
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase text-slate-500 mb-1">TANGGAL SELESAI *</label>
                    <input type="date" name="tanggal_selesai" id="tanggal_selesai" required onchange="calcDuration()" class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-medium">
                </div>
            </div>

            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-base text-emerald-600 shrink-0"></i>
                <p>Dokumen kontrak ini akan di-generate menggunakan template standar legal korporat enterprise lengkap dengan pasal SLA, garansi, NDA, termin pembayaran, serta bidang meterai.</p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="index.php" class="px-5 py-2.5 rounded-xl border text-xs font-semibold text-slate-600 hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 flex items-center gap-2">
                    <i class="fa-solid fa-file-pdf"></i> Terbitkan & Cetak Kontrak PDF
                </button>
            </div>
        </form>
    </div>
</main>

<script>
function autoFillForm() {
    const select = document.getElementById('project_select');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.value !== '') {
        const rawBudget = parseFloat(selectedOption.getAttribute('data-budget')) || 0;
        const start = selectedOption.getAttribute('data-start');
        const deadline = selectedOption.getAttribute('data-deadline');

        // Format angka bulat tanpa pecahan desimal desimal ,00
        document.getElementById('nilai_kontrak').value = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(rawBudget));
        
        if (start) document.getElementById('tanggal_mulai').value = start;
        if (deadline) document.getElementById('tanggal_selesai').value = deadline;

        calcDuration();
    }
}

function calcDuration() {
    const startVal = document.getElementById('tanggal_mulai').value;
    const endVal = document.getElementById('tanggal_selesai').value;

    if (startVal && endVal) {
        const d1 = new Date(startVal);
        const d2 = new Date(endVal);
        
        let months = (d2.getFullYear() - d1.getFullYear()) * 12 + (d2.getMonth() - d1.getMonth());
        if (d2.getDate() >= d1.getDate()) {
            months += 1;
        }
        document.getElementById('durasi_bulan').value = Math.max(1, months);
    }
}

function formatRupiah(el) {
    let value = el.value.replace(/[^0-9]/g, '');
    el.value = value ? new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value) : '';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>