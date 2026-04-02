#!/bin/bash

# ສີສຳລັບ output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}================================${NC}"
echo -e "${GREEN}Docker Management Script${NC}"
echo -e "${BLUE}================================${NC}"

case "$1" in
    status)
        echo -e "${YELLOW}📊 ສະຖານະ Docker Containers:${NC}"
        docker ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
        ;;
        
    logs)
        echo -e "${YELLOW}📋 ກຳລັງສະແດງ Logs (ກົດ Ctrl+C ເພື່ອອອກ)${NC}"
        docker-compose logs -f
        ;;
        
    restart)
        echo -e "${YELLOW}🔄 ກຳລັງ Restart ທຸກ containers...${NC}"
        docker-compose restart
        echo -e "${GREEN}✅ Restart ສຳເລັດ${NC}"
        ;;
        
    stop)
        echo -e "${YELLOW}🛑 ກຳລັງຢຸດທຸກ containers...${NC}"
        docker-compose stop
        echo -e "${GREEN}✅ ຢຸດສຳເລັດ${NC}"
        ;;
        
    start)
        echo -e "${YELLOW}🚀 ກຳລັງເລີ່ມທຸກ containers...${NC}"
        docker-compose start
        echo -e "${GREEN}✅ ເລີ່ມສຳເລັດ${NC}"
        ;;
        
    backup)
        ./backup_new.sh
        ;;
        
    restore)
        ./restore_new.sh
        ;;
        
    check)
        ./check_database.sh
        ;;
        
    *)
        echo "ການໃຊ້ງານ: $0 {status|logs|restart|stop|start|backup|restore|check}"
        echo ""
        echo "  status    - ເບິ່ງສະຖານະ containers"
        echo "  logs      - ເບິ່ງ logs"
        echo "  restart   - Restart ທຸກ containers"
        echo "  stop      - ຢຸດທຸກ containers"
        echo "  start     - ເລີ່ມທຸກ containers"
        echo "  backup    - ສຳຮອງຖານຂໍ້ມູນ"
        echo "  restore   - ກູ້ຄືນຖານຂໍ້ມູນ"
        echo "  check     - ກວດສອບຂໍ້ມູນໃນຖານຂໍ້ມູນ"
        ;;
esac
