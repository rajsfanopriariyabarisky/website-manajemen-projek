<?php
session_start();

// Hanya menghapus variabel session milik Admin/Karyawan
unset(
    $_SESSION['user_id'],
    $_SESSION['nama'],
    $_SESSION['role'],
    $_SESSION['email']
);

header("Location: login.php");
exit;