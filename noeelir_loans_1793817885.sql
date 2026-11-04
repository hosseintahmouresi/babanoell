/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.6.24-MariaDB, for linux-systemd (x86_64)
--
-- Host: localhost    Database: noeelir_loans
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
-- Table structure for table `bank_reviews`
--

DROP TABLE IF EXISTS `bank_reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bank_reviews` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `reviewer_user_id` char(26) NOT NULL,
  `status` varchar(32) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `external_reference` varchar(190) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_bank_reviews_request` (`request_id`,`created_at`),
  KEY `idx_bank_reviews_reviewer` (`reviewer_user_id`,`created_at`),
  CONSTRAINT `fk_bank_reviews_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_reviews`
--

LOCK TABLES `bank_reviews` WRITE;
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
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_bank_settlement_history_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_bank_settlement_history_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_settlement_history`
--

LOCK TABLES `bank_settlement_history` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `calculation_runs`
--

DROP TABLE IF EXISTS `calculation_runs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `calculation_runs` (
  `id` char(26) NOT NULL,
  `plan_id` char(26) NOT NULL,
  `plan_version_id` char(26) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `input_amount` decimal(20,2) NOT NULL,
  `installment_count` int(10) unsigned NOT NULL,
  `installment_interval` int(10) unsigned NOT NULL DEFAULT 1,
  `commission_override` decimal(10,4) DEFAULT NULL,
  `input_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`input_json`)),
  `result_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`result_json`)),
  `result_hash` char(64) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `engine_version` varchar(32) NOT NULL DEFAULT 'v1',
  `plan_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`plan_snapshot_json`)),
  `input_hash` char(64) DEFAULT NULL,
  `mode` varchar(24) NOT NULL DEFAULT 'operational',
  PRIMARY KEY (`id`),
  KEY `idx_calculation_runs_plan` (`plan_id`,`created_at`),
  KEY `idx_calculation_runs_actor` (`actor_user_id`,`created_at`),
  KEY `fk_calculation_runs_version` (`plan_version_id`),
  KEY `idx_calculation_runs_input_hash` (`plan_id`,`plan_version_id`,`input_hash`),
  CONSTRAINT `fk_calculation_runs_plan` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`),
  CONSTRAINT `fk_calculation_runs_version` FOREIGN KEY (`plan_version_id`) REFERENCES `installment_plan_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calculation_runs`
--

LOCK TABLES `calculation_runs` WRITE;
INSERT INTO `calculation_runs` VALUES ('075f04901ae99d695cb6ab5b8c','511c7a1202318f5f0695fcc042','d3c3f4c27ef7a0e24534d90f3e','87dccf0ef6d17c0b175921d5ed',1000000.00,12,1,NULL,'{\"amount\":1000000,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":800000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":200000,\"installmentAmount\":75261,\"totalPayment\":903132,\"profit\":103132,\"walletCharge\":765996,\"guaranteeCheckPrincipal\":1040000,\"guaranteeCheckTotal\":1174072,\"operationCostPercent\":0.010183492470464121,\"operationCost\":20149,\"operationCostWithVat\":22164,\"netLeasingPayment\":777836,\"acceptorCommission\":20000,\"acceptorCommissionWithVat\":22000,\"acceptorPayment\":749144}','63015f7accf7ad9f74941a42643368a742f4ea6f754509839c76039515f6d754','2026-10-30 00:17:58.356006','v1','{\"id\":\"511c7a1202318f5f0695fcc042\",\"code\":\"TEST\",\"name\":\"test (تامین اجتماعی)\",\"type\":\"leasing\",\"version\":5,\"version_id\":\"d3c3f4c27ef7a0e24534d90f3e\",\"settings\":{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":true,\"requires_identity_verification\":true},\"display_fields\":[],\"required_documents\":[\"national_card\",\"birth_certificate\"],\"rules\":{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"requires_identity_verification\":true,\"contract_mode\":\"physical\",\"loan_application_contract_enabled\":true,\"facility_agreement_contract_enabled\":true,\"loan_application_template_code\":\"loan_application_default\",\"facility_agreement_template_code\":\"facility_agreement_default\",\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}},\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]},\"conditions\":\"شرایط \\r\\nو توضیحات\",\"configuration_hash\":\"8731aae4ae1351ac53093cd3be156abfca177cb57c34143ce946afff48af27b5\"}','b87e1e614de8ddfdcbbc1415f31146bfbc145c923b433d502dca53e9fb3b19b9','operational'),('5bf26c667bf49f8b68e3e4b956','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','87dccf0ef6d17c0b175921d5ed',1000000.00,12,1,NULL,'{\"amount\":1000000,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":800000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":200000,\"installmentAmount\":75261,\"totalPayment\":903132,\"profit\":103132,\"walletCharge\":765996,\"guaranteeCheckPrincipal\":1040000,\"guaranteeCheckTotal\":1174072,\"operationCostPercent\":0.010183492470464121,\"operationCost\":20149,\"operationCostWithVat\":22164,\"netLeasingPayment\":777836,\"acceptorCommission\":20000,\"acceptorCommissionWithVat\":22000,\"acceptorPayment\":749144}','63015f7accf7ad9f74941a42643368a742f4ea6f754509839c76039515f6d754','2026-09-22 11:24:20.371997','v1',NULL,NULL,'operational'),('80e3bf57a3b163d6647f872299','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','87dccf0ef6d17c0b175921d5ed',3456789.00,12,1,NULL,'{\"amount\":3456789,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":2765431,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":691358,\"installmentAmount\":260162,\"totalPayment\":3121944,\"profit\":356513,\"walletCharge\":2647893,\"guaranteeCheckPrincipal\":3595061,\"guaranteeCheckTotal\":4058527,\"operationCostPercent\":0.010183492470464194,\"operationCost\":69645,\"operationCostWithVat\":76610,\"netLeasingPayment\":2688822,\"acceptorCommission\":69136,\"acceptorCommissionWithVat\":76049,\"acceptorPayment\":2589640}','1a4b8347cff05342684099fe6b123f5cb626bc7766003f445e1d5e3e950e703e','2026-09-22 14:27:38.472560','v1',NULL,NULL,'operational'),('8178d39cb851fa10271267965d','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','ebb526505d8694dd5e951df25f',1000000000.00,12,1,NULL,'{\"amount\":1000000000,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":800000000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":200000000,\"installmentAmount\":75261057,\"totalPayment\":903132684,\"profit\":103132684,\"walletCharge\":765998524,\"guaranteeCheckPrincipal\":1040000000,\"guaranteeCheckTotal\":1174072489,\"operationCostPercent\":0.010183492470464408,\"operationCost\":20146796,\"operationCostWithVat\":22161476,\"netLeasingPayment\":777838524,\"acceptorCommission\":20000000,\"acceptorCommissionWithVat\":22000000,\"acceptorPayment\":749146557}','b418c32cc51580c9cc82fa91aa580e86ee6bf9028be01f5cadb6941f0ce9c227','2026-09-28 13:32:16.493976','v1',NULL,NULL,'operational'),('a23d51e3fcff1ec7513afd35f7','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','ebb526505d8694dd5e951df25f',524779090.00,12,1,NULL,'{\"amount\":524779090,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":419823272,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":104955818,\"installmentAmount\":39495429,\"totalPayment\":473945148,\"profit\":54121876,\"walletCharge\":401980008,\"guaranteeCheckPrincipal\":545770254,\"guaranteeCheckTotal\":616128692,\"operationCostPercent\":0.010183492470464314,\"operationCost\":10572618,\"operationCostWithVat\":11629880,\"netLeasingPayment\":408193392,\"acceptorCommission\":10495582,\"acceptorCommissionWithVat\":11545140,\"acceptorPayment\":393136448}','ea049797bb0699d8bb2e85b9e333c3cc6b2b3f77f82c480b5c6c92abe3fa4ae2','2026-09-22 17:58:53.610837','v1',NULL,NULL,'operational'),('af07271acaa4ec95d209b31385','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','87dccf0ef6d17c0b175921d5ed',524779090.00,12,1,NULL,'{\"amount\":524779090,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":419823272,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":104955818,\"installmentAmount\":39495429,\"totalPayment\":473945148,\"profit\":54121876,\"walletCharge\":401980008,\"guaranteeCheckPrincipal\":545770254,\"guaranteeCheckTotal\":616128692,\"operationCostPercent\":0.010183492470464314,\"operationCost\":10572618,\"operationCostWithVat\":11629880,\"netLeasingPayment\":408193392,\"acceptorCommission\":10495582,\"acceptorCommissionWithVat\":11545140,\"acceptorPayment\":393136448}','ea049797bb0699d8bb2e85b9e333c3cc6b2b3f77f82c480b5c6c92abe3fa4ae2','2026-09-22 14:06:23.646050','v1',NULL,NULL,'operational'),('cf272af6bc291b28e245a98c3f','511c7a1202318f5f0695fcc042','c9261d80b1630a65277f36d66d','ebb526505d8694dd5e951df25f',1000000000.00,12,1,NULL,'{\"amount\":1000000000,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":800000000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":200000000,\"installmentAmount\":75261057,\"totalPayment\":903132684,\"profit\":103132684,\"walletCharge\":765998524,\"guaranteeCheckPrincipal\":1040000000,\"guaranteeCheckTotal\":1174072489,\"operationCostPercent\":0.010183492470464408,\"operationCost\":20146796,\"operationCostWithVat\":22161476,\"netLeasingPayment\":777838524,\"acceptorCommission\":20000000,\"acceptorCommissionWithVat\":22000000,\"acceptorPayment\":749146557}','b418c32cc51580c9cc82fa91aa580e86ee6bf9028be01f5cadb6941f0ce9c227','2026-09-30 12:02:46.043725','v1','{\"id\":\"511c7a1202318f5f0695fcc042\",\"code\":\"TEST\",\"name\":\"test (تامین اجتماعی)\",\"type\":\"leasing\",\"version\":3,\"version_id\":\"c9261d80b1630a65277f36d66d\",\"settings\":{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":true},\"display_fields\":[],\"required_documents\":[\"national_card\",\"birth_certificate\"],\"rules\":{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"retired\",\"pensioner\"],\"blocked_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"employer\",\"other\"]}},\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"retired\",\"pensioner\"],\"blocked_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"employer\",\"other\"]},\"conditions\":\"شرایط \\r\\nو توضیحات\",\"configuration_hash\":\"c71291b062f74587afa2826aae34ac90a5d83a65318f2dd69e0146a37f8e788c\"}','2d535c9a0735f1850d13ca1e9a6503240a99f27fbe207c27983b2c51100151e1','operational'),('f328b1fb6d65b42e289d6d1ea4','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','ebb526505d8694dd5e951df25f',524779090.00,12,1,NULL,'{\"amount\":524779090,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":419823272,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":104955818,\"installmentAmount\":39495429,\"totalPayment\":473945148,\"profit\":54121876,\"walletCharge\":401980008,\"guaranteeCheckPrincipal\":545770254,\"guaranteeCheckTotal\":616128692,\"operationCostPercent\":0.010183492470464314,\"operationCost\":10572618,\"operationCostWithVat\":11629880,\"netLeasingPayment\":408193392,\"acceptorCommission\":10495582,\"acceptorCommissionWithVat\":11545140,\"acceptorPayment\":393136448}','ea049797bb0699d8bb2e85b9e333c3cc6b2b3f77f82c480b5c6c92abe3fa4ae2','2026-09-22 17:36:20.508936','v1',NULL,NULL,'operational'),('f700cdf051f0977d36b8261c79','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','ebb526505d8694dd5e951df25f',1000000000.00,12,1,NULL,'{\"amount\":1000000000,\"installments\":12,\"interval\":1,\"commission_override\":null}','{\"loanAmount\":800000000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":200000000,\"installmentAmount\":75261057,\"totalPayment\":903132684,\"profit\":103132684,\"walletCharge\":765998524,\"guaranteeCheckPrincipal\":1040000000,\"guaranteeCheckTotal\":1174072489,\"operationCostPercent\":0.010183492470464408,\"operationCost\":20146796,\"operationCostWithVat\":22161476,\"netLeasingPayment\":777838524,\"acceptorCommission\":20000000,\"acceptorCommissionWithVat\":22000000,\"acceptorPayment\":749146557}','b418c32cc51580c9cc82fa91aa580e86ee6bf9028be01f5cadb6941f0ce9c227','2026-09-28 13:20:01.807298','v1',NULL,NULL,'operational'),('f906980137cf88099203c32084','511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','87dccf0ef6d17c0b175921d5ed',300000000.00,12,1,1.0000,'{\"amount\":300000000,\"installments\":12,\"interval\":1,\"commission_override\":1}','{\"loanAmount\":240000000,\"installmentCount\":12,\"numberOfInstallments\":12,\"prepayment\":60000000,\"installmentAmount\":22578317,\"totalPayment\":270939804,\"profit\":30939804,\"walletCharge\":229799556,\"guaranteeCheckPrincipal\":312000000,\"guaranteeCheckTotal\":352221745,\"operationCostPercent\":0.010183492470464343,\"operationCost\":6044040,\"operationCostWithVat\":6648444,\"netLeasingPayment\":233351556,\"acceptorCommission\":3000000,\"acceptorCommissionWithVat\":3300000,\"acceptorPayment\":227271761}','e919716d6999f0580107531939b0cbed16cea1c28173c6ddfca5a0f4684b2719','2026-09-26 13:35:57.600490','v1',NULL,NULL,'operational');
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
  `legacy_id` bigint(20) unsigned DEFAULT NULL,
  `applicant_user_id` char(26) DEFAULT NULL,
  `request_type` varchar(32) NOT NULL DEFAULT 'individual',
  `status` varchar(32) NOT NULL DEFAULT 'submitted',
  `contract_token_hash` char(64) DEFAULT NULL,
  `contract_signed_status` varchar(24) NOT NULL DEFAULT 'pending',
  `contract_signed_at` datetime(6) DEFAULT NULL,
  `contract_expires_at` datetime(6) DEFAULT NULL,
  `otp_hash` varchar(255) DEFAULT NULL,
  `otp_expires_at` datetime(6) DEFAULT NULL,
  `data_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data_json`)),
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cooperation_requests_no` (`request_no`),
  KEY `idx_cooperation_requests_status_type` (`status`,`request_type`),
  KEY `idx_cooperation_requests_applicant` (`applicant_user_id`,`created_at`),
  KEY `idx_cooperation_requests_legacy` (`legacy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cooperation_requests`
--

LOCK TABLES `cooperation_requests` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `credit_checks`
--

DROP TABLE IF EXISTS `credit_checks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `credit_checks` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `provider` varchar(64) NOT NULL,
  `external_report_id` varchar(255) DEFAULT NULL,
  `status` varchar(32) NOT NULL,
  `score` int(11) DEFAULT NULL,
  `risk` varchar(64) DEFAULT NULL,
  `result_summary_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result_summary_json`)),
  `response_hash` char(64) DEFAULT NULL,
  `requested_by_user_id` char(26) DEFAULT NULL,
  `requested_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `verification_request_id` char(26) DEFAULT NULL,
  `proof_hash` char(64) DEFAULT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_credit_checks_request` (`request_id`,`requested_at`),
  KEY `idx_credit_checks_external` (`provider`,`external_report_id`),
  KEY `idx_credit_checks_status` (`request_id`,`status`,`completed_at`),
  KEY `idx_credit_checks_verification_request` (`verification_request_id`),
  CONSTRAINT `fk_credit_checks_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credit_checks`
--

LOCK TABLES `credit_checks` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `financial_receipts`
--

DROP TABLE IF EXISTS `financial_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `financial_receipts` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
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
  KEY `idx_financial_receipts_document` (`document_id`),
  CONSTRAINT `fk_financial_receipts_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
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
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_financier_payment_history_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_financier_payment_history_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `financier_payment_history`
--

LOCK TABLES `financier_payment_history` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `grant_records`
--

DROP TABLE IF EXISTS `grant_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `grant_records` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `grant_status` varchar(32) NOT NULL DEFAULT 'recorded',
  `loan_number` varchar(190) DEFAULT NULL,
  `contract_number` varchar(190) DEFAULT NULL,
  `granted_amount` decimal(20,2) NOT NULL,
  `granted_at` datetime(6) NOT NULL,
  `recorded_by_user_id` char(26) NOT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grant_records_request` (`request_id`),
  KEY `idx_grant_records_granted_at` (`granted_at`),
  CONSTRAINT `fk_grant_records_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `grant_records`
--

LOCK TABLES `grant_records` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `installment_plan_versions`
--

DROP TABLE IF EXISTS `installment_plan_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_plan_versions` (
  `id` char(26) NOT NULL,
  `plan_id` char(26) NOT NULL,
  `version` int(10) unsigned NOT NULL,
  `type` varchar(20) NOT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`settings_json`)),
  `display_fields_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`display_fields_json`)),
  `required_documents_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_documents_json`)),
  `conditions_text` text DEFAULT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`rules_json`)),
  `eligibility_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligibility_json`)),
  `configuration_hash` char(64) DEFAULT NULL,
  `published_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_plan_version` (`plan_id`,`version`),
  KEY `idx_installment_plan_versions_plan` (`plan_id`),
  KEY `idx_plan_versions_hash` (`configuration_hash`),
  CONSTRAINT `fk_installment_plan_versions_plan` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_plan_versions`
--

LOCK TABLES `installment_plan_versions` WRITE;
INSERT INTO `installment_plan_versions` VALUES ('57da1733f0445e518bbd145a02','511c7a1202318f5f0695fcc042',1,'leasing','{\"min_amount\":100000000,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":false}','[]','[\"national_card\",\"birth_certificate\",\"portrait\",\"employment_certificate\",\"business_license\",\"payslip\",\"bank_statement\",\"tax_return\",\"primary_check\",\"secondary_check\"]','شرایط \r\nو توضیحات','87dccf0ef6d17c0b175921d5ed','2026-09-22 10:52:38.635456','{\"visibility_contexts\": [\"merchant_request\", \"applicant_request\", \"calculator\"], \"merchant_access_mode\": \"all\", \"occupation_documents_enabled\": false, \"secondary_check_required\": true, \"check_images_required\": true, \"requires_bank_validation\": \"false\", \"calculation_schema_version\": \"v1\"}','{}',NULL,NULL),('7c8417965db56d8b24ab2e310f','511c7a1202318f5f0695fcc042',2,'leasing','{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":false}','[]','[\"national_card\",\"birth_certificate\",\"portrait\",\"employment_certificate\",\"business_license\",\"payslip\",\"bank_statement\",\"tax_return\",\"primary_check\",\"secondary_check\"]','شرایط \r\nو توضیحات','87dccf0ef6d17c0b175921d5ed','2026-09-22 11:17:52.408917','{\"visibility_contexts\": [\"merchant_request\", \"applicant_request\", \"calculator\"], \"merchant_access_mode\": \"all\", \"occupation_documents_enabled\": false, \"secondary_check_required\": true, \"check_images_required\": true, \"requires_bank_validation\": \"false\", \"calculation_schema_version\": \"v1\"}','{}',NULL,NULL),('c9261d80b1630a65277f36d66d','511c7a1202318f5f0695fcc042',3,'leasing','{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":true}','[]','[\"national_card\",\"birth_certificate\"]','شرایط \r\nو توضیحات','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:00:30.590841','{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"retired\",\"pensioner\"],\"blocked_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"employer\",\"other\"]}}','{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"retired\",\"pensioner\"],\"blocked_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"employer\",\"other\"]}','c71291b062f74587afa2826aae34ac90a5d83a65318f2dd69e0146a37f8e788c',NULL),('d3c3f4c27ef7a0e24534d90f3e','511c7a1202318f5f0695fcc042',5,'leasing','{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":true,\"requires_identity_verification\":true}','[]','[\"national_card\",\"birth_certificate\"]','شرایط \r\nو توضیحات','87dccf0ef6d17c0b175921d5ed','2026-10-06 11:43:45.798099','{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"requires_identity_verification\":true,\"contract_mode\":\"physical\",\"loan_application_contract_enabled\":true,\"facility_agreement_contract_enabled\":true,\"loan_application_template_code\":\"loan_application_default\",\"facility_agreement_template_code\":\"facility_agreement_default\",\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}}','{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}','8731aae4ae1351ac53093cd3be156abfca177cb57c34143ce946afff48af27b5',NULL),('dd841f21e99c4d76d83054e524','511c7a1202318f5f0695fcc042',4,'leasing','{\"min_amount\":100,\"max_amount\":1000000000,\"supplier_costs_percent\":1,\"intermediary_deductions_percent\":0.47999999999999998,\"babanovel_deductions_percent\":1,\"insurance_percent\":0.5,\"guarantee_check_percent\":130,\"acceptor_commission_percent\":2,\"additional_deductions_percent\":0,\"acceptor_deductions_amount\":0,\"vat_percent\":10,\"interest_rate\":23,\"effective_interest_rate\":25,\"credit_share_percent\":80,\"min_interval\":1,\"max_interval\":3,\"other_deductions_amount\":2,\"active_installments\":[12,18,24,36],\"requires_bank_validation\":true}','[]','[\"national_card\",\"birth_certificate\"]','شرایط \r\nو توضیحات','87dccf0ef6d17c0b175921d5ed','2026-09-30 12:08:44.219460','{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}}','{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}','9f50f3e47fac8c526136c918b2b35da34768576771685672520342da4c01da48',NULL);
UNLOCK TABLES;

--
-- Table structure for table `installment_plans`
--

DROP TABLE IF EXISTS `installment_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_plans` (
  `id` char(26) NOT NULL,
  `code` varchar(80) NOT NULL,
  `name` varchar(190) NOT NULL,
  `type` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `current_version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `legacy_id` bigint(20) unsigned DEFAULT NULL,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `eligibility_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligibility_json`)),
  `archived_at` datetime(6) DEFAULT NULL,
  `published_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_plans_code` (`code`),
  KEY `idx_installment_plans_status_type` (`status`,`type`),
  KEY `idx_installment_plans_display_order` (`display_order`,`status`),
  KEY `idx_installment_plans_legacy_id` (`legacy_id`),
  KEY `idx_installment_plans_order_status` (`display_order`,`status`,`updated_at`),
  CONSTRAINT `chk_installment_plans_status` CHECK (`status` in ('draft','active','inactive','archived'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_plans`
--

LOCK TABLES `installment_plans` WRITE;
INSERT INTO `installment_plans` VALUES ('511c7a1202318f5f0695fcc042','TEST','test (تامین اجتماعی)','leasing','active',5,'87dccf0ef6d17c0b175921d5ed','2026-09-22 10:52:38.619458','2026-10-06 11:43:45.801492',NULL,0,'{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}',NULL,'2026-09-22 13:40:19.784906');
UNLOCK TABLES;

--
-- Table structure for table `installment_schedule`
--

DROP TABLE IF EXISTS `installment_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `installment_schedule` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `sequence_no` int(10) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(20,2) NOT NULL,
  `principal_amount` decimal(20,2) NOT NULL,
  `profit_amount` decimal(20,2) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'scheduled',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installment_schedule_request_sequence` (`request_id`,`sequence_no`),
  KEY `idx_installment_schedule_due_date` (`due_date`,`status`),
  CONSTRAINT `fk_installment_schedule_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `installment_schedule`
--

LOCK TABLES `installment_schedule` WRITE;
INSERT INTO `installment_schedule` VALUES ('054a99bc0b0c0f658480682f14','9b9243d8f4aa2bca09f4154605',6,'2027-02-20',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.543535'),('0a698e4116c9e2c8d27426e7dd','5b5a48a363b59fd162007717e8',10,'2017-02-08',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.438130'),('0ab5271b7bf5828d59557feccf','9b9243d8f4aa2bca09f4154605',1,'2026-09-23',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.537180'),('0b9c194549ccbd551786fbdcc5','5b5a48a363b59fd162007717e8',1,'2016-05-09',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.434623'),('13c97d3c68609d34f9fa39c10b','5b5a48a363b59fd162007717e8',4,'2016-08-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.436890'),('144afb6ad64729c0b5acdbbc8c','9b9243d8f4aa2bca09f4154605',11,'2027-07-23',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.545031'),('15af58a467d010324749228b57','9b9243d8f4aa2bca09f4154605',4,'2026-12-22',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.539658'),('1a74efbfdecf9a5a60d4bed072','4bd71ad567b6fda244404247c3',1,'2026-11-22',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.388465'),('1f480a914db94523bf3ff98794','6e671a9b6402b37dc337fa07e3',11,'2027-07-23',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.663542'),('1fd14abb46079602e0bb6bf2c9','9b9243d8f4aa2bca09f4154605',3,'2026-11-22',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.539387'),('2e9c52f375ee9493f05bb3683a','9b9243d8f4aa2bca09f4154605',10,'2027-06-22',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.544799'),('376be33b5d54e8bf07b861b808','4bd71ad567b6fda244404247c3',6,'2027-04-21',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.391256'),('38e4a98e91192b718c5f02ece7','4bd71ad567b6fda244404247c3',11,'2027-09-23',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.397550'),('3f31ada141be83cec01481e313','5b5a48a363b59fd162007717e8',7,'2016-11-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.437521'),('4514725e55b70d512eb8a89d38','6e671a9b6402b37dc337fa07e3',8,'2027-04-21',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.662847'),('47791c536ff92185fb977afdaa','9b9243d8f4aa2bca09f4154605',8,'2027-04-21',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.544052'),('4800ce3a7bf0a1b333f53ea396','9b9243d8f4aa2bca09f4154605',12,'2027-08-23',75261057.00,66666663.00,8594394.00,'scheduled','2026-09-28 13:32:16.545312'),('5251aebb89e0552a837ec76bb7','5b5a48a363b59fd162007717e8',8,'2016-12-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.437707'),('526e99d8408c8325d8f5941e32','9b9243d8f4aa2bca09f4154605',9,'2027-05-22',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.544520'),('54f08f66faef79638c0822546c','4bd71ad567b6fda244404247c3',9,'2027-07-23',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.397012'),('554485b08997ea634b868c70f8','4bd71ad567b6fda244404247c3',5,'2027-03-21',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.391028'),('56edf1b3880abf22b2ebcda352','6e671a9b6402b37dc337fa07e3',7,'2027-03-21',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.662614'),('581bb2a59f2ad434fa94e1485a','4bd71ad567b6fda244404247c3',10,'2027-08-23',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.397266'),('5e065e3c1a2e8f76918a394221','6e671a9b6402b37dc337fa07e3',9,'2027-05-22',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.663066'),('5eda0c6130bbeb9ee49004be07','6e671a9b6402b37dc337fa07e3',5,'2027-01-21',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.662138'),('61fb17d0bd10178e67dcc39860','5b5a48a363b59fd162007717e8',2,'2016-06-09',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.436407'),('61fefb8c56f32e5ae726b3a8f7','5b5a48a363b59fd162007717e8',12,'2017-04-09',75261.00,66663.00,8598.00,'scheduled','2026-09-22 11:24:20.438538'),('7103d4327722713e2b8e21606b','6e671a9b6402b37dc337fa07e3',4,'2026-12-22',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.661878'),('7414d2435debbe937ddea6c9f1','5b5a48a363b59fd162007717e8',9,'2017-01-09',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.437920'),('7de138a57bc2a873e9804d6057','5b5a48a363b59fd162007717e8',11,'2017-03-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.438327'),('80a54d576e8181beeb4e6092b9','4bd71ad567b6fda244404247c3',7,'2027-05-22',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.391506'),('83c24edb63f34c913fe400c384','6e671a9b6402b37dc337fa07e3',6,'2027-02-20',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.662377'),('86e0c35c4742cefe46338e363a','4bd71ad567b6fda244404247c3',12,'2027-10-23',75261.00,66663.00,8598.00,'scheduled','2026-10-30 00:17:58.397782'),('87bc91ce03c0e7b7afe33eb37a','6e671a9b6402b37dc337fa07e3',1,'2026-09-23',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.657311'),('8fa2b27a8b4685fd57bc1cead6','9b9243d8f4aa2bca09f4154605',7,'2027-03-21',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.543773'),('9b93c7f84cfc6c98193596fa69','4bd71ad567b6fda244404247c3',8,'2027-06-22',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.396643'),('9d3e38fdf58363cb8cc3d298e3','4bd71ad567b6fda244404247c3',3,'2027-01-21',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.390310'),('ac7fc4fc4f1f8367263743f15e','4bd71ad567b6fda244404247c3',4,'2027-02-20',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.390548'),('b1854c025f4c5a2aa0493e81b0','9b9243d8f4aa2bca09f4154605',2,'2026-10-23',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.539117'),('b2c17119bc3d89a0300b906b39','6e671a9b6402b37dc337fa07e3',2,'2026-10-23',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.660597'),('b6b37c894e922f191e67e764ac','6e671a9b6402b37dc337fa07e3',10,'2027-06-22',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.663298'),('cc47de1f4c2e9e462b48d04238','6e671a9b6402b37dc337fa07e3',3,'2026-11-22',39495429.00,34985273.00,4510156.00,'scheduled','2026-09-22 14:06:23.660857'),('d1aa59f30ffce96329d9a29784','4bd71ad567b6fda244404247c3',2,'2026-12-22',75261.00,66667.00,8594.00,'scheduled','2026-10-30 00:17:58.390001'),('d7537616274f67ea5ced48374f','5b5a48a363b59fd162007717e8',3,'2016-07-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.436676'),('e43d5779a1461f7564b9fa550f','9b9243d8f4aa2bca09f4154605',5,'2027-01-21',75261057.00,66666667.00,8594390.00,'scheduled','2026-09-28 13:32:16.543149'),('eb24f81229d028b7d43d099737','6e671a9b6402b37dc337fa07e3',12,'2027-08-23',39495429.00,34985269.00,4510160.00,'scheduled','2026-09-22 14:06:23.663776'),('f6c4ea27907885757272c962b3','5b5a48a363b59fd162007717e8',6,'2016-10-11',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.437296'),('f966f9f51b929b015be52e0d91','5b5a48a363b59fd162007717e8',5,'2016-09-10',75261.00,66667.00,8594.00,'scheduled','2026-09-22 11:24:20.437088');
UNLOCK TABLES;

--
-- Table structure for table `integration_outbox`
--

DROP TABLE IF EXISTS `integration_outbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `integration_outbox` (
  `id` char(26) NOT NULL,
  `event_type` varchar(100) NOT NULL,
  `aggregate_type` varchar(64) NOT NULL,
  `aggregate_id` char(26) DEFAULT NULL,
  `destination` varchar(100) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `payload_hash` char(64) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `next_attempt_at` datetime(6) DEFAULT NULL,
  `last_http_code` int(11) DEFAULT NULL,
  `last_error` varchar(1000) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `delivered_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_integration_outbox_delivery` (`status`,`next_attempt_at`,`created_at`),
  KEY `idx_integration_outbox_aggregate` (`aggregate_type`,`aggregate_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `integration_outbox`
--

LOCK TABLES `integration_outbox` WRITE;
INSERT INTO `integration_outbox` VALUES ('296bf2cbb5795b68bc2b664c4e','verification.requested','verification_request','dde13130bdb631917a16ec3bc3','integrations','{\"verification_id\":\"dde13130bdb631917a16ec3bc3\",\"request_id\":\"6e671a9b6402b37dc337fa07e3\",\"verification_type\":\"identity_match\",\"provider\":\"shahkar\"}','1fc8aed0c1c853f574ec69da8ef169b4e40535e24e7717b0e3c72b532e3e9cc4','pending',0,NULL,NULL,NULL,'2026-10-06 15:22:24.460837',NULL),('3c9df38ffc9f5ce0c1cf6da38c','request.status_changed','loan_request','9b9243d8f4aa2bca09f4154605','notifications','{\"request_id\":\"9b9243d8f4aa2bca09f4154605\",\"from\":\"under_review\",\"to\":\"need_correction\",\"reason\":\"مدارک بارگذاری شود\",\"source\":\"api\"}','03df79a7b2a470f0410fc12833221d20ca817c6108c00683d2d2ffccb801d2ab','pending',0,NULL,NULL,NULL,'2026-09-30 12:59:08.349865',NULL);
UNLOCK TABLES;

--
-- Table structure for table `loan_profile_corrections`
--

DROP TABLE IF EXISTS `loan_profile_corrections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_profile_corrections` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `reason` varchar(1000) NOT NULL,
  `changed_fields_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`changed_fields_json`)),
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`after_json`)),
  `row_version_before` bigint(20) unsigned NOT NULL,
  `row_version_after` bigint(20) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_loan_profile_corrections_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_loan_profile_corrections_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_profile_corrections`
--

LOCK TABLES `loan_profile_corrections` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `loan_request_assignments`
--

DROP TABLE IF EXISTS `loan_request_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_request_assignments` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `assignment_type` varchar(32) NOT NULL,
  `assigned_user_id` char(26) NOT NULL,
  `assigned_by_user_id` char(26) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'active',
  `assigned_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `released_at` datetime(6) DEFAULT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_request_assignments_request` (`request_id`,`assignment_type`,`status`),
  KEY `idx_request_assignments_user` (`assigned_user_id`,`status`,`assigned_at`),
  CONSTRAINT `fk_request_assignments_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_request_assignments`
--

LOCK TABLES `loan_request_assignments` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `loan_request_events`
--

DROP TABLE IF EXISTS `loan_request_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_request_events` (
  `id` char(26) NOT NULL,
  `request_id` char(26) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `result` varchar(24) NOT NULL,
  `details_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_loan_request_events_request` (`request_id`,`created_at`),
  KEY `idx_loan_request_events_type` (`event_type`,`created_at`),
  CONSTRAINT `fk_loan_request_events_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_request_events`
--

LOCK TABLES `loan_request_events` WRITE;
INSERT INTO `loan_request_events` VALUES ('01d84fb17a1274a796b8bae358','4bd71ad567b6fda244404247c3','request.created','87dccf0ef6d17c0b175921d5ed','allowed','{\"request_id\":\"panel_8734bb1cf197ffcdebb9f835\",\"submitted\":false,\"plan_version_id\":\"d3c3f4c27ef7a0e24534d90f3e\"}','2026-10-30 00:17:58.398466'),('04dd7147c1e524db00f2ead7a0','4bd71ad567b6fda244404247c3','request.updated','87dccf0ef6d17c0b175921d5ed','allowed','{\"request_id\":\"panel_e2242de8cfb2f84e80927229\",\"fields\":[\"full_name\",\"father_name\",\"certificate_number\",\"national_code\",\"mobile_number\",\"phone_number\",\"birth_date\",\"occupation\",\"residential_address\",\"postal_code\",\"work_address\",\"product_name\",\"merchant_ref\",\"assigned_bank_user_id\"]}','2026-10-30 22:52:22.392460'),('128e6c9a4a9bd70d66f6400ad8','6e671a9b6402b37dc337fa07e3','request.created','87dccf0ef6d17c0b175921d5ed','allowed','{\"submitted\":true,\"plan_version_id\":\"7c8417965db56d8b24ab2e310f\"}','2026-09-22 14:06:23.664599'),('1842451a440dd706a742c18d06','9b9243d8f4aa2bca09f4154605','request.transition','0f1652c3528f92bc3f95edd9ab','allowed','{\"request_id\":\"panel_c327ba67e1f4b39ec19b5308\",\"from\":\"under_review\",\"to\":\"need_correction\",\"reason\":\"مدارک بارگذاری شود\",\"source\":\"api\",\"assigned_bank_user_changed\":true}','2026-09-30 12:59:08.340890'),('1851c751e9ce62e36cf2c4023f','9b9243d8f4aa2bca09f4154605','request.internal_note.created','0f1652c3528f92bc3f95edd9ab','allowed','{\"request_id\":\"panel_c4fd48caf0e6145d4a023b13\",\"comment_id\":\"2942c10d69aff29db8a49ae41a\"}','2026-09-30 12:58:40.864195'),('3f98d17e40f45c14105d4d8942','5b5a48a363b59fd162007717e8','request.created','87dccf0ef6d17c0b175921d5ed','allowed','{\"submitted\":false,\"plan_version_id\":\"7c8417965db56d8b24ab2e310f\"}','2026-09-22 11:24:20.439511'),('b9633620dd9d8b8a11246d63c5','9b9243d8f4aa2bca09f4154605','request.created','ebb526505d8694dd5e951df25f','allowed','{\"submitted\":true,\"plan_version_id\":\"7c8417965db56d8b24ab2e310f\"}','2026-09-28 13:32:16.550507'),('ce02bd17250af44bf74454682c','6e671a9b6402b37dc337fa07e3','request.verification.queued','87dccf0ef6d17c0b175921d5ed','allowed','{\"request_id\":\"panel_d68fe2f41e3ec49d64812057\",\"verification_id\":\"dde13130bdb631917a16ec3bc3\",\"verification_type\":\"identity_match\",\"provider\":\"shahkar\",\"request_hash\":\"dbbb9ee0843cc6421fbccbc36f025fb231a29927ec8a441e7baab46aa3cd7d98\"}','2026-10-06 15:22:24.453067'),('f9446cd07c1ad482b2e1b92cbe','9b9243d8f4aa2bca09f4154605','request.message.created','0f1652c3528f92bc3f95edd9ab','allowed','{\"request_id\":\"panel_da0ad68bcc2ef40f8acca0d1\",\"message_id\":\"ba3244704508124b62d16ce373\",\"attachment_count\":0}','2026-09-30 12:58:26.270539');
UNLOCK TABLES;

--
-- Table structure for table `loan_request_guarantors`
--

DROP TABLE IF EXISTS `loan_request_guarantors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_request_guarantors` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `full_name` varchar(190) NOT NULL,
  `national_code` varchar(10) DEFAULT NULL,
  `mobile_number` varchar(11) DEFAULT NULL,
  `relation_label` varchar(100) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `position_no` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `removed_at` datetime(6) DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_guarantors_request` (`request_id`),
  KEY `idx_guarantors_active_position` (`request_id`,`status`,`position_no`),
  CONSTRAINT `fk_guarantors_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_request_guarantors`
--

LOCK TABLES `loan_request_guarantors` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `loan_request_status_history`
--

DROP TABLE IF EXISTS `loan_request_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_request_status_history` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `actor_user_id` char(26) NOT NULL,
  `reason` text DEFAULT NULL,
  `source` varchar(80) NOT NULL DEFAULT 'api',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_loan_request_status_history_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_loan_request_status_history_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_request_status_history`
--

LOCK TABLES `loan_request_status_history` WRITE;
INSERT INTO `loan_request_status_history` VALUES ('0fb3185502c14348107047b671','6e671a9b6402b37dc337fa07e3','submitted','under_review','87dccf0ef6d17c0b175921d5ed','ورود به صف بررسی','api','2026-09-22 14:06:23.656627'),('260fb0734285337cbbab95ce37','9b9243d8f4aa2bca09f4154605','under_review','need_correction','0f1652c3528f92bc3f95edd9ab','مدارک بارگذاری شود','api','2026-09-30 12:59:08.340075'),('3491d558115521140277385a00','9b9243d8f4aa2bca09f4154605','draft','submitted','ebb526505d8694dd5e951df25f','ثبت نهایی پرونده','api','2026-09-28 13:32:16.531763'),('3eba11e1043f03a5e3488b556c','9b9243d8f4aa2bca09f4154605','submitted','under_review','ebb526505d8694dd5e951df25f','ورود به صف بررسی','api','2026-09-28 13:32:16.533603'),('6a3ac0b20554331da1e0b82899','4bd71ad567b6fda244404247c3',NULL,'draft','87dccf0ef6d17c0b175921d5ed','ایجاد پرونده','api','2026-10-30 00:17:58.386395'),('79463b8fd0980ab9be5746bb88','6e671a9b6402b37dc337fa07e3','draft','submitted','87dccf0ef6d17c0b175921d5ed','ثبت نهایی پرونده','api','2026-09-22 14:06:23.656376'),('c77dab6de4900b6d8bfb292eec','5b5a48a363b59fd162007717e8',NULL,'draft','87dccf0ef6d17c0b175921d5ed','ایجاد پرونده','api','2026-09-22 11:24:20.426119'),('c8026b393a83d7bc395d50e766','9b9243d8f4aa2bca09f4154605',NULL,'draft','ebb526505d8694dd5e951df25f','ایجاد پرونده','api','2026-09-28 13:32:16.524456'),('f7e9b59a6963c57d74a8e288ac','6e671a9b6402b37dc337fa07e3',NULL,'draft','87dccf0ef6d17c0b175921d5ed','ایجاد پرونده','api','2026-09-22 14:06:23.655645');
UNLOCK TABLES;

--
-- Table structure for table `loan_requests`
--

DROP TABLE IF EXISTS `loan_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loan_requests` (
  `id` char(26) NOT NULL,
  `request_no` varchar(32) NOT NULL,
  `idempotency_key` varchar(128) DEFAULT NULL,
  `applicant_user_id` char(26) DEFAULT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `merchant_ref` varchar(190) DEFAULT NULL,
  `assigned_bank_user_id` char(26) DEFAULT NULL,
  `plan_id` char(26) NOT NULL,
  `plan_version_id` char(26) NOT NULL,
  `calculation_run_id` char(26) DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'draft',
  `full_name` varchar(190) DEFAULT NULL,
  `father_name` varchar(190) DEFAULT NULL,
  `certificate_number` varchar(80) DEFAULT NULL,
  `national_code` varchar(10) DEFAULT NULL,
  `mobile_number` varchar(11) DEFAULT NULL,
  `phone_number` varchar(32) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `occupation` varchar(190) DEFAULT NULL,
  `residential_address` text DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `work_address` text DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `order_items_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`order_items_json`)),
  `order_total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `requested_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `facility_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `prepayment` decimal(20,2) NOT NULL DEFAULT 0.00,
  `wallet_charge` decimal(20,2) NOT NULL DEFAULT 0.00,
  `acceptor_payment_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `installment_interval` int(10) unsigned NOT NULL DEFAULT 1,
  `installment_count` int(10) unsigned NOT NULL DEFAULT 0,
  `installment_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `total_payment` decimal(20,2) NOT NULL DEFAULT 0.00,
  `profit` decimal(20,2) NOT NULL DEFAULT 0.00,
  `guarantee_check_principal` decimal(20,2) NOT NULL DEFAULT 0.00,
  `guarantee_check_total` decimal(20,2) NOT NULL DEFAULT 0.00,
  `first_due_date` date DEFAULT NULL,
  `submitted_at` datetime(6) DEFAULT NULL,
  `granted_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `legacy_id` bigint(20) unsigned DEFAULT NULL,
  `acceptor_user_id` char(26) DEFAULT NULL,
  `request_origin` varchar(30) NOT NULL DEFAULT 'admin',
  `acceptor_assignment_mode` varchar(30) NOT NULL DEFAULT '',
  `marketplace_status` varchar(30) NOT NULL DEFAULT '',
  `marketplace_claimed_by_user_id` char(26) DEFAULT NULL,
  `marketplace_claimed_at` datetime(6) DEFAULT NULL,
  `requested_service_scope` varchar(30) NOT NULL DEFAULT '',
  `requested_cities_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requested_cities_json`)),
  `requested_location_terms_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requested_location_terms_json`)),
  `requested_activity_categories_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`requested_activity_categories_json`)),
  `request_public_summary` text DEFAULT NULL,
  `acceptor_invitation_code` varchar(100) DEFAULT NULL,
  `acceptor_invited_by_user_id` char(26) DEFAULT NULL,
  `acceptor_invitation_locked` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_operations_user_id` char(26) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `final_settlement_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `leasing_deposit_amount` decimal(20,2) DEFAULT NULL,
  `settlement_status` varchar(32) NOT NULL DEFAULT 'not_started',
  `settlement_cycle_id` char(26) DEFAULT NULL,
  `settlement_date` datetime(6) DEFAULT NULL,
  `document_receipt_status` varchar(32) NOT NULL DEFAULT 'unreviewed',
  `document_received_at` datetime(6) DEFAULT NULL,
  `bank_account_number` varchar(80) DEFAULT NULL,
  `bank_payment_amount` decimal(20,2) NOT NULL DEFAULT 0.00,
  `bank_payment_at` datetime(6) DEFAULT NULL,
  `bank_settlement_status` varchar(32) NOT NULL DEFAULT '',
  `bank_settlement_note` text DEFAULT NULL,
  `bank_settled_at` datetime(6) DEFAULT NULL,
  `bank_settled_by_user_id` char(26) DEFAULT NULL,
  `bank_settlement_updated_at` datetime(6) DEFAULT NULL,
  `credit_report_external_id` varchar(255) DEFAULT NULL,
  `credit_score` int(11) DEFAULT NULL,
  `credit_risk` varchar(64) DEFAULT NULL,
  `financier_payment_status` varchar(32) NOT NULL DEFAULT 'unreviewed',
  `organization_ref` char(26) DEFAULT NULL,
  `branch_ref` char(26) DEFAULT NULL,
  `bank_organization_ref` char(26) DEFAULT NULL,
  `merchant_id` char(26) DEFAULT NULL,
  `row_version` int(10) unsigned NOT NULL DEFAULT 1,
  `last_transition_at` datetime(6) DEFAULT NULL,
  `rejected_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `archived_at` datetime(6) DEFAULT NULL,
  `document_receipt_note` varchar(1000) DEFAULT NULL,
  `document_received_by_user_id` char(26) DEFAULT NULL,
  `settlement_updated_by_user_id` char(26) DEFAULT NULL,
  `financier_payment_note` varchar(1000) DEFAULT NULL,
  `financier_payment_updated_at` datetime(6) DEFAULT NULL,
  `financier_payment_updated_by_user_id` char(26) DEFAULT NULL,
  `required_documents_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_documents_snapshot_json`)),
  `plan_rules_snapshot_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`plan_rules_snapshot_json`)),
  `plan_snapshot_hash` char(64) DEFAULT NULL,
  `credit_validation_status` varchar(24) NOT NULL DEFAULT 'not_required',
  `credit_validation_provider` varchar(64) DEFAULT NULL,
  `credit_validation_reference` varchar(255) DEFAULT NULL,
  `credit_validation_verified_at` datetime(6) DEFAULT NULL,
  `credit_validation_check_id` char(26) DEFAULT NULL,
  `identity_verification_status` varchar(24) NOT NULL DEFAULT 'not_required',
  `identity_verification_provider` varchar(64) DEFAULT NULL,
  `identity_verification_reference` varchar(255) DEFAULT NULL,
  `identity_verification_verified_at` datetime(6) DEFAULT NULL,
  `identity_verification_request_id` char(26) DEFAULT NULL,
  `bank_settlement_cycle_id` char(26) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_loan_requests_no` (`request_no`),
  UNIQUE KEY `uq_loan_requests_idempotency` (`created_by_user_id`,`idempotency_key`),
  KEY `idx_loan_requests_status` (`status`,`updated_at`),
  KEY `idx_loan_requests_national` (`national_code`,`created_at`),
  KEY `idx_loan_requests_plan` (`plan_id`,`plan_version_id`),
  KEY `idx_loan_requests_bank_user` (`assigned_bank_user_id`,`status`),
  KEY `fk_loan_requests_version` (`plan_version_id`),
  KEY `fk_loan_requests_calculation` (`calculation_run_id`),
  KEY `idx_loan_requests_created_by` (`created_by_user_id`,`updated_at`),
  KEY `idx_loan_requests_applicant` (`applicant_user_id`,`updated_at`),
  KEY `idx_loan_requests_legacy_id` (`legacy_id`),
  KEY `idx_loan_requests_acceptor` (`acceptor_user_id`,`updated_at`),
  KEY `idx_loan_requests_origin` (`request_origin`,`updated_at`),
  KEY `idx_loan_requests_marketplace` (`marketplace_status`,`marketplace_claimed_by_user_id`),
  KEY `idx_loan_requests_operations` (`assigned_operations_user_id`,`status`),
  KEY `idx_loan_requests_settlement` (`settlement_status`,`settlement_date`),
  KEY `idx_loan_requests_settlement_cycle` (`settlement_cycle_id`),
  KEY `idx_loan_requests_bank_settlement` (`bank_settlement_status`,`bank_settled_at`),
  KEY `idx_loan_requests_credit_report` (`credit_report_external_id`),
  KEY `idx_loan_requests_org` (`organization_ref`,`status`),
  KEY `idx_loan_requests_branch` (`branch_ref`,`status`),
  KEY `idx_loan_requests_bank_org` (`bank_organization_ref`,`status`),
  KEY `idx_loan_requests_merchant_id` (`merchant_id`,`status`),
  KEY `idx_loan_requests_row_version` (`id`,`row_version`),
  KEY `idx_loan_requests_last_transition` (`last_transition_at`,`status`),
  KEY `idx_loan_requests_document_receipt` (`document_receipt_status`,`document_received_at`),
  KEY `idx_loan_requests_financier_payment` (`financier_payment_status`,`financier_payment_updated_at`),
  KEY `idx_loan_requests_credit_validation` (`credit_validation_status`,`updated_at`),
  KEY `idx_loan_requests_identity_verification` (`identity_verification_status`,`updated_at`),
  KEY `idx_bank_cycle_projection` (`bank_settlement_cycle_id`),
  CONSTRAINT `fk_loan_requests_calculation` FOREIGN KEY (`calculation_run_id`) REFERENCES `calculation_runs` (`id`),
  CONSTRAINT `fk_loan_requests_plan` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`),
  CONSTRAINT `fk_loan_requests_version` FOREIGN KEY (`plan_version_id`) REFERENCES `installment_plan_versions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loan_requests`
--

LOCK TABLES `loan_requests` WRITE;
INSERT INTO `loan_requests` VALUES ('4bd71ad567b6fda244404247c3','LN-261029-70257CB8','panel-b6e2b65c30f454a3113b76edef009c0a',NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,'511c7a1202318f5f0695fcc042','d3c3f4c27ef7a0e24534d90f3e','075f04901ae99d695cb6ab5b8c','draft','آزمون رابط خودکار — پرونده ساختگی ۱۴۰۵۰۸۰۸',NULL,NULL,NULL,NULL,NULL,'1990-01-02','official_employee',NULL,NULL,NULL,'کالای ساختگی — آزمون مرورگر','[{\"id\":\"\",\"name\":\"کالای ساختگی — آزمون مرورگر\",\"code\":\"\",\"description\":\"کالای ساختگی — آزمون مرورگر\",\"qty\":1,\"quantity\":1,\"unit\":\"\",\"price\":1000000,\"unit_price\":1000000,\"total_price\":1000000}]',1000000.00,1000000.00,800000.00,200000.00,765996.00,749144.00,1,12,75261.00,903132.00,103132.00,1040000.00,1174072.00,'2026-11-22',NULL,NULL,'2026-10-30 00:17:58.358625','2026-10-30 22:52:22.382481',NULL,NULL,'admin','','',NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0.00,NULL,'not_started',NULL,NULL,'unreviewed',NULL,NULL,0.00,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unreviewed',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'[\"national_card\",\"birth_certificate\",\"payslip\"]','{\"visibility_contexts\":[\"merchant_request\",\"applicant_request\",\"calculator\"],\"merchant_access_mode\":\"all\",\"occupation_documents_enabled\":true,\"required_documents_by_occupation\":{\"official_employee\":[\"national_card\",\"birth_certificate\",\"payslip\"],\"contract_employee\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"worker\":[\"national_card\",\"birth_certificate\",\"employment_certificate\"],\"freelancer\":[\"national_card\",\"birth_certificate\",\"business_license\"],\"retired\":[\"national_card\",\"payslip\"],\"pensioner\":[\"national_card\",\"payslip\"],\"employer\":[\"national_card\",\"birth_certificate\",\"business_license\",\"activity_license\",\"bank_statement\"],\"other\":[\"national_card\",\"birth_certificate\",\"activity_license\"]},\"secondary_check_required\":true,\"check_images_required\":true,\"requires_bank_validation\":true,\"requires_identity_verification\":true,\"contract_mode\":\"physical\",\"loan_application_contract_enabled\":true,\"facility_agreement_contract_enabled\":true,\"loan_application_template_code\":\"loan_application_default\",\"facility_agreement_template_code\":\"facility_agreement_default\",\"calculation_schema_version\":\"v1\",\"eligibility\":{\"min_age\":18,\"max_age\":77,\"allowed_occupations\":[\"official_employee\",\"contract_employee\",\"worker\",\"freelancer\",\"retired\",\"pensioner\",\"employer\"],\"blocked_occupations\":[\"other\"]}}','f17528b63203a2624dcaa62ed84045e37872a2d877f54704eb4ede96b79e2db9','pending',NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL),('5b5a48a363b59fd162007717e8','LN-260922-47E4D038','panel-100d56d41d6a169decd32bbf6a145532',NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,'511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','5bf26c667bf49f8b68e3e4b956','draft','دئو./','بلاتنمکگپ','لاتنمکگپ','فغعهخحج','غعهخحجچ','دئنمکگپ','1996-05-09',NULL,'تنمکگپ','852096396','خحجچ','حکگجپچ','[{\"id\":\"\",\"name\":\"حکگجپچ\",\"code\":\"\",\"description\":\"حکگجپچ\",\"qty\":1,\"quantity\":1,\"unit\":\"\",\"price\":1000000,\"unit_price\":1000000,\"total_price\":1000000}]',1000000.00,1000000.00,800000.00,200000.00,765996.00,749144.00,1,12,75261.00,903132.00,103132.00,1040000.00,1174072.00,'2016-05-09',NULL,NULL,'2026-09-22 11:24:20.412694','2026-09-29 15:09:37.876442',NULL,NULL,'admin','','',NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0.00,NULL,'not_started',NULL,NULL,'unreviewed',NULL,NULL,0.00,NULL,'not_recorded',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unreviewed',NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'not_required',NULL,NULL,NULL,NULL,'not_required',NULL,NULL,NULL,NULL,NULL),('6e671a9b6402b37dc337fa07e3','LN-260922-FF871A2F','panel-ac8bf51becc1973d56467a1c72dfe042',NULL,'87dccf0ef6d17c0b175921d5ed',NULL,NULL,'511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','af07271acaa4ec95d209b31385','under_review','مردانی','یداله','122','2092321188','09333126880','09333126880','2591-12-14','کارفرما','بثصقض لض','1234567890','صث لبسزث','لپ تاپ اچ پی','[{\"id\":\"\",\"name\":\"لپ تاپ اچ پی\",\"code\":\"\",\"description\":\"لپ تاپ اچ پی\",\"qty\":1,\"quantity\":1,\"unit\":\"\",\"price\":524779090,\"unit_price\":524779090,\"total_price\":524779090}]',524779090.00,524779090.00,419823272.00,104955818.00,401980008.00,393136448.00,1,12,39495429.00,473945148.00,54121876.00,545770254.00,616128692.00,'2026-09-23','2026-09-22 14:06:23.648572',NULL,'2026-09-22 14:06:23.648572','2026-10-06 15:22:24.449554',NULL,NULL,'admin','','',NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0.00,NULL,'not_started',NULL,NULL,'unreviewed',NULL,NULL,0.00,NULL,'not_recorded',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unreviewed',NULL,NULL,NULL,NULL,2,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'not_required',NULL,NULL,NULL,NULL,'queued','shahkar',NULL,NULL,'dde13130bdb631917a16ec3bc3',NULL),('9b9243d8f4aa2bca09f4154605','LN-260928-C8882C7B','panel-a4f25ec88497b1a71de002daa83aa87e',NULL,'ebb526505d8694dd5e951df25f','63fa8f991ac3a8585a504815de',NULL,'511c7a1202318f5f0695fcc042','7c8417965db56d8b24ab2e310f','8178d39cb851fa10271267965d','need_correction','مهدی مشتاقی','یداله','0','1100049101','09135540144','03191001055','2026-08-26','کارفرما','اصفهان ، مجتمع ملل','1876995595','اصفهان مجتمع ملل','موبایل سامسونگ A56','[{\"id\":\"\",\"name\":\"موبایل سامسونگ A56\",\"code\":\"\",\"description\":\"موبایل سامسونگ A56\",\"qty\":1,\"quantity\":1,\"unit\":\"\",\"price\":1000000000,\"unit_price\":1000000000,\"total_price\":1000000000}]',1000000000.00,1000000000.00,800000000.00,200000000.00,765998524.00,749146557.00,1,12,75261057.00,903132684.00,103132684.00,1040000000.00,1174072489.00,'2026-09-23','2026-09-28 13:32:16.496594',NULL,'2026-09-28 13:32:16.496594','2026-09-30 12:59:08.335926',NULL,'ebb526505d8694dd5e951df25f','merchant','','',NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,0,NULL,NULL,0.00,NULL,'not_started',NULL,NULL,'unreviewed',NULL,NULL,0.00,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'unreviewed',NULL,NULL,NULL,'63fa8f991ac3a8585a504815de',2,'2026-09-30 12:59:08.335926',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'not_required',NULL,NULL,NULL,NULL,'not_required',NULL,NULL,NULL,NULL,NULL);
UNLOCK TABLES;

--
-- Table structure for table `loans_schema_migrations`
--

DROP TABLE IF EXISTS `loans_schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `loans_schema_migrations` (
  `version` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `loans_schema_migrations`
--

LOCK TABLES `loans_schema_migrations` WRITE;
INSERT INTO `loans_schema_migrations` VALUES ('202608260001','create_installment_plans_table','2026-08-27 01:03:42.908667'),('202608260002','create_installment_plan_versions_table','2026-08-27 01:03:42.909164'),('202608260003','create_calculation_runs_table','2026-08-27 01:03:42.909907'),('202608260004','create_loan_requests_table','2026-08-27 01:03:42.910394'),('202608260005','create_loan_request_status_history_table','2026-08-27 01:03:42.910903'),('202608260006','create_loan_request_events_table','2026-08-27 01:03:42.911127'),('202608260007','create_installment_schedule_table','2026-08-27 01:03:42.911346'),('202608260008','create_guarantors_table','2026-08-27 01:03:42.911552'),('202608270001','add_request_access_indexes','2026-08-27 01:03:42.931477'),('202609220001','enforce_installment_plan_statuses','2026-09-22 14:27:06.927799'),('202609260001','extend_core_entities','2026-09-26 14:03:33.892174'),('202609260002','create_merchant_core','2026-09-26 14:03:33.904398'),('202609260003','create_workflow_communication_core','2026-09-26 14:03:33.916444'),('202609260004','create_review_grant_core','2026-09-26 14:03:33.927304'),('202609260005','create_finance_settlement_core','2026-09-26 14:03:33.944463'),('202609260006','create_cooperation_notification_core','2026-09-26 14:03:33.959627'),('202609260007','add_access_scope_fields','2026-09-26 15:12:29.589338'),('202609260008','complete_case_workflow','2026-09-26 15:52:36.226238'),('202609290001','complete_plan_engine','2026-09-29 11:26:18.858675'),('202609290002','core_completion_request_origin_guarantor_credit','2026-09-29 15:09:38.013158'),('202609290003','backfill_legacy_acceptor_merchants','2026-09-29 16:47:58.865886'),('202609300001','workflow_task_sla_engine','2026-09-30 23:30:16.242968'),('202609300002','bank_credit_core','2026-09-30 23:54:00.722989'),('202610210001','create_profile_corrections','2026-10-21 13:37:57.129081'),('202610230002','bank_cycle_projection','2026-10-23 19:34:00.292040'),('202610230003','internal_note_attachments_and_revisions','2026-10-23 19:52:51.740442');
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
  KEY `idx_merchant_plan_permissions_plan` (`plan_id`,`status`),
  CONSTRAINT `fk_merchant_plan_permissions_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_merchant_plan_permissions_plan` FOREIGN KEY (`plan_id`) REFERENCES `installment_plans` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_plan_permissions`
--

LOCK TABLES `merchant_plan_permissions` WRITE;
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
  `status` varchar(32) NOT NULL,
  `settlement_date` datetime(6) DEFAULT NULL,
  `cycle_id` char(26) DEFAULT NULL,
  `note` varchar(1000) DEFAULT NULL,
  `actor_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_merchant_settlement_history_request` (`request_id`,`created_at`),
  KEY `idx_merchant_settlement_history_cycle` (`cycle_id`,`created_at`),
  CONSTRAINT `fk_merchant_settlement_history_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_settlement_history`
--

LOCK TABLES `merchant_settlement_history` WRITE;
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
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`merchant_id`,`user_id`),
  KEY `idx_merchant_memberships_user` (`user_id`,`status`),
  CONSTRAINT `fk_merchant_memberships_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchant_user_memberships`
--

LOCK TABLES `merchant_user_memberships` WRITE;
INSERT INTO `merchant_user_memberships` VALUES ('63fa8f991ac3a8585a504815de','ebb526505d8694dd5e951df25f','owner','active','ebb526505d8694dd5e951df25f','2026-09-28 13:32:16.496594');
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
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_merchants_code` (`code`),
  KEY `idx_merchants_owner` (`owner_user_id`,`status`),
  KEY `idx_merchants_organization` (`organization_ref`,`status`),
  KEY `idx_merchants_legacy_user` (`legacy_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `merchants`
--

LOCK TABLES `merchants` WRITE;
INSERT INTO `merchants` VALUES ('63fa8f991ac3a8585a504815de','ACC-EBB526505D86','پذیرنده منتقل‌شده EBB52650',NULL,'ebb526505d8694dd5e951df25f',NULL,'active','approved',NULL,NULL,'{\"source\": \"loan_request_acceptor_backfill\", \"inferred\": true}',NULL,'ebb526505d8694dd5e951df25f','2026-09-28 13:32:16.496594','2026-09-29 15:09:37.876442');
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(26) NOT NULL,
  `recipient_user_id` char(26) NOT NULL,
  `notification_type` varchar(64) NOT NULL,
  `channel` varchar(24) NOT NULL DEFAULT 'panel',
  `title` varchar(190) NOT NULL,
  `body` text NOT NULL,
  `target_url` varchar(1000) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'queued',
  `read_at` datetime(6) DEFAULT NULL,
  `sent_at` datetime(6) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_notifications_recipient` (`recipient_user_id`,`status`,`created_at`),
  KEY `idx_notifications_type` (`notification_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
INSERT INTO `notifications` VALUES ('4b8028a2e0dc928bc8de0e8ab3','ebb526505d8694dd5e951df25f','request.message','panel','پیام جدید پرونده','یک پیام جدید برای پرونده ثبت شده است.','/loans/requests/9b9243d8f4aa2bca09f4154605','queued',NULL,NULL,'{\"request_id\":\"9b9243d8f4aa2bca09f4154605\"}','2026-09-30 12:58:26.276207'),('e0f927673ca8de920a47fa3fa5','ebb526505d8694dd5e951df25f','request.status_changed','panel','تغییر وضعیت پرونده','وضعیت پرونده به مرحله جدید تغییر کرد.','/loans/requests/9b9243d8f4aa2bca09f4154605','queued',NULL,NULL,'{\"request_id\":\"9b9243d8f4aa2bca09f4154605\"}','2026-09-30 12:59:08.343037');
UNLOCK TABLES;

--
-- Table structure for table `request_comments`
--

DROP TABLE IF EXISTS `request_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_comments` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `author_user_id` char(26) NOT NULL,
  `comment` text NOT NULL,
  `visibility` varchar(24) NOT NULL DEFAULT 'internal',
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `attachment_document_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_document_ids_json`)),
  `operation_key` varchar(100) DEFAULT NULL,
  `payload_sha256` char(64) DEFAULT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_internal_note_request_operation` (`request_id`,`author_user_id`,`operation_key`),
  KEY `idx_request_comments_request` (`request_id`,`created_at`),
  CONSTRAINT `fk_request_comments_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_comments`
--

LOCK TABLES `request_comments` WRITE;
INSERT INTO `request_comments` VALUES ('2942c10d69aff29db8a49ae41a','9b9243d8f4aa2bca09f4154605','0f1652c3528f92bc3f95edd9ab','مدرک','internal','2026-09-30 12:58:40.863324','2026-09-30 12:58:40.863324',NULL,NULL,NULL,1);
UNLOCK TABLES;

--
-- Table structure for table `request_message_reads`
--

DROP TABLE IF EXISTS `request_message_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_message_reads` (
  `message_id` char(26) NOT NULL,
  `user_id` char(26) NOT NULL,
  `read_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`message_id`,`user_id`),
  KEY `idx_request_message_reads_user` (`user_id`,`read_at`),
  CONSTRAINT `fk_request_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `request_messages` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_message_reads`
--

LOCK TABLES `request_message_reads` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `request_messages`
--

DROP TABLE IF EXISTS `request_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_messages` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `sender_user_id` char(26) DEFAULT NULL,
  `sender_role` varchar(64) DEFAULT NULL,
  `message` text NOT NULL,
  `visibility` varchar(24) NOT NULL DEFAULT 'participants',
  `attachment_document_ids_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachment_document_ids_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `edited_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_request_messages_request` (`request_id`,`created_at`),
  KEY `idx_request_messages_sender` (`sender_user_id`,`created_at`),
  CONSTRAINT `fk_request_messages_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_messages`
--

LOCK TABLES `request_messages` WRITE;
INSERT INTO `request_messages` VALUES ('ba3244704508124b62d16ce373','9b9243d8f4aa2bca09f4154605','0f1652c3528f92bc3f95edd9ab','operations_expert','لطفا مدارک صحیح بارگذاری شود.','participants',NULL,'2026-09-30 12:58:26.269592',NULL);
UNLOCK TABLES;

--
-- Table structure for table `request_note_revisions`
--

DROP TABLE IF EXISTS `request_note_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_note_revisions` (
  `id` char(26) NOT NULL,
  `note_id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `changed_by_user_id` char(26) NOT NULL,
  `previous_comment` text NOT NULL,
  `previous_version` int(10) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_note_revision_note` (`note_id`,`created_at`),
  KEY `idx_note_revision_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_note_revisions`
--

LOCK TABLES `request_note_revisions` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `request_tags`
--

DROP TABLE IF EXISTS `request_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `request_tags` (
  `request_id` char(26) NOT NULL,
  `tag` varchar(64) NOT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`request_id`,`tag`),
  KEY `idx_request_tags_tag` (`tag`,`created_at`),
  CONSTRAINT `fk_request_tags_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `request_tags`
--

LOCK TABLES `request_tags` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(32) NOT NULL,
  `name` varchar(255) NOT NULL,
  `applied_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
INSERT INTO `schema_migrations` VALUES ('202608260001','create_installment_plans_table','2026-08-27 00:07:31.126680'),('202608260002','create_installment_plan_versions_table','2026-08-27 00:07:31.133197'),('202608260003','create_calculation_runs_table','2026-08-27 00:07:31.146302'),('202608260004','create_loan_requests_table','2026-08-27 00:07:31.155708'),('202608260005','create_loan_request_status_history_table','2026-08-27 00:07:31.157872'),('202608260006','create_loan_request_events_table','2026-08-27 00:07:31.160652'),('202608260007','create_installment_schedule_table','2026-08-27 00:07:31.162950'),('202608260008','create_guarantors_table','2026-08-27 00:07:31.164962');
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
  CONSTRAINT `fk_settlement_items_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `settlement_cycles` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_settlement_items_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_cycle_items`
--

LOCK TABLES `settlement_cycle_items` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `settlement_cycle_receipts`
--

DROP TABLE IF EXISTS `settlement_cycle_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settlement_cycle_receipts` (
  `id` char(26) NOT NULL,
  `cycle_id` char(26) NOT NULL,
  `document_id` char(26) NOT NULL,
  `amount` decimal(20,2) DEFAULT NULL,
  `created_by_user_id` char(26) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_settlement_receipts_cycle` (`cycle_id`,`created_at`),
  KEY `idx_settlement_receipts_document` (`document_id`),
  CONSTRAINT `fk_settlement_receipts_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `settlement_cycles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_cycle_receipts`
--

LOCK TABLES `settlement_cycle_receipts` WRITE;
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
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_settlement_cycles_no` (`cycle_no`),
  KEY `idx_settlement_cycles_merchant` (`merchant_id`,`status`,`settlement_date`),
  CONSTRAINT `fk_settlement_cycles_merchant` FOREIGN KEY (`merchant_id`) REFERENCES `merchants` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settlement_cycles`
--

LOCK TABLES `settlement_cycles` WRITE;
UNLOCK TABLES;

--
-- Table structure for table `verification_requests`
--

DROP TABLE IF EXISTS `verification_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `verification_requests` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `verification_type` varchar(32) NOT NULL,
  `provider` varchar(64) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'queued',
  `idempotency_key` varchar(128) NOT NULL,
  `subject_fingerprint` char(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `provider_reference` varchar(255) DEFAULT NULL,
  `response_hash` char(64) DEFAULT NULL,
  `score` int(11) DEFAULT NULL,
  `risk` varchar(64) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `requested_by_user_id` char(26) NOT NULL,
  `completed_by_user_id` char(26) DEFAULT NULL,
  `requested_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `completed_at` datetime(6) DEFAULT NULL,
  `expires_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_verification_idempotency` (`request_id`,`verification_type`,`idempotency_key`),
  KEY `idx_verification_request` (`request_id`,`verification_type`,`requested_at`),
  KEY `idx_verification_delivery` (`status`,`provider`,`requested_at`),
  CONSTRAINT `fk_verification_request_case` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `verification_requests`
--

LOCK TABLES `verification_requests` WRITE;
INSERT INTO `verification_requests` VALUES ('dde13130bdb631917a16ec3bc3','6e671a9b6402b37dc337fa07e3','identity_match','shahkar','queued','29bb89fd55fe4194805ae77b66e351aa','5104e1079f58b4e9cf47244a5a196088d475c67225333bec369fa37a0ab5bdae','dbbb9ee0843cc6421fbccbc36f025fb231a29927ec8a441e7baab46aa3cd7d98',NULL,NULL,NULL,NULL,NULL,'87dccf0ef6d17c0b175921d5ed',NULL,'2026-10-06 15:22:24.445134',NULL,'2026-10-06 13:52:24.000000','2026-10-06 15:22:24.445134','2026-10-06 15:22:24.445134');
UNLOCK TABLES;

--
-- Table structure for table `workflow_sla_policies`
--

DROP TABLE IF EXISTS `workflow_sla_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_sla_policies` (
  `id` char(26) NOT NULL,
  `code` varchar(64) NOT NULL,
  `name` varchar(190) NOT NULL,
  `task_code` varchar(64) NOT NULL,
  `queue_code` varchar(64) NOT NULL,
  `required_permission` varchar(128) NOT NULL,
  `due_minutes` int(10) unsigned NOT NULL,
  `warning_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `escalation_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `escalation_queue_code` varchar(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_workflow_sla_code` (`code`),
  KEY `idx_workflow_sla_task` (`task_code`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_sla_policies`
--

LOCK TABLES `workflow_sla_policies` WRITE;
INSERT INTO `workflow_sla_policies` VALUES ('17d0acdead0593faa9d9f60fb9','identity_verification','احراز تطبیق هویت شاهکار','identity_verification','bank','loans.requests.bank_review',720,120,60,'bank',1,'2026-09-30 23:54:00.718323','2026-09-30 23:54:00.718323'),('1c0c4857df2f74218fa252d7b5','operations_review','بررسی اولیه عملیات','operations_review','operations','loans.requests.operations',1440,180,60,'operations',1,'2026-09-30 23:30:16.221972','2026-09-30 23:30:16.221972'),('3a3746c4cfa147e85da128d8da','grant_registration','ثبت نهایی اعطا','grant_registration','operations','loans.requests.grant',480,120,60,'operations',1,'2026-09-30 23:30:16.227029','2026-09-30 23:30:16.227029'),('3bb409c0cf6eb5759ab0e2d21a','finance_review','بررسی مالی پرونده','finance_review','finance','loans.requests.finance',1440,180,60,'finance',1,'2026-09-30 23:30:16.227504','2026-09-30 23:30:16.227504'),('7a9efbed72e699d14a98d0255e','credit_validation','اعتبارسنجی اعتباری','credit_validation','bank','loans.requests.bank_review',1440,180,60,'bank',1,'2026-09-30 23:54:00.721753','2026-09-30 23:54:00.721753'),('a0258c18b083a40fc77c636084','document_verification','تأیید نهایی مدارک','document_verification','operations','loans.requests.document_receipt',1440,180,60,'operations',1,'2026-09-30 23:30:16.226652','2026-09-30 23:30:16.226652'),('a9c94ee42efb363687ddbddd2e','document_followup','پیگیری تکمیل مدارک','document_followup','operations','loans.requests.document_receipt',2880,360,120,'operations',1,'2026-09-30 23:30:16.225941','2026-09-30 23:30:16.225941'),('eb7af558ed29f496db45da0481','bank_settlement','ثبت تسویه بانک با بابانوئل','bank_settlement','finance','loans.requests.bank_settlement',2880,360,120,'finance',1,'2026-09-30 23:30:16.227265','2026-09-30 23:30:16.227265'),('fed1e681733c28874222994a5d','bank_review','بررسی کارشناس بانک','bank_review','bank','loans.requests.bank_review',1440,180,60,'operations',1,'2026-09-30 23:30:16.223959','2026-09-30 23:30:16.223959');
UNLOCK TABLES;

--
-- Table structure for table `workflow_task_history`
--

DROP TABLE IF EXISTS `workflow_task_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_task_history` (
  `id` char(26) NOT NULL,
  `task_id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `action` varchar(64) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `actor_user_id` char(26) DEFAULT NULL,
  `assigned_user_id` char(26) DEFAULT NULL,
  `note` varchar(2000) DEFAULT NULL,
  `metadata_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata_json`)),
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `idx_workflow_task_history_task` (`task_id`,`created_at`),
  KEY `idx_workflow_task_history_request` (`request_id`,`created_at`),
  KEY `idx_workflow_task_history_action` (`action`,`created_at`),
  CONSTRAINT `fk_workflow_task_history_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_workflow_task_history_task` FOREIGN KEY (`task_id`) REFERENCES `workflow_tasks` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_task_history`
--

LOCK TABLES `workflow_task_history` WRITE;
INSERT INTO `workflow_task_history` VALUES ('a3d3d1282beccc75c3f9d93e08','fda23c5befec3e99316de008c0','6e671a9b6402b37dc337fa07e3','sla_escalated','open','open','system:auto',NULL,'SLA task escalated','{\"due_at\":\"2026-10-01 23:30:16.228103\",\"queue_code\":\"operations\"}','2026-10-06 15:21:48.006906');
UNLOCK TABLES;

--
-- Table structure for table `workflow_tasks`
--

DROP TABLE IF EXISTS `workflow_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `workflow_tasks` (
  `id` char(26) NOT NULL,
  `request_id` char(26) NOT NULL,
  `task_code` varchar(64) NOT NULL,
  `title` varchar(190) NOT NULL,
  `description` text DEFAULT NULL,
  `queue_code` varchar(64) NOT NULL,
  `required_permission` varchar(128) NOT NULL,
  `assigned_user_id` char(26) DEFAULT NULL,
  `assigned_by_user_id` char(26) DEFAULT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'open',
  `priority` varchar(16) NOT NULL DEFAULT 'normal',
  `active_key` varchar(128) DEFAULT NULL,
  `sla_policy_code` varchar(64) DEFAULT NULL,
  `due_at` datetime(6) DEFAULT NULL,
  `warning_at` datetime(6) DEFAULT NULL,
  `escalation_at` datetime(6) DEFAULT NULL,
  `last_sla_event` varchar(32) DEFAULT NULL,
  `last_sla_event_at` datetime(6) DEFAULT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'workflow',
  `source_event` varchar(100) DEFAULT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `created_by_user_id` char(26) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `started_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `cancelled_at` datetime(6) DEFAULT NULL,
  `completion_note` varchar(2000) DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_workflow_tasks_active` (`request_id`,`active_key`),
  KEY `idx_workflow_tasks_assignee` (`assigned_user_id`,`status`,`due_at`),
  KEY `idx_workflow_tasks_queue` (`queue_code`,`status`,`due_at`),
  KEY `idx_workflow_tasks_request` (`request_id`,`created_at`),
  KEY `idx_workflow_tasks_sla` (`status`,`due_at`,`escalation_at`),
  CONSTRAINT `fk_workflow_tasks_request` FOREIGN KEY (`request_id`) REFERENCES `loan_requests` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `workflow_tasks`
--

LOCK TABLES `workflow_tasks` WRITE;
INSERT INTO `workflow_tasks` VALUES ('fda23c5befec3e99316de008c0','6e671a9b6402b37dc337fa07e3','operations_review','بررسی اولیه پرونده',NULL,'operations','loans.requests.operations',NULL,NULL,'open','urgent','workflow:operations_review','operations_review','2026-10-01 23:30:16.228103','2026-10-01 20:30:16.228103','2026-10-02 00:30:16.228103','escalated','2026-10-06 15:21:48.004965','workflow','migration.backfill',2,NULL,'2026-09-30 23:30:16.228103',NULL,NULL,NULL,NULL,'2026-10-06 15:21:48.004965');
UNLOCK TABLES;

--
-- Dumping events for database 'noeelir_loans'
--

--
-- Dumping routines for database 'noeelir_loans'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-04 22:14:46
