<?php
require_once __DIR__ . '/../../config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: index.php");
    exit;
}

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
    <title>Invoice - <?= htmlspecialchars($invoice['nomor_invoice']) ?></title>
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
            <i class="fa-solid fa-print"></i> Cetak Invoice / Save PDF
        </button>
    </div>

    <!-- Invoice Sheet -->
    <div class="max-w-4xl mx-auto bg-white p-12 rounded-lg shadow-xl border border-slate-200 print-container">
        
        <!-- Header & Kop -->
        <div class="flex justify-between items-start border-b-2 border-emerald-800 pb-6 mb-8">
            <div>
                <h1 class="text-2xl font-bold tracking-wider text-emerald-950 uppercase">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                <p class="text-xs text-slate-500 font-semibold">IT Consulting & Software Engineering</p>
                <p class="text-xs text-slate-400">Gedung CTC Tower Lt. 8, Jl. Jend. Sudirman, Jakarta</p>
            </div>
            <div class="text-right">
                <h2 class="text-3xl font-extrabold text-slate-900 tracking-wider">INVOICE</h2>
                <p class="text-xs font-mono font-bold text-emerald-700 mt-1"><?= htmlspecialchars($invoice['nomor_invoice']) ?></p>
                <span class="inline-block px-3 py-1 mt-2 text-xs font-bold rounded-full uppercase <?= $invoice['status'] === 'Paid' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' ?>">
                    Status: <?= $invoice['status'] ?>
                </span>
            </div>
        </div>

        <!-- Meta Info -->
        <div class="grid grid-cols-2 gap-8 mb-8 text-xs">
            <div>
                <p class="font-bold uppercase text-slate-400 mb-1">Ditagihkan Kepada:</p>
                <h3 class="text-base font-bold text-slate-900"><?= htmlspecialchars($invoice['klien']) ?></h3>
                <p class="text-slate-600">Proyek Target: <strong><?= htmlspecialchars($invoice['nama_projek']) ?></strong></p>
            </div>
            <div class="text-right space-y-1">
                <p><strong class="text-slate-500">Tanggal Tagihan:</strong> <?= date('d F Y', strtotime($invoice['tanggal_tagihan'])) ?></p>
                <p><strong class="text-slate-500">Tanggal Jatuh Tempo:</strong> <span class="text-red-600 font-bold"><?= date('d F Y', strtotime($invoice['jatuh_tempo'])) ?></span></p>
            </div>
        </div>

        <!-- Tabel Item Tagihan -->
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

        <!-- Terbilang Box -->
        <div class="p-4 bg-slate-50 border border-slate-200 rounded-lg text-xs italic text-slate-700 mb-8">
            <strong>Terbilang:</strong> "<?= terbilang($invoice['jumlah_tagihan']) ?> Rupiah"
        </div>

        <!-- Instruksi Transfer & Tanda Tangan -->
        <div class="grid grid-cols-2 gap-8 text-xs pt-4 border-t border-slate-200">
            <div class="bg-emerald-50/60 p-4 rounded-lg border border-emerald-100">
                <p class="font-bold text-emerald-950 mb-2 uppercase">Instruksi Pembayaran Transfer Bank:</p>
                <p class="text-slate-700"><strong>Bank Mandiri</strong> KCP Sudirman</p>
                <p class="text-slate-700">No. Rekening: <strong class="font-mono text-emerald-800">122-00-0988771-0</strong></p>
                <p class="text-slate-700">Atas Nama: <strong>PT CIPTA TEKNOLOGI CENDEKIA</strong></p>
            </div>

            <div class="text-center">
                <p class="font-bold">PT. CIPTA TEKNOLOGI CENDEKIA</p>
                <div class="h-20 flex items-center justify-center text-slate-300 italic">[Stempel & Tanda Tangan]</div>
                <p class="font-bold underline text-slate-900">Finance & Billing Department</p>
            </div>
        </div>

    </div>
</body>
</html>