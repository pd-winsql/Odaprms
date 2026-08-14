-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: db-oaprms-system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `appointment_billings`
--

DROP TABLE IF EXISTS `appointment_billings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_billings` (
  `billing_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`billing_id`),
  UNIQUE KEY `uq_appointment_billing` (`appointment_id`),
  KEY `idx_appointment_billing_status` (`payment_status`,`updated_at`),
  KEY `idx_appointment_billing_actor` (`recorded_by_user_id`),
  CONSTRAINT `fk_appointment_billing_actor` FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_billing_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_billings`
--

LOCK TABLES `appointment_billings` WRITE;
/*!40000 ALTER TABLE `appointment_billings` DISABLE KEYS */;
INSERT INTO `appointment_billings` VALUES (12,53,1200.00,400.00,800.00,1500.00,'Paid',18,'2026-08-10 09:03:20','2026-08-10 09:03:20',NULL,'2026-08-10 00:41:36','2026-08-10 01:03:20'),(15,66,800.00,400.00,400.00,1000.00,'Paid',18,'2026-08-10 09:03:52','2026-08-10 09:03:52',NULL,'2026-08-10 01:03:52','2026-08-10 01:03:52'),(16,70,800.00,400.00,400.00,1000.00,'Paid',18,'2026-08-12 18:40:25','2026-08-12 18:40:25',NULL,'2026-08-12 10:40:25','2026-08-12 10:40:25'),(22,81,1500.00,400.00,1100.00,2000.00,'Paid',18,'2026-08-12 21:37:24','2026-08-12 21:37:24',NULL,'2026-08-12 13:37:24','2026-08-12 13:37:24'),(32,133,2000.00,400.00,1600.00,2000.00,'Paid',7,'2026-08-14 11:32:19','2026-08-14 11:32:19',NULL,'2026-08-14 03:32:19','2026-08-14 03:32:19'),(33,132,1000.00,400.00,600.00,1000.00,'Paid',7,'2026-08-14 11:32:38','2026-08-14 11:32:38',NULL,'2026-08-14 03:32:38','2026-08-14 03:32:38');
/*!40000 ALTER TABLE `appointment_billings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_checkins`
--

DROP TABLE IF EXISTS `appointment_checkins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_checkins` (
  `checkin_id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `arrived_at` datetime NOT NULL,
  `checked_in_by_user_id` int(11) DEFAULT NULL,
  `lookup_method` enum('Code','Patient Search','Date Override') NOT NULL DEFAULT 'Code',
  `checkin_status` enum('Profile Required','Ready') NOT NULL,
  `profile_required_at_arrival` tinyint(1) NOT NULL DEFAULT 0,
  `ready_at` datetime DEFAULT NULL,
  `queue_status` enum('Waiting','Deferred') NOT NULL DEFAULT 'Waiting',
  `queue_priority` enum('Normal','Emergency') NOT NULL DEFAULT 'Normal',
  `queue_entered_at` datetime DEFAULT NULL,
  `queue_reason` varchar(255) DEFAULT NULL,
  `queue_updated_by_user_id` int(11) DEFAULT NULL,
  `queue_updated_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `date_override_reason` varchar(255) DEFAULT NULL,
  `date_override_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`checkin_id`),
  UNIQUE KEY `uq_appointment_checkins_appointment` (`appointment_id`),
  KEY `idx_appointment_checkins_arrival` (`arrived_at`),
  KEY `idx_appointment_checkins_actor` (`checked_in_by_user_id`),
  KEY `idx_checkins_queue` (`queue_status`,`queue_priority`,`queue_entered_at`),
  KEY `idx_checkins_queue_actor` (`queue_updated_by_user_id`),
  KEY `idx_checkin_override_actor` (`date_override_by_user_id`),
  CONSTRAINT `fk_checkin_override_actor` FOREIGN KEY (`date_override_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_checkins_actor` FOREIGN KEY (`checked_in_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_checkins_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_checkins_queue_actor` FOREIGN KEY (`queue_updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_checkins`
--

LOCK TABLES `appointment_checkins` WRITE;
/*!40000 ALTER TABLE `appointment_checkins` DISABLE KEYS */;
INSERT INTO `appointment_checkins` VALUES (5,39,'2026-08-05 14:56:39',7,'Code','Profile Required',1,NULL,'Waiting','Normal','2026-08-05 14:56:39',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 06:56:39','2026-08-14 03:19:49'),(17,53,'2026-08-10 08:38:14',7,'Code','Ready',1,'2026-08-10 08:40:57','Waiting','Normal','2026-08-10 08:38:14',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 00:38:14','2026-08-14 03:19:49'),(18,66,'2026-08-10 09:02:14',18,'Patient Search','Ready',1,'2026-08-10 09:03:04','Waiting','Normal','2026-08-10 09:02:14',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:02:14','2026-08-14 03:19:49'),(19,67,'2026-08-10 09:27:01',18,'Code','Ready',0,'2026-08-10 09:27:01','Waiting','Normal','2026-08-10 09:27:01',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:27:01','2026-08-14 03:19:49'),(20,70,'2026-08-12 18:39:30',18,'Code','Ready',0,'2026-08-12 18:39:30','Waiting','Normal','2026-08-12 18:39:30',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:39:30','2026-08-14 03:19:49'),(24,68,'2026-08-12 20:58:00',18,'Patient Search','Ready',0,'2026-08-12 20:58:00','Waiting','Normal','2026-08-12 20:58:00',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 12:58:00','2026-08-14 03:19:49'),(27,81,'2026-08-12 21:36:42',18,'Code','Ready',0,'2026-08-12 21:36:42','Waiting','Normal','2026-08-12 21:36:42',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 13:36:42','2026-08-14 03:19:49'),(40,133,'2026-08-14 11:31:01',7,'Code','Ready',0,'2026-08-14 11:31:01','Waiting','Normal','2026-08-14 11:31:01',NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-14 03:31:01','2026-08-14 03:31:01'),(41,132,'2026-08-14 11:31:22',7,'Code','Ready',0,'2026-08-14 11:31:22','Waiting','Normal','2026-08-14 11:32:22',NULL,7,'2026-08-14 11:32:22',NULL,NULL,NULL,'2026-08-14 03:31:22','2026-08-14 03:32:22');
/*!40000 ALTER TABLE `appointment_checkins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_deposits`
--

DROP TABLE IF EXISTS `appointment_deposits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_deposits` (
  `deposit_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`deposit_id`),
  UNIQUE KEY `uq_appointment_deposits_appointment` (`appointment_id`),
  UNIQUE KEY `uq_appointment_deposits_reference` (`gcash_reference`),
  UNIQUE KEY `uq_deposit_transfer_source` (`transferred_from_appointment_id`),
  KEY `idx_appointment_deposits_review` (`status`,`submitted_at`),
  KEY `idx_appointment_deposits_verifier` (`verified_by_user_id`),
  KEY `idx_appointment_deposits_transfer_source` (`transferred_from_appointment_id`),
  KEY `idx_appointment_deposits_transfer_actor` (`transferred_by_user_id`),
  KEY `idx_deposit_deadline_extension_actor` (`deadline_extended_by_user_id`),
  KEY `idx_deposit_refund_actor` (`refunded_by_user_id`),
  CONSTRAINT `fk_deposit_deadline_extension_actor` FOREIGN KEY (`deadline_extended_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_deposit_refund_actor` FOREIGN KEY (`refunded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_deposits_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_deposits_transfer_actor` FOREIGN KEY (`transferred_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_deposits_transfer_source` FOREIGN KEY (`transferred_from_appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_deposits_verifier` FOREIGN KEY (`verified_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_deposits`
--

LOCK TABLES `appointment_deposits` WRITE;
/*!40000 ALTER TABLE `appointment_deposits` DISABLE KEYS */;
INSERT INTO `appointment_deposits` VALUES (10,38,400.00,'123456789','storage/payment_receipts/47de4d4f65a4f9635de21232c102ce9fb39c4084.png','image/png','Under Review','2026-08-05 14:53:22',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 06:52:41','2026-08-05 06:53:22'),(11,39,400.00,'12345678910','storage/payment_receipts/33f52d7a4e9c92dedc91a7cf95e25e1d1a9c14f4.png','image/png','Verified','2026-08-05 14:55:25',7,'2026-08-05 14:56:25',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 06:55:04','2026-08-05 06:56:25'),(12,40,400.00,NULL,NULL,NULL,'Expired',NULL,NULL,NULL,'Payment submission deadline expired.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 10:12:11','2026-08-09 00:45:54'),(13,41,400.00,'1234567891011','storage/payment_receipts/487099509dbe1f3a060bbd4ac68e5eba5ece9a2c.png','image/png','Forfeited','2026-08-05 18:28:13',7,'2026-08-05 18:32:14',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'Patient did not attend the confirmed appointment.',NULL,NULL,NULL,'2026-08-05 10:17:46','2026-08-09 12:18:11'),(24,52,400.00,'987654321','storage/payment_receipts/13a965837143a5692bf4e7aa683e5d5585ed9e5b.jpg','image/jpeg','Verified','2026-08-09 20:14:53',7,'2026-08-09 20:17:33',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-09 12:11:56','2026-08-09 12:17:33'),(25,53,400.00,'231241231243423','storage/payment_receipts/e89892a614678cff58297281c7285578a39b61bf.jpg','image/jpeg','Verified','2026-08-09 20:24:26',7,'2026-08-09 20:24:40',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-09 12:23:47','2026-08-09 12:24:40'),(38,66,400.00,'98765432154','storage/payment_receipts/62d63b09f035e540222845dc6c1734499096b5f9.jpg','image/jpeg','Verified','2026-08-10 09:00:26',18,'2026-08-10 09:01:19',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 00:59:25','2026-08-10 01:01:19'),(39,67,400.00,'98765432132','storage/payment_receipts/f10158f99d5c7068c064384ccc66ebf785e1f095.jpg','image/jpeg','Verified','2026-08-10 09:25:59',18,'2026-08-10 09:26:20',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:25:15','2026-08-10 01:26:20'),(40,70,400.00,'987654321231','storage/payment_receipts/994da49f32b294474a538830f1c7c1e21ee8ee92.jpg','image/jpeg','Verified','2026-08-12 18:33:33',18,'2026-08-12 18:34:53',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:32:58','2026-08-12 10:34:53'),(47,68,400.00,'987654321111','storage/payment_receipts/760e0ed85542de89c6ea06f412f1595bb5a33c9f.jpg','image/jpeg','Verified','2026-08-12 20:45:55',18,'2026-08-12 20:57:37',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 12:44:45','2026-08-12 12:57:37'),(52,81,400.00,'9876543217676','storage/payment_receipts/0f4dc75e5e1b34c1ea09b37c87183c253ba612d4.jpg','image/jpeg','Verified','2026-08-12 21:34:40',18,'2026-08-12 21:35:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 13:34:08','2026-08-12 13:35:43'),(72,132,400.00,'1234563526574574','storage/payment_receipts/766d399117423553ea19535d8e37696c65464d49.jpg','image/jpeg','Verified','2026-08-14 11:28:19',7,'2026-08-14 11:28:43',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-14 03:27:49','2026-08-14 03:28:43'),(73,133,400.00,'123446565685785','storage/payment_receipts/603b11382141b0951d472bfd31edd7090ff9364d.jpg','image/jpeg','Verified','2026-08-14 11:29:41',7,'2026-08-14 11:29:55',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-14 03:29:23','2026-08-14 03:29:55');
/*!40000 ALTER TABLE `appointment_deposits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_email_notifications`
--

DROP TABLE IF EXISTS `appointment_email_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_email_notifications` (
  `notification_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(11) NOT NULL,
  `recipient_user_id` int(11) DEFAULT NULL,
  `notification_type` varchar(50) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (`payload` is null or json_valid(`payload`)),
  `deduplication_key` varchar(150) DEFAULT NULL,
  `delivery_status` enum('Pending','Sent','Failed') NOT NULL DEFAULT 'Pending',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL DEFAULT current_timestamp(),
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`notification_id`),
  UNIQUE KEY `uq_email_notification_deduplication` (`deduplication_key`),
  KEY `idx_email_notification_queue` (`delivery_status`,`scheduled_at`),
  KEY `idx_email_notification_appointment` (`appointment_id`),
  KEY `idx_email_notification_recipient` (`recipient_user_id`),
  CONSTRAINT `fk_email_notification_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_email_notification_recipient` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_email_notifications`
--

LOCK TABLES `appointment_email_notifications` WRITE;
/*!40000 ALTER TABLE `appointment_email_notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_email_notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_services`
--

DROP TABLE IF EXISTS `appointment_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointment_services` (
  `appointment_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  PRIMARY KEY (`appointment_id`,`service_id`),
  KEY `fk_appointment_services_service` (`service_id`),
  CONSTRAINT `fk_appointment_services_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointment_services_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_services`
--

LOCK TABLES `appointment_services` WRITE;
/*!40000 ALTER TABLE `appointment_services` DISABLE KEYS */;
INSERT INTO `appointment_services` VALUES (17,1),(19,3),(20,9),(22,12),(23,9),(24,5),(25,8),(26,2),(27,9),(28,2),(28,6),(38,5),(38,8),(39,2),(40,2),(40,10),(41,3),(52,10),(53,12),(66,8),(67,1),(68,2),(69,1),(70,2),(70,5),(81,2),(132,1),(133,2);
/*!40000 ALTER TABLE `appointment_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`appointment_id`),
  UNIQUE KEY `uq_appointments_payment_token` (`payment_access_token_hash`),
  UNIQUE KEY `uq_appointments_code` (`appointment_code`),
  KEY `clinic_id` (`clinic_id`),
  KEY `fk_appointments_schedule` (`schedule_id`),
  KEY `idx_appointments_patient` (`patient_id`),
  KEY `idx_appointments_daily_logbook` (`date`,`status`,`clinic_id`),
  KEY `idx_appointments_expiry` (`status`,`deposit_required`,`payment_deadline_at`),
  KEY `idx_appointments_review_queue` (`status`,`created_at`),
  KEY `idx_appointments_reviewer` (`reviewed_by_user_id`),
  CONSTRAINT `fk_appointment` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_reviewer` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_appointments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`schedule_id`)
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (17,14,20,1,'2026-07-27','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-B89BDF77','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-24 09:47:53'),(19,14,21,2,'2026-07-29','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-23E29085','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-24 10:29:34'),(20,16,20,1,'2026-07-27','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-766A565A','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-27 06:17:38'),(22,17,20,1,'2026-07-27','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-22975292','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-27 06:52:37'),(23,18,30,2,'2026-08-07','Cancelled',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 18:41:12',NULL,'2026-07-28 03:00:13'),(24,19,30,2,'2026-08-07','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-CFFFAF97','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-28 03:02:02'),(25,14,26,1,'2026-08-04','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-4EAEE953','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-30 01:24:05'),(26,14,25,1,'2026-08-03','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-804B172A','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-30 01:38:36'),(27,21,25,1,'2026-08-03','Cancelled',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-31 12:10:03'),(28,14,30,2,'2026-08-07','Confirmed',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'AVC-129A198F','2026-08-09 18:47:20',NULL,NULL,NULL,NULL,NULL,'2026-07-31 14:33:08'),(38,14,41,2,'2026-08-11','Cancelled',1,'2026-08-05 15:22:41','890c4ef70cbc8c2138a4a0fb28542054ea6a309e3a4bb551df9c88e9136af8db',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-09 20:04:58',NULL,'2026-08-05 06:52:41'),(39,21,27,1,'2026-08-05','Cancelled',1,'2026-08-05 15:25:04','1788ddbf94de599151ea44613df67e0077e1aef6dcb1feaadfa3b53b044c56ac',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 14:56:25',NULL,NULL,'2026-08-05 18:33:27',NULL,'2026-08-05 06:55:04'),(40,14,41,2,'2026-08-11','Cancelled',1,'2026-08-05 18:42:11','2db68df83544600a2ade91a93f539e49ab9f8a55faeaa6fadf4fc4d27bee9847',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-09 08:45:54','Payment submission deadline expired.','2026-08-05 10:12:11'),(41,23,37,1,'2026-08-12','No-show',1,'2026-08-05 18:47:46','ade548caf9a54cb812f19ed691d4b1253d6981d683a8517f4203c03745766aa5',NULL,NULL,NULL,NULL,NULL,'AVC-D8654963','2026-08-05 18:32:14','2026-08-05 18:32:14',NULL,NULL,NULL,NULL,'2026-08-05 10:17:46'),(52,21,43,1,'2026-08-18','Confirmed',1,'2026-08-10 04:11:56',NULL,7,'2026-08-09 20:11:56','2026-08-09 20:11:56',NULL,NULL,'AVC-VV3K28','2026-08-09 20:17:33','2026-08-09 20:17:33',NULL,NULL,NULL,NULL,'2026-08-09 12:11:12'),(53,29,59,2,'2026-08-10','Completed',1,'2026-08-10 04:23:47',NULL,7,'2026-08-09 20:23:47','2026-08-09 20:23:47',NULL,NULL,'AVC-LAWPLC','2026-08-09 20:24:40','2026-08-09 20:24:40','2026-08-10 09:15:17','2026-08-10 09:22:10',NULL,NULL,'2026-08-09 12:23:32'),(66,14,59,2,'2026-08-10','Completed',1,'2026-08-10 16:59:25',NULL,18,'2026-08-10 08:59:25','2026-08-10 08:59:25',NULL,NULL,'AVC-UTDX6A','2026-08-10 09:01:19','2026-08-10 09:01:19','2026-08-10 09:15:18','2026-08-10 09:22:12',NULL,NULL,'2026-08-10 00:58:35'),(67,16,59,2,'2026-08-10','Completed',1,'2026-08-10 17:25:15',NULL,18,'2026-08-10 09:25:15','2026-08-10 09:25:15',NULL,NULL,'AVC-VMBX22','2026-08-10 09:26:20','2026-08-10 09:26:20','2026-08-10 09:30:02','2026-08-10 09:33:33',NULL,NULL,'2026-08-10 01:24:33'),(68,14,37,1,'2026-08-12','Completed',1,'2026-08-13 04:44:45',NULL,18,'2026-08-12 20:44:45','2026-08-12 20:44:45',NULL,NULL,'AVC-WQUVCH','2026-08-12 20:57:37','2026-08-12 20:57:37','2026-08-12 20:58:04','2026-08-12 20:58:06',NULL,NULL,'2026-08-12 09:36:02'),(69,23,37,1,'2026-08-12','Rejected',1,NULL,NULL,18,'2026-08-12 20:58:26',NULL,'2026-08-12 20:58:26','ayaw ko lng',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-12 10:03:30'),(70,16,37,1,'2026-08-12','Completed',1,'2026-08-13 02:32:58',NULL,18,'2026-08-12 18:32:58','2026-08-12 18:32:58',NULL,NULL,'AVC-KU5GAU','2026-08-12 18:34:53','2026-08-12 18:34:53','2026-08-12 18:40:00','2026-08-12 18:44:15',NULL,NULL,'2026-08-12 10:29:59'),(81,17,37,1,'2026-08-12','Completed',1,'2026-08-13 05:34:08',NULL,18,'2026-08-12 21:34:08','2026-08-12 21:34:08',NULL,NULL,'AVC-FUL7E9','2026-08-12 21:35:43','2026-08-12 21:35:43','2026-08-12 21:36:46','2026-08-12 21:37:24',NULL,NULL,'2026-08-12 13:33:48'),(132,14,98,1,'2026-08-14','Completed',1,'2026-08-14 19:27:49',NULL,7,'2026-08-14 11:27:49','2026-08-14 11:27:49',NULL,NULL,'AVC-52E322','2026-08-14 11:28:43','2026-08-14 11:28:43','2026-08-14 11:32:26','2026-08-14 11:32:38',NULL,NULL,'2026-08-14 03:27:42'),(133,16,98,1,'2026-08-14','Completed',1,'2026-08-14 19:29:23',NULL,7,'2026-08-14 11:29:23','2026-08-14 11:29:23',NULL,NULL,'AVC-KRUP7C','2026-08-14 11:29:55','2026-08-14 11:29:55','2026-08-14 11:31:40','2026-08-14 11:32:19',NULL,NULL,'2026-08-14 03:29:18');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `audit_log_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `performed_by_user_id` int(11) DEFAULT NULL,
  `performed_by_name` varchar(255) NOT NULL,
  `performed_by_role` varchar(50) NOT NULL,
  `source` enum('User','System') NOT NULL DEFAULT 'User',
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`audit_log_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_latest_action` (`entity_type`,`entity_id`,`action`,`audit_log_id`),
  KEY `idx_audit_action` (`action`),
  KEY `idx_audit_user` (`performed_by_user_id`),
  KEY `idx_audit_date` (`performed_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`performed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=374 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (18,'appointment',39,'status_changed','Verified the deposit and confirmed appointment #39.','{\"status\":\"Awaiting Payment\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\"}',7,'admin','Admin','User','2026-08-05 06:56:25'),(19,'appointment',39,'patient_checked_in','Checked in the patient for appointment #39.',NULL,'{\"checkin_status\":\"Profile Required\",\"profile_required\":true}',7,'admin','Admin','User','2026-08-05 06:56:39'),(20,'appointment',41,'status_changed','Verified the deposit and confirmed appointment #41.','{\"status\":\"Awaiting Payment\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\"}',7,'admin','Admin','User','2026-08-05 10:32:14'),(21,'appointment',39,'status_changed','Changed appointment #39 status from Confirmed to Cancelled.','{\"status\":\"Confirmed\"}','{\"status\":\"Cancelled\"}',7,'admin','Admin','User','2026-08-05 10:33:27'),(22,'appointment',23,'status_changed','Changed appointment #23 status from Confirmed to Cancelled.','{\"status\":\"Confirmed\"}','{\"status\":\"Cancelled\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-05 10:41:12'),(23,'appointment',40,'status_changed','Cancelled appointment #40 after its payment deadline expired.','{\"status\":\"Awaiting Payment\"}','{\"status\":\"Cancelled\"}',NULL,'System','System','System','2026-08-09 00:45:54'),(79,'appointment',38,'status_changed','Changed appointment #38 status from Awaiting Deposit to Cancelled.','{\"status\":\"Awaiting Deposit\"}','{\"status\":\"Cancelled\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:04:58'),(80,'appointment',52,'appointment_requested','Submitted appointment request #52 for staff review.',NULL,'{\"status\":\"Pending Review\"}',28,'christianjamescapule@gmail.com','Patient','User','2026-08-09 12:11:12'),(81,'appointment',52,'status_changed','Changed appointment #52 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:11:56'),(82,'appointment',52,'status_changed','Verified the deposit and confirmed appointment #52.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-VV3K28\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:17:33'),(83,'appointment',41,'status_changed','Changed appointment #41 status from Confirmed to No-show.','{\"status\":\"Confirmed\"}','{\"status\":\"No-show\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:18:11'),(84,'appointment',53,'appointment_requested','Submitted appointment request #53 for staff review.',NULL,'{\"status\":\"Pending Review\"}',33,'freign@gmail.com','Patient','User','2026-08-09 12:23:32'),(85,'appointment',53,'status_changed','Changed appointment #53 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:23:47'),(86,'appointment',53,'status_changed','Verified the deposit and confirmed appointment #53.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-LAWPLC\"}',7,'admin@gmail.com','Admin','User','2026-08-09 12:24:40'),(153,'appointment',53,'patient_checked_in','Checked in the patient for appointment #53.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Profile Required\",\"profile_required\":true,\"lookup_method\":\"Code\"}',7,'admin@gmail.com','Admin','User','2026-08-10 00:38:14'),(154,'patient',29,'profile_completed','Completed the patient form for patient #29 at the front desk.',NULL,'{\"profile_status\":\"Complete\"}',7,'admin@gmail.com','Admin','User','2026-08-10 00:40:57'),(155,'appointment',53,'cash_billing_recorded','Recorded the final cash billing for appointment #53.',NULL,'{\"service_amount\":1200,\"deposit_applied\":400,\"remaining_balance\":800,\"cash_received\":1500,\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-10 00:41:36'),(156,'appointment',53,'cash_billing_recorded','Recorded the final cash billing for appointment #53.',NULL,'{\"service_amount\":1200,\"deposit_applied\":400,\"remaining_balance\":800,\"cash_received\":1500,\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-10 00:41:56'),(157,'appointment',66,'appointment_requested','Submitted appointment request #66 for staff review.',NULL,'{\"status\":\"Pending Review\"}',14,'winsight11@gmail.com','Patient','User','2026-08-10 00:58:35'),(158,'appointment',66,'status_changed','Changed appointment #66 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 00:59:25'),(159,'appointment',66,'status_changed','Verified the deposit and confirmed appointment #66.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-UTDX6A\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:01:19'),(160,'appointment',66,'patient_checked_in','Checked in the patient for appointment #66.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Profile Required\",\"profile_required\":true,\"lookup_method\":\"Patient Search\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:02:14'),(161,'patient',14,'profile_draft_saved','Saved a draft of the patient form for patient #14 at the front desk.',NULL,'{\"profile_status\":\"Draft\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:02:18'),(162,'patient',14,'profile_completed','Completed the patient form for patient #14 at the front desk.',NULL,'{\"profile_status\":\"Complete\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:03:04'),(163,'appointment',53,'cash_billing_recorded','Recorded the final cash billing for appointment #53.',NULL,'{\"service_amount\":1200,\"deposit_applied\":400,\"remaining_balance\":800,\"cash_received\":1500,\"payment_status\":\"Paid\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:03:20'),(164,'appointment',66,'cash_billing_recorded','Recorded the final cash billing for appointment #66.',NULL,'{\"service_amount\":800,\"deposit_applied\":400,\"remaining_balance\":400,\"cash_received\":1000,\"payment_status\":\"Paid\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:03:52'),(165,'appointment',53,'status_changed','Changed appointment #53 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:15:17'),(166,'appointment',66,'status_changed','Changed appointment #66 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:15:18'),(167,'appointment',53,'status_changed','Changed appointment #53 status from In Progress to Completed.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:22:10'),(168,'appointment',66,'status_changed','Changed appointment #66 status from In Progress to Completed.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:22:12'),(169,'appointment',67,'appointment_requested','Submitted appointment request #67 for staff review.',NULL,'{\"status\":\"Pending Review\"}',15,'stephanieunista@gmail.com','Patient','User','2026-08-10 01:24:33'),(170,'appointment',67,'status_changed','Changed appointment #67 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:25:15'),(171,'appointment',67,'status_changed','Verified the deposit and confirmed appointment #67.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-VMBX22\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:26:20'),(172,'appointment',67,'patient_checked_in','Checked in the patient for appointment #67.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Code\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:27:01'),(173,'appointment',67,'status_changed','Changed appointment #67 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:30:02'),(174,'appointment',67,'status_changed','Changed appointment #67 status from In Progress to Completed.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-10 01:33:33'),(175,'appointment',68,'appointment_requested','Submitted appointment request #68 for staff review.',NULL,'{\"status\":\"Pending Review\"}',14,'winsight11@gmail.com','Patient','User','2026-08-12 09:36:02'),(176,'appointment',69,'appointment_requested','Submitted appointment request #69 for staff review.',NULL,'{\"status\":\"Pending Review\"}',31,'ronniebarasi30@gmail.com','Patient','User','2026-08-12 10:03:30'),(177,'appointment',70,'appointment_requested','Submitted appointment request #70 for staff review.',NULL,'{\"status\":\"Pending Review\"}',15,'stephanieunista@gmail.com','Patient','User','2026-08-12 10:29:59'),(178,'appointment',70,'status_changed','Changed appointment #70 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:32:58'),(179,'appointment',70,'status_changed','Verified the deposit and confirmed appointment #70.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-KU5GAU\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:34:53'),(180,'appointment',70,'patient_checked_in','Checked in the patient for appointment #70.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Code\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:39:30'),(181,'appointment',70,'status_changed','Changed appointment #70 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:40:00'),(182,'appointment',70,'cash_billing_recorded','Recorded the final cash billing for appointment #70.',NULL,'{\"service_amount\":800,\"deposit_applied\":400,\"remaining_balance\":400,\"cash_received\":1000,\"payment_status\":\"Paid\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:40:25'),(183,'appointment',70,'status_changed','Changed appointment #70 status from In Progress to Completed.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 10:44:15'),(217,'appointment',68,'status_changed','Changed appointment #68 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:44:45'),(218,'appointment',68,'status_changed','Verified the deposit and confirmed appointment #68.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-WQUVCH\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:57:37'),(219,'appointment',68,'patient_checked_in','Checked in the patient for appointment #68.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Patient Search\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:58:00'),(220,'appointment',68,'status_changed','Changed appointment #68 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:58:04'),(221,'appointment',68,'status_changed','Changed appointment #68 status from In Progress to Completed.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:58:06'),(222,'appointment',69,'status_changed','Changed appointment #69 status from Pending Review to Rejected.','{\"status\":\"Pending Review\"}','{\"status\":\"Rejected\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 12:58:26'),(245,'appointment',81,'appointment_requested','Submitted appointment request #81 for staff review.',NULL,'{\"status\":\"Pending Review\"}',17,'llantomichelle9@gmail.com','Patient','User','2026-08-12 13:33:48'),(246,'appointment',81,'status_changed','Changed appointment #81 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:34:08'),(247,'appointment',81,'status_changed','Verified the deposit and confirmed appointment #81.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-FUL7E9\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:35:43'),(248,'appointment',81,'patient_checked_in','Checked in the patient for appointment #81.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Code\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:36:42'),(249,'appointment',81,'status_changed','Changed appointment #81 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:36:46'),(250,'appointment',81,'cash_billing_recorded','Recorded the final cash billing for appointment #81.',NULL,'{\"service_amount\":1500,\"deposit_applied\":400,\"amount_due\":1100,\"cash_tendered\":2000,\"change\":900,\"payment_status\":\"Paid\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:37:24'),(251,'appointment',81,'status_changed','Completed appointment #81 after full settlement.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\",\"payment_status\":\"Paid\"}',18,'Winje Joaquin Corpuz','Dental Assistant','User','2026-08-12 13:37:24'),(358,'appointment',132,'appointment_requested','Submitted appointment request #132 for staff review.',NULL,'{\"status\":\"Pending Review\"}',14,'winsight11@gmail.com','Patient','User','2026-08-14 03:27:42'),(359,'appointment',132,'status_changed','Changed appointment #132 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:27:49'),(360,'appointment',132,'status_changed','Verified the deposit and confirmed appointment #132.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-52E322\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:28:43'),(361,'appointment',133,'appointment_requested','Submitted appointment request #133 for staff review.',NULL,'{\"status\":\"Pending Review\"}',15,'stephanieunista@gmail.com','Patient','User','2026-08-14 03:29:18'),(362,'appointment',133,'status_changed','Changed appointment #133 status from Pending Review to Awaiting Deposit.','{\"status\":\"Pending Review\"}','{\"status\":\"Awaiting Deposit\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:29:23'),(363,'appointment',133,'status_changed','Verified the deposit and confirmed appointment #133.','{\"status\":\"Payment Under Review\",\"deposit_status\":\"Under Review\"}','{\"status\":\"Confirmed\",\"deposit_status\":\"Verified\",\"appointment_code\":\"AVC-KRUP7C\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:29:55'),(364,'appointment',133,'patient_checked_in','Checked in the patient for appointment #133.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Code\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:31:01'),(365,'appointment',132,'patient_checked_in','Checked in the patient for appointment #132.',NULL,'{\"status\":\"Checked In\",\"checkin_status\":\"Ready\",\"profile_required\":false,\"lookup_method\":\"Code\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:31:22'),(366,'appointment',133,'status_changed','Changed appointment #133 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:31:40'),(367,'appointment',132,'queue_deferred','Deferred appointment #132 in the patient queue.','{\"queue_status\":\"Waiting\"}','{\"queue_status\":\"Deferred\",\"reason\":\"Emergency\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:31:49'),(368,'appointment',133,'cash_billing_recorded','Recorded the final cash billing for appointment #133.',NULL,'{\"service_amount\":2000,\"deposit_applied\":400,\"amount_due\":1600,\"cash_tendered\":2000,\"change\":400,\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:19'),(369,'appointment',133,'status_changed','Completed appointment #133 after full settlement.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\",\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:19'),(370,'appointment',132,'queue_returned','Returned appointment #132 to the patient queue.','{\"queue_status\":\"Deferred\"}','{\"queue_status\":\"Waiting\",\"queue_priority\":\"Normal\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:22'),(371,'appointment',132,'status_changed','Changed appointment #132 status from Checked In to In Progress.','{\"status\":\"Checked In\"}','{\"status\":\"In Progress\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:26'),(372,'appointment',132,'cash_billing_recorded','Recorded the final cash billing for appointment #132.',NULL,'{\"service_amount\":1000,\"deposit_applied\":400,\"amount_due\":600,\"cash_tendered\":1000,\"change\":400,\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:38'),(373,'appointment',132,'status_changed','Completed appointment #132 after full settlement.','{\"status\":\"In Progress\"}','{\"status\":\"Completed\",\"payment_status\":\"Paid\"}',7,'admin@gmail.com','Admin','User','2026-08-14 03:32:38');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinics`
--

DROP TABLE IF EXISTS `clinics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clinics` (
  `clinic_id` int(11) NOT NULL AUTO_INCREMENT,
  `clinic_name` varchar(100) NOT NULL,
  `clinic_address` varchar(100) NOT NULL,
  `clinic_contact` varchar(15) NOT NULL,
  `clinic_image` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`clinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinics`
--

LOCK TABLES `clinics` WRITE;
/*!40000 ALTER TABLE `clinics` DISABLE KEYS */;
INSERT INTO `clinics` VALUES (1,'Alcala Branch','Zone 4, Tupang, Alcala, Cagayan','0912-345-6789',NULL),(2,'Tuguegarao Branch','Bartolome St., Caggay, Tuguegarao City, Cagayan','0912-345-6789',NULL);
/*!40000 ALTER TABLE `clinics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_verifications`
--

DROP TABLE IF EXISTS `email_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_verifications`
--

LOCK TABLES `email_verifications` WRITE;
/*!40000 ALTER TABLE `email_verifications` DISABLE KEYS */;
INSERT INTO `email_verifications` VALUES (2,'winsight11@gmail.com','652689','2026-07-24 18:02:02',1,'2026-07-24 09:52:02'),(4,'roncorpuz09@gmail.com','904227','2026-07-25 13:38:02',0,'2026-07-25 05:28:02'),(8,'stephanieunista@gmail.com','192956','2026-07-27 13:32:10',1,'2026-07-27 05:22:10'),(9,'llantomichelle9@gmail.com','136841','2026-07-27 14:55:09',1,'2026-07-27 06:45:09'),(13,'small@gmail.com','581017','2026-07-27 09:31:03',0,'2026-07-27 07:21:03'),(15,'winje@gmail.com','908945','2026-07-27 22:50:21',1,'2026-07-27 14:40:21'),(16,'j.cruz@gmail.com','023441','2026-07-28 11:13:10',1,'2026-07-28 03:03:10'),(17,'jcruz@gmail.com','406047','2026-07-28 11:14:43',1,'2026-07-28 03:04:43'),(18,'christianjamescapule@gmail.com','488760','2026-07-31 20:11:35',1,'2026-07-31 12:01:35'),(19,'ronniebarasi30@gmail.com','900802','2026-08-05 18:23:47',1,'2026-08-05 10:13:47'),(20,'freign@gmail.com','119971','2026-08-09 20:31:10',1,'2026-08-09 12:21:10');
/*!40000 ALTER TABLE `email_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_hash` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
INSERT INTO `password_resets` VALUES (38,NULL,'roncorpuz09@gmail.com','475333','2026-07-29 18:21:36',0,'2026-07-29 10:11:36'),(39,NULL,'stephanieunista@gmail.com','873462','2026-07-30 20:35:01',0,'2026-07-30 12:25:01'),(46,NULL,'winsight11@gmail.com','982372','2026-08-14 09:09:54',0,'2026-08-14 00:59:54');
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_account_link_authorizations`
--

DROP TABLE IF EXISTS `patient_account_link_authorizations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_account_link_authorizations` (
  `authorization_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `authorized_email` varchar(255) NOT NULL,
  `status` enum('Active','Used','Revoked','Expired') NOT NULL DEFAULT 'Active',
  `authorized_by_user_id` int(11) DEFAULT NULL,
  `authorized_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `used_by_user_id` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`authorization_id`),
  KEY `idx_link_authorization_lookup` (`authorized_email`,`status`,`expires_at`),
  KEY `idx_link_authorization_patient` (`patient_id`),
  KEY `idx_link_authorization_actor` (`authorized_by_user_id`),
  KEY `idx_link_authorization_used_user` (`used_by_user_id`),
  CONSTRAINT `fk_link_authorization_actor` FOREIGN KEY (`authorized_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_link_authorization_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_link_authorization_used_user` FOREIGN KEY (`used_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_account_link_authorizations`
--

LOCK TABLES `patient_account_link_authorizations` WRITE;
/*!40000 ALTER TABLE `patient_account_link_authorizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_account_link_authorizations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_conditions`
--

DROP TABLE IF EXISTS `patient_conditions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_conditions` (
  `condition_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `condition` varchar(100) NOT NULL,
  PRIMARY KEY (`condition_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_conditions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_conditions`
--

LOCK TABLES `patient_conditions` WRITE;
/*!40000 ALTER TABLE `patient_conditions` DISABLE KEYS */;
INSERT INTO `patient_conditions` VALUES (2,17,'Anemia');
/*!40000 ALTER TABLE `patient_conditions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_consent`
--

DROP TABLE IF EXISTS `patient_consent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_consent` (
  `consent_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `consent_name` varchar(100) DEFAULT NULL,
  `consent_for` varchar(20) DEFAULT NULL,
  `consent_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`consent_id`),
  UNIQUE KEY `uq_patient_consent_patient` (`patient_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_consent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_consent`
--

LOCK TABLES `patient_consent` WRITE;
/*!40000 ALTER TABLE `patient_consent` DISABLE KEYS */;
INSERT INTO `patient_consent` VALUES (2,16,NULL,'myself','2026-07-21','2026-07-27 05:44:48'),(3,17,NULL,'myself','2026-07-25','2026-07-27 07:35:28'),(16,29,'Jon Freign Corpuz','myself','2026-08-10','2026-08-10 00:40:57'),(17,14,'Winjemelron Corpuz','myself','2026-08-10','2026-08-10 01:02:18');
/*!40000 ALTER TABLE `patient_consent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_dental_history`
--

DROP TABLE IF EXISTS `patient_dental_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_dental_history` (
  `dental_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `previous_dentist` varchar(100) DEFAULT NULL,
  `last_dental_visit` date DEFAULT NULL,
  `treatment_done` varchar(255) DEFAULT NULL,
  `reason_for_visit` varchar(255) DEFAULT NULL,
  `referred_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`dental_history_id`),
  UNIQUE KEY `uq_patient_dental_history_patient` (`patient_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_dental_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_dental_history`
--

LOCK TABLES `patient_dental_history` WRITE;
/*!40000 ALTER TABLE `patient_dental_history` DISABLE KEYS */;
INSERT INTO `patient_dental_history` VALUES (3,16,'yesgdbhyfgeg ','2026-07-01','toothtache ','check up','none','2026-07-27 05:50:18','patient','2026-07-27 13:54:32'),(4,17,'STEPHANIE UNISTA','2026-07-25','TOOTHWHITENING','CHECK UP','DR. RON RON','2026-07-27 07:28:06','patient','2026-07-27 15:28:06'),(17,29,'N/A',NULL,'N/A','Consultation','N/A','2026-08-10 00:40:57','staff:7','2026-08-10 08:40:57'),(18,14,'N/A',NULL,NULL,'Consultation','N/A','2026-08-10 01:02:18','staff:18','2026-08-10 09:03:04');
/*!40000 ALTER TABLE `patient_dental_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_duplicate_reviews`
--

DROP TABLE IF EXISTS `patient_duplicate_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_duplicate_reviews` (
  `duplicate_review_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `new_patient_id` int(11) NOT NULL,
  `possible_existing_patient_id` int(11) NOT NULL,
  `match_basis` varchar(100) NOT NULL DEFAULT 'Name and birthdate',
  `status` enum('Pending','Linked','Dismissed') NOT NULL DEFAULT 'Pending',
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `resolution_notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`duplicate_review_id`),
  UNIQUE KEY `uq_duplicate_review_pair` (`new_patient_id`,`possible_existing_patient_id`),
  KEY `idx_duplicate_review_queue` (`status`,`created_at`),
  KEY `idx_duplicate_review_actor` (`reviewed_by_user_id`),
  KEY `fk_duplicate_review_existing_patient` (`possible_existing_patient_id`),
  CONSTRAINT `fk_duplicate_review_actor` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_duplicate_review_existing_patient` FOREIGN KEY (`possible_existing_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_duplicate_review_new_patient` FOREIGN KEY (`new_patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_duplicate_reviews`
--

LOCK TABLES `patient_duplicate_reviews` WRITE;
/*!40000 ALTER TABLE `patient_duplicate_reviews` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_duplicate_reviews` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_medical_history`
--

DROP TABLE IF EXISTS `patient_medical_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_medical_history` (
  `medical_history_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `last_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`medical_history_id`),
  UNIQUE KEY `uq_patient_medical_history_patient` (`patient_id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `fk_medical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_medical_history`
--

LOCK TABLES `patient_medical_history` WRITE;
/*!40000 ALTER TABLE `patient_medical_history` DISABLE KEYS */;
INSERT INTO `patient_medical_history` VALUES (3,16,1,0,NULL,1,NULL,1,NULL,0,NULL,0,0,0,1,NULL,0,0,0,NULL,'2026-07-27 05:50:15','ab+','120/80','patient','2026-07-27 13:56:13'),(4,17,1,0,NULL,0,NULL,0,NULL,0,NULL,0,0,0,0,NULL,0,0,0,NULL,'2026-07-27 07:29:18',NULL,NULL,'patient','2026-07-27 15:29:21'),(17,29,0,0,NULL,0,NULL,0,NULL,0,NULL,0,0,0,0,NULL,0,0,0,NULL,'2026-08-10 00:40:57','AB+',NULL,'staff:7','2026-08-10 08:40:57'),(18,14,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:02:18',NULL,NULL,'staff:18','2026-08-10 09:03:04');
/*!40000 ALTER TABLE `patient_medical_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `identity_match_key` char(64) DEFAULT NULL,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `uq_patients_identity_match` (`identity_match_key`),
  UNIQUE KEY `uq_patients_user_account` (`user_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_patients_profile_completion` (`profile_completed_at`),
  KEY `idx_patients_profile_completed_by` (`profile_completed_by_user_id`),
  KEY `idx_patients_possible_duplicate` (`firstname`,`lastname`,`birthdate`),
  CONSTRAINT `fk_patients_profile_completed_by` FOREIGN KEY (`profile_completed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (14,14,'Win','Corpuz','Joaquin',NULL,21,'Male','09123456789','winsight11@gmail.com','2005-04-09','Single','Baybayog, Alcala, Cagayan','Baybayog, Alcala, Cagayan',NULL,'Student',NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-24 09:47:53','2026-08-10 09:03:04',18,'Complete','ed83b3dae05c29fd241f60f98ed05fb76d8a1f9ea49c42f8fd481b7e6b5a43be'),(16,15,'Ning','Unista','v ',NULL,21,'Female','09218656206','stephanieunista@gmail.com','2004-09-04','Single','masin, alcala cagayan ','masin,alcala, cgayan','step  unista','student','n/a',NULL,NULL,NULL,NULL,NULL,'2026-07-27 05:23:30','2026-07-27 13:23:30',NULL,'Complete','82879cf53db26b5dbf683d55edd6ef6ff462b021ac9a18fd49c944abdb17004b'),(17,17,'Michelle','LLANTO','JACINTO',NULL,21,'Female','09090909090','llantomichelle9@gmail.com','2005-06-02','Single','Baculod Alcala Cagayan','N/A','MICHELLE LLANTO','STUDENT','N/A',NULL,NULL,NULL,NULL,NULL,'2026-07-27 06:49:28','2026-07-27 14:49:28',NULL,'Complete','a35cde19f990a145c1db27df8a7a68c70e7b09e701700ef3d6a8b45effe23ac0'),(18,21,'Juan','Dela Cruz','Santos',NULL,22,'Male','09123456789','jcruz@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-28 03:00:13',NULL,NULL,'Incomplete',NULL),(19,NULL,'Maria','Lago','Palo',NULL,20,'Female','09123456789','m.lago@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-28 03:02:02',NULL,NULL,'Incomplete',NULL),(20,20,'CruzJ','',NULL,NULL,NULL,NULL,NULL,'j.cruz@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-28 03:03:46',NULL,NULL,'Incomplete',NULL),(21,28,'Pogicj','palo','Dikoalam',NULL,NULL,NULL,NULL,'christianjamescapule@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-31 12:02:19',NULL,NULL,'Incomplete',NULL),(23,31,'Ronnie','',NULL,NULL,NULL,NULL,NULL,'ronniebarasi30@gmail.com',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-05 10:14:28',NULL,NULL,'Incomplete',NULL),(29,33,'Jon Freign','Corpuz','Joaquin',NULL,20,'Male','09123456789','freign@gmail.com','2006-02-17','Single','Baybayog, Alcala, Cagayan','N/A','Freign Corpuz','STUDENT','N/A','N/A','N/A',NULL,NULL,NULL,'2026-08-09 12:21:32','2026-08-10 08:40:57',7,'Complete','cd85d7807fe28b764b9b4c614f0dbf302deaabfa1a07c6f9fa37b1e6ae08da4d');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedules`
--

DROP TABLE IF EXISTS `schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schedules` (
  `schedule_id` int(11) NOT NULL AUTO_INCREMENT,
  `clinic_id` int(11) NOT NULL,
  `sched_date` date NOT NULL,
  `max_appointments` smallint(6) NOT NULL DEFAULT 8,
  PRIMARY KEY (`schedule_id`),
  KEY `fkclinic_id` (`clinic_id`),
  CONSTRAINT `fkclinic_id` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`clinic_id`)
) ENGINE=InnoDB AUTO_INCREMENT=99 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedules`
--

LOCK TABLES `schedules` WRITE;
/*!40000 ALTER TABLE `schedules` DISABLE KEYS */;
INSERT INTO `schedules` VALUES (20,1,'2026-07-27',10),(21,2,'2026-07-29',8),(24,1,'2026-07-31',8),(25,1,'2026-08-03',8),(26,1,'2026-08-04',8),(27,1,'2026-08-05',8),(29,2,'2026-08-06',8),(30,2,'2026-08-07',8),(31,2,'2026-08-08',10),(34,1,'2026-08-01',8),(37,1,'2026-08-12',8),(41,2,'2026-08-11',8),(42,1,'2026-08-17',8),(43,1,'2026-08-18',8),(44,2,'2026-08-19',8),(45,2,'2026-08-20',8),(46,2,'2026-08-21',8),(47,1,'2026-08-24',8),(48,1,'2026-08-25',8),(59,2,'2026-08-10',8),(98,1,'2026-08-14',8);
/*!40000 ALTER TABLE `schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_categories`
--

DROP TABLE IF EXISTS `service_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `service_categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) NOT NULL,
  `category_description` varchar(255) DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_categories`
--

LOCK TABLES `service_categories` WRITE;
/*!40000 ALTER TABLE `service_categories` DISABLE KEYS */;
INSERT INTO `service_categories` VALUES (1,'Preventive & Diagnostic Care','Routine visits that catch problems early and keep your smile healthy in between appointments.',1),(2,'Restorative Treatments','Repairing damaged, decayed, or missing teeth so you can bite, chew, and smile with confidence.',2),(3,'Oral Surgery','Extractions and surgical procedures performed with care, plus clear aftercare guidance.',3),(4,'Cosmetic & Orthodontic','Options to align, brighten, and refine the appearance of your smile.',4);
/*!40000 ALTER TABLE `service_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `services` (
  `service_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `service_name` varchar(100) NOT NULL,
  `service_description` varchar(255) DEFAULT NULL,
  `service_icon` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`service_id`),
  KEY `fk_category` (`category_id`),
  CONSTRAINT `fk_category` FOREIGN KEY (`category_id`) REFERENCES `service_categories` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,1,'Cleaning (Prophylaxis)','Professional plaque and tartar removal for a fresher, healthier smile.','fa-solid fa-broom',1,1),(2,1,'Scaling','Deep cleaning below the gumline to treat and help prevent gum disease.','fa-solid fa-teeth',1,2),(3,1,'Periapical X-ray','Detailed imaging of a tooth\'s root and the surrounding bone.','fa-solid fa-x-ray',1,3),(4,2,'Restoration (Fillings)','Composite or amalgam fillings that repair cavities and minor damage.','fa-solid fa-tooth',1,4),(5,2,'Crown / Jackets','A custom cap that protects and rebuilds a weakened or broken tooth.','fa-solid fa-crown',1,5),(6,2,'Bridge','A fixed replacement that closes the gap left by a missing tooth.','fa-solid fa-link',1,6),(7,2,'Root Canal','Treats infected or damaged tooth pulp to help save the natural tooth.','fa-solid fa-syringe',1,7),(8,2,'Dentures','Removable replacements for some or all missing teeth.','fa-solid fa-teeth',1,8),(9,3,'Extraction','Safe removal of a damaged, decayed, or problematic tooth.','fa-solid fa-tooth',1,9),(10,3,'Wisdom Tooth Removal','Removal of impacted or emerging third molars.','fa-solid fa-tooth',1,10),(11,4,'Braces','Gradually aligns crowded, gapped, or misaligned teeth over time.','fa-solid fa-teeth-open',1,11),(12,4,'Whitening','A professional treatment to brighten stained or discolored teeth.','fa-solid fa-star',1,12),(13,4,'Veneer','Thin custom shells that reshape and brighten the front of a tooth.','fa-solid fa-gem',1,13);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 400.00,
  `payment_deadline_minutes` smallint(5) unsigned NOT NULL DEFAULT 30,
  `gcash_account_name` varchar(100) DEFAULT NULL,
  `gcash_account_number` varchar(30) DEFAULT NULL,
  `gcash_qr_path` varchar(255) DEFAULT NULL,
  `last_updated_by` varchar(20) DEFAULT NULL,
  `last_updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
INSERT INTO `site_settings` VALUES (1,'Dr. Aprille','Clinica Dental','site_logo_1785381335.png','Online Dental Appointment & Patient Records Management System','Two Clinics in Cagayan · Alcala & Tuguegarao','Dental care for Alcala and Tuguegarao families.','From routine cleanings to root canals, crowns, and wisdom tooth removal — book your visit online in a few minutes.','Dr. Aprille Ventura Clinica Dental provides patient-centered dental care across our Alcala and Tuguegarao branches — from routine checkups to more involved restorative and cosmetic treatment. Our team takes the time to walk you through every step, so you always know what to expect before, during, and after your visit.','Patient-Centered Care','Every visit is explained clearly, so you always know what to expect.','Experienced Team','Dental professionals handling everything from routine care to advanced treatment.','Two Convenient Branches','Serving patients in both Alcala and Tuguegarao, Cagayan.','Alcala & Tuguegarao, Cagayan','0912-345-6789','info@draprilleventura.com',400.00,480,NULL,NULL,'gcash_qr_c3cf08d76a38b646.jpg','Admin','2026-08-10 08:49:28');
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staffs`
--

DROP TABLE IF EXISTS `staffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staffs` (
  `staff_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female','Prefer not to say') DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `employment_status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`staff_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staffs`
--

LOCK TABLES `staffs` WRITE;
/*!40000 ALTER TABLE `staffs` DISABLE KEYS */;
INSERT INTO `staffs` VALUES (2,16,'stephanie','unista','v','Female','09218656206','stephyyyunista94@gmail.com','Active','2026-07-27 06:24:33'),(3,18,'Winje','Corpuz','Joaquin','Male','09123412345','roncorpuz09@gmail.com','Active','2026-07-27 10:26:30'),(4,22,'Pogi','Naman','Mo','Male','09123412345','corpuzwinjemelron@gmail.com','Active','2026-07-30 13:47:39');
/*!40000 ALTER TABLE `staffs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `user_role` enum('Patient','Admin','Dental Assistant') NOT NULL DEFAULT 'Patient',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (7,'admin@gmail.com','admin','$2y$10$yjiG6c81sf6NPj8gEWkR8.6BEFnug.jLEry2zzD7L9gGzhxY/NTGm','2026-08-09 18:47:19','Admin'),(14,'winsight11@gmail.com','winpogi','$2y$10$u.XUDEHisxdJ6QbWZciI/un2sHC5csepwapa6q9XNkmGlqQ6.mtdi','2026-08-09 18:47:19','Patient'),(15,'stephanieunista@gmail.com','ning','$2y$10$qPSiUoZKhvr6NToziqIhMe8gajcDIgl/PhMfpP2FJlV8DNQJ/jO5C','2026-08-09 18:47:19','Patient'),(16,'stephyyyunista94@gmail.com','stephanie.unista','$2y$10$6dDvGf5.aFptNtGWE9QiseE3qAk9V/jB7rL/4jW2Y.WTTJy.CAvem','2026-08-09 18:47:19','Dental Assistant'),(17,'llantomichelle9@gmail.com','michelle','$2y$10$Y/3.bipXLAteUxw8NpwN0ekDWVojeZTy95QV.wCzVcYntmfn6ARCS','2026-08-09 18:47:19','Patient'),(18,'roncorpuz09@gmail.com','winje.corpuz','$2y$10$6SSUY0/c2WLmquUkQXC7gehJfOLHHBqc1i2uhb6HmH2c64LJc4bMm','2026-08-09 18:47:19','Dental Assistant'),(19,'winje@gmail.com','winje.win','$2y$10$C69fAaA/Er81z90RnoB0H.XQ9ze3mZhkEn/2fK5q7z80h04ZVUVRm','2026-08-09 18:47:19','Patient'),(20,'j.cruz@gmail.com','cruzJ','$2y$10$3y.eHdpfkHY6s.7oChZwEOfGiWzhvMo.yhfjwwgRUgY8LQbys/XlW','2026-08-09 18:47:19','Patient'),(21,'jcruz@gmail.com','cruz.J','$2y$10$F/Rfkca9YMPaDkjVAXRdTeLZvRiAWVX3ldVDr490E8CbCnQw.hSnu','2026-08-09 18:47:19','Patient'),(22,'corpuzwinjemelron@gmail.com','pogi.naman','$2y$10$AeUUJZzAJHanni9kfh452eu2R2nVKKNgWOC.1EOtPR67782eCz3p6','2026-08-09 18:47:19','Dental Assistant'),(28,'christianjamescapule@gmail.com','pogicj','$2y$10$BPHcRiUNHPEqlA2q7g2ETe/jWRamNzub5vNH9WGxri1buDZdC3GW2','2026-08-09 18:47:19','Patient'),(30,'codex-feature-test@example.invalid','codex_feature_test','$2y$10$Z/TB4KriADPNW0Ex53qcbeehjydDYxsZqfs0x1QHSKg2ycmL2MRea','2026-08-09 18:47:19','Admin'),(31,'ronniebarasi30@gmail.com','ronnie','$2y$10$CLljiRpNi.yKtFg4of6h/.iATUX.fcdf102G4mgYmGYjHzg8bX9Uu','2026-08-09 18:47:19','Patient'),(33,'freign@gmail.com','freign','$2y$10$3cm.pj6zomvjewi/LJURD.i6X7XuT75vouIYkPZE5TcLGvkpVZBTm','2026-08-09 20:21:32','Patient');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `vw_appointment_latest_status_change`
--

DROP TABLE IF EXISTS `vw_appointment_latest_status_change`;
/*!50001 DROP VIEW IF EXISTS `vw_appointment_latest_status_change`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_appointment_latest_status_change` AS SELECT
 1 AS `appointment_id`,
  1 AS `audit_log_id`,
  1 AS `status_change_description`,
  1 AS `old_values`,
  1 AS `new_values`,
  1 AS `performed_by_user_id`,
  1 AS `status_changed_by`,
  1 AS `status_changed_by_role`,
  1 AS `source`,
  1 AS `status_changed_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_appointment_overview`
--

DROP TABLE IF EXISTS `vw_appointment_overview`;
/*!50001 DROP VIEW IF EXISTS `vw_appointment_overview`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_appointment_overview` AS SELECT
 1 AS `appointment_id`,
  1 AS `patient_id`,
  1 AS `schedule_id`,
  1 AS `clinic_id`,
  1 AS `date`,
  1 AS `status`,
  1 AS `deposit_required`,
  1 AS `payment_deadline_at`,
  1 AS `reviewed_at`,
  1 AS `accepted_for_payment_at`,
  1 AS `rejected_at`,
  1 AS `rejection_reason`,
  1 AS `appointment_code`,
  1 AS `code_generated_at`,
  1 AS `confirmed_at`,
  1 AS `treatment_started_at`,
  1 AS `completed_at`,
  1 AS `cancelled_at`,
  1 AS `cancellation_reason`,
  1 AS `created_at`,
  1 AS `firstname`,
  1 AS `middlename`,
  1 AS `lastname`,
  1 AS `suffix`,
  1 AS `patient_name`,
  1 AS `age`,
  1 AS `gender`,
  1 AS `phone_number`,
  1 AS `email`,
  1 AS `profile_status`,
  1 AS `profile_completed_at`,
  1 AS `clinic_name`,
  1 AS `service_name` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_appointment_payment_summary`
--

DROP TABLE IF EXISTS `vw_appointment_payment_summary`;
/*!50001 DROP VIEW IF EXISTS `vw_appointment_payment_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_appointment_payment_summary` AS SELECT
 1 AS `appointment_id`,
  1 AS `deposit_id`,
  1 AS `deposit_amount`,
  1 AS `verified_deposit`,
  1 AS `gcash_reference`,
  1 AS `receipt_path`,
  1 AS `receipt_mime`,
  1 AS `deposit_status`,
  1 AS `submitted_at`,
  1 AS `verified_at`,
  1 AS `payment_rejection_reason`,
  1 AS `resubmission_deadline_at`,
  1 AS `refund_reason`,
  1 AS `refunded_at`,
  1 AS `has_receipt`,
  1 AS `payment_verified_by`,
  1 AS `payment_verified_by_role`,
  1 AS `payment_verified_by_name`,
  1 AS `billing_id`,
  1 AS `actual_service_amount`,
  1 AS `deposit_applied`,
  1 AS `remaining_balance`,
  1 AS `cash_received`,
  1 AS `payment_status`,
  1 AS `billing_recorded_at`,
  1 AS `paid_at`,
  1 AS `billing_notes`,
  1 AS `billing_recorded_by` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_patient_information`
--

DROP TABLE IF EXISTS `vw_patient_information`;
/*!50001 DROP VIEW IF EXISTS `vw_patient_information`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_patient_information` AS SELECT
 1 AS `patient_id`,
  1 AS `user_id`,
  1 AS `firstname`,
  1 AS `middlename`,
  1 AS `lastname`,
  1 AS `full_name`,
  1 AS `age`,
  1 AS `gender`,
  1 AS `birthdate`,
  1 AS `civil_status`,
  1 AS `phone_number`,
  1 AS `email`,
  1 AS `home_address`,
  1 AS `work_address`,
  1 AS `occupation`,
  1 AS `office_contact`,
  1 AS `fb_account`,
  1 AS `guardian_name`,
  1 AS `guardian_contact`,
  1 AS `physician_name`,
  1 AS `physician_contact`,
  1 AS `physician_address`,
  1 AS `previous_dentist`,
  1 AS `last_dental_visit`,
  1 AS `treatment_done`,
  1 AS `reason_for_visit`,
  1 AS `referred_by`,
  1 AS `good_health`,
  1 AS `medical_condition`,
  1 AS `medical_condition_detail`,
  1 AS `serious_illness`,
  1 AS `serious_illness_detail`,
  1 AS `hospitalized`,
  1 AS `hospitalized_detail`,
  1 AS `medication`,
  1 AS `medication_detail`,
  1 AS `smoke`,
  1 AS `alcohol`,
  1 AS `drugs`,
  1 AS `allergy`,
  1 AS `allergy_detail`,
  1 AS `pregnant`,
  1 AS `nursing`,
  1 AS `birth_control`,
  1 AS `blood_type`,
  1 AS `blood_pressure`,
  1 AS `patient_conditions`,
  1 AS `consent_name`,
  1 AS `consent_for`,
  1 AS `consent_date`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_schedule_utilization`
--

DROP TABLE IF EXISTS `vw_schedule_utilization`;
/*!50001 DROP VIEW IF EXISTS `vw_schedule_utilization`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_schedule_utilization` AS SELECT
 1 AS `schedule_id`,
  1 AS `clinic_id`,
  1 AS `clinic_name`,
  1 AS `sched_date`,
  1 AS `capacity`,
  1 AS `booked`,
  1 AS `completed`,
  1 AS `cancelled`,
  1 AS `available_slots`,
  1 AS `utilization_rate` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'db-oaprms-system'
--

--
-- Dumping routines for database 'db-oaprms-system'
--

--
-- Final view structure for view `vw_appointment_latest_status_change`
--

/*!50001 DROP VIEW IF EXISTS `vw_appointment_latest_status_change`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_appointment_latest_status_change` AS select `current_log`.`entity_id` AS `appointment_id`,`current_log`.`audit_log_id` AS `audit_log_id`,`current_log`.`description` AS `status_change_description`,`current_log`.`old_values` AS `old_values`,`current_log`.`new_values` AS `new_values`,`current_log`.`performed_by_user_id` AS `performed_by_user_id`,`current_log`.`performed_by_name` AS `status_changed_by`,`current_log`.`performed_by_role` AS `status_changed_by_role`,`current_log`.`source` AS `source`,`current_log`.`performed_at` AS `status_changed_at` from (`audit_logs` `current_log` left join `audit_logs` `newer_log` on(`newer_log`.`entity_type` = `current_log`.`entity_type` and `newer_log`.`entity_id` = `current_log`.`entity_id` and `newer_log`.`action` = `current_log`.`action` and `newer_log`.`audit_log_id` > `current_log`.`audit_log_id`)) where `current_log`.`entity_type` = 'appointment' and `current_log`.`action` = 'status_changed' and `newer_log`.`audit_log_id` is null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_appointment_overview`
--

/*!50001 DROP VIEW IF EXISTS `vw_appointment_overview`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_appointment_overview` AS select `a`.`appointment_id` AS `appointment_id`,`a`.`patient_id` AS `patient_id`,`a`.`schedule_id` AS `schedule_id`,`a`.`clinic_id` AS `clinic_id`,`a`.`date` AS `date`,`a`.`status` AS `status`,`a`.`deposit_required` AS `deposit_required`,`a`.`payment_deadline_at` AS `payment_deadline_at`,`a`.`reviewed_at` AS `reviewed_at`,`a`.`accepted_for_payment_at` AS `accepted_for_payment_at`,`a`.`rejected_at` AS `rejected_at`,`a`.`rejection_reason` AS `rejection_reason`,`a`.`appointment_code` AS `appointment_code`,`a`.`code_generated_at` AS `code_generated_at`,`a`.`confirmed_at` AS `confirmed_at`,`a`.`treatment_started_at` AS `treatment_started_at`,`a`.`completed_at` AS `completed_at`,`a`.`cancelled_at` AS `cancelled_at`,`a`.`cancellation_reason` AS `cancellation_reason`,`a`.`created_at` AS `created_at`,`p`.`firstname` AS `firstname`,`p`.`middlename` AS `middlename`,`p`.`lastname` AS `lastname`,`p`.`suffix` AS `suffix`,concat(`p`.`lastname`,', ',`p`.`firstname`,case when nullif(trim(`p`.`middlename`),'') is null then '' else concat(' ',left(trim(`p`.`middlename`),1),'.') end,case when nullif(trim(`p`.`suffix`),'') is null then '' else concat(' ',trim(`p`.`suffix`)) end) AS `patient_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`p`.`phone_number` AS `phone_number`,`p`.`email` AS `email`,`p`.`profile_status` AS `profile_status`,`p`.`profile_completed_at` AS `profile_completed_at`,`c`.`clinic_name` AS `clinic_name`,(select group_concat(`s`.`service_name` order by `s`.`display_order` ASC,`s`.`service_name` ASC separator ', ') from (`appointment_services` `aps` join `services` `s` on(`s`.`service_id` = `aps`.`service_id`)) where `aps`.`appointment_id` = `a`.`appointment_id`) AS `service_name` from ((`appointments` `a` join `patients` `p` on(`p`.`patient_id` = `a`.`patient_id`)) left join `clinics` `c` on(`c`.`clinic_id` = `a`.`clinic_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_appointment_payment_summary`
--

/*!50001 DROP VIEW IF EXISTS `vw_appointment_payment_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_appointment_payment_summary` AS select `a`.`appointment_id` AS `appointment_id`,`d`.`deposit_id` AS `deposit_id`,`d`.`amount` AS `deposit_amount`,case when `d`.`status` in ('Verified','Transferred') then coalesce(`d`.`amount`,0) else 0 end AS `verified_deposit`,`d`.`gcash_reference` AS `gcash_reference`,`d`.`receipt_path` AS `receipt_path`,`d`.`receipt_mime` AS `receipt_mime`,`d`.`status` AS `deposit_status`,`d`.`submitted_at` AS `submitted_at`,`d`.`verified_at` AS `verified_at`,`d`.`rejection_reason` AS `payment_rejection_reason`,`d`.`resubmission_deadline_at` AS `resubmission_deadline_at`,`d`.`refund_reason` AS `refund_reason`,`d`.`refunded_at` AS `refunded_at`,case when `d`.`receipt_path` is null then 0 else 1 end AS `has_receipt`,`verifier`.`email` AS `payment_verified_by`,`verifier`.`user_role` AS `payment_verified_by_role`,coalesce(nullif(trim(concat_ws(' ',`verifier_staff`.`firstname`,`verifier_staff`.`middlename`,`verifier_staff`.`lastname`)),''),`verifier`.`email`) AS `payment_verified_by_name`,`b`.`billing_id` AS `billing_id`,`b`.`actual_service_amount` AS `actual_service_amount`,`b`.`deposit_applied` AS `deposit_applied`,`b`.`remaining_balance` AS `remaining_balance`,`b`.`cash_received` AS `cash_received`,coalesce(`b`.`payment_status`,'Unpaid') AS `payment_status`,`b`.`recorded_at` AS `billing_recorded_at`,`b`.`paid_at` AS `paid_at`,`b`.`notes` AS `billing_notes`,coalesce(nullif(trim(concat_ws(' ',`recorder_staff`.`firstname`,`recorder_staff`.`middlename`,`recorder_staff`.`lastname`)),''),`recorder`.`email`,'Staff') AS `billing_recorded_by` from ((((((`appointments` `a` left join `appointment_deposits` `d` on(`d`.`appointment_id` = `a`.`appointment_id`)) left join `users` `verifier` on(`verifier`.`id` = `d`.`verified_by_user_id`)) left join `staffs` `verifier_staff` on(`verifier_staff`.`user_id` = `verifier`.`id`)) left join `appointment_billings` `b` on(`b`.`appointment_id` = `a`.`appointment_id`)) left join `users` `recorder` on(`recorder`.`id` = `b`.`recorded_by_user_id`)) left join `staffs` `recorder_staff` on(`recorder_staff`.`user_id` = `recorder`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_patient_information`
--

/*!50001 DROP VIEW IF EXISTS `vw_patient_information`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_patient_information` AS select `p`.`patient_id` AS `patient_id`,`p`.`user_id` AS `user_id`,`p`.`firstname` AS `firstname`,`p`.`middlename` AS `middlename`,`p`.`lastname` AS `lastname`,concat(`p`.`firstname`,' ',coalesce(`p`.`middlename`,''),' ',`p`.`lastname`) AS `full_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`p`.`birthdate` AS `birthdate`,`p`.`civil_status` AS `civil_status`,`p`.`phone_number` AS `phone_number`,`p`.`email` AS `email`,`p`.`home_address` AS `home_address`,`p`.`work_address` AS `work_address`,`p`.`occupation` AS `occupation`,`p`.`office_contact` AS `office_contact`,`p`.`fb_account` AS `fb_account`,`p`.`guardian_name` AS `guardian_name`,`p`.`guardian_contact` AS `guardian_contact`,`p`.`physician_name` AS `physician_name`,`p`.`physician_contact` AS `physician_contact`,`p`.`physician_address` AS `physician_address`,`dh`.`previous_dentist` AS `previous_dentist`,`dh`.`last_dental_visit` AS `last_dental_visit`,`dh`.`treatment_done` AS `treatment_done`,`dh`.`reason_for_visit` AS `reason_for_visit`,`dh`.`referred_by` AS `referred_by`,`mh`.`good_health` AS `good_health`,`mh`.`medical_condition` AS `medical_condition`,`mh`.`medical_condition_detail` AS `medical_condition_detail`,`mh`.`serious_illness` AS `serious_illness`,`mh`.`serious_illness_detail` AS `serious_illness_detail`,`mh`.`hospitalized` AS `hospitalized`,`mh`.`hospitalized_detail` AS `hospitalized_detail`,`mh`.`medication` AS `medication`,`mh`.`medication_detail` AS `medication_detail`,`mh`.`smoke` AS `smoke`,`mh`.`alcohol` AS `alcohol`,`mh`.`drugs` AS `drugs`,`mh`.`allergy` AS `allergy`,`mh`.`allergy_detail` AS `allergy_detail`,`mh`.`pregnant` AS `pregnant`,`mh`.`nursing` AS `nursing`,`mh`.`birth_control` AS `birth_control`,`mh`.`blood_type` AS `blood_type`,`mh`.`blood_pressure` AS `blood_pressure`,group_concat(distinct `pc`.`condition` order by `pc`.`condition` ASC separator ', ') AS `patient_conditions`,`c`.`consent_name` AS `consent_name`,`c`.`consent_for` AS `consent_for`,`c`.`consent_date` AS `consent_date`,`p`.`created_at` AS `created_at` from ((((`patients` `p` left join `patient_dental_history` `dh` on(`p`.`patient_id` = `dh`.`patient_id`)) left join `patient_medical_history` `mh` on(`p`.`patient_id` = `mh`.`patient_id`)) left join `patient_consent` `c` on(`p`.`patient_id` = `c`.`patient_id`)) left join `patient_conditions` `pc` on(`p`.`patient_id` = `pc`.`patient_id`)) group by `p`.`patient_id`,`p`.`user_id`,`p`.`firstname`,`p`.`middlename`,`p`.`lastname`,`p`.`age`,`p`.`gender`,`p`.`birthdate`,`p`.`civil_status`,`p`.`phone_number`,`p`.`email`,`p`.`home_address`,`p`.`work_address`,`p`.`occupation`,`p`.`office_contact`,`p`.`fb_account`,`p`.`guardian_name`,`p`.`guardian_contact`,`p`.`physician_name`,`p`.`physician_contact`,`p`.`physician_address`,`dh`.`previous_dentist`,`dh`.`last_dental_visit`,`dh`.`treatment_done`,`dh`.`reason_for_visit`,`dh`.`referred_by`,`mh`.`good_health`,`mh`.`medical_condition`,`mh`.`medical_condition_detail`,`mh`.`serious_illness`,`mh`.`serious_illness_detail`,`mh`.`hospitalized`,`mh`.`hospitalized_detail`,`mh`.`medication`,`mh`.`medication_detail`,`mh`.`smoke`,`mh`.`alcohol`,`mh`.`drugs`,`mh`.`allergy`,`mh`.`allergy_detail`,`mh`.`pregnant`,`mh`.`nursing`,`mh`.`birth_control`,`mh`.`blood_type`,`mh`.`blood_pressure`,`c`.`consent_name`,`c`.`consent_for`,`c`.`consent_date`,`p`.`created_at` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_schedule_utilization`
--

/*!50001 DROP VIEW IF EXISTS `vw_schedule_utilization`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_schedule_utilization` AS select `s`.`schedule_id` AS `schedule_id`,`s`.`clinic_id` AS `clinic_id`,`c`.`clinic_name` AS `clinic_name`,`s`.`sched_date` AS `sched_date`,`s`.`max_appointments` AS `capacity`,count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) AS `booked`,count(case when `a`.`status` = 'Completed' then `a`.`appointment_id` end) AS `completed`,count(case when `a`.`status` in ('Cancelled','Rejected') then `a`.`appointment_id` end) AS `cancelled`,greatest(`s`.`max_appointments` - count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end),0) AS `available_slots`,case when `s`.`max_appointments` = 0 then 0 else round(count(case when `a`.`status` in ('Pending Review','Awaiting Deposit','Payment Under Review','Confirmed','Checked In','In Progress','Completed') then `a`.`appointment_id` end) * 100 / `s`.`max_appointments`,1) end AS `utilization_rate` from ((`schedules` `s` join `clinics` `c` on(`c`.`clinic_id` = `s`.`clinic_id`)) left join `appointments` `a` on(`a`.`schedule_id` = `s`.`schedule_id`)) group by `s`.`schedule_id`,`s`.`clinic_id`,`c`.`clinic_name`,`s`.`sched_date`,`s`.`max_appointments` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-14 18:33:28
