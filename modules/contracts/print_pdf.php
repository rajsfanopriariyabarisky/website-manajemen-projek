<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch data detail kontrak
$stmt = $pdo->prepare("
    SELECT c.*, 
           COALESCE(p.nama_projek, 'Kontrak Mandiri / Pengajuan') as nama_projek, 
           COALESCE(p.klien, '-') as klien,
           p.requirement_id
    FROM contracts c
    LEFT JOIN projects p ON c.project_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    header("Location: index.php");
    exit;
}

// ACTION: KIRIM KONTRAK KE CLIENT PORTAL & CHAT LOBBY
if (isset($_GET['action']) && $_GET['action'] === 'send_contract') {
    $reqId = $contract['requirement_id'] ?? null;
    
    if (!$reqId && !empty($contract['project_id'])) {
        $stmtFindReq = $pdo->prepare("SELECT requirement_id FROM projects WHERE id = ?");
        $stmtFindReq->execute([$contract['project_id']]);
        $reqId = $stmtFindReq->fetchColumn();
    }
    
    if (!$reqId) {
        $stmtFindReqByTitle = $pdo->prepare("SELECT id FROM client_requirements WHERE LOWER(title) LIKE LOWER(?) OR LOWER(client_name) LIKE LOWER(?) ORDER BY id DESC LIMIT 1");
        $stmtFindReqByTitle->execute(['%' . $contract['nama_projek'] . '%', '%' . $contract['klien'] . '%']);
        $reqId = $stmtFindReqByTitle->fetchColumn();
    }

    if ($reqId) {
        $msgContent = "[SYSTEM] Surat Perjanjian Kerja Sama (SPK) Resmi dengan Nomor Kontrak: [ " . $contract['nomor_kontrak'] . " ] telah diterbitkan oleh Tim PT. Cipta Teknologi Cendekia.\n\nDokumen Perjanjian Kerja resmi ini dapat Anda tinjau & unduh secara langsung kapan saja melalui tombol Kontrak SPK (PDF) di portal.";
        
        $stmtInsertChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, client_read, created_at) VALUES (?, 'system', 'System CTC', ?, 0, NOW())");
        $stmtInsertChat->execute([$reqId, $msgContent]);

        header("Location: print_pdf.php?id=" . $contract['id'] . "&msg=contract_sent");
        exit;
    } else {
        header("Location: print_pdf.php?id=" . $contract['id'] . "&msg=req_not_found");
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

$hariIndo = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];
$bulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

$tglMulaiStamp = !empty($contract['tanggal_mulai']) ? strtotime($contract['tanggal_mulai']) : strtotime($contract['created_at']);
$namaHari = $hariIndo[date('l', $tglMulaiStamp)];
$tglAngka = date('j', $tglMulaiStamp);
$namaBulan = $bulanIndo[(int)date('n', $tglMulaiStamp)];
$tahunAngka = date('Y', $tglMulaiStamp);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Perjanjian Kerja Sama - <?= htmlspecialchars($contract['nomor_kontrak']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- jsPDF + html2canvas CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
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

        #contractContainer {
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
            #contractContainer {
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
            <i class="fa-solid fa-arrow-left"></i> Kembali ke Arsip Kontrak
        </a>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'contract_sent'): ?>
            <div class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-check"></i> Dokumen Kontrak (SPK) berhasil dikirim ke Client Portal!
            </div>
        <?php endif; ?>

        <div class="flex items-center gap-3">
            <a href="print_pdf.php?id=<?= $contract['id'] ?>&action=send_contract" onclick="return confirm('Kirimkan dokumen Surat Perjanjian Kerja (SPK) resmi ini ke Client Portal?')" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 transition text-xs shadow">
                <i class="fa-solid fa-paper-plane"></i> Kirim Kontrak ke Klien Portal
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
    <div id="contractContainer">

        <!-- HALAMAN 1 -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc.png"
                 alt="Kop Surat Utama"
                 class="kop-background"
                 crossorigin="anonymous">

            <div class="doc-font relative z-10 pt-[50mm] pb-[18mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11px] text-black">
                <div>
                    <div class="text-center mb-5">
                        <h2 class="text-[13px] font-bold underline tracking-wide uppercase">SURAT PERJANJIAN KERJA SAMA (SPK)</h2>
                        <h3 class="text-[11px] font-bold tracking-wider uppercase mt-0.5">PENGEMBANGAN SISTEM &amp; TEKNOLOGI INFORMASI</h3>
                        <p class="text-[10px] font-mono font-bold text-slate-800 mt-0.5">Nomor: <?= htmlspecialchars($contract['nomor_kontrak']) ?></p>
                    </div>

                    <p class="mb-3 leading-relaxed text-justify">
                        Pada hari ini, <strong><?= $namaHari ?></strong>, tanggal <strong><?= terbilang($tglAngka) ?></strong> bulan <strong><?= $namaBulan ?></strong> tahun <strong><?= terbilang($tahunAngka) ?></strong> (<?= date('d-m-Y', $tglMulaiStamp) ?>), bertempat di Jakarta, kami yang bertanda tangan di bawah ini:
                    </p>

                    <div class="space-y-2 mb-4 pl-3 border-l-4 border-emerald-800 bg-slate-50/90 p-3 rounded-r-lg font-sans text-[10.5px]">
                        <div>
                            <p class="font-bold text-slate-900">I. PT. CIPTA TEKNOLOGI CENDEKIA</p>
                            <p class="text-slate-700 leading-snug">Berkedudukan di Jakarta, bertindak untuk dan atas nama perseroan sebagai Penyedia Layanan Teknologi Informasi, selanjutnya disebut <strong>PIHAK PERTAMA</strong>.</p>
                        </div>
                        <div>
                            <p class="font-bold text-slate-900">II. <?= strtoupper(htmlspecialchars($contract['klien'])) ?></p>
                            <p class="text-slate-700 leading-snug">Berkedudukan sebagai pemilik proyek, bertindak untuk dan atas nama penerima layanan, selanjutnya disebut <strong>PIHAK KEDUA</strong>.</p>
                        </div>
                    </div>

                    <p class="mb-4 leading-relaxed text-justify">
                        PIHAK PERTAMA dan PIHAK KEDUA (secara bersama-sama disebut "<strong>PARA PIHAK</strong>") sepakat mengikatkan diri dalam Perjanjian Kerja Sama Pekerjaan Proyek <strong>"<?= htmlspecialchars($contract['nama_projek']) ?>"</strong> dengan ketentuan sebagai berikut:
                    </p>

                    <div class="space-y-3">
                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 1<br>RUANG LINGKUP PEKERJAAN (SCOPE OF WORK)</h4>
                            <ol class="list-decimal pl-5 space-y-1 text-justify leading-relaxed">
                                <li>PIHAK KEDUA memberikan pekerjaan kepada PIHAK PERTAMA dan PIHAK PERTAMA menerima penunjukan tersebut untuk melaksanakan pengerjaan proyek <strong><?= htmlspecialchars($contract['nama_projek']) ?></strong>.</li>
                                <li>Ruang lingkup pekerjaan mencakup Analisis Kebutuhan (<em>Requirement Analysis</em>), Perancangan Arsitektur &amp; UI/UX, Pemrograman (<em>Software Development</em>), Pengujian Sistem (<em>QA &amp; Testing</em>), Deploy Server, serta Pelatihan (<em>User Training</em>).</li>
                            </ol>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 2<br>JANGKA WAKTU PELAKSANAAN</h4>
                            <ol class="list-decimal pl-5 space-y-1 text-justify leading-relaxed">
                                <li>Pekerjaan sebagaimana dimaksud dalam Pasal 1 dilaksanakan dalam jangka waktu <strong><?= $contract['durasi_bulan'] ?> (<?= terbilang($contract['durasi_bulan']) ?>) Bulan</strong> kalender.</li>
                                <li>Jangka waktu terhitung efektif mulai tanggal <strong><?= !empty($contract['tanggal_mulai']) ? date('d', strtotime($contract['tanggal_mulai'])) . ' ' . $bulanIndo[(int)date('n', strtotime($contract['tanggal_mulai']))] . ' ' . date('Y', strtotime($contract['tanggal_mulai'])) : '-' ?></strong> sampai dengan tanggal <strong><?= !empty($contract['tanggal_selesai']) ? date('d', strtotime($contract['tanggal_selesai'])) . ' ' . $bulanIndo[(int)date('n', strtotime($contract['tanggal_selesai']))] . ' ' . date('Y', strtotime($contract['tanggal_selesai'])) : '-' ?></strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 3<br>NILAI KONTRAK &amp; SKEMA PEMBAYARAN</h4>
                            <ol class="list-decimal pl-5 space-y-1 text-justify leading-relaxed">
                                <li>Total Nilai Perjanjian Pekerjaan ini adalah sebesar <strong>Rp <?= number_format($contract['nilai_kontrak'], 0, ',', '.') ?>,-</strong> (<em><?= terbilang($contract['nilai_kontrak']) ?> Rupiah</em>), belum termasuk Pajak Pertambahan Nilai (PPN 11%).</li>
                                <li>Pembayaran dilakukan secara bertahap melalui transfer bank ke rekening resmi PIHAK PERTAMA:
                                    <div class="mt-1 font-mono text-[10px] bg-slate-50 p-1.5 rounded border border-slate-200">
                                        Bank / Rekening : BCA / 686-0460155 &nbsp;|&nbsp; A.N : PT CIPTA TEKNOLOGI CENDEKIA
                                    </div>
                                </li>
                                <li>Tahapan pembayaran (<em>Termin</em>) disepakati sebagai berikut:
                                    <ul class="list-disc pl-5 mt-0.5">
                                        <li><strong>Termin I (DP 50%):</strong> Sebesar Rp <?= number_format($contract['nilai_kontrak'] * 0.5, 0, ',', '.') ?>,- dibayarkan paling lambat 7 (tujuh) hari kerja setelah penandatanganan SPK.</li>
                                        <li><strong>Termin II (Pelunasan 50%):</strong> Sebesar Rp <?= number_format($contract['nilai_kontrak'] * 0.5, 0, ',', '.') ?>,- dibayarkan setelah seluruh pekerjaan selesai dan ditandatanganinya Berita Acara Serah Terima (BAST).</li>
                                    </ul>
                                </li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HALAMAN 2 -->
        <div class="page-a4">
            <img src="../../assets/images/kopsurat-ctc-2.png"
                 alt="Kop Surat Halaman 2"
                 class="kop-background"
                 crossorigin="anonymous">

            <div class="doc-font relative z-10 pt-[28mm] pb-[18mm] px-[20mm] h-full flex flex-col justify-between text-justify text-[11px] text-black">
                <div>
                    <div class="space-y-3 mb-4">
                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 4<br>PERUBAHAN LINGKUP PEKERJAAN (CHANGE REQUEST)</h4>
                            <p class="leading-relaxed text-justify">
                                Apabila PIHAK KEDUA mengajukan perubahan atau penambahan fitur di luar dokumen Spesifikasi Kebutuhan Sistem (SRS) yang telah disepakati, maka hal tersebut wajib dituangkan dalam Adendum Kontrak tersendiri dan dapat dikenakan biaya tambahan (<em>Change Request Fee</em>).
                            </p>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 5<br>HAK KEKAYAAN INTELEKTUAL &amp; KERAHASIAAN (NDA)</h4>
                            <ol class="list-decimal pl-5 space-y-1 text-justify leading-relaxed">
                                <li>Seluruh Hak Kekayaan Intelektual, <em>Source Code</em>, dan Hak Cipta atas sistem yang dikembangkan sepenuhnya menjadi milik PIHAK KEDUA setelah pelunasan seluruh kewajiban pembayaran.</li>
                                <li>PARA PIHAK sepakat untuk menjaga kerahasiaan (<em>Non-Disclosure</em>) seluruh data dan informasi bisnis yang diperoleh selama pelaksanaan kerja sama ini.</li>
                            </ol>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 6<br>GARANSI, MAINTENANCE &amp; SLA</h4>
                            <p class="leading-relaxed text-justify">
                                PIHAK PERTAMA memberikan garansi pemeliharaan sistem (<em>System Maintenance &amp; Bug Fixing</em>) selama <strong>3 (tiga) bulan</strong> terhitung sejak tanggal Penandatanganan Berita Acara Serah Terima (BAST) tanpa dikenakan biaya tambahan.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 7<br>KEADAAN MEMAKSA (FORCE MAJEURE)</h4>
                            <p class="leading-relaxed text-justify">
                                PARA PIHAK dibebaskan dari tanggung jawab atas keterlambatan atau kegagalan memenuhi kewajiban yang disebabkan oleh keadaan di luar kendali yang wajar (<em>Force Majeure</em>), seperti bencana alam, wabah, huru-hara, serta kebijakan pemerintah di bidang moneter/teknologi.
                            </p>
                        </div>

                        <div>
                            <h4 class="font-bold text-center uppercase tracking-wide mb-1 text-[11px]">PASAL 8<br>PENYELESAIAN PERSELISIHAN</h4>
                            <p class="leading-relaxed text-justify">
                                Apabila terjadi perselisihan terkait pelaksanaan Perjanjian ini, PARA PIHAK sepakat menyelesaikannya secara musyawarah untuk mufakat. Apabila musyawarah tidak mencapai mufakat, penyelesaian akan dilakukan melalui Pengadilan Negeri Jakarta Selatan.
                            </p>
                        </div>
                    </div>

                    <p class="mb-5 leading-relaxed text-justify">
                        Demikian Surat Perjanjian Kerja Sama ini dibuat dalam rangkap 2 (dua) bermeterai cukup dan mempunyai kekuatan hukum yang sama bagi masing-masing pihak setelah ditandatangani oleh wakil sah dari PARA PIHAK.
                    </p>

                    <div class="font-sans">
                        <div class="grid grid-cols-2 text-center gap-6">
                            <div class="flex flex-col justify-between h-44 border border-dashed border-slate-300 p-3.5 rounded-xl bg-white/80">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11px]">PIHAK KEDUA</p>
                                    <p class="text-slate-600 font-semibold text-[10px]"><?= htmlspecialchars($contract['klien']) ?></p>
                                </div>
                                <div class="h-16 flex items-center justify-center italic text-slate-400 text-[9.5px]">
                                    [ Tanda Tangan &amp; Stempel Klien ]
                                </div>
                                <div>
                                    <p class="font-bold underline text-slate-900 text-[10.5px]">( .................................................... )</p>
                                    <p class="text-[9.5px] text-slate-500 font-medium mt-0.5">Direktur / Perwakilan Resmi</p>
                                </div>
                            </div>

                            <div class="flex flex-col justify-between h-44 border border-emerald-200 bg-emerald-50/40 p-3.5 rounded-xl relative">
                                <div>
                                    <p class="font-bold text-slate-900 text-[11px]">PIHAK PERTAMA</p>
                                    <p class="text-emerald-900 font-bold text-[10.5px]">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                                </div>

                                <div class="relative h-16 w-full flex items-center justify-center">
                                    <img src="../../assets/images/Stempel.png"
                                         alt="Stempel PT CTC"
                                         id="stempelImg"
                                         class="stempel-img absolute w-24 h-24 object-contain left-[22%] top-[-10px] -rotate-12 opacity-60"
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

    </div>

    <script>
    function waitForImages() {
        const imgs = Array.from(document.querySelectorAll('#contractContainer img'));
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

            const pages = document.querySelectorAll('#contractContainer .page-a4');
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

            pdf.save('SPK_<?= preg_replace('/[^a-zA-Z0-9_-]/', '_', $contract['nomor_kontrak']) ?>.pdf');
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