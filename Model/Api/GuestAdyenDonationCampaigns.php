<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;

class GuestAdyenDonationCampaigns implements GuestAdyenDonationCampaignsInterface
{
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private AdyenDonationCampaigns $adyenDonationCampaigns;
    private OrderRepository $orderRepository;

    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        OrderRepository $orderRepository,
        AdyenDonationCampaigns $adyenDonationCampaigns
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->orderRepository = $orderRepository;
        $this->adyenDonationCampaigns = $adyenDonationCampaigns;
    }

    /**
     * {@inheritdoc}
     */
    public function getCampaigns(string $cartId, string $payload): string
    {
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');
        $quoteId = $quoteIdMask->getQuoteId();

        if (!$quoteId) {
            throw new LocalizedException(__('Invalid cart ID.'));
        }

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        return $this->adyenDonationCampaigns->getCampaignData($order, $payload);
    }
}
