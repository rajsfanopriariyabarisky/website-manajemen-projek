<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Proteksi Wajib Login untuk Semua Halaman Admin yang Memakai Header Ini
if (!isset($_SESSION['user_id'])) {
    header("Location: /website%20manajemen%20projek/login.php");
    exit;
}

// Double Check Guard: Jika bukan admin, jangan biarkan me-render komponen Admin Header
if (strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: /website%20manajemen%20projek/my_tasks.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Manajemen Proyek PT. CTC</title>

    <!-- FAVICON TAB BROWSER -->
    <link rel="shortcut icon" href="/website%20manajemen%20projek/assets/images/logo.png" type="image/png">
    <link rel="icon" href="/website%20manajemen%20projek/assets/images/logo.png" type="image/png">

    <!-- GOOGLE FONTS & TAILWIND CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        ctc: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body, input, select, button, h1, h2, h3, h4, h5, h6, textarea { 
            font-family: 'Plus Jakarta Sans', sans-serif !important; 
        }

        /* Smooth Custom Scrollbar */
        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 99px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-800 font-sans antialiased min-h-screen flex selection:bg-emerald-600 selection:text-white">