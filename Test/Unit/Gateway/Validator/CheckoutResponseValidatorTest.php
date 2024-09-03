<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Data\Order\OrderAdapter;
use Adyen\Payment\Gateway\Validator\CheckoutResponseValidator;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\ValidatorException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\Result;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\TestFramework\ObjectManager;
use PHPUnit\Framework\TestCase;

class CheckoutResponseValidatorTest extends AbstractAdyenTestCase
{
    /**
     * @var CheckoutResponseValidator
     */
    private $checkoutResponseValidator;

    /**
     * @var ResultInterfaceFactory|\PHPUnit\Framework\MockObject\MockObject
     */
    private $resultFactoryMock;

    /**
     * @var AdyenLogger|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adyenLoggerMock;

    /**
     * @var Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private $adyenHelperMock;

    private $paymentDataObject;

    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenHelperMock = $this->createMock(Data::class);

        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);

        $this->paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);

        $this->checkoutResponseValidator = new CheckoutResponseValidator(
            $this->resultFactoryMock,
            $this->adyenLoggerMock,
            $this->adyenHelperMock
        );
    }

    public function testIfValidationFailsWhenResponseIsEmpty()
    {
        $validationSubject = [
            'payment' => [],
            'stateObject' => [],
            'response' => []
        ];

        $this->expectException(ValidatorException::class);
        $this->expectExceptionMessage("No responses were provided");

        $this->checkoutResponseValidator->validate($validationSubject);
    }

    public function testValidateSuccessWithAuthorisedResultCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'Authorised',
                    'pspReference' => 'ABC12345'
                ]
            ]
        ];

        //       currently the test validates whether the factory is reached successfully. Not mocking the factory,
        //       having it create a real Result object and testing against that object is also possible.
        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn(new Result(true));

        $result = $this->checkoutResponseValidator->validate($validationSubject);
    }
}
