<?php

namespace Adyen\Payment\Helper;

use Adyen\Model\Checkout\DonationCampaignsRequest;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Adyen\Payment\Helper\Data;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class DonationsHelper extends AbstractHelper
{
    private Data $adyenHelper;

    public function __construct(
        Context $context,
        Data $adyenHelper
    ) {
        parent::__construct($context);
        $this->adyenHelper = $adyenHelper;
    }

    /**
     * @throws LocalizedException
     */
    public function validatePayload(array $payloadData): void
    {
        if (empty($payloadData['merchantAccount']) || empty($payloadData['currency']) || empty($payloadData['locale'])) {
            throw new LocalizedException(__('Invalid donation campaigns request payload.'));
        }
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
            throw new LocalizedException(__('Error fetching donation campaigns: %1', $e->getMessage()));
        }
    }

    //Return the data of the first campaign only.
    public function formatCampaign(array $donationCampaignsResponse): array
    {
        $campaignList = $donationCampaignsResponse['donationCampaigns'] ?? [];

        if (empty($campaignList)) {
            return ['donationCampaigns' => []];
        }

        $firstCampaign = $campaignList[0];

        return [
            'donationCampaigns' => [[
                'nonprofitName' => $firstCampaign['nonprofitName'] ?? '',
                'description' => $firstCampaign['nonprofitDescription'] ?? '',
                'reference' => $firstCampaign['id'] ?? '',
                'nonprofitUrl' => $firstCampaign['nonprofitUrl'] ?? '',
                'logoUrl' => $firstCampaign['logoUrl'] ?? '',
                'bannerUrl' => $firstCampaign['bannerUrl'] ?? '',
                'termsAndConditionsUrl' => $firstCampaign['termsAndConditionsUrl'] ?? '',
                'donation' => $firstCampaign['donation'] ?? []
            ]]
        ];
    }

}
