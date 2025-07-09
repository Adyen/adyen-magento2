<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\Address;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Helper\PlatformInfo;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;

#[CoversClass(Requests::class)]
class RequestsTest extends AbstractAdyenTestCase
{
    private Requests $requests;
    private Data $adyenHelper;
    private Config $adyenConfig;
    private Address $addressHelper;
    private StateData $stateData;
    private Vault $vaultHelper;
    private Http $request;
    private ChargedCurrency $chargedCurrency;
    private PaymentMethods $paymentMethodsHelper;
    private Locale $localeHelper;
    private Context $context;

    protected function setUp(): void
    {
        $this->adyenHelper = $this->createMock(Data::class);
        $this->adyenConfig = $this->createMock(Config::class);
        $this->addressHelper = $this->createMock(Address::class);
        $this->stateData = $this->createMock(StateData::class);
        $this->vaultHelper = $this->createMock(Vault::class);
        $this->request = $this->createMock(Http::class);
        $this->chargedCurrency = $this->createMock(ChargedCurrency::class);
        $this->paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        $this->localeHelper = $this->createMock(Locale::class);
        $this->context = $this->createMock(Context::class);
        $this->context->method('getRequest')->willReturn($this->request);

        $this->requests = new Requests(
            $this->context,
            $this->adyenHelper,
            $this->adyenConfig,
            $this->addressHelper,
            $this->stateData,
            $this->vaultHelper,
            $this->chargedCurrency,
            $this->paymentMethodsHelper,
            $this->localeHelper
        );
    }

    #[Test]
    public function buildMerchantAccountDataAddsCorrectKey(): void
    {
        $this->adyenHelper->method('getAdyenMerchantAccount')
            ->with('scheme', 1)
            ->willReturn('TestMerchant123');

        $result = $this->requests->buildMerchantAccountData('scheme', 1);

        $this->assertArrayHasKey('merchantAccount', $result);
        $this->assertEquals('TestMerchant123', $result['merchantAccount']);
    }

    #[Test]
    public function buildCustomerIpDataAddsIpAddress(): void
    {
        $result = $this->requests->buildCustomerIpData('192.168.0.1');

        $this->assertArrayHasKey('shopperIP', $result);
        $this->assertSame('192.168.0.1', $result['shopperIP']);
    }

    #[Test]
    public function buildPaymentDataFormatsAmountCorrectly(): void
    {
        $this->adyenHelper->method('formatAmount')
            ->with(15.99, 'EUR')
            ->willReturn(1599);

        $result = $this->requests->buildPaymentData(15.99, 'EUR', 'ref123');

        $this->assertSame(['currency' => 'EUR', 'value' => 1599], $result['amount']);
        $this->assertSame('ref123', $result['reference']);
    }

    #[Test]
    public function buildBrowserDataReturnsUserAgentAndAcceptHeader(): void
    {
        $this->request->method('getServer')->willReturnMap([
            ['HTTP_USER_AGENT', null, 'Mozilla'],
            ['HTTP_ACCEPT', null, 'text/html']
        ]);

        $result = $this->requests->buildBrowserData();

        $this->assertSame('Mozilla', $result['browserInfo']['userAgent']);
        $this->assertSame('text/html', $result['browserInfo']['acceptHeader']);
    }

    #[Test]
    public function getShopperReferenceForCustomerIdReturnsPaddedValue(): void
    {
        $this->adyenHelper->expects($this->once())
            ->method('padShopperReference')
            ->with('42')
            ->willReturn('user_42');

        $result = $this->requests->getShopperReference('42', '0000123');

        $this->assertSame('user_42', $result);
    }

    #[Test]
    public function getShopperReferenceForGuestReturnsCombinedValue(): void
    {
        $this->adyenHelper->expects($this->never())->method('padShopperReference');

        $result = $this->requests->getShopperReference(null, '0000123');

        $this->assertStringStartsWith('0000123', $result);
        $this->assertGreaterThan(10, strlen($result));
    }

    #[Test]
    public function buildCustomerDataIncludesEmailTelephoneName(): void
    {
        $billingAddress = $this->createMock(\Magento\Sales\Api\Data\OrderAddressInterface::class);
        $billingAddress->method('getEmail')->willReturn('customer@example.com');
        $billingAddress->method('getTelephone')->willReturn('123456789');
        $billingAddress->method('getFirstname')->willReturn('John');
        $billingAddress->method('getLastname')->willReturn('Doe');
        $billingAddress->method('getCountryId')->willReturn('NL');

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getMethodInstance')->willReturn(
            $this->createConfiguredMock(\Magento\Payment\Model\MethodInterface::class, [
                'getCode' => 'scheme'
            ])
        );
        $payment->method('getOrder')->willReturn(
            $this->createConfiguredMock(\Magento\Sales\Model\Order::class, [
                'getIncrementId' => '000001'
            ])
        );

        $this->localeHelper->method('getStoreLocale')->willReturn('nl_NL');
        $this->adyenHelper->method('padShopperReference')->willReturn('user_1');
        $this->addressHelper->method('getAdyenCountryCode')->willReturn('NL');

        $result = $this->requests->buildCustomerData(
            $billingAddress,
            1,
            1,
            $payment,
            [],
            []
        );

        $this->assertSame('user_1', $result['shopperReference']);
        $this->assertSame('customer@example.com', $result['shopperEmail']);
        $this->assertSame('123456789', $result['telephoneNumber']);
        $this->assertSame('John', $result['shopperName']['firstName']);
        $this->assertSame('Doe', $result['shopperName']['lastName']);
        $this->assertSame('NL', $result['countryCode']);
        $this->assertSame('nl_NL', $result['shopperLocale']);
    }

    #[Test]
    public function buildAddressDataBuildsBillingAndDeliveryAddress(): void
    {
        $billing = $this->createMock(\Magento\Sales\Api\Data\OrderAddressInterface::class);
        $shipping = $this->createMock(\Magento\Sales\Api\Data\OrderAddressInterface::class);

        $billing->method('getCity')->willReturn('Amsterdam');
        $billing->method('getCountryId')->willReturn('NL');
        $billing->method('getPostcode')->willReturn('1011AB');

        $shipping->method('getCity')->willReturn('Rotterdam');
        $shipping->method('getCountryId')->willReturn('NL');
        $shipping->method('getPostcode')->willReturn('3011AB');

        $this->adyenConfig->method('getAdyenAbstractConfigData')->willReturn(1);
        $this->adyenHelper->method('getCustomerStreetLinesEnabled')->willReturn(true);

        $this->addressHelper->method('getStreetAndHouseNumberFromAddress')
            ->willReturnMap([
                [$billing, 1, true, ['name' => 'Damrak', 'house_number' => '1']],
                [$shipping, 1, true, ['name' => 'Coolsingel', 'house_number' => '10']]
            ]);

        $this->addressHelper->method('getAdyenCountryCode')->willReturn('NL');

        $result = $this->requests->buildAddressData($billing, $shipping, 1);

        $this->assertSame('Damrak', $result['billingAddress']['street']);
        $this->assertSame('Coolsingel', $result['deliveryAddress']['street']);
        $this->assertSame('1', $result['billingAddress']['houseNumberOrName']);
        $this->assertSame('10', $result['deliveryAddress']['houseNumberOrName']);
    }

    #[Test]
    public function buildCardRecurringDataWithConsent(): void
    {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $order->method('getQuoteId')->willReturn(1);
        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn('scheme');
        $payment->method('getAdditionalInformation')->willReturn('card');

        $this->vaultHelper->method('getPaymentMethodRecurringActive')->willReturn(true);
        $this->stateData->method('getStateData')->willReturn([]);
        $this->stateData->method('getStoredPaymentMethodIdFromStateData')->willReturn('stored-token');
        $this->vaultHelper->method('getPaymentMethodRecurringProcessingModel')->willReturn('card');

        $result = $this->requests->buildCardRecurringData(1, $payment);

        $this->assertSame('card', $result['recurringProcessingModel']);
    }

    #[Test]
    public function buildAdyenTokenizedRecurringDataForStoredCard(): void
    {
        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['recurringProcessingModel', 'card'],
            ['cc_type', 'visa'],
            ['method', 'scheme']
        ]);

        $this->vaultHelper
            ->method('getPaymentMethodRecurringProcessingModel')
            ->with(AdyenCcConfigProvider::CODE, 1)
            ->willReturn('card');

        $result = $this->requests->buildAdyenTokenizedPaymentRecurringData(1, $payment);

        $this->assertSame('card', $result['recurringProcessingModel']);
    }

    #[Test]
    public function buildDonationDataReturnsCorrectStructure(): void
    {
        $storeId = 1;
        $currency = 'EUR';
        $amount = ['currency' => $currency, 'value' => 1000];
        $returnUrl = 'https://example.com';
        $payload = ['amount' => $amount, 'returnUrl' => $returnUrl];

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $paymentMethodInstance = $this->createMock(\Magento\Payment\Model\MethodInterface::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethodInstance')->willReturn($paymentMethodInstance);
        $payment->method('getMethod')->willReturn('adyen_cc');
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', 'donation-token'],
            ['donationCampaignId', 'campaign-id'],
            ['pspReference', 'psp-ref-123'],
            ['donationPayload', $payload]
        ]);

        $this->vaultHelper
            ->method('getPaymentMethodRecurringProcessingModel')
            ->with(AdyenCcConfigProvider::CODE, 1)
            ->willReturn('adyen_cc');

        $order->method('getCustomerId')->willReturn(42);
        $order->method('getIncrementId')->willReturn('000001');

        $amountCurrency = $this->createConfiguredMock(\Adyen\Payment\Model\AdyenAmountCurrency::class, [
            'getCurrencyCode' => $currency
        ]);

        $this->chargedCurrency->method('getOrderAmountCurrency')->willReturn($amountCurrency);
        $this->paymentMethodsHelper->method('isAlternativePaymentMethod')->willReturn(false);
        $this->adyenHelper->method('getAdyenMerchantAccount')->with('adyen_giving', $storeId)->willReturn('merchant123');
        $this->adyenHelper->method('padShopperReference')->with(42)->willReturn('user_42');

        $result = $this->requests->buildDonationData($payment, $storeId);

        $this->assertSame('scheme', $result['paymentMethod']['type']);
        $this->assertSame('user_42', $result['shopperReference']);
        $this->assertSame('donation-token', $result['donationToken']);
        $this->assertSame('campaign-id', $result['donationCampaignId']);
        $this->assertSame('psp-ref-123', $result['donationOriginalPspReference']);
        $this->assertSame('merchant123', $result['merchantAccount']);
        $this->assertSame($amount, $result['amount']);
        $this->assertSame($returnUrl, $result['returnUrl']);
    }

    #[Test]
    public function buildDonationDataThrowsExceptionIfTokenOrPayloadInvalid(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $order = $this->createStub(\Magento\Sales\Model\Order::class);

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', null],
            ['donationPayload', []]
        ]);

        $this->requests->buildDonationData($payment, 1);
    }

    #[Test]
    public function buildDonationDataThrowsExceptionOnCurrencyMismatch(): void
    {
        $this->expectException(\Magento\Framework\Exception\LocalizedException::class);
        $this->expectExceptionMessage('Donation failed!');

        $payment = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $order = $this->createMock(\Magento\Sales\Model\Order::class);
        $payload = ['amount' => ['currency' => 'USD']];

        $payment->method('getOrder')->willReturn($order);
        $payment->method('getMethod')->willReturn('scheme');
        $payment->method('getMethodInstance')->willReturn(
            $this->createStub(\Magento\Payment\Model\MethodInterface::class)
        );
        $payment->method('getAdditionalInformation')->willReturnMap([
            ['donationToken', 'valid-token'],
            ['donationPayload', $payload],
            ['donationCampaignId', 'campaign-id'],
            ['pspReference', 'psp-ref-123']
        ]);

        $this->chargedCurrency
            ->method('getOrderAmountCurrency')
            ->with($order, false)
            ->willReturn(
                $this->createConfiguredMock(\Adyen\Payment\Model\AdyenAmountCurrency::class, [
                    'getCurrencyCode' => 'EUR'
                ])
            );

        $this->requests->buildDonationData($payment, 1);
    }

}
