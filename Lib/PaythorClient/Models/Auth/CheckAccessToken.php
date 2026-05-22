<?php

namespace Sanalpospro\SanalPosPro\Lib\PaythorClient\Models\Auth;

class CheckAccessToken
{
    /**
     * @var string
     */
    private string $accessToken;

    /**
     * Set access token.
     *
     * @param string $accessToken
     * @return void
     */
    public function setAccessToken(string $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    /**
     * To array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'accesstoken' => $this->accessToken
        ];
    }
}
