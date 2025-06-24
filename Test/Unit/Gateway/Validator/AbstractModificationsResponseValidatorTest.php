<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\AbstractModificationsResponseValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use PHPUnit\Framework\MockObject\MockObject;

class AbstractModificationsResponseValidatorTest extends AbstractAdyenTestCase
{
    protected ?AbstractModificationsResponseValidator $validator;
    protected ResultInterfaceFactory|MockObject $resultFactoryMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected string $modificationType;
    protected array $validStatuses;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createGeneratedMock(ResultInterfaceFactory::class, [
            'create'
        ]);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $this->modificationType = 'capture';
        $this->validStatuses = ['received'];

        $this->validator = new AbstractModificationsResponseValidator(
            $this->resultFactoryMock,
            $this->adyenLoggerMock,
            $this->modificationType,
            $this->validStatuses
        );
    }

    protected function tearDown(): void
    {
        $this->validator = null;
    }

    public function testEmptyResponseCollection()
    {
        $this->expectException(ValidatorException::class);

        $validationSubject = ['response' => []];

        $this->validator->validate($validationSubject);
    }

    public function testValidateMissingRequiredField()
    {
        $mockResponse = ['status' => 'received'];
        $mockErrorMessage = ['pspReference field is missing in capture response.'];
        $validationSubject = ['response' => [$mockResponse]];

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with(['isValid' => false, 'failsDescription' => $mockErrorMessage, 'errorCodes' => []])
            ->willReturn($resultMock);

        $this->assertInstanceOf(ResultInterface::class, $this->validator->validate($validationSubject));
    }

    public function testValidateInvalidResponse()
    {
        $mockResponse = ['status' => 'failed', 'pspReference' => 'mock_pspreference'];
        $mockErrorMessage = ['An error occurred while validating the capture response'];
        $validationSubject = ['response' => [$mockResponse]];

        $this->adyenLoggerMock->expects($this->once())->method('error');

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with(['isValid' => false, 'failsDescription' => $mockErrorMessage, 'errorCodes' => []])
            ->willReturn($resultMock);

        $this->assertInstanceOf(ResultInterface::class, $this->validator->validate($validationSubject));
    }
}
