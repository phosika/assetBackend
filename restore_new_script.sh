#!/bin/bash
# restore.sh - ກູ້ຄືນຂໍ້ມູນຈາກ backup
# ວິທີໃຊ້: ./restore.sh <backup_file.sql.gz> [restore_uploads]

set -e

BACKUP_FILE="$1"
RESTORE_UPLOADS="${2:-no}"
BACKUP_DIR="./backups"

DB_USER="root"
DB_PASS="My_root_passw0rd@!2o26"
DB_NAME="asset_db"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# ກວດສອບການໃຊ້ງານ
if [ -z "$BACKUP_FILE" ]; then
    echo -e "${RED}ກະລຸນາລະບຸໄຟລ໌ backup (.sql.gz) ທີ່ຕ້ອງການກູ້ຄືນ${NC}"
    echo "ຕົວຢ່າງ: $0 backups/asset_db_full_20250315_120000.sql.gz"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ ບໍ່ພົບໄຟລ໌ $BACKUP_FILE${NC}"
    exit 1
fi

# ຟັງຊັນກວດຫາ container (ຄືກັນກັບໃນ backup.sh)
find_db_container() {
    if [ -n "$DB_CONTAINER" ]; then
        if docker ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
            echo "$DB_CONTAINER"
            return
        fi
    fi
    CONTAINER=$(docker ps --format '{{.Names}}' | grep -E '.*-db-1$' | head -n1)
    [ -n "$CONTAINER" ] && echo "$CONTAINER" && return
    CONTAINER=$(docker ps --filter "label=com.docker.compose.service=db" --format '{{.Names}}' | head -n1)
    [ -n "$CONTAINER" ] && echo "$CONTAINER" && return
    CONTAINER=$(docker ps --filter "ancestor=mysql:8.0" --format '{{.Names}}' | head -n1)
    [ -n "$CONTAINER" ] && echo "$CONTAINER" && return
    echo ""
}

CONTAINER_NAME=$(find_db_container)
if [ -z "$CONTAINER_NAME" ]; then
    echo -e "${RED}❌ ບໍ່ພົບ MySQL container. ກະລຸນາໃຊ້ 'docker ps' ເພື່ອກວດ.${NC}"
    exit 1
fi

echo -e "${YELLOW}⚠️  ຄຳເຕືອນ: ການ restore ຈະລຶບຂໍ້ມູນປັດຈຸບັນໃນຖານຂໍ້ມູນທັງໝົດ.${NC}"
read -p "ທ່ານຕ້ອງການສືບຕໍ່ບໍ? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo -e "${RED}ຍົກເລີກການ restore.${NC}"
    exit 0
fi

# 1. ກູ້ຄືນຖານຂໍ້ມູນ
echo -e "${YELLOW}💾 ກຳລັງກູ້ຄືນຖານຂໍ້ມູນຈາກ $BACKUP_FILE ...${NC}"
gunzip -c "$BACKUP_FILE" | docker exec -i "$CONTAINER_NAME" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ ກູ້ຄືນຖານຂໍ້ມູນສຳເລັດ${NC}"
else
    echo -e "${RED}❌ ກູ້ຄືນຖານຂໍ້ມູນລົ້ມເຫລວ${NC}"
    exit 1
fi

# 2. ກູ້ຄືນ uploads (ຖ້າຕ້ອງການ)
if [ "$RESTORE_UPLOADS" = "yes" ]; then
    # ດຶງວັນທີຈາກຊື່ໄຟລ໌ backup (ຮູບແບບ YYYYMMDD_HHMMSS)
    DATE_PART=$(basename "$BACKUP_FILE" | grep -oP '\d{8}_\d{6}' || true)
    if [ -z "$DATE_PART" ]; then
        echo -e "${YELLOW}⚠️ ບໍ່ສາມາດດຶງວັນທີຈາກຊື່ໄຟລ໌, ກະລຸນາລະບຸເສັ້ນທາງ uploads backup ເອງ.${NC}"
    else
        UPLOADS_BACKUP="$BACKUP_DIR/uploads_${DATE_PART}.tar.gz"
        if [ -f "$UPLOADS_BACKUP" ]; then
            echo -e "${YELLOW}📁 ກຳລັງກູ້ຄືນ uploads ...${NC}"
            tar -xzf "$UPLOADS_BACKUP" -C "./src"
            echo -e "${GREEN}✅ ກູ້ຄືນ uploads ສຳເລັດ${NC}"
        else
            echo -e "${YELLOW}⚠️ ບໍ່ພົບໄຟລ໌ uploads ທີ່ກົງກັນ (ຄາດຫວັງ: $UPLOADS_BACKUP)${NC}"
        fi
    fi
else
    echo -e "${YELLOW}ℹ️ ຂ້າມການກູ້ຄືນ uploads (ໃຊ້ຕົວເລືອກ 'yes' ຖ້າຕ້ອງການ)${NC}"
fi

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}🎉 ສຳເລັດການກູ້ຄືນຂໍ້ມູນ!${NC}"
echo -e "${GREEN}=========================================${NC}"
