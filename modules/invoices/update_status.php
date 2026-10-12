<?php
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = $_POST['invoice_id'] ?? null;
    $status     = $_POST['status'] ?? null;

    if ($invoice_id && in_array($status, ['Unpaid', 'Partially Paid', 'Paid'])) {
        $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
        $stmt->execute([$status, $invoice_id]);
    }
}

header("Location: index.php");
exit;