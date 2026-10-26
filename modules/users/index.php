<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
require_once __DIR__ . '/../../config/database.php';

// 1. Proteksi Halaman: Wajib Login & Harus Role Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../my_tasks.php");
    exit;
}

// 2. Fetch Data User Login & Cek Hak Akses Super Admin (Primary Admin)
$loggedInUserId = (int)$_SESSION['user_id'];
$stmtMe = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtMe->execute([$loggedInUserId]);
$currentUser = $stmtMe->fetch();

// Super Admin / Primary Admin Utama ditentukan secara presisi berdasarkan email 'admin@gmail.com'
$isSuperAdmin = ($currentUser && strtolower($currentUser['email']) === 'admin@gmail.com');

$error = '';
$success = '';

// 3. Handle Tambah Karyawan / User Baru (KHUSUS PRIMARY ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    if (!$isSuperAdmin) {
        $error = "Akses Ditolak: Hanya Primary Admin (admin@gmail.com) yang berhak menambahkan anggota tim baru!";
    } else {
        $nama     = trim($_POST['nama'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $jabatan  = trim($_POST['jabatan'] ?? '');
        $role     = $_POST['role'] ?? 'karyawan';
        $password = password_hash('123456', PASSWORD_BCRYPT);

        if (!empty($nama) && !empty($email) && !empty($jabatan)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (nama, email, password, role, jabatan) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$nama, $email, $password, $role, $jabatan]);
                $success = "Anggota tim <strong>" . htmlspecialchars($nama) . "</strong> berhasil ditambahkan!";
            } catch (\PDOException $e) {
                $error = "Email <strong>" . htmlspecialchars($email) . "</strong> sudah terdaftar di sistem!";
            }
        } else {
            $error = "Mohon isi seluruh kolom wajib!";
        }
    }
}

// 4. Handle Hapus Karyawan / User (KHUSUS PRIMARY ADMIN)
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];

    // Ambil data user yang akan dihapus
    $stmtCheck = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$delete_id]);
    $userToDelete = $stmtCheck->fetch();

    if (!$isSuperAdmin) {
        $error = "Akses Ditolak: Hanya Primary Admin (admin@gmail.com) yang berhak menghapus anggota tim!";
    } elseif (!$userToDelete) {
        $error = "Data pengguna tidak ditemukan!";
    } elseif ($delete_id === $loggedInUserId) {
        $error = "Tindakan Ditolak: Anda tidak dapat menghapus akun Anda sendiri!";
    } elseif (strtolower($userToDelete['email']) === 'admin@gmail.com') {
        $error = "Tindakan Ditolak: Akun Primary Admin (admin@gmail.com) bersifat permanen dan tidak dapat dihapus!";
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDel->execute([$delete_id]);
        header("Location: index.php?msg=deleted");
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $success = "Anggota tim berhasil dihapus dari sistem.";
}

// 5. Fetch Semua Karyawan dengan Perhitungan Tugas Tim & Perorangan
$users = $pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT ta.task_id) AS total_assigned_tasks,
           SUM(CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END) AS completed_tasks
    FROM users u
    LEFT JOIN task_assignees ta ON u.id = ta.user_id
    LEFT JOIN tasks t ON ta.task_id = t.id
    GROUP BY u.id
    ORDER BY CASE WHEN LOWER(u.email) = 'admin@gmail.com' THEN 0 ELSE 1 END, u.role ASC, u.nama ASC
")->fetchAll();

// Hitung Statistik Ringkas
$totalPersonel = count($users);
$totalAdmin    = 0;
$totalKaryawan = 0;
foreach ($users as $u) {
    if (strtolower($u['role']) === 'admin') {
        $totalAdmin++;
    } else {
        $totalKaryawan++;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    body, input, select, button, h1, h2, h3, h4, h5, h6 { 
        font-family: 'Plus Jakarta Sans', sans-serif !important; 
    }

    .btn-press { 
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    .btn-press:active { 
        transform: translateY(1px); 
    }

    .input-glow { 
        transition: box-shadow .2s ease, border-color .2s ease; 
    }
    .input-glow:focus { 
        border-color: #059669; 
        box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); 
    }

    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation (Konsisten dengan Proposals) -->
    <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-0.5 rounded-full">
                    RESOURCE MANAGEMENT
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">Manajemen Tim & Karyawan</h1>
            <p class="text-xs sm:text-sm text-slate-500 font-normal mt-0.5">Kelola daftar tim, jobdesk/jabatan, dan alokasi personel PT. CTC</p>
        </div>

        <!-- Tombol Buka Modal Form Tambah Karyawan (Khusus Primary Admin) -->
        <?php if ($isSuperAdmin): ?>
            <button onclick="openUserModal()" class="btn-press bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs sm:text-sm px-4 py-2.5 rounded-xl transition shadow-xs flex items-center justify-center gap-2 shrink-0">
                <i class="fa-solid fa-user-plus text-xs sm:text-sm"></i> Tambah Karyawan Baru
            </button>
        <?php else: ?>
            <div class="px-3.5 py-2 bg-slate-100 border border-slate-200 text-slate-500 rounded-xl text-xs sm:text-sm font-semibold flex items-center gap-2 shrink-0">
                <i class="fa-solid fa-lock text-slate-400"></i> Mode Tinjau (Project Manager)
            </div>
        <?php endif; ?>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-red-500 text-base shrink-0"></i> 
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-xs sm:text-sm font-medium mb-6 flex items-center gap-2.5 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base shrink-0"></i> 
            <div><?= $success ?></div>
        </div>
    <?php endif; ?>

    <!-- Executive Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Total Personel Tim</span>
                <span class="text-2xl font-bold text-slate-900 mt-1 block"><?= $totalPersonel ?> Personel</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg border border-emerald-100">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Karyawan / Technical Staff</span>
                <span class="text-2xl font-bold text-blue-600 mt-1 block"><?= $totalKaryawan ?> Orang</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg border border-blue-100">
                <i class="fa-solid fa-user-gear"></i>
            </div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 sm:p-5 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-xs font-semibold uppercase text-slate-400 tracking-wider block">Project Manager / Admin</span>
                <span class="text-2xl font-bold text-purple-600 mt-1 block"><?= $totalAdmin ?> Personel</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg border border-purple-100">
                <i class="fa-solid fa-user-shield"></i>
            </div>
        </div>
    </div>

    <!-- Live Search & Filter Bar -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-4 mb-6 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="relative w-full sm:w-80">
            <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Cari nama, email, atau jabatan..." class="input-glow w-full px-3.5 py-2.5 pl-10 rounded-xl border border-slate-300 text-xs sm:text-sm font-normal text-slate-800 bg-white focus:outline-none placeholder:text-slate-400">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-magnifying-glass text-sm"></i>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
            <select id="roleFilterSelect" onchange="filterUsers()" class="input-glow px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs sm:text-sm font-semibold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                <option value="all">Semua Role</option>
                <option value="admin">Admin / PM</option>
                <option value="karyawan">Karyawan</option>
            </select>
        </div>
    </div>

    <!-- Tabel Daftar Karyawan Full Width & Clean -->
    <div class="bg-white border border-slate-200/90 rounded-2xl shadow-xs overflow-hidden">
        
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <h3 class="font-bold text-slate-800 text-xs sm:text-sm uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-address-book text-emerald-600"></i> Daftar Anggota Tim PT. CTC
            </h3>
        </div>

        <!-- Table Content -->
        <div class="w-full overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs sm:text-sm table-fixed min-w-[750px]" id="usersTable">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <th class="py-3.5 px-4 w-[28%]">NAMA & JABATAN</th>
                        <th class="py-3.5 px-4 w-[24%]">EMAIL</th>
                        <th class="py-3.5 px-4 w-[16%]">ROLE</th>
                        <th class="py-3.5 px-4 w-[20%]">BEBAN TUGAS</th>
                        <th class="py-3.5 px-4 w-[12%] text-center">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="usersTableBody">
                    <?php foreach ($users as $u): ?>
                        <?php 
                            $roleLower   = strtolower($u['role']);
                            $emailLower  = strtolower($u['email']);
                            $assignedT   = $u['total_assigned_tasks'] ?? 0;
                            $compT       = $u['completed_tasks'] ?? 0;
                            $isPrimary   = ($emailLower === 'admin@gmail.com');
                            $isSelf      = ((int)$u['id'] === $loggedInUserId);
                        ?>
                        <tr class="hover:bg-slate-50/70 transition user-row align-middle"
                            data-search="<?= htmlspecialchars(strtolower($u['nama'] . ' ' . $u['email'] . ' ' . $u['jabatan'])) ?>"
                            data-role="<?= htmlspecialchars($roleLower) ?>">
                            
                            <!-- Nama & Jabatan -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 font-bold flex items-center justify-center text-xs shrink-0 border border-emerald-200/60">
                                        <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 text-xs sm:text-sm truncate">
                                            <?= htmlspecialchars($u['nama']) ?>
                                            <?php if ($isSelf): ?>
                                                <span class="text-[9px] bg-emerald-100 text-emerald-800 px-1.5 py-0.5 rounded font-bold ml-1">Anda</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-xs text-slate-400 font-normal truncate mt-0.5"><?= htmlspecialchars($u['jabatan']) ?></p>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td class="py-3.5 px-4 text-slate-700 font-normal text-xs sm:text-sm truncate">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>

                            <!-- Role -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="px-2.5 py-1 text-xs font-semibold uppercase rounded-lg border tracking-wider inline-block
                                    <?= $roleLower === 'admin' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-100 text-slate-700 border-slate-200' ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>

                            <!-- Beban Tugas (Role Admin Diberikan Tanda Strip) -->
                            <td class="py-3.5 px-4 font-normal text-slate-700 text-xs sm:text-sm truncate">
                                <?php if ($roleLower === 'admin'): ?>
                                    <span class="text-slate-400 italic font-medium">-</span>
                                <?php else: ?>
                                    <span class="text-emerald-700 font-semibold"><?= $compT ?></span> / <?= $assignedT ?> Modul Selesai
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 text-center whitespace-nowrap">
                                <?php if ($isPrimary): ?>
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 text-xs font-semibold rounded-lg inline-block shadow-2xs">
                                        <i class="fa-solid fa-crown text-amber-500 mr-1"></i> Primary Admin
                                    </span>
                                <?php elseif ($isSuperAdmin && !$isSelf): ?>
                                    <a href="index.php?delete=<?= $u['id'] ?>" onclick="return confirm('Yakin ingin menghapus personel <?= htmlspecialchars(addslashes($u['nama'])) ?> dari sistem?')" class="btn-press p-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 rounded-xl transition inline-flex items-center justify-center" title="Hapus Personel">
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </a>
                                <?php elseif ($isSelf): ?>
                                    <span class="text-xs text-slate-400 font-normal italic">Akun Anda</span>
                                <?php else: ?>
                                    <span class="text-xs text-slate-400 font-normal italic"><i class="fa-solid fa-lock text-slate-300 mr-1"></i> Terkunci</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Controls -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3 text-xs text-slate-500 bg-slate-50/50">
            <span class="font-medium" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1.5" id="paginationControls"></div>
        </div>

    </div>

    <!-- MODAL POPUP FORM TAMBAH ANGGOTA TIM (KHUSUS PRIMARY ADMIN) -->
    <?php if ($isSuperAdmin): ?>
    <div id="userModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden relative text-xs sm:text-sm">
            
            <!-- Modal Header -->
            <div class="p-4 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-user-plus text-emerald-400 text-sm"></i>
                    <h3 class="text-xs font-bold uppercase tracking-wider">Tambah Anggota Tim Baru</h3>
                </div>
                <button onclick="closeUserModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="" method="POST" class="p-5 space-y-3.5">
                <input type="hidden" name="add_user" value="1">
                
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input type="text" name="nama" required placeholder="Contoh: Budi Pratama" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Email Karyawan <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required placeholder="budi@ctc.co.id" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Jabatan / Jobdesk <span class="text-red-500">*</span></label>
                    <input type="text" name="jabatan" required placeholder="Contoh: Frontend Developer / UI Designer" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-900 font-normal focus:outline-none placeholder:text-slate-400">
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5">Role Hak Akses</label>
                    <select name="role" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2.5 text-xs sm:text-sm text-slate-800 font-semibold focus:outline-none">
                        <option value="karyawan" selected>Karyawan / Technical Staff</option>
                        <option value="admin">Project Manager / System Admin</option>
                    </select>
                </div>

                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 text-xs text-slate-500 font-normal">
                    Password standar akun baru: <span class="font-mono text-slate-800 font-semibold">123456</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeUserModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs flex items-center gap-1.5">
                        <i class="fa-solid fa-plus text-xs"></i> Simpan Karyawan
                    </button>
                </div>
            </form>

        </div>
    </div>
    <?php endif; ?>

</main>

<script>
function openUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.remove('hidden');
}

function closeUserModal() {
    const modal = document.getElementById('userModal');
    if (modal) modal.classList.add('hidden');
}

const rowsPerPage = 5;
let currentPage = 1;
let filteredRows = [];

document.addEventListener('DOMContentLoaded', function() {
    filterUsers();
});

function filterUsers() {
    const searchVal = document.getElementById('userSearchInput').value.toLowerCase();
    const roleVal   = document.getElementById('roleFilterSelect').value.toLowerCase();
    const allRows   = Array.from(document.querySelectorAll('.user-row'));

    filteredRows = allRows.filter(row => {
        const searchData = row.getAttribute('data-search') || '';
        const roleData   = row.getAttribute('data-role') || '';

        const matchesSearch = searchData.includes(searchVal);
        const matchesRole   = (roleVal === 'all') || (roleData === roleVal);

        return matchesSearch && matchesRole;
    });

    currentPage = 1;
    renderPagination();
}

function renderPagination() {
    const allRows = document.querySelectorAll('.user-row');
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
    document.getElementById('paginationInfo').innerText = `Menampilkan ${displayStart}-${displayEnd} dari ${totalItems} personel`;

    const controls = document.getElementById('paginationControls');
    controls.innerHTML = '';

    if (totalPages <= 1) return;

    // Tombol Previous
    const prevBtn = document.createElement('button');
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left text-xs"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Angka Halaman (1, 2, 3...)
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-semibold text-xs ${i === currentPage ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    // Tombol Next
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right text-xs"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-semibold text-xs ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>