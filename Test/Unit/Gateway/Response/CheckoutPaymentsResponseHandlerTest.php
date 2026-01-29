<?php

namespace Test\Unit\Gateway\Response;

use Adyen\Payment\Helper\OrdersApi;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Helper\Vault;
use Adyen\Payment\Model\Method\TxVariantInterpreter;
use Adyen\Payment\Model\Method\TxVariantInterpreterFactory;
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
    private TxVariantInterpreterFactory|MockObject $txVariantInterpreterFactoryMock;
    private array $handlingSubject;

    protected function setUp(): void
    {
        $this->vaultMock = $this->createMock(Vault::class);
        $this->paymentMethodsMock = $this->createMock(PaymentMethods::class);
        $this->ordersApiHelperMock = $this->createMock(OrdersApi::class);
        $this->txVariantInterpreterFactoryMock =
            $this->createGeneratedMock(TxVariantInterpreterFactory::class, ['create']);

        $this->checkoutPaymentsDetailsHandler = new CheckoutPaymentsResponseHandler(
            $this->vaultMock,
            $this->paymentMethodsMock,
            $this->ordersApiHelperMock,
            $this->txVariantInterpreterFactoryMock
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
        $donationToken = 'donation_token_12345';

        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'Authorised',
                'donationToken' => $donationToken
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

        $this->paymentMock
            ->expects($this->atLeastOnce())
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use ($donationToken) {
                if ($key === 'donationToken') {
                    $this->assertEquals($donationToken, $value);
                }
                return null;
            });

        $this->applyGenericMockExpectations();

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    public function testIfGeneralFlowIsHandledCorrectlyForWallets()
    {
        $walletType = 'googlepay';
        $walletBrand = 'visa';

        $walletVariant = sprintf('%s_%s', $walletBrand, $walletType);

        $txVariantInterpreterMock = $this->createMock(TxVariantInterpreter::class);
        $txVariantInterpreterMock->method('getCard')->willReturn($walletBrand);

        $this->txVariantInterpreterFactoryMock->method('create')
            ->with(['txVariant' => $walletVariant])
            ->willReturn($txVariantInterpreterMock);

        // prepare Handler input.
        $responseCollection = [
            0 => [
                'paymentMethod' => [
                    'brand' => $walletVariant,
                    'type' => $walletType
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
        $this->paymentMock->expects($this->once())->method('setCcType')->with($walletBrand);

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

    public function testIfActionIsStoredForRedirectShopper()
    {
        $actionData = [
            'type' => 'redirect',
            'url' => 'https://test.adyen.com/hpp/redirectShopper.shtml',
            'method' => 'GET'
        ];

        $detailsData = [
            [
                'key' => 'redirectResult',
                'type' => 'text'
            ]
        ];

        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'RedirectShopper',
                'pspReference' => 'ABC12345',
                'action' => $actionData,
                'details' => $detailsData
            ]
        ];

        $this->paymentMock
            ->expects($this->atLeastOnce())
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use ($actionData, $detailsData) {
                if ($key === 'action') {
                    $this->assertEquals($actionData, $value);
                }
                if ($key === 'details') {
                    $this->assertEquals($detailsData, $value);
                }
                return null;
            });

        $this->applyGenericMockExpectations();

        $this->checkoutPaymentsDetailsHandler->handle($this->handlingSubject, $responseCollection);
    }

    public function testIfCheckoutApiOrderIsStoredForActionRequiredStatuses()
    {
        $checkoutApiOrderData = [
            'pspReference' => 'ORDER_PSP_REF_789',
            'orderData' => 'encoded_order_data_for_partial_payment'
        ];

        // prepare Handler input.
        $responseCollection = [
            0 => [
                'additionalData' => [],
                'amount' => [],
                'resultCode' => 'RedirectShopper',
                'pspReference' => 'ABC12345'
            ]
        ];

        // Mock ordersApi to return checkout API order data
        $this->ordersApiHelperMock
            ->expects($this->atLeastOnce())
            ->method('getCheckoutApiOrder')
            ->willReturn($checkoutApiOrderData);

        // Verify that checkout API order data is stored in payment additional information
        $this->paymentMock
            ->expects($this->atLeastOnce())
            ->method('setAdditionalInformation')
            ->willReturnCallback(function ($key, $value) use ($checkoutApiOrderData) {
                if ($key === OrdersApi::DATA_KEY_CHECKOUT_API_ORDER) {
                    $this->assertEquals($checkoutApiOrderData, $value);
                }
                return null;
            });

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
