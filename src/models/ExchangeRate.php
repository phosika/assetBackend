
<?php
require_once __DIR__ . '/../config/database.php';

class ExchangeRate {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getRate($currencyCode, $date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            
            $sql = "SELECT rate FROM exchange_rates 
                    WHERE currency_code = ? AND rate_date <= ?
                    ORDER BY rate_date DESC LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$currencyCode, $date]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return floatval($result['rate']);
            }
            
            // ຄ່າເລີ່ມຕົ້ນ
            $defaultRates = [
                'LAK' => 1,
                'THB' => 310,
                'USD' => 21000,
                'CNY' => 2900
            ];
            
            return $defaultRates[$currencyCode] ?? 1;
            
        } catch (Exception $e) {
            error_log("Error getting exchange rate: " . $e->getMessage());
            return 1;
        }
    }
    
    public function saveRate($data, $userId) {
        try {
            $sql = "INSERT INTO exchange_rates (currency_code, rate, rate_date, source, notes, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                    rate = VALUES(rate),
                    source = VALUES(source),
                    notes = VALUES(notes),
                    updated_at = NOW()";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $data['currency_code'],
                $data['rate'],
                $data['rate_date'],
                $data['source'] ?? 'manual',
                $data['notes'] ?? null,
                $userId
            ]);
            
            error_log("Exchange rate saved: {$data['currency_code']} = {$data['rate']}");
            
            return ['success' => true, 'message' => 'Exchange rate saved successfully'];
            
        } catch (Exception $e) {
            error_log("Error saving exchange rate: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    public function getAllRates($date = null) {
        try {
            $date = $date ?: date('Y-m-d');
            
            // ດຶງຂໍ້ມູນລ່າສຸດສຳລັບແຕ່ລະສະກຸນເງິນ
            $sql = "SELECT r1.* FROM exchange_rates r1
                    INNER JOIN (
                        SELECT currency_code, MAX(rate_date) as max_date
                        FROM exchange_rates
                        WHERE rate_date <= ?
                        GROUP BY currency_code
                    ) r2 ON r1.currency_code = r2.currency_code AND r1.rate_date = r2.max_date";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$date]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rates = [];
            foreach ($results as $row) {
                $rates[$row['currency_code']] = [
                    'currency_code' => $row['currency_code'],
                    'rate' => floatval($row['rate']),
                    'rate_date' => $row['rate_date'],
                    'source' => $row['source'],
                    'notes' => $row['notes']
                ];
            }
            
            return $rates;
            
        } catch (Exception $e) {
            error_log("Error getting all rates: " . $e->getMessage());
            return [];
        }
    }
}