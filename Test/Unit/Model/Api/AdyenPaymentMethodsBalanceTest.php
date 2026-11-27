<?php

namespace Adyen\Payment\Test\Unit\Model\Api;

use Adyen\AdyenException;
use Adyen\Model\Checkout\Amount;
use Adyen\Model\Checkout\BalanceCheckResponse;
use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Model\Api\AdyenPaymentMethodsBalance;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class AdyenPaymentMethodsBalanceTest extends AbstractAdyenTestCase
{
    private StoreManager $storeManager;
    private OrdersApi $ordersApiHelper;
    private BalanceCheckResponse $response;
    private Json $jsonSerializer;
    private AdyenPaymentMethodsBalance $adyenPaymentMethodsBalance;

    protected function setUp(): void
    {
        $this->jsonSerializer = new Json();
        $this->storeManager = $this->createConfiguredMock(
            StoreManager::class,
            [
                'getStore' => $this->createConfiguredMock(
                    Store::class, [
                        'getId' => 1
                    ]
                ),
            ]
        );
        $this->response = new BalanceCheckResponse();
        $this->response->setResultCode('Success');
        $this->response->setBalance(new Amount(['currency'=> 'EUR', 'value'=> 400]));
        $this->ordersApiHelper = $this->createMock(OrdersApi::class);

        $this->adyenPaymentMethodsBalance = new AdyenPaymentMethodsBalance(
            $this->jsonSerializer,
            $this->storeManager,
            $this->ordersApiHelper
        );
    }

    public function testSuccessfulGetBalance()
    {
        $payload = '{"paymentMethod":{"type":"giftcard","brand":"genericgiftcard","encryptedCardNumber":"eyJhbGciOi......"},"amount":{"currency":"EUR","value":8361}}';

        $this->ordersApiHelper->expects($this->once())
            ->method('getGiftcardBalance')
            ->with(json_decode($payload, true), 1)
            ->willReturn($this->response);

        $balance = $this->adyenPaymentMethodsBalance->getBalance($payload);
        $this->assertEquals(json_encode($this->response->jsonSerialize()), $balance);
    }

    public function testFailedGetBalance()
    {
        $payload = '{"paymentMethod":{"type":"giftcard","brand":"genericgiftcard","encryptedCardNumber":"eyJhbGciOi......"},"amount":{"currency":"EUR","value":8361}}';

        $this->ordersApiHelper->method('getGiftcardBalance')
            ->willThrowException(new AdyenException('Balance check failed'));

        $this->expectException(AdyenException::class);
        $this->adyenPaymentMethodsBalance->getBalance($payload);
    }
}
