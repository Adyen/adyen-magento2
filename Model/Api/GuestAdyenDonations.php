<?php
/**
 *
 * Adyen Payment Module
 *
 * Copyright (c) 2023 Adyen N.V.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Adyen <magento@adyen.com>
 */

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenDonationsInterface;
use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;


class GuestAdyenDonations implements GuestAdyenDonationsInterface
{
    private $checkoutSession;
    private $adyenDonationsModel;

    public function __construct(
        Session $checkoutSession,
        AdyenDonations $adyenDonationsModel
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->adyenDonationsModel = $adyenDonationsModel;
    }

    /**
     * @param string $payload
     * @param string $orderId
     * @return void
     * @throws LocalizedException
     */
    public function donate(string $payload, string $orderId): void
    {
        if ($this->checkoutSession->getLastOrderId() !== $orderId) {
            throw new LocalizedException(
                __("Donation failed!")
            );
        }

        $this->adyenDonationsModel->donate($payload);
    }
}
