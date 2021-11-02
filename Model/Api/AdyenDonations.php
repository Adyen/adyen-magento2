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
 * Adyen Payment Module
 *
 * Copyright (c) 2021 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationsInterface;
use Adyen\Util\Uuid;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Payment\Gateway\Command\CommandPoolInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;

class AdyenDonations implements AdyenDonationsInterface
{
    /**
     * @var CommandPoolInterface
     */
    private $commandPool;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var Order
     */
    private $order;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    public function __construct(CommandPoolInterface $commandPool, OrderFactory $orderFactory, Session $checkoutSession)
    {
        $this->commandPool = $commandPool;
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
    }

    /**
     * @inheritDoc
     *
     * @throws CommandException
     * @throws NotFoundException
     */
    public function donate($payload)
    {
        $payload = json_decode($payload, true);

        // Validate JSON that has just been parsed if it was in a valid format
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new LocalizedException(__('Donation failed: Invalid JSON request'));
        }

        $donationsCaptureCommand = $this->commandPool->get('capture');

        /**
         * @var Order
         */
        $order = $this->orderFactory->create()->load($this->checkoutSession->getLastOrderId());

        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');
        if (!$donationToken) {
            throw new LocalizedException(__('Donation failed: Invalid donationToken'));
        }
        $payload['donationToken'] = $donationToken;

        if (!empty($order->getPayment()->getAdditionalInformation('stateData'))) {
            $payload['paymentMethod'] = $order->getPayment()->getAdditionalInformation('stateData')['paymentMethod'];
        } else {
            $payload['paymentMethod'] = ['type' => 'scheme'];
        }

        $payload['pspReference'] = $order->getPayment()->getAdditionalInformation('pspReference');

        $customerId = $order->getCustomerId();
        if ($customerId) {
            $payload['shopperReference'] = str_pad($customerId, 3, '0', STR_PAD_LEFT);
        } else {
            $guestCustomerId = $order->getIncrementId() . Uuid::generateV4();
            $payload['shopperReference'] = $guestCustomerId;
        }

        return $donationsCaptureCommand->execute(['payment' => $payload]);
    }
}
