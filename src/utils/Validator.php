<?php
// src/utils/Validator.php

class Validator {
    


public static function validate($data, $rules) {
    error_log("=== VALIDATOR START ===");
    error_log("Data: " . print_r($data, true));
    error_log("Rules: " . print_r($rules, true));
    
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $rulesList = explode('|', $rule);
        $value = $data[$field] ?? null;
        
        error_log("Validating field: $field, value: " . ($value ?? 'null'));
        
        $isEmpty = ($value === null || $value === '' || (is_array($value) && empty($value)));
        
        foreach ($rulesList as $singleRule) {
            // ກວດສອບ required
            if ($singleRule === 'required') {
                if ($isEmpty) {
                    $errors[$field][] = ucfirst($field) . ' is required';
                    error_log("Validation failed: $field is required");
                    continue;
                }
            }
            
            // ຂ້າມການກວດສອບຖ້າບໍ່ມີຄ່າ ແລະ ບໍ່ແມ່ນ required
            if ($isEmpty && $singleRule !== 'required') {
                continue;
            }
            
            // string
            if ($singleRule === 'string' && !is_string($value)) {
                $errors[$field][] = ucfirst($field) . ' must be a string';
                error_log("Validation failed: $field must be a string");
            }

            // numeric
            if ($singleRule === 'numeric' && !is_numeric($value)) {
                $errors[$field][] = ucfirst($field) . ' must be a number';
                error_log("Validation failed: $field must be a number");
            }
            
            // min
            if (strpos($singleRule, 'min:') === 0) {
                $min = (int)substr($singleRule, 4);
                if (strlen($value) < $min) {
                    $errors[$field][] = ucfirst($field) . ' must be at least ' . $min . ' characters';
                    error_log("Validation failed: $field must be at least $min characters");
                }
            }
            
            // max
            if (strpos($singleRule, 'max:') === 0) {
                $max = (int)substr($singleRule, 4);
                if (strlen($value) > $max) {
                    $errors[$field][] = ucfirst($field) . ' must not exceed ' . $max . ' characters';
                    error_log("Validation failed: $field must not exceed $max characters");
                }
            }
            
            // email
            if ($singleRule === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field][] = ucfirst($field) . ' must be a valid email address';
                error_log("Validation failed: $field must be a valid email");
            }
            
            // in
            if (strpos($singleRule, 'in:') === 0) {
                $allowed = explode(',', substr($singleRule, 3));
                if (!in_array($value, $allowed)) {
                    $errors[$field][] = ucfirst($field) . ' must be one of: ' . implode(', ', $allowed);
                    error_log("Validation failed: $field must be one of: " . implode(', ', $allowed));
                }
            }
        }
    }
    
    error_log("Validation errors: " . print_r($errors, true));
    return $errors;
}
    
    private static function applyRule($field, $value, $rule, $parameter) {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '') {
                    return "The {$field} field is required";
                }
                break;
                
            case 'string':
                if ($value !== null && !is_string($value)) {
                    return "The {$field} must be a string";
                }
                break;
                
            case 'min':
                if (strlen($value) < $parameter) {
                    return "The {$field} must be at least {$parameter} characters";
                }
                break;
                
            case 'max':
                if (strlen($value) > $parameter) {
                    return "The {$field} must not exceed {$parameter} characters";
                }
                break;
                
            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "The {$field} must be a valid email address";
                }
                break;
                
            case 'in':
                $allowed = explode(',', $parameter);
                if (!in_array($value, $allowed)) {
                    return "The {$field} must be one of: " . implode(', ', $allowed);
                }
                break;
                
            case 'numeric':
                if (!is_numeric($value)) {
                    return "The {$field} must be a number";
                }
                break;
        }
        
        return null;
    }
}
?>