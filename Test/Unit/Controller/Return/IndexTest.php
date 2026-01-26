<?php

declare(strict_types=1);

namespace Adyen\Payment\Test\Unit\Controller\Return;

use Adyen\Payment\Controller\Return\Index;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\PaymentResponseHandler;
use Adyen\Payment\Helper\PaymentsDetails;
use Adyen\Payment\Helper\Quote;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Sales\OrderRepository;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Exception;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote as QuoteModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;

class IndexTest extends AbstractAdyenTestCase
{
    private Index $indexController;

    private MockObject $context;
    private MockObject $orderFactory;
    private MockObject $session;
    private MockObject $adyenLogger;
    private MockObject $storeManager;
    private MockObject $quoteHelper;
    private MockObject $configHelper;
    private MockObject $paymentsDetailsHelper;
    private MockObject $paymentResponseHandler;
    private MockObject $cartRepository;
    private MockObject $orderRepository;
    private MockObject $request;
    private MockObject $response;
    private MockObject $messageManager;

    protected function setUp(): void
    {
        $this->context = $this->createMock(Context::class);
        $this->orderFactory = $this->createMock(OrderFactory::class);
        $this->session = $this->createMock(Session::class);
        $this->adyenLogger = $this->createMock(AdyenLogger::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->quoteHelper = $this->createMock(Quote::class);
        $this->configHelper = $this->createMock(Config::class);
        $this->paymentsDetailsHelper = $this->createMock(PaymentsDetails::class);
        $this->paymentResponseHandler = $this->createMock(PaymentResponseHandler::class);
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->orderRepository = $this->createMock(OrderRepository::class);

        $this->request = $this->createMock(RequestInterface::class);
        $this->response = $this->createMock(RedirectInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);

        $this->context->method('getRequest')->willReturn($this->request);
        $this->context->method('getResponse')->willReturn($this->response);
        $this->context->method('getMessageManager')->willReturn($this->messageManager);
        $this->storeManager->method('getStore')->willReturn($store);

        $this->indexController = $this->getMockBuilder(Index::class)
            ->setConstructorArgs([
                $this->context,
                $this->orderFactory,
                $this->session,
                $this->adyenLogger,
                $this->storeManager,
                $this->quoteHelper,
                $this->configHelper,
                $this->paymentsDetailsHelper,
                $this->paymentResponseHandler,
                $this->cartRepository,
                $this->orderRepository
            ])
            ->onlyMethods(['_redirect'])
            ->getMock();
    }

    public function testExecuteWithSuccessfulRedirect(): void
    {
        $params = ['merchantReference' => '1001', 'redirectResult' => 'test'];
        $this->request->method('getParams')->willReturn($params);
        $this->quoteHelper->method('getIsQuoteMultiShippingWithMerchantReference')->willReturn(false);

        $this->configHelper->method('getAdyenAbstractConfigData')
            ->willReturnMap([
                ['custom_success_redirect_path', 1, 'checkout/onepage/success'],
                ['return_path', 1, 'checkout/cart']
            ]);

        $quote = $this->createMock(QuoteModel::class);
        $quote->expects($this->once())->method('setIsActive')->with(false);
        $this->session->method('getQuote')->willReturn($quote);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);
        $order->method('getIncrementId')->willReturn('1001');
        $order->method('getPayment')->willReturn($this->createMock(Order\Payment::class));

        $this->orderRepository->method('getByIncrementId')->willReturn($order);

        $this->paymentsDetailsHelper->method('initiatePaymentDetails')->willReturn(['resultCode' => 'Authorised']);
        $this->paymentResponseHandler->method('handlePaymentsDetailsResponse')->willReturn(true);

        $this->indexController->expects($this->once())
            ->method('_redirect')
            ->with('checkout/onepage/success', ['_query' => ['order_increment_id' => '1001']]);

        $this->indexController->execute();
    }

    public function testExecuteWithFailedRedirect(): void
    {
        $params = ['merchantReference' => '1001', 'redirectResult' => 'test'];
        $this->request->method('getParams')->willReturn($params);
        $this->quoteHelper->method('getIsQuoteMultiShippingWithMerchantReference')->willReturn(false);

        $this->configHelper->method('getAdyenAbstractConfigData')
            ->willReturnMap([
                ['custom_success_redirect_path', 1, null],
                ['return_path', 1, 'checkout/cart']
            ]);

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(1);

        $this->orderRepository->method('getByIncrementId')->willReturn($order);

        $this->paymentsDetailsHelper->method('initiatePaymentDetails')->willThrowException(new Exception('Invalid'));
        $this->paymentResponseHandler->method('handlePaymentsDetailsResponse')->willReturn(false);

        $this->session->expects($this->once())->method('restoreQuote');
        $this->messageManager->expects($this->once())->method('addError');

        $this->indexController->expects($this->once())
            ->method('_redirect')
            ->with('checkout/cart');

        $this->indexController->execute();
    }

    public function testExecuteWithoutParams(): void
    {
        $this->request->method('getParams')->willReturn([]);
        $this->configHelper->method('getAdyenAbstractConfigData')->willReturn('checkout/cart');

        $this->indexController->expects($this->once())
            ->method('_redirect')
            ->with('checkout/cart');

        $this->indexController->execute();
    }

    public function testGetOrderWithValidId(): void
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(10);
        $this->orderRepository->method('getByIncrementId')->with('1001')->willReturn($order);

        $reflection = new \ReflectionClass(Index::class);
        $method = $reflection->getMethod('getOrder');
        $method->setAccessible(true);
        $result = $method->invokeArgs($this->indexController, ['1001']);
        $this->assertSame($order, $result);
    }

    public function testGetOrderThrowsExceptionOnInvalidOrder(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Order cannot be loaded');

        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(null);
        $this->session->method('getLastRealOrder')->willReturn($order);

        $reflection = new \ReflectionClass(Index::class);
        $method = $reflection->getMethod('getOrder');
        $method->setAccessible(true);
        $method->invokeArgs($this->indexController, [null]);

    }
}
