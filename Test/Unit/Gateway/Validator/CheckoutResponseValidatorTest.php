<?php

namespace Adyen\Payment\Test\Unit\Gateway\Validator;

use Adyen\Payment\Gateway\Validator\CheckoutResponseValidator;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Validator\Result;
use Magento\Payment\Gateway\Validator\ResultInterface;
use Magento\Payment\Gateway\Validator\ResultInterfaceFactory;
use Magento\Sales\Model\Order\Payment;

class CheckoutResponseValidatorTest extends AbstractAdyenTestCase
{
    private $checkoutResponseValidator;
    private $resultFactoryMock;
    private $paymentDataObject;
    private $adyenLoggerMock;
    private $adyenHelperMock;


    protected function setUp(): void
    {
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->resultFactoryMock = $this->getMockBuilder(ResultInterfaceFactory::class)
            ->setMethods(['create'])
            ->disableOriginalConstructor()
            ->getMock();
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

        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn(new Result(true));

        $this->checkoutResponseValidator->validate($validationSubject);
    }
     public function testValidateThrowsExceptionForRefusedResultCode()
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

    public function testValidateThrowsExceptionForUnknownResultCode()
    {
        $validationSubject = [
            'payment' => $this->paymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'additionalData' => [],
                    'amount' => [],
                    'resultCode' => 'Some other result code',
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

    public function testValidateHandlesAllowedErrorCode()
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
                    'error' => 'Invalid phone number'
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

        $this->resultFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn(new Result(true));

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

    public function testIfValidationSucceedsOnMiscellaneousResultCodes()
    {
        $resultCodes = [
            'IdentifyShopper',
            'ChallengeShopper',
            'PresentToShopper',
            'Pending',
            'RedirectShopper'
        ];

        $this->resultFactoryMock->expects($this->exactly(count($resultCodes)))
            ->method('create')
            ->with([
                'isValid' => true,
                'failsDescription' => [],
                'errorCodes' => []
            ])
            ->willReturn(new Result(true));

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
                    ],
                ]
            ];

            $this->checkoutResponseValidator->validate($validationSubject);
        }
    }

    public function testIfValidationFailsOnException()
    {
        $mockPaymentDataObject = $this->createMock(PaymentDataObjectInterface::class);
        $mockPaymentDataObject->method('getPayment')->willThrowException(new Exception());

        $validationSubject = [
            'payment' => $mockPaymentDataObject,
            'stateObject' => [],
            'response' => [
                0 => [
                    'error' => 'Mock error message',
                    'errorCode' => '9999'
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
}
