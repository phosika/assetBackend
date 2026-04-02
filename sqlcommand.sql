CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_code VARCHAR(50) UNIQUE NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    parent_company_id INT NULL,
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    tax_id VARCHAR(50),
    status BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_company_id) REFERENCES companies(id)
);


CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_code VARCHAR(50) UNIQUE NOT NULL,
    department_name VARCHAR(200) NOT NULL,
    company_id INT NOT NULL,
    parent_department_id INT NULL,
    manager_id INT NULL,
    status BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (parent_department_id) REFERENCES departments(id)
);

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    employee_code VARCHAR(50) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    department_id INT NOT NULL,
    position VARCHAR(100),
    role ENUM('employee', 'department_head', 'asset_admin', 'super_admin') NOT NULL,
    status BOOLEAN DEFAULT true,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (department_id) REFERENCES departments(id)
);


CREATE TABLE suppliers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    supplier_code VARCHAR(50) UNIQUE NOT NULL,
    supplier_name VARCHAR(200) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    tax_id VARCHAR(50),
    payment_terms TEXT,
    status BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE asset_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_code VARCHAR(50) UNIQUE NOT NULL,
    category_name VARCHAR(200) NOT NULL,
    description TEXT,
    
    -- ໂຄງສ້າງລະດັບຊັ້ນ
    parent_id INT NULL,
    level INT DEFAULT 1 COMMENT '1: ລະດັບໃຫຍ່, 2: ລະດັບກາງ, 3: ລະດັບຍ່ອຍ',
    
    -- ເສັ້ນທາງຂອງລະດັບຊັ້ນ (ເພື່ອຄວາມງ່າຍໃນການຄົ້ນຫາ)
    path VARCHAR(255) COMMENT 'ເສັ້ນທາງຂອງລະດັບຊັ້ນ ເຊັ່ນ: 1/5/10',
    
    -- ຄຸນສົມບັດສຳລັບການຄິດໄລ່ຄ່າເສື່ອມລາຄາ (ສາມາດສືບທອດຈາກລະດັບແມ່ໄດ້)
    depreciation_method ENUM('straight_line', 'declining_balance', 'none') DEFAULT 'straight_line',
    useful_life_years INT,
    depreciation_rate DECIMAL(5,2) COMMENT 'ອັດຕາຄ່າເສື່ອມລາຄາ (%)',
    
    -- ຂໍ້ມູນອື່ນໆ
    is_active BOOLEAN DEFAULT true,
    sort_order INT DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    FOREIGN KEY (parent_id) REFERENCES asset_categories(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    
    -- ກວດສອບວ່າລະດັບຊັ້ນບໍ່ເກີນ 3
    CONSTRAINT check_category_level CHECK (level <= 3)
);

-- ສ້າງ Index ເພື່ອຄວາມໄວໃນການຄົ້ນຫາ
CREATE INDEX idx_category_path ON asset_categories(path);
CREATE INDEX idx_category_parent ON asset_categories(parent_id);
CREATE INDEX idx_category_level ON asset_categories(level);


-- ຕາຕະລາງຄຸນສົມບັດສະເພາະຂອງແຕ່ລະປະເພດ
CREATE TABLE category_attributes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    attribute_name VARCHAR(100) NOT NULL,
    attribute_type ENUM('text', 'number', 'date', 'boolean', 'select') NOT NULL,
    is_required BOOLEAN DEFAULT false,
    options TEXT COMMENT 'ສຳລັບ type select',
    sort_order INT DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id)
);

-- ຕາຕະລາງການສືບທອດຄຸນສົມບັດ
CREATE TABLE category_inheritance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT NOT NULL,
    inherited_from_id INT NOT NULL,
    attribute_name VARCHAR(100),
    is_overridden BOOLEAN DEFAULT false,
    FOREIGN KEY (category_id) REFERENCES asset_categories(id),
    FOREIGN KEY (inherited_from_id) REFERENCES asset_categories(id)
);


DELIMITER //

CREATE PROCEDURE create_category(
    IN p_category_code VARCHAR(50),
    IN p_category_name VARCHAR(200),
    IN p_description TEXT,
    IN p_parent_id INT,
    IN p_depreciation_method ENUM('straight_line', 'declining_balance', 'none'),
    IN p_useful_life_years INT,
    IN p_created_by INT
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
        
        -- ກວດສອບວ່າບໍ່ເກີນ 3 ລະດັບ
        IF v_level >= 3 THEN
            SIGNAL SQLSTATE '45000' 
            SET MESSAGE_TEXT = 'Cannot create category: Maximum level (3) reached';
        END IF;
        
        SET v_level = v_level + 1;
    END IF;
    
    -- ສ້າງປະເພດໃໝ່
    INSERT INTO asset_categories (
        category_code, category_name, description, parent_id, 
        level, depreciation_method, useful_life_years, created_by
    ) VALUES (
        p_category_code, p_category_name, p_description, p_parent_id,
        v_level, p_depreciation_method, p_useful_life_years, p_created_by
    );
    
    -- ໄດ້ຮັບ ID ໃໝ່
    SET v_new_id = LAST_INSERT_ID();
    
    -- ອັບເດດ path
    IF p_parent_id IS NULL THEN
        UPDATE asset_categories 
        SET path = CAST(v_new_id AS CHAR)
        WHERE id = v_new_id;
    ELSE
        UPDATE asset_categories 
        SET path = CONCAT(v_parent_path, '/', v_new_id)
        WHERE id = v_new_id;
    END IF;
    
    -- ສົ່ງຄືນຂໍ້ມູນທີ່ສ້າງໃໝ່
    SELECT * FROM asset_categories WHERE id = v_new_id;
END //

DELIMITER ;



CREATE TABLE assets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- ຂໍ້ມູນພື້ນຖານ
    asset_code VARCHAR(100) UNIQUE NOT NULL,
    asset_name VARCHAR(200) NOT NULL,
    asset_name_en VARCHAR(200),
    old_asset_code VARCHAR(100) COMMENT 'ລະຫັດຊັບສິນເກົ່າ (ກ່ອນໃຊ້ລະບົບ)',
    
    -- ການເຊື່ອມໂຍງກັບປະເພດຊັບສິນ (3 ລະດັບ)
    category_level1_id INT NOT NULL COMMENT 'ປະເພດລະດັບ 1',
    category_level2_id INT COMMENT 'ປະເພດລະດັບ 2',
    category_level3_id INT COMMENT 'ປະເພດລະດັບ 3',
    category_id INT NOT NULL COMMENT 'ປະເພດຍ່ອຍສຸດທ້າຍທີ່ເລືອກ',
    
    -- ລາຍລະອຽດຊັບສິນ
    description TEXT,
    brand VARCHAR(100),
    model VARCHAR(100),
    serial_number VARCHAR(100),
    manufacturing_year INT,
    country_of_origin VARCHAR(100),
    color VARCHAR(50),
    size_dimensions VARCHAR(100),
    weight DECIMAL(10,2) COMMENT 'ນ້ຳໜັກ (ກິໂລກຣາມ)',
    
    -- ຂໍ້ມູນການຊື້
    purchase_date DATE NOT NULL,
    purchase_cost DECIMAL(15,2) NOT NULL,
    purchase_cost_usd DECIMAL(15,2) COMMENT 'ມູນຄ່າຊື້ເປັນໂດລາ',
    exchange_rate DECIMAL(10,4) COMMENT 'ອັດຕາແລກປ່ຽນ',
    supplier_id INT,
    purchase_invoice_no VARCHAR(100),
    purchase_order_no VARCHAR(100),
    payment_status ENUM('paid', 'partial', 'unpaid') DEFAULT 'paid',
    warranty_provider VARCHAR(200),
    warranty_expiry DATE,
    warranty_terms TEXT,
    insurance_policy_no VARCHAR(100),
    insurance_expiry DATE,
    insurance_provider VARCHAR(200),
    
    -- ຂໍ້ມູນສະຖານທີ່
    company_id INT NOT NULL,
    department_id INT NOT NULL,
    current_user_id INT,
    location_id INT COMMENT 'ສະຖານທີ່ເກັບຮັກສາ (ຕາຕະລາງສະຖານທີ່)',
    building VARCHAR(100),
    floor VARCHAR(50),
    room VARCHAR(100),
    exact_location TEXT COMMENT 'ສະຖານທີ່ຕັ້ງທີ່ແນ່ນອນ',
    gps_coordinates VARCHAR(100),
    
    -- ຂໍ້ມູນສະຖານະ
    status ENUM(
        'in_use',           -- ກຳລັງໃຊ້ງານ
        'available',        -- ພ້ອມໃຊ້ງານ
        'maintenance',      -- ກຳລັງສ້ອມແປງ
        'reserved',         -- ຈອງໄວ້
        'disposed',         -- ຕັດຊໍາລຸດແລ້ວ
        'sold',            -- ຂາຍແລ້ວ
        'lost',            -- ສູນຫາຍ
        'damaged',         -- ເສຍຫາຍ
        'stored'           -- ເກັບຮັກສາ
    ) DEFAULT 'available',
    
    asset_condition ENUM(
        'new',              -- ໃໝ່
        'excellent',        -- ດີເລີດ
        'good',            -- ດີ
        'fair',            -- ພໍໃຊ້
        'poor',            -- ຊຸດໂຊມ
        'damaged',         -- ເສຍຫາຍ
        'obsolete'         -- ລ້າສະໄໝ
    ) DEFAULT 'good',
    
    condition_notes TEXT,
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    maintenance_frequency_days INT,
    
    -- ຂໍ້ມູນການເງິນ
    current_value DECIMAL(15,2),
    salvage_value DECIMAL(15,2) DEFAULT 0 COMMENT 'ມູນຄ່າຊາກ',
    accumulated_depreciation DECIMAL(15,2) DEFAULT 0,
    depreciation_start_date DATE,
    depreciation_end_date DATE,
    last_depreciation_date DATE,
    depreciation_method ENUM('straight_line', 'declining_balance', 'sum_of_years', 'units_of_production', 'none') 
        DEFAULT 'straight_line',
    useful_life_years INT,
    useful_life_months INT,
    depreciation_rate DECIMAL(5,2),
    
    -- ຂໍ້ມູນການຮັບປະກັນ ແລະ ເອກະສານ
    has_warranty BOOLEAN DEFAULT false,
    warranty_document_path VARCHAR(500),
    has_manual BOOLEAN DEFAULT false,
    manual_document_path VARCHAR(500),
    has_invoice BOOLEAN DEFAULT false,
    invoice_document_path VARCHAR(500),
    has_certificate BOOLEAN DEFAULT false,
    certificate_document_path VARCHAR(500),
    asset_image_path VARCHAR(500),
    additional_documents TEXT COMMENT 'JSON ເກັບຂໍ້ມູນເອກະສານເພີ່ມເຕີມ',
    
    -- ຂໍ້ມູນການຕິດສະຫຼາກ
    qr_code VARCHAR(255),
    qr_code_image_path VARCHAR(500),
    barcode VARCHAR(100),
    barcode_image_path VARCHAR(500),
    rfid_tag VARCHAR(100),
    asset_label_printed BOOLEAN DEFAULT false,
    last_printed_date DATE,
    
    -- ຂໍ້ມູນການສ້າງ ແລະ ອັບເດດ
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INT,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- ຂໍ້ມູນການກວດສອບ
    verified_by INT,
    verified_at TIMESTAMP NULL,
    verification_notes TEXT,
    is_active BOOLEAN DEFAULT true,
    
    -- ຂໍ້ມູນອື່ນໆ
    notes TEXT,
    custom_fields JSON COMMENT 'ເກັບຂໍ້ມູນສະເພາະທີ່ບໍ່ມີໃນຕາຕະລາງ',
    
    -- Foreign Keys
    FOREIGN KEY (category_level1_id) REFERENCES asset_categories(id),
    FOREIGN KEY (category_level2_id) REFERENCES asset_categories(id),
    FOREIGN KEY (category_level3_id) REFERENCES asset_categories(id),
    FOREIGN KEY (category_id) REFERENCES asset_categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (current_user_id) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- ສ້າງ Indexes ເພື່ອຄວາມໄວໃນການຄົ້ນຫາ
CREATE INDEX idx_asset_code ON assets(asset_code);
CREATE INDEX idx_asset_name ON assets(asset_name);
CREATE INDEX idx_serial_number ON assets(serial_number);
CREATE INDEX idx_category ON assets(category_id);
CREATE INDEX idx_category_level1 ON assets(category_level1_id);
CREATE INDEX idx_category_level2 ON assets(category_level2_id);
CREATE INDEX idx_category_level3 ON assets(category_level3_id);
CREATE INDEX idx_department ON assets(department_id);
CREATE INDEX idx_company ON assets(company_id);
CREATE INDEX idx_current_user ON assets(current_user_id);
CREATE INDEX idx_status ON assets(status);
CREATE INDEX idx_condition ON assets(asset_condition);
CREATE INDEX idx_purchase_date ON assets(purchase_date);
CREATE INDEX idx_warranty_expiry ON assets(warranty_expiry);
CREATE INDEX idx_next_maintenance ON assets(next_maintenance_date);
CREATE INDEX idx_is_active ON assets(is_active);

-- ສ້າງ Fulltext Index ສຳລັບການຄົ້ນຫາຂໍ້ຄວາມ
CREATE FULLTEXT INDEX idx_asset_search ON assets(asset_name, description, notes, asset_name_en);



CREATE TABLE locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    location_code VARCHAR(50) UNIQUE NOT NULL,
    location_name VARCHAR(200) NOT NULL,
    location_type ENUM('building', 'floor', 'room', 'warehouse', 'office') NOT NULL,
    parent_location_id INT,
    company_id INT NOT NULL,
    address TEXT,
    capacity INT COMMENT 'ຄວາມຈຸສູງສຸດ',
    current_usage INT DEFAULT 0,
    manager_id INT,
    phone VARCHAR(20),
    is_active BOOLEAN DEFAULT true,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_location_id) REFERENCES locations(id),
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (manager_id) REFERENCES users(id)
);

CREATE TABLE asset_images (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_type ENUM('main', 'additional', 'damage', 'maintenance') DEFAULT 'additional',
    description VARCHAR(255),
    sort_order INT DEFAULT 0,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

CREATE TABLE asset_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('invoice', 'warranty', 'manual', 'certificate', 'insurance', 'other') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT COMMENT 'ຂະໜາດໄຟລ໌ (ໄບຕ໌)',
    mime_type VARCHAR(100),
    expiry_date DATE,
    is_confidential BOOLEAN DEFAULT false,
    uploaded_by INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);


DROP TRIGGER IF EXISTS update_asset_current_value;

CREATE TRIGGER update_asset_current_value BEFORE UPDATE ON assets FOR EACH ROW 
BEGIN 
    IF NEW.purchase_cost != OLD.purchase_cost OR NEW.accumulated_depreciation != OLD.accumulated_depreciation THEN 
        SET NEW.current_value = NEW.purchase_cost - NEW.accumulated_depreciation; 
    END IF; 
    IF NEW.status IN ('disposed', 'sold') AND OLD.status NOT IN ('disposed', 'sold') THEN 
        SET NEW.current_value = 0; 
        SET NEW.accumulated_depreciation = NEW.purchase_cost; 
    END IF; 
END;



DROP TRIGGER IF EXISTS set_initial_asset_value;

CREATE TRIGGER set_initial_asset_value
BEFORE INSERT ON assets
FOR EACH ROW
BEGIN
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
END;


DROP TRIGGER IF EXISTS update_asset_current_value;

CREATE TRIGGER update_asset_current_value
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END;


DROP TRIGGER IF EXISTS update_maintenance_dates;

CREATE TRIGGER update_maintenance_dates
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END;


DROP TRIGGER IF EXISTS log_asset_changes;

CREATE TRIGGER log_asset_changes
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END;



DROP TRIGGER IF EXISTS auto_update_asset_status;

CREATE TRIGGER auto_update_asset_status
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END;

DROP TRIGGER IF EXISTS update_warranty_status;

CREATE TRIGGER update_warranty_status
BEFORE UPDATE ON assets
FOR EACH ROW
BEGIN
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
END;




INSERT INTO assets (
    asset_code, asset_name, category_id, purchase_date, purchase_cost,
    company_id, department_id, created_by
) VALUES (
    'TEST-001', 'ຊັບສິນທົດສອບ', 1, CURDATE(), 50000,
    1, 1, 1
);

InnoDB rebuilding table to add column FTS_DOC_ID
Trigger does not exist
Trigger does not exist
Trigger does not exist



SHOW TRIGGERS;


INSERT INTO assets (
    asset_code, 
    asset_name, 
    category_level1_id,
    category_id, 
    purchase_date, 
    purchase_cost,
    company_id, 
    department_id, 
    created_by
) VALUES (
    'TEST-002', 
    'ຊັບສິນທົດສອບ 2', 
    1,  
    1,  
    CURDATE(), 
    75000,
    1,  
    1,  
    1
);


SELECT * FROM users WHERE id = 1;



-- ສ້າງຜູ້ໃຊ້ສຳລັບທົດສອບ
INSERT INTO users (
    username, 
    employee_code, 
    first_name, 
    last_name, 
    department_id, 
    role,
    password_hash
) VALUES (
    'admin', 
    'ADMIN001', 
    'Admin', 
    'User', 
    1,  -- ໃຫ້ແນ່ໃຈວ່າ department_id ນີ້ມີໃນຕາຕະລາງ departments
    'super_admin',
    'temp_password_hash'  -- ຕ້ອງຕັ້ງ password ຈິງພາຍຫຼັງ
);

-- ບັນທຶກ id ທີ່ສ້າງໃໝ່
SELECT LAST_INSERT_ID();



INSERT INTO companies (company_code, company_name) VALUES 
('COMP001', 'ບໍລິສັດ ແມ່ ຈຳກັດ'),
('COMP002', 'ບໍລິສັດ ລູກ 1 ຈຳກັດ'),
('COMP003', 'ບໍລິສັດ ລູກ 2 ຈຳກັດ');

-- ລຳດັບທີ 2: ສ້າງພະແນກ (departments)
INSERT INTO departments (department_code, department_name, company_id) VALUES 
('ADMIN', 'ຝ່າຍບໍລິຫານ', 1),
('HR', 'ຝ່າຍຊັບພະຍາກອນມະນຸດ', 1),
('FIN', 'ຝ່າຍການເງິນ', 1),
('IT', 'ຝ່າຍເຕັກໂນໂລຊີ', 1),
('SALE', 'ຝ່າຍຂາຍ', 2);


INSERT INTO users (
    username, 
    password_hash, 
    employee_code, 
    first_name, 
    last_name, 
    email, 
    department_id, 
    role,
    status
) VALUES 
('admin', '$2a$10$YourHashedPasswordHere', 'EMP001', 'Admin', 'System', 'admin@company.com', 1, 'super_admin', true),
('manager', '$2a$10$YourHashedPasswordHere', 'EMP002', 'Manager', 'User', 'manager@company.com', 2, 'department_head', true),
('user1', '$2a$10$YourHashedPasswordHere', 'EMP003', 'User', 'One', 'user1@company.com', 3, 'employee', true);

INSERT INTO asset_categories (category_code, category_name, level) VALUES 
('CAT1', 'ປະເພດຊັບສິນລະດັບ 1', 1),
('CAT2', 'ປະເພດຊັບສິນລະດັບ 2', 2),
('CAT3', 'ປະເພດຊັບສິນລະດັບ 3', 3);

-- ລຳດັບທີ 5: ສ້າງຜູ້ສະໜອງ (suppliers)
INSERT INTO suppliers (supplier_code, supplier_name, contact_person, phone, email) VALUES 
('SUP001', 'ບໍລິສັທ ຜູ້ສະໜອງ 1', 'ສົມຊາຍ', '02012345678', 'supplier1@company.com'),
('SUP002', 'ບໍລິສັທ ຜູ້ສະໜອງ 2', 'ສົມບູນ', '02087654321', 'supplier2@company.com');

-- ລຳດັບທີ 6: ສ້າງສະຖານທີ່ (locations)
INSERT INTO locations (location_code, location_name, location_type, company_id) VALUES 
('LOC001', 'ອາຄານ A', 'building', 1),
('LOC002', 'ອາຄານ B', 'building', 1),
('LOC003', 'ຊັ້ນ 2 ອາຄານ A', 'floor', 1),
('LOC004', 'ຫ້ອງ IT', 'room', 1);

INSERT INTO assets (
    asset_code, 
    asset_name, 
    category_level1_id,
    category_id, 
    purchase_date, 
    purchase_cost,
    company_id, 
    department_id, 
    created_by,
    location_id,
    supplier_id,
    status,
    current_value,
    accumulated_depreciation
) VALUES 
('AST001', 'ຄອມພິວເຕີໂນດບຸກ', 1, 1, '2026-01-15', 15000000, 1, 4, 1, 4, 1, 'in_use', 15000000, 0),
('AST002', 'ເຄື່ອງພິມ', 1, 1, '2026-01-20', 5000000, 1, 4, 1, 4, 1, 'available', 5000000, 0),
('AST003', 'ໂຕະພະນັກງານ', 1, 1, '2026-02-01', 3000000, 2, 5, 2, 1, 2, 'in_use', 3000000, 0);


-- ສ້າງຜູ້ໃຊ້ທີ່ຂາດຫາຍໄປ
INSERT INTO users (
    username, 
    password_hash, 
    employee_code, 
    first_name, 
    last_name, 
    email, 
    department_id, 
    role,
    status
) VALUES 
('manager2', 'temp_hash_123', 'EMP004', 'Manager', 'Two', 'manager2@company.com', 2, 'department_head', true);

-- ບັນທຶກ id ທີ່ສ້າງໃໝ່
SELECT LAST_INSERT_ID();


-- ຖ້າທ່ານມີຜູ້ໃຊ້ id=1 ແລ້ວ, ໃຫ້ປ່ຽນ created_by ຈາກ 2 ເປັນ 1
INSERT INTO assets (
    asset_code, 
    asset_name, 
    category_level1_id,
    category_id, 
    purchase_date, 
    purchase_cost,
    company_id, 
    department_id, 
    created_by,  -- ປ່ຽນຈາກ 2 ເປັນ 1
    location_id,
    supplier_id,
    status,
    current_value,
    accumulated_depreciation
) VALUES 
('AST001', 'ຄອມພິວເຕີໂນດບຸກ', 1, 1, '2026-01-15', 15000000, 1, 4, 1, 4, 1, 'in_use', 15000000, 0),
('AST002', 'ເຄື່ອງພິມ', 1, 1, '2026-01-20', 5000000, 1, 4, 1, 4, 1, 'available', 5000000, 0),
('AST003', 'ໂຕະພະນັກງານ', 1, 1, '2026-02-01', 3000000, 2, 5, 1, 1, 2, 'in_use', 3000000, 0);  -- ປ່ຽນ created_by ຈາກ 2 ເປັນ 1



INSERT INTO assets (
    asset_code, 
    asset_name, 
    category_level1_id,
    category_id, 
    purchase_date, 
    purchase_cost,
    company_id, 
    department_id, 
    created_by,
    location_id,
    supplier_id,
    status,
    current_value,
    accumulated_depreciation
) VALUES 
('AST003', 'ໂຕະພະນັກງານ', 1, 1, '2026-02-01', 3000000, 2, 5, 2, 1, 2, 'in_use', 3000000, 0);


SELECT id, username, employee_code, first_name, last_name, role 
FROM users;

INSERT INTO assets (
    asset_code, 
    asset_name, 
    category_level1_id,
    category_id, 
    purchase_date, 
    purchase_cost,
    company_id, 
    department_id, 
    created_by,  -- ໃຊ້ id 2,3,4 ທີ່ມີຢູ່
    location_id,
    supplier_id,
    status,
    current_value,
    accumulated_depreciation
) VALUES 
-- ຊັບສິນທີ່ສ້າງໂດຍ admin (id=2)
('AST001', 'ຄອມພິວເຕີໂນດບຸກ', 1, 1, '2026-01-15', 15000000, 1, 4, 2, 4, 1, 'in_use', 15000000, 0),

-- ຊັບສິນທີ່ສ້າງໂດຍ manager (id=3)
('AST002', 'ເຄື່ອງພິມ', 1, 1, '2026-01-20', 5000000, 1, 4, 3, 4, 1, 'available', 5000000, 0),

-- ຊັບສິນທີ່ສ້າງໂດຍ user1 (id=4)
('AST003', 'ໂຕະພະນັກງານ', 1, 1, '2026-02-01', 3000000, 2, 5, 4, 1, 2, 'in_use', 3000000, 0);


-- ເບິ່ງຂໍ້ມູນປະເພດຊັບສິນທັງໝົດ
SELECT id, category_code, category_name, level 
FROM asset_categories;

-- ຖ້າບໍ່ມີຂໍ້ມູນເລີຍ, ຜົນທີ່ໄດ້ຈະເປັນ empty set


-- ສ້າງປະເພດຊັບສິນ 3 ລະດັບ
INSERT INTO asset_categories (category_code, category_name, level) VALUES 
-- ລະດັບ 1 (ໃຫຍ່)
('CAT-HW', 'ຮາດແວ', 1),
('CAT-SW', 'ຊອບແວ', 1),
('CAT-FURN', 'ເຄື່ອງເຟີນີເຈີ', 1),

-- ລະດັບ 2 (ກາງ)
('CAT-COM', 'ຄອມພິວເຕີ', 2),
('CAT-PRINT', 'ເຄື່ອງພິມ', 2),
('CAT-DESK', 'ໂຕະ', 2),
('CAT-CHAIR', 'ຕັ່ງ', 2),

-- ລະດັບ 3 (ຍ່ອຍ)
('CAT-LAPTOP', 'ໂນດບຸກ', 3),
('CAT-PC', 'ຄອມພິວເຕີຕັ້ງໂຕະ', 3),
('CAT-LASER', 'ເຄື່ອງພິມ Laser', 3),
('CAT-INKJET', 'ເຄື່ອງພິມ Inkjet', 3);



-- ເບິ່ງຂໍ້ມູນ assets ພ້ອມຊື່ຜູ້ສ້າງ
SELECT 
    a.asset_code,
    a.asset_name,
    a.purchase_cost,
    a.created_by,
    CONCAT(u.first_name, ' ', u.last_name) AS created_by_name,
    u.role AS creator_role,
    d.department_name,
    c.company_name
FROM assets a
LEFT JOIN users u ON a.created_by = u.id
LEFT JOIN departments d ON a.department_id = d.id
LEFT JOIN companies c ON a.company_id = c.id;




-- ເບິ່ງວ່າ current_value ຖືກຕັ້ງອັດຕະໂນມັດຖືກຕ້ອງບໍ
SELECT 
    asset_code,
    purchase_cost,
    accumulated_depreciation,
    current_value
FROM assets;

-- ຖ້າມີການຕັ້ງ depreciation_start_date
SELECT 
    asset_code,
    purchase_date,
    depreciation_start_date,
    useful_life_years,
    depreciation_end_date
FROM assets;



-- ເບິ່ງຂໍ້ມູນແບບລະອຽດຂອງຊັບສິນທັງໝົດ
SELECT 
    asset_code,
    asset_name,
    purchase_date,
    depreciation_start_date,
    useful_life_years,
    depreciation_end_date,
    purchase_cost,
    current_value,
    status
FROM assets
ORDER BY asset_code;


-- ອັບເດດອາຍຸການໃຊ້ງານຕາມປະເພດຊັບສິນ
UPDATE assets SET 
    useful_life_years = 3,
    depreciation_end_date = DATE_ADD(purchase_date, INTERVAL 3 YEAR)
WHERE asset_code = 'AST001';  -- ຄອມພິວເຕີ

UPDATE assets SET 
    useful_life_years = 5,
    depreciation_end_date = DATE_ADD(purchase_date, INTERVAL 5 YEAR)
WHERE asset_code = 'AST002';  -- ເຄື່ອງພິມ

UPDATE assets SET 
    useful_life_years = 10,
    depreciation_end_date = DATE_ADD(purchase_date, INTERVAL 10 YEAR)
WHERE asset_code = 'AST003';  -- ໂຕະ


-- ນັບຈຳນວນຊັບສິນແຍກຕາມສະຖານະ
SELECT 
    status,
    COUNT(*) AS total,
    SUM(purchase_cost) AS total_value
FROM assets
GROUP BY status;

-- ສະເລ່ຍມູນຄ່າຊັບສິນ
SELECT 
    AVG(purchase_cost) AS avg_purchase_cost,
    AVG(current_value) AS avg_current_value,
    MIN(purchase_cost) AS min_value,
    MAX(purchase_cost) AS max_value
FROM assets;