<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2022 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Helper\Webhook;

use Adyen\Payment\Api\CleanupAdditionalInformationInterface;
use Adyen\Payment\Api\Data\OrderPaymentInterface;
use Adyen\Payment\Helper\AdyenOrderPayment;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\Order as OrderHelper;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Model\Notification;
use Magento\Sales\Model\Order as MagentoOrder;
use Adyen\Payment\Model\ResourceModel\Order\Payment\CollectionFactory as OrderPaymentCollectionFactory;
use Magento\Framework\Serialize\SerializerInterface;

class OrderClosedWebhookHandler implements WebhookHandlerInterface
{
    /**
     * @param AdyenOrderPayment $adyenOrderPaymentHelper
     * @param OrderHelper $orderHelper
     * @param Config $configHelper
     * @param OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory
     * @param AdyenLogger $adyenLogger
     * @param SerializerInterface $serializer
     * @param CleanupAdditionalInformationInterface $cleanupAdditionalInformation
     */
    public function __construct(
        private readonly AdyenOrderPayment $adyenOrderPaymentHelper,
        private readonly OrderHelper $orderHelper,
        private readonly Config $configHelper,
        private readonly OrderPaymentCollectionFactory $adyenOrderPaymentCollectionFactory,
        private readonly AdyenLogger $adyenLogger,
        private readonly SerializerInterface $serializer,
        private readonly CleanupAdditionalInformationInterface $cleanupAdditionalInformation
    ) { }

    /**
     * @param MagentoOrder $order
     * @param Notification $notification
     * @param string $transitionState
     * @return MagentoOrder
     * @throws \Exception
     */
    public function handleWebhook(
        MagentoOrder $order,
        Notification $notification,
        string $transitionState
    ): MagentoOrder {
        $additionalData = $notification->getAdditionalData();
        if (!empty($additionalData)) {
            $additionalData = $this->serializer->unserialize($additionalData);
        }

        if ($notification->isSuccessful()) {
            foreach ($additionalData as $key => $value) {
                // Check if the key matches the pattern "order-X-pspReference"
                if (preg_match('/^order-(\d+)-pspReference$/', $key, $matches)) {
                    $orderIndex = (int)$matches[1];
                    $pspReference = $value;
                    $sortValue = $orderIndex;

                    // Retrieve adyen_order_payment for this pspReference
                    $adyenOrderPayment = $this->adyenOrderPaymentCollectionFactory->create()
                        ->addFieldToFilter('pspreference', $pspReference)
                        ->getFirstItem();

                    if ($adyenOrderPayment->getId()) {
                        // Update the status with the order index
                        $adyenOrderPayment->setSortOrder($sortValue);
                        $adyenOrderPayment->save();

                        $this->adyenLogger->addAdyenNotification(
                            sprintf("Updated adyen_order_payment with order status %d for pspReference %s", $sortValue, $pspReference),
                            [
                                'pspReference' => $pspReference,
                                'status' => $sortValue,
                                'merchantReference' => $notification->getMerchantReference()
                            ]
                        );
                    } else {
                        // Log if no matching record was found for the given pspReference
                        $this->adyenLogger->addAdyenNotification(
                            sprintf("No adyen_order_payment record found for pspReference %s", $pspReference),
                            [
                                'pspReference' => $pspReference,
                                'merchantReference' => $notification->getMerchantReference()
                            ]
                        );
                    }
                }
            }

            $order->addCommentToStatusHistory(__('This order has been successfully completed.'));
        } else {
            /** @var OrderPaymentInterface $orderPayment */
            $capturedAdyenOrderPayments = $this->adyenOrderPaymentCollectionFactory
                ->create()
                ->addFieldToFilter('payment_id', $order->getPayment()->getEntityId())
                ->addFieldToFilter('capture_status', [
                    'in' => [
                        OrderPaymentInterface::CAPTURE_STATUS_AUTO_CAPTURE,
                        OrderPaymentInterface::CAPTURE_STATUS_MANUAL_CAPTURE
                    ]
                ])
                ->getItems();

            if (!empty($capturedAdyenOrderPayments)) {
                /*
                 * Update adyen_order_payment table to reflect the refunds on the Adyen side.
                 * It's not possible to create a credit memo.
                 */
                /* @var $adyenOrderPayment OrderPaymentInterface */
                foreach ($capturedAdyenOrderPayments as $adyenOrderPayment) {
                    $this->adyenOrderPaymentHelper->refundFullyAdyenOrderPayment($adyenOrderPayment);
                }

                $order->addCommentToStatusHistory(__('All the funds captured/settled will be refunded by Adyen.'));
                $this->adyenLogger->addAdyenNotification(
                    'All the funds captured/settled will be refunded by Adyen.',
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
            }

            if ($order->canCancel() && $this->configHelper->getNotificationsCanCancel($order->getStoreId())) {
                $this->orderHelper->holdCancelOrder($order, true);

                $this->adyenLogger->addAdyenNotification(
                    'This order has been cancelled by the ORDER_CLOSED notification.',
                    [
                        'pspReference' => $notification->getPspreference(),
                        'merchantReference' => $notification->getMerchantReference()
                    ]
                );
            }

            // Clean-up the data temporarily stored in `additional_information`
            $this->cleanupAdditionalInformation->execute($order->getPayment());
        }

        return $order;
    }
}
