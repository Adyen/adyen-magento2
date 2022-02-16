<?php declare(strict_types=1);

namespace Adyen\Payment\Helper;

use Adyen\Payment\Helper\Config as ConfigHelper;
use Adyen\Payment\Helper\Invoice as InvoiceHelper;
use Adyen\Payment\Helper\PaymentMethods as PaymentMethodsHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Api\PaymentRequest;
use Adyen\Payment\Model\Billing\AgreementFactory;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment as AdyenPayment;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement;
use Adyen\Payment\Model\ResourceModel\Billing\Agreement\CollectionFactory as AgreementCollectionFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection as AdyenPaymentCollection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use Magento\Vault\Api\Data\PaymentTokenFactoryInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use PHPUnit\Framework\TestCase;

class WebhookTest extends TestCase
{
    private $sut;
    private $order;
    private $configHelper;
    private $orderSender;
    private $adyenOrderPaymentCollectionFactory;
    private $caseManagementHelper;

    protected function setUp(): void
    {
        $payment = $this->createMock(Payment::class);
        $this->order = $this->createMock(Order::class);
        $this->order->method('getPayment')->willReturn($payment);
        $this->order->method('getGlobalCurrencyCode')->willReturn('EUR');
        $this->order->method('getOrderCurrencyCode')->willReturn('EUR');
        $this->order->method('getBaseGrandTotal')->willReturn('64.0000');
        $this->order->method('getGrandTotal')->willReturn('64.0000');

        $searchCriteria = $this->createMock(SearchCriteriaInterface::class);
        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($searchCriteria);

        $orderSearchResult = $this->createMock(OrderSearchResultInterface::class);
        $orderSearchResult->method('getItems')->willReturn([$this->order]);
        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('getList')->willReturn($orderSearchResult);

        $this->configHelper = $this->createMock(ConfigHelper::class);
        $this->orderSender = $this->createMock(OrderSender::class);
        $this->adyenOrderPaymentCollectionFactory = $this->getMockBuilder(OrderPaymentCollectionFactory::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->setMethods(['create'])
            ->getMock();
        $this->caseManagementHelper = $this->createMock(CaseManagement::class);

        $this->sut = new Webhook(
            $this->createMock(ScopeConfigInterface::class),
            $searchCriteriaBuilder,
            $orderRepository,
            $this->createPartialMock(Data::class, []),
            $this->orderSender,
            $this->createMock(InvoiceSender::class),
            $this->createGeneratedMock(TransactionFactory::class),
            $this->createGeneratedMock(AgreementFactory::class),
            $this->createGeneratedMock(AgreementCollectionFactory::class),
            $this->createMock(PaymentRequest::class),
            $this->adyenOrderPaymentCollectionFactory,
            $this->createGeneratedMock(OrderStatusCollectionFactory::class),
            $this->createMock(Agreement::class),
            $this->createMock(Builder::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(NotifierInterface::class),
            $this->createMock(TimezoneInterface::class),
            $this->configHelper,
            $this->createMock(PaymentTokenManagement::class),
            $this->createGeneratedMock(PaymentTokenFactoryInterface::class),
            $this->createMock(PaymentTokenRepositoryInterface::class),
            $this->createMock(EncryptorInterface::class),
            new ChargedCurrency($this->configHelper),
            $this->createMock(PaymentMethodsHelper::class),
            $this->createMock(InvoiceResourceModel::class),
            $this->createMock(AdyenOrderPayment::class),
            $this->createMock(InvoiceHelper::class),
            $this->caseManagementHelper,
            $this->createGeneratedMock(PaymentFactory::class),
            $this->createMock(AdyenLogger::class)
        );
    }

    /**
     * Mock a class dynamically generated by Magento
     */
    protected function createGeneratedMock(string $originalClassName)
    {
        return $this->getMockBuilder($originalClassName)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->disableArgumentCloning()
            ->getMock();
    }

    public function testProcessNotificationAuthorisation()
    {
        $this->order->method('getState')->willReturn(Order::STATE_NEW);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'AUTHORISATION',
            'getSuccess' => true,
        ]);
        $notification->expects($this->never())
            ->method('setErrorCount');
        $notification->expects($this->once())
            ->method('setDone')
            ->with(true);

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }

    public function testProcessNotificationAuthorisationFalse()
    {
        $this->order->method('getState')->willReturn(Order::STATE_NEW);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => Notification::AUTHORISATION,
            'getSuccess' => false,
        ]);
        $this->order->method('canCancel')->willReturn(true);
        $this->order->expects($this->once())->method('cancel');
        $this->configHelper->method('getNotificationsCanCancel')
            ->willReturn(true);

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }

    public function testProcessNotificationFullRefund()
    {
        $this->order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'REFUND',
            'getSuccess' => true,
            'getAmountValue' => '6400',
            'getOriginalReference' => ''
        ]);

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }

    // @TODO test credit memo flow here or on a similar test
    public function testProcessNotificationPartialRefund()
    {
        $orderPayment = $this->createConfiguredMock(AdyenPayment::class, [
            'getId' => 123,
            'getTotalRefunded' => 0
        ]);
        $orderPayment->expects($this->once())
            ->method('save');

        $paymentCollection = $this->createMock(AdyenPaymentCollection::class);
        $paymentCollection->method('addFieldToFilter')->willReturnSelf();
        $paymentCollection->method('getFirstItem')->willReturn($orderPayment);
        $this->adyenOrderPaymentCollectionFactory->method('create')->willReturn($paymentCollection);

        $this->order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'REFUND',
            'getSuccess' => true,
            'getAmountValue' => '3200',
            'getOriginalReference' => '123456789123456A'
        ]);

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }

    public function testProcessNotificationPending()
    {
        $this->order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'PENDING',
            'getSuccess' => true,
        ]);

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }

    public function testProcessNotificationManualReviewAccept()
    {
        $payment = $this->order->getPayment();
        $payment->method('getMethod')->willReturn('facilypay_3x');
        $this->order->method('getState')->willReturn(Order::STATE_COMPLETE);
        $notification = $this->createConfiguredMock(Notification::class, [
            'getEventCode' => 'MANUAL_REVIEW_ACCEPT',
            'getSuccess' => true,
            'getPaymentMethod' => 'facilypay_3x',
            'getOriginalReference' => '123123'
        ]);
        $this->caseManagementHelper->expects($this->once())
            ->method('markCaseAsAccepted')
            ->with($this->order, 'Manual review accepted for order w/pspReference: 123123');

        $result = $this->sut->processNotification($notification);

        $this->assertTrue($result);
    }
}
