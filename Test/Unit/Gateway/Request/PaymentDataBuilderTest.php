<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Exception\MissingDataException;
use Adyen\Payment\Gateway\Request\PaymentDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentDataBuilderTest extends AbstractAdyenTestCase
{
    private ?PaymentDataBuilder $paymentDataBuilder = null;

    /** @var MockObject&Requests */
    private $adyenRequestsHelperMock;

    /** @var MockObject&ChargedCurrency */
    private $chargedCurrencyMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adyenRequestsHelperMock = $this->createMock(Requests::class);
        $this->chargedCurrencyMock     = $this->createMock(ChargedCurrency::class);

        $this->paymentDataBuilder = new PaymentDataBuilder(
            $this->adyenRequestsHelperMock,
            $this->chargedCurrencyMock
        );
    }

    protected function tearDown(): void
    {
        $this->paymentDataBuilder = null;
        parent::tearDown();
    }

    /**
     * When shopperConversionId is present on payment additional info,
     * it should be included in the request body.
     *
     * @throws MissingDataException
     * @throws LocalizedException
     */
    public function testBuildAddsShopperConversionIdWhenPresent(): void
    {
        $mockCurrencyCode = 'EUR';
        $mockAmount       = 100.00;
        $mockReference    = '000000123';
        $mockShopperConv  = 'mock-shopper-conversion-id';

        // Order + OrderPayment
        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getIncrementId')->willReturn($mockReference);

        $orderPaymentMock = $this->createMock(OrderPayment::class);
        $orderPaymentMock->method('getOrder')->willReturn($orderMock);
        $orderPaymentMock->method('getAdditionalInformation')
            ->with('shopper_conversion_id')
            ->willReturn($mockShopperConv);

        // PaymentDataObject
        $paymentDataObject = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $orderPaymentMock,
        ]);

        // Charged currency
        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn($mockCurrencyCode);
        $adyenAmountCurrencyMock->method('getAmount')->willReturn($mockAmount);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock)
            ->willReturn($adyenAmountCurrencyMock);

        // Requests helper returns base body; builder adds shopperConversionId if present
        $baseBody = [
            'amount'    => ['currency' => $mockCurrencyCode, 'value' => $mockAmount],
            'reference' => $mockReference,
        ];

        $this->adyenRequestsHelperMock->expects($this->once())
            ->method('buildPaymentData')
            ->with($mockAmount, $mockCurrencyCode, $mockReference, [])
            ->willReturn($baseBody);

        $result = $this->paymentDataBuilder->build(['payment' => $paymentDataObject]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertSame($mockReference, $result['body']['reference']);
        $this->assertSame($mockCurrencyCode, $result['body']['amount']['currency']);
        $this->assertSame($mockAmount, $result['body']['amount']['value']);
        $this->assertArrayHasKey('shopperConversionId', $result['body']);
        $this->assertSame($mockShopperConv, $result['body']['shopperConversionId']);
    }

    /**
     * When shopperConversionId is NOT present, it should not be in the request body.
     *
     * @throws MissingDataException
     * @throws LocalizedException
     */
    public function testBuildOmitsShopperConversionIdWhenAbsent(): void
    {
        $mockCurrencyCode = 'USD';
        $mockAmount       = 55.55;
        $mockReference    = '000000999';

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getIncrementId')->willReturn($mockReference);

        $orderPaymentMock = $this->createMock(OrderPayment::class);
        $orderPaymentMock->method('getOrder')->willReturn($orderMock);
        $orderPaymentMock->method('getAdditionalInformation')
            ->with('shopper_conversion_id')
            ->willReturn(null);

        $paymentDataObject = $this->createConfiguredMock(PaymentDataObject::class, [
            'getPayment' => $orderPaymentMock,
        ]);

        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn($mockCurrencyCode);
        $adyenAmountCurrencyMock->method('getAmount')->willReturn($mockAmount);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock)
            ->willReturn($adyenAmountCurrencyMock);

        $baseBody = [
            'amount'    => ['currency' => $mockCurrencyCode, 'value' => $mockAmount],
            'reference' => $mockReference,
        ];

        $this->adyenRequestsHelperMock->expects($this->once())
            ->method('buildPaymentData')
            ->with($mockAmount, $mockCurrencyCode, $mockReference, [])
            ->willReturn($baseBody);

        $result = $this->paymentDataBuilder->build(['payment' => $paymentDataObject]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertSame($mockReference, $result['body']['reference']);
        $this->assertSame($mockCurrencyCode, $result['body']['amount']['currency']);
        $this->assertSame($mockAmount, $result['body']['amount']['value']);
        $this->assertArrayNotHasKey('shopperConversionId', $result['body']);
    }
}
