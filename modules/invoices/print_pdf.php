<?php
require_once __DIR__ . '/../../config/database.php';

$id         = $_GET['id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$type       = $_GET['type'] ?? 'invoice';

if (!$id && !$project_id) {
    header("Location: index.php");
    exit;
}

$isPelunasan = ($type === 'pelunasan' && !empty($project_id));

if ($isPelunasan) {
    // Mode Kuitansi Pelunasan BAST: Mengambil Detail Proyek & Seluruh Riwayat Invoice Termin
    $stmtProj = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmtProj->execute([$project_id]);
    $project = $stmtProj->fetch();

    if (!$project) {
        header("Location: index.php");
        exit;
    }

    $stmtInvList = $pdo->prepare("SELECT * FROM invoices WHERE project_id = ? ORDER BY termin_ke ASC");
    $stmtInvList->execute([$project_id]);
    $invoiceHistory = $stmtInvList->fetchAll();

    $totalPaidReal = array_sum(array_column($invoiceHistory, 'jumlah_tagihan'));
} else {
    // Mode Cetak Invoice Per Termin
    $stmt = $pdo->prepare("
        SELECT i.*, p.nama_projek, p.klien, p.budget 
        FROM invoices i
        JOIN projects p ON i.project_id = p.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        header("Location: index.php");
        exit;
    }
}

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
    <title><?= $isPelunasan ? 'Kuitansi Pelunasan BAST - ' . htmlspecialchars($project['nama_projek']) : 'Invoice - ' . htmlspecialchars($invoice['nomor_invoice']) ?></title>
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
            line-height: 1.5;
        }

        .doc-font, .doc-font * {
            font-family: 'Times New Roman', Times, serif !important;
        }

        /* Container Dokumen: GAP = 0 MENCEGAH SPILL HALAMAN KOSONG */
        #invoiceContainer {
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
            #invoiceContainer {
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
        <a href="index.php" class="text-xs font-bold text-slate-300 hover:text-white flex items-center gap-1.5 transition">
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Billing
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

    <!-- Wrapper Utama Dokumen PDF (Eksak 1 Halaman A4) -->
    <div id="invoiceContainer">

        <!-- ================= HALAMAN 1 (KOP SURAT UTAMA kopsurat-ctc.png) ================= -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc.png"
                 alt="Kop Surat Utama"
                 class="kop-background"
                 crossorigin="anonymous">

            <!-- Konten Utama Halaman Invoice / Kuitansi -->
            <div class="doc-font relative z-10 pt-[46mm] pb-[18mm] px-[20mm] h-full flex flex-col text-justify text-[11px] text-black">
                
                <!-- Judul Dokumen -->
                <div class="text-center mb-4">
                    <h2 class="text-[14px] font-bold underline tracking-wide uppercase">
                        <?= $isPelunasan ? 'KUITANSI PELUNASAN RESMI (BAST)' : 'INVOICE / TAGIHAN PEMBAYARAN' ?>
                    </h2>
                    <p class="text-[11px] font-mono font-bold text-slate-800 mt-0.5">
                        Nomor: <?= $isPelunasan ? 'KW-CTC/' . date('Y/m') . '/' . sprintf("%03d", $project['id']) : htmlspecialchars($invoice['nomor_invoice']) ?>
                    </p>
                    <div class="mt-1">
                        <span class="inline-block px-3 py-0.5 text-[9.5px] font-sans font-extrabold rounded-full uppercase tracking-wider border <?= $isPelunasan ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : ($invoice['status'] === 'Paid' ? 'bg-emerald-50 text-emerald-800 border-emerald-300' : 'bg-amber-50 text-amber-800 border-amber-300') ?>">
                            STATUS: <?= $isPelunasan ? 'LUNAS 100% (VERIFIED)' : htmlspecialchars($invoice['status']) ?>
                        </span>
                    </div>
                </div>

                <!-- Meta Info Proyek & Klien Mitra -->
                <div class="grid grid-cols-2 gap-6 mb-4 font-sans text-[10.5px] bg-slate-50/90 p-3 rounded-lg border-l-4 border-emerald-800">
                    <div>
                        <p class="font-bold uppercase text-slate-400 text-[9px] tracking-wider mb-0.5">Diberikan Kepada / Klien Mitra:</p>
                        <h3 class="text-sm font-bold text-slate-900 leading-snug"><?= htmlspecialchars($isPelunasan ? $project['klien'] : $invoice['klien']) ?></h3>
                        <p class="text-slate-700 mt-0.5">Sistem Proyek: <strong><?= htmlspecialchars($isPelunasan ? $project['nama_projek'] : $invoice['nama_projek']) ?></strong></p>
                    </div>
                    <div class="text-right space-y-0.5">
                        <p><strong class="text-slate-500">Nilai Total Kontrak:</strong> <span class="font-mono font-bold text-slate-900">Rp <?= number_format($isPelunasan ? $project['budget'] : $invoice['budget'], 0, ',', '.') ?></span></p>
                        <p><strong class="text-slate-500">Tanggal Terbit Dokumen:</strong> <?= date('d F Y') ?></p>
                        <?php if (!$isPelunasan): ?>
                            <p><strong class="text-slate-500">Tanggal Jatuh Tempo:</strong> <?= date('d F Y', strtotime($invoice['jatuh_tempo'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isPelunasan): ?>
                    <!-- KHUSUS KUITANSI PELUNASAN GABUNGAN TERMIN 1 & TERMIN 2 -->
                    <div class="bg-emerald-50/80 border border-emerald-600 p-3 rounded-lg mb-4 font-sans">
                        <h3 class="text-[11px] font-bold text-emerald-950 uppercase mb-1 border-b border-emerald-200 pb-1 tracking-wide">
                            SURAT KETERANGAN PELUNASAN RESMI (BAST)
                        </h3>
                        <p class="text-[10.5px] text-slate-800 leading-relaxed text-justify">
                            Dengan ini PT. Cipta Teknologi Cendekia menyatakan bahwa seluruh kewajiban pembayaran termin penagihan atas proyek <strong>"<?= htmlspecialchars($project['nama_projek']) ?>"</strong> telah <strong>LUNAS 100%</strong> diterima oleh kas perusahaan.
                        </p>
                    </div>

                    <div class="font-sans mb-4">
                        <table class="w-full text-left text-[10.5px] border-collapse border border-slate-300 shadow-xs rounded-lg overflow-hidden">
                            <thead>
                                <tr class="bg-emerald-900 text-white font-bold uppercase text-[9.5px] tracking-wider">
                                    <th class="border border-emerald-800 p-2.5">Rincian Pembayaran Termin</th>
                                    <th class="border border-emerald-800 p-2.5">No. Invoice Ref</th>
                                    <th class="border border-emerald-800 p-2.5 text-center">Status</th>
                                    <th class="border border-emerald-800 p-2.5 text-right">Nominal Terbayar</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <?php foreach ($invoiceHistory as $ih): ?>
                                    <tr class="bg-white hover:bg-slate-50/80 transition-colors">
                                        <td class="border border-slate-300 p-2.5 font-bold text-slate-900">
                                            Termin <?= $ih['termin_ke'] ?> - <?= htmlspecialchars($ih['keterangan_termin']) ?>
                                            <span class="block text-[9.5px] text-slate-500 font-normal">Tgl Bayar: <?= date('d M Y', strtotime($ih['tanggal_tagihan'])) ?></span>
                                        </td>
                                        <td class="border border-slate-300 p-2.5 font-mono font-bold text-slate-800"><?= htmlspecialchars($ih['nomor_invoice']) ?></td>
                                        <td class="border border-slate-300 p-2.5 text-center"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-black rounded text-[9px] border border-emerald-300">PAID</span></td>
                                        <td class="border border-slate-300 p-2.5 text-right font-bold text-slate-900 font-mono">Rp <?= number_format($ih['jumlah_tagihan'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="bg-emerald-50/90 font-bold border-t-2 border-emerald-800">
                                    <td colspan="3" class="border border-slate-300 p-2.5 text-right uppercase text-[10.5px] text-emerald-950 font-black tracking-wide">TOTAL REALISASI PELUNASAN (100%)</td>
                                    <td class="border border-slate-300 p-2.5 text-right text-emerald-900 font-mono text-[12px] font-black">Rp <?= number_format($totalPaidReal, 0, ',', '.') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                <?php else: ?>
                    <!-- TABEL INVOICE STANDAR PER TERMIN -->
                    <div class="font-sans mb-4">
                        <table class="w-full text-left text-[10.5px] border-collapse border border-slate-300 shadow-xs rounded-lg overflow-hidden">
                            <thead>
                                <tr class="bg-emerald-900 text-white font-bold uppercase text-[9.5px] tracking-wider">
                                    <th class="border border-emerald-800 p-2.5">Deskripsi Tagihan Pekerjaan</th>
                                    <th class="border border-emerald-800 p-2.5 w-32 text-center">Termin</th>
                                    <th class="border border-emerald-800 p-2.5 text-right w-40">Jumlah (IDR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr class="bg-white">
                                    <td class="border border-slate-300 p-3 font-bold text-slate-900">
                                        Pembayaran Proyek "<?= htmlspecialchars($invoice['nama_projek']) ?>"
                                        <span class="block text-[10px] text-slate-600 font-semibold mt-0.5"><?= htmlspecialchars($invoice['keterangan_termin']) ?></span>
                                    </td>
                                    <td class="border border-slate-300 p-3 text-center font-bold text-emerald-900">Termin <?= $invoice['termin_ke'] ?></td>
                                    <td class="border border-slate-300 p-3 text-right font-mono font-bold text-slate-900 text-[11.5px]">
                                        Rp <?= number_format($invoice['jumlah_tagihan'], 0, ',', '.') ?>
                                    </td>
                                </tr>
                                <tr class="bg-emerald-50/90 font-bold border-t-2 border-emerald-800">
                                    <td colspan="2" class="border border-slate-300 p-2.5 text-right uppercase text-[10.5px] text-emerald-950 font-black tracking-wide">Total Tagihan (Nett)</td>
                                    <td class="border border-slate-300 p-2.5 text-right text-emerald-900 font-mono text-[12px] font-black">Rp <?= number_format($invoice['jumlah_tagihan'], 0, ',', '.') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Terbilang Box -->
                <div class="p-3 bg-slate-50 border border-slate-300 rounded-lg text-[11px] italic text-slate-800 mb-6 font-sans shadow-xs">
                    <strong>Terbilang:</strong> "<?= terbilang($isPelunasan ? $totalPaidReal : $invoice['jumlah_tagihan']) ?> Rupiah"
                </div>

                <!-- Footer Tanda Tangan & Bank Transfer (Mengalir Alami dengan Margin Pas) -->
                <div class="font-sans mt-4">
                    <div class="grid grid-cols-2 text-center gap-6 items-start">

                        <!-- Instruksi Pembayaran Transfer Bank -->
                        <div class="text-left text-[10px] pt-1">
                            <p class="font-bold text-slate-900 mb-1.5 uppercase tracking-wide text-[10.5px]">Instruksi Pembayaran Transfer Bank:</p>
                            <p class="text-slate-800">Bank : <strong>BCA (Bank Central Asia)</strong></p>
                            <p class="text-slate-800">No. Rekening : <strong class="font-mono text-emerald-900 text-[11px]">686-0460155</strong></p>
                            <p class="text-slate-800">Atas Nama : <strong>PT CIPTA TEKNOLOGI CENDEKIA</strong></p>
                        </div>

                        <!-- Sisi PT CTC: Clean TTD & Stempel Basah Transparan -->
                        <div class="flex flex-col justify-between h-36 relative text-center">
                            <div>
                                <p class="font-bold text-slate-900 text-[11px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                                <p class="text-emerald-900 font-bold text-[10px]">Finance &amp; Billing Department</p>
                            </div>

                            <!-- Area TTD + Stempel basah transparan -->
                            <div class="relative h-14 w-full flex items-center justify-center">
                                <img src="../../assets/images/Stempel.png"
                                     alt="Stempel PT CTC"
                                     id="stempelImg"
                                     class="stempel-img absolute w-24 h-24 object-contain left-[25%] top-[-12px] -rotate-12 opacity-60"
                                     crossorigin="anonymous"
                                     onerror="this.style.display='none'">
                            </div>

                            <div class="relative z-10">
                                <p class="font-bold underline text-slate-900 text-[11px]">Mohammad Habbas Ali, SE.</p>
                                <p class="text-[9.5px] text-slate-600 font-medium mt-0.5">Direktur / Managing Director</p>
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
        const imgs = Array.from(document.querySelectorAll('#invoiceContainer img'));
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
                orientation: 'portrait',
                compress: true
            });

            const pages = document.querySelectorAll('#invoiceContainer .page-a4');
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

            <?php if ($isPelunasan): ?>
                pdf.save('Kuitansi_Pelunasan_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['nama_projek']) ?>.pdf');
            <?php else: ?>
                pdf.save('Invoice_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $invoice['nomor_invoice']) ?>.pdf');
            <?php endif; ?>
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