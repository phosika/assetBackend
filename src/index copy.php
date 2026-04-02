<?php
// src/index.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once 'db.php';


$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';
$path_parts = explode('/', trim($path, '/'));
$resource = $path_parts[0] ?? '';
$id = $path_parts[1] ?? null;

$db = Database::getInstance();

switch ($resource) {
    case 'users':
        handleUsers($method, $id, $db);
        break;
    case 'products':
        handleProducts($method, $id, $db);
        break;
    default:
        echo json_encode([
            'status' => 'success',
            'message' => 'API is running',
            'endpoints' => [
                'GET /users' => 'Get all users',
                'GET /users/{id}' => 'Get user by ID',
                'POST /users' => 'Create new user',
                'PUT /users/{id}' => 'Update user',
                'DELETE /users/{id}' => 'Delete user',
                'GET /products' => 'Get all products'
            ]
        ]);
}

// ຟັງຊັນຈັດການ Users
// function handleUsers($method, $id, $db) {
//     switch ($method) {
//         case 'GET':
//             if ($id) {
//                 // ດຶງຂໍ້ມູນຜູ້ໃຊ້ຕາມ ID
//                 $stmt = $db->prepare("SELECT id, name, email FROM users WHERE id = ?");
//                 $stmt->execute([$id]);
//                 $user = $stmt->fetch();
                
//                 if ($user) {
//                     echo json_encode(['status' => 'success', 'data' => $user]);
//                 } else {
//                     http_response_code(404);
//                     echo json_encode(['status' => 'error', 'message' => 'User not found']);
//                 }
//             } else {
//                 // ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ
//                 $stmt = $db->query("SELECT id, name, email FROM users");
//                 $users = $stmt->fetchAll();
//                 echo json_encode(['status' => 'success', 'data' => $users]);
//             }
//             break;

//         case 'POST':
//             // ສ້າງຜູ້ໃຊ້ໃໝ່
//             $data = json_decode(file_get_contents('php://input'), true);
            
//             if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
//                 http_response_code(400);
//                 echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
//                 return;
//             }

//             $stmt = $db->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
//             $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
            
//             try {
//                 $stmt->execute([$data['name'], $data['email'], $hashed_password]);
//                 $new_id = $db->lastInsertId();
                
//                 echo json_encode([
//                     'status' => 'success',
//                     'message' => 'User created successfully',
//                     'data' => ['id' => $new_id]
//                 ]);
//             } catch (PDOException $e) {
//                 http_response_code(500);
//                 echo json_encode(['status' => 'error', 'message' => 'Failed to create user']);
//             }
//             break;

//         case 'PUT':
//             if (!$id) {
//                 http_response_code(400);
//                 echo json_encode(['status' => 'error', 'message' => 'User ID required']);
//                 return;
//             }

//             $data = json_decode(file_get_contents('php://input'), true);
            
//             $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
//             $stmt->execute([$data['name'], $data['email'], $id]);
            
//             if ($stmt->rowCount() > 0) {
//                 echo json_encode(['status' => 'success', 'message' => 'User updated successfully']);
//             } else {
//                 http_response_code(404);
//                 echo json_encode(['status' => 'error', 'message' => 'User not found']);
//             }
//             break;

//         case 'DELETE':
//             if (!$id) {
//                 http_response_code(400);
//                 echo json_encode(['status' => 'error', 'message' => 'User ID required']);
//                 return;
//             }

//             $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
//             $stmt->execute([$id]);
            
//             if ($stmt->rowCount() > 0) {
//                 echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
//             } else {
//                 http_response_code(404);
//                 echo json_encode(['status' => 'error', 'message' => 'User not found']);
//             }
//             break;

//         default:
//             http_response_code(405);
//             echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
//     }
// }

function handleProducts($method, $id, $db) {
    // ສາມາດເພີ່ມ logic ສຳລັບ products ໄດ້ທີ່ນີ້
    echo json_encode(['status' => 'success', 'message' => 'Products endpoint working']);
}
?>
