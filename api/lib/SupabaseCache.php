<?php
class SupabaseCache
{
    /** @var SupabaseCache|null */
    private static $instance = null;

    /** @var string */
    private $baseUrl = '';

    /** @var string */
    private $apiKey = '';

    /** @var string */
    private $table = 'cache_entries';

    /** @var string */
    private $encryptionKey = '';

    /** @var bool */
    private $enabled = false;

    private function __construct()
    {
        $baseUrl = rtrim((string) getenv('SUPABASE_URL'), '/');
        $apiKey = (string) getenv('SUPABASE_SERVICE_ROLE_KEY');
        if ($apiKey === '') {
            $apiKey = (string) getenv('SUPABASE_ANON_KEY');
        }
        $table = getenv('SUPABASE_CACHE_TABLE');
        $encryptionKey = (string) getenv('CACHE_ENCRYPTION_KEY');

        if ($baseUrl === '' || $apiKey === '' || $encryptionKey === '') {
            return;
        }

        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
        $this->table = $table !== false && $table !== '' ? $table : 'cache_entries';
        $this->encryptionKey = hash('sha256', $encryptionKey, true);
        $this->enabled = extension_loaded('openssl');
    }

    /**
     * @return SupabaseCache
     */
    public static function getInstance(): SupabaseCache
    {
        if (self::$instance === null) {
            self::$instance = new SupabaseCache();
        }

        return self::$instance;
    }

    public static function buildKey(string ...$parts): string
    {
        $normalised = array_map(static fn ($part) => strtolower(trim($part)), $parts);
        return implode(':', $normalised);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $key): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $endpoint = sprintf(
            '%s/rest/v1/%s?select=data,iv,expires_at&key=eq.%s&limit=1',
            $this->baseUrl,
            rawurlencode($this->table),
            rawurlencode($key)
        );

        $response = $this->request('GET', $endpoint);
        if ($response === null) {
            return null;
        }

        $payload = json_decode($response, true);
        if (!is_array($payload) || empty($payload)) {
            return null;
        }

        $entry = $payload[0];
        $expiresAt = isset($entry['expires_at']) ? strtotime($entry['expires_at']) : null;
        if ($expiresAt !== null && $expiresAt < time()) {
            $this->delete($key);
            return null;
        }

        if (!isset($entry['data'], $entry['iv'])) {
            return null;
        }

        return $this->decrypt((string) $entry['data'], (string) $entry['iv']);
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        if (!$this->enabled || $ttlSeconds <= 0) {
            return;
        }

        $encrypted = $this->encrypt($value);
        if ($encrypted === null) {
            return;
        }

        [$cipherText, $iv] = $encrypted;
        $expiresAt = gmdate('c', time() + $ttlSeconds);
        $now = gmdate('c');

        $payload = [[
            'key' => $key,
            'data' => base64_encode($cipherText),
            'iv' => base64_encode($iv),
            'expires_at' => $expiresAt,
            'updated_at' => $now,
        ]];

        $endpoint = sprintf('%s/rest/v1/%s', $this->baseUrl, rawurlencode($this->table));
        $body = json_encode($payload);
        if ($body === false) {
            return;
        }

        $this->request('POST', $endpoint, [
            'Prefer: resolution=merge-duplicates',
            'Content-Type: application/json',
        ], $body);
    }

    private function delete(string $key): void
    {
        $endpoint = sprintf(
            '%s/rest/v1/%s?key=eq.%s',
            $this->baseUrl,
            rawurlencode($this->table),
            rawurlencode($key)
        );

        $this->request('DELETE', $endpoint, ['Prefer: return=minimal']);
    }

    private function encrypt(string $value): ?array
    {
        try {
            $iv = random_bytes(16);
        } catch (Exception $exception) {
            return null;
        }
        $cipher = openssl_encrypt(
            $value,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($cipher === false) {
            return null;
        }

        return [$cipher, $iv];
    }

    private function decrypt(string $value, string $iv): ?string
    {
        $cipherText = base64_decode($value, true);
        $ivBinary = base64_decode($iv, true);

        if ($cipherText === false || $ivBinary === false) {
            return null;
        }

        $plain = openssl_decrypt(
            $cipherText,
            'AES-256-CBC',
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $ivBinary
        );

        return $plain === false ? null : $plain;
    }

    private function request(string $method, string $url, array $extraHeaders = [], ?string $body = null): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $headers = array_merge(
            [
                'apikey: ' . $this->apiKey,
                'Authorization: Bearer ' . $this->apiKey,
            ],
            $extraHeaders
        );

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return $response === false ? null : $response;
    }
}
