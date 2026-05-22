<?php
declare(strict_types=1);

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Resources;

class Process extends AbstractResource
{
    /**
     * Retrieve process details by token.
     *
     * @param string $token Process token
     * @return array|null
     */
    public function retrieve(string $token): ?array
    {
        $response = $this->client->request('GET', "process/getbytoken/{$token}");
        return $this->client->decodeResponse($response);
    }

    /**
     * Get process by token.
     *
     * @param string $token Process token
     * @return array|null
     */
    public function getByToken(string $token): ?array
    {
        $response = $this->client->request('GET', "process/getbytoken/{$token}");
        return $this->client->decodeResponse($response);
    }
}
