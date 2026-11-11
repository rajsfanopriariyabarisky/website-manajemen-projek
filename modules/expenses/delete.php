<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Proteksi Role: Wajib Admin (Keamanan Finansial)
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../my_tasks.php");
    exit;
}

$id         = isset($_GET['id']) ? intval($_GET['id']) : 0;
$project_id = isset($_GET['project_id']) ? intval($_GET['project_id']) : 0;

if ($id > 0) {
    // Fetch detail expense sebelum dihapus untuk logging
    $stmtExp = $pdo->prepare("
        SELECT e.*, p.nama_projek 
        FROM expenses e 
        JOIN projects p ON e.project_id = p.id 
        WHERE e.id = ?
    ");
    $stmtExp->execute([$id]);
    $exp = $stmtExp->fetch();

    if ($exp) {
        $target_project_id = $project_id > 0 ? $project_id : (int)$exp['project_id'];

        // Hapus Data Expense
        $stmtDel = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $stmtDel->execute([$id]);

        // Audit Trail ke Project Logs
        $user_id   = $_SESSION['user_id'] ?? null;
        $nama_user = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? 'System Admin';
        $aktivitas = "Menghapus pencatatan biaya operasional Rp " . number_format($exp['jumlah_biaya'], 0, ',', '.') . " (" . $exp['kategori'] . ") untuk proyek " . $exp['nama_projek'];
        
        try {
            $stmtLog = $pdo->prepare("INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe, created_at) VALUES (?, ?, ?, ?, 'warning', NOW())");
            $stmtLog->execute([$target_project_id, $user_id, $nama_user, $aktivitas]);
        } catch (Exception $e) {}

        header("Location: detail.php?project_id=" . $target_project_id . "&msg=deleted");
        exit;
    }
}

if ($project_id > 0) {
    header("Location: detail.php?project_id=" . $project_id . "&msg=not_found");
} else {
    header("Location: index.php?msg=not_found");
}
exit;