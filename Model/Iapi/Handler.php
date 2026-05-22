<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Model\Iapi;

use Sanalpospro\SanalPosPro\Lib\PaythorClient\PaythorClient;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Sanalpospro\SanalPosPro\Model\Config\PaymentConfig;

/**
 * Dispatches IAPI actions sent by the Paythor CDN admin app.
 *
 * Mirrors AdminSanalPosProIapiController / InternalApi from the PrestaShop
 * module. The CDN app POSTs: iapi_action, iapi_params (JSON), iapi_xfvv.
 */
class Handler
{
    /**
     * @var array
     */
    private array $response = [
        'status'  => 'error',
        'message' => 'Internal error',
    ];

    /**
     *
     * @param PaymentConfig $paymentConfig
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Pool $cacheFrontendPool
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly PaymentConfig $paymentConfig,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList,
        private readonly Pool $cacheFrontendPool,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Run.
     *
     * @param RequestInterface $request
     * @return array
     */
    public function run(RequestInterface $request): array
    {
        $action    = (string)$request->getPost('iapi_action', '');
        $xfvv      = (string)$request->getPost('iapi_xfvv', '');
        $rawParams = (string)$request->getPost('iapi_params', '{}');
        $params    = json_decode($rawParams, true) ?? [];

        if ($action === '') {
            return $this->error('Action not specified.');
        }

        if ($xfvv !== $this->paymentConfig->getXfvv()) {
            return $this->error('Security token mismatch.');
        }

        $method = 'action' . ucfirst($action);
        if (!method_exists($this, $method)) {
            return $this->error('Unknown action: ' . $action);
        }

        return $this->$method($params);
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    /**
     * Action save api keys.
     *
     * @param array $params
     * @return array
     */
    private function actionSaveApiKeys(array $params): array
    {
        $pub = trim((string)($params['iapi_publicKey'] ?? ''));
        $sec = trim((string)($params['iapi_secretKey'] ?? ''));

        if ($pub === '' || $sec === '') {
            return $this->error('Missing API keys.');
        }

        $this->configWriter->save(PaymentConfig::XML_PATH_PUBLIC_KEY, $pub);
        $this->configWriter->save(PaymentConfig::XML_PATH_PRIVATE_KEY, $sec);

        // Flush config cache so the immediately-following checkApiKeys reads fresh keys.
        $this->cacheTypeList->cleanType('config');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }

        return $this->success('API keys saved.');
    }

    /**
     * Action check api keys.
     *
     * @param array $params
     * @return array
     */
    private function actionCheckApiKeys(array $params): array
    {
        $pub = $this->readConfigFromDb(PaymentConfig::XML_PATH_PUBLIC_KEY);
        $sec = $this->readConfigFromDb(PaymentConfig::XML_PATH_PRIVATE_KEY);

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured.');
        }

        try {
            $client = $this->buildClient($pub, $sec);
            $raw    = $client->request('POST', '/check/accesstoken', [
                'accesstoken' => $params['iapi_accessToken'] ?? '',
            ]);

            return $client->decodeResponse($raw);
        } catch (\Throwable $e) {
            return $this->error('Access token check failed: ' . $e->getMessage());
        }
    }

    /**
     * Action set installment options.
     *
     * @param array $params
     * @return array
     */
    private function actionSetInstallmentOptions(array $params): array
    {
        $options = $params['iapi_installmentOptions'] ?? null;
        if (empty($options)) {
            return $this->error('Invalid installment options.');
        }

        $this->configWriter->save('payment/paythor_sanalpospro/installments', json_encode($options));

        // Invalidate config + full-page cache so the product-page installment
        // table reflects the new plan on the very next request (otherwise the
        // cached HTML keeps showing the previous values).
        $this->cacheTypeList->cleanType('config');
        $this->cacheTypeList->cleanType('full_page');

        return $this->success('Installment options updated.');
    }

    /**
     * Action set module settings.
     *
     * @param array $params
     * @return array
     */
    private function actionSetModuleSettings(array $params): array
    {
        $settings = $params['iapi_moduleSettings'] ?? [];
        if (empty($settings) || !is_array($settings)) {
            return $this->error('No settings provided.');
        }

        // Allowed keys mapped from camelCase (sent by React UI) to lowercase config paths.
        $allowedMap = [
            'order_status'        => 'order_status',
            'currency_convert'    => 'currency_convert',
            'showinstallmentstabs' => 'showinstallmentstabs',
            'paymentpagetheme'    => 'paymentpagetheme',
        ];

        $updated = [];
        foreach ($settings as $key => $value) {
            $normalized = strtolower((string)$key);
            if (isset($allowedMap[$normalized])) {
                $this->configWriter->save(
                    'payment/paythor_sanalpospro/' . $allowedMap[$normalized],
                    (string)$value
                );
                // Echo the value back using the ORIGINAL key the UI sent so React state stays in sync.
                $updated[$key] = $value;
            }
        }

        // Flush config cache so the new values are visible on the next request (frontend product page).
        $this->cacheTypeList->cleanType('config');
        $this->cacheTypeList->cleanType('full_page');
        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }

        return $this->success('Module settings updated', ['updated_settings' => $updated]);
    }

    /**
     * Action get merchant info.
     *
     * @param array $params
     * @return array
     */
    private function actionGetMerchantInfo(array $params): array
    {
        $pub = $this->paymentConfig->getPublicKey();
        $sec = $this->paymentConfig->getPrivateKey();

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured.');
        }

        try {
            $client = $this->buildClient($pub, $sec);
            $raw    = $client->request('POST', '/merchant/info', []);

            return $client->decodeResponse($raw);
        } catch (\Throwable $e) {
            return $this->error('Merchant info request failed: ' . $e->getMessage());
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Build client.
     *
     * @param string $publicKey
     * @param string $privateKey
     * @return PaythorClient
     */
    private function buildClient(string $publicKey, string $privateKey): PaythorClient
    {
        $client = new PaythorClient([
            'base_url'    => PaymentConfig::API_BASE_URL,
            'public_key'  => $publicKey,
            'private_key' => $privateKey,
        ]);
        $client->setProgramId(PaymentConfig::PROGRAM_ID);
        $client->setAppId($this->paymentConfig->getAppId());

        return $client;
    }

    /**
     * Read config from db.
     *
     * @param string $path
     * @return string
     */
    private function readConfigFromDb(string $path): string
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('core_config_data');
        $value = $connection->fetchOne(
            $connection->select()
                ->from($table, 'value')
                ->where('path = ?', $path)
                ->where('scope = ?', 'default')
                ->where('scope_id = ?', 0)
        );
        return trim((string)($value ?? ''));
    }

    /**
     * Success.
     *
     * @param string $message
     * @param array $data
     * @return array
     */
    private function success(string $message, array $data = []): array
    {
        return [
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'details' => [],
            'meta'    => [
                'xfvv'  => substr($this->paymentConfig->getXfvv(), 0, 10),
                'nonce' => null,
            ],
        ];
    }

    /**
     * Error.
     *
     * @param string $message
     * @return array
     */
    private function error(string $message): array
    {
        return [
            'status'  => 'error',
            'message' => $message,
            'details' => [],
            'meta'    => [
                'xfvv'  => substr($this->paymentConfig->getXfvv(), 0, 10),
                'nonce' => null,
            ],
        ];
    }
}
