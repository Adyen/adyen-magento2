<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\MerchantRiskIndicatorDataBuilder;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class MerchantRiskIndicatorDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?MerchantRiskIndicatorDataBuilder $merchantRiskIndicatorDataBuilder;
    protected CartRepositoryInterface|MockObject $cartRepositoryMock;
    protected PaymentDataObject|MockObject $paymentDataObjectMock;
    protected Payment|MockObject $paymentMock;
    protected Order|MockObject $orderMock;
    protected Quote|MockObject $quoteMock;
    protected Address|MockObject $shippingAddressMock;
    protected AdyenLogger|MockObject $adyenLoggerMock;
    protected int $quoteId = 1;
    protected array $buildSubject;

    protected function setUp(): void
    {
        // Constructor arguments
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->adyenLoggerMock = $this->createMock(AdyenLogger::class);

        // Other mock objects
        $this->shippingAddressMock = $this->createMock(Address::class);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->method('getShippingAddress')->willReturn($this->shippingAddressMock);
        $this->cartRepositoryMock->method('get')->with($this->quoteId)->willReturn($this->quoteMock);

        $this->orderMock = $this->createMock(Order::class);
        $this->orderMock->method('getQuoteId')->willReturn($this->quoteId);

        $this->paymentMock = $this->createMock(Payment::class);
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);

        $this->paymentDataObjectMock = $this->createMock(PaymentDataObject::class);
        $this->paymentDataObjectMock->method('getPayment')->willReturn($this->paymentMock);

        $this->buildSubject = ['payment' => $this->paymentDataObjectMock];

        // SUT generation
        $this->merchantRiskIndicatorDataBuilder = new MerchantRiskIndicatorDataBuilder(
            $this->cartRepositoryMock,
            $this->adyenLoggerMock
        );
    }

    protected function tearDown(): void
    {
        $this->merchantRiskIndicatorDataBuilder = null;
    }

    private static function testBuildDataProvider(): array
    {
        return [
            [
                'isVirtual' => false,
                'sameAsBillingAddress' => 1,
                'deliveryAddressIndicator' => 'shipToBillingAddress',
            ],
            [
                'isVirtual' => false,
                'sameAsBillingAddress' => 0,
                'deliveryAddressIndicator' => 'shipToNewAddress',
            ],
            [
                'isVirtual' => true,
                'sameAsBillingAddress' => 0,
                'deliveryAddressIndicator' => 'other',
            ]
        ];
    }

    /**
     * @dataProvider testBuildDataProvider
     *
     * @param $isVirtual
     * @param $sameAsBillingAddress
     * @param $deliveryAddressIndicator
     * @return void
     * @throws NoSuchEntityException
     */
    public function testBuildWithoutGiftcards($isVirtual, $sameAsBillingAddress, $deliveryAddressIndicator)
    {
        $customerEmail = 'roni_cost@example.com';

        $this->orderMock->expects($this->once())->method('getIsVirtual')->willReturn($isVirtual);
        $this->orderMock->method('getCustomerEmail')->willReturn($customerEmail);

        $this->shippingAddressMock->method('getSameAsBilling')->willReturn($sameAsBillingAddress);

        $result = $this->merchantRiskIndicatorDataBuilder->build($this->buildSubject);

        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('merchantRiskIndicator', $result['body']);

        if ($isVirtual) {
            $this->assertArrayHasKey('deliveryEmailAddress', $result['body']['merchantRiskIndicator']);
            $this->assertEquals($customerEmail,
                $result['body']['merchantRiskIndicator']['deliveryEmailAddress']);
            $this->assertArrayHasKey('deliveryTimeframe', $result['body']['merchantRiskIndicator']);
            $this->assertEquals('electronicDelivery',
                $result['body']['merchantRiskIndicator']['deliveryTimeframe']);
        } else {
            $this->assertArrayHasKey('addressMatch', $result['body']['merchantRiskIndicator']);
        }

        $this->assertArrayHasKey('deliveryAddressIndicator', $result['body']['merchantRiskIndicator']);
        $this->assertEquals($deliveryAddressIndicator,
            $result['body']['merchantRiskIndicator']['deliveryAddressIndicator']);
    }
}
