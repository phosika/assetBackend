-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: asset_db
-- ------------------------------------------------------
-- Server version	8.0.45

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

--
-- Table structure for table `asset_categories`
--

DROP TABLE IF EXISTS `asset_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_code` varchar(50) NOT NULL,
  `category_name` varchar(200) NOT NULL,
  `description` text,
  `parent_id` int DEFAULT NULL,
  `level` int DEFAULT '1' COMMENT '1: ລະດັບໃຫຍ່, 2: ລະດັບກາງ, 3: ລະດັບຍ່ອຍ',
  `path` varchar(255) DEFAULT NULL COMMENT 'ເສັ້ນທາງຂອງລະດັບຊັ້ນ ເຊັ່ນ: 1/5/10',
  `depreciation_method` enum('straight_line','declining_balance','none') DEFAULT 'straight_line',
  `useful_life_years` int DEFAULT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL COMMENT 'ອັດຕາຄ່າເສື່ອມລາຄາ (%)',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_code` (`category_code`),
  KEY `created_by` (`created_by`),
  KEY `idx_category_path` (`path`),
  KEY `idx_category_parent` (`parent_id`),
  KEY `idx_category_level` (`level`),
  CONSTRAINT `asset_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `asset_categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `check_category_level` CHECK ((`level` <= 3))
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `check_category_level` BEFORE INSERT ON `asset_categories` FOR EACH ROW BEGIN
    DECLARE v_parent_level INT;
    
    -- ກວດສອບວ່າມີ parent_id ຫຼືບໍ່
    IF NEW.parent_id IS NOT NULL THEN
        -- ດຶງຂໍ້ມູນ level ຂອງ parent
        SELECT level INTO v_parent_level
        FROM asset_categories
        WHERE id = NEW.parent_id;
        
        -- ກວດສອບວ່າ level ຂອງ parent ເກີນ 3 ຫຼືບໍ່
        IF v_parent_level >= 3 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Cannot create category: Maximum level (3) reached';
        END IF;
        
        -- ຕັ້ງ level ໃຫ້ກັບ category ໃໝ່
        SET NEW.level = v_parent_level + 1;
    ELSE
        -- ຖ້າເປັນ level 1 (ບໍ່ມີ parent)
        SET NEW.level = 1;
    END IF;
    
    -- ກວດສອບວ່າ category_code ຊ້ຳບໍ
    IF EXISTS (SELECT 1 FROM asset_categories WHERE category_code = NEW.category_code) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Category code already exists';
    END IF;
    
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `update_category_path` AFTER INSERT ON `asset_categories` FOR EACH ROW BEGIN
    DECLARE v_parent_path VARCHAR(255);
    
    -- ຖ້າມີ parent
    IF NEW.parent_id IS NOT NULL THEN
        -- ດຶງ path ຂອງ parent
        SELECT path INTO v_parent_path
        FROM asset_categories
        WHERE id = NEW.parent_id;
        
        -- ອັບເດດ path ໃຫ້ກັບ category ໃໝ່
        UPDATE asset_categories 
        SET path = CONCAT(v_parent_path, '/', NEW.id)
        WHERE id = NEW.id;
    ELSE
        -- ຖ້າເປັນ level 1, ໃຊ້ id ຂອງຕົນເອງ
        UPDATE asset_categories 
        SET path = NEW.id
        WHERE id = NEW.id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `update_category_path_on_update` BEFORE UPDATE ON `asset_categories` FOR EACH ROW BEGIN
    DECLARE v_new_parent_level INT;
    DECLARE v_current_level INT;
    DECLARE v_child_count INT;
    
    -- ກວດສອບວ່າມີ category ຍ່ອຍຢູ່ລຸ່ມນີ້ບໍ
    SELECT COUNT(*) INTO v_child_count
    FROM asset_categories
    WHERE parent_id = OLD.id;
    
    -- ຖ້າມີການປ່ຽນແປງ parent_id
    IF NEW.parent_id != OLD.parent_id THEN
        
        -- ກວດສອບວ່າມີ category ຍ່ອຍ ບໍ່ສາມາດປ່ຽນ parent ໄດ້
        IF v_child_count > 0 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Cannot change parent: Category has subcategories';
        END IF;
        
        -- ດຶງ level ຂອງ parent ໃໝ່
        SELECT level INTO v_new_parent_level
        FROM asset_categories
        WHERE id = NEW.parent_id;
        
        -- ຄຳນວນ level ໃໝ່
        SET v_current_level = v_new_parent_level + 1;
        
        -- ກວດສອບວ່າເກີນ 3 ຫຼືບໍ່
        IF v_current_level > 3 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Cannot update: Would exceed maximum level (3)';
        END IF;
        
        -- ອັບເດດ level
        SET NEW.level = v_current_level;
    END IF;
    
    -- ກວດສອບວ່າ category_code ຊ້ຳບໍ (ຖ້າມີການປ່ຽນແປງ)
    IF NEW.category_code != OLD.category_code THEN
        IF EXISTS (SELECT 1 FROM asset_categories WHERE category_code = NEW.category_code AND id != NEW.id) THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Category code already exists';
        END IF;
    END IF;
    
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `prevent_category_deletion` BEFORE DELETE ON `asset_categories` FOR EACH ROW BEGIN
    DECLARE v_child_count INT;
    DECLARE v_asset_count INT;
    
    -- ກວດສອບວ່າມີ category ຍ່ອຍບໍ
    SELECT COUNT(*) INTO v_child_count
    FROM asset_categories
    WHERE parent_id = OLD.id;
    
    IF v_child_count > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete category: It has subcategories';
    END IF;
    
    -- ກວດສອບວ່າມີຊັບສິນທີ່ໃຊ້ category ນີ້ບໍ
    SELECT COUNT(*) INTO v_asset_count
    FROM assets
    WHERE category_id = OLD.id 
       OR category_level1_id = OLD.id
       OR category_level2_id = OLD.id
       OR category_level3_id = OLD.id;
    
    IF v_asset_count > 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot delete category: It is used by assets';
    END IF;
    
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `asset_documents`
--

DROP TABLE IF EXISTS `asset_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `document_type` enum('invoice','warranty','manual','certificate','insurance','other') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int DEFAULT NULL COMMENT 'ຂະໜາດໄຟລ໌ (ໄບຕ໌)',
  `mime_type` varchar(100) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_confidential` tinyint(1) DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `asset_documents_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `asset_images`
--

DROP TABLE IF EXISTS `asset_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asset_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_id` int NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_type` enum('main','additional','damage','maintenance') DEFAULT 'additional',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `asset_id` (`asset_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `asset_images_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asset_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `assets`
--

DROP TABLE IF EXISTS `assets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assets` (
  `id` int NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(100) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `asset_name_en` varchar(200) DEFAULT NULL,
  `old_asset_code` varchar(100) DEFAULT NULL COMMENT 'ລະຫັດຊັບສິນເກົ່າ (ກ່ອນໃຊ້ລະບົບ)',
  `category_level1_id` int NOT NULL COMMENT 'ປະເພດລະດັບ 1',
  `category_level2_id` int DEFAULT NULL COMMENT 'ປະເພດລະດັບ 2',
  `category_level3_id` int DEFAULT NULL COMMENT 'ປະເພດລະດັບ 3',
  `category_id` int NOT NULL COMMENT 'ປະເພດຍ່ອຍສຸດທ້າຍທີ່ເລືອກ',
  `description` text,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `manufacturing_year` int DEFAULT NULL,
  `country_of_origin` varchar(100) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `size_dimensions` varchar(100) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL COMMENT 'ນ້ຳໜັກ (ກິໂລກຣາມ)',
  `purchase_date` date NOT NULL,
  `purchase_cost` decimal(15,2) NOT NULL,
  `purchase_cost_usd` decimal(15,2) DEFAULT NULL COMMENT 'ມູນຄ່າຊື້ເປັນໂດລາ',
  `exchange_rate` decimal(10,4) DEFAULT NULL COMMENT 'ອັດຕາແລກປ່ຽນ',
  `supplier_id` int DEFAULT NULL,
  `purchase_invoice_no` varchar(100) DEFAULT NULL,
  `purchase_order_no` varchar(100) DEFAULT NULL,
  `payment_status` enum('paid','partial','unpaid') DEFAULT 'paid',
  `warranty_provider` varchar(200) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `warranty_terms` text,
  `insurance_policy_no` varchar(100) DEFAULT NULL,
  `insurance_expiry` date DEFAULT NULL,
  `insurance_provider` varchar(200) DEFAULT NULL,
  `company_id` int NOT NULL,
  `department_id` int NOT NULL,
  `current_user_id` int DEFAULT NULL,
  `location_id` int DEFAULT NULL COMMENT 'ສະຖານທີ່ເກັບຮັກສາ (ຕາຕະລາງສະຖານທີ່)',
  `building` varchar(100) DEFAULT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room` varchar(100) DEFAULT NULL,
  `exact_location` text COMMENT 'ສະຖານທີ່ຕັ້ງທີ່ແນ່ນອນ',
  `gps_coordinates` varchar(100) DEFAULT NULL,
  `status` enum('in_use','available','maintenance','reserved','disposed','sold','lost','damaged','stored') DEFAULT 'available',
  `asset_condition` enum('new','excellent','good','fair','poor','damaged','obsolete') DEFAULT 'good',
  `condition_notes` text,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `maintenance_frequency_days` int DEFAULT NULL,
  `current_value` decimal(15,2) DEFAULT NULL,
  `salvage_value` decimal(15,2) DEFAULT '0.00' COMMENT 'ມູນຄ່າຊາກ',
  `accumulated_depreciation` decimal(15,2) DEFAULT '0.00',
  `depreciation_start_date` date DEFAULT NULL,
  `depreciation_end_date` date DEFAULT NULL,
  `last_depreciation_date` date DEFAULT NULL,
  `depreciation_method` enum('straight_line','declining_balance','sum_of_years','units_of_production','none') DEFAULT 'straight_line',
  `useful_life_years` int DEFAULT NULL,
  `useful_life_months` int DEFAULT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL,
  `has_warranty` tinyint(1) DEFAULT '0',
  `warranty_document_path` varchar(500) DEFAULT NULL,
  `has_manual` tinyint(1) DEFAULT '0',
  `manual_document_path` varchar(500) DEFAULT NULL,
  `has_invoice` tinyint(1) DEFAULT '0',
  `invoice_document_path` varchar(500) DEFAULT NULL,
  `has_certificate` tinyint(1) DEFAULT '0',
  `certificate_document_path` varchar(500) DEFAULT NULL,
  `asset_image_path` varchar(500) DEFAULT NULL,
  `additional_documents` text COMMENT 'JSON ເກັບຂໍ້ມູນເອກະສານເພີ່ມເຕີມ',
  `qr_code` varchar(255) DEFAULT NULL,
  `qr_code_image_path` varchar(500) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `barcode_image_path` varchar(500) DEFAULT NULL,
  `rfid_tag` varchar(100) DEFAULT NULL,
  `asset_label_printed` tinyint(1) DEFAULT '0',
  `last_printed_date` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `verified_by` int DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verification_notes` text,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text,
  `custom_fields` json DEFAULT NULL COMMENT 'ເກັບຂໍ້ມູນສະເພາະທີ່ບໍ່ມີໃນຕາຕະລາງ',
  PRIMARY KEY (`id`),
  UNIQUE KEY `asset_code` (`asset_code`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `verified_by` (`verified_by`),
  KEY `idx_asset_code` (`asset_code`),
  KEY `idx_asset_name` (`asset_name`),
  KEY `idx_serial_number` (`serial_number`),
  KEY `idx_category` (`category_id`),
  KEY `idx_category_level1` (`category_level1_id`),
  KEY `idx_category_level2` (`category_level2_id`),
  KEY `idx_category_level3` (`category_level3_id`),
  KEY `idx_department` (`department_id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_current_user` (`current_user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_condition` (`asset_condition`),
  KEY `idx_purchase_date` (`purchase_date`),
  KEY `idx_warranty_expiry` (`warranty_expiry`),
  KEY `idx_next_maintenance` (`next_maintenance_date`),
  KEY `idx_is_active` (`is_active`),
  FULLTEXT KEY `idx_asset_search` (`asset_name`,`description`,`notes`,`asset_name_en`),
  CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_level1_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_10` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `assets_ibfk_11` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`category_level2_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_3` FOREIGN KEY (`category_level3_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `assets_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `assets_ibfk_6` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `assets_ibfk_7` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `assets_ibfk_8` FOREIGN KEY (`current_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `assets_ibfk_9` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `set_initial_asset_value` BEFORE INSERT ON `assets` FOR EACH ROW BEGIN
    -- ຕັ້ງຄ່າເລີ່ມຕົ້ນສຳລັບ current_value
    IF NEW.current_value IS NULL THEN
        SET NEW.current_value = NEW.purchase_cost;
    END IF;
    
    -- ຕັ້ງຄ່າເລີ່ມຕົ້ນສຳລັບ accumulated_depreciation
    IF NEW.accumulated_depreciation IS NULL THEN
        SET NEW.accumulated_depreciation = 0;
    END IF;
    
    -- ຕັ້ງວັນທີເລີ່ມຕັດຊໍາລຸດ
    IF NEW.depreciation_start_date IS NULL THEN
        SET NEW.depreciation_start_date = NEW.purchase_date;
    END IF;
    
    -- ຄຳນວນວັນທີສິ້ນສຸດການຕັດຊໍາລຸດ
    IF NEW.depreciation_end_date IS NULL AND NEW.useful_life_years IS NOT NULL THEN
        SET NEW.depreciation_end_date = DATE_ADD(NEW.purchase_date, INTERVAL NEW.useful_life_years YEAR);
    END IF;
    
    -- ຕັ້ງສະຖານະເລີ່ມຕົ້ນ
    IF NEW.status IS NULL THEN
        SET NEW.status = 'available';
    END IF;
    
    -- ຕັ້ງສະພາບເລີ່ມຕົ້ນ
    IF NEW.asset_condition IS NULL THEN
        SET NEW.asset_condition = 'good';
    END IF;
    
    -- ຕັ້ງຄ່າ is_active
    IF NEW.is_active IS NULL THEN
        SET NEW.is_active = true;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `update_asset_current_value` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    -- ຄຳນວນມູນຄ່າປັດຈຸບັນ ເມື່ອມີການປ່ຽນແປງ purchase_cost ຫຼື accumulated_depreciation
    IF (NEW.purchase_cost != OLD.purchase_cost) OR (NEW.accumulated_depreciation != OLD.accumulated_depreciation) THEN
        SET NEW.current_value = NEW.purchase_cost - NEW.accumulated_depreciation;
    END IF;
    
    -- ຈັດການກັບກໍລະນີທີ່ status ປ່ຽນເປັນ disposed ຫຼື sold
    IF (NEW.status IN ('disposed', 'sold')) AND (OLD.status NOT IN ('disposed', 'sold')) THEN
        SET NEW.current_value = 0;
        SET NEW.accumulated_depreciation = NEW.purchase_cost;
    END IF;
    
    -- ກໍລະນີ status ກັບມາໃຊ້ງານໃໝ່
    IF (OLD.status IN ('disposed', 'sold')) AND (NEW.status NOT IN ('disposed', 'sold')) THEN
        SET NEW.current_value = NEW.purchase_cost - NEW.accumulated_depreciation;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `update_maintenance_dates` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    -- ຖ້າມີການບັນທຶກວັນທີບຳລຸງຮັກສາຄັ້ງລ້າສຸດ
    IF NEW.last_maintenance_date != OLD.last_maintenance_date THEN
        -- ຄຳນວນວັນທີບຳລຸງຮັກສາຄັ້ງຕໍ່ໄປ
        IF NEW.maintenance_frequency_days IS NOT NULL AND NEW.maintenance_frequency_days > 0 THEN
            SET NEW.next_maintenance_date = DATE_ADD(NEW.last_maintenance_date, 
                INTERVAL NEW.maintenance_frequency_days DAY);
        END IF;
    END IF;
    
    -- ຖ້າວັນທີບຳລຸງຮັກສາຄັ້ງຕໍ່ໄປຜ່ານໄປແລ້ວ ແລະ status ຍັງບໍ່ປ່ຽນ
    IF NEW.next_maintenance_date IS NOT NULL AND NEW.next_maintenance_date < CURDATE() 
       AND NEW.status NOT IN ('maintenance', 'disposed', 'sold') THEN
        -- ສາມາດເພີ່ມການແຈ້ງເຕືອນຜ່ານຕາຕະລາງ notifications
        -- ແຕ່ Trigger ບໍ່ສາມາດ INSERT ໃສ່ຕາຕະລາງອື່ນໄດ້ໂດຍກົງ
        -- ດັ່ງນັ້ນພຽງແຕ່ບັນທຶກໃນ remarks ຫຼື notes
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), ' [ແຈ້ງເຕືອນ: ຮອດກຳນົດບຳລຸງຮັກສາວັນທີ ', 
                               DATE_FORMAT(NEW.next_maintenance_date, '%d/%m/%Y'), ']');
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `log_asset_changes` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    -- ບັນທຶກການປ່ຽນແປງສະຖານທີ່
    IF NEW.department_id != OLD.department_id OR NEW.current_user_id != OLD.current_user_id THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ຍ້າຍ: ', DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), ']');
    END IF;
    
    -- ບັນທຶກການປ່ຽນແປງມູນຄ່າທີ່ສຳຄັນ (>10%)
    IF NEW.purchase_cost != OLD.purchase_cost THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ປ່ຽນມູນຄ່າ: ຈາກ ', OLD.purchase_cost, ' ເປັນ ', NEW.purchase_cost, 
            ' (', DATE_FORMAT(NOW(), '%d/%m/%Y'), ')]');
    END IF;
    
    -- ບັນທຶກການຕັດຊໍາລຸດ
    IF NEW.status = 'disposed' AND OLD.status != 'disposed' THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ຕັດຊໍາລຸດ: ', DATE_FORMAT(NOW(), '%d/%m/%Y'), ']');
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `auto_update_asset_status` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    -- ຖ້າຊັບສິນເສຍຫາຍ ໃຫ້ປ່ຽນສະຖານະເປັນ damaged ໂດຍອັດຕະໂນມັດ
    IF NEW.asset_condition IN ('damaged', 'poor') AND NEW.status = 'in_use' THEN
        SET NEW.status = 'damaged';
    END IF;
    
    -- ຖ້າຊັບສິນຖືກສ້ອມແປງຈົນກັບມາໃຊ້ງານໄດ້
    IF NEW.asset_condition IN ('good', 'excellent') AND OLD.asset_condition IN ('damaged', 'poor') 
       AND NEW.status = 'damaged' THEN
        SET NEW.status = 'available';
    END IF;
    
    -- ຖ້າຊັບສິນມີອາຍຸການໃຊ້ງານເກີນກຳນົດ
    IF NEW.useful_life_years IS NOT NULL AND NEW.purchase_date IS NOT NULL THEN
        IF DATE_ADD(NEW.purchase_date, INTERVAL NEW.useful_life_years YEAR) < CURDATE() 
           AND NEW.status NOT IN ('disposed', 'sold') THEN
            SET NEW.asset_condition = 'obsolete';
            SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
                ' [ໝົດອາຍຸການໃຊ້ງານ: ', DATE_FORMAT(NOW(), '%d/%m/%Y'), ']');
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`%`*/ /*!50003 TRIGGER `update_warranty_status` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    -- ອັບເດດສະຖານະການຮັບປະກັນໂດຍອັດຕະໂນມັດ
    IF NEW.warranty_expiry IS NOT NULL THEN
        IF NEW.warranty_expiry < CURDATE() THEN
            SET NEW.has_warranty = false;
        ELSE
            SET NEW.has_warranty = true;
        END IF;
    END IF;
    
    -- ແຈ້ງເຕືອນກ່ອນປະກັນໝົດອາຍຸ 30 ວັນ
    IF NEW.warranty_expiry IS NOT NULL AND OLD.warranty_expiry IS NOT NULL THEN
        IF NEW.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
           AND OLD.warranty_expiry NOT BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN
            SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
                ' [ແຈ້ງເຕືອນ: ປະກັນຈະໝົດອາຍຸວັນທີ ', 
                DATE_FORMAT(NEW.warranty_expiry, '%d/%m/%Y'), ']');
        END IF;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `barcode_generator`
--

DROP TABLE IF EXISTS `barcode_generator`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barcode_generator` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barcode` varchar(100) NOT NULL,
  `barcode_type` enum('CODE128','EAN13','EAN8','UPC','QR','OTHER') NOT NULL,
  `reference_type` enum('item','stock','asset') NOT NULL,
  `reference_id` int NOT NULL,
  `generated_for` varchar(200) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `generated_by` int NOT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `print_count` int DEFAULT '0',
  `last_printed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `generated_by` (`generated_by`),
  CONSTRAINT `barcode_generator_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `barcode_scans`
--

DROP TABLE IF EXISTS `barcode_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `barcode_scans` (
  `id` int NOT NULL AUTO_INCREMENT,
  `barcode` varchar(100) NOT NULL,
  `scan_type` enum('incoming','outgoing','inventory_count','transfer') NOT NULL,
  `reference_type` enum('purchase_order','sales_order','stock_count') NOT NULL,
  `reference_id` int NOT NULL,
  `stock_id` int DEFAULT NULL,
  `item_id` int NOT NULL,
  `quantity` int DEFAULT '1',
  `scan_location` varchar(200) DEFAULT NULL,
  `scanned_by` int NOT NULL,
  `scan_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_valid` tinyint(1) DEFAULT '1',
  `error_message` text,
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `stock_id` (`stock_id`),
  KEY `item_id` (`item_id`),
  KEY `scanned_by` (`scanned_by`),
  CONSTRAINT `barcode_scans_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `inventory_stock` (`id`),
  CONSTRAINT `barcode_scans_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `barcode_scans_ibfk_3` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_attributes`
--

DROP TABLE IF EXISTS `category_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_attributes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_type` enum('text','number','date','boolean','select') NOT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `options` text COMMENT 'ສຳລັບ type select',
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `category_attributes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `category_inheritance`
--

DROP TABLE IF EXISTS `category_inheritance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `category_inheritance` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category_id` int NOT NULL,
  `inherited_from_id` int NOT NULL,
  `attribute_name` varchar(100) DEFAULT NULL,
  `is_overridden` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `inherited_from_id` (`inherited_from_id`),
  CONSTRAINT `category_inheritance_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `category_inheritance_ibfk_2` FOREIGN KEY (`inherited_from_id`) REFERENCES `asset_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `company_code` varchar(50) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `parent_company_id` int DEFAULT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`),
  KEY `parent_company_id` (`parent_company_id`),
  CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`parent_company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `departments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `department_code` varchar(50) NOT NULL,
  `department_name` varchar(200) NOT NULL,
  `company_id` int NOT NULL,
  `parent_department_id` int DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `department_code` (`department_code`),
  KEY `company_id` (`company_id`),
  KEY `parent_department_id` (`parent_department_id`),
  CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`parent_department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_code` varchar(50) NOT NULL COMMENT 'ລະຫັດສິນຄ້າ',
  `barcode` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'ລະຫັບ Barcode ຫຼື UPC ຂອງສິນຄ້າ',
  `item_name` varchar(200) NOT NULL COMMENT 'ຊື່ສິນຄ້າ',
  `item_name_en` varchar(200) DEFAULT NULL,
  `category_id` int NOT NULL COMMENT 'ປະເພດສິນຄ້າ',
  `description` text,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `specification` text COMMENT 'ຂໍ້ມູນສະເພາະ',
  `purchase_price` decimal(15,2) DEFAULT NULL COMMENT 'ລາຄາຊື້',
  `selling_price` decimal(15,2) DEFAULT NULL COMMENT 'ລາຄາຂາຍ',
  `supplier_id` int DEFAULT NULL,
  `reorder_point` int DEFAULT '0' COMMENT 'ຈຸດທີ່ຕ້ອງສັ່ງຊື້ເພີ່ມ',
  `minimum_stock` int DEFAULT '0' COMMENT 'ສະຕ໋ອກຕ່ຳສຸດ',
  `maximum_stock` int DEFAULT '0' COMMENT 'ສະຕ໋ອກສູງສຸດ',
  `barcode_type` enum('CODE128','EAN13','EAN8','UPC','QR','OTHER') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'CODE128',
  `barcode_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'ທີ່ຢູ່ຮູບພາບ Barcode',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  UNIQUE KEY `barcode` (`barcode`),
  KEY `category_id` (`category_id`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `inventory_stock`
--

DROP TABLE IF EXISTS `inventory_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `inventory_stock` (
  `id` int NOT NULL AUTO_INCREMENT,
  `item_id` int NOT NULL,
  `unique_barcode` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Barcode ສະເພາະຂອງແຕ່ລະຊິ້ນ',
  `barcode_image_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'ທີ່ຢູ່ຮູບພາບ Barcode ຂອງຊິ້ນນີ້',
  `rfid_tag` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'RFID Tag ຖ້າມີ',
  `qr_code` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'QR Code ສຳລັບລິ້ງຂໍ້ມູນ',
  `location_id` int DEFAULT NULL COMMENT 'ສະຖານທີ່ເກັບສິນຄ້າ',
  `batch_number` varchar(100) DEFAULT NULL COMMENT 'ເລກທີລອດ',
  `serial_number` varchar(100) DEFAULT NULL COMMENT 'ເລກຊີຣຽວ (ສຳລັບສິນຄ້າທີ່ມີ)',
  `quantity` int NOT NULL DEFAULT '0' COMMENT 'ຈຳນວນ',
  `unit` varchar(20) DEFAULT 'ຊິ້ນ',
  `purchase_price` decimal(15,2) DEFAULT NULL COMMENT 'ລາຄາຊື້ຕໍ່ຫົວໜ່ວຍ',
  `selling_price` decimal(15,2) DEFAULT NULL COMMENT 'ລາຄາຂາຍຕໍ່ຫົວໜ່ວຍ',
  `purchase_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL COMMENT 'ວັນໝົດອາຍຸ (ຖ້າມີ)',
  `warranty_period` int DEFAULT NULL COMMENT 'ອາຍຸການຮັບປະກັນ (ເດືອນ)',
  `status` enum('in_stock','reserved','sold','damaged','transferred') DEFAULT 'in_stock',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_barcode` (`unique_barcode`),
  KEY `item_id` (`item_id`),
  KEY `location_id` (`location_id`),
  CONSTRAINT `inventory_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_stock_ibfk_2` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `locations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `location_code` varchar(50) NOT NULL,
  `location_name` varchar(200) NOT NULL,
  `location_type` enum('building','floor','room','warehouse','office') NOT NULL,
  `parent_location_id` int DEFAULT NULL,
  `company_id` int NOT NULL,
  `address` text,
  `capacity` int DEFAULT NULL COMMENT 'ຄວາມຈຸສູງສຸດ',
  `current_usage` int DEFAULT '0',
  `manager_id` int DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `location_code` (`location_code`),
  KEY `parent_location_id` (`parent_location_id`),
  KEY `company_id` (`company_id`),
  KEY `manager_id` (`manager_id`),
  CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`parent_location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `locations_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `locations_ibfk_3` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_order_details`
--

DROP TABLE IF EXISTS `purchase_order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_order_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `received_quantity` int DEFAULT '0',
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL,
  `warranty_period` int DEFAULT NULL COMMENT 'ອາຍຸການຮັບປະກັນ (ເດືອນ)',
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `purchase_order_details_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`),
  CONSTRAINT `purchase_order_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `purchase_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL COMMENT 'ເລກທີ PO',
  `supplier_id` int NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `tax` decimal(15,2) DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('draft','ordered','received','cancelled') DEFAULT 'draft',
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_file_path` varchar(500) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_order_details`
--

DROP TABLE IF EXISTS `sales_order_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_order_details` (
  `id` int NOT NULL AUTO_INCREMENT,
  `so_id` int NOT NULL,
  `stock_id` int NOT NULL COMMENT 'ອ້າງອີງໃສ່ inventory_stock',
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL,
  `is_converted_to_asset` tinyint(1) DEFAULT '0' COMMENT 'ປ່ຽນເປັນຊັບສິນແລ້ວບໍ?',
  `asset_id` int DEFAULT NULL COMMENT 'ຖ້າປ່ຽນເປັນຊັບສິນ, ອ້າງອີງໃສ່ assets.id',
  `notes` text,
  PRIMARY KEY (`id`),
  KEY `so_id` (`so_id`),
  KEY `stock_id` (`stock_id`),
  KEY `item_id` (`item_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `sales_order_details_ibfk_1` FOREIGN KEY (`so_id`) REFERENCES `sales_orders` (`id`),
  CONSTRAINT `sales_order_details_ibfk_2` FOREIGN KEY (`stock_id`) REFERENCES `inventory_stock` (`id`),
  CONSTRAINT `sales_order_details_ibfk_3` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `sales_order_details_ibfk_4` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_orders`
--

DROP TABLE IF EXISTS `sales_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sales_orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `so_number` varchar(50) NOT NULL COMMENT 'ເລກທີ SO',
  `customer_name` varchar(200) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_address` text,
  `order_date` date NOT NULL,
  `delivery_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `tax` decimal(15,2) DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `sale_type` enum('retail','wholesale','company') DEFAULT 'retail',
  `company_id` int DEFAULT NULL COMMENT 'ຖ້າຂາຍໃຫ້ບໍລິສັດ',
  `department_id` int DEFAULT NULL COMMENT 'ຖ້າຂາຍໃຫ້ພະແນກ',
  `status` enum('draft','confirmed','delivered','cancelled') DEFAULT 'draft',
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_file_path` varchar(500) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `so_number` (`so_number`),
  KEY `company_id` (`company_id`),
  KEY `department_id` (`department_id`),
  KEY `created_by` (`created_by`),
  KEY `approved_by` (`approved_by`),
  CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `sales_orders_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `sales_orders_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `stock_id` int NOT NULL,
  `movement_type` enum('purchase_in','sale_out','transfer','adjustment','damage','return') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'po, so, adjustment',
  `reference_id` int DEFAULT NULL,
  `quantity_before` int NOT NULL,
  `quantity_change` int NOT NULL,
  `quantity_after` int NOT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT NULL,
  `movement_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `stock_id` (`stock_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `stock_movements_ibfk_1` FOREIGN KEY (`stock_id`) REFERENCES `inventory_stock` (`id`),
  CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `suppliers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(50) NOT NULL,
  `supplier_name` varchar(200) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text,
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` text,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_code` (`supplier_code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('employee','department_head','asset_admin','super_admin') NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `employee_code` (`employee_code`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `vw_barcode_inventory`
--

DROP TABLE IF EXISTS `vw_barcode_inventory`;
/*!50001 DROP VIEW IF EXISTS `vw_barcode_inventory`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_barcode_inventory` AS SELECT 
 1 AS `type`,
 1 AS `reference_id`,
 1 AS `code`,
 1 AS `name`,
 1 AS `barcode`,
 1 AS `barcode_type`,
 1 AS `batch_number`,
 1 AS `serial_number`,
 1 AS `stock_quantity`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `vw_inventory_summary`
--

DROP TABLE IF EXISTS `vw_inventory_summary`;
/*!50001 DROP VIEW IF EXISTS `vw_inventory_summary`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `vw_inventory_summary` AS SELECT 
 1 AS `id`,
 1 AS `item_code`,
 1 AS `item_name`,
 1 AS `brand`,
 1 AS `model`,
 1 AS `category_name`,
 1 AS `supplier_name`,
 1 AS `total_stock`,
 1 AS `total_quantity`,
 1 AS `avg_purchase_price`,
 1 AS `avg_selling_price`,
 1 AS `total_inventory_value`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'asset_db'
--

--
-- Dumping routines for database 'asset_db'
--
/*!50003 DROP FUNCTION IF EXISTS `find_item_by_barcode` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` FUNCTION `find_item_by_barcode`(p_barcode VARCHAR(100)) RETURNS json
    DETERMINISTIC
BEGIN
    DECLARE v_result JSON;
    
    -- ຄົ້ນຫາໃນ inventory_items
    IF EXISTS (SELECT 1 FROM inventory_items WHERE barcode = p_barcode) THEN
        SELECT JSON_OBJECT(
            'type', 'item',
            'id', id,
            'code', item_code,
            'name', item_name,
            'barcode', barcode
        ) INTO v_result
        FROM inventory_items
        WHERE barcode = p_barcode;
        
    -- ຄົ້ນຫາໃນ inventory_stock
    ELSEIF EXISTS (SELECT 1 FROM inventory_stock WHERE unique_barcode = p_barcode) THEN
        SELECT JSON_OBJECT(
            'type', 'stock',
            'id', is2.id,
            'item_id', is2.item_id,
            'item_name', ii.item_name,
            'batch', is2.batch_number,
            'serial', is2.serial_number,
            'barcode', is2.unique_barcode,
            'quantity', is2.quantity
        ) INTO v_result
        FROM inventory_stock is2
        JOIN inventory_items ii ON is2.item_id = ii.id
        WHERE is2.unique_barcode = p_barcode;
        
    -- ຄົ້ນຫາໃນ barcode_generator
    ELSEIF EXISTS (SELECT 1 FROM barcode_generator WHERE barcode = p_barcode) THEN
        SELECT JSON_OBJECT(
            'type', 'generated',
            'barcode', barcode,
            'reference_type', reference_type,
            'reference_id', reference_id
        ) INTO v_result
        FROM barcode_generator
        WHERE barcode = p_barcode;
        
    ELSE
        SET v_result = JSON_OBJECT('type', 'not_found', 'barcode', p_barcode);
    END IF;
    
    RETURN v_result;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `check_inventory_stock` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `check_inventory_stock`(
    IN p_item_id INT
)
BEGIN
    IF p_item_id IS NULL OR p_item_id = 0 THEN
        -- ເບິ່ງທັງໝົດ
        SELECT 
            ii.id AS item_id,
            ii.item_code,
            ii.item_name,
            ii.brand,
            ii.model,
            COUNT(is2.id) AS batch_count,
            SUM(is2.quantity) AS total_quantity,
            AVG(is2.purchase_price) AS avg_purchase_price,
            AVG(is2.selling_price) AS avg_selling_price,
            SUM(is2.quantity * is2.purchase_price) AS total_value
        FROM inventory_items ii
        LEFT JOIN inventory_stock is2 ON ii.id = is2.item_id AND is2.status = 'in_stock'
        GROUP BY ii.id
        HAVING total_quantity > 0 OR total_quantity IS NOT NULL;
    ELSE
        -- ເບິ່ງສະເພາະລາຍການ
        SELECT 
            is2.id AS stock_id,
            is2.batch_number,
            is2.serial_number,
            is2.quantity,
            is2.purchase_price,
            is2.selling_price,
            is2.purchase_date,
            is2.warranty_period,
            DATE_ADD(is2.purchase_date, INTERVAL is2.warranty_period MONTH) AS warranty_expiry
        FROM inventory_stock is2
        WHERE is2.item_id = p_item_id AND is2.status = 'in_stock'
        ORDER BY is2.purchase_date;
    END IF;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `convert_sold_item_to_asset` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `convert_sold_item_to_asset`(
    IN p_sales_order_detail_id INT,
    IN p_asset_code VARCHAR(100),
    IN p_asset_name VARCHAR(200),
    IN p_department_id INT,
    IN p_created_by INT
)
BEGIN
    DECLARE v_stock_id INT;
    DECLARE v_item_id INT;
    DECLARE v_serial_number VARCHAR(100);
    DECLARE v_purchase_price DECIMAL(15,2);
    DECLARE v_purchase_date DATE;
    DECLARE v_warranty_period INT;
    DECLARE v_supplier_id INT;
    DECLARE v_category_id INT;
    DECLARE v_company_id INT;
    DECLARE v_new_asset_id INT;
    
    -- ດຶງຂໍ້ມູນຈາກ sales_order_details ແລະ inventory_stock
    SELECT 
        sod.stock_id,
        sod.item_id,
        is2.serial_number,
        is2.purchase_price,
        is2.purchase_date,
        is2.warranty_period,
        ii.supplier_id,
        ii.category_id
    INTO 
        v_stock_id,
        v_item_id,
        v_serial_number,
        v_purchase_price,
        v_purchase_date,
        v_warranty_period,
        v_supplier_id,
        v_category_id
    FROM sales_order_details sod
    JOIN inventory_stock is2 ON sod.stock_id = is2.id
    JOIN inventory_items ii ON sod.item_id = ii.id
    WHERE sod.id = p_sales_order_detail_id;
    
    -- ໄດ້ຮັບ company_id ຈາກ department
    SELECT company_id INTO v_company_id
    FROM departments
    WHERE id = p_department_id;
    
    -- ສ້າງຊັບສິນໃໝ່
    INSERT INTO assets (
        asset_code,
        asset_name,
        category_id,
        category_level1_id,
        serial_number,
        purchase_date,
        purchase_cost,
        supplier_id,
        company_id,
        department_id,
        current_value,
        status,
        created_by,
        warranty_expiry,
        notes
    ) VALUES (
        p_asset_code,
        p_asset_name,
        v_category_id,
        v_category_id,
        IFNULL(v_serial_number, CONCAT('SN-', FLOOR(RAND() * 1000000))),
        IFNULL(v_purchase_date, CURDATE()),
        v_purchase_price,
        v_supplier_id,
        v_company_id,
        p_department_id,
        v_purchase_price,
        'in_use',
        p_created_by,
        DATE_ADD(IFNULL(v_purchase_date, CURDATE()), INTERVAL IFNULL(v_warranty_period, 12) MONTH),
        'ປ່ຽນມາຈາກສິນຄ້າຂາຍ'
    );
    
    -- ໄດ້ຮັບ ID ຊັບສິນໃໝ່
    SET v_new_asset_id = LAST_INSERT_ID();
    
    -- ອັບເດດ sales_order_details ວ່າປ່ຽນເປັນຊັບສິນແລ້ວ
    UPDATE sales_order_details 
    SET is_converted_to_asset = true,
        asset_id = v_new_asset_id
    WHERE id = p_sales_order_detail_id;
    
    -- ອັບເດດ inventory_stock ວ່າຂາຍແລ້ວ
    UPDATE inventory_stock 
    SET status = 'sold'
    WHERE id = v_stock_id;
    
    -- ສົ່ງຄືນຂໍ້ມູນຊັບສິນໃໝ່
    SELECT 
        a.*,
        d.department_name,
        c.company_name,
        CONCAT(u.first_name, ' ', u.last_name) AS created_by_name
    FROM assets a
    LEFT JOIN departments d ON a.department_id = d.id
    LEFT JOIN companies c ON a.company_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    WHERE a.id = v_new_asset_id;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_category_with_path` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `create_category_with_path`(
    IN p_category_code VARCHAR(50),
    IN p_category_name VARCHAR(200),
    IN p_parent_id INT
)
BEGIN
    DECLARE v_level INT;
    DECLARE v_parent_path VARCHAR(255);
    DECLARE v_new_id INT;
    
    -- ກວດສອບລະດັບຂອງ parent
    IF p_parent_id IS NULL THEN
        SET v_level = 1;
        SET v_parent_path = '';
    ELSE
        SELECT level, path INTO v_level, v_parent_path
        FROM asset_categories 
        WHERE id = p_parent_id;
        
        SET v_level = v_level + 1;
    END IF;
    
    -- ສ້າງ category ໃໝ່
    INSERT INTO asset_categories (
        category_code, category_name, parent_id, level
    ) VALUES (
        p_category_code, p_category_name, p_parent_id, v_level
    );
    
    SET v_new_id = LAST_INSERT_ID();
    
    -- ອັບເດດ path
    IF p_parent_id IS NULL THEN
        UPDATE asset_categories 
        SET path = v_new_id
        WHERE id = v_new_id;
    ELSE
        UPDATE asset_categories 
        SET path = CONCAT(v_parent_path, '/', v_new_id)
        WHERE id = v_new_id;
    END IF;
    
    SELECT * FROM asset_categories WHERE id = v_new_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_new_asset` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `create_new_asset`(
    IN p_asset_code VARCHAR(100),
    IN p_asset_name VARCHAR(200),
    IN p_category_level3_id INT,
    IN p_purchase_date DATE,
    IN p_purchase_cost DECIMAL(15,2),
    IN p_supplier_id INT,
    IN p_company_id INT,
    IN p_department_id INT,
    IN p_current_user_id INT,
    IN p_created_by INT,
    IN p_serial_number VARCHAR(100),
    IN p_warranty_expiry DATE,
    IN p_useful_life_years INT
)
BEGIN
    DECLARE v_category_level1_id INT;
    DECLARE v_category_level2_id INT;
    DECLARE v_depreciation_method ENUM('straight_line', 'declining_balance', 'none');
    DECLARE v_path VARCHAR(255);
    
    -- ກວດສອບວ່າມີ category ນີ້ບໍ
    IF NOT EXISTS (SELECT 1 FROM asset_categories WHERE id = p_category_level3_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Category ID not found';
    END IF;
    
    -- ໄດ້ຮັບຂໍ້ມູນປະເພດລະດັບຊັ້ນ
    SELECT 
        COALESCE(SUBSTRING_INDEX(SUBSTRING_INDEX(path, '/', 1), '/', -1), 0),
        COALESCE(SUBSTRING_INDEX(SUBSTRING_INDEX(path, '/', 2), '/', -1), 0),
        COALESCE(depreciation_method, 'straight_line')
    INTO 
        v_category_level1_id,
        v_category_level2_id,
        v_depreciation_method
    FROM asset_categories
    WHERE id = p_category_level3_id;
    
    -- ຖ້າບໍ່ມີ path ໃຫ້ໃຊ້ category_id ເອງ
    IF v_category_level1_id = 0 THEN
        SET v_category_level1_id = p_category_level3_id;
        SET v_category_level2_id = NULL;
    END IF;
    
    -- ສ້າງຊັບສິນໃໝ່
    INSERT INTO assets (
        asset_code, 
        asset_name, 
        category_level1_id, 
        category_level2_id,
        category_level3_id, 
        category_id, 
        serial_number, 
        purchase_date,
        purchase_cost, 
        supplier_id, 
        company_id, 
        department_id,
        current_user_id, 
        warranty_expiry, 
        useful_life_years,
        depreciation_method, 
        current_value, 
        created_by,
        created_at,
        status,
        asset_condition
    ) VALUES (
        p_asset_code, 
        p_asset_name, 
        v_category_level1_id, 
        IF(v_category_level2_id = v_category_level1_id OR v_category_level2_id = 0, NULL, v_category_level2_id),
        p_category_level3_id, 
        p_category_level3_id, 
        p_serial_number,
        p_purchase_date, 
        p_purchase_cost, 
        p_supplier_id, 
        p_company_id,
        p_department_id, 
        p_current_user_id, 
        p_warranty_expiry,
        p_useful_life_years, 
        v_depreciation_method, 
        p_purchase_cost, 
        p_created_by,
        NOW(),
        'available',
        'good'
    );
    
    -- ສົ່ງຄືນຂໍ້ມູນທີ່ສ້າງໃໝ່ (ໃຊ້ JOIN ແທນ view ຖ້າ view ຍັງບໍ່ມີ)
    SELECT 
        a.*,
        d.department_name,
        c.company_name,
        CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
        cat.category_name AS category_name
    FROM assets a
    LEFT JOIN departments d ON a.department_id = d.id
    LEFT JOIN companies c ON a.company_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN asset_categories cat ON a.category_id = cat.id
    WHERE a.id = LAST_INSERT_ID();
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `create_new_asset_advanced` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `create_new_asset_advanced`(
    IN p_asset_code VARCHAR(100),
    IN p_asset_name VARCHAR(200),
    IN p_category_level3_id INT,
    IN p_purchase_date DATE,
    IN p_purchase_cost DECIMAL(15,2),
    IN p_supplier_id INT,
    IN p_company_id INT,
    IN p_department_id INT,
    IN p_current_user_id INT,
    IN p_created_by INT,
    IN p_serial_number VARCHAR(100),
    IN p_warranty_expiry DATE,
    IN p_useful_life_years INT
)
BEGIN
    DECLARE v_category_level1_id INT;
    DECLARE v_category_level2_id INT;
    DECLARE v_depreciation_method ENUM('straight_line', 'declining_balance', 'none');
    DECLARE v_path VARCHAR(255);
    DECLARE v_category_name VARCHAR(200);
    
    -- ກວດສອບຂໍ້ມູນທີ່ຈຳເປັນ
    IF p_asset_code IS NULL OR p_asset_code = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset code is required';
    END IF;
    
    IF p_asset_name IS NULL OR p_asset_name = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset name is required';
    END IF;
    
    -- ກວດສອບວ່າ asset_code ຊ້ຳບໍ
    IF EXISTS (SELECT 1 FROM assets WHERE asset_code = p_asset_code) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset code already exists';
    END IF;
    
    -- ກວດສອບ category
    IF NOT EXISTS (SELECT 1 FROM asset_categories WHERE id = p_category_level3_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category ID not found';
    END IF;
    
    -- ກວດສອບ company
    IF NOT EXISTS (SELECT 1 FROM companies WHERE id = p_company_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company ID not found';
    END IF;
    
    -- ກວດສອບ department
    IF NOT EXISTS (SELECT 1 FROM departments WHERE id = p_department_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Department ID not found';
    END IF;
    
    -- ກວດສອບ supplier (ຖ້າມີ)
    IF p_supplier_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM suppliers WHERE id = p_supplier_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Supplier ID not found';
    END IF;
    
    -- ກວດສອບ user (ຖ້າມີ)
    IF p_current_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id = p_current_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID not found';
    END IF;
    
    -- ໄດ້ຮັບຂໍ້ມູນປະເພດລະດັບຊັ້ນ
    SELECT 
        COALESCE(SUBSTRING_INDEX(SUBSTRING_INDEX(path, '/', 1), '/', -1), 0),
        COALESCE(SUBSTRING_INDEX(SUBSTRING_INDEX(path, '/', 2), '/', -1), 0),
        COALESCE(depreciation_method, 'straight_line'),
        category_name
    INTO 
        v_category_level1_id,
        v_category_level2_id,
        v_depreciation_method,
        v_category_name
    FROM asset_categories
    WHERE id = p_category_level3_id;
    
    -- ຖ້າບໍ່ມີ path ໃຫ້ໃຊ້ category_id ເອງ
    IF v_category_level1_id = 0 THEN
        SET v_category_level1_id = p_category_level3_id;
        SET v_category_level2_id = NULL;
    END IF;
    
    -- ສ້າງຊັບສິນໃໝ່
    INSERT INTO assets (
        asset_code, 
        asset_name, 
        category_level1_id, 
        category_level2_id,
        category_level3_id, 
        category_id, 
        serial_number, 
        purchase_date,
        purchase_cost, 
        supplier_id, 
        company_id, 
        department_id,
        current_user_id, 
        warranty_expiry, 
        useful_life_years,
        depreciation_method, 
        current_value, 
        created_by,
        created_at,
        status,
        asset_condition,
        depreciation_start_date,
        depreciation_end_date
    ) VALUES (
        p_asset_code, 
        p_asset_name, 
        v_category_level1_id, 
        IF(v_category_level2_id = v_category_level1_id OR v_category_level2_id = 0, NULL, v_category_level2_id),
        p_category_level3_id, 
        p_category_level3_id, 
        p_serial_number,
        p_purchase_date, 
        p_purchase_cost, 
        p_supplier_id, 
        p_company_id,
        p_department_id, 
        p_current_user_id, 
        p_warranty_expiry,
        p_useful_life_years, 
        v_depreciation_method, 
        p_purchase_cost, 
        p_created_by,
        NOW(),
        'available',
        'good',
        p_purchase_date,
        DATE_ADD(p_purchase_date, INTERVAL IFNULL(p_useful_life_years, 5) YEAR)
    );
    
    -- ສົ່ງຄືນຂໍ້ມູນທີ່ສ້າງໃໝ່
    SELECT 
        a.id,
        a.asset_code,
        a.asset_name,
        a.purchase_date,
        a.purchase_cost,
        a.current_value,
        a.status,
        a.serial_number,
        a.warranty_expiry,
        a.useful_life_years,
        a.depreciation_method,
        a.depreciation_start_date,
        a.depreciation_end_date,
        d.department_name,
        c.company_name,
        CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
        cat.category_name,
        cat1.category_name AS category_level1_name,
        cat2.category_name AS category_level2_name,
        cat3.category_name AS category_level3_name
    FROM assets a
    LEFT JOIN departments d ON a.department_id = d.id
    LEFT JOIN companies c ON a.company_id = c.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN asset_categories cat ON a.category_id = cat.id
    LEFT JOIN asset_categories cat1 ON a.category_level1_id = cat1.id
    LEFT JOIN asset_categories cat2 ON a.category_level2_id = cat2.id
    LEFT JOIN asset_categories cat3 ON a.category_level3_id = cat3.id
    WHERE a.id = LAST_INSERT_ID();
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `generate_item_barcode` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `generate_item_barcode`(
    IN p_item_id INT,
    IN p_barcode_type VARCHAR(20),
    IN p_generated_by INT
)
BEGIN
    DECLARE v_item_code VARCHAR(50);
    DECLARE v_item_name VARCHAR(200);
    DECLARE v_barcode VARCHAR(100);
    DECLARE v_exists INT;
    
    -- ໄດ້ຮັບຂໍ້ມູນສິນຄ້າ
    SELECT item_code, item_name INTO v_item_code, v_item_name
    FROM inventory_items
    WHERE id = p_item_id;
    
    -- ສ້າງ Barcode (ຕົວຢ່າງ: ຮູບແບບ ITM-{item_id}-{random})
    SET v_barcode = CONCAT('ITM-', p_item_id, '-', FLOOR(100000 + RAND() * 900000));
    
    -- ກວດສອບວ່າຊ້ຳບໍ
    SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    
    WHILE v_exists > 0 DO
        SET v_barcode = CONCAT('ITM-', p_item_id, '-', FLOOR(100000 + RAND() * 900000));
        SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    END WHILE;
    
    -- ບັນທຶກ Barcode
    INSERT INTO barcode_generator (
        barcode, barcode_type, reference_type, reference_id, 
        generated_for, generated_by
    ) VALUES (
        v_barcode, p_barcode_type, 'item', p_item_id,
        CONCAT(v_item_code, ' - ', v_item_name), p_generated_by
    );
    
    -- ອັບເດດ inventory_items
    UPDATE inventory_items 
    SET barcode = v_barcode,
        barcode_type = p_barcode_type
    WHERE id = p_item_id;
    
    -- ສົ່ງຄືນຂໍ້ມູນ
    SELECT v_barcode AS barcode, 'item' AS reference_type, p_item_id AS reference_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `generate_stock_barcode` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `generate_stock_barcode`(
    IN p_stock_id INT,
    IN p_barcode_type VARCHAR(20),
    IN p_generated_by INT
)
BEGIN
    DECLARE v_item_code VARCHAR(50);
    DECLARE v_batch_number VARCHAR(100);
    DECLARE v_serial_number VARCHAR(100);
    DECLARE v_barcode VARCHAR(100);
    DECLARE v_exists INT;
    
    -- ໄດ້ຮັບຂໍ້ມູນສະຕ໋ອກ
    SELECT 
        ii.item_code,
        is2.batch_number,
        is2.serial_number
    INTO 
        v_item_code,
        v_batch_number,
        v_serial_number
    FROM inventory_stock is2
    JOIN inventory_items ii ON is2.item_id = ii.id
    WHERE is2.id = p_stock_id;
    
    -- ສ້າງ Barcode (ໃຊ້ Serial Number ຖ້າມີ)
    IF v_serial_number IS NOT NULL AND v_serial_number != '' THEN
        SET v_barcode = CONCAT('SN-', v_serial_number);
    ELSE
        SET v_barcode = CONCAT('STK-', p_stock_id, '-', FLOOR(1000 + RAND() * 9000));
    END IF;
    
    -- ກວດສອບວ່າຊ້ຳບໍ
    SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    
    WHILE v_exists > 0 DO
        SET v_barcode = CONCAT('STK-', p_stock_id, '-', FLOOR(1000 + RAND() * 9000));
        SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    END WHILE;
    
    -- ບັນທຶກ Barcode
    INSERT INTO barcode_generator (
        barcode, barcode_type, reference_type, reference_id, 
        generated_for, generated_by
    ) VALUES (
        v_barcode, p_barcode_type, 'stock', p_stock_id,
        CONCAT(v_item_code, ' - Batch: ', IFNULL(v_batch_number, 'N/A')), 
        p_generated_by
    );
    
    -- ອັບເດດ inventory_stock
    UPDATE inventory_stock 
    SET unique_barcode = v_barcode
    WHERE id = p_stock_id;
    
    -- ສົ່ງຄືນຂໍ້ມູນ
    SELECT v_barcode AS barcode, 'stock' AS reference_type, p_stock_id AS reference_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `purchase_items` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `purchase_items`(
    IN p_po_number VARCHAR(50),
    IN p_supplier_id INT,
    IN p_item_id INT,
    IN p_quantity INT,
    IN p_purchase_price DECIMAL(15,2),
    IN p_selling_price DECIMAL(15,2),
    IN p_serial_number VARCHAR(100),
    IN p_warranty_period INT,
    IN p_created_by INT
)
BEGIN
    DECLARE v_po_id INT;
    DECLARE v_total_amount DECIMAL(15,2);
    
    -- ຄຳນວນຍອດລວມ
    SET v_total_amount = p_quantity * p_purchase_price;
    
    -- ສ້າງ Purchase Order
    INSERT INTO purchase_orders (
        po_number, 
        supplier_id, 
        order_date, 
        subtotal, 
        total_amount, 
        created_by,
        status
    ) VALUES (
        p_po_number,
        p_supplier_id,
        CURDATE(),
        v_total_amount,
        v_total_amount,
        p_created_by,
        'received'
    );
    
    SET v_po_id = LAST_INSERT_ID();
    
    -- ເພີ່ມລາຍການຊື້
    INSERT INTO purchase_order_details (
        po_id, 
        item_id, 
        quantity, 
        received_quantity,
        unit_price, 
        total_price,
        warranty_period
    ) VALUES (
        v_po_id,
        p_item_id,
        p_quantity,
        p_quantity,
        p_purchase_price,
        v_total_amount,
        p_warranty_period
    );
    
    -- ຮັບສິນຄ້າເຂົ້າສະຕ໋ອກ
    INSERT INTO inventory_stock (
        item_id,
        batch_number,
        serial_number,
        quantity,
        purchase_price,
        selling_price,
        purchase_date,
        warranty_period,
        status
    ) VALUES (
        p_item_id,
        CONCAT('BATCH-', DATE_FORMAT(CURDATE(), '%Y%m%d')),
        p_serial_number,
        p_quantity,
        p_purchase_price,
        p_selling_price,
        CURDATE(),
        p_warranty_period,
        'in_stock'
    );
    
    -- ສົ່ງຄືນຂໍ້ມູນ
    SELECT 
        v_po_id AS po_id,
        p_po_number AS po_number,
        p_quantity AS quantity,
        v_total_amount AS total_amount;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `scan_barcode` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `scan_barcode`(
    IN p_barcode VARCHAR(100),
    IN p_scan_type VARCHAR(20),
    IN p_reference_type VARCHAR(20),
    IN p_reference_id INT,
    IN p_quantity INT,
    IN p_scan_location VARCHAR(200),
    IN p_scanned_by INT
)
BEGIN
    DECLARE v_item_id INT;
    DECLARE v_stock_id INT;
    DECLARE v_error_message TEXT DEFAULT NULL;
    DECLARE v_is_valid BOOLEAN DEFAULT TRUE;
    
    -- ຄົ້ນຫາຂໍ້ມູນຈາກ Barcode
    IF EXISTS (SELECT 1 FROM inventory_items WHERE barcode = p_barcode) THEN
        SELECT id INTO v_item_id FROM inventory_items WHERE barcode = p_barcode;
        SET v_stock_id = NULL;
        
    ELSEIF EXISTS (SELECT 1 FROM inventory_stock WHERE unique_barcode = p_barcode) THEN
        SELECT item_id, id INTO v_item_id, v_stock_id 
        FROM inventory_stock WHERE unique_barcode = p_barcode;
        
        -- ກວດສອບຄວາມຖືກຕ້ອງຂອງສະຕ໋ອກ
        IF p_scan_type = 'outgoing' THEN
            IF (SELECT quantity FROM inventory_stock WHERE id = v_stock_id) < p_quantity THEN
                SET v_is_valid = FALSE;
                SET v_error_message = 'Insufficient stock';
            END IF;
        END IF;
        
    ELSE
        SET v_is_valid = FALSE;
        SET v_error_message = 'Barcode not found in system';
    END IF;
    
    -- ບັນທຶກການສະແກນ
    INSERT INTO barcode_scans (
        barcode, scan_type, reference_type, reference_id,
        stock_id, item_id, quantity, scan_location,
        scanned_by, is_valid, error_message
    ) VALUES (
        p_barcode, p_scan_type, p_reference_type, p_reference_id,
        v_stock_id, v_item_id, p_quantity, p_scan_location,
        p_scanned_by, v_is_valid, v_error_message
    );
    
    -- ສົ່ງຄືນຜົນການສະແກນ
    SELECT 
        v_is_valid AS success,
        p_barcode AS barcode,
        v_error_message AS message,
        v_item_id AS item_id,
        v_stock_id AS stock_id,
        p_scan_type AS scan_type;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sell_item` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `sell_item`(
    IN p_so_number VARCHAR(50),
    IN p_customer_name VARCHAR(200),
    IN p_stock_id INT,
    IN p_quantity INT,
    IN p_selling_price DECIMAL(15,2),
    IN p_created_by INT
)
BEGIN
    DECLARE v_so_id INT;
    DECLARE v_item_id INT;
    DECLARE v_current_quantity INT;
    DECLARE v_total_amount DECIMAL(15,2);
    
    -- ກວດສອບສະຕ໋ອກ
    SELECT quantity, item_id INTO v_current_quantity, v_item_id
    FROM inventory_stock
    WHERE id = p_stock_id AND status = 'in_stock'
    LIMIT 1;
    
    IF v_current_quantity < p_quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
    
    -- ຄຳນວນຍອດລວມ
    SET v_total_amount = p_quantity * p_selling_price;
    
    -- ສ້າງ Sales Order
    INSERT INTO sales_orders (
        so_number,
        customer_name,
        order_date,
        subtotal,
        total_amount,
        created_by,
        status
    ) VALUES (
        p_so_number,
        p_customer_name,
        CURDATE(),
        v_total_amount,
        v_total_amount,
        p_created_by,
        'delivered'
    );
    
    SET v_so_id = LAST_INSERT_ID();
    
    -- ເພີ່ມລາຍການຂາຍ
    INSERT INTO sales_order_details (
        so_id,
        stock_id,
        item_id,
        quantity,
        unit_price,
        total_price
    ) VALUES (
        v_so_id,
        p_stock_id,
        v_item_id,
        p_quantity,
        p_selling_price,
        v_total_amount
    );
    
    -- ອັບເດດສະຕ໋ອກ
    UPDATE inventory_stock 
    SET quantity = quantity - p_quantity
    WHERE id = p_stock_id;
    
    -- ສົ່ງຄືນຂໍ້ມູນ
    SELECT 
        v_so_id AS so_id,
        p_so_number AS so_number,
        LAST_INSERT_ID() AS sales_order_detail_id,
        v_total_amount AS total_amount;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `update_barcode_print_count` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`%` PROCEDURE `update_barcode_print_count`(
    IN p_barcode VARCHAR(100),
    IN p_printed_by INT
)
BEGIN
    UPDATE barcode_generator 
    SET print_count = print_count + 1,
        last_printed_at = NOW()
    WHERE barcode = p_barcode;
    
    SELECT 
        barcode,
        print_count,
        last_printed_at
    FROM barcode_generator
    WHERE barcode = p_barcode;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `vw_barcode_inventory`
--

/*!50001 DROP VIEW IF EXISTS `vw_barcode_inventory`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_barcode_inventory` AS select 'ITEM' AS `type`,`ii`.`id` AS `reference_id`,`ii`.`item_code` AS `code`,`ii`.`item_name` AS `name`,`ii`.`barcode` AS `barcode`,`ii`.`barcode_type` AS `barcode_type`,NULL AS `batch_number`,NULL AS `serial_number`,NULL AS `stock_quantity` from `inventory_items` `ii` where (`ii`.`barcode` is not null) union all select 'STOCK' AS `type`,`is2`.`id` AS `reference_id`,`ii`.`item_code` AS `code`,`ii`.`item_name` AS `name`,`is2`.`unique_barcode` AS `barcode`,'CODE128' AS `barcode_type`,`is2`.`batch_number` AS `batch_number`,`is2`.`serial_number` AS `serial_number`,`is2`.`quantity` AS `stock_quantity` from (`inventory_stock` `is2` join `inventory_items` `ii` on((`is2`.`item_id` = `ii`.`id`))) where (`is2`.`unique_barcode` is not null) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_inventory_summary`
--

/*!50001 DROP VIEW IF EXISTS `vw_inventory_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_inventory_summary` AS select `ii`.`id` AS `id`,`ii`.`item_code` AS `item_code`,`ii`.`item_name` AS `item_name`,`ii`.`brand` AS `brand`,`ii`.`model` AS `model`,`ac`.`category_name` AS `category_name`,`s`.`supplier_name` AS `supplier_name`,count(`is2`.`id`) AS `total_stock`,sum(`is2`.`quantity`) AS `total_quantity`,avg(`is2`.`purchase_price`) AS `avg_purchase_price`,avg(`is2`.`selling_price`) AS `avg_selling_price`,sum((`is2`.`quantity` * `is2`.`purchase_price`)) AS `total_inventory_value` from (((`inventory_items` `ii` left join `asset_categories` `ac` on((`ii`.`category_id` = `ac`.`id`))) left join `suppliers` `s` on((`ii`.`supplier_id` = `s`.`id`))) left join `inventory_stock` `is2` on(((`ii`.`id` = `is2`.`item_id`) and (`is2`.`status` = 'in_stock')))) group by `ii`.`id` */;
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

-- Dump completed on 2026-02-27  6:11:34
