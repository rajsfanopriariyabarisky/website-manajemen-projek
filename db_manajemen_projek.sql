-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 12 Okt 2026 pada 12.22
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_manajemen_projek`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `nomor_kontrak` varchar(100) NOT NULL,
  `durasi_bulan` int(11) NOT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `nilai_kontrak` decimal(15,2) NOT NULL,
  `file_pdf` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `contracts`
--

INSERT INTO `contracts` (`id`, `project_id`, `nomor_kontrak`, `durasi_bulan`, `tanggal_mulai`, `tanggal_selesai`, `nilai_kontrak`, `file_pdf`, `created_at`) VALUES
(9, 1, '001/CTC-SPK/X/2026', 3, '2026-10-01', '2027-02-01', 10000000000.00, NULL, '2026-10-05 03:48:49'),
(10, 10, '002/CTC-SPK/X/2026', 12, '2026-10-05', '2027-10-05', 50000000.00, NULL, '2026-10-05 03:54:49'),
(14, 11, '003/CTC-SPK/X/2026', 1, '2026-10-05', '2026-10-20', 7000000.00, NULL, '2026-10-05 05:05:42'),
(15, 13, '004/CTC-SPK/X/2026', 5, '2026-10-09', '2027-02-20', 40000000.00, NULL, '2026-10-09 02:50:23'),
(17, 19, '005/CTC-SPK/X/2026', 1, '2026-10-12', '2026-10-17', 100000000.00, NULL, '2026-10-12 07:33:47');

-- --------------------------------------------------------

--
-- Struktur dari tabel `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `kategori` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `jumlah_biaya` decimal(15,2) NOT NULL,
  `tanggal_pengeluaran` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `expenses`
--

INSERT INTO `expenses` (`id`, `project_id`, `kategori`, `keterangan`, `jumlah_biaya`, `tanggal_pengeluaran`) VALUES
(5, 10, 'Infrastruktur & Cloud', 'beli hosting untuk website', 2500000.00, '2026-10-05'),
(6, 10, 'Vendor / Freelancer', 'gaji anak magang', 1500000.00, '2026-10-05'),
(7, 13, 'Vendor / Freelancer', 'gaji anak magang', 2000000.00, '2026-10-09');

-- --------------------------------------------------------

--
-- Struktur dari tabel `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `nomor_invoice` varchar(100) NOT NULL,
  `termin_ke` int(11) DEFAULT 1,
  `keterangan_termin` varchar(200) NOT NULL,
  `jumlah_tagihan` decimal(15,2) NOT NULL,
  `tanggal_tagihan` date NOT NULL,
  `jatuh_tempo` date NOT NULL,
  `status` enum('Unpaid','Partially Paid','Paid') DEFAULT 'Unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `invoices`
--

INSERT INTO `invoices` (`id`, `project_id`, `nomor_invoice`, `termin_ke`, `keterangan_termin`, `jumlah_tagihan`, `tanggal_tagihan`, `jatuh_tempo`, `status`, `created_at`) VALUES
(3, 1, 'INV/CTC/2026/10/001', 1, 'DP 50% Penandatanganan SPK', 50000000.00, '2026-10-05', '2026-10-19', 'Paid', '2026-10-05 03:46:28'),
(4, 10, 'INV/CTC/2026/10/002', 1, 'DP 50% Penandatanganan SPK', 25000000.00, '2026-10-05', '2026-10-19', 'Paid', '2026-10-05 03:53:13'),
(5, 10, 'INV/CTC/2026/10/003', 2, 'Pelunasan 50% BAST Selesai', 25000000.00, '2026-10-05', '2026-11-19', 'Paid', '2026-10-05 03:55:57'),
(6, 13, 'INV/CTC/2026/10/004', 1, 'DP 50% Penandatanganan SPK', 20000000.00, '2026-10-09', '2026-10-23', 'Paid', '2026-10-09 02:59:27'),
(7, 13, 'INV/CTC/2026/10/005', 2, 'Pelunasan 50% BAST Selesai', 20000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 04:39:48'),
(8, 19, 'INV/CTC/2026/10/006', 3, 'Pelunasan 100% Pembayaran Penuh', 100000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 06:46:59'),
(9, 11, 'INV/CTC/2026/10/007', 1, 'DP 50% Penandatanganan SPK', 3500000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 06:58:49'),
(10, 11, 'INV/CTC/2026/10/008', 1, 'DP 50% Penandatanganan SPK', 3500000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 07:00:02'),
(11, 12, 'INV/CTC/2026/10/009', 1, 'DP 50% Penandatanganan SPK', 20000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 07:02:43'),
(12, 12, 'INV/CTC/2026/10/010', 2, 'Pelunasan 50% BAST Selesai', 20000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 07:03:13'),
(13, 20, 'INV/CTC/2026/10/011', 1, 'DP 50% Penandatanganan SPK', 50000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 07:12:57'),
(14, 20, 'INV/CTC/2026/10/012', 2, 'Pelunasan 50% BAST Selesai', 50000000.00, '2026-10-12', '2026-10-26', 'Paid', '2026-10-12 07:13:15');

-- --------------------------------------------------------

--
-- Struktur dari tabel `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `nama_projek` varchar(150) NOT NULL,
  `klien` varchar(100) NOT NULL,
  `budget` decimal(15,2) NOT NULL DEFAULT 0.00,
  `tanggal_mulai` date NOT NULL,
  `deadline` date NOT NULL,
  `status` enum('Planning','In Progress','Completed','On Hold') DEFAULT 'Planning',
  `kategori_rab` enum('Website','IoT','Mobile','Custom') NOT NULL DEFAULT 'Website',
  `rab_custom_data` longtext DEFAULT NULL,
  `client_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `projects`
--

INSERT INTO `projects` (`id`, `nama_projek`, `klien`, `budget`, `tanggal_mulai`, `deadline`, `status`, `kategori_rab`, `rab_custom_data`, `client_token`, `created_at`) VALUES
(1, 'Website Landing Page Parking', 'PT Lynx Indonesia', 100000000.00, '2026-10-01', '2027-02-01', 'In Progress', 'Website', NULL, '26f1e159ec6b0e3202a71cacbba58238', '2026-10-01 03:29:58'),
(10, 'PT PERTAMINA', 'Prabowo subianto', 50000000.00, '2026-10-05', '2027-10-05', 'Completed', 'Website', NULL, NULL, '2026-10-05 03:52:30'),
(11, 'projek test', 'test 1', 7000000.00, '2026-10-05', '2026-10-20', 'Completed', 'Website', NULL, NULL, '2026-10-05 04:44:13'),
(12, 'PT Asnoor Parking', 'Megawati Soekarno Putri', 40000000.00, '2026-10-07', '2026-12-07', 'Completed', 'Website', NULL, NULL, '2026-10-07 04:09:16'),
(13, 'Model Pertanian Modern', 'Badan Pengembangan dan modernisasi Pertanian', 40000000.00, '2026-10-09', '2026-10-20', 'Completed', 'Website', NULL, NULL, '2026-10-09 02:36:49'),
(18, 'test 2', 'Jendral Sudirman', 100000000.00, '2026-10-12', '2026-10-12', 'In Progress', 'Mobile', NULL, NULL, '2026-10-12 04:18:36'),
(19, 'iot pertanianku', 'uzumaki naruto', 100000000.00, '2026-10-12', '2026-10-17', 'Completed', 'IoT', NULL, NULL, '2026-10-12 04:37:32'),
(20, 'pt bakso idola', 'Mamat Sudrajat', 100000000.00, '2026-10-12', '2027-03-12', 'On Hold', 'Website', NULL, NULL, '2026-10-12 04:46:51');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_files`
--

CREATE TABLE `project_files` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_id` int(11) DEFAULT NULL,
  `nama_file` varchar(255) NOT NULL,
  `kategori` varchar(50) NOT NULL DEFAULT 'Dokumen',
  `file_path` varchar(255) NOT NULL,
  `ukuran_file` int(11) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `project_files`
--

INSERT INTO `project_files` (`id`, `project_id`, `task_id`, `nama_file`, `kategori`, `file_path`, `ukuran_file`, `uploaded_by`, `created_at`) VALUES
(4, 12, 21, 'Manual Book - Sistem Manajemen Projek PT CTC.docx', 'Deliverable / Code', '3419a69040b7cb3108fc4174a5c5a1ad.docx', 2212809, 2, '2026-10-07 04:12:03'),
(5, 12, 21, 'logoctc.jpg', 'Deliverable / Code', 'd80f2e84c113a276fc5126e89f37ea33.jpg', 2283463, 6, '2026-10-07 04:13:08'),
(6, 12, 22, 'logoctc.jpg', 'Deliverable / Code', 'fad10fb0a60f62f22546f8ad43bb368d.jpg', 2283463, 4, '2026-10-07 05:22:54'),
(7, 13, 30, 'ctc (priority & hub).png', 'Deliverable / Code', '12026d2a2912673a85a9ab3fb689a561.png', 217979, 6, '2026-10-09 03:11:11');

-- --------------------------------------------------------

--
-- Struktur dari tabel `project_logs`
--

CREATE TABLE `project_logs` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `nama_user` varchar(100) NOT NULL,
  `aktivitas` text NOT NULL,
  `tipe` varchar(50) DEFAULT 'info',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `project_logs`
--

INSERT INTO `project_logs` (`id`, `project_id`, `user_id`, `nama_user`, `aktivitas`, `tipe`, `created_at`) VALUES
(36, 1, 1, 'Administrator', 'Mengalokasikan tugas baru \'Software development\' ke Febrian V.', 'task', '2026-10-05 03:47:17'),
(37, 10, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'In Progress\'', 'info', '2026-10-05 03:56:42'),
(38, 11, 1, 'Administrator', 'Mengalokasikan tugas \'web development\' ke Tim: (billyanz yusuf, Febrian V., Muhammad Fachri, Rajsfano Priariya Barisky)', 'task', '2026-10-05 05:30:55'),
(39, 11, 1, 'Administrator', 'Menyetujui (Approved) tugas \'web development\' -> Status: Done', 'success', '2026-10-05 05:32:09'),
(40, 11, 1, 'Administrator', 'Mengalokasikan tugas \'UI/UX design\' ke Tim: (Febrian V., Rajsfano Priariya Barisky)', 'task', '2026-10-05 05:33:43'),
(41, 1, 6, 'Febrian V.', 'Memperbarui progress \'Software development\' menjadi status: Ready for Review', 'info', '2026-10-05 05:37:14'),
(42, 11, 2, 'Rajsfano Priariya Barisky', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-05 05:45:39'),
(43, 11, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'Completed\'', 'info', '2026-10-07 04:01:57'),
(44, 11, 1, 'Administrator', 'Menyetujui (Approved) tugas \'UI/UX design\' -> Status: Done', 'success', '2026-10-07 04:02:01'),
(45, 10, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'Completed\'', 'info', '2026-10-07 04:02:21'),
(46, 1, 1, 'Administrator', 'Menyetujui (Approved) tugas \'Software development\' -> Status: Done', 'success', '2026-10-07 04:02:41'),
(47, 1, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'Completed\'', 'info', '2026-10-07 04:03:03'),
(48, 10, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'In Progress\'', 'info', '2026-10-07 04:07:39'),
(49, 12, 1, 'Administrator', 'Mengalokasikan tugas \'UI/UX design\' ke Tim: (Febrian V., Rajsfano Priariya Barisky)', 'task', '2026-10-07 04:09:43'),
(50, 12, 2, 'Rajsfano Priariya Barisky', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-07 04:12:03'),
(51, 12, 6, 'Febrian V.', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-07 04:13:08'),
(52, 12, 1, 'Administrator', 'Mengirimkan catatan revisi pada tugas \'UI/UX design\'', 'warning', '2026-10-07 04:22:42'),
(53, 12, 6, 'Febrian V.', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-07 04:22:59'),
(54, 12, 6, 'Febrian V.', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-07 04:28:36'),
(55, 12, 1, 'Administrator', 'Mengirimkan catatan revisi pada tugas \'UI/UX design\'', 'warning', '2026-10-07 04:29:03'),
(56, 12, 1, 'Administrator', 'Menyetujui (Approved) tugas \'UI/UX design\' -> Status: Done', 'success', '2026-10-07 04:29:10'),
(57, 12, 1, 'Administrator', 'Mengalokasikan modul baru \'ERD LRS\' ke Tim: (billyanz yusuf)', 'task', '2026-10-07 05:21:36'),
(58, 12, 1, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'ERD LRS\'', 'warning', '2026-10-07 05:21:58'),
(59, 12, 4, 'billyanz yusuf', 'Memperbarui progress \'ERD LRS\' menjadi status: In Progress', 'info', '2026-10-07 05:22:54'),
(60, 12, 1, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'ERD LRS\'', 'warning', '2026-10-07 05:23:38'),
(61, 12, 4, 'billyanz yusuf', 'Memperbarui progress \'ERD LRS\' menjadi status: In Progress', 'info', '2026-10-07 05:24:11'),
(62, 12, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'ERD LRS\'', 'success', '2026-10-07 05:24:32'),
(63, 12, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-07 05:36:24'),
(64, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf)', 'task', '2026-10-07 05:51:32'),
(65, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'Software development\' ke Tim: (Muhammad Fachri)', 'task', '2026-10-07 05:51:40'),
(66, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'Membuat RAB\' ke Tim: (Febrian V.)', 'task', '2026-10-07 05:52:00'),
(67, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'Membuat RAB\'', 'success', '2026-10-07 06:45:08'),
(68, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'UI/UX design\'', 'success', '2026-10-07 06:45:13'),
(69, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'Software development\'', 'success', '2026-10-07 06:45:16'),
(70, 12, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-07 06:46:44'),
(71, 12, 1, 'Administrator', 'Mengalokasikan modul baru \'frontend\' ke Tim: (billyanz yusuf)', 'task', '2026-10-07 07:04:35'),
(72, 12, 1, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'frontend\'', 'warning', '2026-10-07 07:06:21'),
(73, 12, 4, 'billyanz yusuf', 'Memperbarui progress \'frontend\' menjadi status: In Progress', 'info', '2026-10-07 07:10:56'),
(74, 12, 4, 'billyanz yusuf', 'Memperbarui progress \'frontend\' menjadi status: In Progress', 'info', '2026-10-07 07:13:04'),
(75, 12, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'frontend\'', 'success', '2026-10-07 07:13:18'),
(76, 12, 1, 'Administrator', 'Mengalokasikan modul baru \'nganggur\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-07 07:13:33'),
(77, 12, 1, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'nganggur\'', 'warning', '2026-10-07 07:13:47'),
(78, 12, 2, 'Rajsfano Priariya Barisky', 'Memperbarui progress \'nganggur\' menjadi status: In Progress', 'info', '2026-10-07 07:14:13'),
(79, 12, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-07 07:19:55'),
(80, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'nganggur\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-07 07:22:26'),
(81, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-07 07:22:43'),
(82, 12, 1, 'Administrator', 'Mengalokasikan modul baru \'testing\' ke Tim: (Febrian V.)', 'task', '2026-10-07 09:05:47'),
(83, 13, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'In Progress\'', 'info', '2026-10-09 02:49:52'),
(84, 13, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf, Febrian V.)', 'task', '2026-10-09 03:10:25'),
(85, 13, 6, 'Febrian V.', 'Memperbarui progress \'UI/UX design\' menjadi status: In Progress', 'info', '2026-10-09 03:11:11'),
(86, 13, 1, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'UI/UX design\'', 'warning', '2026-10-09 03:12:03'),
(87, 13, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'UI/UX design\'', 'success', '2026-10-12 03:51:22'),
(88, 10, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-12 04:24:57'),
(89, 12, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'testing\'', 'success', '2026-10-12 04:27:47'),
(90, 13, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-12 04:32:14'),
(91, 18, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'In Progress\'', 'info', '2026-10-12 04:34:53'),
(92, 19, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-12 06:48:07'),
(93, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-12 08:57:31'),
(94, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-12 09:33:50'),
(95, 20, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf, Febrian V.)', 'task', '2026-10-12 09:35:59'),
(96, 20, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'UI/UX design\'', 'success', '2026-10-12 09:37:51'),
(97, 20, 1, 'Administrator', 'Mengalokasikan modul baru \'Software development\' ke Tim: (Muhammad Fachri, Rajsfano Priariya Barisky)', 'task', '2026-10-12 09:38:10'),
(98, 18, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf)', 'task', '2026-10-12 09:42:40'),
(99, 1, 1, 'Administrator', 'Mengalokasikan modul baru \'mobile design\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-12 09:44:44'),
(100, 1, 1, 'Administrator', 'Mengalokasikan modul baru \'finishing\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-12 09:49:14'),
(101, 1, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'In Progress\'', 'info', '2026-10-12 09:50:50');

-- --------------------------------------------------------

--
-- Struktur dari tabel `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `nama_tugas` varchar(200) NOT NULL,
  `prioritas` enum('Low','Medium','High','Urgent') DEFAULT 'Medium',
  `status` enum('To Do','In Progress','Ready for Review','Done') DEFAULT 'To Do',
  `progress_notes` text DEFAULT NULL,
  `updated_by_user_id` int(11) DEFAULT NULL,
  `revision_notes` text DEFAULT NULL,
  `drive_link` varchar(255) DEFAULT NULL,
  `deadline` date NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `assigned_to`, `user_id`, `nama_tugas`, `prioritas`, `status`, `progress_notes`, `updated_by_user_id`, `revision_notes`, `drive_link`, `deadline`, `updated_at`) VALUES
(1, 1, NULL, 1, 'UI/UX design', 'High', 'Done', NULL, NULL, NULL, NULL, '2026-10-05', '2026-10-01 09:36:32'),
(18, 1, 6, 6, 'Software development', 'High', 'Done', 'tes 1', NULL, NULL, 'https://drive.google.com/drive/u/0/', '2026-10-19', '2026-10-07 04:02:41'),
(19, 11, 4, 4, 'web development', 'High', 'Done', NULL, NULL, NULL, NULL, '2026-10-05', '2026-10-05 05:32:09'),
(20, 11, 6, 6, 'UI/UX design', 'Medium', 'Done', 'ini pak sudah beberapa ada yang saya  rubah', NULL, NULL, 'https://drive.google.com/drive/u/0/', '2026-10-05', '2026-10-07 04:02:01'),
(21, 12, 6, 6, 'UI/UX design', 'High', 'Done', 'terima kasih pak sudah saya kerjakan', 6, NULL, 'https://drive.google.com/drive/folders/1RcWjJ5bS6YZasisoOhOJIF1HEJ7_GIev?usp=drive_link', '2026-10-09', '2026-10-07 04:29:10'),
(22, 12, 4, 4, 'ERD LRS', 'Medium', 'Done', 'baik pak sudah saya isi sesuai dengan yang bapak minta', 4, NULL, 'https://drive.google.com/drive/folders/1RcWjJ5bS6YZasisoOhOJIF1HEJ7_GIev?usp=drive_link', '2026-10-07', '2026-10-07 05:24:32'),
(23, 10, 4, 4, 'UI/UX design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-07', '2026-10-07 06:45:13'),
(24, 10, 3, 3, 'Software development', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-07', '2026-10-07 06:45:16'),
(25, 10, 6, 6, 'Membuat RAB', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-06', '2026-10-07 06:45:08'),
(26, 12, 4, 4, 'frontend', 'Medium', 'Done', 'ini sudah saya perbaiki', 4, NULL, '', '2026-10-06', '2026-10-07 07:13:18'),
(27, 12, 2, 2, 'nganggur', 'Medium', 'Done', 'siap nganggur', 2, NULL, '', '2026-10-07', '2026-10-07 07:19:55'),
(28, 10, 2, 2, 'nganggur', 'Urgent', 'Done', NULL, NULL, NULL, NULL, '2026-10-06', '2026-10-12 09:33:50'),
(29, 12, 6, 6, 'testing', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-07', '2026-10-12 04:27:47'),
(30, 13, 4, 4, 'UI/UX design', 'Medium', 'Done', 'sudah saya kerjakan', 6, NULL, 'https://drive.google.com/drive/u/0/', '2026-10-10', '2026-10-12 03:51:22'),
(31, 20, 4, 4, 'UI/UX design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-12', '2026-10-12 09:47:18'),
(32, 20, 3, 3, 'Software development', 'Medium', 'To Do', NULL, NULL, NULL, NULL, '2026-10-10', '2026-10-12 09:41:50'),
(33, 18, 4, 4, 'UI/UX design', 'Medium', 'To Do', NULL, NULL, NULL, NULL, '2026-10-12', '2026-10-12 09:48:30'),
(34, 1, 2, 2, 'mobile design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-09', '2026-10-12 09:47:06'),
(35, 1, 2, 2, 'finishing', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-01', '2026-10-12 09:58:28');

-- --------------------------------------------------------

--
-- Struktur dari tabel `task_assignees`
--

CREATE TABLE `task_assignees` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `task_assignees`
--

INSERT INTO `task_assignees` (`id`, `task_id`, `user_id`, `created_at`) VALUES
(1, 19, 4, '2026-10-05 05:30:55'),
(2, 19, 6, '2026-10-05 05:30:55'),
(3, 19, 3, '2026-10-05 05:30:55'),
(4, 19, 2, '2026-10-05 05:30:55'),
(5, 20, 6, '2026-10-05 05:33:43'),
(6, 20, 2, '2026-10-05 05:33:43'),
(7, 21, 6, '2026-10-07 04:09:43'),
(8, 21, 2, '2026-10-07 04:09:43'),
(9, 22, 4, '2026-10-07 05:21:36'),
(10, 23, 4, '2026-10-07 05:51:32'),
(11, 24, 3, '2026-10-07 05:51:40'),
(12, 25, 6, '2026-10-07 05:52:00'),
(13, 26, 4, '2026-10-07 07:04:35'),
(14, 27, 2, '2026-10-07 07:13:33'),
(15, 28, 2, '2026-10-07 07:22:26'),
(16, 29, 6, '2026-10-07 09:05:47'),
(17, 30, 4, '2026-10-09 03:10:25'),
(18, 30, 6, '2026-10-09 03:10:25'),
(19, 31, 4, '2026-10-12 09:35:59'),
(20, 31, 6, '2026-10-12 09:35:59'),
(21, 32, 3, '2026-10-12 09:38:10'),
(22, 32, 2, '2026-10-12 09:38:10'),
(23, 33, 4, '2026-10-12 09:42:40'),
(24, 34, 2, '2026-10-12 09:44:44'),
(25, 35, 2, '2026-10-12 09:49:14');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','karyawan') DEFAULT 'karyawan',
  `jabatan` varchar(100) DEFAULT 'Developer',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `jabatan`, `created_at`) VALUES
(1, 'Administrator', 'admin@projek.com', 'admin123', 'admin', 'Project Manager', '2026-10-01 03:16:39'),
(2, 'Rajsfano Priariya Barisky', 'fanowp@gmail.com', '123456', 'karyawan', 'IT intern', '2026-10-01 03:40:10'),
(3, 'Muhammad Fachri', 'fahri@gmail.com', '123456', 'karyawan', 'software Development', '2026-10-01 07:04:03'),
(4, 'billyanz yusuf', 'billly@gmail.com', '123456', 'karyawan', 'IT intern', '2026-10-01 09:07:23'),
(6, 'Febrian V.', 'febrian@gmail.com', '$2y$10$QbGktAuuugNeiqUTC7TmvOZFzDzFwJZuxnNFR5xJFDavKzRMW3W6S', 'karyawan', 'IT Consultant', '2026-10-02 07:08:47');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `project_logs`
--
ALTER TABLE `project_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_tasks_assigned_to` (`assigned_to`);

--
-- Indeks untuk tabel `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT untuk tabel `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT untuk tabel `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `project_logs`
--
ALTER TABLE `project_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT untuk tabel `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT untuk tabel `task_assignees`
--
ALTER TABLE `task_assignees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `project_logs`
--
ALTER TABLE `project_logs`
  ADD CONSTRAINT `project_logs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD CONSTRAINT `task_assignees_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
