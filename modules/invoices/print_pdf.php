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
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; padding: 0 !important; }
            .print-container { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; padding: 0 !important; }
        }
        body { font-family: 'Segoe UI', Arial, sans-serif; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen py-8 px-4">

    <!-- Action Bar -->
    <div class="max-w-4xl mx-auto mb-6 flex justify-between items-center no-print">
        <a href="index.php" class="text-xs font-semibold text-emerald-700 hover:underline">
            <i class="fa-solid fa-arrow-left mr-1"></i> Kembali ke Billing
        </a>
        <button onclick="window.print()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-5 py-2.5 rounded-xl flex items-center gap-2 transition text-sm shadow-md">
            <i class="fa-solid fa-print"></i> Cetak Dokumen / Save PDF
        </button>
    </div>

    <!-- Sheet PDF -->
    <div class="max-w-4xl mx-auto bg-white p-12 rounded-lg shadow-xl border border-slate-200 print-container">
        
        <!-- Header & Kop -->
        <div class="flex justify-between items-start border-b-2 border-emerald-800 pb-6 mb-8">
            <div>
                <h1 class="text-2xl font-bold tracking-wider text-emerald-950 uppercase">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                <p class="text-xs text-slate-500 font-semibold">Enterprise IT Solution & Software Engineering</p>
                <p class="text-xs text-slate-400">Perumahan Cahaya Garuda Residence Kav. 31, Bojongsari, Kota Depok, Jawa Barat | Website: https://ctc-nusantara.com/</p>
                <p class="text-[11px] font-sans text-slate-500">Email: ciptateknologicendekia@gmail.com</p>
            </div>
            <div class="text-right">
                <h2 class="text-2xl font-extrabold text-slate-900 tracking-wider uppercase">
                    <?= $isPelunasan ? 'KUITANSI PELUNASAN BAST' : 'INVOICE' ?>
                </h2>
                <p class="text-xs font-mono font-bold text-emerald-700 mt-1">
                    <?= $isPelunasan ? 'KW-CTC/' . date('Y/m') . '/' . sprintf("%03d", $project['id']) : htmlspecialchars($invoice['nomor_invoice']) ?>
                </p>
                <span class="inline-block px-3 py-1 mt-2 text-xs font-bold rounded-full uppercase <?= $isPelunasan ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : ($invoice['status'] === 'Paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
                    STATUS: <?= $isPelunasan ? 'LUNAS 100% (VERIFIED)' : $invoice['status'] ?>
                </span>
            </div>
        </div>

        <!-- Meta Info -->
        <div class="grid grid-cols-2 gap-8 mb-8 text-xs">
            <div>
                <p class="font-bold uppercase text-slate-400 mb-1">Diberikan Kepada / Klien:</p>
                <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($isPelunasan ? $project['klien'] : $invoice['klien']) ?></h3>
                <p class="text-slate-600 mt-1">Proyek Target: <strong><?= htmlspecialchars($isPelunasan ? $project['nama_projek'] : $invoice['nama_projek']) ?></strong></p>
            </div>
            <div class="text-right space-y-1">
                <p><strong class="text-slate-500">Nilai Total Kontrak:</strong> Rp <?= number_format($isPelunasan ? $project['budget'] : $invoice['budget'], 0, ',', '.') ?></p>
                <p><strong class="text-slate-500">Tanggal Terbit:</strong> <?= date('d F Y') ?></p>
            </div>
        </div>

        <?php if ($isPelunasan): ?>
            <!-- KHUSUS KUITANSI PELUNASAN GABUNGAN TERMIN 1 & TERMIN 2 -->
            <div class="bg-emerald-50/80 border-2 border-emerald-600 p-5 rounded-2xl mb-6">
                <h3 class="text-xs font-bold text-emerald-950 uppercase mb-1 border-b border-emerald-200 pb-1">
                    SURAT KETERANGAN PELUNASAN RESMI (BAST)
                </h3>
                <p class="text-xs text-slate-700 leading-relaxed">
                    Dengan ini PT. Cipta Teknologi Cendekia menyatakan bahwa seluruh kewajiban pembayaran termin penagihan atas proyek <strong>"<?= htmlspecialchars($project['nama_projek']) ?>"</strong> telah <strong>LUNAS 100%</strong> diterima oleh kas perusahaan.
                </p>
            </div>

            <table class="w-full text-left text-xs mb-8 border border-slate-200">
                <thead>
                    <tr class="bg-emerald-900 text-white font-bold uppercase">
                        <th class="p-3">Rincian Pembayaran Termin</th>
                        <th class="p-3">No. Invoice Ref</th>
                        <th class="p-3 text-center">Status</th>
                        <th class="p-3 text-right">Nominal Terbayar</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php foreach ($invoiceHistory as $ih): ?>
                        <tr>
                            <td class="p-3 font-semibold text-slate-800">
                                Termin <?= $ih['termin_ke'] ?> - <?= htmlspecialchars($ih['keterangan_termin']) ?>
                                <span class="block text-[10px] text-slate-400 font-normal">Tgl Bayar: <?= date('d M Y', strtotime($ih['tanggal_tagihan'])) ?></span>
                            </td>
                            <td class="p-3 font-mono font-bold text-slate-700"><?= htmlspecialchars($ih['nomor_invoice']) ?></td>
                            <td class="p-3 text-center"><span class="px-2 py-0.5 bg-emerald-100 text-emerald-800 font-bold rounded text-[10px]">PAID</span></td>
                            <td class="p-3 text-right font-bold text-slate-900 font-mono">Rp <?= number_format($ih['jumlah_tagihan'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bg-emerald-50 font-bold text-sm">
                        <td colspan="3" class="p-3 text-right uppercase text-emerald-950">TOTAL REALISASI PELUNASAN (100%)</td>
                        <td class="p-3 text-right text-emerald-700 font-mono text-base">Rp <?= number_format($totalPaidReal, 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>

        <?php else: ?>
            <!-- TABEL INVOICE STANDAR PER TERMIN -->
            <table class="w-full text-left text-xs mb-8 border border-slate-200">
                <thead>
                    <tr class="bg-emerald-900 text-white font-bold uppercase">
                        <th class="p-3">Deskripsi Tagihan</th>
                        <th class="p-3">Termin</th>
                        <th class="p-3 text-right">Jumlah (IDR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr>
                        <td class="p-3 font-semibold text-slate-800">
                            Pembayaran Proyek "<?= htmlspecialchars($invoice['nama_projek']) ?>"
                            <span class="block text-[11px] text-slate-500 font-normal"><?= htmlspecialchars($invoice['keterangan_termin']) ?></span>
                        </td>
                        <td class="p-3 font-bold text-emerald-800">Termin <?= $invoice['termin_ke'] ?></td>
                        <td class="p-3 text-right font-bold text-slate-900 font-mono text-sm">
                            Rp <?= number_format($invoice['jumlah_tagihan'], 0, ',', '.') ?>
                        </td>
                    </tr>
                    <tr class="bg-slate-50 font-bold text-sm">
                        <td colspan="2" class="p-3 text-right uppercase">Total Tagihan (Nett)</td>
                        <td class="p-3 text-right text-emerald-800 font-mono">Rp <?= number_format($invoice['jumlah_tagihan'], 0, ',', '.') ?></td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Terbilang Box -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg text-xs italic text-slate-700 mb-8">
            <strong>Terbilang:</strong> "<?= terbilang($isPelunasan ? $totalPaidReal : $invoice['jumlah_tagihan']) ?> Rupiah"
        </div>

        <!-- Footer Tanda Tangan -->
        <div class="grid grid-cols-2 gap-8 text-xs pt-4 border-t border-slate-200">
            <div class="bg-emerald-50/60 p-4 rounded-lg border border-emerald-100">
                <p class="font-bold text-emerald-950 mb-2 uppercase">Instruksi Pembayaran Transfer Bank:</p>
                <p class="text-slate-700"><strong>Bank BCA</strong> </p>
                <p class="text-slate-700">No. Rekening: <strong class="font-mono text-emerald-800">686-0460155</strong></p>
                <p class="text-slate-700">Atas Nama: <strong>PT CIPTA TEKNOLOGI CENDEKIA</strong></p>
            </div>

            <div class="text-center">
                <p class="font-bold">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                <div class="h-20 flex items-center justify-center text-slate-300 italic">[Stempel Resmi & Tanda Tangan]</div>
                <p class="font-bold underline text-slate-900">Finance & Billing Department</p>
            </div>
        </div>

    </div>
</body>
</html>