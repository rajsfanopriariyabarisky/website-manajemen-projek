<?php
require_once __DIR__ . '/../config/database.php';

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name   = trim($_POST['client_name'] ?? '');
    $client_email  = trim($_POST['client_email'] ?? '');
    $client_phone  = trim($_POST['client_phone'] ?? '');
    $company_name  = trim($_POST['company_name'] ?? '');
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $budget        = !empty($_POST['budget_estimation']) ? floatval($_POST['budget_estimation']) : 0;

    if (empty($client_name) || empty($client_email) || empty($title) || empty($description)) {
        $error_msg = 'Mohon lengkapi seluruh kolom bertanda bintang (*) terlebih dahulu.';
    } else {
        // Handle File Upload
        $file_attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/requirements/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $allowed  = ['pdf', 'doc', 'docx', 'zip', 'rar'];

            if (in_array($file_ext, $allowed)) {
                $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9_.]/', '_', $_FILES['attachment']['name']);
                $target_path = $upload_dir . $file_name;

                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                    $file_attachment = $file_name;
                }
            } else {
                $error_msg = 'Format dokumen tidak didukung! Harap unggah berkas berformat PDF, DOC, DOCX, ZIP, atau RAR.';
            }
        }

        if (empty($error_msg)) {
            try {
                $pdo->beginTransaction();

                // 1. Cek atau Buat Akun Klien di tabel TERPISAH `clients`
                $stmtCheckClient =$pdo->prepare("SELECT id FROM clients WHERE email = ?");
                $stmtCheckClient->execute([$client_email]);
                $client =$stmtCheckClient->fetch();

                if (!$client) {$default_password = password_hash('CTC12345', PASSWORD_BCRYPT);
                    $stmtClient =$pdo->prepare("INSERT INTO clients (nama, email, password) VALUES (?, ?, ?)");
                    $stmtClient->execute([$client_name, $client_email,$default_password]);
                    $client_id =$pdo->lastInsertId();
                } else {
                    $client_id =$client['id'];
                }

                // 2. Insert Data Requirement (Terhubung ke client_id)
                $stmtReq =$pdo->prepare("
                    INSERT INTO client_requirements 
                    (client_name, client_email, client_phone, company_name, title, description, file_attachment, budget_estimation, status, client_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
                ");
                $stmtReq->execute([$client_name, $client_email,$client_phone, $company_name,$title, $description,$file_attachment, $budget,$client_id
                ]);

                $req_id =$pdo->lastInsertId();

                // 3. Auto-Insert Pesan Pertama di Deal Chats
                $stmtChat =$pdo->prepare("
                    INSERT INTO deal_chats (requirement_id, sender_type, sender_name, message) 
                    VALUES (?, 'client', ?, ?)
                ");
                $stmtChat->execute([
                    $req_id,$client_name, 
                    "Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"{$title}\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi."
                ]);

                $pdo->commit();$success_msg = 'Pengajuan requirement proyek Anda berhasil terkirim! Kredensial akun Client Portal telah dibuat otomatis (Password Default: CTC12345).';
            } catch (Exception $e) {$pdo->rollBack();
                $error_msg = 'Terjadi kesalahan sistem saat memproses pengajuan: ' .$e->getMessage();
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
    <title>Pengajuan Requirement Proyek - PT. Cipta Teknologi Cendekia</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4 font-sans relative overflow-x-hidden">

    <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-emerald-600/10 blur-[120px] pointer-events-none rounded-full"></div>

    <div class="w-full max-w-2xl bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden my-8 relative z-10">
        
        <div class="p-6 md:p-8 bg-slate-900/80 border-b border-slate-800 text-center relative">
            <div class="flex justify-center items-center gap-3 mb-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 font-bold text-lg">
                    <i class="fa-solid fa-building"></i>
                </div>
                <div class="text-left">
                    <h1 class="text-base font-black text-white tracking-wide uppercase leading-tight">PT. CIPTA TEKNOLOGI CENDEKIA</h1>
                    <p class="text-[10px] text-slate-400 font-medium">Enterprise Software Development & IT Solution</p>
                </div>
            </div>
            <h2 class="text-lg font-bold text-slate-100 mt-4">Formulir Requirement & Proposal Request</h2>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">Sampaikan kebutuhan spesifikasi proyek Anda. Tim analis kami akan meninjau dokumen dan menyusun Surat Penawaran Resmi.</p>
        </div>

        <div class="p-6 md:p-8">
            <?php if (!empty($success_msg)): ?>
                <div class="mb-6 p-5 bg-emerald-950/90 border border-emerald-500/40 rounded-2xl text-emerald-300 text-xs flex items-start gap-3.5 shadow-lg">
                    <i class="fa-solid fa-circle-check text-xl text-emerald-400 shrink-0 mt-0.5"></i>
                    <div class="flex-1">
                        <strong class="block text-sm font-bold text-white mb-1">Pengajuan Berhasil Dikirim!</strong>
                        <p class="text-emerald-200/90 leading-relaxed mb-3"><?= htmlspecialchars($success_msg) ?></p>
                        <a href="login.php" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition shadow-md">
                            <i class="fa-solid fa-right-to-bracket"></i> Masuk ke Client Portal
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_msg)): ?>
                <div class="mb-6 p-4 bg-red-950/90 border border-red-500/40 rounded-2xl text-red-300 text-xs flex items-center gap-3 shadow-lg">
                    <i class="fa-solid fa-triangle-exclamation text-xl text-red-400 shrink-0"></i>
                    <span class="font-medium"><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="space-y-4 text-xs">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Nama Lengkap Penanggung Jawab <span class="text-red-400">*</span></label>
                        <input type="text" name="client_name" required placeholder="Contoh: Budi Santoso" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Alamat Email Resmi <span class="text-red-400">*</span></label>
                        <input type="email" name="client_email" required placeholder="budi@perusahaan.com" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Nomor Kontak / WhatsApp</label>
                        <input type="text" name="client_phone" placeholder="081234567890" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Nama Perusahaan / Instansi</label>
                        <input type="text" name="company_name" placeholder="PT. Maju Bersama" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                    </div>
                </div>

                <div class="pt-2"><hr class="border-slate-800"></div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Nama / Judul Sistem Proyek <span class="text-red-400">*</span></label>
                    <input type="text" name="title" required placeholder="Contoh: Pengadaan System Smart Parking & Mobile Application" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                </div>

                <div>
                    <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Deskripsi Ruang Lingkup & Fitur Utama <span class="text-red-400">*</span></label>
                    <textarea name="description" rows="4" required placeholder="Uraikan alur bisnis, modul-modul utama, atau fungsionalitas khusus yang ingin dikembangkan..." class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition leading-relaxed"></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Estimasi Alokasi Budget (Rp)</label>
                        <input type="number" name="budget_estimation" placeholder="50000000" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-800 bg-slate-950 text-white focus:outline-none focus:border-emerald-500 transition">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-300 uppercase tracking-wider mb-1">Lampiran Dokumen BRD / PDF</label>
                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.zip,.rar" class="w-full text-slate-400 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 cursor-pointer">
                        <span class="text-[10px] text-slate-500 block mt-1">Format: PDF, DOC, DOCX, ZIP, RAR (Max 10MB)</span>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" class="w-full py-3.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl transition shadow-lg shadow-emerald-950/50 text-xs flex items-center justify-center gap-2 uppercase tracking-wider">
                        <i class="fa-solid fa-paper-plane"></i> Kirim Requirement & Buat Akses Portal
                    </button>
                </div>
            </form>
        </div>
        
        <div class="p-4 bg-slate-950 border-t border-slate-800 text-center text-[11px] text-slate-500">
            &copy; <?= date('Y') ?> PT. Cipta Teknologi Cendekia. Safe & Confidential Portal.
        </div>
    </div>

</body>
</html>