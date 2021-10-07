<?php
/**
 *                       ######
 *                       ######
 * ############    ####( ######  #####. ######  ############   ############
 * #############  #####( ######  #####. ######  #############  #############
 *        ######  #####( ######  #####. ######  #####  ######  #####  ######
 * ###### ######  #####( ######  #####. ######  #####  #####   #####  ######
 * ###### ######  #####( ######  #####. ######  #####          #####  ######
 * #############  #############  #############  #############  #####  ######
 *  ############   ############  #############   ############  #####  ######
 *                                      ######
 *                               #############
 *                               ############
 *
 * Adyen Payment module (https://www.adyen.com/)
 *
 * Copyright (c) 2021 Adyen BV (https://www.adyen.com/)
 * See LICENSE.txt for license details.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper;

use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Adyen\Payment\Model\Order\Payment;
use Adyen\Payment\Model\Order\PaymentFactory;
use Adyen\Payment\Model\ResourceModel\Order\Payment as OrderPaymentResourceModel;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as AdyenOrderPaymentCollection;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Sales\Model\Order;

/**
 * Helper class for anything related to the adyen_order_payment entity
 *
 * Class AdyenOrderPayment
 * @package Adyen\Payment\Helper
 */
class AdyenOrderPayment extends AbstractHelper
{
    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    /**
     * @var AdyenOrderPaymentCollection
     */
    protected $adyenOrderPaymentCollection;

    /**
     * @var Data
     */
    protected $adyenDataHelper;

    /**
     * @var ChargedCurrency
     */
    protected $adyenChargedCurrencyHelper;

    /**
     * @var OrderPaymentResourceModel
     */
    protected $orderPaymentResourceModel;

    /**
     * @var Order\PaymentFactory
     */
    protected $adyenOrderPaymentFactory;

    /**
     * AdyenOrderPayment constructor.
     *
     * @param Context $context
     * @param AdyenLogger $adyenLogger
     * @param AdyenOrderPaymentCollection $adyenOrderPaymentCollection
     * @param Data $adyenDataHelper
     * @param ChargedCurrency $adyenChargedCurrencyHelper
     * @param OrderPaymentResourceModel $orderPaymentResourceModel
     * @param PaymentFactory $adyenOrderPaymentFactory
     */
    public function __construct(
        Context $context,
        AdyenLogger $adyenLogger,
        AdyenOrderPaymentCollection $adyenOrderPaymentCollection,
        Data $adyenDataHelper,
        ChargedCurrency $adyenChargedCurrencyHelper,
        OrderPaymentResourceModel $orderPaymentResourceModel,
        PaymentFactory $adyenOrderPaymentFactory
    ) {
        parent::__construct($context);
        $this->adyenLogger = $adyenLogger;
        $this->adyenOrderPaymentCollection = $adyenOrderPaymentCollection;
        $this->adyenDataHelper = $adyenDataHelper;
        $this->adyenChargedCurrencyHelper = $adyenChargedCurrencyHelper;
        $this->orderPaymentResourceModel = $orderPaymentResourceModel;
        $this->adyenOrderPaymentFactory = $adyenOrderPaymentFactory;
    }

    /**
     * Check if the total amount of the order has been authorised
     *
     * @param Order $order
     * @return bool
     */
    public function isTotalAmountAuthorized(Order $order): bool
    {
        // Get total amount currently authorised
        $queryResult = $this->adyenOrderPaymentCollection
            ->create()
            ->getTotalAmount($order->getPayment()->getEntityId());

        if ($queryResult && isset($queryResult[0]) && is_array($queryResult[0])) {
            $totalAmountAuthorized = $queryResult[0]['total_amount'];
        } else {
            $this->adyenLogger->error(
                sprintf('Unable to obtain the total amount authorized of order %s', $order->getIncrementId())
            );

            return false;
        }

        $orderTotal = $this->adyenChargedCurrencyHelper->getOrderAmountCurrency($order, false);
        $orderTotalAmount = $this->adyenDataHelper->formatAmount($orderTotal->getAmount(), $orderTotal->getCurrencyCode());
        $totalAmountAuthorized = $this->adyenDataHelper->formatAmount($totalAmountAuthorized, $orderTotal->getCurrencyCode());

        if ($totalAmountAuthorized === $orderTotalAmount) {
            return true;
        }

        return false;
    }

    /**
     * Check if ANY adyen_order_payment linked to this order requires a manual capture
     *
     * @param Order $order
     * @param string $status
     * @return bool
     */
    public function hasOrderPaymentWithCaptureStatus(Order $order, string $status): bool
    {
        $requireManualCapture = false;
        $payment = $order->getPayment();
        $adyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments($payment->getEntityId());

        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            if ($adyenOrderPayment[OrderPaymentInterface::CAPTURE_STATUS] === $status) {
                $requireManualCapture = true;
            }
        }

        return $requireManualCapture;
    }

    /**
     * Go trough the adyen_order_payment entries linked and get the amount that has been captured
     *
     * @param Order $order
     * @return int|null
     */
    public function getCapturedAmount(Order $order): ?int
    {
        $orderAmountCents = null;
        $paymentId = $order->getPayment()->getEntityId();
        // Get total amount currently captured
        $queryResult = $this->adyenOrderPaymentCollection
            ->create()
            ->getTotalAmount($paymentId, true);

        if ($queryResult && isset($queryResult[0]) && is_array($queryResult[0])) {
            $amount = $queryResult[0]['total_amount'];
            $orderAmountCents = $this->adyenDataHelper->formatAmount($amount, $order->getOrderCurrencyCode());
        }

        return $orderAmountCents;
    }

    /**
     * Create an entry in the adyen_order_payment table based on the passed notification
     *
     * @param Order $order
     * @param Notification $notification
     * @param $autoCapture
     * @return Payment|null
     */
    public function createAdyenOrderPayment(Order $order, Notification $notification, $autoCapture): ?Payment
    {
        $adyenOrderPayment = null;
        $payment = $order->getPayment();
        $amount = $this->adyenDataHelper->originalAmount($notification->getAmountValue(), $order->getBaseCurrencyCode());
        $captureStatus = $autoCapture ? Payment::CAPTURE_STATUS_AUTO_CAPTURE : Payment::CAPTURE_STATUS_NO_CAPTURE;
        $merchantReference = $notification->getMerchantReference();
        $pspReference = $notification->getPspreference();

        try {
            $date = new \DateTime();
            $adyenOrderPayment = $this->adyenOrderPaymentFactory->create();
            $adyenOrderPayment->setPspreference($pspReference);
            $adyenOrderPayment->setMerchantReference($merchantReference);
            $adyenOrderPayment->setPaymentId($payment->getId());
            $adyenOrderPayment->setPaymentMethod($notification->getPaymentMethod());
            $adyenOrderPayment->setCaptureStatus($captureStatus);
            $adyenOrderPayment->setAmount($amount);
            $adyenOrderPayment->setTotalRefunded(0);
            $adyenOrderPayment->setCreatedAt($date);
            $adyenOrderPayment->setUpdatedAt($date);
            $this->orderPaymentResourceModel->save($adyenOrderPayment);
        } catch (\Exception $e) {
            $this->adyenLogger->error(sprintf(
                'While processing a notification an exception occured. The payment has already been saved in the ' .
                'adyen_order_payment table but something went wrong later. Please check your logs for potential ' .
                'error messages regarding the merchant reference (order id): %s and PSP reference: %s. ' .
                'Exception message: %s',
                $merchantReference,
                $pspReference,
                $e->getMessage()
            ));
        }

        return $adyenOrderPayment;
    }

    /**
     * Set the capture_status of an adyen order payment to manually captured
     *
     * @param Order $order
     * @param Notification $notification
     * @return Payment|null
     *
     * @throws AlreadyExistsException
     */
    public function setCapturedAdyenOrderPayment(Order $order, Notification $notification)
    {
        $orderPayment = null;
        $originalReference = $notification->getOriginalReference();
        $paymentId = $order->getPayment()->getId();
        $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
            'Setting capture_status of adyen_order_payment with pspReference %s to Manual Capture',
            $originalReference
        ));

        $orderPaymentDetails = $this->orderPaymentResourceModel->getOrderPaymentDetails($originalReference, $paymentId);

        if (is_null($orderPaymentDetails) || !array_key_exists(OrderPaymentInterface::ENTITY_ID, $orderPaymentDetails)) {
            $this->adyenLogger->addAdyenNotificationCronjob(sprintf(
                'Unable to identify original auth with pspReference %s using capture with pspReference %s',
                $originalReference,
                $notification->getPspreference()
            ));
        } else {
            $orderPaymentFactory = $this->adyenOrderPaymentFactory->create();
            $orderPayment = $orderPaymentFactory->load($orderPaymentDetails['entity_id'], 'entity_id');
            $orderPayment->setCaptureStatus(Payment::CAPTURE_STATUS_MANUAL_CAPTURE);
            $this->orderPaymentResourceModel->save($orderPayment);
        }

        return $orderPayment;
    }
}
