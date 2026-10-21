<?php
session_start();

// Jika klien sudah login, langsung arahkan ke portal utama
if (isset($_SESSION['client_logged_in']) && $_SESSION['client_logged_in'] === true) {
    header('Location: portal.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PT. Cipta Teknologi Cendekia - Enterprise Portal</title>
    
    <link rel="icon" type="image/jpeg" href="../assets/images/logo.jpg">
    <link rel="shortcut icon" type="image/jpeg" href="../assets/images/logo.jpg">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .gpu {
            will-change: opacity, transform;
            transform: translateZ(0);
        }
        .fade-out {
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s cubic-bezier(0.16, 1, 0.3, 1);
        }
    </style>
</head>
<body class="bg-white text-slate-900 min-h-screen font-sans overflow-hidden select-none">

    <!-- Screen Container Intro -->
    <div id="splashContainer" class="fixed inset-0 z-50 flex items-center justify-center bg-white transition-opacity duration-500 gpu">
        
        <!-- Video Background Fullscreen (Warna Original Kertas/Terang Tanpa Gelap) -->
        <video id="introVideo" autoplay muted playsinline preload="auto" class="absolute inset-0 w-full h-full object-cover">
            <source src="../intro-ctc.mp4" type="video/mp4">
        </video>

        <!-- Minimalist Clean Skip Button (Pojok Kanan Bawah) -->
        <button id="skipBtn" onclick="finishIntro()" class="absolute bottom-6 right-6 sm:bottom-8 sm:right-8 z-20 px-4 py-2 bg-slate-900/70 hover:bg-slate-900 active:scale-95 text-white/90 hover:text-white rounded-full text-[11px] font-bold tracking-wider uppercase backdrop-blur-md transition-all duration-300 flex items-center gap-2 shadow-lg border border-white/20">
            <span>SKIP</span>
            <i class="fa-solid fa-arrow-right text-[10px]"></i>
        </button>

        <!-- Progress Line Tipis di Paling Bawah -->
        <div class="absolute bottom-0 left-0 w-full h-[3px] bg-slate-200/50 z-20">
            <div id="progressBar" class="h-full bg-emerald-600 w-0 transition-all duration-100 ease-linear"></div>
        </div>
    </div>

    <script>
        const video = document.getElementById('introVideo');
        const splash = document.getElementById('splashContainer');
        const progressBar = document.getElementById('progressBar');
        let isRedirecting = false;

        function finishIntro() {
            if (isRedirecting) return;
            isRedirecting = true;
            
            splash.classList.add('fade-out');
            setTimeout(() => {
                window.location.href = 'login.php';
            }, 500);
        }

        function updateProgress() {
            if (video.duration && !isRedirecting) {
                const pct = (video.currentTime / video.duration) * 100;
                progressBar.style.width = pct + '%';
            }
            if (!isRedirecting) {
                requestAnimationFrame(updateProgress);
            }
        }
        requestAnimationFrame(updateProgress);

        video.addEventListener('ended', finishIntro);
        video.addEventListener('error', finishIntro);

        // Fallback jika video ngadat
        setTimeout(() => {
            if (!isRedirecting) finishIntro();
        }, 8000);
    </script>

</body>
</html>