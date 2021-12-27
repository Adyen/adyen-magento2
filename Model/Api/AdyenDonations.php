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
use Magento\Framework\Serialize\Serializer\Json;
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
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var Json
     */
    private $jsonSerializer;

    public function __construct(
        CommandPoolInterface $commandPool,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        Json $jsonSerializer
    ) {
        $this->commandPool = $commandPool;
        $this->orderFactory = $orderFactory;
        $this->checkoutSession = $checkoutSession;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @inheritDoc
     *
     * @throws CommandException|NotFoundException|LocalizedException|\InvalidArgumentException
     */
    public function donate($payload)
    {
        $payload = $this->jsonSerializer->unserialize($payload);
        /** @var Order */
        $order = $this->orderFactory->create()->load($this->checkoutSession->getLastOrderId());

        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');
        if (!$donationToken) {
            throw new LocalizedException(__('Donation failed: Invalid donationToken'));
        }
        $payload['donationToken'] = $donationToken;
        $payload['donationOriginalPspReference'] = $order->getPayment()->getAdditionalInformation('pspReference');

        $customerId = $order->getCustomerId();
        if ($customerId) {
            $payload['shopperReference'] = str_pad($customerId, 3, '0', STR_PAD_LEFT);
        } else {
            $guestCustomerId = $order->getIncrementId() . Uuid::generateV4();
            $payload['shopperReference'] = $guestCustomerId;
        }

        $donationsCaptureCommand = $this->commandPool->get('capture');
        return $donationsCaptureCommand->execute(['payment' => $payload]);
    }
}
