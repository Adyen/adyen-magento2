<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Sales\Api\Data\OrderInterface;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Data;

class AdyenDonationCampaigns implements AdyenDonationCampaignsInterface
{
    private DonationsHelper $donationsHelper;
    private OrderRepository $orderRepository;
    private ChargedCurrency $chargedCurrency;
    private AdyenLogger $adyenLogger;
    private Config $configHelper;
    private Data $adyenHelper;

    public function __construct(
        DonationsHelper $donationsHelper,
        OrderRepository $orderRepository,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        Data $adyenHelper
    ) {
        $this->donationsHelper = $donationsHelper;
        $this->orderRepository = $orderRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * {@inheritdoc}
     */

    public function getCampaigns(int $orderId): string
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->adyenLogger->error(
                'Cannot fetch donation campaigns.Failed to load order with ID ' . $orderId . ': ' . $e->getMessage()
            );
            return 'null';
        }

        if (!$order->getEntityId()) {
            $this->adyenLogger->error("Order ID $orderId has no entity ID. Cannot fetch donation campaigns.");
            return 'null';
        }

        return $this->getCampaignData($order);
    }

    /**
     * @param OrderInterface $order
     * @return string
     */
    public function getCampaignData(OrderInterface $order): string
    {
        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');
        if (!$donationToken) {
            $this->adyenLogger->error('Missing donation token in payment additional information.');
            return 'null';
        }

        $payloadData = [];
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $currencyCode = $orderAmountCurrency->getCurrencyCode();

        //Creating payload
        $payloadData['currency'] = $currencyCode;
        $payloadData['merchantAccount'] = $this->configHelper->getMerchantAccount($order->getStoreId());
        $payloadData['locale'] = $this->adyenHelper->getCurrentLocaleCode($order->getStoreId());

        try {
            $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $order->getStoreId());
            $campaignId = $donationCampaignsResponse['donationCampaigns'][0]['id'];
            $this->donationsHelper->setDonationCampaignId($order, $campaignId);
            $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);
            return json_encode($campaignsData);
        } catch (\Exception $e) {
            $this->adyenLogger->error('Failed to fetch donation campaigns: ' . $e->getMessage());
            return 'null';
        }
    }

}
