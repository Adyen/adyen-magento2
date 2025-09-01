<?php

namespace Adyen\Payment\Test\Unit\Gateway\Request;

use Adyen\Exception\MissingDataException;
use Adyen\Payment\Gateway\Request\PaymentDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Requests;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Method\Adapter;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\InfoInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentDataBuilderTest extends AbstractAdyenTestCase
{
    private ?PaymentDataBuilder $paymentDataBuilder;
    private MockObject|Requests $adyenRequestsHelperMock;
    private MockObject|ChargedCurrency $chargedCurrencyMock;
    private MockObject|CheckoutSession $checkoutSessionMock;
    private MockObject|PaymentDataObject $paymentDataObjectMock;
    private MockObject|OrderAdapterInterface $orderMock;
    private MockObject|InfoInterface $paymentMock;
    private MockObject|Quote $quoteMock;
    private MockObject|Payment $quotePaymentMock;
    private string $mockShopperConversionId = 'mock-shopper-conversion-id';

    /**
     * Set up the test environment
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->adyenRequestsHelperMock = $this->createMock(Requests::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);

        $this->paymentDataBuilder = new PaymentDataBuilder(
            $this->adyenRequestsHelperMock,
            $this->chargedCurrencyMock,
            $this->checkoutSessionMock
        );

        // Mock PaymentDataObject
        $this->paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $this->orderMock = $this->createMock(OrderAdapterInterface::class);
        $this->paymentMock = $this->createMock(InfoInterface::class);
        $this->quoteMock = $this->createMock(Quote::class);
        $this->quotePaymentMock = $this->createMock(Payment::class);
    }

    /**
     * Tear down the test environment
     */
    protected function tearDown(): void
    {
        $this->paymentDataBuilder = null;
        parent::tearDown();
    }

    /**
     * Test build() method
     *
     * @throws MissingDataException
     * @throws LocalizedException
     */
    public function testBuild(): void
    {
        $mockCurrencyCode = 'EUR';
        $mockAmount = 100.00;
        $mockReference = '000000123';

        // Mock CheckoutSession interaction
        $this->checkoutSessionMock->expects($this->once())
            ->method('getQuote')
            ->willReturn($this->quoteMock);

        // Mock Quote Payment Additional Information
        $this->quoteMock->expects($this->once())
            ->method('getPayment')
            ->willReturn($this->quotePaymentMock);

        $this->quotePaymentMock->expects($this->once())
            ->method('getAdditionalInformation')
            ->with('shopper_conversion_id')
            ->willReturn(json_encode($this->mockShopperConversionId));


        // Mock Adyen Requests Helper call
        $expectedRequestData = [
                'amount' => [
                    'currency' => $mockCurrencyCode,
                    'value' => $mockAmount, // Ensure the formatted value matches
                ],
                'reference' => $mockReference,
                'shopperConversionId' => $this->mockShopperConversionId,
        ];


        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getIncrementId')->willReturn($mockReference);


        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);

        $adyenAmountCurrencyMock = $this->createMock(AdyenAmountCurrency::class);
        $adyenAmountCurrencyMock->method('getCurrencyCode')->willReturn($mockCurrencyCode);
        $adyenAmountCurrencyMock->method('getAmount')->willReturn($mockAmount);

        $this->chargedCurrencyMock->method('getOrderAmountCurrency')
            ->with($orderMock)
            ->willReturn($adyenAmountCurrencyMock);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $this->adyenRequestsHelperMock->expects($this->once())
            ->method('buildPaymentData')
            ->with(
                $mockAmount,
                $mockCurrencyCode,
                $mockReference,
                $this->mockShopperConversionId,
                []
            )
            ->willReturn($expectedRequestData);

        $result = $this->paymentDataBuilder->build($buildSubject);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('body', $result);
        $this->assertEquals($expectedRequestData, $result['body']);
        $this->addToAssertionCount(1);
    }
}
