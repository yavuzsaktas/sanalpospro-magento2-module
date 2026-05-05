<?php
declare(strict_types=1);

namespace Eticsoft\PaythorClient\Resources;

use Eticsoft\PaythorClient\Models\Auth\OtpVerify;
use Eticsoft\PaythorClient\Models\Auth\OtpResend;
use Eticsoft\PaythorClient\Models\Auth\Register;
use Eticsoft\PaythorClient\Models\Auth\SignIn;
use Eticsoft\PaythorClient\Models\Auth\ForgotPassword;
use Eticsoft\PaythorClient\Models\Auth\ResetPassword;
use Eticsoft\PaythorClient\Models\Auth\CheckAccessToken;

class Auth extends Resource
{
    /**
     * Verify OTP code.
     *
     * @param OtpVerify $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function otpVerify(OtpVerify $data): ?array
    {
        $response = $this->client->request('POST', 'otp/verify', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * Resend OTP code.
     *
     * @param OtpResend $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function otpResend(OtpResend $data): ?array
    {
        $response = $this->client->request('POST', 'otp/resend', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * Register a new user and merchant.
     *
     * @param Register $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function register(Register $data): ?array
    {
        $response = $this->client->request('POST', 'auth/register/', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * Sign in to get an access token.
     *
     * @param SignIn $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function signIn(SignIn $data): ?array
    {
        $response = $this->client->request('POST', 'auth/signin', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * Initiate the forgot password process.
     *
     * @param ForgotPassword $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function forgotPassword(ForgotPassword $data): ?array
    {
        $response = $this->client->request('POST', 'auth/forgot-password', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * Reset the user's password using an OTP code.
     *
     * @param ResetPassword $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function resetPassword(ResetPassword $data): ?array
    {
        $response = $this->client->request('POST', 'auth/reset-password', $data->toArray());
        return $this->client->decodeResponse($response);
    }

    /**
     * General token check endpoint.
     *
     * @return array|null Decoded JSON response or null on error.
     */
    public function check(): ?array
    {
        $response = $this->client->request('GET', 'auth/check');
        return $this->client->decodeResponse($response);
    }

    /**
     * Validate an access token.
     *
     * @param CheckAccessToken $data
     * @return array|null Decoded JSON response or null on error.
     */
    public function checkAccessToken(CheckAccessToken $data): ?array
    {
        $response = $this->client->request('POST', 'check/accesstoken', $data->toArray());
        return $this->client->decodeResponse($response);
    }
}
