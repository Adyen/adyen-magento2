<?php

namespace Adyen\Payment\Api;

use Magento\Framework\Exception\LocalizedException;

interface GuestAdyenDonationCampaignsInterface
{
    /**
     * Get donation campaigns for a guest cart
     *
     * @param string $cartId Masked cart ID
     * @param string $payload
     * @return string
     * @throws LocalizedException
     */
    public function getCampaigns(string $cartId, string $payload): string;
}
