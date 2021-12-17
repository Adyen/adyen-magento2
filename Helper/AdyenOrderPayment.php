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
     * Add the amount to the total_captured field of the adyen_order_payment and update the status if necessary
     *
     * @param Payment $adyenOrderPayment
     * @param float $amount
     * @return Payment
     * @throws AlreadyExistsException
     */
    public function updatePaymentTotalCaptured(Payment $adyenOrderPayment, float $amount): Payment
    {
        $newTotalCaptured = $adyenOrderPayment->getTotalCaptured() + $amount;
        $adyenOrderPayment->setTotalCaptured($newTotalCaptured);
        $this->orderPaymentResourceModel->save($adyenOrderPayment);

        return $adyenOrderPayment;
    }

    /**
     * Refresh the capture_status of the adyen_order_payment by comparing the captured amount and the full amount
     *
     * @param Payment $adyenOrderPayment
     * @param string $currency
     * @return Payment
     * @throws AlreadyExistsException
     */
    public function refreshPaymentCaptureStatus(Payment $adyenOrderPayment, string $currency): Payment
    {
        $this->adyenLogger->addAdyenNotificationCronjob('Currency: ' . $currency);
        $totalCapturedCents = $this->adyenDataHelper->formatAmount($adyenOrderPayment->getTotalCaptured(), $currency);
        $amountCents = $this->adyenDataHelper->formatAmount($adyenOrderPayment->getAmount(), $currency);

        if ($totalCapturedCents < $amountCents) {
            $captureStatus = OrderPaymentInterface::CAPTURE_STATUS_PARTIAL_CAPTURE;
        } else {
            $captureStatus = OrderPaymentInterface::CAPTURE_STATUS_MANUAL_CAPTURE;
        }

        $adyenOrderPayment->setCaptureStatus($captureStatus);
        $this->orderPaymentResourceModel->save($adyenOrderPayment);

        return $adyenOrderPayment;
    }

    /**
     * Check if the full amount of the order has been finalized (either Manually or Automatically)
     *
     * @param Order $order
     * @return bool
     */
    public function isFullAmountFinalized(Order $order): bool
    {
        $payment = $order->getPayment();
        $finalizedAdyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId(),
            [OrderPaymentInterface::CAPTURE_STATUS_MANUAL_CAPTURE, OrderPaymentInterface::CAPTURE_STATUS_AUTO_CAPTURE]
        );

        return $this->compareAdyenOrderPaymentsAmount($order, $finalizedAdyenOrderPayments);
    }

    /**
     * Check if the full amount of the order has been authorized
     *
     * @param Order $order
     * @return bool
     */
    public function isFullAmountAuthorized(Order $order): bool
    {
        $payment = $order->getPayment();
        $authorisedAdyenOrderPayments = $this->orderPaymentResourceModel->getLinkedAdyenOrderPayments(
            $payment->getEntityId(),
            [OrderPaymentInterface::CAPTURE_STATUS_NO_CAPTURE]
        );

        return $this->compareAdyenOrderPaymentsAmount($order, $authorisedAdyenOrderPayments);
    }

    /**
     * Create an entry in the adyen_order_payment table based on the passed notification
     *
     * @param Order $order
     * @param Notification $notification
     * @param bool $autoCapture
     * @return Payment|null
     */
    public function createAdyenOrderPayment(Order $order, Notification $notification, bool $autoCapture): ?Payment
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
     * Compare the total of the passed adyen order payments to the order grand total
     *
     * @param Order $order
     * @param array $adyenOrderPayments
     * @return bool
     */
    private function compareAdyenOrderPaymentsAmount(Order $order, array $adyenOrderPayments): bool
    {
        $adyenOrderPaymentsTotal = 0;
        foreach ($adyenOrderPayments as $adyenOrderPayment) {
            $adyenOrderPaymentsTotal += $adyenOrderPayment[OrderPaymentInterface::AMOUNT];
        }

        $adyenOrderPaymentsTotalCents = $this->adyenDataHelper->formatAmount($adyenOrderPaymentsTotal, $order->getOrderCurrencyCode());
        $orderAmountCents = $this->adyenDataHelper->formatAmount($order->getGrandTotal(), $order->getOrderCurrencyCode());

        return $adyenOrderPaymentsTotalCents === $orderAmountCents;
    }
}
