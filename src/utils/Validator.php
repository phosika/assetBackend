<?php
// src/utils/Validator.php

class Validator {
    private $errors = [];

    public function validate($data, $rules) {
        $this->errors = [];

        foreach ($rules as $field => $rule) {
            $rulesList = explode('|', $rule);
            
            foreach ($rulesList as $singleRule) {
                $this->validateField($field, $data[$field] ?? null, $singleRule, $data);
            }
        }

        return empty($this->errors);
    }

    private function validateField($field, $value, $rule, $data = []) {
        // ກວດສອບ required
        if ($rule === 'required' && (is_null($value) || $value === '')) {
            $this->errors[$field][] = "The {$field} field is required";
        }

        // ກວດສອບ string
        if ($rule === 'string' && !is_null($value) && !is_string($value)) {
            $this->errors[$field][] = "The {$field} must be a string";
        }

        // ກວດສອບ integer
        if ($rule === 'integer' && !is_null($value) && !is_numeric($value)) {
            $this->errors[$field][] = "The {$field} must be an integer";
        }

        // ກວດສອບ email
        if ($rule === 'email' && !is_null($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = "The {$field} must be a valid email";
        }

        // ກວດສອບ min length
        if (strpos($rule, 'min:') === 0) {
            $min = explode(':', $rule)[1];
            if (!is_null($value) && strlen($value) < $min) {
                $this->errors[$field][] = "The {$field} must be at least {$min} characters";
            }
        }

        // ກວດສອບ max length
        if (strpos($rule, 'max:') === 0) {
            $max = explode(':', $rule)[1];
            if (!is_null($value) && strlen($value) > $max) {
                $this->errors[$field][] = "The {$field} may not be greater than {$max} characters";
            }
        }

        // ກວດສອບ in array
        if (strpos($rule, 'in:') === 0) {
            $allowedValues = explode(',', str_replace('in:', '', $rule));
            if (!is_null($value) && !in_array($value, $allowedValues)) {
                $this->errors[$field][] = "The {$field} must be one of: " . implode(', ', $allowedValues);
            }
        }
    }

    public function fails() {
        return !empty($this->errors);
    }

    public function errors() {
        return $this->errors;
    }
}
?>