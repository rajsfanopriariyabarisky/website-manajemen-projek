<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* =====================================================================
 * API CHAT MODUL — SATU SUMBER UNTUK ADMIN & KARYAWAN
 * - fetch            : ambil pesan incremental (last_id) + tandai terbaca
 * - send             : kirim pesan
 * - unread_summary   : ringkasan unread per task (badge, toast, title)
 * ===================================================================== */

function respond($data) {
    echo json_encode($data);
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function formatChatTime($timestamp) {
    // Format konsisten: tanggal + jam di setiap bubble (dua sisi sama)
    return date('d M Y • H:i', strtotime($timestamp));
}

function chatRowToArray($row) {
    return [
        'id'          => (int) $row['id'],
        'task_id'     => (int) $row['task_id'],
        'sender_id'   => (int) $row['sender_id'],
        'sender_name' => $row['sender_name'],
        'sender_role' => $row['sender_role'],
        'message'     => $row['message'],
        'created_at'  => formatChatTime($row['created_at']),
    ];
}

$action = $_REQUEST['action'] ?? '';

if (!isLoggedIn()) {
    respond(['success' => false, 'error' => 'unauthorized']);
}

$user_id   = (int) $_SESSION['user_id'];
$user_nama = $_SESSION['nama'] ?? 'User';
$user_role = strtolower($_SESSION['role'] ?? 'karyawan');
if ($user_role !== 'admin') {
    $user_role = 'karyawan';
}

/* -------------------------------------------------------------------------
 * ACTION: fetch -> Ambil pesan baru (incremental berdasarkan last_id)
 * ------------------------------------------------------------------------- */
if ($action === 'fetch') {
    $task_id = intval($_GET['task_id'] ?? 0);
    $last_id = intval($_GET['last_id'] ?? 0);
    $reader  = $_GET['reader'] ?? ($user_role === 'admin' ? 'admin' : 'karyawan');

    if ($task_id <= 0) respond(['success' => false, 'error' => 'invalid_task']);

    $opposite = ($reader === 'admin') ? 'karyawan' : 'admin';

    // Tandai pesan lawan bicara sebagai terbaca oleh pembaca saat ini
    $stmtRead = $pdo->prepare("UPDATE task_chats SET is_read = 1 WHERE task_id = ? AND sender_role = ? AND is_read = 0");
    $stmtRead->execute([$task_id, $opposite]);

    // Incremental: hanya pesan dengan id > last_id (ringan, anti-lag)
    $stmt = $pdo->prepare("SELECT * FROM task_chats WHERE task_id = ? AND id > ? ORDER BY id ASC LIMIT 200");
    $stmt->execute([$task_id, $last_id]);

    // Sisa unread dari lawan bicara (untuk badge)
    $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM task_chats WHERE task_id = ? AND sender_role = ? AND is_read = 0");
    $stmtUnread->execute([$task_id, $opposite]);
    $unread = (int) $stmtUnread->fetchColumn();

    // Total pesan saat ini di task ini (untuk deteksi penghapusan real-time di sisi lain)
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM task_chats WHERE task_id = ?");
    $stmtTotal->execute([$task_id]);
    $total = (int) $stmtTotal->fetchColumn();

    respond([
        'success'  => true,
        'messages' => array_map('chatRowToArray', $stmt->fetchAll()),
        'unread'   => $unread,
        'total'    => $total,
    ]);
}

/* -------------------------------------------------------------------------
 * ACTION: unread_summary -> Badge unread per task + info pesan terakhir
 * (dipakai dua sisi untuk badge merah, toast "pesan baru", dan title tab)
 * ------------------------------------------------------------------------- */
if ($action === 'unread_summary') {
    $opposite = ($user_role === 'admin') ? 'karyawan' : 'admin';

    $sql = "SELECT c.task_id,
                   COUNT(*) AS unread_count,
                   MAX(c.id) AS last_id,
                   (SELECT c2.sender_name FROM task_chats c2 WHERE c2.task_id = c.task_id ORDER BY c2.id DESC LIMIT 1) AS last_sender,
                   (SELECT c2.message    FROM task_chats c2 WHERE c2.task_id = c.task_id ORDER BY c2.id DESC LIMIT 1) AS last_message
            FROM task_chats c
            WHERE c.sender_role = ? AND c.is_read = 0";

    $params = [$opposite];

    // Karyawan hanya boleh melihat unread dari tugasnya sendiri
    if ($user_role !== 'admin') {
        $sql .= " AND c.task_id IN (
                    SELECT id FROM tasks WHERE user_id = ? OR assigned_to = ?
                    UNION
                    SELECT task_id FROM task_assignees WHERE user_id = ?
                  )";
        $params[] = $user_id;
        $params[] = $user_id;
        $params[] = $user_id;
    }

    $sql .= " GROUP BY c.task_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $result = [];
    $total  = 0;
    foreach ($stmt->fetchAll() as $r) {
        $cnt = (int) $r['unread_count'];
        $total += $cnt;
        $result[(int) $r['task_id']] = [
            'count'   => $cnt,
            'last_id' => (int) $r['last_id'],
            'sender'  => $r['last_sender'],
            'preview' => function_exists('mb_strimwidth')
                ? mb_strimwidth((string) $r['last_message'], 0, 60, '…')
                : substr((string) $r['last_message'], 0, 60),
        ];
    }

    respond(['success' => true, 'tasks' => $result, 'total' => $total]);
}

/* -------------------------------------------------------------------------
 * ACTION: send -> Simpan pesan baru
 * ------------------------------------------------------------------------- */
if ($action === 'send') {
    $task_id = intval($_POST['task_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($task_id <= 0 || $message === '') {
        respond(['success' => false, 'error' => 'empty']);
    }

    if (mb_strlen($message) > 2000) {
        $message = mb_substr($message, 0, 2000);
    }

    $stmtTask = $pdo->prepare("SELECT id FROM tasks WHERE id = ?");
    $stmtTask->execute([$task_id]);
    if (!$stmtTask->fetch()) {
        respond(['success' => false, 'error' => 'task_not_found']);
    }

    $stmt = $pdo->prepare("INSERT INTO task_chats (task_id, sender_id, sender_name, sender_role, message, is_read) VALUES (?, ?, ?, ?, ?, 0)");
    $stmt->execute([$task_id, $user_id, $user_nama, $user_role, $message]);

    $newId = (int) $pdo->lastInsertId();
    $stmtNew = $pdo->prepare("SELECT * FROM task_chats WHERE id = ?");
    $stmtNew->execute([$newId]);

    respond(['success' => true, 'message' => chatRowToArray($stmtNew->fetch())]);
}


/* -------------------------------------------------------------------------
 * ACTION: delete -> Hapus SELURUH chat pada satu task (ADMIN ONLY)
 * Dipakai untuk merapikan database setelah modul di-approve agar task_chats
 * tidak menumpuk / memperlambat query di masa depan.
 * ------------------------------------------------------------------------- */
if ($action === 'delete') {
    if ($user_role !== 'admin') {
        respond(['success' => false, 'error' => 'forbidden']);
    }

    $task_id = intval($_POST['task_id'] ?? 0);
    if ($task_id <= 0) {
        respond(['success' => false, 'error' => 'invalid_task']);
    }

    // Pastikan task memang ada
    $stmtTask = $pdo->prepare("SELECT id, nama_tugas FROM tasks WHERE id = ?");
    $stmtTask->execute([$task_id]);
    $taskRow = $stmtTask->fetch();
    if (!$taskRow) {
        respond(['success' => false, 'error' => 'task_not_found']);
    }

    $stmtDel = $pdo->prepare("DELETE FROM task_chats WHERE task_id = ?");
    $stmtDel->execute([$task_id]);

    respond(['success' => true, 'deleted' => $stmtDel->rowCount()]);
}

respond(['success' => false, 'error' => 'unknown_action']);