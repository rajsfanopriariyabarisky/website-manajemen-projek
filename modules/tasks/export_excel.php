<?php
require_once __DIR__ . '/../../config/database.php';

$project_id = $_GET['project_id'] ?? null;
if (!$project_id) {
    exit('Project ID tidak ditemukan.');
}

// Fetch Data Proyek & Kontrak
$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(c.durasi_bulan, 3) AS durasi_bulan
    FROM projects p
    LEFT JOIN contracts c ON p.id = c.project_id
    WHERE p.id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    exit('Data proyek tidak ditemukan.');
}

$durasiBulan = max(1, (int)$project['durasi_bulan']);
$totalMinggu = $durasiBulan * 4;

// Set Header Download File Excel (.xls)
$filename = "Gantt_Chart_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $project['nama_projek']) . "_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

$phases = [
    ["fase" => "Fase 1: Inisiasi & UI/UX Design", "deskripsi" => "Analisis kebutuhan, PM-AAS, desain UI/UX, dan arsitektur database.", "weeks" => [1, 2]],
    ["fase" => "Fase 2: Core Development & API", "deskripsi" => "Development Aplikasi, Core Logic & Integrasi API Sistem.", "weeks" => range(3, max(3, (int)floor($totalMinggu * 0.35) + 2))],
    ["fase" => "Fase 3: Algoritma & Modul Spesifik", "deskripsi" => "Implementasi Modul Spesifik, Algoritma, dan Fitur Khusus.", "weeks" => range((int)floor($totalMinggu * 0.5) + 1, (int)floor($totalMinggu * 0.75))],
    ["fase" => "Fase 4: Testing, QA & Bug Fixing", "deskripsi" => "Pengujian fungsi, integrasi Web Dashboard Admin, dan bug fixing.", "weeks" => [$totalMinggu - 1]],
    ["fase" => "Fase 5: UAT, Training & Go-Live", "deskripsi" => "UAT bersama Klien, Pelatihan, & Deployment Go-Live.", "weeks" => [$totalMinggu]]
];
?>
<table border="1">
    <thead>
        <tr>
            <th colspan="<?= $totalMinggu + 2 ?>" style="background-color: #0f172a; color: #ffffff; font-size: 14pt; font-weight: bold; text-align: center;">
                JADWAL PELAKSANAAN PROYEK: <?= strtoupper(htmlspecialchars($project['nama_projek'])) ?>
            </th>
        </tr>
        <tr>
            <th colspan="<?= $totalMinggu + 2 ?>" style="background-color: #f1f5f9; text-align: left;">
                Klien: <?= htmlspecialchars($project['klien']) ?> | Durasi: <?= $durasiBulan ?> Bulan (<?= $totalMinggu ?> Minggu) | Tanggal Cetak: <?= date('d M Y') ?>
            </th>
        </tr>
        <tr style="background-color: #1e293b; color: #ffffff; font-weight: bold;">
            <th style="width: 250px;">Tahapan Pekerjaan</th>
            <th style="width: 350px;">Deskripsi Output</th>
            <?php for ($m = 1; $m <= $totalMinggu; $m++): ?>
                <th style="width: 40px; text-align: center;">M<?= $m ?></th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($phases as $p): ?>
            <tr>
                <td><b><?= htmlspecialchars($p['fase']) ?></b></td>
                <td><?= htmlspecialchars($p['deskripsi']) ?></td>
                <?php for ($m = 1; $m <= $totalMinggu; $m++): ?>
                    <?php $isFilled = in_array($m, $p['weeks']); ?>
                    <td style="text-align: center; <?= $isFilled ? 'background-color: #0f172a; color: #ffffff;' : '' ?>">
                        <?= $isFilled ? 'X' : '' ?>
                    </td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>