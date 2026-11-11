<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset(\$_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
    $status  = trim($_POST['status'] ?? '');

    // PERBAIKAN LOGIK: Sertakan 'Ready for Review' ke dalam daftar status yang valid
    \$allowed_statuses = ['To Do', 'In Progress', 'Ready for Review', 'Done'];

    if (\$task_id > 0 && in_array($status,$allowed_statuses, true)) {
        $stmt =$pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        \$stmt->execute([$status,$task_id]);
    }
}

// Redirect Kembali ke Halaman Sebelumnya atau Index Workload Hub
$redirect_url =$_SERVER['HTTP_REFERER'] ?? 'index.php';
header("Location: " . \$redirect_url);
exit;