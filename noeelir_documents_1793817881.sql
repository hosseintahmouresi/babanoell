/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.24-MariaDB, for linux-systemd (x86_64)
--
-- Host: localhost    Database: noeelir_documents
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
-- Table structure for table `contract_pdf_artifacts`
--

DROP TABLE IF EXISTS `contract_pdf_artifacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_pdf_artifacts` (
  `contract_id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `source_rendered_hash` char(64) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`contract_id`),
  UNIQUE KEY `uq_contract_pdf_document` (`document_id`),
  CONSTRAINT `fk_contract_pdf_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_pdf_artifacts`
--

LOCK TABLES `contract_pdf_artifacts` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `document_access_tokens`
--

DROP TABLE IF EXISTS `document_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_access_tokens` (
  `id` char(26) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `document_id` char(26) NOT NULL,
  `version_id` char(26) NOT NULL,
  `issued_to_user_id` char(26) NOT NULL,
  `purpose` varchar(24) NOT NULL DEFAULT 'download',
  `expires_at` datetime(6) NOT NULL,
  `consumed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_access_token_hash` (`token_hash`),
  KEY `idx_document_access_expiry` (`expires_at`,`consumed_at`),
  KEY `fk_document_access_document` (`document_id`),
  KEY `fk_document_access_version` (`version_id`),
  CONSTRAINT `fk_document_access_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `fk_document_access_version` FOREIGN KEY (`version_id`) REFERENCES `document_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_access_tokens`
--

LOCK TABLES `document_access_tokens` WRITE;
INSERT INTO `document_access_tokens` VALUES ('31a139128d7522738e9912a980','806c2b0e6fb4b22dabd01db073c374329cb5af93971c89fdae14a9175643e648','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','87dccf0ef6d17c0b175921d5ed','download','2026-10-28 14:26:30.407988',NULL,'2026-10-28 17:54:30.417532'),('44e0b4e2ddbc52e17f58e9ac4c','8527a1468207a59e28ced4503d8bbc1cf62366c81069ef72039d6fc296196537','fa44aa67b9a660662d4370398e','3b5c3c2a6bc97be6df086e1534','87dccf0ef6d17c0b175921d5ed','download','2026-09-29 08:00:59.484473',NULL,'2026-09-29 11:28:59.486829'),('977cb1425c7a742b6e43258bc8','2df56287ba455119b7450a913269ee1b3a302194caf348c2443865309da96cfc','5ccd2622a79bbf53720c22e672','b3b7e338380a5159dcbd4d3ccc','87dccf0ef6d17c0b175921d5ed','download','2026-10-28 14:27:14.225030',NULL,'2026-10-28 17:55:14.225532');
UNLOCK TABLES;

--
-- Table structure for table `document_audit_logs`
--

DROP TABLE IF EXISTS `document_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_audit_logs` (
  `id` char(26) NOT NULL,
  `document_id` char(26) DEFAULT NULL,
  `version_id` char(26) DEFAULT NULL,
  `entity_type` varchar(64) DEFAULT NULL,
  `entity_id` char(26) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `result` varchar(24) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_document_audit_document` (`document_id`,`created_at`),
  KEY `idx_document_audit_entity` (`entity_type`,`entity_id`,`created_at`),
  KEY `idx_document_audit_actor` (`actor_user_id`,`created_at`),
  KEY `idx_document_audit_event` (`event_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_audit_logs`
--

LOCK TABLES `document_audit_logs` WRITE;
INSERT INTO `document_audit_logs` VALUES ('2bc9d3eb6cc51c48174f04bd1b','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139',NULL,NULL,'document.access_token_issued','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"request_id\":\"panel_4fbd36ba3b3863627fcd26fc\",\"expires_in\":120,\"purpose\":\"download\"}','185.223.160.59','BabanoellPanel/2.5.0','2026-10-28 17:54:30.440169'),('3537ab59af32b4a02084310c00','e1c42453d13a0762a0f5858389','d1a877b1bc4fe62aa1c249fea6','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:09:09.653525'),('3f4b2a37203203b227d9ec231c','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','loan_request','9b9243d8f4aa2bca09f4154605','document.reviewed','0f1652c3528f92bc3f95edd9ab','allowed',NULL,'{\"request_id\":\"panel_a7aefc68e1d3f05c289a4ff0\",\"status\":\"approved\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:57:48.767935'),('4d47bbfe1e1483b9f6949c70ab','aa230a2c8929a233a2fc776ae8','7b62f1626f31cf378c04a00107','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:14:18.801201'),('5c3146e6d3609227c36c2bf434','6df1b725fc145c51f2d86e4783','1ff8a15bf8be59c43227098fa3','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:13:53.689235'),('65d262be2c27916f08887743c5','fa44aa67b9a660662d4370398e','3b5c3c2a6bc97be6df086e1534','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:15:53.657583'),('6d6903b4ac52ee1bfd2a7fa8bb','3a7ddaaf0ce8236c3592331c3e','a19bba74ee005354f829341975','cooperation_request','681aa88d573abaabd0fe1bc6f9','document.uploaded','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"request_id\":\"panel_6aa4ff59f48df81556a5955e\",\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:15:31.337224'),('6e27a54b884cff8bf1fa76c52c','61ef935858e3f1dfc6513d4fa3','08ca3cdce455df58420e7c4d32','cooperation_request','681aa88d573abaabd0fe1bc6f9','document.uploaded','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"request_id\":\"panel_7830bba10f91129cb9f723b3\",\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:15:45.984303'),('851001468536831a9cd8d972ef','fa44aa67b9a660662d4370398e','3b5c3c2a6bc97be6df086e1534',NULL,NULL,'document.access_token_issued','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"expires_in\":120,\"purpose\":\"download\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-29 11:28:59.488740'),('985797369e65e1dc9b07295d61','14733e1601e519533190e37f3e','60f9b5422416d822c7536362fc','cooperation_request','681aa88d573abaabd0fe1bc6f9','document.uploaded','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"request_id\":\"panel_5adf90b35a0f6ddf4ecd1de8\",\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:15:23.818025'),('c2fbb10fcc1bb551ba3b80d7f1','e4e567200eadcaefb480220148','4cc907123d142b53561590e837','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:15:07.107094'),('cf5905cc64c8d6ec169e3874bb','fa44aa67b9a660662d4370398e','3b5c3c2a6bc97be6df086e1534','loan_request','9b9243d8f4aa2bca09f4154605','document.reviewed','0f1652c3528f92bc3f95edd9ab','allowed','اشتباه است','{\"request_id\":\"panel_d15ae6d1211aa2dd2433f7b1\",\"status\":\"rejected\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:57:22.905531'),('d395fa943c872350201553d643','8a2d9f78b609eb2843b588918a','18aba3d5069a851e1f5191ce12','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:15:19.156900'),('dad0830875ff8759ea2b80ab0a','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:15:28.210677'),('dba12ccfbfef91ada37c1ef2c1','089241c259ac623de1432e4ecf','2a8bbd6c4d8ba7140bd239cec7','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":57665,\"checksum_sha256\":\"1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:08:11.644981'),('dfd82043b9fa51d7c633879e69','5365b8935ffe15e3ce06a4309f','aece582ffa74af63deddb1752c','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:14:55.515896'),('e089627730de6830497bd2ac6b','5ccd2622a79bbf53720c22e672','b3b7e338380a5159dcbd4d3ccc',NULL,NULL,'document.access_token_issued','87dccf0ef6d17c0b175921d5ed','allowed',NULL,'{\"request_id\":\"panel_c8609790e92c2fff682515c3\",\"expires_in\":120,\"purpose\":\"download\"}','185.223.160.59','BabanoellPanel/2.5.0','2026-10-28 17:55:14.226703'),('e8b9358673756cc13ab731895d','59fc85c9640fc0d980da00e051','062a865ad13d9c9ea6f91caf03','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:14:45.750838'),('efb51436cb3fd968ff75ac55f3','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','loan_request','9b9243d8f4aa2bca09f4154605','document.reviewed','0f1652c3528f92bc3f95edd9ab','allowed',NULL,'{\"request_id\":\"panel_c2f9f6e52b6f4df78552b8d6\",\"status\":\"approved\"}','185.223.160.59','BabanoellPanel/2.3.1','2026-09-30 12:56:46.207522'),('fb201666959f96c31ba18f4e1c','5ccd2622a79bbf53720c22e672','b3b7e338380a5159dcbd4d3ccc','loan_request','9b9243d8f4aa2bca09f4154605','document.uploaded','ebb526505d8694dd5e951df25f','allowed',NULL,'{\"mime_type\":\"image/png\",\"size_bytes\":59647,\"checksum_sha256\":\"0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6\"}','185.223.160.59','BabanoellPanel/1.5.2','2026-09-28 14:14:36.882347');
UNLOCK TABLES;

--
-- Table structure for table `document_links`
--

DROP TABLE IF EXISTS `document_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_links` (
  `id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` char(26) NOT NULL,
  `relation_code` varchar(64) NOT NULL DEFAULT 'attachment',
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_link` (`document_id`,`entity_type`,`entity_id`,`relation_code`),
  KEY `idx_document_links_entity` (`entity_type`,`entity_id`,`created_at`),
  CONSTRAINT `fk_document_links_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_links`
--

LOCK TABLES `document_links` WRITE;
INSERT INTO `document_links` VALUES ('1099c88d52a186a2c25246dd14','59d9017e758e2ecb47a47bbd2a','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:15:28.208693'),('250392640ee104372dcadf9fc3','14733e1601e519533190e37f3e','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation_license','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:23.815365'),('325446caa0869fd7b1936ca1f5','6df1b725fc145c51f2d86e4783','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:13:53.687311'),('56fd1c41ccc4075f3dfefdda1c','aa230a2c8929a233a2fc776ae8','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:14:18.800313'),('5a3829d7a9e67b260954c92c60','61ef935858e3f1dfc6513d4fa3','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation_contract','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:45.983344'),('5f92122b2bb16c7ee87cda910a','3a7ddaaf0ce8236c3592331c3e','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation_identity','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:31.336723'),('8ab92f58b88a7b07b318d27bb0','8a2d9f78b609eb2843b588918a','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:15:19.156137'),('8e8fe96d787b3638159d000b66','5ccd2622a79bbf53720c22e672','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:14:36.881293'),('b1b4a3740edb9b0f4806aca3eb','fa44aa67b9a660662d4370398e','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:15:53.656257'),('cf0688fc63a291d9589848d12b','089241c259ac623de1432e4ecf','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:08:11.633485'),('d2abc39513dec30d63719df316','e1c42453d13a0762a0f5858389','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:09:09.652755'),('e0955feb8df19fbab1673b7aa6','e4e567200eadcaefb480220148','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:15:07.104656'),('f371a4b8e6394930b01552883a','59fc85c9640fc0d980da00e051','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:14:45.749880'),('f9e0dcc1a3eef1490a1ba21d45','5365b8935ffe15e3ce06a4309f','loan_request','9b9243d8f4aa2bca09f4154605','attachment','ebb526505d8694dd5e951df25f','2026-09-28 14:14:55.515029');
UNLOCK TABLES;

--
-- Table structure for table `document_reviews`
--

DROP TABLE IF EXISTS `document_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_reviews` (
  `id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `version_id` char(26) NOT NULL,
  `status` varchar(24) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `reviewer_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_document_reviews_document` (`document_id`,`created_at`),
  KEY `idx_document_reviews_status` (`status`,`created_at`),
  KEY `fk_document_reviews_version` (`version_id`),
  CONSTRAINT `fk_document_reviews_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `fk_document_reviews_version` FOREIGN KEY (`version_id`) REFERENCES `document_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_reviews`
--

LOCK TABLES `document_reviews` WRITE;
INSERT INTO `document_reviews` VALUES ('692a2989325123c49614a603f4','fa44aa67b9a660662d4370398e','3b5c3c2a6bc97be6df086e1534','rejected','اشتباه است','0f1652c3528f92bc3f95edd9ab','2026-09-30 12:57:22.903198'),('d6eac6097d1a0a0a73ccee7c0e','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','approved',NULL,'0f1652c3528f92bc3f95edd9ab','2026-09-30 12:56:46.204587'),('db4db4af07eb8ea2a3f50ffee8','59d9017e758e2ecb47a47bbd2a','50ade6e78543204b64cf088139','approved',NULL,'0f1652c3528f92bc3f95edd9ab','2026-09-30 12:57:48.765993');
UNLOCK TABLES;

--
-- Table structure for table `document_versions`
--

DROP TABLE IF EXISTS `document_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_versions` (
  `id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `storage_key` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `extension` varchar(12) NOT NULL,
  `size_bytes` bigint(20) unsigned NOT NULL,
  `checksum_sha256` char(64) NOT NULL,
  `uploaded_by_user_id` char(26) NOT NULL,
  `uploaded_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_document_version` (`document_id`,`version`),
  UNIQUE KEY `uq_document_storage_key` (`storage_key`),
  KEY `idx_document_versions_checksum` (`checksum_sha256`),
  CONSTRAINT `fk_document_versions_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_versions`
--

LOCK TABLES `document_versions` WRITE;
INSERT INTO `document_versions` VALUES ('062a865ad13d9c9ea6f91caf03','59fc85c9640fc0d980da00e051',1,'2026/09/ea46dfcd99315e1111153b090bd32309301c.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:14:45.749274'),('08ca3cdce455df58420e7c4d32','61ef935858e3f1dfc6513d4fa3',1,'2026/09/b6f76caa2b0fd7015762fc508f5cf40215f5.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:45.982584'),('18aba3d5069a851e1f5191ce12','8a2d9f78b609eb2843b588918a',1,'2026/09/1ed752d7e717f223bedbc3026c737cb23c94.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','ebb526505d8694dd5e951df25f','2026-09-28 14:15:19.155636'),('1ff8a15bf8be59c43227098fa3','6df1b725fc145c51f2d86e4783',1,'2026/09/3799d63d1e811eeb28cc73dfa81c9026c91d.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:13:53.686020'),('2a8bbd6c4d8ba7140bd239cec7','089241c259ac623de1432e4ecf',1,'2026/09/f0600f8bd4dd0c58d3c9ba01aa1ea08ec993.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','ebb526505d8694dd5e951df25f','2026-09-28 14:08:11.632175'),('3b5c3c2a6bc97be6df086e1534','fa44aa67b9a660662d4370398e',1,'2026/09/07afae54ab2ef4a24aed5e62e9d4f31fb7df.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:15:53.651719'),('4cc907123d142b53561590e837','e4e567200eadcaefb480220148',1,'2026/09/a68e23b7744a2fecea486da0307a8179cb28.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','ebb526505d8694dd5e951df25f','2026-09-28 14:15:07.103813'),('50ade6e78543204b64cf088139','59d9017e758e2ecb47a47bbd2a',1,'2026/09/e5f3d5abba3b943433d4fec7bc4f833247aa.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','ebb526505d8694dd5e951df25f','2026-09-28 14:15:28.207026'),('60f9b5422416d822c7536362fc','14733e1601e519533190e37f3e',1,'2026/09/f84aa0af214d5b6853523ea036f46875fd01.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:23.814273'),('7b62f1626f31cf378c04a00107','aa230a2c8929a233a2fc776ae8',1,'2026/09/771819594b4616c872682193b0d5aa200a0b.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:14:18.799671'),('a19bba74ee005354f829341975','3a7ddaaf0ce8236c3592331c3e',1,'2026/09/da28be7bfc2341d4cba1ab00ecd872d4cc6a.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:31.336232'),('aece582ffa74af63deddb1752c','5365b8935ffe15e3ce06a4309f',1,'2026/09/2690492a5192c23e7ef7b100038363e791e7.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:14:55.514354'),('b3b7e338380a5159dcbd4d3ccc','5ccd2622a79bbf53720c22e672',1,'2026/09/4b50abb5b4105e3de9adb9a1433c411db6ee.png','1.png','image/png','png',59647,'0a4f52f0b78c582abb9fa298417f13f7d77856ccb6ceaf9a3824afdc5511c9f6','ebb526505d8694dd5e951df25f','2026-09-28 14:14:36.880460'),('d1a877b1bc4fe62aa1c249fea6','e1c42453d13a0762a0f5858389',1,'2026/09/01cf0870824650ef756993b2e77078e549d1.png','2.png','image/png','png',57665,'1dc43d3b73c47556cfbbb2158d672bd1ce8430cfe98817790132d443ffc126df','ebb526505d8694dd5e951df25f','2026-09-28 14:09:09.652157');
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents` (
  `id` char(26) NOT NULL,
  `document_type` varchar(80) NOT NULL,
  `category` varchar(48) NOT NULL,
  `label` varchar(190) NOT NULL,
  `visibility` varchar(24) NOT NULL DEFAULT 'normal',
  `owner_user_id` char(26) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `review_status` varchar(24) NOT NULL DEFAULT 'pending',
  `current_version` int(10) unsigned NOT NULL DEFAULT 1,
  `latest_review_reason` varchar(1000) DEFAULT NULL,
  `reviewed_by_user_id` char(26) DEFAULT NULL,
  `reviewed_at` datetime(6) DEFAULT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `archived_by_user_id` char(26) DEFAULT NULL,
  `archive_reason` varchar(1000) DEFAULT NULL,
  `archived_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `source_service` varchar(64) NOT NULL DEFAULT 'panel',
  `legacy_reference` varchar(190) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `retention_until` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_documents_type_status` (`document_type`,`status`),
  KEY `idx_documents_category_review` (`category`,`review_status`),
  KEY `idx_documents_owner` (`owner_user_id`,`status`),
  KEY `idx_documents_updated` (`updated_at`),
  KEY `idx_documents_legacy_reference` (`legacy_reference`),
  KEY `idx_documents_source` (`source_service`,`created_at`),
  KEY `idx_documents_retention` (`retention_until`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
INSERT INTO `documents` VALUES ('089241c259ac623de1432e4ecf','national_card','identity','کارت ملی','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:08:11.627095','2026-09-28 14:08:11.627095','panel',NULL,NULL,NULL),('14733e1601e519533190e37f3e','cooperation_license','cooperation','مجوز همکاری / فعالیت','confidential','87dccf0ef6d17c0b175921d5ed','active','pending',1,NULL,NULL,NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,NULL,'2026-09-30 12:15:23.812254','2026-09-30 12:15:23.812254','panel',NULL,NULL,NULL),('3a7ddaaf0ce8236c3592331c3e','cooperation_identity','cooperation','مدارک هویتی همکاری','confidential','87dccf0ef6d17c0b175921d5ed','active','pending',1,NULL,NULL,NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,NULL,'2026-09-30 12:15:31.334838','2026-09-30 12:15:31.334838','panel',NULL,NULL,NULL),('5365b8935ffe15e3ce06a4309f','bank_statement','financial','گردش حساب','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:14:55.513283','2026-09-28 14:14:55.513283','panel',NULL,NULL,NULL),('59d9017e758e2ecb47a47bbd2a','secondary_check','check_secondary','چک تکمیلی','confidential','ebb526505d8694dd5e951df25f','active','approved',1,NULL,'0f1652c3528f92bc3f95edd9ab','2026-09-30 12:57:48.766726','ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:15:28.205200','2026-09-30 12:57:48.766726','panel',NULL,NULL,NULL),('59fc85c9640fc0d980da00e051','payslip','financial','فیش حقوقی','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:14:45.746272','2026-09-28 14:14:45.746272','panel',NULL,NULL,NULL),('5ccd2622a79bbf53720c22e672','business_license','employment','جواز کسب','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:14:36.879079','2026-09-28 14:14:36.879079','panel',NULL,NULL,NULL),('61ef935858e3f1dfc6513d4fa3','cooperation_contract','cooperation','قرارداد همکاری','confidential','87dccf0ef6d17c0b175921d5ed','active','pending',1,NULL,NULL,NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,NULL,'2026-09-30 12:15:45.981340','2026-09-30 12:15:45.981340','panel',NULL,NULL,NULL),('6df1b725fc145c51f2d86e4783','portrait','identity','تصویر چهره','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:13:53.683678','2026-09-28 14:13:53.683678','panel',NULL,NULL,NULL),('8a2d9f78b609eb2843b588918a','primary_check','check_primary','چک اصلی','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:15:19.154695','2026-09-28 14:15:19.154695','panel',NULL,NULL,NULL),('aa230a2c8929a233a2fc776ae8','employment_certificate','employment','گواهی اشتغال','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:14:18.797238','2026-09-28 14:14:18.797238','panel',NULL,NULL,NULL),('e1c42453d13a0762a0f5858389','birth_certificate','identity','شناسنامه','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:09:09.651124','2026-09-28 14:09:09.651124','panel',NULL,NULL,NULL),('e4e567200eadcaefb480220148','tax_return','financial','اظهارنامه / اطلاعات مالیاتی','confidential','ebb526505d8694dd5e951df25f','active','pending',1,NULL,NULL,NULL,'ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:15:07.102825','2026-09-28 14:15:07.102825','panel',NULL,NULL,NULL),('fa44aa67b9a660662d4370398e','cooperation_contract','cooperation','قرارداد همکاری','confidential','ebb526505d8694dd5e951df25f','active','rejected',1,'اشتباه است','0f1652c3528f92bc3f95edd9ab','2026-09-30 12:57:22.903812','ebb526505d8694dd5e951df25f',NULL,NULL,NULL,'2026-09-28 14:15:53.646244','2026-09-30 12:57:22.903812','panel',NULL,NULL,NULL);
UNLOCK TABLES;

--
-- Table structure for table `documents_schema_migrations`
--

DROP TABLE IF EXISTS `documents_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `documents_schema_migrations` (
  `version` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents_schema_migrations`
--

LOCK TABLES `documents_schema_migrations` WRITE;
INSERT INTO `documents_schema_migrations` VALUES ('202608270001','create_documents_table','2026-08-27 14:17:57.960535'),('202608270002','create_document_versions_table','2026-08-27 14:17:57.971311'),('202608270003','create_document_links_table','2026-08-27 14:17:57.975302'),('202608270004','create_document_reviews_table','2026-08-27 14:17:57.981839'),('202608270005','create_document_audit_logs_table','2026-08-27 14:17:57.986935'),('202608270006','create_document_access_tokens_table','2026-08-27 14:17:57.996350'),('202609260001','extend_document_core_metadata','2026-09-26 14:03:31.491476'),('202610220001','immutable_contract_pdf_archive','2026-10-23 15:07:45.235673');
UNLOCK TABLES;

--
-- Dumping events for database 'noeelir_documents'
--

--
-- Dumping routines for database 'noeelir_documents'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 22:14:41
