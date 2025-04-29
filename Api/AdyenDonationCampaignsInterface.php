<?php

namespace Adyen\Payment\Api;

use Magento\Framework\Exception\LocalizedException;

interface AdyenDonationCampaignsInterface
{
    /**
     * Retrieve donation campaigns for the current customer's cart
     *
     * @param int $orderId
     * @param string $payload
     * @return string
     * @throws LocalizedException
     */
    public function getCampaigns(int $orderId, string $payload): string;
}
