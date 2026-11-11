<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Auto-Generate Nomor Kontrak SPK (Safe Max Increment)
$currentYear = date('Y');
$currentMonthRoman = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'][(int)date('m') - 1];

$stmtCount = $pdo->prepare("
    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(nomor_kontrak, '/', 1) AS UNSIGNED)), 0) 
    FROM contracts 
    WHERE nomor_kontrak LIKE ?
");
$stmtCount->execute(['%/' . $currentYear]);
$nextNo = intval($stmtCount->fetchColumn()) + 1;

$autoNoKontrak = sprintf("%03d", $nextNo) . "/CTC-SPK/" . $currentMonthRoman . "/" . $currentYear;

$error = '';

// Handle Submit Form Kontrak & SINKRONISASI BIDIREKSIONAL KE TABEL PROJECTS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_contract'])) {
    $project_id      = intval($_POST['project_id'] ?? 0);
    $nomor_kontrak   = trim($_POST['nomor_kontrak'] ?? '');
    $tanggal_mulai   = $_POST['tanggal_mulai'] ?? null;
    $tanggal_selesai = $_POST['tanggal_selesai'] ?? null;
    $durasi_bulan    = max(1, (int)($_POST['durasi_bulan'] ?? 1));
    $persen_pajak    = isset($_POST['persen_pajak']) && $_POST['persen_pajak'] !== '' ? floatval($_POST['persen_pajak']) : 11.00;

    // SANITASI NOMINAL PRESISI: Mendukung format IDR (20.000.000) maupun desimal (20000000.00)
    $raw_budget = trim($_POST['nilai_kontrak'] ?? '');
    if (strpos($raw_budget, '.') !== false && strpos($raw_budget, ',') !== false) {
        $clean_before_comma = explode(',', $raw_budget)[0];
        $nilai_kontrak = floatval(str_replace(['.', ' '], '', $clean_before_comma));
    } elseif (strpos($raw_budget, '.') !== false && strpos($raw_budget, ',') === false) {
        $parts = explode('.', $raw_budget);
        if (count($parts) > 2 || (count($parts) == 2 && strlen($parts[1]) == 3)) {
            $nilai_kontrak = floatval(str_replace(['.', ' '], '', $raw_budget));
        } else {
            $nilai_kontrak = floatval($raw_budget);
        }
    } else {
        $clean = preg_replace('/[^0-9]/', '', $raw_budget);
        $nilai_kontrak = floatval($clean);
    }

    // Kalkulasi Pajak & Total Kontrak
    $nominal_pajak = round(($nilai_kontrak * $persen_pajak) / 100, 2);
    $total_kontrak = $nilai_kontrak + $nominal_pajak;

    if (!empty($project_id) && !empty($nomor_kontrak) && $nilai_kontrak > 0) {
        // 1. Insert Data Kontrak Baru
        $stmtInsert = $pdo->prepare("
            INSERT INTO contracts (project_id, nomor_kontrak, tanggal_mulai, tanggal_selesai, durasi_bulan, nilai_kontrak, persen_pajak, nominal_pajak, total_kontrak)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([$project_id, $nomor_kontrak, $tanggal_mulai, $tanggal_selesai, $durasi_bulan, $nilai_kontrak, $persen_pajak, $nominal_pajak, $total_kontrak]);
        $contract_id = $pdo->lastInsertId();

        // 2. Sinkronisasi Kembali ke Tabel Proyek Utama (Projects)
        $stmtUpProj = $pdo->prepare("
            UPDATE projects 
            SET budget = ?, persen_pajak = ?, nominal_pajak = ?, total_nilai = ?, tanggal_mulai = ?, deadline = ? 
            WHERE id = ?
        ");
        $stmtUpProj->execute([$nilai_kontrak, $persen_pajak, $nominal_pajak, $total_kontrak, $tanggal_mulai, $tanggal_selesai, $project_id]);

        // Redirect langsung ke cetak PDF
        header("Location: print_pdf.php?id=" . $contract_id);
        exit;
    } else {
        $error = "Mohon pilih proyek acuan dan pastikan nominal valid!";
    }
}

// Fetch Proyek Terdaftar (Strict Mode Compatible Query)
$projects = $pdo->query("
    SELECT p.id, p.nama_projek, p.klien, p.budget, p.persen_pajak, p.nominal_pajak, p.total_nilai, p.tanggal_mulai, p.deadline,
           MAX(pr.persen_pajak) AS proposal_persen_pajak
    FROM projects p
    LEFT JOIN proposals pr ON (
        (p.klien IS NOT NULL AND p.klien != '' AND pr.client_name LIKE CONCAT('%', p.klien, '%')) OR
        (pr.title LIKE CONCAT('%', p.nama_projek, '%'))
    )
    GROUP BY p.id, p.nama_projek, p.klien, p.budget, p.persen_pajak, p.nominal_pajak, p.total_nilai, p.tanggal_mulai, p.deadline
    ORDER BY p.nama_projek ASC
")->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .btn-press {
        transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, box-shadow 0.2s ease;
        will-change: transform;
    }
    .btn-press:active {
        transform: scale(0.97);
    }

    .form-card {
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .input-field {
        transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }
    .input-field:focus {
        border-color: #10b981;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
        outline: none;
    }

    .hero-glow {
        background: radial-gradient(circle at 85% 20%, rgba(16, 185, 129, 0.22) 0%, transparent 60%);
    }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- HEADER HERO BANNER -->
    <div class="relative overflow-hidden bg-gradient-to-r from-[#064e3b] via-[#043829] to-[#02241a] text-white p-6 sm:p-7 rounded-3xl shadow-md border border-emerald-800/60 mb-6 flex flex-col md:flex-row justify-between md:items-center gap-6">
        <div class="absolute inset-0 hero-glow pointer-events-none"></div>
        <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 min-w-0">
            <div class="flex items-center gap-2 mb-2">
                <a href="index.php" class="btn-press inline-flex items-center gap-1.5 text-xs font-bold text-emerald-300 hover:text-white bg-white/10 hover:bg-white/20 px-3 py-1 rounded-full border border-white/15 backdrop-blur-md transition">
                    <i class="fa-solid fa-arrow-left text-[10px]"></i> Kembali ke Arsip
                </a>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                <i class="fa-solid fa-file-signature text-emerald-400"></i> Generator Kontrak Resmi SPK
            </h1>
            <p class="text-xs sm:text-sm text-emerald-100/80 font-medium mt-1">
                Lengkapi rincian di bawah ini untuk menerbitkan Perjanjian Kerja Kerjasama resmi PT. CTC.
            </p>
        </div>

        <div class="relative z-10 shrink-0 hidden sm:block">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-400/30 flex items-center justify-center text-emerald-300 text-xl backdrop-blur-md shadow-xs">
                <i class="fa-solid fa-file-contract"></i>
            </div>
        </div>
    </div>

    <!-- NOTIFIKASI ERROR -->
    <?php if (!empty($error)): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-exclamation text-red-500 text-sm"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- FORM UTAMA CONTAINER -->
    <div class="bg-white border border-slate-200/90 rounded-3xl p-6 sm:p-8 shadow-xs max-w-4xl mx-auto">
        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="generate_contract" value="1">

            <!-- SEKSI 1: PROYEK ACUAN -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-folder-tree text-emerald-600"></i> Pilih Proyek Acuan *
                    </label>
                    <span id="client_badge" class="hidden px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <!-- Populated by JS -->
                    </span>
                </div>

                <div class="relative">
                    <select name="project_id" id="project_select" onchange="autoFillForm()" required 
                            class="input-field w-full px-4 py-3 rounded-2xl border border-slate-300/80 text-xs font-semibold bg-slate-50/50 focus:bg-white text-slate-800 cursor-pointer appearance-none pr-10">
                        <option value="">-- Silakan Pilih Proyek Target --</option>
                        <?php foreach ($projects as $p): ?>
                            <?php 
                                $taxPct = (isset($p['persen_pajak']) && is_numeric($p['persen_pajak'])) 
                                    ? (float)$p['persen_pajak'] 
                                    : ((isset($p['proposal_persen_pajak']) && is_numeric($p['proposal_persen_pajak'])) ? (float)$p['proposal_persen_pajak'] : 11.00);
                            ?>
                            <option value="<?= $p['id'] ?>" 
                                    data-klien="<?= htmlspecialchars($p['klien']) ?>"
                                    data-budget="<?= (float)$p['budget'] ?>" 
                                    data-pajak="<?= $taxPct ?>"
                                    data-start="<?= $p['tanggal_mulai'] ?>" 
                                    data-deadline="<?= $p['deadline'] ?>">
                                <?= htmlspecialchars($p['nama_projek']) ?> — [ Klien: <?= htmlspecialchars($p['klien']) ?> ]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-xs">
                        <i class="fa-solid fa-chevron-down"></i>
                    </div>
                </div>
            </div>

            <!-- SEKSI 2: IDENTITAS KONTRAK, NOMINAL & PAJAK -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                
                <!-- Nomor Kontrak -->
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider mb-1.5 flex items-center gap-2">
                        <i class="fa-solid fa-hashtag text-emerald-600"></i> Nomor Kontrak (Otomatis)
                    </label>
                    <div class="relative">
                        <input type="text" name="nomor_kontrak" readonly value="<?= $autoNoKontrak ?>" 
                               class="w-full px-4 py-3 rounded-2xl border border-emerald-200 text-xs font-mono font-extrabold bg-emerald-50/60 text-emerald-900 cursor-not-allowed">
                        <i class="fa-solid fa-lock absolute right-3.5 top-1/2 -translate-y-1/2 text-emerald-500 text-xs"></i>
                    </div>
                    <span class="text-[10px] text-slate-400 font-medium mt-1.5 block">Penomoran terformat sistematis sesuai agenda korporat PT. CTC.</span>
                </div>

                <!-- Nilai Kontrak DPP -->
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider mb-1.5 flex items-center gap-2">
                        <i class="fa-solid fa-money-bill-wave text-emerald-600"></i> Nilai Kontrak / DPP (RP) *
                    </label>
                    <div class="relative flex items-center">
                        <span class="absolute left-4 font-bold text-slate-400 text-xs">Rp</span>
                        <input type="text" name="nilai_kontrak" id="nilai_kontrak" required onkeyup="formatRupiah(this); hitungPajakRealtime();" placeholder="0" 
                               class="input-field w-full pl-10 pr-4 py-3 rounded-2xl border border-slate-300/80 text-xs font-extrabold text-slate-900 font-mono bg-white">
                    </div>
                    <span class="text-[10px] text-slate-400 font-medium mt-1.5 block">Dasar Pengenaan Pajak (DPP) sebelum penambahan pajak.</span>
                </div>

            </div>

            <!-- SEKSI PAJAK & REAL-TIME PREVIEW -->
            <div class="bg-slate-50 border border-slate-200/90 rounded-2xl p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-200 pb-2">
                    <span class="text-xs font-extrabold text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-calculator text-emerald-600"></i> Pengaturan Pajak Kontrak
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[11px] font-extrabold uppercase text-slate-600 tracking-wider mb-1">Pajak (%)</label>
                        <input type="number" id="persen_pajak" name="persen_pajak" value="11" min="0" max="100" step="0.1" oninput="hitungPajakRealtime()" 
                               class="input-field w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white font-bold text-emerald-700 text-xs focus:outline-none">
                    </div>

                    <div class="md:col-span-2 grid grid-cols-2 gap-3 bg-white p-3 rounded-xl border border-slate-200 text-xs font-mono">
                        <div>
                            <span class="text-[10px] text-slate-400 block uppercase font-sans font-semibold">Nominal Pajak</span>
                            <span id="previewNominalPajak" class="font-bold text-amber-600">Rp 0</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 block uppercase font-sans font-semibold">Total Kontrak (Inc. Pajak)</span>
                            <span id="previewTotalKontrak" class="font-bold text-emerald-700 text-xs sm:text-sm">Rp 0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SEKSI 3: JANGKA WAKTU & TANGGAL -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2">
                
                <!-- Durasi Bulan -->
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider mb-1.5 flex items-center gap-2">
                        <i class="fa-solid fa-clock text-emerald-600"></i> Durasi (Bulan) *
                    </label>
                    <input type="number" name="durasi_bulan" id="durasi_bulan" required min="1" value="1" 
                           class="input-field w-full px-4 py-3 rounded-2xl border border-slate-300/80 text-xs font-extrabold font-mono text-slate-800 bg-white">
                </div>

                <!-- Tanggal Mulai -->
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider mb-1.5 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-day text-emerald-600"></i> Tanggal Mulai *
                    </label>
                    <input type="date" name="tanggal_mulai" id="tanggal_mulai" required onchange="calcDuration()" 
                           class="input-field w-full px-4 py-3 rounded-2xl border border-slate-300/80 text-xs font-semibold text-slate-800 bg-white">
                </div>

                <!-- Tanggal Selesai -->
                <div>
                    <label class="block text-xs font-extrabold uppercase text-slate-700 tracking-wider mb-1.5 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-check text-emerald-600"></i> Tanggal Selesai *
                    </label>
                    <input type="date" name="tanggal_selesai" id="tanggal_selesai" required onchange="calcDuration()" 
                           class="input-field w-full px-4 py-3 rounded-2xl border border-slate-300/80 text-xs font-semibold text-slate-800 bg-white">
                </div>

            </div>

            <!-- BANNER INFORMASI LEGAL -->
            <div class="p-4 bg-slate-50 border border-slate-200/90 rounded-2xl text-xs text-slate-600 flex items-start gap-3 mt-4">
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm shrink-0 border border-emerald-100">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="leading-relaxed">
                    <p class="font-bold text-slate-800">Dokumen Perjanjian Kerja Standar Enterprise</p>
                    <p class="text-[11px] text-slate-500 mt-0.5">Dokumen terbit secara otomatis melingkupi pasal SLA, rincian pajak resmi, kewajiban kerahasiaan (NDA), termin pembayaran, garansi pemeliharaan, serta area pembubuhan meterai legal.</p>
                </div>
            </div>

            <!-- TOMBOL AKSI -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="index.php" class="btn-press px-5 py-2.5 rounded-2xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition">
                    Batal
                </a>
                <button type="submit" class="btn-press px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-2xl text-xs font-extrabold shadow-md shadow-emerald-950/20 flex items-center gap-2 transition">
                    <i class="fa-solid fa-file-pdf text-xs"></i>
                    <span>Terbitkan & Auto-Sync Proyek</span>
                </button>
            </div>
        </form>
    </div>
</main>

<script>
function autoFillForm() {
    const select = document.getElementById('project_select');
    const selectedOption = select.options[select.selectedIndex];
    const clientBadge = document.getElementById('client_badge');

    if (selectedOption.value !== '') {
        const rawBudget = parseFloat(selectedOption.getAttribute('data-budget')) || 0;
        const pajakPct  = parseFloat(selectedOption.getAttribute('data-pajak'));
        const start     = selectedOption.getAttribute('data-start');
        const deadline  = selectedOption.getAttribute('data-deadline');
        const klien     = selectedOption.getAttribute('data-klien');

        if (klien && clientBadge) {
            clientBadge.textContent = 'Klien: ' + klien;
            clientBadge.classList.remove('hidden');
        }

        document.getElementById('nilai_kontrak').value = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Math.round(rawBudget));
        document.getElementById('persen_pajak').value = !isNaN(pajakPct) ? pajakPct : 11.00;
        
        if (start) document.getElementById('tanggal_mulai').value = start;
        if (deadline) document.getElementById('tanggal_selesai').value = deadline;

        calcDuration();
        hitungPajakRealtime();
    } else {
        if (clientBadge) clientBadge.classList.add('hidden');
    }
}

function calcDuration() {
    const startVal = document.getElementById('tanggal_mulai').value;
    const endVal   = document.getElementById('tanggal_selesai').value;

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

function hitungPajakRealtime() {
    const rawVal = document.getElementById('nilai_kontrak').value.replace(/[^0-9]/g, '');
    const nilaiKontrak = parseFloat(rawVal) || 0;
    const persenPajak  = parseFloat(document.getElementById('persen_pajak').value) || 0;

    const nominalPajak = Math.round((nilaiKontrak * persenPajak) / 100);
    const totalKontrak = nilaiKontrak + nominalPajak;

    const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

    document.getElementById('previewNominalPajak').innerText = 'Rp ' + formatter.format(nominalPajak);
    document.getElementById('previewTotalKontrak').innerText = 'Rp ' + formatter.format(totalKontrak);
}

document.addEventListener('DOMContentLoaded', hitungPajakRealtime);
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>