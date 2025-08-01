-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 01, 2025 at 11:23 AM
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
-- Database: `sskpaoii_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `donations`
--

CREATE TABLE `donations` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `donor_name` varchar(255) DEFAULT NULL,
  `log_type` enum('add','subtract') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `donation_items`
--

CREATE TABLE `donation_items` (
  `id` int(11) NOT NULL,
  `donation_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `healthstaff_shelters`
--

CREATE TABLE `healthstaff_shelters` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospital_daily_reports`
--

CREATE TABLE `hospital_daily_reports` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `report_date` date NOT NULL,
  `total_patients` int(11) DEFAULT 0,
  `male_patients` int(11) DEFAULT 0,
  `female_patients` int(11) DEFAULT 0,
  `pregnant_women` int(11) DEFAULT 0,
  `disabled_patients` int(11) DEFAULT 0,
  `bedridden_patients` int(11) DEFAULT 0,
  `elderly_patients` int(11) DEFAULT 0,
  `child_patients` int(11) DEFAULT 0,
  `chronic_disease_patients` int(11) DEFAULT 0,
  `diabetes_patients` int(11) DEFAULT 0,
  `hypertension_patients` int(11) DEFAULT 0,
  `heart_disease_patients` int(11) DEFAULT 0,
  `mental_health_patients` int(11) DEFAULT 0,
  `kidney_disease_patients` int(11) DEFAULT 0,
  `other_monitored_diseases` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hospital_update_logs`
--

CREATE TABLE `hospital_update_logs` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `operation_type` enum('add','subtract') NOT NULL,
  `report_date` date NOT NULL,
  `old_total_patients` int(11) DEFAULT 0,
  `old_male_patients` int(11) DEFAULT 0,
  `old_female_patients` int(11) DEFAULT 0,
  `old_pregnant_women` int(11) DEFAULT 0,
  `old_disabled_patients` int(11) DEFAULT 0,
  `old_bedridden_patients` int(11) DEFAULT 0,
  `old_elderly_patients` int(11) DEFAULT 0,
  `old_child_patients` int(11) DEFAULT 0,
  `old_chronic_disease_patients` int(11) DEFAULT 0,
  `old_diabetes_patients` int(11) DEFAULT 0,
  `old_hypertension_patients` int(11) DEFAULT 0,
  `old_heart_disease_patients` int(11) DEFAULT 0,
  `old_mental_health_patients` int(11) DEFAULT 0,
  `old_kidney_disease_patients` int(11) DEFAULT 0,
  `old_other_monitored_diseases` int(11) DEFAULT 0,
  `change_total_patients` int(11) DEFAULT 0,
  `change_male_patients` int(11) DEFAULT 0,
  `change_female_patients` int(11) DEFAULT 0,
  `change_pregnant_women` int(11) DEFAULT 0,
  `change_disabled_patients` int(11) DEFAULT 0,
  `change_bedridden_patients` int(11) DEFAULT 0,
  `change_elderly_patients` int(11) DEFAULT 0,
  `change_child_patients` int(11) DEFAULT 0,
  `change_chronic_disease_patients` int(11) DEFAULT 0,
  `change_diabetes_patients` int(11) DEFAULT 0,
  `change_hypertension_patients` int(11) DEFAULT 0,
  `change_heart_disease_patients` int(11) DEFAULT 0,
  `change_mental_health_patients` int(11) DEFAULT 0,
  `change_kidney_disease_patients` int(11) DEFAULT 0,
  `change_other_monitored_diseases` int(11) DEFAULT 0,
  `new_total_patients` int(11) DEFAULT 0,
  `new_male_patients` int(11) DEFAULT 0,
  `new_female_patients` int(11) DEFAULT 0,
  `new_pregnant_women` int(11) DEFAULT 0,
  `new_disabled_patients` int(11) DEFAULT 0,
  `new_bedridden_patients` int(11) DEFAULT 0,
  `new_elderly_patients` int(11) DEFAULT 0,
  `new_child_patients` int(11) DEFAULT 0,
  `new_chronic_disease_patients` int(11) DEFAULT 0,
  `new_diabetes_patients` int(11) DEFAULT 0,
  `new_hypertension_patients` int(11) DEFAULT 0,
  `new_heart_disease_patients` int(11) DEFAULT 0,
  `new_mental_health_patients` int(11) DEFAULT 0,
  `new_kidney_disease_patients` int(11) DEFAULT 0,
  `new_other_monitored_diseases` int(11) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `occupant_update_logs`
--

CREATE TABLE `occupant_update_logs` (
  `log_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `log_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `operation_type` enum('add','subtract') NOT NULL,
  `total_change` int(11) DEFAULT 0,
  `male_change` int(11) DEFAULT 0,
  `female_change` int(11) DEFAULT 0,
  `pregnant_change` int(11) DEFAULT 0,
  `disabled_change` int(11) DEFAULT 0,
  `bedridden_change` int(11) DEFAULT 0,
  `elderly_change` int(11) DEFAULT 0,
  `child_change` int(11) DEFAULT 0,
  `chronic_disease_change` int(11) DEFAULT 0,
  `diabetes_change` int(11) DEFAULT 0,
  `hypertension_change` int(11) DEFAULT 0,
  `heart_disease_change` int(11) DEFAULT 0,
  `mental_health_change` int(11) DEFAULT 0,
  `kidney_disease_change` int(11) DEFAULT 0,
  `other_monitored_diseases_change` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('maintenance_message', 'ขณะนี้ระบบกำลังปิดปรับปรุงชั่วคราว ขออภัยในความไม่สะดวก'),
('system_status', '0');

-- --------------------------------------------------------

--
-- Table structure for table `shelters`
--

CREATE TABLE `shelters` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(100) NOT NULL,
  `capacity` int(11) DEFAULT 0,
  `current_occupancy` int(11) DEFAULT 0,
  `coordinator` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `amphoe` varchar(100) DEFAULT NULL,
  `tambon` varchar(100) DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `requires_detailed_report` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shelter_logs`
--

CREATE TABLE `shelter_logs` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_unit` varchar(50) NOT NULL,
  `change_amount` int(11) NOT NULL,
  `log_type` enum('add','subtract') NOT NULL,
  `new_total` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shelter_occupant_details`
--

CREATE TABLE `shelter_occupant_details` (
  `id` int(11) NOT NULL,
  `shelter_id` int(11) DEFAULT NULL,
  `male` int(11) NOT NULL,
  `female` int(11) NOT NULL,
  `pregnant` int(11) DEFAULT 0,
  `disabled` int(11) DEFAULT 0,
  `bedridden` int(11) DEFAULT 0,
  `elderly` int(11) DEFAULT 0,
  `children` int(11) DEFAULT 0,
  `diabetes` int(11) DEFAULT 0,
  `hypertension` int(11) DEFAULT 0,
  `heart_disease` int(11) DEFAULT 0,
  `psychiatric` int(11) DEFAULT 0,
  `kidney_dialysis` int(11) DEFAULT 0,
  `other_conditions` text DEFAULT NULL,
  `status` enum('เพิ่ม','ลด') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `shelter_requests`
--

CREATE TABLE `shelter_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `request_type` enum('assign','create') NOT NULL,
  `shelter_id` int(11) DEFAULT NULL,
  `new_shelter_data` text DEFAULT NULL COMMENT 'เก็บข้อมูล JSON สำหรับศูนย์ใหม่',
  `user_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL COMMENT 'ID ของ Admin ที่จัดการ',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_shelters`
--

CREATE TABLE `staff_shelters` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `shelter_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Coordinator','HealthStaff','User') NOT NULL DEFAULT 'User',
  `status` enum('Active','Inactive','Pending') NOT NULL DEFAULT 'Pending',
  `assigned_shelter_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `has_pending_request` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = มีคำร้องที่ยังไม่ถูกจัดการ',
  `verification_code` varchar(255) DEFAULT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='0 = ยังไม่ยืนยัน, 1 = ยืนยันแล้ว';

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `assigned_shelter_id`, `created_at`, `has_pending_request`, `verification_code`, `is_verified`) VALUES
(229, 'ปฐวีกานต์ ศรีคราม', 'admin@mail.com', '$2y$10$Aj6gikT/9Ljcdw7zb9xzR./MjDn5PzD6I4CThyyLx24UJuA1UmmSq', 'Admin', 'Active', NULL, '2025-08-01 09:03:33', 0, NULL, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `donations`
--
ALTER TABLE `donations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shelter_id` (`shelter_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `donation_items`
--
ALTER TABLE `donation_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `donation_id` (`donation_id`);

--
-- Indexes for table `healthstaff_shelters`
--
ALTER TABLE `healthstaff_shelters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`user_id`,`shelter_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `hospital_daily_reports`
--
ALTER TABLE `hospital_daily_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_shelter_date` (`shelter_id`,`report_date`),
  ADD KEY `idx_report_date` (`report_date`),
  ADD KEY `idx_shelter_date` (`shelter_id`,`report_date`);

--
-- Indexes for table `hospital_update_logs`
--
ALTER TABLE `hospital_update_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shelter_date` (`shelter_id`,`report_date`),
  ADD KEY `idx_operation_type` (`operation_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `occupant_update_logs`
--
ALTER TABLE `occupant_update_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_shelter_id` (`shelter_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_log_timestamp` (`log_timestamp`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `shelters`
--
ALTER TABLE `shelters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shelter_logs`
--
ALTER TABLE `shelter_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `shelter_occupant_details`
--
ALTER TABLE `shelter_occupant_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shelter_id` (`shelter_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `shelter_requests`
--
ALTER TABLE `shelter_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `staff_shelters`
--
ALTER TABLE `staff_shelters`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `shelter_id` (`shelter_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `users_ibfk_1` (`assigned_shelter_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `donations`
--
ALTER TABLE `donations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `donation_items`
--
ALTER TABLE `donation_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `healthstaff_shelters`
--
ALTER TABLE `healthstaff_shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `hospital_daily_reports`
--
ALTER TABLE `hospital_daily_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=371;

--
-- AUTO_INCREMENT for table `hospital_update_logs`
--
ALTER TABLE `hospital_update_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `occupant_update_logs`
--
ALTER TABLE `occupant_update_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=208;

--
-- AUTO_INCREMENT for table `shelters`
--
ALTER TABLE `shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=418;

--
-- AUTO_INCREMENT for table `shelter_logs`
--
ALTER TABLE `shelter_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=393;

--
-- AUTO_INCREMENT for table `shelter_occupant_details`
--
ALTER TABLE `shelter_occupant_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `shelter_requests`
--
ALTER TABLE `shelter_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staff_shelters`
--
ALTER TABLE `staff_shelters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=230;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `donations`
--
ALTER TABLE `donations`
  ADD CONSTRAINT `donations_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `donations_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `donation_items`
--
ALTER TABLE `donation_items`
  ADD CONSTRAINT `donation_items_ibfk_1` FOREIGN KEY (`donation_id`) REFERENCES `donations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `healthstaff_shelters`
--
ALTER TABLE `healthstaff_shelters`
  ADD CONSTRAINT `healthstaff_shelters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `healthstaff_shelters_ibfk_2` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospital_daily_reports`
--
ALTER TABLE `hospital_daily_reports`
  ADD CONSTRAINT `hospital_daily_reports_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hospital_update_logs`
--
ALTER TABLE `hospital_update_logs`
  ADD CONSTRAINT `hospital_update_logs_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `occupant_update_logs`
--
ALTER TABLE `occupant_update_logs`
  ADD CONSTRAINT `fk_log_shelter` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `shelter_occupant_details`
--
ALTER TABLE `shelter_occupant_details`
  ADD CONSTRAINT `shelter_occupant_details_ibfk_1` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`),
  ADD CONSTRAINT `shelter_occupant_details_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `shelter_requests`
--
ALTER TABLE `shelter_requests`
  ADD CONSTRAINT `fk_request_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_shelters`
--
ALTER TABLE `staff_shelters`
  ADD CONSTRAINT `staff_shelters_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `staff_shelters_ibfk_2` FOREIGN KEY (`shelter_id`) REFERENCES `shelters` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`assigned_shelter_id`) REFERENCES `shelters` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
