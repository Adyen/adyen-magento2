<?php declare(strict_types=1);

/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2022 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */
namespace Adyen\Payment\Tests\Helper;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Config\Source\Status\AdyenState;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Order\Payment\Collection;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Tests\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Notification\NotifierPool;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status\Collection as OrderStatusCollection;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;

class OrderTest extends AbstractAdyenTestCase
{
    public function testFinalizeOrderFinalized()
    {
        $dataHelper = $this->getSimpleMock(Data::class);
        $dataHelper->method('formatAmount')->willReturn('EUR123');

        $chargedCurrency = $this->getSimpleMock(ChargedCurrency::class);
        $chargedCurrency->method('getOrderAmountCurrency')->willReturn(new AdyenAmountCurrency(1000, 'EUR'));

        $adyenPaymentOrderHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        $adyenPaymentOrderHelper->method('isFullAmountFinalized')->willReturn(true);

        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn('payment_authorized');

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
            $adyenPaymentOrderHelper,
            $chargedCurrency,
            $dataHelper
        );

        $order = $this->createOrder('testStatus');
        $notification = $this->createWebhook();

        $order->expects($this->once())->method('setState')->with(MagentoOrder::STATE_PROCESSING);
        $orderHelper->finalizeOrder($order, $notification);
    }

    public function testFinalizeOrderPartialPayment()
    {
        $dataHelper = $this->getSimpleMock(Data::class);
        $dataHelper->method('formatAmount')->willReturn('EUR123');

        $chargedCurrency = $this->getSimpleMock(ChargedCurrency::class);
        $chargedCurrency->method('getOrderAmountCurrency')->willReturn(new AdyenAmountCurrency(1000, 'EUR'));

        $adyenPaymentOrderHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        $adyenPaymentOrderHelper->method('isFullAmountFinalized')->willReturn(false);

        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn('payment_authorized');

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
            $adyenPaymentOrderHelper,
            $chargedCurrency,
            $dataHelper
        );

        $order = $this->createOrder('testStatus');
        $notification = $this->createWebhook();

        $order->expects($this->never())->method('setState')->with(MagentoOrder::STATE_PROCESSING);
        $orderHelper->finalizeOrder($order, $notification);
    }

    public function testFinalizeOrderMaintainState()
    {
        $dataHelper = $this->getSimpleMock(Data::class);
        $dataHelper->method('formatAmount')->willReturn('EUR123');

        $chargedCurrency = $this->getSimpleMock(ChargedCurrency::class);
        $chargedCurrency->method('getOrderAmountCurrency')->willReturn(new AdyenAmountCurrency(1000, 'EUR'));

        $adyenPaymentOrderHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        $adyenPaymentOrderHelper->method('isFullAmountFinalized')->willReturn(false);

        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn(AdyenState::STATE_MAINTAIN);

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
            $adyenPaymentOrderHelper,
            $chargedCurrency,
            $dataHelper
        );

        $order = $this->createOrder('testStatus');
        $notification = $this->createWebhook();

        $order->expects($this->never())->method('setState')->with(MagentoOrder::STATE_PROCESSING);
        $orderHelper->finalizeOrder($order, $notification);
    }

    public function testHoldCancelOrderCancel()
    {
        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn('payment_cancelled');
        $configHelper->method('getNotificationsCanCancel')->willReturn(true);

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
        );

        $order = $this->createOrder('testStatus');
        $order->method('hasInvoices')->willReturn(false);
        $order->method('canCancel')->willReturn(true);

        $order->expects($this->once())->method('cancel');
        $orderHelper->holdCancelOrder($order, false);
    }

    public function testHoldCancelOrderHold()
    {
        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn(MagentoOrder::STATE_HOLDED);
        $configHelper->method('getNotificationsCanCancel')->willReturn(true);

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
        );

        $order = $this->createOrder('testStatus');
        $order->method('hasInvoices')->willReturn(false);
        $order->method('canHold')->willReturn(true);

        $order->expects($this->once())->method('hold');
        $orderHelper->holdCancelOrder($order, false);
    }

    public function testHoldCancelOrderNotCancellable()
    {
        $configHelper = $this->getSimpleMock(Config::class);
        $configHelper->method('getConfigData')->willReturn('payment_cancelled');
        $configHelper->method('getNotificationsCanCancel')->willReturn(true);

        $orderHelper = $this->createOrderHelper(
            $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING),
            $configHelper,
        );

        $order = $this->createOrder('testStatus');
        $order->method('hasInvoices')->willReturn(true);

        $order->expects($this->never())->method('cancel');
        $order->expects($this->never())->method('hold');
        $orderHelper->holdCancelOrder($order, false);
    }

    public function testRefundOrderSuccessful()
    {
        $dataHelper = $this->getSimpleMock(Data::class);
        $dataHelper->method('parseTransactionId')->willReturn(['pspReference' => '123-refund']);

        $adyenOrderPaymentHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        $adyenOrderPaymentHelper->expects($this->once())->method('refundAdyenOrderPayment');

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            $adyenOrderPaymentHelper,
            null,
            $dataHelper,
            $this->createAdyenOrderPaymentCollection(1)
        );

        $order = $this->createOrder();
        $order->method('canCreditmemo')->willReturn(true);
        $order->getPayment()->expects($this->once())->method('registerRefundNotification');
        $notification = $this->createWebhook('123-refund');

        $orderHelper->refundOrder($order, $notification);
    }

    public function testRefundOrderAdyenOrderPaymentNotFound()
    {
        $dataHelper = $this->getSimpleMock(Data::class);
        $dataHelper->method('parseTransactionId')->willReturn(['pspReference' => '123-refund']);

        $adyenOrderPaymentHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        $adyenOrderPaymentHelper->expects($this->never())->method('refundAdyenOrderPayment');

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            $adyenOrderPaymentHelper,
            null,
            $dataHelper,
            $this->createAdyenOrderPaymentCollection(0)
        );

        $order = $this->createOrder();
        $order->method('canCreditmemo')->willReturn(true);
        // registerRefundNotification is still being called when the adyen_order_payment is not found
        $order->getPayment()->expects($this->once())->method('registerRefundNotification');
        $notification = $this->createWebhook('123-refund');

        $orderHelper->refundOrder($order, $notification);
    }

    protected function createOrder(?string $status = null)
    {
        $orderPaymentMock = $this->getMockBuilder(MagentoOrder\Payment::class)->disableOriginalConstructor()->getMock();
        $orderPaymentMock->method('getMethod')->willReturn('adyen_cc');

        $orderMock = $this->getMockBuilder(MagentoOrder::class)->disableOriginalConstructor()->getMock();
        $orderMock->method('getStatus')->willReturn($status);
        $orderMock->method('getPayment')->willReturn($orderPaymentMock);

        return $orderMock;
    }

    protected function createWebhook(?string $originalReference = null)
    {
        $notificationMock = $this->getMockBuilder(Notification::class)->disableOriginalConstructor()->getMock();
        $notificationMock->method('getAmountValue')->willReturn(1000);
        $notificationMock->method('getEventCode')->willReturn('AUTHORISATION');
        $notificationMock->method('getAmountCurrency')->willReturn('EUR');
        $notificationMock->method('getOriginalReference')->willReturn($originalReference);

        return $notificationMock;
    }

    protected function createOrderStatusCollection($state)
    {
        $orderStatus = $this->getSimpleMock(MagentoOrder\Status::class, ['getState']);
        $orderStatus->method('getState')->willReturn($state);

        $orderStatusCollection = $this->getSimpleMock(OrderStatusCollection::class);
        $orderStatusCollection->method('addFieldToFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('joinStates')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('addStateFilter')->willReturn($orderStatusCollection);
        $orderStatusCollection->method('getFirstItem')->willReturn($orderStatus);

        $orderStatusCollectionFactory = $this->createGeneratedMock(OrderStatusCollectionFactory::class, ['create']);
        $orderStatusCollectionFactory->method('create')->willReturn($orderStatusCollection);

        return $orderStatusCollectionFactory;
    }

    protected function createAdyenOrderPaymentCollection(?int $entityId = null)
    {
        $adyenOrderPayment = $this->getSimpleMock(OrderPaymentInterface::class);
        $adyenOrderPayment->method('getEntityId')->willReturn($entityId);

        $adyenOrderPaymentCollection = $this->getSimpleMock(Collection::class);
        $adyenOrderPaymentCollection->method('addFieldToFilter')->willReturn($adyenOrderPaymentCollection);
        $adyenOrderPaymentCollection->method('getFirstItem')->willReturn($adyenOrderPayment);

        $adyenOrderPaymentCollectionFactory = $this->createGeneratedMock(OrderPaymentCollectionFactory::class, ['create']);
        $adyenOrderPaymentCollectionFactory->method('create')->willReturn($adyenOrderPaymentCollection);

        return $adyenOrderPaymentCollectionFactory;
    }

    protected function createOrderHelper(
        $orderStatusCollectionFactory = null,
        $configHelper = null,
        $adyenPaymentOrderHelper = null,
        $chargedCurrency = null,
        $dataHelper = null,
        $orderPaymentCollectionFactory = null,
        $builder = null,
        $adyenLogger = null,
        $orderSender = null,
        $transactionFactory = null,
        $searchCriteriaBuilder = null,
        $orderRepository = null,
        $notifierPool = null,
        $paymentMethodsHelper = null
    ): Order {
        $context = $this->getSimpleMock(Context::class);

        if (is_null($builder)) {
            $builder = $this->getSimpleMock(Builder::class);
        }

        if (is_null($dataHelper)) {
            $dataHelper = $this->getSimpleMock(Data::class);
        }

        if (is_null($adyenLogger)) {
            $adyenLogger = $this->getSimpleMock(AdyenLogger::class);
        }

        if (is_null($orderSender)) {
            $orderSender = $this->getSimpleMock(OrderSender::class);
        }

        if (is_null($transactionFactory)) {
            $transactionFactory = $this->createGeneratedMock(TransactionFactory::class);
        }

        if (is_null($chargedCurrency)) {
            $chargedCurrency = $this->getSimpleMock(ChargedCurrency::class);
        }

        if (is_null($adyenPaymentOrderHelper)) {
            $adyenPaymentOrderHelper = $this->getSimpleMock(AdyenOrderPayment::class);
        }

        if (is_null($configHelper)) {
            $configHelper = $this->getSimpleMock(Config::class);
        }

        if (is_null($orderStatusCollectionFactory)) {
            $orderStatusCollectionFactory = $this->createGeneratedMock(OrderStatusCollectionFactory::class);
        }

        if (is_null($searchCriteriaBuilder)) {
            $searchCriteriaBuilder = $this->getSimpleMock(SearchCriteriaBuilder::class);
        }

        if (is_null($orderRepository)) {
            $orderRepository = $this->getSimpleMock(OrderRepository::class);
        }

        if (is_null($notifierPool)) {
            $notifierPool = $this->getSimpleMock(NotifierPool::class);
        }

        if (is_null($orderPaymentCollectionFactory)) {
            $orderPaymentCollectionFactory = $this->createGeneratedMock(OrderPaymentCollectionFactory::class);
        }

        if (is_null($paymentMethodsHelper)) {
            $paymentMethodsHelper = $this->getSimpleMock(PaymentMethods::class);
        }

        return new Order(
            $context,
            $builder,
            $dataHelper,
            $adyenLogger,
            $orderSender,
            $transactionFactory,
            $chargedCurrency,
            $adyenPaymentOrderHelper,
            $configHelper,
            $orderStatusCollectionFactory,
            $searchCriteriaBuilder,
            $orderRepository,
            $notifierPool,
            $orderPaymentCollectionFactory,
            $this->getSimpleMock(SerializerInterface::class)
        );
    }
}
