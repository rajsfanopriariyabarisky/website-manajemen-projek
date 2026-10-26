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

// LOGIKA ADAPTIF: Jika durasi > 6 bulan (lebih dari 24 minggu), gunakan skala BULAN agar tabel rapi dan tidak terpotong
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
    <!-- jsPDF + html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* ===== CETAK FULL-BLEED A4 LANDSCAPE TANPA MARGIN PUTIH ===== */
        @page {
            size: A4 landscape;
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
            line-height: 1.4;
        }

        .doc-font, .doc-font * {
            font-family: 'Times New Roman', Times, serif !important;
        }

        /* Container Dokumen PDF */
        #ganttContainer {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: 297mm;
            margin: 0 auto;
            padding: 0;
            background: #ffffff;
        }

        /* Halaman A4 Presisi Full-Bleed Landscape */
        .page-a4-landscape {
            width: 297mm;
            height: 210mm;
            min-height: 210mm;
            max-height: 210mm;
            position: relative;
            overflow: hidden;
            background-color: #ffffff;
            flex-shrink: 0;
            box-sizing: border-box;
        }

        /* Stempel Basah Transparan */
        .stempel-img {
            mix-blend-mode: multiply;
            filter: contrast(115%);
            pointer-events: none;
            user-select: none;
        }

        .no-print { display: flex; }

        @media print {
            html, body {
                width: 297mm !important;
                height: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            .no-print { display: none !important; }
            #ganttContainer {
                margin: 0 !important;
                padding: 0 !important;
                gap: 0 !important;
                width: 297mm !important;
                display: block !important;
            }
            .page-a4-landscape {
                width: 297mm !important;
                height: 210mm !important;
                box-shadow: none !important;
                page-break-after: always !important;
                break-after: page !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
        }

        @media screen {
            .page-a4-landscape {
                box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.4);
            }
        }
    </style>
</head>
<body class="bg-slate-900 min-h-screen py-8 flex flex-col items-center">

    <!-- Action Bar (Browser Only) -->
    <div class="w-[297mm] mb-6 flex flex-col sm:flex-row justify-between items-center gap-3 no-print font-sans">
        <a href="index.php" class="text-xs font-bold text-slate-300 hover:text-white flex items-center gap-1.5 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Workload Hub
        </a>
        <div class="flex items-center gap-3">
            <button onclick="window.print()" class="bg-slate-200 hover:bg-slate-300 text-slate-800 font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow">
                <i class="fa-solid fa-print"></i> Cetak Browser
            </button>
            <button id="btnDownloadPdf" onclick="downloadDirectPDF()" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow-md">
                <i class="fa-solid fa-file-arrow-down"></i> Download PDF Langsung
            </button>
        </div>
    </div>

    <!-- Wrapper Utama Dokumen PDF (1 Halaman Landscape) -->
    <div id="ganttContainer">

        <div class="page-a4-landscape">
            <!-- Konten Utama Halaman Gantt Chart -->
            <div class="doc-font relative z-10 pt-[14mm] pb-[14mm] px-[18mm] h-full flex flex-col justify-between text-justify text-[11px] text-black">
                <div>
                    <!-- Kop Surat Perusahaan (Sesuai Format Resmi CTC) -->
                    <div class="flex items-center justify-between border-b-4 border-double border-emerald-950 pb-3 mb-4">
                        <div class="flex items-center gap-3.5">
                            <img src="../../assets/images/logo.png" alt="Logo PT CTC" class="w-14 h-14 object-cover rounded-md" onerror="this.onerror=null; this.src='../../assets/images/logo.jpg';">
                            <div>
                                <h1 class="text-base font-bold tracking-wider text-emerald-950 uppercase font-sans leading-tight">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                                <p class="text-[10px] font-sans text-slate-600 font-semibold">Enterprise IT Solution, Software Engineering &amp; Digital Transformation</p>
                                <p class="text-[9px] font-sans text-slate-500">Perumahan Cahaya Garuda Residence Kav. 31, Bojongsari, Kota Depok, Jawa Barat | Telp: +62 87785699136 | www.ctc-nusantara.com</p>
                                <p class="text-[9px] font-sans text-slate-500">Email: ciptateknologicendekia@gmail.com</p>
                            </div>
                        </div>
                        <div class="text-right font-sans">
                            <span class="px-3 py-1 bg-emerald-900 text-white text-[10px] font-black tracking-wider rounded uppercase inline-block shadow-sm">
                                JADWAL PELAKSANAAN PROYEK
                            </span>
                            <p class="text-[10px] text-slate-700 font-bold mt-1.5">Tanggal Dokumen: <?= date('d F Y') ?></p>
                            <?php if (!empty($project['nomor_kontrak'])): ?>
                                <p class="text-[9.5px] font-mono text-slate-500 font-semibold">Ref SPK: <?= htmlspecialchars($project['nomor_kontrak']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info Proyek Target -->
                    <div class="grid grid-cols-3 gap-3 mb-4 bg-slate-50/90 p-3 rounded-lg border border-slate-300 text-[10.5px] font-sans shadow-xs">
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">NAMA SISTEM PROYEK:</span>
                            <strong class="text-slate-900 text-[11px] font-bold leading-snug block truncate"><?= htmlspecialchars($project['nama_projek']) ?></strong>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">KLIEN / INSTANSI MITRA:</span>
                            <strong class="text-slate-800 text-[11px] font-bold leading-snug block truncate"><?= htmlspecialchars($project['klien']) ?></strong>
                        </div>
                        <div>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">PROYEKSI PELAKSANAAN:</span>
                            <strong class="text-emerald-900 text-[11px] font-bold leading-snug block">
                                <?= $durasiBulan ?> Bulan (<?= $totalMinggu ?> Minggu)
                                <span class="text-[9.5px] font-normal text-slate-500">(Skala: <?= $useMonthlyMode ? 'Bulanan' : 'Mingguan' ?>)</span>
                            </strong>
                        </div>
                    </div>

                    <!-- Tabel Matriks Gantt Chart yang Presisi -->
                    <div class="font-sans mb-4">
                        <table class="w-full border-collapse border border-slate-300 text-[10px] shadow-sm rounded-lg overflow-hidden" style="table-layout: fixed;">
                            <thead>
                                <tr class="bg-emerald-900 text-white uppercase font-bold text-[9.5px] tracking-wider">
                                    <th class="border border-emerald-800 p-2 text-left" style="width: 27%;">Tahapan Pekerjaan</th>
                                    <th class="border border-emerald-800 p-2 text-left" style="width: 38%;">Deskripsi Singkat Output</th>
                                    <?php for ($u = 1; $u <= $totalColumns; $u++): ?>
                                        <th class="border border-emerald-800 p-1 text-center text-[9px] font-mono font-bold" style="width: auto;">
                                            <?= $columnLabel . $u ?>
                                        </th>
                                    <?php endfor; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach ($phases as $p): ?>
                                    <tr class="bg-white hover:bg-slate-50/80 transition-colors">
                                        <td class="border border-slate-300 p-2 font-bold text-slate-900 leading-snug"><?= htmlspecialchars($p['fase']) ?></td>
                                        <td class="border border-slate-300 p-2 text-slate-700 text-[9.5px] leading-snug"><?= htmlspecialchars($p['deskripsi']) ?></td>
                                        <?php for ($u = 1; $u <= $totalColumns; $u++): ?>
                                            <?php $isFilled = in_array($u, $p['active_units']); ?>
                                            <td class="border border-slate-300 p-1 text-center align-middle">
                                                <?php if ($isFilled): ?>
                                                    <div class="w-3.5 h-3.5 bg-emerald-700 rounded-[2px] mx-auto shadow-xs border border-emerald-800"></div>
                                                <?php endif; ?>
                                            </td>
                                        <?php endfor; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Lembar Persetujuan & Stempel Basah PT CTC -->
                <div class="font-sans">
                    <div class="grid grid-cols-2 text-center gap-8">

                        <!-- Sisi PT CTC: Stempel menimpa di atas nama Direktur -->
                        <div class="flex flex-col justify-between h-36 border border-emerald-200 bg-emerald-50/40 p-3 rounded-xl relative">
                            <div>
                                <p class="font-bold text-slate-900 text-[10.5px]">DISIAPKAN OLEH</p>
                                <p class="text-emerald-900 font-bold text-[10px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                            </div>

                            <!-- Area TTD + Stempel basah transparan -->
                            <div class="relative h-14 w-full flex items-center justify-center">
                                <img src="../../assets/images/Stempel.png"
                                     alt="Stempel PT CTC"
                                     id="stempelImg"
                                     class="stempel-img absolute w-24 h-24 object-contain left-[28%] top-[-14px] -rotate-12 opacity-60"
                                     onerror="this.style.display='none'">
                            </div>

                            <div class="relative z-10">
                                <p class="font-bold underline text-slate-900 text-[10.5px]">Mohammad Habbas Ali, SE.</p>
                                <p class="text-[9px] text-slate-600 font-medium mt-0.5">Direktur / Managing Director</p>
                            </div>
                        </div>

                        <!-- Sisi Klien -->
                        <div class="flex flex-col justify-between h-36 border border-dashed border-slate-300 p-3 rounded-xl bg-white/80">
                            <div>
                                <p class="font-bold text-slate-900 text-[10.5px]">DISETUJUI OLEH KLIEN</p>
                                <p class="text-slate-600 font-semibold text-[10px]"><?= strtoupper(htmlspecialchars($project['klien'])) ?></p>
                            </div>
                            <div class="h-14 flex items-center justify-center italic text-slate-400 text-[9px]">
                                [ Tanda Tangan &amp; Stempel Klien ]
                            </div>
                            <div>
                                <p class="font-bold underline text-slate-900 text-[10.5px]">( .................................................... )</p>
                                <p class="text-[9px] text-slate-500 font-medium mt-0.5">Perwakilan Sah Klien</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ===== Script: Hapus background putih Stempel + Download PDF per-halaman full-bleed ===== -->
    <script>
    function waitForImages() {
        const imgs = Array.from(document.querySelectorAll('#ganttContainer img'));
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
                    canvas.width = img.naturalWidth || 200;
                    canvas.height = img.naturalHeight || 200;
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
                orientation: 'landscape',
                compress: true
            });

            const pages = document.querySelectorAll('#ganttContainer .page-a4-landscape');
            const PAGE_W = 297;
            const PAGE_H = 210;

            for (let i = 0; i < pages.length; i++) {
                const page = pages[i];
                const canvas = await html2canvas(page, {
                    scale: 2, // Presisi tajam untuk A4 Landscape
                    useCORS: true,
                    allowTaint: true,
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
                if (i > 0) pdf.addPage('a4', 'landscape');
                pdf.addImage(imgData, 'JPEG', 0, 0, PAGE_W, PAGE_H, undefined, 'FAST');
            }

            pdf.save('Jadwal_Pelaksanaan_Proyek_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['nama_projek']) ?>.pdf');
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