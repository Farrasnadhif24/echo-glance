-- phpMyAdmin SQL Dump
-- Host: 127.0.0.1
-- Generation Time: Aug 18, 2026
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
-- Database: `echo_glance`
--

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','keluarga','tenaga_medis') NOT NULL,
  `status_verifikasi` enum('pending','approved','rejected') DEFAULT 'pending',
  `str_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT IGNORE INTO `users` (`id`, `nama`, `email`, `password`, `role`, `status_verifikasi`, `str_number`) VALUES
(1, 'Ibu Siti (Keluarga)', 'siti.keluarga@gmail.com', '$2y$12$wtAaraMga3ahgamCTyNoqecSV.Va8jrUrB5qtjDeZiD.0QrDRQSZW', 'keluarga', 'approved', NULL),
(2, 'Dr. Ahmad (Medis)', 'dr.ahmad@hospital.id', '$2y$12$wtAaraMga3ahgamCTyNoqecSV.Va8jrUrB5qtjDeZiD.0QrDRQSZW', 'tenaga_medis', 'approved', 'STR-123456789'),
(3, 'Admin Sistem', 'admin@echoglance.id', '$2y$12$wtAaraMga3ahgamCTyNoqecSV.Va8jrUrB5qtjDeZiD.0QrDRQSZW', 'admin', 'approved', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `device_status`
--

CREATE TABLE IF NOT EXISTS `device_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `mode` enum('mata_only','full_sensor') DEFAULT 'mata_only',
  `sensor_mata` tinyint(1) DEFAULT 1,
  `sensor_mpu` tinyint(1) DEFAULT 0,
  `sensor_fsr` tinyint(1) DEFAULT 0,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `patient_id` (`patient_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `device_status`
--
INSERT IGNORE INTO `device_status` (`patient_id`, `mode`, `sensor_mata`, `sensor_mpu`, `sensor_fsr`) VALUES
(1, 'mata_only', 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `jenis_kebutuhan` varchar(100) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `command_history`
--

CREATE TABLE IF NOT EXISTS `command_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL DEFAULT 1,
  `command_text` varchar(255) NOT NULL,
  `layer` varchar(50) NOT NULL,
  `response_time_ms` int(11) DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpu_log`
--

CREATE TABLE IF NOT EXISTS `mpu_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL DEFAULT 1,
  `pitch` float NOT NULL,
  `roll` float NOT NULL,
  `event_label` varchar(100) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fsr_log`
--

CREATE TABLE IF NOT EXISTS `fsr_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL DEFAULT 1,
  `gaya_rata_rata` float NOT NULL,
  `jumlah_gerakan` int(11) NOT NULL,
  `standar_deviasi` float NOT NULL,
  `sesi_label` varchar(100) NOT NULL,
  `tanggal` date NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
