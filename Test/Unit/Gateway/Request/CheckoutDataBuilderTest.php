<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Model\Ui\AdyenCcConfigProvider;
use Adyen\Payment\Observer\AdyenCcDataAssignObserver;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use Magento\Catalog\Helper\Image;
use PHPUnit\Framework\MockObject\MockObject;

class CheckoutDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?CheckoutDataBuilder $checkoutDataBuilder;

    protected Data|MockObject $adyenHelperMock;
    protected StateData|MockObject $stateDataMock;
    protected CartRepositoryInterface|MockObject $cartRepositoryMock;
    protected ChargedCurrency|MockObject $chargedCurrencyMock;
    protected Config|MockObject $configMock;
    protected PaymentMethods|MockObject $paymentMethodsHelperMock;
    protected Image|MockObject $imageHelperMock;

    public function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->stateDataMock = $this->createMock(StateData::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configMock = $this->createMock(Config::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);
        $this->imageHelperMock = $this->createMock(Image::class);

        $this->checkoutDataBuilder = new CheckoutDataBuilder(
            $this->adyenHelperMock,
            $this->stateDataMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configMock,
            $this->paymentMethodsHelperMock,
            $this->imageHelperMock
        );

        parent::setUp();
    }

    public function tearDown(): void
    {
        $this->checkoutDataBuilder = null;
    }


    public function testAllowThreeDSFlag()
    {
        $storeId = 1;

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn($storeId);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $this->configMock->expects($this->once())
            ->method('getThreeDSFlow')
            ->with($storeId)
            ->willReturn(ThreeDSFlow::THREEDS_NATIVE);

        $request = $this->checkoutDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('nativeThreeDS', $request['body']['authenticationData']['threeDSRequestData']);
    }

    public function testComboCardsDataRemoveInstallments()
    {
        $storeId = 1;

        $addressMock = $this->createMock(AddressInterface::class);
        $addressMock->method('getCountryId')->willReturn('BR');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getBillingAddress')->willReturn($addressMock);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getMethod')->willReturn(AdyenCcConfigProvider::CODE);

        $paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                [AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS, '4'],
                [AdyenCcDataAssignObserver::COMBO_CARD_TYPE, PaymentMethods::FUNDING_SOURCE_DEBIT]
            ]);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $request = $this->checkoutDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('paymentMethod', $request['body']);
        $this->assertArrayHasKey('fundingSource', $request['body']['paymentMethod']);
        $this->assertArrayNotHasKey('installments', $request['body']);
    }

    public function testComboCardsDataWithInstallments()
    {
        $storeId = 1;

        $addressMock = $this->createMock(AddressInterface::class);
        $addressMock->method('getCountryId')->willReturn('BR');

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getBillingAddress')->willReturn($addressMock);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getMethod')->willReturn(AdyenCcConfigProvider::CODE);

        $paymentMock->method('getAdditionalInformation')
            ->willReturnMap([
                [AdyenCcDataAssignObserver::NUMBER_OF_INSTALLMENTS, '4'],
                [AdyenCcDataAssignObserver::COMBO_CARD_TYPE, PaymentMethods::FUNDING_SOURCE_CREDIT]
            ]);

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $request = $this->checkoutDataBuilder->build($buildSubject);

        $this->assertArrayHasKey('paymentMethod', $request['body']);
        $this->assertArrayHasKey('fundingSource', $request['body']['paymentMethod']);
        $this->assertArrayHasKey('installments', $request['body']);
    }
}
