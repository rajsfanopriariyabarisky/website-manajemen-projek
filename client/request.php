<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name  = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $company_name = trim($_POST['company_name'] ?? '');
    $title        = trim($_POST['title'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $budget       = trim($_POST['budget'] ?? '');
    
    if (empty($client_name) || empty($client_email) || empty($title) || empty($description)) {
        $error_msg = 'Harap isi semua kolom wajib yang bertanda bintang (*).';
    } else {
        $file_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/brd/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext  = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed   = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar'];
            
            if (in_array($file_ext, $allowed)) {
                $new_filename = 'BRD_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
                $target_path  = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                    $file_path = 'uploads/brd/' . $new_filename;
                }
            } else {
                $error_msg = 'Format dokumen lampiran tidak didukung. Gunakan PDF, DOCX, XLSX, atau ZIP.';
            }
        }

        if (empty($error_msg)) {
            try {
                $stmtCheck = $pdo->prepare("SELECT id FROM clients WHERE LOWER(email) = LOWER(?)");
                $stmtCheck->execute([$client_email]);
                $existing_client = $stmtCheck->fetch();
                $client_id = $existing_client ? $existing_client['id'] : null;

                $stmt = $pdo->prepare("INSERT INTO client_requirements (client_id, client_name, client_email, phone, company_name, title, description, budget, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'UNDER REVIEW')");
                $stmt->execute([$client_id, $client_name, $client_email, $phone, $company_name, $title, $description, $budget, $file_path]);

                $success_msg = 'Permohonan requirement & proposal proyek berhasil dikirimkan! Tim analis PT. CTC akan segera meninjau pengajuan Anda.';
            } catch (PDOException $e) {
                $error_msg = 'Terjadi kesalahan sistem saat menyimpan data: ' . $e->getMessage();
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

    <!-- Favicon Logo CTC untuk Tab Browser Chrome/Google -->
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .btn-press { transition: all .15s ease; }
        .btn-press:active { transform: scale(.97); }
        .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.12); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen py-4 px-3 font-sans relative selection:bg-emerald-600 selection:text-white flex items-center justify-center">

    <!-- Watermark Logo CTC Raksasa Full Background -->
    <div class="fixed inset-0 flex items-center justify-center pointer-events-none z-0 overflow-hidden">
        <img src="../assets/images/logo.jpg" alt="Watermark CTC" class="w-[850px] max-w-[90vw] h-auto object-contain opacity-[0.08] mix-blend-multiply">
    </div>

    <div class="w-full max-w-2xl mx-auto relative z-10">
        
        <!-- Header Top Navigation -->
        <div class="mb-3 flex items-center justify-between">
            <a href="login.php" class="btn-press inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200/90 rounded-xl text-[11px] font-bold text-slate-700 hover:text-emerald-700 shadow-2xs transition">
                <i class="fa-solid fa-arrow-left text-[10px]"></i> Kembali ke Login
            </a>
            <div class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-mono font-bold text-slate-500 uppercase tracking-wider">Proposal Portal</span>
            </div>
        </div>

        <!-- Container Main Card Compact -->
        <div class="bg-white/95 border border-slate-200/90 rounded-2xl shadow-xl shadow-slate-300/70 p-4 sm:p-6 backdrop-blur-md">
            
            <!-- Header Ringkas -->
            <div class="text-center mb-4 border-b border-slate-100 pb-3">
                <div class="w-12 h-12 mx-auto mb-2 flex items-center justify-center p-1 bg-white rounded-xl shadow-xs border border-slate-200/80">
                    <img src="../assets/images/logo.jpg" alt="Logo PT. CTC" class="w-full h-full object-contain">
                </div>

                <span class="text-[9px] font-mono font-black uppercase tracking-[0.15em] text-emerald-800 bg-emerald-50 border border-emerald-200/80 px-2.5 py-0.5 rounded-full inline-block mb-1">
                    PT. CIPTA TEKNOLOGI CENDEKIA
                </span>
                <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight uppercase">Formulir Requirement Proyek</h1>
            </div>

            <!-- Alert Notifikasi -->
            <?php if (!empty($success_msg)): ?>
                <div class="mb-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800 text-[11px] flex items-start gap-2 shadow-2xs">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm shrink-0 mt-0.5"></i>
                    <div>
                        <p class="font-bold"><?= htmlspecialchars($success_msg) ?></p>
                        <a href="login.php" class="text-emerald-700 underline font-black hover:text-emerald-800">Masuk ke Client Portal &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-xl text-red-700 text-[11px] flex items-start gap-2 shadow-2xs">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-sm shrink-0 mt-0.5"></i>
                    <span class="font-bold leading-relaxed"><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Body Compact -->
            <form method="POST" enctype="multipart/form-data" class="space-y-3 text-[11px]">
                
                <!-- Grid Informasi Kontak -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            NAMA LENGKAP PENANGGUNG JAWAB <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" name="client_name" required placeholder="Budi Santoso"
                                   class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                            <i class="fa-solid fa-user absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            ALAMAT EMAIL RESMI <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="email" name="client_email" required placeholder="budi@perusahaan.com"
                                   class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                            <i class="fa-solid fa-envelope absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            NOMOR KONTAK / WHATSAPP
                        </label>
                        <div class="relative">
                            <input type="text" name="phone" placeholder="081234567890"
                                   class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                            <i class="fa-solid fa-phone absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            NAMA PERUSAHAAN / INSTANSI
                        </label>
                        <div class="relative">
                            <input type="text" name="company_name" placeholder="PT. Maju Bersama"
                                   class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                            <i class="fa-solid fa-building absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>
                    </div>
                </div>

                <!-- Informasi Proyek -->
                <div>
                    <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                        NAMA / JUDUL SISTEM PROYEK <span class="text-red-500">*</span>
                    </label>
                    <div class="relative">
                        <input type="text" name="title" required placeholder="Pengadaan System Smart Parking & Mobile Application"
                               class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                        <i class="fa-solid fa-laptop-code absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                    </div>
                </div>

                <div>
                    <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                        DESKRIPSI RUANG LINGKUP & FITUR UTAMA <span class="text-red-500">*</span>
                    </label>
                    <textarea name="description" rows="3" required placeholder="Uraikan alur bisnis, modul-modul utama, atau fungsionalitas khusus..."
                              class="input-glow w-full p-2.5 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-semibold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal leading-relaxed"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            ESTIMASI BUDGET (RP)
                        </label>
                        <div class="relative">
                            <input type="text" name="budget" placeholder="50.000.000 - 100.000.000"
                                   class="input-glow w-full px-3 py-2 pl-8 rounded-xl border border-slate-300 bg-slate-50/80 text-slate-900 font-bold focus:bg-white focus:outline-none transition-all placeholder:text-slate-400 placeholder:font-normal">
                            <i class="fa-solid fa-money-bill-wave absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block font-extrabold text-slate-700 uppercase tracking-wider mb-1 text-[9px]">
                            LAMPIRAN DOKUMEN BRD (OPSIONAL)
                        </label>
                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.xls,.xlsx,.zip,.rar"
                               class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[10px] file:font-black file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100 cursor-pointer border border-slate-300 rounded-xl bg-slate-50/80 p-0.5">
                    </div>
                </div>

                <button type="submit" class="btn-press w-full py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl shadow-md shadow-emerald-600/20 text-xs uppercase tracking-wider flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-paper-plane text-xs"></i> KIRIM REQUIREMENT
                </button>
            </form>

            <div class="mt-4 pt-3 border-t border-slate-100 text-center text-[11px] text-slate-500 font-medium">
                Sudah pernah mengajukan? <a href="login.php" class="text-emerald-700 font-bold hover:underline">Masuk ke Portal Klien</a>
            </div>
        </div>

        <div class="text-center text-[9px] text-slate-400 font-bold font-mono mt-3">
            &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Enterprise Software Solution.
        </div>
    </div>

</body>
</html>