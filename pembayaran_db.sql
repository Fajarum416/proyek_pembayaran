-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Jul 2025 pada 14.25
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pembayaran_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `payment_history`
--

CREATE TABLE `payment_history` (
  `transaction_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `proof_image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `payment_history`
--

INSERT INTO `payment_history` (`transaction_id`, `student_id`, `payment_date`, `amount`, `proof_image_url`, `created_at`) VALUES
(5, 6, '2025-07-17', 6000000.00, NULL, '2025-07-17 04:30:36'),
(6, 8, '2025-07-17', 2000000.00, NULL, '2025-07-17 04:31:05'),
(7, 9, '2025-07-17', 4000000.00, NULL, '2025-07-17 04:31:23'),
(8, 10, '2025-07-17', 3500000.00, NULL, '2025-07-17 04:35:12'),
(9, 11, '2025-07-17', 3500000.00, NULL, '2025-07-17 04:37:05'),
(10, 12, '2025-07-17', 4000000.00, NULL, '2025-07-17 04:38:09'),
(11, 13, '2025-07-17', 500000.00, NULL, '2025-07-17 04:39:10'),
(12, 14, '2025-07-17', 3500000.00, NULL, '2025-07-17 04:42:05'),
(13, 15, '2025-07-17', 3000000.00, NULL, '2025-07-17 04:42:59'),
(14, 17, '2025-07-17', 5000000.00, NULL, '2025-07-17 04:46:40'),
(15, 19, '2025-07-17', 1500000.00, NULL, '2025-07-17 04:49:01'),
(16, 20, '2025-07-17', 2000000.00, NULL, '2025-07-17 04:49:44'),
(17, 21, '2025-07-17', 3900000.00, NULL, '2025-07-17 04:50:16'),
(18, 22, '2025-07-17', 1000000.00, NULL, '2025-07-17 04:51:48'),
(19, 23, '2025-07-17', 3500000.00, NULL, '2025-07-17 04:52:36'),
(20, 24, '2025-07-17', 3000000.00, NULL, '2025-07-17 04:53:10'),
(21, 25, '2025-07-17', 1000000.00, NULL, '2025-07-17 04:53:40'),
(22, 26, '2025-07-17', 3500000.00, NULL, '2025-07-17 04:54:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_bill` decimal(15,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `students`
--

INSERT INTO `students` (`id`, `name`, `total_bill`, `created_at`) VALUES
(6, 'DIFA', 7000000.00, '2025-07-17 04:30:36'),
(7, 'ASIYAH', 7000000.00, '2025-07-17 04:30:48'),
(8, 'ARGO SEPTIO', 7000000.00, '2025-07-17 04:31:05'),
(9, 'PANJI', 7000000.00, '2025-07-17 04:31:23'),
(10, 'ANISA', 7000000.00, '2025-07-17 04:35:12'),
(11, 'SINDI TIASTUTI', 7000000.00, '2025-07-17 04:37:05'),
(12, 'BELA NUR RAHMADANI', 7000000.00, '2025-07-17 04:38:09'),
(13, 'RIZKA PITRIYANTI', 7000000.00, '2025-07-17 04:39:10'),
(14, 'ADELIA PUTRI RAMADHANTI', 7000000.00, '2025-07-17 04:42:05'),
(15, 'SUSI LUKMANA', 7000000.00, '2025-07-17 04:42:59'),
(16, 'RETNO WIDURI', 7000000.00, '2025-07-17 04:45:01'),
(17, 'SRI ANJANI', 7000000.00, '2025-07-17 04:46:40'),
(18, 'DINDA AULIA PUTRI', 7000000.00, '2025-07-17 04:47:16'),
(19, 'DENI IMAM FAUZI', 7000000.00, '2025-07-17 04:48:26'),
(20, 'LAELA PUTRI WARDANI', 7000000.00, '2025-07-17 04:49:44'),
(21, 'TUTUT ASTUTI', 7000000.00, '2025-07-17 04:50:16'),
(22, 'PRAMITA FATMA NUR APRILIA', 7000000.00, '2025-07-17 04:51:48'),
(23, 'WIJAYA THORIQ ABDUL AZIZ', 7000000.00, '2025-07-17 04:52:36'),
(24, 'HAIKAL FAOZAN ARIF', 7000000.00, '2025-07-17 04:53:10'),
(25, 'AZHAR MALIKI', 7000000.00, '2025-07-17 04:53:40'),
(26, 'TIGNO HERMAWAN', 7000000.00, '2025-07-17 04:54:26');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`) VALUES
(2, 'admin', '$2b$10$yUufyvOiQYJtk.ApnYRzreKMgd1RadI7T935pf4nYJFqqjNkngq5y', '2025-07-17 07:21:42');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `payment_history`
--
ALTER TABLE `payment_history`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indeks untuk tabel `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `payment_history`
--
ALTER TABLE `payment_history`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT untuk tabel `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `payment_history`
--
ALTER TABLE `payment_history`
  ADD CONSTRAINT `payment_history_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
