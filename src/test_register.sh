#!/bin/bash
# test_register.sh - ທົດສອບການລົງທະບຽນ

echo "====================================="
echo "ທົດສອບການລົງທະບຽນຜູ້ໃຊ້ງານ"
echo "====================================="

# ທົດສອບ 1: ລົງທະບຽນສຳເລັດ
echo -e "\n1. ທົດສອບລົງທະບຽນສຳເລັດ:"
curl -X POST http://localhost:8081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser1",
    "email": "test1@example.com",
    "password": "Test@123456",
    "full_name": "Test User 1"
  }' | jq .

# ທົດສອບ 2: ລົງທະບຽນດ້ວຍ username ສັ້ນເກີນໄປ
echo -e "\n2. ທົດສອບລົງທະບຽນດ້ວຍ username ສັ້ນເກີນໄປ:"
curl -X POST http://localhost:8081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "ab",
    "email": "test2@example.com",
    "password": "Test@123456"
  }' | jq .

# ທົດສອບ 3: ລົງທະບຽນດ້ວຍ password ສັ້ນເກີນໄປ
echo -e "\n3. ທົດສອບລົງທະບຽນດ້ວຍ password ສັ້ນເກີນໄປ:"
curl -X POST http://localhost:8081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser3",
    "email": "test3@example.com",
    "password": "123"
  }' | jq .

# ທົດສອບ 4: ລົງທະບຽນດ້ວຍ email ບໍ່ຖືກຕ້ອງ
echo -e "\n4. ທົດສອບລົງທະບຽນດ້ວຍ email ບໍ່ຖືກຕ້ອງ:"
curl -X POST http://localhost:8081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser4",
    "email": "invalid-email",
    "password": "Test@123456"
  }' | jq .

# ທົດສອບ 5: ລົງທະບຽນຊ້ຳກັນ
echo -e "\n5. ທົດສອບລົງທະບຽນຊ້ຳກັນ:"
curl -X POST http://localhost:8081/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser1",
    "email": "test1@example.com",
    "password": "Test@123456"
  }' | jq .

echo -e "\n====================================="
echo "ສຳເລັດການທົດສອບ"
echo "====================================="