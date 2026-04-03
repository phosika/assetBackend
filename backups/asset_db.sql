-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: db
-- Generation Time: Apr 03, 2026 at 09:21 AM
-- Server version: 8.0.45
-- PHP Version: 8.3.26

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `asset_db`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`%` PROCEDURE `check_inventory_stock` (IN `p_item_id` INT)   BEGIN
    IF p_item_id IS NULL OR p_item_id = 0 THEN
        
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
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `convert_sold_item_to_asset` (IN `p_sales_order_detail_id` INT, IN `p_asset_code` VARCHAR(100), IN `p_asset_name` VARCHAR(200), IN `p_department_id` INT, IN `p_created_by` INT)   BEGIN
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
    
    
    SELECT company_id INTO v_company_id
    FROM departments
    WHERE id = p_department_id;
    
    
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
    
    
    SET v_new_asset_id = LAST_INSERT_ID();
    
    
    UPDATE sales_order_details 
    SET is_converted_to_asset = true,
        asset_id = v_new_asset_id
    WHERE id = p_sales_order_detail_id;
    
    
    UPDATE inventory_stock 
    SET status = 'sold'
    WHERE id = v_stock_id;
    
    
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
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `create_category_with_path` (IN `p_category_code` VARCHAR(50), IN `p_category_name` VARCHAR(200), IN `p_parent_id` INT)   BEGIN
    DECLARE v_level INT;
    DECLARE v_parent_path VARCHAR(255);
    DECLARE v_new_id INT;
    
    
    IF p_parent_id IS NULL THEN
        SET v_level = 1;
        SET v_parent_path = '';
    ELSE
        SELECT level, path INTO v_level, v_parent_path
        FROM asset_categories 
        WHERE id = p_parent_id;
        
        SET v_level = v_level + 1;
    END IF;
    
    
    INSERT INTO asset_categories (
        category_code, category_name, parent_id, level
    ) VALUES (
        p_category_code, p_category_name, p_parent_id, v_level
    );
    
    SET v_new_id = LAST_INSERT_ID();
    
    
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
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `create_new_asset` (IN `p_asset_code` VARCHAR(100), IN `p_asset_name` VARCHAR(200), IN `p_category_level3_id` INT, IN `p_purchase_date` DATE, IN `p_purchase_cost` DECIMAL(15,2), IN `p_supplier_id` INT, IN `p_company_id` INT, IN `p_department_id` INT, IN `p_current_user_id` INT, IN `p_created_by` INT, IN `p_serial_number` VARCHAR(100), IN `p_warranty_expiry` DATE, IN `p_useful_life_years` INT)   BEGIN
    DECLARE v_category_level1_id INT;
    DECLARE v_category_level2_id INT;
    DECLARE v_depreciation_method ENUM('straight_line', 'declining_balance', 'none');
    DECLARE v_path VARCHAR(255);
    
    
    IF NOT EXISTS (SELECT 1 FROM asset_categories WHERE id = p_category_level3_id) THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Category ID not found';
    END IF;
    
    
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
    
    
    IF v_category_level1_id = 0 THEN
        SET v_category_level1_id = p_category_level3_id;
        SET v_category_level2_id = NULL;
    END IF;
    
    
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
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `create_new_asset_advanced` (IN `p_asset_code` VARCHAR(100), IN `p_asset_name` VARCHAR(200), IN `p_category_level3_id` INT, IN `p_purchase_date` DATE, IN `p_purchase_cost` DECIMAL(15,2), IN `p_supplier_id` INT, IN `p_company_id` INT, IN `p_department_id` INT, IN `p_current_user_id` INT, IN `p_created_by` INT, IN `p_serial_number` VARCHAR(100), IN `p_warranty_expiry` DATE, IN `p_useful_life_years` INT)   BEGIN
    DECLARE v_category_level1_id INT;
    DECLARE v_category_level2_id INT;
    DECLARE v_depreciation_method ENUM('straight_line', 'declining_balance', 'none');
    DECLARE v_path VARCHAR(255);
    DECLARE v_category_name VARCHAR(200);
    
    
    IF p_asset_code IS NULL OR p_asset_code = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset code is required';
    END IF;
    
    IF p_asset_name IS NULL OR p_asset_name = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset name is required';
    END IF;
    
    
    IF EXISTS (SELECT 1 FROM assets WHERE asset_code = p_asset_code) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Asset code already exists';
    END IF;
    
    
    IF NOT EXISTS (SELECT 1 FROM asset_categories WHERE id = p_category_level3_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Category ID not found';
    END IF;
    
    
    IF NOT EXISTS (SELECT 1 FROM companies WHERE id = p_company_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Company ID not found';
    END IF;
    
    
    IF NOT EXISTS (SELECT 1 FROM departments WHERE id = p_department_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Department ID not found';
    END IF;
    
    
    IF p_supplier_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM suppliers WHERE id = p_supplier_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Supplier ID not found';
    END IF;
    
    
    IF p_current_user_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users WHERE id = p_current_user_id) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'User ID not found';
    END IF;
    
    
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
    
    
    IF v_category_level1_id = 0 THEN
        SET v_category_level1_id = p_category_level3_id;
        SET v_category_level2_id = NULL;
    END IF;
    
    
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
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `generate_item_barcode` (IN `p_item_id` INT, IN `p_barcode_type` VARCHAR(20), IN `p_generated_by` INT)   BEGIN
    DECLARE v_item_code VARCHAR(50);
    DECLARE v_item_name VARCHAR(200);
    DECLARE v_barcode VARCHAR(100);
    DECLARE v_exists INT;
    
    
    SELECT item_code, item_name INTO v_item_code, v_item_name
    FROM inventory_items
    WHERE id = p_item_id;
    
    
    SET v_barcode = CONCAT('ITM-', p_item_id, '-', FLOOR(100000 + RAND() * 900000));
    
    
    SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    
    WHILE v_exists > 0 DO
        SET v_barcode = CONCAT('ITM-', p_item_id, '-', FLOOR(100000 + RAND() * 900000));
        SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    END WHILE;
    
    
    INSERT INTO barcode_generator (
        barcode, barcode_type, reference_type, reference_id, 
        generated_for, generated_by
    ) VALUES (
        v_barcode, p_barcode_type, 'item', p_item_id,
        CONCAT(v_item_code, ' - ', v_item_name), p_generated_by
    );
    
    
    UPDATE inventory_items 
    SET barcode = v_barcode,
        barcode_type = p_barcode_type
    WHERE id = p_item_id;
    
    
    SELECT v_barcode AS barcode, 'item' AS reference_type, p_item_id AS reference_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `generate_stock_barcode` (IN `p_stock_id` INT, IN `p_barcode_type` VARCHAR(20), IN `p_generated_by` INT)   BEGIN
    DECLARE v_item_code VARCHAR(50);
    DECLARE v_batch_number VARCHAR(100);
    DECLARE v_serial_number VARCHAR(100);
    DECLARE v_barcode VARCHAR(100);
    DECLARE v_exists INT;
    
    
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
    
    
    IF v_serial_number IS NOT NULL AND v_serial_number != '' THEN
        SET v_barcode = CONCAT('SN-', v_serial_number);
    ELSE
        SET v_barcode = CONCAT('STK-', p_stock_id, '-', FLOOR(1000 + RAND() * 9000));
    END IF;
    
    
    SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    
    WHILE v_exists > 0 DO
        SET v_barcode = CONCAT('STK-', p_stock_id, '-', FLOOR(1000 + RAND() * 9000));
        SELECT COUNT(*) INTO v_exists FROM barcode_generator WHERE barcode = v_barcode;
    END WHILE;
    
    
    INSERT INTO barcode_generator (
        barcode, barcode_type, reference_type, reference_id, 
        generated_for, generated_by
    ) VALUES (
        v_barcode, p_barcode_type, 'stock', p_stock_id,
        CONCAT(v_item_code, ' - Batch: ', IFNULL(v_batch_number, 'N/A')), 
        p_generated_by
    );
    
    
    UPDATE inventory_stock 
    SET unique_barcode = v_barcode
    WHERE id = p_stock_id;
    
    
    SELECT v_barcode AS barcode, 'stock' AS reference_type, p_stock_id AS reference_id;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `initialize_missing_stock` ()   BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_item_id INT;
    DECLARE v_created_by INT;
    DECLARE cur CURSOR FOR 
        SELECT i.id, COALESCE(i.created_by, 1) 
        FROM inventory_items i 
        LEFT JOIN inventory_stock s ON i.id = s.item_id 
        WHERE s.id IS NULL AND i.is_active = 1;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_item_id, v_created_by;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        INSERT INTO inventory_stock (
            item_id,
            warehouse_id,
            current_quantity,
            reserved_quantity,
            created_at,
            created_by
        ) VALUES (
            v_item_id,
            1,
            0,
            0,
            NOW(),
            v_created_by
        );
    END LOOP;
    
    CLOSE cur;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `purchase_items` (IN `p_po_number` VARCHAR(50), IN `p_supplier_id` INT, IN `p_item_id` INT, IN `p_quantity` INT, IN `p_purchase_price` DECIMAL(15,2), IN `p_selling_price` DECIMAL(15,2), IN `p_serial_number` VARCHAR(100), IN `p_warranty_period` INT, IN `p_created_by` INT)   BEGIN
    DECLARE v_po_id INT;
    DECLARE v_total_amount DECIMAL(15,2);
    
    
    SET v_total_amount = p_quantity * p_purchase_price;
    
    
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
    
    
    SELECT 
        v_po_id AS po_id,
        p_po_number AS po_number,
        p_quantity AS quantity,
        v_total_amount AS total_amount;
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `scan_barcode` (IN `p_barcode` VARCHAR(100), IN `p_scan_type` VARCHAR(20), IN `p_reference_type` VARCHAR(20), IN `p_reference_id` INT, IN `p_quantity` INT, IN `p_scan_location` VARCHAR(200), IN `p_scanned_by` INT)   BEGIN
    DECLARE v_item_id INT;
    DECLARE v_stock_id INT;
    DECLARE v_error_message TEXT DEFAULT NULL;
    DECLARE v_is_valid BOOLEAN DEFAULT TRUE;
    
    
    IF EXISTS (SELECT 1 FROM inventory_items WHERE barcode = p_barcode) THEN
        SELECT id INTO v_item_id FROM inventory_items WHERE barcode = p_barcode;
        SET v_stock_id = NULL;
        
    ELSEIF EXISTS (SELECT 1 FROM inventory_stock WHERE unique_barcode = p_barcode) THEN
        SELECT item_id, id INTO v_item_id, v_stock_id 
        FROM inventory_stock WHERE unique_barcode = p_barcode;
        
        
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
    
    
    INSERT INTO barcode_scans (
        barcode, scan_type, reference_type, reference_id,
        stock_id, item_id, quantity, scan_location,
        scanned_by, is_valid, error_message
    ) VALUES (
        p_barcode, p_scan_type, p_reference_type, p_reference_id,
        v_stock_id, v_item_id, p_quantity, p_scan_location,
        p_scanned_by, v_is_valid, v_error_message
    );
    
    
    SELECT 
        v_is_valid AS success,
        p_barcode AS barcode,
        v_error_message AS message,
        v_item_id AS item_id,
        v_stock_id AS stock_id,
        p_scan_type AS scan_type;
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `sell_item` (IN `p_so_number` VARCHAR(50), IN `p_customer_name` VARCHAR(200), IN `p_stock_id` INT, IN `p_quantity` INT, IN `p_selling_price` DECIMAL(15,2), IN `p_created_by` INT)   BEGIN
    DECLARE v_so_id INT;
    DECLARE v_item_id INT;
    DECLARE v_current_quantity INT;
    DECLARE v_total_amount DECIMAL(15,2);
    
    
    SELECT quantity, item_id INTO v_current_quantity, v_item_id
    FROM inventory_stock
    WHERE id = p_stock_id AND status = 'in_stock'
    LIMIT 1;
    
    IF v_current_quantity < p_quantity THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Insufficient stock';
    END IF;
    
    
    SET v_total_amount = p_quantity * p_selling_price;
    
    
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
    
    
    UPDATE inventory_stock 
    SET quantity = quantity - p_quantity
    WHERE id = p_stock_id;
    
    
    SELECT 
        v_so_id AS so_id,
        p_so_number AS so_number,
        LAST_INSERT_ID() AS sales_order_detail_id,
        v_total_amount AS total_amount;
    
END$$

CREATE DEFINER=`root`@`%` PROCEDURE `update_barcode_print_count` (IN `p_barcode` VARCHAR(100), IN `p_printed_by` INT)   BEGIN
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
END$$

--
-- Functions
--
CREATE DEFINER=`root`@`%` FUNCTION `find_item_by_barcode` (`p_barcode` VARCHAR(100)) RETURNS JSON DETERMINISTIC BEGIN
    DECLARE v_result JSON;
    
    
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int NOT NULL,
  `asset_code` varchar(100) NOT NULL,
  `asset_name` varchar(200) NOT NULL,
  `asset_name_en` varchar(200) DEFAULT NULL,
  `old_asset_code` varchar(100) DEFAULT NULL COMMENT 'ລະຫັດຊັບສິນເກົ່າ (ກ່ອນໃຊ້ລະບົບ)',
  `category_level1_id` int DEFAULT NULL,
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
  `custom_fields` json DEFAULT NULL COMMENT 'ເກັບຂໍ້ມູນສະເພາະທີ່ບໍ່ມີໃນຕາຕະລາງ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_code`, `asset_name`, `asset_name_en`, `old_asset_code`, `category_level1_id`, `category_level2_id`, `category_level3_id`, `category_id`, `description`, `brand`, `model`, `serial_number`, `manufacturing_year`, `country_of_origin`, `color`, `size_dimensions`, `weight`, `purchase_date`, `purchase_cost`, `purchase_cost_usd`, `exchange_rate`, `supplier_id`, `purchase_invoice_no`, `purchase_order_no`, `payment_status`, `warranty_provider`, `warranty_expiry`, `warranty_terms`, `insurance_policy_no`, `insurance_expiry`, `insurance_provider`, `company_id`, `department_id`, `current_user_id`, `location_id`, `building`, `floor`, `room`, `exact_location`, `gps_coordinates`, `status`, `asset_condition`, `condition_notes`, `last_maintenance_date`, `next_maintenance_date`, `maintenance_frequency_days`, `current_value`, `salvage_value`, `accumulated_depreciation`, `depreciation_start_date`, `depreciation_end_date`, `last_depreciation_date`, `depreciation_method`, `useful_life_years`, `useful_life_months`, `depreciation_rate`, `has_warranty`, `warranty_document_path`, `has_manual`, `manual_document_path`, `has_invoice`, `invoice_document_path`, `has_certificate`, `certificate_document_path`, `asset_image_path`, `additional_documents`, `qr_code`, `qr_code_image_path`, `barcode`, `barcode_image_path`, `rfid_tag`, `asset_label_printed`, `last_printed_date`, `created_by`, `created_at`, `updated_by`, `updated_at`, `verified_by`, `verified_at`, `verification_notes`, `is_active`, `notes`, `custom_fields`) VALUES
(23, 'AST20260300005', 'ໂຕະ', NULL, NULL, 1, NULL, NULL, 1, NULL, 'TEST', 'TEST', '12345678990', 2025, 'china', 'white', NULL, NULL, '2026-01-15', 15000000.00, NULL, NULL, 1, '11111111', '22222222', 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4, 4, 4, NULL, NULL, NULL, NULL, NULL, 'in_use', 'good', NULL, '2026-03-27', '2026-06-15', 1, 15000000.00, 0.00, 0.00, '2026-01-15', NULL, NULL, 'straight_line', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'AST20260300005', NULL, NULL, 0, NULL, 2, '2026-02-25 15:27:36', 6, '2026-03-27 07:06:50', NULL, NULL, NULL, 1, NULL, NULL),
(24, 'AST20260300006', 'ເຄື່ອງພິມ', NULL, NULL, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-20', 5000000.00, NULL, NULL, 1, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 4, NULL, 4, NULL, NULL, NULL, NULL, NULL, 'available', 'good', NULL, NULL, NULL, NULL, 5000000.00, 0.00, 0.00, '2026-01-20', '2031-01-20', NULL, 'straight_line', 5, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'AST20260300006', NULL, NULL, 0, NULL, 3, '2026-02-25 15:27:36', 6, '2026-03-23 05:08:56', NULL, NULL, NULL, 1, NULL, NULL),
(25, 'AST20260300007', 'ໂຕະພະນັກງານ', NULL, NULL, 1, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-01', 3000000.00, NULL, NULL, 2, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 2, 5, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'in_use', 'good', NULL, NULL, NULL, NULL, 3000000.00, 0.00, 0.00, '2026-02-01', '2036-02-01', NULL, 'straight_line', 10, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, 'AST20260300007', NULL, NULL, 0, NULL, 4, '2026-02-25 15:27:36', 6, '2026-03-23 05:09:03', NULL, NULL, NULL, 1, NULL, NULL),
(26, 'AST009', 'ໂນດບຸກຂາຍ', NULL, NULL, 1, NULL, NULL, 1, NULL, NULL, NULL, 'SN-697203', NULL, NULL, NULL, NULL, NULL, '2026-02-26', 2800000.00, NULL, NULL, 1, NULL, NULL, 'paid', NULL, '2028-02-26', NULL, NULL, NULL, NULL, 1, 4, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'in_use', 'good', NULL, NULL, NULL, NULL, 2800000.00, 0.00, 0.00, '2026-02-26', NULL, NULL, 'straight_line', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, 2, '2026-02-26 07:57:22', NULL, NULL, NULL, NULL, NULL, 1, 'ປ່ຽນມາຈາກສິນຄ້າຂາຍ', NULL),
(27, '101000005', 'ASUS ROG Strix', NULL, NULL, 8, NULL, NULL, 8, NULL, 'ASUS', 'G502V', NULL, NULL, NULL, NULL, NULL, NULL, '2025-01-01', 15000000.00, NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'in_use', 'good', NULL, NULL, NULL, NULL, 15000000.00, 0.00, 0.00, '2025-01-01', NULL, NULL, 'straight_line', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'QR2026030400000027', NULL, '101000005', NULL, NULL, 0, NULL, 6, '2026-03-04 15:45:02', 6, '2026-03-07 10:14:05', NULL, NULL, NULL, 1, NULL, NULL),
(28, '108000026', 'Monitor 21.5 ACER', NULL, NULL, 28, NULL, NULL, 28, NULL, 'ACER', 'SA220QBbix', NULL, NULL, NULL, NULL, NULL, NULL, '2022-08-21', 3500000.00, NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'in_use', 'good', NULL, NULL, NULL, NULL, 3500000.00, 0.00, 0.00, '2022-08-21', NULL, NULL, 'straight_line', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'QR2026030600000028', NULL, '108000026', NULL, NULL, 0, NULL, 6, '2026-03-06 16:33:31', 6, '2026-03-07 10:14:14', NULL, NULL, NULL, 1, NULL, NULL),
(30, 'AST20260300001', 'ໂນດບຸກ Dell XPS 15', 'ໂນດບຸກ Dell XPS 15', NULL, 8, NULL, NULL, 8, 'ສິນຄ້າທີ່ຂາຍອອກ: ໂນດບຸກ Dell XPS 15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20', 15000000.00, NULL, NULL, 2, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, 'sold', 'good', NULL, NULL, NULL, NULL, 15000000.00, 0.00, 0.00, '2026-03-20', NULL, NULL, 'none', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'QR2026032200000030', NULL, 'BC2026032200000030', NULL, NULL, 0, NULL, 6, '2026-03-22 16:32:41', 6, '2026-03-23 05:06:23', NULL, NULL, NULL, 1, 'ຂາຍອອກຕາມໃບຂາຍ SO-2026-004 - ລູກຄ້າ: ໂພສີກະ ສິດທິສານ', '{\"item_id\": 4, \"quantity\": 1, \"item_code\": \"ITEM2025010001\", \"sale_date\": \"2026-03-20\", \"source_id\": 8, \"unit_price\": 15000000, \"customer_id\": 1, \"source_type\": \"sales_order\", \"customer_name\": \"ໂພສີກະ ສິດທິສານ\", \"source_number\": \"SO-2026-004\"}'),
(31, 'AST20260300002', 'ໂນດບຸກ Dell XPS 15', 'ໂນດບຸກ Dell XPS 15', NULL, 1, NULL, NULL, 1, 'ສິນຄ້າທີ່ຂາຍອອກ: ໂນດບຸກ Dell XPS 15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20', 15000000.00, NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, 'sold', 'good', NULL, NULL, NULL, NULL, 15000000.00, 0.00, 0.00, '2026-03-20', NULL, NULL, 'none', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'QR2026032200000031', NULL, 'BC2026032200000031', NULL, NULL, 0, NULL, 6, '2026-03-22 17:01:22', NULL, '2026-03-23 05:06:23', NULL, NULL, NULL, 1, 'ຂາຍອອກຕາມໃບຂາຍ SO-2026-004 - ລູກຄ້າ: ໂພສີກະ ສິດທິສານ', '{\"item_id\": 4, \"quantity\": 1, \"item_code\": \"ITEM2025010001\", \"sale_date\": \"2026-03-20\", \"source_id\": 8, \"unit_price\": 15000000, \"customer_id\": 1, \"source_type\": \"sales_order\", \"customer_name\": \"ໂພສີກະ ສິດທິສານ\", \"source_number\": \"SO-2026-004\"}'),
(32, 'AST20260300003', 'ໂນດບຸກ Dell XPS 15', 'ໂນດບຸກ Dell XPS 15', NULL, 1, NULL, NULL, 1, 'ສິນຄ້າທີ່ຂາຍອອກ: ໂນດບຸກ Dell XPS 15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-03-20', 15000000.00, NULL, NULL, NULL, NULL, NULL, 'paid', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1, 6, NULL, NULL, NULL, NULL, NULL, NULL, 'sold', 'good', NULL, NULL, NULL, NULL, 15000000.00, 0.00, 0.00, '2026-03-20', NULL, NULL, 'none', NULL, NULL, NULL, 0, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, NULL, 'QR2026032200000032', NULL, 'BC2026032200000032', NULL, NULL, 0, NULL, 6, '2026-03-22 17:49:48', NULL, '2026-03-23 05:06:23', NULL, NULL, NULL, 1, 'ຂາຍອອກຕາມໃບຂາຍ SO-2026-004 - ລູກຄ້າ: ໂພສີກະ ສິດທິສານ', '{\"item_id\": 4, \"quantity\": 1, \"item_code\": \"ITEM2025010001\", \"sale_date\": \"2026-03-20\", \"source_id\": 8, \"unit_price\": 15000000, \"customer_id\": 1, \"source_type\": \"sales_order\", \"customer_name\": \"ໂພສີກະ ສິດທິສານ\", \"source_number\": \"SO-2026-004\"}');

--
-- Triggers `assets`
--
DELIMITER $$
CREATE TRIGGER `auto_update_asset_status` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    
    IF NEW.asset_condition IN ('damaged', 'poor') AND NEW.status = 'in_use' THEN
        SET NEW.status = 'damaged';
    END IF;
    
    
    IF NEW.asset_condition IN ('good', 'excellent') AND OLD.asset_condition IN ('damaged', 'poor') 
       AND NEW.status = 'damaged' THEN
        SET NEW.status = 'available';
    END IF;
    
    
    IF NEW.useful_life_years IS NOT NULL AND NEW.purchase_date IS NOT NULL THEN
        IF DATE_ADD(NEW.purchase_date, INTERVAL NEW.useful_life_years YEAR) < CURDATE() 
           AND NEW.status NOT IN ('disposed', 'sold') THEN
            SET NEW.asset_condition = 'obsolete';
            SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
                ' [ໝົດອາຍຸການໃຊ້ງານ: ', DATE_FORMAT(NOW(), '%d/%m/%Y'), ']');
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_asset_changes` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    
    IF NEW.department_id != OLD.department_id OR NEW.current_user_id != OLD.current_user_id THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ຍ້າຍ: ', DATE_FORMAT(NOW(), '%d/%m/%Y %H:%i'), ']');
    END IF;
    
    
    IF NEW.purchase_cost != OLD.purchase_cost THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ປ່ຽນມູນຄ່າ: ຈາກ ', OLD.purchase_cost, ' ເປັນ ', NEW.purchase_cost, 
            ' (', DATE_FORMAT(NOW(), '%d/%m/%Y'), ')]');
    END IF;
    
    
    IF NEW.status = 'disposed' AND OLD.status != 'disposed' THEN
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
            ' [ຕັດຊໍາລຸດ: ', DATE_FORMAT(NOW(), '%d/%m/%Y'), ']');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `set_initial_asset_value` BEFORE INSERT ON `assets` FOR EACH ROW BEGIN
    
    IF NEW.current_value IS NULL THEN
        SET NEW.current_value = NEW.purchase_cost;
    END IF;
    
    
    IF NEW.accumulated_depreciation IS NULL THEN
        SET NEW.accumulated_depreciation = 0;
    END IF;
    
    
    IF NEW.depreciation_start_date IS NULL THEN
        SET NEW.depreciation_start_date = NEW.purchase_date;
    END IF;
    
    
    IF NEW.depreciation_end_date IS NULL AND NEW.useful_life_years IS NOT NULL THEN
        SET NEW.depreciation_end_date = DATE_ADD(NEW.purchase_date, INTERVAL NEW.useful_life_years YEAR);
    END IF;
    
    
    IF NEW.status IS NULL THEN
        SET NEW.status = 'available';
    END IF;
    
    
    IF NEW.asset_condition IS NULL THEN
        SET NEW.asset_condition = 'good';
    END IF;
    
    
    IF NEW.is_active IS NULL THEN
        SET NEW.is_active = true;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_asset_current_value` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    
    IF (NEW.purchase_cost != OLD.purchase_cost) OR (NEW.accumulated_depreciation != OLD.accumulated_depreciation) THEN
        SET NEW.current_value = NEW.purchase_cost - NEW.accumulated_depreciation;
    END IF;
    
    
    IF (NEW.status IN ('disposed', 'sold')) AND (OLD.status NOT IN ('disposed', 'sold')) THEN
        SET NEW.current_value = 0;
        SET NEW.accumulated_depreciation = NEW.purchase_cost;
    END IF;
    
    
    IF (OLD.status IN ('disposed', 'sold')) AND (NEW.status NOT IN ('disposed', 'sold')) THEN
        SET NEW.current_value = NEW.purchase_cost - NEW.accumulated_depreciation;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_maintenance_dates` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    
    IF NEW.last_maintenance_date != OLD.last_maintenance_date THEN
        
        IF NEW.maintenance_frequency_days IS NOT NULL AND NEW.maintenance_frequency_days > 0 THEN
            SET NEW.next_maintenance_date = DATE_ADD(NEW.last_maintenance_date, 
                INTERVAL NEW.maintenance_frequency_days DAY);
        END IF;
    END IF;
    
    
    IF NEW.next_maintenance_date IS NOT NULL AND NEW.next_maintenance_date < CURDATE() 
       AND NEW.status NOT IN ('maintenance', 'disposed', 'sold') THEN
        
        
        
        SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), ' [ແຈ້ງເຕືອນ: ຮອດກຳນົດບຳລຸງຮັກສາວັນທີ ', 
                               DATE_FORMAT(NEW.next_maintenance_date, '%d/%m/%Y'), ']');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_warranty_status` BEFORE UPDATE ON `assets` FOR EACH ROW BEGIN
    
    IF NEW.warranty_expiry IS NOT NULL THEN
        IF NEW.warranty_expiry < CURDATE() THEN
            SET NEW.has_warranty = false;
        ELSE
            SET NEW.has_warranty = true;
        END IF;
    END IF;
    
    
    IF NEW.warranty_expiry IS NOT NULL AND OLD.warranty_expiry IS NOT NULL THEN
        IF NEW.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
           AND OLD.warranty_expiry NOT BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN
            SET NEW.notes = CONCAT(IFNULL(NEW.notes, ''), 
                ' [ແຈ້ງເຕືອນ: ປະກັນຈະໝົດອາຍຸວັນທີ ', 
                DATE_FORMAT(NEW.warranty_expiry, '%d/%m/%Y'), ']');
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `asset_categories`
--

CREATE TABLE `asset_categories` (
  `id` int NOT NULL,
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
  `created_by` int DEFAULT NULL
) ;

--
-- Dumping data for table `asset_categories`
--

INSERT INTO `asset_categories` (`id`, `category_code`, `category_name`, `description`, `parent_id`, `level`, `path`, `depreciation_method`, `useful_life_years`, `depreciation_rate`, `is_active`, `sort_order`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'HW', 'ຮາດແວ', NULL, NULL, 1, 'ຮາດແວ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:05', NULL),
(2, 'SW', 'ຊອບແວ', NULL, NULL, 1, 'ຊອບແວ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:09', NULL),
(3, 'FURN', 'ເຄື່ອງເຟີນີເຈີ', NULL, NULL, 1, 'ເຄື່ອງເຟີນີເຈີ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:16', NULL),
(4, 'COM', 'ຄອມພິວເຕີ', NULL, 1, 2, 'ຮາດແວ/ຄອມພິວເຕີ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:25', NULL),
(5, 'PRINT', 'ເຄື່ອງພິມ', NULL, 1, 2, 'ຮາດແວ/ເຄື່ອງພິມ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:30', NULL),
(6, 'DESK', 'ໂຕະ', NULL, 3, 2, 'ເຄື່ອງເຟີນີເຈີ/ໂຕະ', 'straight_line', 5, NULL, 1, 2, '2026-02-25 15:25:40', '2026-03-03 14:11:33', NULL),
(7, 'CHAIR', 'ຕັ່ງ', NULL, 3, 2, 'ເຄື່ອງເຟີນີເຈີ/ຕັ່ງ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:37', NULL),
(8, 'LAPTOP', 'ໂນດບຸກ', NULL, 4, 3, 'ຮາດແວ/ຄອມພິວເຕີ/ໂນດບຸກ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:42', NULL),
(9, 'PC', 'ຄອມພິວເຕີຕັ້ງໂຕະ', NULL, 4, 3, 'ຮາດແວ/ຄອມພິວເຕີ/ຄອມພິວເຕີຕັ້ງໂຕະ', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:46', NULL),
(10, 'LASER', 'ເຄື່ອງພິມ Laser', NULL, 5, 3, 'ຮາດແວ/ເຄື່ອງພິມ/ເຄື່ອງພິມ Laser', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:50', NULL),
(11, 'INKJET', 'ເຄື່ອງພິມ Inkjet', NULL, 5, 3, 'ຮາດແວ/ເຄື່ອງພິມ/ເຄື່ອງພິມ Inkjet', 'straight_line', NULL, NULL, 1, 0, '2026-02-25 15:25:40', '2026-03-03 14:11:57', NULL),
(12, 'SERVER', 'ເຊີບເວີ', NULL, 1, 2, 'ຮາດແວ/ເຊີບເວີ', 'straight_line', 10, NULL, 1, 2, '2026-02-26 09:31:48', '2026-03-03 14:12:01', NULL),
(13, 'SERVER-RACK', 'ເຊີບເວີແບບ Rack', NULL, 12, 2, 'ຮາດແວ/ເຊີບເວີ/ເຊີບເວີແບບ Rack', 'straight_line', NULL, NULL, 1, 0, '2026-02-26 09:31:54', '2026-03-03 14:12:05', NULL),
(14, 'SERVER-DELL', 'ເຊີບເວີ Dell', NULL, 13, 3, 'ຮາດແວ/ເຊີບເວີ/ເຊີບເວີແບບ Rack/ເຊີບເວີ Dell', 'straight_line', NULL, NULL, 1, 0, '2026-02-26 09:31:57', '2026-03-03 14:12:12', NULL),
(24, 'CAR', 'ລົດ', NULL, 25, 2, 'ພາຫະນະ/ລົດ', 'straight_line', 10, NULL, 1, 2, '2026-03-03 13:40:17', '2026-03-03 14:27:09', 6),
(25, 'VEHICLE', 'ພາຫະນະ', NULL, NULL, 1, 'ພາຫະນະ', 'straight_line', NULL, NULL, 1, 0, '2026-03-03 14:26:08', NULL, 6),
(26, 'HONDA-CIVIC', 'ຮອນດາ-ຊີວີກ', NULL, 24, 3, 'ພາຫະນະ/ລົດ/ຮອນດາ-ຊີວີກ', 'straight_line', 10, NULL, 1, 3, '2026-03-03 14:30:57', '2026-03-03 14:42:42', 6),
(27, 'HUNDAI-i30', 'ຮຸນໄດ້-ໄອ30', NULL, 24, 3, 'ພາຫະນະ/ລົດ/ຮຸນໄດ້-ໄອ30', 'straight_line', NULL, NULL, 1, 3, '2026-03-03 14:54:38', NULL, 6),
(28, ' MONITOR', 'ຈໍຄອມພີວເຕີ', 'ຈໍຄອມພີວເຕີ', 4, 3, 'ຮາດແວ/ຄອມພິວເຕີ/ຈໍຄອມພີວເຕີ', 'straight_line', 5, NULL, 1, 3, '2026-03-06 16:29:32', NULL, 6);

-- --------------------------------------------------------

--
-- Table structure for table `asset_depreciation`
--

CREATE TABLE `asset_depreciation` (
  `id` int NOT NULL,
  `asset_id` int NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `period_year` int NOT NULL,
  `period_month` int NOT NULL,
  `opening_value` decimal(15,2) NOT NULL COMMENT 'ມູນຄ່າຕົ້ນງວດ',
  `depreciation_amount` decimal(15,2) NOT NULL COMMENT 'ຄ່າເສື່ອມລາຄາງວດ',
  `accumulated_depreciation` decimal(15,2) NOT NULL COMMENT 'ຄ່າເສື່ອມລາຄາສະສົມ',
  `closing_value` decimal(15,2) NOT NULL COMMENT 'ມູນຄ່າສຸດທ້າຍງວດ',
  `depreciation_method` enum('straight_line','declining_balance','sum_of_years','units_of_production','none') NOT NULL,
  `depreciation_rate` decimal(5,2) DEFAULT NULL,
  `useful_life_years` int DEFAULT NULL,
  `useful_life_months` int DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_documents`
--

CREATE TABLE `asset_documents` (
  `id` int NOT NULL,
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
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_images`
--

CREATE TABLE `asset_images` (
  `id` int NOT NULL,
  `asset_id` int NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `image_type` enum('main','additional','damage','maintenance') DEFAULT 'additional',
  `description` varchar(255) DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `asset_sync_log`
--

CREATE TABLE `asset_sync_log` (
  `id` int NOT NULL,
  `source_type` varchar(50) NOT NULL COMMENT 'ປະເພດແຫຼ່ງຂໍ້ມູນ',
  `source_id` int NOT NULL COMMENT 'ID ຂອງແຫຼ່ງຂໍ້ມູນ',
  `source_number` varchar(100) DEFAULT NULL COMMENT 'ເລກທີ່ຂອງແຫຼ່ງຂໍ້ມູນ',
  `customer_id` int DEFAULT NULL COMMENT 'ID ລູກຄ້າ',
  `customer_name` varchar(255) DEFAULT NULL COMMENT 'ຊື່ລູກຄ້າ',
  `total_amount` decimal(15,2) DEFAULT '0.00' COMMENT 'ຍອດລວມ',
  `sale_date` date DEFAULT NULL COMMENT 'ວັນທີຂາຍ',
  `items_data` longtext COMMENT 'ຂໍ້ມູນລາຍການສິນຄ້າ (JSON)',
  `notes` text COMMENT 'ໝາຍເຫດ',
  `synced_by` int DEFAULT NULL COMMENT 'ຜູ້ທີ່ສັງເຄົ໌',
  `synced_at` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'ວັນທີທີ່ສັງເຄົ໌',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `asset_sync_log`
--

INSERT INTO `asset_sync_log` (`id`, `source_type`, `source_id`, `source_number`, `customer_id`, `customer_name`, `total_amount`, `sale_date`, `items_data`, `notes`, `synced_by`, `synced_at`, `created_at`) VALUES
(1, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 02:41:44', '2026-03-22 02:41:44'),
(2, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 02:44:54', '2026-03-22 02:44:54'),
(3, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 02:48:08', '2026-03-22 02:48:08'),
(4, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 02:57:45', '2026-03-22 02:57:45'),
(5, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 02:59:19', '2026-03-22 02:59:19'),
(6, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', NULL, '2026-03-22 03:46:16', '2026-03-22 03:46:16'),
(7, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 15:19:06', '2026-03-22 15:19:06'),
(8, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 15:31:50', '2026-03-22 15:31:50'),
(9, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 15:37:24', '2026-03-22 15:37:24'),
(10, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 16:28:02', '2026-03-22 16:28:02'),
(11, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 16:32:41', '2026-03-22 16:32:41'),
(12, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 17:01:22', '2026-03-22 17:01:22'),
(13, 'sales_order', 8, 'SO-2026-004', 1, 'ໂພສີກະ ສິດທິສານ', 15000000.00, '2026-03-20', '[{\"item_id\":4,\"item_code\":\"ITEM2025010001\",\"item_name\":\"\\u0ec2\\u0e99\\u0e94\\u0e9a\\u0eb8\\u0e81 Dell XPS 15\",\"quantity\":1,\"unit_price\":\"15000000.00\",\"total_price\":\"15000000.00\",\"asset_type\":\"inventory_sold\"}]', 'ສົ່ງຂໍ້ມູນການຂາຍອອກ SO-2026-004 ໄປຍັງລະບົບ Asset', 6, '2026-03-22 17:49:48', '2026-03-22 17:49:48');

-- --------------------------------------------------------

--
-- Table structure for table `barcodes`
--

CREATE TABLE `barcodes` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `barcode_number` varchar(100) NOT NULL,
  `barcode_type` varchar(50) DEFAULT 'CODE128',
  `format` varchar(50) DEFAULT 'CODE128',
  `width` int DEFAULT '2',
  `height` int DEFAULT '60',
  `printed` tinyint DEFAULT '0',
  `printed_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `company_id` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `barcodes`
--

INSERT INTO `barcodes` (`id`, `item_id`, `barcode_number`, `barcode_type`, `format`, `width`, `height`, `printed`, `printed_at`, `created_by`, `company_id`, `created_at`, `updated_at`) VALUES
(1, 4, 'BC010001067112', 'CODE128', 'CODE128', 2, 60, 1, '2026-04-02 05:14:30', 6, 1, '2026-03-21 04:07:50', '2026-04-02 05:14:30'),
(2, 6, 'BC010003087476', 'CODE128', 'CODE128', 2, 60, 1, '2026-04-02 05:14:30', 6, 1, '2026-03-21 04:08:10', '2026-04-02 05:14:30'),
(3, 5, 'BC010002105663', 'CODE128', 'CODE128', 2, 60, 1, '2026-04-02 05:14:30', 6, 1, '2026-03-21 04:08:28', '2026-04-02 05:14:30');

-- --------------------------------------------------------

--
-- Table structure for table `barcode_generator`
--

CREATE TABLE `barcode_generator` (
  `id` int NOT NULL,
  `barcode` varchar(100) NOT NULL,
  `barcode_type` enum('CODE128','EAN13','EAN8','UPC','QR','OTHER') NOT NULL,
  `reference_type` enum('item','stock','asset') NOT NULL,
  `reference_id` int NOT NULL,
  `generated_for` varchar(200) DEFAULT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `generated_by` int NOT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `print_count` int DEFAULT '0',
  `last_printed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `barcode_generator`
--

INSERT INTO `barcode_generator` (`id`, `barcode`, `barcode_type`, `reference_type`, `reference_id`, `generated_for`, `file_path`, `generated_by`, `generated_at`, `print_count`, `last_printed_at`) VALUES
(1, 'ITM-1-431084', 'CODE128', 'item', 1, 'IT-LAP-001 - ໂນດບຸກ Dell Inspiron', NULL, 2, '2026-02-27 03:04:32', 0, NULL),
(2, 'STK-1-6210', 'CODE128', 'stock', 1, 'IT-LAP-001 - Batch: BATCH-20260226', NULL, 2, '2026-02-27 03:04:40', 0, NULL),
(3, '101000005', 'CODE128', 'asset', 27, 'ASUS ROG Strix', NULL, 6, '2026-03-06 16:16:29', 0, NULL),
(4, 'AST003', 'CODE128', 'asset', 25, 'ໂຕະພະນັກງານ', NULL, 6, '2026-03-06 16:17:39', 0, NULL),
(5, 'AST002', 'CODE128', 'asset', 24, 'ເຄື່ອງພິມ', NULL, 6, '2026-03-06 16:25:05', 0, NULL),
(6, '108000026', 'CODE128', 'asset', 28, 'Monitor 21.5 ACER', NULL, 6, '2026-03-06 16:35:14', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `barcode_scans`
--

CREATE TABLE `barcode_scans` (
  `id` int NOT NULL,
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
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `branch_code` varchar(50) NOT NULL,
  `branch_name` varchar(255) NOT NULL,
  `company_id` int NOT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `status` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_attributes`
--

CREATE TABLE `category_attributes` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `attribute_name` varchar(100) NOT NULL,
  `attribute_type` enum('text','number','date','boolean','select') NOT NULL,
  `is_required` tinyint(1) DEFAULT '0',
  `options` text COMMENT 'ສຳລັບ type select',
  `sort_order` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_inheritance`
--

CREATE TABLE `category_inheritance` (
  `id` int NOT NULL,
  `category_id` int NOT NULL,
  `inherited_from_id` int NOT NULL,
  `attribute_name` varchar(100) DEFAULT NULL,
  `is_overridden` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int NOT NULL,
  `company_code` varchar(50) NOT NULL,
  `company_name` varchar(200) NOT NULL,
  `parent_company_id` int DEFAULT NULL,
  `address` text,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `company_code`, `company_name`, `parent_company_id`, `address`, `phone`, `email`, `tax_id`, `status`, `created_at`) VALUES
(1, 'COMP001', 'ບໍລິສັດ ແອວດີຊີ ຂາອອກ-ຂາເຂົ້າຈຳກັດ', NULL, 'ບ້ານຕານມີໄຊ ເມືອງ ໄຊທານີ ແຂວງ ນະຄອນຫລວງວຽງຈັນ', '021771725', 'info@ldc.la', '12345678', 1, '2026-02-25 15:05:07'),
(2, 'COMP002', 'ບໍລິສັດ ສະກາຍຄູ ຈຳກັດ', 1, 'ບ້ານສີສະຫວາດ, ເມືອງຈັນທະບູລີ, ນະຄອນຫຼວງວຽງຈັນ', '021771725', 'info@skycool.la', '1234567890123', 1, '2026-02-25 15:05:07'),
(3, 'COMP003', 'ບໍລິສັດ ຊີອາເຊີວິດ', 1, 'ບ້ານ ຄຳຮູງ ເມືອງ ໄຊທານີ ນະຄອນລວງວຽງຈັນ', '02012345678', 'info@crservice.la', '1234567890', 1, '2026-02-25 15:05:07'),
(4, 'COMP004', 'ບໍລິສັດ ແຊບ ເມວທູໂກ', 1, 'ບ້ານ ຈອມມະນີ ເມືອງ ຈັນທະບູລີ ນະຄອນຫລວງວຽງຈັນ', '021771725', 'info@zaapmealtogo.la', '1234567890123', 1, '2026-03-01 14:11:53'),
(6, 'COMP005', 'ໂຮງງານ ແຊບ ເບັກເກີລີ', 1, 'ບ້ານ ໂນນຄໍ້ ເມືອງ ໄຊເສດຖາ ນະຄອນລວງວຽງຈັນ', '02012345678', 'info@zaapbackery.la', '1234567890', 1, '2026-03-01 15:00:30');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `customer_code` varchar(50) NOT NULL,
  `company_id` int DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `department_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` text,
  `tax_id` varchar(50) DEFAULT NULL,
  `payment_terms` varchar(50) DEFAULT NULL,
  `credit_limit` decimal(15,2) DEFAULT '0.00',
  `status` tinyint DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `customer_code`, `company_id`, `branch_id`, `department_id`, `user_id`, `customer_name`, `contact_person`, `phone`, `email`, `address`, `tax_id`, `payment_terms`, `credit_limit`, `status`, `created_at`, `updated_at`, `created_by`) VALUES
(1, 'CUST001', 1, NULL, 6, 6, 'ໂພສີກະ ສິດທິສານ', 'ໂພສີກະ', '02055915969', 'it.mgr@ldc.la', NULL, '1234567890123', 'COD', 0.00, 1, '2026-03-20 06:54:30', NULL, 6);

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int NOT NULL,
  `department_code` varchar(50) NOT NULL,
  `department_name` varchar(200) NOT NULL,
  `company_id` int NOT NULL,
  `parent_department_id` int DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`id`, `department_code`, `department_name`, `company_id`, `parent_department_id`, `manager_id`, `status`, `created_at`) VALUES
(1, 'ADMIN', 'ຝ່າຍເຕັກໂນໂລຊີຂໍ້ມູນຂ່າວສານ', 1, NULL, 5, 1, '2026-02-25 15:05:14'),
(2, 'HR', 'ຝ່າຍຊັບພະຍາກອນມະນຸດ', 1, NULL, NULL, 1, '2026-02-25 15:05:14'),
(3, 'FIN', 'ຝ່າຍການເງິນ', 1, NULL, NULL, 1, '2026-02-25 15:05:14'),
(4, 'IT', 'ຝ່າຍເຕັກໂນໂລຊີ', 1, NULL, NULL, 1, '2026-02-25 15:05:14'),
(5, 'SALE', 'ຝ່າຍຂາຍ', 2, NULL, NULL, 1, '2026-02-25 15:05:14'),
(6, 'IT001', 'ຝ່າຍໄອທີ', 1, NULL, 6, 1, '2026-03-01 15:41:53');

-- --------------------------------------------------------

--
-- Table structure for table `depreciation_calculation_log`
--

CREATE TABLE `depreciation_calculation_log` (
  `id` int NOT NULL,
  `calculation_date` date NOT NULL,
  `asset_count` int NOT NULL,
  `total_depreciation` decimal(15,2) NOT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `error_message` text,
  `processed_by` int DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `depreciation_standard`
--

CREATE TABLE `depreciation_standard` (
  `id` int NOT NULL,
  `asset_category_id` int DEFAULT NULL COMMENT 'ສຳລັບໝວດໝູ່ສະເພາະ',
  `asset_type` varchar(100) DEFAULT NULL COMMENT 'ປະເພດຊັບສິນ',
  `useful_life_years` int NOT NULL COMMENT 'ອາຍຸການໃຊ້ງານ (ປີ)',
  `depreciation_method` enum('straight_line','declining_balance','sum_of_years','units_of_production') NOT NULL DEFAULT 'straight_line',
  `depreciation_rate` decimal(5,2) DEFAULT NULL COMMENT 'ອັດຕາເສື່ອມລາຄາ (%)',
  `salvage_value_percent` decimal(5,2) DEFAULT '0.00' COMMENT 'ມູນຄ່າຊາກ (% ຂອງມູນຄ່າຊື້)',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `description` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `depreciation_standard`
--

INSERT INTO `depreciation_standard` (`id`, `asset_category_id`, `asset_type`, `useful_life_years`, `depreciation_method`, `depreciation_rate`, `salvage_value_percent`, `is_active`, `effective_from`, `effective_to`, `description`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, NULL, 'ຄອມພິວເຕີ ແລະ ອຸປະກອນ IT', 3, 'straight_line', 33.33, 10.00, 1, '2024-01-01', NULL, 'ຄອມພິວເຕີ, ໂນດບຸກ, ເຄື່ອງພິມ, ອຸປະກອນ IT ທົ່ວໄປ', NULL, '2026-03-27 11:48:13', NULL, NULL),
(2, NULL, 'ເຄື່ອງຈັກ ແລະ ອຸປະກອນການຜະລິດ', 10, 'straight_line', 10.00, 20.00, 1, '2024-01-01', NULL, 'ເຄື່ອງຈັກ, ອຸປະກອນການຜະລິດ', NULL, '2026-03-27 11:48:13', NULL, NULL),
(3, NULL, 'ຍານພາຫະນະ', 5, 'declining_balance', 40.00, 15.00, 1, '2024-01-01', NULL, 'ລົດ, ລົດຈັກ, ຍານພາຫະນະຕ່າງໆ', NULL, '2026-03-27 11:48:13', NULL, NULL),
(4, NULL, 'ເຟີນີເຈີ ແລະ ເຄື່ອງເຟີນີເຈີ', 8, 'straight_line', 12.50, 10.00, 1, '2024-01-01', NULL, 'ໂຕະ, ຕັ່ງ, ຕູ້, ຊັ້ນວາງ', NULL, '2026-03-27 11:48:13', NULL, NULL),
(5, NULL, 'ອາຄານ ແລະ ສິ່ງກໍ່ສ້າງ', 30, 'straight_line', 3.33, 0.00, 1, '2024-01-01', NULL, 'ອາຄານ, ໂຮງງານ, ສິ່ງກໍ່ສ້າງຖາວອນ', NULL, '2026-03-27 11:48:13', NULL, NULL),
(6, NULL, 'ຊອບແວ ແລະ ລິຂະສິດ', 3, 'straight_line', 33.33, 0.00, 1, '2024-01-01', NULL, 'ຊອບແວ, ລິຂະສິດ, ສິດທິບັດ', NULL, '2026-03-27 11:48:13', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int NOT NULL,
  `currency_code` varchar(3) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rate` decimal(15,6) NOT NULL COMMENT 'ອັດຕາແລກປ່ຽນຕໍ່ 1 LAK',
  `base_currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT 'LAK',
  `rate_date` date NOT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exchange_rates`
--

INSERT INTO `exchange_rates` (`id`, `currency_code`, `rate`, `base_currency`, `rate_date`, `source`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'THB', 700.000000, 'LAK', '2026-03-26', 'manual', NULL, 6, '2026-03-26 08:18:05', '2026-03-26 11:45:19'),
(2, 'USD', 21000.000000, 'LAK', '2026-03-26', 'manual', NULL, 6, '2026-03-26 08:18:05', '2026-03-26 11:45:19'),
(3, 'CNY', 2900.000000, 'LAK', '2026-03-26', 'manual', NULL, 6, '2026-03-26 08:18:05', '2026-03-26 11:45:19');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_items`
--

CREATE TABLE `inventory_items` (
  `id` int NOT NULL,
  `item_code` varchar(50) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  `item_name_en` varchar(255) DEFAULT NULL,
  `category_id` int DEFAULT NULL,
  `description` text,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `specification` text,
  `purchase_price` decimal(15,2) DEFAULT '0.00',
  `selling_price` decimal(15,2) DEFAULT '0.00',
  `supplier_id` int DEFAULT NULL,
  `reorder_point` int DEFAULT NULL,
  `minimum_stock` int DEFAULT NULL,
  `maximum_stock` int DEFAULT NULL,
  `barcode_type` varchar(20) DEFAULT 'code128',
  `barcode_image_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_items`
--

INSERT INTO `inventory_items` (`id`, `item_code`, `barcode`, `item_name`, `item_name_en`, `category_id`, `description`, `brand`, `model`, `specification`, `purchase_price`, `selling_price`, `supplier_id`, `reorder_point`, `minimum_stock`, `maximum_stock`, `barcode_type`, `barcode_image_path`, `is_active`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(4, 'ITEM2025010001', 'BC885123456001', 'ໂນດບຸກ Dell XPS 15', 'Dell XPS 15 Laptop', 8, 'ໂນດບຸກປະສິດທິພາບສູງສຳລັບງານກຣາບຟິກ', 'Dell', 'XPS 15', 'Intel Core i9, 32GB RAM, 1TB SSD, NVIDIA RTX 4060', 25000000.00, 28000000.00, 1, NULL, 2, 20, 'code128', NULL, 1, '2026-03-13 13:08:08', '2026-03-23 03:44:14', 6, 6),
(5, 'ITEM2025010002', 'BC885123456002', 'ເຄື່ອງພິມ HP LaserJet', 'HP LaserJet Printer', 10, 'ເຄື່ອງພິມເລເຊີ ຂາວ-ດຳ ຄວາມໄວສູງ', 'HP', 'LaserJet Pro M404dn', 'Print speed: 40ppm, Duplex printing, Ethernet, USB', 3500000.00, 4200000.00, 1, NULL, 1, 10, 'code128', NULL, 1, '2026-03-13 13:08:08', '2026-03-23 03:43:33', 6, 6),
(6, 'ITEM2025010003', 'BC885123456003', 'ໜ້າຈໍຄອມ Samsung 27\"', 'Samsung 27\" Monitor', 28, 'ຈໍຄອມພິວເຕີ 4K UHD ສຳລັບງານອອກແບບ', 'Samsung', 'UJ59 27\"', '4K UHD (3840x2160), IPS Panel, HDMI, DisplayPort', 4500000.00, 5200000.00, 2, NULL, 1, 8, 'code128', NULL, 1, '2026-03-13 13:08:08', '2026-03-23 03:44:01', 6, 6);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stock`
--

CREATE TABLE `inventory_stock` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `current_quantity` int DEFAULT '0',
  `reserved_quantity` int DEFAULT '0',
  `available_quantity` int DEFAULT '0',
  `shelf_location` varchar(100) DEFAULT NULL,
  `last_count_date` datetime DEFAULT NULL,
  `last_count_quantity` int DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `inventory_stock`
--

INSERT INTO `inventory_stock` (`id`, `item_id`, `warehouse_id`, `current_quantity`, `reserved_quantity`, `available_quantity`, `shelf_location`, `last_count_date`, `last_count_quantity`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 4, 1, 5, 0, 5, NULL, '2026-03-26 03:51:22', 5, '2026-03-13 17:01:35', '2026-04-01 14:15:26', NULL, 6),
(2, 5, 1, 5, 0, 5, NULL, '2026-03-26 03:51:22', 5, '2026-03-13 17:01:35', '2026-03-26 03:51:22', NULL, 6),
(4, 6, 1, 3, 0, 3, NULL, '2026-03-26 06:20:14', 3, '2026-03-14 03:58:31', '2026-03-26 06:20:14', 6, 6);

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stocks`
--

CREATE TABLE `inventory_stocks` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `reserved_quantity` decimal(15,2) DEFAULT '0.00',
  `location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `inventory_stocks`
--

INSERT INTO `inventory_stocks` (`id`, `item_id`, `warehouse_id`, `quantity`, `reserved_quantity`, `location`, `batch_number`, `expiry_date`, `status`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 4, 1, 6.00, 0.00, NULL, NULL, NULL, 'active', 6, NULL, '2026-03-26 02:36:00', '2026-03-26 02:36:00'),
(2, 5, 1, 5.00, 0.00, NULL, NULL, NULL, 'active', 6, NULL, '2026-03-26 02:36:00', '2026-03-26 02:36:00'),
(3, 6, 1, 7.00, 0.00, NULL, NULL, NULL, 'active', 6, NULL, '2026-03-26 02:36:00', '2026-03-26 02:36:00');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_stock_counts`
--

CREATE TABLE `inventory_stock_counts` (
  `id` int NOT NULL,
  `item_id` int NOT NULL,
  `count_date` datetime NOT NULL,
  `system_quantity` int NOT NULL,
  `actual_quantity` int NOT NULL,
  `difference` int NOT NULL,
  `count_by` int DEFAULT NULL,
  `notes` text,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

CREATE TABLE `locations` (
  `id` int NOT NULL,
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
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `location_code`, `location_name`, `location_type`, `parent_location_id`, `company_id`, `address`, `capacity`, `current_usage`, `manager_id`, `phone`, `is_active`, `notes`, `created_at`) VALUES
(1, 'LOC001', 'ອາຄານ A', 'building', NULL, 1, NULL, NULL, 0, NULL, NULL, 1, NULL, '2026-02-25 15:09:11'),
(2, 'LOC002', 'ອາຄານ B', 'building', NULL, 1, NULL, NULL, 0, NULL, NULL, 1, NULL, '2026-02-25 15:09:11'),
(3, 'LOC003', 'ຊັ້ນ 2 ອາຄານ A', 'floor', NULL, 1, NULL, NULL, 0, NULL, NULL, 1, NULL, '2026-02-25 15:09:11'),
(4, 'LOC004', 'ຫ້ອງ IT', 'room', NULL, 1, NULL, NULL, 0, NULL, NULL, 1, NULL, '2026-02-25 15:09:11');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `login_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `success` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `ip_address`, `user_agent`, `login_time`, `success`) VALUES
(1, 6, '172.18.0.1', 'curl/8.5.0', '2026-02-28 14:10:28', 1),
(2, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 14:31:10', 1),
(3, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 15:23:29', 1),
(4, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 15:25:14', 1),
(5, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 15:27:25', 1),
(6, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:09:03', 1),
(7, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 16:10:49', 1),
(8, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:16:48', 1),
(9, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:19:37', 1),
(10, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:30:56', 1),
(11, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:51:20', 1),
(12, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-02-28 16:57:02', 1),
(13, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-28 23:55:44', 1),
(14, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 00:09:19', 1),
(15, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-01 00:11:26', 1),
(16, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 00:16:41', 1),
(17, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 00:24:18', 1),
(18, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 01:33:32', 1),
(19, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 01:40:19', 1),
(20, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 03:01:07', 1),
(21, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 03:49:04', 1),
(22, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 03:49:26', 1),
(23, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-01 03:51:04', 1),
(24, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-01 03:53:31', 1),
(25, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 10:00:31', 1),
(26, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 11:03:35', 1),
(27, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 12:07:16', 1),
(28, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 12:36:28', 1),
(29, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 13:58:51', 1),
(30, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 14:56:52', 1),
(31, 4, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-01 15:08:14', 1),
(32, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 15:10:28', 1),
(33, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-01 15:33:47', 1),
(34, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-01 16:41:58', 1),
(35, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 07:11:20', 1),
(36, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-02 08:15:56', 1),
(37, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 09:06:54', 1),
(38, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 10:17:56', 1),
(39, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-02 12:47:42', 1),
(40, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 00:18:05', 1),
(41, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 10:27:58', 1),
(42, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 13:28:14', 1),
(43, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 14:29:10', 1),
(44, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-03 15:39:18', 1),
(45, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 09:13:01', 1),
(46, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 09:38:09', 1),
(47, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 09:38:35', 1),
(48, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-04 09:39:30', 1),
(49, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 09:52:31', 1),
(50, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-04 13:26:53', 1),
(51, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-04 13:42:44', 1),
(52, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 13:53:56', 1),
(53, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 15:14:15', 1),
(54, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 15:47:12', 1),
(55, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-04 15:55:56', 1),
(56, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 16:37:27', 1),
(57, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-04 16:48:44', 1),
(58, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 08:49:19', 1),
(59, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-06 09:37:24', 1),
(60, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-06 15:59:20', 1),
(61, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-06 16:01:44', 1),
(62, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-06 17:11:28', 1),
(63, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-06 17:11:33', 1),
(64, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-07 10:12:33', 1),
(65, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 07:49:34', 1),
(66, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-10 08:26:42', 1),
(67, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 09:28:08', 1),
(68, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 09:28:50', 1),
(69, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 09:40:35', 1),
(70, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 09:51:42', 1),
(71, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 09:54:49', 1),
(72, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 10:08:07', 1),
(73, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 10:09:43', 1),
(74, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 10:15:12', 1),
(75, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 10:28:10', 1),
(76, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Mobile Safari/537.36', '2026-03-10 13:50:50', 1),
(77, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-10 14:54:22', 1),
(78, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-11 06:30:06', 1),
(79, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-11 06:40:50', 1),
(80, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 08:21:51', 1),
(81, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-13 12:29:59', 1),
(82, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 15:20:31', 1),
(83, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-13 16:16:08', 1),
(84, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 01:20:15', 1),
(85, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 03:50:24', 1),
(86, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 06:14:40', 1),
(87, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-14 09:21:03', 1),
(88, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 09:50:33', 1),
(89, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 09:54:52', 1),
(90, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 10:06:26', 1),
(91, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 10:16:30', 1),
(92, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 13:21:49', 1),
(93, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 13:43:13', 1),
(94, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 15:19:27', 1),
(95, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 15:23:03', 1),
(96, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 15:34:45', 1),
(97, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-14 15:37:01', 1),
(98, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-14 17:00:00', 1),
(99, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 00:48:22', 1),
(100, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 00:54:01', 1),
(101, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 01:53:05', 1),
(102, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 02:55:45', 1),
(103, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 03:06:19', 1),
(104, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 03:35:28', 1),
(105, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 03:38:19', 1),
(106, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 04:17:54', 1),
(107, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 04:18:57', 1),
(108, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 04:34:49', 1),
(109, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 04:35:47', 1),
(110, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 09:16:34', 1),
(111, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 11:32:53', 1),
(112, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 12:35:53', 1),
(113, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-15 13:09:28', 1),
(114, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 13:36:44', 1),
(115, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-15 14:41:20', 1),
(116, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 12:37:34', 1),
(117, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 13:24:16', 1),
(118, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 14:30:18', 1),
(119, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-18 15:30:40', 1),
(120, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-18 15:48:47', 1),
(121, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-18 15:51:55', 1),
(122, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:34:50', 1),
(123, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:35:04', 1),
(124, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:35:51', 1),
(125, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:36:59', 1),
(126, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:50:07', 1),
(127, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 01:52:36', 1),
(128, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:01:56', 1),
(129, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:02:42', 1),
(130, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:04:03', 1),
(131, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:21:17', 1),
(132, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:23:38', 1),
(133, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-19 02:24:04', 1),
(134, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-19 02:29:49', 1),
(135, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:42:14', 1),
(136, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 02:47:03', 1),
(137, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 05:27:58', 1),
(138, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 05:57:21', 1),
(139, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 11:57:18', 1),
(140, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-19 12:02:26', 1),
(141, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-19 13:16:11', 1),
(142, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 06:53:13', 1),
(143, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 07:16:39', 1),
(144, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 07:38:08', 1),
(145, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 07:42:16', 1),
(146, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 09:46:08', 1),
(147, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 10:17:59', 1),
(148, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 10:23:48', 1),
(149, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 11:01:21', 1),
(150, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 11:43:42', 1),
(151, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 11:53:26', 1),
(152, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 12:06:26', 1),
(153, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 15:09:30', 1),
(154, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 16:15:51', 1),
(155, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-20 16:22:53', 1),
(156, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-20 16:23:55', 1),
(157, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 00:59:27', 1),
(158, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 02:21:26', 1),
(159, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 02:30:34', 1),
(160, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-21 03:03:39', 1),
(161, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 03:31:15', 1),
(162, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 04:43:59', 1),
(163, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 11:06:30', 1),
(164, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-21 12:23:19', 1),
(165, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 00:19:37', 1),
(166, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 02:05:53', 1),
(167, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 03:45:50', 1),
(168, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 04:16:03', 1),
(169, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 14:49:55', 1),
(170, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-03-22 15:18:47', 1),
(171, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 16:27:47', 1),
(172, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-22 17:49:25', 1),
(173, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-23 03:42:43', 1),
(174, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 04:13:39', 1),
(175, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 05:31:11', 1),
(176, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-24 06:40:10', 1),
(177, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 09:35:54', 1),
(178, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 10:38:49', 1),
(179, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 14:04:00', 1),
(180, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 15:20:33', 1),
(181, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-25 16:28:40', 1),
(182, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-26 01:23:05', 1),
(183, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 01:32:05', 1),
(184, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 02:36:49', 1),
(185, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 03:51:06', 1),
(186, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-26 04:33:34', 1),
(187, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 04:52:01', 1),
(188, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-26 05:59:06', 1),
(189, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 06:18:54', 1),
(190, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 08:12:08', 1),
(191, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 11:44:35', 1),
(192, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-26 12:26:53', 1),
(193, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 13:06:15', 1),
(194, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 14:11:14', 1),
(195, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 15:15:09', 1),
(196, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-26 23:28:13', 1),
(197, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 00:50:57', 1),
(198, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 06:14:38', 1),
(199, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 08:40:11', 1),
(200, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 09:59:40', 1),
(201, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-27 11:52:54', 1),
(202, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 12:50:55', 1),
(203, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-27 12:52:47', 1),
(204, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 13:52:34', 1),
(205, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-27 14:00:54', 1),
(206, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:02:40', 1),
(207, 6, '172.18.0.1', 'PostmanRuntime/7.49.1', '2026-03-27 14:10:06', 1),
(208, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:23:21', 1),
(209, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:23:52', 1),
(210, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:24:41', 1),
(211, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:29:25', 1),
(212, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:32:04', 1),
(213, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:32:26', 1),
(214, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:34:31', 1),
(215, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:37:15', 1),
(216, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:37:49', 1),
(217, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:41:45', 1),
(218, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:42:44', 1),
(219, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-27 14:44:23', 1),
(220, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:49:20', 1),
(221, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 14:50:05', 1),
(222, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:02:35', 1),
(223, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:06:42', 1),
(224, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:12:58', 1),
(225, 6, '172.18.0.1', 'curl/8.5.0', '2026-03-27 15:20:49', 1),
(226, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-27 15:22:36', 1),
(227, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 07:29:21', 1),
(228, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 09:16:12', 1),
(229, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 13:08:17', 1),
(230, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-01 13:08:47', 1),
(231, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-01 13:19:10', 1),
(232, 8, '172.18.0.1', 'curl/8.5.0', '2026-04-01 13:27:07', 1),
(233, 6, '172.18.0.1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-01 13:31:14', 1),
(234, 8, '172.18.0.1', 'curl/8.5.0', '2026-04-01 14:12:28', 1),
(235, 6, '172.18.0.1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-02 01:26:45', 1),
(236, 2, '172.18.0.1', 'curl/8.5.0', '2026-04-02 02:11:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int NOT NULL,
  `po_number` varchar(50) NOT NULL COMMENT 'ເລກທີ PO',
  `supplier_id` int NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `tax` decimal(15,2) DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(3) DEFAULT 'LAK',
  `exchange_rate` decimal(15,6) DEFAULT '1.000000',
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `payment_date` date DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('draft','pending','approved','ordered','shipped','received','cancelled') NOT NULL DEFAULT 'draft',
  `invoice_number` varchar(100) DEFAULT NULL,
  `invoice_file_path` varchar(500) DEFAULT NULL,
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_orders`
--

INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `order_date`, `expected_delivery`, `delivery_date`, `subtotal`, `discount`, `tax`, `total_amount`, `currency_code`, `exchange_rate`, `payment_status`, `payment_date`, `payment_method`, `status`, `invoice_number`, `invoice_file_path`, `notes`, `created_by`, `created_at`, `updated_at`, `approved_by`, `approved_at`) VALUES
(1, 'PU-2026-003', 1, '2026-02-26', NULL, NULL, 14000000.00, 0.00, 0.00, 14000000.00, 'LAK', 1.000000, 'paid', '2026-03-18', 'cash', 'received', NULL, NULL, NULL, 2, '2026-02-26 07:38:26', '2026-03-24 06:13:48', NULL, NULL),
(2, 'PU-2026-004', 1, '2026-02-26', NULL, NULL, 14000000.00, 0.00, 0.00, 14000000.00, 'LAK', 1.000000, 'paid', '2026-03-18', 'cash', 'received', NULL, NULL, NULL, 2, '2026-02-26 07:54:08', '2026-03-24 06:13:48', NULL, NULL),
(3, 'PU-2026-005', 1, '2026-02-26', NULL, NULL, 28000000.00, 0.00, 0.00, 28000000.00, 'LAK', 1.000000, 'paid', '2026-03-18', 'cash', 'received', NULL, NULL, NULL, 2, '2026-02-26 07:56:49', '2026-03-24 06:13:48', NULL, NULL),
(6, 'PU-2026-006', 2, '2026-03-01', '2026-03-17', '2026-03-18', 15000000.00, 0.00, 0.00, 15000000.00, 'LAK', 1.000000, 'paid', '2026-03-18', 'cash', 'received', NULL, NULL, NULL, 6, '2026-03-15 12:27:44', '2026-03-24 06:13:48', 6, '2026-03-18 14:35:58'),
(7, 'PU-2026-007', 2, '2026-03-08', '2026-03-17', '2026-03-18', 7000000.00, 0.00, 0.00, 7000000.00, 'LAK', 1.000000, 'paid', '2026-03-18', 'cash', 'received', NULL, NULL, NULL, 6, '2026-03-18 15:04:15', '2026-03-24 06:13:48', 6, '2026-03-18 15:04:28'),
(8, 'PU-2026-001', 2, '2026-03-26', '2026-03-26', NULL, 3533500.00, 0.00, 0.00, 3533500.00, 'LAK', 1.000000, 'paid', NULL, NULL, 'received', NULL, NULL, NULL, 6, '2026-03-26 13:35:59', '2026-03-26 14:04:51', 6, '2026-03-26 13:56:47'),
(9, 'PU-2026-008', 1, '2026-03-25', '2026-03-28', '2026-03-26', 3500000.00, 0.00, 0.00, 3500000.00, 'LAK', 1.000000, 'unpaid', NULL, NULL, 'received', NULL, NULL, NULL, 6, '2026-03-26 14:52:28', '2026-03-26 15:33:48', 6, '2026-03-26 14:52:50');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_details`
--

CREATE TABLE `purchase_order_details` (
  `id` int NOT NULL,
  `po_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `received_quantity` int DEFAULT '0',
  `updated_at` datetime DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL,
  `warranty_period` int DEFAULT NULL COMMENT 'ອາຍຸການຮັບປະກັນ (ເດືອນ)',
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `purchase_order_details`
--

INSERT INTO `purchase_order_details` (`id`, `po_id`, `item_id`, `quantity`, `received_quantity`, `updated_at`, `received_at`, `unit_price`, `discount`, `total_price`, `warranty_period`, `notes`, `created_at`) VALUES
(2, 2, 1, 5, 5, NULL, NULL, 2800000.00, 0.00, 14000000.00, 24, NULL, '2026-03-26 15:30:41'),
(3, 3, 1, 10, 10, NULL, NULL, 2800000.00, 0.00, 28000000.00, 24, NULL, '2026-03-26 15:30:41'),
(12, 6, 4, 1, 1, NULL, NULL, 15000000.00, 0.00, 15000000.00, NULL, NULL, '2026-03-26 15:30:41'),
(13, 7, 5, 2, 2, NULL, NULL, 3500000.00, 0.00, 7000000.00, NULL, '', '2026-03-26 15:30:41'),
(14, 8, 5, 1, 0, NULL, NULL, 3500000.00, 0.00, 3500000.00, NULL, NULL, '2026-03-26 15:30:41'),
(15, 8, 6, 1, 0, NULL, NULL, 3500.00, 0.00, 3500.00, NULL, NULL, '2026-03-26 15:30:41'),
(16, 8, 4, 2, 0, NULL, NULL, 15000.00, 0.00, 30000.00, NULL, NULL, '2026-03-26 15:30:41'),
(17, 9, 6, 1, 1, NULL, NULL, 3500000.00, 0.00, 3500000.00, NULL, NULL, '2026-03-26 15:30:41');

-- --------------------------------------------------------

--
-- Table structure for table `sales_orders`
--

CREATE TABLE `sales_orders` (
  `id` int NOT NULL,
  `so_number` varchar(50) NOT NULL,
  `customer_id` int NOT NULL,
  `sale_date` date NOT NULL,
  `branch_id` int DEFAULT NULL,
  `company_id` int NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) DEFAULT '0.00',
  `tax` decimal(15,2) DEFAULT '0.00',
  `total_amount` decimal(15,2) NOT NULL,
  `payment_status` enum('unpaid','partial','paid') DEFAULT 'unpaid',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `status` enum('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `synced_to_asset` tinyint(1) DEFAULT '0' COMMENT 'ສົ່ງຂໍ້ມູນໄປ Asset ແລ້ວຫຼືຍັງ',
  `synced_at` datetime DEFAULT NULL COMMENT 'ວັນທີທີ່ສົ່ງຂໍ້ມູນໄປ Asset'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales_orders`
--

INSERT INTO `sales_orders` (`id`, `so_number`, `customer_id`, `sale_date`, `branch_id`, `company_id`, `subtotal`, `discount`, `tax`, `total_amount`, `payment_status`, `payment_method`, `payment_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`, `approved_by`, `approved_at`, `synced_to_asset`, `synced_at`) VALUES
(8, 'SA-2026-004', 1, '2026-03-20', NULL, 1, 15000000.00, 0.00, 0.00, 15000000.00, 'paid', 'cash', NULL, 'confirmed', NULL, 6, '2026-03-20 07:42:40', '2026-03-24 06:13:44', 6, '2026-03-20 10:28:36', 1, '2026-03-23 00:49:48'),
(10, 'SA-2026-002', 1, '2026-03-20', NULL, 1, 4000000.00, 0.00, 0.00, 4000000.00, 'paid', 'cash', NULL, 'cancelled', NULL, 6, '2026-03-20 11:04:51', '2026-03-24 06:13:44', NULL, NULL, 0, NULL),
(12, 'SA-2026-003', 1, '2026-03-20', NULL, 1, 4000000.00, 0.00, 0.00, 4000000.00, 'paid', 'cash', NULL, 'cancelled', NULL, 6, '2026-03-20 11:22:18', '2026-03-24 06:13:44', NULL, NULL, 0, NULL),
(13, 'SA-2026-005', 1, '2026-03-20', NULL, 1, 4500000.00, 0.00, 0.00, 4500000.00, 'paid', 'cash', NULL, 'cancelled', NULL, 6, '2026-03-20 11:39:56', '2026-03-24 06:13:44', NULL, NULL, 0, NULL),
(14, 'SA-2026-006', 1, '2026-03-20', NULL, 1, 15000000.00, 0.00, 0.00, 15000000.00, 'paid', 'cash', NULL, 'cancelled', NULL, 6, '2026-03-20 12:00:36', '2026-03-24 06:13:44', 6, '2026-03-20 12:06:36', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `sales_order_details`
--

CREATE TABLE `sales_order_details` (
  `id` int NOT NULL,
  `so_id` int NOT NULL,
  `item_id` int NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `discount` decimal(5,2) DEFAULT '0.00',
  `total_price` decimal(15,2) NOT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `sales_order_details`
--

INSERT INTO `sales_order_details` (`id`, `so_id`, `item_id`, `quantity`, `unit_price`, `discount`, `total_price`, `notes`, `created_at`) VALUES
(8, 8, 4, 1, 15000000.00, 0.00, 15000000.00, NULL, '2026-03-20 07:42:40'),
(10, 10, 5, 1, 4000000.00, 0.00, 4000000.00, NULL, '2026-03-20 11:04:51'),
(12, 12, 5, 1, 4000000.00, 0.00, 4000000.00, NULL, '2026-03-20 11:22:18'),
(13, 13, 5, 1, 4500000.00, 0.00, 4500000.00, NULL, '2026-03-20 11:39:56'),
(14, 14, 4, 1, 15000000.00, 0.00, 15000000.00, NULL, '2026-03-20 12:00:36');

-- --------------------------------------------------------

--
-- Table structure for table `stock_adjustments`
--

CREATE TABLE `stock_adjustments` (
  `id` int NOT NULL,
  `adjustment_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `adjustment_type` enum('increase','decrease') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_detail` text COLLATE utf8mb4_unicode_ci,
  `item_id` int NOT NULL,
  `warehouse_id` int DEFAULT NULL,
  `adjusted_quantity` decimal(15,2) NOT NULL DEFAULT '0.00',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('draft','approved','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `approved_by` int DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_adjustments`
--

INSERT INTO `stock_adjustments` (`id`, `adjustment_code`, `adjustment_type`, `reason`, `reason_detail`, `item_id`, `warehouse_id`, `adjusted_quantity`, `notes`, `status`, `approved_by`, `approved_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'ADJ-2026030001', 'decrease', 'damage', NULL, 6, 1, 1.00, NULL, 'approved', 6, '2026-03-26 05:26:11', 6, '2026-03-26 05:26:11', '2026-03-26 06:20:49');

-- --------------------------------------------------------

--
-- Table structure for table `stock_count_details`
--

CREATE TABLE `stock_count_details` (
  `id` int NOT NULL,
  `session_id` int NOT NULL,
  `item_id` int NOT NULL,
  `expected_quantity` decimal(15,2) DEFAULT '0.00',
  `counted_quantity` decimal(15,2) DEFAULT '0.00',
  `variance` decimal(15,2) DEFAULT '0.00',
  `variance_percent` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','counted','adjusted','verified') COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `counted_by` int DEFAULT NULL,
  `counted_at` datetime DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `adjusted_by` int DEFAULT NULL,
  `adjusted_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_count_details`
--

INSERT INTO `stock_count_details` (`id`, `session_id`, `item_id`, `expected_quantity`, `counted_quantity`, `variance`, `variance_percent`, `status`, `counted_by`, `counted_at`, `verified_by`, `verified_at`, `adjusted_by`, `adjusted_at`, `notes`) VALUES
(1, 2, 4, 20.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 5, 10.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 2, 6, 8.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 3, 4, 20.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 3, 5, 10.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 3, 6, 8.00, 0.00, 0.00, 0.00, 'pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 12, 6, 5.00, 5.00, 0.00, 0.00, 'counted', 6, '2026-03-26 03:09:49', NULL, NULL, NULL, NULL, NULL),
(23, 12, 5, 9.00, 9.00, 0.00, 0.00, 'counted', 6, '2026-03-26 03:09:41', NULL, NULL, NULL, NULL, NULL),
(24, 12, 4, 10.00, 10.00, 0.00, 0.00, 'counted', 6, '2026-03-26 03:09:21', NULL, NULL, NULL, NULL, NULL),
(25, 13, 6, 5.00, 4.00, -1.00, -20.00, 'counted', 6, '2026-03-26 03:22:27', NULL, NULL, NULL, NULL, NULL),
(26, 13, 5, 9.00, 5.00, -4.00, -44.44, 'counted', 6, '2026-03-26 03:22:16', NULL, NULL, NULL, NULL, NULL),
(27, 13, 4, 10.00, 5.00, -5.00, -50.00, 'counted', 6, '2026-03-26 03:22:19', NULL, NULL, NULL, NULL, NULL),
(28, 14, 6, 4.00, 3.00, -1.00, -25.00, 'counted', 6, '2026-03-26 04:42:29', NULL, NULL, NULL, NULL, NULL),
(29, 14, 5, 5.00, 4.00, -1.00, -20.00, 'counted', 6, '2026-03-26 04:41:12', NULL, NULL, NULL, NULL, NULL),
(30, 14, 4, 5.00, 4.00, -1.00, -20.00, 'counted', 6, '2026-03-26 04:41:15', NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_count_sessions`
--

CREATE TABLE `stock_count_sessions` (
  `id` int NOT NULL,
  `session_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `session_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_type` enum('full','partial','cycle','random') COLLATE utf8mb4_unicode_ci DEFAULT 'full',
  `status` enum('draft','in_progress','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `warehouse_id` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_by` int DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_count_sessions`
--

INSERT INTO `stock_count_sessions` (`id`, `session_code`, `session_name`, `count_type`, `status`, `start_date`, `end_date`, `warehouse_id`, `created_by`, `created_at`, `updated_at`, `completed_by`, `completed_at`, `notes`) VALUES
(1, 'STC-2026030001', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 3', 'full', 'completed', '2026-03-25 10:31:54', '2026-03-25 10:40:05', 1, 6, '2026-03-25 10:01:18', '2026-03-25 10:40:05', 6, '2026-03-25 10:40:05', NULL),
(2, 'STC-2026030002', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 2', 'partial', 'completed', '2026-03-25 17:43:10', '2026-03-25 14:10:22', 1, 6, '2026-03-25 10:43:10', '2026-03-25 14:10:22', 6, '2026-03-25 14:10:22', NULL),
(3, 'STC-2026030003', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 3 ຮອບທີ 2', 'full', 'completed', '2026-03-25 00:00:00', '2026-03-25 14:21:29', 1, 6, '2026-03-25 14:16:03', '2026-03-25 14:21:29', 6, '2026-03-25 14:21:29', NULL),
(12, 'STC-2026030004', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 3 ຮອບທີ 3', 'full', 'completed', '2026-03-26 03:08:55', '2026-03-26 03:09:52', 1, 6, '2026-03-26 03:08:45', '2026-03-26 03:09:52', 6, '2026-03-26 03:09:52', NULL),
(13, 'STC-2026030005', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 3 ຮອບທີ 4', 'full', 'completed', '2026-03-26 03:21:46', '2026-03-26 03:51:22', 1, 6, '2026-03-26 03:21:40', '2026-03-26 03:51:22', 6, '2026-03-26 03:51:22', NULL),
(14, 'STC-2026030006', 'ນັບສະຕ໋ອກປະຊຳເດືອນ 3 ຮອບທີ 5', 'full', 'completed', '2026-03-26 04:40:07', '2026-03-26 04:50:18', 1, 6, '2026-03-26 04:39:58', '2026-03-26 04:50:18', 6, '2026-03-26 04:50:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int NOT NULL,
  `stock_id` int NOT NULL,
  `movement_type` enum('purchase_in','sale_out','transfer','adjustment','damage','return','loan','loan_return') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL COMMENT 'po, so, adjustment',
  `reference_id` int DEFAULT NULL,
  `quantity_before` int NOT NULL,
  `quantity_change` int NOT NULL,
  `quantity_after` int NOT NULL,
  `unit_price` decimal(15,2) DEFAULT NULL,
  `total_value` decimal(15,2) DEFAULT NULL,
  `movement_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  `created_by` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `stock_id`, `movement_type`, `reference_type`, `reference_id`, `quantity_before`, `quantity_change`, `quantity_after`, `unit_price`, `total_value`, `movement_date`, `notes`, `created_by`) VALUES
(1, 1, 'loan', 'loan', NULL, 1, 2, 3, NULL, NULL, '2026-04-01 14:02:37', 'Test loan for history', 8),
(2, 1, 'loan_return', 'return', NULL, 3, 1, 4, NULL, NULL, '2026-04-01 14:12:37', 'Test return for history', 8),
(3, 1, 'loan_return', 'return', NULL, 4, 1, 5, NULL, NULL, '2026-04-01 14:15:26', 'Returned from loan module', 6);

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int NOT NULL,
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
  `created_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `supplier_code`, `supplier_name`, `contact_person`, `phone`, `email`, `address`, `tax_id`, `payment_terms`, `status`, `created_at`, `created_by`, `updated_at`, `updated_by`) VALUES
(1, 'SUP001', 'ບໍລິສັທ ຜູ້ສະໜອງ 1', 'ສົມຊາຍ', '02012345678', 'supplier1@company.com', NULL, NULL, NULL, 1, '2026-02-25 15:08:52', NULL, '2026-03-10 15:03:14', 6),
(2, 'SUP002', 'ບໍລິສັທ ຜູ້ສະໜອງ 2', 'ສົມບູນ', '02087654321', 'supplier2@company.com', NULL, NULL, NULL, 1, '2026-02-25 15:08:52', NULL, '2026-03-10 15:03:18', 6),
(3, 'SUP003', 'DICT', 'ທົດສອບ1', '02055915969', 'test@gmail.com', 'ບ້ານ ສີດຳດວນ ເມືອງ ຈັນທະບູລີ ແຂວງສະຫວັນນະເຂດ', '112233445566', 'net_30', 1, '2026-03-11 06:45:08', NULL, '2026-03-11 06:59:17', 6);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `employee_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department_id` int NOT NULL,
  `company_id` int DEFAULT NULL,
  `branch_id` int DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `role` enum('employee','department_head','manager','asset_admin','super_admin') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `status` tinyint(1) DEFAULT '1',
  `last_login` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `employee_code`, `first_name`, `last_name`, `email`, `phone`, `department_id`, `company_id`, `branch_id`, `position`, `role`, `status`, `last_login`, `deleted_at`, `created_at`) VALUES
(2, 'admin', '$2y$10$fXHzOaYvnLDblD5V1cPR1uO/RQXe/nqyyEDYIGFX04DLue1m4/.oW', 'EMP001', 'Admin', 'System', 'admin@company.com', NULL, 1, NULL, NULL, NULL, 'super_admin', 1, '2026-04-02 02:11:03', NULL, '2026-02-25 15:05:54'),
(3, 'manager', '$2a$10$YourHashedPasswordHere', 'EMP002', 'Department', 'Head', 'manager@company.com', NULL, 2, NULL, NULL, NULL, 'department_head', 1, NULL, NULL, '2026-02-25 15:05:54'),
(4, 'user1', '$2y$12$Pbfcm9Q1EP7BtJMnTl07vOn8i.gLKez7V63AIYQBa.Lg0AQ/M3MBC', 'EMP003', 'User', 'One', 'user1@company.com', NULL, 3, NULL, NULL, NULL, 'employee', 1, '2026-03-01 15:08:14', NULL, '2026-02-25 15:05:54'),
(5, 'manager2', '$2y$12$Pbfcm9Q1EP7BtJMnTl07vOn8i.gLKez7V63AIYQBa.Lg0AQ/M3MBC', 'EMP004', 'Manager', 'Two', 'manager2@company.com', NULL, 2, NULL, NULL, NULL, 'manager', 1, NULL, NULL, '2026-02-25 15:14:03'),
(6, 'Phosika', '$2y$12$Pbfcm9Q1EP7BtJMnTl07vOn8i.gLKez7V63AIYQBa.Lg0AQ/M3MBC', 'EMP005', 'ໂພສີກະ', 'ສິດທດິສານ', 'somchai@example.com', '02055916959', 3, NULL, NULL, 'ຄຸມຄອງລະບົບ', 'super_admin', 1, '2026-04-02 01:26:45', NULL, '2026-02-28 13:47:46'),
(8, 'testuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'TEST001', 'Test', 'User', 'test@example.com', NULL, 1, NULL, NULL, NULL, 'asset_admin', 1, '2026-04-01 14:12:28', NULL, '2026-04-01 13:26:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_activities`
--

CREATE TABLE `user_activities` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_activities`
--

INSERT INTO `user_activities` (`id`, `user_id`, `action`, `description`, `ip_address`, `created_at`) VALUES
(1, 6, 'user_updated', 'User updated by somchai', '172.18.0.1', '2026-03-01 10:51:58'),
(3, 6, 'user_deleted', 'User deleted by Phosika', '172.18.0.1', '2026-03-01 12:07:36'),
(4, 6, 'user_deleted', 'User deleted by Phosika', '172.18.0.1', '2026-03-01 12:09:54'),
(5, 6, 'user_deleted', 'User deleted permanently by Phosika', '172.18.0.1', '2026-03-01 12:19:37'),
(6, 6, 'user_deactivated', 'User status changed to 0 by Phosika', '172.18.0.1', '2026-03-01 12:38:12'),
(7, 6, 'user_activated', 'User status changed to 1 by Phosika', '172.18.0.1', '2026-03-01 12:39:02'),
(8, 6, 'user_deactivated', 'User status changed to 0 by Phosika', '172.18.0.1', '2026-03-01 12:40:12'),
(9, 6, 'user_activated', 'User status changed to 1 by Phosika', '172.18.0.1', '2026-03-01 12:41:33'),
(10, 6, 'user_deactivated', 'User status changed to 0 by Phosika', '172.18.0.1', '2026-03-01 12:44:27'),
(11, 6, 'department_created', 'Department created: ຝ່າຍໄອທີ', '172.18.0.1', '2026-03-01 15:41:53'),
(12, 6, 'department_updated', 'Department updated: ຝ່າຍບໍລິຫານ', '172.18.0.1', '2026-03-01 15:45:41'),
(13, 6, 'department_status_changed', 'Department status changed to 0', '172.18.0.1', '2026-03-01 15:48:00'),
(14, 6, 'department_manager_changed', 'Department manager updated', '172.18.0.1', '2026-03-01 15:50:11'),
(15, 6, 'department_deleted', 'Department deleted: ຝ່າຍຂາຍ', '172.18.0.1', '2026-03-01 15:57:28'),
(16, 6, 'department_deleted', 'Department deleted: ຝ່າຍຂາຍ', '172.18.0.1', '2026-03-01 15:57:33'),
(17, 6, 'department_deleted', 'Department deleted: ຝ່າຍເຕັກໂນໂລຊີ', '172.18.0.1', '2026-03-01 15:57:53'),
(18, 6, 'department_status_changed', 'Department status changed to 1', '172.18.0.1', '2026-03-01 15:58:03'),
(19, 6, 'department_status_changed', 'Department status changed to 1', '172.18.0.1', '2026-03-01 15:58:06'),
(20, 6, 'department_status_changed', 'Department status changed to 1', '172.18.0.1', '2026-03-01 15:58:08'),
(21, 6, 'department_updated', 'Department updated: ຝ່າຍໄອທີ', '172.18.0.1', '2026-03-01 17:15:12'),
(22, 6, 'asset_category_updated', 'Asset category updated: ເຊີບເວີ', '172.18.0.1', '2026-03-02 09:39:51'),
(23, 6, 'asset_category_updated', 'Asset category updated: ໂຕະ', '172.18.0.1', '2026-03-02 09:41:24'),
(24, 6, 'asset_category_created', 'Asset category created: ລົດ', '172.18.0.1', '2026-03-03 13:40:17'),
(25, 6, 'asset_category_created', 'Asset category created: ພາຫະນະ', '172.18.0.1', '2026-03-03 14:26:08'),
(26, 6, 'asset_category_updated', 'Asset category updated: ລົດ', '172.18.0.1', '2026-03-03 14:27:09'),
(27, 6, 'asset_category_created', 'Asset category created: ຮອນດາ-ຊີວີກ', '172.18.0.1', '2026-03-03 14:30:57'),
(28, 6, 'asset_category_updated', 'Asset category updated: ຮອນດາ-ຊີວີກ', '172.18.0.1', '2026-03-03 14:31:48'),
(29, 6, 'asset_category_created', 'Asset category created: ຮຸນໄດ້-ໄອ30', '172.18.0.1', '2026-03-03 14:54:38'),
(30, 6, 'asset_category_created', 'Asset category created: ຈໍຄອມພີວເຕີ', '172.18.0.1', '2026-03-06 16:29:32'),
(31, 6, 'asset_updated', 'Asset updated: ASUS ROG Strix', '172.18.0.1', '2026-03-07 10:14:05'),
(32, 6, 'asset_updated', 'Asset updated: Monitor 21.5 ACER', '172.18.0.1', '2026-03-07 10:14:14'),
(33, 6, 'supplier_updated', 'Supplier updated: ບໍລິສັທ ຜູ້ສະໜອງ 1', '172.18.0.1', '2026-03-10 15:03:05'),
(34, 6, 'supplier_updated', 'Supplier updated: ບໍລິສັທ ຜູ້ສະໜອງ 2', '172.18.0.1', '2026-03-10 15:03:12'),
(35, 6, 'supplier_updated', 'Supplier updated: ບໍລິສັທ ຜູ້ສະໜອງ 1', '172.18.0.1', '2026-03-10 15:03:14'),
(36, 6, 'supplier_updated', 'Supplier updated: ບໍລິສັທ ຜູ້ສະໜອງ 2', '172.18.0.1', '2026-03-10 15:03:18'),
(37, 6, 'supplier_updated', 'Supplier updated: DICT', '172.18.0.1', '2026-03-11 06:59:17'),
(38, 6, 'asset_updated', 'Asset updated: ໂນດບຸກ Dell XPS 15', '172.18.0.1', '2026-03-22 17:51:09'),
(39, 6, 'item_updated', 'Item updated: ເຄື່ອງພິມ HP LaserJet', '172.18.0.1', '2026-03-23 03:43:33'),
(40, 6, 'item_updated', 'Item updated: ໜ້າຈໍຄອມ Samsung 27\"', '172.18.0.1', '2026-03-23 03:44:01'),
(41, 6, 'item_updated', 'Item updated: ໂນດບຸກ Dell XPS 15', '172.18.0.1', '2026-03-23 03:44:14'),
(42, 6, 'asset_updated', 'Asset updated: ໂຕະ', '172.18.0.1', '2026-03-27 07:06:50');

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_barcode_inventory`
-- (See below for the actual view)
--
CREATE TABLE `vw_barcode_inventory` (
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `vw_inventory_summary`
-- (See below for the actual view)
--
CREATE TABLE `vw_inventory_summary` (
);

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int NOT NULL,
  `warehouse_code` varchar(50) NOT NULL,
  `warehouse_name` varchar(255) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `manager_id` int DEFAULT NULL,
  `is_active` tinyint DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `updated_by` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `warehouses`
--

INSERT INTO `warehouses` (`id`, `warehouse_code`, `warehouse_name`, `location`, `manager_id`, `is_active`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'WH001', 'ນຳນັກງານໃຫຍ່', 'ບ້ານ ຕ່ານມີໄຊ ເມືອງ ໄຊທານີ ນະຄອນຫລວງວຽງຈັນ', NULL, 1, '2026-03-13 16:56:42', '2026-03-14 13:51:35', NULL, 6),
(2, 'WH002', 'ສະກາຍຄູ', 'ບ້ານ ສີສະຫວາດ ເມືອງຈັນທະບູລີ ນະຄອນຫລວງວຽງຈັນ', NULL, 1, '2026-03-13 16:56:42', '2026-03-14 13:51:20', NULL, 6),
(3, 'WH003', 'ສາງກະຈາຍສີນຄ້າ', 'ບ້ານ ຕ່ານມີໄຊ ເມືອງ ໄຊທານີ ນະຄອນຫລວງວຽງຈັນ', 2, 1, '2026-03-14 10:17:16', '2026-03-14 13:50:49', 6, 6);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_asset_code` (`asset_code`),
  ADD KEY `idx_asset_name` (`asset_name`),
  ADD KEY `idx_serial_number` (`serial_number`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_category_level1` (`category_level1_id`),
  ADD KEY `idx_category_level2` (`category_level2_id`),
  ADD KEY `idx_category_level3` (`category_level3_id`),
  ADD KEY `idx_department` (`department_id`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_current_user` (`current_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_condition` (`asset_condition`),
  ADD KEY `idx_purchase_date` (`purchase_date`),
  ADD KEY `idx_warranty_expiry` (`warranty_expiry`),
  ADD KEY `idx_next_maintenance` (`next_maintenance_date`),
  ADD KEY `idx_is_active` (`is_active`);
ALTER TABLE `assets` ADD FULLTEXT KEY `idx_asset_search` (`asset_name`,`description`,`notes`,`asset_name_en`);

--
-- Indexes for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_code` (`category_code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_category_path` (`path`),
  ADD KEY `idx_category_parent` (`parent_id`),
  ADD KEY `idx_category_level` (`level`);

--
-- Indexes for table `asset_depreciation`
--
ALTER TABLE `asset_depreciation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `period_start` (`period_start`),
  ADD KEY `period_end` (`period_end`);

--
-- Indexes for table `asset_documents`
--
ALTER TABLE `asset_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `asset_images`
--
ALTER TABLE `asset_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `asset_sync_log`
--
ALTER TABLE `asset_sync_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_synced_at` (`synced_at`),
  ADD KEY `idx_customer` (`customer_id`);

--
-- Indexes for table `barcodes`
--
ALTER TABLE `barcodes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode_number` (`barcode_number`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `idx_barcode_number` (`barcode_number`);

--
-- Indexes for table `barcode_generator`
--
ALTER TABLE `barcode_generator`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `generated_by` (`generated_by`);

--
-- Indexes for table `barcode_scans`
--
ALTER TABLE `barcode_scans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `scanned_by` (`scanned_by`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_code` (`branch_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `category_attributes`
--
ALTER TABLE `category_attributes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `category_inheritance`
--
ALTER TABLE `category_inheritance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `inherited_from_id` (`inherited_from_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_code` (`company_code`),
  ADD KEY `parent_company_id` (`parent_company_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_code` (`customer_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `department_code` (`department_code`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `parent_department_id` (`parent_department_id`);

--
-- Indexes for table `depreciation_calculation_log`
--
ALTER TABLE `depreciation_calculation_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `calculation_date` (`calculation_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `depreciation_standard`
--
ALTER TABLE `depreciation_standard`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_category_id` (`asset_category_id`),
  ADD KEY `asset_type` (`asset_type`);

--
-- Indexes for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_currency_date` (`currency_code`,`rate_date`),
  ADD KEY `idx_currency` (`currency_code`),
  ADD KEY `idx_date` (`rate_date`);

--
-- Indexes for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_code` (`item_code`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_inv_item_code` (`item_code`),
  ADD KEY `idx_inv_barcode` (`barcode`),
  ADD KEY `idx_inv_item_name` (`item_name`),
  ADD KEY `idx_inv_category` (`category_id`),
  ADD KEY `idx_inv_supplier` (`supplier_id`),
  ADD KEY `idx_inv_status` (`is_active`),
  ADD KEY `idx_inv_created` (`created_at`);

--
-- Indexes for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_item_warehouse` (`item_id`,`warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_stock_item` (`item_id`),
  ADD KEY `idx_stock_warehouse` (`warehouse_id`);

--
-- Indexes for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `inventory_stock_counts`
--
ALTER TABLE `inventory_stock_counts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `count_by` (`count_by`),
  ADD KEY `idx_counts_item` (`item_id`),
  ADD KEY `idx_counts_date` (`count_date`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_code` (`location_code`),
  ADD KEY `parent_location_id` (`parent_location_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `manager_id` (`manager_id`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_login_time` (`login_time`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `po_number` (`po_number`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `purchase_order_details`
--
ALTER TABLE `purchase_order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `po_id` (`po_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `so_number` (`so_number`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `sales_order_details`
--
ALTER TABLE `sales_order_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `so_id` (`so_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `adjustment_code` (`adjustment_code`),
  ADD KEY `idx_item_id` (`item_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_adjustment_code` (`adjustment_code`);

--
-- Indexes for table `stock_count_details`
--
ALTER TABLE `stock_count_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_item` (`session_id`,`item_id`),
  ADD KEY `idx_session` (`session_id`),
  ADD KEY `idx_item` (`item_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_scd_counted_by` (`counted_by`),
  ADD KEY `fk_scd_verified_by` (`verified_by`),
  ADD KEY `fk_scd_adjusted_by` (`adjusted_by`);

--
-- Indexes for table `stock_count_sessions`
--
ALTER TABLE `stock_count_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_session_code` (`session_code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_warehouse` (`warehouse_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_scs_created_by` (`created_by`),
  ADD KEY `fk_scs_completed_by` (`completed_by`);

--
-- Indexes for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stock_id` (`stock_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `supplier_code` (`supplier_code`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_supplier_code` (`supplier_code`),
  ADD KEY `idx_supplier_name` (`supplier_name`),
  ADD KEY `idx_supplier_status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `employee_code` (`employee_code`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `warehouse_code` (`warehouse_code`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `asset_categories`
--
ALTER TABLE `asset_categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_depreciation`
--
ALTER TABLE `asset_depreciation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_documents`
--
ALTER TABLE `asset_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_images`
--
ALTER TABLE `asset_images`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `asset_sync_log`
--
ALTER TABLE `asset_sync_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `barcodes`
--
ALTER TABLE `barcodes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `barcode_generator`
--
ALTER TABLE `barcode_generator`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `barcode_scans`
--
ALTER TABLE `barcode_scans`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_attributes`
--
ALTER TABLE `category_attributes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `category_inheritance`
--
ALTER TABLE `category_inheritance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `depreciation_calculation_log`
--
ALTER TABLE `depreciation_calculation_log`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `depreciation_standard`
--
ALTER TABLE `depreciation_standard`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `inventory_items`
--
ALTER TABLE `inventory_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `inventory_stock_counts`
--
ALTER TABLE `inventory_stock_counts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=237;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `purchase_order_details`
--
ALTER TABLE `purchase_order_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `sales_orders`
--
ALTER TABLE `sales_orders`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `sales_order_details`
--
ALTER TABLE `sales_order_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `stock_count_details`
--
ALTER TABLE `stock_count_details`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `stock_count_sessions`
--
ALTER TABLE `stock_count_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_activities`
--
ALTER TABLE `user_activities`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

-- --------------------------------------------------------

--
-- Structure for view `vw_barcode_inventory`
--
DROP TABLE IF EXISTS `vw_barcode_inventory`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `vw_barcode_inventory`  AS SELECT 'ITEM' AS `type`, `ii`.`id` AS `reference_id`, `ii`.`item_code` AS `code`, `ii`.`item_name` AS `name`, `ii`.`barcode` AS `barcode`, `ii`.`barcode_type` AS `barcode_type`, NULL AS `batch_number`, NULL AS `serial_number`, NULL AS `stock_quantity` FROM `inventory_items` AS `ii` WHERE (`ii`.`barcode` is not null)union all select 'STOCK' AS `type`,`is2`.`id` AS `reference_id`,`ii`.`item_code` AS `code`,`ii`.`item_name` AS `name`,`is2`.`unique_barcode` AS `barcode`,'CODE128' AS `barcode_type`,`is2`.`batch_number` AS `batch_number`,`is2`.`serial_number` AS `serial_number`,`is2`.`quantity` AS `stock_quantity` from (`inventory_stock` `is2` join `inventory_items` `ii` on((`is2`.`item_id` = `ii`.`id`))) where (`is2`.`unique_barcode` is not null)  ;

-- --------------------------------------------------------

--
-- Structure for view `vw_inventory_summary`
--
DROP TABLE IF EXISTS `vw_inventory_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `vw_inventory_summary`  AS SELECT `ii`.`id` AS `id`, `ii`.`item_code` AS `item_code`, `ii`.`item_name` AS `item_name`, `ii`.`brand` AS `brand`, `ii`.`model` AS `model`, `ac`.`category_name` AS `category_name`, `s`.`supplier_name` AS `supplier_name`, count(`is2`.`id`) AS `total_stock`, sum(`is2`.`quantity`) AS `total_quantity`, avg(`is2`.`purchase_price`) AS `avg_purchase_price`, avg(`is2`.`selling_price`) AS `avg_selling_price`, sum((`is2`.`quantity` * `is2`.`purchase_price`)) AS `total_inventory_value` FROM (((`inventory_items` `ii` left join `asset_categories` `ac` on((`ii`.`category_id` = `ac`.`id`))) left join `suppliers` `s` on((`ii`.`supplier_id` = `s`.`id`))) left join `inventory_stock` `is2` on(((`ii`.`id` = `is2`.`item_id`) and (`is2`.`status` = 'in_stock')))) GROUP BY `ii`.`id` ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`category_level1_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `assets_ibfk_10` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assets_ibfk_11` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`category_level2_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `assets_ibfk_3` FOREIGN KEY (`category_level3_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `assets_ibfk_4` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `assets_ibfk_5` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `assets_ibfk_6` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `assets_ibfk_7` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `assets_ibfk_8` FOREIGN KEY (`current_user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `assets_ibfk_9` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_categories`
--
ALTER TABLE `asset_categories`
  ADD CONSTRAINT `asset_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `asset_categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_depreciation`
--
ALTER TABLE `asset_depreciation`
  ADD CONSTRAINT `fk_asset_depreciation_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `asset_documents`
--
ALTER TABLE `asset_documents`
  ADD CONSTRAINT `asset_documents_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asset_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `asset_images`
--
ALTER TABLE `asset_images`
  ADD CONSTRAINT `asset_images_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `asset_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `barcodes`
--
ALTER TABLE `barcodes`
  ADD CONSTRAINT `barcodes_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `barcodes_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `barcodes_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `barcode_generator`
--
ALTER TABLE `barcode_generator`
  ADD CONSTRAINT `barcode_generator_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `barcode_scans`
--
ALTER TABLE `barcode_scans`
  ADD CONSTRAINT `barcode_scans_ibfk_3` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `branches_ibfk_2` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `category_attributes`
--
ALTER TABLE `category_attributes`
  ADD CONSTRAINT `category_attributes_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`);

--
-- Constraints for table `category_inheritance`
--
ALTER TABLE `category_inheritance`
  ADD CONSTRAINT `category_inheritance_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`),
  ADD CONSTRAINT `category_inheritance_ibfk_2` FOREIGN KEY (`inherited_from_id`) REFERENCES `asset_categories` (`id`);

--
-- Constraints for table `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `companies_ibfk_1` FOREIGN KEY (`parent_company_id`) REFERENCES `companies` (`id`);

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `customers_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `customers_ibfk_4` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `customers_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `departments`
--
ALTER TABLE `departments`
  ADD CONSTRAINT `departments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `departments_ibfk_2` FOREIGN KEY (`parent_department_id`) REFERENCES `departments` (`id`);

--
-- Constraints for table `depreciation_standard`
--
ALTER TABLE `depreciation_standard`
  ADD CONSTRAINT `fk_depreciation_standard_category` FOREIGN KEY (`asset_category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_items`
--
ALTER TABLE `inventory_items`
  ADD CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `asset_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_items_ibfk_2` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_items_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_items_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_stock`
--
ALTER TABLE `inventory_stock`
  ADD CONSTRAINT `inventory_stock_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stock_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_stock_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `inventory_stock_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_stocks`
--
ALTER TABLE `inventory_stocks`
  ADD CONSTRAINT `inventory_stocks_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stocks_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `inventory_stock_counts`
--
ALTER TABLE `inventory_stock_counts`
  ADD CONSTRAINT `inventory_stock_counts_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_stock_counts_ibfk_2` FOREIGN KEY (`count_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_ibfk_1` FOREIGN KEY (`parent_location_id`) REFERENCES `locations` (`id`),
  ADD CONSTRAINT `locations_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `locations_ibfk_3` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `purchase_order_details`
--
ALTER TABLE `purchase_order_details`
  ADD CONSTRAINT `purchase_order_details_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`);

--
-- Constraints for table `sales_orders`
--
ALTER TABLE `sales_orders`
  ADD CONSTRAINT `sales_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_orders_ibfk_5` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `sales_order_details`
--
ALTER TABLE `sales_order_details`
  ADD CONSTRAINT `sales_order_details_ibfk_1` FOREIGN KEY (`so_id`) REFERENCES `sales_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_order_details_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`);

--
-- Constraints for table `stock_adjustments`
--
ALTER TABLE `stock_adjustments`
  ADD CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  ADD CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`);

--
-- Constraints for table `stock_count_details`
--
ALTER TABLE `stock_count_details`
  ADD CONSTRAINT `fk_scd_adjusted_by` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scd_counted_by` FOREIGN KEY (`counted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scd_item` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scd_session` FOREIGN KEY (`session_id`) REFERENCES `stock_count_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_scd_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_count_sessions`
--
ALTER TABLE `stock_count_sessions`
  ADD CONSTRAINT `fk_scs_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scs_warehouse` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `stock_movements_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `users_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `user_activities`
--
ALTER TABLE `user_activities`
  ADD CONSTRAINT `user_activities_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouses_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
