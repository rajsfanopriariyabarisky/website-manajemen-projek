<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Auto-Migration Schema: Pastikan kolom pajak & requirement_id tersedia di tabel projects
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN persen_pajak DECIMAL(5,2) DEFAULT 11.00 AFTER budget");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN nominal_pajak DECIMAL(15,2) DEFAULT 0.00 AFTER persen_pajak");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN total_nilai DECIMAL(15,2) DEFAULT 0.00 AFTER nominal_pajak");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE projects ADD COLUMN requirement_id INT NULL AFTER client_token");
} catch (Exception $e) {}

// Menangkap Data Auto-Fill jika dipicu dari Convert Requirement (modules/requirements/detail.php)
$req_id      = intval($_GET['req_id'] ?? 0);
$auto_title  = trim($_GET['title'] ?? '');
$auto_client = trim($_GET['client_name'] ?? '');
$auto_budget = floatval($_GET['budget'] ?? 0);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_projek   = trim($_POST['nama_projek']);
    $klien         = trim($_POST['klien']);
    $budget        = floatval(str_replace(['.', ','], ['', '.'], $_POST['budget'] ?? 0)); // DPP
    $persen_pajak  = floatval($_POST['persen_pajak'] ?? 11.00);
    $nominal_pajak = round(($budget * $persen_pajak) / 100, 2);
    $total_nilai   = $budget + $nominal_pajak;

    $tanggal_mulai = $_POST['tanggal_mulai'] ?? date('Y-m-d');
    $durasi_bulan  = max(1, (int)($_POST['durasi_bulan'] ?? 1));
    $deadline      = $_POST['deadline'] ?? date('Y-m-d', strtotime("+$durasi_bulan months", strtotime($tanggal_mulai)));
    $status        = $_POST['status'] ?? 'In Progress';
    $kategori_rab  = $_POST['kategori_rab'] ?? 'Website';
    $origin_req_id = intval($_POST['origin_req_id'] ?? 0);

    // Olah Custom RAB jika kategori RAB = Custom
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

    if (!empty($nama_projek) && !empty($klien) && $budget > 0) {
        // 1. Insert Data Proyek Utama
        $stmtIns = $pdo->prepare("
            INSERT INTO projects 
            (nama_projek, klien, budget, persen_pajak, nominal_pajak, total_nilai, tanggal_mulai, deadline, status, kategori_rab, rab_custom_data, requirement_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([
            $nama_projek, $klien, $budget, $persen_pajak, $nominal_pajak, $total_nilai,
            $tanggal_mulai, $deadline, $status, $kategori_rab, $rab_custom_json,
            $origin_req_id > 0 ? $origin_req_id : null
        ]);
        $newProjectId = $pdo->lastInsertId();

        // 2. Auto-Create Draf Kontrak SPK Terkait dengan Nilai & Pajak Sinkron
        $nomorKontrak = sprintf("%03d", $newProjectId) . "/CTC-SPK/" . date('m/Y');
        $stmtContract = $pdo->prepare("
            INSERT INTO contracts 
            (project_id, nomor_kontrak, durasi_bulan, tanggal_mulai, tanggal_selesai, nilai_kontrak, persen_pajak, nominal_pajak, total_kontrak) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtContract->execute([
            $newProjectId, $nomorKontrak, $durasi_bulan, $tanggal_mulai, $deadline,
            $budget, $persen_pajak, $nominal_pajak, $total_nilai
        ]);

        // 3. Update status Client Requirement jika di-convert
        if ($origin_req_id > 0) {
            $stmtUpReq = $pdo->prepare("UPDATE client_requirements SET status = 'Proposal Sent' WHERE id = ?");
            $stmtUpReq->execute([$origin_req_id]);
        }

        header("Location: detail.php?id=" . $newProjectId . "&msg=project_updated");
        exit;
    } else {
        $error = "Mohon isi seluruh kolom wajib dan pastikan budget lebih dari Rp 0!";
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
    <div class="max-w-3xl mx-auto">
        
        <!-- Top Navigation -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <a href="index.php" class="btn-press text-xs font-semibold text-emerald-700 hover:text-emerald-800 mb-1.5 inline-flex items-center gap-1.5">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Proyek
                </a>
                <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Inisiasi & Tambah Proyek Baru</h1>
                <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Konfigurasi nilai proyek, skema PPN, dan RAB untuk sinkronisasi otomatis ke Proposal & SPK.</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
                <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i>
                <div><?= $error ?></div>
            </div>
        <?php endif; ?>

        <form action="" method="POST" onsubmit="return validateCustomRabSubmit()" class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-xs space-y-4 text-xs sm:text-sm">
            <input type="hidden" name="origin_req_id" value="<?= $req_id ?>">

            <!-- Nama Proyek & Klien -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Proyek <span class="text-red-500">*</span></label>
                    <input type="text" name="nama_projek" value="<?= htmlspecialchars($auto_title) ?>" required placeholder="Contoh: Website Company Profile & Portal Intern" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Klien / Perusahaan <span class="text-red-500">*</span></label>
                    <input type="text" name="klien" value="<?= htmlspecialchars($auto_client) ?>" required placeholder="Contoh: PT. Maju Bersama" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>
            </div>

            <!-- Budget (DPP) & Pengaturan Pajak (PPN) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nilai Pekerjaan / DPP (Rp) <span class="text-red-500">*</span></label>
                    <input type="number" name="budget" id="budgetInput" value="<?= $auto_budget > 0 ? $auto_budget : '' ?>" oninput="calculateTaxPreview()" required placeholder="Contoh: 10000000" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-mono font-semibold focus:outline-none placeholder:text-slate-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tarif Pajak (PPN %)</label>
                    <div class="flex items-center bg-white border border-slate-300 rounded-xl px-3 py-2">
                        <input type="number" name="persen_pajak" id="persenPajakInput" value="11.0" step="0.1" min="0" max="100" oninput="calculateTaxPreview()" class="w-full text-xs sm:text-sm font-mono font-bold focus:outline-none text-slate-800">
                        <span class="text-xs font-bold text-slate-400 ml-1">%</span>
                    </div>
                </div>
            </div>

            <!-- Live Card Summary Finansial & Pajak -->
            <div class="bg-slate-50 border border-slate-200/80 p-3.5 rounded-xl grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Subtotal (DPP)</span>
                    <span class="font-mono font-bold text-slate-900" id="previewDpp">Rp 0</span>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-amber-600 block">Nominal PPN (<span id="previewPajakPct">11.0%</span>)</span>
                    <span class="font-mono font-semibold text-amber-600" id="previewNominalPajak">Rp 0</span>
                </div>
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700 block">Total Nilai (Inc. Pajak)</span>
                    <span class="font-mono font-bold text-emerald-700 text-sm" id="previewTotalNilai">Rp 0</span>
                </div>
            </div>

            <!-- Kategori & Template RAB -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Kategori & Template RAB <span class="text-red-500">*</span></label>
                <select name="kategori_rab" id="kategoriRabSelect" onchange="toggleCustomRabSection()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="Website" selected>RAB Website (Auto-Calculate)</option>
                    <option value="IoT">RAB IoT Hardware & Cloud (Auto-Calculate)</option>
                    <option value="Mobile">RAB Mobile App Android/iOS (Auto-Calculate)</option>
                    <option value="Custom">Custom RAB (Input Manual Komponen & % Persen)</option>
                </select>
            </div>

            <!-- Section Dynamic Custom RAB -->
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

            <!-- Durasi, Tanggal Mulai & Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Durasi Proyek (Bulan) <span class="text-red-500">*</span></label>
                    <input type="number" name="durasi_bulan" id="durasiBulanInput" min="1" max="36" required value="3" onchange="updateDeadlineDate()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Tanggal Mulai <span class="text-red-500">*</span></label>
                    <input type="date" name="tanggal_mulai" id="tanggalMulaiInput" required value="<?= date('Y-m-d') ?>" onchange="updateDeadlineDate()" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Target Deadline <span class="text-red-500">*</span></label>
                    <input type="date" name="deadline" id="deadlineInput" required value="<?= date('Y-m-d', strtotime('+3 months')) ?>" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-semibold focus:outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1.5">Status Proyek Awal</label>
                <select name="status" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                    <option value="In Progress" selected>In Progress (Sedang Berjalan)</option>
                    <option value="On Hold">On Hold (Ditunda / Proposal Sent)</option>
                    <option value="Planning">Planning</option>
                    <option value="Completed">Completed (Selesai)</option>
                </select>
            </div>

            <!-- Submit Buttons -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                <a href="index.php" class="btn-press px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 font-semibold text-xs hover:bg-slate-50">
                    Batal
                </a>
                <button type="submit" class="btn-press px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-xs"></i> Simpan Proyek & Auto-Sync
                </button>
            </div>
        </form>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    calculateTaxPreview();
});

function calculateTaxPreview() {
    const budget = parseFloat(document.getElementById('budgetInput').value) || 0;
    const taxPct = parseFloat(document.getElementById('persenPajakInput').value) || 0;

    const nominalPajak = Math.round((budget * taxPct) / 100);
    const totalNilai   = budget + nominalPajak;

    document.getElementById('previewDpp').innerText          = 'Rp ' + budget.toLocaleString('id-ID');
    document.getElementById('previewPajakPct').innerText     = taxPct.toFixed(1) + '%';
    document.getElementById('previewNominalPajak').innerText = 'Rp ' + nominalPajak.toLocaleString('id-ID');
    document.getElementById('previewTotalNilai').innerText   = 'Rp ' + totalNilai.toLocaleString('id-ID');
}

function updateDeadlineDate() {
    const startVal = document.getElementById('tanggalMulaiInput').value;
    const durasi   = parseInt(document.getElementById('durasiBulanInput').value) || 1;

    if (startVal) {
        const d = new Date(startVal);
        d.setMonth(d.getMonth() + durasi);
        const yyyy = d.getFullYear();
        const mm   = String(d.getMonth() + 1).padStart(2, '0');
        const dd   = String(d.getDate()).padStart(2, '0');
        document.getElementById('deadlineInput').value = `${yyyy}-${mm}-${dd}`;
    }
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
    if (badge) {
        badge.innerText = `${total.toFixed(1)}% / 100%`;
        if (Math.abs(total - 100) < 0.01) {
            badge.className = "px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 text-xs font-mono font-bold";
        } else {
            badge.className = "px-2 py-0.5 rounded-md bg-red-100 text-red-700 text-xs font-mono font-bold";
        }
    }
    return total;
}

function validateCustomRabSubmit() {
    const val = document.getElementById('kategoriRabSelect').value;
    if (val === 'Custom') {
        const totalPct = calculateCustomPercentageTotal();
        if (Math.abs(totalPct - 100) > 0.01) {
            alert(`Total persentase Custom RAB saat ini ${totalPct.toFixed(1)}%. Total harus tepat 100% agar alokasi budget valid!`);
            return false;
        }
    }
    return true;
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>