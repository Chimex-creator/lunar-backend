<?php

namespace App\Utils;

class Response {
    
    // Send success response
    public static function success($data = null, $message = "Success", $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit();
    }
    
    // Send error response
    public static function error($message = "Error occurred", $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        $response = [
            'status' => 'error',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($errors) {
            $response['errors'] = $errors;
        }
        
        echo json_encode($response);
        exit();
    }
    
    // Send 404 Not Found
    public static function notFound($message = "Resource not found") {
        self::error($message, 404);
    }
    
    // Send 401 Unauthorized
    public static function unauthorized($message = "Unauthorized access") {
        self::error($message, 401);
    }
    
    // Send 403 Forbidden
    public static function forbidden($message = "Access denied") {
        self::error($message, 403);
    }
    
    // Send 422 Validation Error
    public static function validationError($errors) {
        self::error("Validation failed", 422, $errors);
    }
}