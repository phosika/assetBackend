-- ຄິດໄລ່ຄ່າເສື່ອມລາຄາຕໍ່ເດືອນ
SELECT 
    asset_code,
    asset_name,
    purchase_cost,
    useful_life_years,
    (purchase_cost / (useful_life_years * 12)) AS monthly_depreciation,
    purchase_cost / useful_life_years AS yearly_depreciation
FROM assets
WHERE useful_life_years IS NOT NULL;



CREATE TABLE inventory_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ລະຫັດສິນຄ້າ',
    item_name VARCHAR(200) NOT NULL COMMENT 'ຊື່ສິນຄ້າ',
    item_name_en VARCHAR(200),
    category_id INT NOT NULL COMMENT 'ປະເພດສິນຄ້າ',
    description TEXT,
    brand VARCHAR(100),
    model VARCHAR(100),
    specification TEXT COMMENT 'ຂໍ້ມູນສະເພາະ',
    
    -- ຂໍ້ມູນການຊື້-ຂາຍ
    purchase_price DECIMAL(15,2) COMMENT 'ລາຄາຊື້',
    selling_price DECIMAL(15,2) COMMENT 'ລາຄາຂາຍ',
    supplier_id INT,
    reorder_point INT DEFAULT 0 COMMENT 'ຈຸດທີ່ຕ້ອງສັ່ງຊື້ເພີ່ມ',
    minimum_stock INT DEFAULT 0 COMMENT 'ສະຕ໋ອກຕ່ຳສຸດ',
    maximum_stock INT DEFAULT 0 COMMENT 'ສະຕ໋ອກສູງສຸດ',
    
    -- ສະຖານະ
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    
    FOREIGN KEY (category_id) REFERENCES asset_categories(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);


CREATE TABLE inventory_stock (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id INT NOT NULL,
    location_id INT COMMENT 'ສະຖານທີ່ເກັບສິນຄ້າ',
    batch_number VARCHAR(100) COMMENT 'ເລກທີລອດ',
    serial_number VARCHAR(100) COMMENT 'ເລກຊີຣຽວ (ສຳລັບສິນຄ້າທີ່ມີ)',
    
    quantity INT NOT NULL DEFAULT 0 COMMENT 'ຈຳນວນ',
    unit VARCHAR(20) DEFAULT 'ຊິ້ນ',
    
    purchase_price DECIMAL(15,2) COMMENT 'ລາຄາຊື້ຕໍ່ຫົວໜ່ວຍ',
    selling_price DECIMAL(15,2) COMMENT 'ລາຄາຂາຍຕໍ່ຫົວໜ່ວຍ',
    
    purchase_date DATE,
    expiry_date DATE COMMENT 'ວັນໝົດອາຍຸ (ຖ້າມີ)',
    warranty_period INT COMMENT 'ອາຍຸການຮັບປະກັນ (ເດືອນ)',
    
    status ENUM('in_stock', 'reserved', 'sold', 'damaged', 'transferred') DEFAULT 'in_stock',
    notes TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);


CREATE TABLE purchase_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'ເລກທີ PO',
    supplier_id INT NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery DATE,
    delivery_date DATE,
    
    subtotal DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    tax DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    payment_date DATE,
    payment_method VARCHAR(50),
    
    status ENUM('draft', 'ordered', 'received', 'cancelled') DEFAULT 'draft',
    invoice_number VARCHAR(100),
    invoice_file_path VARCHAR(500),
    
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);


CREATE TABLE purchase_order_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    po_id INT NOT NULL,
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    unit_price DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    total_price DECIMAL(15,2) NOT NULL,
    
    warranty_period INT COMMENT 'ອາຍຸການຮັບປະກັນ (ເດືອນ)',
    notes TEXT,
    
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id)
);

CREATE TABLE sales_orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    so_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'ເລກທີ SO',
    customer_name VARCHAR(200) NOT NULL,
    customer_phone VARCHAR(20),
    customer_email VARCHAR(100),
    customer_address TEXT,
    
    order_date DATE NOT NULL,
    delivery_date DATE,
    
    subtotal DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    tax DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    payment_date DATE,
    payment_method VARCHAR(50),
    
    sale_type ENUM('retail', 'wholesale', 'company') DEFAULT 'retail',
    company_id INT COMMENT 'ຖ້າຂາຍໃຫ້ບໍລິສັດ',
    department_id INT COMMENT 'ຖ້າຂາຍໃຫ້ພະແນກ',
    
    status ENUM('draft', 'confirmed', 'delivered', 'cancelled') DEFAULT 'draft',
    invoice_number VARCHAR(100),
    invoice_file_path VARCHAR(500),
    
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);


CREATE TABLE sales_order_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    so_id INT NOT NULL,
    stock_id INT NOT NULL COMMENT 'ອ້າງອີງໃສ່ inventory_stock',
    item_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount DECIMAL(15,2) DEFAULT 0,
    total_price DECIMAL(15,2) NOT NULL,
    
    is_converted_to_asset BOOLEAN DEFAULT false COMMENT 'ປ່ຽນເປັນຊັບສິນແລ້ວບໍ?',
    asset_id INT COMMENT 'ຖ້າປ່ຽນເປັນຊັບສິນ, ອ້າງອີງໃສ່ assets.id',
    
    notes TEXT,
    
    FOREIGN KEY (so_id) REFERENCES sales_orders(id),
    FOREIGN KEY (stock_id) REFERENCES inventory_stock(id),
    FOREIGN KEY (item_id) REFERENCES inventory_items(id),
    FOREIGN KEY (asset_id) REFERENCES assets(id)
);


CREATE TABLE stock_movements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    stock_id INT NOT NULL,
    movement_type ENUM('purchase_in', 'sale_out', 'transfer', 'adjustment', 'damage', 'return') NOT NULL,
    reference_type VARCHAR(50) COMMENT 'po, so, adjustment',
    reference_id INT,
    quantity_before INT NOT NULL,
    quantity_change INT NOT NULL,
    quantity_after INT NOT NULL,
    
    unit_price DECIMAL(15,2),
    total_value DECIMAL(15,2),
    
    movement_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    created_by INT NOT NULL,
    
    FOREIGN KEY (stock_id) REFERENCES inventory_stock(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

 
    -- ສ້າງຊັບສິນໃໝ່

    -- ສິນຄ້າໃກ້ໝົດສະຕ໋ອກ
SELECT 
    ii.item_code,
    ii.item_name,
    SUM(is2.quantity) AS current_stock,
    ii.reorder_point,
    ii.minimum_stock
FROM inventory_items ii
LEFT JOIN inventory_stock is2 ON ii.id = is2.item_id AND is2.status = 'in_stock'
GROUP BY ii.id
HAVING current_stock <= ii.reorder_point;

-- ມູນຄ່າສິນຄ້າຄົງຄັງ
SELECT 
    SUM(is2.quantity * is2.purchase_price) AS total_inventory_value,
    COUNT(DISTINCT is2.item_id) AS total_items,
    SUM(is2.quantity) AS total_units
FROM inventory_stock is2
WHERE is2.status = 'in_stock';


CREATE VIEW vw_inventory_summary AS
SELECT 
    ii.id,
    ii.item_code,
    ii.item_name,
    ii.brand,
    ii.model,
    ac.category_name,
    s.supplier_name,
    COUNT(is2.id) AS total_stock,
    SUM(is2.quantity) AS total_quantity,
    AVG(is2.purchase_price) AS avg_purchase_price,
    AVG(is2.selling_price) AS avg_selling_price,
    SUM(is2.quantity * is2.purchase_price) AS total_inventory_value
FROM inventory_items ii
LEFT JOIN asset_categories ac ON ii.category_id = ac.id
LEFT JOIN suppliers s ON ii.supplier_id = s.id
LEFT JOIN inventory_stock is2 ON ii.id = is2.item_id AND is2.status = 'in_stock'
GROUP BY ii.id;


 CREATE PROCEDURE convert_sold_item_to_asset(
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
    
END;


CREATE PROCEDURE purchase_items(
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
    
END;



CREATE PROCEDURE sell_item(
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
    
END;



CREATE PROCEDURE check_inventory_stock(
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
    
END;


SHOW PROCEDURE STATUS WHERE db = DATABASE();

CALL check_inventory_stock(NULL);


-- ຊື້ຄອມພິວເຕີ 5 ເຄື່ອງ
CALL purchase_items(
    'PO-2026-003',      -- po_number
    1,                  -- supplier_id
    1,                  -- item_id
    5,                  -- quantity
    2800000,            -- purchase_price
    3500000,            -- selling_price
    NULL,               -- serial_number (NULL ຖ້າຊື້ຫຼາຍເຄື່ອງ)
    24,                 -- warranty_period (ເດືອນ)
    2                   -- created_by
);

SELECT id, item_code, item_name 
FROM inventory_items;

-- ສ້າງຂໍ້ມູນສິນຄ້າໄອທີ
INSERT INTO inventory_items (
    item_code, 
    item_name, 
    category_id, 
    brand, 
    model, 
    purchase_price, 
    selling_price, 
    supplier_id,
    reorder_point,
    minimum_stock,
    created_by
) VALUES 
('IT-LAP-001', 'ໂນດບຸກ Dell Inspiron', 1, 'Dell', 'Inspiron 15', 2800000, 3500000, 1, 2, 1, 2),
('IT-LAP-002', 'ໂນດບຸກ HP Pavilion', 1, 'HP', 'Pavilion 14', 2600000, 3300000, 1, 2, 1, 2),
('IT-PRN-001', 'ເຄື່ອງພິມ HP LaserJet', 1, 'HP', 'LaserJet Pro', 1800000, 2500000, 1, 1, 1, 2),
('IT-PRN-002', 'ເຄື່ອງພິມ Canon', 1, 'Canon', 'Pixma', 1200000, 1800000, 1, 1, 1, 2),
('IT-ACC-001', 'ເມົ້າສ໌ Logitech', 1, 'Logitech', 'MX Master', 250000, 450000, 1, 5, 3, 2),
('IT-ACC-002', 'ແປ້ນພິມ Mechanical', 1, 'Keychron', 'K2', 350000, 550000, 1, 5, 3, 2);

SELECT COUNT(*) AS total_items FROM inventory_items;

-- ເບິ່ງລາຍການທັງໝົດ
SELECT id, item_code, item_name, purchase_price, selling_price 
FROM inventory_items;


-- ຊື້ໂນດບຸກ Dell 5 ເຄື່ອງ (item_id = 1)
CALL purchase_items(
    'PO-2026-004',      -- po_number
    1,                  -- supplier_id
    1,                  -- item_id (ຕ້ອງມີໃນ inventory_items)
    5,                  -- quantity
    2800000,            -- purchase_price
    3500000,            -- selling_price
    NULL,               -- serial_number
    24,                 -- warranty_period
    2                   -- created_by
);

SELECT * FROM purchase_orders ORDER BY id DESC;

SELECT * FROM purchase_order_details ORDER BY id DESC;

SELECT * FROM inventory_stock ORDER BY id DESC;



-- 1. ຊື້ສິນຄ້າເຂົ້າມາ
CALL purchase_items('PO-2026-005', 1, 1, 10, 2800000, 3500000, NULL, 24, 2);

-- 2. ກວດສອບສະຕ໋ອກ
CALL check_inventory_stock(1);

-- 3. ຂາຍສິນຄ້າ (ສົມມຸດວ່າ stock_id = 1)
CALL sell_item('SO-2026-005', 'ບໍລິສັດ ງ ຈຳກັດ', 1, 1, 3500000, 2);

-- 4. ປ່ຽນເປັນຊັບສິນ (ສົມມຸດວ່າ sales_order_detail_id = 1)
CALL convert_sold_item_to_asset(1, 'AST009', 'ໂນດບຸກຂາຍ', 4, 2);

-- 5. ກວດສອບຂໍ້ມູນສຸດທ້າຍ
SELECT 'inventory_stock' AS table_name, COUNT(*) AS count FROM inventory_stock
UNION ALL
SELECT 'sales_orders', COUNT(*) FROM sales_orders
UNION ALL
SELECT 'sales_order_details', COUNT(*) FROM sales_order_details
UNION ALL
SELECT 'assets', COUNT(*) FROM assets WHERE notes LIKE '%ປ່ຽນມາຈາກສິນຄ້າຂາຍ%';




CREATE PROCEDURE create_new_asset(
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
    
END;


CREATE PROCEDURE create_new_asset_advanced(
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
    
END;


-- ເບິ່ງຊັບສິນທີ່ຫາກໍ່ສ້າງ
SELECT 
    asset_code,
    asset_name,
    purchase_cost,
    current_value,
    useful_life_years,
    depreciation_end_date,
    status
FROM assets
WHERE asset_code IN ('AST010', 'AST011');

Select * from assets


SELECT 
    id,
    category_code,
    category_name,
    level,
    parent_id,
    path,
    CONCAT(REPEAT('  ', level-1), '├─ ', category_name) AS tree_view
FROM asset_categories
ORDER BY path;


-- ຄົ້ນຫາທຸກປະເພດຍ່ອຍພາຍໃຕ້ IT (id=1)
SELECT * FROM asset_categories 
WHERE path LIKE '1/%' OR path = '1';

-- ຄົ້ນຫາທຸກປະເພດຍ່ອຍພາຍໃຕ້ IT-COM (id=4)
SELECT * FROM asset_categories 
WHERE path LIKE '1/4/%' OR path = '1/4';


CREATE PROCEDURE create_category_with_path(
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
END;


-- ສ້າງລະດັບ 1
CALL create_category_with_path('SERVER', 'ເຊີບເວີ', NULL);

-- ສ້າງລະດັບ 2 (ພາຍໃຕ້ SERVER)
CALL create_category_with_path('SERVER-RACK', 'ເຊີບເວີແບບ Rack', 12);

-- ສ້າງລະດັບ 3 (ພາຍໃຕ້ SERVER-RACK)
CALL create_category_with_path('SERVER-DELL', 'ເຊີບເວີ Dell', 13);


-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS check_category_level;

-- ສ້າງ Trigger ໃໝ່
CREATE TRIGGER check_category_level
BEFORE INSERT ON asset_categories
FOR EACH ROW
BEGIN
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
    
END;

-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS update_category_path;

-- ສ້າງ Trigger ສຳລັບອັບເດດ path ຫຼັງຈາກ INSERT
CREATE TRIGGER update_category_path
AFTER INSERT ON asset_categories
FOR EACH ROW
BEGIN
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
END;


-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS check_category_level;

-- ສ້າງ Trigger ໃໝ່ (ແບບຖືກຕ້ອງ)
CREATE TRIGGER check_category_level
BEFORE INSERT ON asset_categories
FOR EACH ROW
BEGIN
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
    
END;


-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS update_category_path;

-- ສ້າງ Trigger ສຳລັບອັບເດດ path ຫຼັງຈາກ INSERT
CREATE TRIGGER update_category_path
AFTER INSERT ON asset_categories
FOR EACH ROW
BEGIN
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
END;

-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS update_category_path_on_update;

-- ສ້າງ Trigger ສຳລັບອັບເດດ path ເມື່ອມີການປ່ຽນແປງ parent
CREATE TRIGGER update_category_path_on_update
BEFORE UPDATE ON asset_categories
FOR EACH ROW
BEGIN
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
    
END;

-- ລຶບ Trigger ເກົ່າຖ້າມີ
DROP TRIGGER IF EXISTS prevent_category_deletion;

-- ສ້າງ Trigger ປ້ອງກັນການລຶບ category ທີ່ມີຍ່ອຍ
CREATE TRIGGER prevent_category_deletion
BEFORE DELETE ON asset_categories
FOR EACH ROW
BEGIN
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
    
END;


-- ທົດສອບການສ້າງ level 1
INSERT INTO asset_categories (category_code, category_name) 
VALUES ('TEST-L1', 'ທົດສອບລະດັບ 1');

-- ທົດສອບການສ້າງ level 2
INSERT INTO asset_categories (category_code, category_name, parent_id) 
VALUES ('TEST-L2', 'ທົດສອບລະດັບ 2', 1);

-- ທົດສອບການສ້າງ level 3
INSERT INTO asset_categories (category_code, category_name, parent_id) 
VALUES ('TEST-L3', 'ທົດສອບລະດັບ 3', 2);

-- ທົດສອບການສ້າງ level 4 (ຄວນມີ error)
INSERT INTO asset_categories (category_code, category_name, parent_id) 
VALUES ('TEST-L4', 'ທົດສອບລະດັບ 4', 3);

-- ເບິ່ງຜົນລັບ
SELECT id, category_code, category_name, level, parent_id, path 
FROM asset_categories 
WHERE category_code LIKE 'TEST-%'
ORDER BY path;

-- ທົດສອບການລຶບ (ຄວນມີ error ຖ້າມີຍ່ອຍ)
DELETE FROM asset_categories WHERE id = 1;


