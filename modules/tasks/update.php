<?php
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'] ?? null;
    $status  = $_POST['status'] ?? null;

    if ($task_id && in_array($status, ['To Do', 'In Progress', 'Done'])) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
        $stmt->execute([$status, $task_id]);
    }
}

// Redirect kembali ke halaman Workload & Priority
header("Location: index.php");
exit;