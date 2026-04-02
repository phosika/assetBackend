
<?php
require_once __DIR__ . '/../models/ExchangeRate.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../utils/Response.php';

class ExchangeRateController {
    private $exchangeRateModel;
    
    public function __construct() {
        $this->exchangeRateModel = new ExchangeRate();
    }
    
    public function getRates() {
        try {
            $userId = AuthMiddleware::authenticate();
            $date = $_GET['date'] ?? date('Y-m-d');
            
            error_log("=== GET EXCHANGE RATES ===");
            error_log("Date: $date");
            
            // ດຶງຂໍ້ມູນຈາກ database
            $rates = $this->exchangeRateModel->getAllRates($date);
            
            error_log("Rates from DB: " . json_encode($rates));
            
            // ຖ້າບໍ່ມີຂໍ້ມູນ, ໃຊ້ຄ່າເລີ່ມຕົ້ນ
            if (empty($rates)) {
                $rates = [
                    'THB' => ['currency_code' => 'THB', 'rate' => 310, 'rate_date' => $date],
                    'USD' => ['currency_code' => 'USD', 'rate' => 21000, 'rate_date' => $date],
                    'CNY' => ['currency_code' => 'CNY', 'rate' => 2900, 'rate_date' => $date]
                ];
            }
            
            Response::success($rates, 200, 'Exchange rates retrieved successfully');
            
        } catch (Exception $e) {
            error_log("Error in getRates: " . $e->getMessage());
            Response::error('Failed to get exchange rates: ' . $e->getMessage(), 500);
        }
    }
    
    public function saveRate() {
        try {
            $userId = AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            
            error_log("=== SAVE EXCHANGE RATE ===");
            error_log("Input: " . json_encode($input));
            
            $result = $this->exchangeRateModel->saveRate($input, $userId);
            
            if ($result['success']) {
                // ຫຼັງຈາກບັນທຶກສຳເລັດ, ດຶງຂໍ້ມູນລ່າສຸດກັບໄປ
                $latestRates = $this->exchangeRateModel->getAllRates($input['rate_date']);
                Response::success($latestRates, 200, $result['message']);
            } else {
                Response::error($result['message'], 400);
            }
        } catch (Exception $e) {
            error_log("Error saving exchange rate: " . $e->getMessage());
            Response::error('Failed to save exchange rate: ' . $e->getMessage(), 500);
        }
    }
    
    public function convertCurrency() {
        try {
            $userId = AuthMiddleware::authenticate();
            
            $amount = floatval($_GET['amount'] ?? 0);
            $fromCurrency = $_GET['from_currency'] ?? 'LAK';
            $toCurrency = $_GET['to_currency'] ?? 'LAK';
            $date = $_GET['date'] ?? date('Y-m-d');
            
            error_log("=== CONVERT CURRENCY ===");
            error_log("Converting: $amount $fromCurrency to $toCurrency on $date");
            
            if ($fromCurrency === $toCurrency) {
                Response::success(['amount' => $amount], 200, 'Same currency');
                return;
            }
            
            $fromRate = $this->exchangeRateModel->getRate($fromCurrency, $date);
            $toRate = $this->exchangeRateModel->getRate($toCurrency, $date);
            
            error_log("Rates - $fromCurrency: $fromRate, $toCurrency: $toRate");
            
            $convertedAmount = $amount * ($toRate / $fromRate);
            
            Response::success([
                'original_amount' => $amount,
                'original_currency' => $fromCurrency,
                'converted_amount' => $convertedAmount,
                'converted_currency' => $toCurrency,
                'exchange_rate' => $toRate / $fromRate,
                'rate_date' => $date
            ], 200, 'Currency converted successfully');
            
        } catch (Exception $e) {
            error_log("Error converting currency: " . $e->getMessage());
            Response::error('Failed to convert currency: ' . $e->getMessage(), 500);
        }
    }
}