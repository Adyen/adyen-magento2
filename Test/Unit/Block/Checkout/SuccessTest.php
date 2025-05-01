<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Block\Checkout;

use Adyen\Payment\Block\Checkout\Success;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Quote\Model\QuoteIdToMaskedQuoteId;
use Adyen\Payment\Helper\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Adyen\Payment\Model\Ui\AdyenCheckoutSuccessConfigProvider;
use Magento\Framework\Serialize\SerializerInterface;
use Adyen\Payment\Helper\Data;

class SuccessTest extends AbstractAdyenTestCase
{
    private $objectManager;
    private $checkoutSessionMock;
    private $customerSessionMock;
    private $orderFactoryMock;
    private $orderMock;
    private $quoteIdToMaskedQuoteIdMock;
    private $successBlock;

    protected function setUp(): void
    {
        $storeId = 1;
        $this->objectManager = new ObjectManager($this);
        $this->checkoutSessionMock = $this->createGeneratedMock(
            CheckoutSession::class,
            ['getLastOrderId']
        );
        $this->customerSessionMock = $this->createGeneratedMock(
            CustomerSession::class,
            ['isLoggedIn']
        );
        $this->paymentMock = $this->createMock(Order\Payment::class);
        $this->orderFactoryMock = $this->createGeneratedMock(OrderFactory::class, ['create']);
        $this->orderMock = $this->createMock(Order::class);
        $this->quoteIdToMaskedQuoteIdMock = $this->createMock(QuoteIdToMaskedQuoteId::class);
        $this->configProviderMock = $this->createMock(AdyenCheckoutSuccessConfigProvider::class);
        $this->serializerMock = $this->createMock(SerializerInterface::class);
        $this->configHelperMock = $this->createMock(Config::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $storeMock = $this->createConfiguredMock(StoreInterface::class, [
            'getId' => $storeId
        ]);
        $this->storeManagerMock = $this->createConfiguredMock(StoreManagerInterface::class, [
            'getStore' => $storeMock
        ]);

        $this->successBlock = $this->objectManager->getObject(
            Success::class,
            [
                'checkoutSession' => $this->checkoutSessionMock,
                'customerSession' => $this->customerSessionMock,
                'orderFactory' => $this->orderFactoryMock,
                'quoteIdToMaskedQuoteId' => $this->quoteIdToMaskedQuoteIdMock,
                'configHelper' => $this->configHelperMock,
                'storeManager' => $this->storeManagerMock,
                'serializerInterface' => $this->serializerMock,
                'configProvider' => $this->configProviderMock,
                'adyenHelper' => $this->adyenHelperMock
            ]
        );
    }

    public function testGetMaskedQuoteIdSuccessful()
    {
        $orderId = 100;
        $maskedQuoteId = 'masked_id_123';
        $quoteId = 1;

        $this->checkoutSessionMock->method('getLastOrderId')->willReturn($orderId);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getQuoteId')->willReturn($quoteId);
        $this->quoteIdToMaskedQuoteIdMock->method('execute')->willReturn($maskedQuoteId);

        $result = $this->successBlock->getMaskedQuoteId();
        $this->assertEquals($maskedQuoteId, $result);
    }

    public function testGetMaskedQuoteIdException()
    {
        $orderId = 100;
        $exception = new \Exception('Error during getMaskedQuoteId');

        $this->checkoutSessionMock->method('getLastOrderId')->willReturn($orderId);
        $this->orderFactoryMock->method('create')->willThrowException($exception);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error during getMaskedQuoteId');

        $this->successBlock->getMaskedQuoteId();
    }

    public function testGetIsCustomerLoggedIn()
    {
        $this->customerSessionMock->method('isLoggedIn')->willReturn(true);

        $this->assertTrue($this->successBlock->getIsCustomerLoggedIn());
    }

    public function testRenderActionReturnsTrue()
    {
        $this->paymentMock->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            return match ($key) {
                'resultCode' => PaymentResponseHandler::RECEIVED,
                'action' => ['type' => 'voucher']
            };
        });

        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->checkoutSessionMock->method('getLastOrderId')->willReturn(123);

        $this->assertTrue($this->successBlock->renderAction());
    }

    public function testRenderActionReturnsFalse()
    {
        $this->paymentMock->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            return match ($key) {
                'resultCode' => PaymentResponseHandler::AUTHORISED,
                'action' => ''
            };
        });

        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->checkoutSessionMock->method('getLastOrderId')->willReturn(123);

        $this->assertFalse($this->successBlock->renderAction());
    }

    public function testGetAction()
    {
        $expectedAction = ['type' => 'voucher'];

        $this->paymentMock->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            return match ($key) {
                'action' => ['type' => 'voucher']
            };
        });

        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->checkoutSessionMock->method('getLastOrderId')->willReturn(123);

        $this->assertEquals(json_encode($expectedAction), $this->successBlock->getAction());
    }

    public function testGetDonationToken()
    {
        $expectedToken = 'sample_donation_token';

        $this->paymentMock->method('getAdditionalInformation')->willReturnCallback(function ($key) {
            return match ($key) {
                'donationToken' => 'sample_donation_token'
            };
        });

        $this->orderMock->method('load')->willReturnSelf();
        $this->orderMock->method('getPayment')->willReturn($this->paymentMock);
        $this->orderFactoryMock->method('create')->willReturn($this->orderMock);
        $this->checkoutSessionMock->method('getLastOrderId')->willReturn(123);

        $this->assertEquals(json_encode($expectedToken), $this->successBlock->getDonationToken());
    }

    public function testAdyenGivingEnabled()
    {
        $storeId = 1;

        $this->configHelperMock->method('adyenGivingEnabled')->with($storeId)->willReturn(true);
        $this->assertTrue($this->successBlock->adyenGivingEnabled());
    }

    public function testGetMerchantAccount()
    {
        $storeId = 1;
        $merchantAccount = 'TestMerchant';

        $this->configHelperMock->method('getMerchantAccount')->with($storeId)->willReturn($merchantAccount);

        $this->assertEquals($merchantAccount, $this->successBlock->getMerchantAccount());
    }

    public function testGetSerializedCheckoutConfig()
    {
        $configData = ['some' => 'config'];
        $serialized = '{"some":"config"}';

        $this->configProviderMock->method('getConfig')->willReturn($configData);
        $this->serializerMock->method('serialize')->with($configData)->willReturn($serialized);

        $this->assertEquals($serialized, $this->successBlock->getSerializedCheckoutConfig());
    }

    public function testGetEnvironment()
    {
        $storeId = 1;
        $environment = 'test';

        $this->adyenHelperMock->method('getCheckoutEnvironment')->with($storeId)->willReturn($environment);

        $this->successBlock = $this->objectManager->getObject(
            Success::class,
            [
                'checkoutSession' => $this->checkoutSessionMock,
                'customerSession' => $this->customerSessionMock,
                'orderFactory' => $this->orderFactoryMock,
                'quoteIdToMaskedQuoteId' => $this->quoteIdToMaskedQuoteIdMock,
                'adyenHelper' => $this->adyenHelperMock,
                'storeManager' => $this->storeManagerMock
            ]
        );

        $this->assertEquals($environment, $this->successBlock->getEnvironment());
    }

    public function testGetLocale()
    {
        $storeId = 1;
        $locale = 'en_US';


        $this->adyenHelperMock->method('getCurrentLocaleCode')->with($storeId)->willReturn($locale);

        $this->successBlock = $this->objectManager->getObject(
            Success::class,
            [
                'checkoutSession' => $this->checkoutSessionMock,
                'customerSession' => $this->customerSessionMock,
                'orderFactory' => $this->orderFactoryMock,
                'quoteIdToMaskedQuoteId' => $this->quoteIdToMaskedQuoteIdMock,
                'adyenHelper' => $this->adyenHelperMock,
                'storeManager' => $this->storeManagerMock
            ]
        );

        $this->assertEquals($locale, $this->successBlock->getLocale());
    }

    public function testGetClientKey()
    {
        $clientKey = 'test_client_key';

        $this->configHelperMock->method('isDemoMode')->willReturn(true);
        $this->configHelperMock->method('getClientKey')->with('test')->willReturn($clientKey);

        $this->successBlock = $this->objectManager->getObject(
            Success::class,
            [
                'checkoutSession' => $this->checkoutSessionMock,
                'customerSession' => $this->customerSessionMock,
                'orderFactory' => $this->orderFactoryMock,
                'quoteIdToMaskedQuoteId' => $this->quoteIdToMaskedQuoteIdMock,
                'configHelper' => $this->configHelperMock
            ]
        );

        $this->assertEquals($clientKey, $this->successBlock->getClientKey());
    }

}
