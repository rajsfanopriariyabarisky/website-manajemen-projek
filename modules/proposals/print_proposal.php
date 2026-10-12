<?php
require_once __DIR__ . '/../../config/database.php';

$project_id = $_GET['project_id'] ?? null;

if (!$project_id) {
    header("Location: index.php");
    exit;
}

// Fetch Data Proyek dari Database
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

// Hitung Durasi Pengerjaan (Default 12 Minggu / 3 Bulan jika tidak ada)
$tglMulai = new DateTime($project['tanggal_mulai']);
$tglSelesai = new DateTime($project['deadline']);
$interval = $tglMulai->diff($tglSelesai);
$durasiBulan = max(1, $interval->m + ($interval->y * 12));
$durasiMinggu = $durasiBulan * 4;

// Auto-Calculate RAB berdasarkan Properti Budget Proyek (Standar Porsi Alokasi IT)
$totalBudget = $project['budget'];
$rab1 = round($totalBudget * 0.15); // UI/UX & Cloud DB Setup (15%)
$rab2 = round($totalBudget * 0.40); // Core App Development (40%)
$rab3 = round($totalBudget * 0.15); // Offline Sync & API Integration (15%)
$rab4 = round($totalBudget * 0.20); // Web Admin & Super Dashboard (20%)
$rab5 = $totalBudget - ($rab1 + $rab2 + $rab3 + $rab4); // UAT & Deployment (10%)

// Auto Generate Nomor Surat Penawaran
function bulanRomawi($bulan) {
    $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
    return $map[(int)$bulan] ?? 'I';
}
$noSurat = sprintf("%03d", $project['id']) . "-B/CTC-SP/" . bulanRomawi(date('n')) . "/" . date('Y');

function terbilang($angka) {
    $satuan = ["", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas"];
    if ($angka < 12) return $satuan[$angka];
    if ($angka < 20) return terbilang($angka - 10) . " Belas";
    if ($angka < 100) return terbilang(floor($angka / 10)) . " Puluh " . terbilang($angka % 10);
    if ($angka < 200) return "Seratus " . terbilang($angka - 100);
    if ($angka < 1000) return terbilang(floor($angka / 100)) . " Ratus " . terbilang($angka % 100);
    if ($angka < 2000) return "Seribu " . terbilang($angka - 1000);
    if ($angka < 1000000) return terbilang(floor($angka / 1000)) . " Ribu " . terbilang($angka % 1000);
    if ($angka < 1000000000) return terbilang(floor($angka / 1000000)) . " Juta " . terbilang($angka % 1000000);
    return number_format($angka, 0, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Penawaran - <?= htmlspecialchars($project['nama_projek']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; color: #000 !important; }
            .print-container { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        }
        body { font-family: 'Times New Roman', Times, serif; line-height: 1.5; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen py-8 px-4">

    <!-- Action Bar (Browser Only) -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print font-sans">
        <a href="index.php" class="text-xs font-semibold text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Daftar Penawaran
        </a>
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl flex items-center gap-2 transition text-sm shadow-md">
            <i class="fa-solid fa-print"></i> Cetak Proposal / Save PDF
        </button>
    </div>

    <!-- Container Proposal Dokumen -->
    <div class="max-w-4xl mx-auto bg-white p-12 rounded-lg shadow-xl border border-slate-200 print-container text-justify">
        
        <!-- Kop Surat Perusahaan -->
        <div class="border-b-4 border-double border-emerald-900 pb-4 mb-6">
            <h1 class="text-xl font-bold tracking-wider text-emerald-950 font-sans uppercase">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
            <p class="text-xs font-sans text-slate-600 font-bold">IT Solution, Software Development & Enterprise System Engineering</p>
            <p class="text-[11px] font-sans text-slate-500">Gedung CTC Tower, Lt. 8, Jl. Jend. Sudirman, Jakarta | Email: info@ctc-nusantara.com | Website: https://ctc-nusantara.com/</p>
        </div>

        <!-- Meta Surat Penawaran -->
        <div class="text-xs font-sans mb-6 space-y-1">
            <div class="flex justify-between">
                <span><strong>Nomor</strong> : <?= $noSurat ?></span>
                <span>Jakarta, <?= date('d F Y') ?></span>
            </div>
            <p><strong>Lampiran</strong> : 1 (Satu) Berkas Rincian Anggaran Biaya & Scope of Work</p>
            <p class="pt-2">Kepada Yth.<br>
            <strong>Pimpinan / Direksi Management</strong><br>
            <span class="font-bold text-slate-900"><?= htmlspecialchars($project['klien']) ?></span><br>
            Di Tempat</p>
            <p class="pt-2"><strong>Hal: Penawaran Pengembangan <?= htmlspecialchars($project['nama_projek']) ?></strong></p>
        </div>

        <p class="text-xs mb-3">Dengan hormat,</p>
        <p class="text-xs mb-3">
            Menindaklanjuti rencana kerja sama dan integrasi sistem pada <strong><?= htmlspecialchars($project['klien']) ?></strong>, kami dari <strong>PT. Cipta Teknologi Cendekia</strong> mengajukan penawaran resmi untuk pengembangan <strong>"<?= htmlspecialchars($project['nama_projek']) ?>"</strong>.
        </p>
        <p class="text-xs mb-6">
            Sistem ini dirancang khusus berbasis arsitektur modern, aman, dan dapat diandalkan untuk meningkatkan efisiensi operasional bisnis mitra secara terukur. Total nilai investasi komersial pengembangan sistem ini adalah sebesar <strong>Rp <?= number_format($totalBudget, 0, ',', '.') ?>,-</strong> (<em><?= terbilang($totalBudget) ?> Rupiah</em>) dengan estimasi durasi pengerjaan selama <strong><?= $durasiMinggu ?> Minggu (<?= $durasiBulan ?> Bulan)</strong>.
        </p>

        <!-- Executive Summary Dinamis -->
        <div class="bg-slate-50 p-4 border-l-4 border-emerald-800 text-xs mb-6 font-sans">
            <h3 class="font-bold text-slate-900 mb-2 uppercase">RINGKASAN EKSEKUTIF (EXECUTIVE SUMMARY)</h3>
            <p class="mb-2">Pengembangan sistem <strong><?= htmlspecialchars($project['nama_projek']) ?></strong> berfokus pada 3 Pilar Utama Keunggulan Teknologi:</p>
            <ol class="list-decimal pl-5 space-y-1">
                <li><strong>Arsitektur High-Availability & Scalable:</strong> Dirancang dengan performa optimal, pengamanan data terenkripsi, serta fleksibilitas integrasi API.</li>
                <li><strong>Intuitive Multi-User Dashboard:</strong> Tampilan UI/UX modern yang mempermudah staf maupun pimpinan dalam memantau operasional real-time.</li>
                <li><strong>Automated Analytics & Reporting:</strong> Sistem pelaporan otomatis yang mendukung pengambilan keputusan strategis secara presisi.</li>
            </ol>
        </div>

        <!-- Scope of Work Dinamis -->
        <div class="text-xs mb-6 space-y-3">
            <h3 class="font-bold text-sm text-slate-900 border-b border-slate-200 pb-1 uppercase">1. RUANG LINGKUP PEKERJAAN (SCOPE OF WORK)</h3>
            
            <div>
                <p class="font-bold text-emerald-900">A. Front-End Application & User Interface</p>
                <ul class="list-disc pl-5 space-y-1 mt-1">
                    <li>Design UI/UX interaktif berbasis standar pengerjaan web/mobile modern.</li>
                    <li>Modul operasional utama sesuai alur kerja bisnis <?= htmlspecialchars($project['klien']) ?>.</li>
                    <li>Integrasi fitur pencarian, filter data, serta navigasi responsif multi-device.</li>
                </ul>
            </div>

            <div>
                <p class="font-bold text-emerald-900">B. Back-End Engine & Cloud Infrastructure</p>
                <p class="pl-2">Konfigurasi Database Cloud, Management Restful API, keamanan enkripsi data, serta penanganan otentikasi akun berhak akses.</p>
            </div>

            <div>
                <p class="font-bold text-emerald-900">C. Web Admin Dashboard & Analytics Management</p>
                <p class="pl-2">Master Data Management, Ringkasan Eksekutif Analytics, serta modul ekspor laporan resmi (PDF/Excel).</p>
            </div>
        </div>

        <!-- RAB Table Terkalkulasi Otomatis -->
        <div class="text-xs mb-6">
            <h3 class="font-bold text-sm text-slate-900 border-b border-slate-200 pb-1 mb-3 uppercase">2. RINCIAN ANGGARAN BIAYA (RAB)</h3>
            <table class="w-full border-collapse border border-slate-300 font-sans text-xs">
                <thead>
                    <tr class="bg-emerald-900 text-white">
                        <th class="border border-slate-300 p-2 w-10">No</th>
                        <th class="border border-slate-300 p-2">Komponen Pekerjaan</th>
                        <th class="border border-slate-300 p-2">Deskripsi / Output</th>
                        <th class="border border-slate-300 p-2 text-right w-36">Biaya (IDR)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center">1</td>
                        <td class="border border-slate-300 p-2 font-bold">Setup Arsitektur, UI/UX & Cloud DB</td>
                        <td class="border border-slate-300 p-2">Perancangan Wireframe UI/UX, Struktur Data, & Server Setup</td>
                        <td class="border border-slate-300 p-2 text-right font-mono"><?= number_format($rab1, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center">2</td>
                        <td class="border border-slate-300 p-2 font-bold">Pengembangan Engine & Core Application</td>
                        <td class="border border-slate-300 p-2">Pengkodean Modul Utama Proyek <?= htmlspecialchars($project['nama_projek']) ?></td>
                        <td class="border border-slate-300 p-2 text-right font-mono"><?= number_format($rab2, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center">3</td>
                        <td class="border border-slate-300 p-2 font-bold">Integrasi API & Framework Engine</td>
                        <td class="border border-slate-300 p-2">Konektivitas Layanan API, Enkripsi Security, & Gateway System</td>
                        <td class="border border-slate-300 p-2 text-right font-mono"><?= number_format($rab3, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center">4</td>
                        <td class="border border-slate-300 p-2 font-bold">Web Admin & Executive Dashboard</td>
                        <td class="border border-slate-300 p-2">Monitoring Real-Time Operasional, Analytics Data & Reporting Hub</td>
                        <td class="border border-slate-300 p-2 text-right font-mono"><?= number_format($rab4, 0, ',', '.') ?></td>
                    </tr>
                    <tr>
                        <td class="border border-slate-300 p-2 text-center">5</td>
                        <td class="border border-slate-300 p-2 font-bold">Testing, Deployment & User Training</td>
                        <td class="border border-slate-300 p-2">User Acceptance Testing (UAT), Cloud Deployment & Documentation</td>
                        <td class="border border-slate-300 p-2 text-right font-mono"><?= number_format($rab5, 0, ',', '.') ?></td>
                    </tr>
                    <tr class="font-bold bg-slate-100">
                        <td colspan="3" class="border border-slate-300 p-2 text-right">TOTAL INVESTASI (Eksklusif PPN)</td>
                        <td class="border border-slate-300 p-2 text-right font-mono text-emerald-800">Rp <?= number_format($totalBudget, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Lembar Persetujuan -->
        <div class="text-xs font-sans mt-10">
            <h3 class="font-bold text-slate-900 mb-3 uppercase border-b border-slate-200 pb-1">3. LEMBAR PERSETUJUAN (ACCEPTANCE OF PROPOSAL)</h3>
            <p class="mb-6">Jika penawaran Pengembangan <strong><?= htmlspecialchars($project['nama_projek']) ?></strong> ini disetujui, mohon menandatangani lembar konfirmasi di bawah ini.</p>

            <div class="grid grid-cols-2 text-center">
                <div>
                    <p class="font-bold">Untuk & Atas Nama Klien</p>
                    <p class="text-slate-500 font-semibold"><?= htmlspecialchars($project['klien']) ?></p>
                    <div class="h-20 my-2 flex items-center justify-center text-slate-300 italic">[Tanda Tangan & Stempel]</div>
                    <p class="font-bold underline">[ Direksi / Perwakilan Resmi ]</p>
                    <p class="text-[10px] text-slate-500"><?= htmlspecialchars($project['klien']) ?></p>
                </div>

                <div>
                    <p class="font-bold">Untuk & Atas Nama</p>
                    <p class="text-slate-500 font-semibold">PT. Cipta Teknologi Cendekia</p>
                    <div class="h-20 my-2 flex items-center justify-center text-slate-300 italic">[Tanda Tangan & Stempel]</div>
                    <p class="font-bold underline">[ Project Director / Consultant ]</p>
                    <p class="text-[10px] text-slate-500">PT. Cipta Teknologi Cendekia</p>
                </div>
            </div>
        </div>

    </div>
</body>
</html>