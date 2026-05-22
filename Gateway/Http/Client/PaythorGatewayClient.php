<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Gateway\Http\Client;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Sanalpospro\SanalPosPro\Model\Api\PaythorAdapter;
use Psr\Log\LoggerInterface;

class PaythorGatewayClient implements ClientInterface
{
    /**
     *
     * @param PaythorAdapter $adapter
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly PaythorAdapter $adapter,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Place request.
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $action  = $request['__action'] ?? 'unknown';

        $this->logger->debug(sprintf('PaythorGatewayClient: dispatching action [%s]', $action), [
            'request_keys' => array_keys($request),
        ]);

        try {
            $response = match ($action) {
                'authorize' => $this->adapter->createPaymentFromGateway($request),
                'capture'   => $this->adapter->captureFromGateway($request),
                'refund'    => $this->adapter->refundFromGateway($request),
                'void'      => $this->adapter->voidFromGateway($request),
                default     => throw new \InvalidArgumentException("Unknown gateway action: {$action}"),
            };
        } catch (\Exception $e) {
            $this->logger->error(sprintf('PaythorGatewayClient: %s failed – %s', $action, $e->getMessage()));
            throw $e;
        }

        $this->logger->debug(sprintf('PaythorGatewayClient: action [%s] completed', $action), [
            'response_keys' => array_keys($response),
        ]);

        return $response;
    }
}
