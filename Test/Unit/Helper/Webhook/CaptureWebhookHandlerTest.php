<?php /** @noinspection PhpParamsInspection */

namespace Adyen\Payment\Test\Unit\Helper\Webhook;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\Webhook\CaptureWebhookHandler;
use Adyen\Payment\Helper\Invoice;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Magento\Sales\Model\Order\InvoiceFactory as MagentoInvoiceFactory;
use Adyen\Payment\Model\Invoice as AdyenInvoice;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Adyen\Payment\Model\Order\Payment;
use Magento\Sales\Model\Order\Invoice as MagentoInvoice;

class CaptureWebhookHandlerTest extends AbstractAdyenTestCase
{
    protected CaptureWebhookHandler $captureWebhookHandler;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize the CaptureWebhookHandler with mock dependencies.
        /** @noinspection PhpParamsInspection */
        $this->captureWebhookHandler = new CaptureWebhookHandler(
            invoiceHelper: $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []),
            adyenOrderPaymentFactory: $this->createGeneratedMock(PaymentFactory::class, ['create', 'load']),
            adyenOrderPaymentHelper: $this->createMockWithMethods(AdyenOrderPayment::class, ['refreshPaymentCaptureStatus'], []),
            adyenLogger: $this->createGeneratedMock(AdyenLogger::class, ['addAdyenNotification', 'getInvoiceContext']),
            magentoInvoiceFactory: $this->createGeneratedMock(MagentoInvoiceFactory::class, ['create', 'load']),
            orderHelper: $this->createGeneratedMock(Order::class, ['fetchOrderByIncrementId']),
            paymentMethodsHelper: $this->createGeneratedMock(PaymentMethods::class)
        );
    }

    public function testHandleWebhookWithAutoCapture()
    {
        // Mock the necessary objects
        $order = $this->createOrder();
        $notification = $this->createWebhook();
        $notification->method('getEventCode')->willReturn('CAPTURE');
        $notification->method('getAmountValue')->willReturn(500); // Partial capture amount
        $notification->method('getOriginalReference')->willReturn('original_reference');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        // Mock methods
        // Set up a partial mock for the Invoice class to expect no calls to handleCaptureWebhook
        $invoiceHelperMock = $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'],[]);
        $invoiceHelperMock->expects($this->never())->method('handleCaptureWebhook');

        // Mock the paymentMethodsHelper to return false for isAutoCapture
        $paymentMethodsHelperMock = $this->createMockWithMethods(PaymentMethods::class, ['isAutoCapture'], []);
        $paymentMethodsHelperMock->method('isAutoCapture')->willReturn(true);

        $this->captureWebhookHandler = new CaptureWebhookHandler(
            invoiceHelper: $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []),
            adyenOrderPaymentFactory: $this->createGeneratedMock(PaymentFactory::class, ['create', 'load']),
            adyenOrderPaymentHelper: $this->createMockWithMethods(AdyenOrderPayment::class, ['refreshPaymentCaptureStatus'], []),
            adyenLogger: $this->createGeneratedMock(AdyenLogger::class, ['addAdyenNotification', 'getInvoiceContext']),
            magentoInvoiceFactory: $this->createGeneratedMock(MagentoInvoiceFactory::class, ['create', 'load']),
            orderHelper: $this->createGeneratedMock(Order::class, ['fetchOrderByIncrementId']),
            paymentMethodsHelper: $paymentMethodsHelperMock
        );

        // Test handleWebhook method
        $result = $this->captureWebhookHandler->handleWebhook($order, $notification, 'paid');

        // Assert that the order is not modified
        $this->assertSame($order, $result);
    }

    public function testHandleWebhookWithoutAutoCapture()
    {
        // Mock the necessary objects
        $order = $this->createOrder();
        $notification = $this->createWebhook();
        $notification->method('getEventCode')->willReturn('CAPTURE');
        $notification->method('getAmountValue')->willReturn(1000); // Full capture amount
        $notification->method('getOriginalReference')->willReturn('original_reference');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        // Mock methods
        $invoice = $this->createConfiguredMock(AdyenInvoice::class, ['getAdyenPaymentOrderId' => 123, 'getInvoiceId' => 456]);

        // Mock the paymentMethodsHelper to return false for isAutoCapture
        $paymentMethodsHelperMock = $this->createMockWithMethods(PaymentMethods::class, ['isAutoCapture'], []);
        $paymentMethodsHelperMock->method('isAutoCapture')->willReturn(false);

        // Set up expectations on the invoiceHelperMock
        $invoiceHelperMock = $this->createMockWithMethods(Invoice::class, ['handleCaptureWebhook'], []);
        $invoiceHelperMock->expects($this->once())->method('handleCaptureWebhook')->willReturn($invoice);

        // Set up a partial mock of orderHelper to expect a call to fetchOrderByIncrementId
        $orderHelperMock = $this->createGeneratedMock(Order::class, ['fetchOrderByIncrementId','finalizeOrder']);
        $orderHelperMock->expects($this->once())->method('fetchOrderByIncrementId')->willReturn($order);
        $orderHelperMock->expects($this->once())
            ->method('finalizeOrder')
            ->with($order, $notification)
            ->willReturn($order);

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
            ->with($adyenOrderPaymentMock, $notification->getAmountCurrency());

        // Create a mock for the magentoInvoiceFactory
        $magentoInvoiceFactoryMock = $this->createMock(MagentoInvoiceFactory::class);

        // Create a mock for MagentoInvoice
        $magentoInvoiceMock = $this->createMock(MagentoInvoice::class);

        // Configure the magentoInvoiceFactoryMock to return the magentoInvoiceMock
        $magentoInvoiceFactoryMock->method('create')->willReturn($magentoInvoiceMock);

        // Configure the load method of the magentoInvoiceMock to return the same mock
        $magentoInvoiceMock->expects($this->once())
            ->method('load')
            ->willReturnSelf();

        // Now, use $invoiceHelperMock in your CaptureWebhookHandler
        /** @noinspection PhpParamsInspection */
        $this->captureWebhookHandler = new CaptureWebhookHandler(
            invoiceHelper: $invoiceHelperMock,
            adyenOrderPaymentFactory: $adyenOrderPaymentFactoryMock,
            adyenOrderPaymentHelper: $adyenOrderPaymentHelperMock,
            adyenLogger: $this->createGeneratedMock(AdyenLogger::class, ['addAdyenNotification', 'getInvoiceContext']),
            magentoInvoiceFactory: $magentoInvoiceFactoryMock,
            orderHelper: $orderHelperMock,
            paymentMethodsHelper: $paymentMethodsHelperMock
        );


        // Test handleWebhook method
        $result = $this->captureWebhookHandler->handleWebhook($order, $notification, 'paid');

        // Assert that the order is finalized
        $this->assertSame($order, $result);
    }

    public function testHandleWebhookTransitionNotPaid()
    {
        // Mock the necessary objects
        $order = $this->createOrder();
        $notification = $this->createWebhook();
        $notification->method('getEventCode')->willReturn('CAPTURE');
        $notification->method('getAmountValue')->willReturn(1000); // Full capture amount
        $notification->method('getOriginalReference')->willReturn('original_reference');
        $notification->method('getPspreference')->willReturn('ABCD1234GHJK5678');
        $notification->method('getPaymentMethod')->willReturn('ADYEN_CC');

        // Test handleWebhook method with transition state different from "PAID"
        $result = $this->captureWebhookHandler->handleWebhook($order, $notification, 'NOT_PAID');

        // Assert that the order is not modified
        $this->assertSame($order, $result);
    }


}
