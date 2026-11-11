<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

// Guard Schema: Pastikan kolom master phone & company_name pada tabel clients siap
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN phone VARCHAR(50) NULL AFTER email");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE clients ADD COLUMN company_name VARCHAR(255) NULL AFTER phone");
} catch (Exception $e) {}

$msg = '';
$msg_type = 'success';

// Handle Action: Edit Information Client (Wajib Masukkan Admin Password Verification "admin123")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_client') {
    $client_id      = intval($_POST['client_id'] ?? 0);
    $nama           = trim($_POST['nama'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $company_name   = trim($_POST['company_name'] ?? '');
    $is_verified    = intval($_POST['is_verified'] ?? 1);
    $admin_password = $_POST['admin_password'] ?? '';

    // Verifikasi 2-Langkah Admin Password (Password default: "admin123")
    if ($admin_password !== 'admin123') {
        $msg = 'Password verifikasi admin salah! Perubahan data akun klien dibatalkan.';
        $msg_type = 'danger';
    } elseif ($client_id <= 0 || empty($nama) || empty($email)) {
        $msg = 'Nama lengkap dan alamat email tidak boleh kosong!';
        $msg_type = 'danger';
    } else {
        try {
            // 1. Ambil Email Lama sebelum di-update untuk relasi requirement
            $stmtOld = $pdo->prepare("SELECT email FROM clients WHERE id = ?");
            $stmtOld->execute([$client_id]);
            $oldEmail = $stmtOld->fetchColumn() ?: '';

            // 2. Update data master klien
            $stmtUpd = $pdo->prepare("
                UPDATE clients 
                SET nama = ?, email = ?, phone = ?, company_name = ?, is_verified = ?
                WHERE id = ?
            ");
            $stmtUpd->execute([$nama, $email, $phone, $company_name, $is_verified, $client_id]);

            // 3. Sinkronkan ke client_requirements agar data proyek & email tetap terhubung sempurna
            $stmtUpdReq = $pdo->prepare("
                UPDATE client_requirements 
                SET client_name = ?, client_email = ?, client_phone = ?, company_name = ?
                WHERE client_id = ? OR LOWER(client_email) = LOWER(?) OR LOWER(client_email) = LOWER(?)
            ");
            $stmtUpdReq->execute([$nama, $email, $phone, $company_name, $client_id, $email, $oldEmail]);

            $msg = "Data akun klien <strong>" . htmlspecialchars($nama) . "</strong> berhasil diperbarui & disinkronkan!";
            $msg_type = 'success';
        } catch (PDOException $e) {
            $msg = 'Terjadi kesalahan sistem saat memperbarui data: ' . $e->getMessage();
            $msg_type = 'danger';
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// FETCH DATA CLIENTS & METRIK REQUEST DARI DB
$stmtClients = $pdo->query("
    SELECT c.*, 
           COUNT(cr.id) AS total_requests,
           MAX(cr.created_at) AS last_request_at
    FROM clients c
    LEFT JOIN client_requirements cr ON (cr.client_id = c.id OR LOWER(cr.client_email) = LOWER(c.email))
    GROUP BY c.id
    ORDER BY c.id DESC
");
$clients = $stmtClients->fetchAll();

// Metrik Penghitung Klien
$totalClients     = count($clients);
$verifiedClients  = 0;
$unverifiedClients = 0;
$totalAllRequests = 0;

foreach ($clients as $c) {
    if ((int)$c['is_verified'] === 1) $verifiedClients++;
    else $unverifiedClients++;
    $totalAllRequests += (int)$c['total_requests'];
}
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }
    .btn-press { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .btn-press:active { transform: translateY(1px); }
    .input-glow:focus { border-color: #059669; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">

    <!-- Header Banner Navigation -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    CLIENT MASTER DATA & MANAGEMENT
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Manajemen Akun Klien Mitra</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Pantau daftar akun klien terdaftar, identitas instansi, histori pengajuan proyek, serta otentikasi data master.</p>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (!empty($msg)): ?>
        <div class="p-4 rounded-xl text-xs sm:text-sm font-semibold mb-6 flex items-center gap-3 shadow-xs border <?= $msg_type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-700' ?>">
            <i class="fa-solid <?= $msg_type === 'success' ? 'fa-circle-check text-emerald-600' : 'fa-triangle-exclamation text-red-500' ?> text-lg shrink-0"></i>
            <div><?= $msg ?></div>
        </div>
    <?php endif; ?>

    <!-- Metrics Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-slate-300 transition">
            <span class="text-[10px] font-bold uppercase text-slate-400 tracking-wider">TOTAL KLIEN TERDAFTAR</span>
            <p class="text-xl sm:text-2xl font-bold text-slate-900 mt-1 font-mono"><?= number_format($totalClients) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-emerald-200 transition">
            <span class="text-[10px] font-bold uppercase text-emerald-600 tracking-wider">AKUN TERVERIFIKASI</span>
            <p class="text-xl sm:text-2xl font-bold text-emerald-600 mt-1 font-mono"><?= number_format($verifiedClients) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-amber-200 transition">
            <span class="text-[10px] font-bold uppercase text-amber-600 tracking-wider">BELUM VERIFIKASI (OTP)</span>
            <p class="text-xl sm:text-2xl font-bold text-amber-600 mt-1 font-mono"><?= number_format($unverifiedClients) ?></p>
        </div>
        <div class="bg-white p-4 sm:p-5 rounded-2xl border border-slate-200/90 shadow-xs hover:border-blue-200 transition">
            <span class="text-[10px] font-bold uppercase text-blue-600 tracking-wider">TOTAL REQUEST PROYEK</span>
            <p class="text-xl sm:text-2xl font-bold text-blue-600 mt-1 font-mono"><?= number_format($totalAllRequests) ?></p>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="clientSearchInput" onkeyup="filterClients()" placeholder="Cari nama klien, email, atau instansi..." 
                   class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="statusFilterSelect" onchange="filterClients()" 
                    class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Status Akun</option>
                <option value="1">Terverifikasi</option>
                <option value="0">Belum Verifikasi</option>
            </select>
        </div>
    </div>

    <!-- Data Table Container -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm min-w-[950px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4">KLIEN & EMAIL</th>
                        <th class="py-3.5 px-4">KONTAK & INSTANSI</th>
                        <th class="py-3.5 px-4 text-center">STATUS AKUN</th>
                        <th class="py-3.5 px-4 text-center">PASSWORD</th>
                        <th class="py-3.5 px-4 text-center">TERDAFTAR SEJAK</th>
                        <th class="py-3.5 px-4 text-center">TOTAL REQUEST</th>
                        <th class="py-3.5 px-4 text-center whitespace-nowrap">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="clientTableBody">
                    <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="7" class="p-8 text-center text-slate-400 font-normal">
                                Belum ada akun klien terdaftar dalam sistem.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($clients as $c): ?>
                            <?php
                                $isVerif = (int)$c['is_verified'] === 1;
                                $phone   = !empty($c['phone']) ? $c['phone'] : '-';
                                $company = !empty($c['company_name']) ? $c['company_name'] : '-';
                            ?>
                            <tr class="hover:bg-slate-50/80 transition-all duration-150 client-row align-middle"
                                data-search="<?= htmlspecialchars(strtolower($c['nama'] . ' ' . $c['email'] . ' ' . $company . ' ' . $phone)) ?>"
                                data-status="<?= $c['is_verified'] ?>">

                                <!-- Nama & Email -->
                                <td class="py-3.5 px-4 max-w-[220px]">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-xl bg-slate-900 text-white flex items-center justify-center text-xs font-bold shrink-0">
                                            <?= strtoupper(substr($c['nama'], 0, 1)) ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-900 text-xs sm:text-sm truncate mb-0.5"><?= htmlspecialchars($c['nama']) ?></p>
                                            <p class="text-xs text-slate-400 font-normal truncate"><?= htmlspecialchars($c['email']) ?></p>
                                        </div>
                                    </div>
                                </td>

                                <!-- Kontak & Instansi -->
                                <td class="py-3.5 px-4 max-w-[220px]">
                                    <p class="font-semibold text-slate-800 text-xs truncate mb-0.5"><i class="fa-solid fa-phone text-[10px] text-emerald-600 mr-1"></i><?= htmlspecialchars($phone) ?></p>
                                    <p class="text-xs text-teal-700 font-semibold truncate"><i class="fa-solid fa-building text-[10px] text-teal-600 mr-1"></i><?= htmlspecialchars($company) ?></p>
                                </td>

                                <!-- Status Akun -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <?php if ($isVerif): ?>
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-300 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-circle-check text-emerald-600"></i> Terverifikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-300 inline-flex items-center gap-1">
                                            <i class="fa-solid fa-clock text-amber-600"></i> Pending OTP
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Password (Sensor & Lock Icon) -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-500 rounded-lg text-xs font-mono font-bold inline-flex items-center gap-1.5 border border-slate-200" title="Password terenkripsi & private (Didekripsi/Reset via fitur Lupa Password oleh Klien)">
                                        <i class="fa-solid fa-lock text-[10px] text-slate-400"></i> ••••••••
                                    </span>
                                </td>

                                <!-- Terdaftar Sejak -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap text-slate-500 text-xs">
                                    <?= !empty($c['created_at']) ? date('d M Y - H:i', strtotime($c['created_at'])) : '-' ?>
                                </td>

                                <!-- Total Request Proyek -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold font-mono bg-blue-50 text-blue-700 border border-blue-200">
                                        <?= (int)$c['total_requests'] ?> Requirement
                                    </span>
                                </td>

                                <!-- Aksi Edit Detail Klien -->
                                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                    <button type="button" 
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($c)) ?>)"
                                            class="btn-press px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs rounded-xl transition inline-flex items-center gap-1.5 shadow-xs">
                                        <i class="fa-solid fa-user-pen text-xs"></i> Edit Info
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500 bg-slate-50/50">
            <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1.5" id="paginationControls"></div>
        </div>
    </div>

</main>

<!-- MODAL EDIT INFORMASI KLIEN (DENGAN KUNCI VERIFIKASI 2-LANGKAH ADMIN PASSWORD "admin123") -->
<div id="editClientModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200/90 rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
        
        <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-user-gear text-emerald-400 text-base"></i>
                <h3 class="text-sm font-bold tracking-tight uppercase">Kelola Akun & Informasi Klien</h3>
            </div>
            <button type="button" onclick="closeEditModal()" class="text-slate-400 hover:text-white transition text-base">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="p-5 sm:p-6 space-y-4 text-xs">
            <input type="hidden" name="action" value="update_client">
            <input type="hidden" name="client_id" id="edit_client_id">

            <!-- Nama Lengkap -->
            <div>
                <label class="block font-semibold uppercase tracking-wider text-slate-700 mb-1">Nama Lengkap Klien <span class="text-red-500">*</span></label>
                <input type="text" name="nama" id="edit_nama" required 
                       class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-slate-900 font-medium focus:outline-none">
            </div>

            <!-- Email -->
            <div>
                <label class="block font-semibold uppercase tracking-wider text-slate-700 mb-1">Alamat Email Klien <span class="text-red-500">*</span></label>
                <input type="email" name="email" id="edit_email" required 
                       class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-slate-900 font-medium focus:outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Phone -->
                <div>
                    <label class="block font-semibold uppercase tracking-wider text-slate-700 mb-1">Nomor Telepon / WA</label>
                    <input type="text" name="phone" id="edit_phone" placeholder="08123456789"
                           class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-slate-900 font-medium focus:outline-none">
                </div>

                <!-- Company Name -->
                <div>
                    <label class="block font-semibold uppercase tracking-wider text-slate-700 mb-1">Nama Perusahaan / Instansi</label>
                    <input type="text" name="company_name" id="edit_company_name" placeholder="PT. Perusahaan"
                           class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-slate-900 font-medium focus:outline-none">
                </div>
            </div>

            <!-- Status Verifikasi Akun -->
            <div>
                <label class="block font-semibold uppercase tracking-wider text-slate-700 mb-1">Status Verifikasi Akun</label>
                <select name="is_verified" id="edit_is_verified" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-300 font-semibold text-slate-800 focus:outline-none">
                    <option value="1">Terverifikasi (Dapat Login & Diskusi)</option>
                    <option value="0">Belum Verifikasi (Perlu OTP)</option>
                </select>
            </div>

            <!-- Lock Info Password Client -->
            <div class="bg-slate-50 border border-slate-200 p-3 rounded-xl flex items-center justify-between text-slate-500">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-lock text-slate-400"></i>
                    <span class="font-medium">Password Akun Klien:</span>
                </span>
                <span class="font-mono font-bold text-slate-700">•••••••• (Privat)</span>
            </div>

            <!-- KUNCI VERIFIKASI 2-LANGKAH ADMIN (Wajib Masukkan 'admin123') -->
            <div class="bg-amber-50 border border-amber-200/90 p-3.5 rounded-xl space-y-2">
                <div class="flex items-center gap-2 text-amber-800 font-bold">
                    <i class="fa-solid fa-shield-halved text-amber-600 text-sm"></i>
                    <span>Verifikasi Otoritas Admin (2-Langkah)</span>
                </div>
                <p class="text-[11px] text-amber-700 leading-relaxed">
                    Masukkan password otorisasi admin untuk mengonfirmasi perubahan data master akun klien ini.
                </p>
                <input type="password" name="admin_password" required placeholder="Ketik password admin 'admin123'..."
                       class="input-glow w-full px-3.5 py-2 rounded-xl border border-amber-300 bg-white text-slate-900 font-medium focus:outline-none">
            </div>

            <!-- Buttons -->
            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                <button type="button" onclick="closeEditModal()" class="btn-press px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-semibold transition">
                    Batal
                </button>
                <button type="submit" class="btn-press px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-semibold uppercase tracking-wider transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    filterClients();
});

function filterClients() {
    const searchVal = document.getElementById('clientSearchInput').value.toLowerCase();
    const statusVal = document.getElementById('statusFilterSelect').value;
    const allRows   = Array.from(document.querySelectorAll('.client-row'));

    filteredRows = allRows.filter(row => {
        const searchData = row.getAttribute('data-search') || '';
        const statusData = row.getAttribute('data-status') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesStatus = (statusVal === 'all') || (statusData === statusVal);

        return matchesSearch && matchesStatus;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.client-row');
    allRows.forEach(row => row.style.display = 'none');

    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / rowsPerPage) || 1;

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx   = startIdx + rowsPerPage;

    filteredRows.slice(startIdx, endIdx).forEach(row => {
        row.style.display = 'table-row';
    });

    const displayStart = totalItems === 0 ? 0 : startIdx + 1;
    const displayEnd   = Math.min(endIdx, totalItems);
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} data`;

    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';

    if (totalPages <= 1) return;

    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}

function openEditModal(client) {
    document.getElementById('edit_client_id').value = client.id;
    document.getElementById('edit_nama').value = client.nama || '';
    document.getElementById('edit_email').value = client.email || '';
    document.getElementById('edit_phone').value = client.phone || '';
    document.getElementById('edit_company_name').value = client.company_name || '';
    document.getElementById('edit_is_verified').value = client.is_verified || 0;

    document.getElementById('editClientModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editClientModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>