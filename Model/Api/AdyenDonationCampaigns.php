<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\DonationsHelper;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Payment\Model\Sales\OrderRepository;

class AdyenDonationCampaigns implements AdyenDonationCampaignsInterface
{
    private DonationsHelper $donationsHelper;
    private OrderRepository $orderRepository;

    public function __construct(
        DonationsHelper $donationsHelper,
        OrderRepository $orderRepository,
    ) {
        $this->donationsHelper = $donationsHelper;
        $this->orderRepository = $orderRepository;
    }

    /**
     * {@inheritdoc}
     */

    public function getCampaigns(int $orderId, string $payload): string
    {
        $order = $this->orderRepository->get($orderId);

        if (!$order->getEntityId()) {
            throw new LocalizedException(__('Order not found.'));
        }

        $payloadData = json_decode($payload, true);
        $this->donationsHelper->validatePayload($payloadData);

        $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $order->getStoreId());
        $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);

        return json_encode($campaignsData);
    }

}
