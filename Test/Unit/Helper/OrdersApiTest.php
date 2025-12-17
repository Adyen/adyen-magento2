<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2025 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Helper\Unit;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\CancelOrderRequest;
use Adyen\Model\Checkout\CancelOrderResponse;
use Adyen\Model\Checkout\CreateOrderRequest;
use Adyen\Model\Checkout\CreateOrderResponse;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\OrdersApi as CheckoutOrdersApi;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\Exception;

class OrdersApiTest extends AbstractAdyenTestCase
{
    /**
     * @var OrdersApi
     */
    private OrdersApi $ordersApiHelper;

    /**
     * @var Config
     */
    private Config $configHelper;

    /**
     * @var Data
     */
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    private AdyenLogger $adyenLogger;

    protected function setUp(): void
    {
        $this->configHelper = $this->createMock(Config::class);
        $this->adyenHelper = $this->createMock(Data::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);

        $this->ordersApiHelper = new OrdersApi(
            $this->configHelper,
            $this->adyenHelper,
            $this->adyenLogger
        );
    }

    /**
     * Test createOrder method happy flow
     *
     * This test verifies the complete flow of createOrder:
     * - Builds the request with correct parameters (testing buildOrdersRequest indirectly)
     * - Initializes the Adyen client
     * - Initializes the OrdersApi service
     * - Logs the request
     * - Calls the Adyen orders API
     * - Stores the order data internally via setCheckoutApiOrder
     * - Logs the response
     * - Returns the correct response array
     *
     * @return void
     * @throws AdyenException
     * @throws NoSuchEntityException
     * @throws Exception
     */
    public function testCreateOrder(): void
    {
        // Test data
        $merchantReference = 'ORDER_12345';
        $amount = 10000;
        $currency = 'EUR';
        $storeId = '1';
        $merchantAccount = 'TestMerchantAccount';
        $pspReference = 'PSP_REF_123456';
        $orderData = 'encoded_order_data_string';

        // Expected request structure (verifies buildOrdersRequest logic)
        $expectedRequest = [
            'reference' => $merchantReference,
            'amount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'merchantAccount' => $merchantAccount
        ];

        // Expected response
        $expectedResponse = [
            'pspReference' => $pspReference,
            'orderData' => $orderData,
            'amount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'remainingAmount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'resultCode' => 'Success'
        ];

        // Mock Config helper - verify getMerchantAccount is called
        $this->configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($this->equalTo($storeId))
            ->willReturn($merchantAccount);

        // Mock Adyen client
        $clientMock = $this->createMock(Client::class);

        // Mock Data helper - verify initializeAdyenClient is called
        $this->adyenHelper->expects($this->once())
            ->method('initializeAdyenClient')
            ->with($this->equalTo($storeId))
            ->willReturn($clientMock);

        // Mock CheckoutOrdersApi service
        $checkoutOrdersApiMock = $this->createMock(CheckoutOrdersApi::class);

        // Mock Data helper - verify initializeOrdersApi is called with the client
        $this->adyenHelper->expects($this->once())
            ->method('initializeOrdersApi')
            ->with($this->equalTo($clientMock))
            ->willReturn($checkoutOrdersApiMock);

        // Verify logRequest is called with the expected request structure
        $this->adyenHelper->expects($this->once())
            ->method('logRequest')
            ->with(
                $this->equalTo($expectedRequest),
                $this->equalTo(Client::API_CHECKOUT_VERSION),
                $this->equalTo('/orders')
            );

        // Mock CreateOrderResponse
        $responseMock = $this->createMock(CreateOrderResponse::class);
        $responseMock->expects($this->once())
            ->method('getPspReference')
            ->willReturn($pspReference);

        $responseMock->expects($this->once())
            ->method('getOrderData')
            ->willReturn($orderData);

        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedResponse);

        // Mock the orders method on CheckoutOrdersApi
        $checkoutOrdersApiMock->expects($this->once())
            ->method('orders')
            ->with($this->callback(function ($request) use ($expectedRequest) {
                return $request instanceof CreateOrderRequest;
            }))
            ->willReturn($responseMock);

        // Verify logResponse is called with the response
        $this->adyenHelper->expects($this->once())
            ->method('logResponse')
            ->with($this->equalTo($expectedResponse));

        // Execute the method
        $result = $this->ordersApiHelper->createOrder($merchantReference, $amount, $currency, $storeId);

        // Verify the response
        $this->assertIsArray($result);
        $this->assertEquals($expectedResponse, $result);
        $this->assertArrayHasKey('pspReference', $result);
        $this->assertArrayHasKey('orderData', $result);
        $this->assertEquals($pspReference, $result['pspReference']);
        $this->assertEquals($orderData, $result['orderData']);

        // Verify internal state - the checkout API order should be stored
        $checkoutApiOrder = $this->ordersApiHelper->getCheckoutApiOrder();
        $this->assertIsArray($checkoutApiOrder);
        $this->assertEquals($pspReference, $checkoutApiOrder['pspReference']);
        $this->assertEquals($orderData, $checkoutApiOrder['orderData']);
    }

    /**
     * Test createOrder method when AdyenException is thrown
     *
     * This test verifies the error handling flow:
     * - When the Adyen API throws an AdyenException
     * - The error is logged
     * - The exception is re-thrown
     * - No checkout API order is stored
     * - logResponse is not called
     *
     * @return void
     * @throws Exception|NoSuchEntityException
     */
    public function testCreateOrderThrowsAdyenException(): void
    {
        // Test data
        $merchantReference = 'ORDER_12345';
        $amount = 10000;
        $currency = 'EUR';
        $storeId = '1';
        $merchantAccount = 'TestMerchantAccount';
        $exceptionMessage = 'Connection timeout';

        // Expected request structure
        $expectedRequest = [
            'reference' => $merchantReference,
            'amount' => [
                'value' => $amount,
                'currency' => $currency
            ],
            'merchantAccount' => $merchantAccount
        ];

        // Mock Config helper
        $this->configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($this->equalTo($storeId))
            ->willReturn($merchantAccount);

        // Mock Adyen client
        $clientMock = $this->createMock(Client::class);

        // Mock Data helper
        $this->adyenHelper->expects($this->once())
            ->method('initializeAdyenClient')
            ->with($this->equalTo($storeId))
            ->willReturn($clientMock);

        // Mock CheckoutOrdersApi service
        $checkoutOrdersApiMock = $this->createMock(CheckoutOrdersApi::class);

        $this->adyenHelper->expects($this->once())
            ->method('initializeOrdersApi')
            ->with($this->equalTo($clientMock))
            ->willReturn($checkoutOrdersApiMock);

        // Verify logRequest is called
        $this->adyenHelper->expects($this->once())
            ->method('logRequest')
            ->with(
                $this->equalTo($expectedRequest),
                $this->equalTo(Client::API_CHECKOUT_VERSION),
                $this->equalTo('/orders')
            );

        // Create the exception to be thrown
        $adyenException = new AdyenException($exceptionMessage);

        // Mock the orders method to throw AdyenException
        $checkoutOrdersApiMock->expects($this->once())
            ->method('orders')
            ->with($this->callback(function ($request) {
                return $request instanceof CreateOrderRequest;
            }))
            ->willThrowException($adyenException);

        // Verify error is logged
        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with($this->equalTo(
                "Connection to the endpoint failed. Check the Adyen Live endpoint prefix configuration."
            ));

        // Verify logResponse is NOT called when exception occurs
        $this->adyenHelper->expects($this->never())
            ->method('logResponse');

        // Expect the exception to be re-thrown
        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage($exceptionMessage);

        // Execute the method
        $this->ordersApiHelper->createOrder($merchantReference, $amount, $currency, $storeId);

        // This should not be reached, but verify no order was stored
        $this->assertNull($this->ordersApiHelper->getCheckoutApiOrder());
    }

    /**
     * Test setCheckoutApiOrder and getCheckoutApiOrder methods
     *
     * @return void
     */
    public function testSetAndGetCheckoutApiOrder(): void
    {
        $pspReference = 'PSP_REF_123456';
        $orderData = 'encoded_order_data_string';

        // Initially should be null
        $this->assertNull($this->ordersApiHelper->getCheckoutApiOrder());

        // Set the checkout API order
        $this->ordersApiHelper->setCheckoutApiOrder($pspReference, $orderData);

        // Retrieve and verify
        $result = $this->ordersApiHelper->getCheckoutApiOrder();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('pspReference', $result);
        $this->assertArrayHasKey('orderData', $result);
        $this->assertEquals($pspReference, $result['pspReference']);
        $this->assertEquals($orderData, $result['orderData']);
    }

    /**
     * Test cancelOrder method happy flow
     *
     * This test verifies the complete flow of cancelOrder:
     * - Gets the storeId from the order
     * - Initializes the Adyen client
     * - Initializes the OrdersApi service
     * - Gets the merchant account from config
     * - Builds the request with proper structure
     * - Logs the request
     * - Calls the Adyen cancel order API
     * - Logs the response
     *
     * @return void
     * @throws Exception
     */
    public function testCancelOrder(): void
    {
        // Test data
        $storeId = '1';
        $pspReference = 'PSP_REF_123456';
        $orderData = 'encoded_order_data_string';
        $merchantAccount = 'TestMerchantAccount';

        // Expected response
        $expectedResponse = [
            'pspReference' => $pspReference,
            'response' => '[cancel-received]',
            'status' => 'received'
        ];

        // Mock Order
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);

        // Mock Adyen client
        $clientMock = $this->createMock(Client::class);

        // Mock Config helper
        $this->configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($this->equalTo($storeId))
            ->willReturn($merchantAccount);

        // Mock Data helper
        $this->adyenHelper->expects($this->once())
            ->method('initializeAdyenClient')
            ->with($this->equalTo($storeId))
            ->willReturn($clientMock);

        // Mock OrdersApi service
        $checkoutOrdersApiMock = $this->createMock(CheckoutOrdersApi::class);

        $this->adyenHelper->expects($this->once())
            ->method('initializeOrdersApi')
            ->with($this->equalTo($clientMock))
            ->willReturn($checkoutOrdersApiMock);

        // Expected request structure
        $expectedRequest = [
            'order' => [
                'pspReference' => $pspReference,
                'orderData' => $orderData
            ],
            'merchantAccount' => $merchantAccount,
        ];

        // Verify logRequest is called with correct parameters
        $this->adyenHelper->expects($this->once())
            ->method('logRequest')
            ->with(
                $this->equalTo($expectedRequest),
                $this->equalTo(Client::API_CHECKOUT_VERSION),
                $this->equalTo('/orders/cancel')
            );

        // Mock cancel order response
        $responseMock = $this->createMock(CancelOrderResponse::class);
        $responseMock->expects($this->once())
            ->method('toArray')
            ->willReturn($expectedResponse);

        // Mock the cancelOrder method on CheckoutOrdersApi
        $checkoutOrdersApiMock->expects($this->once())
            ->method('cancelOrder')
            ->with($this->callback(function ($request) {
                return $request instanceof CancelOrderRequest;
            }))
            ->willReturn($responseMock);

        // Verify logResponse is called with the response
        $this->adyenHelper->expects($this->once())
            ->method('logResponse')
            ->with($this->equalTo($expectedResponse));

        // Execute the method
        $this->ordersApiHelper->cancelOrder($orderMock, $pspReference, $orderData);

        // If we reach here, the method completed successfully
        $this->assertTrue(true);
    }

    /**
     * Test cancelOrder method when an exception is thrown
     *
     * This test verifies the error handling flow for cancelOrder:
     * - When any exception (Throwable) occurs during cancellation
     * - The error is logged with the exception message and pspReference
     * - The exception is caught and NOT re-thrown (graceful degradation)
     *
     * @return void
     * @throws Exception
     */
    public function testCancelOrderHandlesException(): void
    {
        // Test data
        $storeId = '1';
        $pspReference = 'PSP_REF_123456';
        $orderData = 'encoded_order_data_string';
        $merchantAccount = 'TestMerchantAccount';
        $exceptionMessage = 'API connection failed';

        // Mock Order
        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->expects($this->once())
            ->method('getStoreId')
            ->willReturn($storeId);

        // Mock Adyen client
        $clientMock = $this->createMock(Client::class);

        // Mock Data helper
        $this->adyenHelper->expects($this->once())
            ->method('initializeAdyenClient')
            ->with($this->equalTo($storeId))
            ->willReturn($clientMock);

        // Mock OrdersApi service
        $checkoutOrdersApiMock = $this->createMock(CheckoutOrdersApi::class);

        $this->adyenHelper->expects($this->once())
            ->method('initializeOrdersApi')
            ->with($this->equalTo($clientMock))
            ->willReturn($checkoutOrdersApiMock);

        // Mock Config helper
        $this->configHelper->expects($this->once())
            ->method('getMerchantAccount')
            ->with($this->equalTo($storeId))
            ->willReturn($merchantAccount);

        // Expected request structure
        $expectedRequest = [
            'order' => [
                'pspReference' => $pspReference,
                'orderData' => $orderData
            ],
            'merchantAccount' => $merchantAccount,
        ];

        // Verify logRequest is called
        $this->adyenHelper->expects($this->once())
            ->method('logRequest')
            ->with(
                $this->equalTo($expectedRequest),
                $this->equalTo(Client::API_CHECKOUT_VERSION),
                $this->equalTo('/orders/cancel')
            );

        // Create exception to be thrown
        $exception = new \Exception($exceptionMessage);

        // Mock cancelOrder to throw exception
        $checkoutOrdersApiMock->expects($this->once())
            ->method('cancelOrder')
            ->willThrowException($exception);

        // Verify error is logged with correct message and context
        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with(
                $this->callback(function ($message) use ($exceptionMessage) {
                    return strpos($message, 'Error while trying to cancel the order') !== false
                        && strpos($message, $exceptionMessage) !== false;
                }),
                $this->equalTo(['pspReference' => $pspReference])
            );

        // Verify logResponse is NOT called when exception occurs
        $this->adyenHelper->expects($this->never())
            ->method('logResponse');

        // Execute the method - should NOT throw exception (it's caught internally)
        $this->ordersApiHelper->cancelOrder($orderMock, $pspReference, $orderData);

        // If we reach here, the exception was handled gracefully
        $this->assertTrue(true);
    }
}
