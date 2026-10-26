<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

$project_id = $_GET['project_id'] ?? $_GET['id'] ?? null;

if (!$project_id) {
    header("Location: ../projects/index.php");
    exit;
}

// Fetch Data Proyek dari Database
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

// Fallback: Jika tidak ada di tabel projects, cek di tabel proposals
if (!$project) {
    $stmtProp = $pdo->prepare("SELECT * FROM proposals WHERE id = ?");
    $stmtProp->execute([$project_id]);
    $prop = $stmtProp->fetch();

    if ($prop) {
        $project = [
            'id' => $prop['id'],
            'nama_projek' => $prop['title'],
            'klien' => $prop['client_name'],
            'budget' => $prop['nilai_penawaran'],
            'tanggal_mulai' => $prop['created_at'],
            'deadline' => date('Y-m-d', strtotime('+30 days', strtotime($prop['created_at']))),
            'kategori_rab' => 'Website',
            'status' => 'On Hold'
        ];
    } else {
        header("Location: ../projects/index.php");
        exit;
    }
}

// ACTION: KIRIM PROPOSAL KE CLIENT PORTAL & CHAT LOBBY
if (isset($_GET['action']) && $_GET['action'] === 'send_client') {
    // Cari requirement_id terkait
    $reqId = $project['requirement_id'] ?? null;
    
    if (!$reqId) {
        // Fallback pencarian berdasarkan nama proyek atau nama klien
        $stmtFindReq = $pdo->prepare("SELECT id FROM client_requirements WHERE LOWER(title) LIKE LOWER(?) OR LOWER(client_name) LIKE LOWER(?) ORDER BY id DESC LIMIT 1");
        $stmtFindReq->execute(['%' . $project['nama_projek'] . '%', '%' . $project['klien'] . '%']);
        $reqId = $stmtFindReq->fetchColumn();
    }

    if ($reqId) {
        // Update Status Requirement Klien -> Proposal Sent
        $stmtUpReq = $pdo->prepare("UPDATE client_requirements SET status = 'Proposal Sent' WHERE id = ?");
        $stmtUpReq->execute([$reqId]);

        // Update Status Proyek -> On Hold
        $stmtUpProj = $pdo->prepare("UPDATE projects SET status = 'On Hold' WHERE id = ?");
        $stmtUpProj->execute([$project['id']]);

        // Kirim Pesan Otomatis System ke Deal Chats Lobi Klien
        $msgContent = "[SYSTEM] Surat Penawaran Harga (SPH) & Rincian RAB Resmi telah diterbitkan oleh Tim PT. Cipta Teknologi Cendekia untuk proyek: " . $project['nama_projek'] . ".\n\nAnda dapat mengunduh serta meninjau dokumen proposal resmi kapan saja melalui tombol Proposal PDF di atas.";
        
        $stmtInsertChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, client_read, created_at) VALUES (?, 'system', 'System CTC', ?, 0, NOW())");
        $stmtInsertChat->execute([$reqId, $msgContent]);

        header("Location: print_proposal.php?project_id=" . $project['id'] . "&msg=proposal_sent");
        exit;
    } else {
        header("Location: print_proposal.php?project_id=" . $project['id'] . "&msg=req_not_found");
        exit;
    }
}

// Otomatis Ambil Kategori RAB
$kategoriRAB = $project['kategori_rab'] ?? 'Website';
$totalBudget = $project['budget'] ?? 0;

// Hitung Durasi Pengerjaan
$tglMulai = new DateTime($project['tanggal_mulai'] ?? 'now');
$tglSelesai = new DateTime($project['deadline'] ?? '+30 days');
$interval = $tglMulai->diff($tglSelesai);
$durasiBulan = max(1, $interval->m + ($interval->y * 12));
$durasiMinggu = $durasiBulan * 4;

// Penentuan Komponen RAB Sesuai Kategori
$rabItems = [];

switch ($kategoriRAB) {
    case 'IoT':
        $rab1 = round($totalBudget * 0.25);
        $rab2 = round($totalBudget * 0.30);
        $rab3 = round($totalBudget * 0.20);
        $rab4 = round($totalBudget * 0.15);
        $rab5 = $totalBudget - ($rab1 + $rab2 + $rab3 + $rab4);

        $rabItems = [
            ['komponen' => 'Hardware & Sensor Procurement', 'deskripsi' => 'Pengadaan Modul Sensor, Mikrokontroler, & Komponen IoT', 'biaya' => $rab1],
            ['komponen' => 'Firmware & Embedded System Coding', 'deskripsi' => 'Pengkodean Logika Hardware, Calibrating Sensor, & Local Logic', 'biaya' => $rab2],
            ['komponen' => 'IoT Gateway & Cloud Telemetry', 'deskripsi' => 'Integrasi Protokol MQTT/HTTP, Cloud Server, & Data Ingestion', 'biaya' => $rab3],
            ['komponen' => 'Monitoring Dashboard & Control Hub', 'deskripsi' => 'Pengembangan Dashboard Real-time Monitoring & Remote Actuator', 'biaya' => $rab4],
            ['komponen' => 'Field Testing, Calibration & Deployment', 'deskripsi' => 'Pengujian Lapangan, Kalibrasi Alat, & User Training', 'biaya' => $rab5],
        ];
        break;

    case 'Mobile':
        $rab1 = round($totalBudget * 0.20);
        $rab2 = round($totalBudget * 0.35);
        $rab3 = round($totalBudget * 0.20);
        $rab4 = round($totalBudget * 0.15);
        $rab5 = $totalBudget - ($rab1 + $rab2 + $rab3 + $rab4);

        $rabItems = [
            ['komponen' => 'Cross-Platform UI/UX Mobile Design', 'deskripsi' => 'Wireframing, Mobile Prototype, & Adaptive Mobile UI', 'biaya' => $rab1],
            ['komponen' => 'Mobile Core Application (Android & iOS)', 'deskripsi' => 'Pengkodean Aplikasi Mobile Front-End & Local State Management', 'biaya' => $rab2],
            ['komponen' => 'Backend API & Cloud Server Sync', 'deskripsi' => 'Restful API Service, Database Cloud, & Push Notification Engine', 'biaya' => $rab3],
            ['komponen' => 'App Store & Play Store Publishing', 'deskripsi' => 'Setup Developer Account, Store Compliance, & Release Management', 'biaya' => $rab4],
            ['komponen' => 'Mobile Security & Quality Assurance', 'deskripsi' => 'Encryption Hardening, Compatibility Testing, & User Training', 'biaya' => $rab5],
        ];
        break;

    case 'Custom':
        if (!empty($project['rab_custom_data'])) {
            $rawCustom = json_decode($project['rab_custom_data'], true);
            foreach ($rawCustom as $item) {
                $pVal = (float)($item['persen'] ?? 0);
                $biayaCalc = round(($pVal / 100) * $totalBudget);
                $rabItems[] = [
                    'komponen'  => $item['komponen'],
                    'deskripsi' => $item['deskripsi'],
                    'biaya'     => $biayaCalc
                ];
            }
        } else {
            $rabItems = [
                ['komponen' => 'Pengembangan Sistem Spesifik', 'deskripsi' => 'Pengerjaan Modul Khusus Sesuai Kebutuhan Klien', 'biaya' => $totalBudget]
            ];
        }
        break;

    case 'Website':
    default:
        $rab1 = round($totalBudget * 0.15);
        $rab2 = round($totalBudget * 0.40);
        $rab3 = round($totalBudget * 0.15);
        $rab4 = round($totalBudget * 0.20);
        $rab5 = $totalBudget - ($rab1 + $rab2 + $rab3 + $rab4);

        $rabItems = [
            ['komponen' => 'Setup Arsitektur, UI/UX & Cloud DB', 'deskripsi' => 'Perancangan Wireframe UI/UX, Struktur Data, & Server Setup', 'biaya' => $rab1],
            ['komponen' => 'Pengembangan Engine & Core Application', 'deskripsi' => 'Pengkodean Modul Utama Proyek ' . htmlspecialchars($project['nama_projek']), 'biaya' => $rab2],
            ['komponen' => 'Integrasi API & Framework Engine', 'deskripsi' => 'Konektivitas Layanan API, Enkripsi Security, & Gateway System', 'biaya' => $rab3],
            ['komponen' => 'Web Admin & Executive Dashboard', 'deskripsi' => 'Monitoring Real-Time Operasional, Analytics Data & Reporting Hub', 'biaya' => $rab4],
            ['komponen' => 'Testing, Deployment & User Training', 'deskripsi' => 'User Acceptance Testing (UAT), Cloud Deployment & Documentation', 'biaya' => $rab5],
        ];
        break;
}

// Auto Generate Nomor Surat Format Resmi
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
    
    <!-- jsPDF + html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <style>
        /* CETAK FULL-BLEED TANPA MARGIN PUTIH */
        @page {
            size: A4 portrait;
            margin: 0 !important;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: #0f172a;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            line-height: 1.55;
        }

        .doc-font, .doc-font * {
            font-family: 'Times New Roman', Times, serif !important;
        }

        #proposalContainer {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: 210mm;
            margin: 0 auto;
            padding: 0;
            background: #ffffff;
        }

        .page-a4 {
            width: 210mm;
            height: 297mm;
            min-height: 297mm;
            max-height: 297mm;
            position: relative;
            overflow: hidden;
            background-color: #ffffff;
            flex-shrink: 0;
            box-sizing: border-box;
        }

        .kop-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 210mm;
            height: 297mm;
            object-fit: cover;
            z-index: 0;
            pointer-events: none;
            user-select: none;
            display: block;
        }

        .stempel-img {
            mix-blend-mode: multiply;
            filter: contrast(115%);
            pointer-events: none;
            user-select: none;
        }

        .no-print { display: flex; }

        @media print {
            html, body {
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            .no-print { display: none !important; }
            #proposalContainer {
                margin: 0 !important;
                padding: 0 !important;
                gap: 0 !important;
                width: 210mm !important;
                display: block !important;
            }
            .page-a4 {
                width: 210mm !important;
                height: 297mm !important;
                box-shadow: none !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            .page-a4:last-child {
                page-break-after: auto !important;
                break-after: auto !important;
            }
        }

        @media screen {
            .page-a4 {
                box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.4);
            }
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen py-8 flex flex-col items-center">

    <!-- Action Bar (Browser Only) -->
    <div class="w-[210mm] mb-6 flex flex-col sm:flex-row justify-between items-center gap-3 no-print font-sans">
        <a href="../projects/detail.php?id=<?= $project['id'] ?>" class="text-xs font-bold text-slate-300 hover:text-white flex items-center gap-1.5 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Proyek
        </a>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'proposal_sent'): ?>
            <div class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> Proposal berhasil dikirim ke Client Portal & Lobi Chat!
            </div>
        <?php endif; ?>

        <div class="flex items-center gap-3">
            <a href="print_proposal.php?project_id=<?= $project['id'] ?>&action=send_client" onclick="return confirm('Kirimkan dokumen proposal penawaran harga ini secara resmi ke Client Portal?')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow">
                <i class="fa-solid fa-paper-plane"></i> Kirim ke Klien Portal
            </a>
            <button onclick="window.print()" class="bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow">
                <i class="fa-solid fa-print"></i> Cetak Browser
            </button>
            <button id="btnDownloadPdf" onclick="downloadDirectPDF()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow-md">
                <i class="fa-solid fa-file-arrow-down"></i> Download PDF Langsung
            </button>
        </div>
    </div>

    <!-- Wrapper Utama Dokumen PDF (Eksak 2 Halaman) -->
    <div id="proposalContainer">

        <!-- ================= HALAMAN 1 (KOP SURAT UTAMA) ================= -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc.png"
                 alt="Kop Surat Utama"
                 class="kop-background"
                 crossorigin="anonymous">

            <!-- Konten Utama Halaman 1 -->
            <div class="doc-font relative z-10 pt-[52mm] pb-[20mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11.5px] text-black">
                <div>
                    <!-- Meta Surat Penawaran -->
                    <div class="mb-5 space-y-1">
                        <div class="flex justify-between font-bold text-[12px]">
                            <span>Nomor : <?= $noSurat ?></span>
                            <span>Depok, <?= date('d F Y') ?></span>
                        </div>
                        <p><strong>Lampiran</strong> : 1 (Satu) Berkas Rincian Anggaran Biaya &amp; Scope of Work</p>
                        <div class="pt-2">
                            <p>Kepada Yth.</p>
                            <p class="font-bold text-[12px]">Pimpinan / Direksi Management</p>
                            <p class="font-bold text-black text-[12px] underline"><?= htmlspecialchars($project['klien']) ?></p>
                            <p>Di Tempat</p>
                        </div>
                        <p class="pt-2"><strong>Hal: Penawaran Pengembangan <?= htmlspecialchars($project['nama_projek']) ?></strong></p>
                    </div>

                    <!-- Pembuka -->
                    <p class="mb-2.5 leading-relaxed text-justify">Dengan hormat,</p>
                    <p class="mb-2.5 leading-relaxed text-justify">
                        Menindaklanjuti rencana kerja sama dan integrasi sistem pada <strong><?= htmlspecialchars($project['klien']) ?></strong>, kami dari <strong>PT. Cipta Teknologi Cendekia</strong> mengajukan penawaran resmi untuk pengembangan <strong>"<?= htmlspecialchars($project['nama_projek']) ?>"</strong>.
                    </p>
                    <p class="mb-5 leading-relaxed text-justify">
                        Sistem ini dirancang khusus berbasis arsitektur modern, aman, dan dapat diandalkan untuk meningkatkan efisiensi operasional bisnis mitra secara terukur. Total nilai investasi komersial pengembangan sistem ini adalah sebesar <strong>Rp <?= number_format($totalBudget, 0, ',', '.') ?>,-</strong> (<em><?= terbilang($totalBudget) ?> Rupiah</em>) dengan estimasi durasi pengerjaan selama <strong><?= $durasiMinggu ?> Minggu (<?= $durasiBulan ?> Bulan)</strong>.
                    </p>

                    <!-- Executive Summary -->
                    <div class="p-3.5 border-l-4 border-emerald-800 bg-slate-50/90 mb-5 font-sans rounded-r-lg shadow-sm">
                        <h3 class="font-bold text-[11.5px] text-slate-900 mb-1.5 uppercase tracking-wide">RINGKASAN EKSEKUTIF (EXECUTIVE SUMMARY)</h3>
                        <p class="mb-2 text-[11px] text-slate-800 text-justify">Pengembangan sistem <strong><?= htmlspecialchars($project['nama_projek']) ?></strong> berfokus pada 3 Pilar Utama Keunggulan Teknologi:</p>
                        
                        <div class="space-y-1.5 text-[11px] text-slate-800">
                            <div class="flex gap-2 items-start">
                                <span class="font-bold min-w-[14px]">1.</span>
                                <p class="leading-relaxed text-justify"><strong>Arsitektur High-Availability &amp; Scalable:</strong> Performa optimal, pengamanan data terenkripsi, serta integrasi API.</p>
                            </div>
                            <div class="flex gap-2 items-start">
                                <span class="font-bold min-w-[14px]">2.</span>
                                <p class="leading-relaxed text-justify"><strong>Intuitive Multi-User Dashboard:</strong> Tampilan UI/UX modern mempermudah pemantauan operasional real-time.</p>
                            </div>
                            <div class="flex gap-2 items-start">
                                <span class="font-bold min-w-[14px]">3.</span>
                                <p class="leading-relaxed text-justify"><strong>Automated Analytics &amp; Reporting:</strong> Pelaporan otomatis yang mendukung pengambilan keputusan strategis.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Scope of Work -->
                    <div class="space-y-2.5 font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-2 mb-3.5 uppercase tracking-wide">
                            1. RUANG LINGKUP PEKERJAAN (SCOPE OF WORK)
                        </h3>

                        <div class="mb-2">
                            <p class="font-bold text-emerald-900 text-[11.5px] mb-1">A. Front-End Application &amp; User Interface</p>
                            <div class="space-y-1.5 text-[11px] text-slate-700 pl-1">
                                <div class="flex gap-2 items-start">
                                    <span class="text-slate-900 font-bold leading-none mt-[3px]">•</span>
                                    <p class="leading-relaxed text-justify">Design UI/UX interaktif berbasis standar platform <?= htmlspecialchars($kategoriRAB) ?> modern.</p>
                                </div>
                                <div class="flex gap-2 items-start">
                                    <span class="text-slate-900 font-bold leading-none mt-[3px]">•</span>
                                    <p class="leading-relaxed text-justify">Modul operasional utama sesuai alur kerja bisnis <?= htmlspecialchars($project['klien']) ?>.</p>
                                </div>
                                <div class="flex gap-2 items-start">
                                    <span class="text-slate-900 font-bold leading-none mt-[3px]">•</span>
                                    <p class="leading-relaxed text-justify">Fitur pencarian, filter data, serta navigasi responsif multi-device.</p>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <p class="font-bold text-emerald-900 text-[11.5px]">B. Back-End Engine &amp; Cloud Infrastructure</p>
                            <p class="pl-3 text-[11px] text-slate-700 text-justify">Konfigurasi Database Cloud, Restful API, keamanan enkripsi data, &amp; otentikasi akun berhak akses.</p>
                        </div>

                        <div>
                            <p class="font-bold text-emerald-900 text-[11.5px]">C. Web Admin Dashboard &amp; Analytics Management</p>
                            <p class="pl-3 text-[11px] text-slate-700 text-justify">Master Data Management, Ringkasan Eksekutif Analytics, &amp; modul ekspor laporan resmi (PDF/Excel).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= HALAMAN 2 (KOP SURAT HALAMAN 2+) ================= -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc-2.png"
                 alt="Kop Surat Halaman 2"
                 class="kop-background"
                 crossorigin="anonymous">

            <!-- Konten Utama Halaman 2 -->
            <div class="doc-font relative z-10 pt-[28mm] pb-[20mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11.5px] text-black">
                <div>
                    <!-- RAB Table -->
                    <div class="mb-6 font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-2 mb-3.5 uppercase tracking-wide">
                            2. RINCIAN ANGGARAN BIAYA (RAB)
                        </h3>
                        <table class="w-full border-collapse border border-slate-300 text-[11px] shadow-sm rounded-lg overflow-hidden">
                            <thead>
                                <tr class="bg-emerald-900 text-white text-[10.5px] uppercase tracking-wider">
                                    <th class="border border-emerald-800 p-2.5 w-10 text-center font-bold">No</th>
                                    <th class="border border-emerald-800 p-2.5 text-left font-bold">Komponen Pekerjaan</th>
                                    <th class="border border-emerald-800 p-2.5 text-left font-bold">Deskripsi / Output</th>
                                    <th class="border border-emerald-800 p-2.5 text-right w-32 font-bold">Biaya (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php
                                $no = 1;
                                foreach ($rabItems as $item):
                                ?>
                                    <tr class="bg-white hover:bg-slate-50/80 transition-colors">
                                        <td class="border border-slate-300 p-2.5 text-center font-semibold text-slate-600"><?= $no++ ?></td>
                                        <td class="border border-slate-300 p-2.5 font-bold text-slate-900"><?= htmlspecialchars($item['komponen']) ?></td>
                                        <td class="border border-slate-300 p-2.5 text-slate-700 leading-snug"><?= htmlspecialchars($item['deskripsi']) ?></td>
                                        <td class="border border-slate-300 p-2.5 text-right font-mono font-bold text-slate-800"><?= number_format($item['biaya'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="font-bold bg-emerald-50/70 border-t-2 border-emerald-800">
                                    <td colspan="3" class="border border-slate-300 p-2.5 text-right uppercase text-[10.5px] text-emerald-950 font-black tracking-wide">
                                        TOTAL INVESTASI (Eksklusif PPN)
                                    </td>
                                    <td class="border border-slate-300 p-2.5 text-right font-mono text-emerald-900 text-[12px] font-black">
                                        Rp <?= number_format($totalBudget, 0, ',', '.') ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Lembar Persetujuan & Stempel -->
                    <div class="mt-8 font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-2 mb-3.5 uppercase tracking-wide">
                            3. LEMBAR PERSETUJUAN (ACCEPTANCE OF PROPOSAL)
                        </h3>
                        <p class="mb-4 text-slate-700 text-[11px] text-justify">Jika penawaran Pengembangan <strong><?= htmlspecialchars($project['nama_projek']) ?></strong> ini disetujui, mohon menandatangani lembar konfirmasi di bawah ini.</p>

                        <div class="grid grid-cols-2 text-center gap-6">

                            <!-- Sisi Klien -->
                            <div class="flex flex-col justify-between h-48 border border-dashed border-slate-300 p-4 rounded-xl bg-white/80">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11.5px]">Untuk &amp; Atas Nama Klien</p>
                                    <p class="text-slate-600 font-semibold text-[10.5px]"><?= htmlspecialchars($project['klien']) ?></p>
                                </div>
                                <div class="h-20 flex items-center justify-center italic text-slate-400 text-[10px]">
                                    [ Tanda Tangan &amp; Stempel Klien ]
                                </div>
                                <div>
                                    <p class="font-bold underline text-slate-900 text-[11px]">( .................................................... )</p>
                                    <p class="text-[10px] text-slate-500 font-medium mt-0.5">Jabatan / Perwakilan Resmi</p>
                                </div>
                            </div>

                            <!-- Sisi PT CTC -->
                            <div class="flex flex-col justify-between h-48 border border-emerald-200 bg-emerald-50/40 p-4 rounded-xl relative">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11.5px]">Untuk &amp; Atas Nama</p>
                                    <p class="text-emerald-900 font-bold text-[11px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                                </div>

                                <div class="relative h-20 w-full flex items-center justify-center">
                                    <img src="../../assets/images/Stempel.png"
                                         alt="Stempel PT CTC"
                                         id="stempelImg"
                                         class="stempel-img absolute w-24 h-24 object-contain left-[22%] top-[-6px] -rotate-12 opacity-60"
                                         crossorigin="anonymous"
                                         onerror="this.style.display='none'">
                                </div>

                                <div class="relative z-10">
                                    <p class="font-bold underline text-slate-900 text-[11.5px]">Mohammad Habbas Ali, SE.</p>
                                    <p class="text-[10px] text-slate-600 font-medium mt-0.5">Direktur / Managing Director</p>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script>
    function waitForImages() {
        const imgs = Array.from(document.querySelectorAll('#proposalContainer img'));
        return Promise.all(imgs.map(img => {
            if (img.complete && img.naturalWidth > 0) return Promise.resolve();
            return new Promise(res => {
                img.onload = () => res();
                img.onerror = () => res();
            });
        }));
    }

    function processStampTransparency() {
        return new Promise((resolve) => {
            const img = document.getElementById('stempelImg');
            if (!img || img.style.display === 'none' || !img.src) return resolve();

            const run = () => {
                try {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.naturalWidth;
                    canvas.height = img.naturalHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const d = imageData.data;
                    for (let i = 0; i < d.length; i += 4) {
                        const r = d[i], g = d[i+1], b = d[i+2];
                        if (r > 200 && g > 200 && b > 200) {
                            d[i+3] = 0;
                        }
                    }
                    ctx.putImageData(imageData, 0, 0);
                    img.src = canvas.toDataURL('image/png');
                } catch (e) {
                    console.warn('Gagal proses transparansi stempel:', e);
                }
                resolve();
            };

            if (img.complete && img.naturalWidth > 0) {
                run();
            } else {
                img.onload = run;
                img.onerror = () => resolve();
            }
        });
    }

    async function downloadDirectPDF() {
        const btn = document.getElementById('btnDownloadPdf');
        const originalText = btn.innerHTML;

        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses PDF...';
        btn.disabled = true;

        try {
            await waitForImages();
            await processStampTransparency();
            await new Promise(r => setTimeout(r, 250));

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF({
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait',
                compress: true
            });

            const pages = document.querySelectorAll('#proposalContainer .page-a4');
            const PAGE_W = 210;
            const PAGE_H = 297;

            for (let i = 0; i < pages.length; i++) {
                const page = pages[i];
                const canvas = await html2canvas(page, {
                    scale: 3,
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    backgroundColor: '#ffffff',
                    width: page.offsetWidth,
                    height: page.offsetHeight,
                    windowWidth: page.offsetWidth,
                    windowHeight: page.offsetHeight,
                    x: 0,
                    y: 0
                });

                const imgData = canvas.toDataURL('image/jpeg', 0.98);
                if (i > 0) pdf.addPage('a4', 'portrait');
                pdf.addImage(imgData, 'JPEG', 0, 0, PAGE_W, PAGE_H, undefined, 'FAST');
            }

            pdf.save('Surat_Penawaran_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['nama_projek']) ?>.pdf');
        } catch (err) {
            console.error('Error generate PDF:', err);
            window.print();
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    </script>
</body>
</html>