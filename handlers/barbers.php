<?php

function getBarbers() {
    try {
        $conn = new PDO("mysql:host=fdb1032.awardspace.net;dbname=4761973_barbing", "4761973_barbing", "Hello123###");
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $conn->prepare("
            SELECT b.*, u.name, u.email, u.phone 
            FROM barbers b 
            JOIN users u ON b.user_id = u.id 
            WHERE b.is_active = 1
        ");
        $stmt->execute();
        $barbers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Barbers retrieved successfully',
            'data' => $barbers
        ]);
    } catch(PDOException $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}