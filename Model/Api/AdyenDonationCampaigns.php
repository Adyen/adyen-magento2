<?php

namespace Adyen\Payment\Model\Api;

use Adyen\Payment\Api\AdyenDonationCampaignsInterface;
use Adyen\Payment\Helper\ChargedCurrency;
use Adyen\Payment\Helper\Config;
use Adyen\Payment\Helper\DonationsHelper;
use Adyen\Payment\Helper\Locale;
use Adyen\Payment\Model\Sales\OrderRepository;
use Magento\Framework\Exception\LocalizedException;
use Adyen\Payment\Logger\AdyenLogger;
use Adyen\Payment\Helper\Data;
use Magento\Sales\Model\Order;

class AdyenDonationCampaigns implements AdyenDonationCampaignsInterface
{
    private DonationsHelper $donationsHelper;
    private OrderRepository $orderRepository;
    private ChargedCurrency $chargedCurrency;
    private AdyenLogger $adyenLogger;
    private Config $configHelper;
    private Locale $localeHelper;

    public function __construct(
        DonationsHelper $donationsHelper,
        OrderRepository $orderRepository,
        ChargedCurrency $chargedCurrency,
        AdyenLogger $adyenLogger,
        Config $configHelper,
        Locale $localeHelper
    ) {
        $this->donationsHelper = $donationsHelper;
        $this->orderRepository = $orderRepository;
        $this->chargedCurrency = $chargedCurrency;
        $this->adyenLogger = $adyenLogger;
        $this->configHelper = $configHelper;
        $this->localeHelper = $localeHelper;
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */

    public function getCampaigns(int $orderId): string
    {
        try {
            $order = $this->orderRepository->get($orderId);
        } catch (\Exception $e) {
            $this->adyenLogger->error(
                'Cannot fetch donation campaigns.Failed to load order with ID ' . $orderId . ': ' . $e->getMessage()
            );
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }

        if (!$order->getEntityId()) {
            $this->adyenLogger->error("Order ID $orderId has no entity ID. Cannot fetch donation campaigns.");
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }

        return $this->getCampaignData($order);
    }

    /**
     * @param Order $order
     * @return string
     * @throws LocalizedException
     */
    public function getCampaignData(Order $order): string
    {
        $donationToken = $order->getPayment()->getAdditionalInformation('donationToken');
        if (!$donationToken) {
            $this->adyenLogger->error('Missing donation token in payment additional information.');
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }

        $payloadData = [];
        $orderAmountCurrency = $this->chargedCurrency->getOrderAmountCurrency($order, false);
        $currencyCode = $orderAmountCurrency->getCurrencyCode();

        //Creating payload
        $payloadData['currency'] = $currencyCode;
        $payloadData['merchantAccount'] = $this->configHelper->getMerchantAccount($order->getStoreId());
        $payloadData['locale'] = $this->localeHelper->getCurrentLocaleCode($order->getStoreId());

        try {
            $donationCampaignsResponse = $this->donationsHelper->fetchDonationCampaigns($payloadData, $order->getStoreId());
            $campaignId = $donationCampaignsResponse['donationCampaigns'][0]['id'];
            $this->donationsHelper->setDonationCampaignId($order, $campaignId);
            $campaignsData = $this->donationsHelper->formatCampaign($donationCampaignsResponse);
            return json_encode($campaignsData);
        } catch (\Exception $e) {
            $this->adyenLogger->error('Failed to fetch donation campaigns: ' . $e->getMessage());
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }
    }

}
