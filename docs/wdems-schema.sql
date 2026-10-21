-- WDEMS schema-only MySQL export. 2026-10-21 (Asia/Manila).
-- No table records, credentials, or migration history rows are included.
-- Import into an empty database; migrations remain the source of truth.


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint unsigned NOT NULL,
  `attendance_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `attendance_time` datetime DEFAULT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attendances_registration_id_unique` (`registration_id`),
  KEY `attendances_recorded_by_foreign` (`recorded_by`),
  KEY `attendances_attendance_status_index` (`attendance_status`),
  KEY `attendances_verified_at_index` (`verified_at`),
  CONSTRAINT `attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendances_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` bigint NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discipleship_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `enrollment_id` bigint unsigned NOT NULL,
  `session_id` bigint unsigned NOT NULL,
  `status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discipleship_attendances_unique` (`enrollment_id`,`session_id`),
  KEY `discipleship_attendances_recorded_by_foreign` (`recorded_by`),
  KEY `discipleship_attendances_session_id_index` (`session_id`),
  CONSTRAINT `discipleship_attendances_enrollment_id_foreign` FOREIGN KEY (`enrollment_id`) REFERENCES `discipleship_enrollments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discipleship_attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discipleship_attendances_session_id_foreign` FOREIGN KEY (`session_id`) REFERENCES `discipleship_sessions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discipleship_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `program_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discipleship_batches_created_by_foreign` (`created_by`),
  KEY `discipleship_batches_program_id_start_date_index` (`program_id`,`start_date`),
  CONSTRAINT `discipleship_batches_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `discipleship_batches_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `discipleship_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discipleship_enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint unsigned NOT NULL,
  `program_id` bigint unsigned NOT NULL,
  `batch_id` bigint unsigned DEFAULT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `enrolled_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discipleship_enrollments_unique` (`participant_id`,`program_id`),
  KEY `discipleship_enrollments_created_by_foreign` (`created_by`),
  KEY `discipleship_enrollments_program_id_status_index` (`program_id`,`status`),
  KEY `discipleship_enrollments_batch_id_index` (`batch_id`),
  CONSTRAINT `discipleship_enrollments_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `discipleship_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `discipleship_enrollments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `discipleship_enrollments_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discipleship_enrollments_program_id_foreign` FOREIGN KEY (`program_id`) REFERENCES `discipleship_programs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discipleship_programs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `display_order` int NOT NULL DEFAULT '0',
  `required_sessions` int DEFAULT NULL,
  `extra_requirements` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `discipleship_programs_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discipleship_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `batch_id` bigint unsigned NOT NULL,
  `session_date` date NOT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `discipleship_sessions_created_by_foreign` (`created_by`),
  KEY `discipleship_sessions_batch_id_session_date_index` (`batch_id`,`session_date`),
  CONSTRAINT `discipleship_sessions_batch_id_foreign` FOREIGN KEY (`batch_id`) REFERENCES `discipleship_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discipleship_sessions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `email_device_verifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `verified_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email_device_verifications_email_device_token_hash_unique` (`email`,`device_token_hash`),
  KEY `email_device_verifications_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `created_by` bigint unsigned NOT NULL,
  `event_kind` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'running',
  `church_category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `church_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `event_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `distance_value` decimal(6,2) DEFAULT NULL,
  `distance_unit` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT 'km',
  `course_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_map_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `venue_latitude` decimal(10,7) DEFAULT NULL,
  `venue_longitude` decimal(10,7) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `registration_start` datetime DEFAULT NULL,
  `registration_end` datetime DEFAULT NULL,
  `registration_form_saved_at` timestamp NULL DEFAULT NULL,
  `registration_slug` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_common_field_requirements` json DEFAULT NULL,
  `partners` json DEFAULT NULL,
  `faq` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `walkers_welcome` tinyint(1) NOT NULL DEFAULT '0',
  `all_paces_welcome` tinyint(1) NOT NULL DEFAULT '0',
  `all_ages_welcome` tinyint(1) NOT NULL DEFAULT '0',
  `stroller_friendly` tinyint(1) NOT NULL DEFAULT '0',
  `wheelchair_accessible` tinyint(1) NOT NULL DEFAULT '0',
  `sweeper_present` tinyint(1) NOT NULL DEFAULT '0',
  `service_animals_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `leashed_pets_allowed` tinyint(1) NOT NULL DEFAULT '0',
  `quiet_space_available` tinyint(1) NOT NULL DEFAULT '0',
  `registration_form_version` tinyint unsigned NOT NULL DEFAULT '1',
  `registration_disclaimer_html` text COLLATE utf8mb4_unicode_ci,
  `registration_tshirt` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `events_registration_slug_unique` (`registration_slug`),
  KEY `events_created_by_foreign` (`created_by`),
  KEY `events_status_index` (`status`),
  KEY `events_event_type_index` (`event_type`),
  KEY `events_event_kind_index` (`event_kind`),
  CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fellowship_attendances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fellowship_event_id` bigint unsigned NOT NULL,
  `participant_id` bigint unsigned NOT NULL,
  `fellowship_group_id` bigint unsigned DEFAULT NULL,
  `attendance_status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fellowship_attendances_unique` (`fellowship_event_id`,`participant_id`),
  KEY `fellowship_attendances_participant_id_foreign` (`participant_id`),
  KEY `fellowship_attendances_fellowship_group_id_foreign` (`fellowship_group_id`),
  KEY `fellowship_attendances_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `fellowship_attendances_fellowship_event_id_foreign` FOREIGN KEY (`fellowship_event_id`) REFERENCES `fellowship_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fellowship_attendances_fellowship_group_id_foreign` FOREIGN KEY (`fellowship_group_id`) REFERENCES `fellowship_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fellowship_attendances_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fellowship_attendances_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fellowship_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fellowship_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `event_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facilitator` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fellowship_events_created_by_foreign` (`created_by`),
  KEY `fellowship_events_fellowship_type_event_date_index` (`fellowship_type`,`event_date`),
  CONSTRAINT `fellowship_events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `fellowship_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `fellowship_event_id` bigint unsigned NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fellowship_groups_event_name_unique` (`fellowship_event_id`,`name`),
  CONSTRAINT `fellowship_groups_fellowship_event_id_foreign` FOREIGN KEY (`fellowship_event_id`) REFERENCES `fellowship_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `first_timers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint unsigned NOT NULL,
  `first_service_date` date DEFAULT NULL,
  `invited_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `follow_up_notes` text COLLATE utf8mb4_unicode_ci,
  `promoted_at` timestamp NULL DEFAULT NULL,
  `promoted_to_member_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `first_timers_participant_id_foreign` (`participant_id`),
  KEY `first_timers_promoted_to_member_id_foreign` (`promoted_to_member_id`),
  KEY `first_timers_promoted_at_index` (`promoted_at`),
  CONSTRAINT `first_timers_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `first_timers_promoted_to_member_id_foreign` FOREIGN KEY (`promoted_to_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` smallint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `participant_id` bigint unsigned NOT NULL,
  `membership_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `membership_date` date DEFAULT NULL,
  `cell_group` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ministry` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cell_leader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `joined_via` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `members_participant_id_unique` (`participant_id`),
  CONSTRAINT `members_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name_normalized` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name_normalized` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `suffix` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_number_normalized` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `age` tinyint unsigned DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `middle_name_normalized` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `suffix_normalized` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `provisional_event_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `participants_last_name_first_name_index` (`last_name`,`first_name`),
  KEY `participants_email_index` (`email`),
  KEY `participants_first_name_index` (`first_name`),
  KEY `participants_last_name_index` (`last_name`),
  KEY `participants_contact_number_index` (`contact_number`),
  KEY `participants_provisional_event_id_index` (`provisional_event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_field_responses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `registration_id` bigint unsigned NOT NULL,
  `registration_field_id` bigint unsigned NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reg_field_responses_unique` (`registration_id`,`registration_field_id`),
  KEY `registration_field_responses_registration_field_id_foreign` (`registration_field_id`),
  CONSTRAINT `registration_field_responses_registration_field_id_foreign` FOREIGN KEY (`registration_field_id`) REFERENCES `registration_fields` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `registration_field_responses_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_fields` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` json DEFAULT NULL,
  `validation_rules` json DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT '0',
  `display_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `registration_fields_event_id_display_order_index` (`event_id`,`display_order`),
  CONSTRAINT `registration_fields_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registration_verification_challenges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci,
  `email_required` tinyint(1) NOT NULL,
  `phone_required` tinyint(1) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `finalized_at` timestamp NULL DEFAULT NULL,
  `code_acknowledged_at` timestamp NULL DEFAULT NULL,
  `registration_id` bigint unsigned DEFAULT NULL,
  `email_code_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `email_issued_at` timestamp NULL DEFAULT NULL,
  `email_expires_at` timestamp NULL DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone_code_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `phone_issued_at` timestamp NULL DEFAULT NULL,
  `phone_expires_at` timestamp NULL DEFAULT NULL,
  `phone_verified_at` timestamp NULL DEFAULT NULL,
  `email_delivery_failed` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `chosen_channel` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registration_verification_challenges_token_hash_unique` (`token_hash`),
  KEY `registration_verification_challenges_registration_id_foreign` (`registration_id`),
  KEY `verification_event_pending_index` (`event_id`,`finalized_at`),
  KEY `registration_verification_challenges_expires_at_index` (`expires_at`),
  CONSTRAINT `registration_verification_challenges_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `registration_verification_challenges_registration_id_foreign` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `registrations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint unsigned NOT NULL,
  `participant_id` bigint unsigned NOT NULL,
  `registration_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `registration_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `source` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `registered_at` timestamp NULL DEFAULT NULL,
  `flagged_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `consent_accepted_at` timestamp NULL DEFAULT NULL,
  `privacy_notice_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_contact_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attendance_code` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_first_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_last_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relationship_other` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tshirt_choice` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `encoded_by` bigint unsigned DEFAULT NULL,
  `contact_attested_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `registrations_event_id_participant_id_unique` (`event_id`,`participant_id`),
  UNIQUE KEY `registrations_event_attendance_code_unique` (`event_id`,`attendance_code`),
  KEY `registrations_participant_id_foreign` (`participant_id`),
  KEY `registrations_registration_status_index` (`registration_status`),
  KEY `registrations_encoded_by_foreign` (`encoded_by`),
  CONSTRAINT `registrations_encoded_by_foreign` FOREIGN KEY (`encoded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `registrations_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `registrations_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_attendance_first_timers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_occurrence_id` bigint unsigned NOT NULL,
  `participant_id` bigint unsigned NOT NULL,
  `invited_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attendance_status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `svc_attend_first_unique` (`service_occurrence_id`,`participant_id`),
  KEY `service_attendance_first_timers_participant_id_foreign` (`participant_id`),
  KEY `service_attendance_first_timers_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `service_attendance_first_timers_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_attendance_first_timers_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_attendance_first_timers_service_occurrence_id_foreign` FOREIGN KEY (`service_occurrence_id`) REFERENCES `service_occurrences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_attendance_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_occurrence_id` bigint unsigned NOT NULL,
  `participant_id` bigint unsigned NOT NULL,
  `attendance_status` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `recorded_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `svc_attend_members_unique` (`service_occurrence_id`,`participant_id`),
  KEY `service_attendance_members_participant_id_foreign` (`participant_id`),
  KEY `service_attendance_members_recorded_by_foreign` (`recorded_by`),
  CONSTRAINT `service_attendance_members_participant_id_foreign` FOREIGN KEY (`participant_id`) REFERENCES `participants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_attendance_members_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `service_attendance_members_service_occurrence_id_foreign` FOREIGN KEY (`service_occurrence_id`) REFERENCES `service_occurrences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_occurrences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurrence_date` date NOT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `service_leader` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `topic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_occurrences_created_by_foreign` (`created_by`),
  KEY `service_occurrences_service_type_occurrence_date_index` (`service_type`,`occurrence_date`),
  CONSTRAINT `service_occurrences_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trusted_devices` json DEFAULT NULL,
  `email_two_factor_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'staff',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

