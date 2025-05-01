<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\DonationsHelper;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;

class GuestAdyenDonationCampaigns implements GuestAdyenDonationCampaignsInterface
{
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private DonationsHelper $donationsHelper;
    private OrderCollectionFactory $orderCollectionFactory;
    private CartRepositoryInterface $quoteRepository;

    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        DonationsHelper $donationsHelper,
        OrderCollectionFactory $orderCollectionFactory,
        CartRepositoryInterface $quoteRepository,
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->donationsHelper = $donationsHelper;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->quoteRepository = $quoteRepository;
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

        $quote = $this->quoteRepository->get($quoteId);

        $payloadData = json_decode($payload, true);
        $this->donationsHelper->validatePayload($payloadData);

        $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $quote->getStoreId());
        $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);

        return json_encode($campaignsData);
    }
}
