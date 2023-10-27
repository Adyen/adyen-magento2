<?php declare(strict_types=1);

/**
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2023 Adyen N.V. (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Test\Unit\Helper;

use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Creditmemo as AdyenCreditmemoHelper;
use Adyen\Payment\Helper\Data;
use Adyen\Payment\Helper\Order;
use Adyen\Payment\Helper\PaymentMethods;
use Adyen\Payment\Model\AdyenAmountCurrency;
use Adyen\Payment\Model\Config\Source\Status\AdyenState;
use Adyen\Payment\Model\Creditmemo as AdyenCreditmemoModel;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\ResourceModel\Creditmemo\Creditmemo as AdyenCreditMemoResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Test\Unit\AbstractAdyenTestCase;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Notification\NotifierPool;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;

class OrderTest extends AbstractAdyenTestCase
{
    protected $adyenCreditmemoHelperMock;

    public function testFinalizeOrderFinalized()
    {
        $dataHelper = $this->createConfiguredMock(Data::class, ['formatAmount' => 'EUR123']);
        $adyenPaymentOrderHelper = $this->createConfiguredMock(AdyenOrderPayment::class, ['isFullAmountFinalized' => true]);
        $configHelper = $this->createConfiguredMock(Config::class, ['getConfigData' => 'payment_authorized']);
        $chargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
            'getOrderAmountCurrency' => new AdyenAmountCurrency(1000, 'EUR')
        ]);

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
        $dataHelper = $this->createConfiguredMock(Data::class, ['formatAmount' => 'EUR123']);
        $chargedCurrency = $this->createConfiguredMock(ChargedCurrency::class, [
            'getOrderAmountCurrency' => new AdyenAmountCurrency(1000, 'EUR')
        ]);
        $adyenPaymentOrderHelper = $this->createConfiguredMock(AdyenOrderPayment::class, [
            'isFullAmountFinalized' => false
        ]);
        $configHelper = $this->createConfiguredMock(Config::class, [
            'getConfigData' => 'payment_authorized'
        ]);

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
        $configHelper = $this->createConfiguredMock(Config::class, [
            'getConfigData' => 'payment_cancelled',
            'getNotificationsCanCancel' => true
        ]);

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
        $configHelper = $this->createConfiguredMock(Config::class, [
            'getConfigData' => MagentoOrder::STATE_HOLDED,
            'getNotificationsCanCancel' => true
        ]);

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
        $configHelper = $this->createConfiguredMock(Config::class, [
            'getConfigData' => 'payment_cancelled',
            'getNotificationsCanCancel' => true
        ]);

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
        $dataHelper = $this->createPartialMock(Data::class, []);

        $adyenOrderPaymentHelper = $this->createMock(AdyenOrderPayment::class);
        $adyenOrderPaymentHelper->expects($this->once())->method('refundAdyenOrderPayment');

        $adyenCreditmemoHelper = $this->createMock(AdyenCreditmemoHelper::class);
        $adyenCreditMemo = $this->createMock(AdyenCreditmemoModel::class);
        $adyenCreditmemoHelper->expects($this->once())
            ->method('createAdyenCreditMemo')
            ->willReturn($adyenCreditMemo);
        $adyenCreditmemoHelper->expects($this->once())
            ->method('updateAdyenCreditmemosStatus')
            ->with($adyenCreditMemo, AdyenCreditmemoModel::COMPLETED_STATUS);

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            $adyenOrderPaymentHelper,
            null,
            $dataHelper,
            $this->createAdyenOrderPaymentCollection(1),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $adyenCreditmemoHelper
        );

        $orderPaymentReturnMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getCreditmemo' => $this->createMock(MagentoOrder\Creditmemo::class)
        ]);
        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'registerRefundNotification' => $orderPaymentReturnMock
        ]);
        $orderConfigMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Config::class, [
            'getStateDefaultStatus' => MagentoOrder::STATE_CLOSED
        ]);
        $order = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $orderPaymentMock,
            'getConfig' => $orderConfigMock,
            'canCreditmemo' => true
        ]);

        $notification = $this->createWebhook('123-refund', '123-pspref');
        $orderHelper->refundOrder($order, $notification);
    }

    public function testRefundFailedNotice()
    {
        $notification = $this->createMock(Notification::class);
        $notification->method('getPspreference')->willReturn('123');
        $adyenCreditmemoHelper = $this->createMock(AdyenCreditmemoHelper::class);
        $adyenCreditMemo = $this->createMock(AdyenCreditmemoModel::class);

        $adyenCreditmemoHelper->expects($this->once())
            ->method('getAdyenCreditmemoByPspreference')
            ->willReturn($adyenCreditMemo);

        $adyenCreditmemoHelper->expects($this->once())
            ->method('updateAdyenCreditmemosStatus')
            ->with($adyenCreditMemo, AdyenCreditmemoModel::FAILED_STATUS);

        $orderPaymentReturnMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'getCreditmemo' => $this->createMock(MagentoOrder\Creditmemo::class)
        ]);
        $orderPaymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'registerRefundNotification' => $orderPaymentReturnMock
        ]);
        $orderConfigMock = $this->createConfiguredMock(\Magento\Sales\Model\Order\Config::class, [
            'getStateDefaultStatus' => MagentoOrder::STATE_CLOSED
        ]);
        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $adyenCreditmemoHelper
        );
        $order = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $orderPaymentMock,
            'getConfig' => $orderConfigMock,
            'canCreditmemo' => true
        ]);

        $orderHelper->addRefundFailedNotice($order, $notification);
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
        $paymentMethodsHelper = null,
        $adyenCreditmemoResourceModel = null,
        $adyenCreditmemoHelper = null
    ): Order
    {
        $context = $this->createMock(Context::class);

        if (is_null($builder)) {
            $builder = $this->createMock(Builder::class);
        }

        if (is_null($dataHelper)) {
            $dataHelper = $this->createMock(Data::class);
        }

        if (is_null($adyenLogger)) {
            $adyenLogger = $this->createMock(AdyenLogger::class);
        }

        if (is_null($orderSender)) {
            $orderSender = $this->createMock(OrderSender::class);
        }

        if (is_null($transactionFactory)) {
            $transactionFactory = $this->createGeneratedMock(TransactionFactory::class);
        }

        if (is_null($chargedCurrency)) {
            $chargedCurrency = $this->createMock(ChargedCurrency::class);
        }

        if (is_null($adyenPaymentOrderHelper)) {
            $adyenPaymentOrderHelper = $this->createMock(AdyenOrderPayment::class);
        }

        if (is_null($configHelper)) {
            $configHelper = $this->createMock(Config::class);
        }

        if (is_null($orderStatusCollectionFactory)) {
            $orderStatusCollectionFactory = $this->createGeneratedMock(OrderStatusCollectionFactory::class);
        }

        if (is_null($searchCriteriaBuilder)) {
            $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        }

        if (is_null($orderRepository)) {
            $orderRepository = $this->createMock(OrderRepository::class);
        }

        if (is_null($notifierPool)) {
            $notifierPool = $this->createMock(NotifierPool::class);
        }

        if (is_null($orderPaymentCollectionFactory)) {
            $orderPaymentCollectionFactory = $this->createGeneratedMock(OrderPaymentCollectionFactory::class);
        }

        if (is_null($paymentMethodsHelper)) {
            $paymentMethodsHelper = $this->createMock(PaymentMethods::class);
        }

        if (is_null($adyenCreditmemoResourceModel)) {
            $adyenCreditmemoResourceModel = $this->createMock(AdyenCreditMemoResourceModel::class);
        }

        if (is_null($adyenCreditmemoHelper)) {
            $adyenCreditmemoHelper = $this->createMock(AdyenCreditmemoHelper::class);
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
            $paymentMethodsHelper,
            $adyenCreditmemoResourceModel,
            $adyenCreditmemoHelper
        );
    }
}
