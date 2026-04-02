<?php
// src/utils/Validator.php

class Validator {
    private $errors = [];
    private $data = [];

    /**
     * ກວດສອບຂໍ້ມູນຕາມກົດທີ່ກຳນົດ
     */
    public function validate($data, $rules) {
        $this->data = $data;
        $this->errors = [];

        foreach ($rules as $field => $rule) {
            $rulesList = explode('|', $rule);
            
            foreach ($rulesList as $singleRule) {
                $this->applyRule($field, $singleRule);
            }
        }

        return empty($this->errors);
    }

    /**
     * ນຳໃຊ້ກົດແຕ່ລະອັນ
     */
    private function applyRule($field, $rule) {
        // ກວດສອບວ່າມີເງື່ອນໄຂເພີ່ມເຕີມບໍ (ເຊັ່ນ: max:100)
        if (strpos($rule, ':') !== false) {
            list($ruleName, $parameter) = explode(':', $rule, 2);
        } else {
            $ruleName = $rule;
            $parameter = null;
        }

        $value = isset($this->data[$field]) ? $this->data[$field] : null;

        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, 'The ' . $field . ' field is required');
                }
                break;

            case 'string':
                if ($value !== null && !is_string($value)) {
                    $this->addError($field, 'The ' . $field . ' must be a string');
                }
                break;

            case 'email':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid email address');
                }
                break;

            case 'numeric':
                if ($value !== null && !is_numeric($value)) {
                    $this->addError($field, 'The ' . $field . ' must be a number');
                }
                break;

            case 'integer':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_INT)) {
                    $this->addError($field, 'The ' . $field . ' must be an integer');
                }
                break;

            case 'boolean':
                if ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false])) {
                    $this->addError($field, 'The ' . $field . ' must be a boolean');
                }
                break;

            case 'array':
                if ($value !== null && !is_array($value)) {
                    $this->addError($field, 'The ' . $field . ' must be an array');
                }
                break;

            case 'min':
                if ($value !== null && strlen($value) < (int)$parameter) {
                    $this->addError($field, 'The ' . $field . ' must be at least ' . $parameter . ' characters');
                }
                break;

            case 'max':
                if ($value !== null && strlen($value) > (int)$parameter) {
                    $this->addError($field, 'The ' . $field . ' must not exceed ' . $parameter . ' characters');
                }
                break;

            case 'between':
                if ($value !== null && $parameter) {
                    list($min, $max) = explode(',', $parameter);
                    if (strlen($value) < (int)$min || strlen($value) > (int)$max) {
                        $this->addError($field, 'The ' . $field . ' must be between ' . $min . ' and ' . $max . ' characters');
                    }
                }
                break;

            case 'in':
                if ($value !== null && $parameter) {
                    $allowedValues = explode(',', $parameter);
                    if (!in_array($value, $allowedValues)) {
                        $this->addError($field, 'The ' . $field . ' must be one of: ' . implode(', ', $allowedValues));
                    }
                }
                break;

            case 'date':
                if ($value !== null && !strtotime($value)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid date');
                }
                break;

            case 'url':
                if ($value !== null && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid URL');
                }
                break;

            case 'unique':
                if ($value !== null && $parameter) {
                    // ຮູບແບບ: unique:table,column,except,idColumn
                    $parts = explode(',', $parameter);
                    $table = $parts[0];
                    $column = isset($parts[1]) ? $parts[1] : $field;
                    $except = isset($parts[2]) ? $parts[2] : null;
                    $idColumn = isset($parts[3]) ? $parts[3] : 'id';

                    if ($this->checkUnique($table, $column, $value, $except, $idColumn)) {
                        $this->addError($field, 'The ' . $field . ' has already been taken');
                    }
                }
                break;

            case 'exists':
                if ($value !== null && $parameter) {
                    // ຮູບແບບ: exists:table,column
                    $parts = explode(',', $parameter);
                    $table = $parts[0];
                    $column = isset($parts[1]) ? $parts[1] : $field;

                    if (!$this->checkExists($table, $column, $value)) {
                        $this->addError($field, 'The selected ' . $field . ' is invalid');
                    }
                }
                break;

            case 'confirmed':
                $confirmationField = $field . '_confirmation';
                if ($value !== null && $value !== ($this->data[$confirmationField] ?? null)) {
                    $this->addError($field, 'The ' . $field . ' confirmation does not match');
                }
                break;

            case 'phone':
                if ($value !== null && !preg_match('/^[0-9+\-\s()]+$/', $value)) {
                    $this->addError($field, 'The ' . $field . ' must be a valid phone number');
                }
                break;

            case 'password':
                if ($value !== null) {
                    if (!preg_match('/[A-Z]/', $value)) {
                        $this->addError($field, 'The ' . $field . ' must contain at least one uppercase letter');
                    }
                    if (!preg_match('/[a-z]/', $value)) {
                        $this->addError($field, 'The ' . $field . ' must contain at least one lowercase letter');
                    }
                    if (!preg_match('/[0-9]/', $value)) {
                        $this->addError($field, 'The ' . $field . ' must contain at least one number');
                    }
                }
                break;
        }
    }

    /**
     * ກວດສອບຄ່າຊໍ້າໃນຖານຂໍ້ມູນ
     */
    private function checkUnique($table, $column, $value, $except = null, $idColumn = 'id') {
        try {
            $db = Database::getInstance();
            
            $sql = "SELECT COUNT(*) as count FROM $table WHERE $column = ?";
            $params = [$value];

            if ($except !== null) {
                $sql .= " AND $idColumn != ?";
                $params[] = $except;
            }

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();

            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Unique check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ກວດສອບວ່າຄ່າມີຢູ່ໃນຖານຂໍ້ມູນ
     */
    private function checkExists($table, $column, $value) {
        try {
            $db = Database::getInstance();
            
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM $table WHERE $column = ?");
            $stmt->execute([$value]);
            $result = $stmt->fetch();

            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Exists check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ເພີ່ມຂໍ້ຜິດພາດ
     */
    private function addError($field, $message) {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    /**
     * ດຶງຂໍ້ຜິດພາດທັງໝົດ
     */
    public function errors() {
        return $this->errors;
    }

    /**
     * ດຶງຂໍ້ຜິດພາດແບບລວມ
     */
    public function firstError() {
        foreach ($this->errors as $field => $messages) {
            return $messages[0] ?? null;
        }
        return null;
    }

    /**
     * ກວດສອບວ່າມີຂໍ້ຜິດພາດຫຼືບໍ່
     */
    public function fails() {
        return !empty($this->errors);
    }

    /**
     * ດຶງຂໍ້ຜິດພາດຂອງຟິວໃດໜຶ່ງ
     */
    public function getErrors($field) {
        return $this->errors[$field] ?? [];
    }
}
?>