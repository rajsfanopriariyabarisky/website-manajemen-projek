<?php
session_start();
require_once __DIR__ . '/../../config/database.php';

// Proteksi Halaman: Wajib Login
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit;
}

$error = '';$success = '';

// Handle Tambah Karyawan Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {$nama     = trim($_POST['nama']);$email    = trim($_POST['email']);$jabatan  = trim($_POST['jabatan']);$role     = $_POST['role'];$password = password_hash('123456', PASSWORD_BCRYPT);

    if (!empty($nama) && !empty($email) && !empty($jabatan)) {
        try {
            $stmt =$pdo->prepare("INSERT INTO users (nama, email, password, role, jabatan) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$nama, $email,$password, $role,$jabatan]);
            $success = "Karyawan $nama berhasil ditambahkan!";
        } catch (\PDOException $e) {$error = "Email sudah terdaftar di sistem!";
        }
    } else {
        $error = "Mohon isi seluruh kolom wajib!";
    }
}

// Handle Hapus Karyawan
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id != 1) {
        $stmtDel =$pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmtDel->execute([$delete_id]);
        header("Location: index.php");
        exit;
    }
}

// Fetch Semua Karyawan dengan Perhitungan Tugas Tim & Perorangan
$users =$pdo->query("
    SELECT u.*, 
           COUNT(DISTINCT ta.task_id) AS total_assigned_tasks,
           SUM(CASE WHEN t.status = 'Done' THEN 1 ELSE 0 END) AS completed_tasks
    FROM users u
    LEFT JOIN task_assignees ta ON u.id = ta.user_id
    LEFT JOIN tasks t ON ta.task_id = t.id
    GROUP BY u.id
    ORDER BY u.role ASC, u.nama ASC
")->fetchAll();

// Hitung Statistik Ringkas
$totalPersonel = count($users);$totalAdmin    = 0;
$totalKaryawan = 0;
foreach ($users as$u) {
    if (strtolower($u['role']) === 'admin') {$totalAdmin++;
    } else {
        $totalKaryawan++;
    }
}

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<style>
    .btn-press { transition: transform .15s ease, background-color .2s ease, box-shadow .2s ease; }
    .btn-press:active { transform: scale(.96); }

    .input-glow { transition: box-shadow .2s ease, border-color .2s ease; }
    .input-glow:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.15); }

    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>

<main class="flex-1 p-4 sm:p-6 md:p-8 overflow-y-auto w-full min-w-0 bg-[#f8fafc] text-slate-800 font-sans selection:bg-emerald-600 selection:text-white">
    
    <!-- Header Banner Navigation & Action Button -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 bg-white p-5 sm:p-6 rounded-2xl border border-slate-200/90 shadow-2xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="text-[10px] font-black uppercase tracking-[0.18em] text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-lg">
                    RESOURCE MANAGEMENT
                </span>
            </div>
            <h1 class="text-xl md:text-2xl font-black text-slate-900 tracking-tight">Manajemen Tim & Karyawan</h1>
            <p class="text-xs text-slate-500 font-medium mt-0.5">Kelola daftar tim, jobdesk/jabatan, dan alokasi personel PT. CTC</p>
        </div>

        <!-- Tombol Buka Modal Form Tambah Karyawan -->
        <button onclick="openUserModal()" class="btn-press px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl transition flex items-center justify-center gap-2 shadow-md shadow-emerald-600/20 shrink-0 uppercase tracking-wider">
            <i class="fa-solid fa-user-plus text-xs"></i> Tambah Karyawan Baru
        </button>
    </div>

    <!-- Alert Notifications -->
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-2xs">
            <i class="fa-solid fa-triangle-exclamation text-base text-red-600"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl text-xs font-bold mb-6 flex items-center gap-2.5 shadow-2xs">
            <i class="fa-solid fa-circle-check text-base text-emerald-600"></i> <?= $success ?>
        </div>
    <?php endif; ?>

    <!-- Executive Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Total Personel Tim</span>
                <span class="text-xl font-black text-slate-900 mt-0.5 block"><?= $totalPersonel ?> Personel</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg border border-emerald-100">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Karyawan / Technical Staff</span>
                <span class="text-xl font-black text-blue-700 mt-0.5 block"><?= $totalKaryawan ?> Orang</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg border border-blue-100">
                <i class="fa-solid fa-user-gear"></i>
            </div>
        </div>

        <div class="bg-white border border-slate-200/90 rounded-2xl p-4 shadow-2xs flex items-center justify-between">
            <div>
                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block">Project Manager / Admin</span>
                <span class="text-xl font-black text-purple-700 mt-0.5 block"><?= $totalAdmin ?> Personel</span>
            </div>
            <div class="w-11 h-11 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg border border-purple-100">
                <i class="fa-solid fa-user-shield"></i>
            </div>
        </div>
    </div>

    <!-- Tabel Daftar Karyawan Full Width & Clean -->
    <div class="bg-white border border-slate-200/90 rounded-2xl p-5 sm:p-6 shadow-2xs">
        
        <!-- Header Tabel & Search / Filter Bar -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 pb-4 mb-4 border-b border-slate-100">
            <div>
                <h3 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-emerald-600"></i> Daftar Anggota Tim PT. CTC
                </h3>
                <p class="text-xs text-slate-400 font-medium">Monitoring hak akses dan alokasi penugasan modul karyawan</p>
            </div>

            <!-- Live Search & Filter Role -->
            <div class="flex items-center gap-2.5 shrink-0 flex-wrap sm:flex-nowrap w-full sm:w-auto">
                <div class="relative w-full sm:w-56">
                    <input type="text" id="userSearchInput" onkeyup="filterUsers()" placeholder="Cari nama / email / job..." class="input-glow w-full px-3.5 py-2 pl-9 rounded-xl border border-slate-200 text-xs focus:outline-none font-medium">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>

                <select id="roleFilterSelect" onchange="filterUsers()" class="input-glow px-3 py-2 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none w-full sm:w-auto">
                    <option value="all">Semua Role</option>
                    <option value="admin">Admin / PM</option>
                    <option value="karyawan">Karyawan</option>
                </select>
            </div>
        </div>

        <!-- Table Content -->
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[650px]" id="usersTable">
                <thead class="uppercase bg-slate-50 text-slate-500 font-black border-b border-slate-200 text-[10px] tracking-wider">
                    <tr>
                        <th class="py-3.5 px-4">Nama & Jabatan</th>
                        <th class="py-3.5 px-4">Email</th>
                        <th class="py-3.5 px-4">Role</th>
                        <th class="py-3.5 px-4">Beban Tugas (Multi-Team)</th>
                        <th class="py-3.5 px-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="usersTableBody">
                    <?php foreach ($users as$u): ?>
                        <?php 
                            $roleLower = strtolower($u['role']);
                            $assignedT =$u['total_assigned_tasks'] ?? 0;
                            $compT     =$u['completed_tasks'] ?? 0;
                        ?>
                        <tr class="user-row hover:bg-slate-50/80 transition align-middle"
                            data-search="<?= htmlspecialchars(strtolower($u['nama'] . ' ' . $u['email'] . ' ' .$u['jabatan'])) ?>"
                            data-role="<?= htmlspecialchars($roleLower) ?>">
                            
                            <!-- Nama & Jabatan -->
                            <td class="py-3.5 px-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 font-black flex items-center justify-center text-xs shrink-0 shadow-2xs border border-emerald-200/60">
                                        <?= strtoupper(substr($u['nama'], 0, 1)) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-extrabold text-slate-900 text-xs sm:text-sm leading-tight truncate"><?= htmlspecialchars($u['nama']) ?></p>
                                        <span class="text-[10px] text-slate-400 font-bold block truncate"><?= htmlspecialchars($u['jabatan']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <!-- Email -->
                            <td class="py-3.5 px-4 text-slate-600 font-bold">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>

                            <!-- Role -->
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 text-[9px] font-black uppercase rounded-lg border tracking-wider inline-block
                                    <?= $roleLower === 'admin' ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-100 text-slate-700 border-slate-200' ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                            </td>

                            <!-- Beban Tugas -->
                            <td class="py-3.5 px-4 font-bold text-slate-800">
                                <span class="text-emerald-700 font-extrabold"><?= $compT ?></span> / <?= $assignedT ?> Modul Selesai
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-4 text-center">
                                <?php if ($u['id'] != 1): ?>
                                    <a href="index.php?delete=<?= $u['id'] ?>" onclick="return confirm('Yakin ingin menghapus karyawan ini?')" class="btn-press p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-xl transition inline-block" title="Hapus Personel">
                                        <i class="fa-regular fa-trash-can text-sm"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400 font-bold italic">Primary Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginasi Interaktif (1, 2, 3...) -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-3 pt-4 mt-4 border-t border-slate-100 text-xs">
            <span class="text-slate-500 font-bold text-[11px]" id="paginationInfo">Menampilkan 0 data</span>
            <div class="flex items-center gap-1" id="paginationControls"></div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL POPUP FORM TAMBAH ANGGOTA TIM (CLEAN & ELEGANT) -->
    <!-- ========================================================================= -->
    <div id="userModal" class="fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden relative animate-in fade-in zoom-in-95 duration-200">
            
            <!-- Modal Header -->
            <div class="p-5 bg-slate-900 text-white flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center text-sm font-bold">
                        <i class="fa-solid fa-user-plus"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-black uppercase tracking-wider text-white">Tambah Anggota Tim Baru</h3>
                        <p class="text-[10px] text-slate-400 font-medium">Lengkapi data akun personel baru PT. CTC</p>
                    </div>
                </div>
                <button onclick="closeUserModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Modal Form Body -->
            <form action="" method="POST" class="p-5 space-y-4 text-xs">
                <input type="hidden" name="add_user" value="1">
                
                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Nama Lengkap *</label>
                    <input type="text" name="nama" required placeholder="Contoh: Budi Pratama" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Email Karyawan *</label>
                    <input type="email" name="email" required placeholder="budi@ctc.co.id" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Jabatan / Jobdesk *</label>
                    <input type="text" name="jabatan" required placeholder="Contoh: Frontend Developer / UI Designer" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-bold focus:outline-none">
                </div>

                <div>
                    <label class="block text-[10px] font-black uppercase text-slate-400 mb-1">Role Hak Akses</label>
                    <select name="role" class="input-glow w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-extrabold text-slate-700 bg-white focus:outline-none">
                        <option value="karyawan" selected>Karyawan / Technical Staff</option>
                        <option value="admin">Project Manager / System Admin</option>
                    </select>
                </div>

                <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-3 text-[10px] text-slate-500 font-medium">
                    Password standar akun baru: <span class="font-mono text-slate-800 font-extrabold">123456</span>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closeUserModal()" class="btn-press px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-extrabold rounded-xl transition">
                        Batal
                    </button>
                    <button type="submit" class="btn-press px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl transition shadow-md shadow-emerald-600/20 flex items-center gap-2 uppercase tracking-wider">
                        <i class="fa-solid fa-plus text-xs"></i> Simpan Karyawan
                    </button>
                </div>
            </form>

        </div>
    </div>

</main>

<script>
function openUserModal() {
    document.getElementById('userModal').classList.remove('hidden');
}

function closeUserModal() {
    document.getElementById('userModal').classList.add('hidden');
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
    prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
    prevBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === 1 ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => { if(currentPage > 1) { currentPage--; renderPagination(); } };
    controls.appendChild(prevBtn);

    // Angka Halaman (1, 2, 3...)
    for (let i = 1; i <= totalPages; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.innerText = i;
        pageBtn.className = `btn-press px-3 py-1 rounded-lg font-bold ${i === currentPage ? 'bg-emerald-600 text-white shadow-2xs' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
        pageBtn.onclick = () => { currentPage = i; renderPagination(); };
        controls.appendChild(pageBtn);
    }

    // Tombol Next
    const nextBtn = document.createElement('button');
    nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
    nextBtn.className = `btn-press px-2.5 py-1 rounded-lg font-bold ${currentPage === totalPages ? 'bg-slate-100 text-slate-300 cursor-not-allowed' : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-50'}`;
    nextBtn.disabled = currentPage === totalPages;
    nextBtn.onclick = () => { if(currentPage < totalPages) { currentPage++; renderPagination(); } };
    controls.appendChild(nextBtn);
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>