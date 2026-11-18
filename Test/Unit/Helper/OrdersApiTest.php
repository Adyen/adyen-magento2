<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2026 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Helper\Unit;

use Adyen\AdyenException;
use Adyen\Client;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\BalanceCheckRequest;
use Adyen\Model\Checkout\BalanceCheckResponse;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Service\Checkout\OrdersApi as CheckoutOrdersApi;
use PHPUnit\Framework\MockObject\MockObject;

class OrdersApiTest extends AbstractAdyenTestCase
{
    private OrdersApi $ordersApiHelper;
    private Config|MockObject $configHelper;
    private Data|MockObject $adyenHelper;
    private AdyenLogger|MockObject $adyenLogger;

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

    public function testGetGiftcardBalance(): void
    {
        $storeId = 2;
        $request = [
            'paymentMethod' => [
                'type' => 'giftcard',
                'brand' => 'givex',
                'encryptedCardNumber' => 'test_6036280000000000000'
            ],
            'amount' => ['currency' => 'EUR', 'value' => 6000]
        ];

        $response = new BalanceCheckResponse([
            'resultCode' => BalanceCheckResponse::RESULT_CODE_SUCCESS,
            'balance' => new Amount(['currency' => 'EUR', 'value' => 5000])
        ]);

        $checkoutOrdersApiMock = $this->setupGiftcardBalanceService($storeId);
        $checkoutOrdersApiMock->expects($this->once())
            ->method('getBalanceOfGiftCard')
            ->with($this->callback(function (BalanceCheckRequest $balanceCheckRequest) use ($request) {
                return $balanceCheckRequest->getMerchantAccount() === 'TestMerchantAccount'
                    && $balanceCheckRequest->getAmount() === $request['amount']
                    && $balanceCheckRequest->getPaymentMethod() === $request['paymentMethod'];
            }))
            ->willReturn($response);

        $this->adyenLogger->expects($this->never())->method('error');

        $this->assertSame($response, $this->ordersApiHelper->getGiftcardBalance($request, $storeId));
    }

    public function testGetGiftcardBalanceThrowsOnFailedResultCode(): void
    {
        $response = new BalanceCheckResponse([
            'resultCode' => BalanceCheckResponse::RESULT_CODE_FAILED,
            'additionalData' => ['acquirerResponseCode' => 'Invalid PIN']
        ]);

        $checkoutOrdersApiMock = $this->setupGiftcardBalanceService(1);
        $checkoutOrdersApiMock->method('getBalanceOfGiftCard')->willReturn($response);

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with('An error occurred during balance check! Invalid PIN');

        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Invalid PIN');

        $this->ordersApiHelper->getGiftcardBalance(['paymentMethod' => ['type' => 'giftcard']], 1);
    }

    public function testGetGiftcardBalanceRethrowsAdyenException(): void
    {
        $checkoutOrdersApiMock = $this->setupGiftcardBalanceService(1);
        $checkoutOrdersApiMock->method('getBalanceOfGiftCard')
            ->willThrowException(new AdyenException('Connection timeout'));

        $this->adyenLogger->expects($this->once())
            ->method('error')
            ->with('An error occurred during balance check! Connection timeout');

        $this->expectException(AdyenException::class);
        $this->expectExceptionMessage('Connection timeout');

        $this->ordersApiHelper->getGiftcardBalance(['paymentMethod' => ['type' => 'giftcard']], 1);
    }

    private function setupGiftcardBalanceService(int $storeId): CheckoutOrdersApi|MockObject
    {
        $clientMock = $this->createMock(Client::class);
        $checkoutOrdersApiMock = $this->createMock(CheckoutOrdersApi::class);

        $this->configHelper->method('getMerchantAccount')->with($storeId)->willReturn('TestMerchantAccount');
        $this->adyenHelper->method('initializeAdyenClient')->with($storeId)->willReturn($clientMock);
        $this->adyenHelper->method('initializeOrdersApi')->with($clientMock)->willReturn($checkoutOrdersApiMock);

        return $checkoutOrdersApiMock;
    }
}
