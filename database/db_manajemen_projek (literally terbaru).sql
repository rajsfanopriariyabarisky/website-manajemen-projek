-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 27, 2026 at 01:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

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
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `nama`, `email`, `password`, `is_verified`, `otp_code`, `otp_expiry`, `reset_token`, `reset_token_expiry`, `created_at`) VALUES
(1, 'Valen Tanjung', 'ivalviary@gmail.com', '$2y$10$jBSI0ehLPa2HqEOwKTxy9OdqlmFDnpJLq6opXEHd82eZD1nitClbS', 1, NULL, NULL, NULL, NULL, '2026-10-26 10:22:04'),
(2, 'Budi Kan', 'budi@gmail.com', '$2y$10$pXvIwjOBh9A3z7r7CXZk4.zS5hySMImyX7x1knI3CisMiUsIi9G32', 0, '138910', '2026-10-27 14:52:31', NULL, NULL, '2026-10-27 07:50:31');

-- --------------------------------------------------------

--
-- Table structure for table `client_requirements`
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
  `status` enum('Under Review','Proposal Sent','Deal / Accepted','Rejected') DEFAULT 'Under Review',
  `user_id` int(11) DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_requirements`
--

INSERT INTO `client_requirements` (`id`, `client_name`, `client_email`, `client_phone`, `company_name`, `title`, `description`, `file_attachment`, `budget_estimation`, `status`, `user_id`, `client_id`, `created_at`) VALUES
(4, 'Valen Tanjung', 'ivalviary@gmail.com', '081383838399', 'PT. Djarum', 'Website Company Profile', 'Intinya bikin website bagus, budget aman', 'BRD_1793010124_95f8d8da.pdf', 20000000.00, 'Deal / Accepted', NULL, 1, '2026-10-26 10:22:04'),
(6, 'Budi Kan', 'budi@gmail.com', '081383839944', 'PT. Budi Onken', 'Mobile Application - BUDI E-COMMERCE', 'Bikin yang bagus men i swear to god, brr', 'BRD_1793087431_c4116eab.pdf', 123123123.00, 'Under Review', NULL, 2, '2026-10-27 07:50:31'),
(9, 'Valen Tanjung', 'ivalviary@gmail.com', '081383838399', 'PT. Djarum', 'Assalamualaikum Website', 'Assalamualaikum mau bikin web a.', 'BRD_1793090767_03e0d614.pdf', 1000000000.00, 'Deal / Accepted', NULL, 1, '2026-10-27 08:46:07'),
(10, 'Valen Tanjung', 'ivalviary@gmail.com', '081383838399', 'PT. Djarum', 'The best website in the world', 'letsgo', 'BRD_1793093043_c863a0a2.pdf', 10000000.00, 'Deal / Accepted', NULL, 1, '2026-10-27 09:24:03'),
(11, 'Valen Tanjung', 'ivalviary@gmail.com', '081383838399', 'PT. Djarum', 'IoT Smartphone', 'Bikinin IoT terbagus abad ini', 'BRD_1793099020_3cea4bea.pdf', 10000000.00, 'Deal / Accepted', NULL, 1, '2026-10-27 11:03:40');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
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
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `project_id`, `nomor_kontrak`, `durasi_bulan`, `tanggal_mulai`, `tanggal_selesai`, `nilai_kontrak`, `file_pdf`, `created_at`) VALUES
(1, 5, '005/CTC-SPK/10/2026', 3, '2026-10-26', '2027-01-26', 20000000.00, NULL, '2026-10-26 10:26:42'),
(2, 6, '006/CTC-SPK/10/2026', 3, '2026-10-27', '2027-01-27', 1000000000.00, NULL, '2026-10-27 08:47:39'),
(3, 7, '007/CTC-SPK/10/2026', 3, '2026-10-27', '2027-01-27', 10000000.00, NULL, '2026-10-27 09:24:41'),
(4, 8, '008/CTC-SPK/10/2026', 3, '2026-10-27', '2027-01-27', 10000000.00, NULL, '2026-10-27 11:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `deal_chats`
--

CREATE TABLE `deal_chats` (
  `id` int(11) NOT NULL,
  `requirement_id` int(11) NOT NULL,
  `sender_type` enum('admin','client') NOT NULL,
  `sender_name` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `attachment_file` varchar(250) DEFAULT NULL,
  `admin_read` tinyint(1) NOT NULL DEFAULT 0,
  `client_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deal_chats`
--

INSERT INTO `deal_chats` (`id`, `requirement_id`, `sender_type`, `sender_name`, `message`, `attachment_file`, `admin_read`, `client_read`, `created_at`) VALUES
(1, 4, 'client', 'Valen Tanjung', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Website Company Profile\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 1, 1, '2026-10-26 17:22:04'),
(2, 4, 'admin', 'Administrator', 'Baik, akan kita lakukan check terlebih dahulu, mohon ditunggu', NULL, 1, 1, '2026-10-26 17:25:52'),
(3, 4, 'admin', 'System CTC', 'Penawaran resmi proyek [ Website Company Profile ] telah dibuat (Proposal Sent). Status proyek diset \'On Hold\' hingga persetujuan Klien.', NULL, 1, 1, '2026-10-26 17:26:42'),
(4, 4, 'client', 'Valen Tanjung', 'Oke saya setuju dengan penawarannya', NULL, 1, 1, '2026-10-26 17:27:20'),
(5, 4, 'admin', 'Administrator', 'Baik, akan kita kerjakan projek nya sesuai yang tertata di penawaran', NULL, 1, 1, '2026-10-26 17:27:43'),
(6, 4, 'admin', 'Administrator', 'Terimakasih', NULL, 1, 1, '2026-10-26 17:27:48'),
(7, 4, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Deal / Accepted ]', NULL, 1, 1, '2026-10-26 17:28:35'),
(10, 6, 'client', 'Budi Kan', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Mobile Application - BUDI E-COMMERCE\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 0, 1, '2026-10-27 14:50:31'),
(18, 9, 'client', 'Valen Tanjung', 'Halo Tim Konsultan CTC, kami telah mengajukan dokumen requirement proyek: \"Assalamualaikum Website\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 1, 1, '2026-10-27 15:46:07'),
(19, 9, 'admin', 'Administrator', 'Halo Valen Tanjung, requirement proyek \"Assalamualaikum Website\" telah kami terima dan saat ini sedang dalam proses review oleh Tim CTC. Kami akan segera menghubungi Anda melalui diskusi ini.', NULL, 1, 1, '2026-10-27 15:46:27'),
(20, 9, 'admin', 'System CTC', 'Penawaran resmi proyek [ Assalamualaikum Website ] telah dibuat (Proposal Sent). Status proyek diset \'On Hold\' hingga persetujuan Klien.', NULL, 1, 1, '2026-10-27 15:47:39'),
(21, 9, 'client', 'Valen Tanjung', 'Oke saya sudah sesuai proposal nya om', NULL, 1, 1, '2026-10-27 15:47:58'),
(22, 9, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Deal / Accepted ]', NULL, 1, 1, '2026-10-27 15:48:10'),
(23, 10, 'client', 'Valen Tanjung', 'Halo Tim Konsultan CTC, kami telah mengajukan dokumen requirement proyek: \"The best website in the world\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 0, 1, '2026-10-27 16:24:03'),
(24, 10, 'admin', 'Administrator', 'Halo Valen Tanjung, requirement proyek \"The best website in the world\" telah kami terima dan saat ini sedang dalam proses review oleh Tim CTC. Kami akan segera menghubungi Anda melalui diskusi ini.', NULL, 1, 1, '2026-10-27 16:24:23'),
(25, 10, 'admin', 'System CTC', 'Penawaran resmi proyek [ The best website in the world ] telah dibuat (Proposal Sent). Status proyek diset \'On Hold\' hingga persetujuan Klien.', NULL, 1, 1, '2026-10-27 16:24:41'),
(26, 10, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Deal / Accepted ]', NULL, 1, 1, '2026-10-27 16:25:00'),
(27, 11, 'client', 'Valen Tanjung', 'Halo Tim Konsultan CTC, kami telah mengajukan dokumen requirement proyek: \"IoT Smartphone\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 1, 1, '2026-10-27 18:03:40'),
(28, 11, 'admin', 'Administrator', 'Halo Valen Tanjung, requirement proyek \"IoT Smartphone\" telah kami terima dan saat ini sedang dalam proses review oleh Tim CTC. Kami akan segera menghubungi Anda melalui diskusi ini.', NULL, 1, 1, '2026-10-27 18:04:00'),
(29, 11, 'admin', 'System CTC', 'Penawaran resmi proyek [ IoT Smartphone ] telah dibuat (Proposal Sent). Status proyek diset \'On Hold\' hingga persetujuan Klien.', NULL, 1, 1, '2026-10-27 18:04:31'),
(30, 11, 'admin', 'Administrator', 'Baik, bagaimana proposal nya apakah deal?', NULL, 1, 1, '2026-10-27 18:05:45'),
(31, 11, 'client', 'Valen Tanjung', 'deal', NULL, 1, 1, '2026-10-27 18:06:00'),
(32, 11, 'admin', 'System CTC', 'Status pengajuan diperbarui menjadi: [ Deal / Accepted ]', NULL, 1, 1, '2026-10-27 18:07:27');

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
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
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `project_id`, `kategori`, `keterangan`, `jumlah_biaya`, `tanggal_pengeluaran`) VALUES
(1, 6, 'Lainnya', 'Buat sosial 10%', 100000000.00, '2026-10-28');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
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
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `project_id`, `nomor_invoice`, `termin_ke`, `keterangan_termin`, `jumlah_tagihan`, `tanggal_tagihan`, `jatuh_tempo`, `status`, `created_at`) VALUES
(1, 6, 'INV/CTC/2026/10/001', 1, 'DP 50% Penandatanganan SPK', 500000000.00, '2026-10-27', '2026-11-10', 'Unpaid', '2026-10-27 09:18:08'),
(2, 6, 'INV/CTC/2026/10/002', 2, 'Pelunasan 50% BAST Selesai', 500000000.00, '2026-10-31', '2026-11-10', 'Paid', '2026-10-27 09:18:40'),
(3, 5, 'INV/CTC/2026/10/003', 1, 'DP 50% Penandatanganan SPK', 10000000.00, '2026-10-27', '2026-11-10', 'Unpaid', '2026-10-27 09:21:36'),
(4, 7, 'INV/CTC/2026/10/004', 2, 'Pelunasan 100%', 10000000.00, '2026-10-27', '2026-11-10', 'Paid', '2026-10-27 09:26:09'),
(5, 5, 'INV/CTC/2026/10/005', 2, 'Pelunasan 50% BAST Selesai', 10000000.00, '2026-10-27', '2026-11-10', 'Unpaid', '2026-10-27 09:27:38'),
(8, 8, 'INV/CTC/2026/10/006', 1, 'Termin 1 (DP 30% Penandatanganan SPK)', 3000000.00, '2026-10-27', '2026-11-03', 'Paid', '2026-10-27 11:12:59'),
(9, 8, 'INV/CTC/2026/10/007', 2, 'Termin 2 (Angsuran 20% Progress Pengerjaan)', 2000000.00, '2026-11-03', '2026-11-10', 'Paid', '2026-10-27 11:12:59'),
(10, 8, 'INV/CTC/2026/10/008', 3, 'Termin 3 (Pelunasan 50% BAST Selesai)', 5000000.00, '2026-11-10', '2027-01-27', 'Paid', '2026-10-27 11:12:59');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
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
  `requirement_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `nama_projek`, `klien`, `budget`, `tanggal_mulai`, `deadline`, `status`, `kategori_rab`, `rab_custom_data`, `client_token`, `requirement_id`, `created_at`) VALUES
(5, 'Website Company Profile', 'Valen Tanjung', 20000000.00, '2026-10-26', '2027-01-26', 'In Progress', 'Website', NULL, NULL, 4, '2026-10-26 10:26:42'),
(6, 'Assalamualaikum Website', 'Valen Tanjung', 1000000000.00, '2026-10-27', '2027-01-27', 'In Progress', 'Website', NULL, NULL, 9, '2026-10-27 08:47:39'),
(7, 'The best website in the world', 'Valen Tanjung', 10000000.00, '2026-10-27', '2027-01-27', 'In Progress', 'Website', NULL, NULL, 10, '2026-10-27 09:24:41'),
(8, 'IoT Smartphone', 'Valen Tanjung', 10000000.00, '2026-10-27', '2027-01-27', 'In Progress', 'IoT', NULL, NULL, 11, '2026-10-27 11:04:31');

-- --------------------------------------------------------

--
-- Table structure for table `project_files`
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
-- Dumping data for table `project_files`
--

INSERT INTO `project_files` (`id`, `project_id`, `task_id`, `nama_file`, `kategori`, `file_path`, `ukuran_file`, `uploaded_by`, `created_at`) VALUES
(9, 5, 38, 'SPK_001_CTC-SPK_10_2026.pdf', 'Deliverable / Code', '8185e4929b71de298fbe91f594c6f4d8.pdf', 2408092, 2, '2026-10-26 10:34:13');

-- --------------------------------------------------------

--
-- Table structure for table `project_logs`
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
-- Dumping data for table `project_logs`
--

INSERT INTO `project_logs` (`id`, `project_id`, `user_id`, `nama_user`, `aktivitas`, `tipe`, `created_at`) VALUES
(1, 5, 6, 'Administrator', 'Mengalokasikan modul baru \'Web Developer\' ke Tim: (Billianz Yusuf Hema S., Febrian Valentino)', 'task', '2026-10-26 10:32:11'),
(2, 5, 2, 'Febrian Valentino', 'Memperbarui progress \'Web Developer\' menjadi status: In Progress', 'info', '2026-10-26 10:33:04'),
(3, 5, 6, 'Administrator', 'Mengirimkan catatan instruksi revisi pada modul \'Web Developer\'', 'warning', '2026-10-26 10:33:28'),
(4, 5, 2, 'Febrian Valentino', 'Memperbarui progress \'Web Developer\' menjadi status: Ready for Review', 'info', '2026-10-26 10:34:13'),
(5, 5, 2, 'Febrian Valentino', 'Memperbarui progress \'Web Developer\' menjadi status: Ready for Review', 'info', '2026-10-26 10:34:28'),
(6, 5, 6, 'Administrator', 'Menyetujui (Approved) pengerjaan modul \'Web Developer\'', 'success', '2026-10-26 10:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `proposals`
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

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
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
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `project_id`, `assigned_to`, `user_id`, `nama_tugas`, `prioritas`, `status`, `progress_notes`, `updated_by_user_id`, `revision_notes`, `drive_link`, `deadline`, `updated_at`) VALUES
(38, 5, 4, 4, 'Web Developer', 'High', 'Done', 'sudah saya kerjakan', 2, NULL, 'https://www.drive.google.com', '2026-10-26', '2026-10-26 10:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `task_assignees`
--

CREATE TABLE `task_assignees` (
  `id` int(11) NOT NULL,
  `task_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `task_assignees`
--

INSERT INTO `task_assignees` (`id`, `task_id`, `user_id`, `created_at`) VALUES
(28, 38, 4, '2026-10-26 10:32:11'),
(29, 38, 2, '2026-10-26 10:32:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
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
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `jabatan`, `created_at`) VALUES
(1, 'Pak Taufik', 'taufik@gmail.com', '$2y$10$6T9Gt5TbtK3JaQQK3jGHRu39tRoDocO0q1u07SsB6UkwkLhEl1UTm', 'admin', 'Project Manager', '2026-10-21 08:33:06'),
(2, 'Febrian Valentino', 'febrian@gmail.com', '$2y$10$ZvpuyC4at2BG1hNkolk0U.8xOwoXsZWxXVyYLIJPOrdwhrnhWWt92', 'karyawan', 'Web Developer', '2026-10-21 08:33:36'),
(3, 'Rajsfano Priariya B.', 'rajsfano@gmail.com', '$2y$10$aoiSx6ngIOaUAWmBTXNQ9OxJudxeuw6HNtizoElmhQSj4PtZNl77q', 'karyawan', 'UI Designer', '2026-10-21 08:33:56'),
(4, 'Billianz Yusuf Hema S.', 'billianz@gmail.com', '$2y$10$6PcwVz3tSs7/xGXtLkYp1ONtccy0SkaMeQANmfgiD4ky0uL527AVK', 'karyawan', 'Backend Developer', '2026-10-21 08:34:21'),
(5, 'Muhammad Fackhri', 'fackhri@gmail.com', '$2y$10$z26k7CXNwIGq7OtH6Fj8oeITpN656PsTiE77n1DzE6Uz1Cz8aem7G', 'karyawan', 'IT Consultant', '2026-10-21 08:34:59'),
(6, 'Administrator', 'admin@gmail.com', '0192023a7bbd73250516f069df18b500', 'admin', 'Project Manager', '2026-10-21 08:53:09');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `client_requirements`
--
ALTER TABLE `client_requirements`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `deal_chats`
--
ALTER TABLE `deal_chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `requirement_id` (`requirement_id`),
  ADD KEY `idx_deal_chats_req` (`requirement_id`,`id`),
  ADD KEY `idx_req_id` (`requirement_id`,`id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `project_logs`
--
ALTER TABLE `project_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`);

--
-- Indexes for table `proposals`
--
ALTER TABLE `proposals`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `project_id` (`project_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_tasks_assigned_to` (`assigned_to`);

--
-- Indexes for table `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `task_id` (`task_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client_requirements`
--
ALTER TABLE `client_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `deal_chats`
--
ALTER TABLE `deal_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `project_logs`
--
ALTER TABLE `project_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `task_assignees`
--
ALTER TABLE `task_assignees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deal_chats`
--
ALTER TABLE `deal_chats`
  ADD CONSTRAINT `deal_chats_ibfk_1` FOREIGN KEY (`requirement_id`) REFERENCES `client_requirements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_deal_chats_req` FOREIGN KEY (`requirement_id`) REFERENCES `client_requirements` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `project_logs`
--
ALTER TABLE `project_logs`
  ADD CONSTRAINT `project_logs_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_assigned_to` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_assignees`
--
ALTER TABLE `task_assignees`
  ADD CONSTRAINT `task_assignees_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `task_assignees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
