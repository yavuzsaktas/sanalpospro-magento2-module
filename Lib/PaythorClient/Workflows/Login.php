<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Workflows;

use Sanalpospro\SanalPosPro\Lib\PaythorClient\PaythorClient;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Resources\App;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Resources\Auth;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\App\Install;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Auth\SignIn;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Auth\OtpVerify;
use Sanalpospro\SanalPosPro\Lib\PaythorClient\Exception\PaythorClientException;

class Login
{
    /**
     * @var PaythorClient
     */
    public PaythorClient $client;

    /**
     *
     * @param PaythorClient $client
     */
    public function __construct(PaythorClient $client)
    {
        $this->client = $client;
    }

    /**
     * Authenticates with Paythor, verifies OTP, installs app if needed,.
     *
     * And retrieves API keys.
     *
     * @param string $email
     * @param string $password
     * @return array Sign-in response data
     * @throws \Exception
     */
    public function login(string $email, string $password): array
    {
        $signIn = new SignIn();
        $signIn->setEmail($email);
        $signIn->setPassword($password);
        $signIn->setProgramId($this->client->getProgramId());
        $signIn->setAppId($this->client->getAppId());
        $signIn->setStoreUrl("https://store.eticsoft.com");
        $signIn->setStoreStage("development");
        $response = $this->client->auth()->signIn($signIn);

        if (!in_array($response['status_code'], [200, 201])) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow.FoundDirectThrow
            throw new PaythorClientException('Login failed: ' . ($response['message'] ?? 'unknown error'));
        }

        $this->client->setToken($response['data']['token_string']);

        $auth = new Auth($this->client);
        $otpVerify = new OtpVerify();
        $otpVerify->setTarget($email);
        $otpVerify->setOtp('123456');
        $otpResponse = $auth->otpVerify($otpVerify);

        if (!in_array($otpResponse['status_code'], [200, 201])) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow.FoundDirectThrow
            throw new PaythorClientException(
                'OTP verification failed: ' . ($otpResponse['message'] ?? 'unknown error')
            );
        }

        $app = new App($this->client);
        $myApp = $this->getMyApp($app);

        if (empty($myApp)) {
            $install = new Install();
            $install->setAppStage('development');
            $install->setParams([
                'app_id' => $this->client->getAppId(),
                'program_id' => $this->client->getProgramId(),
            ]);
            $app->install($this->client->getAppId(), $install);
            $myApp = $this->getMyApp($app);
        }

        if (empty($myApp)) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow.FoundDirectThrow
            throw new PaythorClientException('App installation failed');
        }

        $apiKeys = $app->getApiKeys($myApp['id']);

        if (!in_array($apiKeys['status_code'], [200, 201])) {
            // phpcs:ignore Magento2.Exceptions.DirectThrow.FoundDirectThrow
            throw new PaythorClientException('API keys retrieval failed: ' . ($apiKeys['message'] ?? 'unknown error'));
        }

        $publicKey = $apiKeys['data']['public_key'] ?? null;
        $secretKey = $apiKeys['data']['secret_key'] ?? null;
        $this->client->setPublicKey((string)$publicKey);
        $this->client->setPrivateKey((string)$secretKey);

        return $response;
    }

    /**
     * Find the merchant's app matching the configured App ID.
     *
     * @param App $app
     * @return array|false
     */
    private function getMyApp(App $app)
    {
        $apps = $app->listMy();
        if (!in_array($apps['status_code'], [200, 201])) {
            return [];
        }
        $clientAppId = $this->client->getAppId();
        $data = current(array_filter($apps['data'], function ($app) use ($clientAppId) {
            return $app['app_id'] == $clientAppId;
        }));
        return $data;
    }
}
