<?php
// src/controllers/InfoController.php

require_once __DIR__ . '/../utils/Response.php';

class InfoController {
    
    public function getApiInfo() {
        $endpoints = [
            // Auth endpoints
            'POST /auth/register' => 'Register new user',
            'POST /auth/login' => 'Login user',
            'POST /auth/refresh' => 'Refresh access token',
            'POST /auth/logout' => 'Logout user',
            
            // User endpoints
            'GET /user/profile' => 'Get current user profile',
            'PUT /user/profile' => 'Update current user profile',
            'POST /user/change-password' => 'Change password',
            'POST /user/profile-image' => 'Upload profile image',
            'GET /users' => 'Get all users (paginated, filterable)',
            'GET /users/dropdown' => 'Get users for dropdown',
            'GET /users/export' => 'Export users data',
            'GET /users/stats' => 'Get user statistics',
            'GET /users/search' => 'Advanced user search',
            'GET /users/by-department/{id}' => 'Get users by department',
            'GET /users/activities/{id}' => 'Get user activities',
            'GET /users/{id}' => 'Get user by ID',
            'POST /users' => 'Create new user',
            'PUT /users/{id}' => 'Update user',
            'DELETE /users/{id}' => 'Delete user',
            'PATCH /users/{id}/status' => 'Update user status',
            'PATCH /users/{id}/role' => 'Update user role',
            
            // Asset endpoints
            'GET /assets' => 'Get all assets (paginated, filterable)',
            'GET /assets/stats' => 'Get asset statistics',
            'GET /assets/search' => 'Search assets',
            'GET /assets/by-user/{userId}' => 'Get assets by user',
            'GET /assets/by-department/{deptId}' => 'Get assets by department',
            'GET /assets/by-barcode/{barcode}' => 'Get asset by barcode',
            'GET /assets/by-rfid/{rfid}' => 'Get asset by RFID',
            'GET /assets/by-serial/{serial}' => 'Get asset by serial number',
            'GET /assets/{id}' => 'Get asset by ID',
            'POST /assets' => 'Create new asset',
            'PUT /assets/{id}' => 'Update asset',
            'PATCH /assets/{id}/status' => 'Update asset status',
            'PATCH /assets/{id}/condition' => 'Update asset condition',
            'PATCH /assets/{id}/user' => 'Update asset user',
            'PATCH /assets/{id}/location' => 'Update asset location',
            'PATCH /assets/{id}/warranty' => 'Update asset warranty',
            'POST /assets/{id}/verify' => 'Verify asset',
            'DELETE /assets/{id}' => 'Delete asset',
            
            // Asset documents
            'GET /assets/{assetId}/documents' => 'Get asset documents',
            'POST /assets/{assetId}/documents' => 'Upload document',
            'DELETE /assets/documents/{docId}' => 'Delete document',
            
            // Asset images
            'GET /assets/{assetId}/images' => 'Get asset images',
            'POST /assets/{assetId}/images' => 'Upload image',
            'POST /assets/images/{imageId}/primary' => 'Set primary image',
            'POST /assets/images/reorder' => 'Reorder images',
            'DELETE /assets/images/{imageId}' => 'Delete image',
            
            // Barcode endpoints
            'POST /barcode/scan' => 'Record barcode scan',
            'GET /barcode/scans' => 'Get scan history',
            'POST /assets/{assetId}/barcode' => 'Generate barcode for asset',
            
            // Supplier endpoints
            'GET /suppliers' => 'Get all suppliers (paginated)',
            'GET /suppliers/dropdown' => 'Get suppliers for dropdown',
            'GET /suppliers/stats' => 'Get supplier statistics',
            'GET /suppliers/search' => 'Search suppliers',
            'GET /suppliers/by-code/{code}' => 'Get supplier by code',
            'GET /suppliers/{id}' => 'Get supplier by ID',
            'POST /suppliers' => 'Create new supplier',
            'PUT /suppliers/{id}' => 'Update supplier',
            'PATCH /suppliers/{id}/status' => 'Update supplier status',
            'DELETE /suppliers/{id}' => 'Delete supplier',
        ];
        
        Response::success([
            'name' => 'Asset Management API',
            'version' => '2.0.0',
            'endpoints' => $endpoints,
            'documentation' => '/api/docs'
        ], 200);
    }
}