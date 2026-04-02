#!/bin/bash

CONTAINER_NAME="asset_db"
DB_NAME="asset_db"
DB_USER="root"
DB_PASS="My_root_passw0rd@!2o26"

# ສີສຳລັບ output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}Database Information${NC}"
echo -e "${BLUE}================================${NC}"

# 1. ກວດສອບ connection
echo -e "${YELLOW}1. ການເຊື່ອມຕໍ່ຖານຂໍ້ມູນ:${NC}"
if docker exec $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "SELECT '✅ Connected' AS status;" 2>/dev/null; then
    echo -e "${GREEN}   ✅ ເຊື່ອມຕໍ່ສຳເລັດ${NC}"
else
    echo -e "${RED}   ❌ ເຊື່ອມຕໍ່ລົ້ມເຫລວ${NC}"
    exit 1
fi

echo ""

# 2. ສະແດງລາຍການຕາຕະລາງ
echo -e "${YELLOW}2. ຕາຕະລາງໃນຖານຂໍ້ມູນ:${NC}"
docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "
SELECT 
    table_name, 
    table_rows, 
    ROUND(data_length/1024/1024, 2) as data_mb,
    ROUND(index_length/1024/1024, 2) as index_mb
FROM information_schema.tables 
WHERE table_schema = '$DB_NAME'
ORDER BY table_name;" 2>/dev/null

echo ""

# 3. ນັບຈຳນວນ Views
echo -e "${YELLOW}3. Views:${NC}"
VIEW_COUNT=$(docker exec $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.views WHERE table_schema = '$DB_NAME'" 2>/dev/null)
echo -e "   ມີທັງໝົດ: ${GREEN}$VIEW_COUNT${NC} views"
if [ $VIEW_COUNT -gt 0 ]; then
    docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "SELECT table_name as view_name FROM information_schema.views WHERE table_schema = '$DB_NAME'" 2>/dev/null
fi

echo ""

# 4. ນັບຈຳນວນ Stored Procedures
echo -e "${YELLOW}4. Stored Procedures:${NC}"
PROC_COUNT=$(docker exec $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = '$DB_NAME' AND routine_type = 'PROCEDURE'" 2>/dev/null)
echo -e "   ມີທັງໝົດ: ${GREEN}$PROC_COUNT${NC} procedures"
if [ $PROC_COUNT -gt 0 ]; then
    docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "SELECT routine_name FROM information_schema.routines WHERE routine_schema = '$DB_NAME' AND routine_type = 'PROCEDURE'" 2>/dev/null
fi

echo ""

# 5. ນັບຈຳນວນ Triggers
echo -e "${YELLOW}5. Triggers:${NC}"
TRIGGER_COUNT=$(docker exec $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -N -e "SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema = '$DB_NAME'" 2>/dev/null)
echo -e "   ມີທັງໝົດ: ${GREEN}$TRIGGER_COUNT${NC} triggers"
if [ $TRIGGER_COUNT -gt 0 ]; then
    docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "SELECT trigger_name, event_object_table FROM information_schema.triggers WHERE trigger_schema = '$DB_NAME'" 2>/dev/null
fi

echo -e "${BLUE}================================${NC}"
