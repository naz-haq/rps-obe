
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
DROP TABLE IF EXISTS `ai_interaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_interaksi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `entity_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `mode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prompt` longtext COLLATE utf8mb4_unicode_ci,
  `response` longtext COLLATE utf8mb4_unicode_ci,
  `tokens_in` int unsigned DEFAULT NULL,
  `tokens_out` int unsigned DEFAULT NULL,
  `biaya` decimal(12,6) DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sukses',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_interaksi_institusi_id_entity_type_entity_id_index` (`institusi_id`,`entity_type`,`entity_id`),
  CONSTRAINT `ai_interaksi_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_interaksi` WRITE;
/*!40000 ALTER TABLE `ai_interaksi` DISABLE KEYS */;
INSERT INTO `ai_interaksi` VALUES (1,1,NULL,'field_asistif',NULL,'asistif:panjangkan','mock','mock-1',NULL,'[MOCK:mock-1] Konteks field: Deskripsi Mata Kuliah.\nUraikan kalimat berikut menjadi lebih lengkap dan jelas sesuai konteks yang sudah ada; jangan mengarang fakta baru.\n\nTeks:\nMata kuliah ini membahas farmasetika',135,53,0.000000,'sukses','2026-07-06 01:53:29','2026-07-06 01:53:29'),(2,1,NULL,'field_asistif',NULL,'asistif:perbaiki','gemini','gemini-2.5-flash-lite',NULL,'Ilmuwan farmasi yang menguasai konsep sains kefarmasian secara komprehensif.',140,20,0.000011,'sukses','2026-07-06 02:01:21','2026-07-06 02:01:21'),(3,1,NULL,'field_asistif',NULL,'asistif:perbaiki','gemini','gemini-2.5-flash-lite',NULL,'Pengkaji obat yang kompeten dalam menganalisis mekanisme kerja dan penggunaan obat secara rasional.',137,20,0.000011,'sukses','2026-07-06 02:01:46','2026-07-06 02:01:46');
/*!40000 ALTER TABLE `ai_interaksi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ai_kredensial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_kredensial` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `provider` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `api_key_encrypted` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_default` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mata_uang` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `anggaran` decimal(12,4) DEFAULT NULL,
  `saldo_provider` decimal(12,4) DEFAULT NULL,
  `saldo_diperbarui_at` timestamp NULL DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_kredensial_institusi_id_provider_index` (`institusi_id`,`provider`),
  CONSTRAINT `ai_kredensial_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_kredensial` WRITE;
/*!40000 ALTER TABLE `ai_kredensial` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_kredensial` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ai_pengaturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_pengaturan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `profil` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simulasi',
  `diubah_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ai_pengaturan_institusi_id_unique` (`institusi_id`),
  CONSTRAINT `ai_pengaturan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_pengaturan` WRITE;
/*!40000 ALTER TABLE `ai_pengaturan` DISABLE KEYS */;
INSERT INTO `ai_pengaturan` VALUES (3,NULL,'simulasi',NULL,'2026-07-04 17:12:40','2026-07-04 17:12:41');
/*!40000 ALTER TABLE `ai_pengaturan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `ai_validasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_validasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ai_interaksi_id` bigint unsigned NOT NULL,
  `klaim` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bukti_chunk_ids` json DEFAULT NULL,
  `skor_grounding` decimal(5,2) DEFAULT NULL,
  `konteks_pengganti` text COLLATE utf8mb4_unicode_ci,
  `tindakan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_validasi_ai_interaksi_id_foreign` (`ai_interaksi_id`),
  CONSTRAINT `ai_validasi_ai_interaksi_id_foreign` FOREIGN KEY (`ai_interaksi_id`) REFERENCES `ai_interaksi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `ai_validasi` WRITE;
/*!40000 ALTER TABLE `ai_validasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_validasi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `meta` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audit_log_institusi_id_foreign` (`institusi_id`),
  KEY `audit_log_entity_entity_id_index` (`entity`,`entity_id`),
  CONSTRAINT `audit_log_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `badan_rujukan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `badan_rujukan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disiplin` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `badan_rujukan_institusi_id_foreign` (`institusi_id`),
  CONSTRAINT `badan_rujukan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `badan_rujukan` WRITE;
/*!40000 ALTER TABLE `badan_rujukan` DISABLE KEYS */;
INSERT INTO `badan_rujukan` VALUES (1,NULL,'Kemdiktisaintek','pemerintah',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,NULL,'APTFI','asosiasi','Farmasi','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,NULL,'LAM-PTKes','akreditasi','Kesehatan','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `badan_rujukan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bahan_kajian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan_kajian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kurikulum_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_kajian_institusi_id_foreign` (`institusi_id`),
  KEY `bahan_kajian_kurikulum_id_foreign` (`kurikulum_id`),
  CONSTRAINT `bahan_kajian_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bahan_kajian_kurikulum_id_foreign` FOREIGN KEY (`kurikulum_id`) REFERENCES `kurikulum` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bahan_kajian` WRITE;
/*!40000 ALTER TABLE `bahan_kajian` DISABLE KEYS */;
INSERT INTO `bahan_kajian` VALUES (1,5,1,'Prinsip Dasar Farmakologi','Farmakokinetika dan farmakodinamika obat.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,'Farmakologi Sistem Organ','Kerja obat pada sistem saraf, kardiovaskular, dan organ lain.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,1,'Kemoterapi dan Antimikroba','Antibiotik, antivirus, antijamur, dan resistensi.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,1,'Toksikologi Dasar','Efek toksik obat dan bahan kimia terhadap tubuh.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,1,'Penggunaan Obat Rasional','Kajian efektivitas, keamanan, dan ketepatan penggunaan obat.','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `bahan_kajian` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `butir_acuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `butir_acuan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kerangka_acuan_id` bigint unsigned NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `kategori` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `wajib` tinyint(1) NOT NULL DEFAULT '1',
  `urutan` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `butir_acuan_kerangka_acuan_id_foreign` (`kerangka_acuan_id`),
  KEY `butir_acuan_parent_id_foreign` (`parent_id`),
  CONSTRAINT `butir_acuan_kerangka_acuan_id_foreign` FOREIGN KEY (`kerangka_acuan_id`) REFERENCES `kerangka_acuan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `butir_acuan_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `butir_acuan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `butir_acuan` WRITE;
/*!40000 ALTER TABLE `butir_acuan` DISABLE KEYS */;
INSERT INTO `butir_acuan` VALUES (1,1,NULL,'profil_lulusan','PL','Menetapkan Profil Lulusan sesuai kebutuhan pemangku kepentingan.',1,1,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,1,NULL,'cpl','CPL','Merumuskan CPL mencakup aspek sikap, pengetahuan, dan keterampilan (SN-Dikti).',1,2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,1,NULL,'bahan_kajian','BK','Menyusun Bahan Kajian yang menopang setiap CPL.',1,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,1,NULL,'struktur','STR','Membentuk mata kuliah dari bahan kajian beserta bobot SKS.',1,4,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,1,NULL,'aturan','ATR','Menetapkan 1 SKS teori setara 170 menit/minggu (50 TM + 60 PT + 60 BM).',1,5,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `butir_acuan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache`;
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

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cache_locks`;
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

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `capaian_mahasiswa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `capaian_mahasiswa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sub_cpmk_id` bigint unsigned DEFAULT NULL,
  `cpmk_id` bigint unsigned DEFAULT NULL,
  `angkatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_mahasiswa` int unsigned DEFAULT NULL,
  `nilai_rata_rata` decimal(5,2) DEFAULT NULL,
  `persentase_capaian_minimal` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `capaian_mahasiswa_sub_cpmk_id_foreign` (`sub_cpmk_id`),
  KEY `capaian_mahasiswa_cpmk_id_foreign` (`cpmk_id`),
  KEY `capaian_mahasiswa_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `capaian_mahasiswa_cpmk_id_foreign` FOREIGN KEY (`cpmk_id`) REFERENCES `cpmk` (`id`) ON DELETE SET NULL,
  CONSTRAINT `capaian_mahasiswa_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `capaian_mahasiswa_sub_cpmk_id_foreign` FOREIGN KEY (`sub_cpmk_id`) REFERENCES `sub_cpmk` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `capaian_mahasiswa` WRITE;
/*!40000 ALTER TABLE `capaian_mahasiswa` DISABLE KEYS */;
/*!40000 ALTER TABLE `capaian_mahasiswa` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `column_mapping`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `column_mapping` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `jenis_file` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mapping` json NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `column_mapping_institusi_id_foreign` (`institusi_id`),
  CONSTRAINT `column_mapping_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `column_mapping` WRITE;
/*!40000 ALTER TABLE `column_mapping` DISABLE KEYS */;
/*!40000 ALTER TABLE `column_mapping` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kurikulum_id` bigint unsigned DEFAULT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `aspek` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level_kkni` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpl_kurikulum_id_kode_unique` (`kurikulum_id`,`kode`),
  KEY `cpl_institusi_id_foreign` (`institusi_id`),
  CONSTRAINT `cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpl_kurikulum_id_foreign` FOREIGN KEY (`kurikulum_id`) REFERENCES `kurikulum` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cpl` WRITE;
/*!40000 ALTER TABLE `cpl` DISABLE KEYS */;
INSERT INTO `cpl` VALUES (1,5,1,'CPL01','Menjunjung tinggi nilai kemanusiaan dan etika profesi kefarmasian dalam menjalankan tugas.','sikap','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,'CPL02','Menunjukkan sikap bertanggung jawab atas pekerjaan secara mandiri maupun dalam tim.','sikap','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,1,'CPL03','Menguasai konsep teoretis farmakologi meliputi farmakokinetika dan farmakodinamika obat.','pengetahuan','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,1,'CPL04','Menguasai prinsip mekanisme kerja obat, kemoterapi, dan resistensi antimikroba.','pengetahuan','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,1,'CPL05','Mampu menerapkan pemikiran logis, kritis, dan sistematis dalam mengkaji ilmu kefarmasian.','keterampilan_umum','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,1,'CPL06','Mampu melakukan pembelajaran mandiri dan mengelola informasi ilmiah secara bertanggung jawab.','keterampilan_umum','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,1,'CPL07','Mampu menganalisis efek dan mekanisme kerja obat pada berbagai sistem organ tubuh.','keterampilan_khusus','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,1,'CPL08','Mampu mengevaluasi penggunaan obat yang rasional beserta risiko efek sampingnya.','keterampilan_khusus','6','SN-Dikti','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cpl_bahan_kajian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cpl_bahan_kajian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `bahan_kajian_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpl_bahan_kajian_cpl_id_bahan_kajian_id_unique` (`cpl_id`,`bahan_kajian_id`),
  KEY `cpl_bahan_kajian_institusi_id_foreign` (`institusi_id`),
  KEY `cpl_bahan_kajian_bahan_kajian_id_foreign` (`bahan_kajian_id`),
  CONSTRAINT `cpl_bahan_kajian_bahan_kajian_id_foreign` FOREIGN KEY (`bahan_kajian_id`) REFERENCES `bahan_kajian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpl_bahan_kajian_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpl_bahan_kajian_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cpl_bahan_kajian` WRITE;
/*!40000 ALTER TABLE `cpl_bahan_kajian` DISABLE KEYS */;
INSERT INTO `cpl_bahan_kajian` VALUES (1,5,3,1,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,3,2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,7,2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,4,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,7,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,4,4,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,8,5,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `cpl_bahan_kajian` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cpmk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cpmk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `taksonomi_id` bigint unsigned DEFAULT NULL,
  `taksonomi_kode` json DEFAULT NULL,
  `bobot_persen` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpmk_inst_mk_kode_unique` (`institusi_id`,`kode_mk`,`kode`),
  KEY `cpmk_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  KEY `cpmk_taksonomi_id_foreign` (`taksonomi_id`),
  CONSTRAINT `cpmk_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpmk_taksonomi_id_foreign` FOREIGN KEY (`taksonomi_id`) REFERENCES `taksonomi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cpmk` WRITE;
/*!40000 ALTER TABLE `cpmk` DISABLE KEYS */;
INSERT INTO `cpmk` VALUES (1,5,'FAR201','CPMK1','Mampu menjelaskan prinsip dasar farmakologi meliputi farmakokinetika dan farmakodinamika obat.',2,'[\"C2\"]',15.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201','CPMK2','Mampu menganalisis mekanisme kerja obat pada berbagai sistem organ tubuh.',4,'[\"C4\"]',25.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,'FAR201','CPMK3','Mampu menganalisis prinsip kemoterapi antimikroba beserta mekanisme resistensinya.',4,'[\"C4\"]',25.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,'FAR201','CPMK4','Mampu mengevaluasi penggunaan obat yang rasional beserta risiko efek sampingnya.',5,'[\"C5\"]',20.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,'FAR201','CPMK5','Mampu bekerja sama dan mengomunikasikan hasil kajian farmakologi secara ilmiah dan bertanggung jawab.',9,'[\"A3\"]',15.00,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `cpmk` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `cpmk_cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cpmk_cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `cpmk_id` bigint unsigned NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `bobot` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cpmk_cpl_cpmk_id_cpl_id_unique` (`cpmk_id`,`cpl_id`),
  KEY `cpmk_cpl_institusi_id_foreign` (`institusi_id`),
  KEY `cpmk_cpl_cpl_id_foreign` (`cpl_id`),
  CONSTRAINT `cpmk_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpmk_cpl_cpmk_id_foreign` FOREIGN KEY (`cpmk_id`) REFERENCES `cpmk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpmk_cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `cpmk_cpl` WRITE;
/*!40000 ALTER TABLE `cpmk_cpl` DISABLE KEYS */;
INSERT INTO `cpmk_cpl` VALUES (1,5,1,3,100.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,2,3,40.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,2,7,60.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,3,4,50.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,3,7,50.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,4,8,100.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,5,2,40.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,5,5,60.00,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `cpmk_cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `dokumen_chunk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen_chunk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dokumen_id` bigint unsigned NOT NULL,
  `urutan` int unsigned NOT NULL DEFAULT '0',
  `teks` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `halaman` int unsigned DEFAULT NULL,
  `embedding` json DEFAULT NULL,
  `token_count` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumen_chunk_dokumen_id_index` (`dokumen_id`),
  CONSTRAINT `dokumen_chunk_dokumen_id_foreign` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `dokumen_chunk` WRITE;
/*!40000 ALTER TABLE `dokumen_chunk` DISABLE KEYS */;
/*!40000 ALTER TABLE `dokumen_chunk` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `dokumen_rujukan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dokumen_rujukan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `badan_rujukan_id` bigint unsigned DEFAULT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `judul` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_asal` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_indexing` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `vector_namespace` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dokumen_rujukan_institusi_id_foreign` (`institusi_id`),
  KEY `dokumen_rujukan_badan_rujukan_id_foreign` (`badan_rujukan_id`),
  CONSTRAINT `dokumen_rujukan_badan_rujukan_id_foreign` FOREIGN KEY (`badan_rujukan_id`) REFERENCES `badan_rujukan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dokumen_rujukan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `dokumen_rujukan` WRITE;
/*!40000 ALTER TABLE `dokumen_rujukan` DISABLE KEYS */;
INSERT INTO `dokumen_rujukan` VALUES (1,5,1,'kpt','Panduan Penyusunan Kurikulum Pendidikan Tinggi (KPT) 2024',NULL,NULL,'selesai',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `dokumen_rujukan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `dosen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `dosen` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `nidn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dosen_institusi_id_nidn_unique` (`institusi_id`,`nidn`),
  CONSTRAINT `dosen_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `dosen` WRITE;
/*!40000 ALTER TABLE `dosen` DISABLE KEYS */;
INSERT INTO `dosen` VALUES (1,5,'0912345678','Dr. apt. Uji Dosen, M.Farm.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'0923456789','apt. Sari Wijaya, M.Sc.','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `dosen` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `evaluasi_cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `evaluasi_cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `periode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ringkasan_naratif` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `dibuat_oleh` bigint unsigned DEFAULT NULL,
  `difinalisasi_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `evaluasi_cpl_institusi_id_foreign` (`institusi_id`),
  KEY `evaluasi_cpl_cpl_id_foreign` (`cpl_id`),
  CONSTRAINT `evaluasi_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evaluasi_cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `evaluasi_cpl` WRITE;
/*!40000 ALTER TABLE `evaluasi_cpl` DISABLE KEYS */;
/*!40000 ALTER TABLE `evaluasi_cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `failed_jobs`;
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

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `generate_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `generate_session` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `mk_id` bigint unsigned DEFAULT NULL,
  `rps_version_id` bigint unsigned DEFAULT NULL,
  `sumber` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baru',
  `tahap` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `draf` json DEFAULT NULL,
  `status_bagian` json DEFAULT NULL,
  `catatan_validasi` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'berjalan',
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `generate_session_mk_id_foreign` (`mk_id`),
  KEY `generate_session_rps_version_id_foreign` (`rps_version_id`),
  KEY `generate_session_institusi_id_mk_id_index` (`institusi_id`,`mk_id`),
  CONSTRAINT `generate_session_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `generate_session_mk_id_foreign` FOREIGN KEY (`mk_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE SET NULL,
  CONSTRAINT `generate_session_rps_version_id_foreign` FOREIGN KEY (`rps_version_id`) REFERENCES `rps_version` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `generate_session` WRITE;
/*!40000 ALTER TABLE `generate_session` DISABLE KEYS */;
/*!40000 ALTER TABLE `generate_session` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `indikator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `indikator` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `sub_cpmk_id` bigint unsigned NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `indikator_institusi_id_foreign` (`institusi_id`),
  KEY `indikator_sub_cpmk_id_foreign` (`sub_cpmk_id`),
  CONSTRAINT `indikator_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `indikator_sub_cpmk_id_foreign` FOREIGN KEY (`sub_cpmk_id`) REFERENCES `sub_cpmk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `indikator` WRITE;
/*!40000 ALTER TABLE `indikator` DISABLE KEYS */;
INSERT INTO `indikator` VALUES (1,5,1,'Ketepatan menjelaskan definisi dan ruang lingkup farmakologi.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,2,'Ketepatan menjelaskan tahapan ADME obat.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,3,'Ketepatan menjelaskan hubungan dosis-respons dan mekanisme reseptor.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,4,'Ketepatan menganalisis efek agonis dan antagonis otonom.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,5,'Ketepatan menganalisis mekanisme obat kardiovaskular.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,6,'Ketepatan menganalisis mekanisme obat sistem saraf pusat.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,7,'Ketepatan menganalisis mekanisme obat pencernaan dan endokrin.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,8,'Ketepatan menganalisis mekanisme kerja antibiotik beta-laktam.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(9,5,9,'Ketepatan menganalisis mekanisme resistensi antimikroba.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(10,5,10,'Ketepatan menganalisis mekanisme antivirus, antijamur, dan antiparasit.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(11,5,11,'Ketepatan mengevaluasi risiko efek samping dan interaksi obat.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(12,5,12,'Ketepatan mengevaluasi indikator penggunaan obat rasional.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(13,5,13,'Kualitas penyajian dan penguasaan materi kajian.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(14,5,14,'Kualitas argumentasi dan telaah pustaka.','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `indikator` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `institusi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `institusi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'prodi',
  `parent_id` bigint unsigned DEFAULT NULL,
  `asosiasi_profesi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `institusi_parent_id_foreign` (`parent_id`),
  CONSTRAINT `institusi_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `institusi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `institusi` WRITE;
/*!40000 ALTER TABLE `institusi` DISABLE KEYS */;
INSERT INTO `institusi` VALUES (1,'FF','Fakultas Farmasi','fakultas',7,'APTFI','2026-07-04 12:26:08','2026-07-06 01:47:27'),(4,'48091-PSPA','Profesi Apoteker','prodi',1,'APTFI','2026-07-05 18:28:25','2026-07-05 18:46:19'),(5,'48201-PSSF','Sarjana Farmasi','prodi',1,'APTFI','2026-07-05 18:51:23','2026-07-06 01:34:19'),(7,'UNIV01','Universitas Contoh Nusantara','universitas',NULL,NULL,'2026-07-06 01:30:11','2026-07-06 01:30:11');
/*!40000 ALTER TABLE `institusi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `job_batches`;
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

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `jobs`;
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

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `kerangka_acuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kerangka_acuan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `badan_rujukan_id` bigint unsigned NOT NULL,
  `dokumen_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kerangka_acuan_badan_rujukan_id_foreign` (`badan_rujukan_id`),
  KEY `kerangka_acuan_dokumen_id_foreign` (`dokumen_id`),
  CONSTRAINT `kerangka_acuan_badan_rujukan_id_foreign` FOREIGN KEY (`badan_rujukan_id`) REFERENCES `badan_rujukan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kerangka_acuan_dokumen_id_foreign` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kerangka_acuan` WRITE;
/*!40000 ALTER TABLE `kerangka_acuan` DISABLE KEYS */;
INSERT INTO `kerangka_acuan` VALUES (1,1,1,'KPT 2024','2024','2024-01-01','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `kerangka_acuan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `keterampilan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `keterampilan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `bahan_kajian_id` bigint unsigned NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `taksonomi_id` bigint unsigned DEFAULT NULL,
  `tingkat_kemampuan` tinyint unsigned DEFAULT NULL,
  `sumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `keterampilan_institusi_id_foreign` (`institusi_id`),
  KEY `keterampilan_bahan_kajian_id_foreign` (`bahan_kajian_id`),
  KEY `keterampilan_taksonomi_id_foreign` (`taksonomi_id`),
  CONSTRAINT `keterampilan_bahan_kajian_id_foreign` FOREIGN KEY (`bahan_kajian_id`) REFERENCES `bahan_kajian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `keterampilan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `keterampilan_taksonomi_id_foreign` FOREIGN KEY (`taksonomi_id`) REFERENCES `taksonomi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `keterampilan` WRITE;
/*!40000 ALTER TABLE `keterampilan` DISABLE KEYS */;
INSERT INTO `keterampilan` VALUES (1,5,1,'Menganalisis prinsip dasar farmakologi secara sistematis.','kognitif',NULL,4,'prodi','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,2,'Menganalisis farmakologi sistem organ secara sistematis.','kognitif',NULL,4,'prodi','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,3,'Menganalisis kemoterapi dan antimikroba secara sistematis.','kognitif',NULL,4,'prodi','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,4,'Menganalisis toksikologi dasar secara sistematis.','kognitif',NULL,4,'prodi','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,5,'Menganalisis penggunaan obat rasional secara sistematis.','kognitif',NULL,4,'prodi','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `keterampilan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `komponen_penilaian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `komponen_penilaian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rps_version_id` bigint unsigned NOT NULL,
  `sub_cpmk_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `instrumen` text COLLATE utf8mb4_unicode_ci,
  `bobot_persen` decimal(6,2) DEFAULT NULL,
  `minggu_ke` tinyint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `komponen_penilaian_rps_version_id_foreign` (`rps_version_id`),
  KEY `komponen_penilaian_sub_cpmk_id_foreign` (`sub_cpmk_id`),
  CONSTRAINT `komponen_penilaian_rps_version_id_foreign` FOREIGN KEY (`rps_version_id`) REFERENCES `rps_version` (`id`) ON DELETE CASCADE,
  CONSTRAINT `komponen_penilaian_sub_cpmk_id_foreign` FOREIGN KEY (`sub_cpmk_id`) REFERENCES `sub_cpmk` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `komponen_penilaian` WRITE;
/*!40000 ALTER TABLE `komponen_penilaian` DISABLE KEYS */;
INSERT INTO `komponen_penilaian` VALUES (1,1,3,'Kuis','kuis','Soal/rubrik penilaian kuis.',15.00,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,1,5,'Tugas Terstruktur','tugas','Soal/rubrik penilaian tugas terstruktur.',25.00,5,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,1,13,'Presentasi Kajian','skill_assessment','Soal/rubrik penilaian presentasi kajian.',15.00,14,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,1,NULL,'Ujian Tengah Semester','uts','Soal/rubrik penilaian ujian tengah semester.',20.00,8,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,1,NULL,'Ujian Akhir Semester','uas','Soal/rubrik penilaian ujian akhir semester.',25.00,16,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `komponen_penilaian` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `konfigurasi_aturan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `konfigurasi_aturan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `badan_rujukan_id` bigint unsigned DEFAULT NULL,
  `jenis_aturan` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nilai` json NOT NULL,
  `diisi_oleh` bigint unsigned DEFAULT NULL,
  `referensi_dokumen_id` bigint unsigned DEFAULT NULL,
  `referensi_halaman` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `konfigurasi_aturan_institusi_id_foreign` (`institusi_id`),
  KEY `konfigurasi_aturan_badan_rujukan_id_foreign` (`badan_rujukan_id`),
  KEY `konfigurasi_aturan_referensi_dokumen_id_foreign` (`referensi_dokumen_id`),
  CONSTRAINT `konfigurasi_aturan_badan_rujukan_id_foreign` FOREIGN KEY (`badan_rujukan_id`) REFERENCES `badan_rujukan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `konfigurasi_aturan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `konfigurasi_aturan_referensi_dokumen_id_foreign` FOREIGN KEY (`referensi_dokumen_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `konfigurasi_aturan` WRITE;
/*!40000 ALTER TABLE `konfigurasi_aturan` DISABLE KEYS */;
INSERT INTO `konfigurasi_aturan` VALUES (1,5,1,'jumlah_minggu','{\"minggu_efektif\": 16, \"minggu_evaluasi\": 2}',NULL,1,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,'bobot_teori','{\"mandiri\": 60, \"tatap_muka\": 50, \"terstruktur\": 60}',NULL,1,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,1,'bobot_praktikum','{\"praktik\": 170}',NULL,1,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,1,'konversi_sks','{\"praktik\": 170, \"teori_mandiri\": 60, \"teori_tatap_muka\": 50, \"teori_terstruktur\": 60}',NULL,1,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `konfigurasi_aturan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `kurikulum`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kurikulum` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institusi_id` bigint unsigned NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tahun` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `tanggal_berlaku` date DEFAULT NULL,
  `tanggal_pensiun` date DEFAULT NULL,
  `mengganti_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kurikulum_ulid_unique` (`ulid`),
  KEY `kurikulum_institusi_id_foreign` (`institusi_id`),
  KEY `kurikulum_mengganti_id_foreign` (`mengganti_id`),
  CONSTRAINT `kurikulum_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `kurikulum_mengganti_id_foreign` FOREIGN KEY (`mengganti_id`) REFERENCES `kurikulum` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `kurikulum` WRITE;
/*!40000 ALTER TABLE `kurikulum` DISABLE KEYS */;
INSERT INTO `kurikulum` VALUES (1,'01KWVD6P06XEYM8HAVGFZZK7C4',5,'KUR-SF-2024','Kurikulum 2024 Program Studi Sarjana Farmasi','2024','berlaku','2024-08-01',NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `kurikulum` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mata_kuliah`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mata_kuliah` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institusi_id` bigint unsigned NOT NULL,
  `kurikulum_id` bigint unsigned DEFAULT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'murni',
  `sifat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rumpun` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deskripsi_singkat` text COLLATE utf8mb4_unicode_ci,
  `sks_teori` tinyint unsigned NOT NULL DEFAULT '0',
  `sks_praktik` tinyint unsigned NOT NULL DEFAULT '0',
  `semester` tinyint unsigned DEFAULT NULL,
  `prodi_kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prasyarat_kode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mata_kuliah_ulid_unique` (`ulid`),
  UNIQUE KEY `mata_kuliah_kurikulum_id_kode_mk_unique` (`kurikulum_id`,`kode_mk`),
  KEY `mata_kuliah_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `mata_kuliah_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mata_kuliah_kurikulum_id_foreign` FOREIGN KEY (`kurikulum_id`) REFERENCES `kurikulum` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mata_kuliah` WRITE;
/*!40000 ALTER TABLE `mata_kuliah` DISABLE KEYS */;
INSERT INTO `mata_kuliah` VALUES (1,'01KWVD6P5SZSBTQV1EDYBS20V5',5,1,'FAR201','Farmakologi Dasar','murni','wajib','Farmakologi dan Farmasi Klinik','Mata kuliah ini membahas prinsip dasar farmakologi meliputi farmakokinetika, farmakodinamika, kerja obat pada sistem organ, kemoterapi antimikroba, serta prinsip penggunaan obat yang rasional sebagai landasan farmasi klinik.',2,0,3,'48201-PSSF',NULL,'2026-07-06 01:47:28','2026-07-06 01:54:31'),(3,'01KWVDK6HD0YSEN90KCQ4EY4PJ',5,1,'FAR202','Farmasetika','praktikum','wajib','Farmasi Sains','Uraikan kalimat berikut menjadi lebih lengkap dan jelas sesuai konteks yang sudah ada; jangan mengarang fakta baru.',0,1,3,NULL,NULL,'2026-07-06 01:54:18','2026-07-06 01:54:18');
/*!40000 ALTER TABLE `mata_kuliah` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_07_04_000001_create_master_tables',1),(5,'2026_07_04_000002_create_reference_tables',1),(6,'2026_07_04_000003_create_curriculum_tables',1),(7,'2026_07_04_000004_create_cpmk_rps_tables',1),(8,'2026_07_04_000005_create_compliance_tables',1),(9,'2026_07_04_000006_create_obaei_tables',1),(10,'2026_07_04_000007_create_governance_tables',1),(11,'2026_07_04_000008_create_generator_tables',1),(12,'2026_07_04_000009_create_ai_tables',1),(13,'2026_07_05_000001_create_ai_pengaturan_table',2),(14,'2026_07_05_000001_add_taksonomi_to_cpmk',3),(15,'2026_07_05_000002_create_cpl_bahan_kajian_table',4),(16,'2026_07_06_000001_add_berkas_to_template_rps',5),(17,'2026_07_06_000002_add_taksonomi_kode_list',6),(18,'2026_07_09_000001_create_rps_approval_workflow',7),(19,'2026_07_06_000003_create_mk_bahan_kajian_table',8),(20,'2026_07_06_012630_create_permission_tables',9),(21,'2026_07_06_012631_create_personal_access_tokens_table',9),(22,'2026_07_09_000002_add_profile_to_users_table',9),(23,'2026_07_06_020000_add_parent_to_institusi_table',10),(24,'2026_07_06_030000_make_email_optional_nidn_unique',11),(25,'2026_07_06_010000_add_kode_dokumen_to_rps_version',12),(26,'2026_07_06_050000_dedupe_cpmk_subcpmk_add_unique',13);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mk_bahan_kajian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mk_bahan_kajian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bahan_kajian_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mk_bahan_kajian_kode_mk_bahan_kajian_id_unique` (`kode_mk`,`bahan_kajian_id`),
  KEY `mk_bahan_kajian_bahan_kajian_id_foreign` (`bahan_kajian_id`),
  KEY `mk_bahan_kajian_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `mk_bahan_kajian_bahan_kajian_id_foreign` FOREIGN KEY (`bahan_kajian_id`) REFERENCES `bahan_kajian` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mk_bahan_kajian_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mk_bahan_kajian` WRITE;
/*!40000 ALTER TABLE `mk_bahan_kajian` DISABLE KEYS */;
INSERT INTO `mk_bahan_kajian` VALUES (1,5,'FAR201',1,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201',2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,'FAR201',3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,'FAR201',5,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `mk_bahan_kajian` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mk_cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mk_cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `bobot` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mk_cpl_cpl_id_foreign` (`cpl_id`),
  KEY `mk_cpl_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `mk_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mk_cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mk_cpl` WRITE;
/*!40000 ALTER TABLE `mk_cpl` DISABLE KEYS */;
INSERT INTO `mk_cpl` VALUES (1,5,'FAR201',3,30.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201',4,25.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,'FAR201',5,15.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,'FAR201',7,20.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,'FAR201',8,10.00,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `mk_cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mk_keterampilan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mk_keterampilan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterampilan_id` bigint unsigned NOT NULL,
  `fokus_spesifik` text COLLATE utf8mb4_unicode_ci,
  `taksonomi_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mk_keterampilan_keterampilan_id_foreign` (`keterampilan_id`),
  KEY `mk_keterampilan_taksonomi_id_foreign` (`taksonomi_id`),
  KEY `mk_keterampilan_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `mk_keterampilan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mk_keterampilan_keterampilan_id_foreign` FOREIGN KEY (`keterampilan_id`) REFERENCES `keterampilan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `mk_keterampilan_taksonomi_id_foreign` FOREIGN KEY (`taksonomi_id`) REFERENCES `taksonomi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mk_keterampilan` WRITE;
/*!40000 ALTER TABLE `mk_keterampilan` DISABLE KEYS */;
INSERT INTO `mk_keterampilan` VALUES (1,5,'FAR201',1,NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201',2,NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,'FAR201',3,NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,'FAR201',5,NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `mk_keterampilan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `mk_pengampu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mk_pengampu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosen_nidn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peran` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'anggota',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mk_pengampu_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `mk_pengampu_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `mk_pengampu` WRITE;
/*!40000 ALTER TABLE `mk_pengampu` DISABLE KEYS */;
INSERT INTO `mk_pengampu` VALUES (1,5,'FAR201','0912345678','koordinator','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201','0923456789','anggota','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `mk_pengampu` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (1,'App\\Models\\User',1),(6,'App\\Models\\User',2),(2,'App\\Models\\User',3);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `notifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifikasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `konten` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unread',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifikasi_institusi_id_user_id_status_index` (`institusi_id`,`user_id`,`status`),
  CONSTRAINT `notifikasi_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `notifikasi` WRITE;
/*!40000 ALTER TABLE `notifikasi` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifikasi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pemenuhan_acuan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pemenuhan_acuan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `butir_acuan_id` bigint unsigned NOT NULL,
  `entity_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `rekomendasi_ai` tinyint(1) NOT NULL DEFAULT '0',
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pemenuhan_acuan_butir_acuan_id_foreign` (`butir_acuan_id`),
  KEY `pemenuhan_acuan_institusi_id_butir_acuan_id_index` (`institusi_id`,`butir_acuan_id`),
  KEY `pemenuhan_acuan_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  CONSTRAINT `pemenuhan_acuan_butir_acuan_id_foreign` FOREIGN KEY (`butir_acuan_id`) REFERENCES `butir_acuan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pemenuhan_acuan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pemenuhan_acuan` WRITE;
/*!40000 ALTER TABLE `pemenuhan_acuan` DISABLE KEYS */;
/*!40000 ALTER TABLE `pemenuhan_acuan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'dashboard.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(2,'konfigurasi-aturan.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(3,'konfigurasi-aturan.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(4,'taksonomi.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(5,'taksonomi.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(6,'dokumen-rujukan.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(7,'dokumen-rujukan.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(8,'checklist-acuan.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(9,'checklist-acuan.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(10,'kurikulum.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(11,'kurikulum.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(12,'overlap.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(13,'overlap.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(14,'generator.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(15,'generator.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(16,'rps.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(17,'rps.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(18,'persetujuan.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(19,'persetujuan.approve','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(20,'obaei.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(21,'obaei.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(22,'governance.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(23,'konfigurasi-ai.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(24,'konfigurasi-ai.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(25,'prompt-ai.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(26,'prompt-ai.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(27,'template-rps.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(28,'template-rps.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(29,'user.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(30,'user.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(31,'role.view','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(32,'role.manage','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(33,'prodi.view','web','2026-07-05 18:15:04','2026-07-05 18:15:04'),(34,'prodi.manage','web','2026-07-05 18:15:04','2026-07-05 18:15:04');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',1,'web','3409d52e6ff96816d853c94cf6bf804d2ce7d468d231079be43557e855b2e2ba','[\"*\"]','2026-07-05 17:39:14',NULL,'2026-07-05 17:39:13','2026-07-05 17:39:14'),(2,'App\\Models\\User',1,'web','97618ba27ecb3d3eaf5815d9018b5bc499aa08673c955c4ce14c3686d25d5de7','[\"*\"]','2026-07-05 17:39:33',NULL,'2026-07-05 17:39:33','2026-07-05 17:39:33'),(3,'App\\Models\\User',1,'web','e36f9a167e577899be5d9e688769d9c7b783f4261aedf07e1945afab381d2595','[\"*\"]','2026-07-05 17:41:00',NULL,'2026-07-05 17:41:00','2026-07-05 17:41:00'),(4,'App\\Models\\User',1,'web','cbc62cd0e230c2639eb03cf1439132dc0c64d2434b0a691a401765178c1df480','[\"*\"]','2026-07-05 17:42:10',NULL,'2026-07-05 17:42:10','2026-07-05 17:42:10'),(5,'App\\Models\\User',1,'web','46d597387693f60bad27a1bf9f323a88b39d1ad6fd35750b1ce76aa714f1eb8e','[\"*\"]','2026-07-05 17:42:41',NULL,'2026-07-05 17:42:40','2026-07-05 17:42:41'),(6,'App\\Models\\User',1,'web','89719f81edde5cf4846f1e2ebf9b7523caee29f406cc9febaae0025578142372','[\"*\"]','2026-07-05 17:57:32',NULL,'2026-07-05 17:57:30','2026-07-05 17:57:32'),(7,'App\\Models\\User',1,'web','4766a31ae109863ae4ceb610bb7c8d02c12629554c2ad3631b0989221cb6dfdf','[\"*\"]','2026-07-05 17:59:30',NULL,'2026-07-05 17:59:00','2026-07-05 17:59:30'),(8,'App\\Models\\User',1,'web','a69f8956598fbef0ffe33505c5cfda19f0fc7a4df9188822be96bfdb9e60ec3e','[\"*\"]','2026-07-05 18:01:51',NULL,'2026-07-05 18:01:50','2026-07-05 18:01:51'),(10,'App\\Models\\User',1,'web','3ad49f2b60ae7a717ce9d6a09db32f36572ecf6b3ed1ff2856264b291e550a06','[\"*\"]','2026-07-05 18:15:21',NULL,'2026-07-05 18:15:20','2026-07-05 18:15:21'),(11,'App\\Models\\User',1,'web','3f2bbaf78868275e18c024cc7f30911f7a50acda0445cd5938a99c92c2547c95','[\"*\"]','2026-07-05 18:29:43',NULL,'2026-07-05 18:29:43','2026-07-05 18:29:43'),(12,'App\\Models\\User',1,'web','96308bd4dd45aab7e3405bc5129be85379c54dd134d0a3c92fba8a2e5eb4da09','[\"*\"]','2026-07-05 18:31:58',NULL,'2026-07-05 18:31:57','2026-07-05 18:31:58'),(13,'App\\Models\\User',1,'web','c18e6eaba92172bfcf9165257f4305d90b3d1e59e35c47cfa6a8c37241ff0153','[\"*\"]','2026-07-05 18:46:34',NULL,'2026-07-05 18:46:34','2026-07-05 18:46:34'),(15,'App\\Models\\User',1,'web','059a2c7112f6352c3dd7b3aa94155aceb13fccfdc370817723555b757531b02d','[\"*\"]','2026-07-05 18:50:53',NULL,'2026-07-05 18:50:50','2026-07-05 18:50:53'),(16,'App\\Models\\User',1,'web','4d8552ab5c9409ea1114b9afbf6c8081cf1f958916821eb46802f26a2b38ba1b','[\"*\"]','2026-07-05 18:52:05',NULL,'2026-07-05 18:52:05','2026-07-05 18:52:05'),(17,'App\\Models\\User',1,'web','7c643dbb62e5dbe74603b336d8e906db317d0ebc54a21a892d80869b6fe02f7f','[\"*\"]','2026-07-05 19:09:11',NULL,'2026-07-05 19:09:11','2026-07-05 19:09:11'),(18,'App\\Models\\User',1,'web','763d9f25fbbc937a769fc25863470239f5f2d9a4b2fac49a8c9242ebe6073e3d','[\"*\"]',NULL,NULL,'2026-07-05 19:10:17','2026-07-05 19:10:17'),(21,'App\\Models\\User',1,'web','5dbacdbdc037c139e7376023a537cfd46223de0534995ce7de0f63197e902d9b','[\"*\"]','2026-07-05 19:42:10',NULL,'2026-07-05 19:42:10','2026-07-05 19:42:10'),(22,'App\\Models\\User',1,'web','43db72869d2b875f4bba2da3f37337dda275ce86ce8ddc9079f1d319d3c20ee1','[\"*\"]',NULL,NULL,'2026-07-05 20:02:33','2026-07-05 20:02:33'),(23,'App\\Models\\User',1,'web','ffd3bafc53e46d65660e28beaa5bddf4b238e9f688086950b723c74aa00abee5','[\"*\"]',NULL,NULL,'2026-07-05 20:02:36','2026-07-05 20:02:36'),(24,'App\\Models\\User',1,'web','450a4a981f7c95f0c92b113b5cf0f1ecc4b705844404f2b53ec110d33972e4c0','[\"*\"]','2026-07-06 02:03:58',NULL,'2026-07-05 20:10:09','2026-07-06 02:03:58'),(25,'App\\Models\\User',3,'web','40d7c7bb0a755c17e5ad7f83f2c497f1cd37afbc308dd8edf4e86b4bb7a79347','[\"*\"]','2026-07-05 21:21:33',NULL,'2026-07-05 21:14:25','2026-07-05 21:21:33'),(26,'App\\Models\\User',1,'web','020a67c87c9cf4cd8f45f4c086dea42a5645b2762fb35e78a7ac8dd7ec78c3c2','[\"*\"]',NULL,NULL,'2026-07-05 22:46:44','2026-07-05 22:46:44'),(27,'App\\Models\\User',1,'web','9ec5d33ac47f4571dc5d7b211d2c838e4837b60425d3cd9b9364f93a7da14aa3','[\"*\"]','2026-07-06 01:42:08',NULL,'2026-07-05 23:39:42','2026-07-06 01:42:08'),(28,'App\\Models\\User',3,'web','0e7fd5a6b60e029a2d32ccc280a09e0ccee488dfadde4c8b78fdd5f7f2a1207a','[\"*\"]','2026-07-06 02:01:54',NULL,'2026-07-05 23:46:29','2026-07-06 02:01:54');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pl_cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pl_cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `profil_lulusan_id` bigint unsigned NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pl_cpl_profil_lulusan_id_cpl_id_unique` (`profil_lulusan_id`,`cpl_id`),
  KEY `pl_cpl_institusi_id_foreign` (`institusi_id`),
  KEY `pl_cpl_cpl_id_foreign` (`cpl_id`),
  CONSTRAINT `pl_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pl_cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pl_cpl_profil_lulusan_id_foreign` FOREIGN KEY (`profil_lulusan_id`) REFERENCES `profil_lulusan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pl_cpl` WRITE;
/*!40000 ALTER TABLE `pl_cpl` DISABLE KEYS */;
INSERT INTO `pl_cpl` VALUES (1,5,1,1,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,1,4,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,2,3,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,2,4,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,2,7,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,2,8,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,3,2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(9,5,3,5,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(10,5,4,5,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(11,5,4,6,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `pl_cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `profil_lulusan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `profil_lulusan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kurikulum_id` bigint unsigned DEFAULT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `profil_lulusan_kurikulum_id_kode_unique` (`kurikulum_id`,`kode`),
  KEY `profil_lulusan_institusi_id_foreign` (`institusi_id`),
  CONSTRAINT `profil_lulusan_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `profil_lulusan_kurikulum_id_foreign` FOREIGN KEY (`kurikulum_id`) REFERENCES `kurikulum` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `profil_lulusan` WRITE;
/*!40000 ALTER TABLE `profil_lulusan` DISABLE KEYS */;
INSERT INTO `profil_lulusan` VALUES (1,5,1,'PL1','Ilmuwan farmasi yang menguasai konsep sains kefarmasian secara komprehensif.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,'PL2','Pengkaji obat yang kompeten dalam menganalisis mekanisme kerja dan penggunaan obat secara rasional.','2026-07-06 01:47:28','2026-07-06 02:01:53'),(3,5,1,'PL3','Komunikator yang mampu menyampaikan informasi kefarmasian secara ilmiah dan etis.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,1,'PL4','Pembelajar sepanjang hayat yang adaptif terhadap perkembangan ilmu kefarmasian.','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `profil_lulusan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `prompt_template`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prompt_template` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `jenis_output` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_mk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sistem_prompt` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `skema_output` json DEFAULT NULL,
  `few_shot` json DEFAULT NULL,
  `versi` int unsigned NOT NULL DEFAULT '1',
  `aktif` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prompt_template_institusi_id_foreign` (`institusi_id`),
  KEY `prompt_template_jenis_output_jenis_mk_index` (`jenis_output`,`jenis_mk`),
  CONSTRAINT `prompt_template_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `prompt_template` WRITE;
/*!40000 ALTER TABLE `prompt_template` DISABLE KEYS */;
/*!40000 ALTER TABLE `prompt_template` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `referensi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `referensi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipe` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sitasi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `referensi_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `referensi_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `referensi` WRITE;
/*!40000 ALTER TABLE `referensi` DISABLE KEYS */;
INSERT INTO `referensi` VALUES (1,5,'FAR201','utama','Katzung BG, Vanderah TW. Basic & Clinical Pharmacology. 15th ed. New York: McGraw-Hill Education; 2021.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,'FAR201','utama','Brunton LL, Knollmann BC. Goodman & Gilman\'s The Pharmacological Basis of Therapeutics. 14th ed. New York: McGraw-Hill; 2023.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,'FAR201','utama','Departemen Farmakologi dan Terapeutik FKUI. Farmakologi dan Terapi. Edisi 6. Jakarta: Badan Penerbit FKUI; 2016.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,'FAR201','pendukung','Ritter JM, Flower RJ, Henderson G, dkk. Rang and Dale\'s Pharmacology. 9th ed. Edinburgh: Elsevier; 2020.','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,'FAR201','pendukung','Ikatan Apoteker Indonesia. ISO Farmakoterapi. Jakarta: PT ISFI Penerbitan; 2020.','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `referensi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(25,1),(26,1),(27,1),(28,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(1,2),(2,2),(3,2),(4,2),(5,2),(6,2),(7,2),(8,2),(9,2),(10,2),(11,2),(12,2),(13,2),(14,2),(15,2),(16,2),(17,2),(18,2),(20,2),(21,2),(22,2),(23,2),(24,2),(25,2),(26,2),(27,2),(28,2),(1,3),(2,3),(4,3),(8,3),(10,3),(12,3),(14,3),(16,3),(18,3),(19,3),(20,3),(1,4),(2,4),(4,4),(8,4),(9,4),(10,4),(11,4),(12,4),(13,4),(14,4),(15,4),(16,4),(17,4),(18,4),(19,4),(20,4),(21,4),(27,4),(1,5),(2,5),(4,5),(8,5),(10,5),(14,5),(15,5),(16,5),(17,5),(18,5),(20,5),(27,5),(1,6),(4,6),(8,6),(10,6),(14,6),(15,6),(16,6),(27,6),(1,7),(8,7),(10,7),(12,7),(14,7),(16,7),(18,7),(19,7),(20,7),(1,8),(8,8),(10,8),(12,8),(14,8),(16,8),(18,8),(19,8),(20,8),(1,9),(10,9),(16,9),(20,9);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'super-admin','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(2,'admin-akademik','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(3,'pimpinan-fakultas','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(4,'kaprodi','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(5,'koordinator-mk','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(6,'dosen','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(7,'stpmp','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(8,'psmf','web','2026-07-05 17:29:11','2026-07-05 17:29:11'),(9,'lpm','web','2026-07-05 17:29:11','2026-07-05 17:29:11');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rps_approval_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rps_approval_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `rps_version_id` bigint unsigned NOT NULL,
  `aksi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dari_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ke_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `actor_id` bigint unsigned DEFAULT NULL,
  `actor_nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rps_approval_log_institusi_id_foreign` (`institusi_id`),
  KEY `rps_approval_log_rps_version_id_index` (`rps_version_id`),
  CONSTRAINT `rps_approval_log_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rps_approval_log_rps_version_id_foreign` FOREIGN KEY (`rps_version_id`) REFERENCES `rps_version` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rps_approval_log` WRITE;
/*!40000 ALTER TABLE `rps_approval_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `rps_approval_log` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rps_minggu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rps_minggu` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rps_version_id` bigint unsigned NOT NULL,
  `minggu_ke` tinyint unsigned NOT NULL,
  `sub_cpmk_id` bigint unsigned DEFAULT NULL,
  `indikator` text COLLATE utf8mb4_unicode_ci,
  `teknik_kriteria_penilaian` text COLLATE utf8mb4_unicode_ci,
  `metode_pembelajaran` text COLLATE utf8mb4_unicode_ci,
  `bentuk_luring` text COLLATE utf8mb4_unicode_ci,
  `bentuk_daring` text COLLATE utf8mb4_unicode_ci,
  `materi_pustaka` text COLLATE utf8mb4_unicode_ci,
  `pengalaman_belajar` text COLLATE utf8mb4_unicode_ci,
  `estimasi_waktu` json DEFAULT NULL,
  `bobot_penilaian` decimal(6,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rps_minggu_rps_version_id_foreign` (`rps_version_id`),
  KEY `rps_minggu_sub_cpmk_id_foreign` (`sub_cpmk_id`),
  CONSTRAINT `rps_minggu_rps_version_id_foreign` FOREIGN KEY (`rps_version_id`) REFERENCES `rps_version` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rps_minggu_sub_cpmk_id_foreign` FOREIGN KEY (`sub_cpmk_id`) REFERENCES `sub_cpmk` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rps_minggu` WRITE;
/*!40000 ALTER TABLE `rps_minggu` DISABLE KEYS */;
INSERT INTO `rps_minggu` VALUES (1,1,1,1,'Ketepatan penjelasan dan analisis terkait pengantar farmakologi dan ruang lingkupnya.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Pengantar farmakologi dan ruang lingkupnya. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi pengantar farmakologi dan ruang lingkupnya dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,1,2,2,'Ketepatan penjelasan dan analisis terkait farmakokinetika: absorpsi, distribusi, metabolisme, dan ekskresi.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakokinetika: absorpsi, distribusi, metabolisme, dan ekskresi. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakokinetika: absorpsi, distribusi, metabolisme, dan ekskresi dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,1,3,3,'Ketepatan penjelasan dan analisis terkait farmakodinamika: reseptor dan hubungan dosis-respons.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakodinamika: reseptor dan hubungan dosis-respons. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakodinamika: reseptor dan hubungan dosis-respons dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',5.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,1,4,4,'Ketepatan penjelasan dan analisis terkait farmakologi sistem saraf otonom.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakologi sistem saraf otonom. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakologi sistem saraf otonom dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,1,5,5,'Ketepatan penjelasan dan analisis terkait farmakologi sistem kardiovaskular.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakologi sistem kardiovaskular. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakologi sistem kardiovaskular dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',8.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,1,6,6,'Ketepatan penjelasan dan analisis terkait farmakologi sistem saraf pusat.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakologi sistem saraf pusat. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakologi sistem saraf pusat dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',5.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,1,7,7,'Ketepatan penjelasan dan analisis terkait farmakologi sistem pencernaan dan endokrin.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Farmakologi sistem pencernaan dan endokrin. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi farmakologi sistem pencernaan dan endokrin dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,1,8,NULL,'Ketepatan menjawab soal ujian sesuai capaian yang diujikan.','Ujian tertulis; penilaian mengacu pada kunci jawaban dan rubrik.','Ujian tertulis (closed book)','Ujian tatap muka di kelas',NULL,'Ujian Tengah Semester (UTS) — materi minggu terkait; Pustaka [1], [2], [3].','Mahasiswa mengerjakan soal ujian secara mandiri.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',20.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(9,1,9,8,'Ketepatan penjelasan dan analisis terkait antimikroba: prinsip dan antibiotik beta-laktam.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Antimikroba: prinsip dan antibiotik beta-laktam. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi antimikroba: prinsip dan antibiotik beta-laktam dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(10,1,10,9,'Ketepatan penjelasan dan analisis terkait antibiotik golongan lain dan resistensi antimikroba.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Antibiotik golongan lain dan resistensi antimikroba. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi antibiotik golongan lain dan resistensi antimikroba dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',8.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(11,1,11,10,'Ketepatan penjelasan dan analisis terkait antivirus, antijamur, dan antiparasit.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Antivirus, antijamur, dan antiparasit. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi antivirus, antijamur, dan antiparasit dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',5.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(12,1,12,11,'Ketepatan penjelasan dan analisis terkait efek samping obat dan interaksi obat.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Efek samping obat dan interaksi obat. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi efek samping obat dan interaksi obat dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(13,1,13,12,'Ketepatan penjelasan dan analisis terkait prinsip penggunaan obat yang rasional.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Prinsip penggunaan obat yang rasional. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi prinsip penggunaan obat yang rasional dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',9.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(14,1,14,13,'Ketepatan penjelasan dan analisis terkait presentasi kajian farmakologi terapan.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Presentasi kajian farmakologi terapan. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi presentasi kajian farmakologi terapan dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',8.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(15,1,15,14,'Ketepatan penjelasan dan analisis terkait diskusi kasus dan telaah pustaka.','Partisipasi, tanya jawab, dan penilaian tugas dengan rubrik.','Kuliah interaktif, diskusi kelompok, dan studi kasus','Kuliah tatap muka di kelas','LMS: materi, kuis, dan forum diskusi asinkron','Diskusi kasus dan telaah pustaka. Pustaka [1], [2], [3].','Mahasiswa mengkaji materi diskusi kasus dan telaah pustaka dan mengerjakan latihan/tugas terstruktur.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',7.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(16,1,16,NULL,'Ketepatan menjawab soal ujian sesuai capaian yang diujikan.','Ujian tertulis; penilaian mengacu pada kunci jawaban dan rubrik.','Ujian tertulis (closed book)','Ujian tatap muka di kelas',NULL,'Ujian Akhir Semester (UAS) — materi minggu terkait; Pustaka [1], [2], [3].','Mahasiswa mengerjakan soal ujian secara mandiri.','{\"teks\": \"TM 3×50 menit, PT 3×60 menit, BM 3×60 menit · Total 510 menit/minggu\", \"bm_menit\": 180, \"pt_menit\": 180, \"tm_menit\": 150, \"total_menit\": 510, \"praktik_menit\": 0}',25.00,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `rps_minggu` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rps_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rps_version` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ulid` char(26) COLLATE utf8mb4_unicode_ci NOT NULL,
  `institusi_id` bigint unsigned NOT NULL,
  `kode_mk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `versi` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `bahasa` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'id',
  `kode_dokumen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `versi_pedoman_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `koordinator_mk` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `catatan_review` text COLLATE utf8mb4_unicode_ci,
  `tanggal_penyusunan` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rps_version_ulid_unique` (`ulid`),
  KEY `rps_version_versi_pedoman_id_foreign` (`versi_pedoman_id`),
  KEY `rps_version_institusi_id_kode_mk_index` (`institusi_id`,`kode_mk`),
  CONSTRAINT `rps_version_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rps_version_versi_pedoman_id_foreign` FOREIGN KEY (`versi_pedoman_id`) REFERENCES `versi_pedoman` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rps_version` WRITE;
/*!40000 ALTER TABLE `rps_version` DISABLE KEYS */;
INSERT INTO `rps_version` VALUES (1,'01KWVD6PD4KRK93RHFWMSAK1CS',5,'FAR201',1,'approved','id','RPS/FF/FAR201/2024',1,2,2,'2024-07-20 01:00:00',3,'2024-07-25 02:00:00','RPS telah ditinjau dan disetujui oleh Ketua Program Studi.','2024-07-15','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `rps_version` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rubrik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubrik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `komponen_penilaian_id` bigint unsigned NOT NULL,
  `jenis` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'analitik',
  `jumlah_level_skala` tinyint unsigned NOT NULL DEFAULT '4',
  `label_skala` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubrik_komponen_penilaian_id_foreign` (`komponen_penilaian_id`),
  CONSTRAINT `rubrik_komponen_penilaian_id_foreign` FOREIGN KEY (`komponen_penilaian_id`) REFERENCES `komponen_penilaian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rubrik` WRITE;
/*!40000 ALTER TABLE `rubrik` DISABLE KEYS */;
INSERT INTO `rubrik` VALUES (1,3,'analitik',4,'[\"Kurang\", \"Cukup\", \"Baik\", \"Sangat Baik\"]','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `rubrik` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `rubrik_kriteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rubrik_kriteria` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rubrik_id` bigint unsigned NOT NULL,
  `kriteria` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bobot` decimal(6,2) DEFAULT NULL,
  `deskriptor` json DEFAULT NULL,
  `urutan` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rubrik_kriteria_rubrik_id_foreign` (`rubrik_id`),
  CONSTRAINT `rubrik_kriteria_rubrik_id_foreign` FOREIGN KEY (`rubrik_id`) REFERENCES `rubrik` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `rubrik_kriteria` WRITE;
/*!40000 ALTER TABLE `rubrik_kriteria` DISABLE KEYS */;
INSERT INTO `rubrik_kriteria` VALUES (1,1,'Penguasaan Materi',40.00,'[\"Materi kurang dikuasai dan banyak keliru.\", \"Materi cukup dikuasai dengan sedikit kekeliruan.\", \"Materi dikuasai dengan baik dan akurat.\", \"Materi dikuasai sangat baik, akurat, dan mendalam.\"]',1,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,1,'Sistematika Penyajian',30.00,'[\"Penyajian tidak terstruktur.\", \"Penyajian cukup terstruktur.\", \"Penyajian terstruktur dan runtut.\", \"Penyajian sangat terstruktur, runtut, dan menarik.\"]',2,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,1,'Kemampuan Diskusi',30.00,'[\"Sulit menjawab pertanyaan.\", \"Menjawab sebagian pertanyaan.\", \"Menjawab pertanyaan dengan tepat.\", \"Menjawab pertanyaan dengan tepat dan argumentatif.\"]',3,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `rubrik_kriteria` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sessions`;
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

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `source_citation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `source_citation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `entity_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `dokumen_id` bigint unsigned DEFAULT NULL,
  `halaman` int unsigned DEFAULT NULL,
  `cuplikan_teks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `source_citation_institusi_id_foreign` (`institusi_id`),
  KEY `source_citation_dokumen_id_foreign` (`dokumen_id`),
  KEY `source_citation_entity_type_entity_id_index` (`entity_type`,`entity_id`),
  CONSTRAINT `source_citation_dokumen_id_foreign` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `source_citation_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `source_citation` WRITE;
/*!40000 ALTER TABLE `source_citation` DISABLE KEYS */;
/*!40000 ALTER TABLE `source_citation` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `sub_cpmk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sub_cpmk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `cpmk_id` bigint unsigned NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `minggu_mulai` tinyint unsigned DEFAULT NULL,
  `minggu_selesai` tinyint unsigned DEFAULT NULL,
  `bobot_persen` decimal(6,2) DEFAULT NULL,
  `taksonomi_id` bigint unsigned DEFAULT NULL,
  `taksonomi_kode` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sub_cpmk_inst_cpmk_kode_unique` (`institusi_id`,`cpmk_id`,`kode`),
  KEY `sub_cpmk_cpmk_id_foreign` (`cpmk_id`),
  KEY `sub_cpmk_taksonomi_id_foreign` (`taksonomi_id`),
  CONSTRAINT `sub_cpmk_cpmk_id_foreign` FOREIGN KEY (`cpmk_id`) REFERENCES `cpmk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sub_cpmk_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sub_cpmk_taksonomi_id_foreign` FOREIGN KEY (`taksonomi_id`) REFERENCES `taksonomi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `sub_cpmk` WRITE;
/*!40000 ALTER TABLE `sub_cpmk` DISABLE KEYS */;
INSERT INTO `sub_cpmk` VALUES (1,5,1,'Sub-CPMK1.1','Mampu menjelaskan ruang lingkup dan peran farmakologi dalam kefarmasian.',1,1,6.00,2,'[\"C2\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,1,'Sub-CPMK1.2','Mampu menjelaskan proses farmakokinetika (absorpsi, distribusi, metabolisme, ekskresi).',2,2,6.00,2,'[\"C2\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,1,'Sub-CPMK1.3','Mampu menjelaskan konsep farmakodinamika dan interaksi obat-reseptor.',3,3,6.00,2,'[\"C2\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,2,'Sub-CPMK2.1','Mampu menganalisis kerja obat pada sistem saraf otonom.',4,4,6.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,2,'Sub-CPMK2.2','Mampu menganalisis kerja obat pada sistem kardiovaskular.',5,5,6.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,2,'Sub-CPMK2.3','Mampu menganalisis kerja obat pada sistem saraf pusat.',6,6,6.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,2,'Sub-CPMK2.4','Mampu menganalisis kerja obat pada sistem pencernaan dan endokrin.',7,7,7.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,3,'Sub-CPMK3.1','Mampu menganalisis prinsip antimikroba dan antibiotik beta-laktam.',9,9,7.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(9,5,3,'Sub-CPMK3.2','Mampu menganalisis golongan antibiotik lain dan mekanisme resistensi.',10,10,7.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(10,5,3,'Sub-CPMK3.3','Mampu menganalisis obat antivirus, antijamur, dan antiparasit.',11,11,7.00,4,'[\"C4\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(11,5,4,'Sub-CPMK4.1','Mampu mengevaluasi efek samping dan interaksi obat.',12,12,8.00,5,'[\"C5\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(12,5,4,'Sub-CPMK4.2','Mampu mengevaluasi ketepatan penggunaan obat yang rasional.',13,13,8.00,5,'[\"C5\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(13,5,5,'Sub-CPMK5.1','Mampu menyusun dan mempresentasikan kajian farmakologi terapan.',14,14,10.00,9,'[\"A3\"]','2026-07-06 01:47:28','2026-07-06 01:47:28'),(14,5,5,'Sub-CPMK5.2','Mampu berdiskusi dan menelaah pustaka farmakologi secara kritis.',15,15,10.00,9,'[\"A3\"]','2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `sub_cpmk` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `taksonomi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `taksonomi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `domain` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kerangka` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kode` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `level` tinyint unsigned NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `kata_kerja` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `taksonomi_institusi_id_foreign` (`institusi_id`),
  KEY `taksonomi_domain_kerangka_index` (`domain`,`kerangka`),
  CONSTRAINT `taksonomi_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `taksonomi` WRITE;
/*!40000 ALTER TABLE `taksonomi` DISABLE KEYS */;
INSERT INTO `taksonomi` VALUES (1,NULL,'kognitif','bloom_anderson','C1','Mengingat',1,'Menarik kembali pengetahuan dari memori.','[\"mendefinisikan\", \"menyebutkan\", \"mengidentifikasi\", \"menuliskan\", \"menyatakan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(2,NULL,'kognitif','bloom_anderson','C2','Memahami',2,'Membangun makna dari informasi.','[\"menjelaskan\", \"menguraikan\", \"merangkum\", \"mencontohkan\", \"mengklasifikasikan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(3,NULL,'kognitif','bloom_anderson','C3','Menerapkan',3,'Menggunakan prosedur pada situasi tertentu.','[\"menerapkan\", \"menghitung\", \"mendemonstrasikan\", \"menggunakan\", \"menyelesaikan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(4,NULL,'kognitif','bloom_anderson','C4','Menganalisis',4,'Menguraikan menjadi bagian dan hubungannya.','[\"menganalisis\", \"membedakan\", \"mengorganisasi\", \"membandingkan\", \"menelaah\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(5,NULL,'kognitif','bloom_anderson','C5','Mengevaluasi',5,'Membuat penilaian berdasarkan kriteria.','[\"mengevaluasi\", \"menilai\", \"mengkritik\", \"memutuskan\", \"merekomendasikan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(6,NULL,'kognitif','bloom_anderson','C6','Mencipta',6,'Menyusun elemen menjadi kesatuan/produk baru.','[\"merancang\", \"menyusun\", \"mengembangkan\", \"memformulasikan\", \"menciptakan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(7,NULL,'afektif','krathwohl','A1','Menerima',1,'Kesediaan memperhatikan fenomena/stimulus.','[\"menanyakan\", \"mengikuti\", \"memilih\", \"mematuhi\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(8,NULL,'afektif','krathwohl','A2','Merespons',2,'Partisipasi aktif dan reaksi terhadap stimulus.','[\"menjawab\", \"membantu\", \"mempresentasikan\", \"melaksanakan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(9,NULL,'afektif','krathwohl','A3','Menghargai',3,'Memberi nilai pada objek/perilaku.','[\"menghargai\", \"mendukung\", \"meyakinkan\", \"menginisiasi\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(10,NULL,'afektif','krathwohl','A4','Mengorganisasi',4,'Memadukan nilai menjadi sistem nilai.','[\"mengorganisasi\", \"membandingkan\", \"memadukan\", \"merumuskan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(11,NULL,'afektif','krathwohl','A5','Karakterisasi',5,'Menjadikan nilai sebagai karakter/pola hidup.','[\"menunjukkan\", \"membiasakan\", \"mempertahankan\", \"membuktikan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(12,NULL,'psikomotorik','dave','P1','Imitasi',1,'Meniru gerakan setelah mengamati.','[\"meniru\", \"mengikuti\", \"mengulangi\", \"mencoba\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(13,NULL,'psikomotorik','dave','P2','Manipulasi',2,'Melakukan gerakan berdasar instruksi.','[\"melakukan\", \"melaksanakan\", \"mengoperasikan\", \"membuat\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(14,NULL,'psikomotorik','dave','P3','Presisi',3,'Melakukan gerakan dengan akurat & mandiri.','[\"menunjukkan\", \"mengkalibrasi\", \"mengendalikan\", \"menyempurnakan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(15,NULL,'psikomotorik','dave','P4','Artikulasi',4,'Mengoordinasikan serangkaian gerakan selaras.','[\"mengoordinasikan\", \"mengintegrasikan\", \"menyesuaikan\", \"merangkai\"]','2026-07-04 20:28:27','2026-07-04 20:28:27'),(16,NULL,'psikomotorik','dave','P5','Naturalisasi',5,'Melakukan gerakan otomatis & alami.','[\"mendesain\", \"menciptakan\", \"mengelola\", \"membiasakan\"]','2026-07-04 20:28:27','2026-07-04 20:28:27');
/*!40000 ALTER TABLE `taksonomi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `target_cpl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `target_cpl` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `cpl_id` bigint unsigned NOT NULL,
  `angkatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ambang_nilai` decimal(5,2) DEFAULT NULL,
  `persentase_target` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `target_cpl_institusi_id_foreign` (`institusi_id`),
  KEY `target_cpl_cpl_id_foreign` (`cpl_id`),
  CONSTRAINT `target_cpl_cpl_id_foreign` FOREIGN KEY (`cpl_id`) REFERENCES `cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `target_cpl_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `target_cpl` WRITE;
/*!40000 ALTER TABLE `target_cpl` DISABLE KEYS */;
INSERT INTO `target_cpl` VALUES (1,5,1,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(2,5,2,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(3,5,3,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(4,5,4,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(5,5,5,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(6,5,6,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(7,5,7,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28'),(8,5,8,'2024',60.00,75.00,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `target_cpl` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `template_rps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `template_rps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `dokumen_asal_id` bigint unsigned DEFAULT NULL,
  `nama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `berkas_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `berkas_nama_asli` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `format` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '0',
  `struktur_kolom` json DEFAULT NULL,
  `dikonfirmasi_oleh` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `template_rps_institusi_id_foreign` (`institusi_id`),
  KEY `template_rps_dokumen_asal_id_foreign` (`dokumen_asal_id`),
  CONSTRAINT `template_rps_dokumen_asal_id_foreign` FOREIGN KEY (`dokumen_asal_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE SET NULL,
  CONSTRAINT `template_rps_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `template_rps` WRITE;
/*!40000 ALTER TABLE `template_rps` DISABLE KEYS */;
/*!40000 ALTER TABLE `template_rps` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `tindak_lanjut`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tindak_lanjut` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `evaluasi_cpl_id` bigint unsigned NOT NULL,
  `sub_cpmk_id` bigint unsigned DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prioritas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tindak_lanjut_institusi_id_foreign` (`institusi_id`),
  KEY `tindak_lanjut_evaluasi_cpl_id_foreign` (`evaluasi_cpl_id`),
  KEY `tindak_lanjut_sub_cpmk_id_foreign` (`sub_cpmk_id`),
  CONSTRAINT `tindak_lanjut_evaluasi_cpl_id_foreign` FOREIGN KEY (`evaluasi_cpl_id`) REFERENCES `evaluasi_cpl` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tindak_lanjut_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tindak_lanjut_sub_cpmk_id_foreign` FOREIGN KEY (`sub_cpmk_id`) REFERENCES `sub_cpmk` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `tindak_lanjut` WRITE;
/*!40000 ALTER TABLE `tindak_lanjut` DISABLE KEYS */;
/*!40000 ALTER TABLE `tindak_lanjut` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nidn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jabatan` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_nidn_unique` (`nidn`),
  KEY `users_institusi_id_foreign` (`institusi_id`),
  CONSTRAINT `users_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,NULL,'Super Administrator','superadmin@rps.local','0000000001',NULL,1,NULL,'$2y$12$iSpAliUA0Jogkxw4PUxeweecXogeSQMBABDrIdLkA2V/rFZDxr3MS',NULL,'2026-07-05 17:29:11','2026-07-05 20:02:04'),(2,5,'Dr. Uji Dosen','uji.dosen@rps.local','0912345678',NULL,1,NULL,'$2y$12$dUOBQ31UqX4NPQQEr93A7O2KSoq1tnL9F03/nhVP.nc5ARYpF5jhW',NULL,'2026-07-05 17:42:40','2026-07-05 19:18:03'),(3,1,'Admin',NULL,'26092001','Admin',1,NULL,'$2y$12$WU93VBGk5Vh6oyZoTHlgEeyiPQs1Nb8nMDNfGezYR.eks9DU3Hu/e',NULL,'2026-07-05 21:06:53','2026-07-05 21:06:53');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `validasi_overlap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `validasi_overlap` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `institusi_id` bigint unsigned NOT NULL,
  `keterampilan_id` bigint unsigned NOT NULL,
  `mk_terlibat` json DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `analisis` text COLLATE utf8mb4_unicode_ci,
  `rekomendasi` text COLLATE utf8mb4_unicode_ci,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `validasi_overlap_institusi_id_foreign` (`institusi_id`),
  KEY `validasi_overlap_keterampilan_id_foreign` (`keterampilan_id`),
  CONSTRAINT `validasi_overlap_institusi_id_foreign` FOREIGN KEY (`institusi_id`) REFERENCES `institusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `validasi_overlap_keterampilan_id_foreign` FOREIGN KEY (`keterampilan_id`) REFERENCES `keterampilan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `validasi_overlap` WRITE;
/*!40000 ALTER TABLE `validasi_overlap` DISABLE KEYS */;
/*!40000 ALTER TABLE `validasi_overlap` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `versi_pedoman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `versi_pedoman` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dokumen_id` bigint unsigned NOT NULL,
  `versi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_berlaku` date DEFAULT NULL,
  `tanggal_nonaktif` date DEFAULT NULL,
  `mk_terdampak` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `versi_pedoman_dokumen_id_foreign` (`dokumen_id`),
  CONSTRAINT `versi_pedoman_dokumen_id_foreign` FOREIGN KEY (`dokumen_id`) REFERENCES `dokumen_rujukan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `versi_pedoman` WRITE;
/*!40000 ALTER TABLE `versi_pedoman` DISABLE KEYS */;
INSERT INTO `versi_pedoman` VALUES (1,1,'2024','2024-01-01',NULL,NULL,'2026-07-06 01:47:28','2026-07-06 01:47:28');
/*!40000 ALTER TABLE `versi_pedoman` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

