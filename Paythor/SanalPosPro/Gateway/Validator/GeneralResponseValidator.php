<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Gateway\Validator;

use Magento\Payment\Gateway\Validator\AbstractValidator;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class GeneralResponseValidator extends AbstractValidator
{
    public function __construct(ResultInterfaceFactory $resultFactory)
    {
        parent::__construct($resultFactory);
    }

    public function validate(array $validationSubject): ResultInterface
    {
        $response = $validationSubject['response'] ?? [];

        $isValid      = true;
        $errorMessages = [];
        $errorCodes    = [];

        if (isset($response['error']) && $response['error'] !== '') {
            $isValid        = false;
            $errorMessages[] = (string) ($response['error_message'] ?? $response['error']);
            $errorCodes[]    = (string) ($response['error_code'] ?? 'GATEWAY_ERROR');
        }

        $statusCode = (int) ($response['status_code'] ?? 200);
        if ($statusCode >= 400) {
            $isValid        = false;
            $errorMessages[] = (string) ($response['error_message'] ?? __('Payment gateway returned an error.'));
            $errorCodes[]    = (string) ($response['error_code'] ?? 'HTTP_' . $statusCode);
        }

        $paythorStatus = $response['paythor_status'] ?? null;
        if ($paythorStatus !== null && in_array($paythorStatus, ['failed', 'declined', 'error'], true)) {
            $isValid        = false;
            $errorMessages[] = (string) ($response['error_message'] ?? __('Payment was declined by the gateway.'));
            $errorCodes[]    = (string) ($response['error_code'] ?? 'PAYMENT_' . strtoupper($paythorStatus));
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
