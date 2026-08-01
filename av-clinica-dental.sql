-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 01, 2026 at 02:01 AM
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
  `date` date NOT NULL,
  `status` enum('Pending','Confirmed','Cancelled','Completed','Rejected') NOT NULL DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `schedule_id`, `clinic_id`, `date`, `status`, `created_at`) VALUES
(17, 14, 20, 1, '2026-07-27', 'Confirmed', '2026-07-24 09:47:53'),
(19, 14, 21, 2, '2026-07-29', 'Confirmed', '2026-07-24 10:29:34'),
(20, 16, 20, 1, '2026-07-27', 'Confirmed', '2026-07-27 06:17:38'),
(22, 17, 20, 1, '2026-07-27', 'Confirmed', '2026-07-27 06:52:37'),
(23, 18, 30, 2, '2026-08-07', 'Pending', '2026-07-28 03:00:13'),
(24, 19, 30, 2, '2026-08-07', 'Pending', '2026-07-28 03:02:02'),
(25, 14, 26, 1, '2026-08-04', 'Confirmed', '2026-07-30 01:24:05'),
(26, 14, 25, 1, '2026-08-03', 'Confirmed', '2026-07-30 01:38:36'),
(27, 21, 25, 1, '2026-08-03', 'Cancelled', '2026-07-31 12:10:03'),
(28, 14, 30, 2, '2026-08-07', 'Pending', '2026-07-31 14:33:08');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_services`
--

CREATE TABLE `appointment_services` (
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_services`
--

INSERT INTO `appointment_services` (`appointment_id`, `service_id`) VALUES
(17, 1),
(19, 3),
(20, 9),
(22, 12),
(23, 9),
(24, 5),
(25, 8),
(26, 2),
(27, 9),
(28, 2),
(28, 6);

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
-- Table structure for table `email_verifications`
--

CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_verifications`
--

INSERT INTO `email_verifications` (`id`, `email`, `otp`, `expires_at`, `used`, `created_at`) VALUES
(2, 'winsight11@gmail.com', '652689', '2026-07-24 18:02:02', 1, '2026-07-24 09:52:02'),
(4, 'roncorpuz09@gmail.com', '904227', '2026-07-25 13:38:02', 0, '2026-07-25 05:28:02'),
(8, 'stephanieunista@gmail.com', '192956', '2026-07-27 13:32:10', 1, '2026-07-27 05:22:10'),
(9, 'llantomichelle9@gmail.com', '136841', '2026-07-27 14:55:09', 1, '2026-07-27 06:45:09'),
(13, 'small@gmail.com', '581017', '2026-07-27 09:31:03', 0, '2026-07-27 07:21:03'),
(15, 'winje@gmail.com', '908945', '2026-07-27 22:50:21', 1, '2026-07-27 14:40:21'),
(16, 'j.cruz@gmail.com', '023441', '2026-07-28 11:13:10', 1, '2026-07-28 03:03:10'),
(17, 'jcruz@gmail.com', '406047', '2026-07-28 11:14:43', 1, '2026-07-28 03:04:43'),
(18, 'christianjamescapule@gmail.com', '488760', '2026-07-31 20:11:35', 1, '2026-07-31 12:01:35');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `token_hash` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `token_hash`, `email`, `otp`, `expires_at`, `used`, `created_at`) VALUES
(38, NULL, 'roncorpuz09@gmail.com', '475333', '2026-07-29 18:21:36', 0, '2026-07-29 10:11:36'),
(39, NULL, 'stephanieunista@gmail.com', '873462', '2026-07-30 20:35:01', 0, '2026-07-30 12:25:01');

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
(14, 14, 'Win', 'Corpuz', 'Joaquin', 21, 'Male', '09123456789', 'winsight11@gmail.com', '2005-04-09', 'Single', 'Baybayog, Alcala, Cagayan', 'Baybayog, Alcala, Cagayan', NULL, 'Student', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-24 09:47:53'),
(16, 15, 'Ning', 'Unista', 'v ', 21, 'Female', '09218656206', 'stephanieunista@gmail.com', '2004-09-04', 'Single', 'masin, alcala cagayan ', 'masin,alcala, cgayan', 'step  unista', 'student', 'n/a', NULL, NULL, NULL, NULL, NULL, '2026-07-27 05:23:30'),
(17, 17, 'Michelle', 'LLANTO', 'JACINTO', 21, 'Female', '09999997652566', 'llantomichelle9@gmail.com', '2005-06-02', 'Single', 'Baculod Alcala Cagayan', 'N/A', 'MICHELLE LLANTO', 'STUDENT', 'N/A', NULL, NULL, NULL, NULL, NULL, '2026-07-27 06:49:28'),
(18, 21, 'Juan', 'Dela Cruz', 'Santos', 22, 'Male', '09123456789', 'jcruz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:00:13'),
(19, NULL, 'Maria', 'Lago', 'Palo', 20, 'Female', '09123456789', 'm.lago@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:02:02'),
(20, 20, 'CruzJ', '', NULL, NULL, NULL, NULL, 'j.cruz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:03:46'),
(21, 28, 'Pogicj', '', NULL, NULL, NULL, NULL, 'christianjamescapule@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-31 12:02:19');

-- --------------------------------------------------------

--
-- Table structure for table `patient_conditions`
--

CREATE TABLE `patient_conditions` (
  `condition_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `condition` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_conditions`
--

INSERT INTO `patient_conditions` (`condition_id`, `patient_id`, `condition`) VALUES
(2, 17, 'Anemia');

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

--
-- Dumping data for table `patient_consent`
--

INSERT INTO `patient_consent` (`consent_id`, `patient_id`, `consent_name`, `consent_for`, `consent_date`, `created_at`) VALUES
(2, 16, NULL, 'myself', '2026-07-21', '2026-07-27 05:44:48'),
(3, 17, NULL, 'myself', '2026-07-25', '2026-07-27 07:35:28');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_dental_history`
--

INSERT INTO `patient_dental_history` (`dental_history_id`, `patient_id`, `previous_dentist`, `last_dental_visit`, `treatment_done`, `reason_for_visit`, `referred_by`, `created_at`, `last_updated_by`, `last_updated_at`) VALUES
(3, 16, 'yesgdbhyfgeg ', '2026-07-01', 'toothtache ', 'check up', 'none', '2026-07-27 05:50:18', 'patient', '2026-07-27 13:54:32'),
(4, 17, 'STEPHANIE UNISTA', '2026-07-25', 'TOOTHWHITENING', 'CHECK UP', 'DR. RON RON', '2026-07-27 07:28:06', 'patient', '2026-07-27 15:28:06');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `blood_type` varchar(10) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_medical_history`
--

INSERT INTO `patient_medical_history` (`medical_history_id`, `patient_id`, `good_health`, `medical_condition`, `medical_condition_detail`, `serious_illness`, `serious_illness_detail`, `hospitalized`, `hospitalized_detail`, `medication`, `medication_detail`, `smoke`, `alcohol`, `drugs`, `allergy`, `allergy_detail`, `pregnant`, `nursing`, `birth_control`, `cond_others`, `created_at`, `blood_type`, `blood_pressure`, `last_updated_by`, `last_updated_at`) VALUES
(3, 16, 1, 0, NULL, 1, NULL, 1, NULL, 0, NULL, 0, 0, 0, 1, NULL, 0, 0, 0, NULL, '2026-07-27 05:50:15', 'ab+', '120/80', 'patient', '2026-07-27 13:56:13'),
(4, 17, 1, 0, NULL, 0, NULL, 0, NULL, 0, NULL, 0, 0, 0, 0, NULL, 0, 0, 0, NULL, '2026-07-27 07:29:18', NULL, NULL, 'patient', '2026-07-27 15:29:21');

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
(20, 1, '2026-07-27', 10),
(21, 2, '2026-07-29', 8),
(24, 1, '2026-07-31', 8),
(25, 1, '2026-08-03', 8),
(26, 1, '2026-08-04', 8),
(27, 1, '2026-08-05', 8),
(29, 2, '2026-08-06', 8),
(30, 2, '2026-08-07', 8),
(31, 2, '2026-08-08', 10),
(34, 1, '2026-08-01', 8),
(35, 2, '2026-08-10', 8);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `service_icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `category_id`, `service_name`, `service_description`, `service_icon`, `is_active`, `display_order`) VALUES
(1, 1, 'Cleaning (Prophylaxis)', 'Professional plaque and tartar removal for a fresher, healthier smile.', 'fa-solid fa-broom', 1, 1),
(2, 1, 'Scaling', 'Deep cleaning below the gumline to treat and help prevent gum disease.', 'fa-solid fa-teeth', 1, 2),
(3, 1, 'Periapical X-ray', 'Detailed imaging of a tooth\'s root and the surrounding bone.', 'fa-solid fa-x-ray', 1, 3),
(4, 2, 'Restoration (Fillings)', 'Composite or amalgam fillings that repair cavities and minor damage.', 'fa-solid fa-tooth', 1, 4),
(5, 2, 'Crown / Jackets', 'A custom cap that protects and rebuilds a weakened or broken tooth.', 'fa-solid fa-crown', 1, 5),
(6, 2, 'Bridge', 'A fixed replacement that closes the gap left by a missing tooth.', 'fa-solid fa-link', 1, 6),
(7, 2, 'Root Canal', 'Treats infected or damaged tooth pulp to help save the natural tooth.', 'fa-solid fa-syringe', 1, 7),
(8, 2, 'Dentures', 'Removable replacements for some or all missing teeth.', 'fa-solid fa-teeth', 1, 8),
(9, 3, 'Extraction', 'Safe removal of a damaged, decayed, or problematic tooth.', 'fa-solid fa-tooth', 1, 9),
(10, 3, 'Wisdom Tooth Removal', 'Removal of impacted or emerging third molars.', 'fa-solid fa-tooth', 1, 10),
(11, 4, 'Braces', 'Gradually aligns crowded, gapped, or misaligned teeth over time.', 'fa-solid fa-teeth-open', 1, 11),
(12, 4, 'Whitening', 'A professional treatment to brighten stained or discolored teeth.', 'fa-solid fa-star', 1, 12),
(13, 4, 'Veneer', 'Thin custom shells that reshape and brighten the front of a tooth.', 'fa-solid fa-gem', 1, 13);

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `category_description` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`category_id`, `category_name`, `category_description`, `display_order`) VALUES
(1, 'Preventive & Diagnostic Care', 'Routine visits that catch problems early and keep your smile healthy in between appointments.', 1),
(2, 'Restorative Treatments', 'Repairing damaged, decayed, or missing teeth so you can bite, chew, and smile with confidence.', 2),
(3, 'Oral Surgery', 'Extractions and surgical procedures performed with care, plus clear aftercare guidance.', 3),
(4, 'Cosmetic & Orthodontic', 'Options to align, brighten, and refine the appearance of your smile.', 4);

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `brand_name_top` varchar(50) DEFAULT 'Dr. Aprille',
  `brand_name_sub` varchar(50) DEFAULT 'Clinica Dental',
  `site_logo` varchar(255) DEFAULT NULL,
  `hero_system_tag` varchar(150) DEFAULT 'Online Dental Appointment & Patient Records Management System',
  `hero_eyebrow` varchar(150) DEFAULT 'Two Clinics in Cagayan · Alcala & Tuguegarao',
  `hero_title` varchar(255) DEFAULT 'Dental care for Alcala and Tuguegarao families.',
  `hero_subtext` varchar(500) DEFAULT 'From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.',
  `about_intro` text DEFAULT NULL,
  `pillar1_title` varchar(100) DEFAULT 'Patient-Centered Care',
  `pillar1_desc` varchar(255) DEFAULT 'Every visit is explained clearly, so you always know what to expect.',
  `pillar2_title` varchar(100) DEFAULT 'Experienced Team',
  `pillar2_desc` varchar(255) DEFAULT 'Dental professionals handling everything from routine care to advanced treatment.',
  `pillar3_title` varchar(100) DEFAULT 'Two Convenient Branches',
  `pillar3_desc` varchar(255) DEFAULT 'Serving patients in both Alcala and Tuguegarao, Cagayan.',
  `contact_address` varchar(255) DEFAULT 'Alcala & Tuguegarao, Cagayan',
  `contact_phone` varchar(20) DEFAULT '0912-345-6789',
  `contact_email` varchar(100) DEFAULT 'info@draprilleventura.com',
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `brand_name_top`, `brand_name_sub`, `site_logo`, `hero_system_tag`, `hero_eyebrow`, `hero_title`, `hero_subtext`, `about_intro`, `pillar1_title`, `pillar1_desc`, `pillar2_title`, `pillar2_desc`, `pillar3_title`, `pillar3_desc`, `contact_address`, `contact_phone`, `contact_email`, `last_updated_by`, `last_updated_at`) VALUES
(1, 'Dr. Aprille', 'Clinica Dental', 'site_logo_1785381335.png', 'Online Dental Appointment & Patient Records Management System', 'Two Clinics in Cagayan · Alcala & Tuguegarao', 'Dental care for Alcala and Tuguegarao families.', 'From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.', 'Dr. Aprille Ventura Clinica Dental provides patient-centered dental care across our Alcala and Tuguegarao branches — from routine checkups to more involved restorative and cosmetic treatment. Our team takes the time to walk you through every step, so you always know what to expect before, during, and after your visit.', 'Patient-Centered Care', 'Every visit is explained clearly, so you always know what to expect.', 'Experienced Team', 'Dental professionals handling everything from routine care to advanced treatment.', 'Two Convenient Branches', 'Serving patients in both Alcala and Tuguegarao, Cagayan.', 'Alcala & Tuguegarao, Cagayan', '0912-345-6789', 'info@draprilleventura.com', 'Admin', '2026-07-30 13:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE `staffs` (
  `staff_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `employment_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staffs`
--

INSERT INTO `staffs` (`staff_id`, `user_id`, `firstname`, `lastname`, `middlename`, `gender`, `phone_number`, `email`, `employment_status`, `created_at`) VALUES
(2, 16, 'stephanie', 'unista', 'v', 'Female', '09218656206', 'stephyyyunista94@gmail.com', 'Active', '2026-07-27 06:24:33'),
(3, 18, 'Winje', 'Corpuz', 'Joaquin', 'Male', '09123412345', 'roncorpuz09@gmail.com', 'Active', '2026-07-27 10:26:30'),
(4, 22, 'Pogi', 'Naman', 'Mo', 'Male', '09123412345', 'corpuzwinjemelron@gmail.com', 'Active', '2026-07-30 13:47:39');

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
(7, 'admin@gmail.com', 'admin', '$2y$10$yjiG6c81sf6NPj8gEWkR8.6BEFnug.jLEry2zzD7L9gGzhxY/NTGm', 'Admin'),
(14, 'winsight11@gmail.com', 'winpogi', '$2y$10$u.XUDEHisxdJ6QbWZciI/un2sHC5csepwapa6q9XNkmGlqQ6.mtdi', 'Patient'),
(15, 'stephanieunista@gmail.com', 'ning', '$2y$10$vNimCUMY2cHtz3PPQfyLNezSOy9WR/Qryu4lQYb1k6AVOfCtaR6dG', 'Patient'),
(16, 'stephyyyunista94@gmail.com', 'stephanie.unista', '$2y$10$6dDvGf5.aFptNtGWE9QiseE3qAk9V/jB7rL/4jW2Y.WTTJy.CAvem', 'Dental Assistant'),
(17, 'llantomichelle9@gmail.com', 'michelle', '$2y$10$CYTbshBCb.qmC2LVdU407OyB5G4IAxge4lwH4Lx.hbVYGTbNJQI7q', 'Patient'),
(18, 'roncorpuz09@gmail.com', 'winje.corpuz', '$2y$10$6SSUY0/c2WLmquUkQXC7gehJfOLHHBqc1i2uhb6HmH2c64LJc4bMm', 'Dental Assistant'),
(19, 'winje@gmail.com', 'winje.win', '$2y$10$C69fAaA/Er81z90RnoB0H.XQ9ze3mZhkEn/2fK5q7z80h04ZVUVRm', 'Patient'),
(20, 'j.cruz@gmail.com', 'cruzJ', '$2y$10$3y.eHdpfkHY6s.7oChZwEOfGiWzhvMo.yhfjwwgRUgY8LQbys/XlW', 'Patient'),
(21, 'jcruz@gmail.com', 'cruz.J', '$2y$10$F/Rfkca9YMPaDkjVAXRdTeLZvRiAWVX3ldVDr490E8CbCnQw.hSnu', 'Patient'),
(22, 'corpuzwinjemelron@gmail.com', 'pogi.naman', '$2y$10$AeUUJZzAJHanni9kfh452eu2R2nVKKNgWOC.1EOtPR67782eCz3p6', 'Dental Assistant'),
(28, 'christianjamescapule@gmail.com', 'pogicj', '$2y$10$BPHcRiUNHPEqlA2q7g2ETe/jWRamNzub5vNH9WGxri1buDZdC3GW2', 'Patient');

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
,`blood_type` varchar(10)
,`blood_pressure` varchar(20)
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

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_patient_information`  AS SELECT `p`.`patient_id` AS `patient_id`, `p`.`user_id` AS `user_id`, `p`.`firstname` AS `firstname`, `p`.`middlename` AS `middlename`, `p`.`lastname` AS `lastname`, concat(`p`.`firstname`,' ',coalesce(`p`.`middlename`,''),' ',`p`.`lastname`) AS `full_name`, `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`birthdate` AS `birthdate`, `p`.`civil_status` AS `civil_status`, `p`.`phone_number` AS `phone_number`, `p`.`email` AS `email`, `p`.`home_address` AS `home_address`, `p`.`work_address` AS `work_address`, `p`.`occupation` AS `occupation`, `p`.`office_contact` AS `office_contact`, `p`.`fb_account` AS `fb_account`, `p`.`guardian_name` AS `guardian_name`, `p`.`guardian_contact` AS `guardian_contact`, `p`.`physician_name` AS `physician_name`, `p`.`physician_contact` AS `physician_contact`, `p`.`physician_address` AS `physician_address`, `dh`.`previous_dentist` AS `previous_dentist`, `dh`.`last_dental_visit` AS `last_dental_visit`, `dh`.`treatment_done` AS `treatment_done`, `dh`.`reason_for_visit` AS `reason_for_visit`, `dh`.`referred_by` AS `referred_by`, `mh`.`good_health` AS `good_health`, `mh`.`medical_condition` AS `medical_condition`, `mh`.`medical_condition_detail` AS `medical_condition_detail`, `mh`.`serious_illness` AS `serious_illness`, `mh`.`serious_illness_detail` AS `serious_illness_detail`, `mh`.`hospitalized` AS `hospitalized`, `mh`.`hospitalized_detail` AS `hospitalized_detail`, `mh`.`medication` AS `medication`, `mh`.`medication_detail` AS `medication_detail`, `mh`.`smoke` AS `smoke`, `mh`.`alcohol` AS `alcohol`, `mh`.`drugs` AS `drugs`, `mh`.`allergy` AS `allergy`, `mh`.`allergy_detail` AS `allergy_detail`, `mh`.`pregnant` AS `pregnant`, `mh`.`nursing` AS `nursing`, `mh`.`birth_control` AS `birth_control`, `mh`.`blood_type` AS `blood_type`, `mh`.`blood_pressure` AS `blood_pressure`, group_concat(distinct `pc`.`condition` order by `pc`.`condition` ASC separator ', ') AS `patient_conditions`, `c`.`consent_name` AS `consent_name`, `c`.`consent_for` AS `consent_for`, `c`.`consent_date` AS `consent_date`, `p`.`created_at` AS `created_at` FROM ((((`patients` `p` left join `patient_dental_history` `dh` on(`p`.`patient_id` = `dh`.`patient_id`)) left join `patient_medical_history` `mh` on(`p`.`patient_id` = `mh`.`patient_id`)) left join `patient_consent` `c` on(`p`.`patient_id` = `c`.`patient_id`)) left join `patient_conditions` `pc` on(`p`.`patient_id` = `pc`.`patient_id`)) GROUP BY `p`.`patient_id`, `p`.`user_id`, `p`.`firstname`, `p`.`middlename`, `p`.`lastname`, `p`.`age`, `p`.`gender`, `p`.`birthdate`, `p`.`civil_status`, `p`.`phone_number`, `p`.`email`, `p`.`home_address`, `p`.`work_address`, `p`.`occupation`, `p`.`office_contact`, `p`.`fb_account`, `p`.`guardian_name`, `p`.`guardian_contact`, `p`.`physician_name`, `p`.`physician_contact`, `p`.`physician_address`, `dh`.`previous_dentist`, `dh`.`last_dental_visit`, `dh`.`treatment_done`, `dh`.`reason_for_visit`, `dh`.`referred_by`, `mh`.`good_health`, `mh`.`medical_condition`, `mh`.`medical_condition_detail`, `mh`.`serious_illness`, `mh`.`serious_illness_detail`, `mh`.`hospitalized`, `mh`.`hospitalized_detail`, `mh`.`medication`, `mh`.`medication_detail`, `mh`.`smoke`, `mh`.`alcohol`, `mh`.`drugs`, `mh`.`allergy`, `mh`.`allergy_detail`, `mh`.`pregnant`, `mh`.`nursing`, `mh`.`birth_control`, `mh`.`blood_type`, `mh`.`blood_pressure`, `c`.`consent_name`, `c`.`consent_for`, `c`.`consent_date`, `p`.`created_at` ;

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
-- Indexes for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`appointment_id`,`service_id`),
  ADD KEY `fk_appointment_services_service` (`service_id`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`clinic_id`);

--
-- Indexes for table `email_verifications`
--
ALTER TABLE `email_verifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`),
  ADD KEY `fk_category` (`category_id`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staffs`
--
ALTER TABLE `staffs`
  ADD PRIMARY KEY (`staff_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

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
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `clinic_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patient_consent`
--
ALTER TABLE `patient_consent`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  MODIFY `dental_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  MODIFY `medical_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `staffs`
--
ALTER TABLE `staffs`
  MODIFY `staff_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

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
-- Constraints for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD CONSTRAINT `fk_appointment_services_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE;

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

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`);

--
-- Constraints for table `staffs`
--
ALTER TABLE `staffs`
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
