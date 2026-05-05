<?php
declare(strict_types=1);

namespace Eticsoft\PaythorClient;

use Magento\Framework\HTTP\Client\Curl;

class PaythorClient
{
    protected string $baseUrl = 'https://live-api.sanalpospro.com/';
    protected ?string $token = null;
    protected ?int $programId = 3;
    protected ?int $appId = 99;
    protected ?int $status = 1;
    protected array $keys = [
        'public' => null,
        'private' => null,
    ];
    protected ?string $hashTime = null;
    protected ?string $hashRand = null;

    /**
     * @param array $options Additional options for the client.
     */
    public function __construct(array $options = [])
    {
        $this->baseUrl = rtrim($this->baseUrl, '/');

        if (isset($options['base_url'])) {
            $this->baseUrl = rtrim($options['base_url'], '/');
        }
        if (isset($options['token'])) {
            $this->token = $options['token'];
        }
        if (isset($options['program_id'])) {
            $this->programId = $options['program_id'];
        }
        if (isset($options['app_id'])) {
            $this->appId = $options['app_id'];
        }
        if (isset($options['public_key'])) {
            $this->keys['public'] = $options['public_key'];
        }
        if (isset($options['private_key'])) {
            $this->keys['private'] = $options['private_key'];
        }
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @param string $token
     * @return void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * @param string $publicKey
     * @return void
     */
    public function setPublicKey(string $publicKey): void
    {
        $this->keys['public'] = $publicKey;
    }

    /**
     * @param string $privateKey
     * @return void
     */
    public function setPrivateKey(string $privateKey): void
    {
        $this->keys['private'] = $privateKey;
    }

    /**
     * @param int $programId
     * @return void
     */
    public function setProgramId(int $programId): void
    {
        $this->programId = $programId;
    }

    /**
     * @return int
     */
    public function getProgramId(): int
    {
        return $this->programId;
    }

    /**
     * @param int $appId
     * @return void
     */
    public function setAppId(int $appId): void
    {
        $this->appId = $appId;
    }

    /**
     * @return int
     */
    public function getAppId(): int
    {
        return $this->appId;
    }

    /**
     * Make an API request using the Magento HTTP client wrapper.
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE, etc.).
     * @param string $url The URL endpoint.
     * @param array $data Request data.
     * @return string
     */
    public function request(string $method, string $url, array $data = []): string
    {
        $curl = new Curl();
        $fullUrl = $this->baseUrl . '/' . ltrim($url, '/');

        $curl->setOption(CURLOPT_TIMEOUT, 30);
        $curl->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $curl->setOption(CURLOPT_MAXREDIRS, 3);
        $curl->setOption(CURLOPT_FOLLOWLOCATION, false);
        $curl->setOption(CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $curl->setOption(CURLOPT_ENCODING, '');

        $curl->addHeader('ETC-PROGRAM-ID', (string)$this->programId);
        $curl->addHeader('ETC-APP-ID', (string)$this->appId);
        $curl->addHeader('Content-Type', 'application/json');

        if ($this->token) {
            $curl->addHeader('Authorization', 'Bearer ' . $this->token);
        }

        if (isset($this->keys['public']) && isset($this->keys['private'])) {
            $hash = $this->generateHash($this->keys['public'], $this->keys['private']);
            $curl->addHeader('X-Timestamp', $this->hashTime);
            $curl->addHeader('X-Nonce', $this->hashRand);
            $curl->addHeader('Authorization', 'ApiKeys ' . $this->keys['public'] . ':' . $hash);
        }

        try {
            $method = strtoupper($method);

            if ($method === 'GET') {
                if (!empty($data)) {
                    $fullUrl .= '?' . http_build_query($data);
                }
                $curl->get($fullUrl);
            } else {
                if ($method !== 'POST') {
                    $curl->setOption(CURLOPT_CUSTOMREQUEST, $method);
                }
                $curl->post($fullUrl, !empty($data) ? json_encode($data) : '');
            }

            $this->setStatus($curl->getStatus());
            return $curl->getBody();
        } catch (\Exception $e) {
            $error = [
                'error' => 'HTTP Error: ' . $e->getMessage(),
                'response' => '',
                'http_code' => 0
            ];
            return json_encode($error);
        }
    }

    /**
     * Decode JSON response.
     *
     * @param string $response Raw response from API
     * @return array
     */
    public function decodeResponse(string $response): array
    {
        if (empty($response)) {
            return [
                'error' => 'Empty response',
                'response' => $response,
                'status' => $this->status,
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'error' => 'JSON decode error: ' . json_last_error_msg(),
                'response' => $response,
                'status' => $this->status,
            ];
        }

        return array_merge($decoded, [
            'status_code' => $this->status,
        ]);
    }

    /**
     * Generate HMAC hash for API key authentication.
     *
     * @param string $publicKey
     * @param string $secretKey
     * @return string
     */
    public function generateHash(string $publicKey, string $secretKey): string
    {
        $this->hashTime = (string)microtime(true);
        $this->hashRand = (string)random_int(1000000, 9999999);
        return hash('sha256', $publicKey . $secretKey . $this->hashTime . $this->hashRand);
    }

    /**
     * @deprecated Use generateHash() instead.
     */
    public function setHash(string $publicKey, string $secretKey): string
    {
        return $this->generateHash($publicKey, $secretKey);
    }

    /**
     * @return Resources\Auth
     */
    public function auth(): Resources\Auth
    {
        return new Resources\Auth($this);
    }

    /**
     * @return Resources\User
     */
    public function user(): Resources\User
    {
        return new Resources\User($this);
    }

    /**
     * @return Resources\Config
     */
    public function config(): Resources\Config
    {
        return new Resources\Config($this);
    }

    /**
     * @return Resources\Gateway
     */
    public function gateway(): Resources\Gateway
    {
        return new Resources\Gateway($this);
    }

    /**
     * @return Resources\App
     */
    public function app(): Resources\App
    {
        return new Resources\App($this);
    }

    /**
     * @return Resources\Payment
     */
    public function payment(): Resources\Payment
    {
        return new Resources\Payment($this);
    }

    /**
     * @return Resources\Process
     */
    public function process(): Resources\Process
    {
        return new Resources\Process($this);
    }
}
