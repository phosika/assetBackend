<?php


class Config {
    private static $env = [];
    
    public static function load() {
        // ເສັ້ນທາງທີ່ເປັນໄປໄດ້ໃນ Docker
        $possiblePaths = [
            '/var/www/html/.env',           // ຢູ່ root ຂອງ web (ທີ່ພວກເຮົາ mount ເຂົ້າໄປ)
            __DIR__ . '/../.env',            // ຢູ່ BACKEND/src/ ຂຶ້ນໄປ 1 ລະດັບ
            __DIR__ . '/../../.env',         // ຢູ່ BACKEND/ ຂຶ້ນໄປ 2 ລະດັບ
        ];
        
        $envFile = null;
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envFile = $path;
                error_log("Config: Found .env file at: " . $path); // ເພີ່ມ log
                break;
            }
        }
        
        if (!$envFile) {
            error_log("Config Warning: No .env file found. Using environment variables only.");
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            $parts = explode('=', $line, 2);
            if (count($parts) == 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                
                self::$env[$name] = trim($value, "\"'");
                $_ENV[$name] = trim($value, "\"'");
                if (function_exists('putenv')) {
                    @putenv("$name=" . trim($value, "\"'"));
                }
            }
        }
    }
    
    public static function get($key, $default = null) {
        // ກວດສອບຈາກ environment variables ກ່ອນ (Check $_ENV first, then getenv)
        $envValue = $_ENV[$key] ?? $_SERVER[$key] ?? (function_exists('getenv') ? getenv($key) : false);
        if ($envValue !== false && $envValue !== null) {
            return $envValue;
        }
        
        // ຖ້າບໍ່ມີ, ກວດສອບຈາກທີ່ເກັບໄວ້
        if (isset(self::$env[$key])) {
            return self::$env[$key];
        }
        
        return $default;
    }
}

// ໂຫຼດ .env ອັດຕະໂນມັດ
Config::load();
?>