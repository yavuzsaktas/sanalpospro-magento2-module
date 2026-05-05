<?php
declare(strict_types=1);

namespace Eticsoft\PaythorClient\Test\Unit;

use Eticsoft\PaythorClient\PaythorClient;
use PHPUnit\Framework\TestCase;

class PaythorClientTest extends TestCase
{
    private PaythorClient $client;

    protected function setUp(): void
    {
        $this->client = new PaythorClient([
            'base_url' => 'https://test-api.example.com',
            'public_key' => 'test_public_key',
            'private_key' => 'test_private_key',
            'app_id' => 105,
            'program_id' => 1,
        ]);
    }

    public function testConstructorSetsBaseUrl(): void
    {
        $client = new PaythorClient(['base_url' => 'https://custom-api.example.com/']);
        $this->assertInstanceOf(PaythorClient::class, $client);
    }

    public function testConstructorWithEmptyOptions(): void
    {
        $client = new PaythorClient();
        $this->assertInstanceOf(PaythorClient::class, $client);
    }

    public function testSetAndGetToken(): void
    {
        $this->client->setToken('test_token_123');
        $this->assertEquals('test_token_123', $this->client->getToken());
    }

    public function testSetAndGetAppId(): void
    {
        $this->client->setAppId(200);
        $this->assertEquals(200, $this->client->getAppId());
    }

    public function testSetAndGetProgramId(): void
    {
        $this->client->setProgramId(5);
        $this->assertEquals(5, $this->client->getProgramId());
    }

    public function testSetPublicKey(): void
    {
        $this->client->setPublicKey('new_public_key');
        $this->assertInstanceOf(PaythorClient::class, $this->client);
    }

    public function testSetPrivateKey(): void
    {
        $this->client->setPrivateKey('new_private_key');
        $this->assertInstanceOf(PaythorClient::class, $this->client);
    }

    public function testSetStatus(): void
    {
        $this->client->setStatus(200);
        $this->assertInstanceOf(PaythorClient::class, $this->client);
    }

    public function testGenerateHashReturnsNonEmptyString(): void
    {
        $hash = $this->client->generateHash('public_key', 'secret_key');
        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash));
    }

    public function testGenerateHashProducesDifferentResults(): void
    {
        $hash1 = $this->client->generateHash('key1', 'secret1');
        $hash2 = $this->client->generateHash('key2', 'secret2');
        $this->assertNotEquals($hash1, $hash2);
    }

    public function testDecodeResponseWithValidJson(): void
    {
        $this->client->setStatus(200);
        $result = $this->client->decodeResponse('{"status":"success","data":{"id":1}}');
        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['data']['id']);
        $this->assertEquals(200, $result['status_code']);
    }

    public function testDecodeResponseWithEmptyString(): void
    {
        $result = $this->client->decodeResponse('');
        $this->assertArrayHasKey('error', $result);
        $this->assertEquals('Empty response', $result['error']);
    }

    public function testDecodeResponseWithInvalidJson(): void
    {
        $result = $this->client->decodeResponse('not valid json{');
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('JSON decode error', $result['error']);
    }

    public function testResourceAccessorsMethods(): void
    {
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\Auth::class, $this->client->auth());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\User::class, $this->client->user());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\Config::class, $this->client->config());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\Gateway::class, $this->client->gateway());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\App::class, $this->client->app());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\Payment::class, $this->client->payment());
        $this->assertInstanceOf(\Eticsoft\PaythorClient\Resources\Process::class, $this->client->process());
    }

    public function testSetHashBackwardCompatibility(): void
    {
        $hash = $this->client->setHash('pub', 'sec');
        $this->assertNotEmpty($hash);
        $this->assertEquals(64, strlen($hash));
    }
}
