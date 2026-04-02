#!/bin/bash

# ຕັ້ງຄ່າຕົວແປ (ອັບເດດຕາມ container ໃໝ່)
CONTAINER_NAME="asset_db"  # ປ່ຽນຈາກ api-db-1 ເປັນ asset_db
DB_NAME="asset_db"
DB_USER="root"
DB_PASS="My_root_passw0rd@!2o26"
BACKUP_DIR="backups"
DATE=$(date +%Y%m%d_%H%M%S)

# ສີສຳລັບ output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# ສ້າງໂຟນເດີ backup
mkdir -p $BACKUP_DIR

echo -e "${YELLOW}🚀 ເລີ່ມສຳຮອງຂໍ້ມູນ: $(date)${NC}"
echo "==================================="

# 1. ສຳຮອງໂຄງສ້າງ (Structure only)
echo -e "${YELLOW}📊 1. ກຳລັງສຳຮອງໂຄງສ້າງ...${NC}"
STRUCTURE_FILE="$BACKUP_DIR/structure_${DATE}.sql"

if docker exec $CONTAINER_NAME mysqldump -u $DB_USER -p"$DB_PASS" \
  --no-data \
  --routines \
  --triggers \
  --events \
  $DB_NAME > "$STRUCTURE_FILE" 2>/dev/null; then
  
  echo -e "${GREEN}   ✅ ສຳຮອງໂຄງສ້າງສຳເລັດ${NC}"
else
  echo -e "${RED}   ❌ ສຳຮອງໂຄງສ້າງລົ້ມເຫລວ${NC}"
  exit 1
fi

# 2. ສຳຮອງຂໍ້ມູນເຕັມ (Full backup)
echo -e "${YELLOW}💾 2. ກຳລັງສຳຮອງຂໍ້ມູນເຕັມ...${NC}"
FULL_FILE="$BACKUP_DIR/full_${DATE}.sql"

if docker exec $CONTAINER_NAME mysqldump -u $DB_USER -p"$DB_PASS" \
  --routines \
  --triggers \
  --events \
  --complete-insert \
  $DB_NAME > "$FULL_FILE" 2>/dev/null; then
  
  # ຄຳນວນຂະໜາດໄຟລ໌
  FILE_SIZE=$(du -h "$FULL_FILE" | cut -f1)
  echo -e "${GREEN}   ✅ ສຳຮອງຂໍ້ມູນເຕັມສຳເລັດ (ຂະໜາດ: $FILE_SIZE)${NC}"
else
  echo -e "${RED}   ❌ ສຳຮອງຂໍ້ມູນເຕັມລົ້ມເຫລວ${NC}"
  exit 1
fi

# 3. ບີບອັດໄຟລ໌
echo -e "${YELLOW}🗜️  3. ກຳລັງບີບອັດໄຟລ໌...${NC}"
gzip "$STRUCTURE_FILE"
gzip "$FULL_FILE"

echo -e "${GREEN}   ✅ ບີບອັດສຳເລັດ${NC}"

# 4. ສະແດງຂໍ້ມູນສະຫຼຸບ
echo ""
echo "==================================="
echo -e "${GREEN}✅✅ ສຳຮອງຂໍ້ມູນສຳເລັດ! ✅✅${NC}"
echo "==================================="
echo -e "${YELLOW}📊 ສະຫຼຸບ:${NC}"
echo "   - ເວລາ: $DATE"
echo "   - Container: $CONTAINER_NAME"
echo "   - Database: $DB_NAME"
echo ""
echo -e "${YELLOW}📁 ໄຟລ໌ທີ່ສ້າງ:${NC}"
ls -lh "$BACKUP_DIR" | grep "$DATE"
echo "==================================="
