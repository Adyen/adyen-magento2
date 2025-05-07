<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\DonationsHelper;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;

class AdyenDonationCampaigns implements AdyenDonationCampaignsInterface
{
    private DonationsHelper $donationsHelper;
    private OrderRepository $orderRepository;
    private ChargedCurrency $chargedCurrency;

    public function __construct(
        DonationsHelper $donationsHelper,
        OrderRepository $orderRepository,
        ChargedCurrency $chargedCurrency
    ) {
        $this->donationsHelper = $donationsHelper;
        $this->orderRepository = $orderRepository;
        $this->chargedCurrency = $chargedCurrency;
    }

    /**
     * {@inheritdoc}
     */

    public function getCampaigns(int $orderId, string $payload): string
    {
        $order = $this->orderRepository->get($orderId);

        if (!$order->getEntityId()) {
            throw new LocalizedException(__('Donation failed!'));
        }

        return $this->getCampaignData($order, $payload);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCampaignData(OrderInterface $order, string $payload): string
    {
        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');

        if (!$donationToken) {
            throw new LocalizedException(__('Donation failed!'));
        }

        $payloadData = json_decode($payload, true);

        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $currencyCode = $orderAmountCurrency->getCurrencyCode();
        if ($payloadData['currency'] !== $currencyCode) {
            throw new LocalizedException(__('Donation failed!'));
        }

        $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $order->getStoreId());
        $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);

        return json_encode($campaignsData);
    }

}
