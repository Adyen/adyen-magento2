<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\CheckoutResponseValidator;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class CheckoutResponseValidatorTest extends AbstractAdyenTestCase
{
    private CheckoutResponseValidator $checkoutResponseValidator;
    private ResultInterfaceFactory|MockObject $resultFactoryMock;
    private PaymentDataObject $paymentDataObject;
    private AdyenLogger|MockObject $adyenLoggerMock;
    private OrdersApi|MockObject $ordersApiHelperMock;

    protected function setUp(): void
    {
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->resultFactoryMock = $this->createMock(ResultInterfaceFactory::class);
        $this->ordersApiHelperMock = $this->createMock(OrdersApi::class);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $this->paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);

        $this->checkoutResponseValidator = new CheckoutResponseValidator(
            $this->resultFactoryMock,
            $this->adyenLoggerMock,
            $this->ordersApiHelperMock
        );
    }

    public function testIfValidationFailsWhenResponseIsEmpty()
    {
        $validationSubject = [
            'payment' => [],
            'stateObject' => [],
            'response' => []
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [],
                'errorCodes' => ['authError_empty_response']
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }

    public function testIfValidationSucceedsOnMiscellaneousResultCodes()
    {
        $resultCodes = CheckoutResponseValidator::VALID_RESULT_CODES;

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->exactly(count($resultCodes)))->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn($resultMock);

        foreach ($resultCodes as $resultCode) {
            $validationSubject = [
                'payment' => $this->paymentDataObject,
                'stateObject' => [],
                'response' => [
                    0 => [
                        'additionalData' => [],
                        'amount' => [],
                        'resultCode' => $resultCode,
                        'pspReference' => 'ABC12345'
                    ]
                ]
            ];

            $this->checkoutResponseValidator->validate($validationSubject);
        }
    }

     public function testValidateFailsWithRefusedResultCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'Refused',
                    'pspReference' => 'ABC12345'
                ]
            ]
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [],
                'errorCodes' => ['authError_refused']
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }

    public function testValidateFailsWithUnknownResultCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'UNKNOWN_RESULT_CODE',
                    'pspReference' => 'ABC12345'
                ]
            ]
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [],
                'errorCodes' => ['authError_generic']
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }

    public function testValidateFailsWithAllowedErrorCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => '',
                    'pspReference' => 'ABC12345',
                    'errorCode' => '124',
                    'error' => 'Error with payment method, please select a different payment method.'
                ]
            ]
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [],
                'errorCodes' => ['124']
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }

    public function testValidateFailsWithNotAllowedErrorCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => '',
                    'pspReference' => 'ABC12345',
                    'errorCode' => '000',
                    'error' => 'Error with payment method, please select a different payment method.'
                ]
            ]
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => false,
                'failsDescription' => [],
                'errorCodes' => ['authError_generic']
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }

     public function testValidateForSuccessfulPartialPayments()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'Authorised',
                    'pspReference' => 'ABC12345',
                    'paymentMethod' => [
                        'name' => 'giftcard',
                        'type' => 'Givex',
                    ]
                ],
                1 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'Authorised',
                    'pspReference' => 'ABC12345',
                    'paymentMethod' => [
                        'name' => 'card',
                        'type' => 'CreditCard',
                    ]
                ]
            ]
        ];

        $resultMock = $this->createMock(ResultInterface::class);
        $this->resultFactoryMock->expects($this->once())->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn($resultMock);

        $this->checkoutResponseValidator->validate($validationSubject);
    }


     public function testValidateForFailedPartialPayments()
     {
         $validationSubject = [
             'payment' => $this->paymentDataObject,
             'stateObject' => [],
             'response' => [
                 0 => [
                     'additionalData' => [],
                     'amount' => [],
                     'resultCode' => 'Authorised',
                     'pspReference' => 'ABC12345',
                     'paymentMethod' => [
                         'name' => 'Givex',
                         'type' => 'giftcard',
                     ]
                 ],
                 1 => [
                     'additionalData' => [],
                     'amount' => [],
                     'resultCode' => 'Refused',
                     'pspReference' => 'ABC12345',
                     'paymentMethod' => [
                         'name' => 'Cards',
                         'type' => 'scheme',
                     ]
                 ]
             ]
         ];

         $resultMock = $this->createMock(ResultInterface::class);
         $this->resultFactoryMock->expects($this->once())->method('create')
             ->with([
                 'isValid' => false,
                 'failsDescription' => [],
                 'errorCodes' => ['authError_refused']
             ])
             ->willReturn($resultMock);

         $this->checkoutResponseValidator->validate($validationSubject);
     }
}
