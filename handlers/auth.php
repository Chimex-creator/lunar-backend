<?php

function registerUser($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$data['email']]);
        
        if ($check->rowCount() > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Email already exists']);
            return;
        }
        
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO users (name, email, phone, password, role) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $role = $data['role'] ?? 'customer';
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $hashed_password,
            $role
        ]);
        
        $user_id = $conn->lastInsertId();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful',
            'data' => [
                'user_id' => $user_id,
                'name' => $data['name'],
                'email' => $data['email']
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

function loginUser($data) {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo json_encode(['status' => 'error', 'message' => 'User not found']);
            return;
        }
        
        if (!password_verify($data['password'], $user['password'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
            return;
        }
        
        unset($user['password']);
        
        $token = base64_encode(json_encode([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'expires' => time() + (86400 * 7)
        ]));
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}   