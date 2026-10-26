<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Mendukung parameter 'id' maupun 'project_id' agar fleksibel
$id = $_GET['id'] ?? $_GET['project_id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch Detail Proyek
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    header("Location: index.php");
    exit;
}

// Fetch Tasks Proyek & PIC
$stmtTasks = $pdo->prepare("
    SELECT t.*, u.nama AS nama_karyawan 
    FROM tasks t
    LEFT JOIN users u ON t.user_id = u.id
    WHERE t.project_id = ?
    ORDER BY t.deadline ASC
");
$stmtTasks->execute([$id]);
$tasks = $stmtTasks->fetchAll();

// Fetch Dokumen Vault Terlampir
$stmtFiles = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY created_at DESC");
$stmtFiles->execute([$id]);
$files = $stmtFiles->fetchAll();

// Hitung Statistik Task
$totalTasks = count($tasks);
$completedTasks = 0;
foreach ($tasks as $tk) {
    if (isset($tk['status']) && strtolower($tk['status']) === 'done') {
        $completedTasks++;
    }
}
$progressPercent = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

// Auto Generate Nomor Surat / Laporan
function bulanRomawi($bulan) {
    $map = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'];
    return $map[(int)$bulan] ?? 'I';
}
$noLaporan = sprintf("%03d", $project['id']) . "/CTC-REP/" . bulanRomawi(date('n')) . "/" . date('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Proyek - <?= htmlspecialchars($project['nama_projek']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsPDF + html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        /* ===== CETAK FULL-BLEED TANPA MARGIN PUTIH ===== */
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

        /* Container Dokumen: GAP = 0 MENCEGAH SPILL HALAMAN KOSONG */
        #reportContainer {
            display: flex;
            flex-direction: column;
            gap: 0;
            width: 210mm;
            margin: 0 auto;
            padding: 0;
            background: #ffffff;
        }

        /* Halaman A4 Presisi Full-Bleed */
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

        /* Background Kop Surat FULL 210x297mm */
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
                width: 210mm !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
            }
            .no-print { display: none !important; }
            #reportContainer {
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
        <a href="detail.php?id=<?= $id ?>" class="text-xs font-bold text-slate-300 hover:text-white flex items-center gap-1.5 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Detail Proyek
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

    <!-- Wrapper Utama Dokumen PDF (2 Halaman Presisi) -->
    <div id="reportContainer">

        <!-- ================= HALAMAN 1 (KOP SURAT UTAMA kopsurat-ctc.png) ================= -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc.png"
                 alt="Kop Surat Utama"
                 class="kop-background"
                 crossorigin="anonymous">

            <!-- Konten Utama Halaman 1 -->
            <div class="doc-font relative z-10 pt-[52mm] pb-[20mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11.5px] text-black">
                <div>
                    <!-- Meta Laporan Proyek -->
                    <div class="mb-4 space-y-1">
                        <div class="flex justify-between font-bold text-[12px]">
                            <span>No. Dokumen : <?= $noLaporan ?></span>
                            <span>Depok, <?= date('d F Y') ?></span>
                        </div>
                        <p><strong>Perihal</strong> : Laporan Ringkasan Progress &amp; Status Proyek</p>
                        <div class="pt-2">
                            <p>Kepada Yth.</p>
                            <p class="font-bold text-[12px]">Pimpinan / Direksi Management</p>
                            <p class="font-bold text-black text-[12px] underline"><?= htmlspecialchars($project['klien']) ?></p>
                            <p>Di Tempat</p>
                        </div>
                    </div>

                    <!-- Ringkasan Informasi Proyek Card -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50/90 border-l-4 border-emerald-800 p-3.5 rounded-r-lg mb-5 font-sans shadow-sm">
                        <div>
                            <span class="text-slate-500 font-bold uppercase block text-[9.5px]">Nama Proyek</span>
                            <span class="font-bold text-slate-900 text-[11.5px] block mt-0.5 leading-snug"><?= htmlspecialchars($project['nama_projek']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-bold uppercase block text-[9.5px]">Klien</span>
                            <span class="font-semibold text-slate-800 text-[11px] block mt-0.5"><?= htmlspecialchars($project['klien']) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-bold uppercase block text-[9.5px]">Total Budget</span>
                            <span class="font-bold text-emerald-900 text-[11px] block mt-0.5">Rp <?= number_format($project['budget'] ?? 0, 0, ',', '.') ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-bold uppercase block text-[9.5px]">Status &amp; Deadline</span>
                            <span class="font-bold text-slate-800 text-[11px] block mt-0.5">
                                <?= htmlspecialchars($project['status']) ?> (<?= !empty($project['deadline']) ? date('d M Y', strtotime($project['deadline'])) : '-' ?>)
                            </span>
                        </div>
                    </div>

                    <!-- Progress Milestone Status Bar -->
                    <div class="mb-5 font-sans">
                        <div class="flex justify-between text-[11px] font-bold mb-1.5">
                            <span class="text-slate-800 uppercase tracking-wide">Pencapaian Progress Milestone</span>
                            <span class="text-emerald-800 font-black"><?= $progressPercent ?>% Completed (<?= $completedTasks ?>/<?= $totalTasks ?> Tugas Selesai)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-3 overflow-hidden border border-slate-300">
                            <div class="bg-emerald-700 h-3 rounded-full transition-all duration-500" style="width: <?= $progressPercent ?>%"></div>
                        </div>
                    </div>

                    <!-- Tabel Workload & Status Tugas -->
                    <div class="font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-1.5 mb-3 uppercase tracking-wide flex justify-between items-center">
                            <span>1. RINCIAN TUGAS &amp; PENUGASAN TIM</span>
                            <span class="text-[10.5px] font-semibold text-slate-600">Total: <?= $totalTasks ?> Modul</span>
                        </h3>

                        <table class="w-full border-collapse border border-slate-300 text-[10.5px] shadow-sm rounded-lg overflow-hidden">
                            <thead>
                                <tr class="bg-emerald-900 text-white uppercase text-[9.5px] tracking-wider">
                                    <th class="border border-emerald-800 p-2 text-center w-8 font-bold">No</th>
                                    <th class="border border-emerald-800 p-2 text-left font-bold">Tugas / Modul</th>
                                    <th class="border border-emerald-800 p-2 text-left font-bold">PIC (Penanggung Jawab)</th>
                                    <th class="border border-emerald-800 p-2 text-center w-20 font-bold">Prioritas</th>
                                    <th class="border border-emerald-800 p-2 text-center w-20 font-bold">Status</th>
                                    <th class="border border-emerald-800 p-2 text-center w-24 font-bold">Deadline</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-slate-400 italic">Belum ada tugas dialokasikan untuk proyek ini.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php $no = 1; foreach ($tasks as $t): ?>
                                        <tr class="bg-white hover:bg-slate-50 transition-colors">
                                            <td class="border border-slate-300 p-2 text-center text-slate-500 font-semibold"><?= $no++ ?></td>
                                            <td class="border border-slate-300 p-2 font-bold text-slate-900 leading-tight">
                                                <?= htmlspecialchars($t['nama_tugas']) ?>
                                                <?php if (!empty($t['progress_notes'])): ?>
                                                    <p class="text-[9.5px] text-slate-500 font-normal italic mt-0.5">Note: <?= htmlspecialchars($t['progress_notes']) ?></p>
                                                <?php endif; ?>
                                            </td>
                                            <td class="border border-slate-300 p-2 text-slate-700 font-medium"><?= htmlspecialchars($t['nama_karyawan'] ?? 'Unassigned') ?></td>
                                            <td class="border border-slate-300 p-2 text-center font-bold uppercase text-[9px] text-slate-700"><?= htmlspecialchars($t['prioritas']) ?></td>
                                            <td class="border border-slate-300 p-2 text-center font-bold text-[9px] uppercase">
                                                <?php 
                                                    $st = strtolower($t['status'] ?? '');
                                                    $badgeClass = ($st === 'done' || $st === 'completed') ? 'text-emerald-800' : 'text-amber-700';
                                                ?>
                                                <span class="<?= $badgeClass ?> font-black"><?= htmlspecialchars($t['status']) ?></span>
                                            </td>
                                            <td class="border border-slate-300 p-2 text-center text-slate-600 font-medium"><?= !empty($t['deadline']) ? date('d M Y', strtotime($t['deadline'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================= HALAMAN 2 (KOP SURAT HALAMAN 2+ kopsurat-ctc-2.png) ================= -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc-2.png"
                 alt="Kop Surat Halaman 2"
                 class="kop-background"
                 crossorigin="anonymous">

            <!-- Konten Utama Halaman 2 -->
            <div class="doc-font relative z-10 pt-[28mm] pb-[20mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11.5px] text-black">
                <div>
                    <!-- Daftar Berkas Document Vault Terlampir -->
                    <div class="mb-6 font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-1.5 mb-3 uppercase tracking-wide">
                            2. BERKAS &amp; DOKUMEN VAULT TERLAMPIR
                        </h3>
                        <table class="w-full border-collapse border border-slate-300 text-[10.5px] shadow-sm rounded-lg overflow-hidden">
                            <thead>
                                <tr class="bg-emerald-900 text-white uppercase text-[9.5px] tracking-wider">
                                    <th class="border border-emerald-800 p-2 text-left font-bold">Nama Berkas</th>
                                    <th class="border border-emerald-800 p-2 text-left font-bold">Kategori</th>
                                    <th class="border border-emerald-800 p-2 text-center w-24 font-bold">Ukuran</th>
                                    <th class="border border-emerald-800 p-2 text-center w-32 font-bold">Tanggal Upload</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php if (empty($files)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-slate-400 italic">Tidak ada berkas dokumen terlampir dalam vault.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($files as $f): ?>
                                        <tr class="bg-white hover:bg-slate-50 transition-colors">
                                            <td class="border border-slate-300 p-2 font-bold text-slate-900"><?= htmlspecialchars($f['nama_file']) ?></td>
                                            <td class="border border-slate-300 p-2 text-slate-600 font-medium"><?= htmlspecialchars($f['kategori'] ?? 'Umum') ?></td>
                                            <td class="border border-slate-300 p-2 text-center text-slate-500 font-mono"><?= round(($f['ukuran_file'] ?? 0) / 1024, 1) ?> KB</td>
                                            <td class="border border-slate-300 p-2 text-center text-slate-600"><?= !empty($f['created_at']) ? date('d M Y', strtotime($f['created_at'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Ringkasan Catatan Verifikasi -->
                    <div class="p-3.5 border-l-4 border-emerald-800 bg-slate-50/90 mb-6 font-sans rounded-r-lg shadow-sm">
                        <h3 class="font-bold text-[11.5px] text-slate-900 mb-1 uppercase tracking-wide">CATATAN &amp; VERIFIKASI PROGRESS DOKUMEN</h3>
                        <p class="text-[11px] text-slate-800 text-justify leading-relaxed">
                            Laporan ringkasan ini menyajikan realisasi pencapaian pengerjaan proyek <strong><?= htmlspecialchars($project['nama_projek']) ?></strong> hingga tanggal <strong><?= date('d F Y') ?></strong>. Seluruh data tugas, berkas vault, dan status milestone yang tercantum telah diperiksa dan disesuaikan dengan standar operational workspace <strong>PT. Cipta Teknologi Cendekia</strong>.
                        </p>
                    </div>

                    <!-- Lembar Persetujuan & Stempel Basah -->
                    <div class="mt-8 font-sans">
                        <h3 class="font-bold text-[12px] text-slate-900 border-b-2 border-slate-300 pb-1.5 mb-3.5 uppercase tracking-wide">
                            3. LEMBAR PENGESAHAN LAPORAN (REPORT APPROVAL)
                        </h3>
                        <p class="mb-4 text-slate-700 text-[11px] text-justify">Demikian laporan ringkasan ini dibuat secara sah untuk dipergunakan sebagai acuan monitoring dan evaluasi bersama.</p>

                        <div class="grid grid-cols-2 text-center gap-6">

                            <!-- Sisi Klien -->
                            <div class="flex flex-col justify-between h-48 border border-dashed border-slate-300 p-4 rounded-xl bg-white/80">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11.5px]">Mengetahui / Perwakilan Klien</p>
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

                            <!-- Sisi PT CTC: Stempel bergeser menimpa di atas nama penanggung jawab -->
                            <div class="flex flex-col justify-between h-48 border border-emerald-200 bg-emerald-50/40 p-4 rounded-xl relative">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11.5px]">Penanggung Jawab Proyek</p>
                                    <p class="text-emerald-900 font-bold text-[11px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                                </div>

                                <!-- Area TTD + Stempel basah transparan menimpa nama -->
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

    <!-- ===== Script: Hapus background putih Stempel + Download PDF per-halaman full-bleed ===== -->
    <script>
    function waitForImages() {
        const imgs = Array.from(document.querySelectorAll('#reportContainer img'));
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

            const pages = document.querySelectorAll('#reportContainer .page-a4');
            const PAGE_W = 210;
            const PAGE_H = 297;

            for (let i = 0; i < pages.length; i++) {
                const page = pages[i];
                const canvas = await html2canvas(page, {
                    scale: 3, // Kualitas cetak HD tajam
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

            pdf.save('Laporan_Proyek_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['nama_projek']) ?>.pdf');
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