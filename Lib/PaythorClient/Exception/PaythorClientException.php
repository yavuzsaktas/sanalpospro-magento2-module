<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Exception;

/**
 * Specialised exception for Paythor client SDK errors.
 *
 * Allows callers to distinguish SDK-originated failures (auth, OTP, install,
 * API-key retrieval, etc.) from generic runtime errors.
 */
class PaythorClientException extends \RuntimeException
{
}
