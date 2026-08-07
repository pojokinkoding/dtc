-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: dtc
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
-- Current Database: `dtc`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `dtc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `dtc`;

--
-- Table structure for table `dtc_app_settings`
--

DROP TABLE IF EXISTS `dtc_app_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_app_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_app_settings`
--

LOCK TABLES `dtc_app_settings` WRITE;
/*!40000 ALTER TABLE `dtc_app_settings` DISABLE KEYS */;
INSERT INTO `dtc_app_settings` VALUES (1,'time_matrix_labels','[\"08:00\",\"09:40\",\"12:30\",\"14:30\",\"18:30\",\"19:30\",\"20:30\",\"21:30\",\"22:30\",\"23:30\"]','2026-07-09 06:29:38');
/*!40000 ALTER TABLE `dtc_app_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dtc_inspection_sessions`
--

DROP TABLE IF EXISTS `dtc_inspection_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_inspection_sessions` (
  `session_id` int(11) NOT NULL AUTO_INCREMENT,
  `parameter_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `shift_name` varchar(20) DEFAULT NULL,
  `operator_id` int(11) NOT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `max_value` decimal(10,3) DEFAULT NULL,
  `min_value` decimal(10,3) DEFAULT NULL,
  `x_bar` decimal(10,4) DEFAULT NULL,
  `range_value` decimal(10,4) DEFAULT NULL,
  `std_dev` decimal(10,6) DEFAULT NULL,
  `zst_value` decimal(10,3) DEFAULT NULL,
  `zlt_value` decimal(10,3) DEFAULT NULL,
  `is_closed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `fk_dtc_session_operator` (`operator_id`),
  KEY `idx_dtc_insp_date` (`inspection_date`),
  KEY `idx_dtc_param_session` (`parameter_id`),
  CONSTRAINT `fk_dtc_session_operator` FOREIGN KEY (`operator_id`) REFERENCES `dtc_users` (`user_id`),
  CONSTRAINT `fk_dtc_session_param` FOREIGN KEY (`parameter_id`) REFERENCES `dtc_master_parameters` (`parameter_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_inspection_sessions`
--

LOCK TABLES `dtc_inspection_sessions` WRITE;
/*!40000 ALTER TABLE `dtc_inspection_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `dtc_inspection_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dtc_master_dtc_specs`
--

DROP TABLE IF EXISTS `dtc_master_dtc_specs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_master_dtc_specs` (
  `spec_id` int(11) NOT NULL AUTO_INCREMENT,
  `model_name` varchar(100) NOT NULL,
  `dtc_name` varchar(100) NOT NULL,
  `lsl` double DEFAULT NULL,
  `usl` double DEFAULT NULL,
  `uom` varchar(20) DEFAULT NULL,
  `target_value` double DEFAULT NULL,
  `section_name` varchar(50) NOT NULL,
  `line_name` varchar(50) NOT NULL,
  `process_name` varchar(100) NOT NULL,
  `measuring_item` varchar(100) NOT NULL,
  `target_zst` double DEFAULT NULL,
  `target_zlt` double DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`spec_id`)
) ENGINE=InnoDB AUTO_INCREMENT=608 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_master_dtc_specs`
--

LOCK TABLES `dtc_master_dtc_specs` WRITE;
/*!40000 ALTER TABLE `dtc_master_dtc_specs` DISABLE KEYS */;
/*!40000 ALTER TABLE `dtc_master_dtc_specs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dtc_master_parameters`
--

DROP TABLE IF EXISTS `dtc_master_parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_master_parameters` (
  `parameter_id` int(11) NOT NULL AUTO_INCREMENT,
  `spec_id` int(11) NOT NULL,
  `target_month` varchar(7) NOT NULL,
  `dtc_name` varchar(100) DEFAULT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `line_name` varchar(50) DEFAULT NULL,
  `process_name` varchar(100) DEFAULT NULL,
  `measuring_item` varchar(100) DEFAULT NULL,
  `target_zst` decimal(10,3) DEFAULT NULL,
  `target_zlt` decimal(10,3) DEFAULT NULL,
  `reference_image` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`parameter_id`),
  KEY `fk_dtc_param_spec` (`spec_id`),
  CONSTRAINT `fk_dtc_param_spec` FOREIGN KEY (`spec_id`) REFERENCES `dtc_master_dtc_specs` (`spec_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_master_parameters`
--

LOCK TABLES `dtc_master_parameters` WRITE;
/*!40000 ALTER TABLE `dtc_master_parameters` DISABLE KEYS */;
/*!40000 ALTER TABLE `dtc_master_parameters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dtc_measurements`
--

DROP TABLE IF EXISTS `dtc_measurements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_measurements` (
  `measurement_id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` int(11) NOT NULL,
  `sample_sequence` int(11) NOT NULL,
  `sample_label` varchar(100) DEFAULT NULL,
  `sample_value` varchar(20) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified_by` int(11) DEFAULT NULL,
  `modified_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`measurement_id`),
  KEY `fk_dtc_meas_creator` (`created_by`),
  KEY `fk_dtc_meas_modifier` (`modified_by`),
  KEY `idx_dtc_sess_meas` (`session_id`),
  CONSTRAINT `fk_dtc_meas_creator` FOREIGN KEY (`created_by`) REFERENCES `dtc_users` (`user_id`),
  CONSTRAINT `fk_dtc_meas_modifier` FOREIGN KEY (`modified_by`) REFERENCES `dtc_users` (`user_id`),
  CONSTRAINT `fk_dtc_meas_session` FOREIGN KEY (`session_id`) REFERENCES `dtc_inspection_sessions` (`session_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_measurements`
--

LOCK TABLES `dtc_measurements` WRITE;
/*!40000 ALTER TABLE `dtc_measurements` DISABLE KEYS */;
/*!40000 ALTER TABLE `dtc_measurements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dtc_users`
--

DROP TABLE IF EXISTS `dtc_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dtc_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dtc_users`
--

LOCK TABLES `dtc_users` WRITE;
/*!40000 ALTER TABLE `dtc_users` DISABLE KEYS */;
INSERT INTO `dtc_users` VALUES (2,'admin','$2y$10$aPVs3cw4IDxItGmHi0CxDudYMzx6qcc43ThiE51r5FN2r0EovukxK','Administrator','Admin','2026-07-09 06:18:41','2026-07-09 07:34:10');
/*!40000 ALTER TABLE `dtc_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'dtc'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-07-16 13:45:36
