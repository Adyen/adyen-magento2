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
use Adyen\Payment\Model\Notification;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

/**
 * Helper class for anything related to Case Management (Manual Review)
 *
 * Class AdyenOrderPayment
 * @package Adyen\Payment\Helper
 */
class CaseManagement
{
    const FRAUD_MANUAL_REVIEW = 'fraudManualReview';

    /**
     * @var AdyenLogger
     */
    private $adyenLogger;

    /**
     * @var Config
     */
    private $configHelper;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * CaseManagement constructor.
     *
     * @param AdyenLogger $adyenLogger
     * @param Config $configHelper
     * @param Serializer $serializer
     */
    public function __construct(
        AdyenLogger $adyenLogger,
        Config $configHelper,
        SerializerInterface $serializer
    ) {
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
    }

    /**
     * Based on the passed array, check if manual review is required
     *
     */
    public function requiresManualReview(Notification $notification): bool
    {
        $additionalData = !empty($notification->getAdditionalData()) ? $this->serializer->unserialize($notification->getAdditionalData()) : [];
        if (!array_key_exists(self::FRAUD_MANUAL_REVIEW, $additionalData)) {
            return false;
        }

        // Strict comparison to 'true' since it will be sent as a string
        if ($additionalData[self::FRAUD_MANUAL_REVIEW] === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Mark an order as pending manual review by adding a comment and also, update the status if the review status is set.
     *
     * @param Order $order
     * @param string $pspReference
     * @param bool $autoCapture
     * @return Order
     */
    public function markCaseAsPendingReview(Order $order, string $pspReference): Order
    {
        $manualReviewComment = sprintf(
            'Manual review required for order w/pspReference: %s. Please check the Adyen platform.',
            $pspReference
        );

        $reviewRequiredStatus = $this->configHelper->getFraudStatus(
            Config::XML_STATUS_FRAUD_MANUAL_REVIEW,
            $order->getStoreId()
        );

        if (!empty($reviewRequiredStatus)) {
            // Ensure that when setting the reviewRequiredStatus, the state will be new.
            if ($order->getState() !== Order::STATE_NEW) {
                $order->setState(Order::STATE_NEW);
            }
            $order->addStatusHistoryComment(__($manualReviewComment), $reviewRequiredStatus);
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Order %s is pending manual review. The following status will be set: %s',
                $order->getIncrementId(),
                $reviewRequiredStatus
            ), [
                'pspReference' => $pspReference,
                'merchantReference' => $order->getPayment()->getData('entity_id')
            ]);
        } else {
            $order->addStatusHistoryComment(__($manualReviewComment));
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Order %s is pending manual review. No status update was configured',
                $order->getIncrementId()
            ), [
                'pspReference' => $pspReference,
                'merchantReference' => $order->getPayment()->getData('entity_id')
            ]);
        }

        return $order;
    }

    /**
     * Mark a pending manual review order as accepted, by adding a comment and also update the status, if the review
     * accept status is set.
     *
     * @param Order $order
     * @param $comment
     * @return Order
     */
    public function markCaseAsAccepted(Order $order, $comment): Order
    {
        $reviewAcceptStatus = $this->configHelper->getFraudStatus(
            Config::XML_STATUS_FRAUD_MANUAL_REVIEW_ACCEPT,
            $order->getStoreId()
        );

        // Empty used to cater for empty string and null cases
        if (!empty($reviewAcceptStatus)) {
            $order->addStatusHistoryComment(__($comment), $reviewAcceptStatus);
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Created comment history for this notification linked to order %s with status update to: %s',
                $order->getIncrementId(),
                $reviewAcceptStatus
            ), [
                'pspReference' => $order->getPayment()->getData('adyen_psp_reference'),
                'merchantReference' => $order->getPayment()->getData('entity_id')
            ]);
        } else {
            $order->addStatusHistoryComment(__($comment));
            $this->adyenLogger->addAdyenNotification(sprintf(
                'Created comment history for this notification linked to order %s without any status update',
                $order->getIncrementId()
            ),
                array_merge(
                    $this->adyenLogger->getOrderContext($order),
                    ['pspReference' => $order->getPayment()->getData('adyen_psp_reference')]
                )
            );
        }

        return $order;
    }

    /**
     * Mark a pending manual review order as rejected, by adding a comment
     *
     * @param Order $order
     * @param $originalPspReference
     * @param string $action
     * @return Order
     */
    public function markCaseAsRejected(Order $order, $originalPspReference, string $action): Order
    {
        $order->addStatusHistoryComment(sprintf(
            'Manual review was rejected for order w/pspReference: %s. The order will be automatically %s.',
            $originalPspReference,
            $action
        ));

        return $order;
    }
}
