<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\OpenInvoice;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
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
    protected OpenInvoice|MockObject $openInvoiceMock;
    protected Image|MockObject $imageMock;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->adyenHelperMock = $this->createMock(Data::class);
        $this->stateDataMock = $this->createMock(StateData::class);
        $this->cartRepositoryMock = $this->createMock(CartRepositoryInterface::class);
        $this->chargedCurrencyMock = $this->createMock(ChargedCurrency::class);
        $this->configMock = $this->createMock(Config::class);
        $this->openInvoiceMock = $this->createMock(OpenInvoice::class);
        $this->imageMock = $this->createMock(Image::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        $this->checkoutDataBuilder = new CheckoutDataBuilder(
            $this->adyenHelperMock,
            $this->stateDataMock,
            $this->cartRepositoryMock,
            $this->chargedCurrencyMock,
            $this->configMock,
            $this->paymentMethodsHelperMock,
            $this->openInvoiceMock,
            $this->imageMock
        );

        parent::setUp();
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->checkoutDataBuilder = null;
    }


    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
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

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testOpenInvoiceData()
    {
        $storeId = 1;

        $shippingMethod = 'Flat Rate';
        $shippingAddressMock = $this->createMock(OrderAddressInterface::class);
        $shippingAddressMock->method('getStreet')->willReturn(['Street first line', 'Street second line']);

        $orderMock = $this->createMock(Order::class);
        $orderMock->method('getQuoteId')->willReturn(1);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getShippingAddress')->willReturn($shippingAddressMock);
        $orderMock->method('getShippingMethod')->willReturn($shippingMethod);

        $paymentMethodInstanceMock = $this->createMock(MethodInterface::class);

        $paymentMock = $this->createMock(Payment::class);
        $paymentMock->method('getOrder')->willReturn($orderMock);
        $paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstanceMock);
        $paymentMock->method('getMethod')->willReturn('adyen_klarna');

        $buildSubject = [
            'payment' => $this->createConfiguredMock(PaymentDataObject::class, [
                'getPayment' => $paymentMock
            ])
        ];

        $this->paymentMethodsHelperMock->method('isOpenInvoice')->willReturn(true);
        $this->adyenHelperMock->method('isPaymentMethodOfType')
            ->with('adyen_klarna', Data::KLARNA)
            ->willReturn(true);

        $request = $this->checkoutDataBuilder->build($buildSubject);

        $this->assertIsArray($request);
        $this->assertArrayHasKey('additionalData', $request['body']);
        $this->assertArrayHasKey('openinvoicedata.merchantData', $request['body']['additionalData']);
    }
}
