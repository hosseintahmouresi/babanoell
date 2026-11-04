/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.24-MariaDB, for linux-systemd (x86_64)
--
-- Host: localhost    Database: noeelir_partners
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
-- Table structure for table `contract_artifact_handoffs`
--

DROP TABLE IF EXISTS `contract_artifact_handoffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_artifact_handoffs` (
  `id` char(26) NOT NULL,
  `contract_id` char(26) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expected_sha256` char(64) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `expires_at` datetime(6) NOT NULL,
  `consumed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_artifact_token` (`token_hash`),
  KEY `idx_contract_artifact_contract` (`contract_id`,`status`,`expires_at`),
  CONSTRAINT `fk_contract_artifact_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_artifact_handoff_status` CHECK (`status` in ('active','consumed','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_artifact_handoffs`
--

LOCK TABLES `contract_artifact_handoffs` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `contract_events`
--

DROP TABLE IF EXISTS `contract_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_events` (
  `id` char(26) NOT NULL,
  `contract_id` char(26) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `result` varchar(24) NOT NULL DEFAULT 'allowed',
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `ip_address` varchar(64) DEFAULT NULL,
  `user_agent` varchar(512) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_contract_events_contract` (`contract_id`,`created_at`),
  CONSTRAINT `fk_contract_events_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_events`
--

LOCK TABLES `contract_events` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `contract_otp_challenges`
--

DROP TABLE IF EXISTS `contract_otp_challenges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_otp_challenges` (
  `id` char(26) NOT NULL,
  `contract_id` char(26) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `max_attempts` smallint(5) unsigned NOT NULL DEFAULT 5,
  `resend_after` datetime(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `verified_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_contract_otp_active` (`contract_id`,`status`,`expires_at`),
  CONSTRAINT `fk_contract_otp_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_otp_status` CHECK (`status` in ('active','verified','expired','locked','superseded'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_otp_challenges`
--

LOCK TABLES `contract_otp_challenges` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `contract_templates`
--

DROP TABLE IF EXISTS `contract_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_templates` (
  `id` char(26) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(180) NOT NULL,
  `contract_kind` varchar(40) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `content_html` mediumtext NOT NULL,
  `variables_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables_json`)),
  `content_hash` char(64) NOT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `published_at` datetime(6) DEFAULT NULL,
  `archived_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_template_code_version` (`code`,`version`),
  KEY `idx_contract_template_kind_status` (`contract_kind`,`status`,`version`),
  CONSTRAINT `chk_contract_template_status` CHECK (`status` in ('draft','active','archived')),
  CONSTRAINT `chk_contract_template_kind` CHECK (`contract_kind` in ('cooperation_agreement','loan_application','facility_agreement'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contract_templates`
--

LOCK TABLES `contract_templates` WRITE;
INSERT INTO `contract_templates` VALUES ('2525dc5e41267c67ec45c8e3ab','loan_application_default','درخواست تسهیلات','loan_application',1,'active','<h1>درخواست تسهیلات</h1><p>شماره پرونده: {{request_no}}</p><p>متقاضی: {{signer_name}}</p><p>مبلغ درخواستی: {{requested_amount}}</p>','[]','c1247ff11d5d3327109448af5a932049777094429691cec042adf0478e154812','00000000000000000000000000','2026-09-30 23:01:51.444548',NULL,'2026-09-30 23:01:51.444548','2026-09-30 23:01:51.444548'),('880ad2adf32793b06c26a64589','cooperation_default','قرارداد همکاری','cooperation_agreement',1,'active','<h1>قرارداد همکاری</h1><p>شماره درخواست: {{request_no}}</p><p>نام متقاضی: {{signer_name}}</p><p>این نسخه در زمان ایجاد قرارداد منجمد و هش می‌شود.</p>','[]','ae901f5ac6dec597d5996ec43641822e0a13a36ab872bdde862673b4da1135dc','00000000000000000000000000','2026-09-30 23:01:51.444110',NULL,'2026-09-30 23:01:51.444110','2026-09-30 23:01:51.444110'),('cfb1b188bc3040415c2adb3d31','facility_agreement_default','قرارداد دریافت تسهیلات','facility_agreement',1,'active','<h1>قرارداد دریافت تسهیلات</h1><p>شماره پرونده: {{request_no}}</p><p>متقاضی: {{signer_name}}</p><p>مبلغ تسهیلات: {{facility_amount}}</p>','[]','dd1c509b34383de72dcc6cc3290132b98915c8b5b620109809fc61ba0bc00975','00000000000000000000000000','2026-09-30 23:01:51.444759',NULL,'2026-09-30 23:01:51.444759','2026-09-30 23:01:51.444759');
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` char(26) NOT NULL,
  `contract_no` varchar(48) NOT NULL,
  `contract_kind` varchar(40) NOT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 1,
  `subject_type` varchar(40) NOT NULL,
  `subject_id` char(26) NOT NULL,
  `reissued_from_id` char(26) DEFAULT NULL,
  `reissue_reason` varchar(1000) DEFAULT NULL,
  `signer_user_id` char(26) DEFAULT NULL,
  `signer_mobile` varchar(32) DEFAULT NULL,
  `mode` varchar(16) NOT NULL DEFAULT 'digital',
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `template_id` char(26) DEFAULT NULL,
  `template_code` varchar(80) NOT NULL,
  `template_version` int(10) unsigned NOT NULL DEFAULT 1,
  `template_hash` char(64) NOT NULL,
  `rendered_content` mediumtext NOT NULL,
  `rendered_hash` char(64) NOT NULL,
  `public_token_hash` char(64) DEFAULT NULL,
  `public_token_expires_at` datetime(6) DEFAULT NULL,
  `viewed_at` datetime(6) DEFAULT NULL,
  `otp_verified_at` datetime(6) DEFAULT NULL,
  `signed_at` datetime(6) DEFAULT NULL,
  `signer_ip` varchar(64) DEFAULT NULL,
  `signer_user_agent` varchar(512) DEFAULT NULL,
  `artifact_document_id` char(26) DEFAULT NULL,
  `artifact_hash` char(64) DEFAULT NULL,
  `artifact_status` varchar(24) NOT NULL DEFAULT 'pending',
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `pdf_document_id` char(26) DEFAULT NULL,
  `pdf_hash` char(64) DEFAULT NULL,
  `pdf_stored_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_contract_no` (`contract_no`),
  UNIQUE KEY `uq_contract_subject_kind_revision` (`subject_type`,`subject_id`,`contract_kind`,`revision`),
  KEY `idx_contract_subject` (`subject_type`,`subject_id`,`status`),
  KEY `idx_contract_signer` (`signer_user_id`,`status`),
  KEY `idx_contract_public_token` (`public_token_hash`),
  KEY `fk_contract_template` (`template_id`),
  KEY `idx_contract_reissue_parent` (`reissued_from_id`),
  CONSTRAINT `fk_contract_template` FOREIGN KEY (`template_id`) REFERENCES `contract_templates` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_contract_mode` CHECK (`mode` in ('physical','digital','legacy')),
  CONSTRAINT `chk_contract_status` CHECK (`status` in ('pending','sent','viewed','otp_verified','signed','expired','cancelled','legacy_imported')),
  CONSTRAINT `chk_contract_artifact_status` CHECK (`artifact_status` in ('pending','stored','failed','not_required')),
  CONSTRAINT `chk_contract_kind` CHECK (`contract_kind` in ('cooperation_agreement','loan_application','facility_agreement')),
  CONSTRAINT `chk_contract_subject_type` CHECK (`subject_type` in ('cooperation_request','loan_request'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `cooperation_documents`
--

DROP TABLE IF EXISTS `cooperation_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperation_documents` (
  `cooperation_request_id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `relation_code` varchar(64) NOT NULL DEFAULT 'attachment',
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`cooperation_request_id`,`document_id`,`relation_code`),
  KEY `idx_cooperation_documents_document` (`document_id`),
  CONSTRAINT `fk_cooperation_documents_request` FOREIGN KEY (`cooperation_request_id`) REFERENCES `cooperation_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperation_documents`
--

LOCK TABLES `cooperation_documents` WRITE;
INSERT INTO `cooperation_documents` VALUES ('681aa88d573abaabd0fe1bc6f9','14733e1601e519533190e37f3e','cooperation_license','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:23.928952'),('681aa88d573abaabd0fe1bc6f9','3a7ddaaf0ce8236c3592331c3e','cooperation_identity','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:31.523136'),('681aa88d573abaabd0fe1bc6f9','61ef935858e3f1dfc6513d4fa3','cooperation_contract','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:15:46.113460');
UNLOCK TABLES;

--
-- Table structure for table `cooperation_form_revisions`
--

DROP TABLE IF EXISTS `cooperation_form_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperation_form_revisions` (
  `revision` int(10) unsigned NOT NULL,
  `definition_json` longtext NOT NULL,
  `updated_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperation_form_revisions`
--

LOCK TABLES `cooperation_form_revisions` WRITE;
INSERT INTO `cooperation_form_revisions` VALUES (1,'{\"fields\":{\"store_name\":{\"visible\":true,\"required\":false},\"store_owner_name\":{\"visible\":true,\"required\":true},\"store_address\":{\"visible\":true,\"required\":false},\"store_images\":{\"visible\":true,\"required\":false},\"company_name\":{\"visible\":true,\"required\":true},\"contact_person\":{\"visible\":true,\"required\":false},\"company_address\":{\"visible\":true,\"required\":false},\"email\":{\"visible\":true,\"required\":false},\"company_images\":{\"visible\":true,\"required\":false},\"company_docs\":{\"visible\":true,\"required\":false},\"activity_field\":{\"visible\":true,\"required\":false},\"how_heard\":{\"visible\":true,\"required\":false},\"activity_description\":{\"visible\":true,\"required\":false},\"postal_code\":{\"visible\":true,\"required\":false},\"applicant_id_number\":{\"visible\":true,\"required\":false},\"mobile_number\":{\"visible\":true,\"required\":false},\"phone_number\":{\"visible\":true,\"required\":false},\"website\":{\"visible\":true,\"required\":false},\"branches\":{\"visible\":true,\"required\":false},\"description\":{\"visible\":true,\"required\":false},\"national_card\":{\"visible\":true,\"required\":false},\"id_card\":{\"visible\":true,\"required\":false},\"business_license\":{\"visible\":true,\"required\":false},\"ownership_or_lease\":{\"visible\":true,\"required\":false},\"thana_certificate\":{\"visible\":true,\"required\":false},\"logo\":{\"visible\":true,\"required\":false},\"check_image\":{\"visible\":true,\"required\":false},\"signature_certificate\":{\"visible\":true,\"required\":false},\"tax_info\":{\"visible\":true,\"required\":false},\"enamad\":{\"visible\":true,\"required\":false}},\"options\":{\"activity_field_options\":[\"پوشاک\",\"لوازم خانگی\",\"موبایل و کامپیوتر\",\"طلا و جواهر\",\"خدمات\"],\"how_heard_options\":[\"جستجوی گوگل\",\"شبکه‌های اجتماعی\",\"معرفی دوستان\",\"تبلیغات\",\"سایر\"]}}',NULL,'2026-11-03 14:39:30.223466'),(2,'{\"fields\":{\"store_name\":{\"visible\":true,\"required\":false},\"store_owner_name\":{\"visible\":true,\"required\":true},\"store_address\":{\"visible\":true,\"required\":false},\"store_images\":{\"visible\":true,\"required\":false},\"company_name\":{\"visible\":true,\"required\":true},\"contact_person\":{\"visible\":true,\"required\":false},\"company_address\":{\"visible\":true,\"required\":false},\"email\":{\"visible\":true,\"required\":false},\"company_images\":{\"visible\":true,\"required\":false},\"company_docs\":{\"visible\":true,\"required\":false},\"activity_field\":{\"visible\":true,\"required\":false},\"how_heard\":{\"visible\":true,\"required\":false},\"activity_description\":{\"visible\":true,\"required\":false},\"postal_code\":{\"visible\":true,\"required\":false},\"applicant_id_number\":{\"visible\":true,\"required\":false},\"mobile_number\":{\"visible\":true,\"required\":false},\"phone_number\":{\"visible\":true,\"required\":false},\"website\":{\"visible\":true,\"required\":false},\"branches\":{\"visible\":true,\"required\":false},\"description\":{\"visible\":true,\"required\":false},\"national_card\":{\"visible\":true,\"required\":false},\"id_card\":{\"visible\":true,\"required\":false},\"business_license\":{\"visible\":true,\"required\":false},\"ownership_or_lease\":{\"visible\":true,\"required\":false},\"thana_certificate\":{\"visible\":true,\"required\":false},\"logo\":{\"visible\":true,\"required\":false},\"check_image\":{\"visible\":true,\"required\":false},\"signature_certificate\":{\"visible\":true,\"required\":false},\"tax_info\":{\"visible\":true,\"required\":false},\"enamad\":{\"visible\":true,\"required\":false}},\"options\":{\"activity_field_options\":[\"پوشاک\",\"لوازم خانگی\",\"موبایل و کامپیوتر\",\"طلا و جواهر\",\"خدمات\"],\"how_heard_options\":[\"جستجوی گوگل\",\"شبکه‌های اجتماعی\",\"معرفی دوستان\",\"تبلیغات\",\"سایر\"]}}','87dccf0ef6d17c0b175921d5ed','2026-11-03 14:42:18.657269');
UNLOCK TABLES;

--
-- Table structure for table `cooperation_profile_corrections`
--

DROP TABLE IF EXISTS `cooperation_profile_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperation_profile_corrections` (
  `id` char(26) NOT NULL,
  `cooperation_request_id` char(26) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `changed_fields_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`changed_fields_json`)),
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`after_json`)),
  `row_version_before` bigint(20) unsigned NOT NULL,
  `row_version_after` bigint(20) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_cooperation_corrections_request` (`cooperation_request_id`,`created_at`),
  CONSTRAINT `fk_cooperation_corrections_request` FOREIGN KEY (`cooperation_request_id`) REFERENCES `cooperation_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperation_profile_corrections`
--

LOCK TABLES `cooperation_profile_corrections` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `cooperation_requests`
--

DROP TABLE IF EXISTS `cooperation_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cooperation_requests` (
  `id` char(26) NOT NULL,
  `request_no` varchar(40) NOT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `legacy_id` bigint(20) unsigned DEFAULT NULL,
  `applicant_user_id` char(26) DEFAULT NULL,
  `merchant_id` char(26) DEFAULT NULL,
  `request_type` varchar(32) NOT NULL DEFAULT 'individual',
  `status` varchar(32) NOT NULL DEFAULT 'submitted',
  `contract_token_hash` char(64) DEFAULT NULL,
  `contract_signed_status` varchar(24) NOT NULL DEFAULT 'pending',
  `contract_signed_at` datetime(6) DEFAULT NULL,
  `contract_expires_at` datetime(6) DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime(6) DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `decision_note` text DEFAULT NULL,
  `assigned_user_id` char(26) DEFAULT NULL,
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` char(26) DEFAULT NULL,
  `submitted_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `decided_at` datetime(6) DEFAULT NULL,
  `decided_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cooperation_request_no` (`request_no`),
  UNIQUE KEY `uq_cooperation_idempotency` (`created_by_user_id`,`idempotency_key`),
  KEY `idx_cooperation_status_type` (`status`,`request_type`,`updated_at`),
  KEY `idx_cooperation_applicant` (`applicant_user_id`,`created_at`),
  KEY `idx_cooperation_merchant` (`merchant_id`,`status`),
  KEY `idx_cooperation_assigned` (`assigned_user_id`,`status`),
  KEY `idx_cooperation_legacy` (`legacy_id`),
  CONSTRAINT `fk_cooperation_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_cooperation_type` CHECK (`request_type` in ('individual','legal')),
  CONSTRAINT `chk_cooperation_status` CHECK (`status` in ('submitted','under_review','need_correction','approved','rejected','cancelled','archived')),
  CONSTRAINT `chk_cooperation_contract_status` CHECK (`contract_signed_status` in ('pending','sent','signed','expired','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperation_requests`
--

LOCK TABLES `cooperation_requests` WRITE;
INSERT INTO `cooperation_requests` VALUES ('681aa88d573abaabd0fe1bc6f9','CO-260930-681AA88D','panel-coop-3e537451a329861e3b352e85729d0d47',NULL,'87dccf0ef6d17c0b175921d5ed',NULL,'individual','under_review',NULL,'pending',NULL,NULL,NULL,NULL,'{\"full_name\":\"مهدی مشتاقی\",\"company_name\":\"\",\"trade_name\":\"بابانوئل\",\"national_code\":\"14011126653\",\"mobile\":\"09962016001\",\"activity\":\"لندتک\"}',NULL,NULL,2,'87dccf0ef6d17c0b175921d5ed','2026-09-30 12:14:53.775358',NULL,NULL,'2026-09-30 12:14:53.775358','2026-09-30 12:15:50.631072');
UNLOCK TABLES;

--
-- Table structure for table `marketplace_claims`
--

DROP TABLE IF EXISTS `marketplace_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `marketplace_claims` (
  `id` char(26) NOT NULL,
  `loan_request_id` char(26) NOT NULL,
  `merchant_id` char(26) NOT NULL,
  `claimed_by_user_id` char(26) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'claimed',
  `source` varchar(32) NOT NULL DEFAULT 'marketplace',
  `claimed_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `released_at` datetime(6) DEFAULT NULL,
  `released_by_user_id` char(26) DEFAULT NULL,
  `release_reason` varchar(500) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_marketplace_request` (`loan_request_id`),
  KEY `idx_marketplace_claims_merchant` (`merchant_id`,`status`,`claimed_at`),
  CONSTRAINT `fk_marketplace_claim_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_marketplace_claim_status` CHECK (`status` in ('claimed','released','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `marketplace_claims`
--

LOCK TABLES `marketplace_claims` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `merchant_invitations`
--

DROP TABLE IF EXISTS `merchant_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_invitations` (
  `id` char(26) NOT NULL,
  `merchant_id` char(26) NOT NULL,
  `invitation_code_hash` char(64) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `created_by_user_id` char(26) NOT NULL,
  `used_by_user_id` char(26) DEFAULT NULL,
  `loan_request_id` char(26) DEFAULT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchant_invitation_hash` (`invitation_code_hash`),
  KEY `idx_merchant_invitations_merchant` (`merchant_id`,`status`,`created_at`),
  CONSTRAINT `fk_merchant_invitation_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_merchant_invitation_status` CHECK (`status` in ('active','used','revoked','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_invitations`
--

LOCK TABLES `merchant_invitations` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `merchant_plan_permissions`
--

DROP TABLE IF EXISTS `merchant_plan_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_plan_permissions` (
  `merchant_id` char(26) NOT NULL,
  `plan_id` char(26) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `limits_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`limits_json`)),
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`merchant_id`,`plan_id`),
  KEY `idx_partner_plan_permissions_plan` (`plan_id`,`status`),
  CONSTRAINT `fk_partner_plan_permissions_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_partner_plan_permission_status` CHECK (`status` in ('active','inactive'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_plan_permissions`
--

LOCK TABLES `merchant_plan_permissions` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `merchant_user_memberships`
--

DROP TABLE IF EXISTS `merchant_user_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchant_user_memberships` (
  `merchant_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `membership_role` varchar(32) NOT NULL DEFAULT 'operator',
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`merchant_id`,`user_id`),
  KEY `idx_merchant_memberships_user` (`user_id`,`status`),
  CONSTRAINT `fk_partner_memberships_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_partner_membership_status` CHECK (`status` in ('active','inactive','revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_user_memberships`
--

LOCK TABLES `merchant_user_memberships` WRITE;
INSERT INTO `merchant_user_memberships` VALUES ('63fa8f991ac3a8585a504815de','ebb526505d8694dd5e951df25f','owner','active',1,'87dccf0ef6d17c0b175921d5ed','2026-09-29 16:48:11.686471','2026-09-29 16:48:11.686471');
UNLOCK TABLES;

--
-- Table structure for table `merchants`
--

DROP TABLE IF EXISTS `merchants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `merchants` (
  `id` char(26) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(190) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `owner_user_id` char(26) DEFAULT NULL,
  `organization_ref` char(26) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `cooperation_status` varchar(32) NOT NULL DEFAULT 'approved',
  `credit_level` varchar(32) DEFAULT NULL,
  `financial_settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`financial_settings_json`)),
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `legacy_user_id` bigint(20) unsigned DEFAULT NULL,
  `legacy_profile_ref` varchar(190) DEFAULT NULL,
  `row_version` bigint(20) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchants_code` (`code`),
  KEY `idx_merchants_owner` (`owner_user_id`,`status`),
  KEY `idx_merchants_org` (`organization_ref`,`status`),
  KEY `idx_merchants_status` (`status`,`cooperation_status`),
  KEY `idx_merchants_legacy` (`legacy_user_id`),
  CONSTRAINT `chk_merchants_status` CHECK (`status` in ('active','inactive','suspended','archived')),
  CONSTRAINT `chk_merchants_cooperation` CHECK (`cooperation_status` in ('pending','under_review','approved','rejected','suspended','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchants`
--

LOCK TABLES `merchants` WRITE;
INSERT INTO `merchants` VALUES ('63fa8f991ac3a8585a504815de','ACC-EBB526505D86','پذیرنده منتقل‌شده EBB52650',NULL,'ebb526505d8694dd5e951df25f',NULL,'active','approved',NULL,NULL,'{\"source\":\"loan_request_acceptor_backfill\",\"inferred\":true}',NULL,'ebb526505d8694dd5e951df25f',1,'87dccf0ef6d17c0b175921d5ed','2026-09-28 13:32:16.496594','2026-09-29 15:09:37.876442');
UNLOCK TABLES;

--
-- Table structure for table `partner_events`
--

DROP TABLE IF EXISTS `partner_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partner_events` (
  `id` char(26) NOT NULL,
  `aggregate_type` varchar(64) NOT NULL,
  `aggregate_id` char(26) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `result` varchar(24) NOT NULL DEFAULT 'allowed',
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `request_id` varchar(128) DEFAULT NULL,
  `caller_service` varchar(32) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_partner_events_aggregate` (`aggregate_type`,`aggregate_id`,`created_at`),
  KEY `idx_partner_events_actor` (`actor_user_id`,`created_at`),
  KEY `idx_partner_events_request` (`request_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partner_events`
--

LOCK TABLES `partner_events` WRITE;
INSERT INTO `partner_events` VALUES ('31bf8d42cb1dc8cf7051d0611c','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation.document.linked','87dccf0ef6d17c0b175921d5ed','allowed','{\"document_id\":\"61ef935858e3f1dfc6513d4fa3\",\"relation_code\":\"cooperation_contract\"}','panel_7830bba10f91129cb9f723b3',NULL,'2026-09-30 12:15:46.114684'),('3c8e56b07450a2a2da980ff2e1','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation.document.linked','87dccf0ef6d17c0b175921d5ed','allowed','{\"document_id\":\"3a7ddaaf0ce8236c3592331c3e\",\"relation_code\":\"cooperation_identity\"}','panel_6aa4ff59f48df81556a5955e',NULL,'2026-09-30 12:15:31.524090'),('42a56fe74a8d51e33e2fa8bc26','migration',NULL,'partners.shadow_import.completed','87dccf0ef6d17c0b175921d5ed','allowed','{\"merchants\":{\"source\":0,\"imported\":0},\"memberships\":{\"source\":0,\"imported\":0},\"plan_permissions\":{\"source\":0,\"imported\":0},\"cooperation_requests\":{\"source\":0,\"imported\":0},\"cooperation_documents\":{\"source\":0,\"imported\":0}}','panel_62fdc60498d1071f4caf8056',NULL,'2026-09-29 16:07:52.014130'),('8e568ed210babf58c09321e20a','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation.status.changed','87dccf0ef6d17c0b175921d5ed','allowed','{\"from\":\"submitted\",\"to\":\"under_review\",\"note\":\"\"}','panel_256c3b799a5a36ced6e928d5',NULL,'2026-09-30 12:15:50.632840'),('90eb0e62f6b124b81a353d38bc','cooperation_form',NULL,'cooperation.form.published','87dccf0ef6d17c0b175921d5ed','allowed','{\"previous_revision\":1,\"revision\":2}','panel_4a0b1d9181e07a9e41acb283','panel','2026-11-03 14:42:18.668121'),('9e31e266f2c23a1e220581f340','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation.document.linked','87dccf0ef6d17c0b175921d5ed','allowed','{\"document_id\":\"14733e1601e519533190e37f3e\",\"relation_code\":\"cooperation_license\"}','panel_5adf90b35a0f6ddf4ecd1de8',NULL,'2026-09-30 12:15:23.930156'),('bbe750cb8dbaa3a7d8368358c9','merchant','63fa8f991ac3a8585a504815de','merchant.membership.updated','87dccf0ef6d17c0b175921d5ed','allowed','{\"user_id\":\"ebb526505d8694dd5e951df25f\",\"role\":\"owner\",\"status\":\"active\",\"primary\":true}','panel_4c5a7e68a7e60bcb7ea735f3',NULL,'2026-09-29 16:48:11.689513'),('c80109af792a49dc4dd60fd06d','cooperation_request','681aa88d573abaabd0fe1bc6f9','cooperation.submitted','87dccf0ef6d17c0b175921d5ed','allowed','{\"request_no\":\"CO-260930-681AA88D\",\"request_type\":\"individual\"}','panel_192fe0300761820dab1b50a3',NULL,'2026-09-30 12:14:53.784702'),('f25320074ecd56f43e7c8e4f25','migration',NULL,'partners.shadow_import.completed','87dccf0ef6d17c0b175921d5ed','allowed','{\"merchants\":{\"source\":1,\"imported\":1},\"memberships\":{\"source\":1,\"imported\":1},\"plan_permissions\":{\"source\":0,\"imported\":0},\"cooperation_requests\":{\"source\":0,\"imported\":0},\"cooperation_documents\":{\"source\":0,\"imported\":0}}','panel_4c5a7e68a7e60bcb7ea735f3',NULL,'2026-09-29 16:48:12.098293');
UNLOCK TABLES;

--
-- Table structure for table `partners_schema_migrations`
--

DROP TABLE IF EXISTS `partners_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `partners_schema_migrations` (
  `version` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `partners_schema_migrations`
--

LOCK TABLES `partners_schema_migrations` WRITE;
INSERT INTO `partners_schema_migrations` VALUES ('202609290001','create_partner_merchant_core','2026-09-29 16:06:18.075812'),('202609290002','create_cooperation_core','2026-09-29 16:06:18.084157'),('202609290003','create_marketplace_and_audit_core','2026-09-29 16:06:18.094461'),('202609300001','create_contract_engine','2026-09-30 23:01:51.450818'),('202610210001','create_cooperation_corrections','2026-10-21 13:37:58.972597'),('202610210002','contract_revisions','2026-10-21 14:09:59.356370'),('202610220003','contract_pdf_state','2026-10-23 15:07:18.747845'),('202611030001','cooperation_form_revisions','2026-11-03 14:39:30.223957');
UNLOCK TABLES;

--
-- Dumping events for database 'noeelir_partners'
--

--
-- Dumping routines for database 'noeelir_partners'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 22:14:47
