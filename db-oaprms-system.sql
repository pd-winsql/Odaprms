-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 09, 2026 at 12:47 PM
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
-- Database: `db-oaprms-system`
--

CREATE DATABASE IF NOT EXISTS `db-oaprms-system`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `db-oaprms-system`;

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE IF NOT EXISTS `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `status` enum('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed','Cancelled','No-show','Rejected','Pending','Awaiting Payment','Rescheduled') NOT NULL DEFAULT 'Pending Review',
  `deposit_required` tinyint(1) NOT NULL DEFAULT 1,
  `payment_deadline_at` datetime DEFAULT NULL,
  `payment_access_token_hash` char(64) DEFAULT NULL,
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `accepted_for_payment_at` datetime DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `appointment_code` varchar(20) DEFAULT NULL,
  `code_generated_at` datetime DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `treatment_started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `schedule_id`, `clinic_id`, `date`, `status`, `deposit_required`, `payment_deadline_at`, `payment_access_token_hash`, `reviewed_by_user_id`, `reviewed_at`, `accepted_for_payment_at`, `rejected_at`, `rejection_reason`, `appointment_code`, `code_generated_at`, `confirmed_at`, `treatment_started_at`, `completed_at`, `cancelled_at`, `cancellation_reason`, `created_at`) VALUES
(17, 14, 20, 1, '2026-07-27', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-B89BDF77', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-24 09:47:53'),
(19, 14, 21, 2, '2026-07-29', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-23E29085', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-24 10:29:34'),
(20, 16, 20, 1, '2026-07-27', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-766A565A', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-27 06:17:38'),
(22, 17, 20, 1, '2026-07-27', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-22975292', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-27 06:52:37'),
(23, 18, 30, 2, '2026-08-07', 'Cancelled', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 18:41:12', NULL, '2026-07-28 03:00:13'),
(24, 19, 30, 2, '2026-08-07', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-CFFFAF97', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:02:02'),
(25, 14, 26, 1, '2026-08-04', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-4EAEE953', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-30 01:24:05'),
(26, 14, 25, 1, '2026-08-03', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-804B172A', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-30 01:38:36'),
(27, 21, 25, 1, '2026-08-03', 'Cancelled', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-31 12:10:03'),
(28, 14, 30, 2, '2026-08-07', 'Confirmed', 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'AVC-129A198F', '2026-08-09 18:47:20', NULL, NULL, NULL, NULL, NULL, '2026-07-31 14:33:08'),
(38, 14, 41, 2, '2026-08-11', 'Awaiting Deposit', 1, '2026-08-05 15:22:41', '890c4ef70cbc8c2138a4a0fb28542054ea6a309e3a4bb551df9c88e9136af8db', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 06:52:41'),
(39, 21, 27, 1, '2026-08-05', 'Cancelled', 1, '2026-08-05 15:25:04', '1788ddbf94de599151ea44613df67e0077e1aef6dcb1feaadfa3b53b044c56ac', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 14:56:25', NULL, NULL, '2026-08-05 18:33:27', NULL, '2026-08-05 06:55:04'),
(40, 14, 41, 2, '2026-08-11', 'Cancelled', 1, '2026-08-05 18:42:11', '2db68df83544600a2ade91a93f539e49ab9f8a55faeaa6fadf4fc4d27bee9847', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-09 08:45:54', 'Payment submission deadline expired.', '2026-08-05 10:12:11'),
(41, 23, 37, 1, '2026-08-12', 'Confirmed', 1, '2026-08-05 18:47:46', 'ade548caf9a54cb812f19ed691d4b1253d6981d683a8517f4203c03745766aa5', NULL, NULL, NULL, NULL, NULL, 'AVC-D8654963', '2026-08-05 18:32:14', '2026-08-05 18:32:14', NULL, NULL, NULL, NULL, '2026-08-05 10:17:46');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_billings`
--

CREATE TABLE IF NOT EXISTS `appointment_billings` (
  `billing_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `actual_service_amount` decimal(10,2) DEFAULT NULL,
  `deposit_applied` decimal(10,2) NOT NULL DEFAULT 0.00,
  `remaining_balance` decimal(10,2) DEFAULT NULL,
  `cash_received` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid',
  `recorded_by_user_id` int(11) DEFAULT NULL,
  `recorded_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_billing_items`
--

CREATE TABLE IF NOT EXISTS `appointment_billing_items` (
  `billing_item_id` bigint(20) UNSIGNED NOT NULL,
  `billing_id` bigint(20) UNSIGNED NOT NULL,
  `service_id` int(11) DEFAULT NULL,
  `service_name_snapshot` varchar(100) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `line_total` decimal(10,2) GENERATED ALWAYS AS (CASE WHEN `unit_price` IS NULL THEN NULL ELSE round(`quantity` * `unit_price`, 2) END) STORED,
  `pricing_source` varchar(32) NOT NULL DEFAULT 'legacy-unknown',
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_checkins`
--

CREATE TABLE IF NOT EXISTS `appointment_checkins` (
  `checkin_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `arrived_at` datetime NOT NULL,
  `checked_in_by_user_id` int(11) DEFAULT NULL,
  `lookup_method` enum('Code','Patient Search','Date Override') NOT NULL DEFAULT 'Code',
  `checkin_status` enum('Profile Required','Ready') NOT NULL,
  `profile_required_at_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `ready_at` datetime DEFAULT NULL,
  `queue_status` enum('Waiting','On Hold') NOT NULL DEFAULT 'Waiting',
  `queue_entered_at` datetime DEFAULT NULL,
  `queue_reason` varchar(255) DEFAULT NULL,
  `serve_next_at` datetime DEFAULT NULL,
  `serve_next_reason` varchar(255) DEFAULT NULL,
  `serve_next_by_user_id` int(11) DEFAULT NULL,
  `queue_updated_by_user_id` int(11) DEFAULT NULL,
  `queue_updated_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `date_override_reason` varchar(255) DEFAULT NULL,
  `date_override_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_checkins`
--

INSERT INTO `appointment_checkins` (`checkin_id`, `appointment_id`, `arrived_at`, `checked_in_by_user_id`, `lookup_method`, `checkin_status`, `profile_required_at_arrival`, `ready_at`, `queue_status`, `queue_entered_at`, `queue_reason`, `serve_next_at`, `serve_next_reason`, `serve_next_by_user_id`, `queue_updated_by_user_id`, `queue_updated_at`, `notes`, `date_override_reason`, `date_override_by_user_id`, `created_at`, `updated_at`) VALUES
(5, 39, '2026-08-05 14:56:39', 7, 'Code', 'Profile Required', 1, NULL, 'Waiting', '2026-08-05 14:56:39', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 06:56:39', '2026-08-05 06:56:39');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_deposits`
--

CREATE TABLE IF NOT EXISTS `appointment_deposits` (
  `deposit_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 400.00,
  `gcash_reference` varchar(100) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `receipt_mime` varchar(100) DEFAULT NULL,
  `status` enum('Awaiting Submission','Under Review','Verified','Rejected','Expired','Transferred','Forfeited','For Refund','Refunded') NOT NULL DEFAULT 'Awaiting Submission',
  `submitted_at` datetime DEFAULT NULL,
  `verified_by_user_id` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) DEFAULT NULL,
  `resubmission_deadline_at` datetime DEFAULT NULL,
  `deadline_extended_by_user_id` int(11) DEFAULT NULL,
  `deadline_extended_at` datetime DEFAULT NULL,
  `deadline_extension_reason` varchar(255) DEFAULT NULL,
  `transferred_from_appointment_id` int(11) DEFAULT NULL,
  `transferred_by_user_id` int(11) DEFAULT NULL,
  `transferred_at` datetime DEFAULT NULL,
  `transfer_reason` varchar(255) DEFAULT NULL,
  `refund_reason` varchar(255) DEFAULT NULL,
  `refunded_by_user_id` int(11) DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refund_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_deposits`
--

INSERT INTO `appointment_deposits` (`deposit_id`, `appointment_id`, `amount`, `gcash_reference`, `receipt_path`, `receipt_mime`, `status`, `submitted_at`, `verified_by_user_id`, `verified_at`, `rejection_reason`, `resubmission_deadline_at`, `deadline_extended_by_user_id`, `deadline_extended_at`, `deadline_extension_reason`, `transferred_from_appointment_id`, `transferred_by_user_id`, `transferred_at`, `transfer_reason`, `refund_reason`, `refunded_by_user_id`, `refunded_at`, `refund_notes`, `created_at`, `updated_at`) VALUES
(10, 38, 400.00, '123456789', 'storage/payment_receipts/47de4d4f65a4f9635de21232c102ce9fb39c4084.png', 'image/png', 'Under Review', '2026-08-05 14:53:22', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 06:52:41', '2026-08-05 06:53:22'),
(11, 39, 400.00, '12345678910', 'storage/payment_receipts/33f52d7a4e9c92dedc91a7cf95e25e1d1a9c14f4.png', 'image/png', 'Verified', '2026-08-05 14:55:25', 7, '2026-08-05 14:56:25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 06:55:04', '2026-08-05 06:56:25'),
(12, 40, 400.00, NULL, NULL, NULL, 'Expired', NULL, NULL, NULL, 'Payment submission deadline expired.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 10:12:11', '2026-08-09 00:45:54'),
(13, 41, 400.00, '1234567891011', 'storage/payment_receipts/487099509dbe1f3a060bbd4ac68e5eba5ece9a2c.png', 'image/png', 'Verified', '2026-08-05 18:28:13', 7, '2026-08-05 18:32:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 10:17:46', '2026-08-05 10:32:14');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_email_notifications`
--

CREATE TABLE IF NOT EXISTS `appointment_email_notifications` (
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`payload` is null or json_valid(`payload`)),
  `deduplication_key` varchar(150) DEFAULT NULL,
  `delivery_status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `attempts` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_services`
--

CREATE TABLE IF NOT EXISTS `appointment_services` (
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `quantity` decimal(8,2) NOT NULL DEFAULT 1.00 COMMENT 'Number of units of this service for the appointment.',
  `unit_price_snapshot` decimal(10,2) DEFAULT NULL COMMENT 'Price captured for this appointment; NULL for legacy/unpriced records.'
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
(28, 6),
(38, 5),
(38, 8),
(39, 2),
(40, 2),
(40, 10),
(41, 3);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_log_id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `performed_by_user_id` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) NOT NULL,
  `performed_by_role` varchar(50) NOT NULL,
  `source` enum('User','System') NOT NULL DEFAULT 'User',
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`audit_log_id`, `entity_type`, `entity_id`, `action`, `description`, `old_values`, `new_values`, `performed_by_user_id`, `performed_by_name`, `performed_by_role`, `source`, `performed_at`) VALUES
(18, 'appointment', 39, 'status_changed', 'Verified the deposit and confirmed appointment #39.', '{\"status\":\"Awaiting Payment\",\"deposit_status\":\"Under Review\"}', '{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\"}', 7, 'admin', 'Admin', 'User', '2026-08-05 06:56:25'),
(19, 'appointment', 39, 'patient_checked_in', 'Checked in the patient for appointment #39.', NULL, '{\"checkin_status\":\"Profile Required\",\"profile_required\":true}', 7, 'admin', 'Admin', 'User', '2026-08-05 06:56:39'),
(20, 'appointment', 41, 'status_changed', 'Verified the deposit and confirmed appointment #41.', '{\"status\":\"Awaiting Payment\",\"deposit_status\":\"Under Review\"}', '{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\"}', 7, 'admin', 'Admin', 'User', '2026-08-05 10:32:14'),
(21, 'appointment', 39, 'status_changed', 'Changed appointment #39 status from Confirmed to Cancelled.', '{\"status\":\"Confirmed\"}', '{\"status\":\"Cancelled\"}', 7, 'admin', 'Admin', 'User', '2026-08-05 10:33:27'),
(22, 'appointment', 23, 'status_changed', 'Changed appointment #23 status from Confirmed to Cancelled.', '{\"status\":\"Confirmed\"}', '{\"status\":\"Cancelled\"}', 18, 'Winje Joaquin Corpuz', 'Dental Assistant', 'User', '2026-08-05 10:41:12'),
(23, 'appointment', 40, 'status_changed', 'Cancelled appointment #40 after its payment deadline expired.', '{\"status\":\"Awaiting Payment\"}', '{\"status\":\"Cancelled\"}', NULL, 'System', 'System', 'System', '2026-08-09 00:45:54');

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE IF NOT EXISTS `clinics` (
  `clinic_id` int(11) NOT NULL,
  `clinic_name` varchar(100) NOT NULL,
  `clinic_address` varchar(100) NOT NULL,
  `clinic_contact` varchar(15) NOT NULL,
  `embed_url` text DEFAULT NULL,
  `clinic_image` varchar(255) DEFAULT NULL,
  `default_start_time` time NOT NULL DEFAULT '08:00:00',
  `default_end_time` time NOT NULL DEFAULT '17:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`clinic_id`, `clinic_name`, `clinic_address`, `clinic_contact`, `embed_url`, `clinic_image`) VALUES
(1, 'Alcala Branch', 'Zone 4, Tupang, Alcala, Cagayan', '0912-345-6789', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2880.167736548895!2d121.63913503906203!3d17.908509915210743!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3385f5f555a3231b%3A0xd3c7413d82013205!2sDr.%20Aprille%20Cabayu-Ventura%20Clinica%20Dental!5e1!3m2!1sen!2sph!4v1787551455140!5m2!1sen!2sph', NULL),
(2, 'Tuguegarao Branch', 'Bartolome St., Caggay, Tuguegarao City, Cagayan', '0912-345-6789', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d555.7286709904844!2d121.74134650485767!3d17.639557171908194!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x338585eb28ccc835%3A0x72f667b3142c792b!2sPurok%205%20Bartolome%20St%2C%20Tuguegarao%20City%2C%203500%20Cagayan!5e1!3m2!1sen!2sph!4v1787287560379!5m2!1sen!2sph', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `email_verifications`
--

CREATE TABLE IF NOT EXISTS `email_verifications` (
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
(18, 'christianjamescapule@gmail.com', '488760', '2026-07-31 20:11:35', 1, '2026-07-31 12:01:35'),
(19, 'ronniebarasi30@gmail.com', '900802', '2026-08-05 18:23:47', 1, '2026-08-05 10:13:47');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE IF NOT EXISTS `password_resets` (
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

CREATE TABLE IF NOT EXISTS `patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `suffix` varchar(20) DEFAULT NULL,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_completed_at` datetime DEFAULT NULL,
  `profile_completed_by_user_id` int(11) DEFAULT NULL,
  `profile_status` enum('Incomplete','Draft','Complete') NOT NULL DEFAULT 'Incomplete',
  `identity_match_key` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `user_id`, `firstname`, `lastname`, `middlename`, `suffix`, `age`, `gender`, `phone_number`, `email`, `birthdate`, `civil_status`, `home_address`, `work_address`, `fb_account`, `occupation`, `office_contact`, `guardian_name`, `guardian_contact`, `physician_name`, `physician_contact`, `physician_address`, `created_at`, `profile_completed_at`, `profile_completed_by_user_id`, `profile_status`, `identity_match_key`) VALUES
(14, 14, 'Win', 'Corpuz', 'Joaquin', NULL, 21, 'Male', '09123456789', 'winsight11@gmail.com', '2005-04-09', 'Single', 'Baybayog, Alcala, Cagayan', 'Baybayog, Alcala, Cagayan', NULL, 'Student', NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-24 09:47:53', NULL, NULL, 'Incomplete', 'ed83b3dae05c29fd241f60f98ed05fb76d8a1f9ea49c42f8fd481b7e6b5a43be'),
(16, 15, 'Ning', 'Unista', 'v ', NULL, 21, 'Female', '09218656206', 'stephanieunista@gmail.com', '2004-09-04', 'Single', 'masin, alcala cagayan ', 'masin,alcala, cgayan', 'step  unista', 'student', 'n/a', NULL, NULL, NULL, NULL, NULL, '2026-07-27 05:23:30', '2026-07-27 13:23:30', NULL, 'Complete', '82879cf53db26b5dbf683d55edd6ef6ff462b021ac9a18fd49c944abdb17004b'),
(17, 17, 'Michelle', 'LLANTO', 'JACINTO', NULL, 21, 'Female', '09999997652566', 'llantomichelle9@gmail.com', '2005-06-02', 'Single', 'Baculod Alcala Cagayan', 'N/A', 'MICHELLE LLANTO', 'STUDENT', 'N/A', NULL, NULL, NULL, NULL, NULL, '2026-07-27 06:49:28', '2026-07-27 14:49:28', NULL, 'Complete', 'a35cde19f990a145c1db27df8a7a68c70e7b09e701700ef3d6a8b45effe23ac0'),
(18, 21, 'Juan', 'Dela Cruz', 'Santos', NULL, 22, 'Male', '09123456789', 'jcruz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:00:13', NULL, NULL, 'Incomplete', NULL),
(19, NULL, 'Maria', 'Lago', 'Palo', NULL, 20, 'Female', '09123456789', 'm.lago@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:02:02', NULL, NULL, 'Incomplete', NULL),
(20, 20, 'CruzJ', '', NULL, NULL, NULL, NULL, NULL, 'j.cruz@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-28 03:03:46', NULL, NULL, 'Incomplete', NULL),
(21, 28, 'Pogicj', 'palo', 'Dikoalam', NULL, NULL, NULL, NULL, 'christianjamescapule@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-07-31 12:02:19', NULL, NULL, 'Incomplete', NULL),
(23, 31, 'Ronnie', '', NULL, NULL, NULL, NULL, NULL, 'ronniebarasi30@gmail.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-08-05 10:14:28', NULL, NULL, 'Incomplete', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_account_link_authorizations`
--

CREATE TABLE IF NOT EXISTS `patient_account_link_authorizations` (
  `authorization_id` bigint(20) UNSIGNED NOT NULL,
  `patient_id` int(11) NOT NULL,
  `authorized_email` varchar(255) NOT NULL,
  `status` enum('Active','Used','Revoked','Expired') NOT NULL DEFAULT 'Active',
  `authorized_by_user_id` int(11) DEFAULT NULL,
  `authorized_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_by_user_id` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_conditions`
--

CREATE TABLE IF NOT EXISTS `patient_conditions` (
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

CREATE TABLE IF NOT EXISTS `patient_consent` (
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

CREATE TABLE IF NOT EXISTS `patient_dental_history` (
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
-- Table structure for table `patient_duplicate_reviews`
--

CREATE TABLE IF NOT EXISTS `patient_duplicate_reviews` (
  `duplicate_review_id` bigint(20) UNSIGNED NOT NULL,
  `new_patient_id` int(11) NOT NULL,
  `possible_existing_patient_id` int(11) NOT NULL,
  `match_basis` varchar(100) NOT NULL DEFAULT 'Name and birthdate',
  `status` enum('Pending','Linked','Dismissed') NOT NULL DEFAULT 'Pending',
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patient_medical_history`
--

CREATE TABLE IF NOT EXISTS `patient_medical_history` (
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
  `no_known_conditions` tinyint(1) DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `schedules` (
  `schedule_id` int(11) NOT NULL,
  `clinic_id` int(11) NOT NULL,
  `sched_date` date NOT NULL,
  `start_time` time NOT NULL DEFAULT '08:00:00',
  `end_time` time NOT NULL DEFAULT '17:00:00',
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
(37, 1, '2026-08-12', 8),
(41, 2, '2026-08-11', 8),
(42, 1, '2026-08-17', 8),
(43, 1, '2026-08-18', 8),
(44, 2, '2026-08-19', 8),
(45, 2, '2026-08-20', 8),
(46, 2, '2026-08-21', 8),
(47, 1, '2026-08-24', 8),
(48, 1, '2026-08-25', 8);

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE IF NOT EXISTS `services` (
  `service_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `service_icon` varchar(100) DEFAULT NULL,
  `default_price` decimal(10,2) DEFAULT NULL COMMENT 'Current catalog price; NULL until service pricing is configured.',
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

CREATE TABLE IF NOT EXISTS `service_categories` (
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

CREATE TABLE IF NOT EXISTS `site_settings` (
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
  `contact_phone` varchar(255) DEFAULT '0912-345-6789',
  `contact_email` varchar(100) DEFAULT 'info@draprilleventura.com',
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 400.00,
  `payment_deadline_minutes` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `gcash_account_name` varchar(100) DEFAULT NULL,
  `gcash_account_number` varchar(30) DEFAULT NULL,
  `gcash_qr_path` varchar(255) DEFAULT NULL,
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `brand_name_top`, `brand_name_sub`, `site_logo`, `hero_system_tag`, `hero_eyebrow`, `hero_title`, `hero_subtext`, `about_intro`, `pillar1_title`, `pillar1_desc`, `pillar2_title`, `pillar2_desc`, `pillar3_title`, `pillar3_desc`, `contact_address`, `contact_phone`, `contact_email`, `deposit_amount`, `payment_deadline_minutes`, `gcash_account_name`, `gcash_account_number`, `gcash_qr_path`, `last_updated_by`, `last_updated_at`) VALUES
(1, 'Dr. Aprille', 'Clinica Dental', 'site_logo_1785381335.png', 'Online Dental Appointment & Patient Records Management System', 'Two Clinics in Cagayan · Alcala & Tuguegarao', 'Dental care for Alcala and Tuguegarao families.', 'From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.', 'Dr. Aprille Ventura Clinica Dental provides patient-centered dental care across our Alcala and Tuguegarao branches — from routine checkups to more involved restorative and cosmetic treatment. Our team takes the time to walk you through every step, so you always know what to expect before, during, and after your visit.', 'Patient-Centered Care', 'Every visit is explained clearly, so you always know what to expect.', 'Experienced Team', 'Dental professionals handling everything from routine care to advanced treatment.', 'Two Convenient Branches', 'Serving patients in both Alcala and Tuguegarao, Cagayan.', 'Alcala & Tuguegarao, Cagayan', '0912-345-6789', 'info@draprilleventura.com', 400.00, 480, NULL, NULL, NULL, 'Admin', '2026-07-30 13:01:09');

-- --------------------------------------------------------

--
-- Table structure for table `staffs`
--

CREATE TABLE IF NOT EXISTS `staffs` (
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

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `user_role` enum('Patient','Admin','Dental Assistant') NOT NULL DEFAULT 'Patient'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password`, `email_verified_at`, `user_role`) VALUES
(7, 'admin@gmail.com', '$2y$10$yjiG6c81sf6NPj8gEWkR8.6BEFnug.jLEry2zzD7L9gGzhxY/NTGm', '2026-08-09 18:47:19', 'Admin'),
(14, 'winsight11@gmail.com', '$2y$10$u.XUDEHisxdJ6QbWZciI/un2sHC5csepwapa6q9XNkmGlqQ6.mtdi', '2026-08-09 18:47:19', 'Patient'),
(15, 'stephanieunista@gmail.com', '$2y$10$vNimCUMY2cHtz3PPQfyLNezSOy9WR/Qryu4lQYb1k6AVOfCtaR6dG', '2026-08-09 18:47:19', 'Patient'),
(16, 'stephyyyunista94@gmail.com', '$2y$10$6dDvGf5.aFptNtGWE9QiseE3qAk9V/jB7rL/4jW2Y.WTTJy.CAvem', '2026-08-09 18:47:19', 'Dental Assistant'),
(17, 'llantomichelle9@gmail.com', '$2y$10$CYTbshBCb.qmC2LVdU407OyB5G4IAxge4lwH4Lx.hbVYGTbNJQI7q', '2026-08-09 18:47:19', 'Patient'),
(18, 'roncorpuz09@gmail.com', '$2y$10$6SSUY0/c2WLmquUkQXC7gehJfOLHHBqc1i2uhb6HmH2c64LJc4bMm', '2026-08-09 18:47:19', 'Dental Assistant'),
(19, 'winje@gmail.com', '$2y$10$C69fAaA/Er81z90RnoB0H.XQ9ze3mZhkEn/2fK5q7z80h04ZVUVRm', '2026-08-09 18:47:19', 'Patient'),
(20, 'j.cruz@gmail.com', '$2y$10$3y.eHdpfkHY6s.7oChZwEOfGiWzhvMo.yhfjwwgRUgY8LQbys/XlW', '2026-08-09 18:47:19', 'Patient'),
(21, 'jcruz@gmail.com', '$2y$10$F/Rfkca9YMPaDkjVAXRdTeLZvRiAWVX3ldVDr490E8CbCnQw.hSnu', '2026-08-09 18:47:19', 'Patient'),
(22, 'corpuzwinjemelron@gmail.com', '$2y$10$AeUUJZzAJHanni9kfh452eu2R2nVKKNgWOC.1EOtPR67782eCz3p6', '2026-08-09 18:47:19', 'Dental Assistant'),
(28, 'christianjamescapule@gmail.com', '$2y$10$BPHcRiUNHPEqlA2q7g2ETe/jWRamNzub5vNH9WGxri1buDZdC3GW2', '2026-08-09 18:47:19', 'Patient'),
(30, 'codex-feature-test@example.invalid', '$2y$10$Z/TB4KriADPNW0Ex53qcbeehjydDYxsZqfs0x1QHSKg2ycmL2MRea', '2026-08-09 18:47:19', 'Admin'),
(31, 'ronniebarasi30@gmail.com', '$2y$10$CLljiRpNi.yKtFg4of6h/.iATUX.fcdf102G4mgYmGYjHzg8bX9Uu', '2026-08-09 18:47:19', 'Patient');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_patient_information`
-- (See below for the actual view)
--
CREATE TABLE IF NOT EXISTS `vw_patient_information` (
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
  ADD UNIQUE KEY `uq_appointments_payment_token` (`payment_access_token_hash`),
  ADD UNIQUE KEY `uq_appointments_code` (`appointment_code`),
  ADD KEY `clinic_id` (`clinic_id`),
  ADD KEY `fk_appointments_schedule` (`schedule_id`),
  ADD KEY `idx_appointments_patient` (`patient_id`),
  ADD KEY `idx_appointments_daily_logbook` (`date`,`status`,`clinic_id`),
  ADD KEY `idx_appointments_expiry` (`status`,`deposit_required`,`payment_deadline_at`),
  ADD KEY `idx_appointments_review_queue` (`status`,`created_at`),
  ADD KEY `idx_appointments_reviewer` (`reviewed_by_user_id`);

--
-- Indexes for table `appointment_billings`
--
ALTER TABLE `appointment_billings`
  ADD PRIMARY KEY (`billing_id`),
  ADD UNIQUE KEY `uq_appointment_billing` (`appointment_id`),
  ADD KEY `idx_appointment_billing_status` (`payment_status`,`updated_at`),
  ADD KEY `idx_appointment_billing_actor` (`recorded_by_user_id`);

--
-- Indexes for table `appointment_billing_items`
--
ALTER TABLE `appointment_billing_items`
  ADD PRIMARY KEY (`billing_item_id`),
  ADD UNIQUE KEY `uq_billing_item_service` (`billing_id`,`service_id`),
  ADD KEY `idx_billing_items_service` (`service_id`);

--
-- Indexes for table `appointment_checkins`
--
ALTER TABLE `appointment_checkins`
  ADD PRIMARY KEY (`checkin_id`),
  ADD UNIQUE KEY `uq_appointment_checkins_appointment` (`appointment_id`),
  ADD KEY `idx_appointment_checkins_arrival` (`arrived_at`),
  ADD KEY `idx_appointment_checkins_actor` (`checked_in_by_user_id`),
  ADD KEY `idx_checkins_queue` (`queue_status`,`serve_next_at`,`queue_entered_at`),
  ADD KEY `idx_checkins_serve_next_actor` (`serve_next_by_user_id`),
  ADD KEY `idx_checkins_queue_actor` (`queue_updated_by_user_id`),
  ADD KEY `idx_checkin_override_actor` (`date_override_by_user_id`);

--
-- Indexes for table `appointment_deposits`
--
ALTER TABLE `appointment_deposits`
  ADD PRIMARY KEY (`deposit_id`),
  ADD UNIQUE KEY `uq_appointment_deposits_appointment` (`appointment_id`),
  ADD UNIQUE KEY `uq_appointment_deposits_reference` (`gcash_reference`),
  ADD UNIQUE KEY `uq_deposit_transfer_source` (`transferred_from_appointment_id`),
  ADD KEY `idx_appointment_deposits_review` (`status`,`submitted_at`),
  ADD KEY `idx_appointment_deposits_verifier` (`verified_by_user_id`),
  ADD KEY `idx_appointment_deposits_transfer_source` (`transferred_from_appointment_id`),
  ADD KEY `idx_appointment_deposits_transfer_actor` (`transferred_by_user_id`),
  ADD KEY `idx_deposit_deadline_extension_actor` (`deadline_extended_by_user_id`),
  ADD KEY `idx_deposit_refund_actor` (`refunded_by_user_id`);

--
-- Indexes for table `appointment_email_notifications`
--
ALTER TABLE `appointment_email_notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD UNIQUE KEY `uq_email_notification_deduplication` (`deduplication_key`),
  ADD KEY `idx_email_notification_queue` (`delivery_status`,`scheduled_at`),
  ADD KEY `idx_email_notification_appointment` (`appointment_id`),
  ADD KEY `idx_email_notification_recipient` (`recipient_user_id`);

--
-- Indexes for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD PRIMARY KEY (`appointment_id`,`service_id`),
  ADD KEY `fk_appointment_services_service` (`service_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`audit_log_id`),
  ADD KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_audit_latest_action` (`entity_type`,`entity_id`,`action`,`audit_log_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_user` (`performed_by_user_id`),
  ADD KEY `idx_audit_date` (`performed_at`);

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
  ADD UNIQUE KEY `uq_patients_identity_match` (`identity_match_key`),
  ADD UNIQUE KEY `uq_patients_user_account` (`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_patients_profile_completion` (`profile_completed_at`),
  ADD KEY `idx_patients_profile_completed_by` (`profile_completed_by_user_id`),
  ADD KEY `idx_patients_possible_duplicate` (`firstname`,`lastname`,`birthdate`);

--
-- Indexes for table `patient_account_link_authorizations`
--
ALTER TABLE `patient_account_link_authorizations`
  ADD PRIMARY KEY (`authorization_id`),
  ADD KEY `idx_link_authorization_lookup` (`authorized_email`,`status`,`expires_at`),
  ADD KEY `idx_link_authorization_patient` (`patient_id`),
  ADD KEY `idx_link_authorization_actor` (`authorized_by_user_id`),
  ADD KEY `idx_link_authorization_used_user` (`used_by_user_id`);

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
  ADD UNIQUE KEY `uq_patient_consent_patient` (`patient_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  ADD PRIMARY KEY (`dental_history_id`),
  ADD UNIQUE KEY `uq_patient_dental_history_patient` (`patient_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_duplicate_reviews`
--
ALTER TABLE `patient_duplicate_reviews`
  ADD PRIMARY KEY (`duplicate_review_id`),
  ADD UNIQUE KEY `uq_duplicate_review_pair` (`new_patient_id`,`possible_existing_patient_id`),
  ADD KEY `idx_duplicate_review_queue` (`status`,`created_at`),
  ADD KEY `idx_duplicate_review_actor` (`reviewed_by_user_id`),
  ADD KEY `fk_duplicate_review_existing_patient` (`possible_existing_patient_id`);

--
-- Indexes for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  ADD PRIMARY KEY (`medical_history_id`),
  ADD UNIQUE KEY `uq_patient_medical_history_patient` (`patient_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uq_schedules_clinic_date` (`clinic_id`,`sched_date`),
  ADD KEY `idx_schedules_date_window` (`sched_date`,`start_time`,`end_time`),
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
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `appointment_billings`
--
ALTER TABLE `appointment_billings`
  MODIFY `billing_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_billing_items`
--
ALTER TABLE `appointment_billing_items`
  MODIFY `billing_item_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_checkins`
--
ALTER TABLE `appointment_checkins`
  MODIFY `checkin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `appointment_deposits`
--
ALTER TABLE `appointment_deposits`
  MODIFY `deposit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `appointment_email_notifications`
--
ALTER TABLE `appointment_email_notifications`
  MODIFY `notification_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `audit_log_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `clinic_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `email_verifications`
--
ALTER TABLE `email_verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `patient_account_link_authorizations`
--
ALTER TABLE `patient_account_link_authorizations`
  MODIFY `authorization_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_conditions`
--
ALTER TABLE `patient_conditions`
  MODIFY `condition_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `patient_consent`
--
ALTER TABLE `patient_consent`
  MODIFY `consent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `patient_dental_history`
--
ALTER TABLE `patient_dental_history`
  MODIFY `dental_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patient_duplicate_reviews`
--
ALTER TABLE `patient_duplicate_reviews`
  MODIFY `duplicate_review_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patient_medical_history`
--
ALTER TABLE `patient_medical_history`
  MODIFY `medical_history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointment` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`);

--
-- Constraints for table `appointment_billings`
--
ALTER TABLE `appointment_billings`
  ADD CONSTRAINT `fk_appointment_billing_actor` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_billing_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE;

--
-- Constraints for table `appointment_billing_items`
--
ALTER TABLE `appointment_billing_items`
  ADD CONSTRAINT `fk_billing_items_billing` FOREIGN KEY (`billing_id`) REFERENCES `appointment_billings` (`billing_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `appointment_checkins`
--
ALTER TABLE `appointment_checkins`
  ADD CONSTRAINT `fk_checkin_override_actor` FOREIGN KEY (`date_override_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_actor` FOREIGN KEY (`checked_in_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_serve_next_actor` FOREIGN KEY (`serve_next_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_checkins_queue_actor` FOREIGN KEY (`queue_updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `appointment_deposits`
--
ALTER TABLE `appointment_deposits`
  ADD CONSTRAINT `fk_deposit_deadline_extension_actor` FOREIGN KEY (`deadline_extended_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_deposit_refund_actor` FOREIGN KEY (`refunded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_deposits_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_deposits_transfer_actor` FOREIGN KEY (`transferred_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_deposits_transfer_source` FOREIGN KEY (`transferred_from_appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_deposits_verifier` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `appointment_email_notifications`
--
ALTER TABLE `appointment_email_notifications`
  ADD CONSTRAINT `fk_email_notification_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_email_notification_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `appointment_services`
--
ALTER TABLE `appointment_services`
  ADD CONSTRAINT `fk_appointment_services_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appointment_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_profile_completed_by` FOREIGN KEY (`profile_completed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_account_link_authorizations`
--
ALTER TABLE `patient_account_link_authorizations`
  ADD CONSTRAINT `fk_link_authorization_actor` FOREIGN KEY (`authorized_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_link_authorization_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_link_authorization_used_user` FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
-- Constraints for table `patient_duplicate_reviews`
--
ALTER TABLE `patient_duplicate_reviews`
  ADD CONSTRAINT `fk_duplicate_review_actor` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duplicate_review_existing_patient` FOREIGN KEY (`possible_existing_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_duplicate_review_new_patient` FOREIGN KEY (`new_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

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

--
-- Backfill itemized billing lines without guessing multi-service prices
--
INSERT IGNORE INTO `appointment_billing_items` (`billing_id`, `service_id`, `service_name_snapshot`, `quantity`, `unit_price`, `pricing_source`, `sort_order`)
SELECT `billing`.`billing_id`, `service`.`service_id`, `service`.`service_name`, 1.00,
  CASE WHEN `service_count`.`total_services` = 1 THEN `billing`.`actual_service_amount` ELSE NULL END,
  CASE WHEN `service_count`.`total_services` = 1 AND `billing`.`actual_service_amount` IS NOT NULL THEN 'legacy-total' ELSE 'legacy-unknown' END,
  `service`.`display_order`
FROM `appointment_billings` AS `billing`
INNER JOIN (
  SELECT `appointment_id`, count(*) AS `total_services`
  FROM `appointment_services`
  GROUP BY `appointment_id`
) AS `service_count` ON `service_count`.`appointment_id` = `billing`.`appointment_id`
INNER JOIN `appointment_services` AS `appointment_service` ON `appointment_service`.`appointment_id` = `billing`.`appointment_id`
INNER JOIN `services` AS `service` ON `service`.`service_id` = `appointment_service`.`service_id`;

--
-- Operational read views
--
CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_appointment_overview` AS
SELECT `a`.`appointment_id` AS `appointment_id`, `a`.`patient_id` AS `patient_id`,
  `a`.`schedule_id` AS `schedule_id`, `a`.`clinic_id` AS `clinic_id`, `a`.`date` AS `date`,
  `schedule_row`.`start_time` AS `start_time`, `schedule_row`.`end_time` AS `end_time`,
  `a`.`status` AS `status`, `a`.`deposit_required` AS `deposit_required`,
  `a`.`payment_deadline_at` AS `payment_deadline_at`, `a`.`reviewed_at` AS `reviewed_at`,
  `a`.`accepted_for_payment_at` AS `accepted_for_payment_at`, `a`.`rejected_at` AS `rejected_at`,
  `a`.`rejection_reason` AS `rejection_reason`, `a`.`appointment_code` AS `appointment_code`,
  `a`.`code_generated_at` AS `code_generated_at`, `a`.`confirmed_at` AS `confirmed_at`,
  `a`.`treatment_started_at` AS `treatment_started_at`, `a`.`completed_at` AS `completed_at`,
  `a`.`cancelled_at` AS `cancelled_at`, `a`.`cancellation_reason` AS `cancellation_reason`,
  `a`.`created_at` AS `created_at`, `p`.`firstname` AS `firstname`, `p`.`middlename` AS `middlename`,
  `p`.`lastname` AS `lastname`, `p`.`suffix` AS `suffix`,
  concat(`p`.`lastname`,', ',`p`.`firstname`,case when nullif(trim(`p`.`middlename`),'') is null then '' else concat(' ',left(trim(`p`.`middlename`),1),'.') end,case when nullif(trim(`p`.`suffix`),'') is null then '' else concat(' ',trim(`p`.`suffix`)) end) AS `patient_name`,
  `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`phone_number` AS `phone_number`,
  `p`.`email` AS `email`, `p`.`profile_status` AS `profile_status`,
  `p`.`profile_completed_at` AS `profile_completed_at`, `c`.`clinic_name` AS `clinic_name`,
  (SELECT group_concat(`s`.`service_name` order by `s`.`display_order`,`s`.`service_name` separator ', ')
   FROM (`appointment_services` `aps` JOIN `services` `s` ON (`s`.`service_id` = `aps`.`service_id`))
   WHERE `aps`.`appointment_id` = `a`.`appointment_id`) AS `service_name`
FROM (((`appointments` `a` JOIN `patients` `p` ON (`p`.`patient_id` = `a`.`patient_id`))
LEFT JOIN `clinics` `c` ON (`c`.`clinic_id` = `a`.`clinic_id`))
LEFT JOIN `schedules` `schedule_row` ON (`schedule_row`.`schedule_id` = `a`.`schedule_id`));

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_appointment_payment_summary` AS
SELECT `a`.`appointment_id` AS `appointment_id`, `d`.`deposit_id` AS `deposit_id`,
  `d`.`amount` AS `deposit_amount`,
  case when `d`.`status` in ('Verified','Transferred') then coalesce(`d`.`amount`,0) else 0 end AS `verified_deposit`,
  `d`.`gcash_reference` AS `gcash_reference`, `d`.`receipt_path` AS `receipt_path`,
  `d`.`receipt_mime` AS `receipt_mime`, `d`.`status` AS `deposit_status`,
  `d`.`submitted_at` AS `submitted_at`, `d`.`verified_at` AS `verified_at`,
  `d`.`rejection_reason` AS `payment_rejection_reason`,
  `d`.`resubmission_deadline_at` AS `resubmission_deadline_at`, `d`.`refund_reason` AS `refund_reason`,
  `d`.`refunded_at` AS `refunded_at`, case when `d`.`receipt_path` is null then 0 else 1 end AS `has_receipt`,
  `verifier`.`email` AS `payment_verified_by`, `verifier`.`user_role` AS `payment_verified_by_role`,
  coalesce(nullif(trim(concat_ws(' ',`verifier_staff`.`firstname`,`verifier_staff`.`middlename`,`verifier_staff`.`lastname`)),''),`verifier`.`email`) AS `payment_verified_by_name`,
  `b`.`billing_id` AS `billing_id`, `b`.`actual_service_amount` AS `actual_service_amount`,
  `b`.`deposit_applied` AS `deposit_applied`, `b`.`remaining_balance` AS `remaining_balance`,
  `b`.`cash_received` AS `cash_received`, coalesce(`b`.`payment_status`,'Unpaid') AS `payment_status`,
  `b`.`recorded_at` AS `billing_recorded_at`, `b`.`paid_at` AS `paid_at`, `b`.`notes` AS `billing_notes`,
  coalesce(nullif(trim(concat_ws(' ',`recorder_staff`.`firstname`,`recorder_staff`.`middlename`,`recorder_staff`.`lastname`)),''),`recorder`.`email`,'Staff') AS `billing_recorded_by`
FROM ((((((`appointments` `a` LEFT JOIN `appointment_deposits` `d` ON (`d`.`appointment_id` = `a`.`appointment_id`))
LEFT JOIN `users` `verifier` ON (`verifier`.`id` = `d`.`verified_by_user_id`))
LEFT JOIN `staffs` `verifier_staff` ON (`verifier_staff`.`user_id` = `verifier`.`id`))
LEFT JOIN `appointment_billings` `b` ON (`b`.`appointment_id` = `a`.`appointment_id`))
LEFT JOIN `users` `recorder` ON (`recorder`.`id` = `b`.`recorded_by_user_id`))
LEFT JOIN `staffs` `recorder_staff` ON (`recorder_staff`.`user_id` = `recorder`.`id`));

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_appointment_latest_status_change` AS
SELECT `current_log`.`entity_id` AS `appointment_id`, `current_log`.`audit_log_id` AS `audit_log_id`,
  `current_log`.`description` AS `status_change_description`, `current_log`.`old_values` AS `old_values`,
  `current_log`.`new_values` AS `new_values`, `current_log`.`performed_by_user_id` AS `performed_by_user_id`,
  `current_log`.`performed_by_name` AS `status_changed_by`,
  `current_log`.`performed_by_role` AS `status_changed_by_role`, `current_log`.`source` AS `source`,
  `current_log`.`performed_at` AS `status_changed_at`
FROM (`audit_logs` `current_log` LEFT JOIN `audit_logs` `newer_log`
  ON (`newer_log`.`entity_type` = `current_log`.`entity_type`
  AND `newer_log`.`entity_id` = `current_log`.`entity_id`
  AND `newer_log`.`action` = `current_log`.`action`
  AND `newer_log`.`audit_log_id` > `current_log`.`audit_log_id`))
WHERE `current_log`.`entity_type` = 'appointment' AND `current_log`.`action` = 'status_changed'
  AND `newer_log`.`audit_log_id` is null;

CREATE OR REPLACE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_schedule_utilization` AS
SELECT `s`.`schedule_id` AS `schedule_id`, `s`.`clinic_id` AS `clinic_id`,
  `c`.`clinic_name` AS `clinic_name`, `s`.`sched_date` AS `sched_date`,
  `s`.`start_time` AS `start_time`, `s`.`end_time` AS `end_time`,
  `s`.`max_appointments` AS `capacity`,
  count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) AS `booked`,
  count(case when `a`.`status` = 'Completed' then `a`.`appointment_id` end) AS `completed`,
  count(case when `a`.`status` in ('Cancelled','Rejected') then `a`.`appointment_id` end) AS `cancelled`,
  greatest(`s`.`max_appointments` - count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end),0) AS `available_slots`,
  case when `s`.`max_appointments` = 0 then 0 else round(count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) * 100 / `s`.`max_appointments`,1) end AS `utilization_rate`
FROM ((`schedules` `s` JOIN `clinics` `c` ON (`c`.`clinic_id` = `s`.`clinic_id`))
LEFT JOIN `appointments` `a` ON (`a`.`schedule_id` = `s`.`schedule_id`))
GROUP BY `s`.`schedule_id`,`s`.`clinic_id`,`c`.`clinic_name`,`s`.`sched_date`,`s`.`start_time`,`s`.`end_time`,`s`.`max_appointments`;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
