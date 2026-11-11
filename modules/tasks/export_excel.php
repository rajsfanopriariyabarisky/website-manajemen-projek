<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;
if (!$project_id) {
    exit('Project ID tidak ditemukan.');
}

// Fetch Data Proyek & Kontrak Terkait
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
    exit('Data proyek tidak ditemukan.');
}

$durasiBulan = max(1, (int)$project['durasi_bulan']);
$totalMinggu = $durasiBulan * 4;

// ALGORITMA DYNAMICAL PROPORTIONAL TIMELINE ALLOCATION
function getPhaseWeeksExcel($durasiBulan) {
    $totalWeeks = max(1, $durasiBulan * 4);
    
    if ($totalWeeks == 4) {
        return [
            1 => [1],
            2 => [2],
            3 => [3],
            4 => [3, 4],
            5 => [4]
        ];
    }

    $f1_end = max(1, (int)round($totalWeeks * 0.15));
    $f2_end = max($f1_end + 1, (int)round($totalWeeks * 0.50));
    $f3_end = max($f2_end + 1, (int)round($totalWeeks * 0.75));
    $f4_end = max($f3_end + 1, $totalWeeks - 1);
    $f5_start = min($totalWeeks, $f4_end);

    return [
        1 => range(1, $f1_end),
        2 => range($f1_end + 1, $f2_end),
        3 => range($f2_end + 1, $f3_end),
        4 => range($f3_end + 1, $f4_end),
        5 => range($f5_start, $totalWeeks)
    ];
}

$phaseWeeksMap = getPhaseWeeksExcel($durasiBulan);

$phases = [
    ["fase" => "Fase 1: Inisiasi & UI/UX Design", "deskripsi" => "Analisis kebutuhan, PM-AAS, desain UI/UX, dan arsitektur database.", "weeks" => $phaseWeeksMap[1]],
    ["fase" => "Fase 2: Core Development & API Integration", "deskripsi" => "Development Aplikasi, Core Logic & Integrasi API Sistem.", "weeks" => $phaseWeeksMap[2]],
    ["fase" => "Fase 3: Algoritma & Modul Spesifik", "deskripsi" => "Implementasi Modul Spesifik, Algoritma, dan Fitur Khusus.", "weeks" => $phaseWeeksMap[3]],
    ["fase" => "Fase 4: Testing, QA & Bug Fixing", "deskripsi" => "Pengujian fungsi, integrasi Web Dashboard Admin, dan bug fixing.", "weeks" => $phaseWeeksMap[4]],
    ["fase" => "Fase 5: UAT, Training & Deployment Go-Live", "deskripsi" => "UAT bersama Klien, Pelatihan, & Deployment Go-Live.", "weeks" => $phaseWeeksMap[5]]
];

// Set Header Download File Excel (.xls)
$filename = "Gantt_Chart_" . preg_replace('/[^a-zA-Z0-9_]/', '_', $project['nama_projek']) . "_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<table border="1" style="border-collapse: collapse; font-family: Arial, sans-serif; font-size: 10pt;">
    <thead>
        <tr>
            <th colspan="<?= $totalMinggu + 2 ?>" style="background-color: #064e3b; color: #ffffff; font-size: 14pt; font-weight: bold; text-align: center; padding: 10px;">
                JADWAL PELAKSANAAN PROYEK: <?= strtoupper(htmlspecialchars($project['nama_projek'])) ?>
            </th>
        </tr>
        <tr>
            <th colspan="<?= $totalMinggu + 2 ?>" style="background-color: #f1f5f9; color: #334155; text-align: left; padding: 6px; font-size: 9pt;">
                <b>Klien:</b> <?= htmlspecialchars($project['klien']) ?> | 
                <b>Durasi:</b> <?= $durasiBulan ?> Bulan (<?= $totalMinggu ?> Minggu) | 
                <b>Ref SPK:</b> <?= htmlspecialchars($project['nomor_kontrak'] ?? '-') ?> | 
                <b>Tanggal Cetak:</b> <?= date('d M Y') ?>
            </th>
        </tr>
        <tr style="background-color: #0f172a; color: #ffffff; font-weight: bold;">
            <th style="width: 260px; padding: 8px; text-align: left;">Tahapan Pekerjaan</th>
            <th style="width: 380px; padding: 8px; text-align: left;">Deskripsi Singkat Output</th>
            <?php for ($m = 1; $m <= $totalMinggu; $m++): ?>
                <th style="width: 38px; text-align: center; background-color: #1e293b; color: #ffffff;">M<?= $m ?></th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($phases as $p): ?>
            <tr>
                <td style="padding: 6px; font-weight: bold; color: #0f172a; border: 1px solid #cbd5e1;">
                    <?= htmlspecialchars($p['fase']) ?>
                </td>
                <td style="padding: 6px; color: #334155; font-size: 9pt; border: 1px solid #cbd5e1;">
                    <?= htmlspecialchars($p['deskripsi']) ?>
                </td>
                <?php for ($m = 1; $m <= $totalMinggu; $m++): ?>
                    <?php $isFilled = in_array($m, $p['weeks']); ?>
                    <td style="text-align: center; vertical-align: middle; border: 1px solid #cbd5e1; <?= $isFilled ? 'background-color: #047857; color: #ffffff; font-weight: bold;' : '' ?>">
                        <?= $isFilled ? 'X' : '' ?>
                    </td>
                <?php endfor; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>