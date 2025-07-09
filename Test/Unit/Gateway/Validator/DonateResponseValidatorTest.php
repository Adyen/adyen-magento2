<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\DonateResponseValidator;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;

class DonateResponseValidatorTest extends AbstractAdyenTestCase
{
    protected ?DonateResponseValidator $donateResponseValidator;
    protected ResultInterfaceFactory|MockObject $resultInterfaceFactoryMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->resultInterfaceFactoryMock = $this->createGeneratedMock(ResultInterfaceFactory::class, [
            'create'
        ]);

        $this->donateResponseValidator = new DonateResponseValidator(
            $this->resultInterfaceFactoryMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->donateResponseValidator = null;
    }

    public function testValidateFailure(): void
    {
        $validationSubject = [
            'response' => [
                'payment' => [
                    'resultCode' => ['Refused']
                ],
                'error' => 'API Exception Mock'
            ]
        ];

        $this->resultInterfaceFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => ['An error occurred with the donation.'],
                'errorCodes' => []
            ])
            ->willReturn($this->createMock(ResultInterface::class));

        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with('An error occurred with the donation: API Exception Mock');

        $result = $this->donateResponseValidator->validate($validationSubject);
        $this->assertInstanceOf(ResultInterface::class, $result);
    }
}
