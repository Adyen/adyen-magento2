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
    private DonationsHelper $donationsHelper;
    private CartRepositoryInterface $quoteRepository;
    private OrderRepository $orderRepository;

    public function __construct(
        QuoteIdMaskFactory $quoteIdMaskFactory,
        DonationsHelper $donationsHelper,
        CartRepositoryInterface $quoteRepository,
        OrderRepository $orderRepository
    ) {
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->donationsHelper = $donationsHelper;
        $this->quoteRepository = $quoteRepository;
        $this->orderRepository = $orderRepository;
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

        $order = $this->orderRepository->getOrderByQuoteId($quoteId);

        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');


        if (!$donationToken) {
            throw new LocalizedException(__('Donation failed!'));
        }

        $payloadData = json_decode($payload, true);
        $this->donationsHelper->validatePayload($payloadData);

        $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $quote->getStoreId());
        $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);

        return json_encode($campaignsData);
    }
}
