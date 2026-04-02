#!/bin/bash

# ຕັ້ງຄ່າ (ອັບເດດຕາມ container ໃໝ່)
CONTAINER_NAME="backend-db-1"  # ປ່ຽນຈາກ api-db-1 ເປັນ asset_db
DB_NAME="asset_db"
DB_USER="root"
DB_PASS="My_root_passw0rd@!2o26"
BACKUP_DIR="backups"

# ສີສຳລັບ output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}Database Restore Script${NC}"
echo -e "${BLUE}================================${NC}"

# ກວດສອບວ່າ container ກຳລັງຮັນຢູ່
if ! docker ps | grep -q $CONTAINER_NAME; then
    echo -e "${RED}❌ Container $CONTAINER_NAME ບໍ່ໄດ້ຮັນ${NC}"
    echo "ກະລຸນາເລີ່ມ container ກ່ອນ: docker start $CONTAINER_NAME"
    exit 1
fi

# ສະແດງລາຍການ backup ທີ່ມີ
echo -e "${YELLOW}📋 ລາຍການໄຟລ໌ Backup ທີ່ມີ:${NC}"
echo "--------------------------------"
ls -lh $BACKUP_DIR | grep -E "full_.*\.sql\.gz|structure_.*\.sql\.gz" | nl
echo "--------------------------------"

if [ $(ls $BACKUP_DIR | grep -c "\.sql\.gz") -eq 0 ]; then
    echo -e "${RED}❌ ບໍ່ພົບໄຟລ໌ backup ໃນໂຟນເດີ $BACKUP_DIR${NC}"
    exit 1
fi

echo ""
read -p "ເລືອກປະເພດການ restore (1=Full, 2=Structure only): " RESTORE_TYPE

case $RESTORE_TYPE in
    1)
        PATTERN="full_"
        TYPE_NAME="Full"
        ;;
    2)
        PATTERN="structure_"
        TYPE_NAME="Structure"
        ;;
    *)
        echo -e "${RED}❌ ເລືອກບໍ່ຖືກຕ້ອງ${NC}"
        exit 1
        ;;
esac

# ສະແດງລາຍການຕາມປະເພດ
echo -e "${YELLOW}📋 ລາຍການ ${TYPE_NAME} backup:${NC}"
FILES=($(ls $BACKUP_DIR | grep "${PATTERN}.*\.sql\.gz" | sort -r))
for i in "${!FILES[@]}"; do
    FILE_SIZE=$(du -h "$BACKUP_DIR/${FILES[$i]}" | cut -f1)
    echo "   $((i+1)). ${FILES[$i]} (${FILE_SIZE})"
done

echo ""
read -p "ເລືອກໝາຍເລກໄຟລ໌ທີ່ຕ້ອງການ restore: " FILE_NUM

if [ -z "${FILES[$((FILE_NUM-1))]}" ]; then
    echo -e "${RED}❌ ເລືອກບໍ່ຖືກຕ້ອງ${NC}"
    exit 1
fi

SELECTED_FILE="${FILES[$((FILE_NUM-1))]}"
echo -e "${GREEN}✅ ເລືອກໄຟລ໌: ${SELECTED_FILE}${NC}"

echo ""
echo -e "${YELLOW}⚠️  ຄຳເຕືອນ: ການ restore ຈະລຶບຂໍ້ມູນປັດຈຸບັນທັງໝົດ${NC}"
read -p "ທ່ານແນ່ໃຈບໍ່ວ່າຕ້ອງການດຳເນີນການ? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
    echo -e "${RED}❌ ຍົກເລີກການ restore${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}🚀 ກຳລັງເລີ່ມ restore...${NC}"

# ສຳຮອງຖານຂໍ້ມູນປັດຈຸບັນກ່ອນ restore
PRE_RESTORE_BACKUP="pre_restore_$(date +%Y%m%d_%H%M%S).sql"
echo -e "${YELLOW}📦 ສຳຮອງຖານຂໍ້ມູນປັດຈຸບັນ: ${PRE_RESTORE_BACKUP}${NC}"
docker exec $CONTAINER_NAME mysqldump -u $DB_USER -p"$DB_PASS" \
  --routines \
  --triggers \
  --events \
  --complete-insert \
  $DB_NAME > "$BACKUP_DIR/$PRE_RESTORE_BACKUP" 2>/dev/null

# Restore
echo -e "${YELLOW}🔄 ກຳລັງ restore ຂໍ້ມູນ...${NC}"
gunzip -c "$BACKUP_DIR/$SELECTED_FILE" | docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" $DB_NAME 2>/dev/null

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Restore ສຳເລັດ!${NC}"
    
    # ສະແດງຂໍ້ມູນສະຫຼຸບ
    echo ""
    echo -e "${BLUE}📊 ຂໍ້ມູນຫຼັງ Restore:${NC}"
    echo "================================"
    
    docker exec -i $CONTAINER_NAME mysql -u $DB_USER -p"$DB_PASS" -e "
    SELECT 'users' as table_name, COUNT(*) as count FROM $DB_NAME.users
    UNION ALL
    SELECT 'companies', COUNT(*) FROM $DB_NAME.companies
    UNION ALL
    SELECT 'departments', COUNT(*) FROM $DB_NAME.departments
    UNION ALL
    SELECT 'assets', COUNT(*) FROM $DB_NAME.assets;" 2>/dev/null
    
    echo "================================"
else
    echo -e "${RED}❌ Restore ລົ້ມເຫລວ${NC}"
    echo "ກະລຸນາກວດສອບໄຟລ໌ backup ວ່າສາມາດໃຊ້ງານໄດ້"
fi
