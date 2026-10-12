<?php
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

// Fetch data detail kontrak
$stmt = $pdo->prepare("
    SELECT c.*, p.nama_projek, p.klien 
    FROM contracts c
    JOIN projects p ON c.project_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$contract = $stmt->fetch();

if (!$contract) {
    header("Location: index.php");
    exit;
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

$tglMulaiStamp = strtotime($contract['tanggal_mulai']);
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
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; color: #000 !important; }
            .print-container { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; }
            .page-break { page-break-before: always; }
        }
        body { font-family: 'Times New Roman', Times, serif; line-height: 1.6; }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen py-10 px-4">

    <!-- Action Bar (Browser Only) -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print font-sans">
        <a href="index.php" class="text-xs font-semibold text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Arsip
        </a>
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl flex items-center gap-2 transition text-sm shadow-md">
            <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
        </button>
    </div>

    <!-- Halaman Dokumen Perjanjian -->
    <div class="max-w-4xl mx-auto bg-white p-14 rounded-lg shadow-xl border border-slate-200 print-container text-justify">
        
        <!-- Kop Surat Perusahaan -->
        <div class="flex items-center justify-between border-b-4 border-double border-emerald-900 pb-5 mb-8">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-emerald-800 text-white flex items-center justify-center font-sans font-bold text-2xl rounded-lg border-2 border-emerald-600">
                    CTC
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-wider text-emerald-950 uppercase font-sans">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p class="text-xs font-sans text-slate-600">IT Consulting, Enterprise Software Development & Digital Transformation</p>
                    <p class="text-[11px] font-sans text-slate-500">Gedung CTC Tower, Lt. 8, Jl. Jend. Sudirman Kav. 52-53, Jakarta Selatan | Telp: (021) 555-8888 | www.ctc.co.id</p>
                </div>
            </div>
        </div>

        <!-- Judul Dokumen -->
        <div class="text-center mb-8">
            <h2 class="text-base font-bold underline tracking-wide uppercase">SURAT PERJANJIAN KERJA SAMA (SPK)</h2>
            <h3 class="text-sm font-bold tracking-wider uppercase mt-0.5">PENGEMBANGAN SISTEM & TEKNOLOGI INFORMASI</h3>
            <p class="text-xs font-mono font-semibold text-slate-700 mt-1">Nomor: <?= htmlspecialchars($contract['nomor_kontrak']) ?></p>
        </div>

        <!-- Pembukaan -->
        <p class="text-xs mb-4">
            Pada hari ini, <strong><?= $namaHari ?></strong>, tanggal <strong><?= terbilang($tglAngka) ?></strong> bulan <strong><?= $namaBulan ?></strong> tahun <strong><?= terbilang($tahunAngka) ?></strong> (<?= date('d-m-Y', $tglMulaiStamp) ?>), bertempat di Jakarta, yang bertanda tangan di bawah ini:
        </p>

        <!-- Para Pihak -->
        <div class="text-xs space-y-3 mb-6 pl-4 border-l-2 border-emerald-800 font-sans bg-slate-50/50 p-3 rounded">
            <div>
                <p class="font-bold text-slate-900">I. PT. CIPTA TEKNOLOGI CENDEKIA</p>
                <p class="text-slate-600">Berkedudukan di Jakarta Selatan, bertindak untuk dan atas nama perseroan sebagai Penyedia Layanan Teknologi Informasi, selanjutnya dalam Perjanjian ini disebut sebagai <strong>PIHAK PERTAMA</strong>.</p>
            </div>
            <div>
                <p class="font-bold text-slate-900">II. <?= strtoupper(htmlspecialchars($contract['klien'])) ?></p>
                <p class="text-slate-600">Berkedudukan sebagai badan usaha / instansi pemilik proyek, bertindak untuk dan atas nama penerima layanan, selanjutnya dalam Perjanjian ini disebut sebagai <strong>PIHAK KEDUA</strong>.</p>
            </div>
        </div>

        <p class="text-xs mb-6">
            PIHAK PERTAMA dan PIHAK KEDUA (secara bersama-sama disebut "<strong>PARA PIHAK</strong>") sepakat untuk mengikatkan diri dalam Perjanjian Kerja Sama Pekerjaan Proyek <strong>"<?= htmlspecialchars($contract['nama_projek']) ?>"</strong> dengan syarat dan ketentuan sebagai berikut:
        </p>

        <!-- Pasal-Pasal Perjanjian Enterprise -->
        <div class="space-y-4 text-xs">
            
            <!-- PASAL 1 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 1<br>RUANG LINGKUP PEKERJAAN (SCOPE OF WORK)</h4>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>PIHAK KEDUA memberikan pekerjaan kepada PIHAK PERTAMA dan PIHAK PERTAMA menerima penunjukan tersebut untuk melaksanakan pengerjaan proyek <strong><?= htmlspecialchars($contract['nama_projek']) ?></strong>.</li>
                    <li>Ruang lingkup pekerjaan mencakup Analisis Kebutuhan (*Requirement Analysis*), Perancangan Arsitektur & UI/UX, Pemrograman (*Software Development*), Pengujian Sistem (*System Testing & QA*), Implementasi Server, serta Pelatihan (*User Training*).</li>
                </ol>
            </div>

            <!-- PASAL 2 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 2<br>JANGKA WAKTU PELAKSANAAN</h4>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Pekerjaan sebagaimana dimaksud dalam Pasal 1 dilaksanakan dalam jangka waktu <strong><?= $contract['durasi_bulan'] ?> (<?= terbilang($contract['durasi_bulan']) ?>) Bulan</strong> kalender.</li>
                    <li>Jangka waktu pelaksanaan terhitung efektif mulai tanggal <strong><?= date('d', strtotime($contract['tanggal_mulai'])) ?> <?= $bulanIndo[(int)date('n', strtotime($contract['tanggal_mulai']))] ?> <?= date('Y', strtotime($contract['tanggal_mulai'])) ?></strong> sampai dengan tanggal <strong><?= date('d', strtotime($contract['tanggal_selesai'])) ?> <?= $bulanIndo[(int)date('n', strtotime($contract['tanggal_selesai']))] ?> <?= date('Y', strtotime($contract['tanggal_selesai'])) ?></strong>.</li>
                </ol>
            </div>

            <!-- PASAL 3 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 3<br>NILAI KONTRAK & SKEMA PEMBAYARAN</h4>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Total Nilai Perjanjian Pekerjaan ini adalah sebesar <strong>Rp <?= number_format($contract['nilai_kontrak'], 0, ',', '.') ?>,-</strong> (<em><?= terbilang($contract['nilai_kontrak']) ?> Rupiah</em>), belum termasuk Pajak Pertambahan Nilai (PPN 11%).</li>
                    <li>Pembayaran dilakukan secara bertahap melalui transfer bank ke rekening resmi PIHAK PERTAMA:
                        <ul class="list-disc pl-5 mt-1 font-mono text-[11px]">
                            <li>Bank / Rekening : Bank Mandiri KCP Sudirman / 122-00-0988771-0</li>
                            <li>Atas Nama : PT CIPTA TEKNOLOGI CENDEKIA</li>
                        </ul>
                    </li>
                    <li>Tahapan pembayaran (*Termin*) disepakati sebagai berikut:
                        <ul class="list-disc pl-5 mt-1">
                            <li><strong>Termin I (DP 50%):</strong> Sebesar Rp <?= number_format($contract['nilai_kontrak'] * 0.5, 0, ',', '.') ?>,- dibayarkan paling lambat 7 (tujuh) hari kerja setelah penandatanganan Perjanjian ini.</li>
                            <li><strong>Termin II (Pelunasan 50%):</strong> Sebesar Rp <?= number_format($contract['nilai_kontrak'] * 0.5, 0, ',', '.') ?>,- dibayarkan setelah seluruh pekerjaan selesai dan ditandatanganinya Berita Acara Serah Terima (BAST).</li>
                        </ul>
                    </li>
                </ol>
            </div>

            <!-- PASAL 4 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 4<br>HAK KEKAYAAN INTELEKTUAL & KERAHASIAAN (NDA)</h4>
                <ol class="list-decimal pl-5 space-y-1">
                    <li>Seluruh Hak Kekayaan Intelektual, *Source Code*, dan Hak Cipta atas sistem yang dikembangkan akan sepenuhnya menjadi milik PIHAK KEDUA setelah PIHAK KEDUA melunasi seluruh kewajiban pembayaran.</li>
                    <li>PARA PIHAK sepakat untuk menjaga kerahasiaan (*Non-Disclosure*) seluruh data, informasi bisnis, dan infrastruktur teknis yang diperoleh selama pelaksanaan kerja sama ini.</li>
                </ol>
            </div>

            <!-- PASAL 5 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 5<br>GARANSI, MAINTENANCE & SLA</h4>
                <p class="pl-2">
                    PIHAK PERTAMA memberikan garansi pemeliharaan sistem (*System Maintenance & Bug Fixing*) selama <strong>3 (tiga) bulan</strong> terhitung sejak tanggal Penandatanganan Berita Acara Serah Terima (BAST) tanpa dikenakan biaya tambahan.
                </p>
            </div>

            <!-- PASAL 6 -->
            <div>
                <h4 class="font-bold text-center uppercase tracking-wider mb-1">PASAL 6<br>PENYELESAIAN PERSELISIHAN</h4>
                <p class="pl-2">
                    Apabila terjadi perselisihan di kemudian hari terkait pelaksanaan Perjanjian ini, PARA PIHAK sepakat untuk menyelesaikannya secara musyawarah untuk mufakat. Apabila tidak tercapai mufakat, maka penyelesaian akan dilakukan melalui Pengadilan Negeri Jakarta Selatan.
                </p>
            </div>

        </div>

        <!-- Penutup -->
        <p class="text-xs mt-6 mb-8">
            Demikian Surat Perjanjian Kerja Sama ini dibuat dalam rangkap 2 (dua) bermeterai cukup dan mempunyai kekuatan hukum yang sama bagi masing-masing pihak setelah ditandatangani oleh wakil sah dari PARA PIHAK.
        </p>

        <!-- Area Tanda Tangan & Meterai -->
        <div class="grid grid-cols-2 text-center text-xs font-sans mt-10">
            <div>
                <p class="font-bold text-slate-900">PIHAK PERTAMA</p>
                <p class="text-slate-500 font-semibold">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                <div class="h-28 my-2 flex items-center justify-center">
                    <div class="border border-dashed border-slate-300 w-24 h-16 rounded flex items-center justify-center text-[9px] text-slate-400">
                        Meterai<br>Rp 10.000
                    </div>
                </div>
                <p class="font-bold text-slate-900 underline">Project Director / Manager</p>
                <p class="text-[10px] text-slate-500">PT. Cipta Teknologi Cendekia</p>
            </div>
            <div>
                <p class="font-bold text-slate-900">PIHAK KEDUA</p>
                <p class="text-slate-500 font-semibold"><?= strtoupper(htmlspecialchars($contract['klien'])) ?></p>
                <div class="h-28 my-2 flex items-center justify-center">
                    <div class="border border-dashed border-slate-300 w-24 h-16 rounded flex items-center justify-center text-[9px] text-slate-400">
                        Tanda Tangan & Stempel
                    </div>
                </div>
                <p class="font-bold text-slate-900 underline">Direktur / Perwakilan Resmi</p>
                <p class="text-[10px] text-slate-500"><?= htmlspecialchars($contract['klien']) ?></p>
            </div>
        </div>

    </div>
</body>
</html>