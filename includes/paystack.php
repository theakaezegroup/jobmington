<?php
/**
 * JOBMINGTON - Paystack Payment Gateway Integration
 * Handles all Paystack API interactions
 * 
 * Documentation: https://paystack.com/docs/api/
 */

defined('JOBMINGTON') or die('Direct access not allowed');

class Paystack {
    private static $secretKey;
    private static $publicKey;
    private static $baseUrl = 'https://api.paystack.co';
    
    /**
     * Initialize Paystack with API keys
     */
    public static function init() {
        self::$secretKey = getenv('PAYSTACK_SECRET_KEY');
        self::$publicKey = getenv('PAYSTACK_PUBLIC_KEY');
        self::$baseUrl = getenv('PAYSTACK_BASE_URL') ?: 'https://api.paystack.co';
        
        if (empty(self::$secretKey) || strpos(self::$secretKey, 'xxxx') !== false) {
            throw new Exception('Paystack secret key not configured. Add PAYSTACK_SECRET_KEY to your server environment or .env file.');
        }
    }
    
    /**
     * Get the public key for frontend
     */
    public static function getPublicKey(): string {
        return self::$publicKey ?: getenv('PAYSTACK_PUBLIC_KEY');
    }
    
    /**
     * Initialize a transaction
     * 
     * @param string $email Customer email
     * @param int $amount Amount in kobo (NGN * 100)
     * @param string $reference Unique transaction reference
     * @param array $metadata Additional transaction data
     * @param string $callbackUrl URL to redirect after payment
     * @return array Transaction initialization response
     */
    public static function initializeTransaction(
        string $email,
        int $amount,
        string $reference,
        array $metadata = [],
        string $callbackUrl = null
    ): array {
        self::init();
        
        $data = [
            'email' => $email,
            'amount' => $amount, // Amount in kobo
            'reference' => $reference,
            'callback_url' => $callbackUrl ?: getenv('PAYSTACK_CALLBACK_URL'),
            'metadata' => $metadata,
            'currency' => 'NGN'
        ];
        
        return self::makeRequest('POST', '/transaction/initialize', $data);
    }
    
    /**
     * Verify a transaction
     * 
     * @param string $reference Transaction reference
     * @return array Transaction verification response
     */
    public static function verifyTransaction(string $reference): array {
        self::init();
        return self::makeRequest('GET', '/transaction/verify/' . rawurlencode($reference));
    }
    
    /**
     * Get transaction details
     * 
     * @param int $transactionId Paystack transaction ID
     * @return array Transaction details
     */
    public static function getTransaction(int $transactionId): array {
        self::init();
        return self::makeRequest('GET', '/transaction/' . $transactionId);
    }
    
    /**
     * List transactions
     * 
     * @param array $params Query parameters (perPage, page, status, from, to)
     * @return array List of transactions
     */
    public static function listTransactions(array $params = []): array {
        self::init();
        $query = http_build_query($params);
        return self::makeRequest('GET', '/transaction?' . $query);
    }
    
    /**
     * Charge authorization (for recurring payments)
     * 
     * @param string $authorizationCode Saved card authorization
     * @param string $email Customer email
     * @param int $amount Amount in kobo
     * @param string $reference Unique reference
     * @return array Charge response
     */
    public static function chargeAuthorization(
        string $authorizationCode,
        string $email,
        int $amount,
        string $reference
    ): array {
        self::init();
        
        return self::makeRequest('POST', '/transaction/charge_authorization', [
            'authorization_code' => $authorizationCode,
            'email' => $email,
            'amount' => $amount,
            'reference' => $reference
        ]);
    }
    
    /**
     * Create a payment plan (subscription)
     * 
     * @param string $name Plan name
     * @param int $amount Amount per interval in kobo
     * @param string $interval daily, weekly, monthly, annually
     * @return array Plan creation response
     */
    public static function createPlan(string $name, int $amount, string $interval = 'monthly'): array {
        self::init();
        
        return self::makeRequest('POST', '/plan', [
            'name' => $name,
            'amount' => $amount,
            'interval' => $interval,
            'currency' => 'NGN'
        ]);
    }
    
    /**
     * Get list of banks for transfers
     * 
     * @return array List of banks
     */
    public static function getBanks(): array {
        self::init();
        return self::makeRequest('GET', '/bank?country=nigeria');
    }
    
    /**
     * Resolve account number
     * 
     * @param string $accountNumber Bank account number
     * @param string $bankCode Bank code
     * @return array Account details
     */
    public static function resolveAccount(string $accountNumber, string $bankCode): array {
        self::init();
        return self::makeRequest('GET', "/bank/resolve?account_number={$accountNumber}&bank_code={$bankCode}");
    }
    
    /**
     * Validate webhook signature
     * 
     * @param string $payload Raw request body
     * @param string $signature X-Paystack-Signature header
     * @return bool Whether signature is valid
     */
    public static function validateWebhook(string $payload, string $signature): bool {
        self::init();
        $computedSignature = hash_hmac('sha512', $payload, self::$secretKey);
        return hash_equals($computedSignature, $signature);
    }
    
    /**
     * Generate unique transaction reference
     * 
     * @param string $prefix Reference prefix
     * @return string Unique reference
     */
    public static function generateReference(string $prefix = 'JMT'): string {
        return $prefix . '-' . time() . '-' . bin2hex(random_bytes(6));
    }
    
    /**
     * Convert NGN to kobo
     * 
     * @param float $naira Amount in Naira
     * @return int Amount in kobo
     */
    public static function toKobo(float $naira): int {
        return (int)round($naira * 100);
    }
    
    /**
     * Convert kobo to NGN
     * 
     * @param int $kobo Amount in kobo
     * @return float Amount in Naira
     */
    public static function toNaira(int $kobo): float {
        return $kobo / 100;
    }
    
    /**
     * Make HTTP request to Paystack API
     * 
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data (for POST/PUT)
     * @return array Response data
     */
    private static function makeRequest(string $method, string $endpoint, array $data = []): array {
        $url = self::$baseUrl . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . self::$secretKey,
            'Content-Type: application/json',
            'Cache-Control: no-cache'
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('Paystack API Error: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $message = $result['message'] ?? 'Unknown error';
            throw new Exception('Paystack Error: ' . $message);
        }
        
        return $result;
    }
}

/**
 * Helper functions for quick access
 */

/**
 * Initialize a Paystack payment
 */
function paystackPay(string $email, float $amountNGN, string $reference, array $meta = []): array {
    return Paystack::initializeTransaction(
        $email,
        Paystack::toKobo($amountNGN),
        $reference,
        $meta
    );
}

/**
 * Verify a Paystack payment
 */
function paystackVerify(string $reference): array {
    return Paystack::verifyTransaction($reference);
}

/**
 * Get Paystack public key
 */
function paystackPublicKey(): string {
    return Paystack::getPublicKey();
}
