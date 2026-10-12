<?php
require_once __DIR__ . '/../../config/database.php';

$project_id = $_GET['project_id'] ?? null;
if (!$project_id) {
    header("Location: index.php");
    exit;
}

// Fetch Data Proyek & Durasi Kontrak
$stmt = $pdo->prepare("
    SELECT p.*, 
           COALESCE(c.durasi_bulan, 3) AS durasi_bulan,
           c.nomor_kontrak
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

$durasiBulan = max(1, (int)$project['durasi_bulan']);
$totalMinggu = $durasiBulan * 4;

// LOGIKA ADAPTIF: Jika durasi > 6 bulan (lebih dari 24 minggu), gunakan skala BULAN agar tabel rapi dan tidak terpotong!
$useMonthlyMode = ($durasiBulan > 6);
$totalColumns   = $useMonthlyMode ? $durasiBulan : $totalMinggu;
$columnLabel    = $useMonthlyMode ? 'B' : 'M'; // 'B' = Bulan, 'M' = Minggu

// Pembagian 5 Fase Universal Matriks (Disesuaikan dengan Mode Bulanan / Mingguan)
if ($useMonthlyMode) {
    // Mode Durasi Panjang (Bulanan: B1 - B12)
    $phases = [
        [
            "fase" => "Fase 1: Inisiasi & UI/UX Design",
            "deskripsi" => "Analisis kebutuhan, PM-AAS, desain UI/UX, dan arsitektur database.",
            "active_units" => [1]
        ],
        [
            "fase" => "Fase 2: Core Development & API",
            "deskripsi" => "Development Aplikasi, Core Logic & Integrasi API Sistem.",
            "active_units" => range(2, max(2, (int)floor($durasiBulan * 0.4)))
        ],
        [
            "fase" => "Fase 3: Algoritma & Modul Spesifik",
            "deskripsi" => "Implementasi Modul Spesifik, Algoritma, dan Fitur Khusus.",
            "active_units" => range((int)floor($durasiBulan * 0.4) + 1, (int)floor($durasiBulan * 0.75))
        ],
        [
            "fase" => "Fase 4: Testing, QA & Bug Fixing",
            "deskripsi" => "Pengujian fungsi, integrasi Web Dashboard Admin, dan bug fixing.",
            "active_units" => [$durasiBulan - 1]
        ],
        [
            "fase" => "Fase 5: UAT, Training & Go-Live",
            "deskripsi" => "UAT bersama Klien, Pelatihan, & Deployment Go-Live.",
            "active_units" => [$durasiBulan]
        ]
    ];
} else {
    // Mode Durasi Normal (Mingguan: M1 - M24)
    $phases = [
        [
            "fase" => "Fase 1: Inisiasi & UI/UX Design",
            "deskripsi" => "Analisis kebutuhan, PM-AAS, desain UI/UX, dan arsitektur database.",
            "active_units" => [1, 2]
        ],
        [
            "fase" => "Fase 2: Core Development & API Integration",
            "deskripsi" => "Development Aplikasi, Core Logic & Integrasi API Sistem.",
            "active_units" => range(3, max(3, (int)floor($totalMinggu * 0.35) + 2))
        ],
        [
            "fase" => "Fase 3: Algoritma & Modul Spesifik",
            "deskripsi" => "Implementasi Modul Spesifik, Algoritma, dan Fitur Khusus.",
            "active_units" => range((int)floor($totalMinggu * 0.5) + 1, (int)floor($totalMinggu * 0.75))
        ],
        [
            "fase" => "Fase 4: Testing, QA & Bug Fixing",
            "deskripsi" => "Pengujian fungsi, integrasi Web Dashboard Admin, dan bug fixing.",
            "active_units" => [$totalMinggu - 1]
        ],
        [
            "fase" => "Fase 5: UAT, Training & Deployment Go-Live",
            "deskripsi" => "UAT bersama Klien, Pelatihan, & Deployment Go-Live.",
            "active_units" => [$totalMinggu]
        ]
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Pelaksanaan Proyek - <?= htmlspecialchars($project['nama_projek']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        @media print {
            .no-print { display: none !important; }
            body { 
                background: white !important; 
                padding: 0 !important; 
                color: #000 !important; 
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .print-container { 
                box-shadow: none !important; 
                border: none !important; 
                width: 100% !important; 
                max-width: 100% !important; 
                padding: 0 !important; 
            }
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen py-6 px-4">

    <!-- Action Bar (Hanya Tampil di Browser) -->
    <div class="max-w-6xl mx-auto mb-4 flex justify-between items-center no-print">
        <a href="index.php" class="text-xs font-bold text-emerald-700 hover:underline flex items-center gap-1">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Workload Hub
        </a>
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 font-semibold">Pilih 'Lanskap / Landscape' saat cetak</span>
            <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl flex items-center gap-2 transition text-xs shadow-md">
                <i class="fa-solid fa-print"></i> Cetak PDF / Print
            </button>
        </div>
    </div>

    <!-- Dokumen Cetak Gantt Chart -->
    <div class="max-w-6xl mx-auto bg-white p-8 rounded-xl shadow-xl border border-slate-200 print-container">
        
        <!-- Kop Surat Resmi PT CTC -->
        <div class="flex justify-between items-start border-b-2 border-emerald-950 pb-4 mb-4">
            <div class="flex items-center gap-3">
                <img src="/website%20manajemen%20projek/assets/images/logo.jpg" alt="Logo PT CTC" class="w-12 h-12 object-cover rounded-md">
                <div>
                    <h1 class="text-base font-black tracking-wider text-emerald-950 uppercase leading-none">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p class="text-[10px] text-emerald-700 font-bold mt-1">Enterprise IT Solution, Cyber Security & Software Engineering</p>
                    <p class="text-[9px] text-slate-500">Perumahan Cahaya Garuda Residence Kav. 31, Bojongsari, Kota Depok, Jawa Barat | Telp: 087785699136</p>
                </div>
            </div>
            <div class="text-right">
                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-800 text-[10px] font-black rounded uppercase border border-emerald-200 inline-block">
                    JADWAL PELAKSANAAN PROYEK
                </span>
                <p class="text-[10px] text-slate-500 mt-1 font-mono font-semibold">TGL: <?= date('d F Y') ?></p>
            </div>
        </div>

        <!-- Info Proyek Target -->
        <div class="grid grid-cols-3 gap-3 mb-4 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
            <div>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Nama Proyek:</span>
                <strong class="text-slate-900 text-xs font-bold leading-tight block"><?= htmlspecialchars($project['nama_projek']) ?></strong>
            </div>
            <div>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Klien / Instansi:</span>
                <strong class="text-slate-800 text-xs font-bold leading-tight block"><?= htmlspecialchars($project['klien']) ?></strong>
            </div>
            <div>
                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Proyeksi Pelaksanaan:</span>
                <strong class="text-emerald-700 text-xs font-bold leading-tight block">
                    <?= $durasiBulan ?> Bulan (<?= $totalMinggu ?> Minggu)
                    <span class="text-[9px] font-normal text-slate-500">(Skala: <?= $useMonthlyMode ? 'Bulanan' : 'Mingguan' ?>)</span>
                </strong>
            </div>
        </div>

        <!-- Tabel Matriks Gantt Chart yang Presisi & Tidak Terpotong -->
        <div class="overflow-hidden border border-slate-300 rounded-lg bg-white mb-6">
            <table class="w-full text-left border-collapse" style="table-layout: fixed;">
                <thead>
                    <tr class="bg-slate-900 text-white uppercase font-black text-[10px]">
                        <th class="p-2 border-r border-slate-700" style="width: 28%;">Tahapan Pekerjaan</th>
                        <th class="p-2 border-r border-slate-700" style="width: 38%;">Deskripsi Singkat Output</th>
                        <?php for ($u = 1; $u <= $totalColumns; $u++): ?>
                            <th class="p-1 text-center border-r border-slate-700 text-[9px] font-mono" style="width: auto;">
                                <?= $columnLabel . $u ?>
                            </th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 text-[10px]">
                    <?php foreach ($phases as $p): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="p-2 font-bold text-slate-900 border-r border-slate-200 leading-tight"><?= $p['fase'] ?></td>
                            <td class="p-2 text-slate-600 border-r border-slate-200 text-[9.5px] leading-tight"><?= $p['deskripsi'] ?></td>
                            <?php for ($u = 1; $u <= $totalColumns; $u++): ?>
                                <?php $isFilled = in_array($u, $p['active_units']); ?>
                                <td class="p-1 text-center border-r border-slate-200 align-middle">
                                    <?php if ($isFilled): ?>
                                        <div class="w-3.5 h-3.5 bg-slate-900 rounded-[2px] mx-auto shadow-sm"></div>
                                    <?php endif; ?>
                                </td>
                            <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Area Tanda Tangan Resmi -->
        <div class="grid grid-cols-2 gap-8 text-center text-[11px] pt-4 border-t border-slate-200">
            <div>
                <p class="font-bold text-slate-900 uppercase">DISIAPKAN OLEH</p>
                <p class="text-slate-500 font-semibold text-[10px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                <div class="h-12 my-1 flex items-center justify-center text-slate-300 italic text-[10px]">
                    [Tanda Tangan & Stempel]
                </div>
                <p class="font-bold underline text-slate-900">Project Manager / Director</p>
            </div>
            <div>
                <p class="font-bold text-slate-900 uppercase">DISETUJUI OLEH KLIEN</p>
                <p class="text-slate-500 font-semibold text-[10px]"><?= strtoupper(htmlspecialchars($project['klien'])) ?></p>
                <div class="h-12 my-1 flex items-center justify-center text-slate-300 italic text-[10px]">
                    [Tanda Tangan & Stempel]
                </div>
                <p class="font-bold underline text-slate-900">Perwakilan Sah Klien</p>
            </div>
        </div>

    </div>
</body>
</html>