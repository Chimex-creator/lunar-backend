<?php

namespace App\Controllers;

use App\Models\User;
use App\Utils\Response;
use App\Utils\Validator;

class AuthController {
    private $userModel;
    
    public function __construct() {
        $this->userModel = new User();
    }
    
    // User Registration
    public function register($data) {
        // Validate required fields
        $validation = Validator::validate($data, [
            'name' => ['required'],
            'email' => ['required', 'email'],
            'phone' => ['required', 'phone'],
            'password' => ['required', 'min:6'],
            'role' => []
        ]);
        
        if ($validation) {
            Response::validationError($validation);
            return;
        }
        
        // Check if email already exists
        $existingUser = $this->userModel->findByEmail($data['email']);
        if ($existingUser) {
            Response::error('Email already registered', 400);
            return;
        }
        
        // Hash password
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Prepare user data
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $hashed_password,
            'role' => $data['role'] ?? 'customer',
            'loyalty_points' => 0
        ];
        
        // Create user
        $user_id = $this->userModel->create($userData);
        
        if ($user_id) {
            // If user is a barber, add to barbers table
            if ($userData['role'] === 'barber') {
                $this->userModel->addToBarbers($user_id);
            }
            
            Response::success([
                'user_id' => $user_id,
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $userData['role']
            ], 'Registration successful! Please login.', 201);
        } else {
            Response::error('Registration failed', 500);
        }
    }
    
    // User Login
    public function login($data) {
        // Validate required fields
        $validation = Validator::validate($data, [
            'email' => ['required', 'email'],
            'password' => ['required']
        ]);
        
        if ($validation) {
            Response::validationError($validation);
            return;
        }
        
        // Find user by email
        $user = $this->userModel->findByEmail($data['email']);
        
        if (!$user) {
            Response::error('Invalid email or password', 401);
            return;
        }
        
        // Verify password
        if (!password_verify($data['password'], $user['password'])) {
            Response::error('Invalid email or password', 401);
            return;
        }
        
        // Remove password from response
        unset($user['password']);
        
        // Generate simple token (for now)
        $token = base64_encode(json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'expires' => time() + (86400 * 7)
        ]));
        
        Response::success([
            'user' => $user,
            'token' => $token
        ], 'Login successful');
    }
}
