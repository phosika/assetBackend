#!/bin/bash

# ຕັ້ງຄ່າຕົວແປ
CONTAINER_NAME="api-db-1"
DB_NAME="asset_db"
DB_USER="root"
DB_PASS="My_root_passw0rd@!2o26"
BACKUP_DIR="backups"
DATE=$(date +%Y%m%d_%H%M%S)

# ສ້າງໂຟນເດີ backup
mkdir -p $BACKUP_DIR

echo "🚀 ເລີ່ມສຳຮອງຂໍ້ມູນ..."

# ສຳຮອງໂຄງສ້າງ
echo "📊 ກຳລັງສຳຮອງໂຄງສ້າງ..."
docker exec $CONTAINER_NAME mysqldump -u $DB_USER -p"$DB_PASS" \
  --no-data \
  --routines \
  --triggers \
  --events \
  $DB_NAME > "$BACKUP_DIR/structure_$DATE.sql"

if [ $? -eq 0 ]; then
  echo "✅ ສຳຮອງໂຄງສ້າງສຳເລັດ"
else
  echo "❌ ສຳຮອງໂຄງສ້າງລົ້ມເຫລວ"
  exit 1
fi

# ສຳຮອງຂໍ້ມູນເຕັມ
echo "💾 ກຳລັງສຳຮອງຂໍ້ມູນເຕັມ..."
docker exec $CONTAINER_NAME mysqldump -u $DB_USER -p"$DB_PASS" \
  --routines \
  --triggers \
  --events \
  --complete-insert \
  $DB_NAME > "$BACKUP_DIR/full_$DATE.sql"

if [ $? -eq 0 ]; then
  echo "✅ ສຳຮອງຂໍ້ມູນເຕັມສຳເລັດ"
else
  echo "❌ �ຳຮອງຂໍ້ມູນເຕັມລົ້ມເຫລວ"
  exit 1
fi

# ບີບອັດໄຟລ໌
echo "🗜️  ກຳລັງບີບອັດໄຟລ໌..."
gzip "$BACKUP_DIR/structure_$DATE.sql"
gzip "$BACKUP_DIR/full_$DATE.sql"

# ສະແດງຂໍ້ມູນ
echo ""
echo "📁 ບ່ອນເກັບ: $BACKUP_DIR/"
ls -lh "$BACKUP_DIR/" | grep "$DATE"

echo ""
echo "✅✅ ສຳຮອງຂໍ້ມູນສຳເລັດ! ✅✅"
