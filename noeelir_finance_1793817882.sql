/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.24-MariaDB, for linux-systemd (x86_64)
--
-- Host: localhost    Database: noeelir_finance
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
-- Table structure for table `bank_settlement_cycle_items`
--

DROP TABLE IF EXISTS `bank_settlement_cycle_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_settlement_cycle_items` (
  `cycle_id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `applicant_name` varchar(255) NOT NULL DEFAULT '',
  `national_code` varchar(20) NOT NULL DEFAULT '',
  `loan_amount` decimal(20,2) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`cycle_id`,`request_id`),
  UNIQUE KEY `uq_bank_cycle_request` (`request_id`),
  KEY `idx_bank_cycle_items_cycle` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_settlement_cycle_items`
--

LOCK TABLES `bank_settlement_cycle_items` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `bank_settlement_cycles`
--

DROP TABLE IF EXISTS `bank_settlement_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_settlement_cycles` (
  `id` char(26) NOT NULL,
  `cycle_no` varchar(42) NOT NULL,
  `bank_user_id` char(26) NOT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `settlement_date` date NOT NULL,
  `notes` varchar(3000) DEFAULT NULL,
  `item_count` int(10) unsigned NOT NULL,
  `total_loan_amount` decimal(20,2) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `idempotency_key` varchar(128) NOT NULL,
  `projection_status` varchar(24) NOT NULL DEFAULT 'pending',
  `projected_at` datetime(6) DEFAULT NULL,
  `projection_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_cycles_no` (`cycle_no`),
  UNIQUE KEY `uq_bank_cycles_idempotency` (`created_by_user_id`,`idempotency_key`),
  KEY `idx_bank_cycles_owner` (`bank_user_id`,`settlement_date`,`created_at`),
  KEY `idx_bank_cycles_projection` (`projection_status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_settlement_cycles`
--

LOCK TABLES `bank_settlement_cycles` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `bank_settlement_history`
--

DROP TABLE IF EXISTS `bank_settlement_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_settlement_history` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL,
  `settled_at` datetime(6) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_bank_settlement_history_request` (`request_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_settlement_history`
--

LOCK TABLES `bank_settlement_history` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `bank_settlements`
--

DROP TABLE IF EXISTS `bank_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_settlements` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'not_recorded',
  `settled_at` datetime(6) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bank_settlement_request` (`request_id`),
  KEY `idx_bank_settlement_status` (`status`,`settled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_settlements`
--

LOCK TABLES `bank_settlements` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_events`
--

DROP TABLE IF EXISTS `finance_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_events` (
  `id` char(26) NOT NULL,
  `event_type` varchar(96) NOT NULL,
  `aggregate_type` varchar(64) NOT NULL,
  `aggregate_id` char(26) DEFAULT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `result` varchar(32) NOT NULL DEFAULT 'allowed',
  `request_id` varchar(128) DEFAULT NULL,
  `caller_service` varchar(32) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_fin_events_aggregate` (`aggregate_type`,`aggregate_id`,`created_at`),
  KEY `idx_fin_events_actor` (`actor_user_id`,`created_at`),
  KEY `idx_fin_events_request` (`request_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_events`
--

LOCK TABLES `finance_events` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_greenpay_otp_intents`
--

DROP TABLE IF EXISTS `finance_greenpay_otp_intents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_greenpay_otp_intents` (
  `id` char(26) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `idempotency_key` varchar(120) NOT NULL,
  `request_fingerprint` char(64) NOT NULL,
  `pocket_hash` char(64) NOT NULL,
  `amount_rial` bigint(20) unsigned NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `provider_code` varchar(64) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `finished_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fin_greenpay_otp_actor_key` (`actor_user_id`,`idempotency_key`),
  KEY `idx_fin_greenpay_otp_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_greenpay_otp_intents`
--

LOCK TABLES `finance_greenpay_otp_intents` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_greenpay_transfer_events`
--

DROP TABLE IF EXISTS `finance_greenpay_transfer_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_greenpay_transfer_events` (
  `id` char(26) NOT NULL,
  `transfer_id` char(26) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `action` varchar(48) NOT NULL,
  `previous_status` varchar(32) DEFAULT NULL,
  `new_status` varchar(32) NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_greenpay_transfer_events` (`transfer_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_greenpay_transfer_events`
--

LOCK TABLES `finance_greenpay_transfer_events` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_greenpay_transfers`
--

DROP TABLE IF EXISTS `finance_greenpay_transfers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_greenpay_transfers` (
  `id` char(26) NOT NULL,
  `cycle_id` char(26) NOT NULL,
  `created_by` char(26) NOT NULL,
  `approved_by` char(26) DEFAULT NULL,
  `reviewed_by` char(26) DEFAULT NULL,
  `idempotency_key` varchar(128) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `external_reference` varchar(48) NOT NULL,
  `from_pocket_encrypted` text NOT NULL,
  `to_pocket_encrypted` text NOT NULL,
  `from_pocket_mask` varchar(8) NOT NULL,
  `to_pocket_mask` varchar(8) NOT NULL,
  `amount_rial` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'prepared',
  `reason` varchar(1000) NOT NULL,
  `decision_note` varchar(1000) DEFAULT NULL,
  `provider_error` varchar(80) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `approved_at` datetime(6) DEFAULT NULL,
  `dispatch_started_at` datetime(6) DEFAULT NULL,
  `provider_accepted_at` datetime(6) DEFAULT NULL,
  `reviewed_at` datetime(6) DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_greenpay_cycle` (`cycle_id`),
  UNIQUE KEY `uq_greenpay_idempotency` (`created_by`,`idempotency_key`),
  UNIQUE KEY `uq_greenpay_reference` (`external_reference`),
  KEY `idx_greenpay_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_greenpay_transfers`
--

LOCK TABLES `finance_greenpay_transfers` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_idempotency`
--

DROP TABLE IF EXISTS `finance_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_idempotency` (
  `idempotency_key` varchar(190) NOT NULL,
  `operation` varchar(96) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `expires_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`idempotency_key`,`operation`),
  KEY `idx_fin_idempotency_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_idempotency`
--

LOCK TABLES `finance_idempotency` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_reconciliation_items`
--

DROP TABLE IF EXISTS `finance_reconciliation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_reconciliation_items` (
  `id` char(26) NOT NULL,
  `request_id` char(26) DEFAULT NULL,
  `cycle_id` char(26) DEFAULT NULL,
  `discrepancy_type` varchar(64) NOT NULL,
  `severity` varchar(16) NOT NULL DEFAULT 'warning',
  `expected_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`expected_json`)),
  `actual_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`actual_json`)),
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `resolution_note` varchar(1000) DEFAULT NULL,
  `resolved_by_user_id` char(26) DEFAULT NULL,
  `resolved_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_fin_recon_status` (`status`,`severity`,`created_at`),
  KEY `idx_fin_recon_request` (`request_id`),
  KEY `idx_fin_recon_cycle` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_reconciliation_items`
--

LOCK TABLES `finance_reconciliation_items` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `finance_schema_migrations`
--

DROP TABLE IF EXISTS `finance_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_schema_migrations` (
  `version` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_schema_migrations`
--

LOCK TABLES `finance_schema_migrations` WRITE;
INSERT INTO `finance_schema_migrations` VALUES ('202610010001','create_finance_core','2026-10-01 22:48:32.414211'),('202610010002','create_cycles_receipts','2026-10-01 22:48:32.428804'),('202610010003','create_reconciliation_audit','2026-10-01 22:48:32.444493'),('202610210004','greenpay_otp_intents','2026-10-22 11:58:26.278703'),('202610220005','greenpay_transfer_intents','2026-10-22 22:07:03.930180'),('202610230006','bank_settlement_cycles','2026-10-23 19:34:31.128409');
UNLOCK TABLES;

--
-- Table structure for table `financial_receipts`
--

DROP TABLE IF EXISTS `financial_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_receipts` (
  `id` char(26) NOT NULL,
  `request_id` char(26) DEFAULT NULL,
  `cycle_id` char(26) DEFAULT NULL,
  `receipt_type` varchar(32) NOT NULL DEFAULT 'transfer',
  `amount` decimal(20,2) DEFAULT NULL,
  `document_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `external_reference` varchar(190) DEFAULT NULL,
  `submitted_by_user_id` char(26) NOT NULL,
  `reviewed_by_user_id` char(26) DEFAULT NULL,
  `review_note` varchar(1000) DEFAULT NULL,
  `submitted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `reviewed_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_financial_receipts_request` (`request_id`,`status`,`submitted_at`),
  KEY `idx_financial_receipts_cycle` (`cycle_id`,`status`,`submitted_at`),
  KEY `idx_financial_receipts_document` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financial_receipts`
--

LOCK TABLES `financial_receipts` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `financier_payment_history`
--

DROP TABLE IF EXISTS `financier_payment_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financier_payment_history` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_financier_payment_history_request` (`request_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financier_payment_history`
--

LOCK TABLES `financier_payment_history` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `financier_payments`
--

DROP TABLE IF EXISTS `financier_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financier_payments` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'unreviewed',
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financier_payment_request` (`request_id`),
  KEY `idx_financier_payment_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financier_payments`
--

LOCK TABLES `financier_payments` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `merchant_settlement_history`
--

DROP TABLE IF EXISTS `merchant_settlement_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_settlement_history` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `merchant_id` char(26) DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `settlement_date` date DEFAULT NULL,
  `cycle_id` char(26) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_merchant_settlement_history_request` (`request_id`,`created_at`),
  KEY `idx_merchant_settlement_history_cycle` (`cycle_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_settlement_history`
--

LOCK TABLES `merchant_settlement_history` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `merchant_settlements`
--

DROP TABLE IF EXISTS `merchant_settlements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_settlements` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `merchant_id` char(26) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'not_started',
  `settlement_date` date DEFAULT NULL,
  `cycle_id` char(26) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'finance',
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchant_settlement_request` (`request_id`),
  KEY `idx_merchant_settlement_merchant` (`merchant_id`,`status`,`settlement_date`),
  KEY `idx_merchant_settlement_cycle` (`cycle_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_settlements`
--

LOCK TABLES `merchant_settlements` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `settlement_cycle_items`
--

DROP TABLE IF EXISTS `settlement_cycle_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settlement_cycle_items` (
  `cycle_id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `settlement_amount` decimal(20,2) NOT NULL,
  `leasing_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `merchant_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`cycle_id`,`request_id`),
  UNIQUE KEY `uq_settlement_item_request` (`request_id`),
  KEY `idx_settlement_item_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_cycle_items`
--

LOCK TABLES `settlement_cycle_items` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `settlement_cycles`
--

DROP TABLE IF EXISTS `settlement_cycles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settlement_cycles` (
  `id` char(26) NOT NULL,
  `cycle_no` varchar(40) NOT NULL,
  `merchant_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'open',
  `settlement_date` date DEFAULT NULL,
  `total_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `leasing_total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `merchant_total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `closed_by_user_id` char(26) DEFAULT NULL,
  `closed_at` datetime(6) DEFAULT NULL,
  `reopened_by_user_id` char(26) DEFAULT NULL,
  `reopened_at` datetime(6) DEFAULT NULL,
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_cycles_no` (`cycle_no`),
  KEY `idx_settlement_cycles_merchant` (`merchant_id`,`status`,`settlement_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_cycles`
--

LOCK TABLES `settlement_cycles` WRITE;
UNLOCK TABLES;

--
-- Dumping events for database 'noeelir_finance'
--

--
-- Dumping routines for database 'noeelir_finance'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 22:14:42
