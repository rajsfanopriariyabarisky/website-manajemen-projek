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
    <title>Project Management System PT. CTC</title>

    <!-- KODE UNTUK MENGGANTI FAVICON TAB BROWSER -->
    <link rel="shortcut icon" href="/website%20manajemen%20projek/assets/images/logo.jpg" type="image/x-icon">
    <link rel="icon" href="/website%20manajemen%20projek/assets/images/logo.jpg" type="image/jpeg">

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ctc: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
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
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex">