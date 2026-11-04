/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.24-MariaDB, for linux-systemd (x86_64)
--
-- Host: localhost    Database: noeelir_integrations
-- ------------------------------------------------------
-- Server version	10.6.24-MariaDB

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
-- Table structure for table `integration_operations`
--

DROP TABLE IF EXISTS `integration_operations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_operations` (
  `id` char(32) NOT NULL,
  `caller` varchar(32) NOT NULL,
  `operation` varchar(72) NOT NULL,
  `idempotency_key` varchar(120) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `target_hash` char(64) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_json`)),
  `error_code` varchar(80) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `finished_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_operation_idempotent` (`caller`,`operation`,`idempotency_key`),
  KEY `idx_operation_rate` (`caller`,`operation`,`target_hash`,`created_at`),
  KEY `idx_operation_status` (`status`,`created_at`),
  CONSTRAINT `chk_operation_state` CHECK (`status` in ('pending','succeeded','failed','uncertain'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integration_operations`
--

LOCK TABLES `integration_operations` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `integration_schema_migrations`
--

DROP TABLE IF EXISTS `integration_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_schema_migrations` (
  `name` varchar(120) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integration_schema_migrations`
--

LOCK TABLES `integration_schema_migrations` WRITE;
INSERT INTO `integration_schema_migrations` VALUES ('202610210001_integrations_core.sql','2026-10-21 15:55:18.149092');
UNLOCK TABLES;

--
-- Table structure for table `integration_security_events`
--

DROP TABLE IF EXISTS `integration_security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_security_events` (
  `id` char(32) NOT NULL,
  `caller` varchar(32) NOT NULL,
  `event_code` varchar(60) NOT NULL,
  `request_id` varchar(128) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_security_recent` (`caller`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integration_security_events`
--

LOCK TABLES `integration_security_events` WRITE;
UNLOCK TABLES;

--
-- Dumping events for database 'noeelir_integrations'
--

--
-- Dumping routines for database 'noeelir_integrations'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 22:14:45
