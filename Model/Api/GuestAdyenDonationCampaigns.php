<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\GuestAdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\DonationsHelper;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Framework\Exception\LocalizedException;

class GuestAdyenDonationCampaigns implements GuestAdyenDonationCampaignsInterface
{
    private QuoteIdMaskFactory $quoteIdMaskFactory;
    private DonationsHelper $donationsHelper;
    private OrderCollectionFactory $orderCollectionFactory;

    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        DonationsHelper $donationsHelper,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->donationsHelper = $donationsHelper;
        $this->orderCollectionFactory = $orderCollectionFactory;
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

        // Fetch the latest order by quote ID
        $order = $this->orderCollectionFactory->create()
            ->addFieldToFilter('quote_id', $quoteId)
            ->setOrder('entity_id', 'DESC')
            ->getFirstItem();

        if (!$order->getEntityId()) {
            throw new LocalizedException(__('Order not found for this cart.'));
        }

        $payloadData = json_decode($payload, true);
        $this->donationsHelper->validatePayload($payloadData);

        $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $order->getStoreId());
        $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);

        return json_encode($campaignsData);
    }
}
