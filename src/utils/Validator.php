<?php

namespace App\Utils;

class Validator {
    
    // Check if email is valid
    public static function isEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    // Check if phone number is valid (simple check)
    public static function isPhone($phone) {
        // Remove spaces and special characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Check length between 10-15 digits
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    // Check if string is not empty
    public static function required($value, $fieldName) {
        if (empty(trim($value))) {
            return "$fieldName is required";
        }
        return null;
    }
    
    // Check minimum length
    public static function minLength($value, $min, $fieldName) {
        if (strlen(trim($value)) < $min) {
            return "$fieldName must be at least $min characters";
        }
        return null;
    }
    
    // Validate multiple fields at once
    public static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $ruleSet) {
            $value = $data[$field] ?? null;
            
            foreach ($ruleSet as $rule) {
                if ($rule === 'required') {
                    $error = self::required($value, $field);
                    if ($error) $errors[$field][] = $error;
                }
                
                if (strpos($rule, 'min:') === 0) {
                    $min = explode(':', $rule)[1];
                    $error = self::minLength($value, $min, $field);
                    if ($error) $errors[$field][] = $error;
                }
                
                if ($rule === 'email' && !empty($value)) {
                    if (!self::isEmail($value)) {
                        $errors[$field][] = "Invalid email format";
                    }
                }
                
                if ($rule === 'phone' && !empty($value)) {
                    if (!self::isPhone($value)) {
                        $errors[$field][] = "Invalid phone number";
                    }
                }
            }
        }
        
        return empty($errors) ? null : $errors;
    }
}