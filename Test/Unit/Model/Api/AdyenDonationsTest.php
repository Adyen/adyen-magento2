<?php
declare(strict_types=1);

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Payment\Gateway\Command\CommandInterface;
use Magento\Sales\Api\Data\OrderInterface;

class AdyenDonationsTest extends AbstractAdyenTestCase
{
    private AdyenDonations $adyenDonations;
    private $commandPool;
    private $jsonSerializer;
    private $dataHelper;
    private $chargedCurrency;
    private $config;
    private $paymentMethodsHelper;
    private $orderRepository;
    private $platformInfo;

    protected function setUp(): void
    {
        $this->commandPool = $this->createMock(CommandPoolInterface::class);
        $this->jsonSerializer = $this->createMock(Json::class);
        $this->dataHelper = $this->createMock(Data::class);
        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->config = $this->createMock(Config::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->platformInfo = $this->createMock(PlatformInfo::class);

        $this->adyenDonations = new AdyenDonations(
            $this->commandPool,
            $this->jsonSerializer,
            $this->dataHelper,
            $this->chargedCurrency,
            $this->config,
            $this->paymentMethodsHelper,
            $this->orderRepository,
            $this->platformInfo
        );
    }

    public function testMakeDonationThrowsExceptionOnCurrencyMismatch(): void
    {
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);
        $order->method('getPayment')->willReturn($payment);

        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', 'token123'],
            ['donationCampaignId', 'camp123']
        ]);

        $orderAmount = $this->createConfiguredMock(\Magento\Framework\DataObject::class, [
            'getCurrencyCode' => 'USD'
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($orderAmount);

        $payload = ['amount' => ['currency' => 'EUR']];

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');

        $this->adyenDonations->makeDonation(json_encode($payload), $order);
    }

    public function testMakeDonationCardSuccess(): void
    {
        $order = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => 123
        ]);

        $payment = $this->createConfiguredMock(Payment::class, [
            'getMethod' => AdyenCcConfigProvider::CODE,
            'getAdditionalInformation' => function ($key) {
                return match ($key) {
                    'donationToken' => 'token123',
                    'donationCampaignId' => 'camp123',
                    'pspReference' => 'psp123'
                };
            }
        ]);
        $order->method('getPayment')->willReturn($payment);

        $orderAmount = $this->createConfiguredMock(\Magento\Framework\DataObject::class, [
            'getCurrencyCode' => 'EUR'
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($orderAmount);
        $this->platformInfo->method('padShopperReference')->willReturn('ref123');

        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('capture')->willReturn($command);
        $command->expects($this->once())->method('execute');

        $this->adyenDonations->makeDonation(json_encode(['amount' => ['currency' => 'EUR']]), $order);
        $this->assertTrue(true); // If no exception, it's a pass
    }

    public function testMakeDonationApmsSuccess(): void
    {
        $order = $this->createConfiguredMock(Order::class, ['getCustomerId' => null, 'getIncrementId' => '1000001']);
        $payment = $this->createConfiguredMock(Payment::class, [
            'getMethod' => 'ideal',
            'getMethodInstance' => 'ideal',
            'getAdditionalInformation' => function ($key) {
                return match ($key) {
                    'donationToken' => 'token123',
                    'donationCampaignId' => 'camp123',
                    'pspReference' => 'psp123'
                };
            }
        ]);
        $order->method('getPayment')->willReturn($payment);

        $orderAmount = $this->createConfiguredMock(\Magento\Framework\DataObject::class, [
            'getCurrencyCode' => 'EUR'
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($orderAmount);

        $this->paymentMethodsHelper->method('isAlternativePaymentMethod')->willReturn(true);
        $this->paymentMethodsHelper->method('getAlternativePaymentMethodTxVariant')->willReturn('ideal');

        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->with('capture')->willReturn($command);
        $command->expects($this->once())->method('execute');

        $this->adyenDonations->makeDonation(json_encode(['amount' => ['currency' => 'EUR']]), $order);
        $this->assertTrue(true);
    }

    public function testMakeDonationRetriesBelowLimit(): void
    {
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);

        $order->method('getPayment')->willReturn($payment);
        $order->method('getCustomerId')->willReturn(123);
        $payment->method('getMethod')->willReturn(AdyenCcConfigProvider::CODE);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', 'token123'],
            ['donationCampaignId', 'camp123'],
            ['pspReference', 'psp123'],
            ['donationTryCount', 3]
        ]);

        $orderAmount = $this->createConfiguredMock(\Magento\Framework\DataObject::class, [
            'getCurrencyCode' => 'EUR'
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($orderAmount);
        $this->platformInfo->method('padShopperReference')->willReturn('ref123');

        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->willReturn($command);
        $command->method('execute')->willThrowException(new LocalizedException(__('fail')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');
        $this->adyenDonations->makeDonation(json_encode(['amount' => ['currency' => 'EUR']]), $order);
    }

    public function testMakeDonationRetriesAboveLimit(): void
    {
        $order = $this->createMock(Order::class);
        $payment = $this->createMock(Payment::class);

        $order->method('getPayment')->willReturn($payment);
        $order->method('getCustomerId')->willReturn(123);
        $payment->method('getMethod')->willReturn(AdyenCcConfigProvider::CODE);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', 'token123'],
            ['donationCampaignId', 'camp123'],
            ['pspReference', 'psp123'],
            ['donationTryCount', 5]
        ]);

        $orderAmount = $this->createConfiguredMock(\Magento\Framework\DataObject::class, [
            'getCurrencyCode' => 'EUR'
        ]);
        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($orderAmount);
        $this->platformInfo->method('padShopperReference')->willReturn('ref123');

        $command = $this->createMock(CommandInterface::class);
        $this->commandPool->method('get')->willReturn($command);
        $command->method('execute')->willThrowException(new LocalizedException(__('fail')));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');
        $this->adyenDonations->makeDonation(json_encode(['amount' => ['currency' => 'EUR']]), $order);
    }
}
