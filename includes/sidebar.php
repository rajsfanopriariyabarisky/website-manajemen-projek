<?php
$baseUrl = "http://localhost/website%20manajemen%20projek";
$currentScript = $_SERVER['SCRIPT_NAME'];

// Hitung Requirement Klien bertipe 'Pending' untuk Badge Counter
$pendingReqCount = 0;
if (isset($pdo)) {
    try {
        $stmtPendingReq = $pdo->query("SELECT COUNT(*) FROM client_requirements WHERE status = 'Pending'");
        $pendingReqCount = (int) $stmtPendingReq->fetchColumn();
    } catch (Exception $e) {
        $pendingReqCount = 0;
    }
}

// LOGIKA BACKEND GANTI PASSWORD ADMIN / PM
$admin_pwd_error   = '';
$admin_pwd_success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_change_password_action'])) {
    if (isset($_SESSION['user_id']) && isset($pdo)) {
        $adm_user_id     = (int)$_SESSION['user_id'];
        $old_password    = $_POST['old_password'] ?? '';
        $new_password    = $_POST['new_password'] ?? '';
        $confirm_password= $_POST['confirm_password'] ?? '';

        if (!empty($old_password) && !empty($new_password) && !empty($confirm_password)) {
            if ($new_password !== $confirm_password) {
                $admin_pwd_error = "Konfirmasi kata sandi baru tidak cocok!";
            } elseif (strlen($new_password) < 6) {
                $admin_pwd_error = "Kata sandi baru minimal 6 karakter!";
            } else {
                $stmtCheckAdmin = $pdo->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
                $stmtCheckAdmin->execute([$adm_user_id]);
                $admData = $stmtCheckAdmin->fetch();

                if ($admData && (password_verify($old_password, $admData['password']) || md5($old_password) === $admData['password'] || $old_password === $admData['password'])) {
                    $hashed_new_pwd = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmtUpAdmPwd   = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmtUpAdmPwd->execute([$hashed_new_pwd, $adm_user_id]);

                    $admin_pwd_success = "Kata sandi akun Admin berhasil diperbarui!";
                } else {
                    $admin_pwd_error = "Kata sandi lama Anda salah!";
                }
            }
        } else {
            $admin_pwd_error = "Mohon isi seluruh kolom kata sandi!";
        }
    }
}
?>
<style>
    /* Custom Scrollbar Sidebar */
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(52, 211, 153, 0.35); border-radius: 99px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: rgba(52, 211, 153, 0.65); }

    /* Motion Micro-Interactions */
    .nav-item-link {
        transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        will-change: transform;
    }
    .nav-item-link:hover {
        transform: translateX(4px);
    }
    aside.is-collapsed .nav-item-link:hover {
        transform: scale(1.08);
    }
    .nav-icon {
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.2s ease;
    }
    .nav-item-link:hover .nav-icon {
        transform: scale(1.15) rotate(-3deg);
    }
    .btn-press {
        transition: transform 0.15s ease, background-color 0.15s ease;
    }
    .btn-press:active {
        transform: scale(0.96);
    }

    /* COLLAPSED / MINI MODE PADA DESKTOP (w-20 / 80px) */
    @media (min-width: 768px) {
        aside.is-collapsed {
            width: 5rem !important; /* 80px */
            padding-left: 0.625rem !important;
            padding-right: 0.625rem !important;
        }
        aside.is-collapsed .sidebar-text-group,
        aside.is-collapsed .category-label,
        aside.is-collapsed .header-expanded,
        aside.is-collapsed .user-expanded {
            display: none !important;
        }
        aside.is-collapsed .header-collapsed,
        aside.is-collapsed .user-collapsed {
            display: flex !important;
        }
        aside.is-collapsed .category-divider {
            display: block !important;
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin: 0.85rem 0;
        }
        aside.is-collapsed .nav-item-link {
            justify-content: center !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            width: 2.85rem !important;
            height: 2.85rem !important;
            margin: 0 auto !important;
        }
        aside.is-collapsed .sidebar-tooltip {
            display: block !important;
        }
    }
</style>

<!-- MOBILE TOPBAR HEADER (Hanya Tampil di Layar HP < 768px) -->
<div class="md:hidden sticky top-0 z-40 bg-[#064e3b] border-b border-emerald-700/60 px-4 py-3 flex items-center justify-between text-white shadow-md backdrop-blur-md">
    <div class="flex items-center gap-2.5">
        <img src="<?= $baseUrl ?>/assets/images/logo2.png" 
             onerror="this.onerror=null; this.src='<?= $baseUrl ?>/assets/images/logo2.png';" 
             alt="Logo PT CTC" 
             class="h-7 w-auto object-contain filter drop-shadow-[0_2px_4px_rgba(0,0,0,0.4)] brightness-110">
        <div>
            <h1 class="font-extrabold text-xs text-white leading-tight tracking-tight">PT. CTC</h1>
            <span class="text-[9px] text-emerald-300 font-semibold block">Cipta Teknologi Cendekia</span>
        </div>
    </div>
    <button type="button" onclick="toggleMobileSidebar()" title="Buka Menu"
        class="btn-press w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-emerald-200 hover:text-white flex items-center justify-center transition border border-white/20">
        <i class="fa-solid fa-bars text-sm"></i>
    </button>
</div>

<!-- BACKDROP OVERLAY UNTUK MOBILE DRAWER -->
<div id="mobileSidebarBackdrop" onclick="toggleMobileSidebar()" 
     class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-300 opacity-0"></div>

<!-- MAIN SIDEBAR (Modern Deep Emerald) -->
<aside id="mainSidebar" 
       class="fixed md:sticky top-0 left-0 h-screen bg-gradient-to-b from-[#064e3b] via-[#043829] to-[#02241a] border-r border-emerald-800/60 p-4 flex flex-col justify-between text-white shadow-xl z-50 select-none transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0 w-64 shrink-0">
    
    <!-- TOP SECTION: Header Logo & Navigasi -->
    <div class="flex flex-col h-full min-h-0">
        
        <!-- HEADER 1: Mode Buka (Expanded) & Mobile -->
        <div class="header-expanded flex items-center justify-between gap-2.5 px-3 py-2.5 mb-5 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-md shrink-0 shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <img src="<?= $baseUrl ?>/assets/images/logo2.png" 
                     onerror="this.onerror=null; this.src='<?= $baseUrl ?>/assets/images/logo2.png';" 
                     alt="Logo PT CTC" 
                     class="h-8 w-auto object-contain shrink-0 filter drop-shadow-[0_2px_5px_rgba(0,0,0,0.35)] brightness-110">
                
                <div class="sidebar-text-group min-w-0 leading-tight">
                    <h1 class="font-extrabold text-sm text-white tracking-tight">PT. CTC</h1>
                    <span class="text-[10px] text-emerald-300 font-semibold block leading-tight mt-0.5">Cipta Teknologi Cendekia</span>
                </div>
            </div>

            <!-- Tombol Tutup di Mobile & Desktop -->
            <button type="button" onclick="handleSidebarToggle()" title="Tutup Menu"
                class="btn-press w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-emerald-200 hover:text-white flex items-center justify-center transition border border-white/20 shrink-0">
                <i class="fa-solid fa-bars-staggered text-xs md:block hidden"></i>
                <i class="fa-solid fa-xmark text-sm md:hidden block"></i>
            </button>
        </div>

        <!-- HEADER 2: Mode Tutup (Collapsed Header Khusus Desktop) -->
        <div class="header-collapsed hidden flex-col items-center justify-center p-2 mb-5 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-md shrink-0 shadow-sm">
            <button type="button" onclick="toggleSidebar()" title="Klik untuk Buka Sidebar" class="relative group cursor-pointer">
                <img src="<?= $baseUrl ?>/assets/images/logo2.png" 
                     onerror="this.onerror=null; this.src='<?= $baseUrl ?>/assets/images/logo2.png';" 
                     alt="Logo PT CTC" 
                     class="h-8 w-auto object-contain transition group-hover:scale-105 filter drop-shadow-[0_2px_5px_rgba(0,0,0,0.35)] brightness-110">
                <div class="absolute -bottom-1 -right-1 w-4 h-4 rounded-md bg-emerald-500 text-slate-950 flex items-center justify-center text-[8px] font-extrabold shadow-xs">
                    <i class="fa-solid fa-angles-right"></i>
                </div>
            </button>
        </div>

        <!-- Area Menu Navigasi (Independent Scroll) -->
        <div class="flex-1 overflow-y-auto sidebar-scroll pr-1 space-y-4">
            
            <!-- SEKSI 1: UTAMA -->
            <div>
                <a href="<?= $baseUrl ?>/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="nav-icon fa-solid fa-gauge-high w-4 text-center text-sm <?= strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false ? 'text-white' : 'text-emerald-300' ?>"></i>
                        <span class="sidebar-text-group truncate">Dashboard</span>
                    </div>
                    <?php if (strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false): ?>
                        <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                    <?php endif; ?>
                    <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                        Dashboard Utama
                    </div>
                </a>
            </div>

            <!-- SEKSI 2: DOKUMEN LEGAL & INISIASI PROYEK -->
            <div>
                <span class="category-label px-3 text-[10px] font-extrabold uppercase tracking-wider text-emerald-300/80 block mb-1.5">Dokumen Legal</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">
                    
                    <!-- 1. KEBUTUHAN KLIEN -->
                    <a href="<?= $baseUrl ?>/modules/requirements/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/requirements/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-inbox w-4 text-center text-sm <?= strpos($currentScript, '/requirements/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Kebutuhan Klien</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <?php if ($pendingReqCount > 0): ?>
                                <span class="sidebar-text-group px-2 py-0.5 text-[10px] font-extrabold bg-red-500 text-white rounded-full shadow-xs animate-pulse">
                                    <?= $pendingReqCount ?>
                                </span>
                            <?php elseif (strpos($currentScript, '/requirements/') !== false): ?>
                                <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                            <?php endif; ?>
                        </div>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Kebutuhan Klien <?= $pendingReqCount > 0 ? "({$pendingReqCount})" : '' ?>
                        </div>
                    </a>

                    <!-- 2. SURAT PENAWARAN -->
                    <a href="<?= $baseUrl ?>/modules/proposals/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/proposals/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-signature w-4 text-center text-sm <?= strpos($currentScript, '/proposals/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Surat Penawaran</span>
                        </div>
                        <?php if (strpos($currentScript, '/proposals/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Surat Penawaran
                        </div>
                    </a>

                    <!-- 3. CETAK KONTRAK PDF -->
                    <a href="<?= $baseUrl ?>/modules/contracts/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/contracts/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-contract w-4 text-center text-sm <?= strpos($currentScript, '/contracts/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Cetak Kontrak PDF</span>
                        </div>
                        <?php if (strpos($currentScript, '/contracts/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Cetak Kontrak PDF
                        </div>
                    </a>

                </div>
            </div>

            <!-- SEKSI 3: MANAJEMEN OPERASIONAL & PENGERJAAN -->
            <div>
                <span class="category-label px-3 text-[10px] font-extrabold uppercase tracking-wider text-emerald-300/80 block mb-1.5">Manajemen Operasional</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">

                    <!-- 1. PROYEK & TIMELINE (Pusat Data Proyek Aktif) -->
                    <a href="<?= $baseUrl ?>/modules/projects/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/projects/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-folder-tree w-4 text-center text-sm <?= strpos($currentScript, '/projects/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Proyek & Timeline</span>
                        </div>
                        <?php if (strpos($currentScript, '/projects/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Proyek & Timeline
                        </div>
                    </a>

                    <!-- 2. WORKLOAD & PRIORITY (Pembagian Tugas Modul Proyek) -->
                    <a href="<?= $baseUrl ?>/modules/tasks/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/tasks/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-list-check w-4 text-center text-sm <?= strpos($currentScript, '/tasks/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Workload & Priority</span>
                        </div>
                        <?php if (strpos($currentScript, '/tasks/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Workload & Priority
                        </div>
                    </a>

                    <!-- 3. MANAJEMEN TIM (Pengaturan SDM / User) -->
                    <a href="<?= $baseUrl ?>/modules/users/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/users/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-users-gear w-4 text-center text-sm <?= strpos($currentScript, '/users/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Manajemen Tim</span>
                        </div>
                        <?php if (strpos($currentScript, '/users/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Manajemen Tim
                        </div>
                    </a>

                </div>
            </div>

            <!-- SEKSI 4: KEUANGAN & BILLING -->
            <div>
                <span class="category-label px-3 text-[10px] font-extrabold uppercase tracking-wider text-emerald-300/80 block mb-1.5">Keuangan & Billing</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">
                    
                    <!-- 1. INVOICE & BILLING (Penagihan Termin / Dana Masuk) -->
                    <a href="<?= $baseUrl ?>/modules/invoices/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/invoices/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-invoice-dollar w-4 text-center text-sm <?= strpos($currentScript, '/invoices/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Invoice & Billing</span>
                        </div>
                        <?php if (strpos($currentScript, '/invoices/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Invoice & Billing Klien
                        </div>
                    </a>

                    <!-- 2. TRACKING BIAYA (Pencatatan Biaya Operasional / Expenses) -->
                    <a href="<?= $baseUrl ?>/modules/expenses/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/expenses/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-wallet w-4 text-center text-sm <?= strpos($currentScript, '/expenses/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Tracking Biaya</span>
                        </div>
                        <?php if (strpos($currentScript, '/expenses/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Tracking Biaya Operasional
                        </div>
                    </a>

                    <!-- 3. LAPORAN KEUANGAN (Rekap Laba Rugi & Export Excel) -->
                    <a href="<?= $baseUrl ?>/modules/reports/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs border <?= strpos($currentScript, '/reports/') !== false ? 'bg-gradient-to-r from-emerald-600 to-emerald-500 border-emerald-400/60 text-white font-bold shadow-lg shadow-emerald-950/40' : 'border-transparent text-emerald-100/90 hover:bg-white/10 hover:text-white font-semibold' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-chart-pie w-4 text-center text-sm <?= strpos($currentScript, '/reports/') !== false ? 'text-white' : 'text-emerald-300' ?>"></i>
                            <span class="sidebar-text-group truncate">Laporan Keuangan</span>
                        </div>
                        <?php if (strpos($currentScript, '/reports/') !== false): ?>
                            <span class="sidebar-text-group w-2 h-2 rounded-full bg-white shadow-xs animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-emerald-500/30 text-white text-xs font-semibold rounded-xl shadow-xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Laporan Keuangan & Profit
                        </div>
                    </a>

                </div>
            </div>

        </div>
    </div>

    <!-- BOTTOM SECTION: Profil User & Action Buttons -->
    <div class="pt-3 mt-3 border-t border-white/15 shrink-0">
        
        <!-- PROFIL MODE BUKA -->
        <div class="user-expanded flex items-center justify-between p-2.5 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-md">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="relative shrink-0">
                    <div class="w-8 h-8 rounded-xl bg-emerald-500 text-slate-950 font-extrabold flex items-center justify-center text-xs shadow-xs">
                        <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?>
                    </div>
                    <span class="w-2 h-2 rounded-full bg-emerald-400 border-2 border-[#064e3b] absolute -bottom-0.5 -right-0.5"></span>
                </div>
                <div class="sidebar-text-group min-w-0 leading-tight">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></p>
                    <p class="text-[10px] text-emerald-200/90 capitalize font-medium truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Role') ?></p>
                </div>
            </div>
            
            <div class="flex items-center gap-1 shrink-0">
                <!-- Tombol Ganti Password -->
                <button type="button" onclick="openAdminPwdModal()" title="Ganti Kata Sandi"
                        class="btn-press w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-emerald-200 hover:text-white flex items-center justify-center transition border border-white/20">
                    <i class="fa-solid fa-key text-[10px]"></i>
                </button>

                <!-- Tombol Logout -->
                <a href="<?= $baseUrl ?>/logout.php" title="Keluar Sistem" 
                   class="btn-press w-7 h-7 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-200 hover:text-white flex items-center justify-center transition border border-red-500/30">
                    <i class="fa-solid fa-right-from-bracket text-[10px]"></i>
                </a>
            </div>
        </div>

        <!-- PROFIL MODE TUTUP (Desktop) -->
        <div class="user-collapsed hidden flex-col items-center gap-2 p-2 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-md">
            <div class="relative shrink-0">
                <div class="w-8 h-8 rounded-xl bg-emerald-500 text-slate-950 font-extrabold flex items-center justify-center text-xs shadow-xs">
                    <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?>
                </div>
                <span class="w-2 h-2 rounded-full bg-emerald-400 border-2 border-[#064e3b] absolute -bottom-0.5 -right-0.5"></span>
            </div>
            
            <!-- Tombol Ganti Password -->
            <button type="button" onclick="openAdminPwdModal()" title="Ganti Kata Sandi" 
               class="btn-press w-7 h-7 rounded-lg bg-white/10 hover:bg-white/20 text-emerald-200 hover:text-white flex items-center justify-center transition border border-white/20">
                <i class="fa-solid fa-key text-[10px]"></i>
            </button>

            <!-- Tombol Logout -->
            <a href="<?= $baseUrl ?>/logout.php" title="Keluar Sistem" 
               class="btn-press w-7 h-7 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-200 hover:text-white flex items-center justify-center transition border border-red-500/30">
                <i class="fa-solid fa-right-from-bracket text-[10px]"></i>
            </a>
        </div>

    </div>

</aside>

<!-- ========================================================================= -->
<!-- MODAL POPUP FORM GANTI PASSWORD ADMIN / PM -->
<!-- ========================================================================= -->
<div id="adminChangePasswordModal" class="fixed inset-0 z-[9999] bg-slate-950/60 backdrop-blur-xs hidden flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden relative text-xs sm:text-sm text-slate-800">
        
        <!-- Header Modal -->
        <div class="p-4 border-b border-slate-100 flex items-center justify-between bg-[#064e3b] text-white">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-key text-emerald-400 text-sm"></i>
                <h3 class="font-bold text-sm">Ganti Kata Sandi Admin</h3>
            </div>
            <button type="button" onclick="closeAdminPwdModal()" class="w-7 h-7 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition focus:outline-none">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        <!-- Form Body -->
        <form action="" method="POST" class="p-5 space-y-3.5">
            <input type="hidden" name="admin_change_password_action" value="1">

            <!-- Alert Notifikasi Di Dalam Modal -->
            <?php if (!empty($admin_pwd_error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-red-500 text-xs"></i>
                    <div><?= $admin_pwd_error ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($admin_pwd_success)): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-3 py-2 rounded-xl text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-xs"></i>
                    <div><?= $admin_pwd_success ?></div>
                </div>
            <?php endif; ?>

            <!-- Password Saat Ini -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Kata Sandi Saat Ini *</label>
                <div class="relative flex items-center">
                    <input type="password" id="admCurPwdInput" name="old_password" required placeholder="••••••••" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-10 text-xs text-slate-900 font-semibold focus:outline-none">
                    <i class="fa-solid fa-lock absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    <button type="button" onclick="toggleAdminPwdVisibility('admCurPwdInput', 'admCurPwdIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                        <i id="admCurPwdIcon" class="fa-regular fa-eye text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Password Baru -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Kata Sandi Baru *</label>
                <div class="relative flex items-center">
                    <input type="password" id="admNewPwdInput" name="new_password" required minlength="6" placeholder="Minimal 6 karakter" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-10 text-xs text-slate-900 font-semibold focus:outline-none">
                    <i class="fa-solid fa-shield-halved absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    <button type="button" onclick="toggleAdminPwdVisibility('admNewPwdInput', 'admNewPwdIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                        <i id="admNewPwdIcon" class="fa-regular fa-eye text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Konfirmasi Password Baru -->
            <div>
                <label class="block text-xs font-medium text-slate-700 mb-1">Konfirmasi Kata Sandi Baru *</label>
                <div class="relative flex items-center">
                    <input type="password" id="admConfirmPwdInput" name="confirm_password" required minlength="6" placeholder="Ulangi kata sandi baru" class="input-glow w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 pl-9 pr-10 text-xs text-slate-900 font-semibold focus:outline-none">
                    <i class="fa-solid fa-circle-check absolute left-3 text-slate-400 text-xs pointer-events-none"></i>
                    <button type="button" onclick="toggleAdminPwdVisibility('admConfirmPwdInput', 'admConfirmPwdIcon')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-emerald-600 focus:outline-none p-1 transition flex items-center justify-center">
                        <i id="admConfirmPwdIcon" class="fa-regular fa-eye text-xs"></i>
                    </button>
                </div>
            </div>

            <div class="bg-slate-50 border border-slate-200/80 rounded-xl p-2.5 text-[10px] text-slate-500 font-medium flex items-center gap-1.5">
                <i class="fa-solid fa-lock text-emerald-600"></i> Terenkripsi & Aman dengan BCRYPT.
            </div>

            <div class="flex items-center justify-end gap-2 pt-1">
                <button type="button" onclick="closeAdminPwdModal()" class="btn-press px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-xs transition">
                    Batal
                </button>
                <button type="submit" class="btn-press px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-xl text-xs shadow-xs transition">
                    Simpan Kata Sandi
                </button>
            </div>
        </form>

    </div>
</div>

<!-- SCRIPT LOGIC RESPONSIF SIDEBAR & MEMORI LOCALSTORAGE -->
<script>
    function openAdminPwdModal() {
        const modal = document.getElementById('adminChangePasswordModal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeAdminPwdModal() {
        const modal = document.getElementById('adminChangePasswordModal');
        if (modal) modal.classList.add('hidden');
    }

    function toggleAdminPwdVisibility(inputId, iconId) {
        const pwdInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);
        
        if (pwdInput && toggleIcon) {
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                pwdInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    }

    (function() {
        const sidebar = document.getElementById('mainSidebar');
        const backdrop = document.getElementById('mobileSidebarBackdrop');

        // Restore state collapse khusus Layar Desktop
        if (window.innerWidth >= 768) {
            const isCollapsed = localStorage.getItem('sidebar_collapsed') === 'true';
            if (isCollapsed) {
                sidebar.classList.add('is-collapsed');
            }
        }

        // Toggle khusus Mobile
        window.toggleMobileSidebar = function() {
            const isOpen = !sidebar.classList.contains('-translate-x-full');
            if (isOpen) {
                sidebar.classList.add('-translate-x-full');
                backdrop.classList.add('opacity-0');
                setTimeout(() => backdrop.classList.add('hidden'), 300);
            } else {
                backdrop.classList.remove('hidden');
                requestAnimationFrame(() => {
                    backdrop.classList.remove('opacity-0');
                    sidebar.classList.remove('-translate-x-full');
                });
            }
        };

        // Toggle khusus Desktop
        window.toggleSidebar = function() {
            const nowCollapsed = sidebar.classList.toggle('is-collapsed');
            localStorage.setItem('sidebar_collapsed', nowCollapsed ? 'true' : 'false');
        };

        // Router fungsi toggle berdasarkan ukuran layar
        window.handleSidebarToggle = function() {
            if (window.innerWidth < 768) {
                toggleMobileSidebar();
            } else {
                toggleSidebar();
            }
        };
    })();
</script>

<?php if (!empty($admin_pwd_error) || !empty($admin_pwd_success)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        openAdminPwdModal();
    });
</script>
<?php endif; ?>