<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

// Proteksi Sesi Login Client
if (
    !isset($_SESSION['client_logged_in']) ||
    $_SESSION['client_logged_in'] !== true ||
    empty($_SESSION['client_user_id'])
) {
    header('Location: login.php');
    exit;
}

$client_id    = intval($_SESSION['client_user_id']);
$client_nama  = $_SESSION['client_nama'] ?? 'Klien';
$client_email = $_SESSION['client_email'] ?? '';

// Ambil data akun klien dari DB
$stmtClient = $pdo->prepare("SELECT * FROM clients WHERE id = ? LIMIT 1");
$stmtClient->execute([$client_id]);
$client_db = $stmtClient->fetch();

if (!$client_db) {
    header('Location: login.php');
    exit;
}

// Ambil data nomor HP & nama perusahaan dari histori pengajuan terdahulu (jika ada)
$stmtPrev = $pdo->prepare("SELECT client_phone, company_name FROM client_requirements WHERE client_id = ? OR LOWER(client_email) = LOWER(?) ORDER BY id DESC LIMIT 1");
$stmtPrev->execute([$client_id, $client_email]);
$prevReq = $stmtPrev->fetch();

$client_phone = $prevReq['client_phone'] ?? '-';
$company_name = $prevReq['company_name'] ?? '-';

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title             = trim($_POST['title'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $budget_raw        = trim($_POST['budget_estimation'] ?? '0');
    $password_input    = $_POST['password'] ?? '';
    $confirm_password  = $_POST['confirm_password'] ?? '';

    // Bersihkan format budget angka
    $budget_clean = (float) preg_replace('/[^0-9.]/', '', $budget_raw);

    // 1. Validasi Input Kosong
    if (empty($title) || empty($description) || empty($password_input) || empty($confirm_password)) {
        $error_msg = 'Semua kolom bertanda bintang (*) wajib diisi!';
    }
    // 2. Validasi Konfirmasi Password
    elseif ($password_input !== $confirm_password) {
        $error_msg = 'Konfirmasi password tidak cocok! Silakan periksa kembali.';
    }
    // 3. Validasi Password Akun
    elseif (!password_verify($password_input, $client_db['password'])) {
        $error_msg = 'Password salah! Tidak bisa mengajukan proyek baru.';
    }
    // 4. Validasi Dokumen Lampiran (Wajib)
    elseif (!isset($_FILES['file_attachment']) || $_FILES['file_attachment']['error'] !== UPLOAD_ERR_OK) {
        $error_msg = 'Lampiran Kebutuhan Projek wajib diunggah!';
    }
    else {
        // Upload Lampiran Dokumen Kebutuhan Projek
        $file_attachment = null;
        $upload_dir = __DIR__ . '/../uploads/brd/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $orig_filename = basename($_FILES['file_attachment']['name']);
        $ext = strtolower(pathinfo($orig_filename, PATHINFO_EXTENSION));
        $allowed_exts = ['pdf', 'doc', 'docx', 'zip', 'rar', 'txt', 'png', 'jpg', 'jpeg'];

        if (in_array($ext, $allowed_exts)) {
            $new_filename = 'BRD_' . time() . '_' . substr(md5(uniqid()), 0, 8) . '.' . $ext;
            $target_file  = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['file_attachment']['tmp_name'], $target_file)) {
                $file_attachment = $new_filename;
            } else {
                $error_msg = 'Gagal mengunggah dokumen lampiran. Silakan coba lagi.';
            }
        } else {
            $error_msg = 'Format dokumen lampiran tidak didukung. Gunakan PDF, DOC, DOCX, ZIP, RAR, PNG, JPG, atau JPEG.';
        }

        if (empty($error_msg)) {
            // Insert Pengajuan Baru ke DB
            $stmtIns = $pdo->prepare("
                INSERT INTO client_requirements 
                (client_name, client_email, client_phone, company_name, title, description, file_attachment, budget_estimation, status, client_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Under Review', ?, NOW())
            ");
            
            $inserted = $stmtIns->execute([
                $client_nama,
                $client_email,
                $client_phone,
                $company_name,
                $title,
                $description,
                $file_attachment,
                $budget_clean,
                $client_id
            ]);

            if ($inserted) {
                $new_req_id = (int) $pdo->lastInsertId();
                $time_now   = date('Y-m-d H:i:s');
                $time_delay = date('Y-m-d H:i:s', strtotime('+20 seconds'));

                // 1. Pesan awal dari Client (Langsung tayang)
                $stmtChatClient = $pdo->prepare("
                    INSERT INTO deal_chats 
                    (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) 
                    VALUES (?, 'client', ?, ?, 0, 1, ?)
                ");
                $init_msg = "Halo Tim Konsultan CTC, kami telah mengajukan dokumen requirement proyek: \"{$title}\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.";
                $stmtChatClient->execute([$new_req_id, $client_nama, $init_msg, $time_now]);

                // 2. Pesan Balasan Otomatis dari Administrator (Tayang otomatis setelah 20 detik)
                $stmtChatAdmin = $pdo->prepare("
                    INSERT INTO deal_chats 
                    (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) 
                    VALUES (?, 'admin', 'Administrator', ?, 1, 0, ?)
                ");
                $auto_reply_msg = "Halo {$client_nama}, requirement proyek \"{$title}\" telah kami terima dan saat ini sedang dalam proses review oleh Tim CTC. Kami akan segera menghubungi Anda melalui diskusi ini.";
                $stmtChatAdmin->execute([$new_req_id, $auto_reply_msg, $time_delay]);

                header("Location: portal.php?req_id={$new_req_id}&msg=submitted");
                exit;
            } else {
                $error_msg = 'Gagal menyimpan pengajuan proyek ke database. Silakan coba lagi.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Proyek Baru - PT. Cipta Teknologi Cendekia</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .btn-press:active { transform: translateY(1px); }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    </style>
</head>

<body class="bg-slate-100 text-slate-800 min-h-screen font-sans antialiased selection:bg-emerald-600 selection:text-white flex flex-col">

<!-- Top Navigation Header -->
<header class="bg-white border-b border-slate-200/90 px-4 sm:px-8 py-3 flex items-center justify-between sticky top-0 z-30 shadow-xs">
    <div class="flex items-center gap-3 min-w-0">
        <a href="portal.php" class="btn-press w-9 h-9 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl flex items-center justify-center transition shrink-0" title="Kembali ke Portal">
            <i class="fa-solid fa-arrow-left text-xs"></i>
        </a>
        <img src="../assets/images/logo.png" alt="Logo CTC" class="h-9 sm:h-10 w-auto object-contain shrink-0">

        <div class="min-w-0 border-l border-slate-200 pl-3">
            <h1 class="text-xs font-bold text-slate-900 uppercase tracking-wider">FORM PENGAJUAN PROYEK</h1>
            <p class="text-[11px] text-slate-500 font-medium truncate">PT. Cipta Teknologi Cendekia</p>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <a href="profile.php" class="group relative btn-press hidden sm:flex items-center gap-2 px-3.5 py-1.5 bg-slate-50 hover:bg-emerald-50 hover:border-emerald-300 border border-slate-200/80 rounded-xl text-xs font-semibold text-slate-700 transition" title="Lihat Profil">
            <i class="fa-solid fa-circle-user text-emerald-600 text-sm group-hover:scale-110 transition-transform"></i>
            <span><?= htmlspecialchars($client_nama) ?></span>
        </a>
    </div>
</header>

<!-- Content Container -->
<main class="max-w-3xl w-full mx-auto p-4 sm:p-6 md:p-8 flex-1">

    <div class="bg-white border border-slate-200/90 rounded-2xl p-6 sm:p-8 shadow-sm space-y-6">

        <div class="border-b border-slate-100 pb-4">
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-700">Client Requirement Form</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Formulir Requirement Proyek Baru</h2>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-1">
                Lengkapi rincian proyek Anda di bawah ini. Identitas akun Anda otomatis terhubung dengan sistem.
            </p>
        </div>

        <?php if (!empty($error_msg)): ?>
            <div class="p-4 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-3 shadow-xs">
                <i class="fa-solid fa-triangle-exclamation text-red-500 text-lg shrink-0"></i>
                <div><?= htmlspecialchars($error_msg) ?></div>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-5" id="ajukanProyekForm">

            <!-- Judul Proyek -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                    Judul / Nama Sistem Proyek <span class="text-red-500">*</span>
                </label>
                <input type="text" name="title" required value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                       placeholder="Contoh: Website Company Profile & Application Management"
                       class="input-glow w-full px-4 py-3 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium text-slate-900 focus:outline-none transition">
            </div>

            <!-- Estimasi Anggaran -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                    Estimasi Anggaran (Rp) <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center text-xs font-bold text-slate-400 pointer-events-none">Rp</span>
                    <input type="text" id="budget_estimation_input" name="budget_estimation" required value="<?= htmlspecialchars($_POST['budget_estimation'] ?? '') ?>"
                           placeholder="20.000.000" autocomplete="off"
                           class="input-glow w-full pl-11 pr-4 py-3 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium text-slate-900 focus:outline-none transition">
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Perkiraan budget yang Anda alokasikan untuk pengerjaan proyek ini (otomatis terformat ribuan).</p>
            </div>

            <!-- Deskripsi Requirement -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                    Deskripsi & Ruang Lingkup Sistem <span class="text-red-500">*</span>
                </label>
                <textarea name="description" rows="5" required
                          placeholder="Jelaskan secara detail fitur-fitur, alur kerja, dan spesifikasi sistem yang ingin Anda buat..."
                          class="input-glow w-full px-4 py-3 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium text-slate-900 focus:outline-none transition leading-relaxed"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

            <!-- Lampiran Kebutuhan Projek -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                    Lampiran Kebutuhan Projek <span class="text-red-500">*</span>
                </label>
                <input type="file" name="file_attachment" required accept=".pdf,.doc,.docx,.zip,.rar,.png,.jpg,.jpeg"
                       class="block w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-300 rounded-xl p-1 bg-white">
                <p class="text-[11px] text-slate-400 mt-1">Format didukung: PDF, DOC, DOCX, ZIP (Maksimal 20MB)</p>
            </div>

            <div class="border-t border-slate-100 pt-5 space-y-4">
                <div class="bg-amber-50 border border-amber-200/80 p-3.5 rounded-xl text-amber-800 text-xs leading-relaxed flex items-start gap-2.5">
                    <i class="fa-solid fa-shield-halved text-amber-600 text-base shrink-0 mt-0.5"></i>
                    <span><strong>Konfirmasi Keamanan:</strong> Masukkan password akun Anda untuk melakukan konfirmasi pengajuan proyek baru.</span>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                            Password Akun <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="password" required placeholder="••••••••"
                               class="input-glow w-full px-4 py-3 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium text-slate-900 focus:outline-none transition">
                    </div>

                    <!-- Confirmation Password -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-2">
                            Konfirmasi Password <span class="text-red-500">*</span>
                        </label>
                        <input type="password" name="confirm_password" required placeholder="••••••••"
                               class="input-glow w-full px-4 py-3 rounded-xl border border-slate-300 text-xs sm:text-sm font-medium text-slate-900 focus:outline-none transition">
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <a href="portal.php" class="btn-press px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-semibold transition">
                    Batal
                </a>

                <button type="submit" class="btn-press px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-semibold uppercase tracking-wider transition shadow-md shadow-emerald-600/20 flex items-center gap-2">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                    <span>Kirim Pengajuan</span>
                </button>
            </div>

        </form>

    </div>

</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const budgetInput = document.getElementById('budget_estimation_input');
    const form = document.getElementById('ajukanProyekForm');

    function formatRupiah(val) {
        if (!val) return '';
        let numberString = val.toString().replace(/[^0-9]/g, '');
        if (!numberString) return '';
        return new Intl.NumberFormat('id-ID').format(numberString);
    }

    if (budgetInput) {
        if (budgetInput.value) {
            budgetInput.value = formatRupiah(budgetInput.value);
        }

        budgetInput.addEventListener('input', function(e) {
            let cursorPosition = e.target.selectionStart;
            let originalLength = e.target.value.length;

            e.target.value = formatRupiah(e.target.value);

            let newLength = e.target.value.length;
            cursorPosition = cursorPosition + (newLength - originalLength);
            e.target.setSelectionRange(cursorPosition, cursorPosition);
        });
    }

    if (form) {
        form.addEventListener('submit', function() {
            if (budgetInput) {
                budgetInput.value = budgetInput.value.replace(/\./g, '');
            }
        });
    }
});
</script>

</body>
</html>