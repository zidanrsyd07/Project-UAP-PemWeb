-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jun 10, 2025 at 03:09 AM
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
-- Database: `pinjamin_library`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id_book` int NOT NULL,
  `book_code` varchar(20) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(100) NOT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `publication_year` year DEFAULT NULL,
  `id_category` int DEFAULT NULL,
  `total_copies` int DEFAULT '1',
  `available_copies` int DEFAULT '1',
  `description` text,
  `image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id_book`, `book_code`, `title`, `author`, `publisher`, `publication_year`, `id_category`, `total_copies`, `available_copies`, `description`, `image`, `created_at`, `updated_at`) VALUES
(1, 'BK001', 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, 1, 3, 2, 'Novel tentang perjuangan anak-anak di Belitung', 'book1.jpg', '2025-06-10 00:02:22', '2025-06-10 01:49:19'),
(2, 'BK002', 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Hasta Mitra', 1980, 1, 2, 2, 'Novel sejarah Indonesia', 'book2.jpg', '2025-06-10 00:02:22', '2025-06-10 00:09:23'),
(3, 'BK003', 'Pemrograman Web dengan PHP', 'Budi Raharjo', 'Informatika', 2020, 3, 5, 5, 'Panduan lengkap pemrograman PHP', 'book3.jpg', '2025-06-10 00:02:22', '2025-06-10 00:09:23'),
(4, 'BK004', 'Sejarah Indonesia Modern', 'M.C. Ricklefs', 'Serambi', 2008, 4, 2, 2, 'Sejarah Indonesia dari masa kolonial hingga modern', 'book4.jpg', '2025-06-10 00:02:22', '2025-06-10 00:09:23'),
(5, 'BK005', 'Fisika Dasar', 'Halliday & Resnick', 'Erlangga', 2018, 5, 4, 4, 'Buku teks fisika untuk mahasiswa', 'book5.jpg', '2025-06-10 00:02:22', '2025-06-10 00:09:23');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id_category` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id_category`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Fiksi', 'Buku-buku cerita fiksi dan novel', '2025-06-10 00:02:22', '2025-06-10 00:02:22'),
(2, 'Non-Fiksi', 'Buku-buku pengetahuan dan referensi', '2025-06-10 00:02:22', '2025-06-10 00:02:22'),
(3, 'Teknologi', 'Buku-buku tentang teknologi dan komputer', '2025-06-10 00:02:22', '2025-06-10 00:02:22'),
(4, 'Sejarah', 'Buku-buku sejarah dan biografi', '2025-06-10 00:02:22', '2025-06-10 00:02:22'),
(5, 'Sains', 'Buku-buku sains dan penelitian', '2025-06-10 00:02:22', '2025-06-10 00:02:22');

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id_loan` int NOT NULL,
  `id_user` int NOT NULL,
  `id_book` int NOT NULL,
  `loan_date` date NOT NULL,
  `due_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `status` enum('borrowed','returned','overdue') DEFAULT 'borrowed',
  `fine` decimal(10,2) DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id_loan`, `id_user`, `id_book`, `loan_date`, `due_date`, `return_date`, `status`, `fine`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2025-06-10', '2025-06-17', NULL, 'borrowed', '0.00', NULL, '2025-06-10 01:49:19', '2025-06-10 01:49:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_user` int NOT NULL,
  `user_number` varchar(20) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` text,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_user`, `user_number`, `username`, `email`, `password`, `full_name`, `phone`, `address`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'admin@pinjamin.com', '0192023a7bbd73250516f069df18b500', 'Administrator Perpustakaan', '081234567890', 'Kantor Perpustakaan', 'admin', 'active', '2025-06-10 00:02:22', '2025-06-10 00:02:22'),
(2, 'M20250001', NULL, 'tes@gmail.com', 'b93939873fd4923043b9dec975811f66', 'Tes', '081234567891', 'Alamat Member Demo', 'member', 'active', '2025-06-10 00:02:22', '2025-06-10 02:20:30');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id_book`),
  ADD UNIQUE KEY `book_code` (`book_code`),
  ADD KEY `id_category` (`id_category`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id_category`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id_loan`),
  ADD KEY `id_user` (`id_user`),
  ADD KEY `id_book` (`id_book`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_user`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `user_number` (`user_number`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id_book` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id_category` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id_loan` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id_user` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `books`
--
ALTER TABLE `books`
  ADD CONSTRAINT `books_ibfk_1` FOREIGN KEY (`id_category`) REFERENCES `categories` (`id_category`);

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`),
  ADD CONSTRAINT `loans_ibfk_2` FOREIGN KEY (`id_book`) REFERENCES `books` (`id_book`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
