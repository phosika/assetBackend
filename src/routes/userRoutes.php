<?php
// src/routes/userRoutes.php

require_once __DIR__ . '/../controllers/UserController.php';

function handleUserRequest($method, $path, $segments) {
    $controller = new UserController();

    // Route: GET /api/users (ດຶງຂໍ້ມູນຜູ້ໃຊ້ທັງໝົດ)
    if ($method === 'GET' && count($segments) === 1 && $segments[0] === 'users') {
        $controller->getAllUsers();
        return true;
    }

    // Route: GET /api/users/dropdown (ດຶງຂໍ້ມູນຜູ້ໃຊ້ແບບຫຍໍ້)
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users' && $segments[1] === 'dropdown') {
        $controller->getUsersForDropdown();
        return true;
    }

    // Route: GET /api/users/export (ສົ່ງອອກຂໍ້ມູນ)
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users' && $segments[1] === 'export') {
        $controller->exportUsers();
        return true;
    }

    // Route: GET /api/users/stats (ສະຖິຕິ)
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users' && $segments[1] === 'stats') {
        $controller->getUserStats();
        return true;
    }

    // Route: GET /api/users/search (ຄົ້ນຫາ)
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users' && $segments[1] === 'search') {
        $controller->searchUsers();
        return true;
    }

    // Route: GET /api/users/by-department/{departmentId}
    if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'users' && $segments[1] === 'by-department') {
        $controller->getUsersByDepartment($segments[2]);
        return true;
    }

    // Route: GET /api/users/{id}
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'users') {
        $controller->getUserById($segments[1]);
        return true;
    }

    // Route: POST /api/users (ສ້າງຜູ້ໃຊ້ໃໝ່)
    if ($method === 'POST' && count($segments) === 1 && $segments[0] === 'users') {
        $controller->createUser();
        return true;
    }

    // Route: PUT /api/users/{id} (ອັບເດດຜູ້ໃຊ້)
    if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'users') {
        $controller->updateUser($segments[1]);
        return true;
    }

    // Route: DELETE /api/users/{id} (ລຶບຜູ້ໃຊ້)
    if ($method === 'DELETE' && count($segments) === 2 && $segments[0] === 'users') {
        $controller->deleteUser($segments[1]);
        return true;
    }

    // Route: PATCH /api/users/{id}/status (ອັບເດດສະຖານະ)
    if ($method === 'PATCH' && count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'status') {
        $controller->updateUserStatus($segments[1]);
        return true;
    }

    // Route: PATCH /api/users/{id}/role (ອັບເດດບົດບາດ)
    if ($method === 'PATCH' && count($segments) === 3 && $segments[0] === 'users' && $segments[2] === 'role') {
        $controller->updateUserRole($segments[1]);
        return true;
    }

    // Route: GET /api/users/activities/{userId}
    if ($method === 'GET' && count($segments) === 3 && $segments[0] === 'users' && $segments[1] === 'activities') {
        $controller->getUserActivities($segments[2]);
        return true;
    }

    // Profile routes
    // Route: GET /api/user/profile
    if ($method === 'GET' && count($segments) === 2 && $segments[0] === 'user' && $segments[1] === 'profile') {
        $controller->getProfile();
        return true;
    }

    // Route: PUT /api/user/profile
    if ($method === 'PUT' && count($segments) === 2 && $segments[0] === 'user' && $segments[1] === 'profile') {
        $controller->updateProfile();
        return true;
    }

    // Route: POST /api/user/change-password
    if ($method === 'POST' && count($segments) === 2 && $segments[0] === 'user' && $segments[1] === 'change-password') {
        $controller->changePassword();
        return true;
    }

    // Route: POST /api/user/profile-image
    if ($method === 'POST' && count($segments) === 2 && $segments[0] === 'user' && $segments[1] === 'profile-image') {
        $controller->uploadProfileImage();
        return true;
    }

    return false;
}
?>