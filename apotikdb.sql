-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 08, 2025 at 05:33 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `apotikdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint NOT NULL,
  `tgl` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `detail` text,
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hutang_supplier`
--

CREATE TABLE `hutang_supplier` (
  `id` bigint NOT NULL,
  `supplier_kode` varchar(50) NOT NULL,
  `pembelian_id` bigint DEFAULT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interaksi_obat`
--

CREATE TABLE `interaksi_obat` (
  `id` int NOT NULL,
  `obat1_kode` varchar(50) NOT NULL,
  `obat2_kode` varchar(50) NOT NULL,
  `tingkat` enum('minor','moderate','major','contraindicated') NOT NULL,
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keu_kasbank`
--

CREATE TABLE `keu_kasbank` (
  `id` bigint NOT NULL,
  `tgl` datetime NOT NULL,
  `jenis` enum('KAS','BANK') NOT NULL,
  `deskripsi` varchar(255) NOT NULL,
  `debit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `kredit` decimal(15,2) NOT NULL DEFAULT '0.00',
  `ref_type` varchar(50) DEFAULT NULL,
  `ref_id` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `m_dokter`
--

CREATE TABLE `m_dokter` (
  `id` int NOT NULL,
  `sip` varchar(100) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `spesialisasi` varchar(150) DEFAULT NULL,
  `fasilitas_kesehatan` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `m_dokter`
--

INSERT INTO `m_dokter` (`id`, `sip`, `nama`, `spesialisasi`, `fasilitas_kesehatan`, `created_at`, `updated_at`) VALUES
(1, '10011', 'dr Yanuar', 'Penyakit Dalam', '-', '2025-09-07 10:40:47', NULL),
(2, 'DR26D747', 'dr. Hamdi', 'Anak', '-', '2025-09-07 11:39:30', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `m_formula_racik`
--

CREATE TABLE `m_formula_racik` (
  `id` int NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `dokter_sip` varchar(100) DEFAULT NULL,
  `komposisi` json DEFAULT NULL,
  `petunjuk` text,
  `standar` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `m_karyawan`
--

CREATE TABLE `m_karyawan` (
  `id` int NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `jabatan` varchar(100) NOT NULL,
  `level_akses` enum('farmasi','kasir','gudang','admin') NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `m_karyawan`
--

INSERT INTO `m_karyawan` (`id`, `kode`, `nama`, `jabatan`, `level_akses`, `created_at`, `updated_at`) VALUES
(1, '311', 'test', 'Staf', 'farmasi', '2025-09-07 12:33:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `m_obat`
--

CREATE TABLE `m_obat` (
  `id` int NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(255) NOT NULL,
  `produsen` varchar(255) DEFAULT NULL,
  `harga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `stok` int NOT NULL DEFAULT '0',
  `expired_date` date NOT NULL,
  `golongan` enum('OTC','OBT','OK','Psikotropika','Narkotika') NOT NULL DEFAULT 'OTC',
  `narkotika_psikotropika` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `m_obat`
--

INSERT INTO `m_obat` (`id`, `kode`, `nama`, `produsen`, `harga`, `stok`, `expired_date`, `golongan`, `narkotika_psikotropika`, `created_at`, `updated_at`) VALUES
(1, '31', 'ANTIMO', '', 100000.00, 8, '2025-09-09', 'OTC', 0, '2025-09-07 04:12:31', '2025-09-08 17:32:19'),
(2, '-', 'AMLODIPIN', '-', 2000.00, 194, '2027-09-12', 'OTC', 0, '2025-09-07 05:17:24', '2025-09-08 17:28:52'),
(3, 'OB8FEBDD', 'PARACETAMOL', '-', 2000.00, 2995, '2026-09-16', 'OTC', 0, '2025-09-07 05:21:37', '2025-09-08 17:24:32'),
(4, 'OB58C8E5', 'AMLODIPIN', '-', 10000.00, 400, '2025-09-07', 'OTC', 0, '2025-09-07 11:55:00', NULL),
(5, 'OB7860CF', 'CEFIME', '-', 1000.00, 1000, '2026-09-07', 'OTC', 0, '2025-09-07 11:55:26', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `m_pelanggan`
--

CREATE TABLE `m_pelanggan` (
  `id` int NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `tgl_lahir` date DEFAULT NULL,
  `jenis_kelamin` enum('L','P') DEFAULT NULL,
  `alergi` text,
  `kondisi_medis` text,
  `no_hp` varchar(30) DEFAULT NULL,
  `alamat` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `m_pelanggan`
--

INSERT INTO `m_pelanggan` (`id`, `kode`, `nama`, `tgl_lahir`, `jenis_kelamin`, `alergi`, `kondisi_medis`, `no_hp`, `alamat`, `created_at`, `updated_at`) VALUES
(1, 'PL02991A', 'Richi Alfasino', '1983-09-09', 'L', NULL, NULL, '085267052222', 'jln angkatan 45 lr harapan no 50 rt 41 kel lorok pakjo palembang', '2025-09-07 11:39:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `m_supplier`
--

CREATE TABLE `m_supplier` (
  `id` int NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `sertifikasi` varchar(200) DEFAULT NULL,
  `alamat` text,
  `kontak` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembelian`
--

CREATE TABLE `pembelian` (
  `id` bigint NOT NULL,
  `no_faktur` varchar(100) NOT NULL,
  `tgl` date NOT NULL,
  `supplier_kode` varchar(50) NOT NULL,
  `pajak` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pembelian_item`
--

CREATE TABLE `pembelian_item` (
  `id` bigint NOT NULL,
  `pembelian_id` bigint NOT NULL,
  `obat_kode` varchar(50) NOT NULL,
  `batch_no` varchar(100) NOT NULL,
  `expired_date` date NOT NULL,
  `qty` int NOT NULL,
  `harga_beli` decimal(15,2) NOT NULL,
  `cold_chain` tinyint(1) NOT NULL DEFAULT '0',
  `suhu_min` decimal(5,2) DEFAULT NULL,
  `suhu_max` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penjualan`
--

CREATE TABLE `penjualan` (
  `id` bigint NOT NULL,
  `no_nota` varchar(100) NOT NULL,
  `tgl` datetime NOT NULL,
  `jenis` enum('OTC','Resep','Racik') NOT NULL,
  `pelanggan_kode` varchar(50) DEFAULT NULL,
  `dokter_sip` varchar(100) DEFAULT NULL,
  `narkotika_psikotropika` tinyint(1) NOT NULL DEFAULT '0',
  `total` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `penjualan`
--

INSERT INTO `penjualan` (`id`, `no_nota`, `tgl`, `jenis`, `pelanggan_kode`, `dokter_sip`, `narkotika_psikotropika`, `total`, `created_by`, `created_at`) VALUES
(1, 'PJ20250907045421', '2025-09-07 04:54:21', 'OTC', NULL, NULL, 0, 11.00, NULL, '2025-09-07 04:54:21'),
(2, 'PJ20250907052204', '2025-09-07 05:22:04', 'OTC', NULL, NULL, 0, 1000.00, NULL, '2025-09-07 05:22:04'),
(3, 'PJ20250907053040', '2025-09-07 05:30:40', 'OTC', NULL, NULL, 0, 0.00, NULL, '2025-09-07 05:30:40'),
(4, 'PJ20250907060133', '2025-09-07 06:01:33', 'OTC', NULL, NULL, 0, 102000.00, NULL, '2025-09-07 06:01:33'),
(5, 'PJ20250907104129', '2025-09-07 10:41:29', 'OTC', NULL, NULL, 0, 2000.00, NULL, '2025-09-07 10:41:29'),
(6, 'PJ20250908170211', '2025-09-08 17:02:11', 'OTC', NULL, NULL, 0, 4000.00, 1, '2025-09-08 17:02:11'),
(7, 'PJ20250908170253', '2025-09-08 17:02:53', 'OTC', NULL, NULL, 0, 4000.00, 1, '2025-09-08 17:02:53'),
(13, 'PJ20250909002414990', '2025-09-09 00:24:32', 'OTC', NULL, NULL, 0, 4000.00, 1, '2025-09-08 17:24:32'),
(16, 'PJ20250909002546185', '2025-09-09 00:26:00', 'OTC', NULL, NULL, 0, 102000.00, 1, '2025-09-08 17:26:00'),
(17, 'PJ20250909002823410', '2025-09-09 00:28:52', 'OTC', NULL, NULL, 0, 2000.00, 1, '2025-09-08 17:28:52'),
(18, 'PJ20250909003208130', '2025-09-09 00:32:19', 'OTC', NULL, NULL, 0, 100000.00, 1, '2025-09-08 17:32:19');

-- --------------------------------------------------------

--
-- Table structure for table `penjualan_item`
--

CREATE TABLE `penjualan_item` (
  `id` bigint NOT NULL,
  `penjualan_id` bigint NOT NULL,
  `obat_kode` varchar(50) NOT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `qty` int NOT NULL,
  `harga_jual` decimal(15,2) NOT NULL,
  `dosis` varchar(100) DEFAULT NULL,
  `etiket` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `penjualan_item`
--

INSERT INTO `penjualan_item` (`id`, `penjualan_id`, `obat_kode`, `batch_no`, `qty`, `harga_jual`, `dosis`, `etiket`, `created_at`) VALUES
(1, 1, '31', NULL, 11, 1.00, '1', NULL, '2025-09-07 04:54:21'),
(2, 2, 'OB8FEBDD', 'TPK81853', 1, 1000.00, NULL, NULL, '2025-09-07 05:22:04'),
(3, 3, '31', NULL, 1, 0.00, NULL, NULL, '2025-09-07 05:30:40'),
(4, 4, '31', NULL, 1, 100000.00, NULL, NULL, '2025-09-07 06:01:33'),
(5, 4, 'OB8FEBDD', NULL, 1, 2000.00, NULL, NULL, '2025-09-07 06:01:33'),
(6, 5, '-', NULL, 1, 2000.00, NULL, '1', '2025-09-07 10:41:29'),
(7, 6, 'OB8FEBDD', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:02:11'),
(8, 6, '-', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:02:11'),
(9, 7, '-', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:02:53'),
(10, 7, 'OB8FEBDD', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:02:53'),
(11, 13, '-', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:24:32'),
(12, 13, 'OB8FEBDD', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:24:32'),
(13, 16, '-', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:26:00'),
(14, 16, '31', NULL, 1, 100000.00, NULL, NULL, '2025-09-08 17:26:00'),
(15, 17, '-', NULL, 1, 2000.00, NULL, NULL, '2025-09-08 17:28:52'),
(16, 18, '31', NULL, 1, 100000.00, NULL, NULL, '2025-09-08 17:32:19');

-- --------------------------------------------------------

--
-- Table structure for table `penjualan_racik`
--

CREATE TABLE `penjualan_racik` (
  `id` bigint NOT NULL,
  `penjualan_id` bigint NOT NULL,
  `formula_kode` varchar(50) DEFAULT NULL,
  `beyond_use_date` date DEFAULT NULL,
  `qc_note` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penjualan_racik_bahan`
--

CREATE TABLE `penjualan_racik_bahan` (
  `id` bigint NOT NULL,
  `racik_id` bigint NOT NULL,
  `obat_kode` varchar(50) NOT NULL,
  `qty` decimal(12,3) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `piutang_pelanggan`
--

CREATE TABLE `piutang_pelanggan` (
  `id` bigint NOT NULL,
  `pelanggan_kode` varchar(50) NOT NULL,
  `penjualan_id` bigint DEFAULT NULL,
  `saldo` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stok_kartu`
--

CREATE TABLE `stok_kartu` (
  `id` bigint NOT NULL,
  `tgl` datetime NOT NULL,
  `obat_kode` varchar(50) NOT NULL,
  `batch_no` varchar(100) DEFAULT NULL,
  `ref_type` enum('BELI','JUAL','RETUR_IN','RETUR_OUT','KOREKSI','RACIK_IN','RACIK_OUT') NOT NULL,
  `ref_id` bigint DEFAULT NULL,
  `qty_in` int NOT NULL DEFAULT '0',
  `qty_out` int NOT NULL DEFAULT '0',
  `saldo` int NOT NULL DEFAULT '0',
  `keterangan` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stok_kartu`
--

INSERT INTO `stok_kartu` (`id`, `tgl`, `obat_kode`, `batch_no`, `ref_type`, `ref_id`, `qty_in`, `qty_out`, `saldo`, `keterangan`, `created_at`) VALUES
(1, '2025-09-07 11:54:21', '31', NULL, 'JUAL', 1, 0, 11, -11, 'Penjualan', '2025-09-07 04:54:21'),
(2, '2025-09-07 12:22:04', 'OB8FEBDD', 'TPK81853', 'JUAL', 2, 0, 1, -1, 'Penjualan', '2025-09-07 05:22:04'),
(3, '2025-09-07 12:30:40', '31', NULL, 'JUAL', 3, 0, 1, -12, 'Penjualan', '2025-09-07 05:30:40'),
(4, '2025-09-07 13:01:33', '31', NULL, 'JUAL', 4, 0, 1, -13, 'Penjualan', '2025-09-07 06:01:33'),
(5, '2025-09-07 13:01:33', 'OB8FEBDD', NULL, 'JUAL', 4, 0, 1, -2, 'Penjualan', '2025-09-07 06:01:33'),
(6, '2025-09-07 17:41:30', '-', NULL, 'JUAL', 5, 0, 1, -1, 'Penjualan', '2025-09-07 10:41:30'),
(7, '2025-09-09 00:02:11', 'OB8FEBDD', NULL, 'JUAL', 6, 0, 1, -3, 'Penjualan', '2025-09-08 17:02:11'),
(8, '2025-09-09 00:02:11', '-', NULL, 'JUAL', 6, 0, 1, -2, 'Penjualan', '2025-09-08 17:02:11'),
(9, '2025-09-09 00:02:53', '-', NULL, 'JUAL', 7, 0, 1, -3, 'Penjualan', '2025-09-08 17:02:53'),
(10, '2025-09-09 00:02:53', 'OB8FEBDD', NULL, 'JUAL', 7, 0, 1, -4, 'Penjualan', '2025-09-08 17:02:53'),
(11, '2025-09-09 00:24:32', '-', NULL, 'JUAL', 13, 0, 1, -4, 'Penjualan', '2025-09-08 17:24:32'),
(12, '2025-09-09 00:24:32', 'OB8FEBDD', NULL, 'JUAL', 13, 0, 1, -5, 'Penjualan', '2025-09-08 17:24:32'),
(13, '2025-09-09 00:26:00', '-', NULL, 'JUAL', 16, 0, 1, -5, 'Penjualan', '2025-09-08 17:26:00'),
(14, '2025-09-09 00:26:00', '31', NULL, 'JUAL', 16, 0, 1, -14, 'Penjualan', '2025-09-08 17:26:00'),
(15, '2025-09-09 00:28:52', '-', NULL, 'JUAL', 17, 0, 1, -6, 'Penjualan', '2025-09-08 17:28:52'),
(16, '2025-09-09 00:32:19', '31', NULL, 'JUAL', 18, 0, 1, -15, 'Penjualan', '2025-09-08 17:32:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `role` enum('admin','apoteker','kasir','gudang','owner') NOT NULL DEFAULT 'apoteker',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `nama`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500', 'Administrator', 'admin', '2025-09-07 14:55:37', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_audit_user` (`user_id`);

--
-- Indexes for table `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_hutang_supplier` (`supplier_kode`);

--
-- Indexes for table `interaksi_obat`
--
ALTER TABLE `interaksi_obat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_interaksi` (`obat1_kode`,`obat2_kode`),
  ADD KEY `fk_interaksi_obat2` (`obat2_kode`);

--
-- Indexes for table `keu_kasbank`
--
ALTER TABLE `keu_kasbank`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `m_dokter`
--
ALTER TABLE `m_dokter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sip` (`sip`);

--
-- Indexes for table `m_formula_racik`
--
ALTER TABLE `m_formula_racik`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD KEY `fk_formula_dokter` (`dokter_sip`);

--
-- Indexes for table `m_karyawan`
--
ALTER TABLE `m_karyawan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `m_obat`
--
ALTER TABLE `m_obat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `m_pelanggan`
--
ALTER TABLE `m_pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `m_supplier`
--
ALTER TABLE `m_supplier`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_faktur` (`no_faktur`),
  ADD KEY `fk_beli_supplier` (`supplier_kode`);

--
-- Indexes for table `pembelian_item`
--
ALTER TABLE `pembelian_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_beli_item` (`pembelian_id`),
  ADD KEY `fk_beli_item_obat` (`obat_kode`);

--
-- Indexes for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_nota` (`no_nota`),
  ADD KEY `fk_jual_pelanggan` (`pelanggan_kode`),
  ADD KEY `fk_jual_dokter` (`dokter_sip`),
  ADD KEY `fk_jual_user` (`created_by`);

--
-- Indexes for table `penjualan_item`
--
ALTER TABLE `penjualan_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jual_item` (`penjualan_id`),
  ADD KEY `fk_jual_item_obat` (`obat_kode`);

--
-- Indexes for table `penjualan_racik`
--
ALTER TABLE `penjualan_racik`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_racik_jual` (`penjualan_id`),
  ADD KEY `fk_racik_formula` (`formula_kode`);

--
-- Indexes for table `penjualan_racik_bahan`
--
ALTER TABLE `penjualan_racik_bahan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_racik_bahan` (`racik_id`),
  ADD KEY `fk_racik_bahan_obat` (`obat_kode`);

--
-- Indexes for table `piutang_pelanggan`
--
ALTER TABLE `piutang_pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_piutang_pelanggan` (`pelanggan_kode`);

--
-- Indexes for table `stok_kartu`
--
ALTER TABLE `stok_kartu`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_stok_obat` (`obat_kode`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interaksi_obat`
--
ALTER TABLE `interaksi_obat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `keu_kasbank`
--
ALTER TABLE `keu_kasbank`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `m_dokter`
--
ALTER TABLE `m_dokter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `m_formula_racik`
--
ALTER TABLE `m_formula_racik`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `m_karyawan`
--
ALTER TABLE `m_karyawan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `m_obat`
--
ALTER TABLE `m_obat`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `m_pelanggan`
--
ALTER TABLE `m_pelanggan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `m_supplier`
--
ALTER TABLE `m_supplier`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pembelian`
--
ALTER TABLE `pembelian`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pembelian_item`
--
ALTER TABLE `pembelian_item`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penjualan`
--
ALTER TABLE `penjualan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `penjualan_item`
--
ALTER TABLE `penjualan_item`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `penjualan_racik`
--
ALTER TABLE `penjualan_racik`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penjualan_racik_bahan`
--
ALTER TABLE `penjualan_racik_bahan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `piutang_pelanggan`
--
ALTER TABLE `piutang_pelanggan`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stok_kartu`
--
ALTER TABLE `stok_kartu`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `hutang_supplier`
--
ALTER TABLE `hutang_supplier`
  ADD CONSTRAINT `fk_hutang_supplier` FOREIGN KEY (`supplier_kode`) REFERENCES `m_supplier` (`kode`);

--
-- Constraints for table `interaksi_obat`
--
ALTER TABLE `interaksi_obat`
  ADD CONSTRAINT `fk_interaksi_obat1` FOREIGN KEY (`obat1_kode`) REFERENCES `m_obat` (`kode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_interaksi_obat2` FOREIGN KEY (`obat2_kode`) REFERENCES `m_obat` (`kode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `m_formula_racik`
--
ALTER TABLE `m_formula_racik`
  ADD CONSTRAINT `fk_formula_dokter` FOREIGN KEY (`dokter_sip`) REFERENCES `m_dokter` (`sip`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `pembelian`
--
ALTER TABLE `pembelian`
  ADD CONSTRAINT `fk_beli_supplier` FOREIGN KEY (`supplier_kode`) REFERENCES `m_supplier` (`kode`);

--
-- Constraints for table `pembelian_item`
--
ALTER TABLE `pembelian_item`
  ADD CONSTRAINT `fk_beli_item` FOREIGN KEY (`pembelian_id`) REFERENCES `pembelian` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_beli_item_obat` FOREIGN KEY (`obat_kode`) REFERENCES `m_obat` (`kode`);

--
-- Constraints for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `fk_jual_dokter` FOREIGN KEY (`dokter_sip`) REFERENCES `m_dokter` (`sip`),
  ADD CONSTRAINT `fk_jual_pelanggan` FOREIGN KEY (`pelanggan_kode`) REFERENCES `m_pelanggan` (`kode`),
  ADD CONSTRAINT `fk_jual_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `penjualan_item`
--
ALTER TABLE `penjualan_item`
  ADD CONSTRAINT `fk_jual_item` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_jual_item_obat` FOREIGN KEY (`obat_kode`) REFERENCES `m_obat` (`kode`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `penjualan_racik`
--
ALTER TABLE `penjualan_racik`
  ADD CONSTRAINT `fk_racik_formula` FOREIGN KEY (`formula_kode`) REFERENCES `m_formula_racik` (`kode`),
  ADD CONSTRAINT `fk_racik_jual` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `penjualan_racik_bahan`
--
ALTER TABLE `penjualan_racik_bahan`
  ADD CONSTRAINT `fk_racik_bahan` FOREIGN KEY (`racik_id`) REFERENCES `penjualan_racik` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_racik_bahan_obat` FOREIGN KEY (`obat_kode`) REFERENCES `m_obat` (`kode`);

--
-- Constraints for table `piutang_pelanggan`
--
ALTER TABLE `piutang_pelanggan`
  ADD CONSTRAINT `fk_piutang_pelanggan` FOREIGN KEY (`pelanggan_kode`) REFERENCES `m_pelanggan` (`kode`);

--
-- Constraints for table `stok_kartu`
--
ALTER TABLE `stok_kartu`
  ADD CONSTRAINT `fk_stok_obat` FOREIGN KEY (`obat_kode`) REFERENCES `m_obat` (`kode`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
