<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'] ?? null;
    $status     = $_POST['status'] ?? null;
    $project_id = $_POST['project_id'] ?? null;

    if ($invoice_id && in_array($status, ['Unpaid', 'Partially Paid', 'Paid'])) {
        $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt->execute([$status, $invoice_id]);
    }

    if ($project_id) {
        header("Location: detail.php?project_id=" . $project_id . "&msg=updated");
        exit;
    }
}

header("Location: index.php");
exit;