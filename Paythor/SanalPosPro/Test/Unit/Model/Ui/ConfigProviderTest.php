<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Test\Unit\Model\Ui;

use Paythor\SanalPosPro\Model\Config\PaymentConfig;
use Paythor\SanalPosPro\Model\Ui\ConfigProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    private ConfigProvider $configProvider;
    private PaymentConfig|MockObject $paymentConfig;

    protected function setUp(): void
    {
        $this->paymentConfig = $this->createMock(PaymentConfig::class);
        $this->configProvider = new ConfigProvider($this->paymentConfig);
    }

    public function testGetCodeConstant(): void
    {
        $this->assertEquals('paythor_sanalpospro', ConfigProvider::CODE);
    }

    public function testGetConfigWhenOperational(): void
    {
        $this->paymentConfig->method('isOperational')->willReturn(true);
        $this->paymentConfig->method('isActive')->willReturn(true);
        $this->paymentConfig->method('getTitle')->willReturn('Credit Card (Paythor)');
        $this->paymentConfig->method('isSandboxMode')->willReturn(false);

        $config = $this->configProvider->getConfig();

        $this->assertArrayHasKey('payment', $config);
        $this->assertArrayHasKey('paythor_sanalpospro', $config['payment']);

        $methodConfig = $config['payment']['paythor_sanalpospro'];
        $this->assertTrue($methodConfig['isActive']);
        $this->assertEquals('Credit Card (Paythor)', $methodConfig['title']);
        $this->assertFalse($methodConfig['isSandbox']);
    }

    public function testGetConfigWhenNotOperational(): void
    {
        $this->paymentConfig->method('isOperational')->willReturn(false);

        $config = $this->configProvider->getConfig();

        $this->assertEmpty($config);
    }

    public function testGetConfigSandboxMode(): void
    {
        $this->paymentConfig->method('isOperational')->willReturn(true);
        $this->paymentConfig->method('isActive')->willReturn(true);
        $this->paymentConfig->method('getTitle')->willReturn('Test Payment');
        $this->paymentConfig->method('isSandboxMode')->willReturn(true);

        $config = $this->configProvider->getConfig();

        $this->assertTrue($config['payment']['paythor_sanalpospro']['isSandbox']);
    }
}
