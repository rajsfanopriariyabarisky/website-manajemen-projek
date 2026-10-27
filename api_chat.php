<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================================================================
   API CHAT REAL-TIME (dipakai ADMIN di modules/requirements/detail.php
   dan KLIEN di folder portal). Satu sumber kebenaran -> tidak ada miss POV.
   ========================================================================= */

function respond($data) {
    echo json_encode($data);
    exit;
}

function isAdminLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isClientLoggedIn() {
    return isset($_SESSION['client_logged_in'])
        && $_SESSION['client_logged_in'] === true
        && !empty($_SESSION['client_user_id']);
}

function formatChatTime($timestamp) {
    return date('d M Y H:i', strtotime($timestamp)); // WIB
}

function chatRowToArray($row) {
    return [
        'id'          => (int) $row['id'],
        'sender_type' => $row['sender_type'],
        'sender_name' => $row['sender_name'],
        'message'     => $row['message'],
        'created_at'  => formatChatTime($row['created_at']),
    ];
}

function strLimit($str, $max) {
    if (function_exists('mb_strlen') && mb_strlen($str) > $max) {
        return mb_substr($str, 0, $max);
    }
    if (strlen($str) > $max) {
        return substr($str, 0, $max);
    }
    return $str;
}

$action = $_REQUEST['action'] ?? '';

/* -------------------------------------------------------------------------
 * ACTION: fetch  -> ambil pesan baru saja (last_id), TANDAI SUDAH DIBACA
 * reader=admin  : pesan dari client jadi admin_read=1
 * reader=client : pesan dari admin jadi client_read=1
 * ------------------------------------------------------------------------- */
if ($action === 'fetch') {
    $req_id  = intval($_GET['req_id'] ?? 0);
    $last_id = intval($_GET['last_id'] ?? 0);
    $reader  = $_GET['reader'] ?? '';

    if ($req_id <= 0) respond(['success' => false, 'error' => 'invalid_req']);

    // Mark-as-read hanya kalau reader valid & sesuai login
    if ($reader === 'admin' && isAdminLoggedIn()) {
        $pdo->prepare("UPDATE deal_chats SET admin_read = 1 WHERE requirement_id = ? AND sender_type = 'client' AND admin_read = 0")
            ->execute([$req_id]);
    } elseif ($reader === 'client' && isClientLoggedIn()) {
        $pdo->prepare("UPDATE deal_chats SET client_read = 1 WHERE requirement_id = ? AND sender_type = 'admin' AND client_read = 0")
            ->execute([$req_id]);
    }

    $current_now = date('Y-m-d H:i:s');

    // Hanya pesan BARU setelah last_id yang waktunya SUDAH TIBA (created_at <= NOW WIB)
    $stmt = $pdo->prepare("SELECT * FROM deal_chats WHERE requirement_id = ? AND id > ? AND created_at <= ? ORDER BY id ASC LIMIT 200");
    $stmt->execute([$req_id, $last_id, $current_now]);
    $messages = array_map('chatRowToArray', $stmt->fetchAll());

    // Unread count SETELAH mark-read (untuk badge, realtime)
    $unread = 0;
    if ($reader === 'admin' && isAdminLoggedIn()) {
        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM deal_chats WHERE requirement_id = ? AND sender_type = 'client' AND admin_read = 0 AND created_at <= ?");
        $stmtUnread->execute([$req_id, $current_now]);
        $unread = (int) $stmtUnread->fetchColumn();
    } elseif ($reader === 'client' && isClientLoggedIn()) {
        $stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM deal_chats WHERE requirement_id = ? AND sender_type = 'admin' AND client_read = 0 AND created_at <= ?");
        $stmtUnread->execute([$req_id, $current_now]);
        $unread = (int) $stmtUnread->fetchColumn();
    }

    respond(['success' => true, 'messages' => $messages, 'unread' => $unread]);
}

/* -------------------------------------------------------------------------
 * ACTION: send -> simpan pesan, kembalikan versi server (jam server = WIB)
 * ------------------------------------------------------------------------- */
if ($action === 'send') {
    $req_id  = intval($_POST['req_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');

    if ($req_id <= 0 || $message === '') respond(['success' => false, 'error' => 'empty']);
    $message = strLimit($message, 4000);

    // Pastikan requirement benar-benar ada
    $stmtReq = $pdo->prepare("SELECT id FROM client_requirements WHERE id = ?");
    $stmtReq->execute([$req_id]);
    if (!$stmtReq->fetch()) respond(['success' => false, 'error' => 'req_not_found']);

    $req_sender_type = $_POST['sender_type'] ?? '';

    // FIX LOGIKA SENDER:
    if (($req_sender_type === 'client' || (empty($req_sender_type) && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'portal.php') !== false)) && isClientLoggedIn()) {
        $sender_type = 'client';
        $sender_name = $_SESSION['client_nama'] ?? '';
        if (empty($sender_name)) {
            $stmtName = $pdo->prepare("SELECT nama FROM clients WHERE id = ?");
            $stmtName->execute([intval($_SESSION['client_user_id'])]);
            $sender_name = $stmtName->fetchColumn() ?: 'Klien';
        }
        $admin_read  = 0; 
        $client_read = 1;
    } elseif (($req_sender_type === 'admin' || empty($req_sender_type)) && isAdminLoggedIn()) {
        $sender_type = 'admin';
        $stmtName = $pdo->prepare("SELECT nama FROM users WHERE id = ?");
        $stmtName->execute([intval($_SESSION['user_id'])]);
        $sender_name = $stmtName->fetchColumn() ?: 'Administrator';
        $admin_read  = 1; 
        $client_read = 0; 
    } else {
        respond(['success' => false, 'error' => 'unauthorized']);
    }

    $now_time = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$req_id, $sender_type, $sender_name, $message, $admin_read, $client_read, $now_time]);

    $newId = (int) $pdo->lastInsertId();
    $stmtNew = $pdo->prepare("SELECT * FROM deal_chats WHERE id = ?");
    $stmtNew->execute([$newId]);

    respond(['success' => true, 'message' => chatRowToArray($stmtNew->fetch())]);
}

respond(['success' => false, 'error' => 'unknown_action']);