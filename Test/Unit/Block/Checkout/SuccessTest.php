<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Block\Checkout;

use Adyen\Payment\Block\Checkout\Success;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Model\Ui\AdyenCheckoutSuccessConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\QuoteIdToMaskedQuoteId;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Success::class)]
class SuccessTest extends AbstractAdyenTestCase
{
    private Success $block;

    private Order $order;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $checkoutSession = $this->createGeneratedMock(
            CheckoutSession::class,
            [],
            ['getLastOrderId']
        );
        $customerSession = $this->createMock(CustomerSession::class);
        $quoteIdToMaskedQuoteId = $this->createMock(QuoteIdToMaskedQuoteId::class);
        $orderFactory = $this->createMock(OrderFactory::class);
        $adyenHelper = $this->createMock(Data::class);
        $configHelper = $this->createMock(Config::class);
        $configProvider = $this->createMock(AdyenCheckoutSuccessConfigProvider::class);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $localeHelperMock = $this->createMock(Locale::class);

        $this->order = $this->createMock(Order::class);

        $orderRepository->method('get')->with(1)->willReturn($this->order);
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(10);
        $storeManager->method('getStore')->willReturn($store);

        $this->block = new Success(
            $context,
            $checkoutSession,
            $customerSession,
            $quoteIdToMaskedQuoteId,
            $orderFactory,
            $adyenHelper,
            $configHelper,
            $configProvider,
            $storeManager,
            $serializer,
            $orderRepository,
            $localeHelperMock
        );
    }

    #[Test]
    public function showAdyenGivingReturnsTrueWhenEnabledAndTokenExists(): void
    {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getAdditionalInformation')->with('donationToken')->willReturn('token123');
        $this->order->method('getPayment')->willReturn($payment);

        $this->block = $this->getMockBuilder(Success::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['adyenGivingEnabled', 'getDonationToken'])
            ->getMock();

        $this->block->method('adyenGivingEnabled')->willReturn(true);
        $this->block->method('getDonationToken')->willReturn('"token123"');

        $this->assertTrue($this->block->showAdyenGiving());
    }

    #[Test]
    public function getLocaleReturnsCorrectLocaleCode(): void
    {
        $helper = $this->getProperty($this->block, 'localeHelper');
        $helper->method('getCurrentLocaleCode')->with(10)->willReturn('en_US');
        $this->setProperty($this->block, 'localeHelper', $helper);

        $this->assertSame('en_US', $this->block->getLocale());
    }

    #[Test]
    public function getEnvironmentReturnsTestEnvironment(): void
    {
        $helper = $this->getProperty($this->block, 'adyenHelper');
        $helper->method('getCheckoutEnvironment')->with(10)->willReturn('test');
        $this->setProperty($this->block, 'adyenHelper', $helper);

        $this->assertSame('test', $this->block->getEnvironment());
    }

    #[Test]
    public function getIsCustomerLoggedInReturnsTrue(): void
    {
        $customerSession = $this->getProperty($this->block, 'customerSession');
        $customerSession->method('isLoggedIn')->willReturn(true);
        $this->setProperty($this->block, 'customerSession', $customerSession);

        $this->assertTrue($this->block->getIsCustomerLoggedIn());
    }

    private function getProperty(object $object, string $property): \PHPUnit\Framework\MockObject\MockObject
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    private function setProperty(object $object, string $property, $value): void
    {
        $ref = new \ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }
}
