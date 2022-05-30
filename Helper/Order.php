<?php
/**
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Config\Source\Status\AdyenState;
use Adyen\Payment\Model\Notification;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Model\Order as MagentoOrder;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Transaction\Builder;
use Magento\Sales\Model\OrderRepository;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory as OrderStatusCollectionFactory;
use Magento\Search\Model\Search;
use Magento\TestFramework\Event\Magento;

/**
 * Helper class for anything related to the invoice entity
 *
 * @package Adyen\Payment\Helper
 */
class Order extends AbstractHelper
{
    /** @var Builder */
    private $transactionBuilder;

    /** @var Data */
    private $dataHelper;

    /** @var AdyenLogger */
    private $adyenLogger;

    /** @var OrderSender */
    private $orderSender;

    /** @var TransactionFactory */
    private $transactionFactory;

    /** @var ChargedCurrency */
    private $chargedCurrency;

    /** @var AdyenOrderPayment */
    private $adyenOrderPaymentHelper;

    /** @var Config */
    private $configHelper;

    /** @var OrderStatusCollectionFactory */
    private $orderStatusCollectionFactory;

    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    public function __construct(
        Context $context,
        Builder $transactionBuilder,
        Data $dataHelper,
        AdyenLogger $adyenLogger,
        OrderSender $orderSender,
        TransactionFactory $transactionFactory,
        ChargedCurrency $chargedCurrency,
        AdyenOrderPayment $adyenOrderPaymentHelper,
        Config $configHelper,
        OrderStatusCollectionFactory $orderStatusCollectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepository $orderRepository
    )
    {
        parent::__construct($context);
        $this->transactionBuilder = $transactionBuilder;
        $this->dataHelper = $dataHelper;
        $this->adyenLogger = $adyenLogger;
        $this->orderSender = $orderSender;
        $this->transactionFactory = $transactionFactory;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenOrderPaymentHelper = $adyenOrderPaymentHelper;
        $this->configHelper = $configHelper;
        $this->orderStatusCollectionFactory = $orderStatusCollectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return TransactionInterface|null
     * @throws \Exception
     */
    public function updatePaymentDetails(MagentoOrder $order, Notification $notification): ?TransactionInterface
    {
        //Set order state to new because with order state payment_review it is not possible to create an invoice
        if (strcmp($order->getState(), MagentoOrder::STATE_PAYMENT_REVIEW) == 0) {
            $order->setState(MagentoOrder::STATE_NEW);
        }

        $paymentObj = $order->getPayment();

        // set pspReference as transactionId
        $paymentObj->setCcTransId($notification->getPspreference());
        $paymentObj->setLastTransId($notification->getPspreference());

        // set transaction
        $paymentObj->setTransactionId($notification->getPspreference());
        // Prepare transaction
        $transaction = $this->transactionBuilder->setPayment($paymentObj)
            ->setOrder($order)
            ->setTransactionId($notification->getPspreference())
            ->build(TransactionInterface::TYPE_AUTH);

        $transaction->setIsClosed(false);
        $transaction->save();

        return $transaction;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    public function addWebhookStatusHistoryComment(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        $order->addStatusHistoryComment(__(sprintf(
            '%s webhook notification w/amount %s %s was processed',
            $notification->getEventCode(),
            $notification->getAmountCurrency(),
            $this->dataHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency())
        )), false);

        return $order;
    }

    /**
     * @param MagentoOrder $order
     */
    public function sendOrderMail(MagentoOrder $order)
    {
        try {
            $this->orderSender->send($order);
            $this->adyenLogger->addAdyenNotificationCronjob('Send order confirmation email to shopper');
        } catch (Exception $exception) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                "Exception in Send Mail in Magento. This is an issue in the the core of Magento" .
                $exception->getMessage()
            );
        }
    }

    /**
     * @param MagentoOrder $order
     * @return MagentoOrder
     *
     * TODO: Throw exception when order cannot be shipped
     */
    public function createShipment(MagentoOrder $order): MagentoOrder
    {
        // create shipment for cash payment
        if ($order->canShip()) {
            $itemQty = [];
            $shipment = $order->prepareShipment($itemQty);
            if ($shipment) {
                $shipment->register();
                $shipment->getOrder()->setIsInProcess(true);
                $comment = __('Shipment created by Adyen');
                $shipment->addComment($comment);

                $transaction = $this->transactionFactory->create();
                $transaction->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();
            }
        } else {
            $this->adyenLogger->addAdyenNotificationCronjob('Order can\'t be shipped');
        }

        return $order;
    }

    /**
     * Finalize order by setting it to captured if manual capture is enabled, or authorized if auto capture is used
     * Full order will only NOT be finalized if the full amount has not been captured/authorized.
     *
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    public function finalizeOrder(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        $amount = $notification->getAmountValue();
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $formattedOrderAmount = $this->dataHelper->formatAmount($orderAmountCurrency->getAmount(), $orderAmountCurrency->getCurrencyCode());
        $fullAmountFinalized = $this->adyenOrderPaymentHelper->isFullAmountFinalized($order);

        $eventLabel = 'payment_authorized';
        $status = $this->configHelper->getConfigData(
            $eventLabel,
            'adyen_abstract',
            $order->getStoreId()
        );
        $possibleStates = Webhook::STATE_TRANSITION_MATRIX[$eventLabel];

        // Set state back to previous state to prevent update if 'maintain status' was configured
        $maintainingState = false;
        if ($status === AdyenState::STATE_MAINTAIN) {
            $maintainingState = true;
            $status = $order->getStatus();
        }

        /*
         * @TODO check for virtual orders
        // virtual order can have different status
        if ($order->getIsVirtual()) {
            $status = $this->getVirtualStatus($status);
        }
        */

        /*
         * @TODO Check for boleto specific stuff
        // check for boleto if payment is totally paid
        if ($order->getPayment()->getMethod() == "adyen_boleto") {
            // check if paid amount is the same as orginal amount
            $originalAmount = $this->boletoOriginalAmount;
            $paidAmount = $this->boletoPaidAmount;

            if ($originalAmount != $paidAmount) {
                // not the full amount is paid. Check if it is underpaid or overpaid
                // strip the  BRL of the string
                $originalAmount = str_replace("BRL", "", $originalAmount);
                $originalAmount = floatval(trim($originalAmount));

                $paidAmount = str_replace("BRL", "", $paidAmount);
                $paidAmount = floatval(trim($paidAmount));

                if ($paidAmount > $originalAmount) {
                    $overpaidStatus = $this->configHelper->getConfigData(
                        'order_overpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($overpaidStatus)) ? $overpaidStatus : $status;
                } else {
                    $underpaidStatus = $this->configHelper->getConfigData(
                        'order_underpaid_status',
                        'adyen_boleto',
                        $order->getStoreId()
                    );
                    // check if there is selected a status if not fall back to the default
                    $status = (!empty($underpaidStatus)) ? $underpaidStatus : $status;
                }
            }
        }*/

        $order = $this->addProcessedStatusHistoryComment($order, $notification);
        if ($fullAmountFinalized) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Notification w/amount %s has completed the capturing of order %s w/amount %s',
                $amount,
                $order->getIncrementId(),
                $formattedOrderAmount
            ));
            $comment = "Adyen Payment Successfully completed";
            // If a status is set, add comment, set status and update the state based on the status
            // Else add comment
            if (!empty($status) && $maintainingState) {
                $order->addStatusHistoryComment(__($comment), $status);
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Maintaining current status: ' . $status,
                    $this->adyenOrderPaymentHelper->getLogOrderContext($order)
                );
            } else if (!empty($status)) {
                $order->addStatusHistoryComment(__($comment), $status);

                $this->setState($order, $status, $possibleStates);
                $this->adyenLogger->addAdyenNotificationCronjob(
                    'Order status was changed to authorised status: ' . $status,
                    $this->adyenOrderPaymentHelper->getLogOrderContext($order)
                );
            } else {
                $order->addStatusHistoryComment(__($comment));
                $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                    'Order %s was finalized. Authorised status not set',
                    $order->getIncrementId()
                ));
            }
        }

        return $order;
    }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @return MagentoOrder
     */
    public function addProcessedStatusHistoryComment(MagentoOrder $order, Notification $notification): MagentoOrder
    {
        $order->addStatusHistoryComment(__(sprintf(
            '%s webhook notification w/amount %s %s was processed',
            $notification->getEventCode(),
            $notification->getAmountCurrency(),
            $this->dataHelper->originalAmount($notification->getAmountValue(), $notification->getAmountCurrency())
        )), false);

        return $order;
    }

    /**
     * Set status on authorisation
     *
     * @param MagentoOrder $order
     *
     * @return MagentoOrder
     */
    public function setPrePaymentAuthorized(MagentoOrder $order): MagentoOrder
    {
        $eventLabel = "payment_pre_authorized";
        $status = $this->configHelper->getConfigData(
            $eventLabel,
            'adyen_abstract',
            $order->getStoreId()
        );
        $possibleStates = Webhook::STATE_TRANSITION_MATRIX[$eventLabel];

        // only do this if status in configuration is set
        if (!empty($status)) {
            $order->setStatus($status);
            $order = $this->setState($order, $status, $possibleStates);

            $this->adyenLogger->addAdyenNotificationCronjob(
                'Order status is changed to Pre-authorised status, status is ' . $status
            );
        } else {
            $this->adyenLogger->addAdyenNotificationCronjob('No pre-authorised status is used so ignore');
        }

        return $order;
    }

    /**
     * @param MagentoOrder $order
     * @param $ignoreHasInvoice
     * @return MagentoOrder
     * @throws LocalizedException
     */
    public function holdCancelOrder(MagentoOrder $order, $ignoreHasInvoice): MagentoOrder
    {
        if (!$this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
            $this->adyenLogger->addAdyenNotificationCronjob(
                'Order cannot be canceled based on the plugin configuration'
            );
            return $order;
        }

        $orderStatus = $this->configHelper->getConfigData(
            'payment_cancelled',
            'adyen_abstract',
            $order->getStoreId()
        );

        // check if order has in invoice only cancel/hold if this is not the case
        if ($ignoreHasInvoice || !$order->hasInvoices()) {
            if ($orderStatus == MagentoOrder::STATE_HOLDED) {
                // Allow magento to hold order
                $order->setActionFlag(MagentoOrder::ACTION_FLAG_HOLD, true);

                if ($order->canHold()) {
                    $order->hold();
                    $order->addCommentToStatusHistory('Order held', $orderStatus);
                } else {
                    $this->adyenLogger->addAdyenNotificationCronjob('Order can not hold or is already on Hold');
                }
            } else {
                $this->adyenLogger->addAdyenNotificationCronjob('Test cancelled stat: ' . $orderStatus);
                // Allow magento to cancel order
                $order->setActionFlag(MagentoOrder::ACTION_FLAG_CANCEL, true);

                if ($order->canCancel()) {
                    $order->cancel();
                    $order->addCommentToStatusHistory('Order cancelled', $orderStatus ?? false);
                } else {
                    $this->adyenLogger->addAdyenNotificationCronjob('Order can not be canceled');
                }
            }
        } else {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                    'Order %s already has an invoice linked so it cannot be canceled', $order->getIncrementId()
            ));
        }

        return $order;
    }

    /**
     * Set order state, based on the passed status
     *
     * @param MagentoOrder $order
     * @param $status
     * @param $possibleStates
     * @return MagentoOrder
     */
    private function setState(MagentoOrder $order, $status, $possibleStates): MagentoOrder
    {
        // Loop over possible states, select first available status that fits this state
        foreach ($possibleStates as $state) {
            $statusObject = $this->orderStatusCollectionFactory->create()
                ->addFieldToFilter('main_table.status', $status)
                ->joinStates()
                ->addStateFilter($state)
                ->getFirstItem();

            if ($statusObject->getState() == $state) {
                // Exit function if fitting state is found
                $order->setState($statusObject->getState());
                $this->adyenLogger->addAdyenNotificationCronjob('State is changed to  ' . $statusObject->getState());

                return $order;
            }
        }

        $this->adyenLogger->addAdyenNotificationCronjob('No new state assigned, status should be connected to one of the following states: ' . json_encode($possibleStates));

        return $order;
    }

    /**
     * Set the order data member by fetching the entity from the database.
     * This should be moved out of this file in the future.
     * @param Notification $notification
     * @return false|\Magento\Sales\Api\Data\OrderInterface
     */
    public function fetchOrderByIncrementId(Notification $notification)
    {
        $incrementId = $notification->getMerchantReference();

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('increment_id', $incrementId, 'eq')
            ->create();

        $orderList = $this->orderRepository->getList($searchCriteria)->getItems();

        /** @var MagentoOrder $order */
        return reset($orderList);
    }
}
