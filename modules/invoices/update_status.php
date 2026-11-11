<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Autentikasi User Session
if (!isset($_SESSION['user_id'])) {
    if (
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
    ) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unauthorized session.']);
        exit;
    }
    header('Location: ../../login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $status     = trim($_POST['status'] ?? '');
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

    $allowed_statuses = ['Unpaid', 'Partially Paid', 'Paid'];

    if ($invoice_id > 0 && in_array($status, $allowed_statuses, true)) {
        try {
            // Fetch detail invoice eksisting untuk audit log & validasi relasi
            $stmtInv = $pdo->prepare("
                SELECT i.*, p.id AS pid, p.nama_projek 
                FROM invoices i
                JOIN projects p ON i.project_id = p.id
                WHERE i.id = ?
            ");
            $stmtInv->execute([$invoice_id]);
            $inv = $stmtInv->fetch();

            if ($inv) {
                $target_project_id = $project_id > 0 ? $project_id : (int)$inv['project_id'];
                $old_status        = $inv['status'];

                // 1. Eksekusi Perubahan Status Invoice
                $stmtUpd = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
                $stmtUpd->execute([$status, $invoice_id]);

                // 2. Pencatatan History Audit Trail ke project_logs
                $user_id   = $_SESSION['user_id'] ?? null;
                $nama_user = $_SESSION['nama'] ?? $_SESSION['user_nama'] ?? 'System Admin';
                
                $log_type  = ($status === 'Paid') ? 'success' : (($status === 'Partially Paid') ? 'info' : 'warning');
                $aktivitas = "Mengubah status pembayaran Invoice {$inv['nomor_invoice']} (Termin {$inv['termin_ke']}) dari '{$old_status}' menjadi '{$status}'";

                $stmtLog = $pdo->prepare("
                    INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmtLog->execute([$target_project_id, $user_id, $nama_user, $aktivitas, $log_type]);

                // 3. Otomatisasi Cek Status Pelunasan 100% Seluruh Termin Proyek
                $stmtCheckAll = $pdo->prepare("
                    SELECT 
                        COUNT(*) AS total_count,
                        SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END) AS paid_count
                    FROM invoices 
                    WHERE project_id = ?
                ");
                $stmtCheckAll->execute([$target_project_id]);
                $summary = $stmtCheckAll->fetch();

                if (
                    $summary && 
                    (int)$summary['total_count'] > 0 && 
                    (int)$summary['total_count'] === (int)$summary['paid_count']
                ) {
                    $logLunas = "Seluruh tagihan invoice termin proyek '{$inv['nama_projek']}' telah LUNAS 100%. Kuitansi pelunasan BAST resmi siap dicetak.";
                    $stmtLogLunas = $pdo->prepare("
                        INSERT INTO project_logs (project_id, user_id, nama_user, aktivitas, tipe, created_at)
                        VALUES (?, ?, ?, ?, 'success', NOW())
                    ");
                    $stmtLogLunas->execute([$target_project_id, $user_id, 'System Automator', $logLunas]);
                }

                // 4. Respon JSON jika dipanggil via AJAX/Fetch
                if (
                    !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
                ) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => 'Status invoice berhasil diperbarui.',
                        'status'  => $status
                    ]);
                    exit;
                }

                // 5. Redirect standar form submit HTML
                header("Location: detail.php?project_id=" . $target_project_id . "&msg=updated");
                exit;
            }
        } catch (PDOException $e) {
            error_log("Update Status Invoice Error: " . $e->getMessage());
        }
    }
}

// Fallback Redirect jika request invalid
$fallback_project = isset($_POST['project_id']) ? intval($_POST['project_id']) : (isset($_GET['project_id']) ? intval($_GET['project_id']) : 0);

if ($fallback_project > 0) {
    header("Location: detail.php?project_id=" . $fallback_project . "&msg=error");
} else {
    header("Location: index.php");
}
exit;