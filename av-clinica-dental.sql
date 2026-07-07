-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 07, 2026 at 05:07 AM
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
-- Database: `av-clinica-dental`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `service` varchar(100) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `schedule_id`, `clinic_id`, `service`, `date`, `status`, `created_at`) VALUES
(10, 1, 14, 1, 'cleaning', '2026-06-26', 'Cancelled', '2026-06-25 15:05:28'),
(11, 4, 15, 1, 'fluoride', '2026-07-02', 'Completed', '2026-07-01 15:12:50');

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `clinic_id` int(11) NOT NULL,
  `clinic_name` varchar(100) NOT NULL,
  `clinic_address` varchar(100) NOT NULL,
  `clinic_contact` varchar(15) NOT NULL,
  `clinic_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`clinic_id`, `clinic_name`, `clinic_address`, `clinic_contact`, `clinic_image`) VALUES
(1, 'Alcala Branch', 'Zone 4, Tupang, Alcala, Cagayan', '0912-345-6789', NULL),
(2, 'Tuguegarao Branch', 'Bartolome St., Caggay, Tuguegarao City, Cagayan', '0912-345-6789', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `civil_status` varchar(20) DEFAULT NULL,
  `home_address` varchar(255) DEFAULT NULL,
  `work_address` varchar(255) DEFAULT NULL,
  `fb_account` varchar(255) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `office_contact` varchar(20) DEFAULT NULL,
  `guardian_name` varchar(100) DEFAULT NULL,
  `guardian_contact` varchar(20) DEFAULT NULL,
  `physician_name` varchar(100) DEFAULT NULL,
  `physician_contact` varchar(20) DEFAULT NULL,
  `physician_address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `user_id`, `firstname`, `lastname`, `middlename`, `age`, `gender`, `phone_number`, `email`, `birthdate`, `civil_status`, `home_address`, `work_address`, `fb_account`, `occupation`, `office_contact`, `guardian_name`, `guardian_contact`, `physician_name`, `physician_contact`, `physician_address`, `created_at`) VALUES
(1, NULL, 'sheesshh', 'palo', 'santossss', 23, 'Female', '09123456789', 'palokaboi@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-25 15:05:28'),
(2, NULL, 'Angelo', 'Cabulay', '', 24, 'Male', '09123456789', 'palokaboi@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-01 15:12:20'),
(3, NULL, 'Angelo', 'Cabulay', '', 24, 'Male', '09123456789', 'palokaboi@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-01 15:12:37'),
(4, NULL, 'Angelo', 'Cabulay', 'boi', 24, 'Male', '09123456789', 'palokaboi@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-01 15:12:50');

-- --------------------------------------------------------

--
-- Table structure for table `patient_conditions`
--

CREATE TABLE `patient_conditions` (
  `condition_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `condition` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_consent`
--

CREATE TABLE `patient_consent` (
  `consent_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `consent_name` varchar(100) DEFAULT NULL,
  `consent_for` varchar(20) DEFAULT NULL,
  `consent_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_dental_history`
--

CREATE TABLE `patient_dental_history` (
  `dental_history_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `previous_dentist` varchar(100) DEFAULT NULL,
  `last_dental_visit` date DEFAULT NULL,
  `treatment_done` varchar(255) DEFAULT NULL,
  `reason_for_visit` varchar(255) DEFAULT NULL,
  `referred_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medical_history`
--

CREATE TABLE `patient_medical_history` (
  `medical_history_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `good_health` tinyint(1) DEFAULT NULL,
  `medical_condition` tinyint(1) DEFAULT NULL,
  `medical_condition_detail` varchar(255) DEFAULT NULL,
  `serious_illness` tinyint(1) DEFAULT NULL,
  `serious_illness_detail` varchar(255) DEFAULT NULL,
  `hospitalized` tinyint(1) DEFAULT NULL,
  `hospitalized_detail` varchar(255) DEFAULT NULL,
  `medication` tinyint(1) DEFAULT NULL,
  `medication_detail` varchar(255) DEFAULT NULL,
  `smoke` tinyint(1) DEFAULT NULL,
  `alcohol` tinyint(1) DEFAULT NULL,
  `drugs` tinyint(1) DEFAULT NULL,
  `allergy` tinyint(1) DEFAULT NULL,
  `allergy_detail` varchar(255) DEFAULT NULL,
  `pregnant` tinyint(1) DEFAULT NULL,
  `nursing` tinyint(1) DEFAULT NULL,
  `birth_control` tinyint(1) DEFAULT NULL,
  `cond_others` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `sched_date` date NOT NULL,
  `max_appointments` smallint(6) NOT NULL DEFAULT 8
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`schedule_id`, `clinic_id`, `sched_date`, `max_appointments`) VALUES
(14, 1, '2026-06-26', 8),
(15, 1, '2026-07-02', 8),
(16, 1, '2026-07-10', 8);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_role` enum('Patient','Admin','Dental Assistant') NOT NULL DEFAULT 'Patient'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `username`, `password`, `user_role`) VALUES
(4, 'e@gmail.com', 'test', '$2y$10$teQbwME4SpYXK81FOA6i3.ljEz/tNajs.st/0mHCGOGwnOzMoGtMG', 'Patient'),
(6, 'test1@gmail.com', 'test2', '$2y$10$F4/Rozsj2sLqEMNK8DshzOewNtgICvfP7RwxRLdjtOisRNgmmvm9S', 'Patient'),
(7, 'admin@gmail.com', 'admin', '$2y$10$yjiG6c81sf6NPj8gEWkR8.6BEFnug.jLEry2zzD7L9gGzhxY/NTGm', 'Admin');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_patient_information`
-- (See below for the actual view)
--
CREATE TABLE `vw_patient_information` (
`patient_id` int(11)
,`user_id` int(11)
,`firstname` varchar(100)
,`middlename` varchar(100)
,`lastname` varchar(100)
,`full_name` varchar(302)
,`age` int(11)
,`gender` varchar(50)
,`birthdate` date
,`civil_status` varchar(20)
,`phone_number` varchar(20)
,`email` varchar(255)
,`home_address` varchar(255)
,`work_address` varchar(255)
,`occupation` varchar(100)
,`office_contact` varchar(20)
,`fb_account` varchar(255)
,`guardian_name` varchar(100)
,`guardian_contact` varchar(20)
,`physician_name` varchar(100)
,`physician_contact` varchar(20)
,`physician_address` varchar(255)
,`previous_dentist` varchar(100)
,`last_dental_visit` date
,`treatment_done` varchar(255)
,`reason_for_visit` varchar(255)
,`referred_by` varchar(100)
,`good_health` tinyint(1)
,`medical_condition` tinyint(1)
,`medical_condition_detail` varchar(255)
,`serious_illness` tinyint(1)
,`serious_illness_detail` varchar(255)
,`hospitalized` tinyint(1)
,`hospitalized_detail` varchar(255)
,`medication` tinyint(1)
,`medication_detail` varchar(255)
,`smoke` tinyint(1)
,`alcohol` tinyint(1)
,`drugs` tinyint(1)
,`allergy` tinyint(1)
,`allergy_detail` varchar(255)
,`pregnant` tinyint(1)
,`nursing` tinyint(1)
,`birth_control` tinyint(1)
,`patient_conditions` mediumtext
,`consent_name` varchar(100)
,`consent_for` varchar(20)
,`consent_date` date
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `vw_patient_information`
--
DROP TABLE IF EXISTS `vw_patient_information`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_patient_information`  AS SELECT `p`.`patient_id` AS `patient_id`, `p`.`user_id` AS `user_id`, `p`.`firstname` AS `firstname`, `p`.`middlename` AS `middlename`, `p`.`lastname` AS `lastname`, concat(`p`.`firstname`,' ',coalesce(`p`.`middlename`,''),' ',`p`.`lastname`) AS `full_name`, `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`birthdate` AS `birthdate`, `p`.`civil_status` AS `civil_status`, `p`.`phone_number` AS `phone_number`, `p`.`email` AS `email`, `p`.`home_address` AS `home_address`, `p`.`work_address` AS `work_address`, `p`.`occupation` AS `occupation`, `p`.`office_contact` AS `office_contact`, `p`.`fb_account` AS `fb_account`, `p`.`guardian_name` AS `guardian_name`, `p`.`guardian_contact` AS `guardian_contact`, `p`.`physician_name` AS `physician_name`, `p`.`physician_contact` AS `physician_contact`, `p`.`physician_address` AS `physician_address`, `dh`.`previous_dentist` AS `previous_dentist`, `dh`.`last_dental_visit` AS `last_dental_visit`, `dh`.`treatment_done` AS `treatment_done`, `dh`.`reason_for_visit` AS `reason_for_visit`, `dh`.`referred_by` AS `referred_by`, `mh`.`good_health` AS `good_health`, `mh`.`medical_condition` AS `medical_condition`, `mh`.`medical_condition_detail` AS `medical_condition_detail`, `mh`.`serious_illness` AS `serious_illness`, `mh`.`serious_illness_detail` AS `serious_illness_detail`, `mh`.`hospitalized` AS `hospitalized`, `mh`.`hospitalized_detail` AS `hospitalized_detail`, `mh`.`medication` AS `medication`, `mh`.`medication_detail` AS `medication_detail`, `mh`.`smoke` AS `smoke`, `mh`.`alcohol` AS `alcohol`, `mh`.`drugs` AS `drugs`, `mh`.`allergy` AS `allergy`, `mh`.`allergy_detail` AS `allergy_detail`, `mh`.`pregnant` AS `pregnant`, `mh`.`nursing` AS `nursing`, `mh`.`birth_control` AS `birth_control`, group_concat(distinct `pc`.`condition` order by `pc`.`condition` ASC separator ', ') AS `patient_conditions`, `c`.`consent_name` AS `consent_name`, `c`.`consent_for` AS `consent_for`, `c`.`consent_date` AS `consent_date`, `p`.`created_at` AS `created_at` FROM ((((`patients` `p` left join `patient_dental_history` `dh` on(`p`.`patient_id` = `dh`.`patient_id`)) left join `patient_medical_history` `mh` on(`p`.`patient_id` = `mh`.`patient_id`)) left join `patient_consent` `c` on(`p`.`patient_id` = `c`.`patient_id`)) left join `patient_conditions` `pc` on(`p`.`patient_id` = `pc`.`patient_id`)) GROUP BY `p`.`patient_id`, `p`.`user_id`, `p`.`firstname`, `p`.`middlename`, `p`.`lastname`, `p`.`age`, `p`.`gender`, `p`.`birthdate`, `p`.`civil_status`, `p`.`phone_number`, `p`.`email`, `p`.`home_address`, `p`.`work_address`, `p`.`occupation`, `p`.`office_contact`, `p`.`fb_account`, `p`.`guardian_name`, `p`.`guardian_contact`, `p`.`physician_name`, `p`.`physician_contact`, `p`.`physician_address`, `dh`.`previous_dentist`, `dh`.`last_dental_visit`, `dh`.`treatment_done`, `dh`.`reason_for_visit`, `dh`.`referred_by`, `mh`.`good_health`, `mh`.`medical_condition`, `mh`.`medical_condition_detail`, `mh`.`serious_illness`, `mh`.`serious_illness_detail`, `mh`.`hospitalized`, `mh`.`hospitalized_detail`, `mh`.`medication`, `mh`.`medication_detail`, `mh`.`smoke`, `mh`.`alcohol`, `mh`.`drugs`, `mh`.`allergy`, `mh`.`allergy_detail`, `mh`.`pregnant`, `mh`.`nursing`, `mh`.`birth_control`, `c`.`consent_name`, `c`.`consent_for`, `c`.`consent_date`, `p`.`created_at` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `clinic_id` (`clinic_id`),
  ADD KEY `fk_appointments_schedule` (`schedule_id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`clinic_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  ADD PRIMARY KEY (`condition_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_consent`
--
ALTER TABLE `patient_consent`
  ADD PRIMARY KEY (`consent_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  ADD PRIMARY KEY (`dental_history_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  ADD PRIMARY KEY (`medical_history_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `fkclinic_id` (`clinic_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `clinic_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_consent`
--
ALTER TABLE `patient_consent`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  MODIFY `dental_history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  MODIFY `medical_history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  ADD CONSTRAINT `fk_conditions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_consent`
--
ALTER TABLE `patient_consent`
  ADD CONSTRAINT `fk_consent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  ADD CONSTRAINT `fk_dental_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  ADD CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `fkclinic_id` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
