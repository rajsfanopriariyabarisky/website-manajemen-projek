<?php
$baseUrl = "http://localhost/website%20manajemen%20projek";
$currentScript = $_SERVER['SCRIPT_NAME'];
?>
<style>
    /* Custom Scrollbar Sidebar */
    .sidebar-scroll::-webkit-scrollbar { width: 4px; }
    .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
    .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(16, 185, 129, 0.25); border-radius: 99px; }
    .sidebar-scroll::-webkit-scrollbar-thumb:hover { background: rgba(16, 185, 129, 0.5); }

    /* Motion Micro-Interactions */
    .nav-item-link {
        transition: transform 0.22s cubic-bezier(0.22, 0.68, 0, 1), background-color 0.22s ease, color 0.22s ease, box-shadow 0.22s ease;
        will-change: transform;
    }
    .nav-item-link:hover {
        transform: translateX(4px);
    }
    aside.is-collapsed .nav-item-link:hover {
        transform: scale(1.1);
    }
    .nav-icon {
        transition: transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1), color 0.22s ease;
    }
    .nav-item-link:hover .nav-icon {
        transform: scale(1.15) rotate(-3deg);
        color: #6ee7b7;
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
            border-top: 1px solid rgba(255, 255, 255, 0.08);
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
<div class="md:hidden sticky top-0 z-40 bg-gradient-to-r from-emerald-950 via-slate-950 to-emerald-950 border-b border-emerald-900/60 px-4 py-3 flex items-center justify-between text-white shadow-lg backdrop-blur-md">
    <div class="flex items-center gap-3">
        <img src="<?= $baseUrl ?>/assets/images/logo.jpg" 
             onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=CTC&background=10b981&color=fff';" 
             alt="PT CTC Logo" 
             class="w-8 h-8 rounded-xl object-cover border border-emerald-400/30 shadow-md">
        <div>
            <h1 class="font-black text-xs text-white leading-tight tracking-wide">PT. CTC</h1>
            <span class="text-[9px] text-emerald-300/90 font-medium block">Cipta Teknologi Cendekia</span>
        </div>
    </div>
    <button type="button" onclick="toggleMobileSidebar()" title="Buka Menu"
        class="w-9 h-9 rounded-xl bg-white/10 hover:bg-emerald-500/20 text-emerald-300 hover:text-white flex items-center justify-center transition border border-white/15 active:scale-95 shadow-inner">
        <i class="fa-solid fa-bars text-sm"></i>
    </button>
</div>

<!-- BACKDROP OVERLAY UNTUK MOBILE DRAWER -->
<div id="mobileSidebarBackdrop" onclick="toggleMobileSidebar()" 
     class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm z-40 hidden md:hidden transition-opacity duration-300 opacity-0"></div>

<!-- MAIN SIDEBAR (Sticky di Desktop, Off-Canvas Drawer di HP) -->
<aside id="mainSidebar" 
       class="fixed md:sticky top-0 left-0 h-screen bg-gradient-to-b from-emerald-950 via-slate-950 to-emerald-950 border-r border-emerald-900/60 p-4 flex flex-col justify-between text-white shadow-2xl z-50 select-none transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0 w-64 shrink-0">
    
    <!-- TOP SECTION: Header Logo & Navigasi -->
    <div class="flex flex-col h-full min-h-0">
        
        <!-- HEADER 1: Mode Buka (Expanded) & Mobile -->
        <div class="header-expanded flex items-center justify-between gap-2.5 px-3 py-3 mb-5 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md shrink-0 shadow-inner">
            <div class="flex items-center gap-3 min-w-0">
                <img src="<?= $baseUrl ?>/assets/images/logo.jpg" 
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=CTC&background=10b981&color=fff';" 
                     alt="PT CTC Logo" 
                     class="w-10 h-10 rounded-xl object-cover shadow-md shadow-emerald-500/20 shrink-0 border border-emerald-400/30">
                
                <div class="sidebar-text-group min-w-0 leading-tight">
                    <h1 class="font-extrabold text-sm text-white tracking-wide">PT. CTC</h1>
                    <span class="text-[10px] text-emerald-300/90 font-medium block leading-tight mt-0.5">Cipta Teknologi Cendekia</span>
                </div>
            </div>

            <!-- Tombol Tutup di Mobile & Desktop -->
            <button type="button" onclick="handleSidebarToggle()" title="Tutup Menu"
                class="w-8 h-8 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/25 text-emerald-300 hover:text-white flex items-center justify-center transition border border-emerald-500/20 active:scale-95 shrink-0">
                <i class="fa-solid fa-bars-staggered text-xs md:block hidden"></i>
                <i class="fa-solid fa-xmark text-sm md:hidden block"></i>
            </button>
        </div>

        <!-- HEADER 2: Mode Tutup (Collapsed Header Khusus Desktop) -->
        <div class="header-collapsed hidden flex-col items-center justify-center p-2 mb-5 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-md shrink-0 shadow-inner">
            <button type="button" onclick="toggleSidebar()" title="Klik untuk Buka Sidebar" class="relative group cursor-pointer">
                <img src="<?= $baseUrl ?>/assets/images/logo.jpg" 
                     onerror="this.onerror=null; this.src='https://ui-avatars.com/api/?name=CTC&background=10b981&color=fff';" 
                     alt="PT CTC Logo" 
                     class="w-10 h-10 rounded-xl object-cover shadow-md shadow-emerald-500/20 border border-emerald-400/30 transition group-hover:scale-105">
                <div class="absolute -bottom-1 -right-1 w-5 h-5 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[9px] shadow-md border border-slate-950">
                    <i class="fa-solid fa-angles-right"></i>
                </div>
            </button>
        </div>

        <!-- Area Menu Navigasi (Independent Scroll) -->
        <div class="flex-1 overflow-y-auto sidebar-scroll pr-1 space-y-4">
            
            <!-- SECTION 1: DASHBOARD UTAMA -->
            <div>
                <a href="<?= $baseUrl ?>/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                    <div class="flex items-center gap-3 min-w-0">
                        <i class="nav-icon fa-solid fa-gauge-high w-4 text-center text-sm <?= strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false ? 'text-white' : 'text-emerald-400' ?>"></i>
                        <span class="sidebar-text-group truncate">Dashboard</span>
                    </div>
                    <?php if (strpos($currentScript, 'index.php') !== false && strpos($currentScript, 'modules') === false): ?>
                        <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                    <?php endif; ?>
                    <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                        Dashboard
                    </div>
                </a>
            </div>

            <!-- SECTION 2: DOKUMEN LEGAL (Tahap Inisiasi Proyek) -->
            <div>
                <span class="category-label px-3 text-[9px] font-black uppercase tracking-[0.18em] text-emerald-400/70 block mb-1.5">Dokumen Legal</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">
                    
                    <a href="<?= $baseUrl ?>/modules/proposals/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/proposals/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-signature w-4 text-center text-sm <?= strpos($currentScript, '/proposals/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Surat Penawaran</span>
                        </div>
                        <?php if (strpos($currentScript, '/proposals/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Surat Penawaran
                        </div>
                    </a>

                    <a href="<?= $baseUrl ?>/modules/contracts/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/contracts/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-contract w-4 text-center text-sm <?= strpos($currentScript, '/contracts/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Cetak Kontrak PDF</span>
                        </div>
                        <?php if (strpos($currentScript, '/contracts/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Cetak Kontrak PDF
                        </div>
                    </a>

                </div>
            </div>

            <!-- SECTION 3: MANAJEMEN OPERASIONAL (Tahap Eksekusi Tim & Proyek) -->
            <div>
                <span class="category-label px-3 text-[9px] font-black uppercase tracking-[0.18em] text-emerald-400/70 block mb-1.5">Manajemen Operasional</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">

                    <a href="<?= $baseUrl ?>/modules/users/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/users/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-users-gear w-4 text-center text-sm <?= strpos($currentScript, '/users/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Manajemen Tim</span>
                        </div>
                        <?php if (strpos($currentScript, '/users/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Manajemen Tim
                        </div>
                    </a>
                    
                    <a href="<?= $baseUrl ?>/modules/projects/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/projects/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-folder-tree w-4 text-center text-sm <?= strpos($currentScript, '/projects/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Proyek & Timeline</span>
                        </div>
                        <?php if (strpos($currentScript, '/projects/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Proyek & Timeline
                        </div>
                    </a>

                    <a href="<?= $baseUrl ?>/modules/tasks/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/tasks/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-list-check w-4 text-center text-sm <?= strpos($currentScript, '/tasks/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Workload & Priority</span>
                        </div>
                        <?php if (strpos($currentScript, '/tasks/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Workload & Priority
                        </div>
                    </a>

                </div>
            </div>

            <!-- SECTION 4: KEUANGAN & BILLING (Tahap Transaksi & Laporan Laba Rugi) -->
            <div>
                <span class="category-label px-3 text-[9px] font-black uppercase tracking-[0.18em] text-emerald-400/70 block mb-1.5">Keuangan & Billing</span>
                <div class="category-divider hidden"></div>
                <div class="space-y-1">
                    
                    <a href="<?= $baseUrl ?>/modules/expenses/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/expenses/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-wallet w-4 text-center text-sm <?= strpos($currentScript, '/expenses/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Tracking Biaya</span>
                        </div>
                        <?php if (strpos($currentScript, '/expenses/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Tracking Biaya
                        </div>
                    </a>

                    <a href="<?= $baseUrl ?>/modules/invoices/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/invoices/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-file-invoice-dollar w-4 text-center text-sm <?= strpos($currentScript, '/invoices/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Invoice & Billing</span>
                        </div>
                        <?php if (strpos($currentScript, '/invoices/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Invoice & Billing
                        </div>
                    </a>

                    <a href="<?= $baseUrl ?>/modules/reports/index.php" class="nav-item-link relative group flex items-center justify-between px-3.5 py-2.5 rounded-xl font-semibold text-xs border <?= strpos($currentScript, '/reports/') !== false ? 'bg-emerald-600 border-emerald-500/80 text-white shadow-lg shadow-emerald-600/35' : 'border-transparent text-emerald-100/80 hover:bg-white/5 hover:text-white hover:border-white/10' ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="nav-icon fa-solid fa-chart-pie w-4 text-center text-sm <?= strpos($currentScript, '/reports/') !== false ? 'text-white' : 'text-emerald-400' ?>"></i>
                            <span class="sidebar-text-group truncate">Laporan Keuangan</span>
                        </div>
                        <?php if (strpos($currentScript, '/reports/') !== false): ?>
                            <span class="sidebar-text-group w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        <?php endif; ?>
                        <div class="sidebar-tooltip hidden absolute left-full ml-3 px-3 py-1.5 bg-slate-900 border border-slate-700 text-white text-xs font-extrabold rounded-xl shadow-2xl whitespace-nowrap pointer-events-none opacity-0 group-hover:opacity-100 transition-opacity duration-200 z-50">
                            Laporan Keuangan
                        </div>
                    </a>

                </div>
            </div>

        </div>
    </div>

    <!-- BOTTOM SECTION: Profil User -->
    <div class="pt-3 mt-3 border-t border-emerald-800/40 shrink-0">
        
        <!-- PROFIL MODE BUKA -->
        <div class="user-expanded flex items-center justify-between p-2.5 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="relative shrink-0">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-emerald-950 font-black flex items-center justify-center text-xs shadow-md">
                        <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?>
                    </div>
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-slate-950 absolute -bottom-0.5 -right-0.5"></span>
                </div>
                <div class="sidebar-text-group min-w-0 leading-tight">
                    <p class="text-xs font-bold text-white truncate"><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></p>
                    <p class="text-[10px] text-emerald-300/80 capitalize font-medium truncate"><?= htmlspecialchars($_SESSION['role'] ?? 'Role') ?></p>
                </div>
            </div>
            
            <a href="<?= $baseUrl ?>/logout.php" title="Keluar Sistem" 
               class="w-8 h-8 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 flex items-center justify-center transition border border-red-500/20 active:scale-95 shrink-0">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
            </a>
        </div>

        <!-- PROFIL MODE TUTUP (Desktop) -->
        <div class="user-collapsed hidden flex-col items-center gap-2.5 p-2 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-sm">
            <div class="relative shrink-0">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 text-emerald-950 font-black flex items-center justify-center text-xs shadow-md">
                    <?= strtoupper(substr($_SESSION['nama'] ?? 'A', 0, 1)) ?>
                </div>
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 border-2 border-slate-950 absolute -bottom-0.5 -right-0.5"></span>
            </div>
            
            <a href="<?= $baseUrl ?>/logout.php" title="Keluar Sistem" 
               class="w-8 h-8 rounded-xl bg-red-500/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 flex items-center justify-center transition border border-red-500/20 active:scale-95">
                <i class="fa-solid fa-right-from-bracket text-xs"></i>
            </a>
        </div>

    </div>

</aside>

<!-- SCRIPT LOGIC RESPONSIF SIDEBAR & MEMORI LOCALSTORAGE -->
<script>
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