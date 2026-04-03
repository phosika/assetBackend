#!/bin/bash
# restore.sh - ກູ້ຄືນຂໍ້ມູນຈາກ backup (ຮອງຮັບ sudo)

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
    echo "ຕົວຢ່າງ: $0 backups/asset_db_full_20260403_093357.sql.gz"
    echo ""
    echo "ໄຟລ໌ທີ່ມີຢູ່:"
    ls -lh backups/*.sql.gz 2>/dev/null || echo "   (ບໍ່ພົບໄຟລ໌ backup)"
    exit 1
fi

if [ ! -f "$BACKUP_FILE" ]; then
    echo -e "${RED}❌ ບໍ່ພົບໄຟລ໌ $BACKUP_FILE${NC}"
    echo ""
    echo "ໄຟລ໌ທີ່ມີຢູ່:"
    ls -lh backups/*.sql.gz 2>/dev/null || echo "   (ບໍ່ພົບໄຟລ໌ backup)"
    exit 1
fi

# ຟັງຊັນກວດຫາ container (ໃຊ້ sudo ຖ້າຈຳເປັນ)
find_db_container() {
    # ລອງໃຊ້ docker ທຳມະດາກ່ອນ
    local DOCKER_CMD="docker"
    if ! docker ps &>/dev/null; then
        # ຖ້າບໍ່ມີສິດ, ໃຊ້ sudo
        DOCKER_CMD="sudo docker"
    fi
    
    # ຖ້າກຳນົດຜ່ານຕົວແປແວດລ້ອມ, ໃຊ້ຄ່ານັ້ນ
    if [ -n "$DB_CONTAINER" ]; then
        if $DOCKER_CMD ps --format '{{.Names}}' | grep -q "^${DB_CONTAINER}$"; then
            echo "$DB_CONTAINER"
            echo "$DOCKER_CMD" > /tmp/docker_cmd.txt
            return
        fi
    fi
    
    # ຊອກຫາ container ທີ່ມີຊື່ລົງທ້າຍດ້ວຍ "-db-1"
    CONTAINER=$($DOCKER_CMD ps --format '{{.Names}}' | grep -E '.*-db-1$' | head -n1)
    if [ -n "$CONTAINER" ]; then
        echo "$CONTAINER"
        echo "$DOCKER_CMD" > /tmp/docker_cmd.txt
        return
    fi
    
    # ຊອກຫາ container ທີ່ມີ label com.docker.compose.service=db
    CONTAINER=$($DOCKER_CMD ps --filter "label=com.docker.compose.service=db" --format '{{.Names}}' | head -n1)
    if [ -n "$CONTAINER" ]; then
        echo "$CONTAINER"
        echo "$DOCKER_CMD" > /tmp/docker_cmd.txt
        return
    fi
    
    # ຊອກຫາ container ທີ່ໃຊ້ image mysql:8.0
    CONTAINER=$($DOCKER_CMD ps --filter "ancestor=mysql:8.0" --format '{{.Names}}' | head -n1)
    if [ -n "$CONTAINER" ]; then
        echo "$CONTAINER"
        echo "$DOCKER_CMD" > /tmp/docker_cmd.txt
        return
    fi
    
    # ຖ້າບໍ່ພົບເລີຍ
    echo ""
}

# ກວດຫາ container ແລະ docker command
CONTAINER_NAME=$(find_db_container)
if [ -z "$CONTAINER_NAME" ]; then
    echo -e "${RED}❌ ບໍ່ພົບ MySQL container.${NC}"
    echo "ກະລຸນາໃຊ້ 'sudo docker ps' ເພື່ອກວດສະພາບ container ທີ່ກຳລັງແລ່ນ."
    exit 1
fi

# ອ່ານ docker command ທີ່ໃຊ້
DOCKER_CMD=$(cat /tmp/docker_cmd.txt 2>/dev/null || echo "docker")
rm -f /tmp/docker_cmd.txt

echo -e "${YELLOW}🔍 ພົບ container: $CONTAINER_NAME${NC}"
echo -e "${YELLOW}🔍 ໃຊ້ຄຳສັ່ງ: $DOCKER_CMD${NC}"

echo -e "${YELLOW}⚠️  ຄຳເຕືອນ: ການ restore ຈະລຶບຂໍ້ມູນປັດຈຸບັນໃນຖານຂໍ້ມູນທັງໝົດ.${NC}"
read -p "ທ່ານຕ້ອງການສືບຕໍ່ບໍ? (yes/no): " confirm
if [ "$confirm" != "yes" ]; then
    echo -e "${RED}ຍົກເລີກການ restore.${NC}"
    exit 0
fi

# 1. ກູ້ຄືນຖານຂໍ້ມູນ
echo -e "${YELLOW}💾 ກຳລັງກູ້ຄືນຖານຂໍ້ມູນຈາກ $BACKUP_FILE ...${NC}"
gunzip -c "$BACKUP_FILE" | $DOCKER_CMD exec -i "$CONTAINER_NAME" mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" 2>&1 | grep -v "Using a password" || true

if [ ${PIPESTATUS[0]} -eq 0 ]; then
    echo -e "${GREEN}✅ ກູ້ຄືນຖານຂໍ້ມູນສຳເລັດ${NC}"
else
    echo -e "${RED}❌ ກູ້ຄືນຖານຂໍ້ມູນລົ້ມເຫລວ${NC}"
    exit 1
fi

# 2. ກູ້ຄືນ uploads (ຖ້າຕ້ອງການ)
if [ "$RESTORE_UPLOADS" = "yes" ]; then
    # ດຶງວັນທີຈາກຊື່ໄຟລ໌ backup
    DATE_PART=$(basename "$BACKUP_FILE" | grep -oE '[0-9]{8}_[0-9]{6}' || true)
    if [ -z "$DATE_PART" ]; then
        echo -e "${YELLOW}⚠️ ບໍ່ສາມາດດຶງວັນທີຈາກຊື່ໄຟລ໌, ຂ້າມການກູ້ຄືນ uploads.${NC}"
    else
        UPLOADS_BACKUP="$BACKUP_DIR/uploads_${DATE_PART}.tar.gz"
        if [ -f "$UPLOADS_BACKUP" ]; then
            echo -e "${YELLOW}📁 ກຳລັງກູ້ຄືນ uploads ...${NC}"
            mkdir -p ./src
            tar -xzf "$UPLOADS_BACKUP" -C ./src
            echo -e "${GREEN}✅ ກູ້ຄືນ uploads ສຳເລັດ${NC}"
        else
            echo -e "${YELLOW}⚠️ ບໍ່ພົບໄຟລ໌ uploads ທີ່ກົງກັນ: $UPLOADS_BACKUP${NC}"
        fi
    fi
else
    echo -e "${YELLOW}ℹ️ ຂ້າມການກູ້ຄືນ uploads (ໃຊ້ຕົວເລືອກ 'yes' ຖ້າຕ້ອງການ)${NC}"
fi

echo -e "${GREEN}=========================================${NC}"
echo -e "${GREEN}🎉 ສຳເລັດການກູ້ຄືນຂໍ້ມູນ!${NC}"
echo -e "${GREEN}=========================================${NC}"
