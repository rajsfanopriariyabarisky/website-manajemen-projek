<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../config/database.php';

if (file_exists(__DIR__ . '/mail.php')) {
    require_once __DIR__ . '/mail.php';
}

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name      = trim($_POST['client_name'] ?? '');
    $client_email     = trim($_POST['client_email'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $phone            = trim($_POST['phone'] ?? '');
    $company_name     = trim($_POST['company_name'] ?? '');
    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $budget_raw       = trim($_POST['budget'] ?? '');

    // Bersihkan format budget angka (100.000.000 -> 100000000.00)
    $budget_clean = preg_replace('/[^0-9]/', '', $budget_raw);
    $budget_val   = is_numeric($budget_clean) ? (float)$budget_clean : 0.00;

    if (empty($client_name) || empty($client_email) || empty($password) || empty($confirm_password) || empty($title) || empty($description)) {
        $error_msg = 'Harap isi semua kolom wajib yang bertanda bintang (*).';
    } elseif ($password !== $confirm_password) {
        $error_msg = 'Password dan Konfirmasi Password tidak cocok! Periksa kembali.';
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Format alamat email tidak valid.';
    } else {
        // DETEKSI SERVER DOMAIN EMAIL AKTIF (Cek MX / A DNS Record)
        $domain = substr(strrchr($client_email, "@"), 1);
        if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
            $error_msg = 'Alamat email tidak terdaftar atau domain email "' . htmlspecialchars($domain) . '" tidak aktif/tidak valid!';
        } else {
            $file_attachment = null;
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = __DIR__ . '/../uploads/brd/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $allowed  = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];

                if (in_array($file_ext, $allowed)) {
                    $new_filename = 'BRD_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                    $target_path  = $upload_dir . $new_filename;

                    if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                        $file_attachment = $new_filename;
                    } else {
                        $error_msg = 'Gagal mengunggah dokumen lampiran. Coba lagi.';
                    }
                } else {
                    $error_msg = 'Format dokumen lampiran tidak didukung. Gunakan PDF, DOCX, XLSX, atau ZIP.';
                }
            }

            if (empty($error_msg)) {
                try {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    // Cek atau buatkan akun di tabel clients
                    $stmtCheck = $pdo->prepare("SELECT * FROM clients WHERE LOWER(email) = LOWER(?)");
                    $stmtCheck->execute([$client_email]);
                    $existing_client = $stmtCheck->fetch();

                    $is_verified_status = 0;

                    if ($existing_client) {
                        $client_id          = $existing_client['id'];
                        $is_verified_status = (int)$existing_client['is_verified'];
                        // Update nama & password jika akun sudah pernah ada
                        $stmtUpd = $pdo->prepare("UPDATE clients SET nama = ?, password = ? WHERE id = ?");
                        $stmtUpd->execute([$client_name, $hashed_password, $client_id]);
                    } else {
                        // Buat akun baru dengan is_verified = 0 (perlu verifikasi OTP awal)
                        $stmtNewClient = $pdo->prepare("INSERT INTO clients (nama, email, password, is_verified) VALUES (?, ?, ?, 0)");
                        $stmtNewClient->execute([$client_name, $client_email, $hashed_password]);
                        $client_id          = $pdo->lastInsertId();
                        $is_verified_status = 0;
                    }

                    // Simpan requirement
                    $stmt = $pdo->prepare("INSERT INTO client_requirements
                        (client_id, client_name, client_email, client_phone, company_name, title, description, budget_estimation, file_attachment, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Under Review')");

                    $stmt->execute([
                        $client_id,
                        $client_name,
                        $client_email,
                        $phone,
                        $company_name,
                        $title,
                        $description,
                        $budget_val,
                        $file_attachment
                    ]);

                    $new_req_id = (int) $pdo->lastInsertId();

                    // Pesan pembuka otomatis di chat negosiasi
                    $welcome_msg = 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: "' . $title . '". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.';
                    $stmtChat = $pdo->prepare("INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message, admin_read, client_read, created_at) VALUES (?, 'client', ?, ?, 0, 1, NOW())");
                    $stmtChat->execute([$new_req_id, $client_name, $welcome_msg]);

                    // FILTERING SPAM LOGIC: Jika akun sudah terverifikasi sebelumnya
                    if ($is_verified_status === 1) {
                        $_SESSION['client_logged_in'] = true;
                        $_SESSION['client_user_id']   = $client_id;
                        $_SESSION['client_nama']      = $client_name;
                        $_SESSION['client_email']     = $client_email;
                        $_SESSION['client_token']     = hash('sha256', bin2hex(random_bytes(16)) . time());

                        header('Location: portal.php?req_id=' . $new_req_id);
                        exit;
                    } else {
                        // Jika akun baru (is_verified = 0): Kirim OTP & arahkan ke verify.php
                        $otp_code   = sprintf("%06d", mt_rand(100000, 999999));
                        $otp_expiry = date('Y-m-d H:i:s', strtotime('+2 minutes'));

                        if (function_exists('sendOtpViaSmtp')) {
                            sendOtpViaSmtp($client_email, $otp_code, $client_name);
                        }

                        $stmtOtp = $pdo->prepare("UPDATE clients SET otp_code = ?, otp_expiry = ? WHERE id = ?");
                        $stmtOtp->execute([$otp_code, $otp_expiry, $client_id]);

                        $_SESSION['pending_client_id']    = $client_id;
                        $_SESSION['pending_client_email'] = $client_email;

                        header('Location: verify.php');
                        exit;
                    }
                } catch (PDOException $e) {
                    $error_msg = 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage();
                }
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
    <title>Formulir Requirement - PT. CTC</title>

    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/logo.png">

    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
        }
        .btn-press { 
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
        }
        .btn-press:active { 
            transform: translateY(1px); 
        }
        .input-glow:focus { 
            border-color: #059669; 
            box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen py-8 px-4 selection:bg-emerald-600 selection:text-white flex items-center justify-center relative">

    <div class="w-full max-w-3xl mx-auto relative z-10">

        <!-- Navigation Bar Atas -->
        <div class="mb-5 flex items-center justify-between">
            <a href="login.php" class="btn-press inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200/90 rounded-xl text-xs font-semibold text-slate-600 hover:text-emerald-700 hover:border-emerald-300 shadow-xs transition">
                <i class="fa-solid fa-arrow-left text-xs"></i> Kembali ke Login
            </a>
            <div class="flex items-center gap-2 px-3 py-1.5 bg-white border border-slate-200/90 rounded-xl shadow-xs">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold text-slate-600">Proposal Portal</span>
            </div>
        </div>

        <!-- Card Form Utama Clean -->
        <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-200/60 p-6 sm:p-10">

            <!-- Header Form Clean -->
            <div class="text-center mb-8 pb-6 border-b border-slate-100">
                <!-- Logo PNG Transparan Tanpa Box -->
                <img src="../assets/images/logo.png" alt="Logo PT. CTC" class="h-16 sm:h-20 w-auto mx-auto mb-3 object-contain">

                <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-800 bg-emerald-50 border border-emerald-200 px-3.5 py-1 rounded-full inline-block mb-2">
                    PT. CIPTA TEKNOLOGI CENDEKIA
                </span>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Formulir Requirement Proyek</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1 max-w-md mx-auto leading-relaxed">
                    Silakan isi rincian kebutuhan sistem Anda di bawah ini untuk penyusunan Surat Penawaran Resmi.
                </p>
            </div>

            <!-- Pesan Sukses -->
            <?php if (!empty($success_msg)): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-xs sm:text-sm flex items-start gap-3 shadow-xs">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-lg shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-semibold"><?= htmlspecialchars($success_msg) ?></p>
                        <a href="login.php" class="text-emerald-700 underline font-bold hover:text-emerald-900 mt-1.5 inline-block">Masuk ke Client Portal &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Pesan Error -->
            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-xs sm:text-sm flex items-start gap-3 shadow-xs">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-lg shrink-0 mt-0.5"></i>
                    <span class="font-medium leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-8">

                <!-- Section 1: Data Akun & Penanggung Jawab -->
                <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-5 sm:p-6 space-y-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-900 pb-2 border-b border-slate-200/80 flex items-center gap-2">
                        <i class="fa-solid fa-user-shield text-emerald-600"></i> Informasi Penanggung Jawab Mitra
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Nama Lengkap <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="client_name" required placeholder="Budi Santoso"
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-user text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Alamat Email Resmi <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="email" name="client_email" required placeholder="budi@perusahaan.com"
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-envelope text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Password Portal Klien <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="reqPassword" name="password" required placeholder="Buat password portal..."
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-key text-sm"></i>
                                </div>
                                <button type="button" onclick="togglePassword('reqPassword', 'eyeIconReq')" 
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none transition-colors">
                                    <i id="eyeIconReq" class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Konfirmasi Password <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="password" id="reqConfirmPassword" name="confirm_password" required placeholder="Ketik ulang password..."
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 pr-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-lock text-sm"></i>
                                </div>
                                <button type="button" onclick="togglePassword('reqConfirmPassword', 'eyeIconConfirm')" 
                                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-emerald-700 focus:outline-none transition-colors">
                                    <i id="eyeIconConfirm" class="fa-solid fa-eye text-sm"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Nomor Kontak / WhatsApp
                            </label>
                            <div class="relative">
                                <input type="text" name="phone" placeholder="081234567890"
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-phone text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Nama Perusahaan / Instansi
                            </label>
                            <div class="relative">
                                <input type="text" name="company_name" placeholder="PT. Maju Bersama"
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-building text-sm"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Detail Requirement Proyek -->
                <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-5 sm:p-6 space-y-4">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-emerald-900 pb-2 border-b border-slate-200/80 flex items-center gap-2">
                        <i class="fa-solid fa-diagram-project text-emerald-600"></i> Detail Requirement Proyek
                    </h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Judul / Nama Sistem Proyek <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <input type="text" name="title" required placeholder="Pengadaan System Smart Parking & Mobile Application"
                                       class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i class="fa-solid fa-laptop-code text-sm"></i>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                Deskripsi Ruang Lingkup & Fitur Utama <span class="text-red-500">*</span>
                            </label>
                            <textarea name="description" rows="4" required placeholder="Uraikan alur bisnis, modul-modul utama, atau fungsionalitas khusus yang Anda butuhkan..."
                                      class="input-glow w-full p-3.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400 leading-relaxed"></textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                    Estimasi Anggaran / Budget (Rp)
                                </label>
                                <div class="relative">
                                    <input type="text" id="budgetInput" name="budget" placeholder="100.000.000"
                                           class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm font-normal focus:outline-none transition-all placeholder:text-slate-400">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                        <i class="fa-solid fa-money-bill-wave text-sm"></i>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs sm:text-sm font-medium text-slate-700 mb-1.5">
                                    Lampiran Dokumen BRD <span class="text-slate-400 font-normal">(PDF, DOCX, XLSX, ZIP)</span>
                                </label>
                                <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
                                       class="block w-full text-xs text-slate-500 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-300 rounded-xl bg-white p-1">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tombol Submit -->
                <div class="pt-2">
                    <button type="submit" class="btn-press w-full py-3.5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl shadow-md hover:shadow-lg text-sm flex items-center justify-center gap-2 transition-all">
                        <i class="fa-solid fa-paper-plane text-sm"></i> Kirim Requirement
                    </button>
                </div>
            </form>

            <div class="mt-8 pt-4 border-t border-slate-100 text-center text-xs sm:text-sm text-slate-500">
                Sudah pernah mengajukan requirement? <a href="login.php" class="text-emerald-600 font-semibold hover:underline">Masuk ke Portal Klien</a>
            </div>
        </div>

        <div class="text-center text-xs text-slate-400 font-medium mt-4">
            &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Software Solution.
        </div>
    </div>

    <script>
        function togglePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const budgetInput = document.getElementById('budgetInput');
        if (budgetInput) {
            budgetInput.addEventListener('input', function(e) {
                let val = this.value.replace(/\D/g, '');
                if (val) {
                    this.value = new Intl.NumberFormat('id-ID').format(val);
                } else {
                    this.value = '';
                }
            });
        }
    </script>
</body>
</html>