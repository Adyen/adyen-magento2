<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2024 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\OrderStatusHistory;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Status\History;
use Magento\Sales\Model\OrderRepository;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\ResourceModel\PaymentResponse\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Adyen\Payment\Helper\PaymentMethods;
use ReflectionClass;
use ReflectionException;

class PaymentResponseHandlerTest extends AbstractAdyenTestCase
{
    const MERCHANT_REFERENCE = '00123456';

    private Payment $paymentMock;
    private MagentoOrder $orderMock;
    private AdyenLogger $adyenLoggerMock;
    private Quote $quoteHelperMock;
    private OrderRepository $orderRepositoryMock;
    private StateData $stateDataHelperMock;
    private PaymentResponseHandler $paymentResponseHandler;
    private Adapter|MockObject $paymentMethodInstanceMock;
    private PaymentMethods|MockObject $paymentMethodsHelperMock;
    private OrdersApi|MockObject $ordersApiHelperMock;

    protected function setUp(): void
    {
        // Constructor argument mocks
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $vaultHelperMock = $this->createMock(Vault::class);
        $this->quoteHelperMock = $this->createMock(Quote::class);
        $orderHelperMock = $this->createMock(OrderHelper::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepository::class);
        $this->stateDataHelperMock = $this->createMock(StateData::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $orderStatusHistoryMock = $this->createMock(OrderStatusHistory::class);
        $this->ordersApiHelperMock = $this->createMock(OrdersApi::class);

        // Other functional mocks
        $this->paymentMock  = $this->createMock(Payment::class);
        $this->orderMock = $this->createMock(MagentoOrder::class);
        $this->paymentMethodInstanceMock = $this->createMock(Adapter::class);

        $orderHistory = $this->createMock(History::class);
        $orderHistory->method('setStatus')->willReturnSelf();
        $orderHistory->method('setComment')->willReturnSelf();
        $orderHistory->method('setEntityName')->willReturnSelf();
        $orderHistory->method('setOrder')->willReturnSelf();

        $this->orderMock->method('getQuoteId')->willReturn(1);
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderMock->method('getStatus')->willReturn('pending');
        $this->orderMock->method('getIncrementId')->willReturn(self::MERCHANT_REFERENCE);
        $this->paymentMock->method('getMethodInstance')->willReturn($this->paymentMethodInstanceMock);

        $orderHelperMock->method('setStatusOrderCreation')->willReturn($this->orderMock);

        $this->paymentResponseHandler = new PaymentResponseHandler(
            $this->adyenLoggerMock,
            $vaultHelperMock,
            $this->quoteHelperMock,
            $orderHelperMock,
            $this->orderRepositoryMock,
            $this->stateDataHelperMock,
            $this->paymentMethodsHelperMock,
            $orderStatusHistoryMock,
            $this->ordersApiHelperMock
        );
    }

    public static function dataSourceForFormatPaymentResponseFinalResultCodes(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::AUTHORISED],
            ['resultCode' => PaymentResponseHandler::REFUSED],
            ['resultCode' => PaymentResponseHandler::ERROR],
            ['resultCode' => PaymentResponseHandler::POS_SUCCESS]
        ];
    }

    /**
     * @param $resultCode
     * @return void
     * @dataProvider dataSourceForFormatPaymentResponseFinalResultCodes
     */
    public function testFormatPaymentResponseForFinalResultCodes($resultCode)
    {
        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    public static function dataSourceForFormatPaymentResponseActionRequiredPayments(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::REDIRECT_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::IDENTIFY_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::CHALLENGE_SHOPPER, 'action' => ['type' => 'qrCode']],
            ['resultCode' => PaymentResponseHandler::PENDING, 'action' => ['type' => 'qrCode']],
        ];
    }

    /**
     * @param $resultCode
     * @param $action
     * @return void
     * @dataProvider dataSourceForFormatPaymentResponseActionRequiredPayments
     */
    public function testFormatPaymentResponseForActionRequiredPayments($resultCode, $action)
    {
        $expectedResult = [
            "isFinal" => false,
            "resultCode" => $resultCode,
            "action" => $action
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, $action);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForVoucherPayments()
    {
        $resultCode = PaymentResponseHandler::PRESENT_TO_SHOPPER;
        $action = ['type' => 'voucher'];

        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode,
            "action" => $action
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, $action);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForOfflinePayments()
    {
        $resultCode = PaymentResponseHandler::RECEIVED;
        $action = ['type' => 'voucher'];
        $expectedResult = [
            "isFinal" => true,
            "resultCode" => $resultCode,
            "action" => $action
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode, $action);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @return void
     */
    public function testFormatPaymentResponseForUnknownResults()
    {
        $resultCode = 'UNRECOGNISED_RESULT_CODE';

        $expectedResult = [
            "isFinal" => true,
            "resultCode" => PaymentResponseHandler::ERROR
        ];

        // Execute method of the tested class
        $result = $this->paymentResponseHandler->formatPaymentResponse($resultCode);

        // Assert conditions
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws \PHPUnit\Framework\MockObject\Exception
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsResponseWithNullResultCode()
    {
        $orderMock = $this->createMock(MagentoOrder::class);

        $paymentsDetailsResponse = [
            'randomData' => 'someRandomValue'
        ];

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $orderMock
        );

        $this->assertFalse($result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsResponseAuthorised()
    {
        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::AUTHORISED,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => 'ideal'
            ],
            'additionalData' => [
                'someData' => 'someValue'
            ],
            'details' => [
                'someData' => 'someValue'
            ],
            'donationToken' => 'XYZ123456789',
            'merchantReference' => self::MERCHANT_REFERENCE
        ];

        $this->quoteHelperMock->method('disableQuote')->willThrowException(new Exception());
        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertTrue($result);
    }


    public static function handlePaymentsDetailsPendingProvider(): array
    {
        return [
            ['paymentMethodCode' => 'bankTransfer'],
            ['paymentMethodCode' => 'sepadirectdebit'],
            ['paymentMethodCode' => 'multibanco'],
        ];
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider handlePaymentsDetailsPendingProvider
     */
    public function testHandlePaymentsDetailsResponsePending($paymentMethodCode)
    {
        $this->stateDataHelperMock->method('cleanQuoteStateData')
            ->willThrowException(new Exception);
        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');

        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::PENDING,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => $paymentMethodCode
            ],
            'merchantReference' => self::MERCHANT_REFERENCE
        ];

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertTrue($result);
    }

    public static function handlePaymentsDetailsPendingReceived(): array
    {
        return [
            ['paymentMethodCode' => 'alipay_hk', 'expectedResult' => false],
            ['paymentMethodCode' => 'multibanco', 'expectedResult' => true]
        ];
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider handlePaymentsDetailsPendingReceived
     */
    public function testHandlePaymentsDetailsResponseReceived($paymentMethodCode, $expectedResult)
    {
        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::RECEIVED,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => $paymentMethodCode
            ],
            'merchantReference' => self::MERCHANT_REFERENCE
        ];

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertEquals($expectedResult, $result);
    }

    public static function handlePaymentsDetailsActionRequiredProvider(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::PRESENT_TO_SHOPPER],
            ['resultCode' => PaymentResponseHandler::IDENTIFY_SHOPPER],
            ['resultCode' => PaymentResponseHandler::CHALLENGE_SHOPPER],
            ['resultCode' => PaymentResponseHandler::REDIRECT_SHOPPER]
        ];
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException
     * @dataProvider handlePaymentsDetailsActionRequiredProvider
     */
    public function testHandlePaymentsDetailsResponseActionRequired($resultCode)
    {
        $paymentsDetailsResponse = [
            'resultCode' => $resultCode,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => 'ideal'
            ],
            'merchantReference' => self::MERCHANT_REFERENCE,
            'action' => [
                'actionData' => 'actionValue'
            ]
        ];

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('addAdyenResult');

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertTrue($result);
    }

    public static function handlePaymentsDetailsActionCancelledOrRefusedProvider(): array
    {
        return [
            ['resultCode' => PaymentResponseHandler::REFUSED],
            ['resultCode' => PaymentResponseHandler::CANCELLED]
        ];
    }

    /**
     * @return void
     * @throws AlreadyExistsException
     * @throws InputException
     * @throws NoSuchEntityException|LocalizedException
     * @dataProvider handlePaymentsDetailsActionCancelledOrRefusedProvider
     */
    public function testHandlePaymentsDetailsResponseCancelOrRefused($resultCode)
    {
        $paymentsDetailsResponse = [
            'resultCode' => $resultCode,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => 'ideal'
            ],
            'merchantReference' => self::MERCHANT_REFERENCE,
            'action' => [
                'actionData' => 'actionValue'
            ]
        ];

        // Mock order cancellation
        $this->orderMock->expects($this->any())
            ->method('canCancel')
            ->willReturn(true);

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('addAdyenResult');

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertFalse($result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsResponseInvalid()
    {
        $paymentsDetailsResponse = [
            'resultCode' => 'UNRECOGNISED_RESULT_CODE'
        ];

        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertFalse($result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsEmptyResponse()
    {
        $paymentsDetailsResponse = [];
        $this->adyenLoggerMock->expects($this->atLeastOnce())->method('error');

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertFalse($result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsResponseInvalidMerchantReference(){
        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::AUTHORISED,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => 'ideal'
            ],
            'merchantReference' => '00777777'
        ];

        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        $this->assertFalse($result);
    }

    /**
     * @throws ReflectionException
     */
    public function testHandlePaymentsDetailsResponseValidMerchantReference()
    {
        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::AUTHORISED,
            'pspReference' => 'ABC123456789',
            'paymentMethod' => [
                'brand' => 'ideal'
            ],
            'merchantReference' => self::MERCHANT_REFERENCE
        ];
        // Mock the isValidMerchantReference to return true
        $reflectionClass = new ReflectionClass(PaymentResponseHandler::class);
        $method = $reflectionClass->getMethod('isValidMerchantReference');
        $isValidMerchantReference = $method->invokeArgs($this->paymentResponseHandler, [$paymentsDetailsResponse,$this->orderMock]);
        $this->assertTrue($isValidMerchantReference);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testPaymentDetailsCallFailureLogsError()
    {
        $resultCode = 'some_result_code';
        $paymentsDetailsResponse = ['error' => 'some error message'];

        // Expect the logger to be called with the specific message
        $this->adyenLoggerMock->expects($this->once())
            ->method('error');

        // Call the method that triggers the logging, e.g., handlePaymentDetailsFailure()
        $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testLogsErrorAndReturnsFalseForUnknownResult()
    {
        // Arrange
        $paymentsDetailsResponse = [
            'merchantReference' => self::MERCHANT_REFERENCE
        ];

        // Mock the logger to expect an error to be logged
        $this->adyenLoggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Unexpected result query parameter. Response: ' . json_encode($paymentsDetailsResponse)));

        // Act: Call the method that will trigger the unexpected result handling
        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse($paymentsDetailsResponse, $this->orderMock);

        // Assert: Ensure the method returned false
        $this->assertFalse($result);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testOrderStatusUpdateWhenResponseIsValid()
    {
        $paymentsDetailsResponse = [
            'merchantReference' => self::MERCHANT_REFERENCE,
            'resultCode' => 'AUTHORISED'
        ];

        $this->orderMock->expects($this->once())
            ->method('getState')
            ->willReturn('pending_payment');

        // Mock the order repository to save the order
        $this->orderRepositoryMock->expects($this->once())
            ->method('save')
            ->with($this->orderMock);

        $this->paymentResponseHandler->handlePaymentsDetailsResponse($paymentsDetailsResponse, $this->orderMock);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    public function testHandlePaymentsDetailsResponseSetsCcType()
    {

        // Mock the method `isWalletPaymentMethod` in your helper if it's being checked
        $this->paymentMethodsHelperMock->method('isWalletPaymentMethod')
            ->with($this->paymentMethodInstanceMock)
            ->willReturn(false); // Assuming false for this test case

        // Payment details response with a payment method brand
        $paymentsDetailsResponse = [
            'resultCode' => PaymentResponseHandler::AUTHORISED,
            'paymentMethod' => [
                'brand' => 'VI'
            ],
            'merchantReference' => self::MERCHANT_REFERENCE
        ];

        // Expect the `setCcType` method to be called on the payment object with the correct value
        $this->paymentMock
            ->method('setCcType')
            ->with($this->equalTo('VI'));

        // Call the method under test
        $result = $this->paymentResponseHandler->handlePaymentsDetailsResponse(
            $paymentsDetailsResponse,
            $this->orderMock
        );

        // Assert the response is as expected
        $this->assertTrue($result);
    }
}
