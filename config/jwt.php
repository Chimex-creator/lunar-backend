<?php

namespace Config;

class JWT {
    private static $secret;
    
    public function __construct() {
        self::$secret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-key';
    }
    
    // Simple token generation (we'll improve this later)
    public static function generateToken($user_id, $email, $role) {
        $payload = [
            'user_id' => $user_id,
            'email' => $email,
            'role' => $role,
            'expires' => time() + (86400 * 7) // 7 days
        ];
        
        // Simple encoding (not secure JWT, just for now)
        $token = base64_encode(json_encode($payload));
        return $token;
    }
    
    // Simple token validation
    public static function validateToken($token) {
        try {
            $decoded = json_decode(base64_decode($token), true);
            
            if (!$decoded || !isset($decoded['expires'])) {
                return null;
            }
            
            if ($decoded['expires'] < time()) {
                return null; // Token expired
            }
            
            return $decoded;
        } catch(\Exception $e) {
            return null;
        }
    }
}