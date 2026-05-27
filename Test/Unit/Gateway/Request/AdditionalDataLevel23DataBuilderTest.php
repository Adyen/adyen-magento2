<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Sales\Model\Order\Address;
use Adyen\Payment\Gateway\Request\AdditionalDataLevel23DataBuilder;
use Adyen\Payment\Gateway\Request\Level23DataValidator;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Requests;
use PHPUnit\Framework\MockObject\MockObject;

class AdditionalDataLevel23DataBuilderTest extends AbstractAdyenTestCase
{
    protected ?AdditionalDataLevel23DataBuilder $additionalDataBuilder;
    protected MockObject|StoreInterface $storeMock;
    protected MockObject|Config $configMock;
    protected MockObject|StoreManagerInterface $storeManagerMock;
    protected MockObject|Data $adyenHelperMock;
    protected MockObject|ChargedCurrency $chargedCurrencyMock;
    protected MockObject|Requests $adyenRequestHelperMock;
    protected MockObject|AdyenLogger $adyenLoggerMock;
    protected MockObject|Level23DataValidator $level23DataValidatorMock;

    public function setUp(): void
    {
        parent::setUp();

        $this->configMock = $this->createMock(Config::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->adyenRequestHelperMock = $this->createMock(Requests::class);
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->storeMock = $this->createMock(StoreInterface::class);
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $this->level23DataValidatorMock = $this->createMock(Level23DataValidator::class);

        $this->additionalDataBuilder = new AdditionalDataLevel23DataBuilder(
            $this->configMock,
            $this->storeManagerMock,
            $this->chargedCurrencyMock,
            $this->adyenRequestHelperMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock,
            $this->level23DataValidatorMock
        );
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->additionalDataBuilder = null;
    }

    protected static function orderTypeDataProvider(): array
    {
        return [
            ['isVirtual' => true],
            ['isVirtual' => false],
        ];
    }

    /**
     * @dataProvider orderTypeDataProvider
     *
     * @param $isVirtual
     * @return void
     * @throws NoSuchEntityException
     */
    public function testLevel23DataConfigurationEnabled($isVirtual)
    {
        $storeId = 1;
        $currencyCode = 'USD';
        $customerId = 123;
        $orderIncrementId = '000000123';
        $shopperReference = '000123';
        $taxAmount = 10.00;
        $formattedTaxAmount = '1000';
        $shippingAmount = 5.00;

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')
            ->with($storeId)
            ->willReturn(true);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn(new AdyenAmountCurrency(null, $currencyCode));

        $this->adyenHelperMock->method('formatAmount')->willReturnCallback(
            function ($amount, $currency) {
                return (int) round($amount * 100);
            }
        );

        $this->adyenRequestHelperMock->method('getShopperReference')
            ->with($customerId, $orderIncrementId)
            ->willReturn($shopperReference);

        $this->level23DataValidatorMock->method('sanitizeCustomerReference')
            ->willReturn($shopperReference);
        $this->level23DataValidatorMock->method('isAmountNotAllZeros')
            ->willReturn(true);
        $this->level23DataValidatorMock->method('sanitizePostalCode')
            ->willReturn('12345');
        $this->level23DataValidatorMock->method('convertCountryCodeToAlpha3')
            ->willReturn('USA');
        $this->level23DataValidatorMock->method('sanitizeStateProvinceCode')
            ->willReturn('MI');
        $this->level23DataValidatorMock->method('sanitizeDescription')
            ->willReturn('Mock Product Name');
        $this->level23DataValidatorMock->method('sanitizeProductCode')
            ->willReturn('ABC123');
        $this->level23DataValidatorMock->method('sanitizeCommodityCode')
            ->willReturn('1');
        $this->level23DataValidatorMock->method('calculateLineItemTotalAmount')
            ->willReturn(3000);

        // Item 1: price is zero -> should be skipped by validateLineItemInput
        $itemMock1 = $this->createMock(Item::class);
        $itemMock1->method('getPrice')->willReturn(0);

        // Item 2: price is valid but qty < 1 -> should be skipped by validateLineItemInput
        $itemMock2 = $this->createMock(Item::class);
        $itemMock2->method('getPrice')->willReturn(10);
        $itemMock2->method('getQtyOrdered')->willReturn(0.5);

        // Item 3: valid item
        $itemMock3 = $this->createMock(Item::class);
        $itemMock3->method('getPrice')->willReturn(15);
        $itemMock3->method('getRowTotal')->willReturn(30);
        $itemMock3->method('getQtyOrdered')->willReturn(2);
        $itemMock3->method('getSku')->willReturn('ABC123');
        $itemMock3->method('getName')->willReturn('Mock Product Name');
        $itemMock3->method('getQuoteItemId')->willReturn(1);
        $itemMock3->method('getDiscountAmount')->willReturn(0);

        $this->level23DataValidatorMock->method('validateLineItemInput')
            ->willReturnCallback(function ($price, $qty) {
                return floatval($price) != 0 && floatval($qty) >= 1;
            });

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => $customerId,
            'getIncrementId' => $orderIncrementId,
            'getTaxAmount' => $taxAmount,
            'getItems' => [$itemMock1, $itemMock2, $itemMock3],
            'getIsNotVirtual' => !$isVirtual,
            'getBaseShippingAmount' => $shippingAmount
        ]);

        $shippingAddressMock = $this->createMock(Address::class);
        $shippingAddressMock->method('getPostcode')->willReturn('12345');
        $shippingAddressMock->method('getCountryId')->willReturn('US');
        $shippingAddressMock->method('getRegionCode')->willReturn('MI');

        $orderMock->method('getShippingAddress')->willReturn($shippingAddressMock);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);
        $buildSubject = ['payment' => $paymentDataObject, 'order' => $orderMock];
        $result = $this->additionalDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('additionalData', $result['body']);
        $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.orderDate', $result['body']['additionalData']);
        $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.customerReference', $result['body']['additionalData']);
        $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.totalTaxAmount', $result['body']['additionalData']);

        if (!$isVirtual) {
            $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.freightAmount', $result['body']['additionalData']);
            $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.destinationPostalCode', $result['body']['additionalData']);
            $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.destinationCountryCode', $result['body']['additionalData']);
            $this->assertArrayHasKey(AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.destinationStateProvinceCode', $result['body']['additionalData']);

            $this->assertSame(
                'USA',
                $result['body']['additionalData'][AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.destinationCountryCode']
            );
        }

        $itemArrayKey = AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.' .
            AdditionalDataLevel23DataBuilder::ITEM_DETAIL_LINE_PREFIX;

        $this->assertArrayHasKey($itemArrayKey . '1.productCode', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.description', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.quantity', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.unitOfMeasure', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.commodityCode', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.totalAmount', $result['body']['additionalData']);
        $this->assertArrayHasKey($itemArrayKey . '1.unitPrice', $result['body']['additionalData']);

        // totalAmount should be calculated value, not raw rowTotal
        $this->assertSame('3000', $result['body']['additionalData'][$itemArrayKey . '1.totalAmount']);

        // Index starts from 1
        $this->assertArrayNotHasKey($itemArrayKey . '0.productCode', $result['body']['additionalData']);

        // Only one line item is valid, others should be filtered out
        $this->assertArrayNotHasKey($itemArrayKey . '2.productCode', $result['body']['additionalData']);
    }

    public function testLevel23DataConfigurationDisabled()
    {
        $storeId = 1;

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')->with($storeId)->willReturn(false);

        $paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $orderMock = $this->createMock(Order::class);
        $buildSubject = ['payment' => $paymentDataObjectMock, 'order' => $orderMock];

        $result = $this->additionalDataBuilder->build($buildSubject);
        $this->assertEmpty($result);
    }

    public function testLineItemWithInvalidDescriptionIsSkipped()
    {
        $storeId = 1;
        $currencyCode = 'USD';

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')->willReturn(true);
        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn(new AdyenAmountCurrency(null, $currencyCode));
        $this->adyenHelperMock->method('formatAmount')->willReturn(1000);
        $this->adyenRequestHelperMock->method('getShopperReference')->willReturn('REF123');
        $this->level23DataValidatorMock->method('sanitizeCustomerReference')->willReturn('REF123');
        $this->level23DataValidatorMock->method('isAmountNotAllZeros')->willReturn(true);
        $this->level23DataValidatorMock->method('validateLineItemInput')->willReturn(true);
        $this->level23DataValidatorMock->method('sanitizeDescription')->willReturn(null);

        $itemMock = $this->createMock(Item::class);
        $itemMock->method('getPrice')->willReturn(10);
        $itemMock->method('getQtyOrdered')->willReturn(1);
        $itemMock->method('getName')->willReturn('A');
        $itemMock->method('getSku')->willReturn('SKU1');

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => 1,
            'getIncrementId' => '100',
            'getTaxAmount' => 10,
            'getItems' => [$itemMock],
            'getIsNotVirtual' => false
        ]);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);
        $buildSubject = ['payment' => $paymentDataObject, 'order' => $orderMock];

        $result = $this->additionalDataBuilder->build($buildSubject);

        $itemArrayKey = AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.' .
            AdditionalDataLevel23DataBuilder::ITEM_DETAIL_LINE_PREFIX;

        $this->assertArrayNotHasKey($itemArrayKey . '1.productCode', $result['body']['additionalData']);
    }

    public function testLineItemWithNegativeTotalAmountIsSkipped()
    {
        $storeId = 1;
        $currencyCode = 'USD';

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')->willReturn(true);
        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn(new AdyenAmountCurrency(null, $currencyCode));
        $this->adyenHelperMock->method('formatAmount')->willReturn(1000);
        $this->adyenRequestHelperMock->method('getShopperReference')->willReturn('REF123');
        $this->level23DataValidatorMock->method('sanitizeCustomerReference')->willReturn('REF123');
        $this->level23DataValidatorMock->method('isAmountNotAllZeros')->willReturn(true);
        $this->level23DataValidatorMock->method('validateLineItemInput')->willReturn(true);
        $this->level23DataValidatorMock->method('sanitizeDescription')->willReturn('Valid Product');
        $this->level23DataValidatorMock->method('sanitizeProductCode')->willReturn('SKU1');
        $this->level23DataValidatorMock->method('sanitizeCommodityCode')->willReturn('1');
        $this->level23DataValidatorMock->method('calculateLineItemTotalAmount')->willReturn(-500);

        $itemMock = $this->createMock(Item::class);
        $itemMock->method('getPrice')->willReturn(10);
        $itemMock->method('getQtyOrdered')->willReturn(1);
        $itemMock->method('getName')->willReturn('Valid Product');
        $itemMock->method('getSku')->willReturn('SKU1');
        $itemMock->method('getQuoteItemId')->willReturn(1);
        $itemMock->method('getDiscountAmount')->willReturn(20);

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => 1,
            'getIncrementId' => '100',
            'getTaxAmount' => 10,
            'getItems' => [$itemMock],
            'getIsNotVirtual' => false
        ]);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);
        $buildSubject = ['payment' => $paymentDataObject, 'order' => $orderMock];

        $result = $this->additionalDataBuilder->build($buildSubject);

        $itemArrayKey = AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.' .
            AdditionalDataLevel23DataBuilder::ITEM_DETAIL_LINE_PREFIX;

        $this->assertArrayNotHasKey($itemArrayKey . '1.productCode', $result['body']['additionalData']);
    }

    public function testTotalTaxAmountAlwaysSent()
    {
        $storeId = 1;
        $currencyCode = 'USD';

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')->willReturn(true);
        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn(new AdyenAmountCurrency(null, $currencyCode));
        $this->adyenHelperMock->method('formatAmount')->willReturn(0);
        $this->adyenRequestHelperMock->method('getShopperReference')->willReturn('REF123');
        $this->level23DataValidatorMock->method('sanitizeCustomerReference')->willReturn('REF123');

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => 1,
            'getIncrementId' => '100',
            'getTaxAmount' => 0,
            'getItems' => [],
            'getIsNotVirtual' => false
        ]);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentDataObject = new PaymentDataObject($orderAdapterMock, $paymentMock);
        $buildSubject = ['payment' => $paymentDataObject, 'order' => $orderMock];

        $result = $this->additionalDataBuilder->build($buildSubject);

        $this->assertArrayHasKey(
            AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.totalTaxAmount',
            $result['body']['additionalData']
        );
        $this->assertSame(
            '0',
            $result['body']['additionalData'][AdditionalDataLevel23DataBuilder::ENHANCED_SCHEME_DATA_PREFIX . '.totalTaxAmount']
        );
    }
}
