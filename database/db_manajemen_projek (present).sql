-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 21, 2026 at 11:44 AM
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
(1, 'Kenneth', 'kennethputh@gmail.com', '$2y$10$iggEYwP2QlWtA17jsdhIouLYnh4ARoTxtj6Jy.lyueGKNMF1SKSwO', 1, NULL, NULL, NULL, NULL, '2026-10-21 08:37:11'),
(2, 'Ival', 'ivalviary@gmail.com', '$2y$10$2KSeEQnNMYPMjT20cpbiRumcD/egFExNvthy/a1k9aiX.NkMSPXHe', 1, NULL, NULL, NULL, NULL, '2026-10-21 08:41:48');

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
(1, 'Kenneth', 'kennethputh@gmail.com', '081322221112', 'PT. Puth Family', 'Website Company Profile', 'Bikin landing page, company profile yang bagus dan mewah', 'BRD_1792571831_32aee711.pdf', 20000000.00, 'Under Review', NULL, 1, '2026-10-21 08:37:11'),
(2, 'Ival', 'ivalviary@gmail.com', '081323153334', 'PT. VIARY INDONESIA', 'Mobile Application Smart', 'Tester', 'BRD_1792572108_1184b74d.pdf', 10000000.00, 'Deal / Accepted', NULL, 2, '2026-10-21 08:41:48');

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
(1, 1, '001/CTC-SPK/10/2026', 3, '2026-10-21', '2027-01-21', 10000000.00, NULL, '2026-10-21 09:28:23');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deal_chats`
--

INSERT INTO `deal_chats` (`id`, `requirement_id`, `sender_type`, `sender_name`, `message`, `attachment_file`, `admin_read`, `client_read`, `created_at`) VALUES
(55, 1, 'client', 'Kenneth', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Website Company Profile\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 1, 1, '2026-10-21 08:37:11'),
(56, 1, 'admin', 'Pak Taufik', 'Baik, sedang dalam peninjauan mohon ditunggu', NULL, 1, 1, '2026-10-21 08:39:05'),
(57, 1, 'client', 'Kenneth', 'Baik', NULL, 1, 1, '2026-10-21 08:39:23'),
(58, 2, 'client', 'Ival', 'Halo Tim Konsultan PT. CTC, kami telah mengajukan dokumen requirement proyek: \"Mobile Application Smart\". Mohon dapat ditinjau untuk penyusunan Surat Penawaran Resmi.', NULL, 1, 1, '2026-10-21 08:41:48'),
(59, 2, 'admin', 'Administrator', 'Baik, mohon ditunggu ya pak', NULL, 1, 1, '2026-10-21 08:59:02'),
(60, 2, 'client', 'Ival', 'Gimana pak?', NULL, 1, 1, '2026-10-21 09:26:28'),
(61, 2, 'admin', 'Administrator', 'Mohon bersabar mas', NULL, 1, 1, '2026-10-21 09:27:14'),
(62, 2, 'admin', 'System CTC', 'Requirement resmi disetujui & dikonversi menjadi Proyek Aktif: [ Mobile Application Smart ]', NULL, 0, 1, '2026-10-21 09:28:23');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `projects`
--

INSERT INTO `projects` (`id`, `nama_projek`, `klien`, `budget`, `tanggal_mulai`, `deadline`, `status`, `kategori_rab`, `rab_custom_data`, `client_token`, `created_at`) VALUES
(1, 'Mobile Application Smart', 'Ival', 10000000.00, '2026-10-21', '2027-01-21', 'On Hold', 'Mobile', NULL, NULL, '2026-10-21 09:28:23');

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
(110, 1, 6, 'Administrator', 'Memperbarui konfigurasi proyek, status \'On Hold\' & Kategori RAB \'Mobile\'', 'info', '2026-10-21 09:28:57');

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
(2, 'Febrian Valentino', 'febrian@gmail.com', '$2y$10$cek87vPT1s.PZmGSiTil6O4dau4JkK/DjHI5OUoN8HiaPi1O8pnqW', 'karyawan', 'Web Developer', '2026-10-21 08:33:36'),
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
  ADD KEY `idx_deal_chats_req` (`requirement_id`,`id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `deal_chats`
--
ALTER TABLE `deal_chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=63;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `project_logs`
--
ALTER TABLE `project_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=111;

--
-- AUTO_INCREMENT for table `proposals`
--
ALTER TABLE `proposals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `task_assignees`
--
ALTER TABLE `task_assignees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  ADD CONSTRAINT `deal_chats_ibfk_1` FOREIGN KEY (`requirement_id`) REFERENCES `client_requirements` (`id`) ON DELETE CASCADE;

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
