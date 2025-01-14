<?php
/** @noinspection PhpParamsInspection */

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Webhook\CaptureWebhookHandler;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\Order\Payment;

class CaptureWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected CaptureWebhookHandler $captureWebhookHandler;
    private MagentoOrder $order;
    private Notification $notification;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the CaptureWebhookHandler with mock dependencies.
        $this->captureWebhookHandler = $this->createCaptureWebhookHandler();
        $this->order = $this->createOrder();
        $this->notification = $this->createWebhook();
        $this->notification->method('getEventCode')->willReturn('CAPTURE');
        $this->notification->method('getAmountValue')->willReturn(500); // Partial capture amount
        $this->notification->method('getOriginalReference')->willReturn('original_reference');
        $this->notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $this->notification->method('getPaymentMethod')->willReturn('ADYEN_CC');
    }

    private function createCaptureWebhookHandler(
        $invoiceHelper = null,
        $adyenOrderPaymentFactory = null,
        $adyenOrderPaymentHelper = null,
        $adyenLogger = null,
        $orderHelper = null,
        $paymentMethodsHelper = null,
        $invoiceRepository = null
    ): CaptureWebhookHandler
    {
        if($invoiceHelper == null) {
            $invoiceHelper = $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []);
        }
        if($adyenOrderPaymentFactory == null) {
            $adyenOrderPaymentFactory = $this->createGeneratedMock(PaymentFactory::class, ['create', 'load']);
        }
        if($adyenOrderPaymentHelper == null) {
            $adyenOrderPaymentHelper = $this->createMockWithMethods(AdyenOrderPayment::class, ['refreshPaymentCaptureStatus'], []);
        }
        if($adyenLogger == null) {
            $adyenLogger = $this->createGeneratedMock(AdyenLogger::class, ['addAdyenNotification', 'getInvoiceContext']);
        }
        if($orderHelper == null) {
            $orderHelper = $this->createGeneratedMock(Order::class, ['fetchOrderByIncrementId', 'finalizeOrder']);
        }
        if($paymentMethodsHelper == null) {
            $paymentMethodsHelper = $this->createGeneratedMock(PaymentMethods::class);
        }
        if($invoiceRepository == null) {
            $invoiceRepository = $this->createGeneratedMock(InvoiceRepositoryInterface::class);
        }

        return new CaptureWebhookHandler(
            invoiceHelper: $invoiceHelper,
            adyenOrderPaymentFactory: $adyenOrderPaymentFactory,
            adyenOrderPaymentHelper: $adyenOrderPaymentHelper,
            adyenLogger: $adyenLogger,
            orderHelper: $orderHelper,
            paymentMethodsHelper: $paymentMethodsHelper,
            invoiceRepository: $invoiceRepository
        );
    }

    public function testHandleWebhookWithAutoCapture()
    {
        // Set up a partial mock for the Invoice class to expect no calls to handleCaptureWebhook
        $invoiceHelperMock = $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []);
        $invoiceHelperMock->expects($this->never())->method('handleCaptureWebhook');

        // Mock the paymentMethodsHelper to return false for isAutoCapture
        $paymentMethodsHelperMock = $this->createMockWithMethods(PaymentMethods::class, ['isAutoCapture'], []);
        $paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);

        $this->captureWebhookHandler = $this->createCaptureWebhookHandler(
            null,
            null,
            null,
            null,
            null,
            $paymentMethodsHelperMock
        );

        // Test handleWebhook method
        $result = $this->captureWebhookHandler->handleWebhook($this->order, $this->notification, 'paid');

        // Assert that the order is not modified
        $this->assertSame($this->order, $result);
    }

    public function testHandleWebhookWithoutAutoCapture()
    {
        // Mock methods
        $adyenInvoice = $this->createConfiguredMock(AdyenInvoice::class, ['getAdyenPaymentOrderId' => 123, 'getInvoiceId' => 456]);
        $magentoInvoice = $this->createMock(MagentoOrder\Invoice::class);

        $magentoInvoiceRepositoryMock = $this->createMock(InvoiceRepositoryInterface::class);
        $magentoInvoiceRepositoryMock->method('get')->willReturn($magentoInvoice);

        // Mock the paymentMethodsHelper to return false for isAutoCapture
        $paymentMethodsHelperMock = $this->createMockWithMethods(PaymentMethods::class, ['isAutoCapture'], []);
        $paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);

        // Set up expectations on the invoiceHelperMock
        $invoiceHelperMock = $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []);
        $invoiceHelperMock->expects($this->once())->method('handleCaptureWebhook')->willReturn($adyenInvoice);

        // Set up a partial mock of orderHelper to expect a call to fetchOrderByIncrementId
        $orderHelperMock = $this->createGeneratedMock(Order::class, ['fetchOrderByIncrementId', 'finalizeOrder']);
        $orderHelperMock->expects($this->once())->method('fetchOrderByIncrementId')->willReturn($this->order);
        $orderHelperMock->expects($this->once())
            ->method('finalizeOrder')
            ->with($this->order, $this->notification)
            ->willReturn($this->order);

        // Mock the adyenOrderPaymentFactory
        $adyenOrderPaymentFactoryMock = $this->createGeneratedMock(PaymentFactory::class, ['create']);

        $adyenOrderPaymentMock = $this->getMockBuilder(Payment::class)
            ->setMethods(['load']) // Define the method you want to mock
            ->disableOriginalConstructor()
            ->getMock();

        $adyenOrderPaymentMock->expects($this->once())
            ->method('load')
            ->with(123, OrderPaymentInterface::ENTITY_ID)
            ->willReturnSelf(); // Return the mock itself

        // Set up expectations for the create and load methods
        $adyenOrderPaymentFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($adyenOrderPaymentMock);

        $adyenOrderPaymentHelperMock = $this->createMock(AdyenOrderPayment::class);

        $adyenOrderPaymentHelperMock->expects($this->once())
            ->method('refreshPaymentCaptureStatus')
            ->with($adyenOrderPaymentMock, $this->notification->getAmountCurrency());

        $this->captureWebhookHandler = $this->createCaptureWebhookHandler(
            $invoiceHelperMock,
            $adyenOrderPaymentFactoryMock,
            $adyenOrderPaymentHelperMock,
            null,
            $orderHelperMock,
            $paymentMethodsHelperMock,
            $magentoInvoiceRepositoryMock
        );

        // Test handleWebhook method
        $result = $this->captureWebhookHandler->handleWebhook($this->order, $this->notification, 'paid');

        // Assert that the order is finalized
        $this->assertEqualsCanonicalizing($this->order, $result);
    }

    public function testHandleWebhookTransitionNotPaid()
    {
        // Test handleWebhook method with transition state different from "PAID"
        $result = $this->captureWebhookHandler->handleWebhook($this->order, $this->notification, 'NOT_PAID');

        // Assert that the order is not modified
        $this->assertEqualsCanonicalizing($this->order, $result);
    }
}
