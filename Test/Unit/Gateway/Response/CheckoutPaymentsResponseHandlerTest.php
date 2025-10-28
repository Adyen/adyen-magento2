<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Gateway\Response\CheckoutPaymentsResponseHandler;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;

class CheckoutPaymentsResponseHandlerTest extends AbstractAdyenTestCase
{
    private CheckoutPaymentsResponseHandler $checkoutPaymentsDetailsHandler;
    private Payment|MockObject $paymentMock;
    private Order|MockObject $orderMock;
    private PaymentDataObject|MockObject $paymentDataObject;
    private Vault|MockObject $vaultMock;
    private PaymentMethods|MockObject $paymentMethodsMock;
    private OrdersApi|MockObject $ordersApiHelperMock;
    private array $handlingSubject;

    protected function setUp(): void
    {
        $this->vaultMock = $this->createMock(Vault::class);
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->ordersApiHelperMock = $this->createMock(OrdersApi::class);

        $this->checkoutPaymentsDetailsHandler = new CheckoutPaymentsResponseHandler(
            $this->vaultMock,
            $this->paymentMethodsMock,
            $this->ordersApiHelperMock
        );

        $paymentMethodInstance = $this->createMock(MethodInterface::class);

        $orderAdapterMock = $this->createMock(OrderAdapterInterface::class);
        $this->orderMock = $this->createMock(Order::class);

        $this->paymentMock = $this->createMock(Payment::class);
        $this->paymentMock->method('getOrder')->willReturn($this->orderMock);
        $this->paymentMock->method('getMethodInstance')->willReturn($paymentMethodInstance);
        $this->paymentDataObject = new PaymentDataObject($orderAdapterMock, $this->paymentMock);

        $this->handlingSubject  = [
            'payment' => $this->paymentDataObject,
            'paymentAction' => "authorize",
            'stateObject' => null
        ];
    }

    public function testIfGeneralFlowIsHandledCorrectly()
    {
        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'Authorised',
            ]
        ];

        $this->paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn('any_method');

        $this->orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(false);

        $this->applyGenericMockExpectations();

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    public function testIfGeneralFlowIsHandledCorrectlyForWallets()
    {
        $walletVariant = 'visa_googlepay';

        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [
                    'paymentMethod' => $walletVariant
                ],
                'amount' => [],
                'resultCode' => 'Authorised',
            ]
        ];

        $this->paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn('any_method');

        $this->orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(false);

        $this->applyGenericMockExpectations();

        $this->paymentMethodsMock->method('isWalletPaymentMethod')->willReturn(true);
        $this->paymentMock->expects($this->once())->method('setCcType')->with($walletVariant);

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    public function testIfBoletoSendsAnEmail()
    {
        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'Authorised',
                'pspReference' => 'ABC12345'
            ]
        ];

        $this->paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn(PaymentMethods::ADYEN_BOLETO);

        // for boleto it should not call this function.
        $this->orderMock
            ->expects($this->never())
            ->method('setCanSendNewEmailFlag')
            ->with(false);

        $this->applyGenericMockExpectations();

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    public function testIfPartialPaymentHandlesLastPaymentResponse()
    {
        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'Authorised',
                'pspReference' => 'ABC54321',
                'paymentMethod' => [
                    'name' => 'giftcard',
                    'type' => 'Givex',
                ]
            ],
            1 => [
                'additionalData' => [
                    'paymentMethod' => 'VI',
                ],
                'amount' => [],
                'resultCode' => 'Authorised',
                'pspReference' => 'ABC12345',
                'paymentMethod' => [
                    'name' => 'card',
                    'type' => 'VI',
                ]
            ]
        ];

        $this->paymentMock
            ->expects($this->any())
            ->method('getMethod')
            ->willReturn('any_method');

        $this->orderMock
            ->expects($this->once())
            ->method('setCanSendNewEmailFlag')
            ->with(false);

        // validate whether the psp reference of the last payment method is used when setting these values.
        $this->paymentMock
            ->expects($this->once())
            ->method('setCcTransId')
            ->with('ABC12345');

        $this->paymentMock
            ->expects($this->once())
            ->method('setLastTransId')
            ->with('ABC12345');

        $this->paymentMock
            ->expects($this->once())
            ->method('setTransactionId')
            ->with('ABC12345');

        $this->paymentMock->expects($this->any())
            ->method('setAdditionalInformation')
            ->willReturnMap([
                ['cc_type', 'VI', null],
                ['resultCode', 'Authorised', null],
            ]);

        $this->applyGenericMockExpectations();

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    private function applyGenericMockExpectations() : void
    {
        $this->paymentMock
            ->expects($this->once())
            ->method('setIsTransactionPending')
            ->with(true);

        $this->paymentMock
            ->expects($this->once())
            ->method('setIsTransactionClosed')
            ->with(false);

        $this->paymentMock
            ->expects($this->once())
            ->method('setShouldCloseParentTransaction')
            ->with(false);
    }
}
