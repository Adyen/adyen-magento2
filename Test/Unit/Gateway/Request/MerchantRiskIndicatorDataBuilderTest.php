<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\MerchantRiskIndicatorDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\GiftcardPayment;
use Adyen\Payment\Model\AdyenAmountCurrency;
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
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected GiftcardPayment|MockObject $giftcardPaymentHelperMock;
    protected PaymentDataObject|MockObject $paymentDataObjectMock;
    protected Payment|MockObject $paymentMock;
    protected Order|MockObject $orderMock;
    protected Quote|MockObject $quoteMock;
    protected Address|MockObject $shippingAddressMock;
    protected int $quoteId = 1;
    protected array $buildSubject;

    protected function setUp(): void
    {
        // Constructor arguments
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->giftcardPaymentHelperMock = $this->createMock(GiftcardPayment::class);

        // Other mock objects
        $this->shippingAddressMock = $this->createMock(Address::class);

        $this->quoteMock = $this->createMock(Quote::class);
        $this->quoteMock->expects($this->atLeastOnce())->method('getId')->willReturn($this->quoteId);
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
            $this->chargedCurrencyMock,
            $this->giftcardPaymentHelperMock
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
                'deliveryAddressIndicator' => 'digitalGoods',
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
        $this->orderMock->expects($this->once())->method('getRelationParentId')->willReturn(null);
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

        $this->assertArrayHasKey('reorderItems', $result['body']['merchantRiskIndicator']);
        $this->assertArrayHasKey('deliveryAddressIndicator', $result['body']['merchantRiskIndicator']);
        $this->assertEquals($deliveryAddressIndicator,
            $result['body']['merchantRiskIndicator']['deliveryAddressIndicator']);
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     */
    public function testBuildPhysicalGoodsWithGiftcard()
    {
        $totalGiftcardDiscount = 1000;
        $currency = 'EUR';
        $numberOfGiftcards = 2;

        $this->orderMock->expects($this->once())->method('getIsVirtual')->willReturn(false);

        $quoteAmountCurrency = $this->createMock(AdyenAmountCurrency::class);
        $quoteAmountCurrency->method('getCurrencyCode')->willReturn($currency);
        $this->chargedCurrencyMock->expects($this->once())->method('getQuoteAmountCurrency')
            ->with($this->quoteMock)
            ->willReturn($quoteAmountCurrency);

        $redeemedGiftcardsMock = '{"redeemedGiftcards":[{"stateDataId":"51","brand":"svs","title":"SVS","balance":{"currency":"EUR","value":5000},"deductedAmount":"50,00\u00a0\u20ac"},{"stateDataId":"52","brand":"svs","title":"SVS","balance":{"currency":"EUR","value":5000},"deductedAmount":"50,00\u00a0\u20ac"}],"remainingAmount":"8,00\u00a0\u20ac","totalDiscount":"100,00\u00a0\u20ac"}';

        $this->giftcardPaymentHelperMock->expects($this->once())
            ->method('getQuoteGiftcardDiscount')
            ->with($this->quoteMock)
            ->willReturn($totalGiftcardDiscount);
        $this->giftcardPaymentHelperMock->expects($this->once())
            ->method('fetchRedeemedGiftcards')
            ->with($this->quoteId)
            ->willReturn($redeemedGiftcardsMock);

        $result = $this->merchantRiskIndicatorDataBuilder->build($this->buildSubject);

        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('merchantRiskIndicator', $result['body']);
        $this->assertArrayHasKey('giftCardCurr', $result['body']['merchantRiskIndicator']);
        $this->assertArrayHasKey('giftCardCount', $result['body']['merchantRiskIndicator']);
        $this->assertEquals($numberOfGiftcards, $result['body']['merchantRiskIndicator']['giftCardCount']);
        $this->assertArrayHasKey('giftCardAmount', $result['body']['merchantRiskIndicator']);
        $this->assertArrayHasKey('currency', $result['body']['merchantRiskIndicator']['giftCardAmount']);
        $this->assertEquals($currency, $result['body']['merchantRiskIndicator']['giftCardAmount']['currency']);
        $this->assertArrayHasKey('value', $result['body']['merchantRiskIndicator']['giftCardAmount']);
        $this->assertEquals($totalGiftcardDiscount,
            $result['body']['merchantRiskIndicator']['giftCardAmount']['value']);
    }
}
