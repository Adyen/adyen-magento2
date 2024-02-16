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
use Magento\Sales\Api\Data\TransactionInterface;

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

    public function testHoldCancelOrderNotConfigurableToCancel()
    {
        $storeId = 1;
        $configHelper = $this->createMock(Config::class);
        $configHelper->method('getNotificationsCanCancel')
            ->with($storeId)
            ->willReturn(false);

        $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                $this->stringContains('Order cannot be cancelled based on the plugin configuration'),
                $this->arrayHasKey('pspReference')
            );

        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $paymentMock->method('getData')->willReturnMap([
            ['adyen_psp_reference', null, 'test_psp_reference'],
            ['entity_id', null, 'test_entity_id']
        ]);

        $orderMock = $this->createMock(MagentoOrder::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderMock->expects($this->never())->method('cancel');
        $orderMock->expects($this->never())->method('hold');

        $orderHelper = $this->createOrderHelper(
            null,
            $configHelper,
            null,
            null,
            null,
            null,
            null,
            $adyenLoggerMock
        );

        $result = $orderHelper->holdCancelOrder($orderMock, false);

        $this->assertSame($orderMock, $result);
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

    public function testUpdatePaymentDetailsWithOrderInitiallyInStatePaymentReview()
    {
        $pspReference = '123456ABCDEF';
        $notificationMock = $this->createMock(Notification::class);
        $notificationMock->method('getPspreference')->willReturn($pspReference);

        $paymentMock = $this->createMock(MagentoOrder\Payment::class);
        $paymentMock->expects($this->once())->method('setCcTransId')->with($pspReference);
        $paymentMock->expects($this->once())->method('setLastTransId')->with($pspReference);
        $paymentMock->expects($this->once())->method('setTransactionId')->with($pspReference);

        $orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $paymentMock,
            'getState' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW,
            'setState' => \Magento\Sales\Model\Order::STATE_NEW
        ]);

        $transactionMock = $this->createMock(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transactionMock->expects($this->once())->method('setIsClosed')->with(false);
        $transactionMock->expects($this->once())->method('save');

        $transactionBuilderMock = $this->createMock(Builder::class);
        $transactionBuilderMock->method('setPayment')->willReturnSelf();
        $transactionBuilderMock->method('setOrder')->willReturnSelf();
        $transactionBuilderMock->method('setTransactionId')->willReturnSelf();
        $transactionBuilderMock->method('build')->willReturn($transactionMock);

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            $transactionBuilderMock
        );

        $result = $orderHelper->updatePaymentDetails($orderMock, $notificationMock);

        $this->assertInstanceOf(\Magento\Sales\Model\Order\Payment\Transaction::class, $result);
    }

    public function testUpdatePaymentDetailsWithOrderNotInStatePaymentReview()
    {
        $pspReference = '123456789';
        $paymentMock = $this->createConfiguredMock(MagentoOrder\Payment::class, [
            'setCcTransId' => $pspReference,
            'setLastTransId' => $pspReference,
            'setTransactionId' => $pspReference
        ]);
        $orderMock = $this->createConfiguredMock(MagentoOrder::class, [
            'getPayment' => $paymentMock,
            'getState' => \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW,
            'setState' => \Magento\Sales\Model\Order::STATE_NEW
        ]);

        $notificationMock = $this->createConfiguredMock(Notification::class, [
            'getPspReference' => $pspReference
        ]);

        $transactionBuilderMock = $this->createMock(Builder::class);
        $transactionMock = $this->createMock(\Magento\Sales\Model\Order\Payment\Transaction::class);
        $transactionBuilderMock->expects($this->once())
            ->method('setPayment')
            ->with($paymentMock)
            ->willReturnSelf();

        $transactionBuilderMock->expects($this->once())
            ->method('setOrder')
            ->with($orderMock)
            ->willReturnSelf();

        $transactionBuilderMock->expects($this->once())
            ->method('setTransactionId')
            ->with($pspReference)
            ->willReturnSelf();

        $transactionBuilderMock->expects($this->once())
            ->method('build')
            ->with(TransactionInterface::TYPE_AUTH)
            ->willReturn($transactionMock);

        $transactionMock->expects($this->once())
            ->method('setIsClosed')
            ->with(false);

        $transactionMock->expects($this->once())
            ->method('save');

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            $transactionBuilderMock
        );

        $result = $orderHelper->updatePaymentDetails($orderMock, $notificationMock);

        $this->assertEquals($transactionMock, $result);
    }

    public function testAddWebhookStatusHistoryComment()
    {
        $eventCode = 'AUTHORISATION';
        $amountCurrency = 'EUR';
        $amountValue = 1000;
        $expectedComment = 'AUTHORISATION webhook notification w/amount EUR 10.00 was processed';
        $dataHelperMock = $this->createMock(Data::class);
        $orderMock = $this->createMock(MagentoOrder::class);
        $notificationMock = $this->createMock(Notification::class);

        $notificationMock->method('getEventCode')->willReturn($eventCode);
        $notificationMock->method('getAmountCurrency')->willReturn($amountCurrency);
        $notificationMock->method('getAmountValue')->willReturn($amountValue);

        $dataHelperMock->method('originalAmount')->with($amountValue, $amountCurrency)->willReturn('10.00');

        $orderMock->expects($this->once())
            ->method('addStatusHistoryComment')
            ->with($this->equalTo($expectedComment), $this->equalTo(false))
            ->willReturnSelf();

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            $dataHelperMock
        );

        $result = $orderHelper->addWebhookStatusHistoryComment($orderMock, $notificationMock);

        $this->assertEquals($orderMock, $result);
    }

    public function testSendOrderMailSuccess()
    {
        $orderMock = $this->createMock(MagentoOrder::class);
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $orderSenderMock = $this->createMock(OrderSender::class);
        $paymentMock = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->disableOriginalConstructor()
            ->getMock();

        $paymentMock->expects($this->exactly(2))
            ->method('getData')
            ->willReturnMap([
                ['adyen_psp_reference', null, 'test_psp_reference'],
                ['entity_id', null, 'test_entity_id']
            ]);

        $orderMock->expects($this->exactly(2))
            ->method('getPayment')
            ->willReturn($paymentMock);

        $orderSenderMock->expects($this->once())
            ->method('send')
            ->with($orderMock);

        $adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                'Send order confirmation email to shopper',
                ['pspReference' => 'test_psp_reference', 'merchantReference' => 'test_entity_id']
            );

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $adyenLoggerMock,
            $orderSenderMock
        );

        $orderHelper->sendOrderMail($orderMock);
    }

    public function testCreateShipmentSuccess()
    {
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $orderMock = $this->createMock(MagentoOrder::class);
        $shipmentMock = $this->createPartialMock(\Magento\Sales\Model\Order\Shipment::class, ['register', 'getOrder', 'addComment']);
        $shipmentMock->method('getOrder')->willReturn($orderMock);
        $transactionBuilderMock = $this->createMock(Builder::class);

        $orderMock->method('canShip')->willReturn(true);

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            $transactionBuilderMock,
            $adyenLoggerMock
        );

        $result = $orderHelper->createShipment($orderMock);

        $this->assertEquals($orderMock, $result);
    }


    public function testCreateShipmentCannotShip()
    {
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);
        $orderMock = $this->createMock(MagentoOrder::class);
        $transactionBuilderMock = $this->createMock(Builder::class);
        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $paymentMock->method('getData')
            ->willReturnMap([
                ['adyen_psp_reference', null, 'test_psp_reference'],
                ['entity_id', null, 'test_entity_id']
            ]);
        $orderMock->method('canShip')->willReturn(false);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $adyenLoggerMock->expects($this->once())
            ->method('addAdyenNotification')
            ->with(
                'Order can\'t be shipped',
                $this->anything()
            );

        $orderHelper = $this->createOrderHelper(
            null,
            null,
            null,
            null,
            null,
            null,
            $transactionBuilderMock,
            $adyenLoggerMock
        );

        $result = $orderHelper->createShipment($orderMock);

        $this->assertEquals($orderMock, $result);
    }

    public function testSetPrePaymentAuthorized()
    {
        $storeId = 1;
        $status = 'pre_authorized';
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $orderMock = $this->createMock(MagentoOrder::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->expects($this->once())->method('setStatus')->with($status);
        $orderMock->expects($this->once())->method('getState')->willReturn('new');

        $configHelperMock = $this->createConfiguredMock(Config::class, ['getConfigData' => $status]);
        $adyenLoggerMock->expects($this->atLeastOnce())->method('addAdyenNotification')
            ->withConsecutive(
                [$this->stringContains('No new state assigned, status should be connected to one of the following states: ["new","adyen_authorized"]')],
                [$this->stringContains('Order status is changed to Pre-authorised status')]
            );

        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $paymentMock->method('getData')->willReturnMap([
            ['adyen_psp_reference', null, 'test_psp_reference'],
            ['entity_id', null, 'test_entity_id']
        ]);
        $orderMock->method('getPayment')->willReturn($paymentMock);
        $orderStatusCollectionMock = $this->createOrderStatusCollection(MagentoOrder::STATE_PROCESSING);

        $orderHelper = $this->createOrderHelper(
            $orderStatusCollectionMock,
            $configHelperMock,
            null,
            null,
            null,
            null,
            null,
            $adyenLoggerMock
        );

        $result = $orderHelper->setPrePaymentAuthorized($orderMock);

        $this->assertInstanceOf(MagentoOrder::class, $result);
        $this->assertEquals('new', $result->getState());
    }

    public function testSetPrePaymentAuthorizedNoStatus()
    {
        $storeId = 1;
        $eventLabel = "payment_pre_authorized";
        $adyenLoggerMock = $this->createMock(AdyenLogger::class);

        $orderMock = $this->createMock(MagentoOrder::class);
        $orderMock->method('getStoreId')->willReturn($storeId);
        $orderMock->method('getState')->willReturn('new');

        $configHelperMock = $this->createMock(Config::class);
        $configHelperMock->method('getConfigData')
            ->with($eventLabel, 'adyen_abstract', $storeId)
            ->willReturn('');

        $adyenLoggerMock->expects($this->once())->method('addAdyenNotification')
            ->with(
                $this->stringContains('No pre-authorised status is used so ignore'),
                $this->arrayHasKey('pspReference')
            );

        $paymentMock = $this->createMock(\Magento\Sales\Model\Order\Payment::class);
        $paymentMock->method('getData')->willReturnMap([
            ['adyen_psp_reference', null, 'test_psp_reference'],
            ['entity_id', null, 'test_entity_id']
        ]);
        $orderMock->method('getPayment')->willReturn($paymentMock);

        $orderHelper = $this->createOrderHelper(
            null,
            $configHelperMock,
            null,
            null,
            null,
            null,
            null,
            $adyenLoggerMock
        );

        $result = $orderHelper->setPrePaymentAuthorized($orderMock);

        $this->assertInstanceOf(MagentoOrder::class, $result);
        $this->assertEquals('new', $result->getState());
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
