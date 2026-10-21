-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 19 Okt 2026 pada 06.06
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
-- Struktur dari tabel `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `clients`
--

INSERT INTO `clients` (`id`, `nama`, `email`, `password`, `created_at`) VALUES
(1, 'Rajsfano Priariya Barisky', '10230103@bsi.ac.id', '$2y$10$Ga8ZA8S1CVFbM82Yd7cPA.W3X8CvnRakmqYYoqyKerqMgR6Hi8oEW', '2026-10-14 04:32:41'),
(2, 'Rajsfano Priariya Barisky', 'rajsfanopriariyabarisky@gmail.com', '$2y$10$wso0q0UiDmD1jr6gVFP8PeQCLMYLQV7VBptKuqyRFQMDny8xLazEe', '2026-10-14 07:48:27'),
(3, 'Dicky Rialdy', 'ceo@keel.com', '$2y$10$m1GPpZgB.JqynuGIzoIdOepxeYgR82LiwbpIDhitkXDsQQ.vzry/i', '2026-10-14 08:26:29'),
(4, 'Roy ricardo', 'roy@gmail.com', '$2y$10$QtCOpo3QU/LZB2Xwo88Yuugy78tETpJ/arp7RZkCtVwxoeZVzkfnS', '2026-10-14 09:20:18');

-- --------------------------------------------------------

--
-- Struktur dari tabel `client_requirements`
--

CREATE TABLE `client_requirements` (
  `id` int(11) NOT NULL,
  `client_name` varchar(250) NOT NULL,
  `client_email` varchar(250) NOT NULL,
  `client_phone` varchar(50) DEFAULT NULL,
  `company_name` varchar(250) DEFAULT NULL,
  `title` varchar(250) NOT NULL,
  `description` text NOT NULL,
  `file_attachment` varchar(250) DEFAULT NULL,
  `budget_estimation` decimal(15,2) DEFAULT 0.00,
  `status` enum('Pending','Under Review','Proposal Sent','Deal / Accepted','Rejected') DEFAULT 'Pending',
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `client_requirements`
--

INSERT INTO `client_requirements` (`id`, `client_name`, `client_email`, `client_phone`, `company_name`, `title`, `description`, `file_attachment`, `budget_estimation`, `status`, `user_id`, `client_id`, `created_at`) VALUES
(3, 'Rajsfano Priariya Barisky', 'rajsfanopriariyabarisky@gmail.com', '081280822265', 'PT. KEEL COMPANY', 'Projek Test 1', 'seadanya', '1791964107_Company_Profile.pdf', 100000000.00, 'Under Review', NULL, 2, '2026-10-14 07:48:27'),
(4, 'Dicky Rialdy', 'ceo@keel.com', '085781540431', 'Keel Company', 'Ecomece Keel Collective', 'Buat sesuai standart perusahaan besar', '1791966389_Company_Profile.pdf', 600000000.00, 'Deal / Accepted', NULL, 3, '2026-10-14 08:26:29'),
(5, 'Roy ricardo', 'roy@gmail.com', '0831218313', 'PT. Kukang kikung', 'Landingpage TokyoDrift', 'apa aja', NULL, 100000000.00, 'Deal / Accepted', NULL, 4, '2026-10-14 09:20:18');

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
(10, 10, '002/CTC-SPK/X/2026', 12, '2026-10-05', '2027-10-05', 50000000.00, NULL, '2026-10-05 03:54:49'),
(20, 1, '003/CTC-SPK/X/2026', 5, '2026-10-01', '2027-02-01', 100000000.00, NULL, '2026-10-14 08:22:06'),
(21, 25, '025/CTC-SPK/10/2026', 5, '2026-10-14', '2027-03-14', 600000000.00, NULL, '2026-10-14 08:27:30'),
(22, 26, '026/CTC-SPK/10/2026', 4, '2026-10-14', '2027-02-14', 100000000.00, NULL, '2026-10-14 09:22:43');

-- --------------------------------------------------------

--
-- Struktur dari tabel `deal_chats`
--

CREATE TABLE `deal_chats` (
  `id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `sender_type` enum('admin','client') NOT NULL,
  `sender_name` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `attachment_file` varchar(250) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `deal_chats`
--

INSERT INTO `deal_chats` (`id`, `requirement_id`, `sender_type`, `sender_name`, `message`, `attachment_file`, `created_at`) VALUES
(8, 3, 'client', 'Rajsfano Priariya Barisky', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Projek Test 1\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, '2026-10-14 07:48:27'),
(9, 3, 'client', 'Rajsfano Priariya Barisky', 'tes', NULL, '2026-10-14 07:48:46'),
(10, 3, 'client', 'Rajsfano Priariya Barisky', '1', NULL, '2026-10-14 07:48:48'),
(11, 3, 'client', 'Rajsfano Priariya Barisky', '2', NULL, '2026-10-14 07:48:51'),
(12, 3, 'client', 'Rajsfano Priariya Barisky', '3', NULL, '2026-10-14 07:48:52'),
(13, 3, 'admin', 'Administrator', 'baik pak ada yang bisa saya bantu?', NULL, '2026-10-14 07:49:54'),
(14, 3, 'admin', 'Administrator', 'deal ya 50 M', NULL, '2026-10-14 07:59:54'),
(15, 3, 'client', 'Rajsfano Priariya Barisky', 'oke Deal', NULL, '2026-10-14 08:00:06'),
(16, 4, 'client', 'Dicky Rialdy', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Ecomece Keel Collective\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, '2026-10-14 08:26:29'),
(17, 4, 'client', 'Dicky Rialdy', 'tes', NULL, '2026-10-14 08:26:43'),
(18, 4, 'client', 'Dicky Rialdy', 'kerjain sekarang saya tf 1M', NULL, '2026-10-14 08:26:56'),
(19, 4, 'admin', 'System CTC', 'Requirement resmi disetujui & dikonversi menjadi Proyek Aktif: [ Ecomece Keel Collective ]', NULL, '2026-10-14 08:27:30'),
(20, 5, 'client', 'Roy ricardo', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Landingpage TokyoDrift\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, '2026-10-14 09:20:18'),
(21, 5, 'client', 'Roy ricardo', 'tes', NULL, '2026-10-14 09:20:45'),
(22, 5, 'admin', 'System CTC', 'Requirement resmi disetujui & dikonversi menjadi Proyek Aktif: [ Landingpage TokyoDrift ]', NULL, '2026-10-14 09:22:43'),
(23, 5, 'admin', 'Administrator', 'deal ya mas', NULL, '2026-10-14 09:53:06'),
(24, 5, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Pending ]', NULL, '2026-10-14 09:56:42'),
(25, 5, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Proposal Sent ]', NULL, '2026-10-14 09:57:25'),
(26, 5, 'client', 'Roy ricardo', 'tes', NULL, '2026-10-14 10:11:38'),
(27, 5, 'admin', 'Administrator', 'tes', NULL, '2026-10-14 10:24:42'),
(28, 5, 'admin', 'Administrator', 'tes', NULL, '2026-10-14 10:24:46'),
(29, 5, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Deal / Accepted ]', NULL, '2026-10-14 10:27:36');

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
(6, 10, 'Vendor / Freelancer', 'gaji anak magang', 1500000.00, '2026-10-05');

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
(15, 1, 'INV/CTC/2026/10/004', 2, 'Pelunasan 50% BAST Selesai', 50000000.00, '2026-10-14', '2026-10-28', 'Paid', '2026-10-14 08:21:28');

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
(1, 'Website Landing Page Parking', 'PT Lynx Indonesia', 100000000.00, '2026-10-01', '2027-02-01', 'Completed', 'Website', NULL, '26f1e159ec6b0e3202a71cacbba58238', '2026-10-01 03:29:58'),
(10, 'PT PERTAMINA', 'Prabowo subianto', 50000000.00, '2026-10-05', '2027-10-05', 'Completed', 'Website', NULL, NULL, '2026-10-05 03:52:30'),
(25, 'Ecomece Keel Collective', 'Dicky Rialdy', 600000000.00, '2026-10-14', '2027-03-14', 'In Progress', 'Website', NULL, NULL, '2026-10-14 08:27:30'),
(26, 'Landingpage TokyoDrift', 'Roy ricardo', 100000000.00, '2026-10-14', '2027-02-14', 'In Progress', 'IoT', NULL, NULL, '2026-10-14 09:22:43');

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
(41, 1, 6, 'Febrian V.', 'Memperbarui progress \'Software development\' menjadi status: Ready for Review', 'info', '2026-10-05 05:37:14'),
(45, 10, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'Completed\'', 'info', '2026-10-07 04:02:21'),
(46, 1, 1, 'Administrator', 'Menyetujui (Approved) tugas \'Software development\' -> Status: Done', 'success', '2026-10-07 04:02:41'),
(47, 1, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'Completed\'', 'info', '2026-10-07 04:03:03'),
(48, 10, 1, 'Administrator', 'Memperbarui data & status proyek menjadi \'In Progress\'', 'info', '2026-10-07 04:07:39'),
(64, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf)', 'task', '2026-10-07 05:51:32'),
(65, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'Software development\' ke Tim: (Muhammad Fachri)', 'task', '2026-10-07 05:51:40'),
(66, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'Membuat RAB\' ke Tim: (Febrian V.)', 'task', '2026-10-07 05:52:00'),
(67, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'Membuat RAB\'', 'success', '2026-10-07 06:45:08'),
(68, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'UI/UX design\'', 'success', '2026-10-07 06:45:13'),
(69, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'Software development\'', 'success', '2026-10-07 06:45:16'),
(80, 10, 1, 'Administrator', 'Mengalokasikan modul baru \'nganggur\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-07 07:22:26'),
(81, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-07 07:22:43'),
(88, 10, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-12 04:24:57'),
(93, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-12 08:57:31'),
(94, 10, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'nganggur\'', 'success', '2026-10-12 09:33:50'),
(99, 1, 1, 'Administrator', 'Mengalokasikan modul baru \'mobile design\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-12 09:44:44'),
(100, 1, 1, 'Administrator', 'Mengalokasikan modul baru \'finishing\' ke Tim: (Rajsfano Priariya Barisky)', 'task', '2026-10-12 09:49:14'),
(101, 1, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'In Progress\'', 'info', '2026-10-12 09:50:50'),
(106, 1, 1, 'Administrator', 'Memperbarui konfigurasi & status proyek menjadi \'Completed\'', 'info', '2026-10-14 08:22:30'),
(107, 26, 1, 'Administrator', 'Mengalokasikan modul baru \'UI/UX design\' ke Tim: (billyanz yusuf)', 'task', '2026-10-14 09:51:02'),
(108, 26, 1, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'UI/UX design\'', 'success', '2026-10-19 03:32:16'),
(109, 26, 1, 'Administrator', 'Mengalokasikan modul baru \'Software development\' ke Tim: (billyanz yusuf)', 'task', '2026-10-19 03:54:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `proposals`
--

CREATE TABLE `proposals` (
  `id` int(11) NOT NULL,
  `nomor_proposal` varchar(100) NOT NULL,
  `client_name` varchar(250) NOT NULL,
  `client_email` varchar(250) NOT NULL,
  `title` varchar(250) NOT NULL,
  `nilai_penawaran` decimal(15,2) DEFAULT 0.00,
  `estimasi_pengerjaan` varchar(100) DEFAULT NULL,
  `ringkasan_scope` text DEFAULT NULL,
  `status` enum('Draft','Sent','Accepted','Rejected') DEFAULT 'Sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `proposals`
--

INSERT INTO `proposals` (`id`, `nomor_proposal`, `client_name`, `client_email`, `title`, `nilai_penawaran`, `estimasi_pengerjaan`, `ringkasan_scope`, `status`, `created_at`) VALUES
(1, 'SPH/CTC/202610/303', 'Rajsfano Priariya Barisky', '10230103@bsi.ac.id', 'KeeL Website Landingpage', 100000000.00, '5 bulan', 'selayaknya aja pak', 'Sent', '2026-10-14 06:48:07'),
(2, 'SPH/CTC/202610/275', 'Rajsfano Priariya Barisky', '10230103@bsi.ac.id', 'KeeL Website Landingpage', 1000000.00, '5bulan', 'seadanya', 'Sent', '2026-10-14 07:10:24'),
(3, 'SPH/CTC/202610/467', 'Rajsfano Priariya Barisky', '10230103@bsi.ac.id', 'KeeL Website Landingpage', 1000000.00, '5bulan', 'apa aja', 'Sent', '2026-10-14 07:11:21'),
(4, 'SPH/CTC/202610/867', 'Rocky Gerung', 'rajsfanopriariyabarisky@gmail.com', 'Projek Pembrantasan korupsi', 100000000.00, '5 bulan', '', 'Sent', '2026-10-14 07:12:54'),
(5, 'SPH/CTC/202610/142', 'Rajsfano Priariya Barisky', 'rajsfanopriariyabarisky@gmail.com', 'Projek Test 1', 100000000.00, '5 bulan', 'ngasal', 'Sent', '2026-10-14 08:00:37'),
(6, 'SPH/CTC/202610/410', 'Dicky Rialdy', 'ceo@keel.com', 'Ecomece Keel Collective', 600000000.00, '5 bulan', 'buat sesuai standart Calle De Larache', 'Sent', '2026-10-14 08:28:10'),
(7, 'SPH/CTC/202610/378', 'Roy ricardo', 'roy@gmail.com', 'Landingpage TokyoDrift', 100000000.00, '5', 'apa aja yang penting sah', 'Sent', '2026-10-14 09:56:08');

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
(23, 10, 4, 4, 'UI/UX design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-07', '2026-10-07 06:45:13'),
(24, 10, 3, 3, 'Software development', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-07', '2026-10-07 06:45:16'),
(25, 10, 6, 6, 'Membuat RAB', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-06', '2026-10-07 06:45:08'),
(28, 10, 2, 2, 'nganggur', 'Urgent', 'Done', NULL, NULL, NULL, NULL, '2026-10-06', '2026-10-12 09:33:50'),
(34, 1, 2, 2, 'mobile design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-09', '2026-10-12 09:47:06'),
(35, 1, 2, 2, 'finishing', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-01', '2026-10-12 09:58:28'),
(36, 26, 4, 4, 'UI/UX design', 'Medium', 'Done', NULL, NULL, NULL, NULL, '2026-10-17', '2026-10-19 03:32:16'),
(37, 26, 4, 4, 'Software development', 'Medium', 'To Do', NULL, NULL, NULL, NULL, '2026-10-19', '2026-10-19 03:54:01');

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
(10, 23, 4, '2026-10-07 05:51:32'),
(11, 24, 3, '2026-10-07 05:51:40'),
(12, 25, 6, '2026-10-07 05:52:00'),
(15, 28, 2, '2026-10-07 07:22:26'),
(24, 34, 2, '2026-10-12 09:44:44'),
(25, 35, 2, '2026-10-12 09:49:14'),
(26, 36, 4, '2026-10-14 09:51:02'),
(27, 37, 4, '2026-10-19 03:54:01');

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
-- Indeks untuk tabel `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indeks untuk tabel `client_requirements`
--
ALTER TABLE `client_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indeks untuk tabel `deal_chats`
--
ALTER TABLE `deal_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requirement_id` (`requirement_id`);

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
-- Indeks untuk tabel `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`);

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
-- AUTO_INCREMENT untuk tabel `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `client_requirements`
--
ALTER TABLE `client_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT untuk tabel `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `deal_chats`
--
ALTER TABLE `deal_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT untuk tabel `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT untuk tabel `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT untuk tabel `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT untuk tabel `project_logs`
--
ALTER TABLE `project_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=110;

--
-- AUTO_INCREMENT untuk tabel `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT untuk tabel `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT untuk tabel `task_assignees`
--
ALTER TABLE `task_assignees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `deal_chats`
--
ALTER TABLE `deal_chats`
  ADD CONSTRAINT `deal_chats_ibfk_1` FOREIGN KEY (`requirement_id`) REFERENCES `client_requirements` (`id`) ON DELETE CASCADE;

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
