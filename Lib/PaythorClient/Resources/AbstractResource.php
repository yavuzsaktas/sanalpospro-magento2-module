<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Resources;

use Sanalpospro\SanalPosPro\Lib\PaythorClient\PaythorClient;

/**
 * Base resource for Paythor client SDK resources.
 */
abstract class AbstractResource
{
    /**
     * @var PaythorClient
     */
    protected PaythorClient $client;

    /**
     * @param PaythorClient $client
     */
    public function __construct(PaythorClient $client)
    {
        $this->client = $client;
    }
}
