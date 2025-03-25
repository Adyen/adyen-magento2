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

        $this->additionalDataBuilder = new AdditionalDataLevel23DataBuilder(
            $this->configMock,
            $this->storeManagerMock,
            $this->chargedCurrencyMock,
            $this->adyenRequestHelperMock,
            $this->adyenHelperMock,
            $this->adyenLoggerMock
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

        $this->adyenHelperMock->method('formatAmount')->willReturn($formattedTaxAmount);

        $this->adyenRequestHelperMock->method('getShopperReference')
            ->with($customerId, $orderIncrementId)
            ->willReturn($shopperReference);

        $itemMock1 = $this->createMock(Item::class);
        $itemMock1->method('getPrice')->willReturn(0);

        $itemMock2 = $this->createMock(Item::class);
        $itemMock2->method('getPrice')->willReturn(10);
        $itemMock2->method('getRowTotal')->willReturn(0);

        $itemMock3 = $this->createMock(Item::class);
        $itemMock3->method('getPrice')->willReturn(10);
        $itemMock3->method('getRowTotal')->willReturn(5);
        $itemMock3->method('getQtyOrdered')->willReturn(0.5);

        $itemMock4 = $this->createMock(Item::class);
        $itemMock4->method('getPrice')->willReturn(15);
        $itemMock4->method('getRowTotal')->willReturn(30);
        $itemMock4->method('getQtyOrdered')->willReturn(2);
        $itemMock4->method('getSku')->willReturn('ABC123');
        $itemMock4->method('getDescription')->willReturn('Mock Product Description');

        $orderMock = $this->createConfiguredMock(Order::class, [
            'getCustomerId' => $customerId,
            'getIncrementId' => $orderIncrementId,
            'getTaxAmount' => $taxAmount,
            'getItems' => [$itemMock1, $itemMock2, $itemMock3, $itemMock4],
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

        // Index starts from 1
        $this->assertArrayNotHasKey($itemArrayKey . '0.productCode', $result['body']['additionalData']);

        // Only one line item is valid, others should be cleaned up
        $this->assertArrayNotHasKey($itemArrayKey . '2.productCode', $result['body']['additionalData']);
        $this->assertArrayNotHasKey($itemArrayKey . '3.productCode', $result['body']['additionalData']);
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

    protected static function taxAmountDataProvider(): array
    {
        return [
            ['taxAmount' => 0],
            ['taxAmount' => null],
            ['taxAmount' => -1]
        ];
    }

    /**
     * @dataProvider taxAmountDataProvider
     *
     * @param $taxAmount
     * @return void
     * @throws NoSuchEntityException
     */
    public function testLevel23DataInvalidTaxAmounts($taxAmount)
    {
        $storeId = 1;
        $currencyCode = 'USD';

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->willReturn(new AdyenAmountCurrency(null, $currencyCode));

        $this->storeMock->method('getId')->willReturn($storeId);
        $this->configMock->method('sendLevel23AdditionalData')->with($storeId)->willReturn(true);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getTaxAmount')->willReturn($taxAmount);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $paymentDataObjectMock->method('getPayment')->willReturn($paymentMock);

        $buildSubject = ['payment' => $paymentDataObjectMock, 'order' => $orderMock];

        $result = $this->additionalDataBuilder->build($buildSubject);
        $this->assertEmpty($result);
    }
}
