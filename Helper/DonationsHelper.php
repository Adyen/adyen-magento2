<?php

namespace Adyen\Payment\Helper;

use Adyen\Model\Checkout\DonationCampaignsRequest;
use Adyen\Payment\Logger\AdyenLogger;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Model\Order;

class DonationsHelper extends AbstractHelper
{
    private Data $adyenHelper;

    /**
     * @var AdyenLogger
     */
    protected $adyenLogger;

    public function __construct(
        Context $context,
        Data $adyenHelper,
        AdyenLogger $adyenLogger
    ) {
        parent::__construct($context);
        $this->adyenHelper = $adyenHelper;
        $this->adyenLogger = $adyenLogger;
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function fetchDonationCampaigns(array $payloadData, int $storeId): array
    {
        $request = new DonationCampaignsRequest($payloadData);

        try {
            $client = $this->adyenHelper->initializeAdyenClient($storeId);
            $service = $this->adyenHelper->initializeDonationsApi($client);
            return $service->donationCampaigns($request)->toArray();
        } catch (\Adyen\AdyenException $e) {
            $this->adyenLogger->error('Error fetching donation campaigns', ['exception' => $e]);
            throw new LocalizedException(__('Unable to retrieve donation campaigns. Please try again later.'));
        }
    }

    //Return the data of the first campaign only.
    public function formatCampaign(array $donationCampaignsResponse): array
    {
        $campaignList = $donationCampaignsResponse['donationCampaigns'] ?? [];

        if (empty($campaignList)) {
            return [];
        }

        $firstCampaign = $campaignList[0];

        //Doing this workaround for now, Will be fixed with next API Library version
        //RoundUp works with donation.type and not with donation.donationType
        $firstCampaign['donation']['type'] = $firstCampaign['donation']['donationType'] ?? '';
        return [
                'nonprofitName' => $firstCampaign['nonprofitName'] ?? '',
                'nonprofitDescription' => $firstCampaign['nonprofitDescription'] ?? '',
                'nonprofitUrl' => $firstCampaign['nonprofitUrl'] ?? '',
                'logoUrl' => $firstCampaign['logoUrl'] ?? '',
                'bannerUrl' => $firstCampaign['bannerUrl'] ?? '',
                'termsAndConditionsUrl' => $firstCampaign['termsAndConditionsUrl'] ?? '',
                'donation' => $firstCampaign['donation'] ?? [],
                'causeName' => $firstCampaign['causeName'] ?? '',
            ];
    }

    public function setDonationCampaignId(Order $order, $campaignId): void
    {
        $order->getPayment()->setAdditionalInformation('donationCampaignId', $campaignId);
        $order->save();
    }

}
