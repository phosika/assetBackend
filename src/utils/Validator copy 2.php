<?php
// src/utils/Validator.php

class Validator {
    
    public static function validate($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $ruleString) {
            $rulesList = explode('|', $ruleString);
            $value = $data[$field] ?? null;
            
            foreach ($rulesList as $rule) {
                // ກວດສອບ rule ທີ່ມີ parameter
                if (strpos($rule, ':') !== false) {
                    list($ruleName, $parameter) = explode(':', $rule);
                } else {
                    $ruleName = $rule;
                    $parameter = null;
                }
                
                $error = self::applyRule($field, $value, $ruleName, $parameter);
                if ($error) {
                    $errors[$field][] = $error;
                    break; // ຢຸດກວດສອບ rule ອື່ນໆ ສຳລັບ field ນີ້
                }
            }
        }
        
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