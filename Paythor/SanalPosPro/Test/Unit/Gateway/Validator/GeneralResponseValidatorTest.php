<?php
declare(strict_types=1);

namespace Paythor\SanalPosPro\Test\Unit\Gateway\Validator;

use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Paythor\SanalPosPro\Gateway\Validator\GeneralResponseValidator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GeneralResponseValidatorTest extends TestCase
{
    private GeneralResponseValidator $validator;
    private ResultInterfaceFactory|MockObject $resultFactory;

    protected function setUp(): void
    {
        $this->resultFactory = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->resultFactory->method('create')
            ->willReturnCallback(function (array $args) {
                $result = $this->createMock(ResultInterface::class);
                $result->method('isValid')->willReturn($args['isValid'] ?? true);
                $result->method('getFailsDescription')->willReturn($args['failsDescription'] ?? []);
                $result->method('getErrorCodes')->willReturn($args['errorCodes'] ?? []);
                return $result;
            });

        $this->validator = new GeneralResponseValidator($this->resultFactory);
    }

    public function testValidateSuccessfulResponse(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'status_code' => 200,
                'paythor_status' => 'success',
            ],
        ]);

        $this->assertTrue($result->isValid());
    }

    public function testValidateResponseWithError(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'error' => 'Something went wrong',
                'error_code' => 'ERR_001',
                'error_message' => 'Payment failed',
            ],
        ]);

        $this->assertFalse($result->isValid());
    }

    public function testValidateResponseWithHttpError(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'status_code' => 500,
            ],
        ]);

        $this->assertFalse($result->isValid());
    }

    public function testValidateResponseWithFailedStatus(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'paythor_status' => 'failed',
                'status_code' => 200,
            ],
        ]);

        $this->assertFalse($result->isValid());
    }

    public function testValidateResponseWithDeclinedStatus(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'paythor_status' => 'declined',
                'status_code' => 200,
            ],
        ]);

        $this->assertFalse($result->isValid());
    }

    public function testValidateResponseWithErrorStatus(): void
    {
        $result = $this->validator->validate([
            'response' => [
                'paythor_status' => 'error',
                'status_code' => 200,
            ],
        ]);

        $this->assertFalse($result->isValid());
    }

    public function testValidateEmptyResponse(): void
    {
        $result = $this->validator->validate([
            'response' => [],
        ]);

        $this->assertTrue($result->isValid());
    }

    public function testValidateMissingResponseKey(): void
    {
        $result = $this->validator->validate([]);

        $this->assertTrue($result->isValid());
    }
}
