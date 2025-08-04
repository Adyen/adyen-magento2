<?php

namespace Adyen\Payment\Test\Gateway\Request;

use Adyen\Payment\Gateway\Request\CheckoutDataBuilder;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\StateData;
use Adyen\Payment\Model\Config\Source\ThreeDSFlow;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Catalog\Helper\Image;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;

class CheckoutDataBuilderTest extends AbstractAdyenTestCase
{
    protected ?CheckoutDataBuilder $checkoutDataBuilder;

    protected StateData|MockObject $stateDataMock;
    protected Config|MockObject $configMock;
    protected PaymentMethods|MockObject $paymentMethodsHelperMock;
    protected Image|MockObject $imageMock;

    /**
     * @return void
     * @throws Exception
     */
    public function setUp(): void
    {
        $this->stateDataMock = $this->createMock(StateData::class);
        $this->configMock = $this->createMock(Config::class);
        $this->imageMock = $this->createMock(Image::class);
        $this->paymentMethodsHelperMock = $this->createMock(PaymentMethods::class);

        $this->checkoutDataBuilder = new CheckoutDataBuilder(
            $this->stateDataMock,
            $this->configMock,
            $this->paymentMethodsHelperMock,
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
     * @throws NoSuchEntityException|Exception
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
}
